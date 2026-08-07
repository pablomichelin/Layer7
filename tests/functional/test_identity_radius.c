/*
 * test_identity_radius.c — IM5 / 20.19
 * Parse JSON, ACL, Authenticator MD5, apply Start/Stop ao mapa.
 *
 *   cc -Wall -Wextra -O2 -I src/layer7d \
 *      -o /tmp/test_identity_radius \
 *      tests/functional/test_identity_radius.c \
 *      src/layer7d/identity_radius.c src/layer7d/identity_map.c \
 *      -lpthread -lcrypto
 */
#include "identity_radius.h"
#include "identity_map.h"

#include <arpa/inet.h>
#include <openssl/evp.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

static int fails;

static void
check(int cond, const char *msg)
{
	if (!cond) {
		fprintf(stderr, "FAIL: %s\n", msg);
		fails++;
	}
}

static int
md5_raw(const uint8_t *data, size_t len, uint8_t out[16])
{
	EVP_MD_CTX *ctx = EVP_MD_CTX_new();
	unsigned int outlen = 0;
	int ok;

	if (ctx == NULL)
		return -1;
	ok = EVP_DigestInit_ex(ctx, EVP_md5(), NULL) == 1 &&
	    EVP_DigestUpdate(ctx, data, len) == 1 &&
	    EVP_DigestFinal_ex(ctx, out, &outlen) == 1 &&
	    outlen == 16;
	EVP_MD_CTX_free(ctx);
	return ok ? 0 : -1;
}

/* Constrói Accounting-Request mínimo válido. */
static size_t
build_acct_req(uint8_t *pkt, size_t cap, const char *secret,
    const char *user, uint32_t framed_host, int status)
{
	uint8_t *p;
	size_t seclen, body_len;
	uint8_t *tmp;
	uint8_t digest[16];
	uint32_t netip, st;

	if (cap < 64)
		return 0;
	memset(pkt, 0, cap);
	pkt[0] = 4; /* Accounting-Request */
	pkt[1] = 7; /* id */
	p = pkt + 20;

	/* User-Name */
	*p++ = 1;
	*p++ = (uint8_t)(2 + strlen(user));
	memcpy(p, user, strlen(user));
	p += strlen(user);

	/* Framed-IP-Address */
	*p++ = 8;
	*p++ = 6;
	netip = htonl(framed_host);
	memcpy(p, &netip, 4);
	p += 4;

	/* Acct-Status-Type */
	*p++ = 40;
	*p++ = 6;
	st = htonl((uint32_t)status);
	memcpy(p, &st, 4);
	p += 4;

	body_len = (size_t)(p - pkt);
	pkt[2] = (uint8_t)((body_len >> 8) & 0xff);
	pkt[3] = (uint8_t)(body_len & 0xff);

	seclen = strlen(secret);
	tmp = malloc(body_len + seclen);
	if (tmp == NULL)
		return 0;
	memcpy(tmp, pkt, body_len);
	memset(tmp + 4, 0, 16);
	memcpy(tmp + body_len, secret, seclen);
	if (md5_raw(tmp, body_len + seclen, digest) != 0) {
		free(tmp);
		return 0;
	}
	free(tmp);
	memcpy(pkt + 4, digest, 16);
	return body_len;
}

int
main(void)
{
	struct l7_radius_cfg cfg;
	struct l7_radius_acct_event ev;
	struct l7_id_map map;
	uint8_t pkt[256];
	size_t plen;
	const char *secret = "lab-secret";
	const char *json =
	    "{\"layer7\":{\"identity\":{\"enabled\":true,\"radius\":{"
	    "\"enabled\":true,\"listen_port\":1813,"
	    "\"bind_address\":\"10.0.0.1\","
	    "\"nas_acl\":[\"192.168.1.10\",\"10.1.1.1\"]}}}}";
	char userbuf[L7_IDMAP_USER_MAX];
	enum l7_id_source src;
	time_t now = 2000;

	fails = 0;
	layer7_radius_cfg_defaults(&cfg);
	check(cfg.listen_port == 1813, "default port");
	check(cfg.radius_enabled == 0, "default OFF");

	check(layer7_radius_cfg_parse_json(json, strlen(json), &cfg) == 0,
	    "parse json");
	check(cfg.radius_enabled == 1, "radius enabled");
	check(cfg.listen_port == 1813, "port");
	check(strcmp(cfg.bind_address, "10.0.0.1") == 0, "bind");
	check(cfg.n_nas == 2, "nas count");
	check(layer7_radius_nas_allowed(&cfg, "192.168.1.10") == 1, "acl allow");
	check(layer7_radius_nas_allowed(&cfg, "8.8.8.8") == 0, "acl deny");

	cfg.n_nas = 0;
	check(layer7_radius_nas_allowed(&cfg, "192.168.1.10") == 0,
	    "empty acl deny");

	plen = build_acct_req(pkt, sizeof(pkt), secret, "alice",
	    0x0a000064 /* 10.0.0.100 */, L7_RADIUS_ACCT_START);
	check(plen >= 20, "build start");
	check(layer7_radius_parse_accounting(pkt, plen, secret, &ev) == 0,
	    "parse start");
	check(ev.valid && strcmp(ev.user, "alice") == 0, "user");
	check(ev.status_type == L7_RADIUS_ACCT_START && ev.has_ip, "start+ip");

	/* secret errado */
	check(layer7_radius_parse_accounting(pkt, plen, "wrong", &ev) != 0,
	    "bad secret");

	/* re-parse com secret correcto antes de aplicar */
	check(layer7_radius_parse_accounting(pkt, plen, secret, &ev) == 0,
	    "reparse start");
	check(layer7_idmap_init(&map) == 0, "map init");
	check(layer7_radius_apply_event(&map, &ev, now) == 0, "apply start");
	check(layer7_idmap_count(&map) == 1, "1 session");
	check(layer7_idmap_lookup_ip(&map, &ev.ip, userbuf, sizeof(userbuf),
		  &src) == 0,
	    "lookup");
	check(strcmp(userbuf, "alice") == 0 && src == L7_ID_SRC_RADIUS,
	    "src radius");

	plen = build_acct_req(pkt, sizeof(pkt), secret, "alice",
	    0x0a000064, L7_RADIUS_ACCT_STOP);
	check(layer7_radius_parse_accounting(pkt, plen, secret, &ev) == 0,
	    "parse stop");
	check(layer7_radius_apply_event(&map, &ev, now + 1) == 0, "apply stop");
	check(layer7_idmap_count(&map) == 0, "removed");

	{
		uint8_t resp[32];
		plen = build_acct_req(pkt, sizeof(pkt), secret, "bob",
		    0x0a000065, L7_RADIUS_ACCT_INTERIM_UPDATE);
		check(layer7_radius_build_response(pkt, plen, secret, resp,
			  sizeof(resp)) == 20,
		    "response len");
		check(resp[0] == 5 && resp[1] == pkt[1], "response code/id");
	}

	layer7_idmap_fini(&map);

	if (fails) {
		fprintf(stderr, "%d failures\n", fails);
		return 1;
	}
	printf("PASS: test_identity_radius\n");
	return 0;
}
