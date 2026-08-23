/*
 * identity_dc.c — IM5 / 20.20
 * Config + HMAC + parse + HTTPS worker (OpenSSL) → identity_map.
 */
#include "identity_dc.h"

#include <arpa/inet.h>
#include <ctype.h>
#include <errno.h>
#include <fcntl.h>
#include <netinet/in.h>
#include <openssl/evp.h>
#include <openssl/hmac.h>
#include <openssl/ssl.h>
#include <pthread.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <strings.h>
#include <sys/socket.h>
#include <sys/types.h>
#include <unistd.h>

#if !defined(__FreeBSD__) && !defined(__OpenBSD__) && !defined(__NetBSD__)
#ifndef explicit_bzero
static void
layer7_explicit_bzero(void *p, size_t n)
{
	volatile unsigned char *v = (volatile unsigned char *)p;
	while (n-- > 0)
		*v++ = 0;
}
#define explicit_bzero(p, n) layer7_explicit_bzero((p), (n))
#endif
#endif

#ifndef L7_NOTE
#define L7_NOTE(...) ((void)0)
#endif
#ifndef L7_WARN
#define L7_WARN(...) ((void)0)
#endif

/* ---- config ---- */

void
layer7_dc_cfg_defaults(struct l7_dc_cfg *c)
{
	if (c == NULL)
		return;
	memset(c, 0, sizeof(*c));
	c->listen_port = L7_DC_DEFAULT_PORT;
	c->skew_sec = L7_DC_DEFAULT_SKEW_SEC;
	snprintf(c->bind_address, sizeof(c->bind_address), "%s", "127.0.0.1");
	snprintf(c->cert_path, sizeof(c->cert_path), "%s", L7_DC_CERT_PATH);
	snprintf(c->key_path, sizeof(c->key_path), "%s", L7_DC_KEY_PATH);
}

void
layer7_dc_cfg_wipe_secret(struct l7_dc_cfg *c)
{
	if (c == NULL)
		return;
	explicit_bzero(c->secret, sizeof(c->secret));
	c->secret_loaded = 0;
}

int
layer7_dc_cfg_load_secret(struct l7_dc_cfg *c, const char *path)
{
	FILE *f;
	char buf[L7_DC_SECRET_MAX];
	size_t n;

	if (c == NULL)
		return -1;
	layer7_dc_cfg_wipe_secret(c);
	if (path == NULL) {
		const char *env = getenv("LAYER7_DC_SECRET");
		if (env != NULL && env[0] != '\0')
			path = env;
		else
			path = L7_DC_SECRET_PATH;
	}
	f = fopen(path, "r");
	if (f == NULL)
		return -1;
	n = fread(buf, 1, sizeof(buf) - 1, f);
	fclose(f);
	while (n > 0 && (buf[n - 1] == '\n' || buf[n - 1] == '\r'))
		n--;
	buf[n] = '\0';
	if (n == 0)
		return -1;
	memcpy(c->secret, buf, n + 1);
	explicit_bzero(buf, sizeof(buf));
	c->secret_loaded = 1;
	return 0;
}

static void
skip_ws(const char **p)
{
	while (**p && isspace((unsigned char)**p))
		(*p)++;
}

static int
parse_bool_val(const char *p, int *out)
{
	skip_ws(&p);
	if (strncmp(p, "true", 4) == 0) {
		*out = 1;
		return 0;
	}
	if (strncmp(p, "false", 5) == 0) {
		*out = 0;
		return 0;
	}
	return -1;
}

static int
parse_int_val(const char *p, int *out)
{
	char *end;
	long v;

	skip_ws(&p);
	v = strtol(p, &end, 10);
	if (end == p)
		return -1;
	*out = (int)v;
	return 0;
}

static int
parse_qstr(const char *p, char *dst, size_t dstsz)
{
	size_t i = 0;

	skip_ws(&p);
	if (*p != '"')
		return -1;
	p++;
	while (*p && *p != '"' && i + 1 < dstsz) {
		if (*p == '\\' && p[1])
			p++;
		dst[i++] = *p++;
	}
	dst[i] = '\0';
	return (*p == '"') ? 0 : -1;
}

