/*
 * Identity session map — estruturas + limites + rwlock (passo 20.12 / ADR-0027 §4.3,
 * ADR-0028). API add/refresh/expire + dump = 20.13; gate entitlement = 20.15.
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
 * multi_user: IP partilhado por users concorrentes (ADR-0027 §4.1) —
 * preenchido na API 20.13; campo reservado já na struct.
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
	int                  initialized;
};

struct l7_id_map_limits {
	unsigned max_ips_per_user;
	unsigned max_sessions;
	unsigned default_ttl_sec;
	unsigned max_groups_cache;
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

#endif /* LAYER7_IDENTITY_MAP_H */
