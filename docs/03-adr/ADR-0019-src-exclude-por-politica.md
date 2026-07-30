# ADR-0019 — Exclusão de origem por política (`src_exclude_*`)

- **Estado:** Aceito; candidato `_50`
- **Data:** 2026-07-30
- **Fase:** Caminho B / F4.3 (Bloco D plano VIP/UX)
- **Backlog:** BG-066

## Contexto

O plano VIP/UX (Blocos B/C) introduziu isenção **global** via excepção
partilhada `vip-isentos` (ADR-0016). O caso fino «isento **só deste**
perfil» exige negar match da política para origens específicas sem criar
16 excepções por perfil nem duplicar o mecanismo global.

Campos novos no schema existente de políticas:

- `match.src_exclude_cidrs` — array de CIDRs/IPs excluídos desta política;
- `match.src_exclude_groups` — grupos cujos membros ficam isentos desta
  política (expandidos para hosts/CIDRs como `match.groups`).

## Decisão

### 1. Semântica no daemon (ambos os modelos)

Em `rule_matches` / `src_matches_rule`, se a origem do fluxo cair na
exclusão expandida, a política **não casa** — o motor continua para a
próxima política ou default.

Resolução de `src_exclude_groups` segue o padrão de
`layer7_policies_expand_groups` (~814 em `policy.c`).

### 2. `scoped_hybrid` — PF `layer7_pexc_N`

Para cada política `block` com exclusões e `needs_pdst`:

- tabela estática `layer7_pexc_N` com entradas expandidas (hosts/CIDRs);
- regra `match from <layer7_pexc_N> to <layer7_pdst_N> tag L7ALLOW`
  (mesmo padrão ADR-0016 / `pallow_N`).

Assim, destinos aprendidos em `pdst_N` não bloqueiam origens excluídas
desta política, sem `pass quick` nem bypass de regras nativas pfSense.

`pexc_0..23` entra em flush, self-heal, `layer7-pfctl flush-all` e
`pkg-deinstall` desde o primeiro commit (lição BG-061).

### 3. `legacy_global` — exclusão só no daemon

Não há tabela PF por política. A exclusão impede o daemon de adicionar o
destino a `layer7_block_dst` **por causa daquele cliente**.

**Trade-off documentado:** se outro cliente já colocou o mesmo destino em
`layer7_block_dst`, a origem excluída continua bloqueada no PF global —
comportamento honesto; operador deve usar `scoped_hybrid` ou excepção
global para isenção total.

### 4. GUI

Campo «Excluir origens (só este perfil)» na secção Avançado (modal +
formulário manual). Validação: origem simultaneamente incluída e excluída
→ erro de formulário.

## Consequências

- BG-066 fecha o gap «isento só deste perfil» sem novo subsistema.
- Campos aditivos ao JSON: versões antigas ignoram-nos (não-regressão).
- Ruleset cresce: até uma tabela + uma regra `match` por política com
  exclusão e `pdst`, dentro do limite de 24 políticas.

## Limitações

- Em `legacy_global`, isenção parcial não remove entrada PF global
  preexistente por outro cliente (ver §3).
- Exclusão por política não substitui excepção global VIP (`vip-isentos`).
- IPv6, ECH e gates appliance (G2–G7) permanecem inalterados.

## Teste

- C: parse `src_exclude_*`, expansão de grupos, decisão sem match quando
  origem excluída; origem não excluída continua sujeita.
- PHP: regras/tabelas `pexc`, ordem L7ALLOW vs block scoped, flush.
- Builder: lint C/PHP/shell, build pacote `_50`.
- Appliance: cenário validacao-lab §19.3 (two-client scoped).

## Rollback

Reinstalar `_49`; campos `src_exclude_*` ignorados pelo daemon antigo.
Executar `layer7-pfctl flush-all` após downgrade se `_50` esteve activo.
