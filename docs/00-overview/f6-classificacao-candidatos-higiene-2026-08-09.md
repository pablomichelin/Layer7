# F6 — Classificação de candidatos (higienização estrutural)

**Data:** `2026-08-10` (auditoria; lab local ~2026-08-09 −03)  
**Pré-requisito:** [`f6-inventario-higiene-estrutural-2026-08-09.md`](f6-inventario-higiene-estrutural-2026-08-09.md)  
**Plano / gate / exclusões:** [`f6-plano-higiene-estrutural-residual.md`](f6-plano-higiene-estrutural-residual.md) (BG-112)  
**Modo:** classificação documental **sem execução** de remoção/arquivo físico.  
**Regras invioláveis desta etapa:**

1. Evidência válida e documentos canónicos **não** se removem por presunção.  
2. **P4 FAIL/ABORT** (`20260809T234042Z-p4-soak-254`) deve ser **preservado e classificado como FAIL/ABORT** — proibido reescrever como PASS ou apagar o veredicto.  
3. Acções `REMOVER` / `ARQUIVAR` abaixo são **recomendadas**; exigem GO humano + lote F6 com mapa de links + rollback.

### Legenda de acção

| Acção | Significado |
|-------|-------------|
| **MANTER** | Fica no sítio (canónico, stub útil, ou evidência viva) |
| **ARQUIVAR** | Mover no futuro para destino indicado (preserva história) |
| **REMOVER** | Apagar apenas com GO; tipicamente lixo local / não-git / vazio sem valor |
| **CORRIGIR** | Conteúdo/path/link — sem apagar o artefacto |

### Legenda git

| Valor | Significado |
|-------|-------------|
| `tracked-clean` | Versionado, sem alteração pendente |
| `M` | Versionado, modificado no working tree |
| `??` | Untracked |
| `!!` | Ignorado (`.gitignore`) |
| `untracked-local` | Presente no disco, fora do índice e tipicamente não ignorado por regra nomeada |

---

## A. Resíduos locais / fora do produto

| ID | Caminho exacto | Git | Tamanho / uso | Referências (ficheiros docs/SSOT) | Motivo | Acção | Destino se arquivar | Impacto | Rollback |
|----|----------------|-----|---------------|-----------------------------------|--------|-------|---------------------|---------|----------|
| INV-001 | `${TMPDIR:-` | untracked-local | 0B; dir vazio; artefacto de expansão shell falhada | 0 (só inventário F6) | Lixo de filesystem; sem conteúdo | **REMOVER** | — | Nulo no produto/git | Recriar dir vazio (irrelevante) |
| INV-002 | `.git.broken.1777051669/` | untracked-local | ~0B útil (`objects/` vazio/residual) | 0 | Quarentena git antiga; não é histórico de produto | **REMOVER** | — | Nulo no produto | N/A (não versionado) |
| INV-003 | `.local/` | `!!` | 85M; diags/builds appliance | política `.gitignore`; handoff menciona `.local` | Cache/diag local | **MANTER** (ignorado) / limpeza local opcional | — | Disco local | Restaurar de backup local se houver |
| INV-004 | `tmp-release/` | `!!` | 16M; `.pkg` 1.9.42–1.9.47 + scripts phaseBD/debug | ~6 menções em docs/inventário; **não** SSOT | Staging operacional; risco de confusão com canónico | **REMOVER** (local) após GO *ou* **ARQUIVAR** offline | Fora do repo: `~/layer7-local-archive/tmp-release-YYYYMMDD/` | Só disco/workflows locais | Restaurar pasta do archive offline |
| INV-005 | `build-from-lab/` | `!!` | 5.3M | 0 canónicas | Build legado lab | **REMOVER** (local) ou **ARQUIVAR** offline | `~/layer7-local-archive/build-from-lab/` | Disco local | Restore archive offline |
| INV-006 | `dist/` | `!!` (`/dist/`) | 0B vazio | `.gitignore` | Pasta vazia residual | **REMOVER** (local) | — | Nulo | `mkdir dist` se algum script exigir |
| INV-007 | `artifacts/*.pkg` (locais) | `!!` | parte dos 27M | hashes `.sha256` tracked | Binários locais; política: não commit `.pkg` | **MANTER** local conforme necessidade; **não** commit | — | Disco; builds offline | Re-fetch GitHub Releases |
| INV-008 | `artifacts/Bkp Freebsd 15/` | `!!` | 68K | `.gitignore` explícito | Backup builder sensível | **MANTER** ignorado; **nunca** commit | — | Segredos se mal tratado | — |
| INV-009 | `artifacts/retained/*.pkg` | `!!` | ~18M com dir | `artifacts/retained/README.md` | Retenção local alinhada a hashes tracked | **MANTER** local | — | Disco | Re-fetch releases |
| INV-010 | `artifacts/*.sha256` + `retained/README.md` | tracked-clean | pequeno | MANUAL/release ops | Integridade versionada | **MANTER** | — | — | `git checkout --` |
| INV-011 | `license-server/frontend/node_modules/` | `!!` | 80M | build frontend | Dependências npm | **MANTER** ignorado / regenerável | — | Disco | `npm ci` |
| INV-012 | `license-server/frontend/dist/` | `!!` | 264K | build frontend | Artefacto build UI | **MANTER** ignorado / regenerável | — | Disco | rebuild frontend |

