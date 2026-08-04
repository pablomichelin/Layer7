# Plano — gates de produção Layer7

**Data:** 2026-08-04 (rev. alinhamento candidato lab)  
**Versão alvo candidata (gates):** `1.8.11_65`  
**Canal público `latest`:** `1.8.11_65`  
**Produção enforce (referência):** `1.8.11_24`  
**Veredicto actual:** **NO-GO** para activação enforce / promoção GO  
**Estado:** CANDIDATO LAB EM VALIDAÇÃO (Gate B1 pendente no appliance)

> **Nota:** `_31` foi superseded por `_32`…`_65`. O histórico de fixes por
> versão mantém-se no `CHANGELOG` e no `CORTEX`; os gates físicos executam-se
> **uma vez** no candidato actual (`_65`), não por cada PORTREVISION intermédio.

---

## 1. Princípios

1. Nenhum gate físico substituído por testes macOS/builder isolados.
2. Ordem: **passivo → monitor → enforce scoped → enforce legacy** (se algum dia).
3. Rollback sempre para `_24` passivo documentado.
4. `scoped_hybrid` permanece experimental até E8 + two-client PASS.
5. Produção enforce intocada até veredicto humano explícito (Onda F do plano mestre).
6. Candidato lab fixado: `1.8.11_65` (`SHA256=e7c8ca44f34e19da3a2958eacfd09fce5c77c77d5acd6d8633e9ca9d42cdd48e`).

---

## 2. Gates obrigatórios (sequência)

### G0 — Higiene repositório (local)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G0.1 | `tests/run-local.sh` PASS | macOS / CI | **PASS** (revalidar no passo 1.0) |
| G0.2 | Lint shell pacote | `sh -n` | **PASS** |
| G0.3 | Working tree limpo para release | `git status` | Revalidar antes de cada bloco |

### G1 — Build FreeBSD (builder 192.168.100.12)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G1.1 | Build nDPI + pacote `_65` | Makefile port | **PASS** (publicado `2026-08-04`) |
| G1.2 | SHA256 artefacto | `sha256` `.pkg` | `e7c8ca44f34e19da3a2958eacfd09fce5c77c77d5acd6d8633e9ca9d42cdd48e` |
| G1.3 | Smoke `layer7d -t` | builder | **PASS** (documentado CORTEX) |
| G1.4 | PHP lint no builder | `php -l` | **PASS** (documentado) |

### G2 — Instalação passiva (appliance)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G2.1 | Instalar `_65` sem activar | `pkg add` + `enabled=false` | **PENDENTE** |
| G2.2 | Daemon arranca, PID legível | `service layer7d status` | **PENDENTE** |
| G2.3 | Zero regras block Layer7 | `pfctl -sr` grep layer7 | **PENDENTE** |
| G2.4 | Tabelas dinâmicas vazias | `layer7-pfctl` / pfctl -T show | **PENDENTE** |
| G2.5 | Binário executável no OS do appliance | `layer7d -V`, `ldd` | **PENDENTE** (FP-011; lab FB16) |

### G3 — Parser PF completo

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G3.1 | Ruleset completo `pfctl -nf` | Com anti-QUIC ON | **PENDENTE** |
| G3.2 | Ordem `on <if> inet` | FP-018 fix `_29+` | Sintético PASS; completo **PENDENTE** |
| G3.3 | Regras `pallow` + `L7ALLOW` + `exc_allow` | FP-017, BG-075 | **PENDENTE** appliance |

### G4 — Monitor activo (captura)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G4.1 | `captures > 0` interfaces reais | stats JSON | **PENDENTE** |
| G4.2 | Ida/volta mesmo fluxo classificado | `cap_*` metrics | **PENDENTE** |
| G4.3 | Sem bloqueio PF | tráfego bancos OK | Histórico PASS Fase 1 |
| G4.4 | Logs contenção L1 | rotação / SQLite | **PENDENTE** (`_26+`) |
| G4.5 | Pressão flow table | `cap_evicted/dropped` | **PENDENTE** (FP-012, `_30+`) |

### G5 — Two-client scoped (validacao-lab §12)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G5.1 | `scoped_hybrid` ON + enforce | duas estações | **PENDENTE** |
| G5.2 | Cliente A block, B allow mesmo destino | curl/browser | **PENDENTE** |
| G5.3 | App policy não quarentena total A | FP-002 | **PENDENTE** |
| G5.4 | Quarentena explícita só A | `quarantine_origin` | **PENDENTE** |
| G5.5 | State kill sessão existente | FP-003 | **PENDENTE** |
| G5.6 | Allow vence blacklist sem bypass nativo | FP-017 | **PENDENTE** |
| G5.7 | Smoke `smoke-enforcement-scoped.sh` | lab script | **PENDENTE** |

### G6 — Licenciamento fail-safe

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G6.1 | Licença inválida → flush PF | DR-05 / F3 (Onda C) | **PENDENTE** appliance mutável |
| G6.2 | Grace 14d offline | daemon | **PENDENTE** |
| G6.3 | Stop serviço → tabelas vazias | rc.d | Parcial documentado |

### G7 — Release pública GO (humano)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G7.1 | G0–G6 PASS documentados | relatório run_id | **NO-GO** |
| G7.2 | CHANGELOG + MANUAL-INSTALL | docs | **PENDENTE** (GO na Onda F) |
| G7.3 | Trust chain F1.2 pacote (BG-028) | manifesto assinado | **Não activo** (Onda I) |
| G7.4 | Tag GitHub GO | release | **PENDENTE** — candidato `v1.8.11_65` |

---

## 3. O que o candidato `_65` inclui (resumo)

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
| GUI i18n | `_65` | BG-076 — não afecta daemon |

---

## 4. Primeiro bloco recomendado (Gate B1 — Onda A do plano mestre)

**Bloco B1 — Gate passivo `1.8.11_65` no appliance**

1. Snapshot VM + rollback `_24` documentado.
2. `pkg add pfSense-pkg-layer7-1.8.11_65.pkg` com `enabled=false`, `mode=monitor`.
3. Executar G2 + G3 + G4 (sem enforce).
4. Recolher `run_id`, stats JSON, `pfctl -sr`, logs em `docs/tests/evidence/`.
5. **Parar** se G2.5 falhar (ABI/OS) — não avançar para G5.
6. Só após G2–G4 PASS: Onda B (G5 two-client).

---

## 5. Rollback por gate

| Falha em | Acção |
|----------|-------|
| G2 | `pkg delete` + reinstall `_24`; confirmar passivo |
| G3 | Desactivar anti-QUIC; flush; escalar FP-018 se persistir |
| G5 | Reverter `scoped_hybrid`; flush-all; manter `_24` enforce ref |
| G6 | Restaurar `.lic`; `enforce_ge_downgrade` manual via stop/start |
| Qualquer | `layer7-pfctl flush-all` + `filter_configure` |

---

## Referências

- [`docs/02-roadmap/plano-fecho-producao-e-consolidacao.md`](../02-roadmap/plano-fecho-producao-e-consolidacao.md)
- [`docs/02-roadmap/checklist-mestre.md`](../02-roadmap/checklist-mestre.md)
- [`docs/04-package/validacao-lab.md`](../04-package/validacao-lab.md) §10a, §10b, §11, §12, §19–20
- [`docs/09-blocking/revisao-funcional-pre-producao-2026-07-29.md`](revisao-funcional-pre-producao-2026-07-29.md)
- BG-052, BG-060, FP-001..FP-020