static const char *
find_key(const char *json, size_t len, const char *key)
{
	size_t klen = strlen(key);
	size_t i;

	for (i = 0; i + klen + 2 < len; i++) {
		if (json[i] != '"')
			continue;
		if (strncmp(json + i + 1, key, klen) == 0 &&
		    json[i + 1 + klen] == '"') {
			const char *p = json + i + 1 + klen + 1;
			skip_ws(&p);
			if (*p == ':')
				return p + 1;
		}
	}
	return NULL;
}

static const char *
skip_qstr(const char *p)
{
	skip_ws(&p);
	if (*p != '"')
		return NULL;
	p++;
	while (*p && *p != '"') {
		if (*p == '\\' && p[1])
			p += 2;
		else
			p++;
	}
	if (*p != '"')
		return NULL;
	return p + 1;
}

static int
parse_acl_array(const char *p, struct l7_dc_cfg *out)
{
	char tmp[L7_DC_ACL_ADDR_MAX];

	skip_ws(&p);
	out->n_dc = 0;
	if (*p == '"') {
		if (parse_qstr(p, tmp, sizeof(tmp)) != 0)
			return -1;
		if (tmp[0] != '\0') {
			snprintf(out->dc_acl[0], sizeof(out->dc_acl[0]), "%s",
			    tmp);
			out->n_dc = 1;
		}
		return 0;
	}
	if (*p != '[')
		return -1;
	p++;
	while (*p && *p != ']') {
		const char *next;

		skip_ws(&p);
		if (*p == ']')
			break;
		if (*p == ',') {
			p++;
			continue;
		}
		if (parse_qstr(p, tmp, sizeof(tmp)) != 0)
			return -1;
		next = skip_qstr(p);
		if (next == NULL)
			return -1;
		p = next;
		if (tmp[0] != '\0' && out->n_dc < L7_DC_ACL_MAX) {
			snprintf(out->dc_acl[out->n_dc],
			    sizeof(out->dc_acl[0]), "%s", tmp);
			out->n_dc++;
		}
		skip_ws(&p);
	}
	return 0;
}

int
layer7_dc_cfg_parse_json(const char *json, size_t len, struct l7_dc_cfg *out)
{
	const char *id, *dc, *q;
	int v;

	if (out == NULL)
		return -1;
	layer7_dc_cfg_defaults(out);
	if (json == NULL || len == 0)
		return 0;

	id = find_key(json, len, "identity");
	if (id == NULL)
		return 0;
	skip_ws(&id);
	if (*id != '{')
		return 0;

	q = find_key(id, (size_t)(json + len - id), "enabled");
	if (q != NULL && parse_bool_val(q, &v) == 0)
		out->identity_enabled = v;

	dc = find_key(id, (size_t)(json + len - id), "dc_agent");
	if (dc == NULL)
		return 0;
	skip_ws(&dc);
	if (*dc != '{')
		return 0;

	q = find_key(dc, (size_t)(json + len - dc), "enabled");
	if (q != NULL && parse_bool_val(q, &v) == 0)
		out->dc_enabled = v;
	q = find_key(dc, (size_t)(json + len - dc), "listen_port");
	if (q != NULL && parse_int_val(q, &v) == 0 && v >= 1 && v <= 65535)
		out->listen_port = v;
	q = find_key(dc, (size_t)(json + len - dc), "bind_address");
	if (q != NULL)
		(void)parse_qstr(q, out->bind_address, sizeof(out->bind_address));
	q = find_key(dc, (size_t)(json + len - dc), "skew_sec");
	if (q != NULL && parse_int_val(q, &v) == 0) {
		if (v < 60)
			v = 60;
		if (v > 900)
			v = 900;
		out->skew_sec = v;
	}
	q = find_key(dc, (size_t)(json + len - dc), "dc_acl");
	if (q != NULL)
		(void)parse_acl_array(q, out);
	q = find_key(dc, (size_t)(json + len - dc), "cert_path");
	if (q != NULL)
		(void)parse_qstr(q, out->cert_path, sizeof(out->cert_path));
	q = find_key(dc, (size_t)(json + len - dc), "key_path");
	if (q != NULL)
		(void)parse_qstr(q, out->key_path, sizeof(out->key_path));

	return 0;
}

