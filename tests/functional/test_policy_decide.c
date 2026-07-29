/*
 * test_policy_decide.c — Caminho B / E1: motor de decisao unificado.
 *
 * Compila standalone:
 *   cc -Wall -Wextra -O2 -I src/layer7d -o /tmp/test_policy_decide \
 *      tests/functional/test_policy_decide.c \
 *      src/layer7d/policy.c src/layer7d/enforce.c
 */
#include "policy.h"
#include "enforce.h"

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

static void
init_youtube_block_policy(struct layer7_policy_rule *r, const char *id,
    int priority, const char *src_host)
{
	memset(r, 0, sizeof(*r));
	snprintf(r->id, sizeof(r->id), "%s", id);
	snprintf(r->name, sizeof(r->name), "Block YouTube");
	r->enabled = 1;
	r->action = LAYER7_ACTION_BLOCK;
	r->priority = priority;
	snprintf(r->hosts[0], sizeof(r->hosts[0]), "youtube.com");
	r->n_hosts = 1;
	if (src_host) {
		snprintf(r->src_hosts[0], sizeof(r->src_hosts[0]), "%s",
		    src_host);
		r->n_src_hosts = 1;
	}
}

static void
test_block_youtube_matching_client(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;

	init_youtube_block_policy(&rules[0], "p-yt", 10, "10.0.0.10");
	layer7_policies_sort(rules, 1);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.10", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "1 decide ok");
	check(dec.action == LAYER7_ACTION_BLOCK, "1 action block");
	check(dec.reason == L7_DECIDE_POLICY_MATCH, "1 reason policy");
	check(dec.would_enforce_block_or_tag == 1, "1 would enforce");
	check(dec.enforce_kind == L7_ENFORCE_DST_SCOPED, "1 enforce dst");
	check(dec.source_scoped == 1, "1 static source scope");
	check(dec.policy_table_idx == 0, "1 table idx 0");
	check(strcmp(dec.matched_policy_id, "p-yt") == 0, "1 policy id");
}

static void
test_block_youtube_other_client_default(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;

	init_youtube_block_policy(&rules[0], "p-yt", 10, "10.0.0.10");
	layer7_policies_sort(rules, 1);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.20", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "2 decide ok");
	check(dec.action == LAYER7_ACTION_ALLOW, "2 action allow default");
	check(dec.reason == L7_DECIDE_DEFAULT_ALLOW, "2 reason default allow");
	check(dec.would_enforce_block_or_tag == 0, "2 no enforce");
}

static void
test_exception_allow_prevails(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_exception exc[1];
	struct layer7_decision dec;

	init_youtube_block_policy(&rules[0], "p-yt", 10, "10.0.0.10");
	layer7_policies_sort(rules, 1);

	memset(exc, 0, sizeof(exc));
	snprintf(exc[0].id, sizeof(exc[0].id), "exc-allow");
	exc[0].enabled = 1;
	exc[0].action = LAYER7_ACTION_ALLOW;
	exc[0].priority = 50;
	snprintf(exc[0].hosts[0], sizeof(exc[0].hosts[0]), "10.0.0.10");
	exc[0].n_hosts = 1;
	layer7_exceptions_sort(exc, 1);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(exc, 1, rules, 1, 1, NULL,
	    "10.0.0.10", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "3 decide ok");
	check(dec.action == LAYER7_ACTION_ALLOW, "3 action allow");
	check(dec.reason == L7_DECIDE_EXCEPTION, "3 reason exception");
	check(dec.would_enforce_block_or_tag == 0, "3 no enforce");
}

