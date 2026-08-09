# Gate D1 — Mint leaf SNI (`serverAuth`)

**Estado:** **PASS local** (código + smoke); **Edge `.24` / B+D pendente** (GO humano)  
**Data:** `2026-08-09`  
**Candidata:** `1.9.43` / `layer7-tlsproxy` `0.1.3`  
**Origem:** Gate D0 — `ERR_SSL_KEY_USAGE_INCOMPATIBLE` (peer = CA)

---

## Declaração

| Campo | Valor |
|-------|--------|
| **Objectivo** | Peer TLS = leaf com `serverAuth` assinado pela CA MITM; CA só no trust store |
| **Impacto** | `src/layer7-tlsproxy/tls_lab.c`; package embute binário; sem activação `.254` |
| **Risco** | Médio (handshake / OpenSSL mint) |
| **Teste** | `tests/harness/mitm-activate-hang/run-local-tls-leaf-fix.sh` |
| **Rollback** | Pacote `1.9.42` / reverter tlsproxy |

---

## Critérios

| # | Critério | Estado |
|---|----------|--------|
| 1 | Peer `mitm-lab.test` = leaf; issuer = CA | **PASS** smoke local |
| 2 | EKU `serverAuth` + KU TLS (não só certSign) + SAN + SKI + verify CA | **PASS** smoke local (`0.1.3`) |
| 3 | Edge `.24` + CA Root → HTML Layer7 | **PENDENTE** (GO B+D; sem escrita `.254`) |
| 4 | Control-plane timeout | **PASS** (mesmo `1.9.43`) |
| 5 | Novo GO antes de `.254` | **obrigatório** |

---

## Comportamento

- Se `--cert/--key` têm `CA:TRUE` → mint-mode ON: leaf por SNI (cache 32), default = 1.º `--block-sni` ou `layer7-mitm.local`.
- Se `--cert` já é leaf (PoC) → mint-mode OFF (legado).
- Gate PHP continua a passar paths da CA; o helper deixa de as apresentar como peer.

## Smoke

```bash
sh tests/harness/mitm-activate-hang/run-local-tls-leaf-fix.sh
# Builder: OK 2026-08-09 — subject=CN=mitm-lab.test issuer=Harness-CA; verify OK; 403 block page
```

**TLS sem bypass:** smoke usa `-CAfile` + `-verify_return_error` + `-verify_hostname`.  
Edge B+D: **sem** `--ignore-certificate-errors` — [`politica-tls-sem-bypass.md`](politica-tls-sem-bypass.md).

## Fingerprints (trust chain)

Comparação A/B/C: [`../tests/evidence/20260809T193500Z-ca-fingerprint-compare/`](../tests/evidence/20260809T193500Z-ca-fingerprint-compare/).  
Na janela B+D: CA carregada = Root `.24` = peer/issuer (**YES**); falha Edge = KU, não mismatch de fingerprint.  
Pós-D1: peer SHA1 ≠ CA; `openssl verify -CAfile ca peer` = OK.

## Próximo

1. Build/publish candidata `1.9.43` (GO).  
2. Novo GO humano B+D em `.254` (com timeout control-plane + leaf).  
3. Não reactivar MITM só com trust da CA sem este binário.
