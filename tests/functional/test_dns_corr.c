/*
 * test_dns_corr.c — BG-104: correlação DNS + allowlist (header-only).
 */
#include "dns_corr.h"

#include <stdio.h>
#include <string.h>
#include <time.h>

static int fails;

static void
expect(int cond, const char *msg)
{
	if (!cond) {
		fprintf(stderr, "FAIL: %s\n", msg);
		fails++;
	}
}

int
main(void)
{
	struct layer7_dns_corr c;
	time_t now = 1000;

	layer7_dns_corr_reset(&c);

	/* Happy path: query + matching answer. */
	layer7_dns_pend_remember(&c, "10.0.0.2", "192.168.100.254", 0x1234,
	    "example.com", now);
	expect(layer7_dns_pend_consume(&c, "10.0.0.2", "192.168.100.254",
	    0x1234, "example.com", now) == 1, "happy consume");
	expect(layer7_dns_pend_consume(&c, "10.0.0.2", "192.168.100.254",
	    0x1234, "example.com", now) == 0, "consume once");

	/* Spoof from wrong resolver IP. */
	layer7_dns_pend_remember(&c, "10.0.0.2", "192.168.100.254", 0x2222,
	    "a.test", now);
	expect(layer7_dns_pend_consume(&c, "10.0.0.2", "8.8.8.8", 0x2222,
	    "a.test", now) == 0, "reject wrong resolver");
	expect(layer7_dns_pend_consume(&c, "10.0.0.2", "192.168.100.254",
	    0x2222, "a.test", now) == 1, "accept real resolver");

	/* qname mismatch. */
	layer7_dns_pend_remember(&c, "10.0.0.2", "1.1.1.1", 0x3333,
	    "good.example", now);
	expect(layer7_dns_pend_consume(&c, "10.0.0.2", "1.1.1.1", 0x3333,
	    "evil.example", now) == 0, "reject qname mismatch");
	expect(layer7_dns_pend_consume(&c, "10.0.0.2", "1.1.1.1", 0x3333,
	    "GOOD.EXAMPLE", now) == 1, "qname case-insensitive");

	/* Allowlist gate: non-empty list rejects unknown. */
	layer7_dns_corr_resolvers_reset(&c);
	expect(layer7_dns_corr_resolver_add(&c, "192.168.100.254") == 0,
	    "add resolver");
	layer7_dns_pend_remember(&c, "10.0.0.2", "8.8.8.8", 0x4444,
	    "x.com", now);
	expect(layer7_dns_pend_consume(&c, "10.0.0.2", "8.8.8.8", 0x4444,
	    "x.com", now) == 0, "allowlist reject external");
	layer7_dns_pend_remember(&c, "10.0.0.2", "192.168.100.254", 0x5555,
	    "y.com", now);
	expect(layer7_dns_pend_consume(&c, "10.0.0.2", "192.168.100.254",
	    0x5555, "y.com", now) == 1, "allowlist accept local");

	/* Fail-open when allowlist empty. */
	layer7_dns_corr_resolvers_reset(&c);
	layer7_dns_pend_remember(&c, "10.0.0.2", "9.9.9.9", 0x6666,
	    "z.com", now);
	expect(layer7_dns_pend_consume(&c, "10.0.0.2", "9.9.9.9", 0x6666,
	    "z.com", now) == 1, "fail-open empty allowlist");

	if (fails) {
		fprintf(stderr, "%d failure(s)\n", fails);
		return 1;
	}
	printf("test_dns_corr: PASS\n");
	return 0;
}
