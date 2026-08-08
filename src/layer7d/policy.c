#include "policy.h"
#include <strings.h>
#include "enforce.h"
#include <arpa/inet.h>
#include <ctype.h>
#include <netinet/in.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
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
	char buf[64];
	char *slash;
	struct in6_addr a6;

	if (!s || !out || !*s)
		return -1;
	snprintf(buf, sizeof(buf), "%s", s);
	slash = strchr(buf, '/');
	if (!slash)
		return -1;
	*slash = '\0';
	pref = atoi(slash + 1);
	if (pref < 0)
		return -1;

	/* IPv4 dotted (preferido quando o formato casa) */
	if (sscanf(buf, "%u.%u.%u.%u", &a, &b, &c, &d) == 4) {
		if (pref > 32 || a > 255 || b > 255 || c > 255 || d > 255)
			return -1;
		memset(out, 0, sizeof(*out));
		out->family = AF_INET;
		out->prefix = pref;
		out->addr.v4 = (uint32_t)((a << 24) | (b << 16) | (c << 8) | d);
		return 0;
	}

	/* IPv6 textual /0–128 */
	if (pref > 128)
		return -1;
	if (inet_pton(AF_INET6, buf, &a6) != 1)
		return -1;
	memset(out, 0, sizeof(*out));
	out->family = AF_INET6;
	out->prefix = pref;
	memcpy(out->addr.v6, &a6, 16);
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

/*
 * Canonicaliza user AD na política (alinhado a layer7_idmap_normalize_user):
 * DOMAIN\user / UPN → local lowercase; rejeita *$ e vazios.
 * Sem dependência de identity_map.c (testes standalone do policy parser).
 */
static int
ad_user_canon(const char *in, char *out, size_t out_sz)
{
	const char *start;
	const char *at;
	const char *slash;
	size_t n, i;

	if (in == NULL || out == NULL || out_sz < 2)
		return -1;
	out[0] = '\0';
	while (*in == ' ' || *in == '\t')
		in++;
	if (*in == '\0')
		return -1;
	start = in;
	slash = strrchr(start, '\\');
	if (slash != NULL && slash[1] != '\0')
		start = slash + 1;
	at = strchr(start, '@');
	if (at != NULL) {
		if (at == start)
			return -1;
		n = (size_t)(at - start);
	} else
		n = strlen(start);
	while (n > 0 && (start[n - 1] == ' ' || start[n - 1] == '\t'))
		n--;
	if (n == 0 || n >= out_sz || n >= L7_AD_USER_LEN)
		return -1;
	for (i = 0; i < n; i++) {
		unsigned char c = (unsigned char)start[i];
		if (c < 0x20 || c == '/' || c == '\\')
			return -1;
		out[i] = (char)tolower(c);
	}
	out[n] = '\0';
	if (out[n - 1] == '$')
		return -1;
	return 0;
}

static int
ad_group_canon(const char *in, char *out, size_t out_sz)
{
	size_t n, i;

	if (in == NULL || out == NULL || out_sz < 2)
		return -1;
	while (*in == ' ' || *in == '\t')
		in++;
	n = strlen(in);
	while (n > 0 && (in[n - 1] == ' ' || in[n - 1] == '\t'))
		n--;
	if (n == 0 || n >= out_sz || n >= L7_AD_GROUP_LEN)
		return -1;
	for (i = 0; i < n; i++) {
		unsigned char c = (unsigned char)in[i];
		if (c < 0x20)
			return -1;
		out[i] = (char)tolower(c);
	}
	out[n] = '\0';
	return 0;
}

