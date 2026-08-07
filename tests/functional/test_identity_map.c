/*
 * test_identity_map.c — 20.12 structs + 20.13 API.
 *
 *   cc -Wall -Wextra -O2 -I src/layer7d -o /tmp/t_idmap \
 *      tests/functional/test_identity_map.c src/layer7d/identity_map.c -lpthread \
 *      && /tmp/t_idmap
 */
#include "identity_map.h"

#include <stdio.h>
#include <string.h>
#include <sys/socket.h>

static int g_fail;

static void
check(int cond, const char *name)
{
	if (cond)
		printf("PASS: %s\n", name);
	else {
		printf("FAIL: %s\n", name);
		g_fail = 1;
	}
}

int
main(void)
{
	struct l7_id_map_limits lim;
	struct l7_id_map map;
	struct l7_id_addr ip_a, ip_b, ip_c, out_ips[8];
	char userbuf[128];
	char dump[2048];
	enum l7_id_source src;
	const char *groups_joao[] = { "TI", "VPN" };
	const char *groups_maria[] = { "RH" };
	int n;
	unsigned removed;
	time_t t0 = 1700000000;

	layer7_idmap_limits(&lim);
	check(lim.max_ips_per_user == 16, "limit ips/user == 16");
	check(lim.max_sessions == 4096, "limit sessions == 4096");
	check(lim.default_ttl_sec == 3600, "limit ttl == 3600");
	check(lim.conflict_window_sec == 60, "conflict window 60s");

	check(layer7_idmap_init(&map) == 0, "init OK");
	check(layer7_idmap_count(&map) == 0, "count 0");

	check(layer7_idmap_addr_set_ipv4(&ip_a, 0xc0a86401) == 0, "ipv4 A"); /* 192.168.100.1 */
	check(layer7_idmap_addr_set_ipv4(&ip_b, 0xc0a86402) == 0, "ipv4 B");
	check(layer7_idmap_addr_set_ipv4(&ip_c, 0xc0a86403) == 0, "ipv4 C");

	/* upsert joao */
	check(layer7_idmap_upsert(&map, "joao.silva", &ip_a, L7_ID_SRC_RADIUS,
		groups_joao, 2, t0) == 0, "upsert joao");
	check(layer7_idmap_count(&map) == 1, "count 1 after upsert");
	check(layer7_idmap_lookup_ip(&map, &ip_a, userbuf, sizeof(userbuf),
		&src) == 0, "lookup joao IP");
	check(strcmp(userbuf, "joao.silva") == 0, "lookup user name");
	check(src == L7_ID_SRC_RADIUS, "lookup source radius");

	/* segundo IP mesmo user */
	check(layer7_idmap_upsert(&map, "joao.silva", &ip_b, L7_ID_SRC_RADIUS,
		NULL, 0, t0 + 1) == 0, "upsert joao IP2");
	n = layer7_idmap_export_user_ips(&map, "joao.silva", out_ips, 8);
	check(n == 2, "export 2 ips");

	/* refresh */
	check(layer7_idmap_refresh(&map, "joao.silva", t0 + 100) == 0,
	    "refresh joao");
	check(layer7_idmap_refresh(&map, "nobody", t0) == -1,
	    "refresh missing");

	/* last-writer: maria toma IP A fora da janela */
	check(layer7_idmap_upsert(&map, "maria.souza", &ip_a, L7_ID_SRC_DC_AGENT,
		groups_maria, 1, t0 + 200) == 0, "upsert maria last-writer");
	check(layer7_idmap_lookup_ip(&map, &ip_a, userbuf, sizeof(userbuf),
		&src) == 0, "IP A agora maria");
	check(strcmp(userbuf, "maria.souza") == 0, "owner maria");
	n = layer7_idmap_export_user_ips(&map, "joao.silva", out_ips, 8);
	check(n == 1, "joao ficou com 1 IP (B)");

	/* multi_user: conflito dentro da janela (60s) */
	check(layer7_idmap_upsert(&map, "joao.silva", &ip_c, L7_ID_SRC_RADIUS,
		NULL, 0, t0 + 300) == 0, "joao IP C");
	check(layer7_idmap_upsert(&map, "maria.souza", &ip_c, L7_ID_SRC_DC_AGENT,
		NULL, 0, t0 + 310) == 0, "maria same IP C conflict");
	check(layer7_idmap_lookup_ip(&map, &ip_c, userbuf, sizeof(userbuf),
		&src) == 1, "lookup multi_user");

	/* dump JSON sem secrets */
	n = layer7_idmap_dump_json(&map, dump, sizeof(dump));
	check(n > 0, "dump json len");
	check(strstr(dump, "\"user\":\"joao.silva\"") != NULL, "dump has joao");
	check(strstr(dump, "\"status\":\"multi_user\"") != NULL ||
	    strstr(dump, "\"multi_user\":true") != NULL, "dump multi_user");
	check(strstr(dump, "password") == NULL, "dump no password key");

	/* expire */
	removed = layer7_idmap_expire(&map, t0 + 300 + 3600 + 1);
	check(removed >= 1, "expire removed some");

	/* re-seed e remove_user */
	layer7_idmap_fini(&map);
	check(layer7_idmap_init(&map) == 0, "re-init");
	check(layer7_idmap_upsert(&map, "pedro", &ip_a, L7_ID_SRC_MANUAL,
		NULL, 0, t0) == 0, "upsert pedro");
	check(layer7_idmap_remove_user(&map, "pedro") == 0, "remove pedro");
	check(layer7_idmap_count(&map) == 0, "count 0 after remove");
	check(layer7_idmap_lookup_ip(&map, &ip_a, userbuf, sizeof(userbuf),
		NULL) == -1, "lookup miss after remove");

	layer7_idmap_fini(&map);
	check(1, "fini OK");

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
