# F3.10 — Runbook da Proxima Campanha Real

## Finalidade

Este documento fecha a parte canónica da **F3.10** sem abrir F4/F5/F6/F7.

Objectivo desta subfase:

- transformar a proxima campanha real da F3 numa execucao sequencial,
  previsivel e auditavel;
- impedir que a F3.11 comece sem preflight suficiente;
- definir criterios objectivos para **abortar antes de gerar falso `FAIL`**;
- fixar a ordem minima de execucao dos cenarios e a evidencia obrigatoria
  antes e durante a campanha.

Leitura complementar obrigatoria:

- [`f3-matriz-prerequisitos-campanha.md`](f3-matriz-prerequisitos-campanha.md)
  para decidir se a campanha pode abrir;
- [`f3-matriz-drift-operacional.md`](f3-matriz-drift-operacional.md)
  para classificar desvios ainda presentes;
- [`f3-pack-operacional-validacao.md`](f3-pack-operacional-validacao.md)
  para a estrutura de evidencias por `run_id`;
- [`f3-gate-fechamento-validacao.md`](f3-gate-fechamento-validacao.md)
  para a leitura final `PASS` / `FAIL` / `INCONCLUSIVE` / `BLOCKED`.

---

## 1. Condicao de entrada da F3.11

A F3.11 so pode ser aberta como campanha real se, antes do primeiro cenario,
todos os itens abaixo estiverem em verde:

1. deploy escolhido com referencia de repo e revisao do ambiente observadas;
2. schema coerente com o contrato canónico necessario para os cenarios;
3. credencial administrativa autorizada e testada;
4. appliance pfSense autenticavel e com baseline recolhivel;
5. inventario minimo de licencas preparado por cenario;
6. janela legitima para relogio, offline, revoke e drift de NIC/UUID quando
   esses cenarios forem executados.

Se um item obrigatorio falhar no preflight, a campanha **nao** entra em modo
de fechamento da F3.

---

## 2. Evidencias minimas antes de qualquer cenario

No directorio raiz do `run_id`, recolher antes de S01:

- `00-campaign-manifest.txt`
- `10-preflight-deploy.txt`
- `20-preflight-schema.txt`
- `30-preflight-admin.txt`
- `40-preflight-appliance.txt`
- `50-preflight-inventory.md`

O helper `scripts/license-validation/init-f3-validation-campaign.sh` passa a
materializar estes seis artefactos base como placeholders minimos, para
reduzir erro humano antes do primeiro cenario. A campanha continua a exigir
preenchimento real e evidencias objectivas; o helper nao substitui a
validacao de entrada.

Os helpers complementares passam a cobrir duas metades do preflight:

- `scripts/license-validation/prepare-f3-preflight.sh` pode orquestrar, no
  mesmo `run_id`, a inicializacao da campanha e os helpers de live/schema/
  appliance quando os acessos existirem;
- `scripts/license-validation/export-live-preflight.sh` pode actualizar
  `10-preflight-deploy.txt` e `30-preflight-admin.txt` com output bruto de
  health/login/CORS;
- `scripts/license-validation/export-schema-preflight.sh` pode actualizar
  `20-preflight-schema.txt` com identidade da base, presenca das tabelas e
  contagem minima via `docker compose exec` read-only;
- `scripts/license-validation/export-appliance-evidence.sh --update-root-preflight`
  pode consolidar a baseline do pfSense em `40-preflight-appliance.txt`.

Quando a baseline do appliance for recolhida via
`scripts/license-validation/export-appliance-evidence.sh`, o operador pode
usar `--update-root-preflight` para consolidar a mesma captura no
`40-preflight-appliance.txt` da campanha, sem copiar/colar manual.

Conteudo minimo desses artefactos:

| Ficheiro | Conteudo minimo obrigatorio |
|----------|-----------------------------|
| `00-campaign-manifest.txt` | `run_id`, operadores, data UTC, docs F3.6/F3.7/F3.8/F3.10 usadas, URL publica, origin observado, objectivo da campanha |
| `10-preflight-deploy.txt` | repo/commit de referencia da campanha, identificacao do deploy observado, host/origin, prova de que o ambiente nao e "desconhecido" |
| `20-preflight-schema.txt` | presenca/ausencia de `licenses`, `activations_log`, `admin_audit_log`, `admin_sessions`, `admin_login_guards` |
| `30-preflight-admin.txt` | resultado do login administrativo, escopo autorizado e limites de uso |
| `40-preflight-appliance.txt` | `layer7d --fingerprint`, `service layer7d status`, `date -u`, estado do `.lic` local, stats JSON inicial |
| `50-preflight-inventory.md` | mapeamento `LIC-A` a `LIC-F`, `license_id`, `license_key`, appliance alvo e estado esperado |

