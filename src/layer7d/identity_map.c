/*
 * Identity session map — init/fini (20.12) + API (20.13).
 * Sem IO de rede; sem threads produtoras (ADR-0028 §4 até 20.15).
 */
#include "identity_map.h"

#include <arpa/inet.h>
#include <errno.h>
#include <netinet/in.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>

void
layer7_idmap_limits(struct l7_id_map_limits *out)
{
	if (out == NULL)
		return;
	out->max_ips_per_user = L7_IDMAP_MAX_IPS_PER_USER;
	out->max_sessions = L7_IDMAP_MAX_SESSIONS;
	out->default_ttl_sec = L7_IDMAP_DEFAULT_TTL_SEC;
	out->max_groups_cache = L7_IDMAP_MAX_GROUPS_CACHE;
	out->conflict_window_sec = L7_IDMAP_CONFLICT_WINDOW_SEC;
}

int
layer7_idmap_init(struct l7_id_map *m)
{
	int rc;

	if (m == NULL)
		return -1;
	memset(m, 0, sizeof(*m));
	m->sessions = calloc(L7_IDMAP_MAX_SESSIONS, sizeof(struct l7_id_session));
	if (m->sessions == NULL)
		return -1;
	rc = pthread_rwlock_init(&m->lock, NULL);
	if (rc != 0) {
		free(m->sessions);
		m->sessions = NULL;
		errno = rc;
		return -1;
	}
	m->capacity = L7_IDMAP_MAX_SESSIONS;
	m->count = 0;
	m->default_ttl_sec = L7_IDMAP_DEFAULT_TTL_SEC;
	m->conflict_window_sec = L7_IDMAP_CONFLICT_WINDOW_SEC;
	m->initialized = 1;
	return 0;
}

void
layer7_idmap_fini(struct l7_id_map *m)
{
	if (m == NULL || !m->initialized)
		return;
	(void)pthread_rwlock_destroy(&m->lock);
	free(m->sessions);
	memset(m, 0, sizeof(*m));
}

unsigned
layer7_idmap_count(const struct l7_id_map *m)
{
	if (m == NULL || !m->initialized)
		return 0;
	return m->count;
}

unsigned
layer7_idmap_capacity(const struct l7_id_map *m)
{
	if (m == NULL || !m->initialized)
		return 0;
	return m->capacity;
}

int
layer7_idmap_rdlock(struct l7_id_map *m)
{
	if (m == NULL || !m->initialized)
		return -1;
	return pthread_rwlock_rdlock(&m->lock) == 0 ? 0 : -1;
}

int
layer7_idmap_wrlock(struct l7_id_map *m)
{
	if (m == NULL || !m->initialized)
		return -1;
	return pthread_rwlock_wrlock(&m->lock) == 0 ? 0 : -1;
}

int
layer7_idmap_unlock(struct l7_id_map *m)
{
	if (m == NULL || !m->initialized)
		return -1;
	return pthread_rwlock_unlock(&m->lock) == 0 ? 0 : -1;
}

int
layer7_idmap_addr_equal(const struct l7_id_addr *a, const struct l7_id_addr *b)
{
	size_t n;

	if (a == NULL || b == NULL || a->family != b->family)
		return 0;
	if (a->family == AF_INET)
		n = 4;
	else if (a->family == AF_INET6)
		n = 16;
	else
		return 0;
	return memcmp(a->addr, b->addr, n) == 0;
}

int
layer7_idmap_addr_set_ipv4(struct l7_id_addr *a, uint32_t host_order)
{
	uint32_t be;

	if (a == NULL)
		return -1;
	memset(a, 0, sizeof(*a));
	a->family = AF_INET;
	be = htonl(host_order);
	memcpy(a->addr, &be, 4);
	return 0;
}

int
layer7_idmap_addr_set_ipv6(struct l7_id_addr *a, const uint8_t in6[16])
{
	if (a == NULL || in6 == NULL)
		return -1;
	memset(a, 0, sizeof(*a));
	a->family = AF_INET6;
	memcpy(a->addr, in6, 16);
	return 0;
}

static int
user_eq(const char *a, const char *b)
{
	if (a == NULL || b == NULL)
		return 0;
	return strcmp(a, b) == 0;
}

static int
find_user_idx(const struct l7_id_map *m, const char *user)
{
	unsigned i;

	for (i = 0; i < m->capacity; i++) {
		if (m->sessions[i].in_use &&
		    user_eq(m->sessions[i].user, user))
			return (int)i;
	}
	return -1;
}

static int
session_has_ip(const struct l7_id_session *s, const struct l7_id_addr *ip)
{
	unsigned i;

	for (i = 0; i < s->n_ips; i++) {
		if (layer7_idmap_addr_equal(&s->ips[i], ip))
			return (int)i;
	}
	return -1;
}

