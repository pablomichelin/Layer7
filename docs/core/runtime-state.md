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

**Evicção:** TTL por inatividade e limite máximo de entradas. O lookup examina
toda a janela de colisão antes de reutilizar um slot expirado; se a janela
estiver cheia, remove deterministicamente o fluxo menos recente para manter a
captura disponível. `cap_evicted` mede pressão/evicção e `cap_dropped` mede
falha de alocação.

### Índice de políticas

- Lista ordenada por `priority` decrescente.
- Para cada regra: estrutura de match pré-compilada (CIDRs, listas de app/category).

### Enforcement

- Handles para **PF tables** / nomes de alias gerados pelo pacote.
- `layer7_pdst_N`: destinos bloqueados por política escopada.
- `layer7_psrc_N`: origens em quarentena explícita.
- `layer7_pallow_N`: destinos aprendidos por política allow explícita; os
  pacotes recebem `L7ALLOW`, sem decisão `pass`.
- Cache do último sync com PF (evitar `pfctl` excessivo).

### Contadores (expor status / GUI)

- `cap_active`, `cap_classified`, `cap_expired`, `cap_evicted`,
  `cap_dropped`, `cap_pkts`, `captures`
- `blocks_applied`, `tags_applied`, `monitor_events`

## Concorrência

V1 assume **um processo**, possivelmente **uma thread** de captura + fila lock-free simples ou lock por bucket de fluxo. Detalhe de threading fica para implementação do Bloco 6.

## Sob reboot

Estado zerado; config relida do XML ao subir.
As tabelas dinâmicas `pdst`, `psrc` e `pallow` são recriadas vazias; o daemon
volta a populá-las somente após nova decisão válida e dentro do TTL.
