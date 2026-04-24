# F3 - Fecho Operacional Restante

## Finalidade

O que ainda falta para fechar a F3 com evidencia real.

Actualizado em `2026-04-14` com base no alinhamento do `license-server` live
e no inventario real ja obtido. Em `2026-04-24`, o `CORTEX.md` e o backlog
foram ressincronizados com a distincao publicado (`1.8.3`) vs `PORTVERSION`
de trabalho (`1.8.4`) e a politica de download administrativo do `.lic` passou
a ter modulo dedicado com regressao no backend (`license-download-policy.js`).

---

## 1. O que ja esta provado

### 1.1 Inventario real (obtido em 2026-04-03)

4 licencas reais no live, obtidas via `GET /api/licenses` com Bearer JWT:

| ID | Cliente | Status | Expiry | Cenario |
|----|---------|--------|--------|---------|
| 8 | Compasi | `active` | 2026-12-31 | activacao valida |
| 7 | Systemup | `active` | 2033-10-24 | longo prazo, mesmo hw que ID 5 |
| 6 | Lasalle Agro | `revoked` | 2026-04-30 | revogacao real |
| 5 | Lasalle | `active` (expirada por data) | 2026-03-31 | expiracao hibrida |

### 1.2 Auth no live

- login funciona com `pablo@systemup.inf.br`;
- Bearer bridge administrativa funciona para endpoints autenticados;
- `/api/auth/session` existe no live e devolve sessao stateful + bridge de
  compatibilidade;
- `Origin` externo em `/api/auth/login` volta a falhar fechado com `403
  Origem administrativa nao autorizada.`.

### 1.3 pfSense / appliance `192.168.100.254`

- IP, hostname, pfSense Plus 25.11.1 confirmados;
- SSH funcional no utilizador temporario `codex`;
- Layer7 instalado, daemon running;
- UUID/VM hints recolhidos;
- ficheiros principais presentes;
- baseline canónico exportado em
  `/tmp/layer7-f3-evidence/20260414T111500Z-appliance-baseline/S13` e
  `/tmp/layer7-f3-evidence/20260414T113500Z-appliance-runtime/S07`;
- baseline read-only adicional exportado em
  `${TMPDIR:-/tmp}/layer7-f3-evidence/20260414T123526Z-appliance254-permissions/S07`,
  confirmando usuario efectivo, permissoes de ficheiros e processo real do
  `layer7d`;
- baseline canónico adicional exportado em
  `/tmp/layer7-f3-evidence/20260414T000000Z-appliance254-continue/S07`, com
  `40-preflight-appliance.txt` actualizado pelo helper, hash local do `.lic`
  e confirmacao repetida do estado real sob o utilizador `codex`;
- appliance actualmente coerente com a licenca `ID 7 / Systemup /
  2033-10-24`, fingerprint
  `e31560f5bc9894e92b9007d3e2e897a374f3d0b493b803c929d54acf51f8f826`.

### 1.4 Host live `192.168.100.244`

- SSH confirmado;
- stack viva com 4 containers;
- directorio activo observado em `/opt/layer7-license`;
- directorio `/opt/license-server` permanece apenas como legado, fora da stack
  actual.

### 1.5 PostgreSQL live

- base `layer7_license`, user `layer7`;
- tabelas `licenses`, `activations_log`, `admin_sessions`,
  `admin_audit_log` e `admin_login_guards` presentes;
- drift administrativo da F2 anteriormente observado no live deixa de
  existir neste ambiente.

---

## 2. O que falta para fechar a F3

### 2.1 DR-02 — RESOLVIDO (2026-04-03)

Testes executados no live (`POST /api/activate`):

| Cenario | Licenca | Repo | Live | Logica |
|---------|---------|------|------|--------|
| hw diferente do binding | ID 8 (Compasi) | 409 | 403 | correcta (rejeita) |
| licenca revogada | ID 6 (Lasalle Agro) | 409 | 403 | correcta (rejeita) |
| licenca expirada | ID 5 (Lasalle) | 409 | 403 | correcta (rejeita) |
| reactivacao legitima | ID 7 (Systemup) | 200 | 200 | correcta (aceita) |

**Veredicto:** drift cosmetico de codigo HTTP (`403` vs `409`) observado no
live anterior. A logica de negocio esta correcta em todos os cenarios. No
branch actual, a politica de rejeicao do `POST /api/activate` fica isolada
em helper testavel e coberta por regressao para `409` em licenca revogada,
licenca expirada e hardware divergente. O que resta aqui e alinhamento do
deploy vivo quando houver publicacao, nao blocker da F3.

### 2.2 Cenarios locais do appliance (DR-05) — PENDENTE

Alvo: appliance `192.168.100.254` (pfSense Plus, Layer7 activo). Conectividade
ICMP e SSH confirmadas. Em `2026-04-14`, o utilizador temporario `codex`
passou a dar acesso shell e permitiu exportar baseline real do appliance.
O bloqueio do DR-05 deixou de ser "entrar no host" e passou a ser
**executar cenarios mutaveis com permissao suficiente para reescrever
`/usr/local/etc/layer7.lic` e controlar o daemon sem ambiguidade**.

