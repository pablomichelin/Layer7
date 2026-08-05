# Evidência GV7.4 — promoção produção enforce `1.9.8`

| Campo | Valor |
|-------|-------|
| `run_id` | `20260805T150500Z-gv7.4-promocao-1.9.8` |
| Passo | **GV7.4** / fecho trilha IPv6 |
| Data | `2026-08-05` |
| Decisor | Operador (GO explícito no chat: «autorizado, pode fazer») |
| Pacote | `pfSense-pkg-layer7-1.9.8` (já publicado; **sem** novo `.pkg`) |
| SHA256 | `229639243fc31333251fa286690bf87db9f20b644039b857ca283d16501a99ec` |

## Pré-condições

| Item | Estado |
|------|--------|
| GV7.1–GV7.3 | PASS (`20260805T133000Z-gv7-fecho`) |
| V5 / 12.10–12.11 / BG-083 | CONCLUÍDOS (`1.9.7` + `1.9.8`) |
| GV5 smoke | PASS (`20260805T143000Z-gv5-12.11-smoke-1.9.8`) |
| Canal `latest` | já `v1.9.8` |
| ADR-0022 CE | LIMITAÇÃO aceite (inalterada) |

## Decisões

1. **Produção enforce:** `1.9.0` → **`1.9.8`**
2. **Canal `latest`:** permanece `1.9.8` — **alinhado** com enforce
3. **Rollback imediato** a partir de `1.9.8`: **`1.9.0`** (produção anterior)
4. **Rollback lab intermédio:** `1.9.7`
5. **Rollback histórico enforce:** `1.8.11_69` / `_24`
6. Sem bump `PORTVERSION`; sem rebuild; sem alteração de políticas no appliance

## Veredicto

**GV7.4 PASS** — produção enforce = **`1.9.8`**. Trilha IPv6 V0–V6 **FECHADA**.
