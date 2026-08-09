# Evidência S8 — MITM OFF / sem runtime (`1.9.38`)

| Campo | Valor |
|-------|--------|
| `run_id` | `20260809T021800Z-s8-mitm-off-1.9.38` |
| Data (UTC) | `2026-08-09T02:18:00Z` (aprox.) |
| Appliance | `192.168.100.254` |
| Pacote | `pfSense-pkg-layer7-1.9.38` / `layer7d -V` → `1.9.38` |
| Modo | `enabled=true`, `mode=enforce` (lab) |
| Config `mitm` | **ausente** (`null`) → defaults OFF |
| Runbook | [`../../../09-blocking/runbook-s1-s8-mitm-pre-runtime.md`](../../../09-blocking/runbook-s1-s8-mitm-pre-runtime.md) |

## Checks executados (só-leitura)

| Check | Resultado |
|-------|-----------|
| Versão pacote/binário | `1.9.38` |
| Processo `layer7-tlsproxy` | **ausente** |
| Processo Squid / ssl_bump | **ausente** |
| Stats `mitm_effective` | **false** |
| Stats `mitm_runtime_available` | **false** |
| Stats `mitm_entitled` | **false** |
| Mutação de config / intercept | **não** |

Excerto stats (`/var/db/layer7/layer7-stats.json`):

```json
"mitm_entitled": false,
"mitm_runtime_available": false,
"mitm_effective": false
```

## Veredicto

| Campo | Valor |
|-------|--------|
| Resultado | **PASS parcial** |
| Cumpre | Sem runtime; `mitm_effective=false`; Squid/tlsproxy ausentes |
| Pendente para S8 completo | Smoke visual ADR-0017 (sinkhole → block page HTTP) documentado neste `run_id` |
| Autoriza `20.10`? | **NÃO** |

## Segurança

- Nenhuma alteração de config.
- Produção enforce pin `1.9.8` **não** tocada.
- Sem claim de intercept / `mitm_effective=true`.
