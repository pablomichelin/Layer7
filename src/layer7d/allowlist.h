/*
 * Allowlist de destinos do Layer7.
 *
 * Bloco 3 da Fase 1: lista branca de destinos (dominios, IPs e CIDRs) que
 * NUNCA devem ser bloqueados pelo daemon. Honrada antes de qualquer
 * pfctl add em `layer7_block_dst` ou `layer7_bld_N`, e replicada como
 * `pass quick inet to <layer7_allow_dst>` no PF do pacote.
 *
 * Entradas vem de duas fontes combinadas (uniao logica):
 *   1. JSON do `layer7.json` (campo `layer7.dst_allowlist[]`).
 *   2. Ficheiro de seed embutido em `/usr/local/etc/layer7/allowlist-seed.txt`
 *      (1 entrada por linha; `#` e linhas vazias sao ignoradas).
 *
 * Tipos de entrada:
 *   - Dominio: ex.: `bb.com.br` — casa por sufixo (qualquer subdominio).
 *   - IPv4 host: ex.: `8.8.8.8`.
 *   - IPv4 CIDR: ex.: `200.201.0.0/16`.
 *
 * V1: IPv6 nao suportado nesta estrutura. Para destinos IPv6 estaticos, o
 * operador pode adicionar regras `pass quick` manuais no pf.conf.sample.
 */
#ifndef LAYER7_ALLOWLIST_H
#define LAYER7_ALLOWLIST_H

#include <stddef.h>
#include <stdint.h>

#define L7_AL_MAX 256
#define L7_AL_ENTRY_LEN 128

enum l7_al_kind {
	L7_AL_DOMAIN = 0,
	L7_AL_IPV4_HOST = 1,
	L7_AL_IPV4_CIDR = 2
};

struct l7_allowlist_entry {
	char value[L7_AL_ENTRY_LEN];
	enum l7_al_kind kind;
	uint32_t ip;     /* host order, valido para IPV4_HOST/CIDR */
	int prefix;      /* 0..32, valido para CIDR */
};

struct l7_allowlist {
	struct l7_allowlist_entry entries[L7_AL_MAX];
	int n;
};

/* Inicializa estrutura vazia. */
void l7_allowlist_reset(struct l7_allowlist *al);

/*
 * Adiciona uma entrada bruta (texto). Determina automaticamente o kind:
 *   - Dominio: comeca por letra/_, contem ponto, sem `/` nem digito como primeiro char.
 *   - IPv4 host: 4 octetos sem `/`.
 *   - IPv4 CIDR: `a.b.c.d/n`.
 * Devolve 0 se aceite, -1 se invalida ou se ja cheia.
 * Entradas duplicadas (por valor exacto) sao silenciosamente ignoradas.
 */
int l7_allowlist_add(struct l7_allowlist *al, const char *value);

/*
 * Carrega entradas do JSON layer7 — campo `dst_allowlist`. Heuristica
 * compativel com o restante parser. Retorna numero de entradas aceites.
 */
int l7_allowlist_parse_json(struct l7_allowlist *al, const char *json,
    size_t len);

/* Carrega entradas do ficheiro de seed (1 por linha). 0 = ok ou ficheiro
 * ausente; -1 so em erro grave. */
int l7_allowlist_load_seed_file(struct l7_allowlist *al, const char *path);

/* True se o dominio `host` (ex.: `app.bb.com.br`) bate, por sufixo, qualquer
 * entrada de dominio da allowlist. */
int l7_allowlist_contains_domain(const struct l7_allowlist *al,
    const char *host);

/* True se o IP (string `a.b.c.d`) cai em qualquer host/CIDR da allowlist. */
int l7_allowlist_contains_ip(const struct l7_allowlist *al,
    const char *ip_str);

/* Numero de entradas. */
int l7_allowlist_count(const struct l7_allowlist *al);

#endif
