/*
 * Identity session map — 20.12 structs + 20.13 API + 20.14 persistência.
 * Gate entitlement / init em main = 20.15.
 *
 * Com identity OFF o daemon não chama init (zero threads, zero alocação).
 * SIGHUP (ADR-0027 §4.2 / ADR-0028): o mapa vivo NÃO deve ser clear/fini —
 * só config das fontes é relida; usar clear apenas em shutdown explícito.
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

/* Snapshot best-effort (20.14); path default no appliance */
#define L7_IDMAP_DEFAULT_SNAP_PATH "/var/db/layer7/identity-map.snap"
#define L7_IDMAP_SNAP_VERSION        1

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
	/* 20.22 — auditoria ADR-0027 §4.1 (lifetime do processo) */
	unsigned             audit_conflicts;     /* identity_ip_conflict */
	unsigned             audit_last_writers;  /* identity_ip_last_writer */
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

/*
 * Esvazia o mapa sem destruir o rwlock (NÃO usar em SIGHUP — ADR-0027 §4.2).
 * Reinício a frio sem snapshot = init + mapa vazio (ou load que falha).
 */
void layer7_idmap_clear(struct l7_id_map *m);

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
 * Lookup por IP (hot path).
 * 0 = user único em out_user; 1 = multi_user (ad_* → não-match);
 * -1 = não encontrado / erro.
 * Se groups != NULL, copia até max_groups (n_groups_out).
 */
int layer7_idmap_lookup_ip(struct l7_id_map *m, const struct l7_id_addr *ip,
    char *out_user, size_t out_user_sz, enum l7_id_source *out_src);

int layer7_idmap_lookup_ip_ex(struct l7_id_map *m, const struct l7_id_addr *ip,
    char *out_user, size_t out_user_sz,
    char groups[][L7_IDMAP_GROUP_MAX], unsigned max_groups,
    unsigned *n_groups_out, enum l7_id_source *out_src);

/* Parse "a.b.c.d" / IPv6 textual → l7_id_addr. 0 OK, -1 inválido. */
int layer7_idmap_addr_from_str(const char *s, struct l7_id_addr *out);

/*
 * Export IPs do user para buffer (enforce/PF futuro).
 * Devolve n copiados, 0 se user ausente, -1 erro.
 */
int layer7_idmap_export_user_ips(struct l7_id_map *m, const char *user,
    struct l7_id_addr *out, unsigned max_out);

/*
 * Substitui grupos do user (LDAP refresh). n_groups=0 limpa.
 * 0 OK, -1 user ausente / erro.
 */
int layer7_idmap_set_groups(struct l7_id_map *m, const char *user,
    const char *const *groups, unsigned n_groups);

/*
 * Lista até max users in_use (ordem de tabela). Devolve n copiados.
 */
unsigned layer7_idmap_list_users(struct l7_id_map *m,
    char users[][L7_IDMAP_USER_MAX], unsigned max);

/*
 * Dump JSON diagnóstico (sem secrets). Truncável.
 * Devolve bytes escritos (sem contar NUL) ou -1.
 */
int layer7_idmap_dump_json(struct l7_id_map *m, char *buf, size_t bufsz);

/* --- 20.14 persistência best-effort (ADR-0027 §4.2) --- */

/*
 * Grava snapshot atómico (tmp + rename). path NULL → L7_IDMAP_DEFAULT_SNAP_PATH.
 * 0 OK, -1 erro. Não inclui secrets.
 */
int layer7_idmap_save(struct l7_id_map *m, const char *path);

/*
 * Carrega snapshot para mapa já init. Entradas expires_at <= now
 * são ignoradas (nunca restauradas). path NULL → default.
 * Valida header, faz clear, depois restaura só entradas válidas.
 * Devolve n sessões carregadas, ou -1 se ficheiro ausente/inválido
 * (mapa fica clear se o header era válido e o clear já correu —
 * open fail = -1 sem alterar).
 */
int layer7_idmap_load(struct l7_id_map *m, const char *path, time_t now);

/* Helpers de endereço (testes / fontes). */
int layer7_idmap_addr_equal(const struct l7_id_addr *a,
    const struct l7_id_addr *b);
int layer7_idmap_addr_set_ipv4(struct l7_id_addr *a, uint32_t host_order);
int layer7_idmap_addr_set_ipv6(struct l7_id_addr *a, const uint8_t in6[16]);

/* --- 20.21 normalização (fontes → mesma chave no mapa) --- */

/*
 * Canonicaliza username de RADIUS/DC/LDAP para a chave do mapa:
 *  - DOMAIN\user  → user
 *  - user@domain  → user (UPN)
 *  - lowercase ASCII
 *  - rejeita contas máquina (*$) e strings vazias
 * Retorna 0 OK, -1 inválido. out deve ter ≥ L7_IDMAP_USER_MAX.
 */
int layer7_idmap_normalize_user(const char *in, char *out, size_t out_sz);

/*
 * Remove um IP da sessão do user (logoff/Stop por endereço).
 * Se o user ficar sem IPs, remove a sessão.
 * 0 OK, -1 user/IP ausente ou erro.
 */
int layer7_idmap_remove_ip(struct l7_id_map *m, const char *user,
    const struct l7_id_addr *ip);

/* 20.22 — contadores de auditoria (ADR-0027 §4.1) */
unsigned layer7_idmap_audit_conflicts(const struct l7_id_map *m);
unsigned layer7_idmap_audit_last_writers(const struct l7_id_map *m);
/* Sessões actualmente em estado multi_user */
unsigned layer7_idmap_count_multi_user(const struct l7_id_map *m);

#endif /* LAYER7_IDENTITY_MAP_H */
