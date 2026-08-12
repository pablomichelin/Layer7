/*
 * layer7d — config layer7; log_level filtra verbosidade; idle se enabled=false.
 */
#if HAVE_NDPI
#include "capture.h"
#include <ndpi_api.h>
#include <ndpi_main.h>
#include <ndpi_typedefs.h>
#endif
#include "allowlist.h"
#include "blacklist.h"
#include "bl_config.h"
#include "config_parse.h"
#include "enforce.h"
#include "features.h"
#include "identity_map.h"
#include "identity_ldap.h"
#include "identity_radius.h"
#include "identity_dc.h"
#include "license.h"
#include "log_store.h"
#include "policy.h"
#include <arpa/inet.h>
#include <errno.h>
#include <fcntl.h>
#include <ifaddrs.h>
#include <netdb.h>
#include <netinet/in.h>
#include <signal.h>
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/stat.h>
#include <sys/wait.h>
#include <syslog.h>
#include <time.h>
#include <unistd.h>

static const char layer7d_version[] =
#include <version.str>
;

#define DEFAULT_CONFIG "/usr/local/etc/layer7.json"
#define LAYER7_LOG_PATH "/var/log/layer7d.log"
#define LAYER7_EVENTS_LOG_PATH "/var/log/layer7-events.log"
#define L7_LOG_MAX_MB_DEFAULT 5
#define L7_LOG_KEEP_DEFAULT 3
#define L7_PF_HELPER_PATH "/usr/local/libexec/layer7-pfctl"
#define L7_PF_RULES_DEBUG_PATH "/tmp/rules.debug"
#define L7_PF_SELFHEAL_MIN_SEC 10
#define L7_ALLOWLIST_SEED_PATH "/usr/local/etc/layer7/allowlist-seed.txt"

/* 0=error 1=warn 2=info 3=debug — mensagens com nível <= s_ll */
static int s_ll = 2;
static int s_log_file_max_mb = L7_LOG_MAX_MB_DEFAULT;
static int s_log_file_keep = L7_LOG_KEEP_DEFAULT;
static int s_event_log_enabled;
static int s_n_event_interfaces;
static char s_event_interfaces[L7_MAX_INTERFACES][L7_IFACE_NAME_LEN];

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
static time_t s_last_stats_write;
static time_t s_last_periodic_log;

/* Agregados das capturas nDPI; ficam zero em builds sem nDPI. */
static unsigned long long s_cap_pkts;
static unsigned long long s_cap_pkts_v4;
static unsigned long long s_cap_pkts_v6;
static unsigned long long s_cap_flows_active;
static unsigned long long s_cap_flows_active_v4;
static unsigned long long s_cap_flows_active_v6;
static unsigned long long s_cap_flows_classified;
static unsigned long long s_cap_flows_classified_v4;
static unsigned long long s_cap_flows_classified_v6;
static unsigned long long s_cap_flows_expired;
static unsigned long long s_cap_flows_evicted;
static unsigned long long s_cap_flows_dropped;
static int s_cap_interfaces;

static struct l7_license_info s_lic;
static time_t s_last_lic_check;
static time_t s_last_checkin_tick;

/* Identity map (20.15) + LDAP worker (20.17): só com entitlement; zero threads OFF. */
static struct l7_id_map s_idmap;
static int s_idmap_active;
static struct l7_ldap_worker *s_ldap_worker;
static struct l7_radius_worker *s_radius_worker;
static struct l7_dc_worker *s_dc_worker;
static int s_license_state = -1; /* 0=invalid, 1=valid, 2=grace/dev */

static struct l7_blacklist *s_blacklist;
static struct l7_allowlist s_allowlist;
static unsigned long long s_allow_hits;
static unsigned long long s_bl_hits;
static unsigned long long s_bl_lookups;
static unsigned long long s_bl_dns_hits;
static unsigned long long s_bl_sni_hits;
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

#define L7_STATS_DIR "/var/db/layer7"
#define L7_STATS_JSON_PATH L7_STATS_DIR "/layer7-stats.json"

static void json_escape_fprint(FILE *f, const char *s);

static int
ensure_layer7_db_dir(void)
{
	struct stat st;

	if (stat(L7_STATS_DIR, &st) == 0) {
		if (!S_ISDIR(st.st_mode))
			return -1;
		return 0;
	}
	if (mkdir(L7_STATS_DIR, 0755) != 0 && errno != EEXIST)
		return -1;
	return 0;
}

static void
write_stats_json(void)
{
	FILE *f;
	int fd;
	int i, limit;
	time_t now = time(NULL);
	char tmp_path[sizeof(L7_STATS_JSON_PATH) + 8];

	if (ensure_layer7_db_dir() != 0)
		return;

	snprintf(tmp_path, sizeof(tmp_path), "%s.tmp", L7_STATS_JSON_PATH);
	(void)unlink(tmp_path);
	fd = open(tmp_path, O_WRONLY | O_CREAT | O_EXCL | O_NOFOLLOW, 0644);
	if (fd < 0)
		return;
	f = fdopen(fd, "w");
	if (!f) {
		close(fd);
		(void)unlink(tmp_path);
		return;
	}

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
	fprintf(f, "  \"cap_pkts\": %llu,\n",
	    (unsigned long long)s_cap_pkts);
	fprintf(f, "  \"cap_pkts_v4\": %llu,\n",
	    (unsigned long long)s_cap_pkts_v4);
	fprintf(f, "  \"cap_pkts_v6\": %llu,\n",
	    (unsigned long long)s_cap_pkts_v6);
	fprintf(f, "  \"cap_active\": %llu,\n",
	    (unsigned long long)s_cap_flows_active);
	fprintf(f, "  \"cap_active_v4\": %llu,\n",
	    (unsigned long long)s_cap_flows_active_v4);
	fprintf(f, "  \"cap_active_v6\": %llu,\n",
	    (unsigned long long)s_cap_flows_active_v6);
	fprintf(f, "  \"cap_classified\": %llu,\n",
	    (unsigned long long)s_cap_flows_classified);
	fprintf(f, "  \"cap_classified_v4\": %llu,\n",
	    (unsigned long long)s_cap_flows_classified_v4);
	fprintf(f, "  \"cap_classified_v6\": %llu,\n",
	    (unsigned long long)s_cap_flows_classified_v6);
	fprintf(f, "  \"cap_expired\": %llu,\n",
	    (unsigned long long)s_cap_flows_expired);
	fprintf(f, "  \"cap_evicted\": %llu,\n",
	    (unsigned long long)s_cap_flows_evicted);
	fprintf(f, "  \"cap_dropped\": %llu,\n",
	    (unsigned long long)s_cap_flows_dropped);
	fprintf(f, "  \"captures\": %d,\n", s_cap_interfaces);

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
	fprintf(f, "  \"license_features\": \"");
	json_escape_fprint(f, s_lic.features);
	fprintf(f, "\",\n");
	fprintf(f, "  \"license_features_flags\": %u,\n",
	    s_lic.features_flags ? s_lic.features_flags : 0);
	fprintf(f, "  \"license_features_truncated\": %s,\n",
	    s_lic.features_truncated ? "true" : "false");
	fprintf(f, "  \"license_clock_suspect\": %s,\n",
	    s_lic.clock_suspect ? "true" : "false");
	fprintf(f, "  \"license_clock_max_seen\": %lld,\n",
	    (long long)s_lic.clock_max_seen);
	fprintf(f, "  \"license_clock_delta_sec\": %ld,\n",
	    s_lic.clock_delta_sec);
	fprintf(f, "  \"identity_active\": %s,\n",
	    s_idmap_active ? "true" : "false");
	fprintf(f, "  \"identity_sessions\": %u,\n",
	    s_idmap_active ? layer7_idmap_count(&s_idmap) : 0);
	fprintf(f, "  \"identity_multi_user_sessions\": %u,\n",
	    s_idmap_active ? layer7_idmap_count_multi_user(&s_idmap) : 0);
	fprintf(f, "  \"identity_audit_conflicts\": %u,\n",
	    s_idmap_active ? layer7_idmap_audit_conflicts(&s_idmap) : 0);
	fprintf(f, "  \"identity_audit_last_writers\": %u,\n",
	    s_idmap_active ? layer7_idmap_audit_last_writers(&s_idmap) : 0);
	fprintf(f, "  \"identity_entitled\": %s,\n",
	    layer7_features_allows_identity(s_lic.features_flags) ?
	    "true" : "false");
	fprintf(f, "  \"mitm_entitled\": %s,\n",
	    layer7_features_allows_mitm(s_lic.features_flags) ?
	    "true" : "false");
	fprintf(f, "  \"mitm_runtime_available\": %s,\n",
	    (access("/usr/local/sbin/layer7-tlsproxy", X_OK) == 0) ?
	    "true" : "false");
	/*
	 * 20.10b/1.9.41: intercept_ready=true (wired).
	 * mitm_effective: flag materializado pelo PHP em sync_helper
	 * (/var/run/layer7/mitm.effective) — evita claim falso OFF/ON.
	 */
	fprintf(f, "  \"mitm_effective\": %s,\n",
	    (access("/var/run/layer7/mitm.effective", R_OK) == 0) ?
	    "true" : "false");
	fprintf(f, "  \"mitm_intercept_ready\": true,\n");
	{
		const char *lst = "off";
		enum l7_ldap_status st = s_ldap_worker ?
		    layer7_ldap_worker_status(s_ldap_worker, time(NULL)) :
		    L7_LDAP_STATUS_OFF;
		if (st == L7_LDAP_STATUS_OK)
			lst = "ok";
		else if (st == L7_LDAP_STATUS_DEGRADED)
			lst = "degraded";
		else if (st == L7_LDAP_STATUS_DOWN)
			lst = "down";
		fprintf(f, "  \"identity_ldap_status\": \"%s\",\n", lst);
	}
	{
		const char *rst = "off";
		enum l7_radius_status st = s_radius_worker ?
		    layer7_radius_worker_status(s_radius_worker) :
		    L7_RADIUS_STATUS_OFF;
		if (st == L7_RADIUS_STATUS_LISTEN)
			rst = "listen";
		else if (st == L7_RADIUS_STATUS_ERROR)
			rst = "error";
		fprintf(f, "  \"identity_radius_status\": \"%s\",\n", rst);
		fprintf(f, "  \"identity_radius_accepted\": %u,\n",
		    layer7_radius_worker_accepted(s_radius_worker));
		fprintf(f, "  \"identity_radius_rejected\": %u,\n",
		    layer7_radius_worker_rejected(s_radius_worker));
	}
	{
		const char *dst = "off";
		enum l7_dc_status st = s_dc_worker ?
		    layer7_dc_worker_status(s_dc_worker) : L7_DC_STATUS_OFF;
		if (st == L7_DC_STATUS_LISTEN)
			dst = "listen";
		else if (st == L7_DC_STATUS_ERROR)
			dst = "error";
		fprintf(f, "  \"identity_dc_status\": \"%s\",\n", dst);
		fprintf(f, "  \"identity_dc_accepted\": %u,\n",
		    layer7_dc_worker_accepted(s_dc_worker));
		fprintf(f, "  \"identity_dc_rejected\": %u,\n",
		    layer7_dc_worker_rejected(s_dc_worker));
	}

	{
		struct l7_checkin_status ci;

		memset(&ci, 0, sizeof(ci));
		(void)layer7_checkin_get_status(&ci);
		fprintf(f, "  \"license_check_in_enabled\": %s,\n",
		    layer7_checkin_config_enabled(config_path) ? "true" : "false");
		fprintf(f, "  \"license_check_in_ok\": %s,\n",
		    ci.ok ? "true" : "false");
		fprintf(f, "  \"license_last_check_in\": %lld,\n",
		    (long long)ci.last_ok);
		fprintf(f, "  \"license_next_check_in\": %lld,\n",
		    (long long)ci.next_due);
		fprintf(f, "  \"license_check_in_error\": \"");
		json_escape_fprint(f, ci.last_error);
		fprintf(f, "\",\n");
	}

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
	fprintf(f, "  \"bl_dns_hits\": %llu,\n",
	    (unsigned long long)s_bl_dns_hits);
	fprintf(f, "  \"bl_sni_hits\": %llu,\n",
	    (unsigned long long)s_bl_sni_hits);
	fprintf(f, "  \"bl_rules_active\": %d,\n", s_bl_n_rules);

	{
		int bl_n_cats = s_blacklist ?
		    l7_blacklist_cat_count(s_blacklist) : 0;
		int bli;

		fprintf(f, "  \"bl_top_categories\": [");
		for (bli = 0; bli < bl_n_cats && bli < 10; bli++) {
			const char *cn =
			    l7_blacklist_get_cat_name(s_blacklist, bli);
			unsigned long long ch =
			    l7_blacklist_get_cat_hit_count(s_blacklist, bli);
			if (!cn)
				cn = "?";
			fprintf(f, "%s\n    {\"cat\": \"",
			    bli > 0 ? "," : "");
			json_escape_fprint(f, cn);
			fprintf(f, "\", \"hits\": %llu}", ch);
		}
		fprintf(f, "%s]\n",
		    bl_n_cats > 0 ? "\n  " : "");
	}

	fprintf(f, "}\n");
	if (fclose(f) != 0) {
		(void)unlink(tmp_path);
		return;
	}
	if (rename(tmp_path, L7_STATS_JSON_PATH) != 0) {
		syslog(LOG_WARNING, "stats: rename tmp failed: %s",
		    strerror(errno));
		(void)unlink(tmp_path);
	}
}

