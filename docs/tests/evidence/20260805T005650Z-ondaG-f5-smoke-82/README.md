# Onda G — passo 8.2: checklist smoke repetível — PASS

| Campo | Valor |
|-------|-------|
| `run_id` | `20260805T005650Z-ondaG-f5-smoke-82` |
| Plano | passo **8.2** — Onda G (F5) |
| Candidato | `1.8.11_69` |
| Backup | Veeam confirmado pelo operador (`2026-08-04`) |

## Fases

| Fase | Resultado |
|------|-----------|
| L1 `tests/run-local.sh` | **PASS** |
| L2 `check-port-files.sh` | **PASS** |
| B1 builder `smoke-layer7d.sh` | **PASS** |
| B2 PORTVERSION builder | **PASS** (`1.8.11_69`) |
| A1 diagnose read-only | **PASS** |
| A2 service + license | **PASS** |
| A3 monitor smoke temporário | **PASS** (PF monitor OK; tabelas estáticas ausentes pós-`filter_configure` — critério mínimo F5) |

## Pós-teste appliance

`mode=enforce`, `enabled=true` — restaurado.

## SSOT checklist

`docs/tests/f5-smoke-checklist.md` + `tests/lab/run-f5-smoke-checklist.sh`

## Veredicto

**Onda G passo 8.2 — PASS** (R8 parcial satisfeito; F5 mínima operacional)
