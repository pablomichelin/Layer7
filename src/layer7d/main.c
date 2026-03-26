/*
 * layer7d — config layer7; log_level filtra verbosidade; idle se enabled=false.
 */
#if HAVE_NDPI
#include "capture.h"
#include <ndpi_api.h>
#include <ndpi_main.h>
#include <ndpi_typedefs.h>
#endif
#include "blacklist.h"
#include "bl_config.h"
#include "config_parse.h"
#include "enforce.h"
#include "license.h"
#include "policy.h"
#include <errno.h>
#include <netdb.h>
#include <netinet/in.h>
#include <signal.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/stat.h>
#include <syslog.h>
#include <time.h>
#include <unistd.h>

static const char layer7d_version[] =
#include <version.str>
;

#define DEFAULT_CONFIG "/usr/local/etc/layer7.json"
#define LAYER7_LOG_PATH "/var/log/layer7d.log"
#define L7_PF_HELPER_PATH "/usr/local/libexec/layer7-pfctl"
#define L7_PF_RULES_DEBUG_PATH "/tmp/rules.debug"
#define L7_PF_SELFHEAL_MIN_SEC 10

/* 0=error 1=warn 2=info 3=debug — mensagens com nível <= s_ll */
static int s_ll = 2;

static volatile sig_atomic_t stop_req;
static volatile sig_atomic_t reload_req;
static const char *config_path = DEFAULT_CONFIG;
static int test_mode;
static int enforce_dry_run;

static struct layer7_parsed s_parsed;
static int s_have_parse;

static struct layer7_policy_rule s_rules[L7_MAX_POLICIES];
static struct layer7_exception s_exc[L7_MAX_EXCEPTIONS];
static int s_np, s_nx;
static int s_ge;
static unsigned long long s_reload_ok;
static unsigned long long s_snapshot_fail;
static unsigned long long s_sighup_count;
static unsigned long long s_sigusr1_count;
static unsigned long long s_loop_ticks;
/* Reservado pós-nDPI: adds reais às tabelas PF */
static unsigned long long s_pf_table_add_ok;
static unsigned long long s_pf_table_add_fail;
static unsigned long long s_pf_dst_add_ok;
static unsigned long long s_pf_dst_add_fail;
static volatile sig_atomic_t usr1_req;

static unsigned long long s_total_classified;
static unsigned long long s_total_blocked;
static unsigned long long s_total_allowed;
static time_t s_boot_time;

static struct l7_license_info s_lic;
static time_t s_last_lic_check;

static struct l7_blacklist *s_blacklist;
static unsigned long long s_bl_hits;
static unsigned long long s_bl_lookups;
static struct l7_bl_rule s_bl_rules[L7_BL_MAX_RULES];
static int s_bl_n_rules;
static time_t s_last_pf_selfheal;
#define L7_LIC_CHECK_INTERVAL 3600

#define L7_STATS_TOP_MAX 128

struct l7_counter {
	char key[64];
	unsigned long long count;
};

static struct l7_counter s_app_blocks[L7_STATS_TOP_MAX];
static int s_n_app_blocks;
static struct l7_counter s_src_blocks[L7_STATS_TOP_MAX];
static int s_n_src_blocks;

static void
stats_increment(struct l7_counter *arr, int *n, int max, const char *key)
{
	int i;
	if (!key || key[0] == '\0')
		return;
	for (i = 0; i < *n; i++) {
		if (strcmp(arr[i].key, key) == 0) {
			arr[i].count++;
			return;
		}
	}
	if (*n < max) {
		strncpy(arr[*n].key, key, sizeof(arr[0].key) - 1);
		arr[*n].key[sizeof(arr[0].key) - 1] = '\0';
		arr[*n].count = 1;
		(*n)++;
	}
}

static int
counter_cmp_desc(const void *a, const void *b)
{
	const struct l7_counter *ca = (const struct l7_counter *)a;
	const struct l7_counter *cb = (const struct l7_counter *)b;
	if (cb->count > ca->count) return 1;
	if (cb->count < ca->count) return -1;
	return 0;
}

#define L7_STATS_JSON_PATH "/tmp/layer7-stats.json"

static void json_escape_fprint(FILE *f, const char *s);

static void
write_stats_json(void)
{
	FILE *f;
	int i, limit;
	time_t now = time(NULL);

	f = fopen(L7_STATS_JSON_PATH ".tmp", "w");
	if (!f)
		return;

	fprintf(f, "{\n");
	fprintf(f, "  \"version\": \"%s\",\n", layer7d_version);
	fprintf(f, "  \"boot_time\": %lld,\n", (long long)s_boot_time);
	fprintf(f, "  \"uptime_seconds\": %lld,\n",
	    (long long)(now - s_boot_time));
	fprintf(f, "  \"timestamp\": %lld,\n", (long long)now);
	fprintf(f, "  \"total_classified\": %llu,\n",
	    (unsigned long long)s_total_classified);
	fprintf(f, "  \"total_blocked\": %llu,\n",
	    (unsigned long long)s_total_blocked);
	fprintf(f, "  \"total_allowed\": %llu,\n",
	    (unsigned long long)s_total_allowed);
	fprintf(f, "  \"policies_active\": %d,\n", s_np);
	fprintf(f, "  \"exceptions\": %d,\n", s_nx);
	fprintf(f, "  \"enforce_mode\": %d,\n", s_ge);
	fprintf(f, "  \"pf_add_ok\": %llu,\n",
	    (unsigned long long)s_pf_table_add_ok);
	fprintf(f, "  \"pf_add_fail\": %llu,\n",
	    (unsigned long long)s_pf_table_add_fail);
	fprintf(f, "  \"dst_add_ok\": %llu,\n",
	    (unsigned long long)s_pf_dst_add_ok);
	fprintf(f, "  \"dst_add_fail\": %llu,\n",
	    (unsigned long long)s_pf_dst_add_fail);

	qsort(s_app_blocks, s_n_app_blocks, sizeof(s_app_blocks[0]),
	    counter_cmp_desc);
	fprintf(f, "  \"top_apps_blocked\": [");
	limit = s_n_app_blocks < 10 ? s_n_app_blocks : 10;
	for (i = 0; i < limit; i++) {
		fprintf(f, "%s\n    {\"app\": \"", i > 0 ? "," : "");
		json_escape_fprint(f, s_app_blocks[i].key);
		fprintf(f, "\", \"count\": %llu}",
		    (unsigned long long)s_app_blocks[i].count);
	}
	fprintf(f, "%s],\n", limit > 0 ? "\n  " : "");

	qsort(s_src_blocks, s_n_src_blocks, sizeof(s_src_blocks[0]),
	    counter_cmp_desc);
	fprintf(f, "  \"top_sources_blocked\": [");
	limit = s_n_src_blocks < 10 ? s_n_src_blocks : 10;
	for (i = 0; i < limit; i++) {
		fprintf(f, "%s\n    {\"ip\": \"", i > 0 ? "," : "");
		json_escape_fprint(f, s_src_blocks[i].key);
		fprintf(f, "\", \"count\": %llu}",
		    (unsigned long long)s_src_blocks[i].count);
	}
	fprintf(f, "%s],\n", limit > 0 ? "\n  " : "");

	fprintf(f, "  \"license_valid\": %s,\n",
	    s_lic.valid ? "true" : "false");
	fprintf(f, "  \"license_expired\": %s,\n",
	    s_lic.expired ? "true" : "false");
	fprintf(f, "  \"license_grace\": %s,\n",
	    s_lic.grace ? "true" : "false");
	fprintf(f, "  \"license_dev_mode\": %s,\n",
	    s_lic.dev_mode ? "true" : "false");
	fprintf(f, "  \"license_days_left\": %d,\n", s_lic.days_left);
	fprintf(f, "  \"license_customer\": \"");
	json_escape_fprint(f, s_lic.customer);
	fprintf(f, "\",\n");
	fprintf(f, "  \"license_expiry\": \"");
	json_escape_fprint(f, s_lic.expiry);
	fprintf(f, "\",\n");
	fprintf(f, "  \"license_hardware_id\": \"");
	json_escape_fprint(f, s_lic.hardware_id);
	fprintf(f, "\",\n");
	fprintf(f, "  \"license_error\": \"");
	json_escape_fprint(f, s_lic.error);
	fprintf(f, "\",\n");

	fprintf(f, "  \"bl_enabled\": %s,\n",
	    s_bl_n_rules > 0 ? "true" : "false");
	fprintf(f, "  \"bl_domains_loaded\": %d,\n",
	    s_blacklist ? l7_blacklist_count(s_blacklist) : 0);
	fprintf(f, "  \"bl_categories_active\": %d,\n",
	    s_blacklist ? l7_blacklist_cat_count(s_blacklist) : 0);
	fprintf(f, "  \"bl_lookups\": %llu,\n",
	    (unsigned long long)s_bl_lookups);
	fprintf(f, "  \"bl_hits\": %llu,\n",
	    (unsigned long long)s_bl_hits);
	fprintf(f, "  \"bl_rules_active\": %d,\n", s_bl_n_rules);

	{
		const char **bl_cat_names = NULL;
		const unsigned long long *bl_cat_hits = NULL;
		int bl_n_cats = 0, bli;

		if (s_blacklist)
			l7_blacklist_get_cat_hits(s_blacklist,
			    &bl_cat_names, &bl_cat_hits, &bl_n_cats);

		fprintf(f, "  \"bl_top_categories\": [");
		for (bli = 0; bli < bl_n_cats && bli < 10; bli++) {
			fprintf(f, "%s\n    {\"cat\": \"",
			    bli > 0 ? "," : "");
			json_escape_fprint(f, bl_cat_names[bli]);
			fprintf(f, "\", \"hits\": %llu}",
			    (unsigned long long)bl_cat_hits[bli]);
		}
		fprintf(f, "%s]\n",
		    bl_n_cats > 0 ? "\n  " : "");
	}

	fprintf(f, "}\n");
	fclose(f);
	rename(L7_STATS_JSON_PATH ".tmp", L7_STATS_JSON_PATH);
}

