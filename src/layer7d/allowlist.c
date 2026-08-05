/*
 * allowlist.c — Bloco 3 (Fase 1) + passo 12.8 (IPv6 host/CIDR).
 *
 * Estrutura linear, comparacoes O(N) com N pequeno (<=256). Conservador por
 * defeito: entradas invalidas sao rejeitadas; nao toca em malloc dinamico.
 */
#include "allowlist.h"

#include <arpa/inet.h>
#include <ctype.h>
#include <netinet/in.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <strings.h>
#include <sys/socket.h>

void
l7_allowlist_reset(struct l7_allowlist *al)
{
	if (!al)
		return;
	memset(al, 0, sizeof(*al));
}

int
l7_allowlist_count(const struct l7_allowlist *al)
{
	return al ? al->n : 0;
}

static int
parse_ipv4(const char *s, uint32_t *out)
{
	unsigned a, b, c, d;

	if (!s || !*s)
		return -1;
	if (sscanf(s, "%u.%u.%u.%u", &a, &b, &c, &d) != 4)
		return -1;
	if (a > 255 || b > 255 || c > 255 || d > 255)
		return -1;
	*out = (uint32_t)((a << 24) | (b << 16) | (c << 8) | d);
	return 0;
}

static int
parse_ipv6(const char *s, unsigned char out[16])
{
	struct in6_addr a6;

	if (!s || !*s || !out)
		return -1;
	if (inet_pton(AF_INET6, s, &a6) != 1)
		return -1;
	memcpy(out, &a6, 16);
	return 0;
}

/* S-03: ::1, fe80::/10, ff00::/8 — nao entram na allowlist PF. */
static int
ipv6_forbidden(const unsigned char addr[16], int prefix)
{
	struct in6_addr a6;

	memcpy(&a6, addr, 16);
	if (IN6_IS_ADDR_LOOPBACK(&a6))
		return 1;
	if (IN6_IS_ADDR_LINKLOCAL(&a6))
		return 1;
	if (IN6_IS_ADDR_MULTICAST(&a6))
		return 1;
	/*
	 * Prefixos demasiado curtos podem cobrir link-local/multicast
	 * mesmo com network base "limpo" (ex. ::/1). Conservador: <10.
	 */
	if (prefix > 0 && prefix < 10)
		return 1;
	return 0;
}

static int
cidr_v6_match(const unsigned char ip[16], const unsigned char net[16],
    int prefix)
{
	int full, rem, i;
	unsigned char mask;

	if (prefix <= 0)
		return 0;
	if (prefix > 128)
		prefix = 128;
	full = prefix / 8;
	rem = prefix % 8;
	for (i = 0; i < full; i++) {
		if (ip[i] != net[i])
			return 0;
	}
	if (rem == 0)
		return 1;
	mask = (unsigned char)(0xffu << (unsigned)(8 - rem));
	return ((ip[full] & mask) == (net[full] & mask));
}

static int
classify(const char *s, struct l7_allowlist_entry *e)
{
	const char *slash;
	size_t i, n;
	char ip_buf[64];
	int prefix;
	unsigned char a6[16];

	if (!s || !*s)
		return -1;
	n = strlen(s);
	if (n >= L7_AL_ENTRY_LEN)
		return -1;

	for (i = 0; i < n; i++) {
		unsigned char c = (unsigned char)s[i];
		if (c <= 0x20 || c == 0x7f)
			return -1;
		if (c == '"' || c == '\\' || c == '\'' || c == '`' ||
		    c == '$' || c == ';' || c == '|' || c == '&' ||
		    c == '<' || c == '>' || c == '(' || c == ')')
			return -1;
	}

	slash = strchr(s, '/');
	if (slash) {
		size_t len = (size_t)(slash - s);
		if (len == 0 || len >= sizeof(ip_buf))
			return -1;
		memcpy(ip_buf, s, len);
		ip_buf[len] = '\0';
		prefix = atoi(slash + 1);

		if (strchr(ip_buf, ':') != NULL) {
			if (parse_ipv6(ip_buf, a6) != 0)
				return -1;
			if (prefix < 1 || prefix > 128)
				return -1;
			if (ipv6_forbidden(a6, prefix))
				return -1;
			memcpy(e->ip6, a6, 16);
			e->ip = 0;
			e->prefix = prefix;
			e->kind = L7_AL_IPV6_CIDR;
			return 0;
		}

		if (parse_ipv4(ip_buf, &e->ip) != 0)
			return -1;
		/* Rejeita 0.0.0.0/0 — allowlist aberta demais para PF. */
		if (prefix < 1 || prefix > 32)
			return -1;
		memset(e->ip6, 0, sizeof(e->ip6));
		e->prefix = prefix;
		e->kind = L7_AL_IPV4_CIDR;
		return 0;
	}

	/* IPv6 host (tem ':') */
	if (strchr(s, ':') != NULL) {
		if (parse_ipv6(s, a6) != 0)
			return -1;
		if (ipv6_forbidden(a6, 128))
			return -1;
		memcpy(e->ip6, a6, 16);
		e->ip = 0;
		e->prefix = 128;
		e->kind = L7_AL_IPV6_HOST;
		return 0;
	}

	if (s[0] >= '0' && s[0] <= '9') {
		if (parse_ipv4(s, &e->ip) != 0)
			return -1;
		memset(e->ip6, 0, sizeof(e->ip6));
		e->prefix = 32;
		e->kind = L7_AL_IPV4_HOST;
		return 0;
	}

	if (strchr(s, '.') == NULL)
		return -1;
	if (n > 253)
		return -1;
	e->ip = 0;
	memset(e->ip6, 0, sizeof(e->ip6));
	e->prefix = 0;
	e->kind = L7_AL_DOMAIN;
	return 0;
}

