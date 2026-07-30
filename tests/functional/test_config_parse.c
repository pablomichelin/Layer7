/*
 * test_config_parse.c — testes unitarios do parser de config do daemon.
 *
 * Cobre, em particular, o toggle `sni_inspection` (Caminho A / A3) incluindo
 * o caso em que a GUI grava a chave DEPOIS de "policies" no JSON — regressao
 * do bug corrigido em 1.8.11_22 (o gate `< policies` rejeitava a chave).
 *
 * Compila standalone (config_parse.c nao tem dependencias externas):
 *   cc -Wall -Wextra -O2 -I src/layer7d -o /tmp/t \
 *      tests/functional/test_config_parse.c src/layer7d/config_parse.c \
 *      src/layer7d/policy.c src/layer7d/enforce.c
 */
#include "config_parse.h"
#include "policy.h"

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

	/* 6. enforcement_model ausente -> has=0 (default legacy_global). */
	{
		const char *json =
		    "{\"layer7\":{\"enabled\":true,\"policies\":[]}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0, "parse ok (sem enforcement_model)");
		check(p.has_enforcement_model == 0, "sem enforcement_model -> has=0");
	}

	/* 7. enforcement_model=legacy_global explicito. */
	{
		const char *json =
		    "{\"layer7\":{\"enforcement_model\":\"legacy_global\","
		    "\"policies\":[]}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0, "parse ok (legacy_global)");
		check(p.has_enforcement_model == 1, "has_enforcement_model (legacy)");
		check(strcmp(p.enforcement_model, "legacy_global") == 0,
		    "enforcement_model=legacy_global");
	}

	/* 8. enforcement_model=scoped_hybrid depois de policies (GUI). */
	{
		const char *json =
		    "{\"layer7\":{\"policies\":[],"
		    "\"enforcement_model\":\"scoped_hybrid\"}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0, "parse ok (scoped_hybrid)");
		check(p.has_enforcement_model == 1, "has_enforcement_model (scoped)");
		check(strcmp(p.enforcement_model, "scoped_hybrid") == 0,
		    "enforcement_model=scoped_hybrid");
	}

	/* 9. valor invalido -> has=0. */
	{
		const char *json =
		    "{\"layer7\":{\"enforcement_model\":\"invalid\","
		    "\"policies\":[]}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0, "parse ok (invalid model)");
		check(p.has_enforcement_model == 0, "invalid enforcement_model -> has=0");
	}

	/* 10. limites locais e log detalhado chegam ao daemon. */
	{
		const char *json =
		    "{\"layer7\":{\"log_file_max_mb\":10,\"log_file_keep\":4,"
		    "\"reports\":{\"event_log_enabled\":true,"
		    "\"event_interfaces\":[\"vmx0\",\"vmx0.10\"]},"
		    "\"policies\":[]}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0,
		    "parse ok (logging L1)");
		check(p.has_log_file_max_mb && p.log_file_max_mb == 10,
		    "log_file_max_mb=10");
		check(p.has_log_file_keep && p.log_file_keep == 4,
		    "log_file_keep=4");
		check(p.has_event_log_enabled && p.event_log_enabled == 1,
		    "event_log_enabled=1");
		check(p.n_event_interfaces == 2,
		    "duas interfaces de eventos");
		check(strcmp(p.event_interfaces[0], "vmx0") == 0 &&
		    strcmp(p.event_interfaces[1], "vmx0.10") == 0,
		    "interfaces de eventos preservadas");
	}

	/* 11. limites fora da faixa falham fechado para defaults runtime. */
	{
		const char *json =
		    "{\"layer7\":{\"log_file_max_mb\":0,\"log_file_keep\":99,"
		    "\"reports\":{\"event_log_enabled\":false},"
		    "\"policies\":[]}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0,
		    "parse ok (logging invalido)");
		check(p.has_log_file_max_mb == 0,
		    "log_file_max_mb invalido ignorado");
		check(p.has_log_file_keep == 0,
		    "log_file_keep invalido ignorado");
		check(p.has_event_log_enabled && p.event_log_enabled == 0,
		    "event_log_enabled=false explicito");
	}

	/* 12. JSON sem objeto layer7 -> falha fechada. */
	{
		const char *json = "{\"foo\":1}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) != 0,
		    "parse falha sem layer7");
		check(p.has_layer7 == 0, "sem layer7 -> has_layer7=0");
	}

	/* 13. JSON truncado apos layer7 -> falha ou parse parcial documentado. */
	{
		const char *json = "{\"layer7\":{\"enabled\":true";
		memset(&p, 0, sizeof(p));
		(void)layer7_parse_json(json, 0, &p);
		/* FP-015: parser heurístico pode aceitar truncado se a chave
		 * aparecer — este teste documenta o comportamento actual. */
		check(p.has_layer7 == 1, "truncado: layer7 detectado");
	}

	/* 14. Campos desconhecidos no topo nao impedem enabled/mode. */
	{
		const char *json =
		    "{\"layer7\":{\"unknown_field\":123,\"enabled\":true,"
		    "\"mode\":\"monitor\",\"policies\":[]}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0,
		    "parse ok com campo desconhecido");
		check(p.has_enabled && p.enabled == 1,
		    "enabled preservado com unknown_field");
	}

	/* 15. FP-015 — enabled dentro de policies confunde parser (fragilidade). */
	{
		const char *json =
		    "{\"layer7\":{\"policies\":[{\"id\":\"p1\","
		    "\"enabled\":false}],\"enabled\":true,\"mode\":\"enforce\"}}";
		memset(&p, 0, sizeof(p));
		check(layer7_parse_json(json, 0, &p) == 0,
		    "parse ok (FP-015 cenario)");
		/* Comportamento actual: primeiro \"enabled\" e o da policy. */
		check(p.has_enabled == 1 && p.enabled == 0,
		    "FP-015: enabled de policy vence (fragil)");
	}

	/* 16. BG-066 — parse src_exclude_* (ADR-0019). */
	{
		struct layer7_policy_rule rules[2];
		int n = 0;
		const char *json =
		    "{\"layer7\":{\"policies\":[{\"id\":\"p-exc\","
		    "\"action\":\"block\",\"enabled\":true,\"match\":{"
		    "\"src_exclude_cidrs\":[\"10.0.0.50/32\"],"
		    "\"src_exclude_groups\":[\"gestores\"],"
		    "\"hosts\":[\"example.com\"]}]}}";

		memset(rules, 0, sizeof(rules));
		check(layer7_policies_parse(json, strlen(json), rules, &n, 2) == 0,
		    "parse ok (src_exclude fields)");
		check(n == 1, "policy with src_exclude loaded");
		check(rules[0].n_src_exclude_cidrs == 1,
		    "src_exclude_cidrs parsed");
		check(rules[0].n_src_exclude_groups == 1,
		    "src_exclude_groups parsed");
	}

	if (g_fail) {
		printf("\nTEST CONFIG_PARSE: FAILED\n");
		return 1;
	}
	printf("\nTEST CONFIG_PARSE: ALL PASSED\n");
	return 0;
}
