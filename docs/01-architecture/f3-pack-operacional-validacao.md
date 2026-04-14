# F3.7 — Pack Operacional de Validacao

## Finalidade

Este documento fecha a parte canónica da **F3.7** sem abrir F4/F5/F6/F7.

O objectivo desta subfase e transformar a matriz da F3.6 num **pack
operacional executavel**, reduzindo atrito humano na recolha de evidencias
sem criar automacao pesada, sem mudar o contrato do produto e sem fingir que
o lab/appliance ja foi executado.

Incluido na F3.7:

- estruturar o pack operacional por `run_id` e por cenario;
- uniformizar nomes de ficheiros e estados `PASS` / `FAIL` /
  `INCONCLUSIVE` / `BLOCKED`;
- formalizar a evidencia minima e a evidencia complementar por cenario;
- disponibilizar um helper shell barato para exportar contexto do backend;
- disponibilizar um helper shell barato para recolher baseline e estado local
  do appliance;
- disponibilizar um template markdown para registo da execucao por cenario.

Complemento formal apos a F3.8:

- [`f3-gate-fechamento-validacao.md`](f3-gate-fechamento-validacao.md)
  passa a fixar o gate final de saida da F3;
- `docs/tests/templates/f3-validation-campaign-report.md` passa a ser o
  relatorio final unico da campanha;
- `scripts/license-validation/init-f3-validation-campaign.sh` passa a ser o
  helper shell opcional para materializar a directoria raiz da campanha.

Fora de escopo na F3.7:

- mexer em `activate`, `download`, `.lic`, daemon ou fingerprint;
- automatizar mudancas de relogio, snapshots, NIC, UUID ou rebind;
- criar suite E2E, CI/CD, observabilidade nova ou tooling pesado;
- declarar a F3 como substancialmente validada sem outputs reais.

---

## 1. Leitura factual da operacionalizacao actual

### 1.1 O que ja esta bom como runbook

Com base em `docs/01-architecture/f3-validacao-manual-evidencias.md`, no
backend actual e em `src/layer7d/license.c`, a F3.6 ja entrega:

- matriz canónica de cenarios obrigatorios/desejaveis;
- comandos objectivos de login, `activate`, download e queries SQL;
- criterios minimos de aprovacao/reprovacao por comportamento;
- leitura honesta do que e backend-only e do que exige appliance.

### 1.2 O que ainda estava disperso

Antes desta F3.7, a parte operacional ainda estava espalhada entre:

- F3.6 como matriz extensa, mas sem estrutura uniforme de ficheiros;
- `MANUAL-USO-LICENCAS.md` como manual de operacao, mas nao como pack;
- `docs/tests/test-matrix.md` como addendum, mas sem convencao de output;
- consultas SQL repetidas manualmente, sem helper barato para exportacao.

### 1.3 O que pode virar pack operacional sem risco

Pode ser padronizado sem tocar no produto:

- directoria unica por execucao (`run_id`);
- subdirectoria por cenario (`S01`, `S02`, ...);
- nomes fixos para outputs de backend, CLI, `.lic` e hashes;
- nomes fixos tambem para `headers`, `HTML` e `cookie jar` da trilha GUI
  autenticada do pfSense quando o `DR-05` usar esse caminho;
- template markdown unico para registo do resultado;
- script shell apenas para exportar `licenses`, `activations_log` e
  `admin_audit_log`.

### 1.4 O que continua necessariamente manual

Continuam manuais por natureza:

- activacao/re-activacao num appliance real;
- simulacao de offline e mudanca controlada de relogio;
- troca de NIC, UUID, clone, restore ou reinstall;
- captura do `.lic` local, dos hashes locais e do estado do daemon;
- classificacao final do cenario como `PASS`, `FAIL`, `INCONCLUSIVE` ou
  `BLOCKED`.

### 1.5 O que seria perigoso automatizar agora

Nao deve ser automatizado nesta fase:

- rollback de snapshots ou mudancas de relogio no appliance;
- rebind, desrevogacao ou revogacao offline;
- qualquer alteracao do contrato de `POST /api/activate`;
- qualquer automacao que escreva no estado do produto para “forcar” prova.

---

## 2. Estrutura oficial do pack operacional

### 2.1 Directoria sugerida

Directoria oficial sugerida para evidencias brutas:

```bash
${TMPDIR:-/tmp}/layer7-f3-evidence/<RUN_ID>/
```

Regra conservadora:

- nao versionar evidencias brutas no Git por defeito;
- guardar o pack bruto fora do repositório;
- so copiar resumos ou excertos pequenos para docs/changelog quando isso for
  realmente necessario.

### 2.2 Estrutura por cenario

