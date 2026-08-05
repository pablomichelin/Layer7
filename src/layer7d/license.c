/*
 * Layer7 license verification — hardware fingerprint + Ed25519 .lic file.
 * Uses OpenSSL EVP API (available in FreeBSD base via libcrypto).
 */

#include "license.h"

#include <ctype.h>
#include <errno.h>
#include <ifaddrs.h>
#include <net/if.h>
#include <net/if_dl.h>
#include <net/if_types.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/sysctl.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <time.h>
#include <unistd.h>

#include <openssl/evp.h>
#include <openssl/sha.h>

/*
 * Ed25519 public key (32 bytes). Replace with real key before production.
 * All-zeros = development mode (license verification skipped).
 * Use scripts/license/generate-license.py to create a key pair.
 */
static const unsigned char l7_ed25519_pubkey[32] = {
	0x8c, 0x52, 0xb6, 0x77, 0x2a, 0x64, 0x74, 0x9e,
	0x4a, 0x57, 0xb3, 0x4b, 0xa1, 0x65, 0x78, 0xa1,
	0xb1, 0x30, 0x96, 0x0b, 0x1a, 0x8e, 0x88, 0xe6,
	0xc1, 0xd8, 0x6d, 0xbd, 0x99, 0xfd, 0x18, 0x24
};

static int
is_dev_key(void)
{
	int i;
	for (i = 0; i < 32; i++) {
		if (l7_ed25519_pubkey[i] != 0)
			return 0;
	}
	return 1;
}

/* --- hex helpers --- */

static int
hex_decode(const char *hex, size_t hexlen, unsigned char *out, size_t outsz)
{
	size_t i;
	if (hexlen % 2 != 0 || hexlen / 2 > outsz)
		return -1;
	for (i = 0; i < hexlen; i += 2) {
		unsigned int b;
		if (sscanf(hex + i, "%2x", &b) != 1)
			return -1;
		out[i / 2] = (unsigned char)b;
	}
	return (int)(hexlen / 2);
}

static void
hex_encode(const unsigned char *data, size_t len, char *out)
{
	size_t i;
	for (i = 0; i < len; i++)
		sprintf(out + i * 2, "%02x", data[i]);
	out[len * 2] = '\0';
}

/* --- hardware fingerprint --- */

static int
get_hostuuid(char *buf, size_t bufsz)
{
	size_t len = bufsz;
	if (sysctlbyname("kern.hostuuid", buf, &len, NULL, 0) != 0)
		return -1;
	while (len > 0 && (buf[len - 1] == '\n' || buf[len - 1] == '\r'))
		buf[--len] = '\0';
	return (len > 0) ? 0 : -1;
}

static int
get_first_mac(char *buf, size_t bufsz)
{
	struct ifaddrs *ifap, *ifa;
	int found = 0;

	if (getifaddrs(&ifap) != 0)
		return -1;

	for (ifa = ifap; ifa != NULL; ifa = ifa->ifa_next) {
		struct sockaddr_dl *sdl;

		if (ifa->ifa_addr == NULL ||
		    ifa->ifa_addr->sa_family != AF_LINK)
			continue;
		if (ifa->ifa_flags & IFF_LOOPBACK)
			continue;

		sdl = (struct sockaddr_dl *)ifa->ifa_addr;
		if (sdl->sdl_type != IFT_ETHER || sdl->sdl_alen != 6)
			continue;

		{
			unsigned char *mac;
			mac = (unsigned char *)LLADDR(sdl);
			snprintf(buf, bufsz,
			    "%02x:%02x:%02x:%02x:%02x:%02x",
			    mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);
			found = 1;
			break;
		}
	}

	freeifaddrs(ifap);
	return found ? 0 : -1;
}

int
layer7_hw_fingerprint(char *out, size_t outsz)
{
	char uuid[128], mac[24], combined[256];
	unsigned char hash[SHA256_DIGEST_LENGTH];

	if (outsz < L7_HW_ID_LEN)
		return -1;
	if (get_hostuuid(uuid, sizeof(uuid)) != 0)
		return -1;
	if (get_first_mac(mac, sizeof(mac)) != 0)
		return -1;

	snprintf(combined, sizeof(combined), "%s:%s", uuid, mac);

	SHA256((const unsigned char *)combined, strlen(combined), hash);
	hex_encode(hash, SHA256_DIGEST_LENGTH, out);
	return 0;
}