int
layer7_dc_peer_allowed(const struct l7_dc_cfg *c, const char *peer_ip)
{
	unsigned i;

	if (c == NULL || peer_ip == NULL || peer_ip[0] == '\0')
		return 0;
	if (c->n_dc == 0)
		return 0;
	for (i = 0; i < c->n_dc; i++) {
		if (strcmp(c->dc_acl[i], peer_ip) == 0)
			return 1;
	}
	return 0;
}

/* ---- crypto / canonical ---- */

static int
sha256_hex(const uint8_t *data, size_t len, char *out_hex, size_t out_sz)
{
	EVP_MD_CTX *ctx;
	uint8_t dig[32];
	unsigned int dlen = 0;
	size_t i;
	int ok;

	if (out_hex == NULL || out_sz < 65)
		return -1;
	ctx = EVP_MD_CTX_new();
	if (ctx == NULL)
		return -1;
	ok = EVP_DigestInit_ex(ctx, EVP_sha256(), NULL) == 1 &&
	    EVP_DigestUpdate(ctx, data, len) == 1 &&
	    EVP_DigestFinal_ex(ctx, dig, &dlen) == 1 && dlen == 32;
	EVP_MD_CTX_free(ctx);
	if (!ok)
		return -1;
	for (i = 0; i < 32; i++)
		snprintf(out_hex + i * 2, 3, "%02x", dig[i]);
	out_hex[64] = '\0';
	return 0;
}

int
layer7_dc_build_canonical(long timestamp, const char *method, const char *path,
    const uint8_t *body, size_t body_len, char *out, size_t out_sz)
{
	char body_hex[65];
	int n;

	if (out == NULL || method == NULL || path == NULL)
		return -1;
	if (sha256_hex(body ? body : (const uint8_t *)"", body ? body_len : 0,
		body_hex, sizeof(body_hex)) != 0)
		return -1;
	n = snprintf(out, out_sz, "%ld\n%s\n%s\n%s", timestamp, method, path,
	    body_hex);
	if (n < 0 || (size_t)n >= out_sz)
		return -1;
	return 0;
}

int
layer7_dc_hmac_hex(const char *secret, const char *canonical, char *out_hex,
    size_t out_hex_sz)
{
	unsigned char dig[EVP_MAX_MD_SIZE];
	unsigned int dlen = 0;
	size_t i;

	if (secret == NULL || canonical == NULL || out_hex == NULL ||
	    out_hex_sz < 65)
		return -1;
	if (HMAC(EVP_sha256(), secret, (int)strlen(secret),
		(const unsigned char *)canonical, strlen(canonical), dig,
		&dlen) == NULL ||
	    dlen != 32)
		return -1;
	for (i = 0; i < 32; i++)
		snprintf(out_hex + i * 2, 3, "%02x", dig[i]);
	out_hex[64] = '\0';
	return 0;
}

int
layer7_dc_hmac_equal(const char *a, const char *b)
{
	size_t i;
	unsigned char diff = 0;

	if (a == NULL || b == NULL)
		return 0;
	if (strlen(a) != 64 || strlen(b) != 64)
		return 0;
	for (i = 0; i < 64; i++)
		diff |= (unsigned char)(tolower((unsigned char)a[i]) ^
		    tolower((unsigned char)b[i]));
	return diff == 0;
}

int
layer7_dc_check_skew(time_t event_ts, time_t now, int skew_sec)
{
	time_t d;

	if (skew_sec < 1)
		skew_sec = L7_DC_DEFAULT_SKEW_SEC;
	d = (event_ts > now) ? (event_ts - now) : (now - event_ts);
	return (d <= (time_t)skew_sec) ? 0 : -1;
}

/* ---- event parse / apply ---- */

