#include "policy.h"
#include <strings.h>
#include "enforce.h"
#include <ctype.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

static void
skip_ws(const char **p)
{
	while (**p == ' ' || **p == '\t' || **p == '\n' || **p == '\r')
		(*p)++;
}

static const char *
json_skip_string(const char *p)
{
	if (*p != '"')
		return NULL;
	p++;
	while (*p && *p != '"') {
		if (*p == '\\' && p[1])
			p++;
		p++;
	}
	return *p == '"' ? p + 1 : NULL;
}

static const char *
json_obj_end(const char *p)
{
	int depth = 0;

	if (*p != '{')
		return NULL;
	for (; *p; p++) {
		if (*p == '"') {
			p = json_skip_string(p);
			if (!p)
				return NULL;
			p--;
			continue;
		}
		if (*p == '{')
			depth++;
		else if (*p == '}') {
			depth--;
			if (depth == 0)
				return p;
		}
	}
	return NULL;
}

static int
key_in_object(const char *obj, const char *obj_end, const char *key)
{
	char buf[64];
	size_t kl = strlen(key);
	const char *p;

	if (kl + 3 >= sizeof(buf))
		return 0;
	buf[0] = '"';
	memcpy(buf + 1, key, kl);
	buf[1 + kl] = '"';
	buf[2 + kl] = '\0';

	p = obj;
	while (p < obj_end) {
		p = strstr(p, buf);
		if (!p || p >= obj_end)
			return 0;
		if (p > obj && p[-1] == '\\') {
			p++;
			continue;
		}
		{
			const char *after = p + strlen(buf);
			skip_ws(&after);
			if (*after == ':')
				return 1;
		}
		p++;
	}
	return 0;
}

static int
extract_quoted_after_key(const char *obj, const char *obj_end,
    const char *key, char *out, size_t outsz)
{
	char pat[72];
	size_t kl = strlen(key);
	const char *p, *q;

	if (kl + 4 >= sizeof(pat))
		return -1;
	pat[0] = '"';
	memcpy(pat + 1, key, kl);
	pat[1 + kl] = '"';
	pat[2 + kl] = '\0';

	p = obj;
	for (;;) {
		p = strstr(p, pat);
		if (!p || p >= obj_end)
			return -1;
		q = p + strlen(pat);
		skip_ws(&q);
		if (*q != ':')
			return -1;
		q++;
		skip_ws(&q);
		if (*q != '"')
			return -1;
		q++;
		{
			size_t n = 0;
			while (*q && *q != '"' && n + 1 < outsz) {
				if (*q == '\\' && q[1])
					q++;
				out[n++] = *q++;
			}
			if (*q != '"')
				return -1;
			out[n] = '\0';
			return 0;
		}
	}
}

static int
extract_bool_after_key(const char *obj, const char *obj_end,
    const char *key, int *out)
{
	char pat[72];
	size_t kl = strlen(key);
	const char *p, *q;

	if (kl + 4 >= sizeof(pat))
		return -1;
	pat[0] = '"';
	memcpy(pat + 1, key, kl);
	pat[1 + kl] = '"';
	pat[2 + kl] = '\0';

	p = strstr(obj, pat);
	if (!p || p >= obj_end)
		return -1;
	q = p + strlen(pat);
	skip_ws(&q);
	if (*q != ':')
		return -1;
	q++;
	skip_ws(&q);
	if (strncmp(q, "true", 4) == 0 && !isalnum((unsigned char)q[4]) &&
	    q[4] != '_') {
		*out = 1;
		return 0;
	}
	if (strncmp(q, "false", 5) == 0 && !isalnum((unsigned char)q[5]) &&
	    q[5] != '_') {
		*out = 0;
		return 0;
	}
	return -1;
}

static int
extract_int_after_key(const char *obj, const char *obj_end,
    const char *key, int *out)
{
	char pat[72];
	size_t kl = strlen(key);
	const char *p, *q;
	long v;

	if (kl + 4 >= sizeof(pat))
		return -1;
	pat[0] = '"';
	memcpy(pat + 1, key, kl);
	pat[1 + kl] = '"';
	pat[2 + kl] = '\0';

	p = strstr(obj, pat);
	if (!p || p >= obj_end)
		return -1;
	q = p + strlen(pat);
	skip_ws(&q);
	if (*q != ':')
		return -1;
	q++;
	skip_ws(&q);
	if (!isdigit((unsigned char)*q) && *q != '-')
		return -1;
	v = strtol(q, (char **)&q, 10);
	if (v > 2147483647L || v < -2147483647L)
		return -1;
	*out = (int)v;
	return 0;
}

