# F6 — Inventário de higienização estrutural (auditoria H.0+)

**Data UTC:** `2026-08-10` (local lab `2026-08-09` ~21:26 −03)  
**Modo:** **somente leitura / inventário** — nenhum ficheiro foi apagado, movido,
renomeado ou arquivado nesta etapa.  
**Contexto:** Onda H (F6 H1–H5) já **PASS** em `2026-08-05`; este documento é
uma **segunda passagem de auditoria** (resíduo pós-H5 + ruído operacional
recente), não reabertura de lógica de produto.  
**Referências:** [`f6-mapa-consolidacao-H0.md`](f6-mapa-consolidacao-H0.md),
[`document-equivalence-map.md`](document-equivalence-map.md),
[`document-classification.md`](document-classification.md),
[`../02-roadmap/roadmap.md`](../02-roadmap/roadmap.md) §F6, `CORTEX.md`,
`AGENTS.md`.

**Método:** `find`/`ls`/`du`/`git status`/`git ls-files`/`rg` + scan de links
Markdown relativos em `docs/**/*.md` (Python). Sem SSH a lab/builder; sem
tocar package/código/releases.

**Classificação (passo 3):**  
[`f6-classificacao-candidatos-higiene-2026-08-09.md`](f6-classificacao-candidatos-higiene-2026-08-09.md)
— acção MANTER / ARQUIVAR / REMOVER / CORRIGIR por ID, com impacto e rollback.
**P4 FAIL/ABORT = MANTER** (não falsificar).

**Plano + gate + exclusões (passo 4 / BG-112):**  
[`f6-plano-higiene-estrutural-residual.md`](f6-plano-higiene-estrutural-residual.md)
— execução física **bloqueada** até GO humano marcar IDs (G0–G7).

---

## 0. Legenda de classificação proposta (ainda sem acção)

| Classe | Significado |
|--------|-------------|
| **R** | Resíduo local (gitignore / não canónico) — candidato a limpeza local |
| **D** | Duplicado / sobreposição documental |
| **O** | Obsoleto ou supersedido (histórico ainda útil) |
| **S** | Stub F6 intencional (manter até GO de remoção) |
| **E** | Evidência (conservar; eventual consolidação/indexação) |
| **C** | Conflito documental formal |
| **L** | Link relativo partido |
| **V** | Versão/comando: verificar se é histórico intencional vs drift operacional |

**Acção futura** (não executada aqui): só após GO humano + mapa de links +
rollback por lote (disciplina F6 / Onda H).

---

## 1. Caminhos estranhos / resíduos na raiz

| ID | Caminho | Observação | Classe | Proposta futura (não executar) |
|----|---------|------------|--------|--------------------------------|
| INV-001 | `${TMPDIR:-` (dir vazio na raiz) | Nome literal de expansão shell falhada (`Apr 2`); vazio; **fora do git** | R | Remover localmente com GO; não precisa arquivo |
| INV-002 | `.git.broken.1777051669/` | Quarentena git antiga (`Apr 24`); só `objects/`; ~0B útil; **fora do git** | R | Apagar local após confirmação humana (não é histórico de produto) |
| INV-003 | `.local/` | 85M; ignorado; diags/builds appliance | R | Manter ignorado; limpeza local opcional |
| INV-004 | `.DS_Store` (vários) | Ignorado | R | Ignorar |

---

## 2. Directórios build / artifacts / dist

| ID | Caminho | Tamanho (aprox.) | Git | Classe | Notas |
|----|---------|------------------|-----|--------|-------|
| INV-010 | `artifacts/` | 27M | `.sha256`+README tracked; `.pkg` **ignored** | R/E | Locais `1.9.39`–`1.9.41` + `retained/` (`_24`,`_69`,`1.9.0`,`1.9.8`,`1.9.37`…) + `Bkp Freebsd 15/` (ignorado) |
| INV-011 | `tmp-release/` | 16M | **ignored** (`/.gitignore`) | R | Contém `.pkg` `1.9.42`–`1.9.47`, scripts phaseBD, debug PHP — **resíduo operacional forte** |
| INV-012 | `build-from-lab/` | 5.3M | ignored | R | Build legado lab |
| INV-013 | `dist/` | 0B (vazio) | ignored via `/dist/` | R | Pasta vazia residual |
| INV-014 | `public-dist/` | 4K | `README.md` tracked | S/D | Espelho de política pública; links para `docs/commercial/*` (outro repo/contexto) |
| INV-015 | `license-server/frontend/dist/` + `node_modules/` | (ignorados) | ignored | R | Build frontend |
| INV-016 | `scripts/build/` | presente | tracked (não coberto por `/build/`) | — | **Não** é resíduo; path intencional |
| INV-017 | `.local/*/build` | sob `.local` | ignored | R | Builds de diagnóstico |

---

## 3. Untracked / modified (working tree)

