/*
 * dns_corr.h — Correlação query↔resposta DNS + allowlist de resolvers (BG-104).
 * Header-only para testes unitários sem pcap/nDPI.
 */
#ifndef LAYER7_DNS_CORR_H
#define LAYER7_DNS_CORR_H

#include "dns_observe.h"

#include <ctype.h>
#include <stdio.h>
#include <stdint.h>
#include <string.h>
#include <time.h>

#ifndef INET6_ADDRSTRLEN
#define INET6_ADDRSTRLEN 46
#endif

#define L7_DNS_PEND_MAX       256
#define L7_DNS_PEND_TTL_SEC   10
#define L7_DNS_RESOLVERS_MAX  32

struct layer7_dns_pend {
	uint8_t  in_use;
	uint16_t id;
	time_t   expires;
	char     client[INET6_ADDRSTRLEN];
	char     resolver[INET6_ADDRSTRLEN];
	char     qname[LAYER7_DNS_HOST_MAX];
};

struct layer7_dns_corr {
	struct layer7_dns_pend pend[L7_DNS_PEND_MAX];
	unsigned int pend_next;
	char resolvers[L7_DNS_RESOLVERS_MAX][INET6_ADDRSTRLEN];
	int n_resolvers;
};

static inline int
layer7_dns_qname_eq(const char *a, const char *b)
{
	size_t i;

	if (!a || !b)
		return 0;
	for (i = 0; a[i] || b[i]; i++) {
		unsigned char ca = (unsigned char)a[i];
		unsigned char cb = (unsigned char)b[i];
		if (tolower(ca) != tolower(cb))
			return 0;
	}
	return 1;
}

static inline void
layer7_dns_corr_reset(struct layer7_dns_corr *c)
{
	if (!c)
		return;
	memset(c, 0, sizeof(*c));
}

static inline void
layer7_dns_corr_resolvers_reset(struct layer7_dns_corr *c)
{
	if (!c)
		return;
	c->n_resolvers = 0;
	memset(c->resolvers, 0, sizeof(c->resolvers));
}

static inline int
layer7_dns_corr_resolver_add(struct layer7_dns_corr *c, const char *ip)
{
	int i;

	if (!c || !ip || !*ip)
		return -1;
	if (strlen(ip) >= INET6_ADDRSTRLEN)
		return -1;
	for (i = 0; i < c->n_resolvers; i++) {
		if (strcmp(c->resolvers[i], ip) == 0)
			return 0;
	}
	if (c->n_resolvers >= L7_DNS_RESOLVERS_MAX)
		return -1;
	snprintf(c->resolvers[c->n_resolvers], INET6_ADDRSTRLEN, "%s", ip);
	c->n_resolvers++;
	return 0;
}

/* Fail-open se lista vazia; caso contrário exige membership exacta. */
static inline int
layer7_dns_corr_resolver_allowed(const struct layer7_dns_corr *c,
    const char *ip)
{
	int i;

	if (!c || !ip || !*ip)
		return 0;
	if (c->n_resolvers <= 0)
		return 1;
	for (i = 0; i < c->n_resolvers; i++) {
		if (strcmp(c->resolvers[i], ip) == 0)
			return 1;
	}
	return 0;
}

static inline void
layer7_dns_pend_remember(struct layer7_dns_corr *c, const char *client,
    const char *resolver, uint16_t id, const char *qname, time_t now)
{
	struct layer7_dns_pend *p;
	unsigned int i;

	if (!c || !client || !*client || !resolver || !*resolver)
		return;
	for (i = 0; i < L7_DNS_PEND_MAX; i++) {
		p = &c->pend[i];
		if (p->in_use && p->id == id &&
		    strcmp(p->client, client) == 0 &&
		    strcmp(p->resolver, resolver) == 0) {
			p->expires = now + L7_DNS_PEND_TTL_SEC;
			if (qname && *qname)
				snprintf(p->qname, sizeof(p->qname), "%s",
				    qname);
			return;
		}
	}
	p = &c->pend[c->pend_next++ % L7_DNS_PEND_MAX];
	p->in_use = 1;
	p->id = id;
	p->expires = now + L7_DNS_PEND_TTL_SEC;
	snprintf(p->client, sizeof(p->client), "%s", client);
	snprintf(p->resolver, sizeof(p->resolver), "%s", resolver);
	if (qname && *qname)
		snprintf(p->qname, sizeof(p->qname), "%s", qname);
	else
		p->qname[0] = '\0';
}

/*
 * Consome pendência se client+id+resolver batem e qname (se presente no pend)
 * coincide. Retorna 1 se aceite.
 */
static inline int
layer7_dns_pend_consume(struct layer7_dns_corr *c, const char *client,
    const char *resolver, uint16_t id, const char *qname, time_t now)
{
	unsigned int i;

	if (!c || !client || !*client || !resolver || !*resolver)
		return 0;
	if (!layer7_dns_corr_resolver_allowed(c, resolver))
		return 0;
	for (i = 0; i < L7_DNS_PEND_MAX; i++) {
		struct layer7_dns_pend *p = &c->pend[i];
		if (!p->in_use)
			continue;
		if (p->expires < now) {
			p->in_use = 0;
			continue;
		}
		if (p->id != id)
			continue;
		if (strcmp(p->client, client) != 0)
			continue;
		if (strcmp(p->resolver, resolver) != 0)
			continue;
		if (p->qname[0] && qname && *qname &&
		    !layer7_dns_qname_eq(p->qname, qname))
			continue;
		p->in_use = 0;
		return 1;
	}
	return 0;
}

static inline int
layer7_dns_extract_qname(const uint8_t *payload, size_t payload_len,
    char *out, size_t out_len)
{
	size_t off = 12;

	if (!payload || payload_len < 12 || !out || out_len == 0)
		return -1;
	return layer7_dns_read_name(payload, payload_len, &off, out, out_len, 0);
}

#endif /* LAYER7_DNS_CORR_H */
