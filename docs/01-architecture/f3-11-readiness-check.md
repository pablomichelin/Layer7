# F3.11 — Readiness Check e Bloqueio Formal

## Finalidade

Este documento regista a verificacao objectiva de readiness da **F3.11**
contra a matriz canónica da F3.10.

Objectivo deste bloco:

- validar item por item se a F3.11 pode ou nao abrir como campanha real;
- usar apenas evidencias reais do ambiente observado nesta rodada;
- interromper antes da campanha quando faltar qualquer pre-requisito
  obrigatorio;
- manter o gate da F3 intacto.

**Resultado formal desta rodada:** `RESULTADO A — F3.11 BLOQUEADA POR
PRE-REQUISITOS`.

**Nota de estado actual (`2026-04-14`):** este documento e historico da
verificacao de `2026-04-02`. O checkpoint posterior alinhou o license-server
live, auth/admin, same-origin e inventario; o blocker corrente da F3 e
apenas `DR-05` no appliance.

---

## 1. Base factual usada nesta verificacao

- **Data UTC da verificacao:** `2026-04-02T20:03Z`
- **Commit local de referencia do repo:** `a9bb2db`
- **Estado do branch local:** `main...origin/main [ahead 17]`
- **Backend publico observado:** `https://license.systemup.inf.br`
- **Origin observado:** `http://192.168.100.244:8445`

Comandos reais usados nesta rodada:

```bash
curl -k -I -sS --max-time 15 https://license.systemup.inf.br
curl -k -si --max-time 15 https://license.systemup.inf.br/api/health
curl -si --max-time 15 http://192.168.100.244:8445/api/health
curl -k -si --max-time 15 -X POST https://license.systemup.inf.br/api/auth/login -H 'Content-Type: application/json' -d '{}'
curl -k -si --max-time 15 -X POST https://license.systemup.inf.br/api/activate -H 'Content-Type: application/json' -d '{}'
ssh -o BatchMode=yes -o ConnectTimeout=10 root@192.168.100.244 'echo ok'
env | rg '^(ADMIN_|LICENSE_|PF_SSH|COOKIE_JAR|L7_BASE_URL|L7_SERVER_DIR)='
rg -n "<PFSENSE_IP>|LICENSE_ID='<LICENSE_ID>'|LICENSE_KEY='<LICENSE_KEY_32_HEX>'|ALT_CUSTOMER_ID='<OUTRO_CUSTOMER_ID>'" docs scripts -S
find docs/08-lab -maxdepth 1 -type f \( -name 'lab-inventory.md' -o -name 'lab-inventory.local.md' \) -print
find . -type f \( -name '*f3*report*.md' -o -name '*preflight*' -o -name '*campaign-manifest*' \) | sort
```

Resumo objectivo das respostas:

- `https://license.systemup.inf.br` respondeu `HTTP/2 200`;
- `https://license.systemup.inf.br/api/health` respondeu `HTTP/2 200`;
- `http://192.168.100.244:8445/api/health` respondeu `HTTP/1.1 200 OK`;
- `POST /api/auth/login` sem credenciais respondeu `400` com
  `"Email e password obrigatorios"`;
- `POST /api/activate` sem payload respondeu `400` com
  `"key e hardware_id obrigatorios"`;
- `ssh root@192.168.100.244` em `BatchMode=yes` falhou com
  `Permission denied (publickey,password)`;
- nao havia variaveis de ambiente `ADMIN_*`, `LICENSE_*` ou `PF_SSH`
  definidas nesta sessao;
- a documentacao operacional continua a expor apenas placeholders para
  `LICENSE_ID`, `LICENSE_KEY`, `ALT_CUSTOMER_ID` e `<PFSENSE_IP>`;
- nao existe `docs/08-lab/lab-inventory.md` preenchido;
- nao existem artefactos versionados de preflight da F3.11; apenas o template
  `docs/tests/templates/f3-validation-campaign-report.md`.

---

## 2. Validacao item por item da F3.11

### 2.1 Preflight do runbook

| Item do runbook F3.10 | Estado | Evidencia real | Leitura objectiva |
|-----------------------|--------|----------------|-------------------|
| 1. Deploy escolhido com referencia de repo e revisao do ambiente observadas | `PARCIAL / BLOQUEADO` | backend publico e origin respondem `200`, mas o shell ao host `192.168.100.244` falhou e a revisao efectiva do deploy nao foi observada | ambiente acessivel por HTTP, mas sem prova da revisao real do deploy |
| 2. Schema coerente com o contrato canónico necessario | `BLOQUEADO` | sem shell/DB access ao deploy; nenhuma query objectiva ao PostgreSQL foi possivel nesta rodada | nao foi possivel confirmar `admin_sessions`, `admin_audit_log` e `admin_login_guards` |
| 3. Credencial administrativa autorizada e testada | `BLOQUEADO` | `POST /api/auth/login` responde, mas nao ha credencial autorizada nesta sessao; `env` vazio para `ADMIN_*` | o fluxo existe, mas a credencial obrigatoria nao foi disponibilizada nem testada |
| 4. Appliance pfSense autenticavel e com baseline recolhivel | `BLOQUEADO` | nao ha `PF_SSH` definido, a documentacao so contem `<PFSENSE_IP>`, e nao existe inventario de lab preenchido | nenhum appliance elegivel foi identificado nesta rodada |
| 5. Inventario minimo de licencas preparado por cenario | `BLOQUEADO` | placeholders para `LICENSE_ID`/`LICENSE_KEY`; nao existe `50-preflight-inventory.md` nem mapeamento real `LIC-A` a `LIC-F` | nao existe pool real de licencas auditavel para abrir a campanha |
| 6. Janela legitima para relogio, offline, revoke e drift de NIC/UUID | `BLOQUEADO` | sem appliance, sem snapshot e sem inventario de lab preenchido | os cenarios S08, S09, S11, S12 e S13 nao podem ser iniciados |

