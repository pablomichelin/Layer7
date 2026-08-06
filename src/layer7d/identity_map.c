/*
 * Identity session map — init/fini + rwlock (20.12).
 * Sem IO; sem threads produtoras (ADR-0028 §4 até 20.15).
 */
#include "identity_map.h"

#include <errno.h>
#include <stdlib.h>
#include <string.h>

void
layer7_idmap_limits(struct l7_id_map_limits *out)
{
	if (out == NULL)
		return;
	out->max_ips_per_user = L7_IDMAP_MAX_IPS_PER_USER;
	out->max_sessions = L7_IDMAP_MAX_SESSIONS;
	out->default_ttl_sec = L7_IDMAP_DEFAULT_TTL_SEC;
	out->max_groups_cache = L7_IDMAP_MAX_GROUPS_CACHE;
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
