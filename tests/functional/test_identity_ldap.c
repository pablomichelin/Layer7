/*
 * test_identity_ldap.c — IM4 / 20.17
 * Cache TTL, fail-mode, parse JSON, mock expand (sem DC real).
 *
 *   cc -Wall -Wextra -O2 -I src/layer7d -DHAVE_OPENLDAP=0 \
 *      -o /tmp/test_identity_ldap \
 *      tests/functional/test_identity_ldap.c \
 *      src/layer7d/identity_ldap.c src/layer7d/identity_map.c -lpthread
 */
#include "identity_ldap.h"
#include "identity_map.h"

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
mock_expand(const struct l7_ldap_cfg *cfg, const char *group,
    char members[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out)
{
	(void)cfg;
	(void)max_out;
	if (n_out)
		*n_out = 0;
	if (group == NULL)
		return -1;
	if (strcmp(group, "fail") == 0)
		return -1;
	if (strcmp(group, "eng") == 0) {
		snprintf(members[0], L7_LDAP_NAME_MAX, "%s", "alice");
		snprintf(members[1], L7_LDAP_NAME_MAX, "%s", "bob");
		*n_out = 2;
		return 0;
	}
	return -1;
}

int
main(void)
{
	struct l7_ldap_cfg cfg;
	struct l7_ldap_cache *cache;
	char members[32][L7_LDAP_NAME_MAX];
	unsigned n = 0;
	time_t now = 1000;
	const char *json =
	    "{\"layer7\":{\"identity\":{\"enabled\":true,\"ldap\":{"
	    "\"enabled\":true,\"server\":\"dc.example.local\",\"port\":636,"
	    "\"use_tls\":true,\"bind_dn\":\"CN=svc,DC=ex\",\"base_dn\":\"DC=ex\","
	    "\"group_depth\":3,\"max_members\":100}}}}";

	fails = 0;
	layer7_ldap_cfg_defaults(&cfg);
	check(cfg.use_tls == 1 && cfg.port == 636, "defaults LDAPS");
	check(layer7_ldap_cfg_parse_json(json, strlen(json), &cfg) == 0,
	    "parse ok");
	check(cfg.identity_enabled == 1 && cfg.ldap_enabled == 1, "enabled");
	check(strcmp(cfg.server, "dc.example.local") == 0, "server");
	check(cfg.group_depth == 3 && cfg.max_members == 100, "limits");

	cache = layer7_ldap_cache_create(10);
	check(cache != NULL, "cache create");
	check(layer7_ldap_cache_status(cache, now) == L7_LDAP_STATUS_OFF,
	    "status off inicial");

	layer7_ldap_set_providers(mock_expand, NULL);
	check(layer7_ldap_resolve_group(cache, &cfg, "eng", members, 32, &n,
		  now) == 0,
	    "resolve eng");
	check(n == 2 && strcmp(members[0], "alice") == 0, "members");
	check(layer7_ldap_cache_status(cache, now) == L7_LDAP_STATUS_OK,
	    "status ok");

	/* miss directo no cache ainda válido */
	n = 0;
	check(layer7_ldap_cache_get_group(cache, "eng", members, 32, &n, now) ==
		0,
	    "cache hit");
	check(n == 2, "cache n");

	/* falha LDAP → DEGRADED com cache fresco */
	check(layer7_ldap_resolve_group(cache, &cfg, "fail", members, 32, &n,
		  now) != 0,
	    "resolve fail");
	check(layer7_ldap_cache_status(cache, now + 1) == L7_LDAP_STATUS_DEGRADED ||
		layer7_ldap_cache_status(cache, now + 1) == L7_LDAP_STATUS_OK,
	    "degraded or ok while cache fresh");
	/* eng ainda servido do cache */
	n = 0;
	check(layer7_ldap_cache_get_group(cache, "eng", members, 32, &n,
		  now + 1) == 0,
	    "cache hit apos fail");

	/* após TTL: DOWN */
	layer7_ldap_cache_mark_fail(cache, now + 20);
	check(layer7_ldap_cache_status(cache, now + 20) == L7_LDAP_STATUS_DOWN,
	    "status down apos TTL");
	check(layer7_ldap_cache_get_group(cache, "eng", members, 32, &n,
		  now + 20) == -2,
	    "get group DOWN");

	/* set_groups no mapa */
	{
		struct l7_id_map m;
		struct l7_id_addr ip;
		const char *g[] = { "eng", "vpn" };
		char users[4][L7_IDMAP_USER_MAX];

		check(layer7_idmap_init(&m) == 0, "idmap init");
		layer7_idmap_addr_set_ipv4(&ip, 0x0a000001);
		check(layer7_idmap_upsert(&m, "alice", &ip, L7_ID_SRC_MANUAL,
			  NULL, 0, now) == 0,
		    "upsert");
		check(layer7_idmap_set_groups(&m, "alice", g, 2) == 0,
		    "set_groups");
		check(layer7_idmap_list_users(&m, users, 4) == 1, "list_users");
		check(strcmp(users[0], "alice") == 0, "user name");
		check(layer7_idmap_set_groups(&m, "alice", NULL, 0) == 0,
		    "clear groups");
		layer7_idmap_fini(&m);
	}

	layer7_ldap_cache_destroy(cache);
	layer7_ldap_set_providers(NULL, NULL);

	if (fails) {
		fprintf(stderr, "%d checks failed\n", fails);
		return 1;
	}
	printf("PASS: test_identity_ldap\n");
	return 0;
}
