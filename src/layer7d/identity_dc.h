/*
 * identity_dc.h — IM5 / 20.20 (+ remove_ip em logoff, 20.21)
 * Receiver HTTPS do agente DC: token + HMAC-SHA256 → mapa (L7_ID_SRC_DC_AGENT).
 * Desenho: docs/01-architecture/desenho-canal-agente-dc-20.20.md
 * Nunca logar o token/secret.
 */
#ifndef LAYER7_IDENTITY_DC_H
#define LAYER7_IDENTITY_DC_H

#include "identity_map.h"

#include <stddef.h>
#include <stdint.h>
#include <time.h>

#define L7_DC_SECRET_MAX      256
#define L7_DC_BIND_MAX         64
#define L7_DC_ACL_MAX          32
#define L7_DC_ACL_ADDR_MAX     64
#define L7_DC_DEFAULT_PORT     8743
#define L7_DC_DEFAULT_SKEW_SEC 300
#define L7_DC_BODY_MAX         4096
#define L7_DC_PATH             "/v1/identity/events"
#define L7_DC_SECRET_PATH \
	"/usr/local/etc/layer7/identity-dc.secret"
#define L7_DC_CERT_PATH \
	"/usr/local/etc/layer7/identity-dc.crt"
#define L7_DC_KEY_PATH \
	"/usr/local/etc/layer7/identity-dc.key"

enum l7_dc_event_type {
	L7_DC_EVT_LOGON = 1,
	L7_DC_EVT_LOGOFF = 2,
	L7_DC_EVT_HEARTBEAT = 3
};

enum l7_dc_status {
	L7_DC_STATUS_OFF = 0,
	L7_DC_STATUS_LISTEN = 1,
	L7_DC_STATUS_ERROR = 2
};

struct l7_dc_cfg {
	int      identity_enabled;
	int      dc_enabled;
	int      listen_port;
	char     bind_address[L7_DC_BIND_MAX];
	char     dc_acl[L7_DC_ACL_MAX][L7_DC_ACL_ADDR_MAX];
	unsigned n_dc;
	int      skew_sec;
	char     secret[L7_DC_SECRET_MAX];
	int      secret_loaded;
	char     cert_path[256];
	char     key_path[256];
};

struct l7_dc_event {
	char              user[L7_IDMAP_USER_MAX];
	struct l7_id_addr ip;
	int               has_ip;
	enum l7_dc_event_type type;
	time_t            timestamp;
	int               valid;
};

void layer7_dc_cfg_defaults(struct l7_dc_cfg *c);
int layer7_dc_cfg_parse_json(const char *json, size_t len, struct l7_dc_cfg *out);
int layer7_dc_cfg_load_secret(struct l7_dc_cfg *c, const char *path);
void layer7_dc_cfg_wipe_secret(struct l7_dc_cfg *c);

int layer7_dc_peer_allowed(const struct l7_dc_cfg *c, const char *peer_ip);

/*
 * Canonical: timestamp + "\n" + METHOD + "\n" + path + "\n" + sha256_hex(body)
 * out_hex deve ter ≥ 65 bytes (64 hex + NUL).
 */
int layer7_dc_hmac_hex(const char *secret, const char *canonical,
    char *out_hex, size_t out_hex_sz);

int layer7_dc_build_canonical(long timestamp, const char *method,
    const char *path, const uint8_t *body, size_t body_len,
    char *out, size_t out_sz);

/* Comparação timing-safe de dois hex HMAC (lowercase). 1 = iguais. */
int layer7_dc_hmac_equal(const char *a, const char *b);

int layer7_dc_check_skew(time_t event_ts, time_t now, int skew_sec);

/* Parse JSON body mínimo. 0 OK, -1 inválido. */
int layer7_dc_parse_event(const char *json, size_t len, struct l7_dc_event *out);

int layer7_dc_apply_event(struct l7_id_map *map, const struct l7_dc_event *ev,
    time_t now);

/*
 * Valida token + HMAC + skew e aplica. headers via callbacks simplificados:
 * token/signature/timestamp já extraídos pelo caller HTTP.
 * Retorna 0 OK, -1 auth, -2 skew/payload, -3 apply.
 */
int layer7_dc_handle_push(struct l7_id_map *map, const struct l7_dc_cfg *cfg,
    const char *token_hdr, const char *sig_hdr, long ts_hdr,
    const char *method, const char *path,
    const uint8_t *body, size_t body_len, time_t now);

/* --- Worker thread (ADR-0028) --- */

struct l7_dc_worker;

struct l7_dc_worker *layer7_dc_worker_start(struct l7_id_map *map,
    const struct l7_dc_cfg *cfg);
void layer7_dc_worker_reload(struct l7_dc_worker *w, const struct l7_dc_cfg *cfg);
void layer7_dc_worker_stop(struct l7_dc_worker *w);
enum l7_dc_status layer7_dc_worker_status(const struct l7_dc_worker *w);
unsigned layer7_dc_worker_accepted(const struct l7_dc_worker *w);
unsigned layer7_dc_worker_rejected(const struct l7_dc_worker *w);

#endif /* LAYER7_IDENTITY_DC_H */
