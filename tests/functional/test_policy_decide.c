/*
 * test_policy_decide.c — Caminho B / E1: motor de decisao unificado.
 *
 * Compila standalone:
 *   cc -Wall -Wextra -O2 -I src/layer7d -o /tmp/test_policy_decide \
 *      tests/functional/test_policy_decide.c \
 *      src/layer7d/policy.c src/layer7d/enforce.c src/layer7d/identity_map.c \
 *      -lpthread
 */
#include "policy.h"
#include "enforce.h"
#include "identity_map.h"

#include <arpa/inet.h>
#include <stdio.h>
#include <string.h>
#include <sys/socket.h>
#include <time.h>

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
	check(!layer7_decision_is_explicit_allow(&dec),
	    "2 default allow still evaluates blacklist");
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
	check(layer7_decision_is_explicit_allow(&dec),
	    "3 exception allow bypasses blacklist");
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
	check(layer7_decision_is_explicit_allow(&dec),
	    "4 policy allow bypasses blacklist");
	check(dec.policy_table_idx == 0, "4 policy allow keeps table idx");
	check(dec.enforce_kind == L7_ENFORCE_DST_SCOPED,
	    "4 policy allow keeps matched destination kind");
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
	groups[0].cidrs[0].family = AF_INET;
	groups[0].cidrs[0].addr.v4 =
	    (10U << 24) | (0U << 16) | (0U << 8) | 0U;
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
test_catch_all_monitor_does_not_shadow_specific_block(void)
{
	struct layer7_policy_rule rules[2];
	struct layer7_decision dec;

	memset(rules, 0, sizeof(rules));
	/* Catch-all monitor com priority MAIOR — bug QA D2 se sombrear. */
	snprintf(rules[0].id, sizeof(rules[0].id), "p-mon-001");
	rules[0].enabled = 1;
	rules[0].action = LAYER7_ACTION_MONITOR;
	rules[0].priority = 10;
	snprintf(rules[1].id, sizeof(rules[1].id), "qa-yt-block-a");
	rules[1].enabled = 1;
	rules[1].action = LAYER7_ACTION_BLOCK;
	rules[1].priority = 5;
	snprintf(rules[1].hosts[0], sizeof(rules[1].hosts[0]), "youtube.com");
	rules[1].n_hosts = 1;
	snprintf(rules[1].src_hosts[0], sizeof(rules[1].src_hosts[0]),
	    "192.168.100.234");
	rules[1].n_src_hosts = 1;
	layer7_policies_sort(rules, 2);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 2, 1, "vmx0",
	    "192.168.100.234", "www.youtube.com", "TLS", "Web", &dec) == 0,
	    "catch-all decide ok");
	check(dec.action == LAYER7_ACTION_BLOCK,
	    "catch-all: specific block wins over higher-pri monitor");
	check(strcmp(dec.matched_policy_id, "qa-yt-block-a") == 0,
	    "catch-all: matched youtube policy");

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 2, 1, "vmx0",
	    "192.168.100.235", "www.youtube.com", "TLS", "Web", &dec) == 0,
	    "catch-all other client decide ok");
	check(dec.action == LAYER7_ACTION_MONITOR,
	    "catch-all: other client falls to monitor");
	check(strcmp(dec.matched_policy_id, "p-mon-001") == 0,
	    "catch-all: other client matched monitor");
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
	check(dec.enforce_kind == L7_ENFORCE_DST_SCOPED, "q0 dst scoped kind");
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
	check(dec.enforce_kind == L7_ENFORCE_DST_SCOPED, "app-only dst scoped");
	check(dec.quarantine_origin == 0, "dec quarantine_origin unset");
}

