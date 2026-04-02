# F3.6 — Validacao Manual e Evidencias

## Finalidade

Este documento fecha a parte canónica da **F3.6** sem abrir F4/F5/F6/F7.

Objectivo da subfase:

- transformar o contrato ja implementado na F3 em matriz manual controlada;
- declarar com honestidade o que ja esta robusto em codigo;
- separar o que ainda depende de evidencia real em lab/appliance;
- definir a politica oficial de **validacao suficiente** da F3;
- fornecer comandos objectivos, checks e evidencias minimas para execucao
  posterior pelo operador.

**Estado desta subfase:** documentacao canónica consolidada em `2026-04-01`;
execucao manual real em lab/appliance ainda pendente.

---

## 1. Escopo e limite desta subfase

Incluido na F3.6:

- leitura factual do backend, da auditoria e do daemon;
- matriz manual de activacao, reactivacao, expiracao, revogacao, download,
  grace, coexistencia de artefactos e fingerprint;
- comandos objectivos para recolha de evidencia;
- criterios de aprovacao/reprovacao por cenario;
- politica conservadora de fecho da F3.

Fora de escopo:

- mudar formato do `.lic`;
- mudar algoritmo de fingerprint;
- criar revogacao offline pesada;
- criar rebind administrativo;
- criar heartbeat online;
- mexer em frontend/UX;
- abrir F4/F5/F6/F7.

---

## 2. Leitura factual da validabilidade actual

### 2.1 O que ja esta robusto em contrato/codigo

Observado no codigo actual:

- `POST /api/activate` opera com transacao explicita e `SELECT ... FOR UPDATE`
  sobre a licenca;
- a primeira activacao valida fixa `hardware_id` e `activated_at`;
- a reactivacao do mesmo hardware preserva o bind e reemite `.lic`;
- activacao com `hardware_id` diferente apos bind falha fechado com `409`;
- expiracao efectiva e derivada de `status` + `expiry` no backend;
- revogacao bloqueia activacao online e download administrativo;
- `PUT /api/licenses/:id` bloqueia mudanca de `customer_id` em licenca
  activada/bindada;
- `GET /api/licenses/:id/download` exige licenca bindada e efectivamente
  `active`;
- a auditoria ja regista:
  - tentativa de activacao em `activations_log`;
  - mutacoes administrativas em `admin_audit_log`;
  - contexto do artefacto emitido em `license_artifact_issued` e
    `license_downloaded`;
- o daemon ja expoe no stats JSON:
  - `license_valid`
  - `license_expired`
  - `license_grace`
  - `license_days_left`
  - `license_hardware_id`
  - `license_error`

### 2.2 O que e validavel apenas em backend

Pode ser comprovado sem appliance real:

- `409` para `hardware_id` diferente em licenca bindada;
- `409` para licenca expirada no caminho online;
- `409` para licenca revogada no caminho online;
- `409` para mudar `customer_id` em licenca bindada;
- `409` para download de licenca nao bindada, expirada ou revogada;
- coerencia do estado efectivo entre `activate`, `licenses`, `customers` e
  `dashboard`;
- presenca de `flow`, `emission_kind` e hashes do artefacto na auditoria.

### 2.3 O que ainda depende de teste pratico

Exige appliance, simulacao real ou ambos:

- grace local de `14` dias no daemon;
- queda para monitor-only apos fim do grace;
- comportamento real do `.lic` antigo apos revogacao no backend;
- coexistencia pratica de artefacto antigo e artefacto novo no mesmo hardware;
- efeito real de reinstall, troca de NIC, clone de VM, restore e migracao no
  fingerprint.

### 2.4 O que exige appliance ou simulacao real

Requer pfSense/lab:

- `layer7d --activate` a partir do daemon real;
- gravacao e leitura de `/usr/local/etc/layer7.lic`;
- leitura de `/tmp/layer7-stats.json`;
- enforce ou monitor-only apos expiracao/grace;
- mismatch local entre fingerprint recalculado e `hardware_id` do `.lic`.

### 2.5 O que depende de relogio/data local

Depende de relogio:

