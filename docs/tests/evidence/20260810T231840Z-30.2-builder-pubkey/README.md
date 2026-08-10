# Evidence: passo 30.2 — reconciliação builder ↔ repo (pubkey fora do git)

- **RUNID:** `20260810T231840Z`
- **Passo:** `30.2` (trilha Anti-pirataria / Anti-tamper)
- **Builder:** `192.168.100.12` (FreeBSD 15)
- **HEAD após pull (fluxo novo, sem stash):** `b2783bb`
- **Decisão 4:** pubkey de produção **fora do git** — SoT em `/root/layer7-build-secrets/`
- **Backup:** `/root/layer7-build-secrets/backup/20260810T231840Z/` (`license.c`, `Makefile`, `.hex`, `.inc`)
- **Diff WT↔HEAD** `license.c` / `Makefile`: **vazio** (já alinhados)
- **Stashes:** preservados (não drop)
- **Verify:** `scripts/package/verify-prod-pubkey.sh` → PASS (license.c == SoT == campo)
- **Pubkey hex:** `8c52b6772a64749e4a57b34ba16578a1b130960b1a8e88e6c1d86dbd99fd1824`
- **Build:** `DISABLE_LICENSES=yes make package` → `pfSense-pkg-layer7-1.9.48.pkg`
- **SHA256 .pkg (builder):** `0ab9154b98665f09a6c5d21a9e58e199d9773791c00bdae14174c789e3742e81`
- **SHA256 `layer7d` no .pkg:** `509a7dd748dbe3b4eb6a19d5e5b2a302e2b393633f939c0fb3267434aa6e67cc` — **idêntico** ao `1.9.48` publicado
- **Smoke:** `layer7d -V` / `-t` / `--fingerprint` PASS
- **Release/publish:** não feito (validação de fluxo apenas)
- **Código de produto:** nenhum (`license.c` não alterado neste passo)