static enum layer7_action
parse_action(const char *s)
{
	if (strcmp(s, "allow") == 0)
		return LAYER7_ACTION_ALLOW;
	if (strcmp(s, "block") == 0)
		return LAYER7_ACTION_BLOCK;
	if (strcmp(s, "monitor") == 0)
		return LAYER7_ACTION_MONITOR;
	if (strcmp(s, "tag") == 0)
		return LAYER7_ACTION_TAG;
	return LAYER7_ACTION_MONITOR;
}

static int
parse_string_array_in_object(const char *ob, const char *oe_end,
    const char *array_key, char *dest_flat, int max_items, size_t item_w,
    int *n_out)
{
	char pat[72];
	size_t kl = strlen(array_key);
	const char *p;

	if (kl + 3 >= sizeof(pat))
		return -1;
	pat[0] = '"';
	memcpy(pat + 1, array_key, kl);
	pat[1 + kl] = '"';
	pat[2 + kl] = '\0';

	*n_out = 0;
	p = strstr(ob, pat);
	if (!p || p >= oe_end)
		return 0;
	p = strchr(p + strlen(pat), '[');
	if (!p || p >= oe_end)
		return 0;
	p++;
	for (;;) {
		skip_ws(&p);
		if (*p == ']')
			break;
		if (*p == '"') {
			const char *sq = p + 1;
			size_t n = 0;
			char *dest;

			if (*n_out >= max_items)
				return -1;
			dest = dest_flat + (size_t)(*n_out) * item_w;
			while (*sq && *sq != '"' && n + 1 < item_w) {
				if (*sq == '\\' && sq[1])
					sq++;
				dest[n++] = *sq++;
			}
			if (*sq != '"')
				return -1;
			dest[n] = '\0';
			(*n_out)++;
			p = sq + 1;
		} else if (*p == ',') {
			p++;
			continue;
		} else
			break;
		skip_ws(&p);
		if (*p == ',')
			p++;
	}
	return 0;
}

static int
parse_cidr_str(const char *s, struct l7_cidr *out)
{
	unsigned a, b, c, d;
	int pref;
	char buf[48];
	char *slash;

	snprintf(buf, sizeof(buf), "%s", s);
	slash = strchr(buf, '/');
	if (!slash)
		return -1;
	*slash = '\0';
	pref = atoi(slash + 1);
	if (pref < 0 || pref > 32)
		return -1;
	if (sscanf(buf, "%u.%u.%u.%u", &a, &b, &c, &d) != 4)
		return -1;
	if (a > 255 || b > 255 || c > 255 || d > 255)
		return -1;
	out->net = (uint32_t)((a << 24) | (b << 16) | (c << 8) | d);
	out->prefix = pref;
	return 0;
}

static int
parse_cidr_array_in_object(const char *ob, const char *oe,
    const char *key, struct l7_cidr *out, int max, int *n_out)
{
	char tmp[L7_MAX_SRC_CIDRS][L7_EXC_HOST_LEN];
	int n = 0, i;

	if (parse_string_array_in_object(ob, oe, key,
	    (char *)tmp, max, L7_EXC_HOST_LEN, &n) != 0)
		return -1;
	*n_out = 0;
	for (i = 0; i < n && *n_out < max; i++) {
		if (parse_cidr_str(tmp[i], &out[*n_out]) == 0)
			(*n_out)++;
	}
	return 0;
}

static int
parse_match_subobject(const char *obj, const char *obj_end,
    struct layer7_policy_rule *r)
{
	const char *mk = strstr(obj, "\"match\"");
	const char *ob, *oe;

