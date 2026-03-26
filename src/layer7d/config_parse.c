/*
 * Extrai "enabled" e "mode" do objeto "layer7" sem dependências externas.
 * Heurística: procura "enabled" / "mode" após "layer7" e antes de "policies"
 * (evita confundir com enabled dentro de policies[]).
 */
#include "config_parse.h"
#include <ctype.h>
#include <string.h>

static void
skip_ws(const char **p)
{
	while (**p == ' ' || **p == '\t' || **p == '\n' || **p == '\r')
		(*p)++;
}

static int
parse_bool_after_colon(const char *p, int *out)
{
	skip_ws(&p);
	if (strncmp(p, "true", 4) == 0 && !isalnum((unsigned char)p[4]) &&
	    p[4] != '_') {
		*out = 1;
		return 0;
	}
	if (strncmp(p, "false", 5) == 0 && !isalnum((unsigned char)p[5]) &&
	    p[5] != '_') {
		*out = 0;
		return 0;
	}
	return -1;
}

static int
parse_quoted_string(const char *p, char *buf, size_t buflen)
{
	size_t n = 0;

	if (*p != '"')
		return -1;
	p++;
	while (*p && *p != '"' && n + 1 < buflen) {
		if (*p == '\\' && p[1])
			p++;
		buf[n++] = *p++;
	}
	if (*p != '"')
		return -1;
	buf[n] = '\0';
	return 0;
}

static int
parse_int_after_colon(const char *p, int *out)
{
	int v = 0;

	skip_ws(&p);
	if (*p < '0' || *p > '9')
		return -1;
	while (*p >= '0' && *p <= '9') {
		if (v > 655350)
			return -1;
		v = v * 10 + (*p - '0');
		p++;
	}
	if (v < 1 || v > 65535)
		return -1;
	*out = v;
	return 0;
}

static int
parse_debug_minutes_val(const char *p, int *out)
{
	int v = 0;

	skip_ws(&p);
	if (*p < '0' || *p > '9')
		return -1;
	while (*p >= '0' && *p <= '9') {
		v = v * 10 + (*p - '0');
		if (v > 720)
			return -1;
		p++;
	}
	*out = v;
	return 0;
}

int
layer7_parse_json(const char *json, size_t len, struct layer7_parsed *out)
{
	const char *layer, *pol, *en, *mo, *ll, *sr, *srh, *srp, *dm, *end;
	size_t L;

	memset(out, 0, sizeof(*out));
	if (!json)
		return -1;
	L = len ? len : strlen(json);
	if (L == 0)
		return -1;
	end = json + L;

	layer = strstr(json, "\"layer7\"");
	if (!layer || layer >= end)
		return -1;
	out->has_layer7 = 1;

	pol = strstr(layer, "\"policies\"");
	en = strstr(layer, "\"enabled\"");
	mo = strstr(layer, "\"mode\"");
	ll = strstr(layer, "\"log_level\"");
	sr = strstr(layer, "\"syslog_remote\"");
	srh = strstr(layer, "\"syslog_remote_host\"");
	srp = strstr(layer, "\"syslog_remote_port\"");
	dm = strstr(layer, "\"debug_minutes\"");

	if (en) {
		const char *q = strchr(en + 9, ':');
		if (q && q < end) {
			q++;
			if (parse_bool_after_colon(q, &out->enabled) == 0) {
				out->has_enabled = 1;
			}
		}
	}

	if (mo) {
		const char *q = strchr(mo + 6, ':');
		if (q && q < end) {
			q++;
			skip_ws(&q);
			if (parse_quoted_string(q, out->mode, sizeof(out->mode)) ==
			    0)
				out->has_mode = 1;
		}
	}

	if (ll) {
		const char *q = strchr(ll + 11, ':');
		if (q && q < end) {
			q++;
			skip_ws(&q);
			if (parse_quoted_string(q, out->log_level,
				sizeof(out->log_level)) == 0)
				out->has_log_level = 1;
		}
	}

	if (sr && (!pol || sr < pol)) {
		const char *q = strchr(sr + 15, ':');
		if (q && q < end) {
			q++;
			if (parse_bool_after_colon(q, &out->syslog_remote) == 0)
				out->has_syslog_remote = 1;
		}
	}

	if (srh && (!pol || srh < pol)) {
		const char *q = strchr(srh + 20, ':');
		if (q && q < end) {
			q++;
			skip_ws(&q);
			if (parse_quoted_string(q, out->syslog_remote_host,
				sizeof(out->syslog_remote_host)) == 0)
				out->has_syslog_remote_host = 1;
		}
	}

	if (srp && (!pol || srp < pol)) {
		const char *q = strchr(srp + 20, ':');
		if (q && q < end) {
			q++;
			if (parse_int_after_colon(q, &out->syslog_remote_port) == 0)
				out->has_syslog_remote_port = 1;
		}
	}

	if (dm && (!pol || dm < pol)) {
		const char *q = strchr(dm + 15, ':');
		if (q && q < end) {
			q++;
			if (parse_debug_minutes_val(q, &out->debug_minutes) == 0)
				out->has_debug_minutes = 1;
		}
	}

	{
		const char *pf = strstr(layer, "\"protos_file\"");
		if (pf && (!pol || pf < pol)) {
			const char *q = strchr(pf + 13, ':');
			if (q && q < end) {
				q++;
				skip_ws(&q);
				if (parse_quoted_string(q, out->protos_file,
				    sizeof(out->protos_file)) == 0)
					out->has_protos_file = 1;
			}
		}
	}

	{
		const char *ifc = strstr(layer, "\"interfaces\"");
		if (ifc && (!pol || ifc < pol)) {
			const char *arr = strchr(ifc, '[');
			if (arr && arr < end) {
				const char *p = arr + 1;
				while (p < end && *p != ']' &&
				    out->n_interfaces < L7_MAX_INTERFACES) {
					while (p < end && (*p == ' ' ||
					    *p == '\t' || *p == '\n' ||
					    *p == '\r' || *p == ','))
						p++;
					if (*p == '"') {
						if (parse_quoted_string(p,
						    out->interfaces[out->n_interfaces],
						    L7_IFACE_NAME_LEN) == 0)
							out->n_interfaces++;
						p++;
						while (p < end && *p != '"')
							p++;
						if (*p == '"')
							p++;
					} else
						break;
				}
			}
		}
	}

	return 0;
}
