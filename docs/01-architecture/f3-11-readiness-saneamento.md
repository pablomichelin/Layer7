# F3.11 — Saneamento Minimo da Readiness

## Finalidade

Este documento regista a rodada de saneamento minimo executada **depois** do
bloqueio formal inicial da F3.11.

Objectivo desta rodada:

- transformar pendencias da readiness em itens **comprovados**,
  **parcialmente comprovados** ou **bloqueados**;
- recolher apenas evidencia real do ambiente observado;
- nao abrir a campanha F3.11 enquanto existir qualquer pre-requisito critico
  sem prova suficiente;
- manter intactos o gate da F3 e a ordem sequencial oficial da campanha.

**Conclusao formal desta rodada:** `readiness parcialmente saneada, mas ainda
bloqueada`.

Isto significa:

- houve progresso de evidencia em publicacao, remoto Git e politica HTTP
  exposta;
- nao houve prova suficiente para liberar shell/DB, schema live, credencial
  administrativa, appliance pfSense real nem inventario `LIC-A` a `LIC-F`;
- a F3.11 **nao** pode ser aberta nesta rodada.

**Nota de estado actual (`2026-04-14`):** esta conclusao permanece como
historico da rodada de saneamento. O checkpoint posterior alinhou o
license-server live, auth/admin, same-origin e inventario; o blocker corrente
da F3 e apenas `DR-05` no appliance.

---

## 1. Ambiente validado e base factual

- **Data UTC da rodada:** `2026-04-02T23:37:26Z`
- **HEAD local no inicio da rodada:** `1834b4833c780b82693f97cd3a986531655c40f3`
- **`origin/main` confirmado remotamente:** `66e00f5a36e78056aae27df6aea0ccbd0ed78553`
- **Estado do branch local:** `main...origin/main [ahead 18]`
- **Backend publico observado:** `https://license.systemup.inf.br`
- **Origin observado:** `http://192.168.100.244:8445`

Leitura objectiva:

- o repositório local continua **18 commits** a frente de `origin/main`;
- a publicacao do estado local em `origin/main` **nao** esta confirmada;
- o ambiente live observado por HTTP continua acessivel, mas a revisao Git
  exacta em producao **nao** foi provada;
- o caminho canónico documentado para a stack continua a ser
  `/opt/layer7-license` com `license-server/docker-compose.yml`, mas isso
  continua **nao confirmado no host live** por falta de acesso legitimado.

---

## 2. Acessos obtidos e limites desta rodada

### 2.1 Acessos realmente obtidos

- acesso HTTP ao backend publico;
- acesso HTTP ao origin `192.168.100.244:8445`;
- acesso ao remoto Git suficiente para confirmar o hash actual de
  `origin/main`;
- acesso ao repositório local e aos artefactos canónicos da F2/F3.

### 2.2 Acessos nao obtidos

- shell read-only ao host `192.168.100.244`;
- acesso objectivo ao PostgreSQL live;
- credencial administrativa autorizada para login real;
- appliance pfSense autenticavel por SSH;
- inventario real de licencas `LIC-A` a `LIC-F`.

### 2.3 Limite operativo assumido nesta rodada

Nao foi usado nenhum segredo legado do repositório como substituto de
autorizacao operacional formal. Sem autorizacao objectiva para shell/DB/admin,
esta rodada limitou-se a recolha segura de evidencia externa e confronto com o
contrato canónico.

---

## 3. Comandos executados e resultados relevantes

