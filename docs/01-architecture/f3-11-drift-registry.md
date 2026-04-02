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
| DR-01 | F3.9 | Schema live | ausencia observada de `admin_sessions`, `admin_audit_log` e `admin_login_guards` no ambiente live observado | F2.2/F2.3 exigem sessao stateful, auditoria e login guards | torna metade administrativa e a trilha de sessao/auditoria nao confiaveis | sim | confirmar por query live e alinhar ambiente antes da campanha |
| DR-02 | F3.9 | Contrato HTTP de activacao | `POST /api/activate` respondeu `403` onde a F3.8 exige `409` em cenario obrigatorio | F3.8 exige leitura binaria coerente do contrato de activacao | contamina S03 e parte da leitura online de S07 | sim | repetir o controlo em cenario real apenas depois de deploy e inventario estarem comprovados |
| DR-03 | F3.9 | Governanca administrativa | campanha real sem credencial administrativa autorizada | F3.10 exige admin autorizado para S04/S05/S06/S10 | metade administrativa fica `BLOCKED` por governanca | sim | obter credencial com escopo formal antes de qualquer nova rodada |
| DR-04 | F3.9 | Inventario de licencas | ausencia de pool minimo `LIC-A` a `LIC-F` em estado dedicado por cenario | F3.10 exige inventario minimo por cenario | campanha fica dependente de improviso e reuso arriscado | sim | materializar inventario real e provado no backend |
| DR-05 | F3.9 | Ambiente de appliance | ausencia de appliance pfSense autenticavel, com baseline e controlos do lab | F3.10 exige SSH, baseline e controlos legitimos | metade local da campanha fica bloqueada | sim | disponibilizar appliance e controlos reais antes da campanha |
| DR-06 | F3.11 saneamento | Politica CORS / same-origin | `POST` e `OPTIONS` em `/api/auth/login` com `Origin: https://evil.example` responderam com `Access-Control-Allow-Origin: *` | F2.3 aceita apenas `same-origin only` em producao | mostra divergencia entre live observado e contrato de auth/admin | sim | registar drift; confirmar runtime/config/publicacao; corrigir apenas em bloco proprio e autorizado |
| DR-07 | F3.11 saneamento | Publicacao / revisao | `origin/main` remoto confirmado em `66e00f5...`; branch local entrou nesta rodada `ahead 19`; live sem commit provado | nenhuma equivalencia live/local/remoto pode ser assumida sem prova | impede usar o repositorio local como prova suficiente do estado do live | sim | obter shell/DB access ao host e provar revisao/stack efectiva antes da campanha |

---

## 2. Detalhe do drift adicional de CORS

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

Impacto:

- reforca que o deploy observado nao pode ser tratado como equivalente ao
  contrato documental da F2/F3;
- impede leitura optimista de readiness administrativa;
- exige nova confirmacao interna do runtime/config do ambiente escolhido.

Bloqueio sobre a F3.11:

- **bloqueia** usar o live observado como ambiente elegivel sem nova prova e
  saneamento;
- **nao autoriza** corrigir runtime/config/publicacao nesta rodada;
- a dependencia futura fica registada como
  `correcao de runtime/config/publicacao em bloco proprio, sem campanha em
  paralelo`.

---

## 3. Leitura consolidada de impacto

### Drifts que bloqueiam a campanha F3.11

- `DR-01` schema live nao comprovado/alinhado;
- `DR-02` contrato `409` vs `403` ainda sem revalidacao real;
- `DR-03` ausencia de credencial admin autorizada;
- `DR-04` ausencia de inventario `LIC-A` a `LIC-F`;
- `DR-05` ausencia de appliance/lab autenticavel;
- `DR-06` politica same-origin divergente no live observado;
- `DR-07` equivalencia entre live, remoto e branch local nao demonstrada.

### Drifts que nao podem ser "resolvidos" durante a campanha

- qualquer drift de schema;
- qualquer drift de runtime/config da superficie administrativa;
- qualquer drift de publicacao/revisao do deploy;
- qualquer falta de governanca de acesso.

Se qualquer um desses pontos persistir no inicio da proxima rodada, o
resultado correcto continua a ser:

- abortar antes da campanha; ou
- marcar cenario afectado como `BLOCKED`;

nunca reinterpretar `FAIL` como aceitavel.

---

## 4. Dependencias futuras registadas

Pendencias que ficam abertas para bloco futuro proprio, sem execucao nesta
rodada:

1. confirmar revisao exacta, directorio real e compose activa do ambiente
   observado;
2. confirmar schema live por query read-only;
3. obter credencial administrativa autorizada e escopo formal;
4. disponibilizar appliance pfSense com snapshot/restore e controlos
   legitimos;
5. materializar inventario `LIC-A` a `LIC-F`;
6. corrigir o drift `same-origin only` vs `Access-Control-Allow-Origin: *`
   apenas em bloco de runtime/config/publicacao autorizado.

---

## 5. Objectivo, impacto, risco, teste e rollback deste bloco

- **Objectivo:** consolidar os drifts acumulados da F3.9 ate ao estado actual
  da F3.11.
- **Impacto:** documental-operacional apenas; nenhum ficheiro de codigo foi
  alterado.
- **Risco:** baixo; o documento apenas classifica desvios ja observados.
- **Teste minimo:** coerencia cruzada com F3.10, readiness check e
  saneamento minimo.
- **Rollback:** `git revert <commit-deste-bloco>`.
