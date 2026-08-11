# Evidência — e2e 30.10 em `.254` com `1.9.54`

**Run ID:** `20260811T114320Z`  
**Host:** `192.168.100.254` (produção Systemup)  
**Passo:** `30.10` validação final pós-fix `fetch_authed`  
**Veredicto:** **PASS** — manter **`1.9.54`** em produção

## Objectivo

Validar ponta a ponta o cliente `1.9.54` (redirect HTTPS autenticado) com
token emitido pelo license-server 30.9 live.

## Conteúdo

| Ficheiro | Conteúdo |
|----------|----------|
| `00-verdict.txt` | Veredicto consolidado |
| `01-baseline-state.txt` | Pré-teste (`1.9.47`; `license_key` redigido) |
| `02-postinstall.txt` | Pós-install `1.9.54` |
| `03-check-in.txt` … `05-check-subscription.txt` | Token OK |
| `06-update-with-token.txt` | Update autenticado **PASS** (RC=0) |
| `06c-false-positive-rollback-note.txt` | Nota do STOP falso (grep histórico) |
| `07-update-without-token.txt` | Hold-active **PASS** |
| `08-gui-helper.txt` | GUI helper **PASS** (`status=ok`) |
| `09-final-state.txt` / `13-fallback-after-heal.txt` | Estado final + heal |

Backup operacional no appliance (fora do git):
`/root/layer7-backup-30.10-e2e-154-20260811T114320Z.tgz`

## Resultados (reais)

| Item | Resultado |
|------|----------|
| Backup / baseline | OK — `1.9.47`, monitor, licença válida, snapshot `ut1-2026-04-25` |
| Install `1.9.54` | POSTINSTALL_PASS (serviço/licença/mode/rede/snapshot + fix presente) |
| Check-in + token | **PASS** — ficheiro `0600`; struct OK; `--check-subscription=ok` |
| Update com token | **PASS** — primary CDN DNS fail (pré-existente); mirror GitHub via redirect HTTPS; `validated_at` 11:09:09Z→11:47:12Z; RC=0 |
| Update sem token | **PASS** — hold-active; snapshot intacto; mode/enforce intactos; token restaurado |
| GUI helper | **PASS** — `status=ok` |
| Heal pós no-token | **PASS** — fallback `healthy` |
| Produção final | **`1.9.54`** |
| `30.11` | **não iniciado** |

## Nota operacional

Durante a automação, um grep sobre o log acumulado casou linhas históricas de
HTTP 302 do teste `1.9.53` e provocou rollback momentâneo para `1.9.47`. O
update autenticado em `1.9.54` já tinha **PASS** (RC=0 + `update complete`).
O pacote foi reinstalado para `1.9.54` e os gates restantes concluídos.
