/*
 * blacklist.h — Blacklists externas (UT1 Universite Toulouse).
 *
 * Hash table de dominios organizada por categoria, com suffix matching
 * e whitelist interna. Subsistema paralelo ao policy engine V1.
 */
#ifndef LAYER7_BLACKLIST_H
#define LAYER7_BLACKLIST_H

#define L7_BL_DIR_DEFAULT	"/usr/local/etc/layer7/blacklists"
#define L7_BL_HASH_BITS		20
#define L7_BL_HASH_SIZE		(1 << L7_BL_HASH_BITS)
#define L7_BL_MAX_CATS		64
#define L7_BL_CAT_LEN		48
#define L7_BL_DOMAIN_MAX	256
#define L7_BL_WL_MAX		256
#define L7_BL_MAX_TOTAL		(8 * 1024 * 1024)

struct l7_blacklist;

/*
 * Carrega dominios das categorias listadas em cats[].
 * Le ficheiros $dir/$cat/domains para cada categoria.
 * whitelist[]/n_whitelist: dominios que NUNCA sao bloqueados.
 * Retorna ponteiro para a blacklist, ou NULL em caso de erro.
 */
struct l7_blacklist *l7_blacklist_load(const char *dir,
    const char **cats, int n_cats,
    const char **whitelist, int n_whitelist);

/*
 * Verifica se um dominio esta na blacklist (com suffix matching).
 * Whitelist verificada ANTES do lookup na hash table.
 * Retorna nome da categoria se bloqueado, ou NULL se permitido.
 */
const char *l7_blacklist_lookup(const struct l7_blacklist *bl,
    const char *domain);

/* Liberta toda a memoria da blacklist. */
void l7_blacklist_free(struct l7_blacklist *bl);

/* Estatisticas. */
int l7_blacklist_count(const struct l7_blacklist *bl);
int l7_blacklist_cat_count(const struct l7_blacklist *bl);

/* Contadores de hits por categoria: usa índice directo. */
const char *l7_blacklist_get_cat_name(const struct l7_blacklist *bl, int idx);
unsigned long long l7_blacklist_get_cat_hit_count(const struct l7_blacklist *bl,
    int idx);

#endif /* LAYER7_BLACKLIST_H */
