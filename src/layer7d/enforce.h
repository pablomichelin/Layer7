/*
 * Enforcement PF (V1): nomes de tabela, formatação e execução de pfctl.
 * O daemon chama layer7_pf_enforce_decision após layer7_flow_decide (nDPI).
 */
#ifndef LAYER7_ENFORCE_H
#define LAYER7_ENFORCE_H

#include <stddef.h>

struct layer7_decision;

enum layer7_enforce_kind;

/* Tabela PF para block (admin cria em ruleset antes de enforce real) */
#define L7_PF_TABLE_BLOCK "layer7_block"
#define L7_PF_TABLE_BLOCK_DST "layer7_block_dst"
#define L7_PF_TABLE_TAG_DEFAULT "layer7_tagged"
/* Allowlist de destinos (Bloco 3 / Fase 1) — usada pelo `pass quick`
 * inserido pelo pacote antes dos `block drop`. */
#define L7_PF_TABLE_ALLOW_DST "layer7_allow_dst"

/* 1 se nome só [A-Za-z0-9_] */
int layer7_pf_table_name_ok(const char *name);

/* IPv4 dotted quad simples (legado) */
int layer7_pf_ipv4_host_ok(const char *ip);

/* IPv4 ou IPv6 textual (sem zona); passo 12.7 */
int layer7_pf_host_ok(const char *ip);

/*
 * Host permitido em tabelas PF / kill states (S-03):
 * rejeita ::1, fe80::/10 e ff00::/8.
 */
int layer7_pf_host_enforce_ok(const char *ip);

/*
 * Escreve em buf: pfctl -t <table> -T add <ip>
 * Retorna bytes escritos ou -1 se inválido.
 */
int layer7_pf_snprint_add(char *buf, size_t buflen, const char *table,
    const char *ip);

/*
 * Executa /sbin/pfctl -t <table> -T add|delete <ip> (fork + waitpid).
 * Requer root. Retorno 0 = sucesso, -1 = validação, fork ou exit != 0.
 * pfSense CE: caminho fixo /sbin/pfctl.
 */
int layer7_pf_exec_table_add(const char *table, const char *ip);
int layer7_pf_exec_table_delete(const char *table, const char *ip);

/*
 * Invalida estados PF depois de inserir um bloqueio reactivo. Sem isto uma
 * conexão já estabelecida continua a passar pela state table.
 */
int layer7_pf_exec_kill_state_pair(const char *src_ip, const char *dst_ip);
int layer7_pf_exec_kill_states_host(const char *ip);
int layer7_pf_exec_kill_states_to(const char *dst_ip);

/* Caminho B / E3 — nome da tabela PF escopada por politica. */
int layer7_pf_policy_table_name(enum layer7_enforce_kind kind, int idx,
    char *buf, size_t buflen);

/* BG-056: destino permitido explicitamente por uma politica. */
int layer7_pf_policy_allow_table_name(int idx, char *buf, size_t buflen);

const char *layer7_enforce_kind_str(enum layer7_enforce_kind kind);

/*
 * Resolve tabela e IP para block em runtime (scoped_hybrid ou legacy_global).
 * Retorno: 1 = deve fazer add; 0 = sem add; -1 = erro de validacao.
 * out_ip aponta para src_ip, dst_ip ou dec->enforce_dst_ip (nao copiar).
 */
int layer7_pf_resolve_block_target(const struct layer7_decision *dec,
    const char *src_ip, const char *dst_ip, int scoped_hybrid,
    char *out_table, size_t tbl_len, const char **out_ip);

/*
 * Se dec exige block/tag e IP valido: pfctl -T add.
 * scoped_hybrid: block usa layer7_pdst_N / layer7_psrc_N; tag inalterado.
 * Retorno: 0 = sem add; 1 = add OK; -1 = pfctl falhou.
 */
int layer7_pf_enforce_decision(const struct layer7_decision *dec,
    const char *src_ip, const char *dst_ip, int scoped_hybrid,
    int dry_run);

#endif