---

## B. Distribuição / espelhos

| ID | Caminho exacto | Git | Tamanho / uso | Referências | Motivo | Acção | Destino | Impacto | Rollback |
|----|----------------|-----|---------------|-------------|--------|-------|---------|---------|----------|
| INV-020 | `public-dist/README.md` (+ dir) | tracked-clean | 4.0K | ~2 refs; texto aponta `docs/commercial/*` | Porta de política pública no repo eng. | **MANTER** | — | Baixo | git revert |
| INV-021 | Raiz `00-LEIA-ME-PRIMEIRO.md` … `16-REFERENCIAS-OFICIAIS.md` (17 stubs) | tracked-clean | ~4K cada | equivalência H5; bookmarks externos | Stubs F6 H5 intencionais | **MANTER** | — | Quebra bookmarks se remover | git restore stubs |
| INV-022 | `docs/archive/raiz-legado/*` | tracked-clean | 112K | ~10 refs mapa/classificação | Texto legado H5 | **MANTER** | — | Perda de história se remover | git revert |
| INV-023 | `logica.md` | tracked-clean | 4K | classificação: placeholder | Notas avulsas | **MANTER** (não expandir) | — | Nulo | git |
| INV-024 | `release-body.md` | tracked-clean | 4K | releases auxiliares | Auxílio publicação | **MANTER** até F7 rever | — | Baixo | git |

---

## C. Stubs / duplicados documentais F6 (canónicos de navegação)

| ID | Caminho exacto | Git | Tamanho | Referências | Motivo | Acção | Destino | Impacto | Rollback |
|----|----------------|-----|---------|-------------|--------|-------|---------|---------|----------|
| INV-030 | `docs/04-tests/` (stub README) | tracked-clean | 4K | ~7 | Redirect H1 | **MANTER** | — | Bookmarks | git |
| INV-031 | `docs/package/` (stub) | tracked-clean | 4K | ~4 | Redirect H2 | **MANTER** | — | Bookmarks | git |
| INV-032 | `docs/archive/pre-f6/` | tracked-clean | 4K+ | mapa H0 | Arquivo pré-move | **MANTER** | — | História F6 | git |
| INV-033 | `docs/02-roadmap/plano-fecho-producao-e-consolidacao.md` | tracked-clean | ~1K stub | ESTADO/CORTEX | Stub 【FECHADO】 | **MANTER** | texto em `docs/archive/planos-fechados/` | Confusão se apagar stub | git |
| INV-034 | `docs/02-roadmap/plano-ipv6-completo.md` | tracked-clean | ~1K stub | idem | Stub 【FECHADO】 | **MANTER** | `docs/archive/planos-fechados/` | idem | git |
| INV-035 | `docs/archive/planos-fechados/` | tracked-clean | 52K | CORTEX/ESTADO | Planos fechados | **MANTER** | — | — | git |
| INV-036 | `docs/00-overview/START-HERE-fecho-producao.md` | tracked-clean | — | ~22 | Arranque manutenção | **MANTER** (canónico) | — | Crítico | git |
| INV-037 | `docs/00-overview/START-HERE-identity-mitm.md` | tracked-clean | — | ~26 | Arranque trilha activa | **MANTER** (canónico) | — | Crítico | git |
| INV-038 | `docs/MANUAL-PRODUTO.md` | tracked-clean | — | ~5 | Hub público canónico | **MANTER** | — | Crítico | git |
| INV-039 | `docs/tutorial/guia-completo-layer7.md` | tracked-clean | 32K dir | ~10 | Tutorial preservado; **não** SSOT | **MANTER** | eventual `docs/archive/tutorial/` só com GO | Baixo se arquivar com stub | git revert + stub |
| INV-040 | `docs/07-prompts/` | tracked-clean | 20K | ~9; supersedido por CORTEX/handoff | Histórico continuidade | **MANTER** + **CORRIGIR** links (INV-070/071) | opcional archive futuro | Baixo | git |
| INV-041 | `docs/changelog/CHANGELOG.md` | tracked-clean | 232K | canónico changelog | Links relativos incorrectos | **MANTER** + **CORRIGIR** paths (INV-072–074) | — | Médio (navegação) | git |
| INV-042 | `docs/13-runbooks/`, `docs/14-logging/` | tracked-clean | — | índices | Destinos H3 canónicos | **MANTER** | — | — | git |