| ID | Item | Classe | Nota |
|----|------|--------|------|
| INV-020 | `docs/tests/evidence/20260809T212230Z-phaseBD-d1-254/` | E | Só `00-RUNID.txt` — run abortado/mínimo **untracked** |
| INV-021 | `docs/tests/evidence/20260809T212234Z-phaseBD-d1-254/` | E | Idem |
| INV-022 | `docs/tests/evidence/20260809T223526Z-phaseBD-d1-254/` | E | Pack completo untracked (incl. CA `.crt`, screenshot) |
| INV-023 | `docs/tests/evidence/20260809T223909Z-post-rollback-baseline/` | E | Baseline pós-rollback untracked |
| INV-024 | `docs/tests/evidence/20260809T234042Z-p4-soak-254/07-failsafe-validate-local.txt` | E | Modified pós-commit `e1106a9` (re-scan local) |
| INV-025 | Contagem untracked paths | — | **35** ficheiros untracked (quase todos evidência 09-ago) |

**Fora do inventário de commit:** artefactos ignored (`.pkg`, `tmp-release/`, `.local/`).

---

## 4. Evidências — volume, clusters, FAIL/ABORT

- **Total de pastas** em `docs/tests/evidence/`: **106**
- Cluster **`*phaseBD-d1-254`**: **23** pastas (iterações Gate C / sync / Edge)
  - Canónico de sucesso referido no CORTEX: `20260809T210753Z-phaseBD-d1-254`
  - GO teste: `20260809T215442Z-phaseBD-d1-254`
  - Untracked/residual: `212230Z`, `212234Z`, `223526Z`

| ID | Evidência (nome) | Sinal | Classe |
|----|------------------|-------|--------|
| INV-030 | `…223500Z-ondaB-g5-two-client-FAIL` | FAIL no nome | E/O |
| INV-031 | `…211700Z-ondaD-f4-10b-PARTIAL` | PARTIAL | E/O |
| INV-032 | `…185719Z-abort-rollbackD-254` | ABORT / NO-GO | E |
| INV-033 | `…185035Z-phaseBD-mitm-254` | NO-GO | E |
| INV-034 | `…223927Z-enforce-smoke-24-NOGO` | NO-GO | E |
| INV-035 | `…221619Z-preG2-G2-254` | NO-GO | E |
| INV-036 | `…224632Z-enfs24-1946` | NO-GO_PARCIAL | E |
| INV-037 | `…203522Z-phaseBD-d1-254` | VERDICT=FAIL | E |
| INV-038 | `…234042Z-p4-soak-254` | FAIL/ABORT operacional (P4) | E |
| INV-039 | Demais `phaseBD-d1-*` pré-`210753Z` | Iterações intermédias | E/D |

**Proposta futura (não executar):** índice canónico “evidência viva vs arquivo de
iteração” + opcional move para `docs/tests/evidence/archive/` **só com GO F6**.

---

## 5. Duplicados / stubs F6 (pós-H5 — intencionais)

| ID | Caminho | Estado | Classe |
|----|---------|--------|--------|
| INV-040 | Raiz `00-`…`16-*.md` | Stubs redirect → `docs/archive/raiz-legado/` | S |
| INV-041 | `docs/04-tests/` | Stub → `docs/tests/` (+ arquivo `archive/pre-f6/04-tests/`) | S/D |
| INV-042 | `docs/package/` | Stub → `docs/04-package/` | S/D |
| INV-043 | `docs/02-roadmap/plano-fecho-*.md` / `plano-ipv6-*.md` | Stubs 【FECHADO】 | S |
| INV-044 | `docs/archive/planos-fechados/` | Arquivo H5 | O/S |
| INV-045 | `docs/archive/raiz-legado/` | Arquivo H5 (18 ficheiros) | O/S |
| INV-046 | `docs/05-runbooks/`, `docs/10-logging/` | **MISSING** (já renomeados H3 → 13/14) | — | OK pós-H3 |
| INV-047 | `docs/00-overview/START-HERE-fecho-producao.md` vs `START-HERE-identity-mitm.md` | Dois arranques **por desenho** | D (controlado) |
| INV-048 | `docs/MANUAL-PRODUTO.md` vs `docs/tutorial/guia-completo-layer7.md` | Hub canónico vs tutorial preservado | D/O |
| INV-049 | `docs/07-prompts/` vs CORTEX/handoff | Continuidade oficial no CORTEX | O/D |

---

## 6. Conflitos documentais formais (novos ou reafirmados)

| ID | Conflito | Fontes | Classe | Nota |
|----|----------|--------|--------|------|
| INV-060 | Estado F6 | Histórico: tabelas diziam F6 **planeada** vs checkpoint **FECHADA** (H1–H5) | C | **Resolvido** neste bloco (INV-080 / BG-112): SSOTs = H1–H5 FECHADA + higiene residual |
| INV-061 | ADR-0012 | `docs/03-adr/README.md` “próximos ADRs” ainda sugere ADR-0012 = reorg estrutural; ficheiro real = políticas MAC→IP | C | Corrigir índice ADR (docs-only) |
| INV-062 | Links em arquivo H5 | `docs/archive/raiz-legado/*` aponta com `../00-overview/...` (partido a partir do arquivo) | L/O | Aceitável como legado **ou** corrigir stubs/arquivo num lote F6 docs |
| INV-063 | CHANGELOG paths | `docs/changelog/CHANGELOG.md` usa `docs/02-roadmap/...` (relativo a `docs/changelog/` → partido) | L | Corrigir para `../02-roadmap/...` |
| INV-064 | Prompts | `docs/07-prompts/README.md` → `../CORTEX.md` (deveria `../../CORTEX.md`) | L | Corrigir num lote docs |

