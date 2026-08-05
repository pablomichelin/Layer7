# Onda D — F4.3 (sec. 11 validacao-lab) — PASS

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T212300Z-ondaD-f4-11-PASS` |
| Appliance | `192.168.100.254` |
| Pacote | `1.8.11_68` |

## Pré-condições aplicadas (lab)

1. `blacklists/config.json` — regra `g5-test-bl` com `"force_dns": true` e CIDR `192.168.100.0/24`
2. `/usr/local/etc/layer7.json` — `mode=enforce` (NAT `rdr` só em enforce; necessário para F4.3)
3. `filter_configure()` via PHP

## Resultado

| Critério | Estado |
|----------|--------|
| `pfctl -s nat` com `rdr` UDP/TCP porta domain → `127.0.0.1` | **PASS** (vmx0 + vmx0.95) |
| Avisos `Layer7: pfctl nat load` | Nenhum observado |
| Regras coerentes com config (CIDR + interfaces) | **PASS** |

Evidência: `11-pfctl-nat.txt`
