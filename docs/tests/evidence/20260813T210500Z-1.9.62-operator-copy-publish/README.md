# Evidência — publish `v1.9.62` (copy de operador MITM/Identity)

**Run ID:** `20260813T210500Z-1.9.62-operator-copy-publish`  
**Source commit (pkg):** `53689488003a6b11d50348baeb87b7ac7168da46`  
**Builder:** `192.168.100.12` (FreeBSD 15)

| Campo | Valor |
|-------|--------|
| Pacote | `pfSense-pkg-layer7-1.9.62.pkg` |
| SHA256 | `b6700576afb47cf9790c4c3fddb746b3021d7070e260ef0e6551c712a7948e5f` |
| Tamanho | 3314221 bytes |
| Fingerprint pubkey release | `d26e3f007e81298bad910f99dd62a22e2109740158b3b3c7f4e79490bdc5a998` |
| `verify-release.sh` | PASS |
| `test_gui_operator_copy.php` | PASS |

**Objectivo:** GUI MITM/Identity/check-in sem ADRs, gates `20.x`, paths de `docs/` nem checklist de lab. Daemon e licença intactos.

**Fora de escopo:** upgrade do soak P4 no `.254`; enforce `1.9.8`; MITM permanente.
