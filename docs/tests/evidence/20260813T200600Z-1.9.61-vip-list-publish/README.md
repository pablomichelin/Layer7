# Evidência — publish `v1.9.61` (Lista VIP texto + DHCP)

**Run ID:** `20260813T200600Z-1.9.61-vip-list-publish`  
**Source commit (pkg):** `b5753d60e1d6fbde4b2d56e9664014f26aeacf86`  
**Builder:** `192.168.100.12` (FreeBSD 15)

| Campo | Valor |
|-------|--------|
| Pacote | `pfSense-pkg-layer7-1.9.61.pkg` |
| SHA256 | `eda5a10e1a9ca597d3bf8051c0ee372840caddffa133abee5e8d9383a5dba426` |
| Tamanho | 3310170 bytes |
| Fingerprint pubkey release | `d26e3f007e81298bad910f99dd62a22e2109740158b3b3c7f4e79490bdc5a998` |
| `verify-release.sh` | PASS |

**Objectivo:** Lista VIP em texto simples (BG-124) e picker de reservas DHCP por interface (BG-125). Daemon, `vip-isentos` e limites 32+16 intactos.

**Fora de escopo:** upgrade do soak P4 no `.254` (permanece `1.9.59` até fecho da janela); enforce `1.9.8`; MITM permanente.