```bash
git ls-remote origin refs/heads/main
git rev-parse HEAD
git rev-parse origin/main
git log --oneline origin/main..HEAD | sed -n '1,25p'
git status --short --branch

curl -k -I -sS --max-time 15 https://license.systemup.inf.br
curl -k -si --max-time 15 https://license.systemup.inf.br/api/health
curl -si --max-time 15 http://192.168.100.244:8445/api/health
curl -k -si --max-time 15 -X POST https://license.systemup.inf.br/api/auth/login -H 'Content-Type: application/json' -d '{}'
curl -k -si --max-time 15 -X POST https://license.systemup.inf.br/api/activate -H 'Content-Type: application/json' -d '{}'
curl -k -si --max-time 15 -X POST https://license.systemup.inf.br/api/auth/login -H 'Origin: https://evil.example' -H 'Content-Type: application/json' -d '{"email":"nobody@example.com","password":"invalid"}'
curl -k -si --max-time 15 -X OPTIONS https://license.systemup.inf.br/api/auth/login -H 'Origin: https://evil.example' -H 'Access-Control-Request-Method: POST'

ssh -o BatchMode=yes -o ConnectTimeout=10 root@192.168.100.244 'echo ok'
env | rg '^(ADMIN_|LICENSE_|PF_SSH|COOKIE_JAR|L7_BASE_URL|L7_SERVER_DIR)='
rg -n "<PFSENSE_IP>|LICENSE_ID='<LICENSE_ID>'|LICENSE_KEY='<LICENSE_KEY_32_HEX>'|ALT_CUSTOMER_ID='<OUTRO_CUSTOMER_ID>'" docs scripts -S
find docs/08-lab -maxdepth 1 -type f \( -name 'lab-inventory.md' -o -name 'lab-inventory.local.md' \) -print
find . -type f \( -name '*f3*report*.md' -o -name '*preflight*' -o -name '*campaign-manifest*' \) | sort
rg -n "admin_sessions|admin_audit_log|admin_login_guards|bootstrap-admin.js|/opt/layer7-license|docker-compose.yml" license-server/backend/migrations license-server/backend/src docs/05-runbooks/license-server-segredos-bootstrap.md docs/05-runbooks/license-server-publicacao-segura.md docs/01-architecture/f3-validacao-manual-evidencias.md -S
```

Resultados objectivos:

- `git ls-remote origin refs/heads/main` confirmou `origin/main =
  66e00f5a36e78056aae27df6aea0ccbd0ed78553`;
- `git rev-parse HEAD` confirmou `HEAD =
  1834b4833c780b82693f97cd3a986531655c40f3`;
- `git status --short --branch` confirmou `ahead 18`;
- `GET /api/health` respondeu `200` tanto no canal publico como no origin;
- `POST /api/auth/login` com payload vazio respondeu `400`;
- `POST /api/activate` com payload vazio respondeu `400`;
- `POST /api/auth/login` com `Origin: https://evil.example` respondeu `401`
  com `Access-Control-Allow-Origin: *`;
- `OPTIONS /api/auth/login` com `Origin: https://evil.example` respondeu `204`
  com `Access-Control-Allow-Origin: *` e
  `Access-Control-Allow-Methods: GET,HEAD,PUT,PATCH,POST,DELETE`;
- `ssh root@192.168.100.244` em `BatchMode=yes` continuou a falhar com
  `Permission denied (publickey,password)`;
- nao havia `ADMIN_*`, `LICENSE_*`, `PF_SSH`, `COOKIE_JAR`, `L7_BASE_URL` ou
  `L7_SERVER_DIR` definidos na sessao;
- nao existe inventario de lab preenchido em `docs/08-lab/`;
- nao existe artefacto versionado de preflight real com `LIC-A` a `LIC-F`;
- o repositório canónico continua a esperar `admin_sessions`,
  `admin_audit_log` e `admin_login_guards`, alem de operar
  `bootstrap-admin.js` a partir de `/opt/layer7-license`.

---

## 4. O que foi comprovado nesta rodada

### 4.1 Deploy observado e estado Git

