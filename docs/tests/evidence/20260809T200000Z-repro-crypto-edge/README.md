# Repro crypto + Edge — `20260809T200000Z`

| Campo | Valor |
|-------|--------|
| **Crypto (builder `.12`)** | **PASS** |
| **Edge real `.24`** | **BLOCKED** — credencial SSH lab corrompida (`/tmp/l7-phaseC-sshpass.env` = 4 bytes) |

## 1) Ferramentas criptográficas (builder FreeBSD)

Fonte: `01-crypto-repro-builder.txt`, `03-inspect-leaf-chain.txt`

| Caso | Resultado |
|------|-----------|
| A) CA-as-peer (`openssl s_server` + peer = CA KU certSign/CRLSign) | **PASS** (classe B+D / `KEY_USAGE`) |
| B) Leaf D1 (`tlsproxy` mint SNI) | **PASS** — peer=`CA:FALSE`, `serverAuth`, SAN=`mitm-lab.test`, block page |
| C) Inspect leaf/chain | **PASS** — chain=1 leaf, cache hit, `verify OK` |
| D) `openssl verify -CAfile` expected CA | **OK** |
| D) `openssl verify` wrong CA | **FAIL** (`unable to get local issuer`) |

Jobs anteriores abortados na UI já tinham o mesmo D1 leaf PASS no builder
(`901808`).

## 2) Edge real (Windows `.24`)

Não executado neste run:

1. Password lab `.24` indisponível neste host.
2. Edge Mac não instalado; Chrome headless local pendurou sem mutar trust store
   (não substitui Edge `.24` + `LocalMachine\Root`).

### Para desbloquear

Fornecer password SSH `administrador@192.168.100.24` (ou restaurar
`/tmp/l7-phaseC-sshpass.env`). Depois, sem activar MITM na `.254`:

1. Subir `tlsproxy` mint (builder ou `.54`) em IP:porta alcançável pela `.24`.
2. Instalar CA em `LocalMachine\Root` (`phase-d-24-edge.ps1 install-ca`).
3. Edge headless **sem** bypass → esperar HTML Layer7 (não `KEY_USAGE` /
   `AUTHORITY_INVALID`).

B+D completo no appliance continua a exigir GO + publish `1.9.43`.