Hoje ja esta provado que:

- `pfSsh.php playback svc restart layer7d` funciona com `codex`;
- `layer7d --fingerprint` funciona;
- `kern.hostuuid` e stats JSON sao observaveis;
- o `.lic` actual e legivel, mas **nao e escrevivel** por `codex`.
- `codex` **nao** tem `sudo` nem `doas` no appliance;
- `pfSsh.php` existe e continua utilizavel por `codex`, mas a superficie
  observada ate agora fica limitada a playbacks predefinidos em
  `/etc/phpshellsessions` (ex.: `svc`), sem prova de via legitima para
  escrita arbitraria em `/usr/local/etc/layer7.lic`;
- uma sonda read-only adicional em `2026-04-14` voltou a listar os
  playbacks de `/etc/phpshellsessions`, a ajuda base do `pfSsh.php`, a
  disponibilidade de `php` CLI e o header de `/usr/local/pkg/layer7.inc`,
  sem revelar playback especifico do Layer7 nem outra via oficial mutavel
  fora da GUI autenticada do pacote;
- o pacote instalado expoe uma via legitima de mutacao pela GUI do pfSense:
  `layer7_settings.php` implementa `register_license` e `revoke_license`,
  chamando `layer7d --activate`, `unlink()` do `.lic` e restart do servico;
  a trilha usa os helpers locais `layer7_lic_path() ->
  /usr/local/etc/layer7.lic` e `layer7_restart_service() -> /usr/local/etc/rc.d/layer7d onestop/onestart`;
  no HTML observado, os formularios usam `POST layer7_settings.php#l7-sistema`
  com `license_code` + `register_license=1` para registar e
  `revoke_license=1` para revogar;
  essa via, porem, continua dependente de contexto autenticado da GUI e de
  permissao efectiva sobre os ficheiros/caminhos tocados, nao tendo ficado
  ainda disponivel ao `codex` por shell;
- a trilha GUI autenticada passa a ter tambem o helper canónico
  `scripts/license-validation/run-pfsense-gui-license-flow.sh`, capaz de
  materializar `probe`, `register` e `revoke` com captura de `headers`,
  `HTML`, `cookie jar` e notas no mesmo `run_id`, inclusive quando a GUI
  relevante so esta acessivel no loopback do appliance via
  `--ssh-target <utilizador@host>` + `--gui-base https://127.0.0.1:9999`;
- o helper canónico `export-appliance-evidence.sh` ja corre de ponta a ponta
  com `codex`, materializando `50-appliance-cli.txt`,
  `60-appliance-license.json`, `70-local-hashes.txt` e
  `40-preflight-appliance.txt` no `run_id`
  `20260414T000000Z-appliance254-continue`;
- o helper canónico `run-pfsense-gui-license-flow.sh` foi exercitado em
  modo `probe` via `--ssh-target codex@192.168.100.254` e
  `--gui-base https://127.0.0.1:9999` no `run_id`
  `20260414T000000Z-dr05-gui-probe-invalid`, com credencial
  deliberadamente invalida: o fluxo abriu a GUI, capturou `PHPSESSID`,
  extraiu `__csrf_magic`, tentou autenticar, leu `layer7_settings.php` e
  classificou correctamente o resultado como `BLOCKED /
  layer7_settings_not_authenticated`, preservando evidencias em
  `/tmp/layer7-f3-evidence/20260414T000000Z-dr05-gui-probe-invalid/S07`;
- o mesmo helper tambem foi validado com password da GUI fornecida por
  ficheiro local, sem expor o segredo na linha de comando, no `run_id`
  `20260414T000000Z-dr05-gui-probe-password-file-v2`; as notas registaram
  `gui_password_source=file`, `license_key_source=none` e o mesmo resultado
  esperado `BLOCKED / layer7_settings_not_authenticated`;
- `service layer7d status` via `codex` reporta falso negativo por falta de
  leitura do pidfile `0600 root:wheel`, mas `pgrep -fl layer7d` confirma o
  daemon vivo (`/usr/local/sbin/layer7d`) e o stats JSON confirma runtime
  activo.

Portanto, a metade read-only do DR-05 ja esta desbloqueada e parcialmente
executada; os cenarios que exigem nova activacao, limpeza do `.lic`, grace
ou troca de artefacto ainda pedem permissao de escrita/control plane no
appliance. O roteiro canónico activo passa a ficar consolidado em
[`f3-runbook-proxima-campanha-real.md`](f3-runbook-proxima-campanha-real.md)
(secao `8. Roteiro operacional do DR-05 no appliance`).

