# F6 — Mapa de consolidação H.0 (Onda H / passo 9.0)

**Data:** `2026-08-05`  
**Estado:** **APROVADO** — base para lotes H1–H4  
**Evidência:** `docs/tests/evidence/20260805T011500Z-ondaH-H0-f6-map/`  
**Rollback:** `git revert` por lote (`docs-f6-loteN`)

> **Regra:** este mapa autoriza os moves da Onda H. Raiz `00-`…`16-` **permanece**
> no sítio (lote H5 diferido) — ver secção 6.

---

## 1. Inventário (2026-08-05)

| Caminho | Ficheiros | Papel actual | Problema |
|---------|-----------|--------------|----------|
| `docs/tests/` | 113 | **Canónico** F5/evidências | — |
| `docs/04-tests/` | 1 (`README.md` stub) | Histórico | Duplica entrada testes |
| `docs/04-package/` | 5 | **Canónico** package/lab | — |
| `docs/package/` | 1 (`gui-validation.md`) | Suplementar | Duplica prefixo package |
| `docs/05-daemon/` | 2 | Suplementar enforcement | Prefixo `05` partilhado |
| `docs/13-runbooks/` | 12 | Runbooks operacionais | Colisão prefixo com `05-daemon` |
| `docs/10-license-server/` | vários | **Canónico** install/licenças | — |
| `docs/14-logging/` | 1 | Suplementar observabilidade | Colisão prefixo com `10-license-server` |
| Raiz `00-`…`16-` | 17 | Legado preservado | Fora do âmbito H1–H4 |

---

## 2. Decisões H.0 (aprovação)

| ID | Origem | Destino | Lote | Racional |
|----|--------|---------|------|----------|
| F6-01 | `docs/04-tests/` | `docs/archive/pre-f6/04-tests/` | **H1** | Stub redirect → `docs/tests/` |
| F6-02 | `docs/04-package/gui-validation.md` | `docs/04-package/gui-validation.md` | **H2** | Unificar package; stub em `docs/package/` |
| F6-03 | `docs/13-runbooks/` | `docs/13-runbooks/` | **H3** | Libertar prefixo `05` para daemon |
| F6-04 | `docs/14-logging/` | `docs/14-logging/` | **H3** | Libertar prefixo `10` para license-server |
| F6-05 | `docs/05-daemon/` | *(sem move)* | — | Conteúdo activo; só indexar em H4 |
| F6-06 | Raiz `00-`…`16-` | *(preservar)* | **H5 diferido** | Alto impacto links externos; mapa equivalência suficiente até revisão humana |

---

## 3. Links afectados (pré-move)

| Padrão | Ocorrências repo (aprox.) | Acção H4 |
|--------|---------------------------|----------|
| `docs/04-tests` | 8 | → stub + archive |
| `docs/package/` | 9 | → `docs/04-package/` + stub |
| `docs/13-runbooks` | 35+ | → `docs/13-runbooks` |
| `docs/14-logging` | 15+ | → `docs/14-logging` |

---

## 4. Ordem de execução (H1 → H4)

```text
H1: mkdir archive → git mv 04-tests → stub README
H2: git mv gui-validation → 04-package → stub package/README
H3: git mv 05-runbooks → 13-runbooks; 10-logging → 14-logging
H4: grep links → actualizar docs/README, classificação, equivalência, CORTEX, backlog BG-015/016
```

---

## 5. Critérios PASS por lote

- Zero link partido nos canónicos (`docs/README.md`, `CORTEX.md`, `MANUAL-INSTALL.md`, ADR index).
- `git grep` sem referências órfãs aos paths antigos (excepto stubs e histórico changelog).
- Commit por lote com mensagem `onda-H/Hn: …`.

---

## 6. H5 diferido (não autorizado neste bloco)

Mover raiz `00-`…`16-` para `docs/archive/raiz-legado/` requer:

- inventário de links externos (GitHub, bookmarks);
- gate humano separado;
- **não** executar sem decisão explícita pós-Onda J.

---

## Referências

- [`document-equivalence-map.md`](document-equivalence-map.md)
- [`document-classification.md`](document-classification.md)
- [`../02-roadmap/plano-fecho-producao-e-consolidacao.md`](../02-roadmap/plano-fecho-producao-e-consolidacao.md) sec. Onda H
