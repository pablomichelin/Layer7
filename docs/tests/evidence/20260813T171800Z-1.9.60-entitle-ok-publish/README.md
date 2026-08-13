# Evidência — publish `v1.9.60` (entitle-ok PATH rc.d)

**Run ID:** `20260813T171800Z-1.9.60-entitle-ok-publish`  
**Source commit (pkg):** `83492d274fc69d9f693b30effe4befde908b1364`  
**Builder:** `192.168.100.12` (FreeBSD 15)

| Campo | Valor |
|-------|--------|
| Pacote | `pfSense-pkg-layer7-1.9.60.pkg` |
| SHA256 | `ec22d3b636adf73bbb6497c2bec05a6ae2c34984e0b92815bfb36dc8ff89329f` |
| Tamanho | 3302495 bytes |
| Fingerprint pubkey release | `d26e3f007e81298bad910f99dd62a22e2109740158b3b3c7f4e79490bdc5a998` |
| `verify-release.sh` | PASS |

**Objectivo:** `layer7-mitm-entitle-ok` encontra `/usr/local/bin/php` no PATH curto do `rc.d`. Entitlement **não** enfraquecido.

**Fora de escopo:** upgrade do soak P4 no `.254` (permanece `1.9.59` até fecho da janela); enforce `1.9.8`; MITM permanente.
