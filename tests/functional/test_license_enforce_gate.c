/*
 * test_license_enforce_gate.c — 30.16 / BG-122 / GA6.1–6.2
 *
 *   cc -Wall -Wextra -O2 -I src/layer7d -o /tmp/t_gate \
 *      tests/functional/test_license_enforce_gate.c \
 *      src/layer7d/license_enforce_gate.c && /tmp/t_gate
 */
#include "license.h"

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

static void
fill_ok(struct l7_license_info *li)
{
	memset(li, 0, sizeof(*li));
	li->valid = 1;
	li->expired = 0;
	li->grace = 0;
	li->days_left = 30;
	snprintf(li->expiry, sizeof(li->expiry), "2099-12-31");
}

int
main(void)
{
	struct l7_license_info li;

	/* N1 — licença válida ⇒ allow */
	fill_ok(&li);
	check(layer7_license_gate_a(&li) == 1, "N1 gate_a valid");
	check(layer7_license_gate_b(&li) == 1, "N1 gate_b structural");
	check(layer7_license_allows_enforce(&li) == 1, "N1 allows_enforce");

	/* N2 — sem licença ⇒ deny */
	memset(&li, 0, sizeof(li));
	check(layer7_license_allows_enforce(&li) == 0, "N2 missing license");
	check(layer7_license_allows_enforce(NULL) == 0, "N2 NULL");

	/* N2 — expirada sem grace */
	fill_ok(&li);
	li.valid = 0;
	li.expired = 1;
	li.grace = 0;
	li.days_left = -20;
	check(layer7_license_allows_enforce(&li) == 0, "N2 expired no grace");

	/* grace activa ⇒ allow (comportamento actual) */
	fill_ok(&li);
	li.expired = 1;
	li.grace = 1;
	li.days_left = -3;
	check(layer7_license_allows_enforce(&li) == 1, "grace allows enforce");

	/* clock_suspect ⇒ deny mesmo com valid */
	fill_ok(&li);
	li.clock_suspect = 1;
	li.valid = 1; /* inconsistência forjada */
	check(layer7_license_gate_b(&li) == 0, "clock_suspect gate_b");
	check(layer7_license_allows_enforce(&li) == 0,
	    "clock_suspect cruzamento nega");

	/* A-02: forçar valid=1 sem expiry ⇒ discordância ⇒ deny */
	memset(&li, 0, sizeof(li));
	li.valid = 1;
	check(layer7_license_gate_a(&li) == 1, "forged valid bit");
	check(layer7_license_gate_b(&li) == 0, "forged sem expiry");
	check(layer7_license_allows_enforce(&li) == 0,
	    "A-02 forged valid sem material");

	/* A-02: valid=1 + expired sem grace ⇒ discordância */
	fill_ok(&li);
	li.valid = 1;
	li.expired = 1;
	li.grace = 0;
	check(layer7_license_allows_enforce(&li) == 0,
	    "A-02 valid+expired sem grace");

	if (g_fail) {
		printf("RESULT: FAIL\n");
		return 1;
	}
	printf("RESULT: PASS\n");
	return 0;
}