/* --- minimal JSON field extraction --- */

static const char *
json_find_string(const char *json, const char *key, char *val, size_t valsz)
{
	char needle[128];
	const char *p;
	size_t i = 0;

	snprintf(needle, sizeof(needle), "\"%s\"", key);
	p = strstr(json, needle);
	if (!p)
		return NULL;
	p += strlen(needle);
	while (*p && (*p == ' ' || *p == '\t' || *p == ':'))
		p++;
	if (*p != '"')
		return NULL;
	p++;

	while (*p && i < valsz - 1) {
		if (*p == '\\' && *(p + 1) == '"') {
			val[i++] = '"';
			p += 2;
		} else if (*p == '\\' && *(p + 1) == '\\') {
			val[i++] = '\\';
			p += 2;
		} else if (*p == '"') {
			break;
		} else {
			val[i++] = *p;
			p++;
		}
	}
	val[i] = '\0';
	return (i > 0) ? val : NULL;
}

/* --- license file verification --- */

static char *
read_file_alloc(const char *path, size_t *out_len)
{
	FILE *f;
	long sz;
	char *buf;

	f = fopen(path, "rb");
	if (!f)
		return NULL;
	if (fseek(f, 0, SEEK_END) != 0) {
		fclose(f);
		return NULL;
	}
	sz = ftell(f);
	if (sz < 0 || sz > 64 * 1024) {
		fclose(f);
		return NULL;
	}
	rewind(f);
	buf = malloc((size_t)sz + 1);
	if (!buf) {
		fclose(f);
		return NULL;
	}
	if ((long)fread(buf, 1, (size_t)sz, f) != sz) {
		free(buf);
		fclose(f);
		return NULL;
	}
	buf[sz] = '\0';
	fclose(f);
	if (out_len)
		*out_len = (size_t)sz;
	return buf;
}

static int
parse_date(const char *s, struct tm *tm)
{
	int y, m, d;
	if (sscanf(s, "%d-%d-%d", &y, &m, &d) != 3)
		return -1;
	memset(tm, 0, sizeof(*tm));
	tm->tm_year = y - 1900;
	tm->tm_mon = m - 1;
	tm->tm_mday = d;
	return 0;
}