---

## D. Evidências — P4 e canónicas MITM/Gates (PRESERVAR)

| ID | Caminho exacto | Git | Tamanho | Referências | Motivo | Acção | Destino | Impacto | Rollback |
|----|----------------|-----|---------|-------------|--------|-------|---------|---------|----------|
| INV-050 | `docs/tests/evidence/20260809T234042Z-p4-soak-254/` | tracked (`M` só em `07-failsafe-validate-local.txt`) | 364K | **≥9** (CORTEX, START-HERE, backlog, checklist, mapa, runbook, inventário) | **P4 CLOSED FAIL/ABORT** — soak 4h incompleto; rollback limpo; veredicto honesto | **MANTER** | — | **Proibido** remover ou alterar `11-VERDICT.txt` para PASS | git restore; **não** “corrigir” veredicto |
| INV-050a | `…/11-VERDICT.txt` (P4) | tracked | — | SSOT do fecho P4 | Regista `P4_SOAK_VERDICT=FAIL` + `outcome_raw=ABORT` | **MANTER** (conteúdo imutável salvo errata factual) | — | Falsificar = regressão documental | — |
| INV-051 | `docs/tests/evidence/20260809T210753Z-phaseBD-d1-254/` | tracked-clean | 136K | CORTEX Gate C | Gate C Edge **PASS** canónico | **MANTER** | — | Alto | git |
| INV-052 | `docs/tests/evidence/20260809T215442Z-phaseBD-d1-254/` | tracked-clean | 144K | CORTEX GO teste | GO teste controlado **PASS** | **MANTER** | — | Alto | git |
| INV-053 | `docs/tests/evidence/20260809T230400Z-p3-mitm-window/` | tracked-clean | (não re-medido) | CORTEX P3 | P3 janela **PASS** | **MANTER** | — | Alto | git |
| INV-054 | `docs/tests/evidence/20260809T215218Z-preflight-mitm-254/` | tracked-clean | — | CORTEX | Preflight | **MANTER** | — | Médio | git |
| INV-055 | `docs/tests/evidence/20260805T011500Z-ondaH-H0-f6-map/` | tracked-clean | — | mapa F6 | Evidência H0 | **MANTER** | — | Médio | git |
| INV-056 | `docs/tests/evidence/20260805T011800Z-ondaH-f6-H1-H4/` | tracked-clean | — | F6 | Evidência H1–H4 | **MANTER** | — | Médio | git |

---

## E. Evidências FAIL / ABORT / NO-GO (preservar; não falsificar)