- grace local no daemon;
- expiracao local do `.lic` ja emitido;
- diferenca entre servidor expirar online e appliance ainda aceitar localmente;
- cenario offline antes e depois do grace.

Leitura conservadora:

- os cenarios com grace so fecham com relogio controlado no appliance ou com
  espera real da data contratual;
- o cenario "backend expirado + `.lic` ainda em grace" exige tambem servidor
  em data ja posterior a `expiry`, seja por espera real, seja por lab isolado
  com relogio controlado.

### 2.6 O que depende de bind/fingerprint real

Depende de fingerprint real:

- reinstall legitimo sem drift;
- reinstall com drift;
- troca de NIC;
- clone de VM;
- restore de snapshot;
- migracao de hypervisor;
- reorder efectivo da primeira NIC elegivel.

### 2.7 O que depende de artefacto ja emitido

Exige `.lic` previo:

- download administrativo de licenca bindada;
- revogacao com `.lic` antigo ainda em campo;
- renovacao + reemissao;
- coexistencia de artefacto antigo e novo;
- comportamento offline depois da perda do contacto com o servidor.

### 2.8 O que ainda nao pode ser provado sem mudanca fora de escopo

Continua impossivel comprovar como garantia do produto actual:

- que o daemon aceita apenas o artefacto "mais recente";
- invalidacao offline imediata de um `.lic` revogado;
- versionamento forte do `.lic`;
- origem intrinseca da "ultima reemissao" apenas pelo ficheiro local;
- robustez universal do fingerprint em todas as permutacoes de
  reinstall/NIC/UUID/clone/migracao sem executar essas mudancas em lab.

### 2.9 Leitura operacional adicional importante

Inferencia conservadora a partir do codigo actual:

- se dois downloads administrativos ocorrerem no mesmo dia sem mudanca em
  `hardware_id`, `expiry`, `customer` ou `features`, o payload assinado tende
  a ser identico, porque o `.lic` actual so carrega `issued` em `YYYY-MM-DD`;
- por isso, **repeticao de download nao implica artefacto diferente**;
- a evidencia de reemissao repetida vem da auditoria, nao necessariamente do
  diff do ficheiro.

---

## 3. Politica oficial de validacao suficiente da F3

### 3.1 Definicao de "substancialmente validada"

A F3 so pode ser tratada como **substancialmente validada** quando:

1. todos os cenarios marcados como **Obrigatorio** neste documento tiverem
   sido executados com evidencia minima recolhida;
2. cada cenario obrigatorio tiver prova em pelo menos dois planos quando
   aplicavel:
   - resposta da API ou comando CLI;
   - estado persistido/auditoria no backend;
   - estado local do appliance/daemon;
3. nenhuma contradicao surgir entre:
   - estado efectivo do backend;
   - auditoria do artefacto;
   - estado local do daemon.

### 3.2 O que nao e necessario para "substancialmente validada"

Nao e exigido nesta definicao minima:

- fechar todas as permutacoes de hypervisor e hardware;
- introduzir revogacao offline forte;
- provar "latest only";
- abrir F4/F5.

### 3.3 O que continua pendente mesmo com validacao suficiente

Mesmo apos todos os obrigatorios passarem, continuam pendentes:

- permutacoes desejaveis de fingerprint em multiplos ambientes;
- qualquer rebind governado;
- qualquer invalidacao offline imediata de artefacto antigo;
- qualquer noção de "artefacto mais recente" consumida pelo daemon.

### 3.4 Regra de evidencias

Nenhum cenario deve ser marcado como validado sem:

- data/hora UTC;
- identificador do ambiente;
- comando executado;
- resposta/codigo HTTP ou saida CLI relevante;
- query de auditoria/estado quando aplicavel;
- conclusao `passa` ou `falha`.

---

## 4. Pre-requisitos e comandos base

### 4.1 Pre-requisitos gerais

- lab isolado ou janela controlada;
- um appliance pfSense acessivel por SSH;
- um deploy do license server acessivel por HTTPS e, quando necessario,
  acesso ao host para `docker compose exec`;
