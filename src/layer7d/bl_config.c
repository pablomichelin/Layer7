/*
 * bl_config.c — Parse do config.json separado das blacklists.
 *
 * Formato esperado:
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

int
l7_bl_config_load(const char *path, struct l7_bl_config *cfg)
{
	FILE *f;
	struct stat st;
	char *buf;
	const char *p;
	size_t sz;

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
		} else if (match_key(&p, "categories")) {
			cfg->n_categories = parse_cat_array(&p,
			    cfg->categories, L7_BL_MAX_CATS);
		} else if (match_key(&p, "whitelist")) {
			cfg->n_whitelist = parse_string_array(&p,
			    cfg->whitelist, L7_BL_WL_MAX,
			    L7_BL_DOMAIN_MAX);
		} else if (match_key(&p, "except_ips")) {
			cfg->n_except_ips = parse_ip_array(&p,
			    cfg->except_ips, 64);
		} else {
			/* skip unknown key+value */
			if (*p == '"') {
				p++;
				while (*p && *p != '"')
					p++;
				if (*p == '"')
					p++;
				skip_ws(&p);
				if (*p == ':')
					p++;
				skip_ws(&p);
				/* skip value */
				if (*p == '"') {
					p++;
					while (*p && *p != '"') {
						if (*p == '\\' && *(p + 1))
							p++;
						p++;
					}
					if (*p == '"')
						p++;
				} else if (*p == '[') {
					int depth = 1;
					p++;
					while (*p && depth > 0) {
						if (*p == '[')
							depth++;
						else if (*p == ']')
							depth--;
						p++;
					}
				} else if (*p == '{') {
					int depth = 1;
					p++;
					while (*p && depth > 0) {
						if (*p == '{')
							depth++;
						else if (*p == '}')
							depth--;
						p++;
					}
				} else {
					while (*p && *p != ',' &&
					    *p != '}')
						p++;
				}
			} else {
				p++;
			}
		}
	}

	free(buf);
	return 0;
}
