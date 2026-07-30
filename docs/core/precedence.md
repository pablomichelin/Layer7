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

No enforcement PF, a precedência allow é materializada com a tag interna
`L7ALLOW`: somente os blocks administrados pelo Layer7 ignoram pacotes
marcados. A precedência do policy engine nunca substitui a política de
segurança nativa do pfSense e não gera `pass quick`.

## Conflito

Dois matches simultâneos não ocorrem com first-match. Se validação detectar regras idênticas de prioridade e overlap total, emitir aviso na GUI (backlog) ou `policy_conflict` em debug.

## Exclusão por política (`src_exclude_*`, BG-066 / ADR-0019)

Campos `match.src_exclude_cidrs` e `match.src_exclude_groups` fazem com que a
origem **não case** na política (first-match continua para políticas seguintes).
Isto é distinto da excepção global `vip-isentos`: a isenção é **só desta**
política. A GUI rejeita origem simultaneamente incluída e excluída.

Em `scoped_hybrid`, o PF usa `layer7_pexc_N` + `match from pexc to pdst tag
L7ALLOW`. Em `legacy_global`, a exclusão actua só no daemon (trade-off: destino
já em `layer7_block_dst` por outro cliente permanece bloqueado).
