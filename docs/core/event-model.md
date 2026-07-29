# Modelo de evento (V1)

## Tipos

### 1. Classificação (`flow_classified`)

Emitido uma vez quando o nDPI chega a `NDPI_STATE_CLASSIFIED` ou quando o
orçamento de 48 pacotes se esgota e `ndpi_detection_giveup()` produz o
resultado final disponível. Estados `NDPI_STATE_PARTIAL` não emitem decisão.

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `v` | int | Versão schema (1) |
| `type` | string | `flow_classified` |
| `ts_ms` | int64 | Epoch ms |
| `flow_id` | string | ID interno do daemon (hash estável do 5-tuple na sessão) |
| `confidence` | string | `detected` \| `guessed` \| `unknown` |
| `master_proto` | string | nDPI master |
| `app_proto` | string | nDPI app |
| `category` | string | nDPI category name |
| `l4` | string | `tcp` \| `udp` |
| `src_ip` / `dst_ip` | string | Texto IPv4/IPv6 |
| `src_port` / `dst_port` | int | |
| `ingress_iface` | string | Nome iface pfSense |
| `policy_id` | string \| null | Regra que venceu (se já aplicável) |
| `action` | string \| null | Decisão: `allow` \| `block` \| `monitor` \| `tag` |

### 2. Operacionais

| `type` | Quando |
|--------|--------|
| `daemon_start` / `daemon_stop` | Ciclo de vida |
| `config_reload` | Após leitura bem-sucedida |
| `config_reload_error` | Parse/validação falhou |
| `policy_match` | Primeira ação aplicada a um fluxo (enforce) |
| `enforce_block` | Bloqueio efetivo (PF) |
| `enforce_tag` | Entrada em table |

### 3. Diagnóstico (opcional V1, `log_level=debug`)

| `type` | Uso |
|--------|-----|
| `classifier_inconclusive` | Poucos pacotes / cifrado |
| `policy_conflict` | Duas regras mesmo peso (não deveria após precedence) |

## Transporte implementado

- **Operacional:** `/var/log/layer7d.log`, texto timestampado.
- **Eventos:** `/var/log/layer7-events.log`, pares `key=value`.
- **Histórico:** SQLite derivado em
  `/usr/local/etc/layer7/reports/reports.db`.
- **Syslog:** mensagens operacionais e auditoria local; duplicação UDP RFC
  3164 quando `syslog_remote=true`.

`flow_decide`/blacklist representa a decisão e alimenta o KPI de bloqueio.
`enforce_block` representa a aplicação no PF com acção interna `enforced`, de
modo a não contar a mesma decisão duas vezes.

## Compatibilidade com PoC

O PoC `layer7_ndpi_poc` é subconjunto; o daemon V1 adiciona `type`, `flow_id`, iface, policy e action.