	if (!mk || mk >= obj_end)
		return 0;
	ob = strchr(mk, '{');
	if (!ob || ob >= obj_end)
		return 0;
	oe = json_obj_end(ob);
	if (!oe || oe > obj_end)
		return -1;
	if (parse_string_array_in_object(ob, oe + 1, "ndpi_app",
		(char *)r->ndpi_apps, L7_MAX_APPS_PER_POLICY,
		L7_POLICY_APP_LEN, &r->n_ndpi_apps) != 0)
		return -1;
	if (parse_string_array_in_object(ob, oe + 1, "ndpi_category",
		(char *)r->ndpi_cats, L7_MAX_CATS_PER_POLICY,
		L7_POLICY_CAT_LEN, &r->n_ndpi_cats) != 0)
		return -1;
	if (parse_string_array_in_object(ob, oe + 1, "hosts",
		(char *)r->hosts, L7_MAX_HOSTS_PER_POLICY,
		L7_POLICY_HOST_LEN, &r->n_hosts) != 0)
		return -1;
	if (parse_string_array_in_object(ob, oe + 1, "src_hosts",
		(char *)r->src_hosts, L7_MAX_SRC_HOSTS,
		L7_EXC_HOST_LEN, &r->n_src_hosts) != 0)
		return -1;
	if (parse_cidr_array_in_object(ob, oe + 1, "src_cidrs",
		r->src_cidrs, L7_MAX_SRC_CIDRS, &r->n_src_cidrs) != 0)
		return -1;
	if (parse_string_array_in_object(ob, oe + 1, "groups",
		(char *)r->groups, L7_MAX_GROUPS_PER_POLICY,
		L7_GROUP_ID_LEN, &r->n_groups) != 0)
		return -1;
	return 0;
}

static uint8_t
day_str_to_bit(const char *s)
{
	if (strcasecmp(s, "sun") == 0) return 1 << 0;
	if (strcasecmp(s, "mon") == 0) return 1 << 1;
	if (strcasecmp(s, "tue") == 0) return 1 << 2;
	if (strcasecmp(s, "wed") == 0) return 1 << 3;
	if (strcasecmp(s, "thu") == 0) return 1 << 4;
	if (strcasecmp(s, "fri") == 0) return 1 << 5;
	if (strcasecmp(s, "sat") == 0) return 1 << 6;
	return 0;
}

static int
parse_time_hhmm(const char *s)
{
	int h, m;
	if (!s || strlen(s) < 4)
		return -1;
	if (s[2] != ':' && s[1] != ':')
		return -1;
	h = atoi(s);
	m = 0;
	{
		const char *colon = strchr(s, ':');
		if (colon)
			m = atoi(colon + 1);
	}
	if (h < 0 || h > 23 || m < 0 || m > 59)
		return -1;
	return h * 60 + m;
}

static void
parse_schedule_in_policy(const char *ob, const char *oe,
    struct l7_schedule *sched)
{
	const char *skey, *sobj, *send;
	int n_days = 0, i;
	char start_str[8], end_str[8];

	sched->has_schedule = 0;
	sched->days = 0;
	sched->start_min = 0;
	sched->end_min = 0;

	if (!key_in_object(ob, oe, "schedule"))
		return;

	skey = strstr(ob, "\"schedule\"");
	if (!skey || skey >= oe)
		return;
	sobj = strchr(skey + 10, '{');
	if (!sobj || sobj >= oe)
		return;
	send = json_obj_end(sobj);
	if (!send || send >= oe)
		return;

	{
		char day_items[8][16];
		int nd = 0;
		(void)parse_string_array_in_object(sobj, send + 1, "days",
		    (char *)day_items, 8, 16, &nd);
		for (i = 0; i < nd; i++)
			sched->days |= day_str_to_bit(day_items[i]);
		n_days = nd;
	}

	start_str[0] = '\0';
	end_str[0] = '\0';
	(void)extract_quoted_after_key(sobj, send + 1, "start",
	    start_str, sizeof(start_str));
	(void)extract_quoted_after_key(sobj, send + 1, "end",
	    end_str, sizeof(end_str));

	if (n_days > 0 && start_str[0] && end_str[0]) {
		int s = parse_time_hhmm(start_str);
		int e = parse_time_hhmm(end_str);
		if (s >= 0 && e >= 0) {
			sched->has_schedule = 1;
			sched->start_min = s;
			sched->end_min = e;
		}
	}
}

