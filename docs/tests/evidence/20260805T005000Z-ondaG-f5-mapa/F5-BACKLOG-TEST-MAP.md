# Onda G — passo 8.1: mapa backlog ↔ testes (F5.3)

**run_id:** `20260805T005000Z-ondaG-f5-mapa`  
**Data:** 2026-08-05  
**Plano:** passo **8.1** — Onda G (F5 malha mínima)

---

## Objectivo

Mapear itens de backlog críticos (BG-009..BG-014, gates G2–G7, cenários F3) para
IDs da `test-matrix.md` e evidências físicas já recolhidas nas Ondas A–D.

---

## Mapa principal

| Backlog | Gate / roteiro | test-matrix | Evidência | Estado |
|---------|----------------|-------------|-----------|--------|
| BG-009 | F4.1 / validacao-lab §10a | **3.8** | `20260804T211600Z-ondaD-f4-10a-PASS` | **PASS** (`_68`/`_69`) |
| BG-010 | F4.2 / §10b | **12.1**, **12.2** | `20260804T212200Z-ondaD-f4-10b-PASS` | **PASS** (`_69` fix SIGHUP) |
| BG-011 | F4.3 / §11 | **6.7** | `20260804T212300Z-ondaD-f4-11-PASS` | **PASS** (`force_dns` NAT) |
| BG-006..008, BG-027 | F3 campanha | **11.4–11.7**, S01–S14 | `20260804T211500Z-ondaC-f3-report` | **PASS** (F3 fechada) |
| BG-077 | S14 check-in | S14 | `20260804T210500Z-ondaC-s14-checkin-PASS` | **PASS** |
| — | G2–G4 Onda A | **2.1**, **3.1**, **6.3** | `20260804T221500Z-ondaA-g2-install-65`, `20260804T222500Z-ondaA-g3-g4-65` | **PASS** |
| — | G5 two-client | **13.6** | `20260804T232800Z-ondaB-g5-full-PASS` | **PASS** |
| — | G6–G7 | **11.x** licenciamento | `20260804T212800Z-ondaD-g6-PASS`, `20260804T212900Z-ondaD-g7-PASS` | **PASS** |
| BG-012 | F5 malha | este mapa + `test-matrix.md` + `f5-smoke-checklist.md` | 8.1+8.2 | **PASS** |
| BG-013 | Cobertura mínima | sec. 1–13 matriz | ver gaps | **Parcial** |
| BG-014 | Evidências ↔ changelog | `docs/tests/evidence/*` | Ondas A–G | **Em curso** |

---

## Gaps abertos (não fechar F5 plena)

| ID | Gap | Bloqueio |
|----|-----|----------|
| CE físico | test-matrix 2.x em pfSense CE | VM CE indisponível (ADR-0022) |
| 13.5 | `_25` smoke Plus | Supersedido por `_69`; opcional revalidar |
| 6.7 VLAN | cenário multi-VLAN opcional §11 | Não executado (opcional BG-011) |
| BG-028 | trust chain pacote | Onda I |
| Smoke único repetível | checklist único builder+appliance | passo **8.2** | **PASS** (`20260805T005650Z-ondaG-f5-smoke-82`) |

---

## Checklist smoke mínimo pós-GO (rascunho 8.2)

1. `tests/run-local.sh` (macOS/CI)
2. Builder: `smoke-layer7d.sh` + `make package`
3. Appliance: `smoke-monitor-mode.sh` + `diagnose-layer7-appliance.sh`
4. Se enforce: `smoke-enforcement-scoped.sh` (two-client)

---

## Rollback

Documental apenas — sem alteração de runtime.

## Risco

Baixo — consolidação de evidência existente.
