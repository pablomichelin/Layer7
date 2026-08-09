/*
 * TLS acceptor + SNI policy + optional localhost upstream.
 * Lab (.54) via LAYER7_TLSPROXY_LAB=1; produto via LAYER7_TLSPROXY_PRODUCT=1.
 * mitm_effective_claim always false aqui. Sem payload em disco.
 */

#include "tls_lab.h"
#include "ipc.h"

#include <arpa/inet.h>
#include <ctype.h>
#include <errno.h>
#include <fcntl.h>
#include <netinet/in.h>
#include <signal.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <strings.h>
#include <sys/socket.h>
#include <unistd.h>

#ifdef __linux__
#include <linux/netfilter_ipv4.h>
#include <netinet/in.h>
#endif

#include <openssl/err.h>
#include <openssl/evp.h>
#include <openssl/pem.h>
#include <openssl/ssl.h>
#include <openssl/x509.h>
#include <openssl/x509v3.h>
#if !defined(OPENSSL_VERSION_NUMBER) || OPENSSL_VERSION_NUMBER < 0x30000000L
#include <openssl/bn.h>
#include <openssl/rsa.h>
#endif
#include <time.h>

#define L7_POLICY_MAX 64
#define L7_SNI_MAX 253
#define L7_BUF 8192
#define L7_LEAF_CACHE 32

/*
 * Gate D1: se --cert/--key forem uma CA (CA:TRUE), o peer TLS e um leaf
 * mintado por SNI (EKU serverAuth). Nunca apresentar a CA como peer.
 * Se --cert for leaf estatico (PoC lab), comportamento legado.
 */
struct l7_leaf_ent {
	char sni[L7_SNI_MAX + 1];
	X509 *cert;
};

static X509 *l7_ca_cert;
static EVP_PKEY *l7_ca_key;
static EVP_PKEY *l7_leaf_key;
static int l7_mint_mode;
static struct l7_leaf_ent l7_leaf_cache[L7_LEAF_CACHE];
static int l7_nleaf;
static long l7_leaf_serial;

enum l7_verdict {
	L7_ALLOW = 0,
	L7_BYPASS = 1,
	L7_BLOCK = 2
};

static volatile sig_atomic_t l7_tls_stop;
static int l7_tls_allow_any;
static int l7_tls_transparent;
static int l7_tls_product;
static char l7_up_host[64];
static int l7_up_port;

static char l7_bypass[L7_POLICY_MAX][L7_SNI_MAX + 1];
static int l7_nbypass;
static char l7_block[L7_POLICY_MAX][L7_SNI_MAX + 1];
static int l7_nblock;

void
l7_tls_set_allow_any(int v)
{
	l7_tls_allow_any = v;
}

void
l7_tls_set_transparent(int v)
{
	l7_tls_transparent = v;
}

void
l7_tls_set_product(int v)
{
	l7_tls_product = v ? 1 : 0;
}

int
l7_tls_product_ok(void)
{
	const char *e;

	if (l7_tls_product)
		return 1;
	e = getenv("LAYER7_TLSPROXY_PRODUCT");
	return (e != NULL && strcmp(e, "1") == 0);
}

void
l7_tls_set_upstream(const char *host, int port)
{
	if (host == NULL || port <= 0 || port > 65535) {
		l7_up_host[0] = '\0';
		l7_up_port = 0;
		return;
	}
	/* Fail-closed: upstream only on loopback in PoC-4. */
	if (strcmp(host, "127.0.0.1") != 0 && strcmp(host, "::1") != 0) {
		fprintf(stderr,
		    "layer7-tlsproxy: refusing upstream %s "
		    "(PoC-4 allows only 127.0.0.1)\n",
		    host);
		l7_up_host[0] = '\0';
		l7_up_port = 0;
		return;
	}
	strncpy(l7_up_host, host, sizeof(l7_up_host) - 1);
	l7_up_host[sizeof(l7_up_host) - 1] = '\0';
	l7_up_port = port;
}

static void
sni_lower(char *s)
{
	for (; *s; s++)
		*s = (char)tolower((unsigned char)*s);
}

static int
sni_valid(const char *sni)
{
	size_t n;

	if (sni == NULL || sni[0] == '\0')
		return 0;
	n = strlen(sni);
	if (n > L7_SNI_MAX)
		return 0;
	if (strchr(sni, ' ') != NULL || strchr(sni, '/') != NULL)
		return 0;
	return 1;
}

