# Runbook - F3.11 Live Access Checklist

## Finalidade

Este runbook e o checklist sequencial minimo para usar **depois** que os
acessos forem entregues de forma legitimada.

Escopo deste runbook:

- confirmar host, directorio real e compose efectiva do ambiente live;
- confirmar revisao do deploy observado;
- confirmar schema e tabelas administrativas no PostgreSQL live;
- confirmar credencial admin no fluxo oficial de sessao;
- confirmar appliance pfSense, baseline e controlos do lab;
- confirmar a trilha legitima autenticada da GUI do pacote no pfSense para
  cenarios mutaveis;
- confirmar inventario real `LIC-A` a `LIC-F`;
- repetir a readiness da F3.11 com base em ambiente e cenario reais.

Nao faz parte deste runbook:

- corrigir deploy;
- alterar codigo;
- abrir campanha sem `go`;
- inventar path, host de appliance ou inventario.

---

## 1. Variaveis a preencher antes da execucao

Estas variaveis so podem ser preenchidas com valores **reais** e
autorizados:

```bash
export L7_BASE_URL='https://license.systemup.inf.br'
export L7_LIVE_HOST='192.168.100.244'
export L7_SERVER_DIR='<DIRECTORIO_REAL_OBSERVADO>'
export PG_READONLY_CMD='<COMANDO_READ_ONLY_PSQL_OU_DOCKER_EXEC_AUTORIZADO>'
export ADMIN_EMAIL='<ADMIN_EMAIL_AUTORIZADO>'
export ADMIN_PASSWORD='<ADMIN_PASSWORD_AUTORIZADA>'
export COOKIE_JAR="$(mktemp)"
export PFSENSE_HOST='<HOST_OU_IP_REAL_DO_APPLIANCE>'
export PFSENSE_GUI_BASE='https://<HOST_OU_IP_REAL_DO_APPLIANCE>:9999'
export PFSENSE_GUI_COOKIE_JAR="$(mktemp)"
export PFSENSE_GUI_LOGIN_HTML="$(mktemp)"
export PFSENSE_GUI_LAYER7_HTML="$(mktemp)"
export PFSENSE_GUI_USER='<UTILIZADOR_GUI_AUTORIZADO>'
export PFSENSE_GUI_PASSWORD='<PASSWORD_GUI_AUTORIZADA>'
export PFSENSE_CONTROL_EVIDENCE='<ARTEFACTO_REAL_COM_SNAPSHOT_E_CONTROLOS>'
export LIC_A_ID='<ID_REAL>'
export LIC_B_ID='<ID_REAL>'
export LIC_C_ID='<ID_REAL>'
export LIC_D_ID='<ID_REAL>'
export LIC_E_ID='<ID_REAL>'
export LIC_F_ID='<ID_REAL>'
export S03_LICENSE_KEY='<LICENSE_KEY_REAL_JA_BINDADA_PARA_O_CONTROLO_S03>'
export ALT_HARDWARE_ID='<HARDWARE_ID_ALTERNATIVO_REAL_PARA_S03>'
```

**Go/no-go inicial:** se qualquer variavel acima continuar em placeholder, o
runbook deve parar antes do passo 1.

---

## 2. Checklist sequencial

### 1. Confirmar host e directorio real da stack

- **Objectivo:** provar que o host observado e acessivel e que o directorio
  real da stack foi identificado sem adivinhacao.
- **Comandos:**

```bash
ssh root@"$L7_LIVE_HOST" '
  hostname -f || hostname
  docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Ports}}"
  docker compose ls 2>/dev/null || true
  find /opt /srv /root -maxdepth 3 -type f \( -name docker-compose.yml -o -name compose.yml \) 2>/dev/null | rg "layer7|license"
'
```

- **Saida esperada:** SSH bem sucedido, hostname real, containers/compose
  observaveis e pelo menos um caminho candidato para a stack.
- **Se falhar:** `NO-GO`. Nao assumir directorio, nao continuar para Git,
  Docker ou PostgreSQL.