static void
test_allow_higher_priority_wins(void)
{
	struct layer7_policy_rule rules[2];
	struct layer7_decision dec;

	init_youtube_block_policy(&rules[0], "p-block", 10, NULL);
	init_youtube_block_policy(&rules[1], "p-allow", 100, NULL);
	rules[1].action = LAYER7_ACTION_ALLOW;
	layer7_policies_sort(rules, 2);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 2, 1, NULL,
	    "10.0.0.10", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "4 decide ok");
	check(dec.action == LAYER7_ACTION_ALLOW, "4 action allow");
	check(dec.reason == L7_DECIDE_POLICY_MATCH, "4 reason policy allow");
	check(strcmp(dec.matched_policy_id, "p-allow") == 0, "4 allow policy");
	check(dec.would_enforce_block_or_tag == 0, "4 no enforce");
}

static void
test_schedule_inactive_no_block(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;

	init_youtube_block_policy(&rules[0], "p-yt", 10, "10.0.0.10");
	rules[0].schedule.has_schedule = 1;
	rules[0].schedule.days = 0; /* nunca activo */
	rules[0].schedule.start_min = 0;
	rules[0].schedule.end_min = 1439;
	layer7_policies_sort(rules, 1);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.10", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "5 decide ok");
	check(dec.action == LAYER7_ACTION_ALLOW, "5 default allow");
	check(dec.reason == L7_DECIDE_DEFAULT_ALLOW, "5 reason default");
	check(dec.would_enforce_block_or_tag == 0, "5 no enforce");
}

static void
test_disabled_policy_no_block(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;

	init_youtube_block_policy(&rules[0], "p-yt", 10, "10.0.0.10");
	rules[0].enabled = 0;
	layer7_policies_sort(rules, 1);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.10", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "6 decide ok");
	check(dec.action == LAYER7_ACTION_ALLOW, "6 default allow");
	check(dec.reason == L7_DECIDE_DEFAULT_ALLOW, "6 reason default");
	check(dec.would_enforce_block_or_tag == 0, "6 no enforce");
}

static void
test_group_expanded_src_cidr(void)
{
	struct layer7_group groups[1];
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec_in, dec_out;

	memset(groups, 0, sizeof(groups));
	snprintf(groups[0].id, sizeof(groups[0].id), "g-lan");
	groups[0].cidrs[0].net = (10U << 24) | (0U << 16) | (0U << 8) | 0U;
	groups[0].cidrs[0].prefix = 24;
	groups[0].n_cidrs = 1;

	memset(&rules[0], 0, sizeof(rules[0]));
	snprintf(rules[0].id, sizeof(rules[0].id), "p-grp");
	rules[0].enabled = 1;
	rules[0].action = LAYER7_ACTION_BLOCK;
	rules[0].priority = 10;
	snprintf(rules[0].hosts[0], sizeof(rules[0].hosts[0]), "youtube.com");
	rules[0].n_hosts = 1;
	snprintf(rules[0].groups[0], sizeof(rules[0].groups[0]), "g-lan");
	rules[0].n_groups = 1;
	layer7_policies_expand_groups(rules, 1, groups, 1);
	layer7_policies_sort(rules, 1);

	memset(&dec_in, 0, sizeof(dec_in));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.10", "www.youtube.com", NULL, NULL, &dec_in) == 0,
	    "7a decide in-group ok");
	check(dec_in.action == LAYER7_ACTION_BLOCK, "7a block in group");
	check(dec_in.reason == L7_DECIDE_POLICY_MATCH, "7a policy match");

	memset(&dec_out, 0, sizeof(dec_out));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.1.10", "www.youtube.com", NULL, NULL, &dec_out) == 0,
	    "7b decide out-group ok");
	check(dec_out.action == LAYER7_ACTION_ALLOW, "7b allow out of group");
	check(dec_out.reason == L7_DECIDE_DEFAULT_ALLOW, "7b default allow");
}