int
l7_tls_policy_add_bypass(const char *sni)
{
	if (!sni_valid(sni) || l7_nbypass >= L7_POLICY_MAX)
		return -1;
	strncpy(l7_bypass[l7_nbypass], sni, L7_SNI_MAX);
	l7_bypass[l7_nbypass][L7_SNI_MAX] = '\0';
	sni_lower(l7_bypass[l7_nbypass]);
	l7_nbypass++;
	return 0;
}

int
l7_tls_policy_add_block(const char *sni)
{
	if (!sni_valid(sni) || l7_nblock >= L7_POLICY_MAX)
		return -1;
	strncpy(l7_block[l7_nblock], sni, L7_SNI_MAX);
	l7_block[l7_nblock][L7_SNI_MAX] = '\0';
	sni_lower(l7_block[l7_nblock]);
	l7_nblock++;
	return 0;
}

static int
sni_in_list(const char *sni, char list[][L7_SNI_MAX + 1], int n)
{
	int i;

	for (i = 0; i < n; i++) {
		if (strcmp(sni, list[i]) == 0)
			return 1;
	}
	return 0;
}

static enum l7_verdict
decide_sni(const char *sni_in)
{
	char sni[L7_SNI_MAX + 1];

	if (sni_in == NULL || sni_in[0] == '\0')
		return L7_ALLOW;
	strncpy(sni, sni_in, L7_SNI_MAX);
	sni[L7_SNI_MAX] = '\0';
	sni_lower(sni);
	if (sni_in_list(sni, l7_block, l7_nblock))
		return L7_BLOCK;
	if (sni_in_list(sni, l7_bypass, l7_nbypass))
		return L7_BYPASS;
	return L7_ALLOW;
}

static void
log_original_dst(int cfd)
{
#ifdef __linux__
	struct sockaddr_in orig;
	socklen_t olen = sizeof(orig);
	char ip[64];

	if (!l7_tls_transparent)
		return;
	memset(&orig, 0, sizeof(orig));
	if (getsockopt(cfd, SOL_IP, SO_ORIGINAL_DST, &orig, &olen) != 0) {
		fprintf(stderr,
		    "layer7-tlsproxy: SO_ORIGINAL_DST unavailable (%s)\n",
		    strerror(errno));
		return;
	}
	if (inet_ntop(AF_INET, &orig.sin_addr, ip, sizeof(ip)) == NULL)
		return;
	fprintf(stderr,
	    "layer7-tlsproxy: transparent orig_dst=%s:%u mitm_effective=false\n",
	    ip, (unsigned)ntohs(orig.sin_port));
#else
	(void)cfd;
	if (l7_tls_transparent)
		fprintf(stderr,
		    "layer7-tlsproxy: --lab-transparent is Linux-only\n");
#endif
}

static void
on_sig(int sig)
{
	(void)sig;
	l7_tls_stop = 1;
}

static int
host_is_loopback(const char *host)
{
	return (host != NULL &&
	    (strcmp(host, "127.0.0.1") == 0 || strcmp(host, "::1") == 0));
}

static int
host_allowed(const char *host)
{
	if (host == NULL || host[0] == '\0')
		return 0;
	if (host_is_loopback(host))
		return 1;
	/* Produto 20.10b: listen selectivo — só loopback. */
	if (l7_tls_product_ok()) {
		fprintf(stderr,
		    "layer7-tlsproxy: product mode refuses bind %s "
		    "(loopback only; selective listen).\n",
		    host);
		return 0;
	}
	if (l7_tls_allow_any)
		return 1;
	fprintf(stderr,
	    "layer7-tlsproxy: refusing bind %s — default is 127.0.0.1 "
	    "(--lab-allow-any only on disposable lab .54).\n",
	    host);
	return 0;
}

static void
http_reply(SSL *ssl, int code, const char *ctype, const char *body)
{
	char hdr[512];
	const char *reason = (code == 200) ? "OK" :
	    (code == 403) ? "Forbidden" : "OK";
	int n;

	n = snprintf(hdr, sizeof(hdr),
	    "HTTP/1.1 %d %s\r\n"
	    "Content-Type: %s\r\n"
	    "Connection: close\r\n"
	    "Cache-Control: no-store\r\n"
	    "Content-Length: %zu\r\n"
	    "\r\n",
	    code, reason, ctype, strlen(body));
	if (n > 0)
		SSL_write(ssl, hdr, n);
	SSL_write(ssl, body, (int)strlen(body));
}

