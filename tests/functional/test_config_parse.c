/*
 * test_config_parse.c — testes unitarios do parser de config do daemon.
 *
 * Cobre, em particular, o toggle `sni_inspection` (Caminho A / A3) incluindo
 * o caso em que a GUI grava a chave DEPOIS de "policies" no JSON — regressao
 * do bug corrigido em 1.8.11_22 (o gate `< policies` rejeitava a chave).
 *
 * Compila standalone (config_parse.c nao tem dependencias externas):
 *   cc -Wall -Wextra -O2 -I src/layer7d -o /tmp/t \
 *      tests/functional/test_config_parse.c src/layer7d/config_parse.c
 */
#include "config_parse.h"

#include <stdio.h>
#include <string.h>

static int g_fail;

static void
check(int cond, const char *name)
{
	if (cond) {
		printf("PASS: %s\n", name);
	} else {
		printf("FAIL: %s\n", name);
		g_fail = 1;
	}
}

int
main(void)
{
	struct layer7_parsed p;

	/* 1. sni_inspection=true ANTES de policies. */
	{
		const char *json =
		    "{\"layer7\":{\"enabled\":true,\"mode\":\"monitor\","
		    "\"sni_inspection\":true,\"policies\":[]}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0, "parse ok (sni antes)");
		check(p.has_sni_inspection == 1, "has_sni_inspection (antes)");
		check(p.sni_inspection == 1, "sni_inspection=1 (antes)");
	}

	/* 2. sni_inspection=true DEPOIS de policies (caso real da GUI). */
	{
		const char *json =
		    "{\"layer7\":{\"enabled\":true,\"mode\":\"monitor\","
		    "\"policies\":[{\"id\":\"p1\",\"enabled\":true}],"
		    "\"sni_inspection\":true}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0, "parse ok (sni depois)");
		check(p.has_sni_inspection == 1, "has_sni_inspection (depois)");
		check(p.sni_inspection == 1, "sni_inspection=1 (depois de policies)");
	}

	/* 3. sni_inspection=false explicito. */
	{
		const char *json =
		    "{\"layer7\":{\"sni_inspection\":false,\"policies\":[]}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0, "parse ok (false)");
		check(p.has_sni_inspection == 1, "has_sni_inspection (false)");
		check(p.sni_inspection == 0, "sni_inspection=0 (false)");
	}

	/* 4. ausente -> has_sni_inspection=0 (default off no daemon). */
	{
		const char *json =
		    "{\"layer7\":{\"enabled\":true,\"policies\":[]}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0, "parse ok (ausente)");
		check(p.has_sni_inspection == 0, "sem sni_inspection -> has=0");
		check(p.sni_inspection == 0, "sem sni_inspection -> valor 0");
	}

	/* 5. enabled/mode continuam a funcionar (nao regressao). */
	{
		const char *json =
		    "{\"layer7\":{\"enabled\":true,\"mode\":\"enforce\","
		    "\"policies\":[]}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0, "parse ok (enabled/mode)");
		check(p.has_enabled && p.enabled == 1, "enabled=1");
		check(p.has_mode && strcmp(p.mode, "enforce") == 0, "mode=enforce");
	}

	if (g_fail) {
		printf("\nTEST CONFIG_PARSE: FAILED\n");
		return 1;
	}
	printf("\nTEST CONFIG_PARSE: ALL PASSED\n");
	return 0;
}
