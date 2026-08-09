# Inspecção leaf/chain — tlsproxy mint D1

| Campo | Valor |
|-------|--------|
| **Run** | `20260809T194000Z` |
| **Alvo** | builder `.12` (não `.254`) |
| **Harness** | `tests/harness/mitm-activate-hang/inspect-leaf-chain.sh` |
| **Veredicto** | **PASS** |

## Resumo

| Item | Resultado |
|------|-----------|
| Issuer | `CN=Layer7-Inspect-CA` (≠ peer) |
| Subject / SAN | `CN=mitm-lab.test` / `DNS:mitm-lab.test` |
| SNI pedido | `mitm-lab.test` (servername callback) |
| EKU | TLS Web Server Authentication |
| KeyUsage | Digital Signature, Key Encipherment (critical) |
| BasicConstraints | `CA:FALSE` (critical) |
| Validade | ~30d; `notBefore` −60s; clock OK |
| Algoritmo | RSA 2048 + sha256WithRSAEncryption |
| Serial | monotónico por SNI mint |
| Chain enviada | **1** cert (leaf only; CA não enviada) |
| Encoding | PEM `BEGIN CERTIFICATE` |
| Cache | mesmo SNI → mesmo SHA256/serial |
| Outro SNI | cert diferente |
| Reload | só via restart processo (`onerestart`); sem SIGHUP de cache |
| Verify | `openssl verify -CAfile ca.crt leaf` OK |

Ver `01-REPORT.txt`.
