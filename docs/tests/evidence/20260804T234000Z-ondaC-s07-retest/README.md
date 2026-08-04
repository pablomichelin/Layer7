# Evidência — S07 reteste após fix license-server

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T234000Z-ondaC-s07-retest` |
| Fix | `isLicenseExpired()` — comparação `Date` PostgreSQL vs `YYYY-MM-DD` |
| Deploy | `192.168.100.244` (`license.systemup.inf.br`) — container `layer7-license-api` rebuild |
| Veredicto **S07** | **PASS** |

## Resultado

| Critério F3 | Resultado |
|-------------|-----------|
| Activacao falha fechada | `layer7d` exit `255`; API `HTTP 409` `Licenca expirada.` |
| Sem `.lic` novo | `LIC_ABSENT_OK` |
| `activations_log.result=fail` | Confirmado (IDs 35–37) |

## Causa-raiz corrigida

`license.expiry` vinha do PostgreSQL como `Date`; `license.expiry < today` (string)
avalava `false` por coerção incorrecta. Normalização para `YYYY-MM-DD` antes da
comparação.

## Nota daemon

Correcção de mensagens HTTP e defesa `.lic` em `layer7d` incluída no código
(`1.8.11_67`); reteste S07 **PASS** no backend mesmo com appliance em `_66`.