static void
test_policy_table_index_sorted(void)
{
	struct layer7_policy_rule rules[3];

	memset(rules, 0, sizeof(rules));
	snprintf(rules[0].id, sizeof(rules[0].id), "low");
	rules[0].priority = 5;
	snprintf(rules[1].id, sizeof(rules[1].id), "high");
	rules[1].priority = 100;
	snprintf(rules[2].id, sizeof(rules[2].id), "mid");
	rules[2].priority = 50;
	layer7_policies_sort(rules, 3);

	check(layer7_policy_table_index(rules, 3, "high") == 0,
	    "idx high first after sort");
	check(layer7_policy_table_index(rules, 3, "mid") == 1,
	    "idx mid second");
	check(layer7_policy_table_index(rules, 3, "low") == 2,
	    "idx low third");
	check(layer7_policy_table_index(rules, 3, "missing") == -1,
	    "idx missing -1");
}

static void
test_empty_block_rejected(void)
{
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"bad-empty\","
	    "\"action\":\"block\",\"enabled\":true,\"match\":{}}]}}";
	struct layer7_policy_rule rules[4];
	int n = 0;

	check(layer7_policies_parse(json, strlen(json), rules, &n, 4) == 0,
	    "empty block parse returns 0");
	check(n == 0, "empty block policy rejected");
}

static void
test_scope_global_parse(void)
{
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"q1\",\"action\":\"block\","
	    "\"enabled\":true,\"scope_global\":true,\"match\":{}}]}}";
	struct layer7_policy_rule rules[4];
	int n = 0;

	check(layer7_policies_parse(json, strlen(json), rules, &n, 4) == 0,
	    "scope_global parse ok");
	check(n == 1, "scope_global empty block accepted");
	check(rules[0].scope_global == 1, "scope_global flag set");
}

static int
dec_applies_psrc(const struct layer7_decision *dec)
{
	return dec && dec->action == LAYER7_ACTION_BLOCK &&
	    dec->enforce_kind == L7_ENFORCE_SRC_SCOPED &&
	    (dec->quarantine_origin || dec->source_scoped ||
	    dec->scope_global);
}

static void
test_app_only_quarantine_false_no_runtime_psrc(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"app0\",\"action\":\"block\","
	    "\"enabled\":true,\"match\":{\"ndpi_app\":[\"YouTube\"]}}]}}";
	int n = 0;

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "q0 parse ok");
	check(n == 1, "q0 policy loaded");
	check(rules[0].quarantine_origin == 0, "q0 quarantine_origin default 0");
	layer7_policies_sort(rules, 1);
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.5", NULL, "YouTube", "Streaming", &dec) == 0,
	    "q0 decide ok");
	check(dec.enforce_kind == L7_ENFORCE_SRC_SCOPED, "q0 src scoped kind");
	check(dec.quarantine_origin == 0, "q0 dec quarantine_origin unset");
	check(!dec_applies_psrc(&dec),
	    "q0 runtime must not apply psrc quarantine");
}

static void
test_app_only_quarantine_true_allows_psrc(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	char tbl[64];
	const char *ip;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"app1\",\"action\":\"block\","
	    "\"enabled\":true,\"quarantine_origin\":true,"
	    "\"match\":{\"ndpi_app\":[\"YouTube\"]}}]}}";
	int n = 0;
	int r;

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "q1 parse ok");
	check(n == 1, "q1 policy loaded");
	check(rules[0].quarantine_origin == 1, "q1 quarantine_origin set");
	layer7_policies_sort(rules, 1);
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.5", NULL, "YouTube", "Streaming", &dec) == 0,
	    "q1 decide ok");
	check(dec.enforce_kind == L7_ENFORCE_SRC_SCOPED, "q1 src scoped kind");
	check(dec.quarantine_origin == 1, "q1 dec quarantine_origin set");
	check(dec_applies_psrc(&dec),
	    "q1 runtime may apply psrc quarantine");
	r = layer7_pf_resolve_block_target(&dec, "10.0.0.5", NULL, 1,
	    tbl, sizeof(tbl), &ip);
	check(r == 1 && strcmp(tbl, "layer7_psrc_0") == 0 &&
	    ip && strcmp(ip, "10.0.0.5") == 0,
	    "q1 resolves psrc table and src ip");
}

