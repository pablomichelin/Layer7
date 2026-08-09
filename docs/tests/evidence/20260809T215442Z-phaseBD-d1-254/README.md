# Evidência — GO humano teste MITM controlado `.254` (`1.9.46`)

| Campo | Valor |
|-------|--------|
| Run | `20260809T215442Z` |
| Veredicto | **PASS** (janela temporária) |
| Decisão humana | GO teste controlado; **NÃO** activação permanente |
| Pacote | `1.9.46` (`SHA256=10998477ef7ae966e6c3566baeb973f922858fc72cc4d3a2dcdd0fb17bae72f5`) |
| Runbook | [`../../../09-blocking/runbook-activacao-mitm-producao-1.9.46.md`](../../../09-blocking/runbook-activacao-mitm-producao-1.9.46.md) |
| Preflight | [`../20260809T215218Z-preflight-mitm-254/`](../20260809T215218Z-preflight-mitm-254/) **PASS** |
| Fail-safe | `at now + 15 minutes` (limpo no teardown) |
| `quic_mode` | **`block`** (não `bypass`) |
| Escopo | src `192.168.100.24/32` → dst `198.18.0.10/32` + SNI `mitm-lab.test` |
| Edge `.24` | **PASS** — block page «Acesso bloqueado»; **sem** `--disable-quic` / bypass TLS |
| Screenshot | `remote/08-edge-screenshot.png` |
| Negativos | escopo **OK**; CA **PASS** (sem trust → sem block page limpa) |
| Rollback | **OK** — MITM OFF; `LISTEN8443=0`; `RDR=0`; `QUIC=0`; GUI=200; NET=OK; CA `.24` removida |
| `.234`/`.235` | intocadas |

## Fingerprints (públicos)

| Item | Valor |
|------|--------|
| CA MITM (appliance) SHA256 | `CF:2E:A1:52:2F:45:54:A5:E9:A1:4B:6C:85:2F:D2:46:EE:C4:E6:11:19:25:5A:E1:9F:8E:6F:58:7B:E1:95:D7` |
| CA subject | `CN=Layer7-PhaseD-Lab-CA` |
| Leaf SHA1 (peer `:8443`) | `16:97:7A:13:C8:33:DD:FD:AF:FB:B6:F6:08:AD:EE:9F:34:56:17:6D` |
| Leaf | `CN=mitm-lab.test`; EKU serverAuth; SAN DNS; `CA:FALSE` |
| Thumb CA no `.24` (durante teste) | `16977A13C833DDFDAFFBB6F608ADEE9F3456176D` |

## PF exacto (durante janela)

```text
rdr … from <layer7_mitm_src> to <layer7_mitm_dst> port https -> 127.0.0.1:8443
block drop quick inet proto udp from <layer7_mitm_src> to <layer7_mitm_dst> port https label "layer7:mitm-anti-quic"
SRC: 192.168.100.24
DST: 198.18.0.10
```

## Notas

1. Sem chaves privadas em evidência (só `.crt` público).  
2. Activação permanente permanece **NO-GO** por decisão humana.  
3. Sem nova release de produto (só docs + evidência operacional).