- snapshot antes de mexer em relogio, NIC, UUID, clone ou restore;
- credencial administrativa valida;
- pelo menos uma licenca dedicada de teste por familia de cenario.

### 4.2 Variaveis base sugeridas

```bash
export L7_BASE_URL='https://license.systemup.inf.br'
export COOKIE_JAR='/tmp/layer7-license.cookies.txt'
export ADMIN_EMAIL='admin@systemup.inf.br'
export ADMIN_PASSWORD='substituir_por_segredo_real'
export LICENSE_ID='<LICENSE_ID>'
export LICENSE_KEY='<LICENSE_KEY_32_HEX>'
export ALT_CUSTOMER_ID='<OUTRO_CUSTOMER_ID>'
export HW_OTHER='1111111111111111111111111111111111111111111111111111111111111111'
export PF_SSH='ssh root@<PFSENSE_IP>'
export L7_SERVER_DIR='/opt/layer7-license'
```

### 4.3 Login administrativo

```bash
rm -f "$COOKIE_JAR"
curl -sS -c "$COOKIE_JAR" "$L7_BASE_URL/api/auth/login" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}"
```

### 4.4 Baseline do appliance

```bash
$PF_SSH 'layer7d --fingerprint'
$PF_SSH 'test -f /usr/local/etc/layer7.lic && cat /usr/local/etc/layer7.lic || echo "sem_licenca_local"'
$PF_SSH 'service layer7d status || true'
$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json'
$PF_SSH 'date -u'
```

### 4.5 Queries objectivas de evidencia no backend

Estado da licenca:

```bash
curl -sS -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID"
```

Activacoes recentes:

```bash
cd "$L7_SERVER_DIR"
docker compose exec -T db sh -lc 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -P pager=off -c "
SELECT created_at, result, hardware_id, error_message
FROM activations_log
WHERE license_id = <LICENSE_ID>
ORDER BY created_at DESC
LIMIT 10;"'
```

Auditoria do artefacto:

```bash
cd "$L7_SERVER_DIR"
docker compose exec -T db sh -lc 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -P pager=off -c "
SELECT created_at,
       event_type,
       reason,
       metadata->>''flow'' AS flow,
       metadata->>''emission_kind'' AS emission_kind,
       metadata->>''effective_status'' AS effective_status,
       metadata->>''artifact_envelope_sha256'' AS artifact_envelope_sha256
FROM admin_audit_log
WHERE metadata->>''license_id'' = ''<LICENSE_ID>''
ORDER BY created_at DESC
LIMIT 20;"'
```

Estado persistido relevante:

```bash
cd "$L7_SERVER_DIR"
docker compose exec -T db sh -lc 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -P pager=off -c "
SELECT id, customer_id, hardware_id, status, expiry, activated_at, revoked_at, archived_at
FROM licenses
WHERE id = <LICENSE_ID>;"'
```

---

## 5. Matriz resumida de cenarios

| ID | Cenario | Ambiente principal | Dependencias criticas | Classificacao |
|----|---------|--------------------|------------------------|---------------|
| S01 | Activacao inicial valida | backend + appliance | bind inicial + auditoria | Obrigatorio |
| S02 | Re-activacao legitima do mesmo hardware | backend + appliance | bind preservado | Obrigatorio |
| S03 | Activacao com hardware diferente para licenca bindada | backend | `409` + activations_log | Obrigatorio |
| S04 | Download administrativo de licenca bindada | backend | bind existente + auditoria do artefacto | Obrigatorio |
| S05 | Mutacao permitida de `expiry` e reemissao | backend + appliance | renovacao legitima | Obrigatorio |
| S06 | Tentativa de mudar `customer_id` em licenca bindada | backend | guardrail F3.4 | Obrigatorio |
| S07 | Licenca expirada no backend sem `.lic` local | backend + appliance | estado efectivo online | Obrigatorio |
| S08 | Licenca expirada no backend com `.lic` local ainda dentro da grace | backend + appliance | relogio controlado + `.lic` previo | Obrigatorio |
| S09 | Licenca revogada no backend com `.lic` antigo offline | backend + appliance | revogacao online sem revogacao offline | Obrigatorio |
| S10 | Multiplos downloads/reemissoes da mesma licenca | backend | auditoria repetida | Desejavel |
| S11 | Coexistencia de artefacto antigo e artefacto novo | backend + appliance | dois `.lic` validos em campo | Obrigatorio |
| S12 | Appliance offline antes e depois do grace | appliance | relogio local + `.lic` previo | Obrigatorio |
| S13 | Divergencia de fingerprint por mudanca de NIC/UUID | appliance/lab | bind real + drift real | Obrigatorio |