Sem estes seis artefactos, a campanha nao deve avancar para S01.

---

## 3. Ordem operacional minima da campanha

### 3.1 Checagens iniciais

1. Gerar `run_id` e directoria da campanha com o helper da F3.7.
2. Registar operador, ambiente, appliance(s), URL publica e origin observado.
3. Congelar a referencia documental da campanha: F3.6, F3.7, F3.8 e F3.10.

### 3.2 Validacao de deploy vs repo

1. Registar o commit/referencia canónica da campanha.
2. Capturar a identificacao do deploy efectivamente observado no host/origin.
3. Confirmar que a campanha nao esta a assumir, sem prova, que o live e
   igual ao repositório.
4. Testar o comportamento minimo de `POST /api/activate` que distingue `409`
   de `403` para o cenario de hardware divergente.

### 3.3 Validacao de schema live

1. Consultar o banco do deploy observado.
2. Confirmar a existencia de `admin_sessions`, `admin_audit_log` e
   `admin_login_guards`.
3. Confirmar que `licenses` e `activations_log` estao legiveis para recolha
   objectiva de evidencias.

### 3.4 Validacao de credenciais admin

1. Executar o login no fluxo oficial de sessao.
2. Confirmar que a credencial esta autorizada para os cenarios
   administrativos desta campanha.
3. Registar explicitamente qualquer limite de escopo.

### 3.5 Validacao do appliance pfSense

1. Confirmar SSH funcional.
2. Recolher baseline: fingerprint, `.lic` local, `date -u`, stats JSON,
   estado do servico.
3. Confirmar snapshot/rollback antes de relogio, offline, revoke, NIC ou
   UUID.

### 3.6 Validacao do inventario de licencas

1. Mapear `LIC-A` a `LIC-F` aos cenarios.
2. Confirmar estado actual de cada licenca no backend.
3. Confirmar em que appliance cada licenca sera exercitada.

### 3.7 Decisao de avancar ou abortar

- so avancar para cenario quando os itens 3.2 a 3.6 estiverem em verde;
- se algum item obrigatorio falhar, abortar a campanha de fechamento da F3
  antes de executar cenarios obrigatorios fora de contexto.

---

## 4. Ordem recomendada de execucao dos cenarios

Ordem minima para reduzir contaminação entre cenarios:

1. `S01`
2. `S02`
3. `S03`
4. `S04`
5. `S06`
6. `S05`
7. `S10` apenas se a janela continuar limpa e sem risco de contaminar o
   restante
8. `S07`
9. `S08`
10. `S12`
11. `S11`
12. `S09`
13. `S13`

Justificacao operacional:

- `S01` e `S02` constroem o baseline real de bind;
- `S03` confirma cedo se o deploy respeita o contrato `409`;
- `S04`, `S06` e `S05` esgotam primeiro a metade administrativa obrigatoria;
- `S07` valida o caminho online de expiracao sem ainda entrar em grace;
- `S08` e `S12` usam o mesmo bloco de controlo de relogio;
- `S11` deve ocorrer antes de `S09`, porque a coexistencia de artefactos
  exige licenca ainda activa antes da revogacao;
- `S13` fica por ultimo porque pode destruir a identidade do appliance.

---

## 5. Criterio para abortar a campanha antes de gerar falso FAIL

Abortar a campanha de fechamento antes do primeiro cenario afectado se
ocorrer qualquer um dos pontos abaixo:

- deploy efectivo sem revisao/prova minimamente observada;
- `activate` ainda a responder fora do contrato esperado para o cenario de
  controlo;
- schema live sem as tabelas canónicas necessarias;
- credencial administrativa ausente, invalida ou sem autorizacao formal;
- appliance sem SSH, sem fingerprint, sem stats JSON ou sem snapshot;
- inventario de licencas incompleto ou em estado diferente do manifesto;
- ausencia de janela legitima para relogio, offline ou drift de NIC/UUID.

