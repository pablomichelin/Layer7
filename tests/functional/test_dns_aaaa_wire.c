/*
 * test_dns_aaaa_wire.c — wire format A + AAAA via dns_observe.h
 */
#include "dns_observe.h"

#include <sys/types.h>
#include <sys/socket.h>
#include <arpa/inet.h>
#include <netinet/in.h>
#include <stdio.h>
#include <string.h>

struct hit {
	int af;
	char ip[INET6_ADDRSTRLEN];
	char qname[LAYER7_DNS_HOST_MAX];
	uint32_t ttl;
};

struct bag {
	struct hit hits[8];
	int n;
};

static void
on_rr(int af, const uint8_t *addr, uint32_t ttl, const char *qname, void *ud)
{
	struct bag *b = ud;
	struct hit *h;

	if (!b || b->n >= 8 || !addr || !qname)
		return;
	h = &b->hits[b->n++];
	h->af = af;
	h->ttl = ttl;
	snprintf(h->qname, sizeof(h->qname), "%s", qname);
	if (af == 4) {
		struct in_addr a;
		uint32_t ip = ((uint32_t)addr[0] << 24) |
		    ((uint32_t)addr[1] << 16) |
		    ((uint32_t)addr[2] << 8) |
		    (uint32_t)addr[3];
		a.s_addr = htonl(ip);
		inet_ntop(AF_INET, &a, h->ip, sizeof(h->ip));
	} else {
		inet_ntop(AF_INET6, addr, h->ip, sizeof(h->ip));
	}
}

static int
fail(const char *msg)
{
	fprintf(stderr, "FAIL: %s\n", msg);
	return 1;
}

int
main(void)
{
	/*
	 * Resposta mínima:
	 * QNAME = youtube.com, ANCOUNT=2
	 *  1) A  142.250.190.78
	 *  2) AAAA 2800:3f0:4004:80c::200e
	 *
	 * Header: id=0x1234, flags=0x8180 (response), qd=1, an=2
	 */
	uint8_t pkt[] = {
		0x12, 0x34, 0x81, 0x80, 0x00, 0x01, 0x00, 0x02,
		0x00, 0x00, 0x00, 0x00,
		/* qname youtube.com */
		0x07, 'y', 'o', 'u', 't', 'u', 'b', 'e',
		0x03, 'c', 'o', 'm',
		0x00,
		0x00, 0x01, /* QTYPE A (irrelevante para answers) */
		0x00, 0x01, /* QCLASS IN */
		/* answer 1: pointer to qname, A */
		0xc0, 0x0c,
		0x00, 0x01, /* type A */
		0x00, 0x01, /* class IN */
		0x00, 0x00, 0x01, 0x2c, /* TTL 300 */
		0x00, 0x04,
		142, 250, 190, 78,
		/* answer 2: pointer, AAAA */
		0xc0, 0x0c,
		0x00, 0x1c, /* type AAAA */
		0x00, 0x01,
		0x00, 0x00, 0x01, 0x2c,
		0x00, 0x10,
		0x28, 0x00, 0x03, 0xf0, 0x40, 0x04, 0x08, 0x0c,
		0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x20, 0x0e
	};
	struct bag bag;
	int n;
	int i;
	int saw_a = 0, saw_aaaa = 0;

	memset(&bag, 0, sizeof(bag));
	n = layer7_dns_foreach_a_aaaa(pkt, sizeof(pkt), on_rr, &bag);
	if (n != 2)
		return fail("expected 2 A/AAAA RRs");
	if (bag.n != 2)
		return fail("callback count");

	for (i = 0; i < bag.n; i++) {
		if (strcmp(bag.hits[i].qname, "youtube.com") != 0)
			return fail("qname");
		if (bag.hits[i].ttl != 300)
			return fail("ttl");
		if (bag.hits[i].af == 4) {
			if (strcmp(bag.hits[i].ip, "142.250.190.78") != 0)
				return fail("A address");
			saw_a = 1;
		} else if (bag.hits[i].af == 6) {
			if (strcmp(bag.hits[i].ip, "2800:3f0:4004:80c::200e") != 0)
				return fail("AAAA address");
			saw_aaaa = 1;
		} else {
			return fail("af");
		}
	}
	if (!saw_a || !saw_aaaa)
		return fail("missing A or AAAA");

	/* Só AAAA (sem A) */
	{
		uint8_t aaaa_only[] = {
			0x00, 0x01, 0x81, 0x80, 0x00, 0x01, 0x00, 0x01,
			0x00, 0x00, 0x00, 0x00,
			0x03, 'w', 'w', 'w',
			0x07, 'e', 'x', 'a', 'm', 'p', 'l', 'e',
			0x03, 'c', 'o', 'm',
			0x00,
			0x00, 0x1c, 0x00, 0x01,
			0xc0, 0x0c,
			0x00, 0x1c, 0x00, 0x01,
			0x00, 0x00, 0x00, 0x3c,
			0x00, 0x10,
			0x20, 0x01, 0x0d, 0xb8, 0x00, 0x00, 0x00, 0x00,
			0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x01
		};
		memset(&bag, 0, sizeof(bag));
		n = layer7_dns_foreach_a_aaaa(aaaa_only, sizeof(aaaa_only),
		    on_rr, &bag);
		if (n != 1 || bag.n != 1 || bag.hits[0].af != 6)
			return fail("AAAA-only");
		if (strcmp(bag.hits[0].ip, "2001:db8::1") != 0)
			return fail("AAAA-only address");
	}

	/* Query com QR=0 deve ser rejeitada pelo parser de answers */
	{
		uint8_t query_pkt[] = {
			0x12, 0x34, 0x01, 0x00, 0x00, 0x01, 0x00, 0x00,
			0x00, 0x00, 0x00, 0x00,
			0x03, 'w', 'w', 'w',
			0x07, 'e', 'x', 'a', 'm', 'p', 'l', 'e',
			0x03, 'c', 'o', 'm',
			0x00,
			0x00, 0x01, 0x00, 0x01
		};
		memset(&bag, 0, sizeof(bag));
		n = layer7_dns_foreach_a_aaaa(query_pkt, sizeof(query_pkt),
		    on_rr, &bag);
		if (n != -1 || bag.n != 0)
			return fail("QR=0 must be rejected");
	}

	printf("PASS test_dns_aaaa_wire\n");
	return 0;
}