| ID | Caminho exacto | Git | Tamanho | Referências | Motivo | Acção | Destino futuro (só GO) | Impacto | Rollback |
|----|----------------|-----|---------|-------------|--------|-------|------------------------|---------|----------|
| INV-060 | `…/20260809T185719Z-abort-rollbackD-254/` | tracked-clean | 28K | CORTEX abort D | ABORT hang filter | **MANTER** | opcional `evidence/archive/mitm-aborts/` | Médio | git |
| INV-061 | `…/20260809T185035Z-phaseBD-mitm-254/` | tracked-clean | 224K | CORTEX B+D NO-GO | NO-GO fase BD | **MANTER** | idem | Médio | git |
| INV-062 | `…/20260809T223927Z-enforce-smoke-24-NOGO/` | tracked-clean | 24K | evidência enforce | NO-GO | **MANTER** | idem | Médio | git |
| INV-063 | `…/20260809T221619Z-preG2-G2-254/` | tracked-clean | 56K | gates | NO-GO | **MANTER** | idem | Médio | git |
| INV-064 | `…/20260809T203522Z-phaseBD-d1-254/` | tracked-clean | 124K | cluster d1 | VERDICT=FAIL iteração | **MANTER** agora; **ARQUIVAR** só com índice | `docs/tests/evidence/archive/phaseBD-d1-iteracoes/` | Baixo se stub/índice | git revert move |
| INV-065 | `…/20260804T223500Z-ondaB-g5-two-client-FAIL/` | tracked-clean | 4K | nome FAIL | FAIL onda B | **MANTER** | archive ondas opcional | Baixo | git |
| INV-066 | `…/20260809T224632Z-enfs24-1946/` | tracked-clean | — | NO-GO_PARCIAL | **MANTER** | — | — | git |

---

## F. Cluster `phaseBD-d1-254` (23 pastas)

### F.1 Canónicos — MANTER

Ver INV-051, INV-052.

### F.2 Untracked — triagem (não apagar por presunção se tiverem conteúdo útil)

| ID | Caminho exacto | Git | Tamanho | Referências | Motivo | Acção recomendada | Destino | Impacto | Rollback |
|----|----------------|-----|---------|-------------|--------|-------------------|---------|---------|----------|
| INV-070 | `…/20260809T212230Z-phaseBD-d1-254/` | `??` | 4K (só RUNID) | inventário | Run abortado mínimo | **REMOVER** *working tree* após confirmação humana de que não há conteúdo único | — | Nulo no git | N/A |
| INV-071 | `…/20260809T212234Z-phaseBD-d1-254/` | `??` | 4K (só RUNID) | inventário | Idem | **REMOVER** working tree (mesma condição) | — | Nulo | N/A |
| INV-072 | `…/20260809T223526Z-phaseBD-d1-254/` | `??` | 136K (pack + CA/screenshot) | inventário (~3) | Evidência completa **não commitada**; pode duplicar/sobrepor canónicos | **MANTER** até revisão humana → depois **commit selectivo** *ou* **ARQUIVAR** offline/local | Se arquivo git: `docs/tests/evidence/archive/phaseBD-d1-untracked/` **com GO** | Médio (histórico lab) | Restaurar de backup; se commitido: git revert |
| INV-073 | `…/20260809T223909Z-post-rollback-baseline/` | `??` | 276K | inventário | Baseline pós-rollback útil | **MANTER** → **commit** recomendado (evidência válida) | — | Perda se apagar sem backup | Re-correr baseline |

### F.3 Iterações tracked (não canónicas de Gate C / GO)

| ID | Caminhos (grupo) | Git | Tamanho agreg. | Motivo | Acção | Destino futuro | Impacto | Rollback |
|----|------------------|-----|----------------|--------|-------|----------------|---------|----------|
| INV-074 | `20260809T195101Z` … `210557Z` `phaseBD-d1-254` (**18** dirs tracked, excl. canónicos) | tracked-clean | ~1.1M | Iterações de debug sync/Edge; história útil mas ruidosa na listagem | **MANTER** até existir índice; depois **ARQUIVAR** em lote (não REMOVER) | `docs/tests/evidence/archive/phaseBD-d1-iteracoes/` + README índice apontando canónicos `210753Z` / `215442Z` | Baixo se links CORTEX actualizados | `git revert` do commit de move |

Lista exacta INV-074:

