/*
 * test_identity_dc.c — IM5 / 20.20
 * HMAC canonical, skew, parse/apply, ACL (sem TLS listen).
 */
#include "identity_dc.h"
#include "identity_map.h"

#include <stdio.h>
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

int
main(void)
{
	struct l7_dc_cfg cfg;
	struct l7_dc_event ev;
	struct l7_id_map map;
	char canon[512], hmac[65];
	const char *secret = "dc-lab-secret";
	const char *body =
	    "{\"user\":\"alice\",\"ip\":\"10.0.0.50\",\"event\":\"logon\","
	    "\"timestamp\":1700000000}";
	time_t now = 1700000000;
	const char *json =
	    "{\"layer7\":{\"identity\":{\"enabled\":true,\"dc_agent\":{"
	    "\"enabled\":true,\"listen_port\":8743,"
	    "\"bind_address\":\"10.0.0.1\",\"skew_sec\":300,"
	    "\"dc_acl\":[\"10.0.0.10\",\"10.0.0.11\"]}}}}";

	fails = 0;
	layer7_dc_cfg_defaults(&cfg);
	check(cfg.listen_port == 8743 && cfg.skew_sec == 300, "defaults");
	check(layer7_dc_cfg_parse_json(json, strlen(json), &cfg) == 0, "parse");
	check(cfg.dc_enabled == 1 && cfg.n_dc == 2, "enabled+acl");
	check(layer7_dc_peer_allowed(&cfg, "10.0.0.10") == 1, "acl allow");
	check(layer7_dc_peer_allowed(&cfg, "1.2.3.4") == 0, "acl deny");

	check(layer7_dc_build_canonical(1700000000, "POST", L7_DC_PATH,
		  (const uint8_t *)body, strlen(body), canon,
		  sizeof(canon)) == 0,
	    "canonical");
	check(layer7_dc_hmac_hex(secret, canon, hmac, sizeof(hmac)) == 0,
	    "hmac");
	check(strlen(hmac) == 64, "hmac len");
	check(layer7_dc_hmac_equal(hmac, hmac) == 1, "hmac eq");
	check(layer7_dc_hmac_equal(hmac, "00000000000000000000000000000000"
					 "00000000000000000000000000000000") ==
		0,
	    "hmac ne");

	check(layer7_dc_check_skew(now, now + 100, 300) == 0, "skew ok");
	check(layer7_dc_check_skew(now, now + 400, 300) != 0, "skew bad");

	check(layer7_dc_parse_event(body, strlen(body), &ev) == 0, "parse ev");
	check(ev.valid && strcmp(ev.user, "alice") == 0, "user");
	check(ev.type == L7_DC_EVT_LOGON && ev.has_ip, "logon+ip");

	snprintf(cfg.secret, sizeof(cfg.secret), "%s", secret);
	cfg.secret_loaded = 1;
	check(layer7_idmap_init(&map) == 0, "map");
	check(layer7_dc_handle_push(&map, &cfg, secret, hmac, 1700000000,
		  "POST", L7_DC_PATH, (const uint8_t *)body, strlen(body),
		  now) == 0,
	    "handle push");
	check(layer7_idmap_count(&map) == 1, "1 session");

	/* bad token */
	check(layer7_dc_handle_push(&map, &cfg, "wrong", hmac, 1700000000,
		  "POST", L7_DC_PATH, (const uint8_t *)body, strlen(body),
		  now) == -1,
	    "bad token");

	layer7_idmap_fini(&map);
	if (fails) {
		fprintf(stderr, "%d failures\n", fails);
		return 1;
	}
	printf("PASS: test_identity_dc\n");
	return 0;
}
