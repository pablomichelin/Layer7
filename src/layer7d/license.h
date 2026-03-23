/*
 * Layer7 license verification — hardware fingerprint + Ed25519 signed .lic.
 * Public key embedded at compile time; private key used only by license server
 * or the generate-license.py script.
 */
#ifndef LAYER7_LICENSE_H
#define LAYER7_LICENSE_H

#include <stddef.h>
#include <time.h>

#define L7_LIC_PATH        "/usr/local/etc/layer7.lic"
#define L7_HW_ID_LEN       65  /* 64 hex chars + NUL */
#define L7_LIC_GRACE_DAYS  14

struct l7_license_info {
	int   valid;          /* 1 = signature ok + hw match + not expired (or grace) */
	int   expired;        /* 1 = past expiry date */
	int   grace;          /* 1 = expired but within grace period */
	int   days_left;      /* days until expiry; negative if expired */
	int   dev_mode;       /* 1 = placeholder key, verification skipped */
	char  hardware_id[L7_HW_ID_LEN];
	char  customer[256];
	char  expiry[16];     /* YYYY-MM-DD */
	char  features[64];
	char  error[256];
};

/*
 * Compute hardware fingerprint: SHA256(kern.hostuuid + ":" + first-NIC-MAC).
 * Writes 64 hex chars + NUL to out. Returns 0 on success, -1 on error.
 */
int layer7_hw_fingerprint(char *out, size_t outsz);

/*
 * Verify /usr/local/etc/layer7.lic against embedded public key and local
 * hardware fingerprint. Fills info struct. Returns 0 if license valid
 * (enforce allowed), -1 if invalid (monitor-only).
 */
int layer7_license_check(struct l7_license_info *info);

/*
 * Attempt online activation: POST fingerprint + key to server, save .lic.
 * Returns 0 on success, -1 on failure (prints error to stderr).
 * url may be NULL to use default server.
 */
int layer7_activate(const char *key, const char *url);

#endif /* LAYER7_LICENSE_H */