static char s_remote_host[256];
static int s_syslog_remote;
static int s_remote_port = 514;
static time_t s_debug_until;

static void
json_escape_print(const char *s)
{
	for (; *s; s++) {
		if (*s == '"' || *s == '\\')
			putchar('\\');
		putchar(*s);
	}
}

static void
json_escape_fprint(FILE *f, const char *s)
{
	for (; *s; s++) {
		if (*s == '"' || *s == '\\')
			fputc('\\', f);
		fputc(*s, f);
	}
}

#if HAVE_NDPI

static int
list_ndpi_protos(void)
{
	struct ndpi_detection_module_struct *ndpi;
	int i, j, n, first;

	ndpi = ndpi_init_detection_module(NULL);
	if (!ndpi) {
		fprintf(stderr, "layer7d: ndpi_init failed\n");
		return 1;
	}
	if (ndpi_finalize_initialization(ndpi) != 0) {
		ndpi_exit_detection_module(ndpi);
		fprintf(stderr, "layer7d: ndpi_finalize failed\n");
		return 1;
	}

	n = (int)ndpi_get_num_protocols(ndpi);
	printf("{\"protocols\":[");
	first = 1;
	for (i = 0; i < n; i++) {
		const char *name = ndpi_get_proto_name(ndpi, (uint16_t)i);
		if (!name || name[0] == '\0')
			continue;
		if (strcmp(name, "Unknown") == 0)
			continue;
		if (!first)
			printf(",");
		first = 0;
		printf("\"");
		json_escape_print(name);
		printf("\"");
	}
	printf("],\"categories\":[");
	first = 1;
	for (i = 0; i < (int)NDPI_PROTOCOL_NUM_CATEGORIES; i++) {
		const char *cn = ndpi_category_get_name(ndpi,
		    (ndpi_protocol_category_t)i);
		if (!cn || cn[0] == '\0')
			continue;
		if (!first)
			printf(",");
		first = 0;
		printf("\"");
		json_escape_print(cn);
		printf("\"");
	}

	printf("],\"protocols_by_category\":{");
	first = 1;
	for (i = 0; i < (int)NDPI_PROTOCOL_NUM_CATEGORIES; i++) {
		const char *cn = ndpi_category_get_name(ndpi,
		    (ndpi_protocol_category_t)i);
		if (!cn || cn[0] == '\0')
			continue;
		int pfirst = 1, any = 0;
		for (j = 0; j < n; j++) {
			ndpi_protocol ptmp;
			const char *pn;
			memset(&ptmp, 0, sizeof(ptmp));
			ptmp.proto.app_protocol = (uint16_t)j;
			if ((int)ndpi_get_proto_category(ndpi, ptmp) != i)
				continue;
			pn = ndpi_get_proto_name(ndpi, (uint16_t)j);
			if (!pn || pn[0] == '\0' || strcmp(pn, "Unknown") == 0)
				continue;
			any = 1;
			break;
		}
		if (!any)
			continue;
		if (!first)
			printf(",");
		first = 0;
		printf("\"");
		json_escape_print(cn);
		printf("\":[");
		for (j = 0; j < n; j++) {
			ndpi_protocol ptmp;
			const char *pn;
			memset(&ptmp, 0, sizeof(ptmp));
			ptmp.proto.app_protocol = (uint16_t)j;
			if ((int)ndpi_get_proto_category(ndpi, ptmp) != i)
				continue;
			pn = ndpi_get_proto_name(ndpi, (uint16_t)j);
			if (!pn || pn[0] == '\0' || strcmp(pn, "Unknown") == 0)
				continue;
			if (!pfirst)
				printf(",");
			pfirst = 0;
			printf("\"");
			json_escape_print(pn);
			printf("\"");
		}
		printf("]");
	}
	printf("}}\n");

	ndpi_exit_detection_module(ndpi);
	return 0;
}

#define L7_MAX_IFACES 8
static struct layer7_capture *s_captures[L7_MAX_IFACES];
static int s_n_captures;
static unsigned long long s_cap_pkts;
static unsigned long long s_cap_flows_classified;
static unsigned long long s_cap_flows_expired;
#endif

static char *read_file(const char *path, size_t *out_len);
static int cfg_disabled(const struct layer7_parsed *p);

static int
effective_ll(void)
{
	time_t now;

	if (s_debug_until == (time_t)0)
		return s_ll;
	now = time(NULL);
	if (now < s_debug_until)
		return 3;
	s_debug_until = 0;
	return s_ll;
}

static void
sync_remote_cfg(const struct layer7_parsed *p)
{
	s_syslog_remote = 0;
	s_remote_host[0] = '\0';
	s_remote_port = 514;
	if (!p->has_syslog_remote || !p->syslog_remote)
		return;
	if (!p->has_syslog_remote_host || p->syslog_remote_host[0] == '\0')
		return;
	strncpy(s_remote_host, p->syslog_remote_host, sizeof(s_remote_host) - 1);
	s_remote_host[sizeof(s_remote_host) - 1] = '\0';
	if (p->has_syslog_remote_port && p->syslog_remote_port >= 1 &&
	    p->syslog_remote_port <= 65535)
		s_remote_port = p->syslog_remote_port;
	s_syslog_remote = 1;
}

static void
layer7_send_remote_syslog(int pri, const char *msg)
{
	static const char *const mon[] = { "Jan", "Feb", "Mar", "Apr", "May",
	    "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec" };
	struct addrinfo hints, *res = NULL;
	char portstr[8], buf[1200], hostn[64], rmsg[1024];
	struct tm tm;
	time_t t;
	int s, n;
	size_t i;

	if (!s_syslog_remote || s_remote_host[0] == '\0' || !msg)
		return;
	snprintf(rmsg, sizeof(rmsg), "%s", msg);
	for (i = 0; rmsg[i]; i++) {
		if (rmsg[i] == '\n' || rmsg[i] == '\r')
			rmsg[i] = ' ';
	}
	snprintf(portstr, sizeof(portstr), "%d", s_remote_port);
	memset(&hints, 0, sizeof(hints));
	hints.ai_socktype = SOCK_DGRAM;
	hints.ai_family = AF_UNSPEC;
	if (getaddrinfo(s_remote_host, portstr, &hints, &res) != 0 || !res)
		return;
	s = socket(res->ai_family, res->ai_socktype, res->ai_protocol);
	if (s < 0) {
		freeaddrinfo(res);
		return;
	}
	t = time(NULL);
	localtime_r(&t, &tm);
	hostn[0] = '\0';
	if (gethostname(hostn, sizeof(hostn)) != 0 || hostn[0] == '\0') {
		strncpy(hostn, "layer7", sizeof(hostn) - 1);
		hostn[sizeof(hostn) - 1] = '\0';
	}
	n = snprintf(buf, sizeof(buf),
	    "<%d>%.3s %2d %02d:%02d:%02d %s layer7d: %s", pri & 0x1ff,
	    mon[tm.tm_mon % 12], tm.tm_mday, tm.tm_hour, tm.tm_min, tm.tm_sec,
	    hostn, rmsg);
	if (n > 0 && n < (int)sizeof(buf))
		(void)sendto(s, buf, (size_t)n, 0, res->ai_addr, res->ai_addrlen);
	close(s);
	freeaddrinfo(res);
}