---

## 7. Links relativos partidos (scan)

- Links relativos verificados em `docs/**/*.md`: **1456**
- Partidos (todos): **31**
- Partidos **fora** de `archive/raiz-legado` e `archive/pre-f6`: **5** (superfície activa)

### 7.1 Superfície activa (prioridade)

| ID | Ficheiro | Alvo partido | Correcção provável |
|----|----------|--------------|--------------------|
| INV-070 | `docs/07-prompts/README.md` | `../CORTEX.md` | `../../CORTEX.md` |
| INV-071 | `docs/07-prompts/f3-prompt-continuacao-2026-04-03.md` | `../../tests/templates/f3-validation-campaign-report.md` | `../tests/templates/...` |
| INV-072 | `docs/changelog/CHANGELOG.md` | `docs/00-overview/handoff-chat-novo.md` | `../00-overview/handoff-chat-novo.md` |
| INV-073 | `docs/changelog/CHANGELOG.md` | `docs/02-roadmap/f4-plano-de-implementacao.md` | `../02-roadmap/f4-...` |
| INV-074 | `docs/changelog/CHANGELOG.md` | `docs/02-roadmap/f5-preparacao-malha.md` | `../02-roadmap/f5-...` |

### 7.2 Arquivo (baixo risco operacional)

Restantes ~26 partidos concentrados em `docs/archive/raiz-legado/` e
`docs/archive/pre-f6/04-tests/README.md` (paths pré-H5 não reescritos).

---

## 8. Versões / comandos / URLs

| ID | Área | Achado | Classe |
|----|------|--------|--------|
| INV-080 | Canal `latest` / SSOT vivo | `1.9.47` em CORTEX / START-HERE / MANUAL (secções actuais) | — | OK |
| INV-081 | `MANUAL-INSTALL.md` | **11** URLs `v1.9.47`; **23** URLs `v1.9.0`…`v1.9.46` em addenda/histórico | V | **Provável intencional** (addenda); validar gate AGENTS: nenhum comando *operacional actual* apontar para versão antiga |
| INV-082 | `MANUAL-INSTALL.md` L2068/L2074 | `fetch`/`pkg add` ainda com `1.9.37` e `1.9.0` | V | Confirmar se estão em secção histórica ou comando “rápido” vivo |
| INV-083 | Produção enforce | `1.9.8` referido como referência enforce (ADR/gates) | V | Intencional — não é drift de `latest` |
| INV-084 | `tmp-release/*.pkg` | Locais `1.9.42`–`1.9.47` não versionados no git | R | Limpeza local pós-GO |
| INV-085 | `artifacts/*.pkg` | Locais ignored; hashes tracked | R | Manter política actual |

---

## 9. Índice ADR / governação estrutural

| ID | Item | Nota |
|----|------|------|
| INV-090 | ADR dedicado a “reorg estrutural F6” | **Não existe** como ADR aceite; README ainda lista como “próximo ADR-0012” (conflito INV-061) |
| INV-091 | BG-015 / BG-016 | Backlog: **Concluído (H1–H5)** |
| INV-092 | Este inventário | Equivale a **H.0+** para higienização residual — moves só após GO |

---

## 10. Resumo executivo (prioridades para lotes futuros)

### P0 — segurança / higiene local (sem git, com GO)
1. Remover `${TMPDIR:-` e `.git.broken.*` (INV-001/002)  
2. Decidir retenção de `tmp-release/` e `.pkg` locais (INV-011/010/084)

### P1 — docs activos (sem move de árvore)
1. Corrigir 5 links activos (INV-070–074)  
2. Resolver conflito status F6 planeada vs FECHADA (INV-060)  
3. Corrigir índice ADR-0012 (INV-061)  
4. Auditar comandos operacionais MANUAL vs addenda (INV-081/082)

### P2 — evidências (só com GO F6)
1. Indexar cluster `phaseBD-d1` (23 dirs) — canónicos vs iterações  
2. Decidir destino dos 4 packs **untracked** (INV-020–023): commit selectivo vs archive local  
3. Política de arquivo de evidências FAIL/ABORT antigas (sem apagar história útil)

### P3 — stubs F6
Manter stubs H5 até GO explícito de remoção (bookmarks externos).

---

## 11. O que este inventário **não** fez

- Não apagou / moveu / renomeou / arquivou ficheiros  
- Não alterou código, package, builder, pfSense, lab ou releases  
- Não fez commit/push  
- Não reabriu P5 / piloto MITM externo  

---

## 12. Próximo passo sugerido (humano)

1. Rever este inventário e marcar IDs **GO / DEFER / IGNORAR**.  
2. Autorizar lote documental P1 (links + conflitos) **sem** moves físicos.  
3. Só depois: lotes F6 de limpeza local / evidência com mapa de links + rollback.
