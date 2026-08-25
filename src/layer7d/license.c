/*
 * Layer7 license verification — hardware fingerprint + Ed25519 .lic file.
 * Uses OpenSSL EVP API (available in FreeBSD base via libcrypto).
 */

#include "license.h"
#include "l7_features.h"

#include <ctype.h>
#include <errno.h>
#include <fcntl.h>
#include <ifaddrs.h>
#include <net/if.h>
#include <net/if_dl.h>
#include <net/if_types.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/file.h>
#include <sys/sysctl.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <time.h>
#include <unistd.h>

#include <openssl/evp.h>
#include <openssl/rand.h>
#include <openssl/sha.h>

static unsigned checkin_effective_features(unsigned lic_flags);

/*
 * Ed25519 public key (32 bytes). Material SoT no builder (passo 30.2);
 * este array é espelho transitório até AP1 remover o embutido.
 *
 * Produção (sem L7_DEV_BUILD): all-zeros NÃO salta verificação — licença
 * inválida / monitor (A-01 / passo 30.4 / ADR-0030).
 * Lab: apenas builds com -DL7_DEV_BUILD (flag ausente do Makefile do port).
 *
 * L7_TEST_ZERO_PUBKEY: só para o harness de teste (nunca no port).
 */
#ifdef L7_TEST_ZERO_PUBKEY
static const unsigned char l7_ed25519_pubkey[32] = { 0 };
#else
static const unsigned char l7_ed25519_pubkey[32] = {
	0x8c, 0x52, 0xb6, 0x77, 0x2a, 0x64, 0x74, 0x9e,
	0x4a, 0x57, 0xb3, 0x4b, 0xa1, 0x65, 0x78, 0xa1,
	0xb1, 0x30, 0x96, 0x0b, 0x1a, 0x8e, 0x88, 0xe6,
	0xc1, 0xd8, 0x6d, 0xbd, 0x99, 0xfd, 0x18, 0x24
};
#endif

#ifdef L7_DEV_BUILD
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
#endif /* L7_DEV_BUILD */

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

/* --- anti-rollback clock mark (30.6 / ADR-0033) --- */

static int parse_time_json_field(const char *json, const char *key, time_t *out);

int
layer7_clock_eval(time_t now, time_t max_seen,
    time_t *new_max_seen, long *delta_sec)
{
	long delta;

	if (new_max_seen)
		*new_max_seen = max_seen;
	if (delta_sec)
		*delta_sec = 0;

	if (now <= 0)
		return 0;

	if (max_seen <= 0) {
		if (new_max_seen)
			*new_max_seen = now;
		return 0;
	}

	if (now >= max_seen) {
		if (new_max_seen)
			*new_max_seen = now;
		return 0;
	}

	delta = (long)(max_seen - now);
	if (delta_sec)
		*delta_sec = delta;
	/* Nunca mover a marca para trás. */
	if (new_max_seen)
		*new_max_seen = max_seen;
	if (delta > L7_CLOCK_SUSPECT_SEC)
		return 1;
	return 0;
}

static const char *
clock_mark_path(void)
{
	const char *e = getenv("L7_CLOCK_MARK_PATH");

	if (e != NULL && e[0] != '\0')
		return e;
	return L7_CLOCK_MARK_PATH;
}

static int
clock_mark_ensure_dir(const char *path)
{
	char dir[512];
	char *slash;
	size_t n;

	n = strlen(path);
	if (n == 0 || n >= sizeof(dir))
		return -1;
	memcpy(dir, path, n + 1);
	slash = strrchr(dir, '/');
	if (slash == NULL || slash == dir)
		return 0;
	*slash = '\0';
	if (mkdir(dir, 0755) == 0 || errno == EEXIST)
		return 0;
	/* pai /var/db pode já existir; tentar só o leaf falhou por outro motivo */
	return -1;
}

static int
clock_mark_load(time_t *max_seen)
{
	char *raw;
	size_t len;
	time_t v = 0;

	if (max_seen)
		*max_seen = 0;
	raw = read_file_alloc(clock_mark_path(), &len);
	if (!raw)
		return 0;
	if (parse_time_json_field(raw, "max_seen", &v) == 0 && v > 0) {
		if (max_seen)
			*max_seen = v;
		free(raw);
		return 1;
	}
	free(raw);
	return 0;
}

