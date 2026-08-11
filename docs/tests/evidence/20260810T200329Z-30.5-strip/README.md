# Evidence: passo 30.5 — strip / endurecimento de build (BG-115)

- **RUNID:** `20260810T200329Z`
- **Passo:** `30.5` (trilha Anti-pirataria / Anti-tamper)
- **Builder:** `192.168.100.12` (FreeBSD 15)
- **Release candidata:** `pfSense-pkg-layer7-1.9.50.pkg`
- **SHA256 `.pkg`:** `3598828d057948732efb10ac0e958b3078f93a7ce86ad35f73d5f5ce086ec85e`
- **SHA256 `layer7d`:** `6bdc45b7da13716d47e809269e9e67c237d9b38c90e67fbeae8eb63807d3ac08`
- **Pubkey SoT:** inalterada (GA1.8 preservado)

## Resultados

| Critério | Resultado |
|----------|-----------|
| GA2.4 | **PASS** — `file` = stripped; `nm` = no symbols; strings sem `is_dev_key` / `layer7_license_check` |
| GA2.5 | **PASS** — `layer7d -t` e `--fingerprint` PASS (`1.9.50`) |
| GA2.11 | **PASS** — limite de diagnóstico registado (core dumps menos legíveis) |
| `-fvisibility=hidden` | Aplicado no port (binários standalone; sem ABI exportada) |
| Strip explícito | `${STRIP_CMD}` após `INSTALL_PROGRAM` para `layer7d` e `layer7-tlsproxy` |
| Ofuscação | **Não** introduzida (R-G) |
| Appliance `.254` | **não** corrido neste passo |

## Script

`scripts/package/test-prod-strip.sh` (saída em `test-prod-strip.out`)

## Nota

Sem ofuscação/packers/anti-debug. Root continua a poder contornar verificação
local (R-A). Strip só afecta builds futuros; releases antigas: RR-3.