static void
layer7_write_local_log(int pri, const char *msg)
{
	FILE *f;
	time_t now;
	struct tm tm;
	char ts[32];
	const char *sev;

	if (!msg || msg[0] == '\0')
		return;

	switch (pri & LOG_PRIMASK) {
	case LOG_ERR:
		sev = "error";
		break;
	case LOG_WARNING:
		sev = "warn";
		break;
	case LOG_NOTICE:
		sev = "notice";
		break;
	case LOG_INFO:
		sev = "info";
		break;
	case LOG_DEBUG:
		sev = "debug";
		break;
	default:
		sev = "log";
		break;
	}

	now = time(NULL);
	localtime_r(&now, &tm);
	strftime(ts, sizeof(ts), "%Y-%m-%d %H:%M:%S", &tm);

	f = fopen(LAYER7_LOG_PATH, "a");
	if (!f)
		return;
	fprintf(f, "%s [%s] %s\n", ts, sev, msg);
	fclose(f);
}

static void
l7_log(int pri, const char *fmt, ...)
{
	va_list ap;
	char line[1024];

	va_start(ap, fmt);
	vsnprintf(line, sizeof(line), fmt, ap);
	va_end(ap);
	layer7_write_local_log(pri, line);
	syslog(pri, "%s", line);
	if (s_syslog_remote)
		layer7_send_remote_syslog(pri, line);
}

#define L7_PRI_FAC (LOG_DAEMON)
#define L7_ERR(...) l7_log(L7_PRI_FAC | LOG_ERR, __VA_ARGS__)
#define L7_WARN(...)                                                           \
	do {                                                                   \
		if (effective_ll() >= 1)                                       \
			l7_log(L7_PRI_FAC | LOG_WARNING, __VA_ARGS__);         \
	} while (0)
#define L7_NOTE(...)                                                           \
	do {                                                                   \
		if (effective_ll() >= 2)                                       \
			l7_log(L7_PRI_FAC | LOG_NOTICE, __VA_ARGS__);          \
	} while (0)
#define L7_INFO(...)                                                           \
	do {                                                                   \
		if (effective_ll() >= 2)                                       \
			l7_log(L7_PRI_FAC | LOG_INFO, __VA_ARGS__);           \
	} while (0)
#define L7_DBG(...)                                                            \
	do {                                                                   \
		if (effective_ll() >= 3)                                       \
			l7_log(L7_PRI_FAC | LOG_DEBUG, __VA_ARGS__);           \
	} while (0)

static void
on_usr1(int sig)
{
	(void)sig;
	usr1_req = 1;
}

#define L7_DST_CACHE_MAX 2048
#define L7_DST_TTL_MIN   300
#define L7_DST_SWEEP_SEC  60

struct l7_dst_entry {
	char     ip[48];
	time_t   expires;
};
static struct l7_dst_entry s_dst_cache[L7_DST_CACHE_MAX];
static int s_n_dst;
static time_t s_last_dst_sweep;

static void
dst_cache_add(const char *ip, uint32_t ttl)
{
	int i;
	time_t expires;
	uint32_t eff_ttl = ttl < L7_DST_TTL_MIN ? L7_DST_TTL_MIN : ttl;

	expires = time(NULL) + (time_t)eff_ttl;

	for (i = 0; i < s_n_dst; i++) {
		if (strcmp(s_dst_cache[i].ip, ip) == 0) {
			if (expires > s_dst_cache[i].expires)
				s_dst_cache[i].expires = expires;
			return;
		}
	}
	if (s_n_dst >= L7_DST_CACHE_MAX) {
		time_t now = time(NULL);
		int oldest = 0;
		for (i = 1; i < s_n_dst; i++) {
			if (s_dst_cache[i].expires < s_dst_cache[oldest].expires)
				oldest = i;
		}
		if (s_dst_cache[oldest].expires > now)
			return;
		layer7_pf_exec_table_delete(L7_PF_TABLE_BLOCK_DST,
		    s_dst_cache[oldest].ip);
		s_dst_cache[oldest] = s_dst_cache[--s_n_dst];
	}
	snprintf(s_dst_cache[s_n_dst].ip, sizeof(s_dst_cache[0].ip), "%s", ip);
	s_dst_cache[s_n_dst].expires = expires;
	s_n_dst++;
}

static void
dst_cache_sweep(void)
{
	time_t now = time(NULL);
	int i;

	if (now - s_last_dst_sweep < L7_DST_SWEEP_SEC)
		return;
	s_last_dst_sweep = now;

	for (i = 0; i < s_n_dst; ) {
		if (s_dst_cache[i].expires < now) {
			layer7_pf_exec_table_delete(L7_PF_TABLE_BLOCK_DST,
			    s_dst_cache[i].ip);
			s_dst_cache[i] = s_dst_cache[--s_n_dst];
		} else
			i++;
	}
}

static void
dst_cache_flush(void)
{
	int i;

	for (i = 0; i < s_n_dst; i++) {
		layer7_pf_exec_table_delete(L7_PF_TABLE_BLOCK_DST,
		    s_dst_cache[i].ip);
	}
	s_n_dst = 0;
}

static int
bl_rule_has_cat(const struct l7_bl_rule *rule, const char *cat)
{
	int i;
	for (i = 0; i < rule->n_categories; i++) {
		if (strcmp(rule->categories[i], cat) == 0)
			return 1;
	}
	return 0;
}

static int
run_shell_cmd_ok(const char *cmd)
{
	int rc;

	if (!cmd || !*cmd)
		return 0;
	rc = system(cmd);
	return rc == 0;
}

static int
pf_table_exists(const char *table)
{
	char cmd[192];

	if (!table || !*table)
		return 0;
	snprintf(cmd, sizeof(cmd),
	    "/sbin/pfctl -s Tables 2>/dev/null | /usr/bin/grep -qw %s",
	    table);
	return run_shell_cmd_ok(cmd);
}

static int
pf_base_tables_ok(void)
{
	if (!pf_table_exists(L7_PF_TABLE_BLOCK))
		return 0;
	if (!pf_table_exists(L7_PF_TABLE_BLOCK_DST))
		return 0;
	return 1;
}

static int
layer7_pf_selfheal(const char *reason)
{
	time_t now;
	int have_base;
	int did_force = 0;

	now = time(NULL);
	if (s_last_pf_selfheal != 0 &&
	    now - s_last_pf_selfheal < L7_PF_SELFHEAL_MIN_SEC) {
		L7_WARN("pf_selfheal: throttled reason=%s last=%lld",
		    reason ? reason : "-", (long long)s_last_pf_selfheal);
		return 0;
	}
	s_last_pf_selfheal = now;

	L7_WARN("pf_selfheal: start reason=%s", reason ? reason : "-");
	if (!run_shell_cmd_ok(L7_PF_HELPER_PATH " ensure >/dev/null 2>&1")) {
		L7_WARN("pf_selfheal: helper ensure failed");
	}

	have_base = pf_base_tables_ok();
	if (!have_base && access(L7_PF_RULES_DEBUG_PATH, R_OK) == 0) {
		did_force = run_shell_cmd_ok(
		    "/sbin/pfctl -f " L7_PF_RULES_DEBUG_PATH " >/dev/null 2>&1");
	}

	have_base = pf_base_tables_ok();
	if (have_base) {
		L7_NOTE("pf_selfheal: success reason=%s fallback=%d",
		    reason ? reason : "-", did_force ? 1 : 0);
		return 1;
	}
	L7_WARN("pf_selfheal: failed reason=%s fallback=%d",
	    reason ? reason : "-", did_force ? 1 : 0);
	return 0;
}

