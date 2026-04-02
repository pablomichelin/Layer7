# Arquitectura da F3 — Licenciamento e Activacao

## Finalidade

Este documento abre formalmente a **F3 — Robustez de licenciamento/activacao**
e passa a ser a referencia canónica do contrato de licenciamento observado no
codigo actual.

Escopo desta abertura controlada:

- mapear o estado real do backend e do daemon;
- explicitar estados, transicoes e semantica minima do contrato;
- registar ambiguidades herdadas da F2;
- materializar apenas o primeiro endurecimento defensivo e compativel do fluxo
  de activacao, sem refactor amplo e sem mudar o modelo comercial.

Documento complementar canónico da mesma fase:

- [`f3-fingerprint-e-binding.md`](f3-fingerprint-e-binding.md) para a
  matriz real do fingerprint, binding ao hardware e politica conservadora da
  F3.2 em cenarios de appliance.
- [`f3-expiracao-revogacao-grace.md`](f3-expiracao-revogacao-grace.md) para a
  semantica real de expiracao, revogacao, validade offline e grace local
  fechada na F3.3.
- [`f3-mutacao-admin-reemissao-guardrails.md`](f3-mutacao-admin-reemissao-guardrails.md)
  para a superficie administrativa real, a politica conservadora de
  imutabilidade parcial apos bind e os guardrails minimos da F3.4.
- [`f3-emissao-reemissao-rastreabilidade.md`](f3-emissao-reemissao-rastreabilidade.md)
  para a trilha real de emissao/reemissao do `.lic`, a governanca do
  artefacto e a rastreabilidade minima da F3.5.
- [`f3-validacao-manual-evidencias.md`](f3-validacao-manual-evidencias.md)
  para a matriz manual de cenarios, evidencias minimas, comandos objectivos e
  politica conservadora de validacao suficiente da F3.6.
- [`f3-pack-operacional-validacao.md`](f3-pack-operacional-validacao.md)
  para o pack operacional da F3.7, a convencao de ficheiros de evidencia, os
  estados `PASS` / `FAIL` / `INCONCLUSIVE` / `BLOCKED` e o helper shell
  barato de exportacao do backend.

---

## 1. Objectivo da F3

Tornar o comportamento de licenciamento previsivel em cenarios normais e de
falha, sem remover nenhuma funcionalidade existente e sem misturar a F3 com
auth/admin/TLS/CORS/CRUD fora do que ja foi fechado na F2.

Checkpoint adicional da F3.2:

- a F3.2 passa a formalizar separadamente a leitura factual do fingerprint,
  as dependencias de `kern.hostuuid` e da primeira MAC Ethernet nao-loopback,
  a matriz conservadora de cenarios reais de appliance e uma unica melhoria
  tecnica defensiva de normalizacao de formato do `hardware_id` persistido no
  servidor, sem alterar a formula base do fingerprint.

Checkpoint adicional da F3.4:

- a F3.4 passa a formalizar separadamente a superficie administrativa real de
  mutacao/reemissao e a bloquear apenas a transferencia silenciosa de
  `customer_id` em licenca ja activada/bindada, sem abrir rebind dedicado,
  sem mudar `.lic` e sem mexer no daemon.

Checkpoint adicional da F3.5:

- a F3.5 passa a formalizar separadamente a trilha real de emissao e
  reemissao do `.lic`, a distinguir emissao inicial de reemissao legitima e a
  reforcar a auditoria minima do artefacto devolvido em `activate` e
  `download`, sem mudar payload, formato `.lic` ou criterio de validacao do
  daemon.

---

## 2. Limites da F3.1

Incluido nesta subfase:

- contrato actual de licenca/activacao;
- primeira activacao online;
- reactivacao do mesmo hardware;
- concorrencia na primeira activacao;
- diferenca entre expiracao online e grace local do daemon;
- tratamento actual do `hardware_id` / fingerprint;
- primeiro endurecimento minimo e seguro em `POST /api/activate`.

Fora de escopo nesta subfase:

- refactor do modelo comercial;
- mudanca do formato `.lic`;
- mudanca da chave publica embutida no daemon;
- redesenho do CRUD administrativo;
- mudanca de auth/sessao/TLS/CORS/rate-limit;
- observabilidade ampliada, package/runtime e qualquer trilha da F4+.

---

## 3. Estado real observado no codigo

### 3.1 Endpoints envolvidos

Backend:

