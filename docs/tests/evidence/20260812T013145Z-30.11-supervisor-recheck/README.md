# Evidência — Supervisor recheck `30.11` (read-only)

**RUNID:** `20260812T013145Z`  
**Modo:** só leitura — sem alterar produção, CF/DNS, pfSense, license-server,
assets/releases GitHub.

## Resultados

| Check | Resultado |
|-------|-----------|
| API `blacklists-ut1-current` `asset_count` | **0** |
| Anónimo nofollow ×4 | **404** |
| Anónimo follow ×4 | **404** (size 9) |
| Residual CDN agora | **nenhum observado** neste vantage |
| Primary sem token | **401** |
| Primary com Bearer | **não reexecutado** — token só no appliance (proibido tocar `.254`); evidência canónica permanece [`../20260812T003214Z-30.11-auth-get-254/`](../20260812T003214Z-30.11-auth-get-254/) |

**Honestidade:** não declarar PASS de re-auth autenticado neste RUNID.
Residual histórico @cut continua em
[`../20260812T011217Z-30.11-cut-mirror/`](../20260812T011217Z-30.11-cut-mirror/).

**Veredicto:** `30.11` continua **FECHADO**.