int
layer7_license_check(struct l7_license_info *info)
{
	char *lic_raw = NULL;
	size_t lic_len;
	char data_str[4096], sig_hex[256];
	unsigned char sig_bin[64];
	int sig_len;
	char hw_id[L7_HW_ID_LEN];
	char lic_hwid[L7_HW_ID_LEN], expiry[16], customer[256], features[64];
	struct tm exp_tm;
	time_t exp_time, now;
	double diff_days;
	EVP_PKEY *pkey = NULL;
	EVP_MD_CTX *mdctx = NULL;
	int verify_ok = 0;

	memset(info, 0, sizeof(*info));

	if (is_dev_key()) {
		info->dev_mode = 1;
		info->valid = 1;
		snprintf(info->error, sizeof(info->error),
		    "development key — license verification skipped");
		if (layer7_hw_fingerprint(info->hardware_id,
		    sizeof(info->hardware_id)) != 0)
			snprintf(info->hardware_id,
			    sizeof(info->hardware_id), "(unknown)");
		return 0;
	}

	if (layer7_hw_fingerprint(hw_id, sizeof(hw_id)) != 0) {
		snprintf(info->error, sizeof(info->error),
		    "failed to compute hardware fingerprint");
		return -1;
	}
	memcpy(info->hardware_id, hw_id, L7_HW_ID_LEN);

	lic_raw = read_file_alloc(L7_LIC_PATH, &lic_len);
	if (!lic_raw) {
		snprintf(info->error, sizeof(info->error),
		    "license file not found: %s", L7_LIC_PATH);
		return -1;
	}

	if (!json_find_string(lic_raw, "data", data_str, sizeof(data_str))) {
		snprintf(info->error, sizeof(info->error),
		    "license file missing \"data\" field");
		free(lic_raw);
		return -1;
	}
	if (!json_find_string(lic_raw, "sig", sig_hex, sizeof(sig_hex))) {
		snprintf(info->error, sizeof(info->error),
		    "license file missing \"sig\" field");
		free(lic_raw);
		return -1;
	}
	free(lic_raw);

	sig_len = hex_decode(sig_hex, strlen(sig_hex), sig_bin, sizeof(sig_bin));
	if (sig_len != 64) {
		snprintf(info->error, sizeof(info->error),
		    "invalid signature length (%d bytes, expected 64)",
		    sig_len);
		return -1;
	}

	/* Ed25519 verification via OpenSSL EVP */
	pkey = EVP_PKEY_new_raw_public_key(EVP_PKEY_ED25519, NULL,
	    l7_ed25519_pubkey, 32);
	if (!pkey) {
		snprintf(info->error, sizeof(info->error),
		    "failed to load Ed25519 public key");
		return -1;
	}

	mdctx = EVP_MD_CTX_new();
	if (!mdctx) {
		EVP_PKEY_free(pkey);
		snprintf(info->error, sizeof(info->error),
		    "EVP_MD_CTX_new failed");
		return -1;
	}

	if (EVP_DigestVerifyInit(mdctx, NULL, NULL, NULL, pkey) != 1) {
		EVP_MD_CTX_free(mdctx);
		EVP_PKEY_free(pkey);
		snprintf(info->error, sizeof(info->error),
		    "EVP_DigestVerifyInit failed");
		return -1;
	}

	verify_ok = EVP_DigestVerify(mdctx, sig_bin, 64,
	    (const unsigned char *)data_str, strlen(data_str));

	EVP_MD_CTX_free(mdctx);
	EVP_PKEY_free(pkey);

	if (verify_ok != 1) {
		snprintf(info->error, sizeof(info->error),
		    "Ed25519 signature verification failed");
		return -1;
	}

	/* Parse license data fields */
	if (!json_find_string(data_str, "hardware_id",
	    lic_hwid, sizeof(lic_hwid))) {
		snprintf(info->error, sizeof(info->error),
		    "license data missing hardware_id");
		return -1;
	}
	if (!json_find_string(data_str, "expiry",
	    expiry, sizeof(expiry))) {
		snprintf(info->error, sizeof(info->error),
		    "license data missing expiry");
		return -1;
	}

	json_find_string(data_str, "customer", customer, sizeof(customer));
	json_find_string(data_str, "features", features, sizeof(features));

	/* Hardware ID match */
	if (strcmp(lic_hwid, hw_id) != 0) {
		snprintf(info->error, sizeof(info->error),
		    "hardware mismatch: license=%.*s local=%.*s",
		    8, lic_hwid, 8, hw_id);
		return -1;
	}

	/* Expiry check */
	if (parse_date(expiry, &exp_tm) != 0) {
		snprintf(info->error, sizeof(info->error),
		    "invalid expiry date format: %s", expiry);
		return -1;
	}

	exp_time = mktime(&exp_tm);
	now = time(NULL);
	diff_days = difftime(exp_time, now) / 86400.0;

	strncpy(info->expiry, expiry, sizeof(info->expiry) - 1);
	strncpy(info->customer, customer, sizeof(info->customer) - 1);
	strncpy(info->features, features, sizeof(info->features) - 1);
	info->days_left = (int)diff_days;

	if (diff_days >= 0) {
		info->valid = 1;
		info->expired = 0;
		info->grace = 0;
		return 0;
	}

	info->expired = 1;
	if (-diff_days <= L7_LIC_GRACE_DAYS) {
		info->valid = 1;
		info->grace = 1;
		snprintf(info->error, sizeof(info->error),
		    "license expired %d day(s) ago — grace period active "
		    "(%d days remaining)",
		    -(int)diff_days,
		    L7_LIC_GRACE_DAYS + (int)diff_days);
		return 0;
	}

	snprintf(info->error, sizeof(info->error),
	    "license expired %d day(s) ago — grace period exhausted",
	    -(int)diff_days);
	return -1;
}