static int
write_full(int fd, const char *buf, size_t n)
{
	size_t off = 0;

	while (off < n) {
		ssize_t w = write(fd, buf + off, n - off);

		if (w < 0) {
			if (errno == EINTR)
				continue;
			return -1;
		}
		if (w == 0)
			return -1;
		off += (size_t)w;
	}
	return 0;
}

/* PoC-4: one-shot HTTP reverse-proxy to loopback upstream. No body logging. */
static int
proxy_allow_upstream(SSL *ssl, const char *req, int req_len)
{
	struct sockaddr_in addr;
	char buf[L7_BUF];
	int ufd = -1;
	ssize_t n;
	int rc = -1;

	if (l7_up_port <= 0 || l7_up_host[0] == '\0')
		return -1;

	ufd = socket(AF_INET, SOCK_STREAM, 0);
	if (ufd < 0)
		return -1;
	memset(&addr, 0, sizeof(addr));
	addr.sin_family = AF_INET;
	addr.sin_port = htons((uint16_t)l7_up_port);
	if (inet_pton(AF_INET, l7_up_host, &addr.sin_addr) != 1)
		goto out;
	if (connect(ufd, (struct sockaddr *)&addr, sizeof(addr)) != 0)
		goto out;
	if (write_full(ufd, req, (size_t)req_len) != 0)
		goto out;
	shutdown(ufd, SHUT_WR);
	while ((n = read(ufd, buf, sizeof(buf))) > 0) {
		if (SSL_write(ssl, buf, (int)n) <= 0)
			goto out;
	}
	rc = 0;
out:
	if (ufd >= 0)
		close(ufd);
	return rc;
}

static void
serve_by_verdict(SSL *ssl, enum l7_verdict v, const char *sni)
{
	char req[L7_BUF];
	char json[512];
	const char *sni_show = (sni && sni[0]) ? sni : "";
	int nread;

	nread = SSL_read(ssl, req, (int)sizeof(req) - 1);
	if (nread < 0)
		nread = 0;
	req[nread] = '\0';

	if (v == L7_BLOCK) {
		static const char *page_lab =
		    "<!DOCTYPE html><html><head><meta charset=\"utf-8\">"
		    "<title>Layer7 — acesso bloqueado</title></head>"
		    "<body style=\"font-family:sans-serif;max-width:40rem;margin:3rem auto\">"
		    "<h1>Acesso bloqueado</h1>"
		    "<p>Este destino foi bloqueado pela política Layer7 (PoC lab).</p>"
		    "<p><small>mitm_effective=false · lab only · sem persistência de payload</small></p>"
		    "</body></html>\n";
		static const char *page_prod =
		    "<!DOCTYPE html><html><head><meta charset=\"utf-8\">"
		    "<title>Layer7 — acesso bloqueado</title></head>"
		    "<body style=\"font-family:sans-serif;max-width:40rem;margin:3rem auto\">"
		    "<h1>Acesso bloqueado</h1>"
		    "<p>Este destino foi bloqueado pela política Layer7.</p>"
		    "<p><small>Block page HTTPS (20.10b) · sem persistência de payload · Squid rejeitado</small></p>"
		    "</body></html>\n";
		http_reply(ssl, 403, "text/html; charset=utf-8",
		    l7_tls_product_ok() ? page_prod : page_lab);
		return;
	}

	if (v == L7_BYPASS) {
		snprintf(json, sizeof(json),
		    "{\"service\":\"layer7-tlsproxy\",\"poc\":\"4\","
		    "\"verdict\":\"bypass\",\"sni\":\"%s\","
		    "\"mitm_effective\":false,\"intercept\":false,"
		    "\"note\":\"lab-sim: policy bypass (no upstream)\"}\n",
		    sni_show);
		http_reply(ssl, 200, "application/json", json);
		return;
	}

	/* ALLOW */
	if (l7_up_port > 0 && nread > 0) {
		if (proxy_allow_upstream(ssl, req, nread) == 0)
			return;
		snprintf(json, sizeof(json),
		    "{\"service\":\"layer7-tlsproxy\",\"poc\":\"4\","
		    "\"verdict\":\"allow\",\"sni\":\"%s\","
		    "\"mitm_effective\":false,\"upstream_error\":true}\n",
		    sni_show);
		http_reply(ssl, 200, "application/json", json);
		return;
	}

	snprintf(json, sizeof(json),
	    "{\"service\":\"layer7-tlsproxy\",\"poc\":\"4\","
	    "\"verdict\":\"allow\",\"sni\":\"%s\","
	    "\"mitm_effective\":false,\"intercept\":false}\n",
	    sni_show);
	http_reply(ssl, 200, "application/json", json);
}

