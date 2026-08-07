/*
 * identity_ldap.c — IM4 / 20.17
 * Config + cache TTL + fail-mode + worker + OpenLDAP (HAVE_OPENLDAP).
 */
#include "identity_ldap.h"

#include <ctype.h>
#include <pthread.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <strings.h>
#include <sys/time.h>
#include <unistd.h>

#if !defined(__FreeBSD__) && !defined(__OpenBSD__) && !defined(__NetBSD__)
/* macOS / outros: explicit_bzero pode faltar nos headers sem feature macros. */
#ifndef explicit_bzero
static void
layer7_explicit_bzero(void *p, size_t n)
{
	volatile unsigned char *v = (volatile unsigned char *)p;
	while (n-- > 0)
		*v++ = 0;
}
#define explicit_bzero(p, n) layer7_explicit_bzero((p), (n))
#endif
#endif

#ifndef HAVE_OPENLDAP
#define HAVE_OPENLDAP 0
#endif

#if HAVE_OPENLDAP
#define LDAP_DEPRECATED 1
#include <ldap.h>
#endif

/* ---- logging stubs (main provides L7_* macros when linked in daemon) ---- */
#ifndef L7_NOTE
#define L7_NOTE(...) ((void)0)
#endif
#ifndef L7_WARN
#define L7_WARN(...) ((void)0)
#endif

/* ---- config defaults / wipe ---- */

void
layer7_ldap_cfg_defaults(struct l7_ldap_cfg *c)
{
	if (c == NULL)
		return;
	memset(c, 0, sizeof(*c));
	c->port = L7_LDAP_DEFAULT_PORT_LDAPS;
	c->use_tls = 1;
	snprintf(c->user_filter, sizeof(c->user_filter),
	    "(&(objectCategory=person)(objectClass=user))");
	snprintf(c->group_filter, sizeof(c->group_filter),
	    "(objectClass=group)");
	c->group_depth = 5;
	c->max_members = 4096;
	c->cache_ttl_sec = L7_LDAP_DEFAULT_CACHE_TTL_SEC;
}

void
layer7_ldap_cfg_wipe_secret(struct l7_ldap_cfg *c)
{
	if (c == NULL)
		return;
	explicit_bzero(c->bind_password, sizeof(c->bind_password));
	c->password_loaded = 0;
}

int
layer7_ldap_cfg_load_secret(struct l7_ldap_cfg *c, const char *path)
{
	FILE *f;
	char buf[L7_LDAP_SECRET_MAX];
	size_t n;

	if (c == NULL)
		return -1;
	layer7_ldap_cfg_wipe_secret(c);
	if (path == NULL) {
		const char *env = getenv("LAYER7_LDAP_SECRET");
		if (env != NULL && env[0] != '\0')
			path = env;
		else
			path = L7_LDAP_SECRET_PATH;
	}
	f = fopen(path, "r");
	if (f == NULL)
		return -1;
	n = fread(buf, 1, sizeof(buf) - 1, f);
	fclose(f);
	while (n > 0 && (buf[n - 1] == '\n' || buf[n - 1] == '\r'))
		n--;
	buf[n] = '\0';
	if (n == 0)
		return -1;
	memcpy(c->bind_password, buf, n + 1);
	explicit_bzero(buf, sizeof(buf));
	c->password_loaded = 1;
	return 0;
}

/* ---- minimal JSON helpers (identity.ldap) ---- */

static void
skip_ws(const char **p)
{
	while (**p && isspace((unsigned char)**p))
		(*p)++;
}

static int
parse_bool_val(const char *p, int *out)
{
	skip_ws(&p);
	if (strncmp(p, "true", 4) == 0) {
		*out = 1;
		return 0;
	}
	if (strncmp(p, "false", 5) == 0) {
		*out = 0;
		return 0;
	}
	return -1;
}

static int
parse_int_val(const char *p, int *out)
{
	char *end;
	long v;

	skip_ws(&p);
	v = strtol(p, &end, 10);
	if (end == p)
		return -1;
	*out = (int)v;
	return 0;
}

static int
parse_qstr(const char *p, char *dst, size_t dstsz)
{
	size_t i = 0;

	skip_ws(&p);
	if (*p != '"')
		return -1;
	p++;
	while (*p && *p != '"' && i + 1 < dstsz) {
		if (*p == '\\' && p[1])
			p++;
		dst[i++] = *p++;
	}
	dst[i] = '\0';
	return (*p == '"') ? 0 : -1;
}

static const char *
find_key(const char *json, size_t len, const char *key)
{
	size_t klen = strlen(key);
	size_t i;

	for (i = 0; i + klen + 2 < len; i++) {
		if (json[i] != '"')
			continue;
		if (strncmp(json + i + 1, key, klen) == 0 &&
		    json[i + 1 + klen] == '"') {
			const char *p = json + i + 1 + klen + 1;
			skip_ws(&p);
			if (*p == ':')
				return p + 1;
		}
	}
	return NULL;
}

