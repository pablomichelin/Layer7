/*
 * bl_config.c — Parse do config.json separado das blacklists.
 *
 * Formato novo (v1.2.0 — regras por IP/CIDR):
 *   {
 *     "enabled": true,
 *     "whitelist": ["google.com", ...],
 *     "rules": [
 *       {
 *         "name": "Funcionarios",
 *         "enabled": true,
 *         "categories": ["adult", "gambling"],
 *         "src_cidrs": ["192.168.10.0/24"],
 *         "except_ips": ["192.168.10.1"]
 *       }
 *     ]
 *   }
 *
 * Formato antigo (v1.1.0 — retrocompativel):
 *   {
 *     "enabled": true,
 *     "categories": ["adult", "gambling", ...],
 *     "whitelist": ["google.com", ...],
 *     "except_ips": ["192.168.10.50", ...]
 *   }
 *
 * Parse manual sem biblioteca JSON (mesmo padrao do projecto).
 */
#include "bl_config.h"

#include <ctype.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>

#define BL_CFG_MAX_SIZE	(64 * 1024)

static void
skip_ws(const char **p)
{
	while (**p && isspace((unsigned char)**p))
		(*p)++;
}

static int
match_key(const char **p, const char *key)
{
	size_t klen = strlen(key);

	skip_ws(p);
	if (**p != '"')
		return 0;
	(*p)++;
	if (strncmp(*p, key, klen) != 0)
		return 0;
	if ((*p)[klen] != '"')
		return 0;
	*p += klen + 1;
	skip_ws(p);
	if (**p != ':')
		return 0;
	(*p)++;
	skip_ws(p);
	return 1;
}

static int
parse_bool(const char **p)
{
	skip_ws(p);
	if (strncmp(*p, "true", 4) == 0) {
		*p += 4;
		return 1;
	}
	if (strncmp(*p, "false", 5) == 0) {
		*p += 5;
		return 0;
	}
	return 0;
}

static int
parse_string(const char **p, char *dst, size_t dstsize)
{
	size_t i = 0;

	skip_ws(p);
	if (**p != '"')
		return -1;
	(*p)++;
	while (**p && **p != '"' && i < dstsize - 1) {
		if (**p == '\\') {
			(*p)++;
			if (!**p)
				break;
		}
		dst[i++] = **p;
		(*p)++;
	}
	dst[i] = '\0';
	if (**p == '"')
		(*p)++;
	return 0;
}

static int
parse_string_array(const char **p, char arr[][L7_BL_DOMAIN_MAX],
    int max, int item_max)
{
	int n = 0;

	skip_ws(p);
	if (**p != '[')
		return 0;
	(*p)++;
	skip_ws(p);

	while (**p && **p != ']' && n < max) {
		if (**p == ',') {
			(*p)++;
			skip_ws(p);
			continue;
		}
		if (**p == '"') {
			if (parse_string(p, arr[n], (size_t)item_max) == 0)
				n++;
			skip_ws(p);
		} else {
			(*p)++;
		}
	}
	if (**p == ']')
		(*p)++;
	return n;
}

static int
parse_cat_array(const char **p, char arr[][L7_BL_CAT_LEN], int max)
{
	int n = 0;

	skip_ws(p);
	if (**p != '[')
		return 0;
	(*p)++;
	skip_ws(p);

	while (**p && **p != ']' && n < max) {
		if (**p == ',') {
			(*p)++;
			skip_ws(p);
			continue;
		}
		if (**p == '"') {
			if (parse_string(p, arr[n], L7_BL_CAT_LEN) == 0)
				n++;
			skip_ws(p);
		} else {
			(*p)++;
		}
	}
	if (**p == ']')
		(*p)++;
	return n;
}

static int
parse_ip_array(const char **p, char arr[][48], int max)
{
	int n = 0;

	skip_ws(p);
	if (**p != '[')
		return 0;
	(*p)++;
	skip_ws(p);

	while (**p && **p != ']' && n < max) {
		if (**p == ',') {
			(*p)++;
			skip_ws(p);
			continue;
		}
		if (**p == '"') {
			if (parse_string(p, arr[n], 48) == 0)
				n++;
			skip_ws(p);
		} else {
			(*p)++;
		}
	}
	if (**p == ']')
		(*p)++;
	return n;
}

/* Skip any JSON value: string, number, bool, null, array, or object. */
static void
skip_value(const char **p)
{
	int depth;

	skip_ws(p);
	if (**p == '"') {
		(*p)++;
		while (**p && **p != '"') {
			if (**p == '\\' && *((*p) + 1))
				(*p)++;
			(*p)++;
		}
		if (**p == '"')
			(*p)++;
	} else if (**p == '[') {
		depth = 1;
		(*p)++;
		while (**p && depth > 0) {
			if (**p == '[')
				depth++;
			else if (**p == ']')
				depth--;
			else if (**p == '"') {
				(*p)++;
				while (**p && **p != '"') {
					if (**p == '\\' && *((*p) + 1))
						(*p)++;
					(*p)++;
				}
			}
			if (**p)
				(*p)++;
		}
	} else if (**p == '{') {
		depth = 1;
		(*p)++;
		while (**p && depth > 0) {
			if (**p == '{')
				depth++;
			else if (**p == '}')
				depth--;
			else if (**p == '"') {
				(*p)++;
				while (**p && **p != '"') {
					if (**p == '\\' && *((*p) + 1))
						(*p)++;
					(*p)++;
				}
			}
			if (**p)
				(*p)++;
		}
	} else {
		while (**p && **p != ',' && **p != '}' && **p != ']')
			(*p)++;
	}
}

