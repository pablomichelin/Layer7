/*
 * test_identity_map.c — passo 20.12 (structs, limites ADR-0027 §4.3, rwlock).
 *
 *   cc -Wall -Wextra -O2 -I src/layer7d -o /tmp/t_idmap \
 *      tests/functional/test_identity_map.c src/layer7d/identity_map.c -lpthread \
 *      && /tmp/t_idmap
 */
#include "identity_map.h"

#include <sys/socket.h>
#include <stdio.h>
#include <string.h>

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
	struct l7_id_session slot;
	unsigned i;

	layer7_idmap_limits(&lim);
	check(lim.max_ips_per_user == 16, "limit ips/user == 16");
	check(lim.max_sessions == 4096, "limit sessions == 4096");
	check(lim.default_ttl_sec == 3600, "limit ttl == 3600");
	check(lim.max_groups_cache == 32, "limit groups cache == 32");

	check(layer7_idmap_init(NULL) == -1, "init NULL fails");
	check(layer7_idmap_init(&map) == 0, "init OK");
	check(map.initialized == 1, "initialized");
	check(layer7_idmap_capacity(&map) == 4096, "capacity 4096");
	check(layer7_idmap_count(&map) == 0, "count 0");
	check(map.default_ttl_sec == 3600, "map default ttl");

	/* Sessão cabem 16 IPs + campos ADR-0027 */
	memset(&slot, 0, sizeof(slot));
	snprintf(slot.user, sizeof(slot.user), "joao.silva");
	slot.source = L7_ID_SRC_RADIUS;
	slot.seen_at = 1000;
	slot.expires_at = 1000 + L7_IDMAP_DEFAULT_TTL_SEC;
	for (i = 0; i < L7_IDMAP_MAX_IPS_PER_USER; i++) {
		slot.ips[i].family = AF_INET;
		slot.ips[i].addr[0] = 192;
		slot.ips[i].addr[1] = 168;
		slot.ips[i].addr[2] = 100;
		slot.ips[i].addr[3] = (uint8_t)i;
		slot.n_ips++;
	}
	check(slot.n_ips == 16, "session holds 16 ips");
	snprintf(slot.groups[0], sizeof(slot.groups[0]), "TI");
	snprintf(slot.groups[1], sizeof(slot.groups[1]), "Diretoria");
	slot.n_groups = 2;
	check(slot.n_groups == 2, "groups cache slots");
	check(slot.multi_user == 0, "multi_user default 0");

	/* rwlock: write then read */
	check(layer7_idmap_wrlock(&map) == 0, "wrlock");
	map.sessions[0] = slot;
	map.sessions[0].in_use = 1;
	map.count = 1;
	check(layer7_idmap_unlock(&map) == 0, "unlock after write");

	check(layer7_idmap_rdlock(&map) == 0, "rdlock");
	check(map.sessions[0].in_use == 1, "slot visible under rdlock");
	check(strcmp(map.sessions[0].user, "joao.silva") == 0, "user preserved");
	check(map.sessions[0].source == L7_ID_SRC_RADIUS, "source RADIUS");
	check(layer7_idmap_unlock(&map) == 0, "unlock after read");

	layer7_idmap_fini(&map);
	check(map.initialized == 0, "fini clears");
	check(layer7_idmap_capacity(&map) == 0, "capacity 0 after fini");
	layer7_idmap_fini(&map); /* idempotent */
	check(1, "fini idempotent");

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