static int
parse_ip_str(const char *s, struct l7_id_addr *out)
{
	struct in_addr a4;
	struct in6_addr a6;

	if (s == NULL || out == NULL)
		return -1;
	if (inet_pton(AF_INET, s, &a4) == 1) {
		return layer7_idmap_addr_set_ipv4(out, ntohl(a4.s_addr));
	}
	if (inet_pton(AF_INET6, s, &a6) == 1) {
		return layer7_idmap_addr_set_ipv6(out, (const uint8_t *)&a6);
	}
	return -1;
}

int
layer7_dc_parse_event(const char *json, size_t len, struct l7_dc_event *out)
{
	const char *q;
	char tmp[256];
	int v;

	if (out == NULL)
		return -1;
	memset(out, 0, sizeof(*out));
	if (json == NULL || len == 0 || len > L7_DC_BODY_MAX)
		return -1;

	q = find_key(json, len, "user");
	if (q == NULL || parse_qstr(q, out->user, sizeof(out->user)) != 0 ||
	    out->user[0] == '\0')
		return -1;

	q = find_key(json, len, "ip");
	if (q == NULL || parse_qstr(q, tmp, sizeof(tmp)) != 0 ||
	    parse_ip_str(tmp, &out->ip) != 0)
		return -1;
	out->has_ip = 1;

	q = find_key(json, len, "event");
	if (q == NULL || parse_qstr(q, tmp, sizeof(tmp)) != 0)
		return -1;
	if (strcmp(tmp, "logon") == 0)
		out->type = L7_DC_EVT_LOGON;
	else if (strcmp(tmp, "logoff") == 0)
		out->type = L7_DC_EVT_LOGOFF;
	else if (strcmp(tmp, "heartbeat") == 0)
		out->type = L7_DC_EVT_HEARTBEAT;
	else
		return -1;

	q = find_key(json, len, "timestamp");
	if (q == NULL || parse_int_val(q, &v) != 0 || v <= 0)
		return -1;
	out->timestamp = (time_t)v;
	out->valid = 1;
	return 0;
}

int
layer7_dc_apply_event(struct l7_id_map *map, const struct l7_dc_event *ev,
    time_t now)
{
	if (map == NULL || ev == NULL || !ev->valid || ev->user[0] == '\0')
		return -1;
	if (ev->type == L7_DC_EVT_LOGOFF) {
		if (ev->has_ip)
			(void)layer7_idmap_remove_ip(map, ev->user, &ev->ip);
		else
			(void)layer7_idmap_remove_user(map, ev->user);
		return 0;
	}
	if (!ev->has_ip)
		return -1;
	return layer7_idmap_upsert(map, ev->user, &ev->ip, L7_ID_SRC_DC_AGENT,
	    NULL, 0, now) < 0 ? -1 : 0;
}

int
layer7_dc_handle_push(struct l7_id_map *map, const struct l7_dc_cfg *cfg,
    const char *token_hdr, const char *sig_hdr, long ts_hdr,
    const char *method, const char *path, const uint8_t *body, size_t body_len,
    time_t now)
{
	char canonical[512];
	char expect[65];
	struct l7_dc_event ev;

	if (cfg == NULL || !cfg->secret_loaded || cfg->secret[0] == '\0')
		return -1;
	if (token_hdr == NULL || sig_hdr == NULL || method == NULL ||
	    path == NULL)
		return -1;
	if (strcmp(token_hdr, cfg->secret) != 0)
		return -1;
	if (layer7_dc_check_skew((time_t)ts_hdr, now, cfg->skew_sec) != 0)
		return -2;
	if (layer7_dc_build_canonical(ts_hdr, method, path, body, body_len,
		canonical, sizeof(canonical)) != 0)
		return -2;
	if (layer7_dc_hmac_hex(cfg->secret, canonical, expect,
		sizeof(expect)) != 0)
		return -1;
	if (!layer7_dc_hmac_equal(expect, sig_hdr))
		return -1;
	if (layer7_dc_parse_event((const char *)body, body_len, &ev) != 0)
		return -2;
	/* timestamp no body deve alinhar com header (tolerância já no skew) */
	if (layer7_dc_check_skew(ev.timestamp, now, cfg->skew_sec) != 0)
		return -2;
	if (map == NULL)
		return 0;
	if (layer7_dc_apply_event(map, &ev, now) != 0)
		return -3;
	return 0;
}

