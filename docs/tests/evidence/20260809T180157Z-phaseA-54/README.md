# Evidência — Fase A destino lab `198.18.0.10` na `.54`

| Campo | Valor |
|-------|--------|
| **Run ID** | `20260809T180157Z` |
| **Host** | `192.168.100.54` (lab descartável) |
| **Veredicto** | **PASS** |
| **Escopo** | Somente `.54` — sem escrita em `.254` / `.24` / `.234` / `.235` |
| **Runbook** | [`docs/09-blocking/runbook-destino-lab-19818-via-54.md`](../../../09-blocking/runbook-destino-lab-19818-via-54.md) |

## Resultado

- Alias `198.18.0.10/32` em `lo`
- Rota retorno `192.168.100.24/32 via 192.168.100.254`
- HTTPS bind **apenas** `198.18.0.10:443` (SNI `mitm-lab.test`)
- Página marcador `L7-PHASE-A-OK-198.18.0.10`
- CA/cert efêmeros (fingerprints em `08-cert-public-reapply.txt` / `11-VERDICT.txt`)
- Rollback ensaiado (full cycle) + re-apply; fail-safe `at` ~180 min
- **Sem** chaves privadas nesta pasta (`NO_PRIVATE_KEYS_IN_EVID=yes`)

## Rollback remoto

```bash
ssh root@192.168.100.54 '/opt/layer7-poc/mitm-lab-a/phase-a-control.sh rollback'
```
