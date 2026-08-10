# F6 — Mapa de consolidação H.0 (Onda H / passo 9.0)

**Data:** `2026-08-05`  
**Estado:** **H1–H5 EXECUTADOS**  
**Evidência H0:** `docs/tests/evidence/20260805T011500Z-ondaH-H0-f6-map/`  
**Rollback:** `git revert` por lote (`docs-f6-loteN` / H5)

---

## 1. Inventário (pós-H5)

| Caminho | Papel |
|---------|-------|
| `docs/tests/` | **Canónico** F5/evidências |
| `docs/04-tests/` | Stub → `docs/tests/` |
| `docs/04-package/` | **Canónico** package/lab |
| `docs/package/` | Stub H2 |
| `docs/05-daemon/` | Suplementar enforcement |
| `docs/13-runbooks/` | Runbooks |
| `docs/10-license-server/` | **Canónico** install/licenças |
| `docs/14-logging/` | Logging |
| `docs/archive/raiz-legado/` | **H5** — texto completo `00-`…`16-` |
| Raiz `00-`…`16-` | **H5** — stubs redirect |
| `docs/archive/planos-fechados/` | **H5** — planos fecho+IPv6 |
| `docs/02-roadmap/plano-fecho*` / `plano-ipv6*` | **H5** — stubs 【FECHADO】 |

---

## 2. Decisões H.0 + H5

| ID | Origem | Destino | Lote | Estado |
|----|--------|---------|------|--------|
| F6-01 | `docs/04-tests/` | `docs/archive/pre-f6/04-tests/` | **H1** | DONE |
| F6-02 | `docs/package/gui-validation.md` | `docs/04-package/` + stub | **H2** | DONE |
| F6-03 | runbooks | `docs/13-runbooks/` | **H3** | DONE |
| F6-04 | logging | `docs/14-logging/` | **H3** | DONE |
| F6-05 | `docs/05-daemon/` | *(sem move)* | H4 | DONE |
| F6-06 | Raiz `00-`…`16-` | `docs/archive/raiz-legado/` + stubs | **H5** | **DONE** `2026-08-05` |
| F6-07 | planos fecho+IPv6 | `docs/archive/planos-fechados/` + stubs | **H5** | **DONE** `2026-08-05` |

---

## 3. Critérios PASS

- Stubs nos caminhos antigos (bookmarks / links externos).
- Banners `【ARQUIVO · LEGADO】` / `【ARQUIVO · PLANO FECHADO】` no conteúdo arquivado.
- Índices: `docs/archive/*/README.md`, `docs/02-roadmap/README.md`.
- Canónicos (`CORTEX`, `docs/README`, ESTADO-PRODUTO, equivalência) actualizados.

---

## 4. Rollback H5

```text
git revert <commit-H5>
```

Ou restaurar ficheiros de `docs/archive/` para os caminhos originais e remover stubs.

---

## Referências

- [`document-equivalence-map.md`](document-equivalence-map.md)
- [`document-classification.md`](document-classification.md)
- [`../archive/planos-fechados/plano-fecho-producao-e-consolidacao.md`](../archive/planos-fechados/plano-fecho-producao-e-consolidacao.md) sec. Onda H
- [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md)

## Pós-H5 — higiene residual (não reabre este mapa)

Auditoria e lotes opcionais:  
[`f6-plano-higiene-estrutural-residual.md`](f6-plano-higiene-estrutural-residual.md) ·
[`f6-inventario-higiene-estrutural-2026-08-09.md`](f6-inventario-higiene-estrutural-2026-08-09.md) ·
[`f6-classificacao-candidatos-higiene-2026-08-09.md`](f6-classificacao-candidatos-higiene-2026-08-09.md).