static void
session_remove_ip_at(struct l7_id_session *s, unsigned idx)
{
	if (idx >= s->n_ips)
		return;
	if (idx + 1 < s->n_ips)
		memmove(&s->ips[idx], &s->ips[idx + 1],
		    (s->n_ips - idx - 1) * sizeof(s->ips[0]));
	s->n_ips--;
	memset(&s->ips[s->n_ips], 0, sizeof(s->ips[0]));
}

static void
clear_session(struct l7_id_session *s)
{
	memset(s, 0, sizeof(*s));
}

static int
find_free_slot(const struct l7_id_map *m)
{
	unsigned i;

	for (i = 0; i < m->capacity; i++) {
		if (!m->sessions[i].in_use)
			return (int)i;
	}
	return -1;
}

/* Eviction: mais antigo (seen_at) primeiro. */
static int
evict_oldest(struct l7_id_map *m)
{
	unsigned i;
	int best = -1;
	time_t oldest = 0;

	for (i = 0; i < m->capacity; i++) {
		if (!m->sessions[i].in_use)
			continue;
		if (best < 0 || m->sessions[i].seen_at < oldest) {
			best = (int)i;
			oldest = m->sessions[i].seen_at;
		}
	}
	if (best < 0)
		return -1;
	clear_session(&m->sessions[best]);
	if (m->count > 0)
		m->count--;
	return best;
}

static void
apply_groups(struct l7_id_session *s, const char *const *groups,
    unsigned n_groups, int *truncated)
{
	unsigned i, n;

	if (groups == NULL || n_groups == 0)
		return;
	n = n_groups;
	if (n > L7_IDMAP_MAX_GROUPS_CACHE) {
		n = L7_IDMAP_MAX_GROUPS_CACHE;
		if (truncated)
			*truncated = 1;
	}
	s->n_groups = 0;
	for (i = 0; i < n; i++) {
		if (groups[i] == NULL || groups[i][0] == '\0')
			continue;
		snprintf(s->groups[s->n_groups], L7_IDMAP_GROUP_MAX, "%s",
		    groups[i]);
		s->n_groups++;
	}
}

static void
mark_multi_user_for_ip(struct l7_id_map *m, const struct l7_id_addr *ip)
{
	unsigned i;

	for (i = 0; i < m->capacity; i++) {
		if (!m->sessions[i].in_use)
			continue;
		if (session_has_ip(&m->sessions[i], ip) >= 0)
			m->sessions[i].multi_user = 1;
	}
}

static int
count_users_with_ip(const struct l7_id_map *m, const struct l7_id_addr *ip)
{
	unsigned i;
	int n = 0;

	for (i = 0; i < m->capacity; i++) {
		if (!m->sessions[i].in_use)
			continue;
		if (session_has_ip(&m->sessions[i], ip) >= 0)
			n++;
	}
	return n;
}