| Item | Status | Evidencia real | Leitura objectiva |
|------|--------|----------------|-------------------|
| Backend publico acessivel | `OK` | `GET https://license.systemup.inf.br` e `/api/health` responderam `200` | o canal publico esta vivo |
| Origin observado acessivel | `OK` | `GET http://192.168.100.244:8445/api/health` respondeu `200` | o origin observado esta vivo |
| `origin/main` confirmado remotamente | `OK` | `git ls-remote origin refs/heads/main` | o remoto Git esta em `66e00f5...` |
| Branch local ainda nao publicado | `OK` | `git status --short --branch` + `git log origin/main..HEAD` | o estado local continua `ahead 18`; nao ha prova de push |
| Revisao exacta em producao | `BLOQUEADO` | sem shell/DB access ao host | nao foi possivel ligar o live a um commit Git exacto |
| Directorio real do servico live | `PARCIAL` | `/opt/layer7-license` existe como caminho canónico nos runbooks | o caminho esperado e conhecido, mas nao foi confirmado no host live |
| Compose/stack efectivamente activa no host | `BLOQUEADO` | sem shell read-only | nao houve prova de `docker compose ps/config` no live |

### 4.2 Schema e contrato canónico esperado

| Item | Status | Evidencia real | Leitura objectiva |
|------|--------|----------------|-------------------|
| Tabelas canónicas esperadas pelo repo | `OK` | `license-server/backend/migrations/001-init.sql` e runtime em `session.js` / `admin-surface.js` | o contrato canónico continua a exigir `admin_sessions`, `admin_audit_log` e `admin_login_guards` |
| Presenca/ausencia real das tabelas no live | `BLOQUEADO` | sem acesso a DB live | a ausencia/presenca real continua sem prova nova nesta rodada |
| Migrations correspondentes no repo | `OK` | `001-init.sql` define as tres estruturas | o repositorio tem baseline para o schema canónico |

### 4.3 Autenticacao/admin e politica HTTP observada

| Item | Status | Evidencia real | Leitura objectiva |
|------|--------|----------------|-------------------|
| Endpoint de login responde | `OK` | `POST /api/auth/login` respondeu `400` e `401` em testes controlados | a superficie de auth esta activa |
| Credencial administrativa autorizada e testavel | `BLOQUEADO` | nenhum `ADMIN_*` fornecido; nenhum escopo formal disponibilizado | nao houve login administrativo real |
| Politica same-origin canónica mantida no live | `BLOQUEADO / DRIFT` | `POST` e `OPTIONS` em `/api/auth/login` com `Origin: https://evil.example` responderam com `Access-Control-Allow-Origin: *`, e nao `403` fail-closed | o live observado diverge do contrato canónico de same-origin only da F2.3 |

### 4.4 Appliance/lab e inventario de licencas

| Item | Status | Evidencia real | Leitura objectiva |
|------|--------|----------------|-------------------|
| Appliance pfSense autenticavel por SSH | `BLOQUEADO` | sem `PF_SSH`; sem IP real; sem inventario local preenchido | nao existe appliance elegivel comprovado nesta rodada |
| Baseline, snapshot, relogio, offline e drift de NIC/UUID | `BLOQUEADO` | sem appliance autenticavel | S08, S09, S11, S12 e S13 continuam inviaveis |
| Inventario real `LIC-A` a `LIC-F` | `BLOQUEADO` | apenas placeholders no repo; sem preflight real versionado | nao existe pool auditavel para a campanha |

---

## 5. Lacunas persistentes e leitura formal

### 5.1 Bloqueios que continuam objectivos

1. **Revisao/schema live continuam sem prova directa**
   - Sem shell read-only e sem query ao PostgreSQL nao ha como confirmar o
     commit, o directorio real, a stack activa nem o schema live.
2. **Autenticacao administrativa continua indisponivel para teste real**
   - O endpoint existe, mas nenhuma credencial autorizada e nenhum escopo
     formal foram disponibilizados.
3. **Appliance pfSense real continua inexistente do ponto de vista
   verificavel**
   - Sem SSH, baseline, snapshot legitimo e control plane mutavel
     efectivamente observado, os cenarios locais permanecem bloqueados.
4. **Inventario `LIC-A` a `LIC-F` continua inexistente**
   - Sem mapeamento real `licenca -> cliente -> appliance -> cenario`, a
     campanha continua dependente de improviso, o que e proibido.

