# Evidência smoke GV5.2 / 12.10 — `1.9.7`

| Campo | Valor |
|-------|-------|
| `run_id` | `20260805T140400Z-gv5-12.10-smoke-1.9.7` |
| Pacote | `pfSense-pkg-layer7-1.9.7` |
| SHA256 | `4a00b40226fb0d92d974c3156d0c6881aa07fde2fe96e8d1821548157cd4fb50` |
| Appliance | `192.168.100.254` |
| Escopo | Smoke **seguro** pós-12.10 (sem 12.11; sem promoção enforce) |

## Veredicto

**PASS** (DNS `rdr inet6` ao vivo + regressão IPv4 + NDP)

| Critério | Resultado |
|----------|-----------|
| Install `1.9.7` + SHA256 | PASS |
| Config enforce/`force_dns` preservada | PASS |
| `pfctl -s nat` `rdr … inet … domain` | PASS (4 regras; vmx0 + vmx0.95) |
| `pfctl -s nat` `rdr … inet6 … domain → ::1` | PASS (4 regras) |
| NDP LAN estável + `ping6` link-local | PASS (64 entradas; 0% loss) |
| Unbound `IN A` | PASS |
| Unbound `IN AAAA` | **N/A half-open** — `portal_ipv6` ausente / sem GUA auto no helper |

## Nota

- Sem alteração de políticas; só upgrade de pacote + `layer7_resync`.
- Produção enforce referência permanece **`1.9.0`** (este smoke é lab no appliance).
- HTTP portal `rdr inet6` / VIP ACL v6 = **12.11** (fora de âmbito).

## Fora de âmbito

12.11; GV7.4 promoção.