static void
l7_mint_reset(void)
{
	int i;

	for (i = 0; i < l7_nleaf; i++) {
		if (l7_leaf_cache[i].cert != NULL)
			X509_free(l7_leaf_cache[i].cert);
		l7_leaf_cache[i].cert = NULL;
		l7_leaf_cache[i].sni[0] = '\0';
	}
	l7_nleaf = 0;
	if (l7_leaf_key != NULL) {
		EVP_PKEY_free(l7_leaf_key);
		l7_leaf_key = NULL;
	}
	if (l7_ca_cert != NULL) {
		X509_free(l7_ca_cert);
		l7_ca_cert = NULL;
	}
	if (l7_ca_key != NULL) {
		EVP_PKEY_free(l7_ca_key);
		l7_ca_key = NULL;
	}
	l7_mint_mode = 0;
}

static X509 *
l7_load_x509_file(const char *path)
{
	FILE *fp;
	X509 *x;

	fp = fopen(path, "r");
	if (fp == NULL)
		return NULL;
	x = PEM_read_X509(fp, NULL, NULL, NULL);
	fclose(fp);
	return x;
}

static EVP_PKEY *
l7_load_key_file(const char *path)
{
	FILE *fp;
	EVP_PKEY *k;

	fp = fopen(path, "r");
	if (fp == NULL)
		return NULL;
	k = PEM_read_PrivateKey(fp, NULL, NULL, NULL);
	fclose(fp);
	return k;
}

static int
l7_sni_dns_ok(const char *sni)
{
	size_t i, n;

	if (!sni_valid(sni))
		return 0;
	n = strlen(sni);
	for (i = 0; i < n; i++) {
		unsigned char c = (unsigned char)sni[i];

		if ((c >= 'a' && c <= 'z') || (c >= '0' && c <= '9') ||
		    c == '.' || c == '-' || c == '_')
			continue;
		return 0;
	}
	return 1;
}

/*
 * Identidade leaf verificável e compatível com Edge/Chromium moderno:
 * CA:FALSE, KU TLS (sem certSign), EKU serverAuth, SAN=DNS:SNI,
 * SHA-256, AKI/SKI, issuer=CA, assinatura verificável com chave da CA.
 */
static int
l7_leaf_identity_ok(X509 *leaf, const char *sni)
{
	EVP_PKEY *capub = NULL;
	GENERAL_NAMES *sans = NULL;
	int i, n, ok = 0;

	if (leaf == NULL || sni == NULL || sni[0] == '\0' ||
	    l7_ca_cert == NULL)
		return 0;
	/* Nunca peer CA — Chromium ERR_SSL_KEY_USAGE_INCOMPATIBLE. */
	if (X509_check_ca(leaf) > 0)
		return 0;
	if (X509_NAME_cmp(X509_get_issuer_name(leaf),
	    X509_get_subject_name(l7_ca_cert)) != 0)
		return 0;
	capub = X509_get_pubkey(l7_ca_cert);
	if (capub == NULL)
		return 0;
	if (X509_verify(leaf, capub) != 1) {
		EVP_PKEY_free(capub);
		return 0;
	}
	EVP_PKEY_free(capub);

	/* SAN DNS obrigatório (Chromium ignora CN sozinho). */
	sans = X509_get_ext_d2i(leaf, NID_subject_alt_name, NULL, NULL);
	if (sans == NULL)
		return 0;
	n = sk_GENERAL_NAME_num(sans);
	for (i = 0; i < n; i++) {
		GENERAL_NAME *gn = sk_GENERAL_NAME_value(sans, i);
		ASN1_STRING *d;
		const char *dns;

		if (gn == NULL || gn->type != GEN_DNS)
			continue;
		d = gn->d.dNSName;
		if (d == NULL)
			continue;
#if defined(OPENSSL_VERSION_NUMBER) && OPENSSL_VERSION_NUMBER >= 0x10100000L
		dns = (const char *)ASN1_STRING_get0_data(d);
#else
		dns = (const char *)ASN1_STRING_data(d);
#endif
		if (dns != NULL && strcasecmp(dns, sni) == 0) {
			ok = 1;
			break;
		}
	}
	GENERAL_NAMES_free(sans);
	if (!ok)
		return 0;

	/* EKU serverAuth presente (se EKU existir, Chromium exige-o). */
	{
		EXTENDED_KEY_USAGE *eku;
		int has_sa = 0;

		eku = X509_get_ext_d2i(leaf, NID_ext_key_usage, NULL, NULL);
		if (eku == NULL)
			return 0;
		n = sk_ASN1_OBJECT_num(eku);
		for (i = 0; i < n; i++) {
			if (OBJ_obj2nid(sk_ASN1_OBJECT_value(eku, i)) ==
			    NID_server_auth) {
				has_sa = 1;
				break;
			}
		}
		EXTENDED_KEY_USAGE_free(eku);
		if (!has_sa)
			return 0;
	}
	return 1;
}

