/*
 * test_allowlist.c — F5 minima (Bloco 6 / Fase 1).
 *
 * Testes unitarios standalone para `l7_allowlist_*`. Sem framework:
 * `assert(3)` + contagem; cada teste imprime PASS/FAIL.
 *
 * Compilar (qualquer host com compilador C):
 *   cc -Wall -Wextra -O2 -I src/layer7d \
 *      -o /tmp/test_allowlist \
 *      tests/functional/test_allowlist.c src/layer7d/allowlist.c
 *   /tmp/test_allowlist
 *
 * Exit code 0 = todos passaram; != 0 = numero de falhas.
 */
#include "allowlist.h"

#include <assert.h>
#include <stdio.h>
#include <string.h>

static int g_fail;

#define CHECK(cond, label)						\
	do {								\
		if (!(cond)) {						\
			fprintf(stderr,					\
			    "FAIL: %s (line %d)\n", label, __LINE__);	\
			g_fail++;					\
		} else {						\
			fprintf(stdout, "PASS: %s\n", label);		\
		}							\
	} while (0)

static void
test_classification(void)
{
	struct l7_allowlist al;

	l7_allowlist_reset(&al);
	CHECK(l7_allowlist_add(&al, "bb.com.br") == 0, "add domain");
	CHECK(l7_allowlist_add(&al, "8.8.8.8") == 0, "add ipv4");
	CHECK(l7_allowlist_add(&al, "200.201.0.0/16") == 0, "add cidr");
	CHECK(l7_allowlist_add(&al, "0.0.0.0/0") == -1, "reject cidr /0");
	CHECK(l7_allowlist_add(&al, "") == -1, "reject empty");
	CHECK(l7_allowlist_add(&al, "no-dot") == -1,
	    "reject domain without dot");
	CHECK(l7_allowlist_add(&al, "999.0.0.1") == -1,
	    "reject invalid ipv4");
	CHECK(l7_allowlist_add(&al, "10.0.0.0/40") == -1,
	    "reject invalid prefix");
	CHECK(l7_allowlist_add(&al, "evil; rm -rf /") == -1,
	    "reject shell metachars");
	CHECK(l7_allowlist_count(&al) == 3, "count after 3 adds");
}

static void
test_domain_match(void)
{
	struct l7_allowlist al;

	l7_allowlist_reset(&al);
	(void)l7_allowlist_add(&al, "bb.com.br");
	(void)l7_allowlist_add(&al, "itau.com.br");

	CHECK(l7_allowlist_contains_domain(&al, "bb.com.br"),
	    "match exact domain");
	CHECK(l7_allowlist_contains_domain(&al, "app.bb.com.br"),
	    "match subdomain");
	CHECK(l7_allowlist_contains_domain(&al, "www.itau.com.br"),
	    "match subdomain of itau");
	CHECK(!l7_allowlist_contains_domain(&al, "evilbb.com.br"),
	    "do not match by infix");
	CHECK(!l7_allowlist_contains_domain(&al, "com.br"),
	    "do not match parent");
	CHECK(!l7_allowlist_contains_domain(&al, "google.com"),
	    "miss unrelated");
}

static void
test_ip_match(void)
{
	struct l7_allowlist al;

	l7_allowlist_reset(&al);
	(void)l7_allowlist_add(&al, "8.8.8.8");
	(void)l7_allowlist_add(&al, "200.201.0.0/16");

	CHECK(l7_allowlist_contains_ip(&al, "8.8.8.8"),
	    "match exact ip");
	CHECK(!l7_allowlist_contains_ip(&al, "8.8.8.9"),
	    "miss neighbour ip");
	CHECK(l7_allowlist_contains_ip(&al, "200.201.10.1"),
	    "match cidr inside");
	CHECK(!l7_allowlist_contains_ip(&al, "200.202.10.1"),
	    "miss cidr outside");
	CHECK(!l7_allowlist_contains_ip(&al, "not.an.ip"),
	    "reject non-ip input");
}

