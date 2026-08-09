# Evidência — S5 + S7 pré-runtime (`1.9.38`)

| Campo | Valor |
|-------|--------|
| `run_id` | `20260809T031200Z-s5-s7-pre-runtime` |
| Tipo | Documental + verificação de código / facto S8 |
| Mutação appliance | **Nenhuma** |

## Resultados

| Critério | Veredicto | Artefacto |
|----------|-----------|-----------|
| **S5** QUIC caminho escrito | **PASS documental** (default `bypass`) | `S5-decisao-quic.md` |
| **S7** privacidade default | **PASS documental** (+ sem runtime) | `S7-politica-privacidade.md` |
| **S8** (prévio) | **PASS** ADR-0017 real | `../20260809T024500Z-s8-adr0017-real-1.9.38/` |

## Ainda bloqueiam 20.10

S1, S2, S3, S4, S6 (exigem PoC runtime) + **GO lab** explícito.

## Ficheiros

- `S5-decisao-quic.md`
- `S7-politica-privacidade.md`
- `01-code-quic-refs.txt`
- `02-code-privacy-refs.txt`
- `03-s8-mitm-false.txt` (extracto: effective=false, sem tlsproxy)