```text
${TMPDIR:-/tmp}/layer7-f3-evidence/
└── <RUN_ID>/
    ├── 00-campaign-manifest.txt
    ├── f3-validation-campaign-report.md
    ├── S01/
    ├── S02/
    ├── S03/
    └── ...
```

### 2.3 Convencao de nomes

Cada cenario deve usar ficheiros fixos, com o minimo de liberdade possivel:

- `00-manifest.txt`
- `01-operator-notes.md`
- `10-backend-license.txt`
- `20-backend-activations-log.txt`
- `30-backend-admin-audit-log.txt`
- `40-http-response.txt`
- `50-appliance-cli.txt`
- `60-appliance-license.json`
- `70-local-hashes.txt`
- `80-extra-notes.txt`
- `41-gui-login-headers.txt`
- `42-gui-login.html`
- `43-gui-auth-headers.txt`
- `44-gui-auth.html`
- `45-gui-layer7-headers.txt`
- `46-gui-layer7.html`
- `47-gui-action-headers.txt`
- `48-gui-action.html`
- `49-gui-post-action-headers.txt`
- `50-gui-post-action.html`

Regra:

- nao renomear ad hoc por cenario;
- se um ficheiro nao se aplicar, deixar ausente e registar o motivo em
  `01-operator-notes.md`;
- se houver mais de uma captura do mesmo tipo, acrescentar sufixo curto e
  ordinal, por exemplo `50-appliance-cli-01.txt`.

### 2.4 `RUN_ID`

Formato sugerido:

```text
YYYYMMDDTHHMMSSZ-<ambiente-curto>
```

Exemplo:

```text
20260402T194500Z-lab-pfsense-a
```

### 2.5 Estados oficiais por cenario

- `PASS`: o comportamento observado bate com a expectativa e a evidencia
  minima foi recolhida.
- `FAIL`: o comportamento observado contradiz a expectativa ou revela
  regressao real.
- `INCONCLUSIVE`: o cenario correu parcialmente, mas falta evidencia minima
  ou o output ficou ambiguo.
- `BLOCKED`: o cenario nem chegou a ser executavel por falta de pre-requisito
  legitimo, janela, acesso, artefacto ou controlo de ambiente.

Regra conservadora:

- ausencia de evidencias minimas nunca vira `PASS`;
- ambiguidade vira `INCONCLUSIVE`, nao “meio PASS”;
- impedimento legitimo vira `BLOCKED`, nao “nao aplicavel”.

---

## 3. Tooling minimo aceite na F3.7

### 3.1 Helper shell

Script oficial desta subfase:

```text
scripts/license-validation/export-license-evidence.sh
```

Finalidade:

- exportar snapshot da licenca;
- exportar `activations_log`;
- exportar `admin_audit_log`;
- criar `00-manifest.txt`;
- copiar o template base para `01-operator-notes.md`.

Limites do helper:

- nao executa `activate`;
- nao altera relogio nem appliance;
- nao chama endpoints de mutacao;
- nao muda schema, contrato ou dados do produto.

Helper complementar de appliance:

```text
scripts/license-validation/export-appliance-evidence.sh
```

Finalidade:

- recolher baseline local do appliance por SSH;
- guardar `service layer7d status`, `kern.hostuuid`, `ifconfig -a`,
  `layer7d --fingerprint` e stats JSON no ficheiro canónico de CLI;
- registar usuario efectivo, permissoes do `.lic`/pidfile e processo real do
  `layer7d`, para distinguir daemon parado de falso negativo por permissao;
- exportar o `.lic` local em formato legivel quando existir;
- guardar hash local do `.lic` e a origem do stats JSON usada na recolha.

Limites do helper:

- nao executa activacao nem mutacao administrativa;
- nao altera relogio, NIC, UUID, `.lic` ou configuracao;
- nao substitui snapshot, janela de manutencao ou leitura humana do cenario.
- com `--update-root-preflight`, apenas consolida a baseline recolhida no
  artefacto raiz `40-preflight-appliance.txt`; continua sem validar o
  preflight por si so.

Helper complementar da trilha GUI autenticada:

```text
scripts/license-validation/run-pfsense-gui-license-flow.sh
```

Finalidade:

- abrir a login page da GUI do pfSense;
- recolher `PHPSESSID` e `__csrf_magic` no mesmo fluxo real;
- confirmar acesso autenticado a `layer7_settings.php`;
- materializar `probe`, `register` ou `revoke` com evidencias por `run_id`,
  inclusive em modo `--ssh-target` quando a GUI util so responder em
  `https://127.0.0.1:9999/` no proprio appliance.

Evidencias tipicas desta trilha:

- `40-gui-cookie-jar.txt`
- `41-gui-login-headers.txt`
- `42-gui-login.html`
- `43-gui-auth-headers.txt`
- `44-gui-auth.html`
- `45-gui-layer7-headers.txt`
- `46-gui-layer7.html`
- `47-gui-action-headers.txt`
- `48-gui-action.html`
- `49-gui-post-action-headers.txt`
- `50-gui-post-action.html`
- `39-gui-flow-notes.txt`

Limites do helper:

- nao inventa credencial, cookie ou sessao;
- nao substitui autorizacao humana legitima da GUI;
- se a trilha devolver login page, `403 CSRF Error` ou sessao incoerente,
  o resultado correcto continua a ser `BLOCKED`.

Helper complementar de live preflight:

```text
scripts/license-validation/export-live-preflight.sh
```

Finalidade:

- recolher health publico e health do origin observado por `curl`;
- guardar probes minimos de origin/CORS/login no artefacto raiz da campanha;
- preencher `10-preflight-deploy.txt` e `30-preflight-admin.txt` com output
  bruto, sem depender de copia manual da consola.

Limites do helper:

- nao prova schema nem revisao Git do deploy por si so;
- nao substitui acesso SSH/DB ao host live;
- se o live divergir do contrato canónico, apenas regista o desvio.

Helper complementar de schema preflight:

```text
scripts/license-validation/export-schema-preflight.sh
```

Finalidade:

- consultar o PostgreSQL observado via `docker compose exec` read-only;
- materializar `20-preflight-schema.txt` com identidade da base, tabelas
  exigidas, contagem minima e colunas administrativas;
- reduzir copia manual de queries do preflight da F3.10.

Limites do helper:

- depende de acesso legitimo ao host/directorio real da stack;
- falha legitimamente se o schema live estiver incompleto;
- nao substitui classificacao humana do drift observado.

Helper orquestrador de preflight:

```text
scripts/license-validation/prepare-f3-preflight.sh
```

Finalidade:

- inicializar a campanha e encadear, no mesmo `run_id`, os helpers de
  estrutura, live, schema e appliance;
- reduzir cola manual entre scripts antes da abertura real da F3.11;
- permitir preflight parcial quando apenas alguns acessos estiverem
  disponiveis, registando explicitamente o que foi ignorado.

Limites do helper:

- nao inventa acessos nem credenciais ausentes;
- nao substitui a decisao humana de `GO/NO-GO`;
- se um helper subjacente falhar, a campanha continua a exigir triagem humana
  do blocker e do drift observado.

Helper opcional de campanha apos a F3.8:

```text
scripts/license-validation/init-f3-validation-campaign.sh
```

Finalidade:

- criar a directoria raiz da campanha por `run_id`;
- copiar o relatorio final canónico da campanha;
- materializar os artefactos raiz de preflight exigidos pela F3.10;
- preparar directoria/manifest minimo por cenario.

### 3.2 Template markdown

Template oficial por cenario:

```text
docs/tests/templates/f3-scenario-evidence.md
```

Uso:

- copiar para `01-operator-notes.md`;
- preencher operador, ambiente, pre-requisitos, ficheiros anexos e resultado;
- manter a decisao final explicita.

---

## 4. Politica oficial de recolha e leitura de evidencias

### 4.1 Regras de recolha

1. Recolher **output bruto primeiro** e interpretar depois.
2. Guardar backend e appliance no mesmo directório do cenario.
3. Manter o `RUN_ID` igual em todos os cenarios do mesmo bloco.
4. Re-exportar evidencias de backend apos cada mutacao relevante.
5. Registar em `01-operator-notes.md` qualquer desvio ao comando canónico.

### 4.2 Evidencia minima obrigatoria

Para considerar um cenario executado:

- comando exacto ou resposta exacta guardada;
- snapshot de backend pertinente ao mesmo `license_id`;
- estado do appliance quando o cenario nao e backend-only;
- classificacao final com justificacao curta.

### 4.3 Evidencia complementar desejavel

- hashes locais de artefactos;
- copia formatada do `.lic`;
- capturas adicionais de `layer7-stats.json`;
- notas de relogio, VM, NIC ou UUID quando o ambiente variou.

### 4.4 Politica de retencao

Politica conservadora da F3:

- nao apagar o pack bruto antes da decisao formal sobre o fecho da F3;
- se houver `FAIL` ou `INCONCLUSIVE`, preservar o pack completo ate a
  correcao ou o registo formal de pendencia;
- se houver `PASS`, manter pelo menos ate ao checkpoint que decidir o fecho
  honesto da F3.

---

## 5. Pre-requisitos comuns

### 5.1 Variaveis base