static void
l7_log_x509_fp(const char *tag, X509 *x)
{
	unsigned char md[EVP_MAX_MD_SIZE];
	unsigned int n = 0;
	unsigned int i;

	if (x == NULL || tag == NULL)
		return;
	if (X509_digest(x, EVP_sha256(), md, &n) != 1 || n == 0)
		return;
	fprintf(stderr, "layer7-tlsproxy: %s sha256=", tag);
	for (i = 0; i < n; i++)
		fprintf(stderr, "%02x", md[i]);
	fprintf(stderr, "\n");
}

static X509 *
l7_mint_leaf_cert(const char *sni)
{
	X509 *x = NULL;
	X509_NAME *subj = NULL;
	X509_EXTENSION *ex = NULL;
	X509V3_CTX v3;
	char san[L7_SNI_MAX + 8];
	ASN1_INTEGER *serial;

	if (l7_ca_cert == NULL || l7_ca_key == NULL || l7_leaf_key == NULL)
		return NULL;
	if (!l7_sni_dns_ok(sni))
		return NULL;

	x = X509_new();
	if (x == NULL)
		return NULL;
	if (X509_set_version(x, 2) != 1)
		goto fail;
	serial = X509_get_serialNumber(x);
	if (serial == NULL)
		goto fail;
	l7_leaf_serial++;
	if (ASN1_INTEGER_set(serial, l7_leaf_serial) != 1)
		goto fail;
	if (X509_gmtime_adj(X509_getm_notBefore(x), -60) == NULL)
		goto fail;
	if (X509_gmtime_adj(X509_getm_notAfter(x), 60L * 60L * 24L * 30L) ==
	    NULL)
		goto fail;
	if (X509_set_pubkey(x, l7_leaf_key) != 1)
		goto fail;
	if (X509_set_issuer_name(x, X509_get_subject_name(l7_ca_cert)) != 1)
		goto fail;

	subj = X509_get_subject_name(x);
	if (subj == NULL)
		goto fail;
	if (X509_NAME_add_entry_by_txt(subj, "CN", MBSTRING_ASC,
	    (const unsigned char *)sni, -1, -1, 0) != 1)
		goto fail;

	X509V3_set_ctx_nodb(&v3);
	X509V3_set_ctx(&v3, l7_ca_cert, x, NULL, NULL, 0);

	ex = X509V3_EXT_nconf_nid(NULL, &v3, NID_basic_constraints,
	    "critical,CA:FALSE");
	if (ex == NULL || X509_add_ext(x, ex, -1) != 1)
		goto fail;
	X509_EXTENSION_free(ex);
	ex = NULL;

	/* KU TLS — sem keyCertSign/cRLSign (Edge/Chromium). */
	ex = X509V3_EXT_nconf_nid(NULL, &v3, NID_key_usage,
	    "critical,digitalSignature,keyEncipherment");
	if (ex == NULL || X509_add_ext(x, ex, -1) != 1)
		goto fail;
	X509_EXTENSION_free(ex);
	ex = NULL;

	ex = X509V3_EXT_nconf_nid(NULL, &v3, NID_ext_key_usage, "serverAuth");
	if (ex == NULL || X509_add_ext(x, ex, -1) != 1)
		goto fail;
	X509_EXTENSION_free(ex);
	ex = NULL;

	if (snprintf(san, sizeof(san), "DNS:%s", sni) >= (int)sizeof(san))
		goto fail;
	ex = X509V3_EXT_nconf_nid(NULL, &v3, NID_subject_alt_name, san);
	if (ex == NULL || X509_add_ext(x, ex, -1) != 1)
		goto fail;
	X509_EXTENSION_free(ex);
	ex = NULL;

	/* SKI/AKI: cadeia verificável e consistente CA↔leaf. */
	ex = X509V3_EXT_nconf_nid(NULL, &v3, NID_subject_key_identifier,
	    "hash");
	if (ex == NULL || X509_add_ext(x, ex, -1) != 1)
		goto fail;
	X509_EXTENSION_free(ex);
	ex = NULL;

	ex = X509V3_EXT_nconf_nid(NULL, &v3, NID_authority_key_identifier,
	    "keyid,issuer");
	if (ex == NULL)
		ex = X509V3_EXT_nconf_nid(NULL, &v3,
		    NID_authority_key_identifier, "issuer");
	if (ex != NULL) {
		if (X509_add_ext(x, ex, -1) != 1) {
			X509_EXTENSION_free(ex);
			ex = NULL;
			goto fail;
		}
		X509_EXTENSION_free(ex);
		ex = NULL;
	}

	if (X509_sign(x, l7_ca_key, EVP_sha256()) == 0)
		goto fail;
	if (!l7_leaf_identity_ok(x, sni)) {
		fprintf(stderr,
		    "layer7-tlsproxy: leaf identity check failed sni=%s\n",
		    sni);
		goto fail;
	}
	return x;

fail:
	if (ex != NULL)
		X509_EXTENSION_free(ex);
	if (x != NULL)
		X509_free(x);
	return NULL;
}