int
layer7_schedule_active(const struct l7_schedule *s)
{
	struct tm *tm;
	time_t now;
	int wday_bit, cur_min;

	if (!s->has_schedule)
		return 1;

	now = time(NULL);
	tm = localtime(&now);
	if (!tm)
		return 1;

	wday_bit = 1 << tm->tm_wday;
	if (!(s->days & wday_bit))
		return 0;

	cur_min = tm->tm_hour * 60 + tm->tm_min;

	if (s->start_min <= s->end_min) {
		return cur_min >= s->start_min && cur_min < s->end_min;
	}
	/* overnight range (e.g. 22:00-06:00) */
	return cur_min >= s->start_min || cur_min < s->end_min;
}

static int
parse_one_policy(const char *ob, const char *oe,
    struct layer7_policy_rule *r)
{
	char act[16];

	memset(r, 0, sizeof(*r));
	r->enabled = 1;
	r->action = LAYER7_ACTION_MONITOR;
	r->priority = 0;

	if (extract_quoted_after_key(ob, oe, "id", r->id, sizeof(r->id)) != 0)
		return -1;
	(void)extract_quoted_after_key(ob, oe, "name", r->name, sizeof(r->name));
	if (extract_bool_after_key(ob, oe, "enabled", &r->enabled) != 0)
		r->enabled = 1;
	if (extract_quoted_after_key(ob, oe, "action", act, sizeof(act)) == 0)
		r->action = parse_action(act);
	if (extract_int_after_key(ob, oe, "priority", &r->priority) != 0)
		r->priority = 0;
	if (extract_quoted_after_key(ob, oe, "tag_table", r->tag_table,
		sizeof(r->tag_table)) != 0)
		r->tag_table[0] = '\0';
	else if (!layer7_pf_table_name_ok(r->tag_table))
		r->tag_table[0] = '\0';
	parse_schedule_in_policy(ob, oe, &r->schedule);
	(void)parse_string_array_in_object(ob, oe, "interfaces",
	    (char *)r->ifaces, L7_MAX_IFACES_PER_RULE,
	    L7_IFACE_NAME_LEN, &r->n_ifaces);
	if (key_in_object(ob, oe, "match")) {
		if (parse_match_subobject(ob, oe, r) != 0)
			return -1;
	}
	return 0;
}

static const char *
find_layer7_key_array(const char *json, size_t len, const char *array_name)
{
	const char *end = json + len;
	const char *layer, *pol;
	char pat[48];
	size_t nl = strlen(array_name);

	if (nl + 5 >= sizeof(pat))
		return NULL;
	memcpy(pat, "\"", 1);
	memcpy(pat + 1, array_name, nl);
	pat[1 + nl] = '"';
	pat[2 + nl] = '\0';

	layer = strstr(json, "\"layer7\"");
	if (!layer || layer >= end)
		return NULL;
	pol = strstr(layer, pat);
	if (!pol || pol >= end)
		return NULL;
	pol = strchr(pol, '[');
	return pol;
}

int
layer7_policies_parse(const char *json, size_t len,
    struct layer7_policy_rule *out, int *n_out, int max_out)
{
	const char *arr, *q, *end;
	int n = 0;

	*n_out = 0;
	if (!json || max_out <= 0)
		return -1;
	end = json + (len ? len : strlen(json));
	arr = find_layer7_key_array(json, len ? len : (size_t)(end - json),
	    "policies");
	if (!arr)
		return 0;

	q = arr + 1;
	while (*q && *q != ']' && n < max_out) {
		while (*q && (*q == ' ' || *q == '\t' || *q == '\n' || *q == '\r' ||
		    *q == ','))
			q++;
		if (*q == ']' || !*q)
			break;
		if (*q == '{') {
			const char *oe = json_obj_end(q);
			if (!oe || oe >= end)
				return -1;
			if (parse_one_policy(q, oe + 1, &out[n]) == 0)
				n++;
			q = oe + 1;
		} else
			q++;
	}
	*n_out = n;
	return 0;
}

static int
parse_one_exception(const char *ob, const char *oe,
    struct layer7_exception *e)
{
	char act[16];
	char single_host[L7_EXC_HOST_LEN];
	char single_cidr[L7_EXC_HOST_LEN];

	memset(e, 0, sizeof(*e));
	e->enabled = 1;
	e->action = LAYER7_ACTION_ALLOW;
	e->priority = 0;

