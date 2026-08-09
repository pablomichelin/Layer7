# Evidência — Fase C cliente Windows `.24` (hosts + CA)

| Campo | Valor |
|-------|--------|
| **Run ID** | `20260809T181302Z` |
| **Host** | `192.168.100.24` (Windows Server 2022 descartável) |
| **Veredicto** | **PASS** |
| **Escopo** | Somente `.24` (+ extensão fail-safe B no `.254`, já autorizada) |
| **Runbook** | [`docs/09-blocking/runbook-destino-lab-19818-via-54.md`](../../../09-blocking/runbook-destino-lab-19818-via-54.md) |

## Resultado

- `hosts` marcado: `198.18.0.10 mitm-lab.test # L7-PHASE-C-19818-mitm-lab.test`
- CA efêmera em `LocalMachine\Root`: `CN=Layer7-PhaseA-Ephemeral-CA`  
  thumbprint `768AD5B382F2D950DB4273D64E122788732575D8`
- Default GW `.254`; TCP/443 + TLS trust OK (sem bypass de política)
- Edge **151.0.4129.72** headless real → página `L7-PHASE-A-OK-198.18.0.10`  
  **sem** intersticial de certificado (`08-edge-screenshot.png`)
- Rollback ensaiado (CA+hosts) **PASS**; re-apply deixou ambiente pronto para D
- `.254`: rota B UP, MITM OFF, GUI 200; fail-safe sleep + `at` 15:50 −03
- **Sem** chaves privadas / senhas nesta pasta

## Rollback C

Na `.24` (PowerShell admin):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File C:\Windows\Temp\phase-c-24.ps1 rollback
```