static void
test_app_static_source_uses_pdst(void)
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
	check(dec.enforce_kind == L7_ENFORCE_DST_SCOPED,
	    "app-src chooses pdst");
	check(dec.source_scoped == 1, "app-src source scope propagated");
	check(dec.quarantine_origin == 0, "app-src no explicit quarantine");
	check(!dec_applies_psrc(&dec), "app-src must not quarantine source");
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
	check(dec_app.enforce_kind == L7_ENFORCE_DST_SCOPED,
	    "mixed app path chooses pdst");

	memset(&dec_host, 0, sizeof(dec_host));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.5", "www.youtube.com", "TLS", "Web",
	    &dec_host) == 0, "mixed host path decide");
	check(dec_host.action == LAYER7_ACTION_BLOCK, "mixed host path block");
	check(dec_host.enforce_kind == L7_ENFORCE_DST_SCOPED,
	    "mixed host path chooses pdst");
}

static void
test_src_exclude_cidr_no_match(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec_in, dec_out;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"yt-exc\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":10,"
	    "\"match\":{\"hosts\":[\"youtube.com\"],"
	    "\"src_cidrs\":[\"10.0.0.0/24\"],"
	    "\"src_exclude_cidrs\":[\"10.0.0.50/32\"]}}]}}";
	int n = 0;

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "exclude parse ok");
	check(n == 1, "exclude policy loaded");
	check(rules[0].n_src_exclude_cidrs == 1, "exclude cidr count");
	layer7_policies_sort(rules, 1);

	memset(&dec_in, 0, sizeof(dec_in));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.10", "www.youtube.com", NULL, NULL, &dec_in) == 0,
	    "exclude in-subnet decide");
	check(dec_in.action == LAYER7_ACTION_BLOCK, "exclude in-subnet block");

	memset(&dec_out, 0, sizeof(dec_out));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.50", "www.youtube.com", NULL, NULL, &dec_out) == 0,
	    "exclude host decide");
	check(dec_out.action == LAYER7_ACTION_ALLOW, "exclude host allow");
	check(dec_out.reason == L7_DECIDE_DEFAULT_ALLOW,
	    "exclude host default allow");
}

static void
test_vip_exception_host_beyond_eight_allowed(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_exception exc[1];
	struct layer7_decision dec;
	char json[4096];
	char *p;
	int n_exc = 0;
	int i;

	init_youtube_block_policy(&rules[0], "p-yt", 10, NULL);
	layer7_policies_sort(rules, 1);

	p = json + snprintf(json, sizeof(json),
	    "{\"layer7\":{\"exceptions\":[{\"id\":\"vip-isentos\","
	    "\"action\":\"allow\",\"enabled\":true,\"priority\":9000,"
	    "\"hosts\":[");
	for (i = 1; i <= 10; i++) {
		if (i > 1)
			p += snprintf(p, sizeof(json) - (size_t)(p - json), ",");
		p += snprintf(p, sizeof(json) - (size_t)(p - json),
		    "\"10.9.0.%d\"", i);
	}
	snprintf(p, sizeof(json) - (size_t)(p - json), "]}]}}");

	memset(exc, 0, sizeof(exc));
	check(layer7_exceptions_parse(json, strlen(json), exc, &n_exc, 1) == 0,
	    "vip parse ok (10 hosts)");
	check(n_exc == 1, "vip exception loaded");
	check(exc[0].n_hosts == 10, "10 vip hosts parsed (not truncated at 8)");
	layer7_exceptions_sort(exc, 1);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(exc, 1, rules, 1, 1, NULL,
	    "10.9.0.10", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "vip host 10 decide ok");
	check(dec.action == LAYER7_ACTION_ALLOW, "vip host 10 allow");
	check(dec.reason == L7_DECIDE_EXCEPTION, "vip host 10 exception reason");
	check(layer7_decision_is_explicit_allow(&dec),
	    "vip host 10 explicit allow");
}

static void
test_src_exclude_group_expanded(void)
{
	struct layer7_group groups[1];
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"grp-exc\","
	    "\"action\":\"block\",\"enabled\":true,"
	    "\"match\":{\"hosts\":[\"youtube.com\"],"
	    "\"src_cidrs\":[\"10.0.0.0/24\"],"
	    "\"src_exclude_groups\":[\"vip\"]}],"
	    "\"groups\":[{\"id\":\"vip\",\"hosts\":[\"10.0.0.99\"]}]}}";
	int n = 0, ng = 0;

	memset(groups, 0, sizeof(groups));
	memset(rules, 0, sizeof(rules));
	check(layer7_groups_parse(json, strlen(json), groups, &ng, 1) == 0,
	    "exclude group parse");
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "exclude policy parse");
	layer7_policies_expand_exclude_groups(rules, n, groups, ng);
	layer7_policies_sort(rules, 1);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "10.0.0.99", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "exclude expanded group decide");
	check(dec.action == LAYER7_ACTION_ALLOW, "exclude expanded allow");
}