---

## 6. Cenarios detalhados

### S01 — Activacao inicial valida

- **Pre-requisitos:** licenca `active`, sem `hardware_id`, appliance online e
  sem `/usr/local/etc/layer7.lic` para esta licenca.
- **Comandos/checks:**

```bash
$PF_SSH 'layer7d --fingerprint'
$PF_SSH "layer7d --activate $LICENSE_KEY"
curl -sS -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID"
```

- **Resultado esperado:** activacao com sucesso; `hardware_id` persistido;
  `activated_at` preenchido; evento `license_artifact_issued` com
  `initial_issue`; `activations_log.result='success'`.
- **Evidencia minima:** saida do `--activate`, detalhe da licenca via API,
  query em `activations_log`, query em `admin_audit_log`.
- **Sinais de falha/regressao:** `404`, `409`, `.lic` nao gravado, bind nao
  persistido, ausencia de auditoria.
- **Risco operacional:** baixo.

### S02 — Re-activacao legitima do mesmo hardware

- **Pre-requisitos:** S01 concluido; mesmo appliance; mesma `license_key`.
- **Comandos/checks:**

```bash
$PF_SSH "layer7d --activate $LICENSE_KEY"
curl -sS -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID"
```

- **Resultado esperado:** sucesso; `hardware_id` inalterado; `activated_at`
  original preservado; auditoria com `reactivation_reissue`.
- **Evidencia minima:** saida do `--activate`, estado da licenca, query de
  auditoria do artefacto e query persistida de `activated_at`.
- **Sinais de falha/regressao:** novo bind, `activated_at` sobrescrito,
  `409`, ausencia de `reactivation_reissue`.
- **Risco operacional:** baixo.

### S03 — Activacao com hardware diferente para licenca bindada

- **Pre-requisitos:** licenca ja bindada.
- **Comandos/checks:**

```bash
curl -si -X POST "$L7_BASE_URL/api/activate" \
  -H 'Content-Type: application/json' \
  -d "{\"key\":\"$LICENSE_KEY\",\"hardware_id\":\"$HW_OTHER\"}"
```

- **Resultado esperado:** `409`; mensagem `Hardware ID nao corresponde.`;
  `activations_log.result='fail'`; bind persistido inalterado.
- **Evidencia minima:** HTTP status/body, query em `activations_log`, query
  do estado persistido.
- **Sinais de falha/regressao:** `200`, troca de bind, ausencia de log de
  falha.
- **Risco operacional:** medio.

### S04 — Download administrativo de licenca bindada

- **Pre-requisitos:** licenca efectivamente `active` e com `hardware_id`
  persistido.
- **Comandos/checks:**

```bash
curl -sS -b "$COOKIE_JAR" \
  "$L7_BASE_URL/api/licenses/$LICENSE_ID/download" \
  -o "/tmp/layer7-$LICENSE_ID.lic"
cat "/tmp/layer7-$LICENSE_ID.lic"
```

- **Resultado esperado:** download com sucesso; artefacto `{ data, sig }`;
  auditoria `license_downloaded` com `admin_download_reissue`.
- **Evidencia minima:** ficheiro descarregado, cabecalhos/HTTP 200,
  `admin_audit_log`.
- **Sinais de falha/regressao:** `409` indevido, artefacto sem `data/sig`,
  ausencia de auditoria.
- **Risco operacional:** medio.

### S05 — Mutacao permitida de `expiry` e reemissao

- **Pre-requisitos:** licenca bindada e activa; preferencialmente guardar o
  artefacto antigo antes da mudanca.
- **Comandos/checks:**