/*
 * Encontra o objecto "identity" { ... } e depois "ldap" dentro — parse
 * heurístico suficiente para o schema GUI 20.16.
 */
int
layer7_ldap_cfg_parse_json(const char *json, size_t len, struct l7_ldap_cfg *out)
{
	const char *id, *ldap, *q;
	int v;

	if (out == NULL)
		return -1;
	layer7_ldap_cfg_defaults(out);
	if (json == NULL || len == 0)
		return 0;

	id = find_key(json, len, "identity");
	if (id == NULL)
		return 0;
	skip_ws(&id);
	if (*id != '{')
		return 0;

	/* identity.enabled */
	q = find_key(id, (size_t)(json + len - id), "enabled");
	if (q != NULL && parse_bool_val(q, &v) == 0)
		out->identity_enabled = v;

	ldap = find_key(id, (size_t)(json + len - id), "ldap");
	if (ldap == NULL)
		return 0;
	skip_ws(&ldap);
	if (*ldap != '{')
		return 0;

	q = find_key(ldap, (size_t)(json + len - ldap), "enabled");
	if (q != NULL && parse_bool_val(q, &v) == 0)
		out->ldap_enabled = v;
	q = find_key(ldap, (size_t)(json + len - ldap), "server");
	if (q != NULL)
		(void)parse_qstr(q, out->server, sizeof(out->server));
	q = find_key(ldap, (size_t)(json + len - ldap), "port");
	if (q != NULL && parse_int_val(q, &v) == 0 && v >= 1 && v <= 65535)
		out->port = v;
	q = find_key(ldap, (size_t)(json + len - ldap), "use_tls");
	if (q != NULL && parse_bool_val(q, &v) == 0)
		out->use_tls = v;
	q = find_key(ldap, (size_t)(json + len - ldap), "bind_dn");
	if (q != NULL)
		(void)parse_qstr(q, out->bind_dn, sizeof(out->bind_dn));
	q = find_key(ldap, (size_t)(json + len - ldap), "base_dn");
	if (q != NULL)
		(void)parse_qstr(q, out->base_dn, sizeof(out->base_dn));
	q = find_key(ldap, (size_t)(json + len - ldap), "user_filter");
	if (q != NULL)
		(void)parse_qstr(q, out->user_filter, sizeof(out->user_filter));
	q = find_key(ldap, (size_t)(json + len - ldap), "group_filter");
	if (q != NULL)
		(void)parse_qstr(q, out->group_filter,
		    sizeof(out->group_filter));
	q = find_key(ldap, (size_t)(json + len - ldap), "group_depth");
	if (q != NULL && parse_int_val(q, &v) == 0) {
		if (v < 1)
			v = 1;
		if (v > 10)
			v = 10;
		out->group_depth = v;
	}
	q = find_key(ldap, (size_t)(json + len - ldap), "max_members");
	if (q != NULL && parse_int_val(q, &v) == 0) {
		if (v < 1)
			v = 1;
		if (v > 16384)
			v = 16384;
		out->max_members = v;
	}
	return 0;
}

/* ---- cache ---- */

enum { L7_LDAP_KIND_GROUP = 1, L7_LDAP_KIND_USER = 2 };

struct l7_ldap_cache_entry {
	int      in_use;
	int      kind;
	char     key[L7_LDAP_NAME_MAX];
	char     values[L7_IDMAP_MAX_GROUPS_CACHE][L7_LDAP_NAME_MAX];
	unsigned n_values;
	time_t   expires_at;
};

struct l7_ldap_cache {
	pthread_mutex_t lock;
	struct l7_ldap_cache_entry entries[L7_LDAP_CACHE_ENTRIES];
	unsigned ttl_sec;
	time_t   last_ok;
	time_t   last_fail;
	int      ever_ok;
};

struct l7_ldap_cache *
layer7_ldap_cache_create(unsigned ttl_sec)
{
	struct l7_ldap_cache *c;

	c = calloc(1, sizeof(*c));
	if (c == NULL)
		return NULL;
	if (pthread_mutex_init(&c->lock, NULL) != 0) {
		free(c);
		return NULL;
	}
	c->ttl_sec = ttl_sec ? ttl_sec : L7_LDAP_DEFAULT_CACHE_TTL_SEC;
	return c;
}

void
layer7_ldap_cache_destroy(struct l7_ldap_cache *c)
{
	if (c == NULL)
		return;
	(void)pthread_mutex_destroy(&c->lock);
	free(c);
}

void
layer7_ldap_cache_clear(struct l7_ldap_cache *c)
{
	if (c == NULL)
		return;
	pthread_mutex_lock(&c->lock);
	memset(c->entries, 0, sizeof(c->entries));
	c->last_ok = 0;
	c->last_fail = 0;
	c->ever_ok = 0;
	pthread_mutex_unlock(&c->lock);
}

void
layer7_ldap_cache_set_ttl(struct l7_ldap_cache *c, unsigned ttl_sec)
{
	if (c == NULL)
		return;
	pthread_mutex_lock(&c->lock);
	c->ttl_sec = ttl_sec ? ttl_sec : L7_LDAP_DEFAULT_CACHE_TTL_SEC;
	pthread_mutex_unlock(&c->lock);
}