	(void)extract_quoted_after_key(ob, oe, "id", e->id, sizeof(e->id));
	if (extract_bool_after_key(ob, oe, "enabled", &e->enabled) != 0)
		e->enabled = 1;
	if (extract_quoted_after_key(ob, oe, "action", act, sizeof(act)) == 0)
		e->action = parse_action(act);
	if (extract_int_after_key(ob, oe, "priority", &e->priority) != 0)
		e->priority = 0;

	/* multi-host: "hosts": ["ip1","ip2",...] */
	(void)parse_string_array_in_object(ob, oe, "hosts",
	    (char *)e->hosts, L7_EXC_MAX_HOSTS,
	    L7_EXC_HOST_LEN, &e->n_hosts);

	/* backward compat: single "host" field */
	if (e->n_hosts == 0 &&
	    extract_quoted_after_key(ob, oe, "host", single_host,
	    sizeof(single_host)) == 0 && single_host[0]) {
		snprintf(e->hosts[0], L7_EXC_HOST_LEN, "%s", single_host);
		e->n_hosts = 1;
	}

	/* multi-cidr: "cidrs": ["a.b.c.d/n",...] */
	(void)parse_cidr_array_in_object(ob, oe, "cidrs",
	    e->cidrs, L7_EXC_MAX_CIDRS, &e->n_cidrs);

	/* backward compat: single "cidr" field */
	if (e->n_cidrs == 0 &&
	    extract_quoted_after_key(ob, oe, "cidr", single_cidr,
	    sizeof(single_cidr)) == 0 && single_cidr[0]) {
		if (parse_cidr_str(single_cidr, &e->cidrs[0]) == 0)
			e->n_cidrs = 1;
	}

	/* interfaces */
	(void)parse_string_array_in_object(ob, oe, "interfaces",
	    (char *)e->ifaces, L7_MAX_IFACES_PER_RULE,
	    L7_IFACE_NAME_LEN, &e->n_ifaces);

	if (e->n_hosts == 0 && e->n_cidrs == 0)
		return -1;
	return 0;
}

int
layer7_exceptions_parse(const char *json, size_t len,
    struct layer7_exception *out, int *n_out, int max_out)
{
	const char *arr, *q, *end;
	int n = 0;

	*n_out = 0;
	if (!json || max_out <= 0)
		return -1;
	end = json + (len ? len : strlen(json));
	arr = find_layer7_key_array(json, len ? len : (size_t)(end - json),
	    "exceptions");
	if (!arr)
		return 0;

	q = arr + 1;
	while (*q && *q != ']' && n < max_out) {
		while (*q && (*q == ' ' || *q == '\t' || *q == '\n' || *q == '\r' ||
		    *q == ','))
			q++;
		if (*q == ']' || !*q)
			break;
		if (*q == '{') {
			const char *oe = json_obj_end(q);
			if (!oe || oe >= end)
				return -1;
			if (parse_one_exception(q, oe + 1, &out[n]) == 0)
				n++;
			q = oe + 1;
		} else
			q++;
	}
	*n_out = n;
	return 0;
}

static int
policy_cmp(const void *a, const void *b)
{
	const struct layer7_policy_rule *x = a;
	const struct layer7_policy_rule *y = b;

	if (x->priority != y->priority)
		return y->priority - x->priority;
	return strcmp(x->id, y->id);
}

static int
exc_cmp(const void *a, const void *b)
{
	const struct layer7_exception *x = a;
	const struct layer7_exception *y = b;

	if (x->priority != y->priority)
		return y->priority - x->priority;
	return strcmp(x->id, y->id);
}

void
layer7_policies_sort(struct layer7_policy_rule *rules, int n)
{
	if (n > 1)
		qsort(rules, (size_t)n, sizeof(rules[0]), policy_cmp);
}

void
layer7_exceptions_sort(struct layer7_exception *exc, int n)
{
	if (n > 1)
		qsort(exc, (size_t)n, sizeof(exc[0]), exc_cmp);
}

static int
parse_one_group(const char *ob, const char *oe, struct layer7_group *g)
{
	memset(g, 0, sizeof(*g));
	if (extract_quoted_after_key(ob, oe, "id", g->id, sizeof(g->id)) != 0)
		return -1;
	(void)extract_quoted_after_key(ob, oe, "name", g->name,
	    sizeof(g->name));
	(void)parse_cidr_array_in_object(ob, oe, "cidrs",
	    g->cidrs, L7_MAX_GROUP_CIDRS, &g->n_cidrs);
	(void)parse_string_array_in_object(ob, oe, "hosts",
	    (char *)g->hosts, L7_MAX_GROUP_HOSTS,
	    L7_EXC_HOST_LEN, &g->n_hosts);
	if (g->n_cidrs == 0 && g->n_hosts == 0)
		return -1;
	return 0;
}