```bash
curl -sS -b "$COOKIE_JAR" -X PUT "$L7_BASE_URL/api/licenses/$LICENSE_ID" \
  -H 'Content-Type: application/json' \
  -d '{"expiry":"2027-12-31"}'

curl -sS -b "$COOKIE_JAR" \
  "$L7_BASE_URL/api/licenses/$LICENSE_ID/download" \
  -o "/tmp/layer7-$LICENSE_ID-renewed.lic"
```

- **Resultado esperado:** `expiry` actualizado; bind preservado; novo
  download permitido; reactivacao do mesmo hardware continua valida.
- **Evidencia minima:** resposta do `PUT`, resposta do download, query do
  estado persistido, opcionalmente refresh do appliance com o novo `.lic`.
- **Sinais de falha/regressao:** bind alterado, `customer_id` alterado,
  download negado sem revogacao/expiracao, `409` indevido.
- **Risco operacional:** medio.

### S06 — Tentativa de mudar `customer_id` em licenca bindada

- **Pre-requisitos:** licenca bindada; `ALT_CUSTOMER_ID` valido e visivel.
- **Comandos/checks:**

```bash
curl -si -b "$COOKIE_JAR" -X PUT "$L7_BASE_URL/api/licenses/$LICENSE_ID" \
  -H 'Content-Type: application/json' \
  -d "{\"customer_id\":$ALT_CUSTOMER_ID}"
```

- **Resultado esperado:** `409`; mensagem a bloquear transferencia silenciosa;
  `customer_id` persistido inalterado; evento `license_update_denied`.
- **Evidencia minima:** HTTP status/body, query do estado persistido, query da
  auditoria administrativa.
- **Sinais de falha/regressao:** `200`, `customer_id` alterado, ausencia de
  auditoria de negacao.
- **Risco operacional:** alto.

### S07 — Licenca expirada no backend sem `.lic` local

- **Pre-requisitos:** licenca dedicada expirada no backend; appliance sem
  `/usr/local/etc/layer7.lic` dessa licenca.
- **Comandos/checks:**

```bash
$PF_SSH 'rm -f /usr/local/etc/layer7.lic'
$PF_SSH "layer7d --activate $LICENSE_KEY"
curl -sS -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID"
```

- **Resultado esperado:** activacao falha; backend mostra estado efectivo
  `expired`; nenhum `.lic` novo fica gravado.
- **Evidencia minima:** saida do `--activate`, detalhe da licenca via API,
  ausencia do ficheiro local, `activations_log.result='fail'`.
- **Sinais de falha/regressao:** activacao bem-sucedida, `.lic` novo criado,
  estado efectivo incoerente.
- **Risco operacional:** medio.

### S08 — Licenca expirada no backend com `.lic` local ainda dentro da grace

- **Pre-requisitos:** artefacto ja emitido antes da expiracao; ambiente de lab
  onde servidor e appliance possam ser observados apos `expiry` sem usar
  producao; appliance com relogio em `expiry + N` dias, com `N <= 14`.
- **Comandos/checks:**

```bash
curl -sS -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID"
$PF_SSH 'date -u'
$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json'
```

- **Resultado esperado:** backend trata a licenca como `expired` e nega nova
  activacao/download; appliance continua com `license_valid=true`,
  `license_expired=true`, `license_grace=true`.
- **Evidencia minima:** detalhe da licenca no backend, tentativa online com
  `409`, stats JSON do appliance dentro da grace.
- **Sinais de falha/regressao:** backend continuar `active`, daemon cair para
  invalida antes de `14` dias, ou backend permitir download/activacao.
- **Risco operacional:** alto.

### S09 — Licenca revogada no backend com `.lic` antigo offline

- **Pre-requisitos:** `.lic` valido ja guardado no appliance; licenca ainda
  dentro da data/grace; appliance sem depender de novo contacto ao servidor.
- **Comandos/checks:**

```bash
curl -sS -b "$COOKIE_JAR" -X POST "$L7_BASE_URL/api/licenses/$LICENSE_ID/revoke" \
  -H 'Content-Type: application/json' \
  -d '{}'

curl -si -X POST "$L7_BASE_URL/api/activate" \
  -H 'Content-Type: application/json' \
  -d "{\"key\":\"$LICENSE_KEY\",\"hardware_id\":\"$HW_OTHER\"}"

$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json'
```

