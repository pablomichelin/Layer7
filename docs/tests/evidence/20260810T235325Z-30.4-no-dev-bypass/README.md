# Evidence: passo 30.4 — remover bypass `is_dev_key` de produção (BG-114)

- **RUNID:** `20260810T235325Z`
- **Passo:** `30.4` (trilha Anti-pirataria / Anti-tamper)
- **Builder:** `192.168.100.12` (FreeBSD 15)
- **Release:** `pfSense-pkg-layer7-1.9.49.pkg` (publicado `pablomichelin/Layer7`)
- **SHA256 `.pkg`:** `f380ad493c5229fc08704673abf758edaa5e15ea05061820d04bb9abdca4d3cb`
- **SHA256 `layer7d`:** `76d7f44b351ce7ac512ca96b17c581627492d987521bdb5a5a9e22414f9b1789`
- **Pubkey SoT:** inalterada (GA1.8 preservado)

## Resultados

| Critério | Resultado |
|----------|-----------|
| GA2.1 | **PASS** — `is_dev_key` só sob `#ifdef L7_DEV_BUILD`; string/símbolo ausentes no `.pkg` |
| GA2.2 | **PASS** — pubkey all-zeros + build produção ⇒ `valid=0` / `dev_mode=0` / `rc=-1` |
| GA2.3 | **PASS** — port Makefile **sem** `-DL7_DEV_BUILD` activo |
| Controlo | `L7_DEV_BUILD`+zero ⇒ bypass activo (lab only) |
| `verify-prod-pubkey.sh` | PASS |
| `smoke-layer7d.sh` (builder) | PASS |
| `layer7d -V` / `-t` / `--fingerprint` | PASS (`1.9.49`) |
| Appliance `.254` | **não** corrido neste passo |

## Script

`scripts/package/test-prod-no-dev-bypass.sh`

## Nota

Publicado em `pablomichelin/Layer7` tag `v1.9.49` (pedido explícito pós-30.4).
