# Evidence: passo 30.3 — baseline de auditoria do artefacto (AP0)

- **RUNID:** `20260810T234552Z`
- **Passo:** `30.3` (trilha Anti-pirataria / Anti-tamper)
- **Artefacto:** `pfSense-pkg-layer7-1.9.48.pkg` (publicado `pablomichelin/Layer7`)
- **Código de produto:** nenhum (`license.c` intocado)

## Resultados

| Item | Resultado |
|------|-----------|
| SHA256 `.pkg` | `78fb0cfd151d2d32c19d8892ed176df8992f9c265a0d88fdfd005a624eab84eb` (bate com release) |
| SHA256 `layer7d` | `509a7dd748dbe3b4eb6a19d5e5b2a302e2b393633f939c0fb3267434aa6e67cc` (igual 30.2) |
| Símbolos | **stripped** — `is_dev_key` ABSENT em `nm` |
| String bypass (`development key`) | **ABSENT** no binário (provável DCE com pubkey const ≠ 0) |
| Pubkey produção | **FOUND** (`8c52b677…1824`) |
| Campo `license_dev_mode` | FOUND (status JSON; **não** é o bypass) |
| Residual fonte A-01 | **PRESENT** em `src/layer7d/license.c` (sem `L7_DEV_BUILD`) — **30.4** |
| GA1.7 selftest / dirty fixture | **PASS** — `--gate` exit 1 quando marcadores presentes |
| GA1.7 sobre `1.9.48` | exit 0 — sem marcadores binários (estado real; sem falso PASS de “limpo no fonte”) |

## RR-3 / decisão 8

Inventário de tags publicadas em `04-releases-inventory.txt`. Amostra
`1.9.48` / `1.9.47` / `1.9.8` / `1.8.11_24` em `05-sample-old-releases.txt`.
**Não** despublicar neste passo; execução conforme decisão 8 em `30.19`.

## Script

`scripts/package/audit-release-dev-bypass.sh`

```sh
sh scripts/package/audit-release-dev-bypass.sh --selftest
sh scripts/package/audit-release-dev-bypass.sh --inventory path/to.pkg
sh scripts/package/audit-release-dev-bypass.sh --gate path/to.pkg
sh scripts/package/audit-release-dev-bypass.sh --check-source
```

## Ficheiros

| Ficheiro | Conteúdo |
|----------|----------|
| `01-pkg-1.9.48-sha256.txt` | SHA256 publicado |
| `02-inventory-1.9.48.txt` | Inventário GA1.6 |
| `02b-gate-1.9.48.txt` | `--gate` no latest |
| `02c-source-residual.txt` | Residual fonte (exit 1 até 30.4) |
| `03-gate-selftest.txt` | Selftest GA1.7 |
| `03b-gate-dirty-fixture.txt` | Fixture suja → FAIL |
| `04-releases-inventory.txt` | Tags GitHub (RR-3) |
| `05-sample-old-releases.txt` | Amostra versões antigas |
| `11-VERDICT.txt` | Veredicto |
