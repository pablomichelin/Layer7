# Plano — gates de produção Layer7

**Data:** 2026-08-05 (rev. GV7.4)  
**Versão alvo / produção enforce:** `1.9.8`  
**Canal público `latest`:** `1.9.8` (alinhado)  
**Veredicto actual:** **GO** — produção enforce documental (Onda F `1.9.0` + **GV7.4** `1.9.8`)  
**Estado:** G2–G7 **PASS** no candidato `_69`/série `1.9.x`; CE **LIMITAÇÃO** (ADR-0022 aceite)

> **Nota:** `_31` foi superseded por `_32`…`_69`. O histórico de fixes por
> versão mantém-se no `CHANGELOG` e no `CORTEX`; os gates físicos executam-se
> **uma vez** no candidato actual (`_69`), não por cada PORTREVISION intermédio.

---

## 1. Princípios

1. Nenhum gate físico substituído por testes macOS/builder isolados.
2. Ordem: **passivo → monitor → enforce scoped → enforce legacy** (se algum dia).
3. Rollback imediato documentado: `1.8.11_69`; rollback histórico enforce: `_24`.
4. `scoped_hybrid` permanece experimental até E8 + two-client PASS.
5. **GO Onda F (`2026-08-05`):** produção enforce = **`1.9.0`** (promoção semver; gates validados em `1.8.11_69`).
6. **GV7.4 (`2026-08-05`):** produção enforce = **`1.9.8`** (trilha IPv6 completa; alinhada com `latest`).
7. CE físico no build `_69` pendente — ADR-0022 aceite como ressalva.

### Confirmação passo 1.1 (P1 — 2026-08-04, confirmado GO)

| Campo | Valor verificado |
|-------|------------------|
| Versão candidata / GO | `1.9.0` (`PORTVERSION=1.9.0`, `PORTREVISION=0`) |
| SHA256 artefacto | `cde469a105db0b9f07dee1bf65838494ce209a1e86912d2169b0f124d631569f` |
| GitHub `releases/latest` | `v1.9.0` |
| Evidência GO | `docs/tests/evidence/20260805T010100Z-ondaF-go-enforce/` |
| Veredicto | **GO ENFORCE** — produção = **`1.9.0`**; CE LIMITAÇÃO aceite |

---

## 2. Gates obrigatórios (sequência)

### G0 — Higiene repositório (local)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G0.1 | `tests/run-local.sh` PASS | macOS / CI | **PASS** |
| G0.2 | Lint shell pacote | `sh -n` | **PASS** |
| G0.3 | Working tree limpo para release | `git status` | Revalidar antes de cada bloco |

### G1 — Build FreeBSD (builder 192.168.100.12)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G1.1 | Build nDPI + pacote `_69` | Makefile port | **PASS** |
| G1.2 | SHA256 artefacto | `sha256` `.pkg` | `b6d11ccdbb0b59209a501ee4240706e873153c2780c283721d904158f6b06764` |
| G1.3 | Smoke `layer7d -t` | builder | **PASS** |
| G1.4 | PHP lint no builder | `php -l` | **PASS** |

### G2 — Instalação passiva (appliance)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G2.1 | Instalar `_69` sem activar | `pkg add` + `enabled=false` | **PASS** |
| G2.2 | Daemon arranca, PID legível | `service layer7d status` | **PASS** |
| G2.3 | Zero regras block Layer7 | `pfctl -sr` grep layer7 | **PASS** |
| G2.4 | Tabelas dinâmicas vazias | `layer7-pfctl` / pfctl -T show | **PASS** |
| G2.5 | Binário executável no OS do appliance | `layer7d -V`, `ldd` | **PASS** |

### G3 — Parser PF completo

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G3.1 | Ruleset completo `pfctl -nf` | Com anti-QUIC ON | **PASS** |
| G3.2 | Ordem `on <if> inet` | FP-018 fix `_29+` | **PASS** |
| G3.3 | Regras `pallow` + `L7ALLOW` + `exc_allow` | FP-017, BG-075 | **PASS** |

