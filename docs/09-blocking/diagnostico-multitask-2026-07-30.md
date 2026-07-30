# Diagnóstico multitask — candidato 1.8.11_31

**Data:** 2026-07-30  
**Coordenação:** revisão read-only A–D + consolidação + 3 blocos locais  
**Commit analisado:** `2ba9b5f` (`docs: record Layer7 1.8.11_31 build evidence`)  
**PORTREVISION:** `31` (inalterado nesta rodada)  
**Release pública:** `1.8.11_24` (`v1.8.11_24`)

---

## 1. Veredito actualizado

| Decisão | Estado |
|---------|--------|
| Publicar `_31` / incrementar revisão | **NO-GO** |
| Build FreeBSD `_31` | **GO** (evidência documentada G1) |
| Instalação passiva lab (Gate B1) | **NO-GO** — G2/G2.5 pendentes |
| Two-client scoped (G5) | **NO-GO** |
| Release sem G0–G7 | **Nunca GO** |

**Veredicto global:** **NO-GO** para enforce e publicação. Candidato `_31` **congelado** com ledger coerente, testes locais reforçados (R-21) e próximo passo único = **Gate B1 passivo** no appliance.

---

## 2. Factos confirmados

| Item | Valor |
|------|-------|
| Branch | `main` @ `2ba9b5f` |
| Describe | `v1.8.11_24-22-g2ba9b5f` |
| Diff `_24..HEAD` | 81 ficheiros, +5067 / −625, 22 commits |
| SHA256 `_31` (artefacto) | `dc5118dd01193a83a6c6d15cc3ae4ca300647294a5b188e1991a363b4c453e33` |
| SHA256 `_24` (release) | `1d5573f0a0c7803a87d8cb536ad9eee43e85daa9bf98bf7edc84ef554e2c7818` |
| `enforcement_model` default | `legacy_global` |
| `scoped_hybrid` | experimental, OFF |
| `tests/run-local.sh` | **PASS** (PHP SKIP macOS) |
| `.pkg` no clone | ausente — hash **N/C** localmente |

---

## 3. Contradições corrigidas

| ID | Antes | Depois |
|----|-------|--------|
| C1 | «`_31` resolve FP-001..020» | Corrigido: **12 FP** com fix código; **8 abertos** (FP-009..016); **0** validados fisicamente |
| C2 | README 09-blocking vs classificação | Registado — actualizar `document-classification.md` em bloco F0 |
| C3 | `_24` publicada vs «não publicada» em revisão-codigo | Histórico jun/2026 — não SSOT |
| C4 | Docs Etapa 1 untracked | Parcialmente resolvido nesta rodada (novos artefactos) |
| C5 | `activate.js` modificado | Fora scope — isolar antes de release |

---

## 4. Correções implementadas (esta rodada)

Três blocos locais — **sem** incremento de PORTREVISION.

### Bloco 1 — B-002: flush `layer7_exc_allow_*`

- **Sintoma:** reorder/delete de excepções allow podia deixar tabelas órfãs.
- **Evidência:** `layer7_exc_allow_N` em ruleset mas ausente de `layer7_flush_dynamic_tables()` e `flush-all`.
- **Correcção:** loop 0..15 em `layer7.inc` + `flush_tables_exception_allow` em `layer7-pfctl`.
- **Teste:** R-21 `test_flush_coverage.sh` PASS.
- **Risco:** baixo.
- **Rollback:** revert dos três ficheiros.
- **Gate:** G3 uninstall/reorder excepção.

### Bloco 2 — B-003: flush blacklist em `layer7_bl_apply()`

- **Sintoma:** delete/reindex blacklist sem flush → IPs stale em `layer7_bld_*`.
- **Evidência:** `layer7_bl_apply()` chamava `filter_configure` sem `layer7_flush_dynamic_tables()`.
- **Correcção:** flush antes de resync.
- **Teste:** R-21 PASS.
- **Risco:** breve gap de bloqueio durante flush.
- **Rollback:** revert `layer7.inc`.
- **Gate:** G5 blacklist delete lab.

### Bloco 3 — B-004: pkg-deinstall alinhado com `flush-all`