/* ---- HTTPS worker ---- */

struct l7_dc_rate {
	char     ip[L7_DC_ACL_ADDR_MAX];
	time_t   window_start;
	unsigned count;
};

struct l7_dc_worker {
	pthread_t           thr;
	pthread_mutex_t     mu;
	int                 stop;
	int                 running;
	int                 sock;
	enum l7_dc_status   status;
	struct l7_id_map   *map;
	struct l7_dc_cfg    cfg;
	SSL_CTX            *ssl_ctx;
	unsigned            accepted;
	unsigned            rejected;
	struct l7_dc_rate   rates[64];
	unsigned            n_rates;
	unsigned            global_count;
	time_t              global_window;
};

static int
set_nonblock(int fd)
{
	int fl = fcntl(fd, F_GETFL, 0);
	if (fl < 0)
		return -1;
	return fcntl(fd, F_SETFL, fl | O_NONBLOCK);
}

static int
rate_allow(struct l7_dc_worker *w, const char *peer, time_t now)
{
	unsigned i;

	if (w->global_window != now) {
		w->global_window = now;
		w->global_count = 0;
	}
	if (w->global_count >= 200)
		return 0;
	w->global_count++;

	for (i = 0; i < w->n_rates; i++) {
		if (strcmp(w->rates[i].ip, peer) == 0) {
			if (w->rates[i].window_start != now) {
				w->rates[i].window_start = now;
				w->rates[i].count = 0;
			}
			if (w->rates[i].count >= 50)
				return 0;
			w->rates[i].count++;
			return 1;
		}
	}
	if (w->n_rates < 64) {
		snprintf(w->rates[w->n_rates].ip,
		    sizeof(w->rates[0].ip), "%s", peer);
		w->rates[w->n_rates].window_start = now;
		w->rates[w->n_rates].count = 1;
		w->n_rates++;
	}
	return 1;
}

static SSL_CTX *
dc_ssl_ctx_new(const struct l7_dc_cfg *cfg)
{
	SSL_CTX *ctx;
	const SSL_METHOD *meth;

#if OPENSSL_VERSION_NUMBER >= 0x10100000L
	meth = TLS_server_method();
#else
	meth = SSLv23_server_method();
#endif
	ctx = SSL_CTX_new(meth);
	if (ctx == NULL)
		return NULL;
#if OPENSSL_VERSION_NUMBER >= 0x10100000L
	SSL_CTX_set_min_proto_version(ctx, TLS1_2_VERSION);
#else
	SSL_CTX_set_options(ctx, SSL_OP_NO_SSLv2 | SSL_OP_NO_SSLv3 | SSL_OP_NO_TLSv1 |
	    SSL_OP_NO_TLSv1_1);
#endif

	if (SSL_CTX_use_certificate_file(ctx, cfg->cert_path, SSL_FILETYPE_PEM) !=
		1 ||
	    SSL_CTX_use_PrivateKey_file(ctx, cfg->key_path, SSL_FILETYPE_PEM) !=
		1 ||
	    SSL_CTX_check_private_key(ctx) != 1) {
		SSL_CTX_free(ctx);
		return NULL;
	}
	return ctx;
}

static int
dc_open_sock(const struct l7_dc_cfg *cfg)
{
	int fd, on = 1;
	struct sockaddr_in sa;

	fd = socket(AF_INET, SOCK_STREAM, 0);
	if (fd < 0)
		return -1;
	(void)setsockopt(fd, SOL_SOCKET, SO_REUSEADDR, &on, sizeof(on));
	memset(&sa, 0, sizeof(sa));
	sa.sin_family = AF_INET;
	sa.sin_port = htons((uint16_t)cfg->listen_port);
	if (cfg->bind_address[0] == '\0' ||
	    strcmp(cfg->bind_address, "0.0.0.0") == 0) {
		/* Desenho: default seguro = não WAN. 0.0.0.0 só se explícito. */
		sa.sin_addr.s_addr = htonl(INADDR_ANY);
	} else if (inet_pton(AF_INET, cfg->bind_address, &sa.sin_addr) != 1) {
		close(fd);
		return -1;
	}
	if (bind(fd, (struct sockaddr *)&sa, sizeof(sa)) != 0) {
		close(fd);
		return -1;
	}
	if (listen(fd, 16) != 0) {
		close(fd);
		return -1;
	}
	(void)set_nonblock(fd);
	return fd;
}