```bash
export L7_BASE_URL='https://license.systemup.inf.br'
export COOKIE_JAR='/tmp/layer7-license.cookies.txt'
export ADMIN_EMAIL='admin@systemup.inf.br'
export ADMIN_PASSWORD='substituir_por_segredo_real'
export LICENSE_ID='<LICENSE_ID>'
export LICENSE_KEY='<LICENSE_KEY_32_HEX>'
export ALT_CUSTOMER_ID='<OUTRO_CUSTOMER_ID_EXISTENTE>'
export PF_SSH='ssh root@<PFSENSE_IP>'
export RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-lab-pfsense-a"
export EVIDENCE_ROOT="${TMPDIR:-/tmp}/layer7-f3-evidence"
```

### 5.2 Login administrativo

```bash
rm -f "$COOKIE_JAR"
curl -sS -c "$COOKIE_JAR" "$L7_BASE_URL/api/auth/login" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}"
```

### 5.3 Exportacao base de backend

Exemplo:

```bash
./scripts/license-validation/export-license-evidence.sh \
  --license-id "$LICENSE_ID" \
  --scenario-code S01 \
  --run-id "$RUN_ID" \
  --output-root "$EVIDENCE_ROOT"

./scripts/license-validation/export-appliance-evidence.sh \
  --scenario-code S01 \
  --run-id "$RUN_ID" \
  --output-root "$EVIDENCE_ROOT" \
  --ssh-target root@192.168.100.254 \
  --update-root-preflight

./scripts/license-validation/export-live-preflight.sh \
  --run-id "$RUN_ID" \
  --output-root "$EVIDENCE_ROOT" \
  --base-url "$L7_BASE_URL" \
  --origin-url "http://192.168.100.244:8445" \
  --admin-email "$ADMIN_EMAIL" \
  --admin-password "$ADMIN_PASSWORD" \
  --curl-insecure

./scripts/license-validation/export-schema-preflight.sh \
  --run-id "$RUN_ID" \
  --output-root "$EVIDENCE_ROOT" \
  --compose-dir /opt/layer7-license

./scripts/license-validation/prepare-f3-preflight.sh \
  --run-id "$RUN_ID" \
  --output-root "$EVIDENCE_ROOT" \
  --base-url "$L7_BASE_URL" \
  --origin-url "http://192.168.100.244:8445" \
  --admin-email "$ADMIN_EMAIL" \
  --admin-password "$ADMIN_PASSWORD" \
  --curl-insecure \
  --compose-dir /opt/layer7-license \
  --ssh-target root@192.168.100.254
```

Isto cria:

```text
$EVIDENCE_ROOT/$RUN_ID/S01/00-manifest.txt
$EVIDENCE_ROOT/$RUN_ID/S01/01-operator-notes.md
$EVIDENCE_ROOT/$RUN_ID/S01/10-backend-license.txt
$EVIDENCE_ROOT/$RUN_ID/S01/20-backend-activations-log.txt
$EVIDENCE_ROOT/$RUN_ID/S01/30-backend-admin-audit-log.txt
```

### 5.4 Capturas locais frequentes

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S01"

$PF_SSH "layer7d --activate $LICENSE_KEY" \
  | tee "$SCENARIO_DIR/50-appliance-cli.txt"

$PF_SSH 'cat /usr/local/etc/layer7.lic | python3 -m json.tool' \
  | tee "$SCENARIO_DIR/60-appliance-license.json"

$PF_SSH 'sha256 /usr/local/etc/layer7.lic' \
  | tee "$SCENARIO_DIR/70-local-hashes.txt"

$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json' \
  | tee -a "$SCENARIO_DIR/50-appliance-cli.txt"
```

### 5.5 Helper orquestrador para cenarios do appliance

Quando o objectivo for executar um cenario de activacao no pfSense com
snapshot antes/depois do backend e baseline local no mesmo `run_id`, pode-se
usar o helper:

```bash
./scripts/license-validation/run-appliance-activation-scenario.sh \
  --scenario-code S01 \
  --run-id "$RUN_ID" \
  --output-root "$EVIDENCE_ROOT" \
  --license-id "$LICENSE_ID" \
  --license-key "$LICENSE_KEY" \
  --compose-dir "$COMPOSE_DIR" \
  --ssh-target "$PF_SSH_TARGET"
