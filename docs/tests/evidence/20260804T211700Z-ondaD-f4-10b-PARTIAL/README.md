# Onda D — F4.2 (sec. 10b validacao-lab) — PARTIAL

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T211700Z-ondaD-f4-10b-PARTIAL` |
| Appliance | `192.168.100.254` |

## Resultado

| Item | Estado |
|------|--------|
| `update-blacklists.sh` presente | OK |
| `fallback.state` `status=healthy` | OK |
| Cron 03:00 mirror GitHub | OK (log) |
| `update-blacklists.sh --apply` | `INFO: apply complete` |
| SIGHUP ao daemon | **WARN** — `cannot read /var/run/layer7d.pid` (investigar) |

Veredicto: **PARTIAL** — pipeline healthy; SIGHUP skip impede PASS completo do critério 10b.

Evidência: `10b-output.txt`
