# Evidência — IM9 / 20.31 malha lab Identity (rede)

| Campo | Valor |
|-------|-------|
| `run_id` | `20260808T135500Z-im9-20.31-identity-mesh` |
| Passo | **20.31** (IM9) |
| Pacote | **1.9.29** |
| Appliance | `192.168.100.254` (`systemupfw.system.up`) |
| Data UTC | `2026-08-08T13:52:55Z` |
| Script | `tests/lab/run-im9-20.31-identity-mesh.sh` |

## Veredicto

| Critério | Resultado |
|----------|-----------|
| 20.31 malha (não-regressão Identity OFF) | **PASS** |
| GI9.1 evidências indexadas | **PASS** (este `run_id`) |
| MITM na malha | **SKIP / DEFER 20.7a** |
| Lab AD/DC/RADIUS físico | **RESIDUAL** (GI5.1, GI6.*, GI7 lab) — não bloqueia fecho documental IM9 |

## Artefactos

| Ficheiro | Conteúdo |
|----------|----------|
| `01-mesh-output.txt` | Saída appliance (PASS; exit 0) |
| `02-run-local.txt` | `tests/run-local.sh` — ALL PASSED (incl. identity_* + policy_decide GI7 unit) |
| `run_id.txt` | Identificador |

## O que a malha prova

1. Pacote/daemon **1.9.29** no appliance.
2. Sem `.lic` / Identity OFF → **sem** listeners `8743`/`1813`.
3. GUI Identity contém limite honesto **ADR-0029**.
4. PF sem `block drop` layer7 com `enabled=false`.
5. Suite local Identity (mapa, LDAP, RADIUS, DC, `ad_*` policy) **PASS**.

## O que fica residual (honesto)

- Expand grupo LDAP em DC real (GI5.1).
- NAS RADIUS accounting físico (GI5.3 lab).
- Agente DC Windows + conflito/logout em lab (GI6).
- Checklist `tests/lab/run-gi7-identity-policies.sh` com AD real (GI7 lab).

Estes residuais **não** reabrem IM7/IM8 (ADR-0029) nem MITM.

## Próximo

**20.32** — MANUAL-INSTALL / USO-LICENÇAS / notes comerciais Identity de rede.