void
layer7_ldap_cache_mark_ok(struct l7_ldap_cache *c, time_t now)
{
	if (c == NULL)
		return;
	pthread_mutex_lock(&c->lock);
	c->last_ok = now;
	c->ever_ok = 1;
	pthread_mutex_unlock(&c->lock);
}

void
layer7_ldap_cache_mark_fail(struct l7_ldap_cache *c, time_t now)
{
	if (c == NULL)
		return;
	pthread_mutex_lock(&c->lock);
	c->last_fail = now;
	pthread_mutex_unlock(&c->lock);
}

enum l7_ldap_status
layer7_ldap_cache_status(const struct l7_ldap_cache *c, time_t now)
{
	unsigned i;
	int has_fresh = 0;
	time_t last_ok, last_fail;
	int ever_ok;
	unsigned ttl;

	if (c == NULL)
		return L7_LDAP_STATUS_OFF;
	pthread_mutex_lock((pthread_mutex_t *)&c->lock);
	last_ok = c->last_ok;
	last_fail = c->last_fail;
	ever_ok = c->ever_ok;
	ttl = c->ttl_sec;
	for (i = 0; i < L7_LDAP_CACHE_ENTRIES; i++) {
		if (c->entries[i].in_use && c->entries[i].expires_at > now) {
			has_fresh = 1;
			break;
		}
	}
	pthread_mutex_unlock((pthread_mutex_t *)&c->lock);

	if (!ever_ok && last_fail == 0)
		return L7_LDAP_STATUS_OFF;
	if (last_fail == 0 || last_ok >= last_fail)
		return L7_LDAP_STATUS_OK;
	if (has_fresh || (last_ok > 0 && (now - last_ok) < (time_t)ttl))
		return L7_LDAP_STATUS_DEGRADED;
	return L7_LDAP_STATUS_DOWN;
}

static int
cache_put(struct l7_ldap_cache *c, int kind, const char *key,
    const char *const *vals, unsigned n_vals, time_t now)
{
	unsigned i, slot = L7_LDAP_CACHE_ENTRIES, n;
	time_t oldest_exp = 0;

	if (c == NULL || key == NULL || key[0] == '\0')
		return -1;
	pthread_mutex_lock(&c->lock);
	for (i = 0; i < L7_LDAP_CACHE_ENTRIES; i++) {
		if (c->entries[i].in_use && c->entries[i].kind == kind &&
		    strcmp(c->entries[i].key, key) == 0) {
			slot = i;
			break;
		}
		if (!c->entries[i].in_use && slot == L7_LDAP_CACHE_ENTRIES)
			slot = i;
	}
	if (slot == L7_LDAP_CACHE_ENTRIES) {
		/* eviction: expires_at mais antigo */
		for (i = 0; i < L7_LDAP_CACHE_ENTRIES; i++) {
			if (!c->entries[i].in_use)
				continue;
			if (slot == L7_LDAP_CACHE_ENTRIES ||
			    c->entries[i].expires_at < oldest_exp) {
				slot = i;
				oldest_exp = c->entries[i].expires_at;
			}
		}
	}
	if (slot >= L7_LDAP_CACHE_ENTRIES) {
		pthread_mutex_unlock(&c->lock);
		return -1;
	}
	memset(&c->entries[slot], 0, sizeof(c->entries[slot]));
	c->entries[slot].in_use = 1;
	c->entries[slot].kind = kind;
	snprintf(c->entries[slot].key, sizeof(c->entries[slot].key), "%s",
	    key);
	n = n_vals;
	if (n > L7_IDMAP_MAX_GROUPS_CACHE)
		n = L7_IDMAP_MAX_GROUPS_CACHE;
	for (i = 0; i < n; i++) {
		if (vals == NULL || vals[i] == NULL || vals[i][0] == '\0')
			continue;
		snprintf(c->entries[slot].values[c->entries[slot].n_values],
		    L7_LDAP_NAME_MAX, "%s", vals[i]);
		c->entries[slot].n_values++;
	}
	c->entries[slot].expires_at = now + (time_t)c->ttl_sec;
	pthread_mutex_unlock(&c->lock);
	return 0;
}

