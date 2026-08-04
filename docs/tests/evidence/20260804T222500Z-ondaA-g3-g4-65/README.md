# Evidência Onda A — G3 + G4 (`_65`)

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T222500Z-ondaA-g3-g4-65` |
| Plano | passos **2.2** (G3) + **2.3** (G4) |
| Appliance | `192.168.100.254` |
| Estado | `enabled=true`, `mode=monitor`, `_65` |

## G3 — PF parser (dry-run)

- `pfctl -nf /tmp/rules.debug` — PASS
- Snippet enforce gerado em memória (não carregado) com anti-QUIC ON — `pfctl -nf` PASS
- `on vmx0 inet` confirmado (FP-018)

## G4 — Monitor activo

| Métrica | Valor |
|---------|-------|
| `captures` | 2 |
| `cap_pkts` | 25728 |
| `cap_classified` | 708 |
| `cap_dropped` | 0 |
| `cap_evicted` | 0 |
| `enforce_mode` | 0 |
| `smoke-monitor-mode.sh` | exit 0 (todos PASS) |

## Ficheiros

| Ficheiro | Descrição |
|----------|-----------|
| `g3-enforce-snippet-dryrun.txt` | Snippet PF enforce + anti-QUIC (não aplicado) |
| `g3-g4-output.txt` | Snippet + rules.debug + stats JSON |
| `smoke-monitor-mode.txt` | Saída smoke monitor |

## Notas

- G3 enforce testado em **dry-run** para não injectar blocks em produção.
- Layer7 activado em monitor (`enabled=true`) para captura — sem block PF Layer7.
