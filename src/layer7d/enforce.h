/*
 * Enforcement PF (V1): nomes de tabela, formatação e execução de pfctl.
 * O daemon chama layer7_pf_enforce_decision após layer7_flow_decide (nDPI).
 */
#ifndef LAYER7_ENFORCE_H
#define LAYER7_ENFORCE_H

#include <stddef.h>

struct layer7_decision;

/* Tabela PF para block (admin cria em ruleset antes de enforce real) */
#define L7_PF_TABLE_BLOCK "layer7_block"
#define L7_PF_TABLE_BLOCK_DST "layer7_block_dst"
#define L7_PF_TABLE_TAG_DEFAULT "layer7_tagged"

/* 1 se nome só [A-Za-z0-9_] */
int layer7_pf_table_name_ok(const char *name);

/* IPv4 dotted quad simples */
int layer7_pf_ipv4_host_ok(const char *ip);

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
 * Se dec->would_enforce_block_or_tag e IP válido: pfctl -T add.
 * Retorno: 0 = sem add (monitor/allow ou IP inválido); 1 = add OK; -1 = pfctl falhou.
 */
int layer7_pf_enforce_decision(const struct layer7_decision *dec,
    const char *src_ipv4, int dry_run);

#endif