- **Sintoma:** Package Manager remove via hook POST com flush parcial (faltavam pallow, pdst, allow_dst, exc_allow).
- **Evidência:** GUI removal já usava `flush-all` em PRE-delete; hook não.
- **Correcção:** `flush-all` em PRE-DEINSTALL; fallback POST expandido.
- **Teste:** R-21 PASS.
- **Risco:** baixo.
- **Rollback:** revert `pkg-deinstall.in`.
- **Gate:** smoke uninstall Package Manager.

---

## 5. Itens não editados (causa-raiz não comprovada ou fora scope)

| ID | Motivo |
|----|--------|
| FP-015 | Parser JSON — refactor grande; **contrato** em `test_config_parse.c` #12–15 (fragilidade documentada) |
| FP-003, FP-016 | Exigem appliance (state kill, two-client) |
| FP-011 | ABI FB16 — install passivo pendente |
| B-001 | SSOT `layer7.json` vs config.xml — ADR, não auto-migration |
| B-006 | Watchdog daemon — risco médio, gate crash |
| B-008 | `write_rules()` vs monitor — SUSPEITA, gate smoke appliance |

---

## 6. Testes executados

```text
sh tests/run-local.sh → ALL LOCAL TESTS PASSED
```

| Resultado | Detalhe |
|-----------|---------|
| C unitários | 8 suites PASS |
| R-02 config_parse FP-015 | PASS (casos #12–15, continuação 2026-07-30) |
| R-21 flush coverage | PASS |
| R-08 pidfile | PASS |
| R-09..R-12 PHP | **SKIP** (sem PHP macOS) |
| R-17 two-client | **PENDENTE** appliance |
| smoke-layer7d | não executado macOS (bloqueio intencional) |

---

## 7. Dependências de appliance

- G2 install passivo `_31` + `ldd` FB16 (FP-011)
- G3 ruleset completo `pfctl -nf`
- G4 captura real + métricas `cap_*`
- G5 two-client + state kill + allow vs nativo
- G6 licença inválida flush observável
- F3 DR-05 cenários licença

---

## 8. Limitações arquitecturais (inalteradas)

- `legacy_global` default — per-client real só com `scoped_hybrid` + gate
- IPv4-only, sem MITM, ECH/DoH bypass documentado
- nDPI sem pin commit no repo (reprodutibilidade builder)
- BG-028 trust chain inactiva — SHA256 manual
- Config Layer7 fora de `config.xml` (B-001 by design)

---

## 9. Próximo gate recomendado

**Gate B1 passivo** (`plano-gates-producao.md` §G2):

1. Snapshot appliance + rollback `_24` documentado
2. `pkg add` `_31` com `enabled=false`, `mode=monitor`
3. `layer7d -V`, `service layer7d status`, `ldd`
4. Zero blocks PF; tabelas vazias
5. **Sem** activar enforce até G5 PASS

---

## 10. Risco e rollback global

| Risco | Mitigação |
|-------|-----------|
| Binário FB16 não validado | G2.5 antes de qualquer enforce |
| Correções flush locais não no `.pkg` publicado | Rebuild `_31` após commit aprovado |
| Working tree sujo | G0.3 antes de tag |

**Rollback:** reinstalar `1.8.11_24` + `layer7-pfctl flush-all` + `enabled=false`.

---

## 11. Artefactos desta rodada

| Ficheiro | Papel |
|----------|-------|
| `matriz-unificada-rev-fp-aud.md` | Ledger REV/FP/AUD/BG |
| `layer7-regression-matrix.md` | Actualizado R-21 |
| `diagnostico-multitask-2026-07-30.md` | Este relatório |
| `tests/unit/test_flush_coverage.sh` | Contract test lifecycle |

---

## 12. Decisão final

**NO-GO** para release, enforce e Gate B1 até execução física documentada. **GO somente para build** (já PASS no builder). Candidato `_31` permanece congelado em `_31` até humano autorizar rebuild pós-commit destes fixes locais.

---

## 13. Continuação 2026-07-30

| Acção | Estado |
|-------|--------|
| Governança: CORTEX, backlog BG-061, checklist, README 09-blocking | Actualizado |
| Testes FP-015 contrato (`test_config_parse.c` #12–15) | PASS |
| Commit / rebuild / appliance | **Pendente decisão humana** |
