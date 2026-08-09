# S3 / GI3.1 — browser Windows real

| Campo | Valor |
|-------|--------|
| Host | `192.168.100.24` Windows Server 2022 (10.0.20348) descartável |
| Browser | Microsoft Edge **151.0.4129.72** (binário Windows; headless=new) |
| URL / SNI | `https://blocked.test:8443/` |
| Path | Edge → `.54:8443` `layer7-tlsproxy` lab (listener temporário) |
| CA | efémera na `.54`; só `.crt` público na `.24` (Root store) |
| HTTP | **403** + HTML «Acesso bloqueado» |
| Peer cert | `CN=blocked.test` emitido por `CN=Layer7-S3-Ephemeral-CA` |
| Screenshot | `02-edge-block-page.png` |
| curl como substituto | **não** |
| Produção `.254/.234/.235` | **não tocada** |

## Veredicto S3

**PASS** — browser Windows real + CA no trust store + página HTTPS legível.
