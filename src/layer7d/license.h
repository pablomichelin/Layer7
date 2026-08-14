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
#define L7_CHECKIN_STATE_PATH "/var/db/layer7-checkin.json"
#define L7_CONTENT_SUBSCRIPTION_PATH "/var/db/layer7/content-subscription.json"
#define L7_CHECKIN_DEFAULT_INTERVAL_HOURS 168
#define L7_CHECKIN_DEFAULT_MAX_OFFLINE_HOURS 336

/* Anti-rollback 30.6 / ADR-0033 — marca do maior timestamp observado.
 * Limiar conservador: retrocesso ≤ 1 dia = tolerado (NTP/VM); > 1 dia =
 * estado temporal suspeito → monitor. RR-4: apagar o ficheiro ou relógio
 * congelado desde a instalação contornam isto; fecho real = AP3. */
#define L7_CLOCK_MARK_PATH       "/var/db/layer7/clock-mark.json"
#define L7_CLOCK_SUSPECT_SEC     86400L  /* 1 dia */

#define L7_CHECKIN_OK           0
#define L7_CHECKIN_DENIED       1
#define L7_CHECKIN_NETWORK      2
#define L7_CHECKIN_SKIP         3
#define L7_CHECKIN_OFFLINE_MAX  4

/* 30.13 / contrato 30.12 — skew de iat (±1 dia); falha ⇒ check-in falho (N3). */
#define L7_CHECKIN_IAT_SKEW_SEC 86400L
#define L7_CHECKIN_NONCE_BYTES  32
#define L7_CHECKIN_NONCE_B64_LEN 43 /* base64url sem padding */

struct l7_license_info {
	int   valid;          /* 1 = signature ok + hw match + not expired (or grace) */
	int   expired;        /* 1 = past expiry date */
	int   grace;          /* 1 = expired but within grace period */
	int   days_left;      /* days until expiry; negative if expired */
	int   dev_mode;       /* 1 = placeholder key, verification skipped */
	int   clock_suspect;  /* 1 = anti-rollback: relógio atrás da marca (30.6) */
	time_t clock_max_seen;/* maior timestamp persistido / observado */
	long  clock_delta_sec;/* max_seen - now quando há retrocesso; senão 0 */
	char  hardware_id[L7_HW_ID_LEN];
	char  customer[256];
	char  expiry[16];     /* YYYY-MM-DD */
	char  features[64];   /* raw CSV do .lic (truncado a 63; ADR-0025 P1) */
	unsigned features_flags; /* L7_FEAT_* parseados (sempre inclui BASE) */
	int   features_truncated; /* 1 se o CSV do .lic excedeu 63 bytes */
	char  error[256];
};

struct l7_checkin_status {
	int    enabled;
	int    ok;
	time_t last_ok;
	time_t last_attempt;
	time_t next_due;
	int    interval_hours;
	int    max_offline_hours;
	char   last_error[256];
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
 * 30.16 / BG-122 — gates de enforce (puros, sem I/O).
 * Gate A: bit canónico `valid`.
 * Gate B: recomputa a partir de expiry/expired/grace/clock_suspect.
 * allows_enforce: ambos 1; discordância ⇒ 0 (monitor / N2).
 */
int layer7_license_gate_a(const struct l7_license_info *li);
int layer7_license_gate_b(const struct l7_license_info *li);
int layer7_license_allows_enforce(const struct l7_license_info *li);

/*
 * Avalia anti-rollback temporal (puro — sem I/O). Retorna 1 se suspeito
 * (retrocesso > L7_CLOCK_SUSPECT_SEC), 0 caso contrário.
 * Nunca move *new_max_seen para trás. Usado por license_check e testes.
 */
int layer7_clock_eval(time_t now, time_t max_seen,
    time_t *new_max_seen, long *delta_sec);

/*
 * Attempt online activation: POST fingerprint + key to server, save .lic.
 * Returns 0 on success, -1 on failure (prints error to stderr).
 * url may be NULL to use default server.
 */
int layer7_activate(const char *key, const char *url);

/*
 * BG-077: periodic online check-in with license server.
 * Returns L7_CHECKIN_* codes. url may be NULL for default endpoint.
 */
int layer7_checkin_store_key(const char *key);
void layer7_checkin_mark_ok_from_activate(void);
int layer7_checkin_config_enabled(const char *config_path);
/*
 * P1-5 / BG-128: 1 se o check-in NÃO é obrigatório (flag ausente/false =
 * air-gap) OU existe license_key no estado. 0 se check_in_enabled e não
 * há chave — enforce deve recusar. Não trata falha de rede (N3).
 */
int layer7_checkin_enforce_ready(const char *config_path);
int layer7_checkin_due(time_t now);
int layer7_checkin_offline_expired(time_t now);
int layer7_check_in(const char *url);
int layer7_checkin_get_status(struct l7_checkin_status *st);

#ifdef L7_TEST_CHECKIN_SIGNED
/*
 * Harness 30.13: valida payload interior v2 (passos 3–6 do contrato) sem I/O.
 * Retorna 0 se campos OK; -1 se rejeitado. status_out recebe status.
 */
int layer7_checkin_validate_payload_test(const char *payload,
    const char *nonce, const char *hw_id, time_t now,
    char *status_out, size_t status_sz);
#endif

#ifdef L7_TEST_CHECKIN_STATE
/*
 * Harness P2-7/P2-8/P2-10: persistência atómica + escape + promote 0600.
 * Não entra no binário do port (flag ausente do Makefile).
 */
int layer7_test_checkin_save_error(const char *key, const char *last_error);
int layer7_test_write_bytes_0600(const char *path, const void *buf, size_t len);
#endif

#endif /* LAYER7_LICENSE_H */
