# F6 — Plano de higienização estrutural residual (pós-H1–H5)

**Estado:** **AUDITORIA PASS** — execução física **bloqueada** até GO humano  
**Data:** `2026-08-10`  
**BG:** BG-112  
**Não substitui:** Onda H (H1–H5) já **PASS** — ver [`f6-mapa-consolidacao-H0.md`](f6-mapa-consolidacao-H0.md)

---

## 1. Objectivo

Reduzir resíduo pós-H5 (lixo local, untracked, links partidos, ruído de
evidências iterativas, conflitos de status F6) **sem** perda de rastreabilidade
e **sem** misturar código/package/lab/releases.

---

## 2. Artefactos canónicos deste bloco

| Artefacto | Papel |
|-----------|-------|
| Este plano | Gate, exclusões, lotes, ordem |
| [`f6-inventario-higiene-estrutural-2026-08-09.md`](f6-inventario-higiene-estrutural-2026-08-09.md) | Inventário rastreável (INV-*) |
| [`f6-classificacao-candidatos-higiene-2026-08-09.md`](f6-classificacao-candidatos-higiene-2026-08-09.md) | MANTER / ARQUIVAR / REMOVER / CORRIGIR |
| [`document-equivalence-map.md`](document-equivalence-map.md) | Equivalência + regra pós-H5 + higiene residual |
| [`document-classification.md`](document-classification.md) | Classes (sem reclassificar canónicos por presunção) |
| [`f6-mapa-consolidacao-H0.md`](f6-mapa-consolidacao-H0.md) | Mapa H1–H5 (histórico executado) |

---

## 3. Lista explícita de exclusão (NÃO TOCAR neste plano)

Mesmo com GO de higiene, **fica fora de escopo** salvo GO humano **separado**
e nomeado:

1. **Código** (`src/`, `package/pfSense-pkg-layer7/`, `webgui/`, `tests/` de produto)  
2. **License server** runtime/DB/segredos (`license-server/` excepto docs)  
3. **Builder FreeBSD** e ficheiros sensíveis do builder  
4. **pfSense / appliances** (`.254`, `.24`, `.54`, `.234`, `.235`) — sem SSH/mutação  
5. **GitHub Releases / tags / assets** e canal `latest`  
6. **P5 / piloto MITM externo** e qualquer reactivação MITM  
7. **Alterar veredictos de evidência** (especialmente P4 FAIL/ABORT → não virar PASS)  
8. **Apagar evidência canónica ou FAIL/ABORT/NO-GO** por “limpeza”  
9. **Remover stubs H5** da raiz `00-`…`16-` ou stubs `docs/04-tests`, `docs/package` sem GO de bookmarks  
10. **Commit de segredos / `.pkg` / `.env` / passfiles `/tmp`**  
11. **F7 release engineering** misturado no mesmo lote  
12. **Reabrir planos 【FECHADO】** (fecho produção, IPv6)  

---

## 4. Gate de execução (obrigatório antes de qualquer lote físico)

Todos os itens devem estar **PASS** antes de `REMOVER` / `ARQUIVAR` / move:

| # | Critério | Estado actual |
|---|----------|---------------|
| G0 | Inventário + classificação lidos e IDs marcados GO/DEFER pelo humano | **PENDENTE** GO |
| G1 | Lista de exclusão (§3) reafirmada no pedido de execução | PENDENTE |
| G2 | Mapa de links afectados do lote preenchido (ficheiros → paths) | PENDENTE |
| G3 | Rollback do lote definido (`git revert` e/ou restore offline) | PENDENTE |
| G4 | Scan de segredos no diff do lote **PASS** (sem literais novos) | PENDENTE |
| G5 | Nenhum path na exclusão §3 no lote | PENDENTE |
| G6 | Evidências MANTER (P4, Gate C, GO teste, P3, ABORTs) **fora** do lote de remoção | PENDENTE |
| G7 | Commit por lote pequeno; push só com pedido explícito | PENDENTE |

**Gate documental (lote P1 CORRIGIR — sem moves):** G0–G1 + G4 + G5 bastam;
G2/G3 aplicam-se a paths alterados (links), não a moves.

**Bloqueio:** se G0 falhar (humano não marcou IDs), **não executar**.

---

## 5. Lotes previstos (ordem segura)

### Lote P1 — CORRIGIR docs (sem moves) — preferido primeiro

| Item | IDs | Acção | Estado |
|------|-----|-------|--------|
| Status F6 roadmap/CORTEX/checklist/backlog | INV-080 | CORRIGIR canonicidade | **aplicado** neste bloco documental (H1–H5 FECHADA + BG-112) |
| Índice ADR-0012 | INV-081 | CORRIGIR | PENDENTE GO lote P1 |
| Links activos partidos | INV-070–074 inv. / INV-082 class. | CORRIGIR | PENDENTE GO lote P1 |
| Gate MANUAL comandos vivos vs addenda | INV-083 | Auditar/CORRIGIR só se drift operacional | PENDENTE |

**Exclusão do lote:** qualquer `git mv` / delete de evidência.

### Lote P2 — REMOVER local (fora do git ou working tree vazio)

| Item | IDs |
|------|-----|
| `${TMPDIR:-`, `.git.broken.*`, `dist/` vazio | INV-001, 002, 006 |
| RUNID-only untracked (se humano confirmar vazio) | INV-070, 071 classificação |

**Exclusão:** `tmp-release/` e `.pkg` só com GO explícito (INV-004/005/007).

### Lote P3 — Evidência untracked útil

| Item | IDs |
|------|-----|
| Commit selectivo `223909Z` baseline / triagem `223526Z` | INV-072, 073 |
| **Proibido** alterar P4 veredicto | INV-050 |

### Lote P4 — ARQUIVAR iterações phaseBD (só GO forte)

| Item | IDs |
|------|-----|
| 18 iterações tracked → `docs/tests/evidence/archive/phaseBD-d1-iteracoes/` + índice | INV-074 |
| Canónicos `210753Z` / `215442Z` / P4 **ficam** | INV-050–052 |

---

## 6. Critérios de saída do plano residual

- [x] Conflito F6 planeada vs FECHADA **resolvido** nos SSOTs (INV-080)  
- [ ] Links activos partidos = 0 (ou DEFER documentado)  
- [x] Inventário/classificação/plano commitados (este bloco)  
- [x] Nenhum canónico/P4 removido ou falsificado (este bloco só docs)  
- [ ] Lixos locais P2 tratados ou DEFER explícito  
- [x] CORTEX checkpoint reflecte: H1–H5 FECHADA + higiene residual (auditoria/lotes)

---

## 7. Rollback geral

- Docs: `git revert` por commit de lote  
- Remoções locais: restore de `~/layer7-local-archive/…` se tiver sido arquivado offline  
- Moves evidência: `git revert` do commit de move + restaurar índice CORTEX  

---

## 8. Relação com Onda H

```text
H1–H5 (2026-08-05)     = F6 consolidação principal — FECHADA
Higiene residual (agora)= auditoria + lotes opcionais pós-H5 — NÃO reabre H1–H5
```
