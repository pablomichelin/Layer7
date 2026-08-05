# Onda H — F6 consolidação (passos 9.0 + 9.1–9.4)

| Campo | Valor |
|-------|-------|
| `run_id` | `20260805T011800Z-ondaH-f6-H1-H4` |
| H.0 | **PASS** — `f6-mapa-consolidacao-H0.md` |
| H1 | `04-tests` → `archive/pre-f6/` + stub |
| H2 | `package/gui-validation` → `04-package/` + stub |
| H3 | `05-runbooks` → `13-runbooks`; `10-logging` → `14-logging` |
| H4 | Links + índices actualizados |
| H5 | **DIFERIDO** (raiz legado) |

## Veredicto

**Onda H — PASS** (R9 satisfeito; H5 explicitamente fora de âmbito)

## Rollback

`git revert` por commit de lote H1–H4.