static void
canonicalize_ad_targets(struct layer7_policy_rule *r)
{
	int i, w;
	char utmp[L7_AD_USER_LEN];
	char gtmp[L7_AD_GROUP_LEN];

	w = 0;
	for (i = 0; i < r->n_ad_users; i++) {
		if (ad_user_canon(r->ad_users[i], utmp, sizeof(utmp)) != 0)
			continue;
		snprintf(r->ad_users[w], sizeof(r->ad_users[w]), "%s", utmp);
		w++;
	}
	r->n_ad_users = w;

	w = 0;
	for (i = 0; i < r->n_ad_groups; i++) {
		if (ad_group_canon(r->ad_groups[i], gtmp, sizeof(gtmp)) != 0)
			continue;
		snprintf(r->ad_groups[w], sizeof(r->ad_groups[w]), "%s", gtmp);
		w++;
	}
	r->n_ad_groups = w;
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
	if (parse_string_array_in_object(ob, oe + 1, "src_exclude_groups",
		(char *)r->src_exclude_groups, L7_MAX_GROUPS_PER_POLICY,
		L7_GROUP_ID_LEN, &r->n_src_exclude_groups) != 0)
		return -1;
	if (parse_cidr_array_in_object(ob, oe + 1, "src_exclude_cidrs",
		r->src_exclude_cidrs, L7_MAX_SRC_CIDRS,
		&r->n_src_exclude_cidrs) != 0)
		return -1;
	if (parse_string_array_in_object(ob, oe + 1, "groups",
		(char *)r->groups, L7_MAX_GROUPS_PER_POLICY,
		L7_GROUP_ID_LEN, &r->n_groups) != 0)
		return -1;
	if (parse_string_array_in_object(ob, oe + 1, "ad_users",
		(char *)r->ad_users, L7_MAX_AD_USERS_PER_POLICY,
		L7_AD_USER_LEN, &r->n_ad_users) != 0)
		return -1;
	if (parse_string_array_in_object(ob, oe + 1, "ad_groups",
		(char *)r->ad_groups, L7_MAX_AD_GROUPS_PER_POLICY,
		L7_AD_GROUP_LEN, &r->n_ad_groups) != 0)
		return -1;
	canonicalize_ad_targets(r);
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
policy_block_has_criteria(const struct layer7_policy_rule *r)
{
	if (!r)
		return 0;
	return r->n_hosts > 0 || r->n_ndpi_apps > 0 || r->n_ndpi_cats > 0;
}

static int
parse_one_policy(const char *ob, const char *oe,
    struct layer7_policy_rule *r)
{
	char act[16];
	int quarantine = 0;

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
	(void)extract_bool_after_key(ob, oe, "scope_global", &r->scope_global);
	if (extract_bool_after_key(ob, oe, "quarantine_origin",
	    &r->quarantine_origin) != 0)
		r->quarantine_origin = 0;
	if (extract_bool_after_key(ob, oe, "quarantine", &quarantine) == 0 &&
	    quarantine)
		r->quarantine_origin = 1;
	parse_schedule_in_policy(ob, oe, &r->schedule);
	(void)parse_string_array_in_object(ob, oe, "interfaces",
	    (char *)r->ifaces, L7_MAX_IFACES_PER_RULE,
	    L7_IFACE_NAME_LEN, &r->n_ifaces);
	if (key_in_object(ob, oe, "match")) {
		if (parse_match_subobject(ob, oe, r) != 0)
			return -1;
	}
	if (r->action == LAYER7_ACTION_BLOCK && !policy_block_has_criteria(r) &&
	    !r->quarantine_origin && !r->scope_global)
		return -1;
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
	/*
	 * Caminho A / A2: IPs de dispositivos resolvidos pelo pacote
	 * (MAC->IP via DHCP leases/ARP) sao gravados em "device_ips" e
	 * entram tambem como hosts de origem do grupo. Append apos os
	 * hosts manuais, respeitando o limite. Retrocompatível: configs
	 * sem "device_ips" mantem o comportamento anterior.
	 */
	if (g->n_hosts < L7_MAX_GROUP_HOSTS) {
		int n_dev = 0;
		(void)parse_string_array_in_object(ob, oe, "device_ips",
		    (char *)g->hosts[g->n_hosts],
		    L7_MAX_GROUP_HOSTS - g->n_hosts,
		    L7_EXC_HOST_LEN, &n_dev);
		g->n_hosts += n_dev;
	}
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

void
layer7_policies_expand_exclude_groups(struct layer7_policy_rule *rules,
    int n_rules, const struct layer7_group *groups, int n_groups)
{
	int i, j, k;

	for (i = 0; i < n_rules; i++) {
		struct layer7_policy_rule *r = &rules[i];
		if (r->n_src_exclude_groups == 0)
			continue;
		for (j = 0; j < r->n_src_exclude_groups; j++) {
			const struct layer7_group *g = NULL;
			for (k = 0; k < n_groups; k++) {
				if (strcmp(groups[k].id,
				    r->src_exclude_groups[j]) == 0) {
					g = &groups[k];
					break;
				}
			}
			if (!g)
				continue;
			for (k = 0; k < g->n_cidrs &&
			    r->n_src_exclude_cidrs < L7_MAX_SRC_CIDRS; k++) {
				r->src_exclude_cidrs[r->n_src_exclude_cidrs] =
				    g->cidrs[k];
				r->n_src_exclude_cidrs++;
			}
			for (k = 0; k < g->n_hosts &&
			    r->n_src_exclude_hosts < L7_MAX_SRC_HOSTS; k++) {
				snprintf(r->src_exclude_hosts[
				    r->n_src_exclude_hosts],
				    L7_EXC_HOST_LEN, "%s", g->hosts[k]);
				r->n_src_exclude_hosts++;
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
cidr_v6_match(const unsigned char ip[16], const unsigned char net[16],
    int prefix)
{
	int full, rem, i;
	unsigned char mask;

	if (prefix <= 0)
		return 1;
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

/* Compara host string com CIDR (v4 ou v6). Retorna 1 se casa. */
static int
cidr_matches_ip_str(const struct l7_cidr *c, const char *ip_str)
{
	uint32_t v4;
	struct in6_addr a6;

	if (!c || !ip_str || !*ip_str)
		return 0;
	if (c->family == AF_INET) {
		if (ipv4_parse(ip_str, &v4) != 0)
			return 0;
		return cidr_u32_match(v4, c->addr.v4, c->prefix);
	}
	if (c->family == AF_INET6) {
		if (inet_pton(AF_INET6, ip_str, &a6) != 1)
			return 0;
		return cidr_v6_match((const unsigned char *)&a6, c->addr.v6,
		    c->prefix);
	}
	return 0;
}

/* Igualdade de host IPv4/IPv6 (formas textuais equivalentes via inet_pton). */
static int
ip_host_equal(const char *a, const char *b)
{
	uint32_t a4, b4;
	struct in6_addr a6, b6;

	if (!a || !b)
		return 0;
	if (strcmp(a, b) == 0)
		return 1;
	if (ipv4_parse(a, &a4) == 0 && ipv4_parse(b, &b4) == 0)
		return a4 == b4;
	if (inet_pton(AF_INET6, a, &a6) == 1 &&
	    inet_pton(AF_INET6, b, &b6) == 1)
		return memcmp(&a6, &b6, 16) == 0;
	return 0;
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
	int i;

	if (!iface_list_matches(e->ifaces, e->n_ifaces, iface))
		return 0;
	if (!src_ip || !*src_ip)
		return 0;
	for (i = 0; i < e->n_hosts; i++) {
		if (ip_host_equal(src_ip, e->hosts[i]))
			return 1;
	}
	for (i = 0; i < e->n_cidrs; i++) {
		if (cidr_matches_ip_str(&e->cidrs[i], src_ip))
			return 1;
	}
	return 0;
}

static int
src_excluded_from_rule(const struct layer7_policy_rule *r, const char *src_ip)
{
	int i;

	if (r->n_src_exclude_hosts == 0 && r->n_src_exclude_cidrs == 0)
		return 0;
	if (!src_ip || !*src_ip)
		return 0;
	for (i = 0; i < r->n_src_exclude_hosts; i++) {
		if (ip_host_equal(src_ip, r->src_exclude_hosts[i]))
			return 1;
	}
	for (i = 0; i < r->n_src_exclude_cidrs; i++) {
		if (cidr_matches_ip_str(&r->src_exclude_cidrs[i], src_ip))
			return 1;
	}
	return 0;
}

static int
src_matches_rule(const struct layer7_policy_rule *r, const char *src_ip)
{
	int i;

	if (src_excluded_from_rule(r, src_ip))
		return 0;
	if (r->n_src_hosts == 0 && r->n_src_cidrs == 0)
		return 1;
	if (!src_ip || !*src_ip)
		return 0;
	for (i = 0; i < r->n_src_hosts; i++) {
		if (ip_host_equal(src_ip, r->src_hosts[i]))
			return 1;
	}
	for (i = 0; i < r->n_src_cidrs; i++) {
		if (cidr_matches_ip_str(&r->src_cidrs[i], src_ip))
			return 1;
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
    const char *host, int *app_match_out, int *cat_match_out,
    int *host_match_out)
{
	int i;
	int app_matched = 0, cat_matched = 0, host_matched = 0;

	if (app_match_out)
		*app_match_out = 0;
	if (cat_match_out)
		*cat_match_out = 0;
	if (host_match_out)
		*host_match_out = 0;

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
		cat_matched = 1;
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
	if (app_match_out)
		*app_match_out = app_matched;
	if (cat_match_out)
		*cat_match_out = cat_matched;
	if (host_match_out)
		*host_match_out = host_matched;

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
dec_clear_scoped(struct layer7_decision *dec)
{
	dec->enforce_kind = L7_ENFORCE_NONE;
	dec->policy_table_idx = -1;
	dec->scope_global = 0;
	dec->quarantine_origin = 0;
	dec->source_scoped = 0;
	dec->enforce_dst_ip[0] = '\0';
}

static enum layer7_enforce_kind
policy_enforce_kind(const struct layer7_policy_rule *r, int app_matched,
    int cat_matched, int host_matched)
{
	/* Uma politica app+host usa OR. O tipo PF deve seguir o criterio que
	 * realmente casou, nao apenas a presenca de hosts na configuracao. */
	if (host_matched)
		return L7_ENFORCE_DST_SCOPED;
	/*
	 * O bloqueio normal de app/categoria deve atingir apenas o destino do
	 * fluxo classificado. Colocar a origem em psrc corta todo o trafego
	 * externo do cliente e fica reservado à quarentena explicitamente
	 * solicitada pelo operador.
	 */
	if (app_matched || cat_matched) {
		if (r->quarantine_origin)
			return L7_ENFORCE_SRC_SCOPED;
		return L7_ENFORCE_DST_SCOPED;
	}
	if (r->quarantine_origin || r->scope_global)
		return L7_ENFORCE_SRC_SCOPED;
	return L7_ENFORCE_NONE;
}

static void
dec_set_scoped_policy(const struct layer7_policy_rule *r,
    const struct layer7_policy_rule *rules, int n_rules,
    int app_matched, int cat_matched, int host_matched,
    struct layer7_decision *dec)
{
	dec->policy_table_idx = layer7_policy_table_index(rules, n_rules, r->id);
	dec->scope_global = r->scope_global;
	dec->quarantine_origin = r->quarantine_origin;
	dec->source_scoped = r->n_src_hosts > 0 || r->n_src_cidrs > 0;
	dec->enforce_dst_ip[0] = '\0';
	dec->enforce_kind = policy_enforce_kind(r, app_matched, cat_matched,
	    host_matched);
	if (r->action == LAYER7_ACTION_BLOCK &&
	    dec->enforce_kind != L7_ENFORCE_NONE &&
	    dec->policy_table_idx >= 0) {
		char tbl[64];

		if (layer7_pf_policy_table_name(dec->enforce_kind,
		    dec->policy_table_idx, tbl, sizeof(tbl)) >= 0) {
			strncpy(dec->pf_table, tbl, sizeof(dec->pf_table) - 1);
			dec->pf_table[sizeof(dec->pf_table) - 1] = '\0';
		}
	}
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

int
layer7_policy_table_index(const struct layer7_policy_rule *rules, int n,
    const char *policy_id)
{
	int i;

	if (!rules || n <= 0 || !policy_id || !*policy_id)
		return -1;
	for (i = 0; i < n; i++) {
		if (strcmp(rules[i].id, policy_id) == 0)
			return i;
	}
	return -1;
}

int
layer7_decision_is_explicit_allow(const struct layer7_decision *dec)
{
	if (!dec || dec->action != LAYER7_ACTION_ALLOW)
		return 0;
	return dec->reason == L7_DECIDE_EXCEPTION ||
	    dec->reason == L7_DECIDE_POLICY_MATCH;
}

/*
 * Politica sem criterio positivo (hosts/apps/cats/ifaces/src) — tipicamente
 * "Monitor geral". Tem de ser fallback: se competir por priority com regras
 * especificas, sombreava bloqueios (QA D2: monitor pri=10 > youtube pri=5).
 */
static int
rule_is_catch_all(const struct layer7_policy_rule *r)
{
	if (!r)
		return 1;
	return r->n_ndpi_apps == 0 && r->n_ndpi_cats == 0 && r->n_hosts == 0 &&
	    r->n_ifaces == 0 && r->n_src_hosts == 0 && r->n_src_cidrs == 0;
}

static int
decide_try_policy_slot(const struct layer7_policy_rule *rules, int n_rules,
    int i, int global_enforce, const char *iface, const char *client_ip,
    const char *domain_or_host, const char *ndpi_app, const char *ndpi_cat,
    struct layer7_decision *dec)
{
	const struct layer7_policy_rule *r = &rules[i];
	int app_matched, cat_matched, host_matched;

	if (!r->enabled)
		return 0;
	if (!rule_matches(r, iface, client_ip, ndpi_app, ndpi_cat,
	    domain_or_host, &app_matched, &cat_matched, &host_matched))
		return 0;
	dec->action = r->action;
	dec->reason = L7_DECIDE_POLICY_MATCH;
	strncpy(dec->matched_policy_id, r->id,
	    sizeof(dec->matched_policy_id) - 1);
	dec->matched_policy_id[sizeof(dec->matched_policy_id) - 1] = '\0';
	fill_enforce(r, global_enforce, dec);
	dec_set_scoped_policy(r, rules, n_rules, app_matched, cat_matched,
	    host_matched, dec);
	return 1;
}

int
layer7_decide_for_client(const struct layer7_exception *exc, int n_exc,
    const struct layer7_policy_rule *rules, int n_rules, int global_enforce,
    const char *iface, const char *client_ip,
    const char *domain_or_host, const char *ndpi_app, const char *ndpi_cat,
    struct layer7_decision *dec)
{
	int i;

	if (!dec)
		return -1;

	memset(dec, 0, sizeof(*dec));
	dec->matched_policy_id[0] = '\0';
	dec->matched_exception_id[0] = '\0';
	dec_clear_pf(dec);
	dec_clear_scoped(dec);

	for (i = 0; i < n_exc; i++) {
		if (!exc[i].enabled)
			continue;
		if (!exception_matches_src(&exc[i], client_ip, iface))
			continue;
		dec->action = exc[i].action;
		dec->reason = L7_DECIDE_EXCEPTION;
		strncpy(dec->matched_exception_id, exc[i].id,
		    sizeof(dec->matched_exception_id) - 1);
		dec->matched_exception_id[sizeof(dec->matched_exception_id) - 1] =
		    '\0';
		fill_enforce_action(exc[i].action, global_enforce, dec);
		return 0;
	}

	/* Passagem 1: politicas especificas (ja ordenadas por priority desc). */
	for (i = 0; i < n_rules; i++) {
		if (rule_is_catch_all(&rules[i]))
			continue;
		if (decide_try_policy_slot(rules, n_rules, i, global_enforce,
		    iface, client_ip, domain_or_host, ndpi_app, ndpi_cat, dec))
			return 0;
	}
	/* Passagem 2: catch-all (ex.: Monitor geral). */
	for (i = 0; i < n_rules; i++) {
		if (!rule_is_catch_all(&rules[i]))
			continue;
		if (decide_try_policy_slot(rules, n_rules, i, global_enforce,
		    iface, client_ip, domain_or_host, ndpi_app, ndpi_cat, dec))
			return 0;
	}

	if (global_enforce) {
		dec->action = LAYER7_ACTION_ALLOW;
		dec->reason = L7_DECIDE_DEFAULT_ALLOW;
	} else {
		dec->action = LAYER7_ACTION_MONITOR;
		dec->reason = L7_DECIDE_DEFAULT_MONITOR;
	}
	dec->would_enforce_block_or_tag = 0;
	return 0;
}

void
layer7_flow_decide(const struct layer7_exception *exc, int n_exc,
    const struct layer7_policy_rule *rules, int n_rules, int global_enforce,
    const char *iface, const char *src_ip,
    const char *ndpi_app, const char *ndpi_category, const char *host,
    struct layer7_decision *dec)
{
	(void)layer7_decide_for_client(exc, n_exc, rules, n_rules, global_enforce,
	    iface, src_ip, host, ndpi_app, ndpi_category, dec);
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