static int
layer7_pf_add_with_selfheal(const char *table, const char *ip,
    const char *reason)
{
	int r;

	r = layer7_pf_exec_table_add(table, ip);
	if (r == 0)
		return 0;
	if (!layer7_pf_selfheal(reason))
		return -1;
	r = layer7_pf_exec_table_add(table, ip);
	if (r == 0)
		return 0;
	return -1;
}

static void
bl_flush_rule_tables(void)
{
	char cmd[128];
	int i;
	for (i = 0; i < L7_BL_MAX_RULES; i++) {
		snprintf(cmd, sizeof(cmd),
		    "/sbin/pfctl -t layer7_bld_%d -T flush 2>/dev/null", i);
		(void)system(cmd);
	}
}

static void
layer7_on_dns_resolved(const char *iface, const char *domain,
    const char *resolved_ip, uint32_t ttl)
{
	int r;
	const char *bl_cat;

	if (!s_have_parse || !s_ge)
		return;

	if (layer7_domain_is_blocked(s_rules, s_np, domain)) {
		r = layer7_pf_add_with_selfheal(L7_PF_TABLE_BLOCK_DST,
		    resolved_ip, "dns_block_dst");
		if (r == 0) {
			s_pf_dst_add_ok++;
			dst_cache_add(resolved_ip, ttl);
			L7_INFO("dns_block: iface=%s domain=%s ip=%s ttl=%u table=%s",
			    iface ? iface : "-", domain, resolved_ip, ttl,
			    L7_PF_TABLE_BLOCK_DST);
		} else {
			s_pf_dst_add_fail++;
			L7_WARN("dns_block: pfctl add failed iface=%s domain=%s ip=%s",
			    iface ? iface : "-", domain, resolved_ip);
		}
		return;
	}

	if (s_blacklist && s_bl_n_rules > 0) {
		int ri;
		s_bl_lookups++;
		bl_cat = l7_blacklist_lookup(s_blacklist, domain);
		if (bl_cat) {
			s_bl_hits++;
			for (ri = 0; ri < s_bl_n_rules; ri++) {
				char tbl[32];
				if (!s_bl_rules[ri].enabled)
					continue;
				if (!bl_rule_has_cat(&s_bl_rules[ri],
				    bl_cat))
					continue;
				snprintf(tbl, sizeof(tbl),
				    "layer7_bld_%d", ri);
				r = layer7_pf_add_with_selfheal(tbl,
				    resolved_ip, "dns_blacklist_rule");
				if (r == 0) {
					s_pf_dst_add_ok++;
					L7_INFO("bl_block: iface=%s domain=%s "
					    "cat=%s ip=%s rule=%d/%s "
					    "table=%s", iface ? iface : "-",
					    domain, bl_cat,
					    resolved_ip, ri,
					    s_bl_rules[ri].name, tbl);
				} else {
					s_pf_dst_add_fail++;
				}
			}
		}
	}
}

static void
layer7_on_dns_query(const char *iface, const char *src_ip,
    const char *resolver_ip, const char *qname)
{
	if (!src_ip || !qname || qname[0] == '\0')
		return;
	if (strstr(qname, ".in-addr.arpa") || strstr(qname, ".ip6.arpa"))
		return;

	L7_INFO("dns_query: iface=%s src=%s resolver=%s qname=%s",
	    iface ? iface : "-", src_ip, resolver_ip ? resolver_ip : "-",
	    qname);
}

/*
 * Chamado pelo loop quando nDPI classificar um fluxo (origem + app/cat).
 * mode=enforce + decisão block → dst_ip em layer7_block_dst.
 * mode=enforce + decisão tag  → src_ip em layer7_tagged.
 * mode=monitor → apenas loga a decisão, nunca chama pfctl.
 */
static void
layer7_on_classified_flow(const char *iface, const char *src_ip,
    const char *dst_ip, const char *ndpi_app, const char *ndpi_cat,
    const char *host)
{
	struct layer7_decision dec;
	int r;

	if (!s_have_parse || !src_ip || cfg_disabled(&s_parsed))
		return;
	layer7_flow_decide(s_exc, s_nx, s_rules, s_np, s_ge, iface, src_ip,
	    ndpi_app, ndpi_cat, host, &dec);

	s_total_classified++;
	if (dec.action == LAYER7_ACTION_BLOCK) {
		s_total_blocked++;
		stats_increment(s_app_blocks, &s_n_app_blocks,
		    L7_STATS_TOP_MAX, ndpi_app);
		stats_increment(s_src_blocks, &s_n_src_blocks,
		    L7_STATS_TOP_MAX, src_ip);
	} else {
		s_total_allowed++;
	}

	if (dec.action == LAYER7_ACTION_BLOCK ||
	    dec.action == LAYER7_ACTION_TAG) {
		L7_NOTE("flow_decide: iface=%s src=%s dst=%s host=%s app=%s cat=%s action=%s "
		    "reason=%s policy=%s",
		    iface ? iface : "-",
		    src_ip, dst_ip ? dst_ip : "-",
		    host ? host : "-",
		    ndpi_app ? ndpi_app : "(null)",
		    ndpi_cat ? ndpi_cat : "(null)",
		    layer7_action_str(dec.action),
		    layer7_decide_reason_str(dec.reason),
		    dec.matched_policy_id[0] ? dec.matched_policy_id : "-");
	} else {
		L7_DBG("flow_decide: iface=%s src=%s dst=%s host=%s app=%s cat=%s action=%s "
		    "reason=%s",
		    iface ? iface : "-",
		    src_ip, dst_ip ? dst_ip : "-",
		    host ? host : "-",
		    ndpi_app ? ndpi_app : "(null)",
		    ndpi_cat ? ndpi_cat : "(null)",
		    layer7_action_str(dec.action),
		    layer7_decide_reason_str(dec.reason));
	}

	if (!s_ge)
		return;

	if (dec.action == LAYER7_ACTION_BLOCK && dst_ip &&
	    layer7_pf_ipv4_host_ok(dst_ip)) {
		r = layer7_pf_add_with_selfheal(L7_PF_TABLE_BLOCK_DST, dst_ip,
		    "flow_block_dst");
		if (r == 0) {
			s_pf_dst_add_ok++;
			dst_cache_add(dst_ip, L7_DST_TTL_MIN);
			L7_INFO("enforce_block_dst: iface=%s dst=%s policy=%s",
			    iface ? iface : "-", dst_ip,
			    dec.matched_policy_id[0] ?
			    dec.matched_policy_id : "-");
		} else if (r == -1) {
			s_pf_dst_add_fail++;
			L7_WARN("pfctl add failed table=%s ip=%s",
			    L7_PF_TABLE_BLOCK_DST, dst_ip);
		}
	} else if (dec.action == LAYER7_ACTION_TAG) {
		r = -1;
		if (dec.would_enforce_block_or_tag && dec.pf_table[0] &&
		    layer7_pf_ipv4_host_ok(src_ip)) {
			if (layer7_pf_add_with_selfheal(dec.pf_table, src_ip,
			    "flow_tag_src") == 0)
				r = 1;
		}
		if (r == 1) {
			s_pf_table_add_ok++;
			L7_INFO("enforce_tag: iface=%s src=%s table=%s policy=%s",
			    iface ? iface : "-", src_ip, dec.pf_table,
			    dec.matched_policy_id[0] ?
			    dec.matched_policy_id : "-");
		} else if (r == -1) {
			s_pf_table_add_fail++;
			L7_WARN("pfctl add failed table=%s ip=%s",
			    dec.pf_table, src_ip);
		}
	}
}

