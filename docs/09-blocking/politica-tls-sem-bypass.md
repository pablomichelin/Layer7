# Política — TLS sem bypass (MITM / lab / gates)

**Estado:** vigente na candidata `1.9.43`  
**Regra:** não suavizar validação TLS; não contornar segurança com flags de ignore.

## Proibido (gates MITM, Edge/Chromium, evidência B+D)

| Bypass | Exemplos |
|--------|----------|
| Flags de browser | `--ignore-certificate-errors`, `--ignore-certificate-errors-spki-list`, `--allow-insecure-localhost` como substituto de trust |
| Cliente TLS “cego” | `curl -k` / `--insecure`, `ssl.CERT_NONE`, `verify=False`, `NODE_TLS_REJECT_UNAUTHORIZED=0` |
| Validação omitida | `openssl s_client` sem `-CAfile` + sem `-verify_return_error` quando o objectivo é provar trust |
| “PASS” por interstitial ignorado | capturar DOM de erro Chromium e tratar como sucesso |

## Obrigatório

1. **Trust real:** CA MITM no store correcto (`LocalMachine\Root` no Windows lab) **ou** `-CAfile` / `--cacert` apontando para essa CA.
2. **Peer = leaf** compatível Chromium (`serverAuth`, SAN, não `CA:TRUE`) — Gate D1 / tlsproxy `0.1.3+`.
3. **Edge/Chromium headless** sem flags de ignore; falha de trust = **FAIL** do gate (não contornar).
4. Harnesses em `tests/harness/mitm-activate-hang/` usam `-CAfile` + `-verify_return_error` (ou `openssl verify -CAfile`).

## PoC lab (`src/layer7-tlsproxy` SNI/upstream)

Permitido validar cadeia com `-CAfile`/`--cacert` do material lab (cert/CA do PoC).  
**Proibido** `CERT_NONE` / `curl -k` como caminho por defeito — usar helper com verify de cadeia.

## Rollback desta política

Nenhum — é restrição de segurança. Reverter só com GO humano explícito e ADR.
