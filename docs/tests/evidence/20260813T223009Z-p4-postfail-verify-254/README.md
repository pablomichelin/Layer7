# Evidência — verificação pós-P4 retry FAIL (`.254`)

**Estado:** MITM **OFF** verificado `2026-08-13T22:30:09Z` (read-only).  
**Não** é PASS de soak 4 h. **Não** autoriza piloto/permanente.

## Resultado

| Campo | Valor |
|-------|--------|
| Pacote | `1.9.59` |
| Layer7 | `enabled=true` · `mode=monitor` |
| MITM | `mitm_enabled=false` · `mitm_effective=false` · `deadline_unix=0` |
| Intercept | `RDR=0` · `LISTEN8443=0` · `layer7-tlsproxy` not running |
| P4.1 supervisor | cron `/etc/crontab` 1 min · stamp fresco (idade ~11 s) |
| Soak `170000Z` | **CLOSED FAIL** (`health_ssh_fail` sample=14) |
| Rollback no fecho | incompleto (`rollback_clean=0`); P4.1 tick + deadline limparam intercept depois |
| Residual | JSON `ca.present=true` (metadados Layer7-P4-Soak-CA) **sem** ficheiros CA |

## Causa do abort (ops, não motor TLS)

`07-health-14.txt` do soak: `AUTH_FAIL no_key_no_SSHPASS_no_passfile`.  
O helper SSH do orquestrador perdeu chave/passfile a meio do soak. Health 00–13 ainda autenticavam.

## Artefactos

- `00-state.txt` — dump read-only `.254`
- Soak: [`../20260813T170000Z-p4-retry-254/`](../20260813T170000Z-p4-retry-254/)
