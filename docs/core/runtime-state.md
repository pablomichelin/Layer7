# Estado runtime (`layer7d`)

## Objetivo

O que existe **só em RAM** entre reloads (não persistido).

## Estruturas principais

### Módulo de detecção

- Ponteiro para `ndpi_detection_module` (nDPI).
- Bitmask de protocolos (default: todos, futuro: perfis).

### Tabela de fluxos ativos

- Chave: 5-tuple normalizado + iface (ou cookie do datapath).
- Valor:
  - `struct ndpi_flow_struct *` (ou handle opaco)
  - `last_seen_ms`
  - `classification` em cache
  - `policy_decision` (se já resolvido)
  - `packets_count`

**Evicção:** TTL por inatividade (ex.: 5 min TCP idle, 60 s UDP) + limite máximo de entradas (proteção memória).

### Índice de políticas

- Lista ordenada por `priority` decrescente.
- Para cada regra: estrutura de match pré-compilada (CIDRs, listas de app/category).

### Enforcement

- Handles para **PF tables** / nomes de alias gerados pelo pacote.
- Cache do último sync com PF (evitar `pfctl` excessivo).

### Contadores (expor status / GUI)

- `flows_active`, `flows_classified`, `packets_processed`
- `blocks_applied`, `tags_applied`, `monitor_events`

## Concorrência

V1 assume **um processo**, possivelmente **uma thread** de captura + fila lock-free simples ou lock por bucket de fluxo. Detalhe de threading fica para implementação do Bloco 6.

## Sob reboot

Estado zerado; config relida do XML ao subir.
