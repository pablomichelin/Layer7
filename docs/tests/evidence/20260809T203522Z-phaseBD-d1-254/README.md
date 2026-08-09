# Evidência B+D `1.9.44` — FAIL tabelas PF

| Campo | Valor |
|-------|--------|
| Run | `20260809T203522Z` |
| Veredicto | **FAIL** (Edge) |
| Pacote | `1.9.44` |
| Sync | **PASS** (`0.341s`) |
| Leaf | **PASS** (`CN=mitm-lab.test` / EKU serverAuth / CA:FALSE) |
| Edge | **FAIL** — DOM = página Phase A (bypass MITM) |
| Causa | rdr emitido; tabelas `layer7_mitm_src/dst` **não materializadas** no PF live |
| Seguimento | `layer7_mitm_tables_apply_to_pf` → candidata `1.9.45` |

Pós-rollback: MITM OFF, sem `:8443`.
