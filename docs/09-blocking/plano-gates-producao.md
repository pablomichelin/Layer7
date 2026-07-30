# Plano — gates de produção Layer7

**Data:** 2026-07-29  
**Versão alvo candidata:** `1.8.11_31`  
**Release pública actual:** `1.8.11_24`  
**Veredicto actual:** **NO-GO** para activação enforce / publicação `_31`  
**Estado:** CANDIDATO INTERNO EM VALIDAÇÃO

---

## 1. Princípios

1. Nenhum gate físico substituído por testes macOS/builder isolados.
2. Ordem: **passivo → monitor → enforce scoped → enforce legacy** (se algum dia).
3. Rollback sempre para `_24` passivo documentado.
4. `scoped_hybrid` permanece experimental até E8 + two-client PASS.
5. Produção real intocada até veredicto humano explícito.

---

## 2. Gates obrigatórios (sequência)

### G0 — Higiene repositório (local)

| # | Critério | Método | Estado 2026-07-29 |
|---|----------|--------|-------------------|
| G0.1 | `tests/run-local.sh` PASS | macOS / CI | **PASS** (PHP SKIP) |
| G0.2 | Lint shell pacote | `sh -n` | **PASS** |
| G0.3 | Working tree limpo para release | `git status` | **FAIL** — `activate.js`, `00-LEIA-ME-PRIMEIRO.md` modificados; `artifacts/` untracked |

### G1 — Build FreeBSD (builder 192.168.100.12)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G1.1 | Build nDPI + pacote `_31` | Makefile port | **PASS** (documentado CORTEX) |
| G1.2 | SHA256 artefacto | `sha256` `.pkg` | `dc5118dd…453e33` |
| G1.3 | Smoke `layer7d -t` | builder | **PASS** (documentado) |
| G1.4 | PHP lint no builder | `php -l` | **PASS** (documentado) |

### G2 — Instalação passiva (appliance)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G2.1 | Instalar `_31` sem activar | `pkg add` + `enabled=false` | **PENDENTE** |
| G2.2 | Daemon arranca, PID legível | `service layer7d status` | **PENDENTE** |
| G2.3 | Zero regras block Layer7 | `pfctl -sr` grep layer7 | **PENDENTE** |
| G2.4 | Tabelas dinâmicas vazias | `layer7-pfctl` / pfctl -T show | **PENDENTE** |
| G2.5 | Binário FB16 executável | `layer7d -V`, `ldd` | **PENDENTE** (FP-011) |

### G3 — Parser PF completo

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G3.1 | Ruleset completo `pfctl -nf` | Com anti-QUIC ON | **PENDENTE** |
| G3.2 | Ordem `on <if> inet` | FP-018 fix `_29` | Sintético PASS; completo **PENDENTE** |
| G3.3 | Regras `pallow` + `L7ALLOW` | FP-017 | **PENDENTE** appliance |

### G4 — Monitor activo (captura)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G4.1 | `captures > 0` interfaces reais | stats JSON | **PENDENTE** |
| G4.2 | Ida/volta mesmo fluxo classificado | `cap_*` metrics | **PENDENTE** |
| G4.3 | Sem bloqueio PF | tráfego bancos OK | Histórico PASS Fase 1 |
| G4.4 | Logs contenção L1 | rotação / SQLite | **PENDENTE** (`_26`) |
| G4.5 | Pressão flow table | `cap_evicted/dropped` | **PENDENTE** (FP-012) |

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
| G6.1 | Licença inválida → flush PF | DR-05 / F3 | **PENDENTE** appliance mutável |
| G6.2 | Grace 14d offline | daemon | **PENDENTE** |
| G6.3 | Stop serviço → tabelas vazias | rc.d | Parcial documentado |

### G7 — Release pública (humano)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| G7.1 | G0–G6 PASS documentados | relatório run_id | **NO-GO** |
| G7.2 | CHANGELOG + MANUAL-INSTALL | docs | **PENDENTE** |
| G7.3 | Trust chain F1.2 pacote (BG-028) | manifesto assinado | **Não activo** |
| G7.4 | Tag GitHub `v1.8.11_31` | release | **Não criada** |

---

## 3. Gates por versão (o que cada candidato desbloqueia)

| Versão | Desbloqueia gate | Ainda bloqueado |
|--------|------------------|-----------------|
| `_24` | Release pública; monitor Fase 1 | Two-client; bugs FP-001..020 |
| `_25` | PID, interfaces, scoped validation | Appliance |
| `_26` | Logs L1 | Appliance |
| `_27` | Enforcement funcional código | Appliance |
| `_28` | Allow PF seguro | pfctl -nf + two-client |
| `_29` | Anti-QUIC syntax | Ruleset completo |
| `_30` | Flow table resiliente | Carga real |
| `_31` | nDPI estado final | **Todos G2–G7** |

---

## 4. Primeiro bloco recomendado pós-auditoria

**Bloco B1 — Gate passivo `_31` no appliance (read-only → install passivo)**

1. Snapshot VM + rollback `_24` documentado.
2. `pkg add pfSense-pkg-layer7-1.8.11_31.pkg` com `enabled=false`, `mode=monitor`.
3. Executar G2 + G3 + G4 (sem enforce).
4. Recolher run_id, stats JSON, `pfctl -sr`, logs.
5. **Parar** se G2.5 falhar (ABI) — não avançar para G5.

---

## 5. Rollback por gate

| Falha em | Acção |
|----------|-------|
| G2 | `pkg delete` + reinstall `_24`; confirmar passivo |
| G3 | Desactivar anti-QUIC; flush; escalar FP-018 se persistir |
| G5 | Reverter `scoped_hybrid`; flush-all; manter `_24` |
| G6 | Restaurar `.lic`; `enforce_ge_downgrade` manual via stop/start |
| Qualquer | `layer7-pfctl flush-all` + `filter_configure` |

---

## Referências

- `docs/02-roadmap/checklist-mestre.md`
- `docs/04-package/validacao-lab.md` §10a, §10b, §11, §12
- `docs/09-blocking/revisao-funcional-pre-producao-2026-07-29.md`
- BG-052, FP-016
