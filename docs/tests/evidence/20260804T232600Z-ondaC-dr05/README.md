# Evidência Onda C — DR-05 campanha F3 (parcial)

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T232600Z-ondaC-dr05` |
| Plano | passos **4.1–4.2** — Onda C |
| Appliance | `192.168.100.254` (`root` SSH) |
| License server | `192.168.100.244` (`/opt/layer7-license`) |
| Pacote | `1.8.11_66` |
| Backup pré-campanha | `/root/layer7.lic.ondaC-backup` (appliance) |
| Veredicto campanha | **F3 não pode fechar** (S07 FAIL; continuação em `20260804T233500Z-ondaC-dr05-veeam`) |

## Resultados por cenário

| Cenário | Licença | Resultado | Notas |
|---------|---------|-----------|-------|
| BASELINE | — | PASS | `export-appliance-evidence.sh`; preflight em `40-preflight-appliance.txt` |
| **S07** | ID 5 (Lasalle, expiry `2026-03-31`) | **PASS** *(reteste `234000Z` após fix `244`)* | API `409`; sem `.lic`; `activations_log=fail` |
| **S08** | ID 5 | **PASS** *(run `233500Z-veeam`)* | Relógio `2026-04-10`: `license_valid=true`, `license_grace=true` |
| **S09** | ID 7 (Systemup, `revoked`) | **PARTIAL** | Backend `244` confirma `revoked`; appliance offline `license_valid=true`; online inconclusivo |
| **S12** | ID 5 | **PASS** *(run `233500Z-veeam`)* | Três fases: antes expiry / dentro grace / após grace |
| **S13** | — | **PARTIAL** *(run `233500Z-veeam`)* | Baseline capturado; drift NIC/UUID pendente janela dedicada |

## Achados relevantes (fora do contrato F3.6)

1. **S07 — activação de licença expirada:** o backend aceita activação (`200`) e o daemon persiste `.lic` mesmo quando a verificação local falha por expiração. Critério canónico F3 exige falha fechada sem `.lic` novo.
2. **layer7d — mensagem enganosa:** respostas HTTP `404` do servidor são reportadas como *"could not reach license server"* apesar de `curl` funcionar no mesmo host.
3. **Inventário live actualizado (`2026-08-04`):** ID 7 e ID 8 estão `revoked` no PostgreSQL live; appliance mantém `.lic` Systemup com expiry `2028-07-08` (válido offline).

## Restauro pós-campanha

- `.lic` de produção restaurado de `/root/layer7.lic.ondaC-backup`
- `license_valid=true`, `license_customer=Systemup`, `license_expiry=2028-07-08`
- `layer7d` reiniciado; modo Layer7 inalterado (`monitor`)

## Ficheiros por cenário

| Pasta | Conteúdo principal |
|-------|-------------------|
| `BASELINE/` | preflight appliance, stats, hash `.lic` |
| `S07/` | activate CLI, backend antes/depois, activations_log |
| `S09/` | backend revoked, stats offline, tentativa activate |

## Próximo passo

- Decisão humana: corrigir backend/daemon para S07 **ou** actualizar contrato F3 com ADR se o comportamento for intencional.
- Reabrir janela com snapshot Veeam para S08/S12/S13 quando autorizado.