/* Passo 12.6 — CIDR IPv6 parse + match (BG-081). */
static void
test_ipv6_src_cidr_match(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec_in, dec_out, dec_v4;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"v6-lan\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":10,"
	    "\"match\":{\"hosts\":[\"youtube.com\"],"
	    "\"src_cidrs\":[\"2804:6c4:11d:cc00::/64\"]}}]}}";
	int n = 0;

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "v6 cidr parse ok");
	check(n == 1, "v6 policy loaded");
	check(rules[0].n_src_cidrs == 1, "v6 cidr count");
	check(rules[0].src_cidrs[0].family == AF_INET6, "v6 family");
	check(rules[0].src_cidrs[0].prefix == 64, "v6 prefix 64");
	layer7_policies_sort(rules, 1);

	memset(&dec_in, 0, sizeof(dec_in));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "2804:6c4:11d:cc00:250:56ff:feb8:f83a", "www.youtube.com",
	    NULL, NULL, &dec_in) == 0, "v6 in-prefix decide");
	check(dec_in.action == LAYER7_ACTION_BLOCK, "v6 in-prefix block");

	memset(&dec_out, 0, sizeof(dec_out));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "2804:6c4:11d:dd00::1", "www.youtube.com", NULL, NULL,
	    &dec_out) == 0, "v6 out-prefix decide");
	check(dec_out.action == LAYER7_ACTION_ALLOW, "v6 out-prefix allow");

	/* Cliente IPv4 nao casa CIDR v6 */
	memset(&dec_v4, 0, sizeof(dec_v4));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "192.168.100.244", "www.youtube.com", NULL, NULL, &dec_v4) == 0,
	    "v4 vs v6 cidr decide");
	check(dec_v4.action == LAYER7_ACTION_ALLOW, "v4 vs v6 cidr allow");
}

static void
test_ipv6_src_exclude_and_host_equal(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec_ex, dec_ok;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"v6-ex\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":10,"
	    "\"match\":{\"hosts\":[\"example.com\"],"
	    "\"src_cidrs\":[\"2001:db8:1::/48\"],"
	    "\"src_exclude_cidrs\":[\"2001:db8:1:2::/64\"],"
	    "\"src_hosts\":[]}}]}}";
	int n = 0;

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "v6 exclude parse");
	layer7_policies_sort(rules, 1);

	memset(&dec_ex, 0, sizeof(dec_ex));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "2001:db8:1:2::99", "www.example.com", NULL, NULL, &dec_ex) == 0,
	    "v6 excluded decide");
	check(dec_ex.action == LAYER7_ACTION_ALLOW, "v6 excluded allow");

	memset(&dec_ok, 0, sizeof(dec_ok));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "2001:db8:1:3::1", "www.example.com", NULL, NULL, &dec_ok) == 0,
	    "v6 not-excluded decide");
	check(dec_ok.action == LAYER7_ACTION_BLOCK, "v6 not-excluded block");
}