int
layer7_groups_parse(const char *json, size_t len,
    struct layer7_group *out, int *n_out, int max_out)
{
	const char *arr, *q, *end;
	int n = 0;

	*n_out = 0;
	if (!json || max_out <= 0)
		return -1;
	end = json + (len ? len : strlen(json));
	arr = find_layer7_key_array(json, len ? len : (size_t)(end - json),
	    "groups");
	if (!arr)
		return 0;

	q = arr + 1;
	while (*q && *q != ']' && n < max_out) {
		while (*q && (*q == ' ' || *q == '\t' || *q == '\n' ||
		    *q == '\r' || *q == ','))
			q++;
		if (*q == ']' || !*q)
			break;
		if (*q == '{') {
			const char *oe = json_obj_end(q);
			if (!oe || oe >= end)
				return -1;
			if (parse_one_group(q, oe + 1, &out[n]) == 0)
				n++;
			q = oe + 1;
		} else
			q++;
	}
	*n_out = n;
	return 0;
}

void
layer7_policies_expand_groups(struct layer7_policy_rule *rules,
    int n_rules, const struct layer7_group *groups, int n_groups)
{
	int i, j, k;

	for (i = 0; i < n_rules; i++) {
		struct layer7_policy_rule *r = &rules[i];
		if (r->n_groups == 0)
			continue;
		for (j = 0; j < r->n_groups; j++) {
			const struct layer7_group *g = NULL;
			for (k = 0; k < n_groups; k++) {
				if (strcmp(groups[k].id, r->groups[j]) == 0) {
					g = &groups[k];
					break;
				}
			}
			if (!g)
				continue;
			for (k = 0; k < g->n_cidrs &&
			    r->n_src_cidrs < L7_MAX_SRC_CIDRS; k++) {
				r->src_cidrs[r->n_src_cidrs] = g->cidrs[k];
				r->n_src_cidrs++;
			}
			for (k = 0; k < g->n_hosts &&
			    r->n_src_hosts < L7_MAX_SRC_HOSTS; k++) {
				snprintf(r->src_hosts[r->n_src_hosts],
				    L7_EXC_HOST_LEN, "%s", g->hosts[k]);
				r->n_src_hosts++;
			}
		}
	}
}

static int
ipv4_parse(const char *s, uint32_t *out)
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
cidr_u32_match(uint32_t ip, uint32_t net, int prefix)
{
	uint32_t mask;

	if (prefix <= 0)
		return 1;
	if (prefix >= 32)
		return ip == net;
	mask = (uint32_t)(0xffffffffU << (unsigned)(32 - prefix));
	return (ip & mask) == (net & mask);
}

static int
iface_list_matches(const char ifaces[][L7_IFACE_NAME_LEN], int n,
    const char *iface)
{
	int i;
	if (n == 0)
		return 1;
	if (!iface || !*iface)
		return 0;
	for (i = 0; i < n; i++) {
		if (strcmp(ifaces[i], iface) == 0)
			return 1;
	}
	return 0;
}

static int
exception_matches_src(const struct layer7_exception *e, const char *src_ip,
    const char *iface)
{
	uint32_t ip;
	int i;

	if (!iface_list_matches(e->ifaces, e->n_ifaces, iface))
		return 0;
	if (!src_ip || !*src_ip)
		return 0;
	for (i = 0; i < e->n_hosts; i++) {
		if (strcmp(src_ip, e->hosts[i]) == 0)
			return 1;
	}
	if (e->n_cidrs > 0 && ipv4_parse(src_ip, &ip) == 0) {
		for (i = 0; i < e->n_cidrs; i++) {
			if (cidr_u32_match(ip, e->cidrs[i].net,
			    e->cidrs[i].prefix))
				return 1;
		}
	}
	return 0;
}