static X509 *
l7_leaf_get_or_mint(const char *sni)
{
	char name[L7_SNI_MAX + 1];
	int i;
	X509 *x;

	strncpy(name, sni, L7_SNI_MAX);
	name[L7_SNI_MAX] = '\0';
	sni_lower(name);
	if (!l7_sni_dns_ok(name))
		return NULL;

	for (i = 0; i < l7_nleaf; i++) {
		if (strcmp(l7_leaf_cache[i].sni, name) == 0)
			return l7_leaf_cache[i].cert;
	}
	if (l7_nleaf >= L7_LEAF_CACHE) {
		/* Evict slot 0 (FIFO simples). */
		X509_free(l7_leaf_cache[0].cert);
		memmove(&l7_leaf_cache[0], &l7_leaf_cache[1],
		    sizeof(l7_leaf_cache[0]) * (L7_LEAF_CACHE - 1));
		l7_nleaf = L7_LEAF_CACHE - 1;
		l7_leaf_cache[l7_nleaf].cert = NULL;
		l7_leaf_cache[l7_nleaf].sni[0] = '\0';
	}
	x = l7_mint_leaf_cert(name);
	if (x == NULL)
		return NULL;
	strncpy(l7_leaf_cache[l7_nleaf].sni, name, L7_SNI_MAX);
	l7_leaf_cache[l7_nleaf].sni[L7_SNI_MAX] = '\0';
	l7_leaf_cache[l7_nleaf].cert = x;
	l7_nleaf++;
	return x;
}

static int
l7_sni_callback(SSL *ssl, int *ad, void *arg)
{
	const char *sni;
	X509 *leaf;
	char name[L7_SNI_MAX + 1];

	(void)arg;
	if (!l7_mint_mode)
		return SSL_TLSEXT_ERR_OK;

	sni = SSL_get_servername(ssl, TLSEXT_NAMETYPE_host_name);
	if (sni == NULL || sni[0] == '\0')
		return SSL_TLSEXT_ERR_OK;

	strncpy(name, sni, L7_SNI_MAX);
	name[L7_SNI_MAX] = '\0';
	sni_lower(name);
	leaf = l7_leaf_get_or_mint(name);
	if (leaf == NULL) {
		*ad = SSL_AD_UNRECOGNIZED_NAME;
		return SSL_TLSEXT_ERR_ALERT_FATAL;
	}
	if (SSL_use_certificate(ssl, leaf) != 1 ||
	    SSL_use_PrivateKey(ssl, l7_leaf_key) != 1) {
		*ad = SSL_AD_INTERNAL_ERROR;
		return SSL_TLSEXT_ERR_ALERT_FATAL;
	}
	return SSL_TLSEXT_ERR_OK;
}