static int
run_enforce_once_cli(const char *path, const char *ip, const char *app,
    const char *cat, int dry)
{
	struct layer7_parsed p;
	struct layer7_policy_rule rules[L7_MAX_POLICIES];
	struct layer7_exception exc[L7_MAX_EXCEPTIONS];
	int np = 0, nx = 0, ge;
	char *buf;
	size_t len;
	struct layer7_decision dec;
	int r;

	buf = read_file(path, &len);
	if (!buf) {
		fprintf(stderr, "layer7d: cannot read %s: %s\n", path,
		    strerror(errno));
		return 1;
	}
	memset(&p, 0, sizeof(p));
	layer7_parse_json(buf, len, &p);
	if (layer7_policies_parse(buf, len, rules, &np, L7_MAX_POLICIES) != 0) {
		fprintf(stderr, "layer7d: policies parse error (%s)\n", path);
		free(buf);
		return 1;
	}
	if (layer7_exceptions_parse(buf, len, exc, &nx, L7_MAX_EXCEPTIONS) !=
	    0) {
		fprintf(stderr, "layer7d: exceptions parse error (%s)\n", path);
		free(buf);
		return 1;
	}
	{
		struct layer7_group grps[L7_MAX_GROUPS];
		int ng = 0;
		(void)layer7_groups_parse(buf, len, grps, &ng, L7_MAX_GROUPS);
		layer7_policies_expand_groups(rules, np, grps, ng);
	}
	layer7_policies_sort(rules, np);
	layer7_exceptions_sort(exc, nx);
	ge = p.has_mode && strcmp(p.mode, "enforce") == 0;
	free(buf);

	layer7_flow_decide(exc, nx, rules, np, ge, NULL, ip, app, cat, NULL,
	    &dec);
	printf(
	    "enforce-once: action=%s reason=%s would_enforce=%d table=%s\n",
	    layer7_action_str(dec.action),
	    layer7_decide_reason_str(dec.reason), dec.would_enforce_block_or_tag,
	    dec.pf_table[0] ? dec.pf_table : "(none)");

	r = layer7_pf_enforce_decision(&dec, ip, dry);
	if (r == -1) {
		fprintf(stderr, "layer7d: pfctl add failed (table=%s ip=%s)\n",
		    dec.pf_table, ip);
		return 1;
	}
	if (dec.would_enforce_block_or_tag && dec.pf_table[0] &&
	    layer7_pf_ipv4_host_ok(ip)) {
		if (dry)
			printf("dry-run: pfctl -t %s -T add %s\n", dec.pf_table,
			    ip);
		else if (r == 1)
			printf("pfctl add ok: table=%s ip=%s\n", dec.pf_table,
			    ip);
	} else
		printf("no pf table add (monitor/allow or mode!=enforce)\n");
	return 0;
}

static void
set_ll_from_parsed(const struct layer7_parsed *p)
{
	if (!p->has_log_level) {
		s_ll = 2;
		return;
	}
	if (strcmp(p->log_level, "error") == 0)
		s_ll = 0;
	else if (strcmp(p->log_level, "warn") == 0)
		s_ll = 1;
	else if (strcmp(p->log_level, "info") == 0)
		s_ll = 2;
	else if (strcmp(p->log_level, "debug") == 0)
		s_ll = 3;
	else
		s_ll = 2;
}

static void on_signal(int sig)
{
	(void)sig;
	stop_req = 1;
}

static void on_hup(int sig)
{
	(void)sig;
	reload_req = 1;
}

static void usage(void)
{
	fprintf(stderr,
	    "usage: layer7d [-V] [-t] [-c path] [-e IP APP [CAT]] [-n] "
	    "[--list-protos]\n"
	    "               [--fingerprint] [--activate KEY [URL]]\n"
	    "  -V               versão do binário\n"
	    "  -t               testa JSON (stdout)\n"
	    "  -c path          caminho (omissão: %s)\n"
	    "  -e IP APP [CAT]  uma decisão + opcional pfctl add\n"
	    "  -n               com -e: não executar pfctl (dry)\n"
	    "  --list-protos    lista protocolos e categorias nDPI em JSON\n"
	    "  --fingerprint    mostra o hardware ID desta máquina\n"
	    "  --activate KEY   activa licença online (KEY + URL opcional)\n"
	    "  runtime: SIGHUP reload; SIGUSR1 stats; nDPI→pf via policy\n",
	    DEFAULT_CONFIG);
}

static char *
read_file(const char *path, size_t *out_len)
{
	FILE *f;
	char *buf;
	long sz;

	f = fopen(path, "rb");
	if (!f)
		return NULL;
	if (fseek(f, 0, SEEK_END) != 0) {
		fclose(f);
		return NULL;
	}
	sz = ftell(f);
	if (sz < 0 || sz > (long)(8 * 1024 * 1024)) {
		fclose(f);
		return NULL;
	}
	rewind(f);
	buf = malloc((size_t)sz + 1);
	if (!buf) {
		fclose(f);
		return NULL;
	}
	if (fread(buf, 1, (size_t)sz, f) != (size_t)sz) {
		free(buf);
		fclose(f);
		return NULL;
	}
	buf[sz] = '\0';
	fclose(f);
	*out_len = (size_t)sz;
	return buf;
}

static int
cfg_disabled(const struct layer7_parsed *p)
{
	return p->has_enabled && !p->enabled;
}

static void
refresh_enforce_cfg(void)
{
	int ge = 0;

	if (s_have_parse && !cfg_disabled(&s_parsed) &&
	    s_parsed.has_mode && strcmp(s_parsed.mode, "enforce") == 0)
		ge = 1;
	if (ge && !s_lic.valid)
		ge = 0;
	s_ge = ge;
}

