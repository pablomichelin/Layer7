# Evidência — Fase B rota lab `198.18.0.10/32` no `.254`

| Campo | Valor |
|-------|--------|
| **Run ID** | `20260809T180624Z` |
| **Host** | `192.168.100.254` (produção) |
| **Veredicto** | **PASS** |
| **Escopo** | Somente `.254` — rota runtime; sem GUI persist; sem MITM/rdr; sem `.24/.234/.235` |
| **Runbook** | [`docs/09-blocking/runbook-destino-lab-19818-via-54.md`](../../../09-blocking/runbook-destino-lab-19818-via-54.md) |

## Resultado

- Rota host: `198.18.0.10 → 192.168.100.54` (`vmx0`, **não** persistida em `staticroutes`)
- TLS/marker a partir do `.254`: `L7-PHASE-A-OK-198.18.0.10`
- tcpdump `.254`/`vmx0` e `.54`/`ens160`: handshake TCP/443 + ICMP
- Regra PF temporária: **não adicionada** (`pass in quick on vmx0 inet all` já cobre)
- Smoke: GUI `:9999`=200, `layer7d` running, Internet OK, zero `:8443`/rdr MITM
- Fail-safe: `sleep 1140` (pid em `/tmp/l7-phaseB-failsafe.pid`) + `at now + 15 minutes`
- Backup operacional no appliance: `/tmp/config.xml.bak-phaseB-20260809T180624Z` (**não** commitado)

## Rollback B

```bash
ssh root@192.168.100.254 '/tmp/l7-phaseB-rollback.sh'
# ou:
ssh root@192.168.100.254 'route delete -host 198.18.0.10'
```
