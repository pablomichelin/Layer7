# Evidência — smoke enforce produção `1.9.29`

| Campo | Valor |
|-------|-------|
| `run_id` | `20260808T181000Z-prod-enforce-1.9.29-smoke` |
| Pacote | **1.9.29** |
| Appliance | `192.168.100.254` |
| Clientes | `.234` (A) · `.235` (B) |
| Config | `enabled=1` · `enforce` · `legacy_global` |
| Data | `2026-08-08` ~15:11 -03 |

## Veredicto: **PASS** (todos os critérios abaixo)

### Appliance
| Teste | Resultado |
|-------|-----------|
| pkg/daemon 1.9.29 | PASS |
| Licença Systemup válida | PASS |
| enforce + legacy_global + ifaces vmx0,vmx0.95 | PASS |
| capture aberta nas 2 ifaces | PASS (log) |
| blockpage :8099 | PASS |
| Identity 8743/1813 fechados | PASS |
| GUI ADR-0029 | PASS |
| allow_dst / block_dst tabelas | PASS (60 / 3) |

### Clientes A e B
| Teste | A | B |
|-------|---|---|
| example.com HTTP | 200 | 200 |
| google.com HTTPS | 200 | 200 |
| youtube.com HTTPS | 200 | 200 |
| example.com DNS | IP público | IP público |
| bet365.com DNS | **sinkhole 254** | **sinkhole 254** |
| pornhub / xvideos DNS | **sinkhole 254** | **sinkhole 254** |
| dns.google (DoH) DNS | **sinkhole 254** | **sinkhole 254** |
| google.com / cloudflare.com DNS | público | público |
| Blockpage HTTP Host:bet365 | 200 + “Acesso bloqueado” | 200 + Layer7 |

## Conclusão

Enforce de produção **operacional** no `1.9.29` com políticas anti-bypass DNS +
protecção infantil (sinkhole + blockpage) e tráfego legítimo intacto nos dois
clientes Ubuntu.