```

Para cenarios como `S07`, em que o `.lic` local deve ser removido antes da
tentativa, acrescentar `--clear-local-license`.

O helper:

- exporta evidencia do backend antes do passo local;
- executa `layer7d --activate` no appliance quando aplicavel;
- recolhe baseline local via `export-appliance-evidence.sh`;
- exporta evidencia do backend depois do passo local.

---

## 6. Matriz operacional executavel dos cenarios obrigatorios

## S01 — Activacao inicial valida

- **Pre-requisitos:** licenca activa, sem `hardware_id`, appliance online e
  sem `.lic` local valido.
- **Comandos exactos:**

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S01"
./scripts/license-validation/export-license-evidence.sh --license-id "$LICENSE_ID" --scenario-code S01 --run-id "$RUN_ID" --output-root "$EVIDENCE_ROOT"
$PF_SSH "layer7d --activate $LICENSE_KEY" | tee "$SCENARIO_DIR/50-appliance-cli.txt"
$PF_SSH 'cat /usr/local/etc/layer7.lic | python3 -m json.tool' | tee "$SCENARIO_DIR/60-appliance-license.json"
./scripts/license-validation/export-license-evidence.sh --license-id "$LICENSE_ID" --scenario-code S01 --run-id "$RUN_ID" --output-root "$EVIDENCE_ROOT"
```

- **Outputs a guardar:** CLI de activacao, `.lic` formatado, snapshot de
  backend, `activations_log`, `admin_audit_log`.
- **Nomes sugeridos:** `10-backend-license.txt`,
  `20-backend-activations-log.txt`, `30-backend-admin-audit-log.txt`,
  `50-appliance-cli.txt`, `60-appliance-license.json`.
- **PASS:** activacao bem-sucedida, bind preenchido, `activated_at`
  preenchido, `license_artifact_issued` com `initial_issue`.
- **FAIL:** HTTP/CLI falha, bind nao fica persistido ou auditoria/activacao
  nao aparecem.
- **INCONCLUSIVE:** activacao parece boa mas falta output bruto minimo.
- **BLOCKED:** licenca ja estava bindada, appliance indisponivel ou acesso
  administrativo inexistente.

## S02 — Re-activacao legitima do mesmo hardware

- **Pre-requisitos:** `S01` concluido, mesmo appliance e mesma licenca.
- **Comandos exactos:**

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S02"
./scripts/license-validation/export-license-evidence.sh --license-id "$LICENSE_ID" --scenario-code S02 --run-id "$RUN_ID" --output-root "$EVIDENCE_ROOT"
$PF_SSH "layer7d --activate $LICENSE_KEY" | tee "$SCENARIO_DIR/50-appliance-cli.txt"
./scripts/license-validation/export-license-evidence.sh --license-id "$LICENSE_ID" --scenario-code S02 --run-id "$RUN_ID" --output-root "$EVIDENCE_ROOT"
```

- **Outputs a guardar:** CLI, snapshot de backend, `activations_log`,
  auditoria com `reactivation_reissue`.
- **Nomes sugeridos:** `10-backend-license.txt`,
  `20-backend-activations-log.txt`, `30-backend-admin-audit-log.txt`,
  `50-appliance-cli.txt`.
- **PASS:** re-activacao bem-sucedida, mesmo `hardware_id`, `activated_at`
  preservado, evento `reactivation_reissue`.
- **FAIL:** novo bind, erro indevido ou ausencia do rasto esperado.
- **INCONCLUSIVE:** re-activacao ocorre, mas sem comparacao com o estado
  anterior.
- **BLOCKED:** appliance ou licenca ja nao correspondem ao bind original.

## S03 — Activacao com hardware diferente para licenca ja bindada

- **Pre-requisitos:** licenca ja bindada, segundo hardware ou `hardware_id`
  deliberadamente diferente.
- **Comandos exactos:**

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S03"
curl -si -X POST "$L7_BASE_URL/api/activate" \
  -H 'Content-Type: application/json' \
  -d "{\"key\":\"$LICENSE_KEY\",\"hardware_id\":\"1111111111111111111111111111111111111111111111111111111111111111\"}" \
  | tee "$SCENARIO_DIR/40-http-response.txt"
./scripts/license-validation/export-license-evidence.sh --license-id "$LICENSE_ID" --scenario-code S03 --run-id "$RUN_ID" --output-root "$EVIDENCE_ROOT"
```

- **Outputs a guardar:** resposta HTTP crua, snapshot de backend,
  `activations_log`.
- **Nomes sugeridos:** `40-http-response.txt`,
  `10-backend-license.txt`, `20-backend-activations-log.txt`,
  `30-backend-admin-audit-log.txt`.
- **PASS:** `409`, `activations_log.result='fail'`, bind original inalterado.
- **FAIL:** activacao indevida, alteracao do bind ou resposta incoerente.
- **INCONCLUSIVE:** resposta nao guardada integralmente ou licenca estava
  noutro estado.
- **BLOCKED:** nao existe licenca bindada valida para o teste.

## S04 — Download administrativo de licenca bindada

- **Pre-requisitos:** licenca activa e bindada; sessao administrativa valida.
- **Comandos exactos:**

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S04"
curl -sS -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID/download" \
  -o "$SCENARIO_DIR/40-http-response.txt"