static void
test_ipv6_exception_cidr(void)
{
	struct layer7_exception exc[1];
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	const char *json =
	    "{\"layer7\":{"
	    "\"exceptions\":[{\"id\":\"vip6\",\"enabled\":true,"
	    "\"action\":\"allow\",\"priority\":100,"
	    "\"cidrs\":[\"2804:6c4:11d:cc00::1009/128\"]}],"
	    "\"policies\":[{\"id\":\"block-all-yt\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":10,"
	    "\"match\":{\"hosts\":[\"youtube.com\"]}}]}}";
	int n = 0, ne = 0;

	memset(exc, 0, sizeof(exc));
	memset(rules, 0, sizeof(rules));
	check(layer7_exceptions_parse(json, strlen(json), exc, &ne, 1) == 0,
	    "v6 exception parse");
	check(ne == 1 && exc[0].n_cidrs == 1, "v6 exception cidr");
	check(exc[0].cidrs[0].family == AF_INET6, "v6 exception family");
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "v6 exception policy parse");
	layer7_exceptions_sort(exc, ne);
	layer7_policies_sort(rules, n);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(exc, ne, rules, n, 1, NULL,
	    "2804:6c4:11d:cc00::1009", "www.youtube.com", NULL, NULL,
	    &dec) == 0, "v6 exception decide");
	check(dec.action == LAYER7_ACTION_ALLOW, "v6 exception allow");
	check(dec.reason == L7_DECIDE_EXCEPTION, "v6 exception reason");
}

static void
test_ad_user_match_via_idmap(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	struct l7_id_map map;
	struct l7_id_addr ip;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"p-ad-user\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":50,"
	    "\"match\":{\"hosts\":[\"youtube.com\"],"
	    "\"ad_users\":[\"joao.silva\"]}}]}}";
	int n = 0;

	memset(&map, 0, sizeof(map));
	check(layer7_idmap_init(&map) == 0, "ad_user idmap init");
	check(layer7_idmap_addr_set_ipv4(&ip, 0x0a00000a) == 0,
	    "ad_user addr 10.0.0.10");
	check(layer7_idmap_upsert(&map, "CORP\\Joao.Silva", &ip,
	    L7_ID_SRC_DC_AGENT, NULL, 0, time(NULL)) == 0,
	    "ad_user upsert");
	layer7_policies_set_identity_map(&map);

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "ad_user parse");
	layer7_policies_sort(rules, n);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.10", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "ad_user decide mapped");
	check(dec.action == LAYER7_ACTION_BLOCK, "ad_user block mapped");
	check(dec.source_scoped == 1, "ad_user source_scoped");

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.99", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "ad_user decide other");
	check(dec.action == LAYER7_ACTION_ALLOW, "ad_user other default allow");

	layer7_policies_set_identity_map(NULL);
	layer7_idmap_fini(&map);
}

static void
test_ad_group_match_via_idmap(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	struct l7_id_map map;
	struct l7_id_addr ip;
	const char *grps[] = { "TI", "VPN" };
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"p-ad-grp\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":50,"
	    "\"match\":{\"hosts\":[\"facebook.com\"],"
	    "\"ad_groups\":[\"ti\"]}}]}}";
	int n = 0;

	memset(&map, 0, sizeof(map));
	check(layer7_idmap_init(&map) == 0, "ad_group idmap init");
	check(layer7_idmap_addr_set_ipv4(&ip, 0x0a000014) == 0,
	    "ad_group addr 10.0.0.20");
	check(layer7_idmap_upsert(&map, "maria", &ip, L7_ID_SRC_RADIUS,
	    grps, 2, time(NULL)) == 0, "ad_group upsert");
	layer7_policies_set_identity_map(&map);

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "ad_group parse");
	layer7_policies_sort(rules, n);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.20", "www.facebook.com", NULL, NULL, &dec) == 0,
	    "ad_group decide");
	check(dec.action == LAYER7_ACTION_BLOCK, "ad_group block");

	layer7_policies_set_identity_map(NULL);
	layer7_idmap_fini(&map);
}

static void
test_ad_multi_user_no_match(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	struct l7_id_map map;
	struct l7_id_addr ip;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"p-ad-mu\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":50,"
	    "\"match\":{\"hosts\":[\"youtube.com\"],"
	    "\"ad_users\":[\"alice\",\"bob\"]}}]}}";
	int n = 0;
	time_t now = time(NULL);

	memset(&map, 0, sizeof(map));
	check(layer7_idmap_init(&map) == 0, "ad_mu idmap init");
	check(layer7_idmap_addr_set_ipv4(&ip, 0x0a00001e) == 0,
	    "ad_mu addr");
	/* Conflito na janela → multi_user */
	check(layer7_idmap_upsert(&map, "alice", &ip, L7_ID_SRC_DC_AGENT,
	    NULL, 0, now) == 0, "ad_mu upsert alice");
	check(layer7_idmap_upsert(&map, "bob", &ip, L7_ID_SRC_DC_AGENT,
	    NULL, 0, now) == 0, "ad_mu upsert bob");
	layer7_policies_set_identity_map(&map);

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "ad_mu parse");
	layer7_policies_sort(rules, n);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.30", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "ad_mu decide");
	check(dec.action == LAYER7_ACTION_ALLOW,
	    "ad_mu multi_user → no ad match");

	layer7_policies_set_identity_map(NULL);
	layer7_idmap_fini(&map);
}

