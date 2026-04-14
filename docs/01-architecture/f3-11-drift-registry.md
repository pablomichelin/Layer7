# F3.11 - Drift Registry Consolidado

## Finalidade

Este documento consolida os drifts conhecidos da trilha **F3.9 -> F3.11**
sem executar correcoes em runtime, sem reabrir decisoes fechadas e sem
reatribuir blocker de ambiente como se fosse progresso tecnico.

Objectivo deste registry:

- manter uma lista unica e cumulativa dos drifts observados ate agora;
- distinguir drift de deploy, drift de contrato, drift de governanca e drift
  de publicacao/revisao;
- destacar a divergencia adicional de CORS/same-origin observada no live;
- deixar explicito se cada drift bloqueia ou nao a campanha F3.11.

**Regra central:** drift registado nesta fase vira item de governanca e de
seguimento. Nao vira correcao improvisada, nem motivo para relaxar o gate da
F3.

---

## 1. Matriz consolidada de drifts

| ID | Origem temporal | Categoria | Evidencia objectiva | Contrato canonico | Impacto | Bloqueia campanha F3.11 | Tratamento correcto nesta fase |
|----|-----------------|-----------|---------------------|-------------------|---------|-------------------------|--------------------------------|
| DR-01 | F3.9 | Schema live | observacao antiga do live sem `admin_sessions`, `admin_audit_log` e `admin_login_guards`; em `2026-04-14` o ambiente activo em `/opt/layer7-license` passou a expor as tres tabelas | F2.2/F2.3 exigem sessao stateful, auditoria e login guards | historico resolvido no ambiente live actual | nao | manter apenas como historico de drift ja saneado |
| DR-02 | F3.9 | Contrato HTTP de activacao | `POST /api/activate` respondeu `403` onde a F3.8 exige `409`; em `2026-04-03`, os cenarios de hw diferente, revogada, expirada e reactivacao legitima foram revalidados no live | F3.8 exige leitura binaria coerente do contrato de activacao | drift cosmetico de codigo HTTP; logica de negocio correcta | nao | alinhar `403` -> `409` em bloco futuro proprio, sem bloquear a F3 |
| DR-03 | F3.9-F3.11 | Auth/admin live | observacao antiga do live com JWT no body, mas sem `/api/auth/session`; em `2026-04-14`, o ambiente activo passou a responder `/api/auth/session` e a manter bridge Bearer compativel | F2.2/F2.3 exigem sessao stateful canónica por cookie no painel admin | historico resolvido no ambiente live actual | nao | manter apenas como historico de drift ja saneado |
| DR-04 | F3.9 | Inventario de licencas | ausencia antiga de pool minimo `LIC-A` a `LIC-F`; em `2026-04-03`, 4 licencas reais foram obtidas do backend live | F3.10 exige inventario minimo por cenario | resolvido por inventario real suficiente para a leitura actual da F3 | nao | manter apenas como historico de drift ja saneado |
| DR-05 | F3.9-F3.11 | Ambiente de appliance | appliance real `192.168.100.254` ja observado com Layer7 activo; baseline read-only `20260414T123526Z-appliance254-permissions` confirma `codex`, `.lic` legivel mas nao escrevivel, pidfile `0600 root:wheel`, processo `layer7d` vivo e stats JSON valido; em `2026-04-14`, o run canónico `20260414T000000Z-appliance254-continue` confirmou tambem que `export-appliance-evidence.sh` corre de ponta a ponta com `codex` e actualiza `40-preflight-appliance.txt`; no mesmo checkpoint, `codex` foi revalidado sem `sudo`, sem `doas` e sem prova de playback livre em `pfSsh.php` para escrita arbitraria no `.lic`; uma sonda adicional a `/etc/phpshellsessions`, `pfSsh.php`, `php` CLI e `/usr/local/pkg/layer7.inc` nao revelou playback Layer7 nem outra via oficial mutavel fora da GUI; a GUI instalada do pacote expõe `register_license` / `revoke_license` como via legitima mutavel, apoiada por `layer7_lic_path() -> /usr/local/etc/layer7.lic` e `layer7_restart_service()`, mas ainda sem contexto autenticado disponivel ao `codex` | F3.10 exige SSH, baseline e controlos legitimos | metade local da campanha continua incompleta para cenarios mutaveis: snapshot/restore, offline/online, grace/relogio, NIC/UUID e clone/restore | sim | completar agora os cenarios locais restantes com control plane legitimo observado, snapshot/rollback e evidencias por `run_id` |
| DR-06 | F3.11 saneamento | Politica CORS / same-origin | observacao antiga do live com `Access-Control-Allow-Origin: *`; em `2026-04-14`, `POST` e `OPTIONS` em `/api/auth/login` com `Origin: https://evil.example` passaram a responder `403 {\"error\":\"Origem administrativa nao autorizada.\"}` | F2.3 aceita apenas `same-origin only` em producao | historico resolvido no ambiente live actual | nao | manter apenas como historico de drift ja saneado |
| DR-07 | F3.11 saneamento | Publicacao / revisao | stack viva observada em `/opt/layer7-license`, mas o host actual nao fornece checkout Git, bind mount ou metadata util de commit; a revisao exacta do deploy continua nao demonstravel | nenhuma equivalencia live/local/remoto pode ser assumida sem prova | impede usar o repositorio local como prova suficiente do estado do live, mas nao bloqueia os cenarios de licenciamento do appliance | nao | manter aberto para F7/governanca operacional, sem bloquear a F3 |

