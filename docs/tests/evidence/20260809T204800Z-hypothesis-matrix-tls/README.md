# Matriz de hipóteses TLS — B+D (Gate D0, sem edição de produto)

| Campo | Valor |
|-------|--------|
| **Run** | `20260809T204800Z` |
| **Escopo** | Diagnóstico só leitura + repro harness; **sem** mutar código; **sem** reactivar MITM |
| **Pacote NO-GO Edge** | `1.9.42` (evidência `20260809T185035Z`) |
| **Veredicto** | Causa Edge **fechada**; hipóteses alternativas **descartadas** com evidência |

## Matriz

| ID | Hipótese | Veredicto | Evidência mínima |
|----|----------|-----------|------------------|
| H9 | Peer TLS com KU incompatível (CA como servidor) | **CAUSA-RAIZ** | DOM Edge `ERR_SSL_KEY_USAGE_INCOMPATIBLE` (`…185035Z…/remote/08-edge-dom.html`); CA PEM `CA:TRUE` + `Certificate Sign, CRL Sign` (`06-mitm-ca.crt`); harness `run-local-tls-ca-peer.sh` **PASS**; wiring `LAYER7_TLSPROXY_CERT=ca.crt` (`13-pkg-19242-wire.txt`) |
| H1 | Mismatch fingerprint CA carregada ≠ Root `.24` ≠ peer | **DESCARTADA** | `MATCH_LOADED_VS_ROOT=YES` (`25EDD8…`); `10-fingerprint-compare.txt` |
| H2 | Peer ≠ CA (seria leaf) no `1.9.42` | **DESCARTADA** (peer **era** CA) | `11-peer-was-ca.txt` + extensões CA = peer |
| H3 | Leaf sem SAN/EKU | **N/A no 1.9.42** (não havia leaf); no peer B+D: sem `serverAuth`/SAN SNI | `01-hypotheses-raw.txt` H3 |
| H4 | Chain incompleta | **DESCARTADA** como causa Edge | Erro ≠ incomplete chain; Root tinha a CA; `KEY_USAGE` |
| H5 | Cache antigo de leaf | **DESCARTADA** | `1.9.42` sem mint/cache de leaf |
| H6 | Geração tardia / clock skew | **DESCARTADA** | Erro ≠ `ERR_CERT_DATE_INVALID` |
| H7 | CA não carregada no tlsproxy | **DESCARTADA** | CA exportada + thumb = Root |
| H8 | Import no store errado | **DESCARTADA** | Erro ≠ `ERR_CERT_AUTHORITY_INVALID`; `certutil -addstore Root` |

## Repro mínima (sem appliance)

```sh
sh tests/harness/mitm-activate-hang/run-local-tls-ca-peer.sh
# esperado: PASS peer TLS = CA KU certSign/CRLSign
```

## Fora desta matriz

- Hang control-plane / `onerestart` — ver addendum D0 F1-bis (`20260809T195101Z` + repro `timeout` sem `-k`).
- Validação Edge com leaf D1 — **não** fechada neste gate (ciclo `195101Z` sem Edge MITM).
