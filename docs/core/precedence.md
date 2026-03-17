# Precedência de políticas (V1)

## Regra principal

1. Apenas políticas com `enabled=true`.
2. Ordenar por **`priority` numérico decrescente** (maior prioridade primeiro).
3. Empate: ordem estável pelo **`id` lexicográfico** (determinístico).

## Primeira vitória

O primeiro match **ganha** (first match). Não há merge de ações entre regras.

## Interação com default implícito

Se nenhuma regra casar:

- **Modo monitor:** tratar como `monitor` implícito (evento opcional `no_policy_match`).
- **Modo enforce:** default **`allow`** (não bloquear o que não foi classificado/regrado) — **explícito na GUI** como política padrão.

> Alternativa futura: default `block` em zona restrita; fora de escopo V1 sem UX clara.

## Exceções (`exceptions[]`)

Avaliadas **antes** da matriz principal (maior precedência), com ordem interna por `priority` ou ordem de lista.

## Conflito

Dois matches simultâneos não ocorrem com first-match. Se validação detectar regras idênticas de prioridade e overlap total, emitir aviso na GUI (backlog) ou `policy_conflict` em debug.