---

## 2. Evidencia nova obtida em 2026-04-03

### Bearer JWT funciona no live

Em `2026-04-03`, foi executado login fresco seguido de `GET /api/licenses`
com `Authorization: Bearer <token>`. Resultado:

- `POST /api/auth/login` com credencial real => `200 OK` com JWT e objecto
  `admin`;
- `GET /api/licenses` com `Authorization: Bearer <jwt>` => `200 OK` com
  listagem real de 4 licencas.

Isto prova que:

- o live ja aceita Bearer JWT para endpoints autenticados;
- o erro anterior `401 Token invalido ou expirado` era apenas token expirado;
- a listagem real do inventario esta agora disponivel sem deploy de codigo
  novo.

### Inventario real obtido

4 licencas reais no live:

| ID | Cliente | Status | Expiry | Hardware bound? |
|----|---------|--------|--------|-----------------|
| 8 | Compasi | `active` | 2026-12-31 | sim |
| 7 | Systemup | `active` | 2033-10-24 | sim |
| 6 | Lasalle Agro | `revoked` | 2026-04-30 | sim |
| 5 | Lasalle | `active` (expirada por data) | 2026-03-31 | sim |

Observacoes:

- licencas 7 e 5 partilham o mesmo `hardware_id`;
- licenca 5 esta expirada por data (`expiry < hoje`) mas `status` continua
  `active`, confirmando o modelo hibrido da F3.3;
- licenca 6 esta revogada com `revoked_at` preenchido.

### License-server live alinhado em 2026-04-14

Em `2026-04-14`, o ambiente activo em `/opt/layer7-license` foi observado com:

- stack Docker viva com `api`, `db`, `web` e `nginx`;
- tabelas `admin_sessions`, `admin_audit_log` e `admin_login_guards`
  presentes na base `layer7_license`;
- `node bootstrap-admin.js status` a devolver `total_admins: 1`;
- `POST` e `OPTIONS` em `/api/auth/login` com `Origin` externo a responder
  `403` fail-closed.

Isto prova que o drift administrativo do live deixou de ser blocker real da
F3.

---

## 3. Reclassificacao dos drifts apos evidencia de 2026-04-03