### 2. Confirmar revisao live e compose efectiva

- **Objectivo:** provar revisao observada e stack efectivamente activa no
  directorio real.
- **Comandos:**

```bash
ssh root@"$L7_LIVE_HOST" "
  cd '$L7_SERVER_DIR' &&
  pwd &&
  git rev-parse HEAD &&
  git status --short --branch &&
  docker compose ps &&
  docker compose config --services
"
```

- **Saida esperada:** `pwd` igual ao directorio real, `git rev-parse HEAD`
  com hash observavel, estado do branch visivel e servicos `docker compose`
  coerentes com a stack Layer7.
- **Se falhar:** `NO-GO`. O live continua sem revisao provada e a F3.11 nao
  pode abrir.

### 3. Confirmar schema live no PostgreSQL

- **Objectivo:** provar identidade da base e existencia das tabelas minimas do
  contrato canonico.
- **Comandos:**

```bash
$PG_READONLY_CMD -c "SELECT current_database(), current_user, inet_server_addr(), inet_server_port();"
$PG_READONLY_CMD -c "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('licenses','activations_log','admin_sessions','admin_audit_log','admin_login_guards') ORDER BY 1;"
```

- **Saida esperada:** base/utilizador identificados e retorno com as cinco
  tabelas listadas.
- **Se falhar:** `NO-GO`. O schema live continua sem prova suficiente.

### 4. Confirmar existencia real de `admin_sessions`, `admin_audit_log` e `admin_login_guards`

- **Objectivo:** provar que as tabelas administrativas existem e sao
  consultaveis no ambiente real.
- **Comandos:**

```bash
$PG_READONLY_CMD -c "SELECT 'admin_sessions' AS table_name, count(*)::bigint AS row_count FROM admin_sessions UNION ALL SELECT 'admin_audit_log', count(*)::bigint FROM admin_audit_log UNION ALL SELECT 'admin_login_guards', count(*)::bigint FROM admin_login_guards;"
$PG_READONLY_CMD -c "SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name IN ('admin_sessions','admin_audit_log','admin_login_guards') ORDER BY table_name, ordinal_position;"
```

- **Saida esperada:** tres linhas de `row_count` e lista de colunas sem
  `permission denied` nem `relation does not exist`.
- **Se falhar:** `NO-GO`. O drift de schema/admin continua aberto.

### 5. Confirmar credencial admin e login real

- **Objectivo:** provar credencial autorizada no fluxo oficial de sessao.
- **Comandos:**

```bash
rm -f "$COOKIE_JAR"
curl -k -si --max-time 15 -c "$COOKIE_JAR" \
  "$L7_BASE_URL/api/auth/login" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}"

curl -k -si --max-time 15 -b "$COOKIE_JAR" \
  "$L7_BASE_URL/api/auth/session"
```

- **Saida esperada:** login bem sucedido, `Set-Cookie` com
  `layer7_admin_session` e sessao resolvida em `/api/auth/session`.
- **Se falhar:** `NO-GO`. Sem sessao valida, a metade administrativa da F3.11
  continua fechada.

### 6. Confirmar pfSense via SSH, baseline e control plane legitimo

- **Objectivo:** provar appliance real, baseline recolhivel e existencia de
  controlos do lab, sem assumir acesso mutavel onde ele nao existir.
- **Comandos:**

```bash
test -f "$PFSENSE_CONTROL_EVIDENCE" && sed -n '1,160p' "$PFSENSE_CONTROL_EVIDENCE"

ssh root@"$PFSENSE_HOST" '
  hostname
  date -u
  sysctl -n kern.hostuuid
  ifconfig -a
  service layer7d status
  /usr/local/sbin/layer7d --fingerprint
  test -f /usr/local/etc/layer7.lic && sha256 /usr/local/etc/layer7.lic || echo no_local_license
  test -f /var/db/layer7/layer7-stats.json && cat /var/db/layer7/layer7-stats.json || echo no_stats_json
'
```

