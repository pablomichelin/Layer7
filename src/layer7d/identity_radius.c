/*
 * identity_radius.c — IM5 / 20.19
 * RADIUS accounting receiver: parse RFC 2866 + UDP worker + mapa.
 */
#include "identity_radius.h"

#include <arpa/inet.h>
#include <ctype.h>
#include <errno.h>
#include <fcntl.h>
#include <netinet/in.h>
#include <openssl/evp.h>
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

#define RADIUS_CODE_ACCT_REQUEST  4
#define RADIUS_CODE_ACCT_RESPONSE 5
#define RADIUS_ATTR_USER_NAME     1
#define RADIUS_ATTR_FRAMED_IP     8
#define RADIUS_ATTR_ACCT_STATUS   40
#define RADIUS_ATTR_FRAMED_IPV6   168

/* ---- MD5 via OpenSSL EVP ---- */

static int
md5_buf(const uint8_t *data, size_t len, uint8_t out[16])
{
	EVP_MD_CTX *ctx;
	unsigned int outlen = 0;
	int ok;

	ctx = EVP_MD_CTX_new();
	if (ctx == NULL)
		return -1;
	ok = EVP_DigestInit_ex(ctx, EVP_md5(), NULL) == 1 &&
	    EVP_DigestUpdate(ctx, data, len) == 1 &&
	    EVP_DigestFinal_ex(ctx, out, &outlen) == 1 &&
	    outlen == 16;
	EVP_MD_CTX_free(ctx);
	return ok ? 0 : -1;
}

/* ---- config ---- */

void
layer7_radius_cfg_defaults(struct l7_radius_cfg *c)
{
	if (c == NULL)
		return;
	memset(c, 0, sizeof(*c));
	c->listen_port = L7_RADIUS_DEFAULT_PORT;
	snprintf(c->bind_address, sizeof(c->bind_address), "%s", "0.0.0.0");
}

void
layer7_radius_cfg_wipe_secret(struct l7_radius_cfg *c)
{
	if (c == NULL)
		return;
	explicit_bzero(c->secret, sizeof(c->secret));
	c->secret_loaded = 0;
}

