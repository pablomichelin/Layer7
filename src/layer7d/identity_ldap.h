/*
 * identity_ldap.h — IM4 / 20.17
 * Config LDAP + cache de grupos + fail-mode (ADR-0027 §4) + worker (ADR-0028).
 * Nunca logar bind password. IO só na thread worker (nunca no hot path).
 */
#ifndef LAYER7_IDENTITY_LDAP_H
#define LAYER7_IDENTITY_LDAP_H

#include "identity_map.h"

#include <stddef.h>
#include <time.h>

#define L7_LDAP_HOST_MAX     256
#define L7_LDAP_DN_MAX       512
#define L7_LDAP_FILTER_MAX   512
#define L7_LDAP_NAME_MAX     L7_IDMAP_GROUP_MAX
#define L7_LDAP_SECRET_MAX   256
#define L7_LDAP_CACHE_ENTRIES 256
#define L7_LDAP_DEFAULT_CACHE_TTL_SEC 300
#define L7_LDAP_DEFAULT_PORT_LDAPS 636
#define L7_LDAP_DEFAULT_PORT_LDAP  389
#define L7_LDAP_SECRET_PATH \
	"/usr/local/etc/layer7/identity-ldap.secret"

enum l7_ldap_status {
	L7_LDAP_STATUS_OFF = 0,       /* config/entitlement off */
	L7_LDAP_STATUS_OK = 1,        /* último refresh OK */
	L7_LDAP_STATUS_DEGRADED = 2,  /* down mas cache ainda válido */
	L7_LDAP_STATUS_DOWN = 3       /* down e cache expirado / vazio */
};

struct l7_ldap_cfg {
	int      identity_enabled;
	int      ldap_enabled;
	char     server[L7_LDAP_HOST_MAX];
	int      port;
	int      use_tls;
	char     bind_dn[L7_LDAP_DN_MAX];
	char     base_dn[L7_LDAP_DN_MAX];
	char     user_filter[L7_LDAP_FILTER_MAX];
	char     group_filter[L7_LDAP_FILTER_MAX];
	int      group_depth;   /* 1..10 */
	int      max_members;   /* 1..16384 */
	unsigned cache_ttl_sec;
	char     bind_password[L7_LDAP_SECRET_MAX]; /* runtime only; never log */
	int      password_loaded;
};

/* Defaults GUI 20.16 / ADR-0027. */
void layer7_ldap_cfg_defaults(struct l7_ldap_cfg *c);

/*
 * Parse bloco identity/ldap do JSON layer7.json (heurístico).
 * Não carrega password — chamar layer7_ldap_cfg_load_secret().
 */
int layer7_ldap_cfg_parse_json(const char *json, size_t len,
    struct l7_ldap_cfg *out);

/* Lê secret 0600. path NULL → L7_LDAP_SECRET_PATH. 0 OK, -1 ausente/erro. */
int layer7_ldap_cfg_load_secret(struct l7_ldap_cfg *c, const char *path);

/* Apaga password da memória. */
void layer7_ldap_cfg_wipe_secret(struct l7_ldap_cfg *c);

/* --- Cache / fail-mode --- */

struct l7_ldap_cache;

struct l7_ldap_cache *layer7_ldap_cache_create(unsigned ttl_sec);
void layer7_ldap_cache_destroy(struct l7_ldap_cache *c);
void layer7_ldap_cache_clear(struct l7_ldap_cache *c);
void layer7_ldap_cache_set_ttl(struct l7_ldap_cache *c, unsigned ttl_sec);

/* Marca último contacto LDAP OK ou falha (actualiza status/fail-mode). */
void layer7_ldap_cache_mark_ok(struct l7_ldap_cache *c, time_t now);
void layer7_ldap_cache_mark_fail(struct l7_ldap_cache *c, time_t now);

enum l7_ldap_status layer7_ldap_cache_status(const struct l7_ldap_cache *c,
    time_t now);

/*
 * Guarda membros de um grupo (CN/sAMAccountName).
 * Truncado a max_members do cfg no caller.
 */
int layer7_ldap_cache_put_group(struct l7_ldap_cache *c, const char *group,
    const char *const *members, unsigned n_members, time_t now);

/*
 * Lookup membros. 0 OK (pode n=0), -1 miss, -2 DOWN (fail-mode sem cache).
 * DEGRADED serve entradas ainda dentro do TTL.
 */
int layer7_ldap_cache_get_group(struct l7_ldap_cache *c, const char *group,
    char out[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out,
    time_t now);

int layer7_ldap_cache_put_user_groups(struct l7_ldap_cache *c,
    const char *user, const char *const *groups, unsigned n_groups,
    time_t now);

int layer7_ldap_cache_get_user_groups(struct l7_ldap_cache *c,
    const char *user, char out[][L7_LDAP_NAME_MAX], unsigned max_out,
    unsigned *n_out, time_t now);

/* --- Expand (provider injectável para testes) --- */

typedef int (*l7_ldap_expand_group_fn)(const struct l7_ldap_cfg *cfg,
    const char *group, char members[][L7_LDAP_NAME_MAX], unsigned max_out,
    unsigned *n_out);

typedef int (*l7_ldap_user_groups_fn)(const struct l7_ldap_cfg *cfg,
    const char *user, char groups[][L7_LDAP_NAME_MAX], unsigned max_out,
    unsigned *n_out);

/* NULL = implementação OpenLDAP (se compilada) ou -1. */
void layer7_ldap_set_providers(l7_ldap_expand_group_fn expand,
    l7_ldap_user_groups_fn user_groups);

/*
 * Resolve grupo → membros (provider + cache).
 * Em sucesso actualiza cache e mark_ok.
 * Em falha mark_fail e tenta servir cache (DEGRADED) ou -2 (DOWN).
 */
int layer7_ldap_resolve_group(struct l7_ldap_cache *cache,
    const struct l7_ldap_cfg *cfg, const char *group,
    char members[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out,
    time_t now);

int layer7_ldap_resolve_user_groups(struct l7_ldap_cache *cache,
    const struct l7_ldap_cfg *cfg, const char *user,
    char groups[][L7_LDAP_NAME_MAX], unsigned max_out, unsigned *n_out,
    time_t now);

/* --- Worker thread (ADR-0028) --- */

struct l7_ldap_worker;

/*
 * Arranca thread se cfg.ldap_enabled && identity. Para se OFF.
 * map pode ser NULL só em testes de cache; em produção passa o mapa.
 */
struct l7_ldap_worker *layer7_ldap_worker_start(struct l7_id_map *map,
    const struct l7_ldap_cfg *cfg);

/* Relê cfg (SIGHUP). Copia cfg; password incluída — wipe no caller após. */
void layer7_ldap_worker_reload(struct l7_ldap_worker *w,
    const struct l7_ldap_cfg *cfg);

void layer7_ldap_worker_stop(struct l7_ldap_worker *w);

enum l7_ldap_status layer7_ldap_worker_status(const struct l7_ldap_worker *w,
    time_t now);

/* Acesso ao cache do worker (diagnóstico / 20.18). NULL se parado. */
struct l7_ldap_cache *layer7_ldap_worker_cache(struct l7_ldap_worker *w);

#endif /* LAYER7_IDENTITY_LDAP_H */