static void
tolower_hex_inplace(char *s)
{
	while (*s) {
		*s = (char)tolower((unsigned char)*s);
		s++;
	}
}

static int
http_read_request(SSL *ssl, char *buf, size_t cap, size_t *out_len)
{
	size_t n = 0;
	int r;

	while (n + 1 < cap) {
		r = SSL_read(ssl, buf + n, (int)(cap - 1 - n));
		if (r <= 0)
			break;
		n += (size_t)r;
		buf[n] = '\0';
		if (strstr(buf, "\r\n\r\n") != NULL)
			break;
		if (n >= cap - 1)
			break;
	}
	*out_len = n;
	return (n > 0) ? 0 : -1;
}

static void
http_respond(SSL *ssl, int code, const char *msg)
{
	char hdr[256];
	int n;

	n = snprintf(hdr, sizeof(hdr),
	    "HTTP/1.1 %d %s\r\nContent-Length: 0\r\nConnection: close\r\n\r\n",
	    code, msg ? msg : "");
	if (n > 0)
		(void)SSL_write(ssl, hdr, n);
}

static void
handle_client(struct l7_dc_worker *w, const struct l7_dc_cfg *cfg, int cfd,
    const char *peer)
{
	SSL *ssl = NULL;
	char req[8192];
	size_t req_len = 0;
	char *hdr_end, *body;
	size_t body_len = 0, hdr_len;
	char method[16], path[128];
	char token[L7_DC_SECRET_MAX], sig[128];
	long ts = 0;
	const char *p;
	int rc;
	time_t now;

	if (w->ssl_ctx == NULL) {
		close(cfd);
		w->rejected++;
		return;
	}
	ssl = SSL_new(w->ssl_ctx);
	if (ssl == NULL) {
		close(cfd);
		w->rejected++;
		return;
	}
	SSL_set_fd(ssl, cfd);
	if (SSL_accept(ssl) <= 0) {
		SSL_free(ssl);
		close(cfd);
		w->rejected++;
		return;
	}

	now = time(NULL);
	if (!layer7_dc_peer_allowed(cfg, peer) || !rate_allow(w, peer, now)) {
		http_respond(ssl, 429, "Too Many Requests");
		SSL_shutdown(ssl);
		SSL_free(ssl);
		close(cfd);
		w->rejected++;
		return;
	}

	if (http_read_request(ssl, req, sizeof(req), &req_len) != 0) {
		SSL_free(ssl);
		close(cfd);
		w->rejected++;
		return;
	}
	hdr_end = strstr(req, "\r\n\r\n");
	if (hdr_end == NULL) {
		http_respond(ssl, 400, "Bad Request");
		goto done;
	}
	hdr_len = (size_t)(hdr_end - req);
	body = hdr_end + 4;
	body_len = req_len > hdr_len + 4 ? req_len - (hdr_len + 4) : 0;

	method[0] = path[0] = token[0] = sig[0] = '\0';
	if (sscanf(req, "%15s %127s", method, path) != 2) {
		http_respond(ssl, 400, "Bad Request");
		goto done;
	}
	if (strcasecmp(method, "POST") != 0 ||
	    strcmp(path, L7_DC_PATH) != 0) {
		http_respond(ssl, 404, "Not Found");
		goto done;
	}

	/* Content-Length */
	p = strcasestr(req, "\r\nContent-Length:");
	if (p != NULL) {
		long cl = strtol(p + 17, NULL, 10);
		if (cl < 0 || cl > L7_DC_BODY_MAX) {
			http_respond(ssl, 413, "Payload Too Large");
			goto done;
		}
		while (body_len < (size_t)cl && req_len + 1 < sizeof(req)) {
			int r = SSL_read(ssl, req + req_len,
			    (int)(sizeof(req) - 1 - req_len));
			if (r <= 0)
				break;
			req_len += (size_t)r;
			req[req_len] = '\0';
			body = strstr(req, "\r\n\r\n");
			if (body == NULL)
				break;
			body += 4;
			body_len = req_len - (size_t)(body - req);
		}
		if (body_len > (size_t)cl)
			body_len = (size_t)cl;
	}
	if (body_len == 0 || body_len > L7_DC_BODY_MAX) {
		http_respond(ssl, 400, "Bad Request");
		goto done;
	}

	token[0] = sig[0] = '\0';
	p = strcasestr(req, "\r\nX-Layer7-Token:");
	if (p != NULL) {
		p += 17;
		while (*p == ' ')
			p++;
		snprintf(token, sizeof(token), "%s", p);
	} else {
		p = strcasestr(req, "\r\nAuthorization:");
		if (p != NULL) {
			const char *b = strcasestr(p, "Bearer ");
			if (b != NULL) {
				b += 7;
				while (*b == ' ')
					b++;
				snprintf(token, sizeof(token), "%s", b);
			}
		}
	}
	{
		char *nl = strpbrk(token, "\r\n");
		if (nl)
			*nl = '\0';
	}
	p = strcasestr(req, "\r\nX-Layer7-Signature:");
	if (p != NULL) {
		p += 21;
		while (*p == ' ')
			p++;
		snprintf(sig, sizeof(sig), "%s", p);
		{
			char *nl = strpbrk(sig, "\r\n");
			if (nl)
				*nl = '\0';
		}
		tolower_hex_inplace(sig);
	}
	p = strcasestr(req, "\r\nX-Layer7-Timestamp:");
	if (p != NULL)
		ts = strtol(p + 21, NULL, 10);

	if (token[0] == '\0' || sig[0] == '\0' || ts <= 0) {
		http_respond(ssl, 401, "Unauthorized");
		w->rejected++;
		goto done;
	}

	rc = layer7_dc_handle_push(w->map, cfg, token, sig, ts, "POST",
	    L7_DC_PATH, (const uint8_t *)body, body_len, now);
	if (rc == 0) {
		http_respond(ssl, 204, "No Content");
		w->accepted++;
		L7_NOTE("identity: dc_agent event ok peer=%s", peer);
	} else if (rc == -2) {
		http_respond(ssl, 400, "Bad Request");
		w->rejected++;
	} else {
		http_respond(ssl, 401, "Unauthorized");
		w->rejected++;
	}

done:
	SSL_shutdown(ssl);
	SSL_free(ssl);
	close(cfd);
}

