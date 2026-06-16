# Release Notes — `pfSense-pkg-layer7` `1.8.11_24` (RASCUNHO)

- **Data:** 2026-06-16
- **Estado:** rascunho técnico — **sem `.pkg` publicado**; `MANUAL-INSTALL.md`
  inalterado até gate e release
- **Tag GitHub prevista:** `v1.8.11_24` (após gate)
- **Tipo:** Caminho B E0–E3 + estabilização pós-revisão pré-instalação
- **Última release pública de referência:** `1.8.11_23`

---

## 1. Resumo

Consolida **enforcement escopado por política** (Caminho B blocos E0–E3):
modelo `enforcement_model`, decisão unificada no daemon, regras PF
`layer7_pdst_N` / `layer7_psrc_N` e runtime que deixa de usar
`layer7_block_dst` em `scoped_hybrid`. Inclui correcções críticas da revisão
de código de 2026-06-15 (flush em licença inválida, allowlist `/0`, resync PF,
`except_ips`, TTL em blacklists, etc.). Testes locais PASS; **gate two-client
no appliance permanece PENDENTE**.

---

## 2. Novidades (Caminho B E0–E3)

### E0 — Fundação

- Campo `layer7.enforcement_model`: `legacy_global` (default) |
  `scoped_hybrid` (**experimental**).
- Selector em **Definições**; parse em `config_parse.c`.

### E1 — Decisão unificada

- `layer7_decide_for_client()` para DNS e fluxos classificados.
- `scope_global` e `quarantine_origin` na decisão.
- Testes: `tests/functional/test_policy_decide.c`.

### E2 — PF escopado no pacote

- Regras `from {src} to <layer7_pdst_N>` e quarentena origem em
  `<layer7_psrc_N>`.
- Checkbox **scope_global** em Políticas.
- Testes: `tests/functional/test_scoped_pf_inc.php`.

### E3 — Runtime daemon

- População de tabelas escopadas conforme decisão (não destino global em
  scoped).
- Cache TTL por `(table, ip)`.
- Testes: `tests/functional/test_enforce_scoped.c`.

---

## 3. Correcções (pós-revisão)

| Item | REV / tema |
|------|------------|
| Flush PF quando licença inválida no recheck | REV-002 |
| Rejeição CIDR `0.0.0.0/0` na allowlist | REV-003 |
| `filter_configure()` ao mudar `enforcement_model` | REV-015 (parcial) |
| `layer7_pf_config_resync()` em saves de políticas/grupos/excepções/dispositivos | REV-016 (parcial) |
| Repovoar `layer7_allow_dst` no resync PF | allow_dst flush |
| DNS respeita `enabled=false` | DNS disabled |
| `quarantine_origin` end-to-end | quarentena app-only |
| Políticas block vazias exigem flags explícitas | scope_global warnings |
| `except_ips` aplicado em blacklists | REV-007 (parcial) |
| TTL em entradas `layer7_bld_N` | REV-004 (parcial) |

---

## 4. Limitações e gates

- **`legacy_global` permanece default** — bloqueio por destino partilhado
  (`layer7_block_dst`) é intencional até E8 (**REV-001 by design**).
- **`scoped_hybrid` é experimental** — não usar em produção sem validação lab.
- **Gate two-client PENDENTE:** roteiro sec. 12 em
  [`docs/04-package/validacao-lab.md`](../04-package/validacao-lab.md).
- E4–E8 (semântica match, testes lab alargados, release) continuam no backlog.

---

## 5. Teste mínimo (pré-release)

```sh
sh tests/run-local.sh
```

No appliance (obrigatório antes de publicar):

- [`validacao-lab.md`](../04-package/validacao-lab.md) secção **12** (two-client).

---

## 6. Rollback

Enquanto não publicado: manter `1.8.11_23` ou `PORTREVISION=23` no branch.

Após publicação: `pkg delete pfSense-pkg-layer7` + reinstalar `1.8.11_23`
via `install.sh` do release correspondente.

---

## 7. Compatibilidade

- pfSense CE: mesma linha validada em `1.8.11_18`–`_23`
- FreeBSD builder: `192.168.100.12` (ver `docs/08-lab/builder-freebsd.md`)

---

## Referências

- Changelog: [`docs/changelog/CHANGELOG.md`](../changelog/CHANGELOG.md) → `[1.8.11_24]`
- Revisão pré-instalação: [`docs/09-blocking/revisao-codigo-pre-install-2026-06-15.md`](../09-blocking/revisao-codigo-pre-install-2026-06-15.md)
- Plano Caminho B: [`docs/09-blocking/plano-enforcement-100-porcento.md`](../09-blocking/plano-enforcement-100-porcento.md)
- ADR-0014: [`docs/03-adr/ADR-0014-enforcement-escopado-por-politica.md`](../03-adr/ADR-0014-enforcement-escopado-por-politica.md)