static void
test_ad_off_no_match(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"p-ad-off\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":50,"
	    "\"match\":{\"hosts\":[\"youtube.com\"],"
	    "\"ad_users\":[\"joao\"]}}]}}";
	int n = 0;

	layer7_policies_set_identity_map(NULL);
	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "ad_off parse");
	layer7_policies_sort(rules, n);
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.10", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "ad_off decide");
	check(dec.action == LAYER7_ACTION_ALLOW,
	    "ad_off without map → no match");
}

/* GI7.4 / 20.25: ad_group priority alta vence src_hosts priority baixa. */
static void
test_ad_priority_beats_static_ip(void)
{
	struct layer7_policy_rule rules[2];
	struct layer7_decision dec;
	struct l7_id_map map;
	struct l7_id_addr ip;
	const char *grps[] = { "ti" };
	const char *json =
	    "{\"layer7\":{\"policies\":["
	    "{\"id\":\"p-ip-low\",\"action\":\"allow\",\"enabled\":true,"
	    "\"priority\":10,\"match\":{\"hosts\":[\"youtube.com\"],"
	    "\"src_hosts\":[\"10.0.0.40\"]}},"
	    "{\"id\":\"p-ad-high\",\"action\":\"block\",\"enabled\":true,"
	    "\"priority\":90,\"match\":{\"hosts\":[\"youtube.com\"],"
	    "\"ad_groups\":[\"ti\"]}}"
	    "]}}";
	int n = 0;

	memset(&map, 0, sizeof(map));
	check(layer7_idmap_init(&map) == 0, "ad_pri idmap init");
	check(layer7_idmap_addr_set_ipv4(&ip, 0x0a000028) == 0,
	    "ad_pri addr 10.0.0.40");
	check(layer7_idmap_upsert(&map, "ana", &ip, L7_ID_SRC_DC_AGENT,
	    grps, 1, time(NULL)) == 0, "ad_pri upsert");
	layer7_policies_set_identity_map(&map);

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 2) == 0,
	    "ad_pri parse");
	check(n == 2, "ad_pri two policies");
	layer7_policies_sort(rules, n);
	check(strcmp(rules[0].id, "p-ad-high") == 0, "ad_pri sorted high first");

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.40", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "ad_pri decide");
	check(dec.action == LAYER7_ACTION_BLOCK, "ad_pri high ad wins block");
	check(strcmp(dec.matched_policy_id, "p-ad-high") == 0,
	    "ad_pri matched ad policy");

	layer7_policies_set_identity_map(NULL);
	layer7_idmap_fini(&map);
}