/* --- online activation (stub — requires license server) --- */

static int
json_safe_string(const char *s)
{
	for (; *s; s++) {
		if (*s == '"' || *s == '\\' || *s == '\'' ||
		    (unsigned char)*s < 0x20)
			return 0;
	}
	return 1;
}

static int
shell_safe_url(const char *s)
{
	const unsigned char *p;

	if (!s || strncmp(s, "https://", 8) != 0)
		return 0;
	for (p = (const unsigned char *)s; *p; p++) {
		if (*p <= 0x20 || *p == 0x7f || *p == '\'' || *p == '"' ||
		    *p == '\\' || *p == '`' || *p == '$' || *p == ';' ||
		    *p == '|' || *p == '&' || *p == '<' || *p == '>' ||
		    *p == '(' || *p == ')')
			return 0;
	}
	return 1;
}

#define L7_ACTIVATE_BODY_TMP "/tmp/layer7-activate.body"
#define L7_ACTIVATE_HTTP_TMP "/tmp/layer7-activate.http"

static void
activation_cleanup_temp(void)
{
	(void)unlink(L7_ACTIVATE_BODY_TMP);
	(void)unlink(L7_ACTIVATE_HTTP_TMP);
}

static int
read_text_file_trim(const char *path, char *buf, size_t bufsz)
{
	FILE *f;
	size_t n;

	if (!buf || bufsz == 0)
		return -1;

	f = fopen(path, "r");
	if (!f)
		return -1;

	n = fread(buf, 1, bufsz - 1, f);
	buf[n] = '\0';
	fclose(f);

	while (n > 0 && (buf[n - 1] == '\n' || buf[n - 1] == '\r' ||
	    buf[n - 1] == ' ' || buf[n - 1] == '\t'))
		buf[--n] = '\0';

	return 0;
}

static int
promote_activate_body(void)
{
	char *raw;
	size_t len;
	FILE *out;

	raw = read_file_alloc(L7_ACTIVATE_BODY_TMP, &len);
	if (!raw)
		return -1;

	out = fopen(L7_LIC_PATH, "w");
	if (!out) {
		free(raw);
		return -1;
	}

	if (fwrite(raw, 1, len, out) != len) {
		fclose(out);
		free(raw);
		(void)unlink(L7_LIC_PATH);
		return -1;
	}

	fclose(out);
	free(raw);
	return 0;
}

