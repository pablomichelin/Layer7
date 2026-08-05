# G6 — Licenciamento fail-safe — PASS

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T212800Z-ondaD-g6-PASS` |
| Appliance | `192.168.100.254` |
| Pacote | `1.8.11_69` |

## Resultados

| Gate | Resultado | Evidência |
|------|-----------|-----------|
| **G6.1** | **PASS** | Restart sem `layer7.lic`: `valid=0`; tabela `layer7_block` vazia (entrada teste `127.0.0.253` removida) |
| **G6.2** | **PASS** | Onda C **S08/S12** (`20260804T233500Z-ondaC-dr05-veeam`) — grace 14d offline |
| **G6.3** | **PASS** | `service layer7d onestop` → tabelas `layer7_block`, `layer7_block_dst`, `layer7_tagged` vazias |

Ver: `g6-output.txt`
