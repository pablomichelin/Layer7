# Evidência — ABORT rollback D (filter_configure hang)

| Campo | Valor |
|-------|--------|
| **Run ID** | `20260809T185719Z` |
| **Veredicto** | **NO-GO** (gate falhou por `filter_configure` pendurado) |
| **Acção** | Somente rollback preparado `/tmp/l7-phaseBD-rollback.sh` |
| **Proibido neste bloco** | Recuperar PF; load PF manual; tocar licença / `.24` / `.54` |

## Estado pós-rollback `.254`

| Check | Resultado |
|-------|-----------|
| `mitm.enabled` / `mitm_effective` | **no / no** |
| source / dest / block_sni | **vazios** |
| rdr MITM / `:8443` | **ausentes** |
| tabelas `layer7_mitm_*` | **inexistentes** |
| rota `198.18` | **ausente** |
| `layer7-tlsproxy` | **not running** |
| `layer7d` | **running** |
| GUI `:9999` | **200** |
| SSH / Internet / LAN | **OK** |

## Ficheiros

- `01-pre-state.txt` — já limpo antes deste abort (janela anterior)
- `02-rollback-exec.txt` — execução do script preparado
- `03-post-state.txt` — confirmação
- `11-VERDICT.txt` — `OVERALL=NO-GO`, `STATE=PASS_CLEAN` (appliance limpo após abort)