static char s_remote_host[256];
static int s_syslog_remote;
static int s_remote_port = 514;
static time_t s_debug_until;

static void
json_escape_print(const char *s)
{
	if (!s)
		return;
	for (; *s; s++) {
		if (*s == '"' || *s == '\\')
			putchar('\\');
		putchar(*s);
	}
}

static void
json_escape_fprint(FILE *f, const char *s)
{
	if (!s)
		return;
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
#endif

static char *read_file(const char *path, size_t *out_len);
static int cfg_disabled(const struct layer7_parsed *p);
static int run_shell_cmd_ok(const char *cmd);
static void enforcement_flush_all_tables(void);

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
sync_local_log_cfg(const struct layer7_parsed *p)
{
	int i;

	s_log_file_max_mb = L7_LOG_MAX_MB_DEFAULT;
	s_log_file_keep = L7_LOG_KEEP_DEFAULT;
	s_event_log_enabled = 0;
	s_n_event_interfaces = 0;

	if (!p)
		return;
	if (p->has_log_file_max_mb)
		s_log_file_max_mb = p->log_file_max_mb;
	if (p->has_log_file_keep)
		s_log_file_keep = p->log_file_keep;
	if (p->has_event_log_enabled)
		s_event_log_enabled = p->event_log_enabled;
	for (i = 0; i < p->n_event_interfaces &&
	    i < L7_MAX_INTERFACES; i++) {
		strncpy(s_event_interfaces[s_n_event_interfaces],
		    p->event_interfaces[i], L7_IFACE_NAME_LEN - 1);
		s_event_interfaces[s_n_event_interfaces][L7_IFACE_NAME_LEN - 1] =
		    '\0';
		s_n_event_interfaces++;
	}
}

static int
event_iface_allowed(const char *iface)
{
	int i;

	if (s_n_event_interfaces == 0)
		return 1;
	if (!iface || iface[0] == '\0')
		return 0;
	for (i = 0; i < s_n_event_interfaces; i++) {
		if (strcmp(s_event_interfaces[i], iface) == 0)
			return 1;
	}
	return 0;
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

static int
layer7_write_local_log(const char *path, int pri, const char *msg)
{
	time_t now;
	struct tm tm;
	char ts[32], line[1280];
	const char *sev;
	int n;

	if (!msg || msg[0] == '\0')
		return 0;

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
	n = snprintf(line, sizeof(line), "%s [%s] %s", ts, sev, msg);
	if (n < 0 || (size_t)n >= sizeof(line))
		return -1;
	return layer7_log_store_append(path, line,
	    (size_t)s_log_file_max_mb * 1024U * 1024U,
	    (unsigned int)s_log_file_keep);
}

static void
l7_log(int pri, const char *fmt, ...)
{
	va_list ap;
	char line[1024];

	va_start(ap, fmt);
	vsnprintf(line, sizeof(line), fmt, ap);
	va_end(ap);
	(void)layer7_write_local_log(LAYER7_LOG_PATH, pri, line);
	syslog(pri, "%s", line);
	if (s_syslog_remote)
		layer7_send_remote_syslog(pri, line);
}

static void
l7_event_log(int force_audit, const char *iface, int pri, const char *fmt, ...)
{
	static time_t last_write_warn;
	va_list ap;
	char line[1024];
	time_t now;

	if (!force_audit &&
	    (!s_event_log_enabled || !event_iface_allowed(iface)))
		return;

	va_start(ap, fmt);
	vsnprintf(line, sizeof(line), fmt, ap);
	va_end(ap);
	if (layer7_write_local_log(LAYER7_EVENTS_LOG_PATH, pri, line) != 0) {
		now = time(NULL);
		if (now - last_write_warn >= 300) {
			last_write_warn = now;
			syslog(LOG_DAEMON | LOG_WARNING,
			    "event_log_write_failed path=%s error=%s",
			    LAYER7_EVENTS_LOG_PATH, strerror(errno));
		}
	}

	/* Bloqueios sao auditoria essencial: ficam tambem no syslog local.
	 * Eventos detalhados normais nao duplicam no system.log. */
	if (force_audit)
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
#define L7_EVENT_INFO(iface, ...)                                              \
	do {                                                                   \
		if (effective_ll() >= 2)                                       \
			l7_event_log(0, iface, L7_PRI_FAC | LOG_INFO,          \
			    __VA_ARGS__);                                      \
	} while (0)
#define L7_EVENT_NOTE(iface, ...)                                              \
	do {                                                                   \
		if (effective_ll() >= 2)                                       \
			l7_event_log(0, iface, L7_PRI_FAC | LOG_NOTICE,        \
			    __VA_ARGS__);                                      \
	} while (0)
#define L7_EVENT_DBG(iface, ...)                                               \
	do {                                                                   \
		if (effective_ll() >= 3)                                       \
			l7_event_log(0, iface, L7_PRI_FAC | LOG_DEBUG,         \
			    __VA_ARGS__);                                      \
	} while (0)
#define L7_AUDIT_NOTE(iface, ...)                                              \
	l7_event_log(1, iface, L7_PRI_FAC | LOG_NOTICE, __VA_ARGS__)

static void
on_usr1(int sig)
{
	(void)sig;
	usr1_req = 1;
}

#define L7_DST_CACHE_MAX 2048
#define L7_DST_TTL_MIN   60
#define L7_DST_TTL_MAX   3600
#define L7_DST_TTL_DEF   300
#define L7_DST_SWEEP_SEC  60

struct l7_allow_cache_entry {
	char     ip[48];
	time_t   expires;
};
static struct l7_allow_cache_entry s_allow_cache[L7_DST_CACHE_MAX];
static int s_n_allow_cache;

struct l7_enforce_cache_entry {
	char     table[64];
	char     ip[48];
	time_t   expires;
};
static struct l7_enforce_cache_entry s_enforce_cache[L7_DST_CACHE_MAX];
static int s_n_enforce_cache;
static time_t s_last_enforce_cache_sweep;

static int
pf_table_flush_try(const char *table)
{
	char cmd[128];

	if (!layer7_pf_table_name_ok(table))
		return -1;
	snprintf(cmd, sizeof(cmd),
	    "/sbin/pfctl -t %s -T flush 2>/dev/null", table);
	return run_shell_cmd_ok(cmd) ? 0 : -1;
}

static void
pf_table_flush_logged(const char *table, const char *ctx)
{
	if (pf_table_flush_try(table) != 0)
		L7_WARN("pfctl flush failed table=%s ctx=%s",
		    table, ctx ? ctx : "-");
}

static void refresh_enforce_cfg(void);

/*
 * 30.16 / BG-122 — enforce só se s_ge e cruzamento de gates de licença.
 * Revalida nos hot-paths para que um NOP isolado em refresh_enforce_cfg
 * não active bloqueio sem licença (mitiga A-02; R-A permanece).
 */
static int
enforce_armed(void)
{
	return s_ge && layer7_license_allows_enforce(&s_lic);
}

static void
enforce_ge_downgrade(int prev_ge, const char *reason)
{
	if (prev_ge && !s_ge) {
		L7_WARN("%s: license/config invalid — enforce disabled, "
		    "flushing PF dynamic tables",
		    reason ? reason : "enforce_downgrade");
		enforcement_flush_all_tables();
	}
}

static void identity_module_sync(const char *reason);
static void identity_module_shutdown(void);

static void
license_apply_invalidation(const char *reason)
{
	struct l7_license_info li;
	int prev_ge = s_ge;

	memset(&li, 0, sizeof(li));
	(void)layer7_license_check(&li);
	s_lic = li;
	s_license_state = 0;
	s_ge = 0;
	refresh_enforce_cfg();
	enforce_ge_downgrade(prev_ge, reason);
	identity_module_sync(reason);
}

/*
 * ADR-0028 / 20.15–20.19: sem entitlement → zero threads / zero mapa.
 * SIGHUP: mapa sobrevive; workers LDAP/RADIUS relêem config.
 */
static void
identity_ldap_sync_worker(const char *reason)
{
	char *buf = NULL;
	size_t len = 0;
	struct l7_ldap_cfg cfg;

	layer7_ldap_cfg_defaults(&cfg);
	buf = read_file(config_path, &len);
	if (buf != NULL) {
		(void)layer7_ldap_cfg_parse_json(buf, len, &cfg);
		free(buf);
	}
	(void)layer7_ldap_cfg_load_secret(&cfg, NULL);

	if (cfg.ldap_enabled && cfg.server[0] != '\0') {
		if (s_ldap_worker == NULL) {
			s_ldap_worker = layer7_ldap_worker_start(&s_idmap, &cfg);
			if (s_ldap_worker)
				L7_NOTE("identity: ldap worker ON (%s)",
				    reason ? reason : "?");
			else
				L7_WARN("identity: ldap worker start failed (%s)",
				    reason ? reason : "?");
		} else {
			layer7_ldap_worker_reload(s_ldap_worker, &cfg);
		}
	} else if (s_ldap_worker != NULL) {
		layer7_ldap_worker_stop(s_ldap_worker);
		s_ldap_worker = NULL;
		L7_NOTE("identity: ldap worker OFF (%s)",
		    reason ? reason : "?");
	}
	layer7_ldap_cfg_wipe_secret(&cfg);
}

static void
identity_radius_sync_worker(const char *reason)
{
	char *buf = NULL;
	size_t len = 0;
	struct l7_radius_cfg cfg;

	layer7_radius_cfg_defaults(&cfg);
	buf = read_file(config_path, &len);
	if (buf != NULL) {
		(void)layer7_radius_cfg_parse_json(buf, len, &cfg);
		free(buf);
	}
	(void)layer7_radius_cfg_load_secret(&cfg, NULL);

	if (cfg.radius_enabled) {
		if (s_radius_worker == NULL) {
			s_radius_worker = layer7_radius_worker_start(&s_idmap,
			    &cfg);
			if (s_radius_worker)
				L7_NOTE("identity: radius worker ON (%s)",
				    reason ? reason : "?");
			else
				L7_WARN("identity: radius worker start failed (%s)",
				    reason ? reason : "?");
		} else {
			layer7_radius_worker_reload(s_radius_worker, &cfg);
		}
	} else if (s_radius_worker != NULL) {
		layer7_radius_worker_stop(s_radius_worker);
		s_radius_worker = NULL;
		L7_NOTE("identity: radius worker OFF (%s)",
		    reason ? reason : "?");
	}
	layer7_radius_cfg_wipe_secret(&cfg);
}

static void
identity_dc_sync_worker(const char *reason)
{
	char *buf = NULL;
	size_t len = 0;
	struct l7_dc_cfg cfg;

	layer7_dc_cfg_defaults(&cfg);
	buf = read_file(config_path, &len);
	if (buf != NULL) {
		(void)layer7_dc_cfg_parse_json(buf, len, &cfg);
		free(buf);
	}
	(void)layer7_dc_cfg_load_secret(&cfg, NULL);

	if (cfg.dc_enabled) {
		if (s_dc_worker == NULL) {
			s_dc_worker = layer7_dc_worker_start(&s_idmap, &cfg);
			if (s_dc_worker)
				L7_NOTE("identity: dc_agent worker ON (%s)",
				    reason ? reason : "?");
			else
				L7_WARN("identity: dc_agent worker start failed (%s)",
				    reason ? reason : "?");
		} else {
			layer7_dc_worker_reload(s_dc_worker, &cfg);
		}
	} else if (s_dc_worker != NULL) {
		layer7_dc_worker_stop(s_dc_worker);
		s_dc_worker = NULL;
		L7_NOTE("identity: dc_agent worker OFF (%s)",
		    reason ? reason : "?");
	}
	layer7_dc_cfg_wipe_secret(&cfg);
}

static void
identity_module_sync(const char *reason)
{
	int want = layer7_features_allows_identity(s_lic.features_flags);
	time_t now = time(NULL);

	if (want && !s_idmap_active) {
		int n;

		if (layer7_idmap_init(&s_idmap) != 0) {
			L7_WARN("identity: init failed (%s)",
			    reason ? reason : "?");
			return;
		}
		n = layer7_idmap_load(&s_idmap, NULL, now);
		s_idmap_active = 1;
		layer7_policies_set_identity_map(&s_idmap);
		L7_NOTE("identity: module ON (%s) sessions_loaded=%d",
		    reason ? reason : "?", n < 0 ? 0 : n);
		identity_ldap_sync_worker(reason);
		identity_radius_sync_worker(reason);
		identity_dc_sync_worker(reason);
	} else if (!want && s_idmap_active) {
		if (s_ldap_worker) {
			layer7_ldap_worker_stop(s_ldap_worker);
			s_ldap_worker = NULL;
		}
		if (s_radius_worker) {
			layer7_radius_worker_stop(s_radius_worker);
			s_radius_worker = NULL;
		}
		if (s_dc_worker) {
			layer7_dc_worker_stop(s_dc_worker);
			s_dc_worker = NULL;
		}
		layer7_policies_set_identity_map(NULL);
		(void)layer7_idmap_save(&s_idmap, NULL);
		layer7_idmap_fini(&s_idmap);
		s_idmap_active = 0;
		L7_NOTE("identity: module OFF (%s) — map fini, zero threads",
		    reason ? reason : "?");
	} else if (want && s_idmap_active) {
		unsigned rem = layer7_idmap_expire(&s_idmap, now);

		if (rem > 0)
			L7_NOTE("identity: expired %u stale (%s)", rem,
			    reason ? reason : "?");
		identity_ldap_sync_worker(reason);
		identity_radius_sync_worker(reason);
		identity_dc_sync_worker(reason);
	}
}

static void
identity_module_shutdown(void)
{
	if (s_ldap_worker) {
		layer7_ldap_worker_stop(s_ldap_worker);
		s_ldap_worker = NULL;
	}
	if (s_radius_worker) {
		layer7_radius_worker_stop(s_radius_worker);
		s_radius_worker = NULL;
	}
	if (s_dc_worker) {
		layer7_dc_worker_stop(s_dc_worker);
		s_dc_worker = NULL;
	}
	if (!s_idmap_active)
		return;
	layer7_policies_set_identity_map(NULL);
	(void)layer7_idmap_save(&s_idmap, NULL);
	layer7_idmap_fini(&s_idmap);
	s_idmap_active = 0;
}

static void
license_checkin_tick(void)
{
	time_t tnow = time(NULL);
	int result;

	if (!layer7_checkin_config_enabled(config_path))
		return;

	if (layer7_checkin_offline_expired(tnow)) {
		(void)unlink(L7_LIC_PATH);
		L7_WARN("license_checkin: max offline exceeded — "
		    "invalidating local license");
		license_apply_invalidation("license_checkin_offline");
		return;
	}

	if (!layer7_checkin_due(tnow))
		return;
	if (s_last_checkin_tick != 0 &&
	    tnow - s_last_checkin_tick < 60)
		return;

	s_last_checkin_tick = tnow;
	result = layer7_check_in(NULL);
	if (result == L7_CHECKIN_DENIED ||
	    result == L7_CHECKIN_OFFLINE_MAX) {
		L7_WARN("license_checkin: remote denial — enforce disabled");
		license_apply_invalidation("license_checkin");
	} else if (result == L7_CHECKIN_OK) {
		struct l7_license_info li;

		memset(&li, 0, sizeof(li));
		if (layer7_license_check(&li) == 0) {
			s_lic = li;
			s_license_state = (li.grace || li.dev_mode) ? 2 : 1;
			refresh_enforce_cfg();
			identity_module_sync("license_checkin");
		}
	}
}

static void
allow_cache_add(const char *ip, uint32_t ttl)
{
	int i;
	time_t expires;
	uint32_t eff_ttl = ttl;

	if (!ip || !layer7_pf_host_enforce_ok(ip))
		return;
	if (eff_ttl < L7_DST_TTL_MIN)
		eff_ttl = L7_DST_TTL_MIN;
	if (eff_ttl > L7_DST_TTL_MAX)
		eff_ttl = L7_DST_TTL_MAX;
	expires = time(NULL) + (time_t)eff_ttl;

	for (i = 0; i < s_n_allow_cache; i++) {
		if (strcmp(s_allow_cache[i].ip, ip) == 0) {
			if (expires > s_allow_cache[i].expires)
				s_allow_cache[i].expires = expires;
			return;
		}
	}
	if (s_n_allow_cache >= L7_DST_CACHE_MAX) {
		L7_WARN("allow_cache full (%d) — evicting oldest entry ip=%s",
		    L7_DST_CACHE_MAX, s_allow_cache[0].ip);
		layer7_pf_exec_table_delete(L7_PF_TABLE_ALLOW_DST,
		    s_allow_cache[0].ip);
		memmove(&s_allow_cache[0], &s_allow_cache[1],
		    (size_t)(s_n_allow_cache - 1) * sizeof(s_allow_cache[0]));
		s_n_allow_cache--;
	}
	snprintf(s_allow_cache[s_n_allow_cache].ip,
	    sizeof(s_allow_cache[0].ip), "%s", ip);
	s_allow_cache[s_n_allow_cache].expires = expires;
	s_n_allow_cache++;
}

static void
allow_cache_sweep(void)
{
	time_t now = time(NULL);
	int i;

	for (i = 0; i < s_n_allow_cache; ) {
		if (s_allow_cache[i].expires < now) {
			layer7_pf_exec_table_delete(L7_PF_TABLE_ALLOW_DST,
			    s_allow_cache[i].ip);
			s_allow_cache[i] =
			    s_allow_cache[--s_n_allow_cache];
		} else
			i++;
	}
}

/*
 * IPs locais das interfaces do firewall. O daemon NUNCA pode adicionar um
 * IP local a uma tabela block: o sinkhole da block page resolve dominios
 * bloqueados para o IP portal (uma interface do firewall) e a resposta DNS
 * voltava a entrar no enforcement — bloqueando a GUI/SSH do proprio pfSense
 * para todas as redes (bug observado em lab com 192.168.100.254).
 */
#define L7_LOCAL_ADDR_MAX 64
static char s_local_addrs[L7_LOCAL_ADDR_MAX][INET6_ADDRSTRLEN];
static int s_n_local_addrs;
static time_t s_local_addrs_ts;

static int
ip_is_local_iface_addr(const char *ip)
{
	time_t now = time(NULL);
	int i;

	if (!ip || ip[0] == '\0')
		return 0;
	if (s_local_addrs_ts == 0 || now - s_local_addrs_ts > 60) {
		struct ifaddrs *ifap = NULL, *ifa;

		s_n_local_addrs = 0;
		s_local_addrs_ts = now;
		if (getifaddrs(&ifap) == 0) {
			for (ifa = ifap; ifa; ifa = ifa->ifa_next) {
				if (!ifa->ifa_addr)
					continue;
				if (s_n_local_addrs >= L7_LOCAL_ADDR_MAX)
					break;
				if (ifa->ifa_addr->sa_family == AF_INET) {
					struct sockaddr_in *sin =
					    (struct sockaddr_in *)(void *)
					    ifa->ifa_addr;

					if (!inet_ntop(AF_INET, &sin->sin_addr,
					    s_local_addrs[s_n_local_addrs],
					    INET6_ADDRSTRLEN))
						continue;
					s_n_local_addrs++;
				} else if (ifa->ifa_addr->sa_family ==
				    AF_INET6) {
					struct sockaddr_in6 *sin6 =
					    (struct sockaddr_in6 *)(void *)
					    ifa->ifa_addr;

					/* S-03: link-local não entra em block;
					 * ainda assim listar para self-protect */
					if (!inet_ntop(AF_INET6,
					    &sin6->sin6_addr,
					    s_local_addrs[s_n_local_addrs],
					    INET6_ADDRSTRLEN))
						continue;
					s_n_local_addrs++;
				}
			}
			freeifaddrs(ifap);
		}
	}
	for (i = 0; i < s_n_local_addrs; i++) {
		if (strcmp(s_local_addrs[i], ip) == 0)
			return 1;
	}
	return 0;
}

/* Politica block prevalece sobre allowlist dinamica (IPs partilhados CDN). */
static void
allow_cache_revoke_ip(const char *ip)
{
	int i;

	if (!ip || ip[0] == '\0')
		return;
	(void)layer7_pf_exec_table_delete(L7_PF_TABLE_ALLOW_DST, ip);
	for (i = 0; i < s_n_allow_cache; ) {
		if (strcmp(s_allow_cache[i].ip, ip) == 0) {
			s_allow_cache[i] =
			    s_allow_cache[--s_n_allow_cache];
		} else
			i++;
	}
}

static void
enforce_cache_add(const char *table, const char *ip, uint32_t ttl)
{
	int i;
	time_t expires;
	uint32_t eff_ttl = ttl;

	if (!table || !table[0] || !ip || ip[0] == '\0')
		return;
	if (!layer7_pf_table_name_ok(table) || !layer7_pf_host_enforce_ok(ip))
		return;

	if (eff_ttl < L7_DST_TTL_MIN)
		eff_ttl = L7_DST_TTL_MIN;
	if (eff_ttl > L7_DST_TTL_MAX)
		eff_ttl = L7_DST_TTL_MAX;
	if (eff_ttl == 0)
		eff_ttl = L7_DST_TTL_DEF;
	expires = time(NULL) + (time_t)eff_ttl;

	for (i = 0; i < s_n_enforce_cache; i++) {
		if (strcmp(s_enforce_cache[i].table, table) == 0 &&
		    strcmp(s_enforce_cache[i].ip, ip) == 0) {
			if (expires > s_enforce_cache[i].expires)
				s_enforce_cache[i].expires = expires;
			return;
		}
	}
	if (s_n_enforce_cache >= L7_DST_CACHE_MAX) {
		time_t now = time(NULL);
		int oldest = 0;
		int swept = 0;

		for (i = 0; i < s_n_enforce_cache; ) {
			if (s_enforce_cache[i].expires < now) {
				layer7_pf_exec_table_delete(
				    s_enforce_cache[i].table,
				    s_enforce_cache[i].ip);
				s_enforce_cache[i] =
				    s_enforce_cache[--s_n_enforce_cache];
				swept++;
			} else
				i++;
		}
		if (swept > 0)
			L7_WARN("enforce_cache full — swept %d expired entries",
			    swept);
		if (s_n_enforce_cache >= L7_DST_CACHE_MAX) {
			for (i = 1; i < s_n_enforce_cache; i++) {
				if (s_enforce_cache[i].expires <
				    s_enforce_cache[oldest].expires)
					oldest = i;
			}
			L7_WARN("enforce_cache full — evicting oldest "
			    "table=%s ip=%s",
			    s_enforce_cache[oldest].table,
			    s_enforce_cache[oldest].ip);
			layer7_pf_exec_table_delete(
			    s_enforce_cache[oldest].table,
			    s_enforce_cache[oldest].ip);
			s_enforce_cache[oldest] =
			    s_enforce_cache[--s_n_enforce_cache];
		}
		if (s_n_enforce_cache >= L7_DST_CACHE_MAX) {
			L7_WARN("enforce_cache still full after eviction — "
			    "skip add table=%s ip=%s", table, ip);
			return;
		}
	}
	snprintf(s_enforce_cache[s_n_enforce_cache].table,
	    sizeof(s_enforce_cache[0].table), "%s", table);
	snprintf(s_enforce_cache[s_n_enforce_cache].ip,
	    sizeof(s_enforce_cache[0].ip), "%s", ip);
	s_enforce_cache[s_n_enforce_cache].expires = expires;
	s_n_enforce_cache++;
}

static void
enforce_cache_sweep(void)
{
	time_t now = time(NULL);
	int i;

	if (now - s_last_enforce_cache_sweep < L7_DST_SWEEP_SEC)
		return;
	s_last_enforce_cache_sweep = now;

	for (i = 0; i < s_n_enforce_cache; ) {
		if (s_enforce_cache[i].expires < now) {
			layer7_pf_exec_table_delete(s_enforce_cache[i].table,
			    s_enforce_cache[i].ip);
			s_enforce_cache[i] =
			    s_enforce_cache[--s_n_enforce_cache];
		} else
			i++;
	}
	allow_cache_sweep();
}

static void
enforce_cache_flush(void)
{
	int i;

	for (i = 0; i < s_n_enforce_cache; i++) {
		layer7_pf_exec_table_delete(s_enforce_cache[i].table,
		    s_enforce_cache[i].ip);
	}
	s_n_enforce_cache = 0;
	pf_table_flush_logged(L7_PF_TABLE_BLOCK_DST, "enforce_cache_flush");
}

/*
 * Esvazia todas as tabelas PF dinamicas controladas pelo Layer7
 * (Bloco 5): `layer7_block_dst`, `layer7_block`, `layer7_bld_*`,
 * `layer7_pdst_*`, `layer7_psrc_*`, `layer7_pallow_*`, `layer7_pexc_*` e
 * `layer7_allow_dst`.
 * Entradas estaticas IPv4/CIDR da allowlist sao repovoadas pelo pacote via
 * `layer7_dst_allowlist_apply_to_pf()` em filter reload / resync.
 */
static void
enforcement_flush_all_tables(void)
{
	int i, unavailable = 0;

	unavailable += pf_table_flush_try(L7_PF_TABLE_BLOCK_DST) != 0;
	unavailable += pf_table_flush_try(L7_PF_TABLE_BLOCK) != 0;

	for (i = 0; i < L7_BL_MAX_RULES; i++) {
		char tbl[32];

		snprintf(tbl, sizeof(tbl), "layer7_bld_%d", i);
		unavailable += pf_table_flush_try(tbl) != 0;
	}

	for (i = 0; i < L7_MAX_POLICIES; i++) {
		char tbl[32];

		snprintf(tbl, sizeof(tbl), "layer7_pdst_%d", i);
		unavailable += pf_table_flush_try(tbl) != 0;
		snprintf(tbl, sizeof(tbl), "layer7_psrc_%d", i);
		unavailable += pf_table_flush_try(tbl) != 0;
		snprintf(tbl, sizeof(tbl), "layer7_pallow_%d", i);
		unavailable += pf_table_flush_try(tbl) != 0;
		snprintf(tbl, sizeof(tbl), "layer7_pexc_%d", i);
		unavailable += pf_table_flush_try(tbl) != 0;
	}

	unavailable += pf_table_flush_try(L7_PF_TABLE_ALLOW_DST) != 0;
	s_n_enforce_cache = 0;
	s_n_allow_cache = 0;
	if (unavailable > 0)
		L7_DBG("flush_all: %d tabelas ausentes/indisponiveis",
		    unavailable);
}

static int layer7_pf_add_with_selfheal(const char *table, const char *ip,
    const char *reason);

static void
layer7_apply_policy_allow_enforcement(const struct layer7_decision *dec,
    const char *src_ip, const char *dst_ip, uint32_t ttl,
    const char *reason)
{
	char tbl[64];
	int r;

	if (!dec || !enforce_armed() ||
	    dec->action != LAYER7_ACTION_ALLOW ||
	    dec->reason != L7_DECIDE_POLICY_MATCH ||
	    dec->policy_table_idx < 0 ||
	    !dst_ip || !layer7_pf_host_enforce_ok(dst_ip))
		return;
	if (layer7_pf_policy_allow_table_name(dec->policy_table_idx, tbl,
	    sizeof(tbl)) < 0)
		return;

	r = layer7_pf_add_with_selfheal(tbl, dst_ip, reason);
	if (r == 0) {
		enforce_cache_add(tbl, dst_ip, ttl);
		L7_AUDIT_NOTE(NULL,
		    "enforce_allow: table=%s src=%s dst=%s policy=%s reason=%s",
		    tbl, src_ip ? src_ip : "-", dst_ip,
		    dec->matched_policy_id[0] ?
		    dec->matched_policy_id : "-",
		    reason ? reason : "-");
	} else {
		L7_WARN("enforce_allow: pfctl add failed table=%s dst=%s "
		    "policy=%s", tbl, dst_ip,
		    dec->matched_policy_id[0] ?
		    dec->matched_policy_id : "-");
	}
}

static void
layer7_apply_block_enforcement(const struct layer7_decision *dec,
    const char *src_ip, const char *dst_ip, uint32_t ttl,
    int scoped_hybrid, const char *reason)
{
	char tbl[64];
	const char *ip;
	int r;

	if (!dec || !enforce_armed())
		return;

	r = layer7_pf_resolve_block_target(dec, src_ip, dst_ip, scoped_hybrid,
	    tbl, sizeof(tbl), &ip);
	if (r <= 0)
		return;

	if (ip_is_local_iface_addr(ip)) {
		/*
		 * DNS sinkhole entrega o IP do portal/firewall ao cliente. Registe
		 * a decisao DNS uma vez como auditoria; os fluxos seguintes para o
		 * portal sao esperados e nao podem poluir o log operacional.
		 */
		if (reason && strcmp(reason, "dns_block") == 0) {
			L7_AUDIT_NOTE(NULL,
			    "enforce_block: outcome=sinkhole kind=%s table=%s "
			    "src=%s dst=%s ip=%s policy=%s reason=dns_sinkhole",
			    layer7_enforce_kind_str(dec->enforce_kind), tbl,
			    src_ip ? src_ip : "-", dst_ip ? dst_ip : "-", ip,
			    dec->matched_policy_id[0] ? dec->matched_policy_id : "-");
		} else {
			L7_DBG("enforce_block: skip local sinkhole/portal ip=%s "
			    "policy=%s reason=%s", ip,
			    dec->matched_policy_id[0] ? dec->matched_policy_id : "-",
			    reason ? reason : "-");
		}
		return;
	}

	r = layer7_pf_add_with_selfheal(tbl, ip, reason);
	if (r == 0) {
		int kill_r = 0;

		s_pf_dst_add_ok++;
		allow_cache_revoke_ip(ip);
		enforce_cache_add(tbl, ip, ttl);
		/*
		 * A decisão ocorre depois de o PF criar estado. Remover somente
		 * o estado afectado torna o bloqueio imediato sem reload global.
		 * psrc é quarentena explícita; nesse caso encerra todos os
		 * estados do host. legacy_global encerra estados para o destino.
		 */
		if (dec->reason == L7_DECIDE_EXCEPTION &&
		    strcmp(tbl, L7_PF_TABLE_BLOCK) == 0)
			kill_r = layer7_pf_exec_kill_states_host(src_ip);
		else if (scoped_hybrid &&
		    dec->enforce_kind == L7_ENFORCE_SRC_SCOPED)
			kill_r = layer7_pf_exec_kill_states_host(src_ip);
		else if (!scoped_hybrid)
			kill_r = layer7_pf_exec_kill_states_to(dst_ip);
		else
			kill_r = layer7_pf_exec_kill_state_pair(src_ip, dst_ip);
		if (kill_r != 0)
			L7_WARN("enforce_block: state kill failed kind=%s "
			    "src=%s dst=%s policy=%s",
			    layer7_enforce_kind_str(dec->enforce_kind),
			    src_ip ? src_ip : "-", dst_ip ? dst_ip : "-",
			    dec->matched_policy_id[0] ?
			    dec->matched_policy_id : "-");
		L7_AUDIT_NOTE(NULL,
		    "enforce_block: kind=%s table=%s src=%s dst=%s ip=%s "
		    "policy=%s reason=%s",
		    layer7_enforce_kind_str(dec->enforce_kind), tbl,
		    src_ip ? src_ip : "-", dst_ip ? dst_ip : "-", ip,
		    dec->matched_policy_id[0] ?
		    dec->matched_policy_id : "-",
		    reason ? reason : "-");
	} else {
		s_pf_dst_add_fail++;
		L7_WARN("enforce_block: pfctl add failed kind=%s table=%s "
		    "ip=%s policy=%s",
		    layer7_enforce_kind_str(dec->enforce_kind), tbl, ip,
		    dec->matched_policy_id[0] ?
		    dec->matched_policy_id : "-");
	}
}

static int
bl_rule_matches_domain(const struct l7_bl_rule *rule, const char *domain,
    const char **matched_cat)
{
	const char *cats[L7_BL_MAX_CATS];
	int i;

	if (!rule || !domain || rule->n_categories <= 0)
		return 0;
	for (i = 0; i < rule->n_categories && i < L7_BL_MAX_CATS; i++)
		cats[i] = rule->categories[i];
	return l7_blacklist_lookup_categories(s_blacklist, domain, cats, i,
	    matched_cat);
}

/*
 * Verifica se src_ip pertence a algum dos src_cidrs da regra blacklist.
 */
static int
bl_rule_matches_src(const struct l7_bl_rule *rule, const char *src_ip)
{
	return l7_bl_rule_matches_src(rule, src_ip);
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

/* Aceita IPv4/IPv6 host ou CIDR — usado para popular `layer7_allow_dst`.
 * Delegado a layer7_pf_table_entry_ok (inet_pton + S-03). */
static int
pf_entry_strict_ok(const char *entry)
{
	return layer7_pf_table_entry_ok(entry);
}

static int
pf_table_add_entry(const char *table, const char *entry)
{
	if (!layer7_pf_table_name_ok(table) || !pf_entry_strict_ok(entry))
		return -1;
	return layer7_pf_exec_table_add_entry(table, entry);
}

static void
pf_table_flush(const char *table)
{
	pf_table_flush_logged(table, "pf_table_flush");
}

/*
 * (Re)carrega a allowlist de destinos (Bloco 3):
 *   1. reset estrutura
 *   2. carrega seed embutido (`/usr/local/etc/layer7/allowlist-seed.txt`)
 *   3. acrescenta entradas do JSON activo (`layer7.dst_allowlist`)
 *   4. popula a tabela PF `layer7_allow_dst` com host/CIDR IPv4+IPv6
 *      (entradas de dominio sao adicionadas em runtime quando o cliente
 *      resolver o dominio via DNS).
 */
static void
reload_allowlist(const char *json, size_t len)
{
	int seed_n, json_n, i, ok_n = 0;

	l7_allowlist_reset(&s_allowlist);
	seed_n = l7_allowlist_load_seed_file(&s_allowlist,
	    L7_ALLOWLIST_SEED_PATH);
	json_n = json ? l7_allowlist_parse_json(&s_allowlist, json, len) : 0;

	pf_table_flush(L7_PF_TABLE_ALLOW_DST);
	for (i = 0; i < s_allowlist.n; i++) {
		const struct l7_allowlist_entry *e = &s_allowlist.entries[i];
		if (e->kind == L7_AL_DOMAIN)
			continue;
		if (pf_table_add_entry(L7_PF_TABLE_ALLOW_DST,
		    e->value) == 0)
			ok_n++;
	}
	L7_NOTE("allowlist: seed=%d json=%d total=%d pf_add_ok=%d",
	    seed_n, json_n, s_allowlist.n, ok_n);
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

/*
 * BG-103: carregar rules.debug sem TOCTOU — open+O_NOFOLLOW+fstat,
 * depois pfctl -f - no mesmo fd (stdin).
 */
static int
pf_load_rules_debug_trusted(const char *path)
{
	int fd = -1;
	int flags;
	int status;
	int dn;
	struct stat st;
	pid_t pid;

	if (!path || !*path)
		return 0;
	flags = O_RDONLY | O_CLOEXEC;
#ifdef O_NOFOLLOW
	flags |= O_NOFOLLOW;
#endif
	fd = open(path, flags);
	if (fd < 0) {
		if (errno == ELOOP || errno == EMLINK)
			L7_WARN("pf_selfheal: refusing symlink %s", path);
		return 0;
	}
	if (fstat(fd, &st) != 0) {
		close(fd);
		return 0;
	}
	if (!S_ISREG(st.st_mode) || st.st_uid != 0 ||
	    (st.st_mode & (S_IWGRP | S_IWOTH)) != 0) {
		L7_WARN("pf_selfheal: refusing untrusted %s", path);
		close(fd);
		return 0;
	}

	pid = fork();
	if (pid < 0) {
		close(fd);
		return 0;
	}
	if (pid == 0) {
		if (dup2(fd, STDIN_FILENO) < 0)
			_exit(127);
		if (fd > STDERR_FILENO)
			close(fd);
		dn = open("/dev/null", O_WRONLY | O_CLOEXEC);
		if (dn >= 0) {
			(void)dup2(dn, STDOUT_FILENO);
			(void)dup2(dn, STDERR_FILENO);
			if (dn > STDERR_FILENO)
				close(dn);
		}
		execl("/sbin/pfctl", "pfctl", "-f", "-", (char *)NULL);
		_exit(127);
	}
	close(fd);
	for (;;) {
		if (waitpid(pid, &status, 0) >= 0)
			break;
		if (errno != EINTR)
			return 0;
	}
	return (WIFEXITED(status) && WEXITSTATUS(status) == 0) ? 1 : 0;
}

static int
layer7_pf_selfheal(const char *required_table, const char *reason)
{
	time_t now;
	int ready;
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

	ready = required_table ? pf_table_exists(required_table) :
	    pf_base_tables_ok();
	if (!ready) {
		did_force = pf_load_rules_debug_trusted(L7_PF_RULES_DEBUG_PATH);
		if (!did_force && access(L7_PF_RULES_DEBUG_PATH, R_OK) == 0) {
			L7_WARN("pf_selfheal: refusing untrusted %s",
			    L7_PF_RULES_DEBUG_PATH);
		}
	}

	ready = required_table ? pf_table_exists(required_table) :
	    pf_base_tables_ok();
	if (ready) {
		L7_NOTE("pf_selfheal: success table=%s reason=%s fallback=%d",
		    required_table ? required_table : "base",
		    reason ? reason : "-", did_force ? 1 : 0);
		return 1;
	}
	L7_WARN("pf_selfheal: failed table=%s reason=%s fallback=%d",
	    required_table ? required_table : "base",
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
	if (!layer7_pf_selfheal(table, reason))
		return -1;
	r = layer7_pf_exec_table_add(table, ip);
	if (r == 0)
		return 0;
	return -1;
}

static void
bl_flush_rule_tables(void)
{
	char tbl[32];
	int i, unavailable = 0;

	for (i = 0; i < L7_BL_MAX_RULES; i++) {
		snprintf(tbl, sizeof(tbl), "layer7_bld_%d", i);
		unavailable += pf_table_flush_try(tbl) != 0;
	}
	if (unavailable > 0)
		L7_DBG("bl_reload: %d tabelas ausentes/indisponiveis",
		    unavailable);
}

static int
enforcement_is_scoped_hybrid(void)
{
	return s_parsed.has_enforcement_model &&
	    strcmp(s_parsed.enforcement_model, "scoped_hybrid") == 0;
}

static void
layer7_on_dns_resolved(const char *iface, const char *client_ip,
    const char *domain, const char *resolved_ip, uint32_t ttl)
{
	struct layer7_decision dec;
	int scoped;
	int dom_allow, ip_allow;

	if (!s_have_parse || cfg_disabled(&s_parsed) || !enforce_armed())
		return;

	L7_EVENT_DBG(iface,
	    "dns_resolved: iface=%s client=%s domain=%s ip=%s ttl=%u",
	    iface ? iface : "-", client_ip ? client_ip : "-",
	    domain ? domain : "-", resolved_ip ? resolved_ip : "-", ttl);

	scoped = enforcement_is_scoped_hybrid();
	memset(&dec, 0, sizeof(dec));
	(void)layer7_decide_for_client(s_exc, s_nx, s_rules, s_np,
	    enforce_armed(), iface, client_ip, domain, NULL, NULL, &dec);
	if (resolved_ip && *resolved_ip) {
		strncpy(dec.enforce_dst_ip, resolved_ip,
		    sizeof(dec.enforce_dst_ip) - 1);
		dec.enforce_dst_ip[sizeof(dec.enforce_dst_ip) - 1] = '\0';
	}
	/* Politica block prevalece sobre allowlist (ex.: youtube.com na seed). */
	if (dec.would_enforce_block_or_tag &&
	    dec.action == LAYER7_ACTION_BLOCK &&
	    resolved_ip && *resolved_ip &&
	    layer7_pf_host_enforce_ok(resolved_ip)) {
		layer7_apply_block_enforcement(&dec, client_ip,
		    resolved_ip, ttl, scoped, "dns_block");
		return;
	}

	/* Allowlist gate (Bloco 3): dominio/IP na lista branca → layer7_allow_dst. */
	dom_allow = (domain && *domain &&
	    l7_allowlist_contains_domain(&s_allowlist, domain));
	ip_allow = (resolved_ip && *resolved_ip &&
	    l7_allowlist_contains_ip(&s_allowlist, resolved_ip));
	if (dom_allow || ip_allow) {
		if (resolved_ip && *resolved_ip &&
		    layer7_pf_host_enforce_ok(resolved_ip)) {
			if (pf_table_add_entry(L7_PF_TABLE_ALLOW_DST,
			    resolved_ip) == 0)
				allow_cache_add(resolved_ip, ttl);
		}
		s_allow_hits++;
		L7_EVENT_INFO(iface,
		    "allowlist_dns: iface=%s domain=%s ip=%s "
		    "(%s) — pf_pass",
		    iface ? iface : "-", domain ? domain : "-",
		    resolved_ip ? resolved_ip : "-",
		    dom_allow ? "domain" : "ip");
		return;
	}

	/* Allow explicita de politica/excepcao prevalece sobre a blacklist.
	 * O default allow continua a consultar as categorias UT1. */
	if (layer7_decision_is_explicit_allow(&dec)) {
		layer7_apply_policy_allow_enforcement(&dec, client_ip,
		    resolved_ip, ttl, "dns_allow");
		L7_EVENT_DBG(iface,
		    "blacklist_bypass: iface=%s domain=%s client=%s "
		    "reason=%s",
		    iface ? iface : "-", domain ? domain : "-",
		    client_ip ? client_ip : "-",
		    layer7_decide_reason_str(dec.reason));
		return;
	}

	if (s_blacklist && s_bl_n_rules > 0 && resolved_ip &&
	    layer7_pf_host_enforce_ok(resolved_ip) &&
	    !ip_is_local_iface_addr(resolved_ip)) {
		int ri;

		s_bl_lookups++;
		for (ri = 0; ri < s_bl_n_rules; ri++) {
			char tbl[32];
			const char *matched_cat = NULL;
			if (!s_bl_rules[ri].enabled)
				continue;
			if (!bl_rule_matches_src(&s_bl_rules[ri], client_ip))
				continue;
			if (!bl_rule_matches_domain(&s_bl_rules[ri], domain,
			    &matched_cat))
				continue;
			s_bl_hits++;
			s_bl_dns_hits++;
			snprintf(tbl, sizeof(tbl), "layer7_bld_%d", ri);
			if (layer7_pf_add_with_selfheal(tbl, resolved_ip,
			    "dns_blacklist_rule") == 0) {
				s_pf_dst_add_ok++;
				enforce_cache_add(tbl, resolved_ip, ttl);
				if (s_bl_rules[ri].n_src_cidrs > 0)
					(void)layer7_pf_exec_kill_state_pair(
					    client_ip, resolved_ip);
				else
					(void)layer7_pf_exec_kill_states_to(
					    resolved_ip);
				L7_AUDIT_NOTE(iface,
				    "bl_block: iface=%s domain=%s "
				    "cat=%s client=%s ip=%s rule=%d/%s "
				    "table=%s", iface ? iface : "-",
				    domain, matched_cat ? matched_cat : "-",
				    client_ip ? client_ip : "-", resolved_ip,
				    ri, s_bl_rules[ri].name, tbl);
			} else {
				s_pf_dst_add_fail++;
			}
		}
	} else if (resolved_ip && *resolved_ip &&
	    !layer7_pf_host_enforce_ok(resolved_ip)) {
		L7_WARN("dns_blacklist: skip invalid resolved ip=%s domain=%s",
		    resolved_ip, domain ? domain : "-");
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

	L7_EVENT_INFO(iface,
	    "dns_query: iface=%s src=%s resolver=%s qname=%s",
	    iface ? iface : "-", src_ip, resolver_ip ? resolver_ip : "-",
	    qname);
}

/*
 * Chamado pelo loop quando nDPI classificar um fluxo (origem + app/cat).
 * mode=enforce + block scoped_hybrid → pdst (destino) ou psrc (origem).
 * mode=enforce + block legacy_global → dst_ip em layer7_block_dst.
 * mode=enforce + tag → src_ip em layer7_tagged.
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
	/*
	 * Nomes bloqueados por DNS sinkhole resolvem para o proprio firewall.
	 * Esse trafego e destinado ao portal/GUI local, nunca deve voltar ao
	 * motor de policy nem produzir uma classificacao enganosa (por exemplo,
	 * host DoH + app SSH). A decisao DNS ja foi auditada no callback DNS.
	 */
	if (dst_ip && ip_is_local_iface_addr(dst_ip)) {
		L7_DBG("flow_skip: local sinkhole/portal iface=%s src=%s dst=%s "
		    "host=%s app=%s",
		    iface ? iface : "-", src_ip, dst_ip,
		    host ? host : "-", ndpi_app ? ndpi_app : "-");
		return;
	}
	layer7_flow_decide(s_exc, s_nx, s_rules, s_np, enforce_armed(),
	    iface, src_ip, ndpi_app, ndpi_cat, host, &dec);

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
		if (dec.action == LAYER7_ACTION_BLOCK)
			L7_AUDIT_NOTE(iface,
			    "flow_decide: iface=%s src=%s dst=%s host=%s "
			    "app=%s cat=%s action=%s reason=%s policy=%s",
			    iface ? iface : "-", src_ip,
			    dst_ip ? dst_ip : "-", host ? host : "-",
			    ndpi_app ? ndpi_app : "(null)",
			    ndpi_cat ? ndpi_cat : "(null)",
			    layer7_action_str(dec.action),
			    layer7_decide_reason_str(dec.reason),
			    dec.matched_policy_id[0] ?
			    dec.matched_policy_id : "-");
		else
			L7_EVENT_NOTE(iface,
			    "flow_decide: iface=%s src=%s dst=%s host=%s "
			    "app=%s cat=%s action=%s reason=%s policy=%s",
			    iface ? iface : "-", src_ip,
			    dst_ip ? dst_ip : "-", host ? host : "-",
			    ndpi_app ? ndpi_app : "(null)",
			    ndpi_cat ? ndpi_cat : "(null)",
			    layer7_action_str(dec.action),
			    layer7_decide_reason_str(dec.reason),
			    dec.matched_policy_id[0] ?
			    dec.matched_policy_id : "-");
	} else {
		L7_EVENT_DBG(iface,
		    "flow_decide: iface=%s src=%s dst=%s host=%s app=%s cat=%s action=%s "
		    "reason=%s",
		    iface ? iface : "-",
		    src_ip, dst_ip ? dst_ip : "-",
		    host ? host : "-",
		    ndpi_app ? ndpi_app : "(null)",
		    ndpi_cat ? ndpi_cat : "(null)",
		    layer7_action_str(dec.action),
		    layer7_decide_reason_str(dec.reason));
	}

	if (!enforce_armed())
		return;

	if (layer7_decision_is_explicit_allow(&dec) && dst_ip &&
	    layer7_pf_host_enforce_ok(dst_ip)) {
		layer7_apply_policy_allow_enforcement(&dec, src_ip, dst_ip,
		    L7_DST_TTL_DEF, "flow_allow");
	}

	if (dec.action == LAYER7_ACTION_BLOCK) {
		/* src_scoped e executavel quando a regra tem origem estatica,
		 * scope_global explicito ou quarentena dinamica da origem. */
		if (enforcement_is_scoped_hybrid() &&
		    dec.enforce_kind == L7_ENFORCE_SRC_SCOPED) {
			if ((dec.quarantine_origin || dec.source_scoped ||
			    dec.scope_global) &&
			    layer7_pf_host_enforce_ok(src_ip)) {
				layer7_apply_block_enforcement(&dec, src_ip,
				    dst_ip, L7_DST_TTL_DEF, 1,
				    "flow_block_psrc");
			} else {
				L7_NOTE("flow_block: src-scoped policy=%s sem "
				    "origem/scope_global/quarantine — skip psrc "
				    "src=%s app=%s",
				    dec.matched_policy_id[0] ?
				    dec.matched_policy_id : "-",
				    src_ip,
				    ndpi_app ? ndpi_app : "-");
			}
		} else if (dst_ip && layer7_pf_host_enforce_ok(dst_ip)) {
			/* Allowlist gate (Bloco 3): nunca bloquear destinos
			 * da lista branca (SNI/host ou IP/CIDR), excepto quando
			 * a politica manual impoe block (prevalece). */
			int policy_block = (dec.reason == L7_DECIDE_POLICY_MATCH);
			int dom_allow = (host && *host &&
			    l7_allowlist_contains_domain(&s_allowlist, host));
			int ip_allow =
			    l7_allowlist_contains_ip(&s_allowlist, dst_ip);

			if (!policy_block && (dom_allow || ip_allow)) {
				if (dom_allow)
					(void)pf_table_add_entry(
					    L7_PF_TABLE_ALLOW_DST, dst_ip);
				s_allow_hits++;
				L7_EVENT_NOTE(iface,
				    "allowlist_flow: iface=%s host=%s "
				    "dst=%s (%s) — skip block policy=%s",
				    iface ? iface : "-", host ? host : "-",
				    dst_ip, dom_allow ? "domain" : "ip",
				    dec.matched_policy_id[0] ?
				    dec.matched_policy_id : "-");
			} else {
				layer7_apply_block_enforcement(&dec, src_ip,
				    dst_ip, L7_DST_TTL_DEF,
				    enforcement_is_scoped_hybrid(), "flow_block");
			}
		}
	}

		/* SNI/host blacklist check (Melhoria B) — apos decisao de politica manual */
		if (s_blacklist && s_bl_n_rules > 0 && host && *host &&
		    dec.action != LAYER7_ACTION_BLOCK && dst_ip &&
		    layer7_pf_host_enforce_ok(dst_ip) &&
		    !ip_is_local_iface_addr(dst_ip) &&
		    !layer7_decision_is_explicit_allow(&dec) &&
		    !l7_allowlist_contains_domain(&s_allowlist, host) &&
		    !l7_allowlist_contains_ip(&s_allowlist, dst_ip)) {
			int ri;

			s_bl_lookups++;
			for (ri = 0; ri < s_bl_n_rules; ri++) {
				char tbl[32];
				const char *matched_cat = NULL;
				if (!s_bl_rules[ri].enabled)
					continue;
				if (!bl_rule_matches_src(&s_bl_rules[ri], src_ip))
					continue;
				if (!bl_rule_matches_domain(&s_bl_rules[ri],
				    host, &matched_cat))
					continue;
				s_bl_hits++;
				s_bl_sni_hits++;
				snprintf(tbl, sizeof(tbl), "layer7_bld_%d", ri);
				r = layer7_pf_add_with_selfheal(tbl, dst_ip,
				    "sni_blacklist");
				if (r == 0) {
					s_pf_dst_add_ok++;
					enforce_cache_add(tbl, dst_ip,
					    L7_DST_TTL_DEF);
					if (s_bl_rules[ri].n_src_cidrs > 0)
						(void)layer7_pf_exec_kill_state_pair(
						    src_ip, dst_ip);
					else
						(void)layer7_pf_exec_kill_states_to(
						    dst_ip);
					L7_AUDIT_NOTE(iface,
					    "sni_bl_block: iface=%s "
					    "host=%s cat=%s src=%s dst=%s "
					    "rule=%d/%s table=%s",
					    iface ? iface : "-", host,
					    matched_cat ? matched_cat : "-",
					    src_ip, dst_ip, ri,
					    s_bl_rules[ri].name, tbl);
				} else {
					s_pf_dst_add_fail++;
				}
			}
		}

	if (dec.action == LAYER7_ACTION_TAG) {
		r = -1;
		if (dec.would_enforce_block_or_tag && dec.pf_table[0] &&
		    layer7_pf_host_enforce_ok(src_ip)) {
			if (layer7_pf_add_with_selfheal(dec.pf_table, src_ip,
			    "flow_tag_src") == 0)
				r = 1;
		}
		if (r == 1) {
			s_pf_table_add_ok++;
			L7_EVENT_INFO(iface,
			    "enforce_tag: iface=%s src=%s table=%s policy=%s",
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
run_enforce_once_cli(const char *path, const char *ip, const char *dst_ip,
    const char *app, const char *cat, int dry)
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
		layer7_policies_expand_exclude_groups(rules, np, grps, ng);
	}
	layer7_policies_sort(rules, np);
	layer7_exceptions_sort(exc, nx);
	ge = p.has_mode && strcmp(p.mode, "enforce") == 0;
	{
		int scoped = p.has_enforcement_model &&
		    strcmp(p.enforcement_model, "scoped_hybrid") == 0;
		char tbl[64];
		const char *enforce_ip;

		free(buf);

		layer7_flow_decide(exc, nx, rules, np, ge, NULL, ip, app, cat,
		    NULL, &dec);
		printf(
		    "enforce-once: action=%s reason=%s would_enforce=%d "
		    "kind=%s idx=%d policy=%s\n",
		    layer7_action_str(dec.action),
		    layer7_decide_reason_str(dec.reason),
		    dec.would_enforce_block_or_tag,
		    layer7_enforce_kind_str(dec.enforce_kind),
		    dec.policy_table_idx,
		    dec.matched_policy_id[0] ? dec.matched_policy_id :
		    "(none)");

		r = layer7_pf_enforce_decision(&dec, ip, dst_ip, scoped, dry);
		if (r == -1) {
			fprintf(stderr,
			    "layer7d: pfctl add failed (policy=%s ip=%s)\n",
			    dec.matched_policy_id[0] ? dec.matched_policy_id :
			    "-", ip);
			return 1;
		}
		if (dec.action == LAYER7_ACTION_BLOCK &&
		    layer7_pf_resolve_block_target(&dec, ip, dst_ip, scoped, tbl,
		    sizeof(tbl), &enforce_ip) == 1) {
			if (dry)
				printf("dry-run: pfctl -t %s -T add %s\n", tbl,
				    enforce_ip);
			else if (r == 1)
				printf("pfctl add ok: kind=%s table=%s ip=%s "
				    "policy=%s\n",
				    layer7_enforce_kind_str(dec.enforce_kind),
				    tbl, enforce_ip,
				    dec.matched_policy_id[0] ?
				    dec.matched_policy_id : "-");
		} else if (dec.action == LAYER7_ACTION_TAG &&
		    dec.would_enforce_block_or_tag && dec.pf_table[0] &&
		    layer7_pf_host_enforce_ok(ip)) {
			if (dry)
				printf("dry-run: pfctl -t %s -T add %s\n",
				    dec.pf_table, ip);
			else if (r == 1)
				printf("pfctl add ok: table=%s ip=%s\n",
				    dec.pf_table, ip);
		} else
			printf("no pf table add (monitor/allow or mode!=enforce)\n");
		return 0;
	}
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
	    "usage: layer7d [-V] [-t] [-c path] [-d DST] [-e IP APP [CAT]] [-n] "
	    "[--list-protos]\n"
	    "               [--fingerprint] [--activate KEY [URL]]\n"
	    "               [--check-in [URL]] [--license-status] [--ldap-test]\n"
	    "  -V               versão do binário\n"
	    "  -t               testa JSON (stdout)\n"
	    "  -c path          caminho (omissão: %s)\n"
	    "  -d DST           destino IPv4 para o diagnóstico -e\n"
	    "  -e IP APP [CAT]  uma decisão + opcional pfctl add\n"
	    "  -n               com -e: não executar pfctl (dry)\n"
	    "  --list-protos    lista protocolos e categorias nDPI em JSON\n"
	    "  --fingerprint    mostra o hardware ID desta máquina\n"
	    "  --activate KEY   activa licença online (KEY + URL opcional)\n"
	    "  --check-in [URL] força check-in online (BG-077)\n"
	    "  --license-status estado da licença em chave=valor (exit 0 se válida)\n"
	    "  --ldap-test      testa bind+Base DN LDAP (JSON; sem passwords)\n"
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
	/* Ponto de decisão 1: cruzamento gate_a ∩ gate_b (não só s_lic.valid). */
	if (ge && !layer7_license_allows_enforce(&s_lic))
		ge = 0;
	/* Ponto de decisão 2: reconfirmação redundante legível (GA6.2). */
	if (ge && !layer7_license_gate_a(&s_lic))
		ge = 0;
	if (ge && !layer7_license_gate_b(&s_lic))
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
	sync_local_log_cfg(&p);
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
			layer7_policies_expand_exclude_groups(rules, np, grps, ng);
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
				    layer7_pf_host_enforce_ok(srcs[a])) {
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
				layer7_policies_expand_exclude_groups(tmp_r, tn,
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
	if (use_syslog)
		reload_allowlist(buf, len);
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
		L7_NOTE("logging: detailed=%d event_ifaces=%d max_mb=%d "
		    "rotated=%d",
		    s_event_log_enabled, s_n_event_interfaces,
		    s_log_file_max_mb, s_log_file_keep);
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
		/* BG-104: seed allowlist de resolvers (local + config). */
		layer7_capture_dns_resolvers_reset();
		for (i = 0; i < s_parsed.n_interfaces; i++)
			layer7_capture_dns_resolvers_seed_iface(
			    s_parsed.interfaces[i]);
		for (i = 0; i < s_parsed.n_dns_observe_resolvers; i++)
			(void)layer7_capture_dns_resolvers_add(
			    s_parsed.dns_observe_resolvers[i]);
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
			layer7_capture_set_sni(c, s_parsed.sni_inspection);
			s_captures[s_n_captures++] = c;
			L7_NOTE("capture: opened %s (nDPI active, sni_inspection=%d)",
			    s_parsed.interfaces[i], s_parsed.sni_inspection);
		}
	}
	if (s_n_captures == 0)
		L7_WARN("capture: no interfaces opened — nDPI disabled");
}

static void
aggregate_capture_stats(void)
{
	int i;
	unsigned long long pkts = 0, active = 0, cl = 0, ex = 0;
	unsigned long long evicted = 0, dropped = 0;
	unsigned long long pkts4 = 0, pkts6 = 0, act4 = 0, act6 = 0;
	unsigned long long cl4 = 0, cl6 = 0;

	for (i = 0; i < s_n_captures; i++) {
		unsigned long long p, a, c, e, v, d;
		unsigned long long p4, p6, a4, a6, c4, c6;

		layer7_capture_stats(s_captures[i], &p, &a, &c, &e, &v, &d);
		layer7_capture_stats_af(s_captures[i], &p4, &p6, &a4, &a6,
		    &c4, &c6);
		pkts += p;
		active += a;
		cl += c;
		ex += e;
		evicted += v;
		dropped += d;
		pkts4 += p4;
		pkts6 += p6;
		act4 += a4;
		act6 += a6;
		cl4 += c4;
		cl6 += c6;
	}
	s_cap_pkts = pkts;
	s_cap_pkts_v4 = pkts4;
	s_cap_pkts_v6 = pkts6;
	s_cap_flows_active = active;
	s_cap_flows_active_v4 = act4;
	s_cap_flows_active_v6 = act6;
	s_cap_flows_classified = cl;
	s_cap_flows_classified_v4 = cl4;
	s_cap_flows_classified_v6 = cl6;
	s_cap_flows_expired = ex;
	s_cap_flows_evicted = evicted;
	s_cap_flows_dropped = dropped;
	s_cap_interfaces = s_n_captures;
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
	const char *enforce_dst = NULL;

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
		if (strcmp(argv[vi], "--check-in") == 0) {
			const char *url = NULL;
			int result;

			if (vi + 1 < argc && argv[vi + 1][0] != '-')
				url = argv[vi + 1];
			result = layer7_check_in(url);
			if (result == L7_CHECKIN_OK)
				return 0;
			if (result == L7_CHECKIN_SKIP)
				return 2;
			return 1;
		}
		/*
		 * BG-032 (Bloco 6): CLI `--license-status` para inspeccao
		 * sem precisar de syslog. Imprime estado da licenca actual
		 * em formato chave=valor (compativel com `awk -F=`).
		 * Retorna 0 se valida (incl. grace) e 1 se invalida/ausente.
		 */
		if (strcmp(argv[vi], "--license-status") == 0) {
			struct l7_license_info li;

			memset(&li, 0, sizeof(li));
			(void)layer7_license_check(&li);
			printf("valid=%d\n", li.valid ? 1 : 0);
			printf("expired=%d\n", li.expired ? 1 : 0);
			printf("grace=%d\n", li.grace ? 1 : 0);
			printf("dev_mode=%d\n", li.dev_mode ? 1 : 0);
			printf("days_left=%d\n", li.days_left);
			printf("hardware_id=%s\n",
			    li.hardware_id[0] ? li.hardware_id : "");
			printf("customer=%s\n",
			    li.customer[0] ? li.customer : "");
			printf("expiry=%s\n", li.expiry[0] ? li.expiry : "");
			printf("features=%s\n",
			    li.features[0] ? li.features : "");
			printf("features_flags=0x%x\n", li.features_flags);
			printf("features_truncated=%d\n",
			    li.features_truncated ? 1 : 0);
			printf("clock_suspect=%d\n",
			    li.clock_suspect ? 1 : 0);
			printf("clock_max_seen=%lld\n",
			    (long long)li.clock_max_seen);
			printf("clock_delta_sec=%ld\n", li.clock_delta_sec);
			if (li.error[0])
				printf("error=%s\n", li.error);
			return li.valid ? 0 : 1;
		}
		/*
		 * 20.18: teste LDAP para GUI. JSON sem passwords/secrets.
		 * Exit 0 se OK, 1 se falha, 2 se OpenLDAP ausente/config incompleta.
		 */
		if (strcmp(argv[vi], "--ldap-test") == 0) {
			char *buf = NULL;
			size_t len = 0;
			struct l7_ldap_cfg cfg;
			struct l7_ldap_test_result tr;
			const char *cpath = DEFAULT_CONFIG;
			int i2;

			for (i2 = 1; i2 < argc; i2++) {
				if (strcmp(argv[i2], "-c") == 0 &&
				    i2 + 1 < argc) {
					cpath = argv[i2 + 1];
					break;
				}
			}
			layer7_ldap_cfg_defaults(&cfg);
			buf = read_file(cpath, &len);
			if (buf != NULL) {
				(void)layer7_ldap_cfg_parse_json(buf, len,
				    &cfg);
				free(buf);
			}
			(void)layer7_ldap_cfg_load_secret(&cfg, NULL);
			memset(&tr, 0, sizeof(tr));
			(void)layer7_ldap_test_connection(&cfg, &tr);
			/* GI5.4: stderr sem secrets (servidor/porto/fase apenas). */
			if (tr.ok)
				fprintf(stderr,
				    "identity: ldap-test OK server=%s port=%d "
				    "tls=%d ms=%u\n",
				    tr.server, tr.port, tr.use_tls, tr.ms);
			else
				fprintf(stderr,
				    "identity: ldap-test FAIL phase=%s "
				    "server=%s port=%d\n",
				    tr.phase, tr.server[0] ? tr.server : "-",
				    tr.port);
			printf("{\"ok\":%s,\"phase\":\"%s\",\"server\":\"%s\","
			    "\"port\":%d,\"tls\":%s,\"base_ok\":%s,\"ms\":%u,"
			    "\"ldap_rc\":%d,\"message\":\"",
			    tr.ok ? "true" : "false", tr.phase, tr.server,
			    tr.port, tr.use_tls ? "true" : "false",
			    tr.base_ok ? "true" : "false", tr.ms, tr.ldap_rc);
			/* escape mínimo da mensagem */
			{
				const char *p;
				for (p = tr.message; *p; p++) {
					if (*p == '"' || *p == '\\')
						putchar('\\');
					if (*p == '\n' || *p == '\r')
						continue;
					putchar(*p);
				}
			}
			printf("\"}\n");
			layer7_ldap_cfg_wipe_secret(&cfg);
			if (tr.ok)
				return 0;
			if (strcmp(tr.phase, "config") == 0)
				return 2;
			return 1;
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
		if (strcmp(argv[i], "-d") == 0) {
			if (i + 1 >= argc ||
			    !layer7_pf_host_enforce_ok(argv[i + 1])) {
				fprintf(stderr,
				    "layer7d: -d requer destino IPv4 válido\n");
				return 1;
			}
			enforce_dst = argv[i + 1];
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
			return run_enforce_once_cli(config_path, ip, enforce_dst,
			    app, cat, enforce_dry_run);
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
	s_last_stats_write = s_boot_time;
	s_last_periodic_log = s_boot_time;

	openlog("layer7d", LOG_PID | LOG_CONS, LOG_DAEMON);
	syslog(LOG_NOTICE, "daemon_start version=%s", layer7d_version);

	/*
	 * Licenca ANTES do primeiro apply_config (QA D3): refresh_enforce_cfg()
	 * exige s_lic.valid; se a licenca viesse depois, o log inicial ficava
	 * enforce_cfg=0 e havia janela teorica sem enforce ate ao SIGHUP.
	 */
	memset(&s_lic, 0, sizeof(s_lic));
	s_last_lic_check = time(NULL);
	if (layer7_license_check(&s_lic) == 0) {
		s_license_state = (s_lic.grace || s_lic.dev_mode) ? 2 : 1;
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
	} else {
		s_license_state = 0;
		L7_WARN("license: INVALID — %s", s_lic.error);
		L7_WARN("license: enforce disabled, monitor-only mode");
		if (s_lic.clock_suspect) {
			L7_AUDIT_NOTE(NULL,
			    "license_clock_suspect: max_seen=%lld delta=%ld "
			    "— enforce degraded to monitor (30.6)",
			    (long long)s_lic.clock_max_seen,
			    s_lic.clock_delta_sec);
		}
	}

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

	if (s_license_state == 0) {
		s_ge = 0;
		enforcement_flush_all_tables();
	} else {
		refresh_enforce_cfg();
		if (s_have_parse && !cfg_disabled(&s_parsed))
			L7_NOTE("enforce_ready: enforce_cfg=%d mode=%s", s_ge,
			    s_parsed.has_mode ? s_parsed.mode : "?");
	}

	/* Load blacklists at startup (same logic as SIGHUP reload) */
	{
		struct l7_bl_config bl_cfg;
		memset(&bl_cfg, 0, sizeof(bl_cfg));
		if (l7_bl_config_load(L7_BL_DIR_DEFAULT "/config.json",
		    &bl_cfg) == 0
		    && bl_cfg.enabled && bl_cfg.n_rules > 0) {
			const char *all_cats[L7_BL_MAX_CATS];
			const char *bwl[L7_BL_WL_MAX];
			int all_n = 0, ri, ci, ai, found;

			for (ri = 0; ri < bl_cfg.n_rules; ri++) {
				for (ci = 0;
				    ci < bl_cfg.rules[ri].n_categories;
				    ci++) {
					found = 0;
					for (ai = 0; ai < all_n; ai++) {
						if (strcmp(all_cats[ai],
						    bl_cfg.rules[ri]
						    .categories[ci]) == 0) {
							found = 1;
							break;
						}
					}
					if (!found &&
					    all_n < L7_BL_MAX_CATS)
						all_cats[all_n++] =
						    bl_cfg.rules[ri]
						    .categories[ci];
				}
			}
			for (ai = 0; ai < bl_cfg.n_whitelist; ai++)
				bwl[ai] = bl_cfg.whitelist[ai];

			memcpy(s_bl_rules, bl_cfg.rules,
			    sizeof(bl_cfg.rules));
			s_bl_n_rules = bl_cfg.n_rules;

			if (all_n > 0) {
				struct l7_bl_limits lim;

				memset(&lim, 0, sizeof(lim));
				lim.max_entries = bl_cfg.max_entries;
				lim.mem_percent = bl_cfg.mem_percent;
				s_blacklist = l7_blacklist_load(
				    L7_BL_DIR_DEFAULT, all_cats, all_n,
				    bwl, bl_cfg.n_whitelist, &lim);
			}
			L7_NOTE("blacklists_startup: %d domains in "
			    "%d categories, %d rules",
			    s_blacklist ?
			    l7_blacklist_count(s_blacklist) : 0,
			    s_blacklist ?
			    l7_blacklist_cat_count(s_blacklist) : 0,
			    s_bl_n_rules);
		}
	}

	open_captures();

	identity_module_sync("startup");

	for (;;) {
		if (stop_req) {
			close_captures();
			identity_module_shutdown();
			/* Bloco 5: ao parar/desinstalar, garantir que nenhuma
			 * tabela dinamica fica com IPs `stale` que continuariam
			 * a ser bloqueados pelas regras PF que persistem ate
			 * proximo reload do firewall. */
			enforcement_flush_all_tables();
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
			L7_DBG(
			    "SIGUSR1 stats: ver=%s reload_ok=%llu snapshot_fail=%llu "
			    "sighup=%llu usr1=%llu loop_ticks=%llu "
			    "policies=%d exceptions=%d enforce_cfg=%d "
			    "have_parse=%d pf_add_ok=%llu pf_add_fail=%llu "
			    "dst_add_ok=%llu dst_add_fail=%llu dst_cache=%d "
			    "cap_pkts=%llu cap_active=%llu cap_classified=%llu "
			    "cap_expired=%llu cap_evicted=%llu cap_dropped=%llu "
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
			    s_n_enforce_cache,
			    (unsigned long long)s_cap_pkts,
			    (unsigned long long)s_cap_flows_active,
			    (unsigned long long)s_cap_flows_classified,
			    (unsigned long long)s_cap_flows_expired,
			    (unsigned long long)s_cap_flows_evicted,
			    (unsigned long long)s_cap_flows_dropped,
			    s_n_captures);
#else
			L7_DBG(
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
			int prev_ge;

			reload_req = 0;
			s_sighup_count++;
			prev_ge = s_ge;
			L7_NOTE("SIGHUP: reload config");
			enforce_cache_flush();
			close_captures();
			if (stat(config_path, &st) == 0)
				(void)apply_config(1);
			else {
				L7_WARN("SIGHUP: missing %s", config_path);
				s_have_parse = 0;
			}

			if (!pf_base_tables_ok())
				(void)layer7_pf_selfheal(NULL, "sighup_reload");

			/* Bloco 5: transicao enforce -> passivo (monitor /
			 * disabled / license invalid). Flush forte de todas as
			 * tabelas dinamicas para evitar bloqueio residual. */
			if (prev_ge && !s_ge) {
				L7_NOTE("mode transition enforce->passive: "
				    "flushing PF dynamic tables");
				enforcement_flush_all_tables();
			}

			open_captures();

			/* Identity: SIGHUP não descarta o mapa (ADR-0027 §4.2) */
			identity_module_sync("sighup");

			/* Reload blacklists from separate config.json */
			{
				struct l7_bl_config bl_cfg;
				struct l7_blacklist *new_bl = NULL;
				struct l7_blacklist *old_bl;
				int bl_load_ok = 0;

				memset(&bl_cfg, 0, sizeof(bl_cfg));
				if (l7_bl_config_load(
				    L7_BL_DIR_DEFAULT "/config.json",
				    &bl_cfg) == 0 && bl_cfg.enabled &&
				    bl_cfg.n_rules > 0) {
					const char *all_cats[L7_BL_MAX_CATS];
					const char *bwl[L7_BL_WL_MAX];
					int all_n = 0, ri, ci, ai, found;

					for (ri = 0; ri < bl_cfg.n_rules; ri++) {
						for (ci = 0;
						    ci < bl_cfg.rules[ri].n_categories;
						    ci++) {
							found = 0;
							for (ai = 0; ai < all_n; ai++) {
								if (strcmp(
								    all_cats[ai],
								    bl_cfg.rules[ri].categories[ci]) == 0) {
									found = 1;
									break;
								}
							}
							if (!found && all_n < L7_BL_MAX_CATS)
								all_cats[all_n++] =
								    bl_cfg.rules[ri].categories[ci];
						}
					}

					for (ai = 0; ai < bl_cfg.n_whitelist; ai++)
						bwl[ai] = bl_cfg.whitelist[ai];

					if (all_n > 0) {
						struct l7_bl_limits lim;

						memset(&lim, 0, sizeof(lim));
						lim.max_entries =
						    bl_cfg.max_entries;
						lim.mem_percent =
						    bl_cfg.mem_percent;
						new_bl = l7_blacklist_load(
						    L7_BL_DIR_DEFAULT,
						    all_cats, all_n,
						    bwl, bl_cfg.n_whitelist,
						    &lim);
					}

					if (new_bl || all_n == 0) {
						bl_load_ok = 1;
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
						    bl_cfg.n_rules);
					} else {
						L7_WARN("blacklists: "
						    "rules loaded (%d), "
						    "but failed to load "
						    "UT1 categories",
						    bl_cfg.n_rules);
					}
				} else {
					bl_load_ok = 1;
				}

				if (bl_load_ok) {
					bl_flush_rule_tables();
					memset(s_bl_rules, 0, sizeof(s_bl_rules));
					if (bl_cfg.enabled && bl_cfg.n_rules > 0) {
						memcpy(s_bl_rules, bl_cfg.rules,
						    sizeof(bl_cfg.rules));
						s_bl_n_rules = bl_cfg.n_rules;
					} else {
						s_bl_n_rules = 0;
					}
					old_bl = s_blacklist;
					s_blacklist = new_bl;
					if (old_bl)
						l7_blacklist_free(old_bl);
				} else if (s_blacklist) {
					L7_WARN("blacklists: keeping "
					    "previous blacklist "
					    "(reload failed)");
				}
			}
		}

		/* Periodic license re-check (every L7_LIC_CHECK_INTERVAL) */
		{
			time_t tnow = time(NULL);
			if (tnow - s_last_lic_check >= L7_LIC_CHECK_INTERVAL) {
				struct l7_license_info li;
				int prev_ge, new_state;

				s_last_lic_check = tnow;
				memset(&li, 0, sizeof(li));
				prev_ge = s_ge;
				if (layer7_license_check(&li) == 0) {
					new_state = (li.grace || li.dev_mode) ? 2 : 1;
					s_lic = li;
					refresh_enforce_cfg();
					if (new_state != s_license_state &&
					    li.grace)
						L7_WARN("license_recheck: %s",
						    li.error);
					else if (new_state != s_license_state)
						L7_NOTE("license_recheck: valid "
						    "customer=%s days_left=%d",
						    li.customer, li.days_left);
					else
						L7_DBG("license_recheck: unchanged "
						    "state=%s",
						    new_state == 2 ?
						    "grace/dev" : "valid");
				} else {
					new_state = 0;
					s_lic = li;
					if (new_state != s_license_state) {
						L7_WARN("license_recheck: INVALID — "
						    "%s", li.error);
						L7_WARN("license_recheck: enforce "
						    "disabled, monitor-only");
						if (li.clock_suspect) {
							L7_AUDIT_NOTE(NULL,
							    "license_clock_suspect: "
							    "max_seen=%lld delta=%ld "
							    "— enforce degraded to "
							    "monitor (30.6)",
							    (long long)li.clock_max_seen,
							    li.clock_delta_sec);
						}
					} else
						L7_DBG("license_recheck: unchanged "
						    "state=invalid");
					s_ge = 0;
					enforce_ge_downgrade(prev_ge,
					    "license_recheck");
				}
				s_license_state = new_state;
				identity_module_sync("license_recheck");
			}
		}

		license_checkin_tick();

		s_loop_ticks++;
		tick++;
		if (s_have_parse && cfg_disabled(&s_parsed)) {
			if (tick % 360 == 0)
				L7_DBG("layer7.enabled=false — still idle");
			sleep(60);
		}
#if HAVE_NDPI
		else if (s_n_captures > 0) {
			int j, total = 0;
			time_t loop_now;
			for (j = 0; j < s_n_captures; j++) {
				int r = layer7_capture_poll(s_captures[j], 64);
				if (r > 0)
					total += r;
			}
			enforce_cache_sweep();
			if (total == 0)
				usleep(10000);
			loop_now = time(NULL);
			if (loop_now - s_last_stats_write >= 60) {
				aggregate_capture_stats();
				write_stats_json();
				s_last_stats_write = loop_now;
			}
			if (loop_now - s_last_periodic_log >= 3600) {
				s_last_periodic_log = loop_now;
				L7_INFO(
				    "periodic: reload_ok=%llu policies=%d "
				    "exceptions=%d enforce=%d "
				    "pkts=%llu active=%llu classified=%llu "
				    "evicted=%llu dropped=%llu "
				    "blocked=%llu allowed=%llu",
				    (unsigned long long)s_reload_ok, s_np,
				    s_nx, s_ge,
				    (unsigned long long)s_cap_pkts,
				    (unsigned long long)s_cap_flows_active,
				    (unsigned long long)s_cap_flows_classified,
				    (unsigned long long)s_cap_flows_evicted,
				    (unsigned long long)s_cap_flows_dropped,
				    (unsigned long long)s_total_blocked,
				    (unsigned long long)s_total_allowed);
			}
		}
#endif
		else {
			time_t loop_now = time(NULL);
			if (loop_now - s_last_periodic_log >= 3600) {
				s_last_periodic_log = loop_now;
				L7_INFO(
				    "periodic_state: reload_ok=%llu "
				    "snapshot_fail=%llu policies=%d "
				    "exceptions=%d enforce_cfg=%d "
				    "(no captures active)",
				    (unsigned long long)s_reload_ok,
				    (unsigned long long)s_snapshot_fail, s_np,
				    s_nx, s_ge);
			}
			sleep(30);
		}
	}
}