/* GI7.1: ad_groups só casa IPs de membros do grupo. */
static void
test_ad_group_only_members(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	struct l7_id_map map;
	struct l7_id_addr ip_in, ip_out;
	const char *grps_in[] = { "ti" };
	const char *grps_out[] = { "vendas" };
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"p-g1\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":50,"
	    "\"match\":{\"hosts\":[\"youtube.com\"],"
	    "\"ad_groups\":[\"ti\"]}}]}}";
	int n = 0;
	time_t now = time(NULL);

	memset(&map, 0, sizeof(map));
	check(layer7_idmap_init(&map) == 0, "gi71 init");
	check(layer7_idmap_addr_set_ipv4(&ip_in, 0x0a000032) == 0, "gi71 in");
	check(layer7_idmap_addr_set_ipv4(&ip_out, 0x0a000033) == 0, "gi71 out");
	check(layer7_idmap_upsert(&map, "membro", &ip_in, L7_ID_SRC_MANUAL,
	    grps_in, 1, now) == 0, "gi71 upsert in");
	check(layer7_idmap_upsert(&map, "outro", &ip_out, L7_ID_SRC_MANUAL,
	    grps_out, 1, now) == 0, "gi71 upsert out");
	layer7_policies_set_identity_map(&map);
	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "gi71 parse");
	layer7_policies_sort(rules, n);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.50", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "gi71 member decide");
	check(dec.action == LAYER7_ACTION_BLOCK, "gi71 member block");

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.51", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "gi71 nonmember decide");
	check(dec.action == LAYER7_ACTION_ALLOW, "gi71 nonmember allow");

	layer7_policies_set_identity_map(NULL);
	layer7_idmap_fini(&map);
}

/* GI7.2: troca de IP no mapa → política ad_users segue o user. */
static void
test_ad_user_ip_remap(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	struct l7_id_map map;
	struct l7_id_addr ip_a, ip_c;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"p-remap\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":50,"
	    "\"match\":{\"hosts\":[\"youtube.com\"],"
	    "\"ad_users\":[\"joao\"]}}]}}";
	int n = 0;
	time_t now = time(NULL);

	memset(&map, 0, sizeof(map));
	check(layer7_idmap_init(&map) == 0, "gi72 init");
	check(layer7_idmap_addr_set_ipv4(&ip_a, 0x0a00003c) == 0, "gi72 A");
	check(layer7_idmap_addr_set_ipv4(&ip_c, 0x0a00003e) == 0, "gi72 C");
	check(layer7_idmap_upsert(&map, "joao", &ip_a, L7_ID_SRC_RADIUS,
	    NULL, 0, now) == 0, "gi72 upsert A");
	layer7_policies_set_identity_map(&map);
	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "gi72 parse");
	layer7_policies_sort(rules, n);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.60", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "gi72 on A");
	check(dec.action == LAYER7_ACTION_BLOCK, "gi72 block on A");

	check(layer7_idmap_remove_ip(&map, "joao", &ip_a) == 0, "gi72 remove A");
	check(layer7_idmap_upsert(&map, "joao", &ip_c, L7_ID_SRC_RADIUS,
	    NULL, 0, now) == 0, "gi72 upsert C");

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.60", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "gi72 after on A");
	check(dec.action == LAYER7_ACTION_ALLOW, "gi72 A no longer matches");

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.62", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "gi72 on C");
	check(dec.action == LAYER7_ACTION_BLOCK, "gi72 block follows to C");

	layer7_policies_set_identity_map(NULL);
	layer7_idmap_fini(&map);
}

/* GI7.5 parcial: após expire/TTL a sessão some → ad_* não-match (base intacta). */
static void
test_ad_after_expire_no_match(void)
{
	struct layer7_policy_rule rules[2];
	struct layer7_decision dec;
	struct l7_id_map map;
	struct l7_id_addr ip;
	const char *json =
	    "{\"layer7\":{\"policies\":["
	    "{\"id\":\"p-ad\",\"action\":\"block\",\"enabled\":true,"
	    "\"priority\":90,\"match\":{\"hosts\":[\"youtube.com\"],"
	    "\"ad_users\":[\"joao\"]}},"
	    "{\"id\":\"p-ip\",\"action\":\"block\",\"enabled\":true,"
	    "\"priority\":10,\"match\":{\"hosts\":[\"youtube.com\"],"
	    "\"src_hosts\":[\"10.0.0.70\"]}}"
	    "]}}";
	int n = 0;
	time_t now = time(NULL);

	memset(&map, 0, sizeof(map));
	check(layer7_idmap_init(&map) == 0, "gi75 init");
	check(layer7_idmap_addr_set_ipv4(&ip, 0x0a000046) == 0, "gi75 addr");
	check(layer7_idmap_upsert(&map, "joao", &ip, L7_ID_SRC_RADIUS,
	    NULL, 0, now) == 0, "gi75 upsert");
	layer7_policies_set_identity_map(&map);
	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 2) == 0,
	    "gi75 parse");
	layer7_policies_sort(rules, n);

	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.70", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "gi75 before expire");
	check(strcmp(dec.matched_policy_id, "p-ad") == 0, "gi75 matched ad");

	check(layer7_idmap_expire(&map, now + 86400 * 365) >= 1,
	    "gi75 expire sessions");
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, n, 1, NULL,
	    "10.0.0.70", "www.youtube.com", NULL, NULL, &dec) == 0,
	    "gi75 after expire");
	check(strcmp(dec.matched_policy_id, "p-ip") == 0,
	    "gi75 falls back to IP policy (base intacta)");

	layer7_policies_set_identity_map(NULL);
	layer7_idmap_fini(&map);
}