- `GET /api/health` em `license-server/backend/src/index.js`
- `POST /api/activate` em `license-server/backend/src/routes/activate.js`
- `POST /api/auth/login`, `GET /api/auth/session`, `POST /api/auth/logout`
  em `license-server/backend/src/routes/auth.js`
- `GET/POST/PUT/DELETE /api/licenses`, `POST /api/licenses/:id/revoke`,
  `GET /api/licenses/:id/download` em
  `license-server/backend/src/routes/licenses.js`
- `GET/POST/PUT/DELETE /api/customers` em
  `license-server/backend/src/routes/customers.js`
- `GET /api/dashboard` em `license-server/backend/src/routes/dashboard.js`

Daemon/pfSense:

- `layer7d --fingerprint` em `src/layer7d/license.c`
- `layer7d --activate KEY [URL]` em `src/layer7d/license.c`
- verificacao local de `.lic` em `src/layer7d/license.c`
- leitura de estado da GUI em
  `package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc`

### 3.2 Tabelas e campos relevantes

- `licenses`
  - `license_key`
  - `hardware_id`
  - `expiry`
  - `status`
  - `activated_at`
  - `revoked_at`
  - `archived_at`
  - `customer_id`
- `customers`
  - `id`
  - `name`
  - `archived_at`
- `activations_log`
  - `license_id`
  - `hardware_id`
  - `ip_address`
  - `user_agent`
  - `result`
  - `error_message`
  - `created_at`
- `admin_sessions`, `admin_audit_log` e `admin_login_guards`
  continuam relevantes para operacao/admin, mas nao alteram o contrato
  publico de activacao.

### 3.3 Transacoes e locks existentes

Estado observado:

- `POST /api/activate` ja opera dentro de `runInTransaction()`.
- A activacao usa `SELECT ... FOR UPDATE` sobre a linha de `licenses`
  identificada por `license_key`.
- `create/update/revoke/archive` de licencas e clientes tambem usam
  transacao explicita.
- Nao existe lock dedicado por `customer` na activacao; o lock efectivo desta
  subfase recai sobre a linha da `licenses`.

### 3.4 Semantica actual de expiracao

No backend:

- `licenses.status` aceita `active`, `revoked` e `expired`.
- A expiracao nao e persistida automaticamente quando a data passa.
- Leituras/listagens tratam como **expirada** tanto a licenca com
  `status = 'expired'` quanto a licenca com `status = 'active'` e
  `expiry < CURRENT_DATE`.
- `POST /api/activate` recusa activacao quando `isLicenseExpired(license)` ou
  quando `status = 'expired'`.
- `GET /api/licenses/:id/download` tambem recusa estados nao activos apos a
  normalizacao derivada.

No daemon:

- a verificacao local do `.lic` aceita a data `expiry` enquanto `diff_days >= 0`;
- se a data ja passou, entra em **grace period local de 14 dias**;
- so depois do grace o daemon considera a licenca invalida.

Conclusao observada:

- a **activacao online e strict** para licenca expirada;
- o **consumo local da licenca ja emitida** ainda pode operar em grace.

### 3.5 Semantica actual de fingerprint / hardware_id

No servidor:

- `hardware_id` e tratado como valor opaco, obrigatorio, com regex
  `^[a-f0-9]{64}$`;
- o backend normaliza para lowercase e nao tenta recalcular fingerprint;
- o primeiro bind de hardware ocorre na primeira activacao valida;
- depois do bind, qualquer activacao com `hardware_id` diferente retorna `409`.

No daemon:

- o fingerprint local e `SHA256(kern.hostuuid + ":" + primeira MAC Ethernet
  nao-loopback)`;
- o daemon envia esse valor em `POST /api/activate`;
- a verificacao local do `.lic` compara o `hardware_id` do ficheiro assinado
  com o fingerprint recalculado na maquina.

### 3.6 Comportamento actual em reactivacao

- se a licenca estiver activa, nao arquivada, nao revogada, nao expirada e o
  `hardware_id` for o mesmo, o backend emite um novo `.lic`;
- antes desta F3.1, a reactivacao repetida actualizava `updated_at` mesmo sem
  mudanca real do bind;
- `activated_at` ja era protegido por `COALESCE`, portanto nao era sobrescrito
  depois da primeira activacao bem-sucedida.

### 3.7 Comportamento actual em concorrencia

- concorrencia entre duas activacoes da mesma `license_key` ja era
  serializada pelo `FOR UPDATE`;
- corrida entre dois `hardware_id` diferentes tende a ficar consistente:
  quem confirmar primeiro fixa o bind e o segundo falha com `409`;
