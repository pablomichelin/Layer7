/*
 * test_enforce_scoped.c — Caminho B / E3: resolucao de tabela PF escopada.
 *
 * Compila standalone:
 *   cc -Wall -Wextra -O2 -I src/layer7d -o /tmp/test_enforce_scoped \
 *      tests/functional/test_enforce_scoped.c src/layer7d/enforce.c
 */
#include "enforce.h"
#include "policy.h"

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
test_policy_table_name(void)
{
	char buf[64];

	check(layer7_pf_policy_table_name(L7_ENFORCE_DST_SCOPED, 0, buf,
	    sizeof(buf)) > 0 &&
	    strcmp(buf, "layer7_pdst_0") == 0,
	    "pdst table name idx 0");
	check(layer7_pf_policy_table_name(L7_ENFORCE_SRC_SCOPED, 3, buf,
	    sizeof(buf)) > 0 &&
	    strcmp(buf, "layer7_psrc_3") == 0,
	    "psrc table name idx 3");
	check(layer7_pf_policy_table_name(L7_ENFORCE_NONE, 0, buf,
	    sizeof(buf)) < 0,
	    "none kind rejects table name");
	check(layer7_pf_policy_allow_table_name(4, buf, sizeof(buf)) > 0 &&
	    strcmp(buf, "layer7_pallow_4") == 0,
	    "policy allow table name idx 4");
	check(layer7_pf_policy_allow_table_name(L7_MAX_POLICIES, buf,
	    sizeof(buf)) < 0, "policy allow rejects out-of-range idx");
}

static void
test_resolve_dst_scoped(void)
{
	struct layer7_decision dec;
	char tbl[64];
	const char *ip;
	int r;

	memset(&dec, 0, sizeof(dec));
	dec.action = LAYER7_ACTION_BLOCK;
	dec.would_enforce_block_or_tag = 1;
	dec.enforce_kind = L7_ENFORCE_DST_SCOPED;
	dec.policy_table_idx = 2;
	strncpy(dec.enforce_dst_ip, "142.250.185.78",
	    sizeof(dec.enforce_dst_ip) - 1);

	r = layer7_pf_resolve_block_target(&dec, "10.0.0.10",
	    "142.250.185.78", 1, tbl, sizeof(tbl), &ip);
	check(r == 1 && strcmp(tbl, "layer7_pdst_2") == 0 &&
	    ip && strcmp(ip, "142.250.185.78") == 0,
	    "scoped dst resolves pdst table and dst ip");
}

static void
test_resolve_src_scoped(void)
{
	struct layer7_decision dec;
	char tbl[64];
	const char *ip;
	int r;

	memset(&dec, 0, sizeof(dec));
	dec.action = LAYER7_ACTION_BLOCK;
	dec.would_enforce_block_or_tag = 1;
	dec.enforce_kind = L7_ENFORCE_SRC_SCOPED;
	dec.policy_table_idx = 1;

	r = layer7_pf_resolve_block_target(&dec, "10.0.0.99",
	    "8.8.8.8", 1, tbl, sizeof(tbl), &ip);
	check(r == 1 && strcmp(tbl, "layer7_psrc_1") == 0 &&
	    ip && strcmp(ip, "10.0.0.99") == 0,
	    "scoped src resolves psrc table and src ip");
}

static void
test_resolve_legacy_global(void)
{
	struct layer7_decision dec;
	char tbl[64];
	const char *ip;
	int r;

	memset(&dec, 0, sizeof(dec));
	dec.action = LAYER7_ACTION_BLOCK;
	dec.would_enforce_block_or_tag = 1;
	dec.enforce_kind = L7_ENFORCE_DST_SCOPED;
	dec.policy_table_idx = 0;

	r = layer7_pf_resolve_block_target(&dec, "10.0.0.10",
	    "1.2.3.4", 0, tbl, sizeof(tbl), &ip);
	check(r == 1 && strcmp(tbl, L7_PF_TABLE_BLOCK_DST) == 0 &&
	    ip && strcmp(ip, "1.2.3.4") == 0,
	    "legacy_global uses block_dst regardless of enforce_kind");
}

static void
test_resolve_monitor_skips(void)
{
	struct layer7_decision dec;
	char tbl[64];
	const char *ip;

	memset(&dec, 0, sizeof(dec));
	dec.action = LAYER7_ACTION_MONITOR;
	dec.would_enforce_block_or_tag = 0;

	check(layer7_pf_resolve_block_target(&dec, "10.0.0.10", "1.2.3.4",
	    1, tbl, sizeof(tbl), &ip) == 0,
	    "monitor action skips enforce");
}