```text
docs/tests/evidence/20260809T195101Z-phaseBD-d1-254/
docs/tests/evidence/20260809T201013Z-phaseBD-d1-254/
docs/tests/evidence/20260809T201156Z-phaseBD-d1-254/
docs/tests/evidence/20260809T201228Z-phaseBD-d1-254/
docs/tests/evidence/20260809T201345Z-phaseBD-d1-254/
docs/tests/evidence/20260809T201504Z-phaseBD-d1-254/
docs/tests/evidence/20260809T203522Z-phaseBD-d1-254/
docs/tests/evidence/20260809T203825Z-phaseBD-d1-254/
docs/tests/evidence/20260809T203953Z-phaseBD-d1-254/
docs/tests/evidence/20260809T204028Z-phaseBD-d1-254/
docs/tests/evidence/20260809T204058Z-phaseBD-d1-254/
docs/tests/evidence/20260809T204344Z-phaseBD-d1-254/
docs/tests/evidence/20260809T204452Z-phaseBD-d1-254/
docs/tests/evidence/20260809T205257Z-phaseBD-d1-254/
docs/tests/evidence/20260809T205302Z-phaseBD-d1-254/
docs/tests/evidence/20260809T205703Z-phaseBD-d1-254/
docs/tests/evidence/20260809T210040Z-phaseBD-d1-254/
docs/tests/evidence/20260809T210557Z-phaseBD-d1-254/
```

---

## G. Conflitos / correcções documentais (sem remoção de SSOT)

| ID | Alvo | Git | Uso | Motivo | Acção | Impacto | Rollback |
|----|------|-----|-----|--------|-------|---------|----------|
| INV-080 | `docs/02-roadmap/roadmap.md` linha estado F6 + espelho em `CORTEX.md` tabela fases (+ checklist/backlog BG-112) | tracked | SSOT fases | Dizia F6 **planeada** vs checkpoint **FECHADA** H1–H5 | **CORRIGIR** — **aplicado** neste bloco (H1–H5 FECHADA + BG-112 auditoria) | Consistência agentes | git |
| INV-081 | `docs/03-adr/README.md` “próximo ADR-0012 = reorg” vs `ADR-0012-politicas-por-dispositivo-mac-para-ip.md` | tracked | índice ADR | ID reutilizado / desactualizado | **CORRIGIR** índice (sugerir novo ID se ADR estrutural for necessário) | Evita ADR errado | git |
| INV-082 | Links partidos activos (prompts + CHANGELOG) | tracked | nav | Paths relativos errados | **CORRIGIR** (ver inventário §7.1) | Navegação | git |
| INV-083 | `docs/10-license-server/MANUAL-INSTALL.md` addenda `v1.9.0`…`v1.9.46` | tracked | ops | Histórico vs comandos vivos | **MANTER** addenda; **CORRIGIR** só se gate operacional apontar versão morta fora de addenda | Risco install errada se drift real | git |
| INV-084 | `docs/tests/evidence/20260809T234042Z-p4-soak-254/07-failsafe-validate-local.txt` | `M` | evidência P4 | Re-scan local pós-commit | **MANTER**; commit cosmético opcional | Nulo | git checkout |

---

## H. Matriz resumo por acção (contagem)

| Acção | IDs (exemplos) | Notas |
|-------|----------------|-------|
| **MANTER** | INV-003, 007–012, 020–042, **050–056**, **060–066**, 051–054, stubs H5, canónicos START-HERE/MANUAL | Inclui **todo** P4 e evidências FAIL/ABORT/NO-GO canónicas |
| **ARQUIVAR** (futuro GO) | INV-074 (18 iterações), opcional 039/040/060–064 | Sempre com índice + stubs/links; **nunca** apagar |
| **REMOVER** (local/GO) | INV-001, 002, 006; opcional 004/005; INV-070/071 só se confirmado vazio | Preferir fora do git |
| **CORRIGIR** | INV-080–083, 040–041 links | Docs-only |

---

## I. Declaração explícita — P4

```text
EVIDÊNCIA: docs/tests/evidence/20260809T234042Z-p4-soak-254/
VEREDICTO CLASSIFICADO: FAIL / ABORT operacional
NÃO É: PASS de soak 4h
NÃO FAZER: apagar pasta; reescrever 11-VERDICT como PASS; omitir do CORTEX
ACÇÃO: MANTER (preservar)
MOTIVO: rastreabilidade de decisão (supervisor não armado; rollback limpo)
```

---

## J. Próximo passo (humano)

Marcar cada ID com: `GO_REMOVER` / `GO_ARQUIVAR` / `GO_CORRIGIR` / `DEFER` / `IGNORAR`.  
Só então abrir lotes F6 executáveis (P1 docs CORRIGIR primeiro — sem moves).