**Regra:** nesses casos o resultado correcto e **aborto de campanha com
drift/blocker registado**, nao `FAIL` do produto.

---

## 6. Criterio para marcar BLOCKED antecipadamente

Marcar o cenario como `BLOCKED` sem o executar quando o pre-requisito ja
estiver objectivamente ausente:

| Cenario | Marcar `BLOCKED` antecipadamente quando |
|---------|-----------------------------------------|
| S01 | nao existir LIC-A activa sem bind ou o appliance estiver sem acesso |
| S02 | S01 nao tiver produzido baseline valido no mesmo hardware |
| S03 | nao existir licenca bindada previamente provada |
| S04 | nao houver sessao admin autorizada ou `admin_audit_log` |
| S05 | nao houver sessao admin autorizada, bind valido ou licenca dedicada |
| S06 | nao houver `ALT_CUSTOMER_ID` valido ou sessao admin autorizada |
| S07 | nao houver licenca expirada dedicada ou appliance sem `.lic` local limpo |
| S08 | nao houver artefacto previo valido e controlo de relogio |
| S09 | nao houver artefacto antigo preservado e isolamento offline |
| S10 | nao houver janela limpa para repeticao administrativa |
| S11 | nao houver artefacto antigo guardado antes da reemissao |
| S12 | nao houver snapshot e controlo de data antes/durante/depois da grace |
| S13 | o lab nao permitir drift real e reversivel de NIC/UUID/clone/restore |

---

## 7. Evidencias obrigatorias por cenario

Usar os nomes da F3.7 sempre que aplicavel.

| ID | Evidencias obrigatorias |
|----|-------------------------|
| S01 | `40-http-response.txt` ou saida CLI do `--activate`, `10-backend-license.txt`, `20-backend-activations-log.txt`, `30-backend-admin-audit-log.txt`, `50-appliance-cli.txt` |
| S02 | saida CLI da reactivacao, `10-backend-license.txt`, `20-backend-activations-log.txt`, `30-backend-admin-audit-log.txt` |
| S03 | `40-http-response.txt`, `20-backend-activations-log.txt`, `10-backend-license.txt` |
| S04 | `40-http-response.txt`, artefacto descarregado, `30-backend-admin-audit-log.txt` |
| S05 | resposta do `PUT`, artefacto reemitido, `10-backend-license.txt`, prova de bind preservado, opcionalmente prova no appliance |
| S06 | `40-http-response.txt`, `10-backend-license.txt`, `30-backend-admin-audit-log.txt` |
| S07 | saida CLI do `--activate`, prova de ausencia de `.lic` local, `10-backend-license.txt`, `20-backend-activations-log.txt` |
| S08 | `10-backend-license.txt`, tentativa online negada, `50-appliance-cli.txt` ou `60-appliance-license.json`, `date -u` do appliance |
| S09 | resposta da revogacao, `10-backend-license.txt`, tentativa online negada, stats/offline do appliance |
| S10 | dois downloads ou duas respostas guardadas, `30-backend-admin-audit-log.txt`, `70-local-hashes.txt` como evidencia adicional |
| S11 | copia do artefacto antigo, copia do artefacto novo, hashes dos dois, stats do appliance com cada um, `30-backend-admin-audit-log.txt` |
| S12 | tres capturas de data/estado: antes da expiracao, dentro da grace e apos a grace; stats JSON e estado do servico |
| S13 | `kern.hostuuid` antes/depois, fingerprint antes/depois, stats JSON, descricao exacta da mudanca aplicada e tentativa online se houve drift |

---

## 8. Roteiro operacional do DR-05 no appliance

Este bloco existe para executar o unico blocker real restante da F3 sem
depender de prompt historico fora da trilha canónica.

### 8.1 Estado actual do appliance

No estado observado em `2026-04-14`, ja esta provado que:

- o appliance `192.168.100.254` existe, responde e corre Layer7;
- o utilizador temporario `codex` consegue aceder por SSH e recolher
  baseline read-only;
- `pfSsh.php playback svc restart layer7d` funciona com `codex`;
- `layer7d --fingerprint`, `kern.hostuuid` e stats JSON sao observaveis;
- o `.lic` actual e legivel, mas nao escrevivel por `codex`;
- `service layer7d status` pode dar falso negativo por falta de leitura do
  pidfile `0600 root:wheel`, enquanto `pgrep -fl layer7d` e o stats JSON
  continuam a provar o daemon vivo.

