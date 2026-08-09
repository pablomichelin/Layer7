# Evidência B+D Edge — PASS (`1.9.46`)

| Campo | Valor |
|-------|--------|
| Run | `20260809T210753Z` |
| Veredicto | **PASS** (Gate C) |
| Pacote | `1.9.46` (`SHA256=10998477ef7ae966e6c3566baeb973f922858fc72cc4d3a2dcdd0fb17bae72f5`) |
| Sync | OK (`sync_sec=0.719`) + `filter_configure_safe` via `rc.filter_configure_sync` |
| PF tables | OK src=`192.168.100.24` dst=`198.18.0.10` |
| Rdr | OK `from <layer7_mitm_src> to <layer7_mitm_dst> → :8443` |
| Anti-QUIC | OK `layer7:mitm-anti-quic` UDP/443 escopo tabelas (sem inet6/any) |
| Leaf | OK `CN=mitm-lab.test` / EKU serverAuth / CA:FALSE |
| Edge `.24` | **PASS** — DOM «acesso bloqueado»; sem interstitial; **sem** `--disable-quic` |
| Negativos escopo | OK (origem/destino fora das tabelas) |
| Rollback | OK — MITM OFF; `LISTEN8443=0`; `RDR=0`; `QUIC=0`; GUI=200; NET=OK |

## Notas

1. `204452Z` permanece **diagnóstico** (Edge só com `--disable-quic`).
2. Produto: tabelas live (`1.9.45`) + anti-QUIC escopo + sync real (`1.9.46`).
3. `.234`/`.235` intocadas; licenciamento intocado.