- corrida entre duas activacoes com o mesmo `hardware_id` podia gerar
  mutacao desnecessaria de `updated_at`, embora sem rebind indevido.

---

## 4. Ambiguidades e gaps observados

1. **Estado persistido vs estado derivado**
   `expired` existe no schema, mas parte do comportamento operacional continua
   derivado de `expiry`, nao de transicao persistida unica.

2. **Grace local vs activacao online**
   o backend nega activacao de licenca expirada, enquanto o daemon ainda aceita
   a licenca local em grace por 14 dias.

3. **Fingerprint pouco governado**
   o servidor valida apenas formato; a estabilidade do fingerprint depende
   totalmente do daemon e ainda nao tem matriz formal de cenarios de troca de
   NIC, reinstall, VM ou mudanca da ordem de interfaces.

4. **Idempotencia parcial**
   havia protecao transacional suficiente para evitar rebind inconsistente, mas
   a reactivacao do mesmo hardware ainda mutava o registo sem necessidade.

---

## 5. Contrato canónico proposto para a F3

### 5.1 Principios

- preservar compatibilidade com clientes actuais (`POST /api/activate`
  continua a responder `{"data","sig"}` em sucesso);
- nao sobrescrever `hardware_id` depois do primeiro bind valido;
- tornar explicito que o estado de expiracao e hoje uma combinacao de
  persistencia (`status`) e derivacao (`expiry`);
- distinguir claramente o que e contrato **online** do servidor e o que e
  contrato **local** do daemon.

### 5.2 Estados canónicos relevantes

#### Estado persistido da licenca

- `active`
- `revoked`
- `expired`
- `archived` como estado de visibilidade operacional via `archived_at`

#### Estado operacional derivado para leitura/decisao

- `active-unbound`
  - `status = 'active'`
  - `hardware_id IS NULL`
  - `archived_at IS NULL`
  - `expiry >= hoje`
- `active-bound`
  - `status = 'active'`
  - `hardware_id IS NOT NULL`
  - `archived_at IS NULL`
  - `expiry >= hoje`
- `expired-derived`
  - `status = 'expired'`
  - ou `status = 'active'` com `expiry < hoje`
- `revoked`
  - `status = 'revoked'`
- `archived`
  - `archived_at IS NOT NULL`
  - ou cliente associado arquivado para efeitos de visibilidade/admin

### 5.3 Transicoes canónicas

- criacao de licenca:
  - entra em `active-unbound`
- primeira activacao valida:
  - `active-unbound -> active-bound`
  - fixa `hardware_id`
  - fixa `activated_at`
  - gera `.lic` assinado
- reactivacao valida no mesmo hardware:
  - `active-bound -> active-bound`
  - nao altera o bind
  - pode reemitir `.lic`
- tentativa concorrente com hardware diferente apos primeiro bind:
  - permanece em `active-bound`
  - resposta `409`
- expiracao por data:
  - leitura/admin passam a tratar como `expired-derived`
  - activacao online falha fechado
- revogacao administrativa:
  - `active-*` ou `expired-derived -> revoked`
- arquivo administrativo:
  - qualquer estado visivel -> `archived`

---

## 6. Comportamento esperado por cenario

### Activacao valida (primeira activacao)

- pre-condicoes:
  - `license_key` existe
  - licenca nao arquivada
  - cliente visivel
  - nao revogada
  - nao expirada para activacao online
- efeito:
  - fixa `hardware_id`
  - fixa `activated_at`
  - grava `activations_log(result='success')`
  - devolve `.lic` assinado

### Activacao repetida do mesmo hardware

- pre-condicoes:
  - mesma `license_key`
  - mesmo `hardware_id`
  - licenca ainda valida para activacao online
- efeito esperado:
  - nao faz rebind
  - nao recria a primeira activacao
  - pode reemitir `.lic`
  - regista nova tentativa bem-sucedida em `activations_log`

### Activacao concorrente

- mesma licenca, mesmo hardware:
  - ambas podem concluir com sucesso
  - o estado persistido final deve permanecer unico e coerente
- mesma licenca, hardware diferente:
  - a primeira activacao valida vence o bind
  - a posterior incompatível falha com `409`

### Activacao invalida

- chave inexistente ou licenca invisivel/arquivada: `404`
- payload invalido: `400`
- `hardware_id` diferente do bind actual: `409`

### Licenca expirada

- online:
  - `POST /api/activate` falha com `409`
