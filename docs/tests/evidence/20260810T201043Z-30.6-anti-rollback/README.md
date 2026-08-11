# Evidence: passo 30.6 — anti-rollback de relógio (BG-116)

- **RUNID:** `20260810T201043Z`
- **Passo:** `30.6` (trilha Anti-pirataria / Anti-tamper)
- **Builder:** `192.168.100.12` (FreeBSD 15)
- **Release candidata:** `pfSense-pkg-layer7-1.9.51.pkg`
- **SHA256 `.pkg`:** `aec3642824df0fd8b3a49d9cc41b4b8a30e8c88dd5be6d6da7e142965b722204`
- **SHA256 `layer7d`:** `7494d449376cf7218fcaa7dc382b1101ccac4b4d6d9e744daa3e0f0e41991888`
- **Pubkey SoT:** inalterada
- **Estado:** `/var/db/layer7/clock-mark.json`
- **Limiar:** `L7_CLOCK_SUSPECT_SEC=86400` (1 dia)

## Resultados

| Critério | Resultado |
|----------|-----------|
| GA3.1 | **PASS** — marca persistente (`clock-mark.json`); unitário + path canónico |
| GA3.2 | **PASS** — avanço normal actualiza marca (`test_license_clock`) |
| GA3.3 | **PASS** — retrocesso ≤1d tolerado sem suspeito |
| GA3.4 | **PASS** (lógica) — retrocesso >1d ⇒ `clock_suspect` + `valid=0` + `L7_AUDIT_NOTE` no boot/recheck |
| GA3.5 | **PASS** — sem crash/exit por estado temporal |
| GA3.6 | **PASS** (doc) — runbook N6 `docs/13-runbooks/anti-rollback-relogio.md` |
| GA3.7 | **DEFERRED lab** — N1 no appliance com `.lic` válido não corrido neste passo |
| GA3.8 | **PASS** (doc) — `.pkg` anterior ignora o ficheiro (N7) |
| GA3.9 | **PASS** — RR-4 declarado no ADR-0033 + runbook |
| `layer7d -t` / `-V` | PASS (`1.9.51`) |
| Appliance `.254` | **não** corrido neste passo |

## Script

`tests/functional/test_license_clock.c` → `test_license_clock.out`

## Limites (RR-4)

Root pode apagar a marca; relógio congelado desde a instalação não é detectado.
Fecho real do vector: AP3.
