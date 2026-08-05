# Onda D — F4.2 (sec. 10b validacao-lab) — PASS

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T212200Z-ondaD-f4-10b-PASS` |
| Appliance | `192.168.100.254` |
| Pacote (lab) | `1.8.11_68` + hotfix `send_sighup` (corrige em `_69`) |

## Causa raiz (bug)

`daemon(8)` grava `/var/run/layer7d.pid` **sem newline final**. `read` preenche o PID
mas devolve rc=1 (EOF antes do delimitador). `update-blacklists.sh` tratava isso como
falha; `rc.d/layer7d` e `layer7-stats-collect.sh` já aceitavam o valor.

## Correcção

`package/pfSense-pkg-layer7/files/usr/local/etc/layer7/update-blacklists.sh` —
alinhado a `layer7d_pid_from_file()` em `rc.d/layer7d`.

## Resultado

| Item | Estado |
|------|--------|
| `update-blacklists.sh --apply` | **PASS** — `INFO: sent SIGHUP to daemon` |
| `fallback.state` `status=healthy` | OK |
| Critério 10b (SIGHUP válido) | **PASS** |

Evidência: `10b-output.txt`