static void
test_exception_block_quarantines_source(void)
{
	struct layer7_decision dec;
	char tbl[64];
	const char *ip;
	int r;

	memset(&dec, 0, sizeof(dec));
	dec.action = LAYER7_ACTION_BLOCK;
	dec.reason = L7_DECIDE_EXCEPTION;
	dec.would_enforce_block_or_tag = 1;
	snprintf(dec.pf_table, sizeof(dec.pf_table), "%s",
	    L7_PF_TABLE_BLOCK);

	r = layer7_pf_resolve_block_target(&dec, "10.0.0.77",
	    "1.2.3.4", 1, tbl, sizeof(tbl), &ip);
	check(r == 1 && strcmp(tbl, L7_PF_TABLE_BLOCK) == 0 &&
	    ip && strcmp(ip, "10.0.0.77") == 0,
	    "exception block quarantines source in scoped mode");
}

static void
test_enforce_kind_str(void)
{
	check(strcmp(layer7_enforce_kind_str(L7_ENFORCE_DST_SCOPED),
	    "dst_scoped") == 0, "kind str dst_scoped");
	check(strcmp(layer7_enforce_kind_str(L7_ENFORCE_SRC_SCOPED),
	    "src_scoped") == 0, "kind str src_scoped");
	check(strcmp(layer7_enforce_kind_str(L7_ENFORCE_NONE),
	    "none") == 0, "kind str none");
}

/* Passo 12.7 — host IPv6 + S-03 + resolve dual-stack */
static void
test_host_ok_dualstack(void)
{
	check(layer7_pf_host_ok("10.0.0.1") == 1, "host_ok v4");
	check(layer7_pf_host_ok("2804:6c4:11d:cc00::1009") == 1, "host_ok v6");
	check(layer7_pf_host_ok("not-an-ip") == 0, "host_ok reject junk");
	check(layer7_pf_host_enforce_ok("2001:db8::1") == 1,
	    "enforce_ok gua/ula doc");
	check(layer7_pf_host_enforce_ok("::1") == 0, "S-03 reject loopback");
	check(layer7_pf_host_enforce_ok("fe80::1") == 0,
	    "S-03 reject link-local");
	check(layer7_pf_host_enforce_ok("ff02::1") == 0,
	    "S-03 reject multicast");
	check(layer7_pf_host_enforce_ok("192.168.1.1") == 1,
	    "enforce_ok v4 still");
}

static void
test_resolve_dst_scoped_v6(void)
{
	struct layer7_decision dec;
	char tbl[64];
	const char *ip;
	int r;
	char cmd[128];

	memset(&dec, 0, sizeof(dec));
	dec.action = LAYER7_ACTION_BLOCK;
	dec.would_enforce_block_or_tag = 1;
	dec.enforce_kind = L7_ENFORCE_DST_SCOPED;
	dec.policy_table_idx = 2;
	strncpy(dec.enforce_dst_ip, "2001:db8:abcd::53",
	    sizeof(dec.enforce_dst_ip) - 1);

	r = layer7_pf_resolve_block_target(&dec,
	    "2804:6c4:11d:cc00:250:56ff:feb8:f83a",
	    "2001:db8:abcd::53", 1, tbl, sizeof(tbl), &ip);
	check(r == 1 && strcmp(tbl, "layer7_pdst_2") == 0 &&
	    ip && strcmp(ip, "2001:db8:abcd::53") == 0,
	    "scoped dst resolves pdst with v6");

	check(layer7_pf_snprint_add(cmd, sizeof(cmd), "layer7_block_dst",
	    "2001:db8::1") > 0 &&
	    strstr(cmd, "2001:db8::1") != NULL,
	    "snprint_add accepts v6");
	check(layer7_pf_snprint_add(cmd, sizeof(cmd), "layer7_block_dst",
	    "fe80::1") < 0,
	    "snprint_add rejects link-local");
}

static void
test_resolve_rejects_s03_src(void)
{
	struct layer7_decision dec;
	char tbl[64];
	const char *ip;
	int r;

	memset(&dec, 0, sizeof(dec));
	dec.action = LAYER7_ACTION_BLOCK;
	dec.reason = L7_DECIDE_EXCEPTION;
	dec.would_enforce_block_or_tag = 1;
	snprintf(dec.pf_table, sizeof(dec.pf_table), "%s",
	    L7_PF_TABLE_BLOCK);

	r = layer7_pf_resolve_block_target(&dec, "fe80::abcd",
	    "2001:db8::1", 1, tbl, sizeof(tbl), &ip);
	check(r == 0, "exception block skips link-local src (S-03)");
}

int
main(void)
{
	test_policy_table_name();
	test_resolve_dst_scoped();
	test_resolve_src_scoped();
	test_resolve_legacy_global();
	test_resolve_monitor_skips();
	test_exception_block_quarantines_source();
	test_enforce_kind_str();
	test_host_ok_dualstack();
	test_resolve_dst_scoped_v6();
	test_resolve_rejects_s03_src();

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