static int
load_adulto_or(struct layer7_policy_rule *rules, int *n)
{
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"profile-adulto\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":20,"
	    "\"scope_global\":true,\"match_mode\":\"or\",\"match\":{"
	    "\"hosts\":[\"pornhub.com\"],"
	    "\"ndpi_category\":[\"AdultContent\"]}}]}}";

	memset(rules, 0, sizeof(*rules));
	*n = 0;
	if (layer7_policies_parse(json, strlen(json), rules, n, 1) != 0)
		return -1;
	if (*n != 1)
		return -1;
	layer7_policies_sort(rules, 1);
	return 0;
}

static void
test_adulto_or_host_empty_cat(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	int n = 0;

	check(load_adulto_or(rules, &n) == 0, "adulto parse or");
	check(rules[0].match_mode == L7_MATCH_MODE_OR, "adulto match_mode or");
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "172.16.8.130", "pornhub.com", "TLS", NULL, &dec) == 0,
	    "adulto empty cat decide");
	check(dec.action == LAYER7_ACTION_BLOCK, "adulto empty cat block");
	check(strcmp(dec.matched_policy_id, "profile-adulto") == 0,
	    "adulto empty cat id");
}

static void
test_adulto_or_host_web_cat(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	int n = 0;

	check(load_adulto_or(rules, &n) == 0, "adulto web parse");
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "172.16.8.130", "www.pornhub.com", "TLS", "Web", &dec) == 0,
	    "adulto web decide");
	check(dec.action == LAYER7_ACTION_BLOCK, "adulto web block");
	check(strcmp(dec.matched_policy_id, "profile-adulto") == 0,
	    "adulto web id");
}

static void
test_adulto_or_unknown_host_adultcontent(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	int n = 0;

	check(load_adulto_or(rules, &n) == 0, "adulto unknown+cat parse");
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "172.16.8.130", "random-tube.example", "TLS", "AdultContent",
	    &dec) == 0, "adulto unknown+cat decide");
	check(dec.action == LAYER7_ACTION_BLOCK, "adulto unknown+cat block");
}

static void
test_adulto_or_unknown_host_web_no_block(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	int n = 0;

	check(load_adulto_or(rules, &n) == 0, "adulto unknown+web parse");
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "172.16.8.130", "example.com", "TLS", "Web", &dec) == 0,
	    "adulto unknown+web decide");
	check(dec.action == LAYER7_ACTION_ALLOW, "adulto unknown+web no block");
	check(dec.reason == L7_DECIDE_DEFAULT_ALLOW,
	    "adulto unknown+web default");
}

static void
test_adulto_or_not_shadowed_by_pmon(void)
{
	struct layer7_policy_rule rules[2];
	struct layer7_decision dec;
	const char *json =
	    "{\"layer7\":{\"policies\":["
	    "{\"id\":\"p-mon-001\",\"action\":\"monitor\",\"enabled\":true,"
	    "\"priority\":50,\"match\":{}},"
	    "{\"id\":\"profile-adulto\",\"action\":\"block\",\"enabled\":true,"
	    "\"priority\":20,\"scope_global\":true,\"match_mode\":\"or\","
	    "\"match\":{\"hosts\":[\"pornhub.com\"],"
	    "\"ndpi_category\":[\"AdultContent\"]}}"
	    "]}}";
	int n = 0;

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 2) == 0,
	    "pmon+adulto parse");
	check(n == 2, "pmon+adulto count");
	layer7_policies_sort(rules, 2);
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 2, 1, NULL,
	    "172.16.8.130", "pornhub.com", "TLS", "Web", &dec) == 0,
	    "pmon+adulto decide");
	check(dec.action == LAYER7_ACTION_BLOCK, "pmon does not shadow");
	check(strcmp(dec.matched_policy_id, "profile-adulto") == 0,
	    "pmon loser");
}

