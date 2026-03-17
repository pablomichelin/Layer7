# Modelo de configuração (V1)

## Objetivo

Descrever o que a **GUI** persiste e o **`layer7d`** lê após reload. No pfSense CE, o pacote grava nós dentro de `config.xml` (namespace do pacote).

## Nó raiz sugerido

`layer7` (filho de `<package>` ou equivalente no XML do pacote pfSense).

## Campos globais

| Campo | Tipo | Default V1 | Descrição |
|-------|------|------------|-----------|
| `enabled` | bool | `false` | Serviço ativo |
| `mode` | enum | `monitor` | `monitor` \| `enforce` |
| `log_level` | enum | `info` | `error` \| `warn` \| `info` \| `debug` |
| `syslog_remote` | bool | `false` | Espelhar eventos relevantes para syslog |
| `interfaces` | lista iface | `[]` | Interfaces onde o daemon observa (nomes pfSense: `lan`, `opt1`…) |

## Políticas (`policies[]`)

Cada item:

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `id` | string UUID | sim | Identificador estável |
| `name` | string | sim | Rótulo humano |
| `enabled` | bool | sim | |
| `action` | enum | sim | `allow` \| `block` \| `monitor` \| `tag` |
| `priority` | int | sim | Maior = avaliado primeiro (ver [precedence](precedence.md)) |
| `match` | objeto | sim | Critérios (ver [policy-matrix](policy-matrix.md)) |
| `tag_table` | string | se `action=tag` | Nome lógico da PF table / alias a popular |

## Exceções (`exceptions[]`) — opcional V1

Lista de `cidr` ou `host` + `policy_id` a isentar ou forçar.

## Persistência e reload

1. Admin altera na GUI → valida → grava XML.
2. Sinal ao daemon (`SIGHUP` ou socket) → `layer7d` relê config e reconstrói tabelas de política **sem** derrubar fluxos já classificados além do necessário.

## Amostra JSON lógica

Ver [`../../samples/config/layer7-minimal.json`](../../samples/config/layer7-minimal.json) (espelho do que o XML expressará).