### 5.2 Drift adicional observado nesta rodada

Foi observada divergencia adicional entre o contrato canónico da F2.3 e o
live exposto:

- **Superficie:** `/api/auth/login`
- **Cenario:** `Origin` externo (`https://evil.example`)
- **Resposta observada:** `401` para credenciais invalidas e `204` no
  preflight, ambos com `Access-Control-Allow-Origin: *`
- **Resposta canónica esperada:** politica `same-origin only`, sem `*`, com
  fail-closed para origin administrativo fora da allowlist
- **Impacto na F3:** reforca que o deploy observado nao pode ser tratado como
  equivalente ao contrato documental da F2/F3 sem nova verificacao interna no
  host e no backend live

Este drift foi **documentado**, nao corrigido.

---

## 6. Tabela consolidada de pre-requisitos da readiness

| Pre-requisito critico | Estado | Leitura final desta rodada |
|-----------------------|--------|----------------------------|
| Backend publico acessivel | `OK` | comprovado |
| Origin observado acessivel | `OK` | comprovado |
| `origin/main` confirmado remotamente | `OK` | comprovado |
| Push do estado local confirmado | `BLOQUEADO` | nao confirmado; branch continua `ahead 18` |
| Revisao exacta em producao | `BLOQUEADO` | sem shell/DB access |
| Directorio real do servico live | `PARCIAL` | caminho canónico conhecido; live nao confirmado |
| Compose/stack activa | `BLOQUEADO` | sem shell read-only |
| Schema live confirmado | `BLOQUEADO` | sem DB access |
| Credencial administrativa autorizada | `BLOQUEADO` | inexistente nesta rodada |
| Login admin real testado | `BLOQUEADO` | inexistente nesta rodada |
| Appliance pfSense autenticavel | `BLOQUEADO` | inexistente nesta rodada |
| Baseline/snapshot/relogio/offline/NIC/UUID | `BLOQUEADO` | inexistente nesta rodada |
| Inventario real `LIC-A` a `LIC-F` | `BLOQUEADO` | inexistente nesta rodada |
| Revalidacao `409` vs `403` com licenca/cenario reais | `BLOQUEADO` | impossivel sem inventario/admin/appliance |

---

## 7. Conclusao formal

- **Readiness:** `parcialmente saneada, mas ainda bloqueada`
- **F3.11:** `nao pode ser aberta`
- **F3:** `permanece aberta`

Motivo objectivo:

- esta rodada melhorou a rastreabilidade do estado real de publicacao e
  expôs drift adicional de politica HTTP/admin no live;
- ainda assim, os pre-requisitos criticos da F3.10 continuam sem prova
  suficiente para campanha real.

---

## 8. Saneamento minimo antes de nova tentativa

1. Disponibilizar shell read-only ao host `192.168.100.244` ou ambiente
   substituto elegivel, com queries objectivas ao PostgreSQL.
2. Confirmar o commit/revisao exacta em producao e a stack activa real.
3. Fornecer credencial administrativa autorizada e escopo formal de uso.
4. Materializar um appliance pfSense autenticavel com baseline e snapshot.
5. Materializar o inventario real `LIC-A` a `LIC-F`.
6. So depois repetir a readiness check e, em seguida, a revalidacao do
   contrato `409` vs `403` com licenca/cenario reais.

---

## 9. Impacto, risco, teste e rollback deste bloco

- **Objectivo:** reduzir lacunas de evidência da readiness da F3.11 sem abrir
  a campanha.
- **Impacto:** documental-operacional apenas; nenhum ficheiro de codigo foi
  alterado.
- **Risco:** baixo; nao houve mutacao em deploy, banco, licencas nem
  appliance.
- **Teste minimo:** comandos Git, HTTP, SSH e busca no repositório listados
  neste documento.
- **Rollback:** `git revert <commit-deste-bloco>`.