- **Resultado esperado:** servidor marca `revoked` e passa a negar activacao;
  o appliance continua a aceitar o `.lic` antigo enquanto assinatura,
  fingerprint e data local permitirem.
- **Evidencia minima:** resposta da revogacao, detalhe da licenca, stats JSON
  do appliance, opcionalmente tentativa de download com `409`.
- **Sinais de falha/regressao:** download/activacao continuarem permitidos no
  servidor, ou o daemon invalidar imediatamente sem motivo de data/hardware.
- **Risco operacional:** alto.

### S10 — Multiplos downloads/reemissoes da mesma licenca

- **Pre-requisitos:** licenca bindada e activa; mesma data e mesmos campos
  funcionais, se o objectivo for observar repeticao pura.
- **Comandos/checks:**

```bash
curl -sS -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID/download" \
  -o /tmp/layer7-a.lic
curl -sS -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID/download" \
  -o /tmp/layer7-b.lic
```

- **Resultado esperado:** dois actos auditados; os ficheiros podem ser
  identicos se o payload funcional nao mudou no mesmo dia.
- **Evidencia minima:** queries de `admin_audit_log`; hashes locais dos dois
  ficheiros apenas como evidencia adicional, nao como criterio de sucesso.
- **Sinais de falha/regressao:** falta de segundo evento auditado, `409`
  indevido ou erro na segunda reemissao sem mudanca de estado.
- **Risco operacional:** medio.

### S11 — Coexistencia de artefacto antigo e artefacto novo

- **Pre-requisitos:** um `.lic` antigo guardado antes de renovar `expiry` ou
  outro campo assinado; licenca continua bindada ao mesmo hardware.
- **Comandos/checks:**

```bash
$PF_SSH 'cp /usr/local/etc/layer7.lic /root/layer7-old.lic'

curl -sS -b "$COOKIE_JAR" -X PUT "$L7_BASE_URL/api/licenses/$LICENSE_ID" \
  -H 'Content-Type: application/json' \
  -d '{"expiry":"2027-12-31"}'

curl -sS -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID/download" \
  -o /tmp/layer7-new.lic

scp /tmp/layer7-new.lic root@<PFSENSE_IP>:/root/layer7-new.lic
$PF_SSH 'cp /root/layer7-old.lic /usr/local/etc/layer7.lic && /bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json'
$PF_SSH 'cp /root/layer7-new.lic /usr/local/etc/layer7.lic && /bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json'
```

- **Resultado esperado:** ambos os artefactos podem ser aceites no mesmo
  hardware enquanto a data local permitir; o daemon nao sabe qual e o "mais
  recente".
- **Evidencia minima:** copia dos dois ficheiros, stats do appliance com cada
  um, auditoria do download/reemissao, estado persistido da licenca.
- **Sinais de falha/regressao:** suposicao errada de exclusividade forte,
  auditoria ausente ou artefacto novo rejeitado sem mudanca de bind/data.
- **Risco operacional:** alto.

### S12 — Appliance offline antes e depois do grace

- **Pre-requisitos:** appliance com `.lic` valido ja emitido; ambiente
  isolado/snapshot antes de mexer em data; preferencialmente sem depender do
  servidor durante o teste.
- **Comandos/checks:**

```bash
$PF_SSH 'date -u'
$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json'

# Depois repetir em data > expiry e <= expiry+14
$PF_SSH 'date -u 202604150010'
$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json'

# Depois repetir em data > expiry+14
$PF_SSH 'date -u 202604300010'
$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json'
```

- **Resultado esperado:** antes da expiracao continua valida; dentro da grace
  fica `valid=true`, `expired=true`, `grace=true`; apos a grace fica
  `valid=false` e o daemon deve cair para monitor-only.
- **Evidencia minima:** datas usadas, tres capturas do stats JSON, estado do
  servico e, se possivel, log do daemon.
