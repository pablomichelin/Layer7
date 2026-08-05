/*
 * blacklist.h — Blacklists externas (UT1 Universite Toulouse).
 *
 * Hash table de dominios organizada por categoria, com suffix matching
 * e whitelist interna. Subsistema paralelo ao policy engine V1.
 */
#ifndef LAYER7_BLACKLIST_H
#define LAYER7_BLACKLIST_H

#include <stddef.h>

#define L7_BL_DIR_DEFAULT	"/usr/local/etc/layer7/blacklists"
#define L7_BL_HASH_BITS		20
#define L7_BL_HASH_SIZE		(1 << L7_BL_HASH_BITS)
#define L7_BL_MAX_CATS		64
#define L7_BL_CAT_LEN		48
#define L7_BL_DOMAIN_MAX	256
#define L7_BL_WL_MAX		256

/* BG-100: hard-cap absoluto (UT1 adult ~4.6M cabe com margem). */
#define L7_BL_MAX_TOTAL_HARD		(5 * 1000 * 1000)
#define L7_BL_MAX_TOTAL_DEFAULT		L7_BL_MAX_TOTAL_HARD
#define L7_BL_MAX_TOTAL			L7_BL_MAX_TOTAL_HARD /* legado */

#define L7_BL_MEM_PERCENT_DEFAULT	25
#define L7_BL_MEM_PERCENT_MIN		5
#define L7_BL_MEM_PERCENT_MAX		50
#define L7_BL_MEM_BUDGET_MIN_MB		128
#define L7_BL_MEM_BUDGET_MAX_MB		1536

struct l7_blacklist;

/* Limites de load (NULL ⇒ defaults). max_entries=0 ⇒ DEFAULT. */
struct l7_bl_limits {
	int max_entries; /* 1..HARD */
	int mem_percent; /* 5..50 (% de hw.physmem) */
};

/*
 * Carrega dominios das categorias listadas em cats[].
 * Le ficheiros $dir/$cat/domains para cada categoria.
 * whitelist[]/n_whitelist: dominios que NUNCA sao bloqueados.
 * limits: teto de entradas + orcamento RAM (%); NULL = defaults.
 * Para no primeiro limite (contagem ou bytes). Retorna NULL em erro.
 */
struct l7_blacklist *l7_blacklist_load(const char *dir,
    const char **cats, int n_cats,
    const char **whitelist, int n_whitelist,
    const struct l7_bl_limits *limits);

/*
 * Verifica se um dominio esta na blacklist (com suffix matching).
 * Whitelist verificada ANTES do lookup na hash table.
 * Retorna nome da categoria se bloqueado, ou NULL se permitido.
 */
const char *l7_blacklist_lookup(const struct l7_blacklist *bl,
    const char *domain);

/*
 * Verifica se dominio pertence a uma das categorias fornecidas.
 * matched_cat, quando nao-NULL, recebe a categoria exacta encontrada.
 */
int l7_blacklist_lookup_categories(const struct l7_blacklist *bl,
    const char *domain, const char **cats, int n_cats,
    const char **matched_cat);

/* Liberta toda a memoria da blacklist. */
void l7_blacklist_free(struct l7_blacklist *bl);

/* Estatisticas. */
int l7_blacklist_count(const struct l7_blacklist *bl);
int l7_blacklist_cat_count(const struct l7_blacklist *bl);
int l7_blacklist_was_truncated(const struct l7_blacklist *bl);
size_t l7_blacklist_bytes_used(const struct l7_blacklist *bl);
size_t l7_blacklist_bytes_budget(const struct l7_blacklist *bl);

/* Contadores de hits por categoria: usa índice directo. */
const char *l7_blacklist_get_cat_name(const struct l7_blacklist *bl, int idx);
unsigned long long l7_blacklist_get_cat_hit_count(const struct l7_blacklist *bl,
    int idx);

/* Helpers de clamp (testaveis / GUI). */
int l7_bl_clamp_max_entries(int v);
int l7_bl_clamp_mem_percent(int v);
size_t l7_bl_compute_mem_budget(int mem_percent);

#endif /* LAYER7_BLACKLIST_H */