### 2.2 Matriz de pre-requisitos da F3.10

| Pre-requisito | Estado | Evidencia real | Impacto |
|---------------|--------|----------------|---------|
| HTTPS publico do backend | `VALIDADO` | `curl -I https://license.systemup.inf.br` e `/api/health` responderam `200` | prova de conectividade publica |
| Origin observado do deploy | `VALIDADO` | `curl http://192.168.100.244:8445/api/health` respondeu `200` | prova de observacao do origin |
| Acesso ao host/origin para evidencias persistidas | `PENDENTE` | `ssh -o BatchMode=yes root@192.168.100.244` falhou com `Permission denied` | sem acesso a shell/DB nao ha prova de schema nem de queries objectivas |
| Credencial administrativa autorizada | `PENDENTE` | endpoint de login respondeu, mas nao ha credencial real/autorizada no ambiente actual | S04, S05, S06 e S10 nao podem abrir |
| Prova de escopo autorizado para admin | `PENDENTE` | nenhum registo de escopo/autorizacao foi fornecido nesta rodada | campanha deve abortar antes da metade administrativa |
| Appliance com SSH funcional | `PENDENTE` | nenhum IP/host real de appliance; so placeholders documentais | metade local da campanha nao pode abrir |
| Baseline do appliance recolhivel | `PENDENTE` | sem appliance identificado e sem `PF_SSH` | S01, S02, S07, S08, S09, S11, S12 e S13 continuam bloqueados |
| Snapshot/rollback do appliance | `PENDENTE` | inexistencia de appliance/inventario preenchido | sem controlo legitimo de relogio/NIC/UUID |
| Inventario minimo `LIC-A` a `LIC-F` | `PENDENTE` | placeholders de licenca no repo e ausencia de ficheiro de preflight real | nao ha como provar S01-S13 em ordem oficial |

---

## 3. Leitura formal dos blockers desta rodada

Blockers da F3.10 que continuam **nao saneados**:

1. **DO-01 / schema:** continua sem prova de saneamento porque nao houve
   acesso a shell/DB do deploy observado.
2. **DO-02 / contrato HTTP:** continua sem prova de saneamento porque nao
   existe inventario/licenca real para repetir o controlo que distingue
   `409` de `403`.
3. **DO-03 / autenticacao-admin:** continua aberto; a credencial
   administrativa obrigatoria nao foi fornecida nem autorizada nesta rodada.
4. **DO-04 / inventario de licencas:** continua aberto; nao existe pool real
   `LIC-A` a `LIC-F`.
5. **DO-05 / ambiente de appliance:** continua aberto; nao existe appliance
   pfSense autenticavel e com baseline recolhivel.

Conclusao binaria:

- a F3.11 **nao** pode ser aberta;
- nenhum cenario da ordem oficial foi executado;
- a F3 permanece aberta.

---

## 4. Saneamento minimo recomendado antes de nova tentativa

1. Disponibilizar acesso read-only ao deploy observado ou ambiente alternativo
   elegivel, com provas de revisao e queries objectivas ao PostgreSQL.
2. Fornecer credencial administrativa autorizada para a campanha, com escopo
   registado explicitamente.
3. Materializar o inventario real `LIC-A` a `LIC-F` em artefacto de preflight.
4. Disponibilizar appliance pfSense autenticavel por SSH, com snapshot e
   controlo de relogio/offline/NIC/UUID.
5. So depois reexecutar o readiness check; apenas se todos os itens ficarem
   verdes a F3.11 pode comecar.

---

## 5. Objectivo, impacto, risco, teste e rollback deste bloco

- **Objectivo:** decidir com evidencia real se a F3.11 pode abrir.
- **Impacto:** documental-operacional apenas; nenhum ficheiro de codigo foi
  alterado.
- **Risco:** baixo; o bloco aborta antes de qualquer mutacao no produto.
- **Teste minimo:** comandos reais de conectividade, login, activate, SSH,
  inventario e placeholders executados nesta rodada.
- **Rollback:** `git revert <commit-deste-bloco>`; nenhum rollback de runtime
  e necessario porque nao houve campanha nem mudanca de codigo.
