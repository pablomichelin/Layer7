# Onda C — S14 check-in remoto (BG-077) — PASS

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T210500Z-ondaC-s14-checkin-PASS` |
| Appliance | `192.168.100.254` |
| Pacote | `1.8.11_68` |
| License server | `192.168.100.244` / `license.systemup.inf.br` |
| Cenário | **S14** — revogação remota via `POST /api/license/check-in` |

## Procedimento

1. Criada licença lab `id=11` (cliente Contacenter) no PostgreSQL.
2. `layer7d --activate <key>` — `.lic` gravado; chave em `/var/db/layer7-checkin.json`.
3. `UPDATE licenses SET status='revoked'` para `id=11`.
4. `layer7d --check-in` → `check-in denied — Licenca revogada.`
5. Pós-condição: `valid=0`, `/usr/local/etc/layer7.lic` ausente.
6. Restauro: reactivação licença Systemup (`id=9`).

## Veredicto

**PASS** — revogação no servidor reflecte-se no appliance no check-in imediato.

## Artefactos

- `S14-check-in-transcript.txt` — saída CLI
- `pfSense-pkg-layer7-1.8.11_68.pkg` + `.sha256` — build candidato