static int
src_matches_rule(const struct layer7_policy_rule *r, const char *src_ip)
{
	int i;
	uint32_t ip;

	if (r->n_src_hosts == 0 && r->n_src_cidrs == 0)
		return 1;
	if (!src_ip || !*src_ip)
		return 0;
	for (i = 0; i < r->n_src_hosts; i++) {
		if (strcmp(src_ip, r->src_hosts[i]) == 0)
			return 1;
	}
	if (r->n_src_cidrs > 0 && ipv4_parse(src_ip, &ip) == 0) {
		for (i = 0; i < r->n_src_cidrs; i++) {
			if (cidr_u32_match(ip, r->src_cidrs[i].net,
			    r->src_cidrs[i].prefix))
				return 1;
		}
	}
	return 0;
}

static int
host_matches_rule(const char *flow_host, const char *rule_host)
{
	size_t flow_len, rule_len;

	if (!flow_host || !*flow_host || !rule_host || !*rule_host)
		return 0;
	flow_len = strlen(flow_host);
	rule_len = strlen(rule_host);
	if (flow_len == rule_len && strcasecmp(flow_host, rule_host) == 0)
		return 1;
	if (flow_len <= rule_len)
		return 0;
	if (flow_host[flow_len - rule_len - 1] != '.')
		return 0;
	return strcasecmp(flow_host + (flow_len - rule_len), rule_host) == 0;
}

static int
rule_matches(const struct layer7_policy_rule *r, const char *iface,
    const char *src_ip, const char *ndpi_app, const char *ndpi_cat,
    const char *host)
{
	int i;
	int app_matched = 0, host_matched = 0;

	if (!layer7_schedule_active(&r->schedule))
		return 0;
	if (!iface_list_matches(r->ifaces, r->n_ifaces, iface))
		return 0;
	if (!src_matches_rule(r, src_ip))
		return 0;

	if (r->n_ndpi_cats > 0) {
		if (!ndpi_cat)
			return 0;
		for (i = 0; i < r->n_ndpi_cats; i++) {
			if (strcmp(ndpi_cat, r->ndpi_cats[i]) == 0)
				break;
		}
		if (i >= r->n_ndpi_cats)
			return 0;
	}

	if (r->n_ndpi_apps > 0 && ndpi_app) {
		for (i = 0; i < r->n_ndpi_apps; i++) {
			if (strcmp(ndpi_app, r->ndpi_apps[i]) == 0) {
				app_matched = 1;
				break;
			}
		}
	}

	if (r->n_hosts > 0 && host && *host) {
		for (i = 0; i < r->n_hosts; i++) {
			if (host_matches_rule(host, r->hosts[i])) {
				host_matched = 1;
				break;
			}
		}
	}

	/*
	 * When BOTH apps AND hosts are configured: OR between them.
	 * Catches QUIC/TLS flows by host when nDPI reports generic protocol.
	 */
	if (r->n_ndpi_apps > 0 && r->n_hosts > 0)
		return app_matched || host_matched;

	if (r->n_ndpi_apps > 0)
		return app_matched;

	if (r->n_hosts > 0)
		return host_matched;

	return 1;
}

static void
dec_clear_pf(struct layer7_decision *dec)
{
	dec->pf_table[0] = '\0';
}

static void
dec_set_pf_block(struct layer7_decision *dec)
{
	strncpy(dec->pf_table, L7_PF_TABLE_BLOCK, sizeof(dec->pf_table) - 1);
	dec->pf_table[sizeof(dec->pf_table) - 1] = '\0';
}

static void
dec_set_pf_tag(struct layer7_decision *dec, const struct layer7_policy_rule *r)
{
	const char *t;

	t = (r->tag_table[0] && layer7_pf_table_name_ok(r->tag_table)) ?
	    r->tag_table :
	    L7_PF_TABLE_TAG_DEFAULT;
	strncpy(dec->pf_table, t, sizeof(dec->pf_table) - 1);
	dec->pf_table[sizeof(dec->pf_table) - 1] = '\0';
}

static void
fill_enforce(const struct layer7_policy_rule *r, int global_enforce,
    struct layer7_decision *dec)
{
	dec_clear_pf(dec);
	if (global_enforce &&
	    (r->action == LAYER7_ACTION_BLOCK ||
		r->action == LAYER7_ACTION_TAG)) {
		dec->would_enforce_block_or_tag = 1;
		if (r->action == LAYER7_ACTION_BLOCK)
			dec_set_pf_block(dec);
		else
			dec_set_pf_tag(dec, r);
	} else
		dec->would_enforce_block_or_tag = 0;
}