static void *
dc_worker_main(void *arg)
{
	struct l7_dc_worker *w = arg;

	while (1) {
		struct l7_dc_cfg cfg;
		int stop, sock;
		fd_set rfds;
		struct timeval tv;
		struct sockaddr_in peer;
		socklen_t peerlen;
		int cfd;
		char peer_ip[INET_ADDRSTRLEN];

		pthread_mutex_lock(&w->mu);
		stop = w->stop;
		cfg = w->cfg;
		sock = w->sock;
		pthread_mutex_unlock(&w->mu);
		if (stop)
			break;
		if (sock < 0) {
			usleep(200000);
			continue;
		}
		FD_ZERO(&rfds);
		FD_SET(sock, &rfds);
		tv.tv_sec = 0;
		tv.tv_usec = 200000;
		if (select(sock + 1, &rfds, NULL, NULL, &tv) <= 0)
			continue;
		peerlen = sizeof(peer);
		cfd = accept(sock, (struct sockaddr *)&peer, &peerlen);
		if (cfd < 0)
			continue;
		if (inet_ntop(AF_INET, &peer.sin_addr, peer_ip,
			sizeof(peer_ip)) == NULL) {
			close(cfd);
			continue;
		}
		pthread_mutex_lock(&w->mu);
		cfg = w->cfg;
		pthread_mutex_unlock(&w->mu);
		handle_client(w, &cfg, cfd, peer_ip);
		layer7_dc_cfg_wipe_secret(&cfg);
	}
	return NULL;
}

