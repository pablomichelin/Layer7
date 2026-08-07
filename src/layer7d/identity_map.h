/*
 * Identity session map — 20.12 structs + 20.13 API (add/refresh/expire,
 * lookup/export, dump JSON). Gate entitlement = 20.15.
 *
 * Com identity OFF o daemon não chama init (zero threads, zero alocação).
 */
#ifndef LAYER7_IDENTITY_MAP_H
#define LAYER7_IDENTITY_MAP_H

#include <pthread.h>
#include <stddef.h>
#include <stdint.h>
#include <time.h>

/* ADR-0027 §4.3 — defaults (ajustáveis no futuro via config) */
#define L7_IDMAP_MAX_IPS_PER_USER   16
#define L7_IDMAP_MAX_SESSIONS     4096
#define L7_IDMAP_DEFAULT_TTL_SEC  3600
#define L7_IDMAP_MAX_GROUPS_CACHE   32
#define L7_IDMAP_CONFLICT_WINDOW_SEC 60

#define L7_IDMAP_USER_MAX          128
#define L7_IDMAP_GROUP_MAX          64

/* Fontes de sessão (ADR-0027 §2) */
enum l7_id_source {
	L7_ID_SRC_NONE = 0,
	L7_ID_SRC_RADIUS = 1,
	L7_ID_SRC_DC_AGENT = 2,
	L7_ID_SRC_ENDPOINT = 3, /* IM7 */
	L7_ID_SRC_TS = 4,       /* IM8 */
	L7_ID_SRC_MANUAL = 5    /* diagnóstico / testes */
};

/* Endereço compacto (v4 nos primeiros 4 bytes; v6 completo). */
struct l7_id_addr {
	uint8_t family; /* AF_INET ou AF_INET6 */
	uint8_t addr[16];
};

/*
 * Sessão user → IPs + grupos em cache.
 * multi_user: IP partilhado por users concorrentes (ADR-0027 §4.1).
 */
struct l7_id_session {
	int                 in_use;
	char                user[L7_IDMAP_USER_MAX];
	struct l7_id_addr   ips[L7_IDMAP_MAX_IPS_PER_USER];
	unsigned            n_ips;
	enum l7_id_source   source;
	time_t              seen_at;
	time_t              expires_at; /* seen_at + ttl */
	char                groups[L7_IDMAP_MAX_GROUPS_CACHE][L7_IDMAP_GROUP_MAX];
	unsigned            n_groups;
	int                 multi_user;
};

struct l7_id_map {
	pthread_rwlock_t     lock;
	struct l7_id_session *sessions;
	unsigned             capacity;       /* L7_IDMAP_MAX_SESSIONS */
	unsigned             count;          /* entradas in_use */
	unsigned             default_ttl_sec;
	unsigned             conflict_window_sec;
	int                  initialized;
};

struct l7_id_map_limits {
	unsigned max_ips_per_user;
	unsigned max_sessions;
	unsigned default_ttl_sec;
	unsigned max_groups_cache;
	unsigned conflict_window_sec;
};

void layer7_idmap_limits(struct l7_id_map_limits *out);

/* Aloca tabela + rwlock. Retorna 0 OK, -1 erro. */
int layer7_idmap_init(struct l7_id_map *m);

/* Liberta tabela; safe se nunca init ou já fini. */
void layer7_idmap_fini(struct l7_id_map *m);

unsigned layer7_idmap_count(const struct l7_id_map *m);
unsigned layer7_idmap_capacity(const struct l7_id_map *m);

/* Wrappers rwlock (escritores = fontes; leitores = hot path futuro). */
int layer7_idmap_rdlock(struct l7_id_map *m);
int layer7_idmap_wrlock(struct l7_id_map *m);
int layer7_idmap_unlock(struct l7_id_map *m);

/* --- 20.13 API (adquire wrlock/rdlock internamente) --- */

/*
 * Add ou refresh: associa IP ao user, actualiza seen/expires/source/groups.
 * groups pode ser NULL / n_groups=0.
 * Retorna 0 OK, -1 erro, 1 limite IPs/grupos (parcial), 2 mapa cheio após eviction.
 */
int layer7_idmap_upsert(struct l7_id_map *m, const char *user,
    const struct l7_id_addr *ip, enum l7_id_source source,
    const char *const *groups, unsigned n_groups, time_t now);

/* Renova TTL de todas as entradas do user. 0 OK, -1 não encontrado. */
int layer7_idmap_refresh(struct l7_id_map *m, const char *user, time_t now);

/* Remove sessões com expires_at <= now. Devolve quantas removeu. */
unsigned layer7_idmap_expire(struct l7_id_map *m, time_t now);

/* Remove sessão do user (logout). 0 OK, -1 não encontrado. */
int layer7_idmap_remove_user(struct l7_id_map *m, const char *user);

/*
 * Lookup por IP (hot path futuro).
 * 0 = user único em out_user; 1 = multi_user (ad_* → não-match);
 * -1 = não encontrado / erro.
 */
int layer7_idmap_lookup_ip(struct l7_id_map *m, const struct l7_id_addr *ip,
    char *out_user, size_t out_user_sz, enum l7_id_source *out_src);

/*
 * Export IPs do user para buffer (enforce/PF futuro).
 * Devolve n copiados, 0 se user ausente, -1 erro.
 */
int layer7_idmap_export_user_ips(struct l7_id_map *m, const char *user,
    struct l7_id_addr *out, unsigned max_out);

/*
 * Dump JSON diagnóstico (sem secrets). Truncável.
 * Devolve bytes escritos (sem contar NUL) ou -1.
 */
int layer7_idmap_dump_json(struct l7_id_map *m, char *buf, size_t bufsz);

/* Helpers de endereço (testes / fontes). */
int layer7_idmap_addr_equal(const struct l7_id_addr *a,
    const struct l7_id_addr *b);
int layer7_idmap_addr_set_ipv4(struct l7_id_addr *a, uint32_t host_order);
int layer7_idmap_addr_set_ipv6(struct l7_id_addr *a, const uint8_t in6[16]);

#endif /* LAYER7_IDENTITY_MAP_H */
