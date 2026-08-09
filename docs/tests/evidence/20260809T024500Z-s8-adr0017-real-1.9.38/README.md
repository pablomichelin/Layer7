# Evidência S8 — ADR-0017 real + MITM OFF (`1.9.38`)

| Campo | Valor |
|-------|--------|
| `run_id` | `20260809T024500Z-s8-adr0017-real-1.9.38` |
| Tipo | **Teste real** (sem mutação de config) |
| GO humano | Sim (`2026-08-08` chat — cuidado produção) |
| Appliance | `192.168.100.254` — `layer7d` **1.9.38** |
| Cliente A | `192.168.100.234` (`server`) |
| Cliente B | `192.168.100.235` (`zpro-aimirim`) |
| Segurança | **Nenhuma** alteração a `layer7.json` / PF / políticas |
| SHA config | `db7fa26f…3a57` = live = backup pré-teste |

## Método (produção-safe)

Em vez de injectar política nova (risco), usámos domínios **já sinkholados**
pela config operacional (`hide.me`, `bet365.com` — VPN/apostas; não críticos
aos serviços dos servidores) e validámos:

1. DNS → portal `192.168.100.254`
2. HTTP → página Layer7 **«Acesso bloqueado»** (ADR-0017)
3. `example.com` / `google.com` / `youtube.com` continuam **200**
4. `mitm_effective=false`; sem `tlsproxy` / Squid
5. `rdr` `layer7_nat`: TCP:80 → `127.0.0.1:8099`

## Resultados

| Check | `.234` | `.235` |
|-------|--------|--------|
| dig `hide.me` / `bet365.com` | `192.168.100.254` | `192.168.100.254` |
| HTTP sinkhole título | **Acesso bloqueado** | **Acesso bloqueado** |
| HTTP code sinkhole | **200** | **200** |
| `example.com` | **200** | **200** |
| `google.com` HTTPS | **200** | **200** |
| `youtube.com` HTTPS | **200** | **200** |

Appliance: `mitm_effective=false`, `mitm_runtime_available=false`, sem tlsproxy/Squid.

## Veredicto

| Campo | Valor |
|-------|--------|
| Resultado | **PASS** |
| S8 | MITM OFF ≡ ADR-0017 (sinkhole + block page HTTP) **com runtime ausente** |
| Autoriza `20.10`? | **NÃO** — faltam S1–S7 medidos + GO lab runtime |

## Ficheiros

- `00-pre-state.txt` — políticas existentes (6) sem canário novo
- `01-baseline-234.txt` / `01-baseline-235.txt`
- `02-appliance-blockpage-path.txt` — rdr + :8099 + HTML local
- `03-client-234-adr0017.txt` / `03-client-235-adr0017.txt`
- `05-config-unchanged.txt` — SHA idêntico pré/pós

## Nota operacional

Backup em appliance: `/root/layer7.json.pre-s8-adr0017-20260809T024246Z`  
(não foi necessário restaurar — config nunca mudou).