int
layer7_idmap_upsert(struct l7_id_map *m, const char *user,
    const struct l7_id_addr *ip, enum l7_id_source source,
    const char *const *groups, unsigned n_groups, time_t now)
{
	int idx, ip_idx, other, free_i, trunc = 0, rc = 0;
	unsigned i;
	struct l7_id_session *s;

	if (m == NULL || !m->initialized || user == NULL || user[0] == '\0' ||
	    ip == NULL || (ip->family != AF_INET && ip->family != AF_INET6))
		return -1;
	if (layer7_idmap_wrlock(m) != 0)
		return -1;

	/* Conflito / last-writer noutros users com o mesmo IP */
	for (i = 0; i < m->capacity; i++) {
		if (!m->sessions[i].in_use || user_eq(m->sessions[i].user, user))
			continue;
		ip_idx = session_has_ip(&m->sessions[i], ip);
		if (ip_idx < 0)
			continue;
		other = (int)i;
		if (now >= m->sessions[other].seen_at &&
		    (now - m->sessions[other].seen_at) <=
			(time_t)m->conflict_window_sec) {
			/* Janela de conflito: manter ambos + multi_user */
			m->sessions[other].multi_user = 1;
		} else {
			/* Last-writer: remove IP do outro */
			session_remove_ip_at(&m->sessions[other],
			    (unsigned)ip_idx);
			if (m->sessions[other].n_ips == 0) {
				clear_session(&m->sessions[other]);
				if (m->count > 0)
					m->count--;
			}
		}
	}

	idx = find_user_idx(m, user);
	if (idx < 0) {
		free_i = find_free_slot(m);
		if (free_i < 0) {
			free_i = evict_oldest(m);
			if (free_i < 0) {
				(void)layer7_idmap_unlock(m);
				return 2;
			}
			rc = 2; /* sinaliza eviction (ainda OK) */
		}
		idx = free_i;
		s = &m->sessions[idx];
		clear_session(s);
		s->in_use = 1;
		snprintf(s->user, sizeof(s->user), "%s", user);
		m->count++;
	} else
		s = &m->sessions[idx];

	s->source = source;
	s->seen_at = now;
	s->expires_at = now + (time_t)m->default_ttl_sec;
	apply_groups(s, groups, n_groups, &trunc);

	ip_idx = session_has_ip(s, ip);
	if (ip_idx < 0) {
		if (s->n_ips >= L7_IDMAP_MAX_IPS_PER_USER) {
			/* Descarta o IP mais antigo da lista (índice 0) */
			session_remove_ip_at(s, 0);
			trunc = 1;
		}
		s->ips[s->n_ips] = *ip;
		s->n_ips++;
	}

	if (count_users_with_ip(m, ip) > 1)
		mark_multi_user_for_ip(m, ip);
	else {
		/* Único dono deste IP: limpa multi_user se o user já não
		 * partilha nenhum IP com outros. */
		int still = 0;
		unsigned j;

		idx = find_user_idx(m, user);
		if (idx >= 0) {
			s = &m->sessions[idx];
			for (j = 0; j < s->n_ips; j++) {
				if (count_users_with_ip(m, &s->ips[j]) > 1) {
					still = 1;
					break;
				}
			}
			if (!still)
				s->multi_user = 0;
		}
	}

	(void)layer7_idmap_unlock(m);
	if (trunc && rc == 0)
		return 1;
	return rc;
}

int
layer7_idmap_refresh(struct l7_id_map *m, const char *user, time_t now)
{
	int idx;

	if (m == NULL || !m->initialized || user == NULL)
		return -1;
	if (layer7_idmap_wrlock(m) != 0)
		return -1;
	idx = find_user_idx(m, user);
	if (idx < 0) {
		(void)layer7_idmap_unlock(m);
		return -1;
	}
	m->sessions[idx].seen_at = now;
	m->sessions[idx].expires_at = now + (time_t)m->default_ttl_sec;
	(void)layer7_idmap_unlock(m);
	return 0;
}

unsigned
layer7_idmap_expire(struct l7_id_map *m, time_t now)
{
	unsigned i, removed = 0;

	if (m == NULL || !m->initialized)
		return 0;
	if (layer7_idmap_wrlock(m) != 0)
		return 0;
	for (i = 0; i < m->capacity; i++) {
		if (!m->sessions[i].in_use)
			continue;
		if (m->sessions[i].expires_at <= now) {
			clear_session(&m->sessions[i]);
			if (m->count > 0)
				m->count--;
			removed++;
		}
	}
	(void)layer7_idmap_unlock(m);
	return removed;
}

int
layer7_idmap_remove_user(struct l7_id_map *m, const char *user)
{
	int idx;

	if (m == NULL || !m->initialized || user == NULL)
		return -1;
	if (layer7_idmap_wrlock(m) != 0)
		return -1;
	idx = find_user_idx(m, user);
	if (idx < 0) {
		(void)layer7_idmap_unlock(m);
		return -1;
	}
	clear_session(&m->sessions[idx]);
	if (m->count > 0)
		m->count--;
	(void)layer7_idmap_unlock(m);
	return 0;
}

int
layer7_idmap_lookup_ip(struct l7_id_map *m, const struct l7_id_addr *ip,
    char *out_user, size_t out_user_sz, enum l7_id_source *out_src)
{
	unsigned i;
	int found = -1;
	int n_hit = 0;
	int multi = 0;

	if (m == NULL || !m->initialized || ip == NULL)
		return -1;
	if (layer7_idmap_rdlock(m) != 0)
		return -1;
	for (i = 0; i < m->capacity; i++) {
		if (!m->sessions[i].in_use)
			continue;
		if (session_has_ip(&m->sessions[i], ip) < 0)
			continue;
		n_hit++;
		if (m->sessions[i].multi_user)
			multi = 1;
		if (found < 0)
			found = (int)i;
	}
	if (n_hit == 0) {
		(void)layer7_idmap_unlock(m);
		return -1;
	}
	if (n_hit > 1 || multi) {
		(void)layer7_idmap_unlock(m);
		if (out_user != NULL && out_user_sz > 0)
			out_user[0] = '\0';
		if (out_src)
			*out_src = L7_ID_SRC_NONE;
		return 1; /* multi_user → ad_* não-match */
	}
	if (out_user != NULL && out_user_sz > 0)
		snprintf(out_user, out_user_sz, "%s",
		    m->sessions[found].user);
	if (out_src)
		*out_src = m->sessions[found].source;
	(void)layer7_idmap_unlock(m);
	return 0;
}