static void
test_app_only_no_quarantine_decision(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"app1\",\"action\":\"block\","
	    "\"enabled\":true,\"match\":{\"ndpi_app\":[\"YouTube\"]}}]}}";
	int n = 0;

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "app-only parse ok");
	check(n == 1, "app-only policy loaded");
	check(rules[0].quarantine_origin == 0, "quarantine_origin default 0");
	layer7_policies_sort(rules, 1);
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.5", NULL, "YouTube", "Streaming", &dec) == 0,
	    "app-only decide ok");
	check(dec.enforce_kind == L7_ENFORCE_SRC_SCOPED, "app-only src scoped");
	check(dec.quarantine_origin == 0, "dec quarantine_origin unset");
}

static void
test_app_static_source_allows_psrc(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"app-src\","
	    "\"action\":\"block\",\"enabled\":true,\"match\":{"
	    "\"ndpi_app\":[\"YouTube\"],\"src_hosts\":[\"10.0.0.5\"]}}]}}";
	int n = 0;

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "app-src parse ok");
	check(n == 1, "app-src policy loaded");
	layer7_policies_sort(rules, 1);
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.5", NULL, "YouTube", "Streaming", &dec) == 0,
	    "app-src decide ok");
	check(dec.enforce_kind == L7_ENFORCE_SRC_SCOPED,
	    "app-src chooses psrc");
	check(dec.source_scoped == 1, "app-src source scope propagated");
	check(dec.quarantine_origin == 0, "app-src no explicit quarantine");
	check(dec_applies_psrc(&dec), "app-src runtime may populate psrc");
}

static void
test_mixed_app_host_uses_matched_path(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec_app, dec_host;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"mixed\","
	    "\"action\":\"block\",\"enabled\":true,\"match\":{"
	    "\"ndpi_app\":[\"YouTube\"],\"hosts\":[\"youtube.com\"],"
	    "\"src_hosts\":[\"10.0.0.5\"]}}]}}";
	int n = 0;

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "mixed parse ok");
	check(n == 1, "mixed policy loaded");
	layer7_policies_sort(rules, 1);

	memset(&dec_app, 0, sizeof(dec_app));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.5", "other.example", "YouTube", "Streaming",
	    &dec_app) == 0, "mixed app path decide");
	check(dec_app.action == LAYER7_ACTION_BLOCK, "mixed app path block");
	check(dec_app.enforce_kind == L7_ENFORCE_SRC_SCOPED,
	    "mixed app path chooses psrc");

	memset(&dec_host, 0, sizeof(dec_host));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.5", "www.youtube.com", "TLS", "Web",
	    &dec_host) == 0, "mixed host path decide");
	check(dec_host.action == LAYER7_ACTION_BLOCK, "mixed host path block");
	check(dec_host.enforce_kind == L7_ENFORCE_DST_SCOPED,
	    "mixed host path chooses pdst");
}

int
main(void)
{
	test_block_youtube_matching_client();
	test_block_youtube_other_client_default();
	test_exception_allow_prevails();
	test_allow_higher_priority_wins();
	test_schedule_inactive_no_block();
	test_disabled_policy_no_block();
	test_group_expanded_src_cidr();
	test_policy_table_index_sorted();
	test_empty_block_rejected();
	test_scope_global_parse();
	test_app_only_quarantine_false_no_runtime_psrc();
	test_app_only_quarantine_true_allows_psrc();
	test_app_only_no_quarantine_decision();
	test_app_static_source_allows_psrc();
	test_mixed_app_host_uses_matched_path();

	if (g_fail) {
		printf("\nSOME TESTS FAILED\n");
		return 1;
	}
	printf("\nALL POLICY DECIDE TESTS PASSED\n");
	return 0;
}