static void
test_ipv6_match(void)
{
	struct l7_allowlist al;

	l7_allowlist_reset(&al);
	CHECK(l7_allowlist_add(&al, "2001:db8::1") == 0, "add ipv6 host");
	CHECK(l7_allowlist_add(&al, "2001:db8:abcd::/48") == 0,
	    "add ipv6 cidr");
	CHECK(l7_allowlist_add(&al, "::/0") == -1, "reject ipv6 /0");
	CHECK(l7_allowlist_add(&al, "::1") == -1, "reject loopback v6");
	CHECK(l7_allowlist_add(&al, "fe80::1") == -1, "reject link-local");
	CHECK(l7_allowlist_add(&al, "ff02::1") == -1, "reject multicast");
	CHECK(l7_allowlist_add(&al, "2001:db8::/8") == -1,
	    "reject too-short v6 prefix");

	CHECK(l7_allowlist_contains_ip(&al, "2001:db8::1"),
	    "match ipv6 host");
	CHECK(l7_allowlist_contains_ip(&al, "2001:0db8:0000:0000:0000:0000:0000:0001"),
	    "match ipv6 host expanded form");
	CHECK(!l7_allowlist_contains_ip(&al, "2001:db8::2"),
	    "miss neighbour ipv6 host");
	CHECK(l7_allowlist_contains_ip(&al, "2001:db8:abcd::99"),
	    "match ipv6 cidr inside");
	CHECK(!l7_allowlist_contains_ip(&al, "2001:db8:abce::1"),
	    "miss ipv6 cidr outside");
	CHECK(!l7_allowlist_contains_ip(&al, "8.8.8.8"),
	    "v4 does not match v6 entries");
}

static void
test_json_parse(void)
{
	struct l7_allowlist al;
	const char *json =
	    "{ \"layer7\": { \"dst_allowlist\": ["
	    "  \"bb.com.br\",\n"
	    "  \"8.8.8.8\","
	    "  \"200.201.0.0/16\","
	    "  \"invalid;ip\""
	    "] } }";

	l7_allowlist_reset(&al);
	(void)l7_allowlist_parse_json(&al, json, strlen(json));
	CHECK(l7_allowlist_count(&al) == 3,
	    "parse_json keeps 3 valid, drops 1 invalid");
	CHECK(l7_allowlist_contains_domain(&al, "app.bb.com.br"),
	    "json: subdomain match");
	CHECK(l7_allowlist_contains_ip(&al, "200.201.5.5"),
	    "json: cidr match");
}

static void
test_json_parse_v6(void)
{
	struct l7_allowlist al;
	const char *json =
	    "{ \"layer7\": { \"dst_allowlist\": ["
	    "  \"2001:db8::53\","
	    "  \"2804:6c4:11d:cc00::/64\","
	    "  \"fe80::1\""
	    "] } }";

	l7_allowlist_reset(&al);
	(void)l7_allowlist_parse_json(&al, json, strlen(json));
	CHECK(l7_allowlist_count(&al) == 2,
	    "json v6 keeps 2 valid, drops link-local");
	CHECK(l7_allowlist_contains_ip(&al, "2001:db8::53"),
	    "json: ipv6 host match");
	CHECK(l7_allowlist_contains_ip(&al,
	    "2804:6c4:11d:cc00:250:56ff:feb8:f83a"),
	    "json: ipv6 cidr match lab prefix");
}

static void
test_dedup(void)
{
	struct l7_allowlist al;

	l7_allowlist_reset(&al);
	(void)l7_allowlist_add(&al, "bb.com.br");
	(void)l7_allowlist_add(&al, "BB.COM.BR");
	(void)l7_allowlist_add(&al, "bb.com.br");
	CHECK(l7_allowlist_count(&al) == 1,
	    "dedup is case-insensitive and exact-value");
}

int
main(void)
{
	g_fail = 0;
	test_classification();
	test_domain_match();
	test_ip_match();
	test_ipv6_match();
	test_json_parse();
	test_json_parse_v6();
	test_dedup();
	if (g_fail) {
		fprintf(stderr, "\n%d test(s) FAILED\n", g_fail);
		return g_fail;
	}
	fprintf(stdout, "\nALL OK\n");
	return 0;
}