Em linguagem operacional: o blocker actual ja nao e "SSH ao host", mas sim
"via legitima para mutacao controlada do `.lic` e do daemon". Ate este
checkpoint, essa via **nao** apareceu por `sudo`, `doas` nem por playback
livre no `pfSsh.php`; a sonda adicional a `phpshellsessions`, `pfSsh.php`
e `php` CLI tambem nao revelou atalho oficial novo. A unica trilha legitima
mutavel observada localmente continua a ser a GUI autenticada do proprio
pacote, apoiada pelos helpers `layer7_lic_path()` e
`layer7_restart_service()`.

No codigo-fonte instalado da pagina, nao foi observada referencia explicita
a `__csrf_magic` nem a campo `csrf` proprio desta pagina; ainda assim, a
sessao/autenticacao efectiva da GUI do pfSense deve ser tratada como
obrigatoria e observada no fluxo real, nao inferida a partir do source file.
No pfSense instalado, a trilha de login da GUI foi observada com os campos
`usernamefld`, `passwordfld` e `login`, enquanto a proteccao CSRF activa usa
`__csrf_magic`; em sucesso, a auth regenera a sessao e marca
`$_SESSION['Logged_In']` / `$_SESSION['Username']`.
No appliance observado, `http://127.0.0.1/` redirecciona para
`https://127.0.0.1:9999/`, e a resposta inicial da GUI ja entrega
`PHPSESSID` e injecta `csrfMagicToken` / `__csrf_magic` no HTML de login.
Sem sessao autenticada, `layer7_settings.php` devolve a propria pagina de
login; e um `POST` com CSRF fora de sincronia devolve `HTTP 403` com
`CSRF Error`, reforcando que a trilha legitima deve reutilizar o
`PHPSESSID` e o `__csrf_magic` do mesmo fluxo real da GUI.
No fluxo observado com `curl`, o jar de cookies fica em formato Netscape e
preserva o `PHPSESSID` como cookie de sessao `HttpOnly`, o que e suficiente
para reproduzir o fluxo desde que o `__csrf_magic` seja colhido da mesma
sessao viva.

**Roteiro operacional unificado** (comandos completos, criterios `PASS`/`FAIL`
e evidencia minima): ver
[`f3-validacao-manual-evidencias.md`](f3-validacao-manual-evidencias.md)
(cenarios `S07` a `S09` onde aplicavel ao appliance, `S12` relogio/grace,
`S13` NIC/UUID/clone) e
[`f3-pack-operacional-validacao.md`](f3-pack-operacional-validacao.md)
(`run_id`, directoria de evidencias), em conjunto com
[`f3-runbook-proxima-campanha-real.md`](f3-runbook-proxima-campanha-real.md)
(secao `8. Roteiro operacional do DR-05 no appliance`, incluindo a trilha
GUI autenticada, a sequencia `curl` e o helper
`run-pfsense-gui-license-flow.sh`).

**Mapa rapido DR-05:**

| Tema | Cenario F3.6 | Notas |
|------|--------------|-------|
| offline/online, daemon vs backend | S07, S08, S09 | S07 ja tem baseline/export; S08-S09 ainda dependem de mutacao controlada |
| relogio / fim de grace | S12 | snapshot obrigatorio antes; rollback = restore |
| NIC / UUID / clone / restore | S13 | baseline ja exportado; cenarios mutaveis continuam pendentes |
| seguranca | — | snapshot/restore da VM como rede de seguranca, nao como "cenario" isolado |

**Apos executar:** actualizar drift registry (DR-05), este documento, scorecard,
execution master register, `f3-11-start-here`, `CORTEX.md` e o relatorio em
`docs/tests/templates/f3-validation-campaign-report.md`.

**Nota:** o prompt historico em `docs/07-prompts/` permanece apenas como
contexto preservado; o caminho activo da F3 deve seguir o runbook canónico
da campanha e as docs F3.6/F3.7/F3.8.

### 2.3 Relatorio final de campanha

Depois de 2.2:
- consolidar evidencias por `run_id`;
- preencher relatorio final;
- decidir binariamente: `F3 pode fechar` ou `F3 nao pode fechar`.

---

## 3. O que NAO falta para fechar a F3

Itens que o ChatGPT tratava como blockers da F3 mas que sao de outras fases
ou que ja ficaram alinhados no live:

- schema administrativo do live (tabelas `admin_sessions`, etc.) — **ja
  alinhado no live**; nao bloqueia a F3;
- CORS/same-origin — **ja alinhado no live**; nao bloqueia a F3;
- proveniencia exacta do deploy — F7/operacional;
- sessao stateful por cookie — **ja alinhada no live**; nao bloqueia a F3;
- `5/5 insumos entregue valido` com burocracia de intake/triagem/ciclo — overhead desnecessario.

---

## 4. Objectivo, impacto, risco, teste e rollback

- **Objectivo:** simplificar e actualizar o caminho real para fechar a F3.
- **Impacto:** documental.
- **Risco:** baixo.
- **Rollback:** `git revert <commit-deste-bloco>`.
