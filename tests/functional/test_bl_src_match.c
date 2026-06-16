/*
 * test_bl_src_match.c — except_ips e src_cidrs em regras blacklist.
 */
#include "bl_config.h"

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
test_except_ips(void)
{
	struct l7_bl_rule rule;

	memset(&rule, 0, sizeof(rule));
	rule.enabled = 1;
	strncpy(rule.src_cidrs[0], "192.168.10.0/24", sizeof(rule.src_cidrs[0]) - 1);
	rule.n_src_cidrs = 1;
	strncpy(rule.except_ips[0], "192.168.10.50", sizeof(rule.except_ips[0]) - 1);
	rule.n_except_ips = 1;

	check(l7_bl_rule_matches_src(&rule, "192.168.10.10") == 1,
	    "cidr match non-except");
	check(l7_bl_rule_matches_src(&rule, "192.168.10.50") == 0,
	    "except_ip excludes client");
	check(l7_bl_rule_matches_src(&rule, "192.168.20.1") == 0,
	    "outside cidr miss");
}

static void
test_global_no_cidr(void)
{
	struct l7_bl_rule rule;

	memset(&rule, 0, sizeof(rule));
	check(l7_bl_rule_matches_src(&rule, "10.0.0.1") == 1,
	    "no src_cidrs matches all");
}

int
main(void)
{
	g_fail = 0;
	test_except_ips();
	test_global_no_cidr();
	if (g_fail) {
		printf("\nBL SRC MATCH TESTS FAILED\n");
		return 1;
	}
	printf("\nALL BL SRC MATCH TESTS PASSED\n");
	return 0;
}