int
layer7_activate(const char *key, const char *url)
{
	char hw_id[L7_HW_ID_LEN];
	char cmd[1536];
	char body[512];
	char http_code[8];
	char response_body[1024];
	char server_error[256];
	int rc, status;

	if (!key || key[0] == '\0') {
		fprintf(stderr, "layer7d: activation key is required\n");
		return -1;
	}

	if (!json_safe_string(key)) {
		fprintf(stderr,
		    "layer7d: activation key contains invalid characters "
		    "(no quotes, backslashes or control chars allowed)\n");
		return -1;
	}

	if (layer7_hw_fingerprint(hw_id, sizeof(hw_id)) != 0) {
		fprintf(stderr,
		    "layer7d: failed to compute hardware fingerprint\n");
		return -1;
	}

	if (!url || url[0] == '\0')
		url = "https://license.systemup.inf.br/api/activate";
	if (!shell_safe_url(url)) {
		fprintf(stderr,
		    "layer7d: activation URL must be https and shell-safe\n");
		return -1;
	}

	fprintf(stderr, "layer7d: activating...\n");
	fprintf(stderr, "  server:       %s\n", url);
	fprintf(stderr, "  hardware_id:  %s\n", hw_id);
	fprintf(stderr, "  key:          %.8s...\n", key);

	activation_cleanup_temp();

	snprintf(body, sizeof(body),
	    "{\"key\":\"%s\",\"hardware_id\":\"%s\"}", key, hw_id);

	snprintf(cmd, sizeof(cmd),
	    "curl -sS -o %s -w '%%{http_code}' -X POST "
	    "-H 'Content-Type: application/json' "
	    "-d '%s' '%s' > %s 2>/dev/null",
	    L7_ACTIVATE_BODY_TMP, body, url, L7_ACTIVATE_HTTP_TMP);

	rc = system(cmd);
	if (rc != 0 || read_text_file_trim(L7_ACTIVATE_HTTP_TMP, http_code,
	    sizeof(http_code)) != 0) {
		activation_cleanup_temp();
		fprintf(stderr,
		    "layer7d: activation failed — could not reach "
		    "license server at %s\n"
		    "  Check network connectivity and that curl is installed.\n"
		    "  Alternatively, place a valid .lic file at %s\n",
		    url, L7_LIC_PATH);
		return -1;
	}

	status = atoi(http_code);
	if (status < 200 || status > 299) {
		server_error[0] = '\0';
		if (read_text_file_trim(L7_ACTIVATE_BODY_TMP, response_body,
		    sizeof(response_body)) == 0)
			(void)json_find_string(response_body, "error",
			    server_error, sizeof(server_error));

		activation_cleanup_temp();

		if (server_error[0] != '\0') {
			fprintf(stderr,
			    "layer7d: activation rejected by license server "
			    "(HTTP %d): %s\n",
			    status, server_error);
		} else {
			fprintf(stderr,
			    "layer7d: activation rejected by license server "
			    "(HTTP %d)\n",
			    status);
		}
		return -1;
	}

	if (promote_activate_body() != 0) {
		activation_cleanup_temp();
		fprintf(stderr,
		    "layer7d: activation failed — could not save license to "
		    "%s\n", L7_LIC_PATH);
		return -1;
	}

	activation_cleanup_temp();

	fprintf(stderr, "layer7d: license saved to %s\n", L7_LIC_PATH);

	{
		struct l7_license_info li;

		if (layer7_license_check(&li) == 0) {
			(void)layer7_checkin_store_key(key);
			layer7_checkin_mark_ok_from_activate();
			fprintf(stderr,
			    "layer7d: license valid — customer=%s "
			    "expiry=%s features=%s\n",
			    li.customer, li.expiry, li.features);
			return 0;
		}

		(void)unlink(L7_LIC_PATH);
		fprintf(stderr,
		    "layer7d: activation rejected — downloaded license did not "
		    "pass verification: %s\n",
		    li.error);
		return -1;
	}
}

/* --- BG-077: online check-in / remote revocation --- */

#define L7_CHECKIN_BODY_TMP "/tmp/layer7-checkin.body"
#define L7_CHECKIN_HTTP_TMP "/tmp/layer7-checkin.http"
#define L7_CHECKIN_DEFAULT_URL \
	"https://license.systemup.inf.br/api/license/check-in"

struct l7_checkin_state {
	char license_key[65];
	time_t last_check_in_ok;
	time_t last_check_in_attempt;
	int check_in_interval_hours;
	int max_offline_hours;
	char last_error[256];
};

static void
checkin_cleanup_temp(void)
{
	(void)unlink(L7_CHECKIN_BODY_TMP);
	(void)unlink(L7_CHECKIN_HTTP_TMP);
}

static int
parse_bool_json_value(const char *p)
{
	if (!p)
		return 0;
	while (*p && *p != ':')
		p++;
	if (*p != ':')
		return 0;
	p++;
	while (*p == ' ' || *p == '\t')
		p++;
	if (strncmp(p, "true", 4) == 0)
		return 1;
	return 0;
}

static int parse_int_json_field(const char *json, const char *key, int *out)
{
	char needle[128];
	const char *p;
	int value;

	snprintf(needle, sizeof(needle), "\"%s\"", key);
	p = strstr(json, needle);
	if (!p)
		return -1;
	p += strlen(needle);
	while (*p && (*p == ' ' || *p == '\t' || *p == ':'))
		p++;
	value = (int)strtol(p, NULL, 10);
	if (value <= 0)
		return -1;
	*out = value;
	return 0;
}

