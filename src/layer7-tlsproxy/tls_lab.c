/*
 * PoC-2/3 — TLS acceptor + SNI policy (bypass | block | allow).
 *
 * Lab VM 192.168.100.54 only. No decrypted payload on disk.
 * mitm_effective always reported false (product claim forbidden).
 */

#include "tls_lab.h"
#include "ipc.h"

#include <arpa/inet.h>
#include <ctype.h>
#include <errno.h>
#include <netinet/in.h>
#include <signal.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <unistd.h>

#include <openssl/err.h>
#include <openssl/ssl.h>

#define L7_POLICY_MAX 64
#define L7_SNI_MAX 253

enum l7_verdict {
	L7_ALLOW = 0,
	L7_BYPASS = 1,
	L7_BLOCK = 2
};

static volatile sig_atomic_t l7_tls_stop;
static int l7_tls_allow_any;

static char l7_bypass[L7_POLICY_MAX][L7_SNI_MAX + 1];
static int l7_nbypass;
static char l7_block[L7_POLICY_MAX][L7_SNI_MAX + 1];
static int l7_nblock;

void
l7_tls_set_allow_any(int v)
{
	l7_tls_allow_any = v;
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
	/* block wins over bypass if both listed (fail-closed for explicit block) */
	if (sni_in_list(sni, l7_block, l7_nblock))
		return L7_BLOCK;
	if (sni_in_list(sni, l7_bypass, l7_nbypass))
		return L7_BYPASS;
	return L7_ALLOW;
}

static void
on_sig(int sig)
{
	(void)sig;
	l7_tls_stop = 1;
}

static int
host_allowed(const char *host)
{
	if (host == NULL || host[0] == '\0')
		return 0;
	if (strcmp(host, "127.0.0.1") == 0 || strcmp(host, "::1") == 0)
		return 1;
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

static void
serve_by_verdict(SSL *ssl, enum l7_verdict v, const char *sni)
{
	char req[2048];
	char json[512];
	const char *sni_show = (sni && sni[0]) ? sni : "";

	/* Read request (discard body). No payload persisted. */
	SSL_read(ssl, req, (int)sizeof(req) - 1);

	if (v == L7_BLOCK) {
		static const char *page =
		    "<!DOCTYPE html><html><head><meta charset=\"utf-8\">"
		    "<title>Layer7 — acesso bloqueado</title></head>"
		    "<body style=\"font-family:sans-serif;max-width:40rem;margin:3rem auto\">"
		    "<h1>Acesso bloqueado</h1>"
		    "<p>Este destino foi bloqueado pela política Layer7 (PoC lab).</p>"
		    "<p><small>mitm_effective=false · lab only · sem persistência de payload</small></p>"
		    "</body></html>\n";
		http_reply(ssl, 403, "text/html; charset=utf-8", page);
		return;
	}

	if (v == L7_BYPASS) {
		snprintf(json, sizeof(json),
		    "{\"service\":\"layer7-tlsproxy\",\"poc\":\"3\","
		    "\"verdict\":\"bypass\",\"sni\":\"%s\","
		    "\"mitm_effective\":false,\"intercept\":false,"
		    "\"note\":\"lab-sim: policy bypass (no upstream splice yet)\"}\n",
		    sni_show);
		http_reply(ssl, 200, "application/json", json);
		return;
	}

	snprintf(json, sizeof(json),
	    "{\"service\":\"layer7-tlsproxy\",\"poc\":\"3\","
	    "\"verdict\":\"allow\",\"sni\":\"%s\","
	    "\"mitm_effective\":false,\"intercept\":false}\n",
	    sni_show);
	http_reply(ssl, 200, "application/json", json);
}

int
l7_tls_lab_listen(const char *bind_host, int bind_port,
    const char *cert_path, const char *key_path, int oneshot)
{
	SSL_CTX *ctx = NULL;
	int lfd = -1;
	int rc = 1;
	struct sockaddr_in addr4;

	if (!l7_ipc_lab_ok()) {
		fprintf(stderr,
		    "layer7-tlsproxy: refusing TLS listen — "
		    "set LAYER7_TLSPROXY_LAB=1 (lab .54 only).\n");
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
	/* Need servername callback for SNI visibility on some stacks; get after accept. */
	if (SSL_CTX_use_certificate_file(ctx, cert_path, SSL_FILETYPE_PEM) != 1 ||
	    SSL_CTX_use_PrivateKey_file(ctx, key_path, SSL_FILETYPE_PEM) != 1 ||
	    SSL_CTX_check_private_key(ctx) != 1) {
		ERR_print_errors_fp(stderr);
		goto out;
	}

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
	    "layer7-tlsproxy: TLS lab %s:%d (poc3; bypass=%d block=%d; "
	    "mitm_effective=false)\n",
	    bind_host, bind_port, l7_nbypass, l7_nblock);

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
	EVP_cleanup();
	return rc;
}
