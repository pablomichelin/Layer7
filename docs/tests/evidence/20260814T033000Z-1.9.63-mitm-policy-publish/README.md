# Evidência — publish `v1.9.63` (MITM como política / 20.35)

**Run ID:** `20260814T033000Z-1.9.63-mitm-policy-publish`  
**Source commit (pkg):** `7dd821448c9c3f25ffa98bdcc20dbea63ade9e14`  
**Builder:** `192.168.100.12` (FreeBSD 15)

| Campo | Valor |
|-------|--------|
| Pacote | `pfSense-pkg-layer7-1.9.63.pkg` |
| SHA256 | `f47b1dd82e7d99f8a1f8e6bbd2fe101c0ed33688b45cfcfbb356367db853c373` |
| Tamanho | 3313866 bytes |
| Fingerprint pubkey release | `d26e3f007e81298bad910f99dd62a22e2109740158b3b3c7f4e79490bdc5a998` |
| `verify-release.sh` | **PASS** |

**Objectivo:** MITM opera na GUI como política («até desligar» + temporizada).
Ficha retirada (ADR-0035). Melhorar todos os dias, sem tecto.

**Fora de escopo:** upgrade do soak P4 no `.254`; enforce `1.9.8`; MITM permanente.