Logo, a metade read-only do `DR-05` ja esta desbloqueada. O que falta e
permissao/control plane suficiente para cenarios mutaveis que exijam apagar
ou substituir `/usr/local/etc/layer7.lic`, controlar o daemon sem ambiguidade
e aplicar snapshot/restore antes de relogio, NIC, UUID, clone ou restore.

### 8.2 Ordem segura dentro do DR-05

Executar nesta ordem:

1. baseline e snapshot do appliance;
2. cenarios mutaveis de menor impacto (`S07`, depois `S08` e `S09`);
3. relogio/grace (`S12`) apenas com snapshot imediatamente anterior;
4. drift de identidade (`S13`) por ultimo, com rollback estrutural claro.

Regra pratica:

- nao misturar `S12` e `S13` na mesma janela sem restore limpo entre eles;
- nao usar o builder para provar nada do `DR-05`;
- nao tratar o repo como substituto do que o appliance observou;
- se faltar permissao legitima para escrita/control plane, marcar
  `BLOCKED`, nao `FAIL`.

### 8.3 Comandos base do bloco mutavel

Preparacao da campanha:

```bash
export RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)-appliance254"
export EVIDENCE_ROOT="${TMPDIR:-/tmp}/layer7-f3-evidence"
export PF_SSH_TARGET='codex@192.168.100.254'
```

Baseline/preflight local do appliance:

```bash
./scripts/license-validation/export-appliance-evidence.sh \
  --scenario-code S07 \
  --run-id "$RUN_ID" \
  --output-root "$EVIDENCE_ROOT" \
  --ssh-target "$PF_SSH_TARGET" \
  --update-root-preflight
```

Cenario mutavel com orquestracao no mesmo `run_id`:

```bash
export L7_LICENSE_KEY="$(cat /caminho/seguro/licenca.key)"

./scripts/license-validation/run-appliance-activation-scenario.sh \
  --scenario-code S07 \
  --run-id "$RUN_ID" \
  --output-root "$EVIDENCE_ROOT" \
  --license-id "$LICENSE_ID" \
  --license-key "$L7_LICENSE_KEY" \
  --compose-dir "$L7_SERVER_DIR" \
  --ssh-target "$PF_SSH_TARGET"
```

Quando o cenario exigir `.lic` limpo antes da tentativa, acrescentar
`--clear-local-license`.

### 8.3.1 Trilha legitima alternativa via GUI do pacote

No appliance observado, a pagina instalada
`/usr/local/www/packages/layer7/layer7_settings.php` expõe o fluxo oficial
de mutacao da licenca do proprio pacote:

- `POST layer7_settings.php#l7-sistema` com `name="register_license"` e
  `license_code=<codigo>` para registar/activar;
- `POST layer7_settings.php#l7-sistema` com `name="revoke_license"` para
  revogar/remover a licenca local.

Implementacao observada no pacote instalado:

- `register_license` chama `/usr/local/sbin/layer7d --activate <codigo>`;
- em sucesso, actualiza `layer7.json` e reinicia o servico;
- `revoke_license` faz `unlink()` de `/usr/local/etc/layer7.lic`,
  limpa `license_key_mask` do JSON e reinicia o servico;
- os helpers locais usados nesta trilha sao `layer7_lic_path()` e
  `layer7_restart_service()`.

Regra operacional:

- esta e uma trilha **legitima** para fechar `DR-05`;
- ela continua dependente de sessao autenticada da GUI do pfSense;
- nao assumir automacao HTTP cega sem primeiro observar o fluxo real da
  sessao/autenticacao no ambiente;
- se a campanha usar esta trilha, guardar evidencia bruta do pedido,
  resposta e efeito local no appliance no mesmo `run_id`.

Sessao/autenticacao observada no pfSense instalado:

- o login da GUI usa `POST` com `usernamefld`, `passwordfld` e `login`;
- o pfSense injecta proteccao CSRF com `__csrf_magic`;
- em sucesso, a auth do pfSense faz `session_regenerate_id()` e marca a
  sessao com `$_SESSION['Logged_In'] = "True"` e `$_SESSION['Username']`.
- no appliance observado, `http://127.0.0.1/` responde `301` para
  `https://127.0.0.1:9999/`;