static int
clock_mark_save(time_t max_seen)
{
	const char *path;
	char tmp[560];
	FILE *f;

	if (max_seen <= 0)
		return -1;
	path = clock_mark_path();
	(void)clock_mark_ensure_dir(path);
	snprintf(tmp, sizeof(tmp), "%s.tmp", path);
	f = fopen(tmp, "w");
	if (!f)
		return -1;
	fprintf(f,
	    "{\n"
	    "  \"v\": 1,\n"
	    "  \"max_seen\": %lld\n"
	    "}\n",
	    (long long)max_seen);
	if (fclose(f) != 0) {
		(void)unlink(tmp);
		return -1;
	}
	(void)chmod(tmp, 0600);
	if (rename(tmp, path) != 0) {
		(void)unlink(tmp);
		return -1;
	}
	return 0;
}

/* Ed25519 verify — mesma pubkey embutida (.lic e check-in 30.13). */
static int
l7_ed25519_verify(const char *data, const char *sig_hex)
{
	unsigned char sig_bin[64];
	int sig_len;
	EVP_PKEY *pkey;
	EVP_MD_CTX *mdctx;
	int verify_ok;

	if (!data || !sig_hex || strlen(sig_hex) != 128)
		return -1;
	sig_len = hex_decode(sig_hex, 128, sig_bin, sizeof(sig_bin));
	if (sig_len != 64)
		return -1;

	pkey = EVP_PKEY_new_raw_public_key(EVP_PKEY_ED25519, NULL,
	    l7_ed25519_pubkey, 32);
	if (!pkey)
		return -1;

	mdctx = EVP_MD_CTX_new();
	if (!mdctx) {
		EVP_PKEY_free(pkey);
		return -1;
	}

	if (EVP_DigestVerifyInit(mdctx, NULL, NULL, NULL, pkey) != 1) {
		EVP_MD_CTX_free(mdctx);
		EVP_PKEY_free(pkey);
		return -1;
	}

	verify_ok = EVP_DigestVerify(mdctx, sig_bin, 64,
	    (const unsigned char *)data, strlen(data));

	EVP_MD_CTX_free(mdctx);
	EVP_PKEY_free(pkey);
	return (verify_ok == 1) ? 0 : -1;
}

