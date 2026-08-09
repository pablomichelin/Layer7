# Evidência — B+D com `1.9.43` / D1 (incompleto)

| Campo | Valor |
|-------|--------|
| **Run** | `20260809T195101Z` |
| **Veredicto** | **FAIL / INCOMPLETO** — Edge D não executado |
| **Pacote** | `1.9.43` instalado (MITM OFF após rollback) |

## O que passou

| Fase | Estado |
|------|--------|
| Install `1.9.43` (mint-mode no binário) | **PASS** |
| Phase A `.54` | **PASS** |
| Phase B rota | **PASS** |
| Phase C `.24` (hosts + Edge path sem MITM) | **PASS** |
| Phase D CA + sync | **PARCIAL** — peer leaf visto (`CN=mitm-lab.test` / issuer `Layer7-PhaseD-Lab-CA`) antes do hang |
| Edge MITM block page | **não corrido** |
| Rollback A/B/D + `.24` | **PASS** (trap EXIT) |

## Causa da interrupção (só o comprovado)

| Facto | Evidência |
|-------|-----------|
| `sync=fail` após `sync_sec=141.646` (≫ 20s) | `06-phaseD-activate.txt` |
| Wrapper `timeout` **sem** `-k` pode pendurar se o filho ignora TERM | `15-timeout-nok-repro.txt` (builder) |
| Peer já era leaf enquanto sync bloqueado | observação RO na janela |
| Cleanup pós-falha → MITM OFF / sem Edge D | `06-phaseD-activate-recovered.txt`, rollback `90-*` |

**Não fechado:** se `onerestart` ignora TERM de forma determinística. Sem correcção especulativa (Gate D0 addendum).

## Estado pós-run (RO)

- `.254`: `1.9.43`, MITM OFF, sem rota `198.18.0.10` (após rollback)
- SSOT diagnóstico: [`diagnostico-D0-addendum-hipoteses-20260809.md`](../../../09-blocking/diagnostico-D0-addendum-hipoteses-20260809.md)

## Próximo (sem improvisar fix)

Repro isolado RO de `onerestart` sob `timeout 20`, **ou** GO humano para correcção control-plane após prova; só depois Edge MITM.