/* Skip unknown key (already consumed ":") + value */
static void
skip_unknown_kv(const char **p)
{
	if (**p == '"') {
		(*p)++;
		while (**p && **p != '"') {
			if (**p == '\\' && *((*p) + 1))
				(*p)++;
			(*p)++;
		}
		if (**p == '"')
			(*p)++;
		skip_ws(p);
		if (**p == ':')
			(*p)++;
		skip_ws(p);
		skip_value(p);
	} else {
		(*p)++;
	}
}

/* Parse one rule object: { "name":"...", "enabled":true, ... } */
static int
parse_one_rule(const char **p, struct l7_bl_rule *rule)
{
	skip_ws(p);
	if (**p != '{')
		return -1;
	(*p)++;

	memset(rule, 0, sizeof(*rule));
	rule->enabled = 1;

	while (**p && **p != '}') {
		skip_ws(p);
		if (**p == ',' || **p == '\n' || **p == '\r') {
			(*p)++;
			continue;
		}
		if (**p == '}')
			break;

		if (match_key(p, "name")) {
			parse_string(p, rule->name,
			    sizeof(rule->name));
		} else if (match_key(p, "enabled")) {
			rule->enabled = parse_bool(p);
		} else if (match_key(p, "categories")) {
			rule->n_categories = parse_cat_array(p,
			    rule->categories, L7_BL_MAX_CATS);
		} else if (match_key(p, "src_cidrs")) {
			rule->n_src_cidrs = parse_ip_array(p,
			    rule->src_cidrs, L7_BL_RULE_CIDRS);
		} else if (match_key(p, "except_ips")) {
			rule->n_except_ips = parse_ip_array(p,
			    rule->except_ips, L7_BL_RULE_EXCEPT);
		} else {
			skip_unknown_kv(p);
		}
	}

	if (**p == '}')
		(*p)++;
	return 0;
}

/* Parse rules array: [ {...}, {...}, ... ] */
static int
parse_rules_array(const char **p, struct l7_bl_rule *rules, int max)
{
	int n = 0;

	skip_ws(p);
	if (**p != '[')
		return 0;
	(*p)++;
	skip_ws(p);

	while (**p && **p != ']' && n < max) {
		if (**p == ',') {
			(*p)++;
			skip_ws(p);
			continue;
		}
		if (**p == '{') {
			if (parse_one_rule(p, &rules[n]) == 0 &&
			    rules[n].n_categories > 0)
				n++;
			skip_ws(p);
		} else {
			(*p)++;
		}
	}
	if (**p == ']')
		(*p)++;
	return n;
}

int
l7_bl_config_load(const char *path, struct l7_bl_config *cfg)
{
	FILE *f;
	struct stat st;
	char *buf;
	const char *p;
	size_t sz;
	int has_rules = 0;
	/* Old format backward compat */
	char old_cats[L7_BL_MAX_CATS][L7_BL_CAT_LEN];
	int old_n_cats = 0;
	char old_except[64][48];
	int old_n_except = 0;

	memset(cfg, 0, sizeof(*cfg));

	if (!path || stat(path, &st) != 0)
		return -1;

	sz = (size_t)st.st_size;
	if (sz == 0 || sz > BL_CFG_MAX_SIZE)
		return -1;

	buf = malloc(sz + 1);
	if (!buf)
		return -1;

	f = fopen(path, "r");
	if (!f) {
		free(buf);
		return -1;
	}
	if (fread(buf, 1, sz, f) != sz) {
		fclose(f);
		free(buf);
		return -1;
	}
	fclose(f);
	buf[sz] = '\0';

	p = buf;
	skip_ws(&p);
	if (*p != '{') {
		free(buf);
		return -1;
	}
	p++;

	while (*p && *p != '}') {
		skip_ws(&p);
		if (*p == ',' || *p == '\n' || *p == '\r') {
			p++;
			continue;
		}
		if (*p == '}')
			break;

		if (match_key(&p, "enabled")) {
			cfg->enabled = parse_bool(&p);
		} else if (match_key(&p, "whitelist")) {
			cfg->n_whitelist = parse_string_array(&p,
			    cfg->whitelist, L7_BL_WL_MAX,
			    L7_BL_DOMAIN_MAX);
		} else if (match_key(&p, "rules")) {
			cfg->n_rules = parse_rules_array(&p,
			    cfg->rules, L7_BL_MAX_RULES);
			has_rules = 1;
		} else if (match_key(&p, "categories")) {
			old_n_cats = parse_cat_array(&p,
			    old_cats, L7_BL_MAX_CATS);
		} else if (match_key(&p, "except_ips")) {
			old_n_except = parse_ip_array(&p,
			    old_except, 64);
		} else {
			skip_unknown_kv(&p);
		}
	}

	/*
	 * Backward compat: old format without rules[].
	 * Convert flat categories/except_ips to a single global rule.
	 */
	if (!has_rules && old_n_cats > 0) {
		struct l7_bl_rule *r = &cfg->rules[0];
		int i;

		memset(r, 0, sizeof(*r));
		strncpy(r->name, "global", sizeof(r->name) - 1);
		r->enabled = 1;
		r->n_categories = old_n_cats;
		for (i = 0; i < old_n_cats && i < L7_BL_MAX_CATS; i++)
			strncpy(r->categories[i], old_cats[i],
			    sizeof(r->categories[0]) - 1);
		r->n_except_ips = old_n_except;
		for (i = 0; i < old_n_except && i < L7_BL_RULE_EXCEPT; i++)
			strncpy(r->except_ips[i], old_except[i],
			    sizeof(r->except_ips[0]) - 1);
		r->n_src_cidrs = 0;
		cfg->n_rules = 1;
	}

	free(buf);
	return 0;
}
