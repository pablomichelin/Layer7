# Evidência GV6 PASS — dual-stack `1.9.6`

| Campo | Valor |
|-------|-------|
| `run_id` | `20260805T130620Z-gv6-dualstack` |
| Pacote | `pfSense-pkg-layer7-1.9.6` |
| Script | `tests/lab/run-ipv6-dualstack.sh` (`L7_RUN_LAB=1`) |
| Roteiro | `validacao-lab.md` §21 |

## Veredicto

**GV6.1 / GV6.2 / GV6.3 PASS**

| Cliente | yt4 | yt6 | g4 | g6 |
|---------|-----|-----|----|----|
| A | 000 | 000 | 200 | 200 |
| B | 200 | 200 | 200 | 200 |

- `scoped_hybrid` + `src_hosts` = IPv4(A) + GUA + SLAAC
- DNS local: A+AAAA → `layer7_pdst_0` (16 entradas após retries)
- Lab restaurado a `legacy_global` / 4 políticas; `pass inet6` LAN mantida

## Nota operacional

Aprendizagem A e AAAA pode ser **assíncrona** — o smoke espera ambos no
`pdst` antes do curl (até 5 tentativas).

## Fora de âmbito

V5 (`rdr inet6` / block page / VIP DNS v6) — ADR-0024 Opção B.
