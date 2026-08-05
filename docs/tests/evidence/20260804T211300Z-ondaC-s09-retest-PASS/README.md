# S09 — Revogação no backend + `.lic` offline (reteste BG-077)

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T211300Z-ondaC-s09-retest-PASS` |
| Licença teste | `id=12` (revogada após teste) |
| Pacote | `1.8.11_68` |

## Resultado: **PASS** (modelo BG-077)

| Fase | `license_valid` | `.lic` |
|------|-----------------|--------|
| Após activação | `true` | presente |
| Após revogação (offline, sem check-in) | `true` | presente — comportamento offline documentado |
| Após `layer7d --check-in` | `false` | **removido** |

## Conclusão

S09 deixa de ser lacuna comercial quando o operador activa `check_in_enabled`
(ou força `--check-in`): revogação remota reflecte-se no appliance.
Equivalência formal com **S14**.

Evidência: `S09-transcript.txt`