int
l7_allowlist_add(struct l7_allowlist *al, const char *value)
{
	struct l7_allowlist_entry tmp;
	int i;

	if (!al || !value)
		return -1;
	if (al->n >= L7_AL_MAX)
		return -1;

	memset(&tmp, 0, sizeof(tmp));
	if (classify(value, &tmp) != 0)
		return -1;
	snprintf(tmp.value, sizeof(tmp.value), "%s", value);

	for (i = 0; i < al->n; i++) {
		if (strcasecmp(al->entries[i].value, tmp.value) == 0)
			return 0;
	}
	al->entries[al->n++] = tmp;
	return 0;
}

int
l7_allowlist_parse_json(struct l7_allowlist *al, const char *json, size_t len)
{
	const char *key, *arr, *end, *p;
	int added = 0;

	if (!al || !json)
		return 0;
	end = json + (len ? len : strlen(json));

	key = strstr(json, "\"dst_allowlist\"");
	if (!key || key >= end)
		return 0;
	arr = strchr(key, '[');
	if (!arr || arr >= end)
		return 0;
	p = arr + 1;
	while (p < end && *p != ']' && al->n < L7_AL_MAX) {
		while (p < end && (*p == ' ' || *p == '\t' || *p == '\n' ||
		    *p == '\r' || *p == ','))
			p++;
		if (p >= end || *p == ']')
			break;
		if (*p != '"') {
			break;
		}
		{
			const char *sq = p + 1;
			char buf[L7_AL_ENTRY_LEN];
			size_t n = 0;
			while (sq < end && *sq && *sq != '"' &&
			    n + 1 < sizeof(buf)) {
				if (*sq == '\\' && sq[1])
					sq++;
				buf[n++] = *sq++;
			}
			if (sq >= end || *sq != '"')
				break;
			buf[n] = '\0';
			if (l7_allowlist_add(al, buf) == 0)
				added++;
			p = sq + 1;
		}
	}
	return added;
}

int
l7_allowlist_load_seed_file(struct l7_allowlist *al, const char *path)
{
	FILE *f;
	char line[L7_AL_ENTRY_LEN + 16];
	int added = 0;

	if (!al || !path)
		return 0;
	f = fopen(path, "r");
	if (!f)
		return 0;
	while (fgets(line, sizeof(line), f) && al->n < L7_AL_MAX) {
		char *s = line;
		size_t n;
		while (*s == ' ' || *s == '\t')
			s++;
		if (*s == '#' || *s == '\0' || *s == '\n' || *s == '\r')
			continue;
		n = strlen(s);
		while (n > 0 && (s[n - 1] == '\n' || s[n - 1] == '\r' ||
		    s[n - 1] == ' ' || s[n - 1] == '\t'))
			s[--n] = '\0';
		if (n == 0)
			continue;
		if (l7_allowlist_add(al, s) == 0)
			added++;
	}
	fclose(f);
	return added;
}

static int
domain_suffix_match(const char *host, const char *suffix)
{
	size_t lh, ls;

	if (!host || !*host || !suffix || !*suffix)
		return 0;
	lh = strlen(host);
	ls = strlen(suffix);
	if (lh == ls)
		return strcasecmp(host, suffix) == 0;
	if (lh < ls + 1)
		return 0;
	if (host[lh - ls - 1] != '.')
		return 0;
	return strcasecmp(host + (lh - ls), suffix) == 0;
}

int
l7_allowlist_contains_domain(const struct l7_allowlist *al, const char *host)
{
	int i;

	if (!al || !host || !*host)
		return 0;
	for (i = 0; i < al->n; i++) {
		if (al->entries[i].kind != L7_AL_DOMAIN)
			continue;
		if (domain_suffix_match(host, al->entries[i].value))
			return 1;
	}
	return 0;
}

int
l7_allowlist_contains_ip(const struct l7_allowlist *al, const char *ip_str)
{
	uint32_t ip, mask;
	unsigned char a6[16];
	int i, p;
	int is_v6 = 0;

	if (!al || !ip_str || !*ip_str)
		return 0;
	if (parse_ipv4(ip_str, &ip) == 0)
		is_v6 = 0;
	else if (parse_ipv6(ip_str, a6) == 0)
		is_v6 = 1;
	else
		return 0;

	for (i = 0; i < al->n; i++) {
		const struct l7_allowlist_entry *e = &al->entries[i];

		if (!is_v6) {
			if (e->kind == L7_AL_IPV4_HOST) {
				if (ip == e->ip)
					return 1;
			} else if (e->kind == L7_AL_IPV4_CIDR) {
				p = e->prefix;
				if (p < 1 || p > 32)
					continue;
				if (p >= 32) {
					if (ip == e->ip)
						return 1;
					continue;
				}
				mask = (uint32_t)(0xffffffffU <<
				    (unsigned)(32 - p));
				if ((ip & mask) == (e->ip & mask))
					return 1;
			}
			continue;
		}

		if (e->kind == L7_AL_IPV6_HOST) {
			if (memcmp(a6, e->ip6, 16) == 0)
				return 1;
		} else if (e->kind == L7_AL_IPV6_CIDR) {
			if (cidr_v6_match(a6, e->ip6, e->prefix))
				return 1;
		}
	}
	return 0;
}
