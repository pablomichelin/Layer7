# Evidência smoke GV5.3 / 12.11 — `1.9.8`

| Campo | Valor |
|-------|-------|
| `run_id` | `20260805T143000Z-gv5-12.11-smoke-1.9.8` |
| Pacote | `pfSense-pkg-layer7-1.9.8` |
| SHA256 | `229639243fc31333251fa286690bf87db9f20b644039b857ca283d16501a99ec` |
| Appliance | `192.168.100.254` |
| Escopo | Smoke **seguro** pós-12.11 (sem promoção enforce) |

## Veredicto

**PASS** (HTTP/HTTPS `rdr inet6` + dual-listen + regressão DNS + NDP)

| Critério | Resultado |
|----------|-----------|
| Install `1.9.8` + SHA256 | PASS |
| Config enforce/`force_dns` preservada | PASS (`enabled=1`, `mode=enforce`, `force_dns=1`) |
| Listen `127.0.0.1:8099` + `[::1]:8099` | PASS |
| `portal6` via `get_interface_ipv6` | PASS (`2804:6c4:11d:cc00:250:56ff:fe88:a4f7`) |
| `rdr inet6` HTTP/HTTPS → `::1:8099` | PASS (4 regras; vmx0 + vmx0.95) |
| DNS `rdr` inet + inet6 domain | PASS (4+4; regressão OK) |
| Unbound `IN A` + `IN AAAA` | PASS |
| NDP + `ping6` GUA/LL self | PASS (0% loss) |
| Webgui port | `9999` (80/443 rdr correctos; ADR-0017 OK) |

## Nota

- Sem alteração de políticas; só upgrade + resync.
- Produção enforce referência permanece **`1.9.0`** (este smoke é lab).
- Fecha GV5 / BG-083 / passo 12.11.

## Fora de âmbito

GV7.4 promoção enforce.
