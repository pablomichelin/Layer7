/*
 * blacklist.c — Hash table de dominios com suffix matching e whitelist.
 *
 * Subsistema paralelo ao policy engine V1 para blacklists externas
 * (UT1 Universite Toulouse). Nao altera nenhuma estrutura existente.
 */
#include "blacklist.h"

#include <ctype.h>
#include <errno.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <syslog.h>

/* Logging — reutiliza as macros do daemon se disponíveis, senão syslog */
#ifndef L7_NOTE
#define L7_NOTE(fmt, ...) syslog(LOG_NOTICE, fmt, ##__VA_ARGS__)
#endif
#ifndef L7_WARN
#define L7_WARN(fmt, ...) syslog(LOG_WARNING, fmt, ##__VA_ARGS__)
#endif
#ifndef L7_ERROR
#define L7_ERROR(fmt, ...) syslog(LOG_ERR, fmt, ##__VA_ARGS__)
#endif

/* --- Hash table entry ------------------------------------------------ */

struct l7_bl_entry {
	char *domain;
	uint8_t cat_idx;
	struct l7_bl_entry *next;
};

/* --- Blacklist struct ------------------------------------------------ */

struct l7_blacklist {
	struct l7_bl_entry **buckets;
	int n_entries;
	int n_cats;
	char cats[L7_BL_MAX_CATS][L7_BL_CAT_LEN];
	unsigned long long cat_hits[L7_BL_MAX_CATS];
	int n_whitelist;
	char **whitelist;
};

/* --- FNV-1a hash ----------------------------------------------------- */

static uint32_t
fnv1a_hash(const char *str)
{
	uint32_t h = 2166136261U;

	while (*str) {
		h ^= (uint32_t)(unsigned char)tolower((unsigned char)*str);
		h *= 16777619U;
		str++;
	}
	return h;
}

/* --- Validation helpers ---------------------------------------------- */

static int
cat_name_valid(const char *name)
{
	const char *p;

	if (!name || !*name || strlen(name) > (L7_BL_CAT_LEN - 1))
		return 0;
	for (p = name; *p; p++) {
		if (!islower((unsigned char)*p) && !isdigit((unsigned char)*p) &&
		    *p != '_' && *p != '-')
			return 0;
	}
	return 1;
}

static int
domain_valid(const char *d)
{
	const char *p;
	int has_dot = 0;

	if (!d || !*d || strlen(d) > 253)
		return 0;
	for (p = d; *p; p++) {
		if (*p == '/')
			return 0;
		if (*p == ':')
			return 0;
		if (*p == ' ' || *p == '\t')
			return 0;
		if (*p == '.')
			has_dot = 1;
	}
	return has_dot;
}

/* --- Count labels in a domain ---------------------------------------- */

static int
count_labels(const char *d)
{
	int n = 1;
	const char *p;

	for (p = d; *p; p++) {
		if (*p == '.')
			n++;
	}
	return n;
}

/* --- Normalize domain to lowercase ----------------------------------- */

static void
domain_lower(char *dst, const char *src, size_t dstsize)
{
	size_t i;

	for (i = 0; i < dstsize - 1 && src[i]; i++)
		dst[i] = (char)tolower((unsigned char)src[i]);
	dst[i] = '\0';

	/* strip leading dot */
	if (dst[0] == '.' && dst[1]) {
		memmove(dst, dst + 1, strlen(dst));
	}
}

/* --- Insert domain into hash table ----------------------------------- */

static int
insert_domain(struct l7_blacklist *bl, const char *raw_domain, uint8_t cat_idx)
{
	char norm[L7_BL_DOMAIN_MAX];
	uint32_t h;
	int bucket;
	struct l7_bl_entry *e;
	size_t dlen;

	if (bl->n_entries >= L7_BL_MAX_TOTAL)
		return -1;

	domain_lower(norm, raw_domain, sizeof(norm));

	if (!domain_valid(norm))
		return -1;

	h = fnv1a_hash(norm);
	bucket = (int)(h & (L7_BL_HASH_SIZE - 1));

	/* check duplicate */
	for (e = bl->buckets[bucket]; e; e = e->next) {
		if (strcmp(e->domain, norm) == 0)
			return 0;
	}

	dlen = strlen(norm);
	e = malloc(sizeof(*e) + dlen + 1);
	if (!e) {
		L7_ERROR("bl_insert: out of memory");
		return -1;
	}
	e->domain = (char *)(e + 1);
	memcpy(e->domain, norm, dlen + 1);
	e->cat_idx = cat_idx;
	e->next = bl->buckets[bucket];
	bl->buckets[bucket] = e;
	bl->n_entries++;
	return 0;
}