static int
apply_config(int use_syslog)
{
	struct layer7_parsed p;
	char *buf;
	size_t len;
	int pe_loaded = 0;

	buf = read_file(config_path, &len);
	if (!buf) {
		if (test_mode) {
			fprintf(stderr, "layer7d: cannot read %s: %s\n",
			    config_path, strerror(errno));
			return 1;
		}
		if (use_syslog)
			L7_WARN("config read failed: %s (%s)", config_path,
			    strerror(errno));
		s_have_parse = 0;
		return 1;
	}

	memset(&p, 0, sizeof(p));
	layer7_parse_json(buf, len, &p);
	sync_remote_cfg(&p);
	if (use_syslog && p.has_syslog_remote && p.syslog_remote &&
	    (!p.has_syslog_remote_host || p.syslog_remote_host[0] == '\0'))
		L7_WARN(
		    "syslog_remote=true but syslog_remote_host empty — remote "
		    "log disabled");

	if (test_mode) {
		struct layer7_policy_rule rules[L7_MAX_POLICIES];
		int np = 0;
		int k;

		if (!p.has_layer7) {
			fprintf(stderr, "layer7d: no \"layer7\" key in JSON\n");
			free(buf);
			return 1;
		}
		struct layer7_exception exc[L7_MAX_EXCEPTIONS];
		int nx = 0;

		if (layer7_policies_parse(buf, len, rules, &np,
			L7_MAX_POLICIES) != 0) {
			fprintf(stderr, "layer7d: policies parse error\n");
			free(buf);
			return 1;
		}
		if (layer7_exceptions_parse(buf, len, exc, &nx,
			L7_MAX_EXCEPTIONS) != 0) {
			fprintf(stderr, "layer7d: exceptions parse error\n");
			free(buf);
			return 1;
		}
		{
			struct layer7_group grps[L7_MAX_GROUPS];
			int ng = 0;
			(void)layer7_groups_parse(buf, len, grps, &ng,
			    L7_MAX_GROUPS);
			layer7_policies_expand_groups(rules, np, grps, ng);
		}
		layer7_policies_sort(rules, np);
		layer7_exceptions_sort(exc, nx);

		printf("layer7d_version: %s\n", layer7d_version);
		printf("config: %s\n", config_path);
		printf("  layer7: found\n");
		if (p.has_enabled)
			printf("  enabled: %s\n", p.enabled ? "true" : "false");
		else
			printf("  enabled: (not found)\n");
		if (p.has_mode)
			printf("  mode: %s\n", p.mode);
		else
			printf("  mode: (not found)\n");
		if (p.has_log_level)
			printf("  log_level: %s\n", p.log_level);
		else
			printf("  log_level: (not found)\n");
		if (p.has_syslog_remote)
			printf("  syslog_remote: %s\n",
			    p.syslog_remote ? "true" : "false");
		else
			printf("  syslog_remote: (not found)\n");
		if (p.has_syslog_remote_host && p.syslog_remote_host[0])
			printf("  syslog_remote_host: %s\n", p.syslog_remote_host);
		else
			printf("  syslog_remote_host: (not set)\n");
		if (p.has_syslog_remote_port)
			printf("  syslog_remote_port: %d\n", p.syslog_remote_port);
		else
			printf("  syslog_remote_port: (default 514)\n");
		if (p.has_debug_minutes)
			printf("  debug_minutes: %d (boost após reload)\n",
			    p.debug_minutes);
		else
			printf("  debug_minutes: (not set)\n");
		if (p.has_protos_file)
			printf("  protos_file: %s\n", p.protos_file);
		else
			printf("  protos_file: (default "
			    "/usr/local/etc/layer7-protos.txt)\n");
		if (p.n_interfaces > 0) {
			int x;
			printf("  interfaces: [");
			for (x = 0; x < p.n_interfaces; x++) {
				if (x)
					printf(", ");
				printf("%s", p.interfaces[x]);
			}
			printf("]\n");
		} else
			printf("  interfaces: (none)\n");

		printf("  policies: %d (sorted priority desc, id asc)\n", np);
		for (k = 0; k < np; k++) {
			printf("    [%d] id=%s pri=%d action=%s enabled=%s", k,
			    rules[k].id, rules[k].priority,
			    layer7_action_str(rules[k].action),
			    rules[k].enabled ? "true" : "false");
			if (rules[k].tag_table[0])
				printf(" tag_table=%s", rules[k].tag_table);
			if (rules[k].n_ndpi_apps == 0 && rules[k].n_ndpi_cats == 0 &&
			    rules[k].n_hosts == 0)
				printf(" match.*\n");
			else {
				int j;
				if (rules[k].n_ndpi_apps > 0) {
					printf(" app=[");
					for (j = 0; j < rules[k].n_ndpi_apps; j++) {
						if (j)
							printf(",");
						printf("%s", rules[k].ndpi_apps[j]);
					}
					printf("]");
				}
				if (rules[k].n_ndpi_cats > 0) {
					printf(" cat=[");
					for (j = 0; j < rules[k].n_ndpi_cats; j++) {
						if (j)
							printf(",");
						printf("%s", rules[k].ndpi_cats[j]);
					}
					printf("]");
				}
				if (rules[k].n_hosts > 0) {
					printf(" hosts=[");
					for (j = 0; j < rules[k].n_hosts; j++) {
						if (j)
							printf(",");
						printf("%s", rules[k].hosts[j]);
					}
					printf("]");
				}
				if (rules[k].n_ifaces > 0) {
					printf(" ifaces=[");
					for (j = 0; j < rules[k].n_ifaces; j++) {
						if (j) printf(",");
						printf("%s", rules[k].ifaces[j]);
					}
					printf("]");
				}
				if (rules[k].n_src_hosts > 0) {
					printf(" src_hosts=[");
					for (j = 0; j < rules[k].n_src_hosts; j++){
						if (j) printf(",");
						printf("%s", rules[k].src_hosts[j]);
					}
					printf("]");
				}
				if (rules[k].n_src_cidrs > 0) {
					printf(" src_cidrs=%d",
					    rules[k].n_src_cidrs);
				}
				printf("\n");
			}
		}

		printf("  exceptions: %d (priority desc)\n", nx);
		for (k = 0; k < nx; k++) {
			int h;
			printf("    [%d] id=%s pri=%d action=%s", k,
			    exc[k].id[0] ? exc[k].id : "(none)",
			    exc[k].priority, layer7_action_str(exc[k].action));
			for (h = 0; h < exc[k].n_hosts; h++)
				printf(" host=%s", exc[k].hosts[h]);
			for (h = 0; h < exc[k].n_cidrs; h++)
				printf(" cidr/pref=%d", exc[k].cidrs[h].prefix);
			if (exc[k].n_ifaces > 0) {
				printf(" ifaces=");
				for (h = 0; h < exc[k].n_ifaces; h++)
					printf("%s%s", h ? "," : "",
					    exc[k].ifaces[h]);
			}
			printf("\n");
		}

		printf("  policy dry-run (exceptions → policies → default):\n");
		for (k = 0; k < 2; k++) {
			int ge = (k == 1);
			static const char *lbl[] = { "monitor", "enforce" };
			static const char *srcs[] = { "10.0.0.99", "10.0.0.1",
				"10.0.0.1", "10.0.0.1", NULL, "192.168.77.10" };
			static const char *apps[] = { "BitTorrent", "BitTorrent",
				"HTTP", "HTTP", "HTTP", "HTTP" };
			static const char *cats[] = { NULL, NULL, "Web", NULL,
				"Web", "Web" };
			int a, ncase = 6;

			printf("    --- as if global mode=%s ---\n", lbl[k]);
			for (a = 0; a < ncase; a++) {
				struct layer7_decision dec;

				layer7_flow_decide(exc, nx, rules, np, ge,
				    NULL, srcs[a], apps[a], cats[a], NULL, &dec);
				printf("      src=%s app=%s cat=%s -> %s reason=%s",
				    srcs[a] ? srcs[a] : "(null)",
				    apps[a] ? apps[a] : "(null)",
				    cats[a] ? cats[a] : "(null)",
				    layer7_action_str(dec.action),
				    layer7_decide_reason_str(dec.reason));
				if (dec.matched_exception_id[0])
					printf(" exception=%s",
					    dec.matched_exception_id);
				if (dec.matched_policy_id[0])
					printf(" policy=%s", dec.matched_policy_id);
				if ((dec.reason == L7_DECIDE_POLICY_MATCH ||
					dec.reason == L7_DECIDE_EXCEPTION) &&
				    (dec.action == LAYER7_ACTION_BLOCK ||
					dec.action == LAYER7_ACTION_TAG))
					printf(" would_enforce=%d",
					    dec.would_enforce_block_or_tag);
				if (dec.would_enforce_block_or_tag &&
				    dec.pf_table[0] && srcs[a] &&
				    layer7_pf_ipv4_host_ok(srcs[a])) {
					char pfc[160];
					if (layer7_pf_snprint_add(pfc,
						sizeof(pfc), dec.pf_table,
						srcs[a]) > 0)
						printf(" pfctl_suggest=%s", pfc);
				}
				printf("\n");
			}
		}
		printf(
		    "  pf_exec: layer7_pf_exec_table_add/delete → /sbin/pfctl "
		    "(runtime após nDPI)\n");
		free(buf);
		return 0;
	}

	{
		struct layer7_policy_rule tmp_r[L7_MAX_POLICIES];
		struct layer7_exception tmp_x[L7_MAX_EXCEPTIONS];
		int tn = 0, tx = 0, okp, okx;

		okp = (layer7_policies_parse(buf, len, tmp_r, &tn,
			  L7_MAX_POLICIES) == 0);
		okx = (layer7_exceptions_parse(buf, len, tmp_x, &tx,
			  L7_MAX_EXCEPTIONS) == 0);
		if (okp && okx) {
			{
				struct layer7_group grps[L7_MAX_GROUPS];
				int ng = 0;
				(void)layer7_groups_parse(buf, len, grps,
				    &ng, L7_MAX_GROUPS);
				layer7_policies_expand_groups(tmp_r, tn,
				    grps, ng);
			}
			memcpy(s_rules, tmp_r, (size_t)tn * sizeof(s_rules[0]));
			memcpy(s_exc, tmp_x, (size_t)tx * sizeof(s_exc[0]));
			s_np = tn;
			s_nx = tx;
			layer7_policies_sort(s_rules, s_np);
			layer7_exceptions_sort(s_exc, s_nx);
			s_reload_ok++;
			pe_loaded = 1;
		} else {
			s_snapshot_fail++;
			if (use_syslog) {
				if (!okp)
					L7_WARN("policies[] parse failed (%s)",
					    config_path);
				if (!okx)
					L7_WARN("exceptions[] parse failed (%s)",
					    config_path);
			}
		}
	}
	free(buf);
	s_parsed = p;
	s_have_parse = 1;
	set_ll_from_parsed(&p);
	if (pe_loaded)
		refresh_enforce_cfg();

	if (p.has_debug_minutes) {
		if (p.debug_minutes <= 0)
			s_debug_until = 0;
		else {
			s_debug_until = time(NULL) + (time_t)p.debug_minutes * 60;
			if (use_syslog)
				L7_NOTE(
				    "debug_boost: LOG_DEBUG for %d min (until "
				    "%lld epoch)",
				    p.debug_minutes, (long long)s_debug_until);
		}
	}

	if (use_syslog) {
		if (!p.has_layer7)
			L7_WARN("config: no \"layer7\" in %s", config_path);
		else if (cfg_disabled(&p))
			L7_NOTE(
			    "config: layer7.enabled=false — idle (sem motor L7)");
		else if (pe_loaded)
			L7_NOTE(
			    "config: policies=%d exceptions=%d enforce_cfg=%d "
			    "reload#%llu (%s)",
			    s_np, s_nx, s_ge,
			    (unsigned long long)s_reload_ok, config_path);
		else
			L7_WARN(
			    "policies/exceptions parse falhou — snapshot runtime "
			    "inalterado (%s)",
			    config_path);
	}
	return 0;
}