static int
l7_setup_certs(SSL_CTX *ctx, const char *cert_path, const char *key_path)
{
	X509 *loaded;
	EVP_PKEY *loaded_key;
	const char *def_sni;
	X509 *def_leaf;

	l7_mint_reset();
	l7_leaf_serial = (long)time(NULL) & 0x7fffffffL;
	if (l7_leaf_serial <= 0)
		l7_leaf_serial = 1;

	loaded = l7_load_x509_file(cert_path);
	loaded_key = l7_load_key_file(key_path);
	if (loaded == NULL || loaded_key == NULL) {
		fprintf(stderr,
		    "layer7-tlsproxy: failed to load cert/key PEM\n");
		ERR_print_errors_fp(stderr);
		if (loaded != NULL)
			X509_free(loaded);
		if (loaded_key != NULL)
			EVP_PKEY_free(loaded_key);
		return -1;
	}

	if (X509_check_ca(loaded) > 0) {
		/* CA key deve corresponder ao certificado (identidade consistente). */
		if (X509_check_private_key(loaded, loaded_key) != 1) {
			fprintf(stderr,
			    "layer7-tlsproxy: CA cert/key mismatch\n");
			ERR_print_errors_fp(stderr);
			X509_free(loaded);
			EVP_PKEY_free(loaded_key);
			return -1;
		}
		l7_mint_mode = 1;
		l7_ca_cert = loaded;
		l7_ca_key = loaded_key;
		l7_log_x509_fp("ca", l7_ca_cert);
#if defined(OPENSSL_VERSION_NUMBER) && OPENSSL_VERSION_NUMBER >= 0x30000000L
		l7_leaf_key = EVP_RSA_gen(2048);
#else
		l7_leaf_key = EVP_PKEY_new();
		if (l7_leaf_key != NULL) {
			RSA *rsa = RSA_new();
			BIGNUM *e = BN_new();

			if (rsa == NULL || e == NULL ||
			    BN_set_word(e, RSA_F4) != 1 ||
			    RSA_generate_key_ex(rsa, 2048, e, NULL) != 1 ||
			    EVP_PKEY_assign_RSA(l7_leaf_key, rsa) != 1) {
				if (rsa != NULL)
					RSA_free(rsa);
				EVP_PKEY_free(l7_leaf_key);
				l7_leaf_key = NULL;
			}
			/* rsa ownership transferred on success */
			if (e != NULL)
				BN_free(e);
		}
#endif
		if (l7_leaf_key == NULL) {
			fprintf(stderr,
			    "layer7-tlsproxy: leaf RSA keygen failed\n");
			l7_mint_reset();
			return -1;
		}

		def_sni = (l7_nblock > 0) ? l7_block[0] : "layer7-mitm.local";
		def_leaf = l7_leaf_get_or_mint(def_sni);
		if (def_leaf == NULL) {
			fprintf(stderr,
			    "layer7-tlsproxy: default leaf mint failed\n");
			ERR_print_errors_fp(stderr);
			l7_mint_reset();
			return -1;
		}
		if (SSL_CTX_use_certificate(ctx, def_leaf) != 1 ||
		    SSL_CTX_use_PrivateKey(ctx, l7_leaf_key) != 1 ||
		    SSL_CTX_check_private_key(ctx) != 1) {
			fprintf(stderr,
			    "layer7-tlsproxy: default leaf install failed\n");
			ERR_print_errors_fp(stderr);
			l7_mint_reset();
			return -1;
		}
		/* Não enviar a CA como peer na cadeia — só leaf (CA no trust store). */
#if defined(OPENSSL_VERSION_NUMBER) && OPENSSL_VERSION_NUMBER >= 0x10002000L
		SSL_CTX_clear_chain_certs(ctx);
#endif
		SSL_CTX_set_tlsext_servername_callback(ctx, l7_sni_callback);
		l7_log_x509_fp("leaf_default", def_leaf);
		fprintf(stderr,
		    "layer7-tlsproxy: mint-mode ON (CA issuer; leaf peer "
		    "serverAuth+SAN; Chromium-safe; default=%s)\n",
		    def_sni);
		return 0;
	}

	/* Legado PoC: ficheiro ja e leaf de servidor. */
	l7_mint_mode = 0;
	if (SSL_CTX_use_certificate(ctx, loaded) != 1 ||
	    SSL_CTX_use_PrivateKey(ctx, loaded_key) != 1 ||
	    SSL_CTX_check_private_key(ctx) != 1) {
		ERR_print_errors_fp(stderr);
		X509_free(loaded);
		EVP_PKEY_free(loaded_key);
		return -1;
	}
	/* CTX fica com refs proprias via use_*; libertar loads locais. */
	X509_free(loaded);
	EVP_PKEY_free(loaded_key);
	fprintf(stderr,
	    "layer7-tlsproxy: mint-mode OFF (static server cert)\n");
	return 0;
}