static int
layer7_license_check_path(const char *path, struct l7_license_info *info)
{
	char *lic_raw = NULL;
	size_t lic_len;
	char data_str[4096], sig_hex[256];
	char hw_id[L7_HW_ID_LEN];
	char lic_hwid[L7_HW_ID_LEN], expiry[16], customer[256], features[256];
	struct tm exp_tm;
	time_t exp_time, now;
	double diff_days;

	memset(info, 0, sizeof(*info));
	memset(lic_hwid, 0, sizeof(lic_hwid));
	memset(expiry, 0, sizeof(expiry));
	memset(customer, 0, sizeof(customer));
	memset(features, 0, sizeof(features));

	if (!path || path[0] == '\0') {
		snprintf(info->error, sizeof(info->error),
		    "license file not found: %s", L7_LIC_PATH);
		return -1;
	}

#ifdef L7_DEV_BUILD
	if (is_dev_key()) {
		info->dev_mode = 1;
		info->valid = 1;
		info->features_flags = L7_FEAT_BASE;
		snprintf(info->features, sizeof(info->features), "base");
		snprintf(info->error, sizeof(info->error),
		    "development key — license verification skipped");
		if (layer7_hw_fingerprint(info->hardware_id,
		    sizeof(info->hardware_id)) != 0)
			snprintf(info->hardware_id,
			    sizeof(info->hardware_id), "(unknown)");
		return 0;
	}
#endif /* L7_DEV_BUILD */

	if (layer7_hw_fingerprint(hw_id, sizeof(hw_id)) != 0) {
		snprintf(info->error, sizeof(info->error),
		    "failed to compute hardware fingerprint");
		return -1;
	}
	memcpy(info->hardware_id, hw_id, L7_HW_ID_LEN);

	lic_raw = read_file_alloc(path, &lic_len);
	if (!lic_raw) {
		snprintf(info->error, sizeof(info->error),
		    "license file not found: %s", path);
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

	if (l7_ed25519_verify(data_str, sig_hex) != 0) {
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
	{
		struct l7_features feat;

		if (layer7_features_parse(features, &feat)) {
			fprintf(stderr,
			    "license_features_truncated: features CSV exceeded %d bytes\n",
			    L7_FEATURES_MAX);
		}
		memcpy(info->features, feat.raw, sizeof(info->features));
		info->features_truncated = feat.truncated;
		/* Efectivos = .lic ∩ check-in (check-in só retira). */
		info->features_flags = checkin_effective_features(feat.flags);
	}
	info->days_left = (int)diff_days;

	/* Anti-rollback 30.6: após assinatura+HW OK; antes de aceitar valid/grace. */
	{
		time_t max_seen = 0;
		time_t new_max = 0;
		long delta = 0;
		int suspect;

		(void)clock_mark_load(&max_seen);
		suspect = layer7_clock_eval(now, max_seen, &new_max, &delta);
		info->clock_max_seen = new_max;
		info->clock_delta_sec = delta;
		if (new_max > max_seen)
			(void)clock_mark_save(new_max);

		if (suspect) {
			info->clock_suspect = 1;
			info->valid = 0;
			info->grace = 0;
			if (diff_days < 0)
				info->expired = 1;
			snprintf(info->error, sizeof(info->error),
			    "clock rollback suspected (delta=%ld s) — "
			    "enforce degraded to monitor; sync time and "
			    "restart layer7d",
			    delta);
			return -1;
		}
	}

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

int
layer7_license_check(struct l7_license_info *info)
{
	return layer7_license_check_path(L7_LIC_PATH, info);
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

#define L7_ACTIVATE_BODY_TMP "/var/db/layer7/activate.body"
#define L7_ACTIVATE_HTTP_TMP "/var/db/layer7/activate.http"
#define L7_VAR_DB_DIR "/var/db/layer7"
/* Contrato 30.8 D6 — espelho do path em license.h. */
#ifndef L7_CONTENT_SUBSCRIPTION_PATH
#define L7_CONTENT_SUBSCRIPTION_PATH "/var/db/layer7/content-subscription.json"
#endif

static int
ensure_layer7_var_db(void)
{
	struct stat st;

	if (stat(L7_VAR_DB_DIR, &st) == 0) {
		if (!S_ISDIR(st.st_mode))
			return -1;
		return 0;
	}
	if (mkdir(L7_VAR_DB_DIR, 0755) != 0 && errno != EEXIST)
		return -1;
	return 0;
}

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
write_bytes_0600(const char *path, const void *buf, size_t len)
{
	FILE *out;

	if (!path || !buf)
		return -1;

	out = fopen(path, "w");
	if (!out)
		return -1;

	if (fwrite(buf, 1, len, out) != len) {
		fclose(out);
		(void)unlink(path);
		return -1;
	}
	if (fchmod(fileno(out), 0600) != 0) {
		fclose(out);
		(void)unlink(path);
		return -1;
	}
	if (fclose(out) != 0) {
		(void)unlink(path);
		return -1;
	}
	(void)chmod(path, 0600);
	return 0;
}

#ifdef L7_TEST_ACTIVATE_PROMOTE
static int
l7_test_root_active(void)
{
	const char *r = getenv("LAYER7_TEST_ROOT");

	return (r != NULL && r[0] != '\0');
}

static const char *
l7_test_promote_hook(void)
{
	if (!l7_test_root_active())
		return NULL;
	return getenv("L7_ACTIVATE_PROMOTE_HOOK");
}
#endif

static int
license_sibling_tmp(const char *dest, char *out, size_t outsz)
{
	if (!dest || dest[0] == '\0' || !out || outsz < 8)
		return -1;
	if (snprintf(out, outsz, "%s.tmp", dest) >= (int)outsz)
		return -1;
	return 0;
}

/*
 * Copia src para dest.tmp (0600, mesmo directório), valida o candidato
 * e só então faz rename atómico. Falha/unlink do tmp não toca dest.
 * Não rename de activate.body (/var). Sem fsync novo.
 */
static int
promote_license_atomic(const char *src_path, const char *dest_path,
    struct l7_license_info *info, int *verify_fail)
{
	char tmp[1024];
	char *raw;
	size_t len;
	struct l7_license_info local;
	struct l7_license_info *li = info ? info : &local;
	int need_verify = 1;

	if (verify_fail)
		*verify_fail = 0;
	memset(li, 0, sizeof(*li));

	if (!src_path || !dest_path)
		return -1;
	if (license_sibling_tmp(dest_path, tmp, sizeof(tmp)) != 0)
		return -1;

	raw = read_file_alloc(src_path, &len);
	if (!raw)
		return -1;
	if (write_bytes_0600(tmp, raw, len) != 0) {
		free(raw);
		(void)unlink(tmp);
		return -1;
	}
	free(raw);

#ifdef L7_TEST_ACTIVATE_PROMOTE
	{
		const char *hook = l7_test_promote_hook();

		if (hook && strcmp(hook, "stop-after-write") == 0)
			return -1;
		if (hook && strcmp(hook, "accept-candidate") == 0)
			need_verify = 0;
	}
#endif

	if (need_verify && layer7_license_check_path(tmp, li) != 0) {
		if (verify_fail)
			*verify_fail = 1;
		(void)unlink(tmp);
		return -1;
	}

	if (rename(tmp, dest_path) != 0) {
		(void)unlink(tmp);
		return -1;
	}
	return 0;
}

static int
promote_activate_body(struct l7_license_info *info, int *verify_fail)
{
	return promote_license_atomic(L7_ACTIVATE_BODY_TMP, L7_LIC_PATH,
	    info, verify_fail);
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

	if (ensure_layer7_var_db() != 0) {
		fprintf(stderr,
		    "layer7d: activation failed — cannot create %s\n",
		    L7_VAR_DB_DIR);
		return -1;
	}
	activation_cleanup_temp();

	snprintf(body, sizeof(body),
	    "{\"key\":\"%s\",\"hardware_id\":\"%s\"}", key, hw_id);

	snprintf(cmd, sizeof(cmd),
	    "%s -sS --connect-timeout 10 --max-time 30 "
	    "-o %s -w '%%{http_code}' -X POST "
	    "-H 'Content-Type: application/json' "
	    "-d '%s' '%s' > %s 2>/dev/null",
	    L7_CURL_BIN, L7_ACTIVATE_BODY_TMP, body, url, L7_ACTIVATE_HTTP_TMP);

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

	{
		struct l7_license_info li;
		int verify_fail = 0;

		if (promote_activate_body(&li, &verify_fail) != 0) {
			activation_cleanup_temp();
			if (verify_fail) {
				fprintf(stderr,
				    "layer7d: activation rejected — downloaded license did not "
				    "pass verification: %s\n",
				    li.error);
			} else {
				fprintf(stderr,
				    "layer7d: activation failed — could not save license to "
				    "%s\n", L7_LIC_PATH);
			}
			return -1;
		}

		activation_cleanup_temp();

		fprintf(stderr, "layer7d: license saved to %s\n", L7_LIC_PATH);

		if (layer7_license_check(&li) == 0) {
			(void)layer7_checkin_store_key(key);
			layer7_checkin_mark_ok_from_activate();
			fprintf(stderr,
			    "layer7d: license valid — customer=%s "
			    "expiry=%s features=%s\n",
			    li.customer, li.expiry, li.features);
			return 0;
		}

		fprintf(stderr,
		    "layer7d: activation rejected — downloaded license did not "
		    "pass verification: %s\n",
		    li.error);
		return -1;
	}
}

/* --- BG-077: online check-in / remote revocation --- */

#define L7_CHECKIN_BODY_TMP "/var/db/layer7/checkin.body"
#define L7_CHECKIN_HTTP_TMP "/var/db/layer7/checkin.http"
#define L7_CHECKIN_DEFAULT_URL \
	"https://license.systemup.inf.br/api/license/check-in"

struct l7_checkin_state {
	char license_key[65];
	time_t last_check_in_ok;
	time_t last_check_in_attempt;
	int check_in_interval_hours;
	int max_offline_hours;
	char last_error[256];
	char features[64]; /* último CSV do check-in; vazio = sem redução */
	int features_set;  /* 1 se o servidor enviou o campo features */
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

static int checkin_load_state(struct l7_checkin_state *st);

static unsigned
checkin_effective_features(unsigned lic_flags)
{
	struct l7_checkin_state st;
	struct l7_features feat;

	if (!checkin_load_state(&st) || !st.features_set)
		return lic_flags | L7_FEAT_BASE;

	(void)layer7_features_parse(st.features, &feat);
	return layer7_features_intersect(lic_flags, feat.flags);
}

static void
checkin_state_defaults(struct l7_checkin_state *st)
{
	memset(st, 0, sizeof(*st));
	st->check_in_interval_hours = L7_CHECKIN_DEFAULT_INTERVAL_HOURS;
	st->max_offline_hours = L7_CHECKIN_DEFAULT_MAX_OFFLINE_HOURS;
}

static const char *
checkin_state_path(void)
{
	const char *e = getenv("L7_CHECKIN_STATE_PATH");

	if (e != NULL && e[0] != '\0')
		return e;
	return L7_CHECKIN_STATE_PATH;
}

static int
checkin_load_state(struct l7_checkin_state *st)
{
	char *raw;
	size_t len;

	checkin_state_defaults(st);
	raw = read_file_alloc(checkin_state_path(), &len);
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
	if (json_find_string(raw, "features", st->features,
	    sizeof(st->features))) {
		st->features_set = 1;
	}

	free(raw);
	return st->license_key[0] != '\0' ? 1 : 0;
}

static void
json_escape_fprint(FILE *f, const char *s)
{
	if (!s)
		return;
	for (; *s; s++) {
		unsigned char c = (unsigned char)*s;

		if (c < 0x20)
			continue;
		if (c == '"' || c == '\\')
			fputc('\\', f);
		fputc((int)c, f);
	}
}

static int
checkin_state_lock(const char *state_path)
{
	char lock_path[1024];
	int fd;

	if (!state_path || state_path[0] == '\0')
		return -1;
	if (snprintf(lock_path, sizeof(lock_path), "%s.lock",
	    state_path) >= (int)sizeof(lock_path))
		return -1;
	fd = open(lock_path, O_RDWR | O_CREAT, 0600);
	if (fd < 0)
		return -1;
	if (flock(fd, LOCK_EX) != 0) {
		(void)close(fd);
		return -1;
	}
	return fd;
}

static void
checkin_state_unlock(int fd)
{
	if (fd < 0)
		return;
	(void)flock(fd, LOCK_UN);
	(void)close(fd);
}

static int
checkin_save_state(const struct l7_checkin_state *st)
{
	const char *path;
	const char *tmp;
	char tmp_buf[1024];
	FILE *f;
	int lockfd;
	int rc;

	if (!st)
		return -1;

	path = checkin_state_path();
	lockfd = checkin_state_lock(path);
	if (lockfd < 0)
		return -1;

#ifdef L7_TEST_CHECKIN_STATE
	{
		const char *e = getenv("L7_CHECKIN_STATE_TMP");

		if (e != NULL && e[0] != '\0')
			tmp = e;
		else {
			if (snprintf(tmp_buf, sizeof(tmp_buf), "%s.tmp",
			    path) >= (int)sizeof(tmp_buf)) {
				checkin_state_unlock(lockfd);
				return -1;
			}
			tmp = tmp_buf;
		}
	}
#else
	if (snprintf(tmp_buf, sizeof(tmp_buf), "%s.tmp", path) >=
	    (int)sizeof(tmp_buf)) {
		checkin_state_unlock(lockfd);
		return -1;
	}
	tmp = tmp_buf;
#endif

	f = fopen(tmp, "w");
	if (!f) {
		checkin_state_unlock(lockfd);
		return -1;
	}

	fputs("{\n  \"license_key\": \"", f);
	json_escape_fprint(f, st->license_key);
	fprintf(f, "\",\n  \"last_check_in_ok\": %lld,\n",
	    (long long)st->last_check_in_ok);
	fprintf(f, "  \"last_check_in_attempt\": %lld,\n",
	    (long long)st->last_check_in_attempt);
	fprintf(f, "  \"check_in_interval_hours\": %d,\n",
	    st->check_in_interval_hours);
	fprintf(f, "  \"max_offline_hours\": %d,\n",
	    st->max_offline_hours);
	fputs("  \"last_error\": \"", f);
	json_escape_fprint(f, st->last_error);
	fputs("\",\n  \"features\": \"", f);
	json_escape_fprint(f, st->features_set ? st->features : "");
	fprintf(f, "\",\n  \"features_set\": %s\n}\n",
	    st->features_set ? "true" : "false");

	if (fflush(f) != 0) {
		fclose(f);
		(void)unlink(tmp);
		checkin_state_unlock(lockfd);
		return -1;
	}
	(void)fchmod(fileno(f), 0600);
	if (fclose(f) != 0) {
		(void)unlink(tmp);
		checkin_state_unlock(lockfd);
		return -1;
	}
	(void)chmod(tmp, 0600);
	rc = rename(tmp, path);
	if (rc != 0)
		(void)unlink(tmp);
	else
		(void)chmod(path, 0600);
	checkin_state_unlock(lockfd);
	return rc == 0 ? 0 : -1;
}

/*
 * 30.10 / contrato 30.8: persiste content_subscription do check-in activo.
 * Falha de parse/rede NÃO apaga o ficheiro anterior (R-J / offline).
 * Denied paths não chamam isto — token antigo permanece até exp.
 */
static int
checkin_persist_content_subscription(const char *response_body)
{
	char data_str[1536];
	char sig_hex[140];
	char tmp_path[sizeof(L7_CONTENT_SUBSCRIPTION_PATH) + 8];
	FILE *f;
	size_t i;

	if (!response_body || !strstr(response_body, "\"content_subscription\""))
		return 0;

	memset(data_str, 0, sizeof(data_str));
	memset(sig_hex, 0, sizeof(sig_hex));
	if (!json_find_string(response_body, "data", data_str, sizeof(data_str)))
		return -1;
	if (!json_find_string(response_body, "sig", sig_hex, sizeof(sig_hex)))
		return -1;
	if (strlen(sig_hex) != 128)
		return -1;
	for (i = 0; sig_hex[i] != '\0'; i++) {
		char c = sig_hex[i];
		if (!((c >= '0' && c <= '9') || (c >= 'a' && c <= 'f') ||
		    (c >= 'A' && c <= 'F')))
			return -1;
	}
	if (data_str[0] == '\0')
		return -1;

	if (ensure_layer7_var_db() != 0)
		return -1;

	snprintf(tmp_path, sizeof(tmp_path), "%s.tmp",
	    L7_CONTENT_SUBSCRIPTION_PATH);
	f = fopen(tmp_path, "w");
	if (!f)
		return -1;

	fputs("{\"data\":\"", f);
	for (i = 0; data_str[i] != '\0'; i++) {
		unsigned char c = (unsigned char)data_str[i];
		if (c == '\\' || c == '"')
			fputc('\\', f);
		fputc((int)c, f);
	}
	fprintf(f, "\",\"sig\":\"%s\"}\n", sig_hex);
	if (fclose(f) != 0) {
		(void)unlink(tmp_path);
		return -1;
	}
	(void)chmod(tmp_path, 0600);
	if (rename(tmp_path, L7_CONTENT_SUBSCRIPTION_PATH) != 0) {
		(void)unlink(tmp_path);
		return -1;
	}
	(void)chmod(L7_CONTENT_SUBSCRIPTION_PATH, 0600);
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
	st.features[0] = '\0';
	st.features_set = 0;
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
layer7_checkin_enforce_ready(const char *config_path)
{
	struct l7_checkin_state st;

	if (!layer7_checkin_config_enabled(config_path))
		return 1;
	if (!checkin_load_state(&st) || !st.license_key[0])
		return 0;
	return 1;
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

/* base64url sem padding — 32 bytes → 43 chars (contrato 30.12 D3). */
static int
checkin_b64url_encode32(const unsigned char in[L7_CHECKIN_NONCE_BYTES],
    char out[L7_CHECKIN_NONCE_B64_LEN + 1])
{
	static const char tbl[] =
	    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_";
	size_t i, o;

	o = 0;
	for (i = 0; i + 2 < L7_CHECKIN_NONCE_BYTES; i += 3) {
		unsigned int n = ((unsigned int)in[i] << 16) |
		    ((unsigned int)in[i + 1] << 8) | (unsigned int)in[i + 2];
		out[o++] = tbl[(n >> 18) & 63];
		out[o++] = tbl[(n >> 12) & 63];
		out[o++] = tbl[(n >> 6) & 63];
		out[o++] = tbl[n & 63];
	}
	/* 32 % 3 == 2 → um grupo final de 2 bytes → 3 chars, sem padding. */
	{
		unsigned int n = ((unsigned int)in[30] << 16) |
		    ((unsigned int)in[31] << 8);
		out[o++] = tbl[(n >> 18) & 63];
		out[o++] = tbl[(n >> 12) & 63];
		out[o++] = tbl[(n >> 6) & 63];
	}
	out[o] = '\0';
	return (o == L7_CHECKIN_NONCE_B64_LEN) ? 0 : -1;
}

static int
checkin_generate_nonce(char out[L7_CHECKIN_NONCE_B64_LEN + 1])
{
	unsigned char raw[L7_CHECKIN_NONCE_BYTES];

	if (RAND_bytes(raw, L7_CHECKIN_NONCE_BYTES) != 1)
		return -1;
	return checkin_b64url_encode32(raw, out);
}

/*
 * Contrato 30.12 §4.5 passos 3–6 sobre o JSON interior (já verificado).
 * Retorna 0 se OK; -1 se rejeitar (check-in falho / N3).
 */
static int
checkin_validate_v2_payload(const char *payload, const char *nonce,
    const char *hw_id, time_t now, char *status_out, size_t status_sz)
{
	char got_nonce[L7_CHECKIN_NONCE_B64_LEN + 1];
	char got_hw[L7_HW_ID_LEN];
	char status[32];
	int ver;
	time_t iat;
	long skew;

	if (!payload || !nonce || !hw_id)
		return -1;

	ver = 0;
	if (parse_int_json_field(payload, "v", &ver) != 0 || ver != 1)
		return -1;

	memset(status, 0, sizeof(status));
	if (!json_find_string(payload, "status", status, sizeof(status)))
		return -1;

	memset(got_nonce, 0, sizeof(got_nonce));
	if (!json_find_string(payload, "nonce", got_nonce, sizeof(got_nonce)))
		return -1;
	if (strcmp(got_nonce, nonce) != 0)
		return -1;

	memset(got_hw, 0, sizeof(got_hw));
	if (!json_find_string(payload, "hardware_id", got_hw, sizeof(got_hw)))
		return -1;
	if (strcmp(got_hw, hw_id) != 0)
		return -1;

	iat = 0;
	if (parse_time_json_field(payload, "iat", &iat) != 0 || iat <= 0)
		return -1;
	skew = (long)now - (long)iat;
	if (skew < 0)
		skew = -skew;
	if (skew > L7_CHECKIN_IAT_SKEW_SEC)
		return -1;

	if (status_out && status_sz > 0) {
		snprintf(status_out, status_sz, "%s", status);
	}
	return 0;
}

#ifdef L7_TEST_CHECKIN_SIGNED
int
layer7_checkin_validate_payload_test(const char *payload,
    const char *nonce, const char *hw_id, time_t now,
    char *status_out, size_t status_sz)
{
	return checkin_validate_v2_payload(payload, nonce, hw_id, now,
	    status_out, status_sz);
}
#endif

/*
 * Extrai envelope v2, verifica Ed25519 e passos 3–6.
 * Em sucesso escreve payload interior em payload_out.
 * Retorna 0 OK; -1 envelope/sig/campos inválidos.
 */
static int
checkin_open_signed_envelope(const char *response_body, const char *nonce,
    const char *hw_id, time_t now, char *payload_out, size_t payload_sz,
    char *status_out, size_t status_sz)
{
	char data_str[6144];
	char sig_hex[140];
	size_t i;

	if (!response_body || !payload_out || payload_sz < 2)
		return -1;

	memset(data_str, 0, sizeof(data_str));
	memset(sig_hex, 0, sizeof(sig_hex));
	if (!json_find_string(response_body, "data", data_str, sizeof(data_str)))
		return -1;
	if (!json_find_string(response_body, "sig", sig_hex, sizeof(sig_hex)))
		return -1;
	if (strlen(sig_hex) != 128)
		return -1;
	for (i = 0; sig_hex[i] != '\0'; i++) {
		char c = sig_hex[i];
		if (!((c >= '0' && c <= '9') || (c >= 'a' && c <= 'f') ||
		    (c >= 'A' && c <= 'F')))
			return -1;
	}

	if (l7_ed25519_verify(data_str, sig_hex) != 0)
		return -1;

	if (checkin_validate_v2_payload(data_str, nonce, hw_id, now,
	    status_out, status_sz) != 0)
		return -1;

	if (strlen(data_str) >= payload_sz)
		return -1;
	memcpy(payload_out, data_str, strlen(data_str) + 1);
	return 0;
}

int
layer7_check_in(const char *url)
{
	struct l7_checkin_state st;
	char hw_id[L7_HW_ID_LEN];
	char nonce[L7_CHECKIN_NONCE_B64_LEN + 1];
	char cmd[2048];
	char body[640];
	char http_code[8];
	/* 30.13: envelope + content_subscription aninhado. */
	char response_body[8192];
	char payload[6144];
	char status[32];
	char server_error[256];
	int rc, http_status;
	time_t now;

#ifdef L7_DEV_BUILD
	if (is_dev_key())
		return L7_CHECKIN_SKIP;
#endif /* L7_DEV_BUILD */

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

	if (checkin_generate_nonce(nonce) != 0) {
		fprintf(stderr,
		    "layer7d: check-in failed — cannot generate nonce\n");
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

	now = time(NULL);
	st.last_check_in_attempt = now;
	if (ensure_layer7_var_db() != 0) {
		snprintf(st.last_error, sizeof(st.last_error),
		    "cannot create %s", L7_VAR_DB_DIR);
		(void)checkin_save_state(&st);
		return L7_CHECKIN_NETWORK;
	}
	checkin_cleanup_temp();

	/* 30.13 D2/D9: cliente novo sempre envia nonce. */
	snprintf(body, sizeof(body),
	    "{\"key\":\"%s\",\"hardware_id\":\"%s\",\"nonce\":\"%s\"}",
	    st.license_key, hw_id, nonce);

	snprintf(cmd, sizeof(cmd),
	    "%s -sS --connect-timeout 10 --max-time 30 "
	    "-o %s -w '%%{http_code}' -X POST "
	    "-H 'Content-Type: application/json' "
	    "-d '%s' '%s' > %s 2>/dev/null",
	    L7_CURL_BIN, L7_CHECKIN_BODY_TMP, body, url, L7_CHECKIN_HTTP_TMP);

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

	/*
	 * 30.13 D9/D11: cliente novo exige envelope assinado.
	 * JSON legado, sig inválida, replay ou servidor falso → falha de
	 * check-in sem invalidar licença (N3), excepto revoked/expired
	 * autenticados.
	 */
	memset(payload, 0, sizeof(payload));
	memset(status, 0, sizeof(status));
	if (checkin_open_signed_envelope(response_body, nonce, hw_id, now,
	    payload, sizeof(payload), status, sizeof(status)) != 0) {
		snprintf(st.last_error, sizeof(st.last_error),
		    "unsigned or invalid check-in response");
		(void)checkin_save_state(&st);
		checkin_cleanup_temp();
		fprintf(stderr,
		    "layer7d: check-in failed — response not signed/valid "
		    "(HTTP %d)\n", http_status);
		return L7_CHECKIN_NETWORK;
	}

	if (strcmp(status, "active") == 0) {
		int interval = st.check_in_interval_hours;
		int max_offline = st.max_offline_hours;
		char feat_raw[256];

		if (http_status < 200 || http_status > 299) {
			snprintf(st.last_error, sizeof(st.last_error),
			    "active status with unexpected HTTP %d",
			    http_status);
			(void)checkin_save_state(&st);
			checkin_cleanup_temp();
			return L7_CHECKIN_NETWORK;
		}

		if (parse_int_json_field(payload, "check_in_interval_hours",
		    &interval) == 0)
			st.check_in_interval_hours = interval;
		if (parse_int_json_field(payload, "max_offline_hours",
		    &max_offline) == 0)
			st.max_offline_hours = max_offline;

		memset(feat_raw, 0, sizeof(feat_raw));
		if (json_find_string(payload, "features", feat_raw,
		    sizeof(feat_raw))) {
			struct l7_features feat;

			(void)layer7_features_parse(feat_raw, &feat);
			memcpy(st.features, feat.normalized, sizeof(st.features));
			st.features_set = 1;
		}

		/* 30.10/C10: token dentro do payload assinado. */
		if (checkin_persist_content_subscription(payload) != 0) {
			fprintf(stderr,
			    "layer7d: check-in OK — content_subscription "
			    "persist skipped (keeping previous token if any)\n");
		}

		st.last_check_in_ok = time(NULL);
		st.last_error[0] = '\0';
		(void)checkin_save_state(&st);
		checkin_cleanup_temp();
		fprintf(stderr, "layer7d: check-in OK — license active\n");
		return L7_CHECKIN_OK;
	}

	if (strcmp(status, "revoked") == 0 || strcmp(status, "expired") == 0) {
		server_error[0] = '\0';
		(void)json_find_string(payload, "error", server_error,
		    sizeof(server_error));
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

	snprintf(st.last_error, sizeof(st.last_error),
	    "check-in rejected (status=%s HTTP %d)", status, http_status);
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

#ifdef L7_TEST_CHECKIN_STATE
int
layer7_test_checkin_save_error(const char *key, const char *last_error)
{
	struct l7_checkin_state st;

	checkin_state_defaults(&st);
	if (checkin_load_state(&st))
		;
	if (key && key[0] != '\0' && json_safe_string(key))
		snprintf(st.license_key, sizeof(st.license_key), "%s", key);
	if (last_error)
		snprintf(st.last_error, sizeof(st.last_error), "%s", last_error);
	return checkin_save_state(&st);
}

int
layer7_test_write_bytes_0600(const char *path, const void *buf, size_t len)
{
	return write_bytes_0600(path, buf, len);
}
#endif

#ifdef L7_TEST_ACTIVATE_PROMOTE
int
layer7_test_promote_license(const char *src_path, const char *dest_path)
{
	struct l7_license_info li;
	int verify_fail = 0;

	return promote_license_atomic(src_path, dest_path, &li, &verify_fail);
}
#endif