#if HAVE_NDPI
static void
close_captures(void)
{
	int i;

	for (i = 0; i < s_n_captures; i++) {
		if (s_captures[i]) {
			layer7_capture_close(s_captures[i]);
			s_captures[i] = NULL;
		}
	}
	s_n_captures = 0;
}

static void
open_captures(void)
{
	int i;
	char errbuf[256];

	close_captures();
	if (!s_have_parse || cfg_disabled(&s_parsed))
		return;
	if (s_parsed.n_interfaces == 0) {
		L7_NOTE("capture: no interfaces configured — nDPI idle");
		return;
	}
	{
		const char *pf = s_parsed.has_protos_file ?
		    s_parsed.protos_file : NULL;
		if (pf && pf[0])
			L7_NOTE("capture: protos_file=%s", pf);
		for (i = 0; i < s_parsed.n_interfaces &&
		    s_n_captures < L7_MAX_IFACES; i++) {
			struct layer7_capture *c;

			c = layer7_capture_open(s_parsed.interfaces[i], 1536,
			    layer7_on_classified_flow,
			    layer7_on_dns_resolved,
			    layer7_on_dns_query, pf,
			    errbuf, (int)sizeof(errbuf));
			if (!c) {
				L7_WARN("capture_open(%s) failed: %s",
				    s_parsed.interfaces[i], errbuf);
				continue;
			}
			s_captures[s_n_captures++] = c;
			L7_NOTE("capture: opened %s (nDPI active)",
			    s_parsed.interfaces[i]);
		}
	}
	if (s_n_captures == 0)
		L7_WARN("capture: no interfaces opened — nDPI disabled");
}

static void
aggregate_capture_stats(void)
{
	int i;
	unsigned long long pkts = 0, cl = 0, ex = 0;

	for (i = 0; i < s_n_captures; i++) {
		unsigned long long p, a, c, e;
		layer7_capture_stats(s_captures[i], &p, &a, &c, &e);
		pkts += p;
		cl += c;
		ex += e;
		(void)a;
	}
	s_cap_pkts = pkts;
	s_cap_flows_classified = cl;
	s_cap_flows_expired = ex;
}
#else
static void close_captures(void) {}
static void open_captures(void) {}
static void aggregate_capture_stats(void) {}
#endif