static int
parse_time_json_field(const char *json, const char *key, time_t *out)
{
	char needle[128];
	const char *p;
	long value;

	snprintf(needle, sizeof(needle), "\"%s\"", key);
	p = strstr(json, needle);
	if (!p)
		return -1;
	p += strlen(needle);
	while (*p && (*p == ' ' || *p == '\t' || *p == ':'))
		p++;
	value = strtol(p, NULL, 10);
	if (value < 0)
		return -1;
	*out = (time_t)value;
	return 0;
}

static void
checkin_state_defaults(struct l7_checkin_state *st)
{
	memset(st, 0, sizeof(*st));
	st->check_in_interval_hours = L7_CHECKIN_DEFAULT_INTERVAL_HOURS;
	st->max_offline_hours = L7_CHECKIN_DEFAULT_MAX_OFFLINE_HOURS;
}

static int
checkin_load_state(struct l7_checkin_state *st)
{
	char *raw;
	size_t len;

	checkin_state_defaults(st);
	raw = read_file_alloc(L7_CHECKIN_STATE_PATH, &len);
	if (!raw)
		return 0;

	(void)json_find_string(raw, "license_key", st->license_key,
	    sizeof(st->license_key));
	(void)parse_time_json_field(raw, "last_check_in_ok",
	    &st->last_check_in_ok);
	(void)parse_time_json_field(raw, "last_check_in_attempt",
	    &st->last_check_in_attempt);
	(void)parse_int_json_field(raw, "check_in_interval_hours",
	    &st->check_in_interval_hours);
	(void)parse_int_json_field(raw, "max_offline_hours",
	    &st->max_offline_hours);
	(void)json_find_string(raw, "last_error", st->last_error,
	    sizeof(st->last_error));

	free(raw);
	return st->license_key[0] != '\0' ? 1 : 0;
}

static int
checkin_save_state(const struct l7_checkin_state *st)
{
	FILE *f;

	f = fopen(L7_CHECKIN_STATE_PATH, "w");
	if (!f)
		return -1;

	fprintf(f,
	    "{\n"
	    "  \"license_key\": \"%s\",\n"
	    "  \"last_check_in_ok\": %lld,\n"
	    "  \"last_check_in_attempt\": %lld,\n"
	    "  \"check_in_interval_hours\": %d,\n"
	    "  \"max_offline_hours\": %d,\n"
	    "  \"last_error\": \"%s\"\n"
	    "}\n",
	    st->license_key,
	    (long long)st->last_check_in_ok,
	    (long long)st->last_check_in_attempt,
	    st->check_in_interval_hours,
	    st->max_offline_hours,
	    st->last_error);

	fclose(f);
	(void)chmod(L7_CHECKIN_STATE_PATH, 0600);
	return 0;
}

static int
checkin_interval_seconds(const struct l7_checkin_state *st)
{
	const char *env;
	long override;

	env = getenv("L7_CHECK_IN_INTERVAL_SEC");
	if (env && env[0] != '\0') {
		override = strtol(env, NULL, 10);
		if (override > 0)
			return (int)override;
	}

	if (st->check_in_interval_hours > 0)
		return st->check_in_interval_hours * 3600;
	return L7_CHECKIN_DEFAULT_INTERVAL_HOURS * 3600;
}

static void
checkin_invalidate_local(struct l7_checkin_state *st, const char *reason)
{
	(void)unlink(L7_LIC_PATH);
	if (reason && reason[0] != '\0') {
		snprintf(st->last_error, sizeof(st->last_error), "%s", reason);
	} else {
		st->last_error[0] = '\0';
	}
}

int
layer7_checkin_store_key(const char *key)
{
	struct l7_checkin_state st;

	if (!key || key[0] == '\0' || !json_safe_string(key))
		return -1;

	checkin_state_defaults(&st);
	if (checkin_load_state(&st))
		; /* keep intervals if file existed */
	snprintf(st.license_key, sizeof(st.license_key), "%s", key);
	return checkin_save_state(&st);
}

