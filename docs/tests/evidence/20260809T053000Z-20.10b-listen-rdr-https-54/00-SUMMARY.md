# Evidência — 20.10b listen selectivo + PF rdr + página HTTPS

**Data:** `2026-08-09T053000Z`  
**Host lab:** `192.168.100.54` (descartável)  
**Builder:** `192.168.100.12`  
**Pacote:** `1.9.40`  
**SHA256:** `fbbf206d1b159722a28073dd402f9b0c8ef381eff07eb3a886e5ef8310a41afe`  
**Produção `.254/.234/.235`:** **não tocada**

## Resultado

| Gate | Estado |
|------|--------|
| Product listen loopback-only | **PASS** |
| HTTPS block page (20.10b label) | **PASS** |
| `intercept_ready=true` no health | **PASS** |
| `mitm_effective_claim=false` no binário | **PASS** |
| PHP `test_mitm_config.php` (rdr selectivo / gate) | **PASS** |
| Build `.pkg` 1.9.40 | **PASS** |
| PF `pfctl -nf` no builder | **N/A** (pf netlink ausente); snippet alinhado ao padrão blockpage |
| GI2/GI3 | **não avançado** (20.11) |
| Intercept produção | **não** |

## Rollback

- `mitm.enabled=false`; remover gate `/var/run/layer7/tlsproxy.product`
- Pacote: `1.9.39`