struct l7_dc_worker *
layer7_dc_worker_start(struct l7_id_map *map, const struct l7_dc_cfg *cfg)
{
	struct l7_dc_worker *w;
	int fd;
	SSL_CTX *ctx;

	if (cfg == NULL || !cfg->identity_enabled || !cfg->dc_enabled)
		return NULL;
	if (!cfg->secret_loaded || cfg->secret[0] == '\0') {
		L7_WARN("identity: dc_agent start refused — secret ausente");
		return NULL;
	}
	if (cfg->n_dc == 0) {
		L7_WARN("identity: dc_agent start refused — DC ACL vazia");
		return NULL;
	}

	SSL_library_init();
	OpenSSL_add_ssl_algorithms();
	ctx = dc_ssl_ctx_new(cfg);
	if (ctx == NULL) {
		L7_WARN("identity: dc_agent TLS cert/key invalidos (%s / %s)",
		    cfg->cert_path, cfg->key_path);
		return NULL;
	}
	fd = dc_open_sock(cfg);
	if (fd < 0) {
		SSL_CTX_free(ctx);
		L7_WARN("identity: dc_agent bind failed port=%d addr=%s",
		    cfg->listen_port, cfg->bind_address);
		return NULL;
	}

	w = calloc(1, sizeof(*w));
	if (w == NULL) {
		close(fd);
		SSL_CTX_free(ctx);
		return NULL;
	}
	if (pthread_mutex_init(&w->mu, NULL) != 0) {
		close(fd);
		SSL_CTX_free(ctx);
		free(w);
		return NULL;
	}
	w->map = map;
	w->cfg = *cfg;
	w->sock = fd;
	w->ssl_ctx = ctx;
	w->status = L7_DC_STATUS_LISTEN;
	if (pthread_create(&w->thr, NULL, dc_worker_main, w) != 0) {
		close(fd);
		SSL_CTX_free(ctx);
		pthread_mutex_destroy(&w->mu);
		layer7_dc_cfg_wipe_secret(&w->cfg);
		free(w);
		return NULL;
	}
	w->running = 1;
	L7_NOTE("identity: dc_agent worker ON port=%d bind=%s dc=%u",
	    cfg->listen_port, cfg->bind_address, cfg->n_dc);
	return w;
}

void
layer7_dc_worker_reload(struct l7_dc_worker *w, const struct l7_dc_cfg *cfg)
{
	if (w == NULL || cfg == NULL)
		return;
	pthread_mutex_lock(&w->mu);
	layer7_dc_cfg_wipe_secret(&w->cfg);
	w->cfg = *cfg;
	pthread_mutex_unlock(&w->mu);
	L7_NOTE("identity: dc_agent reloaded enabled=%d", cfg->dc_enabled);
}

void
layer7_dc_worker_stop(struct l7_dc_worker *w)
{
	int sock;

	if (w == NULL)
		return;
	pthread_mutex_lock(&w->mu);
	w->stop = 1;
	sock = w->sock;
	w->sock = -1;
	pthread_mutex_unlock(&w->mu);
	if (sock >= 0)
		close(sock);
	if (w->running)
		(void)pthread_join(w->thr, NULL);
	w->running = 0;
	if (w->ssl_ctx) {
		SSL_CTX_free(w->ssl_ctx);
		w->ssl_ctx = NULL;
	}
	w->status = L7_DC_STATUS_OFF;
	layer7_dc_cfg_wipe_secret(&w->cfg);
	pthread_mutex_destroy(&w->mu);
	free(w);
	L7_NOTE("identity: dc_agent worker stopped");
}

enum l7_dc_status
layer7_dc_worker_status(const struct l7_dc_worker *w)
{
	return w ? w->status : L7_DC_STATUS_OFF;
}

unsigned
layer7_dc_worker_accepted(const struct l7_dc_worker *w)
{
	return w ? w->accepted : 0;
}

unsigned
layer7_dc_worker_rejected(const struct l7_dc_worker *w)
{
	return w ? w->rejected : 0;
}