sha256 "$SCENARIO_DIR/40-http-response.txt" | tee "$SCENARIO_DIR/70-local-hashes.txt"
./scripts/license-validation/export-license-evidence.sh --license-id "$LICENSE_ID" --scenario-code S04 --run-id "$RUN_ID" --output-root "$EVIDENCE_ROOT"
```

- **Outputs a guardar:** artefacto descarregado, hash local, auditoria do
  download.
- **Nomes sugeridos:** `40-http-response.txt`, `70-local-hashes.txt`,
  `10-backend-license.txt`, `30-backend-admin-audit-log.txt`.
- **PASS:** download bem-sucedido, resposta em `{ data, sig }`, auditoria
  `license_downloaded` com `admin_download_reissue`.
- **FAIL:** download negado sem motivo, resposta invalida ou auditoria
  ausente.
- **INCONCLUSIVE:** ficheiro descarregado sem hash ou sem snapshot de backend.
- **BLOCKED:** sessao administrativa invalida ou licenca nao bindada.

## S05 — Mutacao permitida de `expiry` e reemissao

- **Pre-requisitos:** licenca activa e bindada; nova data de `expiry`
  definida; acesso administrativo.
- **Comandos exactos:**

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S05"
curl -si -X PUT -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID" \
  -H 'Content-Type: application/json' \
  -d '{"expiry":"2030-12-31"}' \
  | tee "$SCENARIO_DIR/40-http-response.txt"
curl -sS -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID/download" \
  -o "$SCENARIO_DIR/40-http-response-01.txt"
sha256 "$SCENARIO_DIR/40-http-response-01.txt" | tee "$SCENARIO_DIR/70-local-hashes.txt"
./scripts/license-validation/export-license-evidence.sh --license-id "$LICENSE_ID" --scenario-code S05 --run-id "$RUN_ID" --output-root "$EVIDENCE_ROOT"
```

- **Outputs a guardar:** `PUT`, novo download, hash local, snapshot de
  backend e auditoria.
- **Nomes sugeridos:** `40-http-response.txt`,
  `40-http-response-01.txt`, `70-local-hashes.txt`,
  `10-backend-license.txt`, `30-backend-admin-audit-log.txt`.
- **PASS:** `expiry` muda, bind fica preservado, download/reativacao do mesmo
  hardware continuam possiveis.
- **FAIL:** bind muda, `PUT` e aceite de forma incoerente ou download falha
  indevidamente.
- **INCONCLUSIVE:** `PUT` sucede mas nao ha evidencia da reemissao.
- **BLOCKED:** licenca sem permissao de mutacao ou sem sessao valida.

## S06 — Tentativa de mudar `customer_id` em licenca bindada

- **Pre-requisitos:** licenca activa/bindada e `customer_id` alternativo
  existente.