### G4 — Monitor activo (captura)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G4.1 | `captures > 0` interfaces reais | stats JSON | **PASS** |
| G4.2 | Ida/volta mesmo fluxo classificado | `cap_*` metrics | **PASS** |
| G4.3 | Sem bloqueio PF | tráfego bancos OK | **PASS** |
| G4.4 | Logs contenção L1 | rotação / SQLite | **PASS** |
| G4.5 | Pressão flow table | `cap_evicted/dropped` | **PASS** |

### G5 — Two-client scoped (validacao-lab §12)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G5.1 | `scoped_hybrid` ON + enforce | duas estações | **PASS** (`_66` pfnearly) |
| G5.2 | Cliente A block, B allow mesmo destino | curl/browser | **PASS** |
| G5.3 | App policy não quarentena total A | FP-002 | **PASS** |
| G5.4 | Quarentena explícita só A | `quarantine_origin` | **PASS** |
| G5.5 | State kill sessão existente | FP-003 | **PASS** |
| G5.6 | Allow vence blacklist sem bypass nativo | FP-017 | **PASS** |
| G5.7 | Smoke `smoke-enforcement-scoped.sh` | lab script | **PASS** |

### G6 — Licenciamento fail-safe

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G6.1 | Licença inválida → flush PF | restart sem `.lic` / DR-05 | **PASS** |
| G6.2 | Grace 14d offline | daemon + Onda C S08/S12 | **PASS** |
| G6.3 | Stop serviço → tabelas vazias | rc.d | **PASS** |

### G7 — Release pública GO (humano)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G7.1 | G0–G6 PASS documentados | relatório run_id | **PASS** |
| G7.2 | CHANGELOG + MANUAL-INSTALL | docs | **PASS** (`1.8.11_69`) |
| G7.3 | Trust chain F1.2 pacote (BG-028) | manifesto assinado | **Não activo** (Onda I) |
| G7.4 | Tag GitHub GO | release | **PASS** — `v1.8.11_69` |
| G7.5 | Onda F GO humano | evidência | **PASS** (`20260805T010100Z-ondaF-go-enforce`) |

---

## 3. O que o candidato `_69` inclui (resumo)

| Área | Versão mínima no código | Nota |
|------|-------------------------|------|
| PID / interfaces / scoped | `_25`+ | Ciclo de vida |
| Logs L1 | `_26`+ | BG-054 |
| Enforcement funcional | `_27`+ | BG-055 |
| Allow PF (`L7ALLOW`) | `_28`+ | BG-056 |
| Anti-QUIC syntax | `_29`+ | BG-057 |
| Flow table | `_30`+ | BG-058 |
| nDPI finalização | `_31`+ | BG-059 |
| VIP PF live | `_64`+ | BG-075 |
| GUI i18n | `_65`+ | BG-076 |
| pfnearly G5 | `_66`+ | BG-077 base |
| Check-in online | `_68`+ | BG-077 |
| SIGHUP blacklists | `_69` | F4.2 |

---

## 4. Rollback por gate

| Falha em | Acção |
|----------|-------|
| Pós-GO imediato | Reinstall `_68` |
| G2 | `pkg delete` + reinstall `_68` ou `_24` histórico |
| G3 | Desactivar anti-QUIC; flush; escalar FP-018 se persistir |
| G5 | Reverter `scoped_hybrid`; flush-all |
| G6 | Restaurar `.lic`; `enforce_ge_downgrade` manual via stop/start |
| Qualquer | `layer7-pfctl flush-all` + `filter_configure` |

---

## Referências

- [`docs/02-roadmap/plano-fecho-producao-e-consolidacao.md`](../02-roadmap/plano-fecho-producao-e-consolidacao.md)
- [`docs/02-roadmap/checklist-mestre.md`](../02-roadmap/checklist-mestre.md)
- [`docs/04-package/validacao-lab.md`](../04-package/validacao-lab.md) §10a, §10b, §11, §12, §19–20
- [`docs/09-blocking/revisao-funcional-pre-producao-2026-07-29.md`](revisao-funcional-pre-producao-2026-07-29.md)
- BG-052, BG-060, FP-001..FP-020
