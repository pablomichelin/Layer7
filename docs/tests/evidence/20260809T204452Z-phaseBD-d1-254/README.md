# Evidência B+D Edge — DIAGNÓSTICO (`1.9.44` + hotpatch tabelas)

| Campo | Valor |
|-------|--------|
| Run | `20260809T204452Z` |
| Veredicto | **DIAGNÓSTICO** — **não** é Gate C PASS |
| Base pkg | `1.9.44` + hotpatch `layer7_mitm_tables_apply_to_pf` (código `1.9.45`) |
| Sync | OK (`sync_sec=0.358`) |
| PF tables | OK src=`192.168.100.24` dst=`198.18.0.10` |
| Leaf | OK `CN=mitm-lab.test` / EKU serverAuth / CA:FALSE |
| Edge `.24` | Block page **só** com `--disable-quic` no harness |
| Rollback | OK — MITM OFF, sem `:8443`, rota lab removida |

## Notas

1. Sem materializar tabelas PF live, o rdr existia mas o tráfego ia à origem Phase A → corrigido em `1.9.45` (`layer7_mitm_tables_apply_to_pf`).
2. Com HTTP/3 (QUIC/UDP 443) o Edge contorna o rdr TCP — `--disable-quic` no harness é **diagnóstico**, não critério de gate nem solução de produto.
3. Solução de produto: anti-QUIC escopo MITM (`layer7_mitm_src` → `layer7_mitm_dst`) em `1.9.46+`; Gate C exige Edge **sem** flags/bypass.
4. GO B+D/Edge do proprietário; `.234`/`.235` intocadas; licenciamento intocado.
