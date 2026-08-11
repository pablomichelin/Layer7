# Evidence: passo 30.7 — entitlements GUI assinados (BG-120)

- **RUNID:** `20260810T214800Z`
- **Passo:** `30.7` (trilha Anti-pirataria / Anti-tamper)
- **Builder:** `192.168.100.12` (FreeBSD 15)
- **Release candidata:** `pfSense-pkg-layer7-1.9.52.pkg`
- **SHA256 `.pkg`:** `79312d1b73eb8744be817c9ef2b9a7cdf768439632dabbad35e9fb7bfa607134`
- **Pubkey SoT:** inalterada (`8c52b677…1824`)
- **PEM licença (GUI):** `license-signing-public-key.pem` (mesmos bytes públicos)
- **Verify:** `openssl pkeyutl` (PHP `openssl_verify` Ed25519 inviável neste stack)

## Resultados

| Critério | Resultado |
|----------|-----------|
| GA2.8 | **PASS** — stats forjados / `.lic` sem sig / check-in sozinho **não** unlock |
| GA2.9 | **PASS** — `layer7-mitm-entitle-ok` + rc.d; sync_helper revalida em produção |
| GA2.10 | **PASS** — `test_entitlements_gui.php` + `test_mitm_config.php` + `test_mitm_regress.php` |
| T1 `full` | **PASS** — não concede mitm/identity |
| Check-in ∩ | **PASS** — só retira bits |
| Pubkey SoT | **PASS** — `verify-prod-pubkey.sh` |
| Runtime MITM | **inalterado** (R-I) — testes forçam `$entitled` sob `LAYER7_TEST_ROOT` |

## Script

`tests/functional/test_entitlements_gui.php` → `00-builder-tests.txt`

## Limites (R-A)

Root no appliance pode ainda contornar (patch rc.d / substituir PEM / etc.).
Fecha o bypass *trivial* A-07 (stats / gate escrito à mão).