/* --- Load one domains file ------------------------------------------- */

static int
load_domains_file(struct l7_blacklist *bl, const char *path, uint8_t cat_idx)
{
	FILE *f;
	char line[512];
	char *nl;
	int count = 0;

	f = fopen(path, "r");
	if (!f)
		return -1;

	while (fgets(line, (int)sizeof(line), f)) {
		nl = strchr(line, '\n');
		if (nl)
			*nl = '\0';
		nl = strchr(line, '\r');
		if (nl)
			*nl = '\0';

		if (line[0] == '\0' || line[0] == '#')
			continue;

		if (bl->n_entries >= L7_BL_MAX_TOTAL) {
			L7_WARN("bl_load: max entries reached (%d), "
			    "skipping rest of %s", L7_BL_MAX_TOTAL, path);
			break;
		}

		if (insert_domain(bl, line, cat_idx) == 0)
			count++;
	}
	fclose(f);
	return count;
}

/* --- Whitelist helpers ----------------------------------------------- */

static int
setup_whitelist(struct l7_blacklist *bl, const char **domains, int n)
{
	int i;

	bl->n_whitelist = 0;
	bl->whitelist = NULL;

	if (n <= 0 || !domains)
		return 0;
	if (n > L7_BL_WL_MAX)
		n = L7_BL_WL_MAX;

	bl->whitelist = calloc((size_t)n, sizeof(char *));
	if (!bl->whitelist)
		return -1;

	for (i = 0; i < n; i++) {
		char norm[L7_BL_DOMAIN_MAX];

		if (!domains[i] || !*domains[i])
			continue;
		domain_lower(norm, domains[i], sizeof(norm));
		bl->whitelist[bl->n_whitelist] = strdup(norm);
		if (!bl->whitelist[bl->n_whitelist])
			continue;
		bl->n_whitelist++;
	}
	return 0;
}

static int
is_whitelisted(const struct l7_blacklist *bl, const char *domain)
{
	int i;
	const char *p;

	if (!bl->whitelist || bl->n_whitelist <= 0)
		return 0;

	p = domain;
	while (p && *p) {
		if (count_labels(p) < 2)
			break;
		for (i = 0; i < bl->n_whitelist; i++) {
			if (strcmp(p, bl->whitelist[i]) == 0)
				return 1;
		}
		p = strchr(p, '.');
		if (p)
			p++;
	}
	return 0;
}

/* --- Public API ------------------------------------------------------ */

