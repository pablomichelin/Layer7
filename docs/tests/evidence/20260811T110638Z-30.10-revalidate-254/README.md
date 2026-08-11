# Evidência — revalidação 30.10 em `.254` (após 30.9 live)

**Run ID:** `20260811T110638Z`  
**Host:** `192.168.100.254` (produção Systemup)  
**Passo:** `30.10` revalidação pós-deploy license-server **30.9**  
**Veredicto:** **STOP / ROLLBACK** — e2e de campo **não** completo

## Objectivo

Revalidar o cliente `1.9.53` com emissão live de `content_subscription`
(30.9 no `license.systemup.inf.br`), com rollback imediato se regressão.

## Conteúdo

| Ficheiro | Conteúdo |
|----------|----------|
| `00-verdict.txt` | Veredicto consolidado |
| `01-baseline-state.txt` | Pré-teste (`1.9.47`; `license_key` redigido) |
| `02-postinstall.txt` | Pós-install `1.9.53` |
| `03-check-in.txt` … `05-check-subscription.txt` | Token OK |
| `06-update-with-token.txt` | Update com token — **FAIL** (302 autenticado) |
| `06c-fetch-diag.txt` | `fetch` vs `curl`/`curl -L` |
| `11-post-rollback.txt` | Rollback `1.9.47` |
| `12-heal-update-147.txt` / `13-fallback-after-heal.txt` | Fallback restored healthy |

Backup operacional no appliance (fora do git):
`/root/layer7-backup-30.10-revalidate-20260811T110638Z.tgz`

## Resultados (reais)

| Item | Resultado |
|------|----------|
| Backup / baseline | OK — `1.9.47`, monitor, licença válida, snapshot `ut1-2026-04-25` |
| Install `1.9.53` | POSTINSTALL_PASS |
| Check-in + `content_subscription` | **PASS** — 30.9 live confirma emissão; ficheiro `0600`; `--check-subscription=ok` |
| Update com token | **FAIL** — gate token OK; primary DNS fail (pré-existente); mirror GitHub `curl` autenticado **HTTP 302** (sem `-L`) |
| Update sem token / GUI helper | **não exercidos** (STOP após FAIL do update com token) |
| Rollback | **PASS** → `1.9.47` |
| Heal pós-rollback | update anónimo `1.9.47` RC=0; fallback `healthy` |
| `30.11` | **não iniciado** |

## Causa do STOP

`fetch_authed()` em `update-blacklists.sh` usa `curl` **sem** seguir redirects.
O mirror GitHub Releases devolve **302** → asset CDN; `curl -L` obtém 200.
Com primary `downloads.systemup.inf.br` sem DNS, o update autenticado fica
inoperante em campo apesar do token válido.

## Impacto / risco / rollback

- **Impacto:** janela curta em `1.9.53`; produção restaurada a `1.9.47`.
- **Risco mitigado:** deixar `1.9.53` bloquearia updates automáticos de BL.
- **Rollback executado:** `pkg add -f` de `1.9.47` + heal do fallback.

## Pré-requisito / próxima decisão

1. Corrigir fetch autenticado (seguir 302 / equivalente) num candidato `.pkg`
   **antes** de nova promoção em `.254`.
2. Idealmente restaurar primary CDN `downloads.systemup.inf.br`.
3. **Não** iniciar `30.11` até e2e de update com token PASS em campo.