- **Comandos exactos:**

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S06"
curl -si -X PUT -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID" \
  -H 'Content-Type: application/json' \
  -d "{\"customer_id\":$ALT_CUSTOMER_ID}" \
  | tee "$SCENARIO_DIR/40-http-response.txt"
./scripts/license-validation/export-license-evidence.sh --license-id "$LICENSE_ID" --scenario-code S06 --run-id "$RUN_ID" --output-root "$EVIDENCE_ROOT"
```

- **Outputs a guardar:** resposta do `PUT`, snapshot de backend, auditoria.
- **Nomes sugeridos:** `40-http-response.txt`,
  `10-backend-license.txt`, `30-backend-admin-audit-log.txt`.
- **PASS:** `409`, `customer_id` persistido inalterado, evento
  `license_update_denied`.
- **FAIL:** update aceite ou ownership alterado.
- **INCONCLUSIVE:** falha por payload invalido sem provar o guardrail do bind.
- **BLOCKED:** licenca ainda nao bindada ou cliente alvo inexistente quando o
  teste exige um cliente real.

## S07 — Licenca expirada no backend sem `.lic` local

- **Pre-requisitos:** licenca com `expiry` no passado; appliance sem `.lic`
  local valido.
- **Comandos exactos:**

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S07"
$PF_SSH 'rm -f /usr/local/etc/layer7.lic'
$PF_SSH "layer7d --activate $LICENSE_KEY" | tee "$SCENARIO_DIR/50-appliance-cli.txt"
./scripts/license-validation/export-license-evidence.sh --license-id "$LICENSE_ID" --scenario-code S07 --run-id "$RUN_ID" --output-root "$EVIDENCE_ROOT"
```

- **Outputs a guardar:** CLI do appliance, snapshot de backend,
  `activations_log`.
- **Nomes sugeridos:** `50-appliance-cli.txt`,
  `10-backend-license.txt`, `20-backend-activations-log.txt`.
- **PASS:** activacao falha fechada, nenhum `.lic` novo e estado efectivo
  `expired`.
- **FAIL:** activacao nova e aceite ou `.lic` reaparece indevidamente.
- **INCONCLUSIVE:** expiracao nao ficou demonstrada no backend.
- **BLOCKED:** nao ha janela segura para apagar o `.lic` local.

## S08 — Licenca expirada no backend com `.lic` local ainda dentro da grace

- **Pre-requisitos:** `.lic` antigo ainda presente; backend expirado; relogio
  local controlado dentro de `expiry + 14 dias`.
- **Comandos exactos:**

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S08"
$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json' \
  | tee "$SCENARIO_DIR/50-appliance-cli.txt"
$PF_SSH 'cat /usr/local/etc/layer7.lic | python3 -m json.tool' \
  | tee "$SCENARIO_DIR/60-appliance-license.json"
./scripts/license-validation/export-license-evidence.sh --license-id "$LICENSE_ID" --scenario-code S08 --run-id "$RUN_ID" --output-root "$EVIDENCE_ROOT"
```

- **Outputs a guardar:** stats locais, `.lic` local, snapshot do backend.
- **Nomes sugeridos:** `50-appliance-cli.txt`,
  `60-appliance-license.json`, `10-backend-license.txt`.
- **PASS:** backend `expired`, appliance com `license_valid=true` e
  `license_grace=true`.
- **FAIL:** appliance invalida antes do fim da grace ou backend nao reflecte
  expiracao.
- **INCONCLUSIVE:** relogio local nao ficou registado ou stats nao ficaram
  capturados.
- **BLOCKED:** sem controlo de relogio/data ou sem `.lic` previo.

## S09 — Licenca revogada no backend com `.lic` antigo offline

- **Pre-requisitos:** licenca ja emitida no appliance; revogacao feita no
  backend; appliance isolado da rede.
- **Comandos exactos:**

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S09"
curl -si -X POST -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID/revoke" \
  | tee "$SCENARIO_DIR/40-http-response.txt"
curl -si -b "$COOKIE_JAR" "$L7_BASE_URL/api/licenses/$LICENSE_ID/download" \
  | tee "$SCENARIO_DIR/40-http-response-01.txt"
$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json' \
  | tee "$SCENARIO_DIR/50-appliance-cli.txt"
./scripts/license-validation/export-license-evidence.sh --license-id "$LICENSE_ID" --scenario-code S09 --run-id "$RUN_ID" --output-root "$EVIDENCE_ROOT"
```

- **Outputs a guardar:** resposta de revogacao, tentativa de download negado,
  stats locais offline, snapshot de backend, auditoria.
- **Nomes sugeridos:** `40-http-response.txt`,
  `40-http-response-01.txt`, `50-appliance-cli.txt`, `10-backend-license.txt`,
  `30-backend-admin-audit-log.txt`.
- **PASS:** backend passa a revogado, novas activacoes/downloads falham, mas o
  appliance offline com `.lic` antigo continua consistente com o limite
  documentado.
- **FAIL:** revogacao nao persiste, novos downloads continuam abertos ou
  offline se comporta fora do contrato actual.
- **INCONCLUSIVE:** nao ficou provado que o appliance estava mesmo offline.
- **BLOCKED:** impossibilidade de isolar o appliance ou de revogar a licenca.

## S11 — Coexistencia de artefacto antigo e artefacto novo

- **Pre-requisitos:** pelo menos dois artefactos da mesma licenca obtidos em
  momentos diferentes; appliance disponivel.
- **Comandos exactos:**

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S11"
sha256 /caminho/para/artefacto-antigo.lic | tee "$SCENARIO_DIR/70-local-hashes.txt"
sha256 /caminho/para/artefacto-novo.lic | tee -a "$SCENARIO_DIR/70-local-hashes.txt"
./scripts/license-validation/export-license-evidence.sh --license-id "$LICENSE_ID" --scenario-code S11 --run-id "$RUN_ID" --output-root "$EVIDENCE_ROOT"
```

- **Outputs a guardar:** hashes dos dois ficheiros, auditoria correspondente,
  stats do appliance com o artefacto instalado quando aplicavel.
- **Nomes sugeridos:** `70-local-hashes.txt`,
  `10-backend-license.txt`, `30-backend-admin-audit-log.txt`,
  `50-appliance-cli.txt`.
- **PASS:** coexistencia fica demonstrada e coerente com a auditoria; nao ha
  “latest only” consumido pelo daemon.
- **FAIL:** evidencia contradiz a trilha auditada ou o comportamento local.
- **INCONCLUSIVE:** apenas um artefacto ficou preservado ou faltam hashes.
- **BLOCKED:** artefacto antigo ja nao existe para comparacao.

## S12 — Appliance offline antes e depois do grace

- **Pre-requisitos:** `.lic` local emitido; controlo de data/relogio; modo
  offline real.
- **Comandos exactos:**

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S12"
$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json' \
  | tee "$SCENARIO_DIR/50-appliance-cli-01.txt"
# ajustar relogio para dentro da grace
$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json' \
  | tee "$SCENARIO_DIR/50-appliance-cli-02.txt"
# ajustar relogio para depois da grace
$PF_SSH '/bin/kill -USR1 "$(cat /var/run/layer7d.pid)" && cat /tmp/layer7-stats.json' \
  | tee "$SCENARIO_DIR/50-appliance-cli-03.txt"
```

- **Outputs a guardar:** tres snapshots locais, notas de relogio em
  `01-operator-notes.md`, `.lic` local.
- **Nomes sugeridos:** `50-appliance-cli-01.txt`,
  `50-appliance-cli-02.txt`, `50-appliance-cli-03.txt`,
  `60-appliance-license.json`.
- **PASS:** antes/dentro da grace continua valido; depois da grace cai para o
  comportamento degradado esperado.
- **FAIL:** transicao local contradiz o contrato de grace.
- **INCONCLUSIVE:** relogio nao ficou registado com clareza.
- **BLOCKED:** ambiente sem controlo de data ou sem janela para isolar a
  maquina.

## S13 — Divergencia de fingerprint por mudanca de NIC/UUID

- **Pre-requisitos:** ambiente que permita observar `kern.hostuuid`,
  interfaces e fingerprint antes/depois da mudanca.
- **Comandos exactos:**

```bash
SCENARIO_DIR="$EVIDENCE_ROOT/$RUN_ID/S13"
$PF_SSH 'sysctl -n kern.hostuuid && ifconfig && layer7d --fingerprint' \
  | tee "$SCENARIO_DIR/50-appliance-cli-01.txt"
# aplicar mudanca controlada de NIC, UUID, clone ou restore
$PF_SSH 'sysctl -n kern.hostuuid && ifconfig && layer7d --fingerprint' \
  | tee "$SCENARIO_DIR/50-appliance-cli-02.txt"
```

- **Outputs a guardar:** `kern.hostuuid`, `ifconfig`, fingerprint antes/depois
  e tentativa online se houve drift.
- **Nomes sugeridos:** `50-appliance-cli-01.txt`,
  `50-appliance-cli-02.txt`, `40-http-response.txt`.
- **PASS:** drift ou estabilidade observados batem com a matriz da F3.2/F3.6.
- **FAIL:** resultado contradiz a formula documentada ou gera bind inesperado.
- **INCONCLUSIVE:** mudanca de ambiente nao ficou isolada ou nao se sabe qual
  atributo mudou.
- **BLOCKED:** sem capacidade real de mudar NIC/UUID/VM de forma controlada.

---

## 7. Checklist operacional rapido por cenario

Para cada cenario obrigatorio:

1. Definir `RUN_ID` e `SCENARIO_DIR`.
2. Exportar snapshot inicial do backend.
3. Executar o comando canónico do cenario.
4. Guardar outputs nos nomes padronizados.
5. Re-exportar snapshot final do backend.
6. Preencher `01-operator-notes.md`.
7. Marcar `PASS`, `FAIL`, `INCONCLUSIVE` ou `BLOCKED`.

---

## 8. O que continua pendente apos a F3.7

- execucao real dos cenarios obrigatorios em lab/appliance;
- recolha de outputs reais com o pack agora padronizado;
- decisao formal sobre o fecho honesto da F3 com base nas evidencias;
- qualquer melhoria futura de revogacao offline, rebind ou “latest only”.

---

## 9. Itens fora de escopo

- feature nova de licenciamento;
- mudanca de formato `.lic`;
- mudanca do algoritmo de fingerprint;
- heartbeat online;
- automacao de snapshots, relojio ou hypervisor;
- qualquer abertura de F4/F5/F6/F7.

---

## 10. Proximos passos seguros depois da F3.7

1. Executar o pack da F3.7 num ambiente controlado, sem misturar alteracoes
   de produto.
2. Anexar evidencias brutas por `RUN_ID` e por cenario.
3. Consolidar quais cenarios obrigatorios fecharam em `PASS`, `FAIL`,
   `INCONCLUSIVE` ou `BLOCKED`.
4. So depois decidir se a F3 pode ser tratada como substancialmente validada
   ou se ainda precisa de uma subfase conservadora adicional.