struct l7_blacklist *
l7_blacklist_load(const char *dir, const char **cats, int n_cats,
    const char **whitelist, int n_whitelist)
{
	struct l7_blacklist *bl;
	int i, loaded;
	char path[512];
	char custom_path[512];

	if (!dir || !cats || n_cats <= 0)
		return NULL;
	if (n_cats > L7_BL_MAX_CATS)
		n_cats = L7_BL_MAX_CATS;

	bl = calloc(1, sizeof(*bl));
	if (!bl) {
		L7_ERROR("bl_load: out of memory (struct)");
		return NULL;
	}

	bl->buckets = calloc(L7_BL_HASH_SIZE, sizeof(struct l7_bl_entry *));
	if (!bl->buckets) {
		L7_ERROR("bl_load: out of memory (buckets)");
		free(bl);
		return NULL;
	}

	setup_whitelist(bl, whitelist, n_whitelist);

	bl->n_cats = 0;
	for (i = 0; i < n_cats; i++) {
		if (!cats[i] || !cat_name_valid(cats[i])) {
			L7_WARN("bl_load: skipping invalid category '%s'",
			    cats[i] ? cats[i] : "(null)");
			continue;
		}

		snprintf(path, sizeof(path), "%s/%s/domains", dir, cats[i]);

		loaded = 0;
		{
			int base_loaded;
			base_loaded = load_domains_file(bl, path,
			    (uint8_t)bl->n_cats);
			if (base_loaded > 0)
				loaded += base_loaded;
		}
		snprintf(custom_path, sizeof(custom_path),
		    "%s/_custom/%s.domains", dir, cats[i]);
		{
			int custom_loaded;
			custom_loaded = load_domains_file(bl, custom_path,
			    (uint8_t)bl->n_cats);
			if (custom_loaded > 0)
				loaded += custom_loaded;
		}
		if (loaded <= 0) {
			L7_WARN("bl_load: category %s — no domains "
			    "found (base=%s custom=%s)",
			    cats[i], path, custom_path);
			continue;
		}

		strncpy(bl->cats[bl->n_cats], cats[i],
		    sizeof(bl->cats[0]) - 1);
		bl->cats[bl->n_cats][sizeof(bl->cats[0]) - 1] = '\0';
		bl->cat_hits[bl->n_cats] = 0;

		L7_NOTE("bl_load: category %s — %d domains",
		    cats[i], loaded);
		bl->n_cats++;

		if (bl->n_entries >= L7_BL_MAX_TOTAL) {
			L7_WARN("bl_load: max total entries reached (%d)",
			    L7_BL_MAX_TOTAL);
			break;
		}
	}

	if (bl->n_cats == 0 || bl->n_entries == 0) {
		L7_WARN("bl_load: no domains loaded (cats=%d entries=%d)",
		    bl->n_cats, bl->n_entries);
		l7_blacklist_free(bl);
		return NULL;
	}

	return bl;
}

const char *
l7_blacklist_lookup(const struct l7_blacklist *bl, const char *domain)
{
	char norm[L7_BL_DOMAIN_MAX];
	const char *p;
	uint32_t h;
	int bucket;
	struct l7_bl_entry *e;

	if (!bl || !bl->buckets || !domain || !*domain)
		return NULL;

	domain_lower(norm, domain, sizeof(norm));

	if (is_whitelisted(bl, norm))
		return NULL;

	p = norm;
	while (p && *p) {
		if (count_labels(p) < 2)
			break;

		h = fnv1a_hash(p);
		bucket = (int)(h & (L7_BL_HASH_SIZE - 1));

		for (e = bl->buckets[bucket]; e; e = e->next) {
			if (strcmp(e->domain, p) == 0) {
				/* cast away const for hit counter */
				((struct l7_blacklist *)bl)->cat_hits[e->cat_idx]++;
				return bl->cats[e->cat_idx];
			}
		}

		p = strchr(p, '.');
		if (p)
			p++;
	}
	return NULL;
}

void
l7_blacklist_free(struct l7_blacklist *bl)
{
	int i;
	struct l7_bl_entry *e, *next;

	if (!bl)
		return;

	if (bl->buckets) {
		for (i = 0; i < L7_BL_HASH_SIZE; i++) {
			e = bl->buckets[i];
			while (e) {
				next = e->next;
				free(e);
				e = next;
			}
		}
		free(bl->buckets);
	}

	if (bl->whitelist) {
		for (i = 0; i < bl->n_whitelist; i++)
			free(bl->whitelist[i]);
		free(bl->whitelist);
	}

	free(bl);
}

int
l7_blacklist_count(const struct l7_blacklist *bl)
{
	if (!bl)
		return 0;
	return bl->n_entries;
}

int
l7_blacklist_cat_count(const struct l7_blacklist *bl)
{
	if (!bl)
		return 0;
	return bl->n_cats;
}

const char *
l7_blacklist_get_cat_name(const struct l7_blacklist *bl, int idx)
{
	if (!bl || idx < 0 || idx >= bl->n_cats)
		return NULL;
	return bl->cats[idx];
}

unsigned long long
l7_blacklist_get_cat_hit_count(const struct l7_blacklist *bl, int idx)
{
	if (!bl || idx < 0 || idx >= bl->n_cats)
		return 0;
	return bl->cat_hits[idx];
}
