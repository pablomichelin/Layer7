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

int
layer7_activate(const char *key, const char *url)
{
	char hw_id[L7_HW_ID_LEN];
	char cmd[1024];
	char body[512];
	int rc;

	if (!key || key[0] == '\0') {
		fprintf(stderr, "layer7d: activation key is required\n");
		return -1;
	}

	if (layer7_hw_fingerprint(hw_id, sizeof(hw_id)) != 0) {
		fprintf(stderr,
		    "layer7d: failed to compute hardware fingerprint\n");
		return -1;
	}

	if (!url || url[0] == '\0')
		url = "https://license.systemup.inf.br/api/activate";

	fprintf(stderr, "layer7d: activating...\n");
	fprintf(stderr, "  server:       %s\n", url);
	fprintf(stderr, "  hardware_id:  %s\n", hw_id);
	fprintf(stderr, "  key:          %.8s...\n", key);

	snprintf(body, sizeof(body),
	    "{\"key\":\"%s\",\"hardware_id\":\"%s\"}", key, hw_id);

	snprintf(cmd, sizeof(cmd),
	    "/usr/bin/fetch -qo %s -T 15 "
	    "-H 'Content-Type: application/json' "
	    "\"POST:%s\" <<'EOFBODY'\n%s\nEOFBODY",
	    L7_LIC_PATH, url, body);

	/*
	 * For V1 without a license server, use curl if available,
	 * otherwise fall back to fetch. Both are common on FreeBSD.
	 */
	snprintf(cmd, sizeof(cmd),
	    "curl -sf -o %s -X POST "
	    "-H 'Content-Type: application/json' "
	    "-d '%s' '%s' 2>/dev/null || "
	    "fetch -qo %s -T 15 '%s' 2>/dev/null",
	    L7_LIC_PATH, body, url,
	    L7_LIC_PATH, url);

	rc = system(cmd);
	if (rc != 0) {
		fprintf(stderr,
		    "layer7d: activation failed — could not reach "
		    "license server at %s\n"
		    "  Ensure the server is running and reachable.\n"
		    "  Alternatively, place a valid .lic file at %s\n",
		    url, L7_LIC_PATH);
		return -1;
	}

	fprintf(stderr, "layer7d: license saved to %s\n", L7_LIC_PATH);

	{
		struct l7_license_info li;
		if (layer7_license_check(&li) == 0) {
			fprintf(stderr,
			    "layer7d: license valid — customer=%s "
			    "expiry=%s features=%s\n",
			    li.customer, li.expiry, li.features);
			return 0;
		} else {
			fprintf(stderr,
			    "layer7d: warning — downloaded license did not "
			    "pass verification: %s\n",
			    li.error);
			return -1;
		}
	}
}