static void
test_mixed_hosts_cat_and_default_unchanged(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"profile-escolas\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":20,"
	    "\"scope_global\":true,\"match\":{"
	    "\"hosts\":[\"pornhub.com\"],"
	    "\"ndpi_category\":[\"AdultContent\"]}}]}}";
	int n = 0;

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "mixed and parse");
	check(n == 1, "mixed and loaded");
	check(rules[0].match_mode == L7_MATCH_MODE_AND, "mixed default and");
	layer7_policies_sort(rules, 1);
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "172.16.8.130", "pornhub.com", "TLS", "Web", &dec) == 0,
	    "mixed and web decide");
	check(dec.action == LAYER7_ACTION_ALLOW,
	    "mixed and still requires category");
}

static void
test_profile_adulto_compat_without_match_mode(void)
{
	struct layer7_policy_rule rules[1];
	struct layer7_decision dec;
	const char *json =
	    "{\"layer7\":{\"policies\":[{\"id\":\"profile-adulto\","
	    "\"action\":\"block\",\"enabled\":true,\"priority\":20,"
	    "\"scope_global\":true,\"match\":{"
	    "\"hosts\":[\"pornhub.com\"],"
	    "\"ndpi_category\":[\"AdultContent\"]}}]}}";
	int n = 0;

	memset(rules, 0, sizeof(rules));
	check(layer7_policies_parse(json, strlen(json), rules, &n, 1) == 0,
	    "compat parse");
	check(n == 1, "compat loaded");
	check(rules[0].match_mode == L7_MATCH_MODE_OR,
	    "compat id implies or");
	layer7_policies_sort(rules, 1);
	memset(&dec, 0, sizeof(dec));
	check(layer7_decide_for_client(NULL, 0, rules, 1, 1, NULL,
	    "172.16.8.130", "pornhub.com", "TLS", "Web", &dec) == 0,
	    "compat decide");
	check(dec.action == LAYER7_ACTION_BLOCK, "compat leftover blocks");
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
	test_catch_all_monitor_does_not_shadow_specific_block();
	test_policy_table_index_sorted();
	test_empty_block_rejected();
	test_scope_global_parse();
	test_app_only_quarantine_false_no_runtime_psrc();
	test_app_only_quarantine_true_allows_psrc();
	test_app_only_no_quarantine_decision();
	test_app_static_source_uses_pdst();
	test_mixed_app_host_uses_matched_path();
	test_src_exclude_cidr_no_match();
	test_vip_exception_host_beyond_eight_allowed();
	test_src_exclude_group_expanded();
	test_ipv6_src_cidr_match();
	test_ipv6_src_exclude_and_host_equal();
	test_ipv6_exception_cidr();
	test_ad_user_match_via_idmap();
	test_ad_group_match_via_idmap();
	test_ad_multi_user_no_match();
	test_ad_off_no_match();
	test_ad_priority_beats_static_ip();
	test_ad_group_only_members();
	test_ad_user_ip_remap();
	test_ad_after_expire_no_match();
	test_adulto_or_host_empty_cat();
	test_adulto_or_host_web_cat();
	test_adulto_or_unknown_host_adultcontent();
	test_adulto_or_unknown_host_web_no_block();
	test_adulto_or_not_shadowed_by_pmon();
	test_mixed_hosts_cat_and_default_unchanged();
	test_profile_adulto_compat_without_match_mode();

	if (g_fail) {
		printf("\nSOME TESTS FAILED\n");
		return 1;
	}
	printf("\nALL POLICY DECIDE TESTS PASSED\n");
	return 0;
}