- local:
  - o daemon ainda pode operar em grace por `14` dias com um `.lic` ja emitido

### Licenca revogada

- activacao online falha com `409`
- download administrativo do `.lic` deixa de ser permitido

---

## 7. Decisao de compatibilidade

Decisao desta abertura da F3:

- manter o payload de sucesso de `POST /api/activate` inalterado;
- manter `400` / `404` / `409` ja usados na F2.4;
- nao alterar o algoritmo de fingerprint nesta subfase;
- nao alterar o grace local do daemon nesta subfase;
- endurecer apenas a idempotencia e a previsibilidade do bind no backend.

---

## 8. Primeiro endurecimento concreto materializado na F3.1

Arquivo afectado:

- `license-server/backend/src/routes/activate.js`

Mudanca aplicada:

- a reactivacao do mesmo hardware deixa de actualizar o registo da licenca sem
  necessidade;
- o backend passa a usar o `hardware_id` efectivamente persistido para assinar
  o `.lic`;
- o `UPDATE` da activacao fica guardado por condicao adicional
  `hardware_id IS NULL OR hardware_id = $1`, reforcando a semantica de bind
  unico no proprio `UPDATE`;
- o log de falha passa a usar o `hardware_id` ja normalizado pelo parser da
  rota.

Resultado esperado:

- nenhuma funcionalidade removida;
- mesma resposta de sucesso para clientes actuais;
- menos drift operacional em reactivacoes repetidas;
- bind inicial continua a ser unico e previsivel.

---

## 9. Ordem conservadora das proximas subfases da F3

### F3.2 — fingerprint, binding e cenarios reais de appliance

- formalizar a formula real do fingerprint e os riscos de appliance;
- fechar a politica conservadora de binding sem alterar o algoritmo base.

### F3.3 — expiracao, revogacao, grace e validade offline

- formalizar a diferenca entre estado persistido e estado efectivo;
- declarar o limite real da revogacao actual e da validade offline do `.lic`;
- bloquear rebind administrativo precoce por risco real do `.lic` antigo.

### F3.4 — mutacao administrativa, reemissao e guardrails

- mapear a superficie administrativa real da licenca;
- distinguir mutacoes seguras de mutacoes perigosas apos bind;
- bloquear apenas o minimo necessario para impedir transferencia silenciosa
  de licenca bindada.

### F3.5 — emissao, reemissao e rastreabilidade do artefacto

- mapear a trilha real de emissao e download do `.lic`;
- distinguir emissao inicial, reemissao legitima e reemissao administrativa;
- reforcar a auditoria minima do artefacto emitido sem mudar o contrato.

### F3.6 — testes e evidencias de licenciamento

- transformar o contrato da F3 em matriz de casos repetiveis em appliance;
- formalizar comandos objectivos, evidencias minimas e criterios de
  aprovacao/reprovacao sem fingir que a execucao real ja aconteceu.

### F3.7 — pack operacional da validacao manual

- transformar a matriz da F3.6 num pack executavel por `run_id` e por
  cenario;
- padronizar outputs, nomes de ficheiros e classificacao final do resultado;
- acrescentar apenas tooling barato fora do produto para exportar evidencias
  de backend e reduzir ambiguidade humana.

---

## 10. Riscos fora de escopo que permanecem

- semantica persistida de `expired` continua incompleta;
- grace local continua apenas no daemon, agora com politica operacional
  documentada e operacionalizada em pack, mas ainda sem validacao
  manual/appliance fechada;
- fingerprint continua sensivel a mudancas reais do hardware/base system;
- a revogacao actual continua sem invalidacao offline do `.lic` ja emitido;
- nao houve nesta subfase mudanca de revogacao offline, rotacao de chaves,
  package/runtime ou observabilidade ampliada.

---

## 11. Objectivo, impacto, risco, teste e rollback deste bloco

- **Objectivo:** abrir formalmente a F3 e aumentar a previsibilidade da
  activacao sem quebrar clientes actuais.
- **Impacto:** documental forte; tecnico pequeno e localizado em
  `POST /api/activate`.
- **Risco:** baixo, porque nao muda payload de sucesso nem contrato de auth,
  CRUD ou daemon.
- **Teste minimo:** `node --check license-server/backend/src/routes/activate.js`
  e revisao de diff/documentacao.
- **Rollback:** `git revert <commit-da-f3.1>` ou restaurar a revisao anterior
  de `activate.js` e dos documentos desta abertura.
