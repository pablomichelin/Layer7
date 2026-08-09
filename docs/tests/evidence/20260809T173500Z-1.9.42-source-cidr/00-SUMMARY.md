# Evidência — `1.9.42` MITM `source_cidr` hardening

**Data:** `2026-08-09T173500Z`  
**Pacote:** `1.9.42`  
**SHA256:** `6bd6ba374b398ec82cd43ea2246f16a3774f4377d3cac6411265472d3d3a4c4b`  
**Builder:** `192.168.100.12` (FreeBSD 15)  
**Lab topologia:** `192.168.100.54` (Ubuntu isolado)  
**PF parse RO:** `192.168.100.254` (`pfctl -nf` apenas; **sem** load)  
**Produção `.254/.234/.235`:** **não escrita**

## Resultado

| Gate | Estado |
|------|--------|
| `test_mitm_config.php` (builder + `.54`) | **PASS** |
| `test_flush_coverage.sh` | **PASS** |
| Snippet `from <layer7_mitm_src> to <layer7_mitm_dst>` | **PASS** |
| `from any` ausente | **PASS** |
| Negativos: empty / source-only / dest-only / IPv6 / any | **PASS** |
| Topologia `.24/32` elegível; `.100` não | **PASS** |
| `pfctl -nf` RO no `.254` | **PASS** (EXIT 0; tabelas mitm não carregadas) |
| Build `.pkg` 1.9.42 + conteúdo | **PASS** |
| Auditoria adversária | **PASS** (`01-ADVERSARIAL.md`) |
| Activação produção `.254` | **não** (parar antes da 1ª escrita) |

## Rollback

- Pacote: `1.9.41`
- Config: `source_cidr`/`dest_cidr` vazios ⇒ zero rdr; `mitm.enabled=false`