int
layer7_idmap_export_user_ips(struct l7_id_map *m, const char *user,
    struct l7_id_addr *out, unsigned max_out)
{
	int idx;
	unsigned n, i;

	if (m == NULL || !m->initialized || user == NULL)
		return -1;
	if (layer7_idmap_rdlock(m) != 0)
		return -1;
	idx = find_user_idx(m, user);
	if (idx < 0) {
		(void)layer7_idmap_unlock(m);
		return 0;
	}
	n = m->sessions[idx].n_ips;
	if (out != NULL && max_out > 0) {
		if (n > max_out)
			n = max_out;
		for (i = 0; i < n; i++)
			out[i] = m->sessions[idx].ips[i];
	} else
		n = m->sessions[idx].n_ips;
	(void)layer7_idmap_unlock(m);
	return (int)n;
}

static const char *
source_name(enum l7_id_source s)
{
	switch (s) {
	case L7_ID_SRC_RADIUS:
		return "radius";
	case L7_ID_SRC_DC_AGENT:
		return "dc_agent";
	case L7_ID_SRC_ENDPOINT:
		return "endpoint";
	case L7_ID_SRC_TS:
		return "ts";
	case L7_ID_SRC_MANUAL:
		return "manual";
	default:
		return "none";
	}
}

static void
addr_to_str(const struct l7_id_addr *a, char *buf, size_t buflen)
{
	if (a->family == AF_INET) {
		struct in_addr in;

		memcpy(&in.s_addr, a->addr, 4);
		if (inet_ntop(AF_INET, &in, buf, (socklen_t)buflen) == NULL)
			snprintf(buf, buflen, "?");
	} else if (a->family == AF_INET6) {
		if (inet_ntop(AF_INET6, a->addr, buf, (socklen_t)buflen) == NULL)
			snprintf(buf, buflen, "?");
	} else
		snprintf(buf, buflen, "?");
}

int
layer7_idmap_dump_json(struct l7_id_map *m, char *buf, size_t bufsz)
{
	size_t off = 0;
	unsigned i, j, k;
	int n;

	if (m == NULL || !m->initialized || buf == NULL || bufsz < 3)
		return -1;
	if (layer7_idmap_rdlock(m) != 0)
		return -1;

#define APPEND(...)                                                         \
	do {                                                                \
		n = snprintf(buf + off, bufsz > off ? bufsz - off : 0,       \
		    __VA_ARGS__);                                           \
		if (n < 0) {                                                \
			(void)layer7_idmap_unlock(m);                       \
			return -1;                                          \
		}                                                           \
		if ((size_t)n >= (bufsz > off ? bufsz - off : 0)) {         \
			if (bufsz >= 4) {                                   \
				buf[bufsz - 4] = '.';                      \
				buf[bufsz - 3] = '.';                      \
				buf[bufsz - 2] = '.';                      \
				buf[bufsz - 1] = '\0';                       \
			}                                                   \
			(void)layer7_idmap_unlock(m);                       \
			return (int)(bufsz - 1);                            \
		}                                                           \
		off += (size_t)n;                                           \
	} while (0)

	APPEND("{\"sessions\":[");
	k = 0;
	for (i = 0; i < m->capacity; i++) {
		char ipbuf[64];
		const struct l7_id_session *s = &m->sessions[i];
		const char *status;

		if (!s->in_use)
			continue;
		if (s->multi_user)
			status = "multi_user";
		else
			status = "active";
		if (k > 0)
			APPEND(",");
		APPEND("{\"user\":\"%s\",\"source\":\"%s\",\"seen_at\":%lld,"
		    "\"expires_at\":%lld,\"multi_user\":%s,\"status\":\"%s\","
		    "\"ips\":[",
		    s->user, source_name(s->source),
		    (long long)s->seen_at, (long long)s->expires_at,
		    s->multi_user ? "true" : "false", status);
		for (j = 0; j < s->n_ips; j++) {
			addr_to_str(&s->ips[j], ipbuf, sizeof(ipbuf));
			APPEND("%s\"%s\"", j ? "," : "", ipbuf);
		}
		APPEND("],\"groups\":[");
		for (j = 0; j < s->n_groups; j++)
			APPEND("%s\"%s\"", j ? "," : "", s->groups[j]);
		APPEND("]}");
		k++;
	}
	APPEND("],\"count\":%u}", m->count);
#undef APPEND

	(void)layer7_idmap_unlock(m);
	return (int)off;
}