int main(int argc, char **argv)
{
	struct sigaction sa;
	struct stat st;
	int i;
	int tick = 0;
	int vi;

	for (vi = 1; vi < argc; vi++) {
		if (strcmp(argv[vi], "-V") == 0) {
			puts(layer7d_version);
			return 0;
		}
#if HAVE_NDPI
		if (strcmp(argv[vi], "--list-protos") == 0) {
			return list_ndpi_protos();
		}
#endif
		if (strcmp(argv[vi], "--fingerprint") == 0) {
			char hwid[L7_HW_ID_LEN];
			if (layer7_hw_fingerprint(hwid,
			    sizeof(hwid)) != 0) {
				fprintf(stderr,
				    "layer7d: failed to compute "
				    "hardware fingerprint\n");
				return 1;
			}
			printf("%s\n", hwid);
			return 0;
		}
		if (strcmp(argv[vi], "--activate") == 0) {
			const char *key, *url = NULL;
			if (vi + 1 >= argc) {
				fprintf(stderr,
				    "layer7d: --activate requires KEY\n");
				return 1;
			}
			key = argv[vi + 1];
			if (vi + 2 < argc && argv[vi + 2][0] != '-')
				url = argv[vi + 2];
			return layer7_activate(key, url);
		}
	}

	i = 1;
	while (i < argc) {
		if (strcmp(argv[i], "-t") == 0) {
			test_mode = 1;
			i++;
			continue;
		}
		if (strcmp(argv[i], "-n") == 0) {
			enforce_dry_run = 1;
			i++;
			continue;
		}
		if (strcmp(argv[i], "-c") == 0) {
			if (i + 1 >= argc) {
				fprintf(stderr, "layer7d: -c requer caminho\n");
				return 1;
			}
			config_path = argv[i + 1];
			i += 2;
			continue;
		}
		if (strcmp(argv[i], "-e") == 0) {
			const char *ip, *app, *cat = NULL;

			if (test_mode) {
				fprintf(stderr,
				    "layer7d: -e e -t são mutuamente exclusivos\n");
				return 1;
			}
			if (i + 3 > argc) {
				fprintf(stderr,
				    "layer7d: -e requer IP APP [categoria_ndpi]\n");
				usage();
				return 1;
			}
			ip = argv[i + 1];
			app = argv[i + 2];
			i += 3;
			if (i < argc && argv[i][0] != '-')
				cat = argv[i++];
			if (i < argc) {
				fprintf(stderr,
				    "layer7d: argumentos após -e IP APP [CAT]: "
				    "remova '%s' ou reordene (-c antes de -e)\n",
				    argv[i]);
				return 1;
			}
			return run_enforce_once_cli(config_path, ip, app, cat,
			    enforce_dry_run);
		}
		if (strcmp(argv[i], "-h") == 0 ||
		    strcmp(argv[i], "--help") == 0) {
			usage();
			return 0;
		}
		if (argv[i][0] == '-') {
			fprintf(stderr, "layer7d: unknown argument: %s\n",
			    argv[i]);
			usage();
			return 1;
		}
		fprintf(stderr, "layer7d: argumento inesperado: %s\n",
		    argv[i]);
		usage();
		return 1;
	}

	if (test_mode)
		return apply_config(0) ? 1 : 0;

	sa.sa_handler = on_signal;
	sigemptyset(&sa.sa_mask);
	sa.sa_flags = 0;
	sigaction(SIGTERM, &sa, NULL);
	sigaction(SIGINT, &sa, NULL);
	sa.sa_handler = on_hup;
	sigaction(SIGHUP, &sa, NULL);
	sa.sa_handler = on_usr1;
	sigaction(SIGUSR1, &sa, NULL);

	s_boot_time = time(NULL);

	openlog("layer7d", LOG_PID | LOG_CONS, LOG_DAEMON);
	syslog(LOG_NOTICE, "daemon_start version=%s", layer7d_version);

	if (stat(config_path, &st) == 0) {
		L7_NOTE("config file present: %s (%lld bytes)", config_path,
		    (long long)st.st_size);
		(void)apply_config(1);
	} else if (errno == ENOENT) {
		L7_NOTE("config absent: %s — copy layer7.json.sample",
		    config_path);
		s_have_parse = 0;
	} else
		L7_WARN("config path %s: %s", config_path, strerror(errno));

	if (stat(config_path, &st) == 0 && s_reload_ok == 0ULL &&
	    s_snapshot_fail > 0ULL)
		L7_WARN(
		    "degraded: políticas/exceções inválidas — snapshot não "
		    "carregado (%s)",
		    config_path);

	/* License check at startup */
	memset(&s_lic, 0, sizeof(s_lic));
	s_last_lic_check = time(NULL);
	if (layer7_license_check(&s_lic) == 0) {
		if (s_lic.dev_mode)
			L7_WARN("license: DEV MODE — no production key "
			    "embedded; enforce allowed");
		else if (s_lic.grace)
			L7_WARN("license: %s", s_lic.error);
		else
			L7_NOTE("license: valid customer=%s expiry=%s "
			    "features=%s days_left=%d",
			    s_lic.customer, s_lic.expiry,
			    s_lic.features, s_lic.days_left);
		refresh_enforce_cfg();
	} else {
		L7_WARN("license: INVALID — %s", s_lic.error);
		L7_WARN("license: enforce disabled, monitor-only mode");
		s_ge = 0;
	}

	open_captures();

	for (;;) {
		if (stop_req) {
			close_captures();
			if (s_blacklist) {
				l7_blacklist_free(s_blacklist);
				s_blacklist = NULL;
			}
			l7_log(L7_PRI_FAC | LOG_NOTICE, "daemon_stop");
			closelog();
			return 0;
		}
		if (usr1_req) {
			usr1_req = 0;
			s_sigusr1_count++;
			aggregate_capture_stats();
			write_stats_json();
#if HAVE_NDPI
			L7_NOTE(
			    "SIGUSR1 stats: ver=%s reload_ok=%llu snapshot_fail=%llu "
			    "sighup=%llu usr1=%llu loop_ticks=%llu "
			    "policies=%d exceptions=%d enforce_cfg=%d "
			    "have_parse=%d pf_add_ok=%llu pf_add_fail=%llu "
			    "dst_add_ok=%llu dst_add_fail=%llu dst_cache=%d "
			    "cap_pkts=%llu cap_classified=%llu cap_expired=%llu "
			    "captures=%d",
			    layer7d_version,
			    (unsigned long long)s_reload_ok,
			    (unsigned long long)s_snapshot_fail,
			    (unsigned long long)s_sighup_count,
			    (unsigned long long)s_sigusr1_count,
			    (unsigned long long)s_loop_ticks, s_np, s_nx, s_ge,
			    s_have_parse,
			    (unsigned long long)s_pf_table_add_ok,
			    (unsigned long long)s_pf_table_add_fail,
			    (unsigned long long)s_pf_dst_add_ok,
			    (unsigned long long)s_pf_dst_add_fail,
			    s_n_dst,
			    (unsigned long long)s_cap_pkts,
			    (unsigned long long)s_cap_flows_classified,
			    (unsigned long long)s_cap_flows_expired,
			    s_n_captures);
#else
			L7_NOTE(
			    "SIGUSR1 stats: ver=%s reload_ok=%llu snapshot_fail=%llu "
			    "sighup=%llu usr1=%llu loop_ticks=%llu "
			    "policies=%d exceptions=%d enforce_cfg=%d "
			    "have_parse=%d pf_add_ok=%llu pf_add_fail=%llu",
			    layer7d_version,
			    (unsigned long long)s_reload_ok,
			    (unsigned long long)s_snapshot_fail,
			    (unsigned long long)s_sighup_count,
			    (unsigned long long)s_sigusr1_count,
			    (unsigned long long)s_loop_ticks, s_np, s_nx, s_ge,
			    s_have_parse,
			    (unsigned long long)s_pf_table_add_ok,
			    (unsigned long long)s_pf_table_add_fail);
#endif
		}
		if (reload_req) {
			reload_req = 0;
			s_sighup_count++;
			L7_NOTE("SIGHUP: reload config");
			dst_cache_flush();
			close_captures();
			if (stat(config_path, &st) == 0)
				(void)apply_config(1);
			else {
				L7_WARN("SIGHUP: missing %s", config_path);
				s_have_parse = 0;
			}

			if (!pf_base_tables_ok())
				(void)layer7_pf_selfheal("sighup_reload");

			open_captures();

			/* Reload blacklists from separate config.json */
			{
				struct l7_bl_config bl_cfg;
				struct l7_blacklist *new_bl = NULL;
				struct l7_blacklist *old_bl;

				bl_flush_rule_tables();
				memset(s_bl_rules, 0, sizeof(s_bl_rules));
				s_bl_n_rules = 0;

				if (l7_bl_config_load(
				    L7_BL_DIR_DEFAULT "/config.json",
				    &bl_cfg) == 0
				    && bl_cfg.enabled
				    && bl_cfg.n_rules > 0) {
					const char *all_cats[L7_BL_MAX_CATS];
					const char *bwl[L7_BL_WL_MAX];
					int all_n = 0, ri, ci, ai, found;

					for (ri = 0; ri < bl_cfg.n_rules;
					    ri++) {
						for (ci = 0; ci <
						    bl_cfg.rules[ri]
						    .n_categories; ci++) {
							found = 0;
							for (ai = 0;
							    ai < all_n;
							    ai++) {
								if (strcmp(
								    all_cats[ai],
								    bl_cfg.rules[ri]
								    .categories[ci])
								    == 0) {
									found = 1;
									break;
								}
							}
							if (!found &&
							    all_n <
							    L7_BL_MAX_CATS)
								all_cats[all_n++] =
								    bl_cfg
								    .rules[ri]
								    .categories[ci];
						}
					}

					for (ai = 0;
					    ai < bl_cfg.n_whitelist;
					    ai++)
						bwl[ai] =
						    bl_cfg.whitelist[ai];

					memcpy(s_bl_rules, bl_cfg.rules,
					    sizeof(bl_cfg.rules));
					s_bl_n_rules = bl_cfg.n_rules;

					if (all_n > 0) {
						new_bl = l7_blacklist_load(
						    L7_BL_DIR_DEFAULT,
						    all_cats, all_n,
						    bwl,
						    bl_cfg.n_whitelist);
					}

					if (new_bl || all_n == 0) {
						L7_NOTE("blacklists: "
						    "loaded %d domains "
						    "in %d categories, "
						    "%d rules",
						    new_bl ?
						    l7_blacklist_count(
						    new_bl) : 0,
						    new_bl ?
						    l7_blacklist_cat_count(
						    new_bl) : 0,
						    s_bl_n_rules);
					} else {
						L7_WARN("blacklists: "
						    "rules loaded (%d), "
						    "but failed to load "
						    "UT1 categories",
						    s_bl_n_rules);
					}
				}

				old_bl = s_blacklist;
				s_blacklist = new_bl;
				if (old_bl)
					l7_blacklist_free(old_bl);
			}
		}

		/* Periodic license re-check (every L7_LIC_CHECK_INTERVAL) */
		{
			time_t tnow = time(NULL);
			if (tnow - s_last_lic_check >= L7_LIC_CHECK_INTERVAL) {
				struct l7_license_info li;
				s_last_lic_check = tnow;
				memset(&li, 0, sizeof(li));
				if (layer7_license_check(&li) == 0) {
					s_lic = li;
					refresh_enforce_cfg();
					if (li.grace)
						L7_WARN("license_recheck: %s",
						    li.error);
				} else {
					s_lic = li;
					L7_WARN("license_recheck: INVALID — "
					    "%s", li.error);
					L7_WARN("license_recheck: enforce "
					    "disabled, monitor-only");
					s_ge = 0;
				}
			}
		}

		s_loop_ticks++;
		tick++;
		if (s_have_parse && cfg_disabled(&s_parsed)) {
			if (tick % 20 == 0)
				L7_INFO("layer7.enabled=false — still idle");
			sleep(60);
		}
#if HAVE_NDPI
		else if (s_n_captures > 0) {
			int j, total = 0;
			for (j = 0; j < s_n_captures; j++) {
				int r = layer7_capture_poll(s_captures[j], 64);
				if (r > 0)
					total += r;
			}
			dst_cache_sweep();
			if (total == 0)
				usleep(10000);
			if (tick % 600 == 0 && tick > 0) {
				aggregate_capture_stats();
				write_stats_json();
				L7_INFO(
				    "periodic: reload_ok=%llu policies=%d "
				    "exceptions=%d enforce=%d "
				    "pkts=%llu classified=%llu "
				    "blocked=%llu allowed=%llu",
				    (unsigned long long)s_reload_ok, s_np,
				    s_nx, s_ge,
				    (unsigned long long)s_cap_pkts,
				    (unsigned long long)s_cap_flows_classified,
				    (unsigned long long)s_total_blocked,
				    (unsigned long long)s_total_allowed);
			}
		}
#endif
		else {
			if (tick % 120 == 0 && tick > 0)
				L7_INFO(
				    "periodic_state: reload_ok=%llu "
				    "snapshot_fail=%llu policies=%d "
				    "exceptions=%d enforce_cfg=%d "
				    "(no captures active)",
				    (unsigned long long)s_reload_ok,
				    (unsigned long long)s_snapshot_fail, s_np,
				    s_nx, s_ge);
			sleep(30);
		}
	}
}