static int
cache_get(struct l7_ldap_cache *c, int kind, const char *key,
    char out[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out,
    time_t now, int *expired)
{
	unsigned i, j, n;

	if (expired)
		*expired = 0;
	if (n_out)
		*n_out = 0;
	if (c == NULL || key == NULL)
		return -1;
	pthread_mutex_lock(&c->lock);
	for (i = 0; i < L7_LDAP_CACHE_ENTRIES; i++) {
		if (!c->entries[i].in_use || c->entries[i].kind != kind)
			continue;
		if (strcmp(c->entries[i].key, key) != 0)
			continue;
		if (c->entries[i].expires_at <= now) {
			if (expired)
				*expired = 1;
			pthread_mutex_unlock(&c->lock);
			return -1;
		}
		n = c->entries[i].n_values;
		if (n > max_out)
			n = max_out;
		for (j = 0; j < n; j++)
			snprintf(out[j], L7_LDAP_NAME_MAX, "%s",
			    c->entries[i].values[j]);
		if (n_out)
			*n_out = n;
		pthread_mutex_unlock(&c->lock);
		return 0;
	}
	pthread_mutex_unlock(&c->lock);
	return -1;
}

int
layer7_ldap_cache_put_group(struct l7_ldap_cache *c, const char *group,
    const char *const *members, unsigned n_members, time_t now)
{
	return cache_put(c, L7_LDAP_KIND_GROUP, group, members, n_members, now);
}

int
layer7_ldap_cache_get_group(struct l7_ldap_cache *c, const char *group,
    char out[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out,
    time_t now)
{
	int exp = 0;
	enum l7_ldap_status st;
	int rc;

	st = layer7_ldap_cache_status(c, now);
	rc = cache_get(c, L7_LDAP_KIND_GROUP, group, out, max_out, n_out, now,
	    &exp);
	if (rc == 0)
		return 0;
	if (st == L7_LDAP_STATUS_DOWN)
		return -2;
	return -1;
}

int
layer7_ldap_cache_put_user_groups(struct l7_ldap_cache *c, const char *user,
    const char *const *groups, unsigned n_groups, time_t now)
{
	return cache_put(c, L7_LDAP_KIND_USER, user, groups, n_groups, now);
}

int
layer7_ldap_cache_get_user_groups(struct l7_ldap_cache *c, const char *user,
    char out[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out,
    time_t now)
{
	int exp = 0;
	enum l7_ldap_status st;
	int rc;

	st = layer7_ldap_cache_status(c, now);
	rc = cache_get(c, L7_LDAP_KIND_USER, user, out, max_out, n_out, now,
	    &exp);
	if (rc == 0)
		return 0;
	if (st == L7_LDAP_STATUS_DOWN)
		return -2;
	return -1;
}

/* ---- providers ---- */

static l7_ldap_expand_group_fn s_expand_fn;
static l7_ldap_user_groups_fn s_user_groups_fn;

void
layer7_ldap_set_providers(l7_ldap_expand_group_fn expand,
    l7_ldap_user_groups_fn user_groups)
{
	s_expand_fn = expand;
	s_user_groups_fn = user_groups;
}

#if HAVE_OPENLDAP

static int
ldap_connect_bind(const struct l7_ldap_cfg *cfg, LDAP **out)
{
	LDAP *ld = NULL;
	char uri[512];
	int rc, ver = LDAP_VERSION3;
	struct timeval tv;

	*out = NULL;
	if (cfg == NULL || cfg->server[0] == '\0' || !cfg->password_loaded)
		return -1;
	snprintf(uri, sizeof(uri), "%s://%s:%d",
	    cfg->use_tls ? "ldaps" : "ldap", cfg->server, cfg->port);
	rc = ldap_initialize(&ld, uri);
	if (rc != LDAP_SUCCESS || ld == NULL)
		return -1;
	ldap_set_option(ld, LDAP_OPT_PROTOCOL_VERSION, &ver);
	tv.tv_sec = 5;
	tv.tv_usec = 0;
	ldap_set_option(ld, LDAP_OPT_NETWORK_TIMEOUT, &tv);
	ldap_set_option(ld, LDAP_OPT_TIMEOUT, &tv);
	{
		struct berval cred;

		cred.bv_val = (char *)cfg->bind_password;
		cred.bv_len = strlen(cfg->bind_password);
		rc = ldap_sasl_bind_s(ld, cfg->bind_dn, LDAP_SASL_SIMPLE, &cred,
		    NULL, NULL, NULL);
	}
	if (rc != LDAP_SUCCESS) {
		ldap_unbind_ext_s(ld, NULL, NULL);
		return -1;
	}
	*out = ld;
	return 0;
}

static int
rdn_cn(const char *dn, char *out, size_t outsz)
{
	const char *p = dn;
	size_t i = 0;

	if (dn == NULL || out == NULL || outsz == 0)
		return -1;
	if (strncasecmp(p, "CN=", 3) == 0 || strncasecmp(p, "cn=", 3) == 0)
		p += 3;
	while (*p && *p != ',' && i + 1 < outsz)
		out[i++] = *p++;
	out[i] = '\0';
	return i > 0 ? 0 : -1;
}

static int
entry_is_group(LDAP *ld, LDAPMessage *e)
{
	BerElement *ber = NULL;
	char *a;
	struct berval **vals;
	int is_group = 0;

	for (a = ldap_first_attribute(ld, e, &ber); a != NULL;
	     a = ldap_next_attribute(ld, e, ber)) {
		if (strcasecmp(a, "objectClass") != 0) {
			ldap_memfree(a);
			continue;
		}
		vals = ldap_get_values_len(ld, e, a);
		if (vals) {
			int i;
			for (i = 0; vals[i]; i++) {
				if (vals[i]->bv_val &&
				    (strcasecmp(vals[i]->bv_val, "group") == 0 ||
					strcasecmp(vals[i]->bv_val,
					    "groupOfNames") == 0 ||
					strcasecmp(vals[i]->bv_val,
					    "groupOfUniqueNames") == 0))
					is_group = 1;
			}
			ldap_value_free_len(vals);
		}
		ldap_memfree(a);
	}
	if (ber)
		ber_free(ber, 0);
	return is_group;
}

static int
expand_group_rec(LDAP *ld, const struct l7_ldap_cfg *cfg, const char *group_cn,
    char members[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out,
    int depth, char visited[][L7_LDAP_NAME_MAX], unsigned *n_vis)
{
	char filter[768];
	char *attrs[] = { "member", "uniqueMember", "objectClass", NULL };
	LDAPMessage *res = NULL, *e;
	int rc, i;
	unsigned u;

	if (depth <= 0 || group_cn == NULL || group_cn[0] == '\0')
		return 0;
	for (u = 0; u < *n_vis; u++) {
		if (strcasecmp(visited[u], group_cn) == 0)
			return 0;
	}
	if (*n_vis < 64) {
		snprintf(visited[*n_vis], L7_LDAP_NAME_MAX, "%s", group_cn);
		(*n_vis)++;
	}

	/* (& group_filter (|(cn=)(sAMAccountName=))) */
	snprintf(filter, sizeof(filter),
	    "(&%s(|(cn=%s)(sAMAccountName=%s)))",
	    cfg->group_filter[0] ? cfg->group_filter : "(objectClass=group)",
	    group_cn, group_cn);
	rc = ldap_search_ext_s(ld, cfg->base_dn, LDAP_SCOPE_SUBTREE, filter,
	    attrs, 0, NULL, NULL, NULL, 0, &res);
	if (rc != LDAP_SUCCESS || res == NULL)
		return -1;

	for (e = ldap_first_entry(ld, res); e != NULL;
	     e = ldap_next_entry(ld, e)) {
		BerElement *ber = NULL;
		char *a;
		struct berval **vals;

		for (a = ldap_first_attribute(ld, e, &ber); a != NULL;
		     a = ldap_next_attribute(ld, e, ber)) {
			if (strcasecmp(a, "member") != 0 &&
			    strcasecmp(a, "uniqueMember") != 0) {
				ldap_memfree(a);
				continue;
			}
			vals = ldap_get_values_len(ld, e, a);
			if (!vals) {
				ldap_memfree(a);
				continue;
			}
			for (i = 0; vals[i]; i++) {
				char name[L7_LDAP_NAME_MAX];
				char *attrs2[] = { "objectClass", NULL };
				LDAPMessage *res2 = NULL, *e2;
				int is_g = 0;
				const char *dn;

				if (vals[i]->bv_val == NULL)
					continue;
				dn = vals[i]->bv_val;
				if (rdn_cn(dn, name, sizeof(name)) != 0)
					continue;
				if (ldap_search_ext_s(ld, dn,
					LDAP_SCOPE_BASE, "(objectClass=*)",
					attrs2, 0, NULL, NULL, NULL, 1,
					&res2) == LDAP_SUCCESS && res2) {
					e2 = ldap_first_entry(ld, res2);
					if (e2)
						is_g = entry_is_group(ld, e2);
					ldap_msgfree(res2);
				}
				if (is_g) {
					(void)expand_group_rec(ld, cfg, name,
					    members, max_out, n_out, depth - 1,
					    visited, n_vis);
				} else if (*n_out < max_out) {
					unsigned k, dup = 0;
					for (k = 0; k < *n_out; k++) {
						if (strcasecmp(members[k],
							name) == 0)
							dup = 1;
					}
					if (!dup) {
						snprintf(members[*n_out],
						    L7_LDAP_NAME_MAX, "%s",
						    name);
						(*n_out)++;
					}
				}
				if ((int)*n_out >= cfg->max_members)
					break;
			}
			ldap_value_free_len(vals);
			ldap_memfree(a);
			if ((int)*n_out >= cfg->max_members)
				break;
		}
		if (ber)
			ber_free(ber, 0);
	}
	ldap_msgfree(res);
	return 0;
}

static int
openldap_expand_group(const struct l7_ldap_cfg *cfg, const char *group,
    char members[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out)
{
	LDAP *ld = NULL;
	char visited[64][L7_LDAP_NAME_MAX];
	unsigned n_vis = 0;
	int rc;

	if (n_out)
		*n_out = 0;
	if (ldap_connect_bind(cfg, &ld) != 0)
		return -1;
	rc = expand_group_rec(ld, cfg, group, members, max_out, n_out,
	    cfg->group_depth, visited, &n_vis);
	ldap_unbind_ext_s(ld, NULL, NULL);
	return rc;
}

static int
openldap_user_groups(const struct l7_ldap_cfg *cfg, const char *user,
    char groups[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out)
{
	LDAP *ld = NULL;
	char filter[768];
	char *attrs[] = { "memberOf", NULL };
	LDAPMessage *res = NULL, *e;
	int rc, i;

	if (n_out)
		*n_out = 0;
	if (ldap_connect_bind(cfg, &ld) != 0)
		return -1;
	snprintf(filter, sizeof(filter),
	    "(&%s(|(sAMAccountName=%s)(cn=%s)(uid=%s)))",
	    cfg->user_filter[0] ? cfg->user_filter :
				  "(objectClass=user)",
	    user, user, user);
	rc = ldap_search_ext_s(ld, cfg->base_dn, LDAP_SCOPE_SUBTREE, filter,
	    attrs, 0, NULL, NULL, NULL, 1, &res);
	if (rc != LDAP_SUCCESS || res == NULL) {
		ldap_unbind_ext_s(ld, NULL, NULL);
		return -1;
	}
	e = ldap_first_entry(ld, res);
	if (e) {
		struct berval **vals = ldap_get_values_len(ld, e, "memberOf");
		if (vals) {
			for (i = 0; vals[i] && *n_out < max_out; i++) {
				char name[L7_LDAP_NAME_MAX];
				if (vals[i]->bv_val &&
				    rdn_cn(vals[i]->bv_val, name,
					sizeof(name)) == 0) {
					snprintf(groups[*n_out],
					    L7_LDAP_NAME_MAX, "%s", name);
					(*n_out)++;
				}
			}
			ldap_value_free_len(vals);
		}
	}
	ldap_msgfree(res);
	ldap_unbind_ext_s(ld, NULL, NULL);
	return 0;
}

#else /* !HAVE_OPENLDAP */

static int
openldap_expand_group(const struct l7_ldap_cfg *cfg, const char *group,
    char members[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out)
{
	(void)cfg;
	(void)group;
	(void)members;
	(void)max_out;
	if (n_out)
		*n_out = 0;
	return -1;
}

static int
openldap_user_groups(const struct l7_ldap_cfg *cfg, const char *user,
    char groups[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out)
{
	(void)cfg;
	(void)user;
	(void)groups;
	(void)max_out;
	if (n_out)
		*n_out = 0;
	return -1;
}

#endif /* HAVE_OPENLDAP */

static int
do_expand(const struct l7_ldap_cfg *cfg, const char *group,
    char members[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out)
{
	if (s_expand_fn)
		return s_expand_fn(cfg, group, members, max_out, n_out);
	return openldap_expand_group(cfg, group, members, max_out, n_out);
}

static int
do_user_groups(const struct l7_ldap_cfg *cfg, const char *user,
    char groups[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out)
{
	if (s_user_groups_fn)
		return s_user_groups_fn(cfg, user, groups, max_out, n_out);
	return openldap_user_groups(cfg, user, groups, max_out, n_out);
}

int
layer7_ldap_resolve_group(struct l7_ldap_cache *cache,
    const struct l7_ldap_cfg *cfg, const char *group,
    char members[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out,
    time_t now)
{
	unsigned n = 0;
	const char *ptrs[L7_IDMAP_MAX_GROUPS_CACHE];
	unsigned i, cap;
	int rc;

	if (n_out)
		*n_out = 0;
	if (cfg == NULL || !cfg->ldap_enabled || group == NULL)
		return -1;
	cap = max_out;
	if ((int)cap > cfg->max_members)
		cap = (unsigned)cfg->max_members;
	if (cap > L7_IDMAP_MAX_GROUPS_CACHE)
		cap = L7_IDMAP_MAX_GROUPS_CACHE;

	rc = do_expand(cfg, group, members, cap, &n);
	if (rc == 0) {
		for (i = 0; i < n && i < L7_IDMAP_MAX_GROUPS_CACHE; i++)
			ptrs[i] = members[i];
		(void)layer7_ldap_cache_put_group(cache, group, ptrs, n, now);
		layer7_ldap_cache_mark_ok(cache, now);
		if (n_out)
			*n_out = n;
		return 0;
	}
	layer7_ldap_cache_mark_fail(cache, now);
	return layer7_ldap_cache_get_group(cache, group, members, max_out,
	    n_out, now);
}

int
layer7_ldap_resolve_user_groups(struct l7_ldap_cache *cache,
    const struct l7_ldap_cfg *cfg, const char *user,
    char groups[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out,
    time_t now)
{
	unsigned n = 0;
	const char *ptrs[L7_IDMAP_MAX_GROUPS_CACHE];
	unsigned i, cap;
	int rc;

	if (n_out)
		*n_out = 0;
	if (cfg == NULL || !cfg->ldap_enabled || user == NULL)
		return -1;
	cap = max_out;
	if (cap > L7_IDMAP_MAX_GROUPS_CACHE)
		cap = L7_IDMAP_MAX_GROUPS_CACHE;

	rc = do_user_groups(cfg, user, groups, cap, &n);
	if (rc == 0) {
		for (i = 0; i < n && i < L7_IDMAP_MAX_GROUPS_CACHE; i++)
			ptrs[i] = groups[i];
		(void)layer7_ldap_cache_put_user_groups(cache, user, ptrs, n,
		    now);
		layer7_ldap_cache_mark_ok(cache, now);
		if (n_out)
			*n_out = n;
		return 0;
	}
	layer7_ldap_cache_mark_fail(cache, now);
	return layer7_ldap_cache_get_user_groups(cache, user, groups, max_out,
	    n_out, now);
}

/* ---- worker ---- */

struct l7_ldap_worker {
	pthread_t           thr;
	pthread_mutex_t     mu;
	int                 stop;
	int                 running;
	struct l7_id_map   *map;
	struct l7_ldap_cfg  cfg;
	struct l7_ldap_cache *cache;
};

static void *
ldap_worker_main(void *arg)
{
	struct l7_ldap_worker *w = arg;

	while (1) {
		struct l7_ldap_cfg cfg;
		struct l7_id_map *map;
		int stop, enabled;
		time_t now;
		char users[64][L7_IDMAP_USER_MAX];
		unsigned nu, i;

		pthread_mutex_lock(&w->mu);
		stop = w->stop;
		cfg = w->cfg;
		map = w->map;
		enabled = cfg.ldap_enabled;
		pthread_mutex_unlock(&w->mu);
		if (stop)
			break;

		now = time(NULL);
		if (enabled && map != NULL && w->cache != NULL) {
			nu = layer7_idmap_list_users(map, users, 64);
			for (i = 0; i < nu; i++) {
				char groups[L7_IDMAP_MAX_GROUPS_CACHE]
				    [L7_LDAP_NAME_MAX];
				unsigned ng = 0;
				const char *gptr[L7_IDMAP_MAX_GROUPS_CACHE];
				unsigned g;
				int rc;

				rc = layer7_ldap_resolve_user_groups(w->cache,
				    &cfg, users[i], groups,
				    L7_IDMAP_MAX_GROUPS_CACHE, &ng, now);
				if (rc == 0) {
					for (g = 0; g < ng; g++)
						gptr[g] = groups[g];
					(void)layer7_idmap_set_groups(map,
					    users[i], gptr, ng);
				} else if (rc == -2) {
					/* DOWN: limpa grupos AD da sessão */
					(void)layer7_idmap_set_groups(map,
					    users[i], NULL, 0);
				}
			}
		}
		layer7_ldap_cfg_wipe_secret(&cfg);

		/* intervalo curto o suficiente para lab; 30s */
		for (i = 0; i < 30; i++) {
			pthread_mutex_lock(&w->mu);
			stop = w->stop;
			pthread_mutex_unlock(&w->mu);
			if (stop)
				break;
			sleep(1);
		}
	}
	return NULL;
}

struct l7_ldap_worker *
layer7_ldap_worker_start(struct l7_id_map *map, const struct l7_ldap_cfg *cfg)
{
	struct l7_ldap_worker *w;

	if (cfg == NULL || !cfg->ldap_enabled)
		return NULL;
	w = calloc(1, sizeof(*w));
	if (w == NULL)
		return NULL;
	if (pthread_mutex_init(&w->mu, NULL) != 0) {
		free(w);
		return NULL;
	}
	w->map = map;
	w->cfg = *cfg;
	w->cache = layer7_ldap_cache_create(cfg->cache_ttl_sec);
	if (w->cache == NULL) {
		pthread_mutex_destroy(&w->mu);
		free(w);
		return NULL;
	}
	if (pthread_create(&w->thr, NULL, ldap_worker_main, w) != 0) {
		layer7_ldap_cache_destroy(w->cache);
		pthread_mutex_destroy(&w->mu);
		layer7_ldap_cfg_wipe_secret(&w->cfg);
		free(w);
		return NULL;
	}
	w->running = 1;
	L7_NOTE("identity: ldap worker started server=%s port=%d tls=%d",
	    cfg->server, cfg->port, cfg->use_tls);
	return w;
}

void
layer7_ldap_worker_reload(struct l7_ldap_worker *w,
    const struct l7_ldap_cfg *cfg)
{
	if (w == NULL || cfg == NULL)
		return;
	pthread_mutex_lock(&w->mu);
	layer7_ldap_cfg_wipe_secret(&w->cfg);
	w->cfg = *cfg;
	if (w->cache)
		layer7_ldap_cache_set_ttl(w->cache, cfg->cache_ttl_sec);
	pthread_mutex_unlock(&w->mu);
	L7_NOTE("identity: ldap worker config reloaded enabled=%d",
	    cfg->ldap_enabled);
}

void
layer7_ldap_worker_stop(struct l7_ldap_worker *w)
{
	if (w == NULL)
		return;
	pthread_mutex_lock(&w->mu);
	w->stop = 1;
	pthread_mutex_unlock(&w->mu);
	if (w->running)
		(void)pthread_join(w->thr, NULL);
	w->running = 0;
	layer7_ldap_cache_destroy(w->cache);
	w->cache = NULL;
	layer7_ldap_cfg_wipe_secret(&w->cfg);
	pthread_mutex_destroy(&w->mu);
	free(w);
	L7_NOTE("identity: ldap worker stopped");
}

enum l7_ldap_status
layer7_ldap_worker_status(const struct l7_ldap_worker *w, time_t now)
{
	if (w == NULL || w->cache == NULL)
		return L7_LDAP_STATUS_OFF;
	return layer7_ldap_cache_status(w->cache, now);
}

struct l7_ldap_cache *
layer7_ldap_worker_cache(struct l7_ldap_worker *w)
{
	return w ? w->cache : NULL;
}

#if HAVE_OPENLDAP
int
layer7_ldap_test_connection(const struct l7_ldap_cfg *cfg,
    struct l7_ldap_test_result *out)
{
	LDAP *ld = NULL;
	LDAPMessage *res = NULL;
	struct timeval t0, t1;
	int rc;
	char *attrs[] = { "namingContexts", "objectClass", NULL };

	if (out == NULL)
		return -1;
	memset(out, 0, sizeof(*out));
	out->ldap_rc = 0;
	snprintf(out->phase, sizeof(out->phase), "%s", "config");

	if (cfg == NULL || cfg->server[0] == '\0' || cfg->base_dn[0] == '\0' ||
	    cfg->bind_dn[0] == '\0' || !cfg->password_loaded) {
		snprintf(out->message, sizeof(out->message), "%s",
		    "Config LDAP incompleta (servidor, Base DN, bind DN ou credenciais).");
		return -1;
	}
	snprintf(out->server, sizeof(out->server), "%s", cfg->server);
	out->port = cfg->port;
	out->use_tls = cfg->use_tls ? 1 : 0;

	gettimeofday(&t0, NULL);
	snprintf(out->phase, sizeof(out->phase), "%s", "connect");
	if (ldap_connect_bind(cfg, &ld) != 0) {
		gettimeofday(&t1, NULL);
		out->ms = (unsigned)((t1.tv_sec - t0.tv_sec) * 1000 +
		    (t1.tv_usec - t0.tv_usec) / 1000);
		snprintf(out->phase, sizeof(out->phase), "%s", "bind");
		snprintf(out->message, sizeof(out->message), "%s",
		    "Falha a ligar ou autenticar no servidor LDAP "
		    "(rede, TLS, porto ou credenciais).");
		/* Sem password / DN nos logs de mensagem. */
		return -1;
	}
	snprintf(out->phase, sizeof(out->phase), "%s", "search");
	rc = ldap_search_ext_s(ld, cfg->base_dn, LDAP_SCOPE_BASE,
	    "(objectClass=*)", attrs, 0, NULL, NULL, NULL, 1, &res);
	out->ldap_rc = rc;
	if (rc != LDAP_SUCCESS) {
		gettimeofday(&t1, NULL);
		out->ms = (unsigned)((t1.tv_sec - t0.tv_sec) * 1000 +
		    (t1.tv_usec - t0.tv_usec) / 1000);
		ldap_unbind_ext_s(ld, NULL, NULL);
		snprintf(out->message, sizeof(out->message),
		    "Bind OK mas pesquisa na Base DN falhou (rc=%d).", rc);
		return -1;
	}
	out->base_ok = (res != NULL && ldap_count_entries(ld, res) > 0) ? 1 : 0;
	if (res)
		ldap_msgfree(res);
	ldap_unbind_ext_s(ld, NULL, NULL);
	gettimeofday(&t1, NULL);
	out->ms = (unsigned)((t1.tv_sec - t0.tv_sec) * 1000 +
	    (t1.tv_usec - t0.tv_usec) / 1000);
	out->ok = 1;
	snprintf(out->phase, sizeof(out->phase), "%s", "ok");
	snprintf(out->message, sizeof(out->message), "%s",
	    "Ligacao LDAP OK (bind + Base DN).");
	return 0;
}
#else /* !HAVE_OPENLDAP */
int
layer7_ldap_test_connection(const struct l7_ldap_cfg *cfg,
    struct l7_ldap_test_result *out)
{
	if (out == NULL)
		return -1;
	memset(out, 0, sizeof(*out));
	snprintf(out->phase, sizeof(out->phase), "%s", "config");
	if (cfg == NULL || cfg->server[0] == '\0' || cfg->base_dn[0] == '\0' ||
	    cfg->bind_dn[0] == '\0' || !cfg->password_loaded) {
		snprintf(out->message, sizeof(out->message), "%s",
		    "Config LDAP incompleta (servidor, Base DN, bind DN ou credenciais).");
		if (cfg != NULL) {
			snprintf(out->server, sizeof(out->server), "%s",
			    cfg->server);
			out->port = cfg->port;
			out->use_tls = cfg->use_tls ? 1 : 0;
		}
		return -1;
	}
	snprintf(out->server, sizeof(out->server), "%s", cfg->server);
	out->port = cfg->port;
	out->use_tls = cfg->use_tls ? 1 : 0;
	snprintf(out->message, sizeof(out->message), "%s",
	    "Cliente OpenLDAP nao compilado neste binario.");
	return -1;
}
#endif