- **Sinais de falha/regressao:** ausencia de grace, grace superior a `14`
  dias, enforce continuar apos grace esgotada.
- **Risco operacional:** alto.

### S13 — Divergencia de fingerprint por mudanca de NIC/UUID

- **Pre-requisitos:** lab com snapshot/clone; capacidade real de alterar NIC,
  MAC, UUID ou ordem de interfaces; licenca bindada previamente.
- **Comandos/checks:**

```bash
$PF_SSH 'sysctl -n kern.hostuuid'
$PF_SSH 'layer7d --fingerprint'

# Aplicar a mudanca controlada de NIC/UUID/clone/restore fora do produto

$PF_SSH 'sysctl -n kern.hostuuid'
$PF_SSH 'layer7d --fingerprint'
$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json'
```

- **Resultado esperado:** se o fingerprint mudar, o `.lic` antigo deve falhar
  localmente por hardware mismatch e a activacao online deve falhar com `409`;
  se o fingerprint nao mudar, documentar explicitamente que o ambiente
  preservou a identidade observada pelo daemon.
- **Evidencia minima:** `kern.hostuuid` antes/depois, fingerprint antes/depois,
  stats JSON local, tentativa online se houve drift, descricao exacta da
  mudanca fisica/virtual aplicada.
- **Sinais de falha/regressao:** bind mudar no servidor sem controlo, daemon
  aceitar fingerprint diferente, ou operador declarar "mesmo appliance"
  sem prova do fingerprint.
- **Risco operacional:** alto.

---

## 7. Criticos de aprovacao e reprovacao

### Aprovacao minima por cenario

Um cenario so passa quando:

- o resultado observado bate com o contrato documentado da F3;
- a evidencia minima foi recolhida;
- nao ha contradicao entre API, banco e appliance.

### Reprovacao automatica do cenario

O cenario reprova se houver qualquer um destes sinais:

- codigo HTTP diferente do esperado sem explicacao documental;
- `admin_audit_log` ou `activations_log` ausente quando devia existir;
- bind alterado sem workflow dedicado;
- download/activacao aceites em estado que devia falhar fechado;
- daemon divergir do contrato de grace/fingerprint/data.

---

## 8. O que continua pendente apos a F3.6

Mesmo com esta subfase documentada, continuam pendentes:

- execucao real dos cenarios obrigatorios em lab/appliance;
- recolha de outputs reais e anexo de evidencia;
- permutacoes desejaveis adicionais de fingerprint;
- qualquer decisao sobre rebind, revogacao offline forte ou "latest only".

---

## 9. Itens explicitamente fora de escopo

- refactor amplo;
- mudanca do `.lic`;
- mudanca do fingerprint;
- heartbeat;
- revogacao offline pesada;
- rebind administrativo;
- observabilidade ampliada;
- F4/F5/F6/F7.

---

## 10. Proximos passos seguros apos a F3.6

1. Executar primeiro os cenarios obrigatorios `S01` a `S09`, `S11`, `S12`
   e `S13` em ambiente controlado.
2. Guardar outputs, timestamps e identificador do ambiente junto com a matriz
   de testes.
3. Reavaliar se `S10` e permutacoes adicionais de fingerprint ficam como
   desejaveis ou se algum incidente observado os torna bloqueadores.
4. So depois decidir se a F3 pode ser formalmente encerrada ou se precisa de
   um bloco tecnico adicional ainda dentro da propria F3.

---

## 11. Objectivo, impacto, risco, teste e rollback desta subfase

- **Objectivo:** fechar a F3.6 no plano documental e operacional, sem fingir
  que a validacao real ja aconteceu.
- **Impacto:** documental forte; nenhum impacto funcional no produto.
- **Risco:** baixo, porque nao muda backend, daemon, `.lic` ou GUI.
- **Teste minimo:** revisao cruzada com o codigo actual, consistencia com
  roadmap/backlog/checklist/manual/tests e validacao leve de sintaxe.
- **Rollback:** `git revert <commit-da-f3.6>` ou restaurar os documentos
  anteriores; nenhum rollback de runtime e necessario porque nao ha mudanca de
  codigo nesta subfase.
