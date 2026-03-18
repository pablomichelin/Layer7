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
| `syslog_remote` | bool | `false` | Duplicar logs do daemon por UDP para servidor syslog |
| `syslog_remote_host` | string | `""` | Hostname ou IPv4 do coletor (obrigatório se `syslog_remote`) |
| `syslog_remote_port` | int | `514` | Porta UDP |
| `debug_minutes` | int | `0` | `0` = normal; `1–720` = forçar LOG_DEBUG no daemon durante N min após cada **reload** (SIGHUP) |
| `interfaces` | lista iface | `[]` | Interfaces alvo (nomes pfSense: `lan`, `opt1`…). **GUI:** **Settings** (CSV, até 8). Consumo pelo nDPI = backlog técnico. |

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

Avaliadas **antes** das políticas (ver [precedence](precedence.md)). Cada item:

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | string | Opcional (útil em logs / dry-run) |
| `enabled` | bool | Default `true` |
| `priority` | int | Maior = primeiro (empate por `id`) |
| `action` | enum | Tipicamente `allow` (bypass às políticas) |
| `host` | IPv4 | Match exacto do **src** do fluxo (ex.: `10.0.0.99`) |
| `cidr` | string | `a.b.c.d/nn` (IPv4). **Um** de `host` ou `cidr` por regra. |

O `layer7d -t` lista exceções e inclui dry-run com `src` + app + categoria.

## Persistência e reload

1. Admin altera na GUI → valida → grava XML.
2. Sinal ao daemon (`SIGHUP` ou socket) → `layer7d` relê config e reconstrói tabelas de política **sem** derrubar fluxos já classificados além do necessário.

## Amostra JSON lógica

Ver [`../../samples/config/layer7-minimal.json`](../../samples/config/layer7-minimal.json) (espelho do que o XML expressará).