void
layer7_checkin_mark_ok_from_activate(void)
{
	struct l7_checkin_state st;
	time_t now = time(NULL);

	if (!checkin_load_state(&st))
		return;
	st.last_check_in_ok = now;
	st.last_check_in_attempt = now;
	st.last_error[0] = '\0';
	(void)checkin_save_state(&st);
}

int
layer7_checkin_config_enabled(const char *config_path)
{
	char *json;
	const char *p;
	int enabled = 0;

	if (getenv("L7_CHECK_IN_FORCE") != NULL)
		return 1;

	if (!config_path)
		config_path = "/usr/local/etc/layer7.json";

	json = read_file_alloc(config_path, NULL);
	if (!json)
		return 0;

	p = strstr(json, "\"check_in_enabled\"");
	if (p)
		enabled = parse_bool_json_value(p);

	free(json);
	return enabled;
}

int
layer7_checkin_due(time_t now)
{
	struct l7_checkin_state st;
	int interval_sec;

	if (!checkin_load_state(&st) || !st.license_key[0])
		return 0;

	interval_sec = checkin_interval_seconds(&st);
	if (st.last_check_in_ok > 0)
		return (now - st.last_check_in_ok) >= interval_sec;

	if (st.last_check_in_attempt == 0)
		return 1;
	return (now - st.last_check_in_attempt) >= 3600;
}

int
layer7_checkin_offline_expired(time_t now)
{
	struct l7_checkin_state st;
	time_t anchor;
	long max_offline_sec;

	if (!checkin_load_state(&st) || !st.license_key[0])
		return 0;

	max_offline_sec = (long)st.max_offline_hours * 3600L;
	if (max_offline_sec <= 0)
		max_offline_sec = (long)L7_CHECKIN_DEFAULT_MAX_OFFLINE_HOURS * 3600L;

	anchor = st.last_check_in_ok;
	if (anchor <= 0)
		anchor = st.last_check_in_attempt;
	if (anchor <= 0)
		return 0;

	return (now - anchor) >= max_offline_sec;
}