- **Saida esperada:** artefacto de controlos legivel, SSH funcional,
  fingerprint real, `kern.hostuuid`, estado do servico e baseline local
  recolhidos.
- **Se falhar:** `NO-GO` para S01/S02/S07/S08/S09/S11/S12/S13. Sem baseline
  e sem controlos, a campanha nao abre.

### 7. Confirmar a trilha autenticada da GUI do pacote no pfSense

- **Objectivo:** provar que existe sessao GUI autorizada e utilizavel para os
  cenarios mutaveis que dependem de `register_license` / `revoke_license`.
- **Comandos:**

```bash
rm -f "$PFSENSE_GUI_COOKIE_JAR" "$PFSENSE_GUI_LOGIN_HTML" "$PFSENSE_GUI_LAYER7_HTML"

curl -k -sS --max-time 15 \
  -c "$PFSENSE_GUI_COOKIE_JAR" \
  "$PFSENSE_GUI_BASE/" >"$PFSENSE_GUI_LOGIN_HTML"

export PFSENSE_GUI_CSRF_TOKEN="$(sed -n 's/.*csrfMagicToken = \"\\([^\"]*\\)\".*/\\1/p' "$PFSENSE_GUI_LOGIN_HTML" | head -n1)"

test -n "$PFSENSE_GUI_CSRF_TOKEN"

curl -k -sS --max-time 15 \
  -b "$PFSENSE_GUI_COOKIE_JAR" \
  -c "$PFSENSE_GUI_COOKIE_JAR" \
  -X POST "$PFSENSE_GUI_BASE/" \
  --data-urlencode "__csrf_magic=$PFSENSE_GUI_CSRF_TOKEN" \
  --data-urlencode "usernamefld=$PFSENSE_GUI_USER" \
  --data-urlencode "passwordfld=$PFSENSE_GUI_PASSWORD" \
  --data-urlencode "login=Sign In" >/dev/null

curl -k -sS --max-time 15 \
  -b "$PFSENSE_GUI_COOKIE_JAR" \
  "$PFSENSE_GUI_BASE/packages/layer7/layer7_settings.php" >"$PFSENSE_GUI_LAYER7_HTML"

rg -n "register_license|revoke_license|license_code" "$PFSENSE_GUI_LAYER7_HTML"
```

Opcionalmente, a mesma verificacao pode ser materializada pelo helper:

```bash
scripts/license-validation/run-pfsense-gui-license-flow.sh \
  --scenario-code S07 \
  --run-id "$RUN_ID" \
  --output-root "${TMPDIR:-/tmp}/layer7-f3-evidence" \
  --gui-base "$PFSENSE_GUI_BASE" \
  --gui-user "$PFSENSE_GUI_USER" \
  --gui-password "$PFSENSE_GUI_PASSWORD" \
  --action probe
```

Se a GUI estiver disponivel apenas no loopback do appliance, usar:

```bash
scripts/license-validation/run-pfsense-gui-license-flow.sh \
  --scenario-code S07 \
  --run-id "$RUN_ID" \
  --output-root "${TMPDIR:-/tmp}/layer7-f3-evidence" \
  --ssh-target "$PFSENSE_HOST" \
  --gui-base 'https://127.0.0.1:9999' \
  --gui-user "$PFSENSE_GUI_USER" \
  --gui-password "$PFSENSE_GUI_PASSWORD" \
  --action probe
```

- **Saida esperada:** login page inicial com `PHPSESSID`, extraccao valida de
  `__csrf_magic`, `POST` de login sem erro explicito e
  `layer7_settings.php` aberto como pagina autenticada do pacote, contendo
  `register_license`, `revoke_license` ou `license_code`.
- **Artefactos aceitaveis do helper:** `39-gui-flow-notes.txt`,
  `40-gui-cookie-jar.txt`, `41-gui-login-headers.txt`,
  `42-gui-login.html`, `45-gui-layer7-headers.txt`,
  `46-gui-layer7.html`.