| ID | Estado anterior | Estado actual | Razao da mudanca |
|----|-----------------|---------------|------------------|
| DR-01 | aberto, bloqueante | resolvido | ambiente activo agora expoe `admin_sessions`, `admin_audit_log` e `admin_login_guards` |
| DR-02 | aberto, bloqueante | resolvido como drift cosmético | revalidado em 2026-04-03: live usa `403` onde repo usa `409`, mas logica de negocio esta correcta (rejeita binding conflituante, revogada e expirada; aceita reactivacao legitima) |
| DR-03 | aberto, bloqueante | resolvido | `/api/auth/session` e a bridge Bearer estao alinhados no live actual |
| DR-04 | aberto, bloqueante | resolvido | inventario real de 4 licencas obtido |
| DR-05 | aberto, bloqueante | aberto, pendente | baseline read-only reforcada em 2026-04-14, helper canónico revalidado no run `20260414T000000Z-appliance254-continue` e control plane limitado explicitamente: sem `sudo`, sem `doas`, sem via observada de playback livre para escrita no `.lic`, sem playback Layer7 adicional em `pfSsh.php`/`phpshellsessions` e com a unica via legitima mutavel observada dependente da GUI autenticada do pacote; cenarios mutaveis continuam pendentes por falta de control plane legitimo observado disponivel ao operador |
| DR-06 | aberto, bloqueante | resolvido | live voltou a responder `403` fail-closed para `Origin` externo em `/api/auth/login` |
| DR-07 | aberto, bloqueante | aberto, nao bloqueante para F3 | proveniencia do deploy nao bloqueia validacao de licenciamento |

---

## 4. Detalhe historico do drift de CORS

### DR-06 - `/api/auth/login` aceita `Origin` externo com wildcard

Base factual:

- `POST /api/auth/login` com `Origin: https://evil.example` respondeu `401`
  com `Access-Control-Allow-Origin: *`;
- `OPTIONS /api/auth/login` com `Origin: https://evil.example` respondeu
  `204` com `Access-Control-Allow-Origin: *`.

Contrato canonico aplicavel:

- F2.3 fechou a superficie administrativa como `same-origin only` em
  producao;
- o browser administrativo deveria operar apenas em
  `https://license.systemup.inf.br`;
- wildcard `*` em superficie de auth/admin nao e compativel com essa leitura
  canonica.

Classificacao:

- **Categoria:** drift de runtime/config/publicacao da superficie
  administrativa;
- **Severidade:** critica;
- **Natureza:** divergencia observada no live, nao inferencia sobre codigo;
- **Tratamento nesta rodada:** apenas registo formal.

Impacto historico:

- reforca que o deploy observado nao pode ser tratado como equivalente ao
  contrato documental da F2/F3;
- impede leitura optimista de readiness administrativa;
- exige nova confirmacao interna do runtime/config do ambiente escolhido.

Bloqueio historico sobre a F3.11:

- **bloqueava** usar o live observado como ambiente elegivel sem nova prova e
  saneamento;
- depois do checkpoint de `2026-04-14`, deixa de bloquear a F3 porque o live
  voltou a responder `403` fail-closed para `Origin` externo;
- a secao permanece apenas para preservar a memoria factual do drift antigo.

---

## 5. Leitura consolidada de impacto pos-2026-04-03

### Drifts que bloqueiam a campanha F3

- `DR-05` cenarios locais mutaveis do appliance — pendentes de execucao com
  permissao suficiente para escrita/control plane.

### Drifts reclassificados como fora do escopo F3

- `DR-07` proveniencia do deploy — F7/operacional.

### Drifts resolvidos

- `DR-01` schema/admin live — alinhado no ambiente activo;
- `DR-02` contrato `409` vs `403` — revalidado: drift cosmetico, logica correcta;
- `DR-03` auth/admin live — sessao stateful + Bearer alinhados no ambiente activo;
- `DR-04` inventario — 4 licencas reais obtidas;
- `DR-06` CORS/same-origin — fail-closed no ambiente activo.

---

## 6. Dependencias futuras

1. executar cenarios locais mutaveis do appliance para fechar DR-05;
2. alinhar codigos HTTP do activate (`403` -> `409`) quando o live for
   actualizado;
3. resolver proveniencia do deploy quando oportuno.

---

## 7. Objectivo, impacto, risco, teste e rollback

- **Objectivo:** reclassificar drifts com base em evidencia real de
  2026-04-03.
- **Impacto:** documental; reclassifica blockers sem alterar codigo.
- **Risco:** baixo; baseado em evidencia objectiva.
- **Teste minimo:** coerencia com inventario real e escopo F3 vs F2.
- **Rollback:** `git revert <commit-deste-bloco>`.