static void
fill_enforce_action(enum layer7_action act, int global_enforce,
    struct layer7_decision *dec)
{
	dec_clear_pf(dec);
	if (global_enforce &&
	    (act == LAYER7_ACTION_BLOCK || act == LAYER7_ACTION_TAG)) {
		dec->would_enforce_block_or_tag = 1;
		if (act == LAYER7_ACTION_BLOCK)
			dec_set_pf_block(dec);
		else
			strncpy(dec->pf_table, L7_PF_TABLE_TAG_DEFAULT,
			    sizeof(dec->pf_table) - 1);
	} else
		dec->would_enforce_block_or_tag = 0;
}

void
layer7_flow_decide(const struct layer7_exception *exc, int n_exc,
    const struct layer7_policy_rule *rules, int n_rules, int global_enforce,
    const char *iface, const char *src_ip,
    const char *ndpi_app, const char *ndpi_category, const char *host,
    struct layer7_decision *dec)
{
	int i;

	memset(dec, 0, sizeof(*dec));
	dec->matched_policy_id[0] = '\0';
	dec->matched_exception_id[0] = '\0';
	dec_clear_pf(dec);

	for (i = 0; i < n_exc; i++) {
		if (!exc[i].enabled)
			continue;
		if (!exception_matches_src(&exc[i], src_ip, iface))
			continue;
		dec->action = exc[i].action;
		dec->reason = L7_DECIDE_EXCEPTION;
		strncpy(dec->matched_exception_id, exc[i].id,
		    sizeof(dec->matched_exception_id) - 1);
		dec->matched_exception_id[sizeof(dec->matched_exception_id) - 1] =
		    '\0';
		fill_enforce_action(exc[i].action, global_enforce, dec);
		return;
	}

	for (i = 0; i < n_rules; i++) {
		const struct layer7_policy_rule *r = &rules[i];

		if (!r->enabled)
			continue;
		if (!rule_matches(r, iface, src_ip, ndpi_app, ndpi_category, host))
			continue;
		dec->action = r->action;
		dec->reason = L7_DECIDE_POLICY_MATCH;
		strncpy(dec->matched_policy_id, r->id,
		    sizeof(dec->matched_policy_id) - 1);
		dec->matched_policy_id[sizeof(dec->matched_policy_id) - 1] =
		    '\0';
		fill_enforce(r, global_enforce, dec);
		return;
	}

	if (global_enforce) {
		dec->action = LAYER7_ACTION_ALLOW;
		dec->reason = L7_DECIDE_DEFAULT_ALLOW;
	} else {
		dec->action = LAYER7_ACTION_MONITOR;
		dec->reason = L7_DECIDE_DEFAULT_MONITOR;
	}
	dec->would_enforce_block_or_tag = 0;
}

const char *
layer7_action_str(enum layer7_action a)
{
	switch (a) {
	case LAYER7_ACTION_ALLOW:
		return "allow";
	case LAYER7_ACTION_BLOCK:
		return "block";
	case LAYER7_ACTION_MONITOR:
		return "monitor";
	case LAYER7_ACTION_TAG:
		return "tag";
	default:
		return "?";
	}
}

const char *
layer7_decide_reason_str(enum layer7_decide_reason r)
{
	switch (r) {
	case L7_DECIDE_EXCEPTION:
		return "exception";
	case L7_DECIDE_POLICY_MATCH:
		return "policy_match";
	case L7_DECIDE_DEFAULT_MONITOR:
		return "default_monitor";
	case L7_DECIDE_DEFAULT_ALLOW:
		return "default_allow";
	default:
		return "?";
	}
}

int
layer7_domain_is_blocked(const struct layer7_policy_rule *rules,
    int n_rules, const char *domain)
{
	int i, j;

	if (!rules || n_rules <= 0 || !domain || !*domain)
		return 0;

	for (i = 0; i < n_rules; i++) {
		const struct layer7_policy_rule *r = &rules[i];

		if (!r->enabled || r->action != LAYER7_ACTION_BLOCK)
			continue;
		if (!layer7_schedule_active(&r->schedule))
			continue;
		for (j = 0; j < r->n_hosts; j++) {
			if (host_matches_rule(domain, r->hosts[j]))
				return 1;
		}
	}
	return 0;
}