- **Se falhar:** `NO-GO` para S07/S08/S09/S11/S12/S13. Se `layer7_settings.php`
  continuar a devolver login page, redirect inesperado ou `HTTP 403 CSRF Error`,
  classificar como `BLOCKED` de control plane e nao como `FAIL` do produto.

### 8. Confirmar inventario real `LIC-A` a `LIC-F`

- **Objectivo:** provar que o inventario existe no backend e que os IDs
  reservados batem com o estado real.
- **Comandos:**

```bash
$PG_READONLY_CMD -c "SELECT id, license_key, customer_id, hardware_id, status, expiry, activated_at, revoked_at, archived_at FROM licenses WHERE id IN ($LIC_A_ID,$LIC_B_ID,$LIC_C_ID,$LIC_D_ID,$LIC_E_ID,$LIC_F_ID) ORDER BY id;"
```

- **Saida esperada:** seis registos reais, coerentes com o mapeamento de
  campanha e sem IDs inexistentes.
- **Se falhar:** `NO-GO`. Nao improvisar licencas no dia da campanha.

### 9. Repetir a readiness da F3.11 com cenario real

- **Objectivo:** repetir a readiness com ambiente real e, no minimo, revalidar
  o controlo binario `409` vs `403` do S03.
- **Comandos:**

```bash
curl -k -si --max-time 15 "$L7_BASE_URL/api/health"
curl -si --max-time 15 "http://$L7_LIVE_HOST:8445/api/health"

curl -k -si --max-time 15 -X POST "$L7_BASE_URL/api/auth/login" \
  -H 'Origin: https://evil.example' \
  -H 'Content-Type: application/json' \
  -d '{"email":"nobody@example.com","password":"invalid"}'

curl -k -si --max-time 15 -X OPTIONS "$L7_BASE_URL/api/auth/login" \
  -H 'Origin: https://evil.example' \
  -H 'Access-Control-Request-Method: POST'

curl -k -si --max-time 15 -X POST "$L7_BASE_URL/api/activate" \
  -H 'Content-Type: application/json' \
  -d "{\"key\":\"$S03_LICENSE_KEY\",\"hardware_id\":\"$ALT_HARDWARE_ID\"}"
```

- **Saida esperada:** health publico e origin `200`; comportamento de CORS
  observado e classificado; controlo de activacao devolve `409` para
  hardware divergente em licenca bindada, conforme o contrato canonico do
  cenario S03.
- **Se falhar:** `NO-GO`. Registar drift/blocker; nao abrir campanha.

---

## 3. Criterio final de go/no-go

### `GO`

So existe `GO` se:

1. os nove passos acima concluirem com output real e coerente;
2. o live tiver revisao e stack provadas;
3. o schema live contiver as tabelas canonicas obrigatorias;
4. a credencial admin funcionar no fluxo oficial;
5. o appliance e o inventario estiverem em verde;
6. o controlo do passo 9 nao revelar drift bloqueante novo.

### `NO-GO`

Qualquer uma das condicoes abaixo fecha a rodada como `NO-GO`:

- SSH ao host live falha;
- directorio real da stack continua ambiguo;
- `git rev-parse HEAD` no live nao foi obtido;
- PostgreSQL live nao foi consultado em read-only;
- `admin_sessions`, `admin_audit_log` ou `admin_login_guards` faltam;
- login admin nao fecha sessao valida;
- appliance ou controlos do lab continuam incompletos;
- trilha GUI autenticada do pacote continua indisponivel;
- inventario `LIC-A` a `LIC-F` continua parcial;
- o controlo real do passo 9 continua a devolver contrato incompativel.

---

## 4. Objectivo, impacto, risco, teste e rollback deste bloco

- **Objectivo:** fornecer um roteiro curto, seguro e executavel para usar
  assim que os acessos forem entregues.
- **Impacto:** documental-operacional apenas; nenhum ficheiro de codigo foi
  alterado.
- **Risco:** baixo; o runbook apenas define a ordem minima de validacao.
- **Teste minimo:** coerencia com F3.10, readiness check e schema canonico do
  repositorio.
- **Rollback:** `git revert <commit-deste-bloco>`.
