# Evidência P1 — baseline appliance (passo 1.3)

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T220000Z-p1-baseline-appliance254` |
| Data | 2026-08-04 |
| Appliance | `192.168.100.254` |
| Plano | P1 passo 1.3 — diagnose baseline |
| Modo | read-only (`scripts/diagnose-layer7-appliance.sh`) |
| Pacote instalado | `1.8.11_62` (pré-candidato `_65`) |
| Estado Layer7 | `enabled=false`, `mode=monitor`, `legacy_global` |

## Ficheiros

| Ficheiro | Descrição |
|----------|-----------|
| `diagnose-baseline.txt` | Saída completa do script de diagnóstico |

## Notas

- Snapshot hypervisor (passo 1.2): **PASS** — Veeam diário em `254`, `12` e `244` (confirmado `2026-08-04`; ver [`p1-snapshot-gate-b1.md`](../../../08-lab/p1-snapshot-gate-b1.md)).
- Não substitui gates G2–G4 no candidato `_65`.
