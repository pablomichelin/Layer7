# Modelo de evento (V1)

## Tipos

### 1. Classificação (`flow_classified`)

Emitido quando um fluxo é classificado com confiança suficiente (alinhado ao PoC, com campos extras).

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

## Transporte

- **Local:** arquivo rotativo ou memória para GUI.
- **Syslog:** linha JSON (facilita SIEM) quando `syslog_remote=true`.

## Compatibilidade com PoC

O PoC `layer7_ndpi_poc` é subconjunto; o daemon V1 adiciona `type`, `flow_id`, iface, policy e action.