int
layer7_check_in(const char *url)
{
	struct l7_checkin_state st;
	char hw_id[L7_HW_ID_LEN];
	char cmd[2048];
	char body[512];
	char http_code[8];
	char response_body[2048];
	char status[32];
	char server_error[256];
	int rc, http_status;

	if (is_dev_key())
		return L7_CHECKIN_SKIP;

	if (!checkin_load_state(&st) || !st.license_key[0]) {
		fprintf(stderr,
		    "layer7d: check-in skipped — no stored license key "
		    "(activate first)\n");
		return L7_CHECKIN_SKIP;
	}

	if (layer7_hw_fingerprint(hw_id, sizeof(hw_id)) != 0) {
		fprintf(stderr,
		    "layer7d: check-in failed — hardware fingerprint error\n");
		return L7_CHECKIN_NETWORK;
	}

	if (!url || url[0] == '\0') {
		url = getenv("L7_CHECK_IN_URL");
		if (!url || url[0] == '\0')
			url = L7_CHECKIN_DEFAULT_URL;
	}
	if (!shell_safe_url(url)) {
		fprintf(stderr, "layer7d: check-in URL must be https and shell-safe\n");
		return L7_CHECKIN_NETWORK;
	}

	st.last_check_in_attempt = time(NULL);
	checkin_cleanup_temp();

	snprintf(body, sizeof(body),
	    "{\"key\":\"%s\",\"hardware_id\":\"%s\"}",
	    st.license_key, hw_id);

	snprintf(cmd, sizeof(cmd),
	    "curl -sS -o %s -w '%%{http_code}' -X POST "
	    "-H 'Content-Type: application/json' "
	    "-d '%s' '%s' > %s 2>/dev/null",
	    L7_CHECKIN_BODY_TMP, body, url, L7_CHECKIN_HTTP_TMP);

	rc = system(cmd);
	if (rc != 0 || read_text_file_trim(L7_CHECKIN_HTTP_TMP, http_code,
	    sizeof(http_code)) != 0) {
		snprintf(st.last_error, sizeof(st.last_error),
		    "license server unreachable");
		(void)checkin_save_state(&st);
		checkin_cleanup_temp();
		fprintf(stderr,
		    "layer7d: check-in failed — could not reach license server "
		    "at %s\n", url);
		return L7_CHECKIN_NETWORK;
	}

	http_status = atoi(http_code);
	if (read_text_file_trim(L7_CHECKIN_BODY_TMP, response_body,
	    sizeof(response_body)) != 0)
		response_body[0] = '\0';

	if (http_status >= 200 && http_status <= 299) {
		if (!json_find_string(response_body, "status", status,
		    sizeof(status)) || strcmp(status, "active") != 0) {
			snprintf(st.last_error, sizeof(st.last_error),
			    "unexpected check-in status");
			(void)checkin_save_state(&st);
			checkin_cleanup_temp();
			return L7_CHECKIN_NETWORK;
		}

		{
			int interval = st.check_in_interval_hours;
			int max_offline = st.max_offline_hours;

			if (parse_int_json_field(response_body,
			    "check_in_interval_hours", &interval) == 0)
				st.check_in_interval_hours = interval;
			if (parse_int_json_field(response_body,
			    "max_offline_hours", &max_offline) == 0)
				st.max_offline_hours = max_offline;
		}

		st.last_check_in_ok = time(NULL);
		st.last_error[0] = '\0';
		(void)checkin_save_state(&st);
		checkin_cleanup_temp();
		fprintf(stderr, "layer7d: check-in OK — license active\n");
		return L7_CHECKIN_OK;
	}

	server_error[0] = '\0';
	status[0] = '\0';
	(void)json_find_string(response_body, "status", status, sizeof(status));
	(void)json_find_string(response_body, "error", server_error,
	    sizeof(server_error));

	if (http_status == 409 &&
	    (strcmp(status, "revoked") == 0 ||
	     strcmp(status, "expired") == 0 ||
	     server_error[0] != '\0')) {
		if (server_error[0] != '\0')
			snprintf(st.last_error, sizeof(st.last_error), "%s",
			    server_error);
		else if (strcmp(status, "expired") == 0)
			snprintf(st.last_error, sizeof(st.last_error),
			    "Licenca expirada.");
		else
			snprintf(st.last_error, sizeof(st.last_error),
			    "Licenca revogada.");

		checkin_invalidate_local(&st, st.last_error);
		(void)checkin_save_state(&st);
		checkin_cleanup_temp();
		fprintf(stderr, "layer7d: check-in denied — %s\n",
		    st.last_error);
		return L7_CHECKIN_DENIED;
	}

	if (server_error[0] != '\0')
		snprintf(st.last_error, sizeof(st.last_error), "%s", server_error);
	else
		snprintf(st.last_error, sizeof(st.last_error),
		    "check-in rejected (HTTP %d)", http_status);
	(void)checkin_save_state(&st);
	checkin_cleanup_temp();
	fprintf(stderr, "layer7d: check-in failed — %s\n", st.last_error);
	return L7_CHECKIN_NETWORK;
}

int
layer7_checkin_get_status(struct l7_checkin_status *st)
{
	struct l7_checkin_state state;
	time_t now;

	if (!st)
		return -1;

	memset(st, 0, sizeof(*st));
	st->interval_hours = L7_CHECKIN_DEFAULT_INTERVAL_HOURS;
	st->max_offline_hours = L7_CHECKIN_DEFAULT_MAX_OFFLINE_HOURS;

	if (!checkin_load_state(&state))
		return 0;

	st->last_ok = state.last_check_in_ok;
	st->last_attempt = state.last_check_in_attempt;
	st->interval_hours = state.check_in_interval_hours;
	st->max_offline_hours = state.max_offline_hours;
	snprintf(st->last_error, sizeof(st->last_error), "%s",
	    state.last_error);

	now = time(NULL);
	if (state.last_check_in_ok > 0) {
		st->ok = 1;
		st->next_due = state.last_check_in_ok +
		    (time_t)checkin_interval_seconds(&state);
	} else if (state.last_check_in_attempt > 0) {
		st->next_due = state.last_check_in_attempt + 3600;
	} else {
		st->next_due = now;
	}
	return 0;
}
