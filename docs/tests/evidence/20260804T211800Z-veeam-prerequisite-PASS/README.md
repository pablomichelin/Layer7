# Pré-requisito Veeam — PASS (reconfirmação)

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T211800Z-veeam-prerequisite-PASS` |
| Data | 2026-08-04 |
| Fonte | Operador Systemup (chat) — «já temos backup Veeam» |
| Veredicto | **PASS** — rollback Veeam disponível para lab |

## Âmbito

| Host | IP | Função | Veeam |
|------|-----|--------|-------|
| pfSense Systemup | `192.168.100.254` | Appliance lab / produção | **OK** |
| Builder FreeBSD | `192.168.100.12` | Build `.pkg` | **OK** |
| License server | `192.168.100.244` | PostgreSQL + API | **OK** |

## O que isto desbloqueia

- S08, S12 — já executados com Veeam (`20260804T233500Z-ondaC-dr05-veeam`)
- **S13** — pré-requisito de backup **satísfeito**; pendente apenas a **aplicação controlada** de drift NIC/UUID + restore se necessário

## O que isto **não** substitui

- Execução do cenário S13 (comandos `50-appliance-cli-02` após mudança)
- Restore Veeam após drift — só se o teste alterar o ambiente de forma não reversível por script

## Referências

- [`docs/08-lab/p1-snapshot-gate-b1.md`](../../../08-lab/p1-snapshot-gate-b1.md) — passo P1.2
- Evidência anterior: `20260804T233500Z-ondaC-dr05-veeam/S13/00-manifest.txt`
