# Evidência Onda C — DR-05 continuação (Veeam disponível)

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T233500Z-ondaC-dr05-veeam` |
| Pré-requisito | Backup **Veeam** confirmado pelo operador (todos os servidores do projecto) |
| Appliance | `192.168.100.254` |
| Licença teste | ID 5 (Lasalle, expiry `2026-03-31`) |
| Licença produção | Restaurada de `/root/layer7.lic.veeam-backup` (Systemup `2028-07-08`) |

## Resultados

| Cenário | Resultado | Evidência-chave |
|---------|-----------|-----------------|
| **S08** | **PASS** | `2026-04-10` UTC: `license_valid=true`, `license_grace=true`, `license_expired=true` |
| **S12** | **PASS** | Antes expiry (`2026-03-25`): válida; dentro grace (`2026-04-05`): grace; após grace (`2026-04-20`): `license_valid=false` |
| **S13** | **PARTIAL** | Baseline `kern.hostuuid` + fingerprint capturados; drift NIC/UUID **não aplicado** (janela dedicada + restore Veeam) |

## Rollback aplicado

- Relógio restaurado para UTC real (`2026-08-04`)
- `.lic` Systemup reposto; `layer7d` reiniciado
- Pós-restore: `license_valid=true`, `license_customer=Systemup`

## Relação com run anterior

- Run `20260804T232600Z-ondaC-dr05`: S07 **FAIL**, S09 PARTIAL
- Este run desbloqueia S08/S12; **F3 continua aberta** por S07 + S13 incompleto

## Servidores com Veeam (operador)

| Host | Função |
|------|--------|
| `192.168.100.254` | pfSense produção Systemup |
| `192.168.100.12` | Builder FreeBSD |
| `192.168.100.244` | License server live |
