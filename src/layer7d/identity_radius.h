/*
 * identity_radius.h — IM5 / 20.19
 * RADIUS accounting receiver (UDP): User-Name + Framed-IP → mapa daemon.
 * Secret + ACL NAS; thread própria (ADR-0028). Nunca logar o shared secret.
 */
#ifndef LAYER7_IDENTITY_RADIUS_H
#define LAYER7_IDENTITY_RADIUS_H

#include "identity_map.h"

#include <stddef.h>
#include <stdint.h>
#include <time.h>

#define L7_RADIUS_SECRET_MAX     256
#define L7_RADIUS_BIND_MAX       64
#define L7_RADIUS_NAS_MAX        32
#define L7_RADIUS_NAS_ADDR_MAX   64
#define L7_RADIUS_DEFAULT_PORT   1813
#define L7_RADIUS_PKT_MAX        4096
#define L7_RADIUS_SECRET_PATH \
	"/usr/local/etc/layer7/identity-radius.secret"

/* Acct-Status-Type (RFC 2866) */
#define L7_RADIUS_ACCT_START            1
#define L7_RADIUS_ACCT_STOP             2
#define L7_RADIUS_ACCT_INTERIM_UPDATE  3

enum l7_radius_status {
	L7_RADIUS_STATUS_OFF = 0,
	L7_RADIUS_STATUS_LISTEN = 1,
	L7_RADIUS_STATUS_ERROR = 2
};

struct l7_radius_cfg {
	int      identity_enabled;
	int      radius_enabled;
	int      listen_port; /* 1..65535; default 1813 */
	char     bind_address[L7_RADIUS_BIND_MAX]; /* "0.0.0.0" / IPv4 */
	char     nas_acl[L7_RADIUS_NAS_MAX][L7_RADIUS_NAS_ADDR_MAX];
	unsigned n_nas;
	char     secret[L7_RADIUS_SECRET_MAX]; /* runtime only; never log */
	int      secret_loaded;
};

/* Evento parseado (sem secret). */
struct l7_radius_acct_event {
	char             user[L7_IDMAP_USER_MAX];
	struct l7_id_addr ip;
	int              has_ip;
	int              status_type; /* Start/Stop/Interim */
	int              valid;       /* 1 se User-Name + IP + status OK */
};

void layer7_radius_cfg_defaults(struct l7_radius_cfg *c);

/*
 * Parse bloco identity.radius do JSON layer7.json (heurístico).
 * Não carrega secret — chamar layer7_radius_cfg_load_secret().
 */
int layer7_radius_cfg_parse_json(const char *json, size_t len,
    struct l7_radius_cfg *out);

/* Lê secret 0600. path NULL → env LAYER7_RADIUS_SECRET ou path default. */
int layer7_radius_cfg_load_secret(struct l7_radius_cfg *c, const char *path);

void layer7_radius_cfg_wipe_secret(struct l7_radius_cfg *c);

/* 1 se peer (string IP) está na ACL; ACL vazia = rejeitar. */
int layer7_radius_nas_allowed(const struct l7_radius_cfg *c,
    const char *peer_ip);

/*
 * Valida Authenticator + extrai atributos de Accounting-Request (code 4).
 * secret deve estar carregado. Retorna 0 OK, -1 inválido.
 * Nunca escreve o secret em out.
 */
int layer7_radius_parse_accounting(const uint8_t *pkt, size_t pkt_len,
    const char *secret, struct l7_radius_acct_event *out);

/*
 * Constrói Accounting-Response (code 5) para o request.
 * out_len deve ser >= 20. Retorna bytes escritos ou -1.
 */
int layer7_radius_build_response(const uint8_t *req, size_t req_len,
    const char *secret, uint8_t *out, size_t out_cap);

/*
 * Aplica evento ao mapa: Start/Interim → upsert; Stop → remove_ip (ou
 * remove_user se sem Framed-IP). Usernames normalizados (20.21).
 * Retorna 0 OK, -1 erro / evento inválido.
 */
int layer7_radius_apply_event(struct l7_id_map *map,
    const struct l7_radius_acct_event *ev, time_t now);

/* --- Worker thread (ADR-0028) --- */

struct l7_radius_worker;

struct l7_radius_worker *layer7_radius_worker_start(struct l7_id_map *map,
    const struct l7_radius_cfg *cfg);

void layer7_radius_worker_reload(struct l7_radius_worker *w,
    const struct l7_radius_cfg *cfg);

void layer7_radius_worker_stop(struct l7_radius_worker *w);

enum l7_radius_status layer7_radius_worker_status(
    const struct l7_radius_worker *w);

/* Contadores diagnóstico (sem secrets). */
unsigned layer7_radius_worker_accepted(const struct l7_radius_worker *w);
unsigned layer7_radius_worker_rejected(const struct l7_radius_worker *w);

#endif /* LAYER7_IDENTITY_RADIUS_H */