- a resposta inicial de `https://127.0.0.1:9999/` devolve `Set-Cookie:
  PHPSESSID=...; secure; HttpOnly; SameSite=Strict` e injecta no HTML
  `csrfMagicToken` / `csrfMagicName="__csrf_magic"`.
- no fluxo observado com `curl`, o cookie jar fica no formato Netscape e
  guarda `PHPSESSID` como cookie `HttpOnly` normal de sessao;
- sem sessao autenticada, `https://127.0.0.1:9999/packages/layer7/layer7_settings.php`
  devolve novamente a pagina de login, nao a pagina do pacote;
- um `POST` com token CSRF fora de sincronia devolve `HTTP 403` com
  `CSRF Error`, o que confirma que a campanha deve reutilizar o
  `PHPSESSID` e o `__csrf_magic` observados no mesmo fluxo real.

Portanto, o passo humano minimo para usar esta trilha sem improviso e:

1. abrir sessao real na GUI do pfSense;
2. observar os cookies/sessao efectivamente emitidos pelo ambiente
   (nome observado: `PHPSESSID`);
3. observar o token `__csrf_magic` da pagina, injectado por `csrfMagicToken`;
4. confirmar que `layer7_settings.php` ja abre como pagina autenticada do
   pacote, e nao como login;
5. so depois submeter `register_license` ou `revoke_license` em
   `layer7_settings.php#l7-sistema`;
6. se o POST devolver `403 CSRF Error`, abandonar a tentativa e recolher
   nova pagina/token/sessao antes de repetir;
7. guardar request, response e efeito local no mesmo `run_id`.

#### Sequencia `curl` minima sugerida

Exemplo operacional, com placeholders:

```bash
export PF_GUI_BASE='https://192.168.100.254:9999'
export PF_GUI_USER='<UTILIZADOR_GUI_AUTORIZADO>'
export PF_GUI_PASS='<PASSWORD_GUI_AUTORIZADA>'
export PF_COOKIE_JAR="${TMPDIR:-/tmp}/pfsense-layer7-gui.cookies"
export PF_LOGIN_HTML="${TMPDIR:-/tmp}/pfsense-layer7-login.html"
export PF_LAYER7_HTML="${TMPDIR:-/tmp}/pfsense-layer7-settings.html"

rm -f "$PF_COOKIE_JAR" "$PF_LOGIN_HTML" "$PF_LAYER7_HTML"

curl -k -sS -c "$PF_COOKIE_JAR" "$PF_GUI_BASE/" > "$PF_LOGIN_HTML"

export PF_CSRF_TOKEN="$(grep -o 'csrfMagicToken = "[^"]*"' "$PF_LOGIN_HTML" | head -n1 | cut -d '"' -f2)"

curl -k -sS -b "$PF_COOKIE_JAR" -c "$PF_COOKIE_JAR" \
  -X POST "$PF_GUI_BASE/" \
  --data-urlencode "__csrf_magic=$PF_CSRF_TOKEN" \
  --data-urlencode "usernamefld=$PF_GUI_USER" \
  --data-urlencode "passwordfld=$PF_GUI_PASS" \
  --data-urlencode 'login=Sign In' \
  > /dev/null

curl -k -sS -b "$PF_COOKIE_JAR" \
  "$PF_GUI_BASE/packages/layer7/layer7_settings.php" \
  > "$PF_LAYER7_HTML"
```

Leitura esperada:

- se `PF_LAYER7_HTML` ainda contiver a pagina de login, a sessao nao abriu;
- se a resposta do `POST` devolver `403 CSRF Error`, recolher nova pagina de
  login e novo `__csrf_magic` antes de repetir;
- so depois de confirmar acesso autenticado a `layer7_settings.php` deve-se
  enviar `register_license=1` ou `revoke_license=1`.

Exemplos de submissao, apenas depois da sessao autenticada estar confirmada:

```bash
export PF_LAYER7_CSRF="$(grep -o 'name='\''__csrf_magic'\'' value=\"[^\"]*\"' "$PF_LAYER7_HTML" | head -n1 | sed 's/.*value=\"//; s/\"$//')"

curl -k -sS -b "$PF_COOKIE_JAR" -c "$PF_COOKIE_JAR" \
  -X POST "$PF_GUI_BASE/packages/layer7/layer7_settings.php#l7-sistema" \
  --data-urlencode "__csrf_magic=$PF_LAYER7_CSRF" \
  --data-urlencode "license_code=$LICENSE_KEY" \
  --data-urlencode "register_license=1" \
  > "${TMPDIR:-/tmp}/pfsense-layer7-register.html"

curl -k -sS -b "$PF_COOKIE_JAR" -c "$PF_COOKIE_JAR" \
  -X POST "$PF_GUI_BASE/packages/layer7/layer7_settings.php#l7-sistema" \
  --data-urlencode "__csrf_magic=$PF_LAYER7_CSRF" \
  --data-urlencode "revoke_license=1" \
  > "${TMPDIR:-/tmp}/pfsense-layer7-revoke.html"
```

Guardrails:

- usar sempre o `__csrf_magic` da pagina efectiva que sera submetida;
- nao reutilizar token de uma sessao anterior;
- guardar headers/resposta HTML quando houver erro;
- se a GUI devolver login page, `403 CSRF Error` ou redirect inesperado,
  classificar como `BLOCKED` da trilha GUI e nao como `FAIL` do produto.

Helper opcional desta trilha:

- `scripts/license-validation/run-pfsense-gui-license-flow.sh` pode
  materializar a mesma sequencia em modo `probe`, `register` ou `revoke`,
  guardando `headers`, `HTML`, `cookie jar` e notas em
  `${output_root}/${run_id}/${scenario_code}/`;
- quando a GUI estiver acessivel apenas no proprio appliance, o helper pode
  ser executado com `--ssh-target <utilizador@host>` e `--gui-base`
  apontando para `https://127.0.0.1:9999`.
- para reduzir exposicao de segredos na linha de comando, o helper aceita
  `--gui-password-file <path>` ou `L7_GUI_PASSWORD`, e aceita
  `--license-key-file <path>` ou `L7_LICENSE_KEY` no fluxo `register`.

### 8.4 Mapa minimo de cenarios do DR-05

| Tema | Cenario F3.6 | Leitura operacional |
|------|--------------|---------------------|
| online/offline e estado efectivo | `S07`, `S08`, `S09` | `S07` valida ausencia de `.lic` local e leitura online; `S08` e `S09` exigem artefacto previo e isolamento controlado |
| relogio e grace local | `S12` | snapshot obrigatorio antes; rollback = restore |
| NIC / UUID / clone / restore | `S13` | baseline antes/depois, drift real e reversivel, sem improviso |
| snapshot/restore | transversal | rede de seguranca do bloco, nunca substitui a evidencia do cenario |

### 8.5 Evidencia minima especifica do DR-05

Para cada cenario mutavel do appliance, guardar no minimo:

- `50-appliance-cli.txt`;
- `60-appliance-license.json` quando houver `.lic` legivel;
- `70-local-hashes.txt` quando houver artefacto local;
- snapshot do backend antes/depois, quando o cenario tocar activacao,
  expiracao, reemissao ou revogacao;
- nota explicita do resultado `PASS` / `FAIL` / `INCONCLUSIVE` / `BLOCKED`
  e do motivo objectivo.

Se o live continuar a responder `403` onde o repo espera `409`, tratar isso
como `DR-02` cosmetico ja conhecido: validar a logica de negocio
(`rejeita`/`aceita`) antes do codigo HTTP.

### 8.6 Apos executar o DR-05

Actualizar no mesmo bloco:

1. `docs/01-architecture/f3-11-drift-registry.md`;
2. `docs/01-architecture/f3-fecho-operacional-restante.md`;
3. `docs/01-architecture/f3-11-readiness-scorecard.md`;
4. `docs/01-architecture/f3-11-execution-master-register.md`;
5. `docs/00-overview/f3-11-start-here.md`;
6. `CORTEX.md`;
7. `docs/tests/templates/f3-validation-campaign-report.md`.

---

## 9. Fecho operativo da campanha

1. Preencher o relatorio final unico da campanha.
2. Contar `PASS`, `FAIL`, `INCONCLUSIVE` e `BLOCKED`.
3. Se qualquer obrigatorio ficar fora de `PASS`, concluir `F3 nao pode
   fechar`.
4. Se todos os obrigatorios ficarem em `PASS`, concluir `F3 pode fechar`.

Nao existe fecho parcial da F3. A F3.11 so cumpre a sua funcao se executar
este runbook sem reaprender blockers ja registados na F3.9/F3.10.