int
layer7_radius_cfg_load_secret(struct l7_radius_cfg *c, const char *path)
{
	FILE *f;
	char buf[L7_RADIUS_SECRET_MAX];
	size_t n;

	if (c == NULL)
		return -1;
	layer7_radius_cfg_wipe_secret(c);
	if (path == NULL) {
		const char *env = getenv("LAYER7_RADIUS_SECRET");
		if (env != NULL && env[0] != '\0')
			path = env;
		else
			path = L7_RADIUS_SECRET_PATH;
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

/* ---- minimal JSON helpers (identity.radius) ---- */

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
parse_nas_acl_array(const char *p, struct l7_radius_cfg *out)
{
	char tmp[L7_RADIUS_NAS_ADDR_MAX];

	skip_ws(&p);
	out->n_nas = 0;
	/* Aceita "10.0.0.1" ou ["10.0.0.1","10.0.0.2"] */
	if (*p == '"') {
		if (parse_qstr(p, tmp, sizeof(tmp)) != 0)
			return -1;
		if (tmp[0] != '\0') {
			snprintf(out->nas_acl[0], sizeof(out->nas_acl[0]),
			    "%s", tmp);
			out->n_nas = 1;
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
		if (tmp[0] != '\0' && out->n_nas < L7_RADIUS_NAS_MAX) {
			snprintf(out->nas_acl[out->n_nas],
			    sizeof(out->nas_acl[0]), "%s", tmp);
			out->n_nas++;
		}
		skip_ws(&p);
	}
	return 0;
}

int
layer7_radius_cfg_parse_json(const char *json, size_t len,
    struct l7_radius_cfg *out)
{
	const char *id, *rad, *q;
	int v;

	if (out == NULL)
		return -1;
	layer7_radius_cfg_defaults(out);
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

	rad = find_key(id, (size_t)(json + len - id), "radius");
	if (rad == NULL)
		return 0;
	skip_ws(&rad);
	if (*rad != '{')
		return 0;

	q = find_key(rad, (size_t)(json + len - rad), "enabled");
	if (q != NULL && parse_bool_val(q, &v) == 0)
		out->radius_enabled = v;
	q = find_key(rad, (size_t)(json + len - rad), "listen_port");
	if (q != NULL && parse_int_val(q, &v) == 0 && v >= 1 && v <= 65535)
		out->listen_port = v;
	q = find_key(rad, (size_t)(json + len - rad), "bind_address");
	if (q != NULL)
		(void)parse_qstr(q, out->bind_address, sizeof(out->bind_address));
	q = find_key(rad, (size_t)(json + len - rad), "nas_acl");
	if (q != NULL)
		(void)parse_nas_acl_array(q, out);

	return 0;
}

int
layer7_radius_nas_allowed(const struct l7_radius_cfg *c, const char *peer_ip)
{
	unsigned i;

	if (c == NULL || peer_ip == NULL || peer_ip[0] == '\0')
		return 0;
	if (c->n_nas == 0)
		return 0; /* ACL vazia = rejeitar (seguro) */
	for (i = 0; i < c->n_nas; i++) {
		if (strcmp(c->nas_acl[i], peer_ip) == 0)
			return 1;
	}
	return 0;
}

/* ---- packet parse / response ---- */

static uint32_t
attr_u32(const uint8_t *v, size_t len)
{
	uint32_t x = 0;
	size_t i;

	for (i = 0; i < len && i < 4; i++)
		x = (x << 8) | v[i];
	return x;
}

int
layer7_radius_parse_accounting(const uint8_t *pkt, size_t pkt_len,
    const char *secret, struct l7_radius_acct_event *out)
{
	uint16_t length;
	size_t seclen, attr_off;
	uint8_t *buf = NULL;
	uint8_t digest[16];
	int has_user = 0, has_status = 0;

	if (out == NULL)
		return -1;
	memset(out, 0, sizeof(*out));
	if (pkt == NULL || secret == NULL || secret[0] == '\0')
		return -1;
	if (pkt_len < 20 || pkt_len > L7_RADIUS_PKT_MAX)
		return -1;
	if (pkt[0] != RADIUS_CODE_ACCT_REQUEST)
		return -1;
	length = (uint16_t)((pkt[2] << 8) | pkt[3]);
	if (length < 20 || length > pkt_len)
		return -1;

	/* Request Authenticator = MD5(Code+ID+Len+16zero+attrs+secret) */
	seclen = strlen(secret);
	buf = malloc((size_t)length + seclen);
	if (buf == NULL)
		return -1;
	memcpy(buf, pkt, length);
	memset(buf + 4, 0, 16);
	memcpy(buf + length, secret, seclen);
	if (md5_buf(buf, (size_t)length + seclen, digest) != 0) {
		explicit_bzero(buf, (size_t)length + seclen);
		free(buf);
		return -1;
	}
	explicit_bzero(buf, (size_t)length + seclen);
	free(buf);
	if (memcmp(digest, pkt + 4, 16) != 0)
		return -1;

	attr_off = 20;
	while (attr_off + 2 <= length) {
		uint8_t atype = pkt[attr_off];
		uint8_t alen = pkt[attr_off + 1];
		const uint8_t *aval;
		size_t vlen;

		if (alen < 2 || attr_off + alen > length)
			break;
		aval = pkt + attr_off + 2;
		vlen = (size_t)alen - 2;

		if (atype == RADIUS_ATTR_USER_NAME && vlen > 0) {
			size_t n = vlen;
			if (n >= sizeof(out->user))
				n = sizeof(out->user) - 1;
			memcpy(out->user, aval, n);
			out->user[n] = '\0';
			has_user = 1;
		} else if (atype == RADIUS_ATTR_FRAMED_IP && vlen == 4) {
			uint32_t host;
			memcpy(&host, aval, 4);
			host = ntohl(host);
			if (host != 0 && host != 0xffffffffU) {
				(void)layer7_idmap_addr_set_ipv4(&out->ip, host);
				out->has_ip = 1;
			}
		} else if (atype == RADIUS_ATTR_FRAMED_IPV6 && vlen == 16) {
			(void)layer7_idmap_addr_set_ipv6(&out->ip, aval);
			out->has_ip = 1;
		} else if (atype == RADIUS_ATTR_ACCT_STATUS && vlen >= 1) {
			out->status_type = (int)attr_u32(aval, vlen);
			has_status = 1;
		}
		attr_off += alen;
	}

	if (!has_user || !has_status || !out->has_ip)
		return -1;
	if (out->status_type != L7_RADIUS_ACCT_START &&
	    out->status_type != L7_RADIUS_ACCT_STOP &&
	    out->status_type != L7_RADIUS_ACCT_INTERIM_UPDATE)
		return -1;
	out->valid = 1;
	return 0;
}

int
layer7_radius_build_response(const uint8_t *req, size_t req_len,
    const char *secret, uint8_t *out, size_t out_cap)
{
	size_t seclen;
	uint8_t *buf;
	uint8_t digest[16];

	if (req == NULL || secret == NULL || out == NULL || out_cap < 20)
		return -1;
	if (req_len < 20)
		return -1;

	out[0] = RADIUS_CODE_ACCT_RESPONSE;
	out[1] = req[1]; /* identifier */
	out[2] = 0;
	out[3] = 20; /* length */
	memcpy(out + 4, req + 4, 16); /* placeholder = request auth */

	seclen = strlen(secret);
	buf = malloc(20 + seclen);
	if (buf == NULL)
		return -1;
	memcpy(buf, out, 20);
	memcpy(buf + 20, secret, seclen);
	if (md5_buf(buf, 20 + seclen, digest) != 0) {
		explicit_bzero(buf, 20 + seclen);
		free(buf);
		return -1;
	}
	explicit_bzero(buf, 20 + seclen);
	free(buf);
	memcpy(out + 4, digest, 16);
	return 20;
}

int
layer7_radius_apply_event(struct l7_id_map *map,
    const struct l7_radius_acct_event *ev, time_t now)
{
	if (map == NULL || ev == NULL || !ev->valid || ev->user[0] == '\0')
		return -1;
	if (ev->status_type == L7_RADIUS_ACCT_STOP) {
		(void)layer7_idmap_remove_user(map, ev->user);
		return 0;
	}
	if (!ev->has_ip)
		return -1;
	return layer7_idmap_upsert(map, ev->user, &ev->ip, L7_ID_SRC_RADIUS,
	    NULL, 0, now) < 0 ? -1 : 0;
}

/* ---- worker ---- */

struct l7_radius_worker {
	pthread_t           thr;
	pthread_mutex_t     mu;
	int                 stop;
	int                 running;
	int                 sock;
	enum l7_radius_status status;
	struct l7_id_map   *map;
	struct l7_radius_cfg cfg;
	unsigned            accepted;
	unsigned            rejected;
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
radius_open_sock(const struct l7_radius_cfg *cfg)
{
	int fd;
	struct sockaddr_in sa;
	int on = 1;

	fd = socket(AF_INET, SOCK_DGRAM, 0);
	if (fd < 0)
		return -1;
	if (setsockopt(fd, SOL_SOCKET, SO_REUSEADDR, &on, sizeof(on)) != 0) {
		close(fd);
		return -1;
	}
	if (set_nonblock(fd) != 0) {
		close(fd);
		return -1;
	}
	memset(&sa, 0, sizeof(sa));
	sa.sin_family = AF_INET;
	sa.sin_port = htons((uint16_t)cfg->listen_port);
	if (cfg->bind_address[0] == '\0' ||
	    strcmp(cfg->bind_address, "0.0.0.0") == 0) {
		sa.sin_addr.s_addr = htonl(INADDR_ANY);
	} else if (inet_pton(AF_INET, cfg->bind_address, &sa.sin_addr) != 1) {
		close(fd);
		return -1;
	}
	if (bind(fd, (struct sockaddr *)&sa, sizeof(sa)) != 0) {
		close(fd);
		return -1;
	}
	return fd;
}

static void
handle_datagram(struct l7_radius_worker *w, const struct l7_radius_cfg *cfg,
    const uint8_t *pkt, size_t pkt_len, const struct sockaddr_in *peer)
{
	char peer_ip[INET_ADDRSTRLEN];
	struct l7_radius_acct_event ev;
	uint8_t resp[32];
	int rlen;
	time_t now;

	if (inet_ntop(AF_INET, &peer->sin_addr, peer_ip, sizeof(peer_ip)) ==
	    NULL) {
		w->rejected++;
		return;
	}
	if (!layer7_radius_nas_allowed(cfg, peer_ip)) {
		w->rejected++;
		L7_NOTE("identity: radius reject NAS acl peer=%s", peer_ip);
		return;
	}
	if (!cfg->secret_loaded || cfg->secret[0] == '\0') {
		w->rejected++;
		return;
	}
	if (layer7_radius_parse_accounting(pkt, pkt_len, cfg->secret, &ev) !=
	    0) {
		w->rejected++;
		return;
	}
	now = time(NULL);
	if (w->map != NULL)
		(void)layer7_radius_apply_event(w->map, &ev, now);
	w->accepted++;
	L7_NOTE("identity: radius acct user=%s status=%d peer=%s",
	    ev.user, ev.status_type, peer_ip);

	rlen = layer7_radius_build_response(pkt, pkt_len, cfg->secret, resp,
	    sizeof(resp));
	if (rlen > 0 && w->sock >= 0) {
		(void)sendto(w->sock, resp, (size_t)rlen, 0,
		    (const struct sockaddr *)peer, sizeof(*peer));
	}
}

static void *
radius_worker_main(void *arg)
{
	struct l7_radius_worker *w = arg;
	uint8_t buf[L7_RADIUS_PKT_MAX];

	while (1) {
		struct l7_radius_cfg cfg;
		int stop, sock;
		struct sockaddr_in peer;
		socklen_t peerlen;
		ssize_t n;
		fd_set rfds;
		struct timeval tv;

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
		if (!FD_ISSET(sock, &rfds))
			continue;

		peerlen = sizeof(peer);
		n = recvfrom(sock, buf, sizeof(buf), 0,
		    (struct sockaddr *)&peer, &peerlen);
		if (n < 20)
			continue;

		pthread_mutex_lock(&w->mu);
		cfg = w->cfg;
		pthread_mutex_unlock(&w->mu);
		handle_datagram(w, &cfg, buf, (size_t)n, &peer);
		layer7_radius_cfg_wipe_secret(&cfg);
	}
	return NULL;
}

struct l7_radius_worker *
layer7_radius_worker_start(struct l7_id_map *map, const struct l7_radius_cfg *cfg)
{
	struct l7_radius_worker *w;
	int fd;

	if (cfg == NULL || !cfg->radius_enabled)
		return NULL;
	if (!cfg->secret_loaded || cfg->secret[0] == '\0') {
		L7_WARN("identity: radius start refused — secret ausente");
		return NULL;
	}
	if (cfg->n_nas == 0) {
		L7_WARN("identity: radius start refused — NAS ACL vazia");
		return NULL;
	}

	fd = radius_open_sock(cfg);
	if (fd < 0) {
		L7_WARN("identity: radius bind failed port=%d addr=%s errno=%d",
		    cfg->listen_port, cfg->bind_address, errno);
		return NULL;
	}

	w = calloc(1, sizeof(*w));
	if (w == NULL) {
		close(fd);
		return NULL;
	}
	if (pthread_mutex_init(&w->mu, NULL) != 0) {
		close(fd);
		free(w);
		return NULL;
	}
	w->map = map;
	w->cfg = *cfg;
	w->sock = fd;
	w->status = L7_RADIUS_STATUS_LISTEN;

	if (pthread_create(&w->thr, NULL, radius_worker_main, w) != 0) {
		close(fd);
		pthread_mutex_destroy(&w->mu);
		layer7_radius_cfg_wipe_secret(&w->cfg);
		free(w);
		return NULL;
	}
	w->running = 1;
	L7_NOTE("identity: radius worker ON port=%d bind=%s nas=%u",
	    cfg->listen_port, cfg->bind_address, cfg->n_nas);
	return w;
}

void
layer7_radius_worker_reload(struct l7_radius_worker *w,
    const struct l7_radius_cfg *cfg)
{
	int need_rebind = 0;
	int fd = -1;

	if (w == NULL || cfg == NULL)
		return;

	pthread_mutex_lock(&w->mu);
	if (w->cfg.listen_port != cfg->listen_port ||
	    strcmp(w->cfg.bind_address, cfg->bind_address) != 0)
		need_rebind = 1;
	layer7_radius_cfg_wipe_secret(&w->cfg);
	w->cfg = *cfg;
	pthread_mutex_unlock(&w->mu);

	if (!cfg->radius_enabled)
		return;

	if (need_rebind) {
		fd = radius_open_sock(cfg);
		if (fd < 0) {
			pthread_mutex_lock(&w->mu);
			w->status = L7_RADIUS_STATUS_ERROR;
			pthread_mutex_unlock(&w->mu);
			L7_WARN("identity: radius rebind failed");
			return;
		}
		pthread_mutex_lock(&w->mu);
		if (w->sock >= 0)
			close(w->sock);
		w->sock = fd;
		w->status = L7_RADIUS_STATUS_LISTEN;
		pthread_mutex_unlock(&w->mu);
	}
	L7_NOTE("identity: radius worker reloaded enabled=%d port=%d",
	    cfg->radius_enabled, cfg->listen_port);
}

void
layer7_radius_worker_stop(struct l7_radius_worker *w)
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
	w->status = L7_RADIUS_STATUS_OFF;
	layer7_radius_cfg_wipe_secret(&w->cfg);
	pthread_mutex_destroy(&w->mu);
	free(w);
	L7_NOTE("identity: radius worker stopped");
}

enum l7_radius_status
layer7_radius_worker_status(const struct l7_radius_worker *w)
{
	if (w == NULL)
		return L7_RADIUS_STATUS_OFF;
	return w->status;
}

unsigned
layer7_radius_worker_accepted(const struct l7_radius_worker *w)
{
	return w ? w->accepted : 0;
}

unsigned
layer7_radius_worker_rejected(const struct l7_radius_worker *w)
{
	return w ? w->rejected : 0;
}