int
l7_tls_lab_listen(const char *bind_host, int bind_port,
    const char *cert_path, const char *key_path, int oneshot)
{
	SSL_CTX *ctx = NULL;
	int lfd = -1;
	int rc = 1;
	struct sockaddr_in addr4;

	if (!l7_tls_product_ok() && !l7_ipc_lab_ok()) {
		fprintf(stderr,
		    "layer7-tlsproxy: refusing TLS listen — "
		    "set LAYER7_TLSPROXY_PRODUCT=1 (produto) ou "
		    "LAYER7_TLSPROXY_LAB=1 (lab .54).\n");
		return 3;
	}
	if (cert_path == NULL || key_path == NULL ||
	    cert_path[0] == '\0' || key_path[0] == '\0') {
		fprintf(stderr, "layer7-tlsproxy: --cert and --key required\n");
		return 2;
	}
	if (bind_port <= 0 || bind_port > 65535) {
		fprintf(stderr, "layer7-tlsproxy: invalid port\n");
		return 2;
	}
	if (!host_allowed(bind_host))
		return 3;

	l7_tls_stop = 0;
	signal(SIGINT, on_sig);
	signal(SIGTERM, on_sig);

	SSL_library_init();
	SSL_load_error_strings();
	OpenSSL_add_ssl_algorithms();

	ctx = SSL_CTX_new(TLS_server_method());
	if (ctx == NULL) {
		ERR_print_errors_fp(stderr);
		goto out;
	}
	SSL_CTX_set_min_proto_version(ctx, TLS1_2_VERSION);
	if (l7_setup_certs(ctx, cert_path, key_path) != 0)
		goto out;

	lfd = socket(AF_INET, SOCK_STREAM, 0);
	if (lfd < 0) {
		perror("socket");
		goto out;
	}
	{
		int on = 1;
		setsockopt(lfd, SOL_SOCKET, SO_REUSEADDR, &on, sizeof(on));
	}
	memset(&addr4, 0, sizeof(addr4));
	addr4.sin_family = AF_INET;
	addr4.sin_port = htons((uint16_t)bind_port);
	if (inet_pton(AF_INET, bind_host, &addr4.sin_addr) != 1) {
		fprintf(stderr, "layer7-tlsproxy: inet_pton failed for %s\n",
		    bind_host);
		goto out;
	}
	if (bind(lfd, (struct sockaddr *)&addr4, sizeof(addr4)) != 0) {
		perror("bind");
		goto out;
	}
	if (listen(lfd, 16) != 0) {
		perror("listen");
		goto out;
	}

	fprintf(stderr,
	    "layer7-tlsproxy: TLS %s %s:%d (bypass=%d block=%d "
	    "upstream=%s:%d transparent=%d; mitm_effective_claim=false)\n",
	    l7_tls_product_ok() ? "product" : "lab",
	    bind_host, bind_port, l7_nbypass, l7_nblock,
	    l7_up_port ? l7_up_host : "-", l7_up_port, l7_tls_transparent);

	while (!l7_tls_stop) {
		struct sockaddr_in peer;
		socklen_t plen = sizeof(peer);
		int cfd;
		SSL *ssl;
		const char *sni;
		enum l7_verdict v;

		cfd = accept(lfd, (struct sockaddr *)&peer, &plen);
		if (cfd < 0) {
			if (errno == EINTR)
				continue;
			perror("accept");
			break;
		}
		ssl = SSL_new(ctx);
		if (ssl == NULL) {
			close(cfd);
			continue;
		}
		log_original_dst(cfd);
		SSL_set_fd(ssl, cfd);
		if (SSL_accept(ssl) <= 0) {
			ERR_print_errors_fp(stderr);
		} else {
			sni = SSL_get_servername(ssl, TLSEXT_NAMETYPE_host_name);
			v = decide_sni(sni);
			serve_by_verdict(ssl, v, sni ? sni : "");
		}
		SSL_shutdown(ssl);
		SSL_free(ssl);
		close(cfd);
		if (oneshot) {
			rc = 0;
			break;
		}
	}
	if (l7_tls_stop)
		rc = 0;

out:
	if (lfd >= 0)
		close(lfd);
	if (ctx != NULL)
		SSL_CTX_free(ctx);
	l7_mint_reset();
	EVP_cleanup();
	return rc;
}
