# Gates IPv6 appliance — `1.9.3`

| Campo | Valor |
|-------|-------|
| `run_id` | `20260805T110000Z-gv-ipv6-1.9.3` |
| Data | 2026-08-05 |
| Versão | `pfSense-pkg-layer7-1.9.3` |
| Appliance | `192.168.100.254` (pfSense Plus 26.03.1) |
| Cliente A | `192.168.100.234` / IPv6 `2804:6c4:11d:cc00::100b` |
| Cliente B | `192.168.100.235` / IPv6 `2804:6c4:11d:cc00::100a` |

## Veredicto

**GV1 parcial → 1.3 PASS; 1.6 GAP (Plus).**  
**GV3 parcial → 3.2/3.3/3.4/3.5 PASS (com ressalvas).**  
**GV4 parcial → sintaxe/tabela PASS; enforce real BLOQUEADO por lab (sem `pass inet6` LAN).**

Produção enforce `1.9.0` **não** alterada. Config lab restaurada a
`legacy_global` / `enforce` / 4 políticas após cada teste.

## Matriz

| Gate | Resultado | Evidência |
|------|-----------|-----------|
| GV1.3 `pfctl -nf` scoped ON | **PASS** (`rc=0`) | `06-gv1-3-scoped-pfctl-nf.txt` |
| GV1.5 NDP desenho | **PASS** (sem block icmp6 genérico Layer7; NDP vizinhos vivos) | `02-gv3-ndp-reachability.txt` |
| GV1.6 `localsubnets` v6 | **GAP** — tabela `localsubnets` **não existe** neste Plus; equivalente `LAN__NETWORK` contém `2804:6c4:11d:cc00::/64` | `01-pf-tables-inet6.txt`, `03-ipv6-routing-localsubnets.txt` |
| GV3.1 install `1.9.3` | **PASS** (já instalado; QA2) | `00-baseline.txt` |
| GV3.2 captura v6 | **PASS** (`cap_pkts_v6`/`cap_classified_v6` > 0) | `00-baseline.txt` |
| GV3.3 não regressão v4 | **PASS** (`cap_classified_v4`↑; google4/yt4=200) | `10-gv3-3-ipv4-regression.txt` |
| GV3.4 zero block em monitor | **PASS** (0 regras Layer7; tabelas 0) | `09-gv3-4-monitor-zero-block.txt` |
| GV3.5 NDP/RA | **PASS** (SLAAC/DHCPv6 addrs; NDP entries A/B) | `02-…` |
| GV4.1 block app cliente v6 | **BLOQUEADO lab** — LAN sem `pass inet6` (só `pass inet`); clientes sem egress IPv6; WAN do FW ping6 OK | `03-…`, `04-lan-v6-egress-diag.txt` |
| GV4.2 tabela pdst com IPv6 | **PASS sintaxe** — regra `inet6 from <src_v6> to <pdst_N>`; `pfctl -T add` IPv6 OK | `11-gv4-synthetic-pdst-v6.txt` |
| GV4.3 two-client v6 | **BLOQUEADO lab** (mesmo motivo GV4.1) | — |
| GV4.4 rollback | **PASS** (restore JSON + resync após cada bloco) | `07-`, `09-`, `11-` |
| GV4.5 especiais fora das tabelas | **PARCIAL** — daemon unit PASS prévio; `pfctl -T add` manual aceita `fe80`/`ff02`/`::1` (esperado); flush limpou | `11-…` |

## Achados

### A1 — MÉDIO — `localsubnets` ausente no pfSense Plus

Layer7 emite `to !<localsubnets>`. Neste appliance a tabela **não existe**;
Plus usa `LAN__NETWORK` (já dual-stack). Com tabela vazia/ausente,
`to ! <localsubnets>` trata **qualquer destino** como não-local.

Impacto actual mitigado: `layer7_block` vazio (0 pkts em block:src6).
Risco sobe se quarentena `psrc`/anti-DoT com `localsubnets` for activada.

**Próximo:** FIX-ipv6 — garantir tabela `localsubnets` populada **ou**
alias Plus-aware (`LAN__NETWORK` / redes conectadas).

### A2 — LAB — Sem `pass inet6` na LAN

Regras de utilizador: `pass in quick on vmx0 inet all` — **sem** par `inet6`.
Clientes A/B: IPv4 OK; IPv6 internet timeout. Appliance WAN: `ping6` Google OK.
**Não é regressão Layer7** (`block:dst6` = 0 packets).

Bloqueia GV4.1/GV4.3 até existir allow IPv6 LAN (decisão humana / regra lab).

### A3 — Captura IPv6 OK em produção lab

Mesmo sem egress cliente, tráfego IPv6 local/NDP/etc. alimenta
`cap_*_v6` — revalida GV3.2 em `1.9.3`.

## Ficheiros

| Ficheiro | Conteúdo |
|----------|----------|
| `00-baseline.txt` | pkg, stats, modelo |
| `01-pf-tables-inet6.txt` | tabelas / regras Layer7 |
| `02-gv3-ndp-reachability.txt` | NDP + curl4/6 |
| `03-ipv6-routing-localsubnets.txt` | rotas, `LAN__NETWORK`, ping6 FW |
| `04-lan-v6-egress-diag.txt` | pass LAN só inet |
| `05-gv1-scoped-emit.txt` | emissão PHP scoped |
| `06-gv1-3-scoped-pfctl-nf.txt` | GV1.3 |
| `07-gv1-restore.txt` | restore pós-GV1 |
| `08-post-restore-confirm.txt` | confirmação |
| `09-gv3-4-monitor-zero-block.txt` | GV3.4 |
| `10-gv3-3-ipv4-regression.txt` | GV3.3 |
| `11-gv4-synthetic-pdst-v6.txt` | GV4 sintético |
| `layer7.json.pre-gv` | backup pré-campanha |

## Próximos passos autorizados

1. Decisão humana: permitir IPv6 na LAN do lab (`pass inet6` em `vmx0`) para fechar GV4.1/4.3.
2. FIX-ipv6 / backlog: `localsubnets` no Plus (A1) — candidato patch `1.9.4`.
3. Depois: retomar 12.10 (V5) com GO, ou GV6.
