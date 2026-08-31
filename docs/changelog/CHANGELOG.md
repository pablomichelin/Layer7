# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

## [Unreleased]

## [1.9.78] — 2026-08-31

### Changed

- **BG-172 — Eventos em linguagem de operador:** a página Eventos deixa
  de mostrar só `flow_decide` / `dns_query` / `dns_resolved`. Cada linha
  passa a ter título e frase («Tráfego observado», «Pedido de nome (DNS)»).
  O detalhe técnico fica atrás de «Mostrar detalhe tecnico». O filtro
  casa texto humano e a linha crua. O ingest SQLite não muda
  (`dns_resolved` continua fora dos relatórios).

### Fixed

- **BG-171 — Pornografia (host OU categoria):** o perfil rápido exigia
  `AdultContent` **e** host da lista. `pornhub.com` falhava quando o nDPI
  reportava `Web`/`TLS`/vazio e o simulador caía em `p-mon-001`.
  `match_mode=or` no catálogo `adulto` e na política `profile-adulto`;
  default das restantes políticas continua AND. Interface, origem, horário
  e exclusões permanecem obrigatórios. Upgrade carimba `profile-adulto`
  existente sem duplicar. Simulador alinhado ao daemon (OR + catch-all
  em fallback).

## [1.9.77] — 2026-08-31

### Security

- **BG-170 — check-in obrigatório:** o operador já não pode desligar o
  check-in periódico. O daemon ignora `check_in_enabled=false` no JSON;
  a GUI deixa de expor o interruptor. Revogar no portal volta a cortar
  caixas que tinham opt-out. Falha de rede continua a não derrubar o
  bloqueio (N3).
  Pacote `SHA256=1b595f5014316f0fa25e52a974b1e7137a13ec443af80f5e000849c103445f57`.

## [1.9.76] — 2026-08-26

### Changed

- **BG-169 — perfil rápido Pornografia:** o atalho `adulto` passa a
  chamar-se **Pornografia** (id estável, políticas `profile-adulto`
  intactas). Hosts   15 → 64 (teto do daemon) + categoria nDPI
  `AdultContent`. O preset Protecção infantil herda um subconjunto
  alargado. Cobertura completa continua na blacklist UT1 adult.
  Pacote `SHA256=a7d6ba444351f57611c1a6ca70c480bce1b26322425577330b01e6cac805bcc0`.

## [1.9.75] — 2026-08-26

### Fixed

- **BG-168 — PF sem licença deixava de ser passivo:** `layer7_pf_should_enforce()`
  passa a exigir `enforce_mode=1` (o mesmo critério do badge BG-167). Pedido
  **aplicar** sem `.lic` já não injecta anti-QUIC, `block drop` nem NAT de
  bloqueio. Causa do incidente no mercado: JSON `mode=enforce` armava o
  pfSense enquanto o daemon ficava em monitor.
  Pacote `SHA256=90e5bb2e6369ca2c5b2ce5afc926cacd2ea0fdd2426b13d400901b1de3c72e75`.

## [1.9.74] — 2026-08-26

### Fixed

- **BG-167 — GUI sem falso positivo de enforce:** o dashboard, Diagnósticos
  e Definições mostram o modo **efectivo** do daemon (`enforce_mode`). Sem
  licença o badge passa a **monitorizar**, com nota «pedido aplicar», em
  vez do vermelho **aplicar**. O selector continua a gravar o pedido.
  Pacote `SHA256=bb4cc7810b26d2246ffd71912d04b0c83299eb826f09b7a324a83dfa42084542`.

## [1.9.73] — 2026-08-25

### Fixed

- **BG-165 — auditoria de licença no cliente:** activate/check-in do
  daemon usam `/usr/local/bin/curl`; estado de check-in com `flock`;
  badge da GUI lê o `.lic` se as stats do daemon faltarem; revoke,
  import e save desarmam Identity/MITM sem entitlement; install-ping
  não inventa `hardware_id` e não embute PORTVERSION.
- **BG-166 — license-server (git privado):** UPSERT de install-ping
  deixa de apagar inventário com payload mínimo; activate e download
  passam por `normalizeFeatures`; logs de activate/check-in usam
  `getClientIp`. Sem overlay `.244` (P0-1).

## [1.9.72] — 2026-08-25

### Fixed

- **BG-163 — install-ping deixava de enviar:** o helper já não carrega
  `config.inc` (lê `config.xml`), usa `php -f` e `/usr/local/bin/curl`
  (o `PATH` do daemon não inclui `/usr/local/bin`), e volta a tentar
  aos 15 min se o POST falhar. Fail-open mantido.

## [1.9.71] — 2026-08-22

### Added

- **BG-162 — sinal de instalação/heartbeat sem serial:** o pacote
  envia inventário (FQDN, WAN, IPs/nomes de interfaces,
  uniqueid/plataforma) a `license.systemup.inf.br` na instalação e a
  cada 24 h, mesmo sem chave. Fail-open (N3). Portal `2.2.0` página
  Instalações. ADR-0036. Overlay `.244` `20260823T022826Z`.

### Fixed

- **BG-161 — add-on não liga no upgrade de licença:** o daemon Identity
  passa a exigir token **e** toggle `identity.enabled` (default OFF).
  Ganhar `identity`/`mitm` na activação persiste os toggles OFF; perder o
  token desliga. Editar JSON/defaults sem entitlement não activa MITM
  (`mitm_effective`) nem Identity.

## [1.9.69] — 2026-08-15

### Fixed

- **BG-160 — migrar defaults após upgrade:** a migração dos defaults da página
  de bloqueio ocorre ao guardar Configurações no idioma selecionado, mesmo se
  esse idioma já estava selecionado antes da atualização.

## [1.9.68] — 2026-08-15

### Fixed

- **BG-159 — completar inglês de Configurações:** traduzidos os rótulos,
  explicações e opções que ainda continham termos em português. Ao trocar de
  idioma, os títulos e mensagens padrão da página de bloqueio são migrados
  apenas se ainda forem os defaults do pacote; conteúdo personalizado não é
  alterado.

## [1.9.67] — 2026-08-15

### Fixed

- **BG-158 — integridade PT/EN/ES da interface:** substituído o fallback
  espanhol para inglês por catálogo ES completo; o português passa a carregar
  as chaves históricas em inglês; textos dinâmicos de Eventos e o rótulo de
  idioma de Configurações passam pelo catálogo. O gate de i18n agora exige
  cobertura PT/EN/ES e bloqueia o retorno ao fallback EN em espanhol.

## [1.9.66] — 2026-08-15

### Added

- **BG-157 — opção Español:** o idioma espanhol passa a estar disponível na
  configuração do serviço e na página pública de bloqueio. O catálogo espanhol
  traduz a interface operacional e usa o catálogo EN como fallback seguro para
  mensagens técnicas ainda não especializadas, sem retorno ao português.

## [1.9.65] — 2026-08-15

### Added

- **BG-156 — cobertura contínua i18n EN:** novo gate
  `tests/functional/test_i18n_coverage.js` verifica todas as chaves literais
  `l7_t()` da GUI, descrições do catálogo de perfis e a página pública de
  bloqueio no idioma escolhido.

- **BG-128 P0-1 git — serving `30.11` versionado:** allowlist de 7 paths
  (`content-auth.js` + teste, `routes/content.js`, `.gitkeep`,
  `CONTENT_BLACKLISTS_DIR` + volume `:ro`, vhost `downloads` sobre o nginx
  HEAD com P1-2 `$remote_addr`, `.gitignore` do snapshot). Clone limpo
  resolve `require('./routes/content')`. **P0-1 NÃO encerrado** — deploy
  integral / rebuild / overlay no `.244` continua proibido. Sem
  `index.js`/auth/TOTP/SPA/package/daemon/`.env`/bind/snapshot/host.

### Changed

- **BG-156 — inglês completo no pacote:** completado o catálogo EN para
  Allowlist, Blacklists, Diagnostics, Identity, MITM, remoção e mensagens de
  validação; corrigidas traduções mistas. A página pública de bloqueio passa a
  apresentar título, mensagem padrão, rótulos e idioma HTML em EN quando
  `layer7.language=en`, preservando textos personalizados do operador.

- **BG-128 P2-9 / BG-154 — upgrade não injecta check-in ON:**
  AVALIADO neste bloco (opção A — cadeado + docs). O GO `30.14` /
  ADR-0032 permanece: novas = ON; existentes = opt-in; isolados =
  `false` (R-J). `load_or_default` e `pkg-install.in` **não** chamam
  `layer7_check_in_apply_migration_policy`; chave ausente ⇒ efectivo
  OFF. Injectar `true` no upgrade invertiria o `30.14` — só com GO
  novo. Cadeado em `test_check_in_default_30.14.php`. Sem mudança de
  runtime. Sem `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P0-2 residual — TOTP challenge single-use + bind:** FEITO
  no git. O `challenge_token` passa a levar `jti` aleatório no HMAC.
  `/login` invalida desafios unused do admin, grava
  `admin_totp_challenges` e só depois devolve o token. `/login/totp`
  valida HMAC+`jti` e consome com `SELECT … FOR UPDATE` + `used_at`
  **antes** de `createSession`. Replay, `jti` forjado/inexistente,
  token antigo sem `jti` e desafio anterior → `401` genérico. Sem
  bind IP/UA, sem env/segredo novo, sem SPA, sem rate-limit, sem
  compose/Docker/deploy/`PORTVERSION` (P0-1 ACTIVO).
- **BG-128 P2-6 Bloco B — Postgres healthy antes da API:** FEITO no
  git. `docker-compose.yml` passa a ter `db.healthcheck` com
  `pg_isready -U $$POSTGRES_USER -d $$POSTGRES_DB` (env **dentro** do
  contentor) e `api.depends_on.db.condition: service_healthy`. Sem
  healthcheck em `api`/`web`/`nginx`. Sem mudança de `USER node`,
  frontend, Dockerfile, código API, imagem/tag ou env. Hash compose
  P0-1 actualizado (`b0dcfe28…`); inventário original `7845ac36…`
  preservado; 4 hashes JS/gitkeep intactos. Cadeado
  `dockerfile-p26.test.js` (4 PASS). Sem Docker build/up, sem
  `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P3-7 / BG-153 — colisão TZ/expiry; não usar timegm:**
  AVALIADO no git (opção A — **FEITO documental**). A divergência
  appliance UTC−3 vs expiry UTC no servidor é o contrato HEAD
  (cliente mais estrito no dia D; **não** é bypass). A correção
  histórica REV-030 / texto original P3-7 (`timegm` / meio-dia UTC
  / `gmmktime`) **altera o contrato** e **piora** a diferença
  Brasil/UTC (grace passaria a começar às 21:00 de D−1). Sem
  mudança de runtime em `license.c` / `layer7.inc` /
  `crud-validation.js`. Cadeado `test_license_expiry_policy.php`
  intacto (meia-noite local, `mktime` hora 0). Política A–D só
  com GO (prova P2-13). Sem `PORTVERSION`, sem deploy
  (P0-1 ACTIVO).
- **BG-128 P2-6 Bloco A — Docker context + user:** FEITO no git.
  `backend/.dockerignore` e `frontend/.dockerignore` excluem `.env`,
  `.env.*`, `node_modules` e `.git`. O `backend/Dockerfile` corre
  `CMD` como `USER node` após o último `COPY`. Frontend nginx
  listen 80 **sem** `USER node`. Compose/healthcheck **fora**
  (Bloco B; hash P0-1 intacto). Cadeado
  `dockerfile-p26.test.js` (4 PASS). Sem Docker build/up, sem
  `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P3-9 / BG-150 — docs pós-cut «404 esperado»:** AVALIADO
  no git (opção A — **FEITO documental**; URLs **não** removidos).
  A tag `blacklists-ut1-current` foi cortada em `30.11`; os quatro
  URLs GitHub de *download* devolvem **404 esperado**. Primary
  **exige token** (401 sem token). Isto **não** é o canal do pacote
  (`releases/latest` / `v1.9.63`) nem motivo para reupload GA4.11.
  O espelho no runtime é **legado / fallback**. Nota
  `docs/09-blocking/nota-404-esperado-cut-30.11.md`. Evidência
  `docs/tests/evidence/20260814T204500Z-p39-404-esperado/`. Sem
  mudança de runtime. Sem `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P3-8 / BG-148 — recheck read-only do cut `30.11`:** AVALIADO
  no git após a evidência `20260814T200900Z` (confirmação
  `20260814T201800Z`). Release `blacklists-ut1-current` id `313502667`
  continua `prerelease=true` / `draft=false` / `assets=[]` /
  `asset_count=0`; anónimo 404×4 (nofollow e follow, size 9, sem
  302→CDN); primary sem token 401. Contraste `releases/latest` =
  `v1.9.63` (7 assets) — canal do pacote, **não** é P3-8. Sem mudança
  de runtime. Residual P3-9 fechado em BG-150 (opção A; URLs **não**
  removidos). Sem `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P2-13 — política de datas / meia-noite / DST:** AVALIADO
  no git após gates deste bloco. `expiry=YYYY-MM-DD` continua a
  meia-noite local (`mktime` hora 0). No dia D às 12:00 a licença já
  está em grace; o servidor UTC ainda a trata como activa. `tm_isdst=0`
  no daemon pode divergir ±1 h da GUI em fusos com DST. Não há correção
  única segura (fim do dia UTC vs local vs `timegm` vs só `tm_isdst=-1`
  colidem com REV-030 / P3-7 / P2-11). Sem mudança de runtime em
  `license.c` / binding PHP. Cadeado
  `test_license_expiry_policy.php`. Sem `PORTVERSION`, sem deploy
  (P0-1 ACTIVO).
- **BG-128 P1-9 — prova residual pós-P2-3:** AVALIADO no git após gates
  deste bloco. Auth com Host oficial no origin HTTP é o contrato F2.1
  (edge TLS → origin HTTP + `$scheme`). Não é exposição aberta no HEAD
  (`127.0.0.1:8445`, `default_server` 444, API sem `ports:`). Fechar o
  Host oficial partiria o F2.1 ou reabriria P2-3. Bind live `0.0.0.0`
  continua operacional, **não** versionado. Sem mudança de runtime.
  Cadeado `origin-bind-p19.test.js`. Sem `PORTVERSION`, sem deploy
  (P0-1 ACTIVO).

## [1.9.64] — 2026-08-15

### Fixed

- **BG-155 / revisão de código 2026-08-15 — integridade de check-in, PF,
  dependências e gate TLS:** o check-in passa a usar transação e `FOR UPDATE`
  na linha da licença antes de emitir a resposta assinada, serializando com
  revoke/replace/rebind. A validação PF rejeita IPv6 com zone-id em vez de
  passar um membro inválido ao `pfctl`. As imagens Docker passam a usar
  lockfiles versionados e `npm ci`; o gate TLS aceita flags explícitas para
  OpenSSL fora do loader path padrão no macOS. O smoke volta a compilar o
  módulo do gate de licença, e o preparador de release usa build não
  interativo. Publicado `v1.9.64`
  (`SHA256=692ab615b0a45f70958f2b866d339e44f833f7953aeec5f780ee0af9e5afeb5f`)
  com manifesto Ed25519 validado. **P0-1 permanece ativo:** nenhum
  deploy do servidor `.244`.

- **BG-128 P3-6 / BG-144 — gate de alinhamento da chave de produção:**
  FEITO no git após gates deste bloco. `verify-prod-pubkey.sh` deixa de
  comparar só o array C de `license.c` com o SoT do builder: extrai os
  32 bytes raw do PEM do port (`license-signing-public-key.pem`, SPKI
  Ed25519 44 B) e **FAIL** se PEM ≠ SoT ou PEM ≠ C (em falta,
  inválido, OID errado ou outra Ed25519). Validação C vs SoT
  preservada. Selftest local
  `test_verify_prod_pubkey.sh` (SoT=C=PEM PASS; outra Ed25519 / PEM
  em falta / inválido / SoT≠C FAIL) via
  `L7_PROD_PUBKEY_HEX_FILE` + PEM temporário, sem
  `/root/layer7-build-secrets`. `license.c`, PEM e `PORTVERSION`
  intactos. Residual P3-7. Sem deploy (P0-1 ACTIVO).
- **BG-128 P3-5 / BG-142 — promoção atómica do `.lic` em Activate:**
  FEITO no git após gates deste bloco. `layer7_activate` deixa de
  truncar/substituir `L7_LIC_PATH` antes de validar o candidato.
  `promote_license_atomic` grava tmp 0600 no mesmo directório do
  destino, corre `layer7_license_check_path` no candidato e só então
  faz `rename`. Falha/unlink do tmp preserva o `.lic` anterior.
  `activate.body` em `/var` não é renameado. `layer7_license_check`
  permanece wrapper do path final (contrato intacto). Mensagens/
  exit de sucesso e cleanup preservados. Sem fsync novo. Hook
  `L7_ACTIVATE_PROMOTE_HOOK` só com `L7_TEST_ACTIVATE_PROMOTE` +
  `LAYER7_TEST_ROOT` (ausente do Makefile do port). Sem check-in/
  clock/pakey/expiry. Residual P3-6 (fechado neste bloco). Sem
  `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P3-4 / BG-140 — falha de pool em GET /2fa/status:** FEITO
  no git após gates deste bloco. `GET /api/auth/2fa/status` passa a
  capturar rejeição de `pool.query` com try/catch local. Log interno
  só `err.message` (sem email/token/segredo). Resposta 500
  `{error:'Erro interno.'}` via `buildAuthErrorResponse`. Express 4
  sem o catch deixava a Promise rejeitada sem 500 JSON
  (`unhandledRejection`; pedido HTTP pendente). Segundo GET saudável
  continua 200 `{totp_enabled:true}`; 401/403 intactos. Sem wrapper
  global, sem Express 5, sem dependência. Residual P3-5. Sem
  `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P3-3C / BG-138 — comparação TOTP timing-safe:** FEITO
  no git após gates deste bloco. `verifyTotp` deixa o `===` e compara
  HOTP/TOTP com Buffer UTF-8 + guarda de comprimento +
  `crypto.timingSafeEqual`. Comprimentos diferentes = mismatch sem
  chamar `timingSafeEqual` (evita RangeError). Janela/step/HMAC/
  normalização intactos. Código válido no mesmo `now` → true;
  6 dígitos inválidos → false; `''`, null/undefined, 5/7 dígitos e
  não-dígitos → false sem throw. `auth.js` / `/login/totp` / enable /
  disable continuam a receber booleanos. Residual P3-4. Sem
  `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P3-3B / BG-136 — password de técnico >=12:** FEITO
  no git após gates deste bloco. `normalizePassword` em `routes/users.js`
  sobe de 10 para 12 e a mensagem passa a «pelo menos 12 caracteres».
  `POST /api/users` com 10 → 400; com 12 → 201 (`is_owner=false`).
  `PUT` com password 10 → 400 e hash inalterado; `PUT` sem password
  não toca no hash nem revoga sessões. Autorização `users.manage` e
  owner 409 intactos. `/login` **não** rejeita password de 10.
  `Users.jsx` (`minLength={10}`) e `bootstrap-admin.js` intocados.
  Residual P3-3C (`===` no TOTP). Sem `PORTVERSION`, sem deploy
  (P0-1 ACTIVO).
- **BG-128 P3-3A / BG-134 — enumeração disabled em POST /login:** FEITO
  no git após gates deste bloco. `POST /api/auth/login` trata conta
  desactivada e email inexistente com a mesma `401` `Credenciais
  invalidas`. Ambos fazem trabalho bcrypt (hash real ou dummy
  constante) e chamam `registerLoginFailure`. A auditoria interna
  pode continuar `account_disabled`; o body HTTP não vaza estado.
  Sucesso, falha de conta activa, lock 5/15 e 10/15, TOTP e CSRF
  intactos. Residual P3-3 após P3-3B: `===` no TOTP (P3-3C).
  Sem `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P3-2 / BG-132 — exactidão TOTP em GET /session:** FEITO
  no git após gates deste bloco. O SELECT de `resolveSessionToken`
  passa a incluir `a.totp_enabled`. `buildSessionMetadata` já lia o
  campo; sem a coluna, `toPublicAdmin` convertia `undefined` em
  `false` e a UI via TOTP desligado. Auth/TTL/revoke/`createSession`/
  CSRF/TOTP flows intactos. Sem `PORTVERSION`, sem deploy (P0-1
  ACTIVO).
- **BG-128 P3-1 / BG-131 — sessão única atómica:** FEITO no git após
  gates deste bloco. `createSession` passa a
  `BEGIN` + `SELECT id FROM admins WHERE id = $1 FOR UPDATE` + revoke +
  insert + `COMMIT`. Prova: `BEGIN` sozinho (sem lock) continua a
  deixar 2 linhas `revoked_at IS NULL` em READ COMMITTED. Unique
  parcial fora (exigiria limpar duplicados). Dois `createSession`
  paralelos → 1 activa. Refresh/revogação/TTL 30 min/8 h / TOTP /
  CSRF intactos. Sem `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P2-4 — lock de login atómico:** FEITO no git após gates
  deste bloco. `updateLoginGuard` deixa o read-modify-write
  (SELECT + `failure_count+1` + UPSERT `EXCLUDED`) e incrementa
  `failure_count` no próprio `ON CONFLICT`. Contrato intacto: 5 falhas
  de conta / 10 de IP na janela de 15 min → lock de 15 min. 10
  `registerLoginFailure` paralelos → count=10 + lock. Sem CSRF/proxy/
  sessão/TOTP/compose, sem `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P2-2 — CSRF admin fail-closed:** FEITO no git após gates
  deste bloco. `/api/users` e `/api/search` entram na superfície
  administrativa. Mutações e emissão de sessão sem `Origin` na allowlist
  nem `Sec-Fetch-Site: same-origin` → 403. Portal oficial e APIs Bearer
  autenticadas intactas. Activate/check-in/content/health fora do gate.
  Sem P2-13/proxy/lifecycle, sem `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P2-3 — `X-Forwarded-Proto` fail-closed:** FEITO no git após
  gates deste bloco. O origin deixa de honrar o proto do cliente
  (`$scheme`). `requireSecureSessionRequest` deixa de tratar `req.secure`
  como prova de TLS. HTTP + `X-Forwarded-Proto: https` no Host de origin
  → login 400. Canal oficial F2.1 intacto. Sem CSRF/DST/lifecycle, sem
  `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P2-17 — `LAYER7_TEST_NOW` só com `LAYER7_TEST_ROOT`:**
  FEITO no git após gates deste bloco. `layer7_license_now()` deixa de
  congelar a data de binding da GUI só pelo ambiente. O harness
  (`test_entitlements_gui.php`) continua a poder fixar o relógio sob
  raiz controlada. Sem P2-9/P2-13/P3, sem `PORTVERSION`, sem deploy
  (P0-1 ACTIVO). O daemon C não lê esta variável.
- **BG-128 M1 — fingerprint GUI via daemon:** FEITO no git após gates
  deste bloco. `layer7_local_hardware_id()` deixa a fórmula PHP
  (`sysctl kern.hostuuid` + `ifconfig -l ether`) e chama
  `/usr/local/sbin/layer7d --fingerprint` (CLI one-shot; empacotado em
  `PREFIX/sbin`). Saída inválida / `rc≠0` / binário ausente fecha.
  `LAYER7_TEST_HW_ID` só com `LAYER7_TEST_ROOT`. Sem P2-9/P2-13/P3, sem
  `PORTVERSION`, sem deploy (P0-1 ACTIVO). Equivalência FreeBSD no
  appliance **pendente**.
- **BG-128 A1/A2/M2 — lifecycle keep-config/upgrade fail-closed:**
  FEITO no git após gates deste bloco (`28c97ad` + governação). Staging de CA MITM e secrets
  Identity deixa `/tmp` e passa a `/var/db/layer7/deinstall-preserve`
  (0700). Segredos ficam 0600 em todo o fluxo. Se o backup obrigatório
  falhar, **não** há `rm -rf` de `/usr/local/etc/layer7`. Contratos
  keep-config / keep-license / upgrade / uninstall inalterados. Harness
  funcional em tempdir (quatro ramos + mutante de backup). Sem M1/P2-13/
  P2-9/P3, sem `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P2-11 — binding HW + expiry/grace na GUI/helper:**
  FEITO no git após gates deste bloco. `layer7_entitlements()` e
  `layer7-mitm-entitle-ok` deixam de desbloquear Identity/MITM com um
  `.lic` só assinado: exigem o mesmo binding do daemon (hardware_id
  exacto + expiry com grace de 14 dias). Fingerprint local sem processo
  do daemon (`sysctl kern.hostuuid` + `ifconfig -l ether`). Stats
  forjados continuam sem unlock. Sem P2-9/P2-13/P3, sem `PORTVERSION`,
  sem deploy (P0-1 ACTIVO).
- **BG-128 P2-7 + P2-8 + P2-10 — persistência check-in / `.lic`:**
  FEITO no git após gates deste bloco. `checkin_save_state`
  passa a tmp + `chmod 0600` + `rename` com escape JSON; `store_key` zera
  só `features`/`features_set`; `promote_activate_body` grava `.lic` 0600.
  Sem P2-9/P2-11/P2-13, sem `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 governação estado vs commit:** SSOTs/checklist não dizem
  «FEITO no git» nem marcam `[x]` antes de gates PASS, revisão do diff,
  staging por paths e commit. Até lá o estado é «implementado, pendente
  de gates/commit» (`AGENTS.md`).
- **BG-128 P1-5…P1-8 + P2-12 — package/daemon lifecycle:**
  FEITO no git após gates (`c2b9fdb` + governação neste commit).
  `layer7_checkin_enforce_ready()` recusa enforce se `check_in_enabled` e
  não houver `license_key` (air-gap = flag `false`; N3 intacto).
  `PKG_UPGRADE` e keep-config preservam `layer7.json` / `.lic` / CA MITM /
  `identity-*.secret` / estado de check-in. Uninstall real sem keep apaga
  os três paths em `/var/db` e remove anti-DoH no PRE-DEINSTALL. Revoke
  GUI chama `layer7_clear_local_license_state()`. Sem P2-9, sem
  `PORTVERSION`, sem deploy (P0-1 ACTIVO).
- **BG-128 P1-4 + P2-1 — bootstrap owner:** `init` adquire
  `LOCK TABLE admins IN SHARE ROW EXCLUSIVE MODE` e cria o primeiro admin
  já como owner activo com `*`. A promoção de legado no arranque fica
  limitada a `ORDER BY id ASC LIMIT 1`. Vários owners geram alerta e não
  são promovidos nem demovidos. Sem unique index, sem transferência, sem
  compose/.env/seed/SPA, sem deploy (P0-1 ACTIVO).
- **BG-128 P1-2 — XFF / rate-limit IP:** `getClientIp` deixa de usar o
  primeiro hop de `X-Forwarded-For` (cliente) e passa a `req.ip`
  (`trust proxy: 1`) ou ao socket. O nginx de origin substitui
  `X-Forwarded-For` por `$remote_addr` (já não `$proxy_add_x_forwarded_for`).
  `X-Forwarded-Proto` intacto (P2-3). Sem single-use TOTP, sem deploy
  (P0-1 ACTIVO), sem package/daemon/SPA.
- **BG-128 P1-3 — segundo factor TOTP:** `POST /api/auth/login/totp`
  recusa `is_active=false`; password OK com TOTP ligado deixa de resetar
  as guardas de login; falha TOTP incrementa o lock existente (email+IP)
  e devolve `401` genérico sem enumerar conta/desafio/código. Contrato de
  sucesso inalterado. Sem XFF/rate-limit (P1-2), sem single-use/bind
  (residual P0-2), sem deploy (P0-1 ACTIVO).
- **BG-128 P0-2 — TOTP challenge fail-closed:** o HMAC do `challenge_token`
  deixa de aceitar o fallback estático `layer7-totp-dev-secret`. Em produção
  (ou `NODE_ENV` vazio) a API recusa arrancar sem
  `ADMIN_BEARER_JWT_SECRET`/`JWT_SECRET`. `development`/`test` explícitos
  podem arrancar sem esses valores; o challenge continua recusado sem
  segredo. Sem deploy (P0-1 ACTIVO); sem package/daemon/SPA.
- **BG-128 P1-1 — check-in de chave arquivada:** `POST /api/license/check-in`
  com nonce passa a ver linhas `revoked`/`expired` arquivadas (replace/DELETE)
  e devolve envelope v2 assinado HTTP 409 com esse status. Chave inexistente
  continua 404. Sem deploy (P0-1 ACTIVO); sem package/daemon.
- **CI smoke layer7d (Linux):** `src/layer7d/features.h` foi renomeado para
  `l7_features.h` para não tapar `<features.h>` da glibc (`-I src/layer7d`).
  O workflow falhava em todo o push com `__GLIBC_USE (…)` / `SSL` em cadeia.
  Sem mudança de comportamento do daemon; **sem** novo `PORTVERSION`.

## [1.9.63] — 2026-08-14

### Added

- **20.35 / MITM como política:** a GUI passa a oferecer «Manter ligada até
  eu desligar» (`max_minutes=0`) além da janela temporizada 1–240 min.
  Copy de operador (sem `mitm_effective` / rdr / ficha). Default continua
  OFF; origem∧destino obrigatórios; break-glass e supervisor mantêm-se.
  Ficha/P5 retirados (ADR-0035). **Não** liga `.254`.
  Publicado `v1.9.63` (`SHA256=f47b1dd82e7d99f8a1f8e6bbd2fe101c0ed33688b45cfcfbb356367db853c373`).

## [1.9.62] — 2026-08-13

### Changed

- **GUI operador (BG-126):** páginas MITM, Identity e o texto de check-in
  em Definições deixam de mostrar ADRs, IDs de passo (`20.8`…), paths de
  `docs/`, checklist de lab e códigos internos (`N3`, `30.14`, `SKU Y`).
  O comportamento do add-on e da licença **não** muda — só a copy.
  **Não** promove enforce `1.9.8` nem MITM permanente.
  Publicado `v1.9.62` (`SHA256=b6700576afb47cf9790c4c3fddb746b3021d7070e260ef0e6551c712a7948e5f`).

## [1.9.61] — 2026-08-13

### Changed

- **Lista VIP (BG-124):** export/import deixa de exigir JSON. O formato
  canónico para o operador é texto simples, uma linha por isento
  (`192.168.1.60, Silvana`). A GUI passa a ter editor em lote (textarea).
  Import aceita `.txt` / `.csv` e JSON legado (incluindo vírgula final de
  edição manual).
- **Lista VIP (BG-125):** lê as reservas DHCP estáticas de cada interface
  (`dhcpd/<if>/staticmap` e DHCPv6) e permite adicionar os IPs prefixados
  à Lista VIP com o nome da reserva. GUI em colunas por interface, com
  filtro. Não isenta automaticamente.
  Daemon, `vip-isentos` e limites 32+16 inalterados.
  **Não** promove enforce `1.9.8` nem MITM permanente.
  Publicado `v1.9.61` (`SHA256=eda5a10e1a9ca597d3bf8051c0ee372840caddffa133abee5e8d9383a5dba426`).

## [1.9.60] — 2026-08-13

### Fixed

- **MITM rc.d / GA2.9:** `layer7-mitm-entitle-ok` passa a resolver PHP/Python
  por caminho absoluto (`/usr/local/bin/php`). O PATH do `rc.d` é
  `/sbin:/bin:/usr/sbin:/usr/bin` e não via `php` no PATH — o helper
  recusava arranque mesmo com `.lic` `mitm` assinado (P4 retry `170000Z`).
  Entitlement **não** foi enfraquecido. Teste PATH curto em
  `test_entitlements_gui.php`. **Não** promove enforce `1.9.8` nem MITM
  permanente. Soak P4 no `.254` **não** é actualizado neste bloco.
  Publicado `v1.9.60` (`SHA256=ec22d3b636adf73bbb6497c2bec05a6ae2c34984e0b92815bfb36dc8ff89329f`).

## [1.9.59] — 2026-08-13

### Added

- **MITM P4.1 — supervisor on-box:** cron `* * * * *` chama
  `layer7-mitm-window-tick.php` (armado no `pkg-install`, removido no
  deinstall). Expire/cleanup da janela sem GUI nem watchdog no Mac.
  Stamp `/var/run/layer7/mitm.window-supervisor` (armado se fresco ≤180 s);
  GUI mostra o estado. O tick **nunca** liga `mitm.enabled`.
  Testes `test_mitm_config.php` + `test_mitm_regress.php` PASS.
  Runbook: `docs/09-blocking/runbook-p4-retry-supervisor-onbox.md`.
  Publicado `v1.9.59` (`SHA256=64899e157d97adf659dfb265bff169801ffe6109f32d2f75377ca5963b2c34b9`).
  **Não** promove enforce `1.9.8` nem MITM permanente.

## [1.9.58] — 2026-08-13

### Security

- **BG-028 Fase 1 / ADR-0023 — primeira publish F1.2:** GitHub Release
  `v1.9.58` em `pablomichelin/Layer7` com manifesto Ed25519, `.sig`, chave
  pública, `install.sh` carimbado fail-closed e `uninstall.sh`. Fingerprint
  SHA256 da chave pública:
  `d26e3f007e81298bad910f99dd62a22e2109740158b3b3c7f4e79490bdc5a998`.
  Custódia da privada: humana, fora do git e do builder. **Não** promove
  `.254` / GA5.9 neste bloco. Evidência
  `docs/tests/evidence/20260813T154800Z-bg028-f12-publish/`.

- **Anti-pirataria 30.17 — marcação por cliente (atribuição):** após update
  autenticado (ou `--stamp-attribution`), grava sidecars opacos
  `.state/content-attribution.json` + `.l7-content-attribution`
  (`mark=SHA256(l7-attr-v1:license_id:hardware_id)`); sem PII cleartext; sem
  telemetria; não altera tar/manifesto. Doc privacidade
  `docs/01-architecture/marcacao-cliente-30.17.md`. GA6.3/GA6.4 PASS.

- **Anti-pirataria 30.16 / BG-122 — decisão de licença distribuída:** gates A/B
  com cruzamento (`license_enforce_gate.c`); `refresh_enforce_cfg` + hot-paths
  (`enforce_armed`) deixam de depender do `if` único sobre `s_lic.valid`
  (mitiga A-02; R-A permanece). Unit N1/N2 + anti-forja **PASS**. GA6.1/GA6.2
  PASS (unit).

- **Anti-pirataria 30.14 / BG-118 — check-in default ON (GO humano):**
  `check_in_enabled: true` em `layer7.json.sample` / bare config (instalações
  novas); upgrade **não** altera valor já gravado (`false`/ausente = OFF);
  GUI toggle + runbook isolados
  (`docs/13-runbooks/check-in-migration-30.14.md`); **N3** intacto. GA5.7/5.8/5.10/5.11
  PASS; GA5.9 campo PENDENTE.

### Added

- **Anti-pirataria 30.13 / BG-119 — check-in assinado com nonce:** license-server
  dual-mode (pedido com `nonce` → envelope `{data,sig}` Ed25519; sem nonce →
  JSON legado ADR-0021); `layer7d` gera nonce CSPRNG/base64url, verifica
  assinatura + eco nonce/hardware_id + skew `iat`, rejeita resposta não
  assinada. GA5.2–5.6 PASS (unit).

### Documentation

- **Anti-pirataria 30.19 — fecho da trilha AP0–AP4:** GA6.7–6.12 **PASS**
  (agenda EULA; reavaliação ameaças; RR-1…RR-5; R-L/CE; decisão 8/RR-3;
  tags **não** alteradas). Doc
  `docs/01-architecture/fecho-trilha-antipirataria-30.19.md`.

- **Anti-pirataria 30.18 / BG-123 — cadeia F1.2 no processo de release:**
  política obrigatória manifesto + `.sig` + pubkey; dry-run
  `tests/functional/test_release_signing_f12_30.18.sh`. Activação de campo
  neste `1.9.58` (BG-028).

- **Anti-pirataria 30.11 — GA4.12 N/A + prep cut:** decisão humana
  (`2026-08-12`): comunicação externa a clientes não necessária (decisões
  internas; sem destinatários; impacto futuro → janela de manutenção ops).
  Gate GA4.12 = **N/A**. Cut / GA4.10 / GA4.15 executados no passo `30.11`.

- **Anti-pirataria 30.11 preflight — primary auth GET PASS:** em `.254`
  (`1.9.54`), GET HTTPS a `downloads.systemup.inf.br/.../manifest` + `.sig`
  com Bearer local → **200/200** (823/64); sem token → **401**. Evidência
  `docs/tests/evidence/20260812T003214Z-30.11-auth-get-254/`.

- **Anti-pirataria 30.10 — e2e `.254` PASS com `1.9.54`:** GA4.4 PASS;
  produção mantida em `1.9.54`; evidência
  `docs/tests/evidence/20260811T114320Z-30.10-e2e-154-254/`.

### Release

- Canal publico `latest` — F1.2 completo em `pablomichelin/Layer7`
- SHA256: `8b4a2dc6ecd62c126222186112ea80ee75407d35c35049f94631980092108d3d`
- Rollback lab: `1.9.54`
- Produção `.254` **não** actualizada (`1.9.54`)

## [1.9.54] — 2026-08-11

### Fixed

- **Anti-pirataria 30.10 / BG-117 — `fetch_authed` redirects:** update autenticado
  segue redirects HTTPS (caso GitHub Releases 302→CDN) sem `--location-trusted`.
  Credenciais `Authorization: Bearer` e `X-Layer7-Content-Token` ficam no host
  actual e são omitidas em cross-host; Location não-HTTPS é recusada (máx. 5
  hops). Preserva hold-active/enforce sem token (contrato 30.8). Testes
  regressivos 302→200 + anti-leak em
  `tests/functional/test_content_subscription_update.sh`. Pubkey SoT inalterada.
  Sem `30.11` / license-server / nginx. **Campo:** e2e `.254` **PASS**
  (`20260811T114320Z`) — produção `1.9.54`. Rollback lab: `1.9.53`.

### Release

- Canal publico `latest` — `.pkg` + `.sha256` em `pablomichelin/Layer7`
- SHA256: `e9935975990448d46aaf1f6e598d2b76b986f43d5df8b50a5aee35000aa0351a`
- Rollback lab: `1.9.53`

## [1.9.53] — 2026-08-10

### Security

- **Anti-pirataria 30.10 / BG-117:** cliente de update de blacklists exige token
  de subscrição de conteúdo válido (contrato 30.8). Check-in activo persiste
  `/var/db/layer7/content-subscription.json` (`0600`). Sem token válido:
  **não** contacta URLs `current`; mantém snapshot local; enforce intacto
  (R-D/R-C/N3/N4). Fetch autenticado com `Authorization: Bearer` +
  `X-Layer7-Content-Token`. GUI (Blacklists/Settings) mostra estado da
  subscrição. Runbook `docs/13-runbooks/content-subscription-update.md`.
  Manifesto Ed25519 (ADR-0005) continua obrigatório. Pubkey SoT inalterada.
  Sem retirada do espelho (`30.11`). Testes locais/builder PASS
  (`test_content_subscription_update.sh`, `test_content_subscription_client.php`).
  **Nota operacional (`2026-08-11`):** GA4.4 campo **BLOCKED** — license-server
  live ainda sem 30.9; não promover em produção até deploy + revalidação.
  Herda 30.7–30.9 (repo).

### Changed

- **Anti-pirataria 30.9 / BG-117 (license-server, sem bump):** `POST /api/license/check-in`
  activo emite `content_subscription` (Ed25519, TTL 30d). GA4.2/GA4.3/GA4.13
  PASS — consolidado na trilha com `1.9.53` no cliente.

### Release

- Canal publico `latest` — `.pkg` + `.sha256` em `pablomichelin/Layer7`
- SHA256: `5a13e3b7c4272c98e975e4af499aaf5f7f990600a3ebc1a6423140dcaae4a1b4`
- Rollback lab: `1.9.52`

## [1.9.52] — 2026-08-10

### Security

- **Anti-pirataria 30.7 / BG-120:** entitlements da GUI derivados apenas de
  `.lic` Ed25519 verificado (`openssl pkeyutl`; PHP `openssl_verify` inviável
  neste stack). Stats / `.lic` sem sig / check-in sozinho **não** desbloqueiam
  Identity/MITM. Check-in só intersecta (retira bits). PEM
  `license-signing-public-key.pem`. Gate rc.d `layer7-mitm-entitle-ok` (GA2.9).
  Sem assinatura local no daemon (ADR-0030 / R-A). GA2.8–2.10 PASS
  (`tests/functional/test_entitlements_gui.php`). Pubkey SoT inalterada.
  Herda 30.4–30.6.

### Release

- Canal publico `latest` — `.pkg` + `.sha256` em `pablomichelin/Layer7`
- SHA256: `79312d1b73eb8744be817c9ef2b9a7cdf768439632dabbad35e9fb7bfa607134`
- Rollback lab: `1.9.51`

## [1.9.51] — 2026-08-10

### Security

- **Anti-pirataria 30.6 / BG-116:** anti-rollback de relógio (ADR-0033). Marca
  persistente em `/var/db/layer7/clock-mark.json`; retrocesso >1 dia ⇒
  `clock_suspect`, enforce degradado para monitor + `L7_AUDIT_NOTE`.
  Recuperação N6: sincronizar hora e reiniciar `layer7d` (runbook
  `docs/13-runbooks/anti-rollback-relogio.md`). RR-4 declarado. Teste
  `tests/functional/test_license_clock.c`. Pubkey SoT inalterada. Herda
  30.4/30.5.

### Release

- Canal publico `latest` — `.pkg` + `.sha256` em `pablomichelin/Layer7`
- SHA256: `aec3642824df0fd8b3a49d9cc41b4b8a30e8c88dd5be6d6da7e142965b722204`
- Rollback lab: `1.9.50`

## [1.9.50] — 2026-08-10

### Security

- **Anti-pirataria 30.5 / BG-115:** strip explícito (`${STRIP_CMD}`) no
  `INSTALL_PROGRAM` do port para `layer7d` e `layer7-tlsproxy`;
  `-fvisibility=hidden` nos binários standalone. `nm`/`strings` sem
  `is_dev_key` / `layer7_license_check`. Gate GA2.4 / GA2.5 / GA2.11 PASS
  (`scripts/package/test-prod-strip.sh`). Sem ofuscação (R-G). Limite
  aceite: core dumps de produção menos legíveis. Pubkey SoT inalterada.
  Herda `30.4` (`is_dev_key` só sob `L7_DEV_BUILD`).

### Release

- Canal publico `latest` — `.pkg` + `.sha256` em `pablomichelin/Layer7`
- SHA256: `3598828d057948732efb10ac0e958b3078f93a7ce86ad35f73d5f5ce086ec85e`
- Rollback lab: `1.9.49`

## [1.9.49] — 2026-08-10

### Security

- **Anti-pirataria 30.4 / BG-114:** `is_dev_key()` e o bypass de desenvolvimento
  existem apenas sob `#ifdef L7_DEV_BUILD` (flag ausente do Makefile do port).
  Pubkey all-zeros num build de produção ⇒ licença inválida (monitor), nunca
  válida. Gate GA2.1–2.3 PASS (`scripts/package/test-prod-no-dev-bypass.sh`).
  Pubkey SoT inalterada (GA1.8).

### Release

- Canal publico `latest` — `.pkg` + `.sha256` em `pablomichelin/Layer7`
- SHA256: `f380ad493c5229fc08704673abf758edaa5e15ea05061820d04bb9abdca4d3cb`
- Rollback lab: `1.9.48`

## [1.9.48] — 2026-08-10

### Added

- **GUI Diagnósticos — Reportar erro:** fluxo opt-in em 3 passos
  (descrever → pré-visualizar metadados seguros → abrir GitHub ou
  copiar URL). Issue pré-preenchida em `pablomichelin/Layer7` com
  versão pkg/daemon, modo, model, contagem de interfaces e flag MITM.
  Não envia `.lic`, chaves, logs, dumps nem IPs de clientes. Sem
  telemetria/backend. Helpers
  `layer7_error_report_safe_context` /
  `layer7_error_report_issue_url` em `layer7.inc`. Regressão
  `package/pfSense-pkg-layer7/tests/test_error_report.php`.
- **Pack produto:** índice navegável
  `docs/00-overview/pack-produto-layer7.md` + PRD / UML / catálogo
  reorganizados (TOC, badges, diagramas Mermaid legíveis, fluxo Report).

### Release

- Canal publico `latest` — `.pkg` + `.sha256` em `pablomichelin/Layer7`
- SHA256: `78fb0cfd151d2d32c19d8892ed176df8992f9c265a0d88fdfd005a624eab84eb`
- Rollback lab: `1.9.47`

## [1.9.47] — 2026-08-09

### Added

- **P3 MITM janela failsafe + visibilidade:** `mitm.window.max_minutes`
  (1–240, default 15) e `deadline_unix` explícito; `mitm_effective`
  fail-closed se expirado; auto-disable persiste `enabled=OFF` via
  `layer7_mitm_expire_if_needed` (sync/resync/GUI); GUI mostra src/dst/
  block_sni, `quic_mode`, deadline e tempo restante; break-glass OFF;
  audit metadados em `/var/log/layer7-mitm-audit.log`
  (activate/deactivate/expire/break_glass/failsafe) — **zero** payload TLS.
  Sem alargar rdr (`from any` proibido). Suite builder PASS
  (`docs/tests/evidence/20260809T230400Z-p3-mitm-window/`).

### Release

- Canal publico `latest` — `.pkg` + `.sha256` em `pablomichelin/Layer7`
- SHA256: `2155daca7f80eb0c90af4f736d71131d01d22b63942831aa1c0191240f9df833`
- Rollback lab: `1.9.46`
- Activação piloto externa continua **NO-GO** até ficha site + soak (P4/P5)

## [1.9.46] — 2026-08-09

### Fixed

- **Anti-QUIC / HTTP3 no escopo MITM:** com `mitm_effective` + control-plane
  materializado, `layer7_generate_mitm_quic_filter_rules_text()` emite
  `block drop quick inet proto udp from <layer7_mitm_src> to <layer7_mitm_dst>
  port 443` em `pfearly` (antes do pass LAN), com `table … persist` para
  `pfctl -nf` isolado. Força fallback TCP para o rdr MITM. Sem regra global,
  sem IPv6; activada/desactivada com o MITM; teardown remove. Contrato:
  `layer7_mitm_quic_line_ok`. Regressões em
  `package/pfSense-pkg-layer7/tests/test_mitm_regress.php`.
- **`layer7_filter_configure_safe` sync real:** invoca
  `/etc/rc.filter_configure_sync` (bootstrap shaper/ipsec/vpn), não
  `filter_configure()`/`send_event` async nem `php -r` só com `filter.inc`
  (falha dummynet). Materializa rdr/quic antes do retorno.
- Gate Edge: **proibido** `--disable-quic` como critério — o produto trata QUIC.

### Release

- Tag `v1.9.46` / `releases/latest` — Gate B+C **PASS**
  (`docs/tests/evidence/20260809T210753Z-phaseBD-d1-254/`; Edge sem flags)
- SHA256: `10998477ef7ae966e6c3566baeb973f922858fc72cc4d3a2dcdd0fb17bae72f5`
- Rollback lab: `1.9.42`

## [1.9.45] — 2026-08-09 (candidata lab)

### Fixed

- **Tabelas PF MITM não materializadas:** `layer7_mitm_tables_apply_to_pf()`
  (ensure+replace `layer7_mitm_src/dst`) após `filter_configure_safe` /
  `layer7_pf_config_resync`. Sem isto o rdr existia mas o cliente via a
  origem. Diagnóstico lab:
  `docs/tests/evidence/20260809T204452Z-phaseBD-d1-254/` (Edge c/
  `--disable-quic` = **não** Gate C PASS; ver `1.9.46` anti-QUIC).

### Release

- Candidata lab — tabelas PF; supersedida por `1.9.46` para Gate C
- Rollback: `1.9.42` / `1.9.44` sem tabelas apply

## [1.9.44] — 2026-08-09 (candidata lab)

### Fixed

- **Sync MITM ~25s / helper morto pelo `timeout`:** `layer7_exec_timeout`
  passa a `timeout --foreground -k <grace>` e o rc.d usa `daemon -f`.
  Sem `--foreground`, o FreeBSD `timeout` acompanha o process group do
  `layer7-tlsproxy` daemonizado, não regressa no `onestart` e mata o helper
  após ~20s+grace (`sync_sec≈25.5`). Prova:
  `docs/tests/evidence/20260809T202500Z-sync-timeout-foreground-fix/`
  (`SYNC=ok sync_sec=0.332`, MITM left OFF). Consolida correcções `1.9.43`
  (D1 leaf `0.1.3`, lifecycle, TLS sem bypass).
- **Harness hang:** mock `onerestart` usa `exec /bin/sleep` para TERM atingir
  o processo sob `--foreground` (Gate B4).

### Release

- Candidata lab — **Gate B PASS** no builder; publicar `.pkg`+SHA quando GO
- Rollback: `1.9.42`

## [1.9.43] — 2026-08-09 (candidata lab; supersedida por 1.9.44)

### Fixed

- **Control-plane finito (B+D hang):** `layer7_exec_timeout()` + timeouts
  (`sysrc`/`service`/`fetch`/`pkg`/`activate`); `layer7_mitm_sync_helper` e
  blockpage/daemon restart deixam de usar `exec()` sem limite; em falha/timeout
  MITM faz limpeza idempotente (gate/flag/`onestop`/sysrc NO/flush) e erro
  explícito na GUI. Causa do hang do activador B+D: `service … onerestart`
  síncrono sem timeout. Testes:
  `tests/functional/test_ctrl_exec_timeout.php`,
  `tests/harness/mitm-activate-hang/run-local-timeout-fix.sh`.

- **ETAPA 2 / D0 F1-bis (`timeout` sem `-k`):** `layer7_exec_timeout` passa a
  usar `/usr/bin/timeout -k <L7_CTRL_TIMEOUT_KILL_GRACE> <sec>` (default grace
  5s) para `SIGKILL` se o filho ignorar `SIGTERM` — fecha o hang observado em
  `sync_sec=141` com constante 20s (`20260809T195101Z` + repro builder).
  Fallback `proc_open` espelha TERM→grace→KILL. Teste: filho `trap '' TERM`.

- **Correcções mínimas pós-D0 (uma por causa):** ver
  `docs/09-blocking/correccoes-minimas-1.9.43-pos-D0.md` —
  (1) leaf SNI D1; (2) `timeout -k`; (3) sync `onestop`+`onestart` +
  `layer7_mitm_helper_listening()` se timeout com listen já UP (não limpar
  runtime bom — `195101Z`).

- **Fail-closed scope MITM:** `source_cidr` ∧ `dest_cidr` IPv4 explícitos
  obrigatórios para `mitm_effective` / helper / rdr; vazio, `any`,
  `0.0.0.0/0` ou inválido ⇒ OFF (`layer7_mitm_intercept_scope_ok` +
  `layer7_mitm_validate`). Sem helper em loopback sem scope.

- **Anti-expansão MITM (rdr):** proibido `from any` / `to any`, rdr genérico,
  `inet6`/`::1` implícito, prefixo IPv4 `</8`, e activação com tokens
  proibidos misturados (validate falha — sem aceitar o resto em silêncio).
  Contrato de linha: `layer7_mitm_rdr_line_ok`.

- **Lifecycle fail-safe / teardown / rollback:** rdr só com control-plane
  materializado (gate+flag); `layer7_mitm_ctrl_cleanup` teardown limpo
  (onestop→pkill se necessário, sysrc NO, flush tabelas, gate/flag); em falha
  de sync a GUI chama `layer7_mitm_failsafe_rollback` (enabled=OFF +
  `filter_configure_safe`). Idempotente.

- **filter_configure bounded:** `layer7_filter_configure_safe` — anti-reentrada
  (resync/depth/lock), timeout `L7_CTRL_TIMEOUT_FILTER` (subprocess + `-k`),
  skip idempotente se lock ocupado. MITM enable/disable/reload idempotentes
  (mesmo gate+listen ⇒ sem bounce). GUI MITM/settings/allowlist/diagnostics
  e `layer7_pf_config_resync` / `layer7_bl_apply` passam pelo wrapper.

- **TLS sem bypass:** política
  `docs/09-blocking/politica-tls-sem-bypass.md` — proibido
  `--ignore-certificate-errors` / `curl -k` / `CERT_NONE` nos gates MITM/Edge;
  harnesses e PoC passam a `-CAfile`/`CERT_REQUIRED` + helper `tls-http-get.sh`.

- **Testes de regressão próximos ao código:**
  `src/layer7-tlsproxy/test-regress.sh` (`make test-regress`) e
  `package/pfSense-pkg-layer7/tests/test_mitm_regress.php` (também em
  `tests/run-local.sh`).

- **Gates obrigatórios `1.9.43`:** SSOT
  `docs/09-blocking/gates-obrigatorios-1.9.43-mitm.md` (builder antes de
  publish; B+D Edge humano; `.254` DEFER).

- **Gate D1 / Edge TLS (B+D NO-GO):** `layer7-tlsproxy` `0.1.3` — se
  `--cert/--key` forem CA (`CA:TRUE`), minta leaf por SNI (`serverAuth`,
  `digitalSignature`/`keyEncipherment`, SAN=DNS:SNI, SKI/AKI, SHA-256);
  verifica issuer/assinatura (`l7_leaf_identity_ok`); CA **não** é peer nem
  vai na cadeia. Corrige `ERR_SSL_KEY_USAGE_INCOMPATIBLE`. CA GUI gerada com
  extensões `CA:TRUE`+`keyCertSign`+SKI; import rejeita leaf. Smoke:
  `tests/harness/mitm-activate-hang/run-local-tls-leaf-fix.sh`.
  **Edge `.24` / novo B+D:** ainda exige GO humano (sem activar `.254` neste bloco).

### Release

- Candidata lab — publicar `.pkg` + SHA em `pablomichelin/Layer7` quando build OK
- Rollback: `1.9.42`

## [1.9.42] — 2026-08-09

### Security

- **BG-087 / hardening MITM source scope:** `intercept.source_cidr` (IPv4, default vazio)
  obrigatório **em conjunto** com `dest_cidr` para emitir rdr; tabelas
  `<layer7_mitm_src>` + `<layer7_mitm_dst>`; forma
  `from <layer7_mitm_src> to <layer7_mitm_dst>` — **proibido** `from any`.
  Dest-only (regressão `1.9.41`) = zero rdr. Anti-lockout/self, limites,
  dedupe, rejeição `any`/`0.0.0.0/0`/IPv6/loopback. Lifecycle OFF/uninstall
  limpa ambas as tabelas. GUI PME com ajuda clara. Evidência:
  `docs/tests/evidence/20260809T173500Z-1.9.42-source-cidr/`.

### Release

- Tag `v1.9.42` / `releases/latest`
- SHA256: `6bd6ba374b398ec82cd43ea2246f16a3774f4377d3cac6411265472d3d3a4c4b`
- Rollback: `1.9.41`

## [1.9.41] — 2026-08-09

### Fixed

- **BG-087 / 20.10b correctivo — auditoria adversária do `1.9.40`:**
  - F1: rdr MITM emite em modo **monitor** (não só enforce).
  - F2: CA generate/import/delete sincroniza helper + `filter_configure`.
  - F3: deinstall para tlsproxy, remove gate/flag, flush tabelas MITM.
  - F4: sem rdr IPv6 sem listener `::1` (só IPv4 → `127.0.0.1`).
  - F5: daemon reporta `mitm_effective` via flag `/var/run/layer7/mitm.effective`.
  - F6: exclusão de IPs/CIDRs do appliance em `dest_cidr` (anti-lockout).
  Evidência: `docs/tests/evidence/20260809T053000Z-20.10b-postrelease-audit/`.

### Release

- Tag `v1.9.41` / `releases/latest`
- SHA256: `1518ad6825aad51bb97897335e441ac630be0ce6af74b80738ec06e77ca0c1f4`
- Rollback: `1.9.40`

## [1.9.40] — 2026-08-09

### Added

- **BG-087 / 20.10b — Listen selectivo + PF rdr + página HTTPS:**
  `intercept_ready=true`; helper `--product-listen` (loopback only) gated por
  ficheiro rc `/var/run/layer7/tlsproxy.product` só com `mitm_effective`;
  gerador `layer7_generate_mitm_rdr_snippet()` selectivo via
  `mitm.intercept.dest_cidr` (vazio = zero rdr); página HTML HTTPS no helper;
  GUI campos dest/block SNI. Default OFF; Squid rejeitado. GI2/GI3 não
  fechados. Sem activação em produção `.254/.234/.235`. Evidência:
  `docs/tests/evidence/20260809T053000Z-20.10b-listen-rdr-https-54/`.

### Release

- Tag `v1.9.40` / `releases/latest`
- SHA256: `fbbf206d1b159722a28073dd402f9b0c8ef381eff07eb3a886e5ef8310a41afe`
- Rollback: `1.9.39`


## [1.9.39] — 2026-08-09

### Added

- **BG-087 / 20.10a — Runtime empacotado (GO produto):** `layer7-tlsproxy` `0.1.0`
  no `.pkg`; `rc.d` default OFF (recusa start até intercept_ready); PHP
  `layer7_mitm_runtime_available()` detecta binário;
  `layer7_mitm_intercept_ready()=false` ⇒ `mitm_effective` permanece false;
  daemon reporta `mitm_runtime_available` via `access(2)`. Sem PF rdr / sem
  intercept. Squid rejeitado. GO: `docs/09-blocking/GO-produto-20.10.md`.

### Release

- Tag `v1.9.39` / `releases/latest`
- SHA256: `6e7f4e9fe751c73a0dbdb990bd7799b37aa6136288dcb3d3941d1b42f2f4f4c9`
- Rollback: `1.9.38`


## [1.9.38] — 2026-08-08

### Added

- **BG-087 / 20.9 — Toggle intenção + bypass endurecido:** `mitm.enabled`
  (intenção com CA+entitlement) vs `mitm_effective` (false sem runtime);
  `quic_mode` default (contribui a **S5 parcial**; S5 lab ainda aberto);
  CIDR protegidos loopback; contrato IPC
  `docs/01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md`;
  status `mitm_effective=false`. Sem intercept TLS. Squid rejeitado.

### Release

- Tag `v1.9.38` / `releases/latest`
- SHA256: `7c60f6b1a052b675fd064825bd7f0ae79012143b271215d39ed9848b059d1dab`
- Rollback: `1.9.37`


## [1.9.37] — 2026-08-08

### Added

- **BG-087 / 20.8 — MITM scaffolding:** schema `mitm.*` (default OFF); gestão CA
  (gerar/importar/exportar/remover; chave 0600 fora do JSON); bypass SNI/CIDR;
  GUI `layer7_mitm.php`; `mitm_entitled` + `mitm_runtime_available=false` no
  status do daemon. `enabled` forçado false sem `layer7-tlsproxy`.
  Teste: `tests/functional/test_mitm_config.php`. Desenho:
  `docs/01-architecture/desenho-layer7-tlsproxy-mitm.md`. Squid rejeitado.

### Release

- Tag `v1.9.37` (foi `releases/latest` até `1.9.38`)
- SHA256: `d80b522d386165dcd540c8c90577e3292e6b31c4cd7600305ba5f70fec223868`
- Rollback: `1.9.36`

## [1.9.36] — 2026-08-08

### Changed

- **BG-111 — Perfis rápidos: categorias colapsadas:** todas as categorias
  começam fechadas (sem reabrir via localStorage); badges de activos e de
  alterações pendentes no cabeçalho; botões Expandir/Recolher tudo.
  BG-110 (aplicar em lote) mantém-se.

### Release

- Tag `v1.9.36` / `releases/latest`
- SHA256: `abfd772f7cd959b10ff488f7980f6c295e5185fe254a4fc5ffdb3ae88d15a71b`
- Rollback: `1.9.35`

## [1.9.35] — 2026-08-08

### Changed

- **BG-110 — Perfis rápidos em lote:** switches passam a rascunho local; barra
  **Aplicar / Descartar**; um único `save` + `layer7_pf_config_resync` para N
  perfis (em vez de ~20s por clique). AJAX com fallback form.

### Release

- Tag `v1.9.35` / `releases/latest`
- SHA256: `5f88e1312bc30037ffb32141a208860a708440b5271c1adf55c40a2aa992f4f4`
- Rollback: `1.9.34`

## [1.9.34] — 2026-08-08

### Changed

- **Redeploy público do BG-108:** mesma funcionalidade de `1.9.33` (KPI unificado,
  subnav Políticas, Chart.js offline, Kick/Rumble→Streaming, admin-block),
  publicada como **`1.9.34`** para permitir upgrade limpo via pacote em todos os
  clientes (o `1.9.33` tinha sido rebuild/promovido in-place com SHA diferente).

### Release

- Tag `v1.9.34` / `releases/latest`
- SHA256: `87982ffb33d533c23638f22ddd481b2d0c85f5a8aa020f4d92583080e8010eec`
- Rollback: `1.9.33` ou `1.9.30`

## [1.9.33] — 2026-08-08

### Changed

- **BG-108 — UX visual wave 2:** unificação dos KPIs Estado/Relatórios
  (classes partilhadas `.l7-kpi-card*`), Status e Remoção em `layer7-admin-block`,
  subnav canónica de Políticas (`layer7_render_policies_subnav`), Kick/Rumble
  movidos para o grupo Streaming (IDs de política intactos), meta de perfis
  só-categoria (`N cats`), Chart.js 4.4.4 vendored em
  `packages/layer7/js/chart.umd.min.js` (sem CDN; empty state se ausente),
  tipografia do preset «Distracoes no trabalho». Sem mudança de enforcement.
  Mapa Identity live fica em BG-109.
- **BG-107 — Perfis rápidos / layout:** cores de marca RA, aliases FA6, grelha
  `auto-fill`, agregados «(todas/todos)», redirect RA→`#l7-ra`, admin-block em
  Identity/Exceptions/Categories/Test/Reports.

### Fixed

- **BG-108:** Chart.js offline; Kick/Rumble em Streaming; tipografia
  «Distraccoes»; cartões só-categoria sem `0 apps · 0 hosts` vazio.
- **BG-106 (operacional):** builder permanece FreeBSD 15; install Plus/16 com
  `IGNORE_OSVERSION=yes pkg add -f` documentado no MANUAL.

### Release

- Tag `v1.9.33` / `releases/latest`
- SHA256: `71e55377fef2de7051b472f3253e51dc56bcb1ca36bd4f9b708ae5418590ec29`
- Rollback: `1.9.30`

## [1.9.30] — 2026-08-08

### Fixed

- Grupo **Acesso remoto** com perfis individuais + pacote «Acesso Remoto (todos)»;
  LogMeIn no catálogo.

### Release

- SHA256: `40b9046f33d3c02cd9c472e3cf9ee98c961ffcda7966b20a9cf0a64f6e20a2bf`

### Fixed (trilha anterior ainda em Unreleased documental)

- **BG-105 — sinkhole DNS/local portal:** a decisão DNS bloqueada para o IP
  local do firewall continua auditável como `outcome=sinkhole`, mas o fluxo
  subsequente ao portal não volta a ser classificado nem avaliado por
  políticas. Isso elimina o ciclo de eventos enganosos e o log storm
  `enforce_block: skip IP local do firewall` observado no appliance.
- Regressão dedicada em `tests/unit/test_sinkhole_local_guard.sh`, integrada
  em `tests/run-local.sh`: confirma a guarda antes de `layer7_flow_decide()`,
  auditoria do DNS e diagnóstico de repetição apenas em nível debug.
- **Gate de pacote:** `smoke-layer7d.sh` passa a compilar os três módulos
  Identity referenciados por `main.c`, mantendo a lista de fontes alinhada ao
  `Makefile` do port e evitando falso erro de linker no builder.

### Notes

- Candidato validado no builder: `1.9.31` (`SHA256
  dcbad868b3e06c19214662dd4caf5ac82417c9af4cb17fddff8cbfc425ffcd15`).
  Suite local, smoke FreeBSD, build e metadados do `.pkg` passaram.
- Instalação controlada em `192.168.100.254`: daemon, configuração e PF
  válidos; `dns.google` e `mask.icloud.com` permanecem bloqueados por
  sinkhole nos clientes `.234`/`.235`, sem nova ocorrência do log storm;
  navegação HTTPS normal passou. A auditoria do callback DNS do daemon não
  foi observada porque o Unbound encerrou TLS antes de nDPI classificar,
  com SNI desativado. Não é release pública; rollback `1.9.30` foi validado
  antes do upgrade e mantido no appliance.
- O install exigiu `pkg add -f`: o artefato vem de builder FreeBSD 15 e o
  appliance é FreeBSD 16. `pkg check`, `ldd`, daemon e PF passaram, mas
  **BG-106** bloqueia publicação até haver artefato nativo ou compatibilidade
  formalmente fechada.

## [1.9.30] — 2026-08-08

### Added

- **Perfis rápidos — grupo Acesso remoto:** cartões individuais (AnyDesk,
  TeamViewer, RustDesk, RDP, VNC, LogMeIn, RMM, etc.) + pacote
  «Acesso Remoto (todos)»; um toggle por software.
- Catálogo `remote-access-catalog.json`: entrada **LogMeIn / GoTo Resolve**.

### Notes

- Canal `latest`: **`1.9.30`**. Produção enforce **`1.9.8`**.
- Limite daemon/GUI de **24 políticas** activas mantém-se — usar cartões
  selectivos ou o pacote agregado.
- SHA256: `40b9046f33d3c02cd9c472e3cf9ee98c961ffcda7966b20a9cf0a64f6e20a2bf`. Rollback lab: `1.9.29`.

## [1.9.29] — 2026-08-08

### Added

- **Identity 20.28–20.30 / GI8 / ADR-0029:** sequência segura — ADIAR agente
  endpoint; exclusão TS/VDI; GUI Identity H* (limite honesto).
- **Identity 20.27:** especificação agente endpoint (conservada para reopen).
- **Identity 20.31–20.33 / IM9 / GI9:** malha OFF + MANUAL/notes + homologação
  two-client real (`20260808T174100Z-im9-20.33-homolog-1.9.29`).

### Notes

- Canal `latest`: **`1.9.29`**. Produção enforce **`1.9.8`**.
- SHA256: `cab8d2d13e12e57f6078d1f3a4a15b90dcc6c19e953f6f79409f910502c45fec`. Rollback lab: `1.9.28`.

## [1.9.28] — 2026-08-08

### Added

- **Identity 20.24 / IM6:** match de `ad_users`/`ad_groups` no motor de
  decisão via mapa daemon (`lookup_ip_ex`); Identity OFF / multi_user /
  user ausente → não-match `ad_*` (políticas IP/MAC intactas).
  `layer7_policies_set_identity_map` no hot path.

### Notes

- Canal `latest`: **`1.9.28`**. Produção enforce **`1.9.8`**.
- SHA256: `510c29c8c10ec48ebcac10056db980de0c379d8073a344548b2a2ca2eff76923`. Rollback lab: `1.9.27`.

## [1.9.27] — 2026-08-08

### Added

- **Identity 20.23 / IM6:** políticas aceitam `match.ad_users` /
  `match.ad_groups` (daemon parse + normalização; GUI Policies com
  entitlement Identity). Distinto de `match.groups` (IP/MAC Layer7).
  Match/enforce via mapa fica no **20.24**.

### Notes

- Canal `latest`: **`1.9.27`**. Produção enforce **`1.9.8`**.
- SHA256: `ab92ad64f59a6acf87ed8c5a868c4fd79fa8c3d594100b09d60db68cad671a2b`. Rollback lab: `1.9.26`.

## [1.9.26] — 2026-08-08

### Added

- **Identity 20.22:** audit `identity_ip_conflict` / `identity_ip_last_writer`
  (syslog); contadores no mapa/status; recompute `multi_user`; nota UX
  topologia na GUI Identity.

### Notes

- Canal `latest`: **`1.9.26`**. Produção enforce **`1.9.8`**.
- SHA256: `d1399c711091a7c4b0bbccfef9f71c6016ef9f2423eca8deb190d3c8a79bd5db`. Rollback lab: `1.9.25`.

## [1.9.25] — 2026-08-08

### Added

- **Identity 20.21:** normalização de username (`DOMAIN\user` / UPN → chave
  canónica lowercase) + `layer7_idmap_remove_ip` (logoff/Stop multi-IP);
  RADIUS e agente DC partilham a mesma chave no mapa.
  Testes em `test_identity_map.c`.

### Notes

- Canal `latest`: **`1.9.25`**. Produção enforce **`1.9.8`**.
- SHA256: `967f059f90c09b388d93baa4bb4546a407fed5b2ed5c1f14193e5b54fb356006`. Rollback lab: `1.9.24`.

## [1.9.24] — 2026-08-08

### Added

- **Identity DC agent Windows (IM5 / fecho 20.20):** serviço leve no DC —
  `docs/samples/identity-dc-agent/Layer7IdentityDcAgent.ps1` (Event Log
  4624/4634/4647 → HTTPS+HMAC); `Install-`/`Uninstall-` Scheduled Task;
  `config.example.json` + README (PME). Cliente lab `Send-Layer7IdentityEvent.ps1`
  mantém-se.

### Changed

- GUI Identity: help do receiver aponta para a pasta `identity-dc-agent`.

### Notes

- Canal `latest`: **`1.9.24`**. Produção enforce **`1.9.8`**.
- SHA256: `7490a5950fd3bcb0bafaaeed01e88afda58db83f86dcaf215a6c74fff9c29bc1`. Rollback lab: `1.9.23`.
- GI6 lab (DC físico) residual. MITM permanece DEFER.

## [1.9.23] — 2026-08-08

### Fixed

- **MITM:** de volta ao menu; layout igual a Eventos/Estado (tabs no `panel-body`,
  nao no heading escuro). Identity e Acesso Remoto alinhados ao mesmo padrao.

### Notes

- Canal `latest`: **`1.9.23`**. Produção enforce **`1.9.8`**.
- SHA256: `2e17628e092248da20fab70991e4d2199955585aa290f8edd40e86ff12384573`. Rollback lab: `1.9.22`.
- MITM continua DEFER (placeholder); permanece visivel para nao se perder.

## [1.9.22] — 2026-08-07

### Changed

- **MITM:** removido do menu (DEFER 20.7a) — deixava a barra secundaria fora do padrao
  em todas as telas; pagina permanece acessivel por URL.

### Fixed

- Tabs: remove overflow-x/flex que causava scrollbar no menu.

### Notes

- Canal `latest`: **`1.9.22`**. Produção enforce **`1.9.8`**.
- SHA256: `79fb1ba64136b88781b123bfab8942b46f466f4185813d484c9bb08aa87e4fe2`. Rollback lab: `1.9.21`.

## [1.9.21] — 2026-08-07

### Changed

- **Menu:** Acesso Remoto deixou de ser guia top-level — perfil em Politicas/Perfis
  rapidos + botao de detalhe (lista de softwares).
- **MITM:** movido para tabs secundarias (DEFER); pagina alinhada ao layout admin.

### Fixed

- Wrap de Definicoes / underline secundario no menu.

### Notes

- Canal `latest`: **`1.9.21`**. Produção enforce **`1.9.8`**.
- SHA256: `763d60e4f1ac447818ea5fdbd595187aca5d4857e67d22e62e011a5c1a07d68c`.
- Rollback lab: `1.9.20`.

## [1.9.20] — 2026-08-07

### Fixed

- **Tabs GUI contraste:** tema pfSense forca links brancos em panel-heading;
  tabs Layer7 passam a vermelho/cinza explicitos (legiveis no fundo claro).

### Notes

- Canal `latest`: **`1.9.20`**. Produção enforce **`1.9.8`**.
- SHA256: `a41e7287c257c6842adbda4f81b1e2b411dbc912cc2da8433bbcc62fc1d8f254`.
- Rollback lab: `1.9.19`.

## [1.9.19] — 2026-08-07

### Fixed

- **GUI MITM 50x:** variavel de entitlements colidia com head.inc
  (foreach de interfaces); paginas MITM/Identity usam l7_ent / l7_feat_raw.
- **Tabs GUI:** Diagnosticos / Remocao do pacote em linha secundaria propria
  (deixam de parecer aninhadas sob Relatorios/Definicoes).

### Notes

- Canal `latest`: **`1.9.19`**. Produção enforce **`1.9.8`**.
- SHA256: `19509802932a86d1d37ae65569bf75f55fc2c888fc68950ef9eab9d11ec97815`.
- Rollback lab: `1.9.18`. MITM permanece DEFER (só gate comercial / placeholder).

## [1.9.18] — 2026-08-07

### Added

- **Identity DC receiver (IM5 / 20.20 parcial):** `identity_dc` no daemon —
  HTTPS (OpenSSL), token + HMAC-SHA256, ACL DC, skew, rate limit → mapa
  (`L7_ID_SRC_DC_AGENT`); GUI Identity secção agente DC (gerar token, bind LAN);
  script lab `docs/samples/identity-dc-agent/Send-Layer7IdentityEvent.ps1`.
- Teste: `tests/functional/test_identity_dc.c`.

### Notes

- Canal `latest` após publish: **`1.9.18`**. Produção enforce permanece
  **`1.9.8`** até GO.
- SHA256: `cda98abcba72de8878dddc881412af0b64d833aa686637a361c57cb8cdfff834`.
- Rollback lab: `1.9.17`. Serviço Windows Event Log completo = passo seguinte.
- Defaults DC **OFF**; MITM permanece DEFER. RADIUS 20.19 mantém-se.

## [1.9.17] — 2026-08-07

### Added

- **Identity RADIUS accounting receiver (IM5 / 20.19):** `identity_radius` no
  daemon — thread UDP (ADR-0028), Accounting-Request (User-Name +
  Framed-IP/v6), shared secret 0600, ACL NAS (vazia = rejeitar), Start/Interim
  → mapa (`L7_ID_SRC_RADIUS`), Stop → remove user; Accounting-Response.
- GUI Identity: secção RADIUS (porto 1813, bind, ACL NAS, secret).
- Testes: `test_identity_radius.c`, `test_identity_radius_config.php`.

### Notes

- Canal `latest` após publish: **`1.9.17`**. Produção enforce permanece
  **`1.9.8`** até GO.
- SHA256: `72d1a1717ac88cb68015d7912c8828099ff944e976f85f5901dfdb0471c7c49f`.
- Rollback lab: `1.9.16`. GI5.3 PASS (unitário); lab NAS físico residual.
- Defaults RADIUS **OFF**; MITM permanece DEFER. Sem secret/ACL → worker
  não arranca.

## [1.9.16] — 2026-08-07

### Added

- **Identity Test LDAP (IM4 / 20.18):** botão «Testar ligacao LDAP» na GUI;
  `layer7d --ldap-test` (JSON stdout; stderr sem secrets — GI5.4);
  estado em `/var/db/layer7/identity-ldap-test.json`.
- Testes: extensão `test_identity_ldap.c` + `test_identity_ldap_test.php`.

### Notes

- Canal `latest`: **`1.9.16`**. Produção enforce permanece **`1.9.8`** até GO.
- SHA256: `dc061b5caa179731b9cf471d37868fb722a6d3701752b81f41668d079259ff3d`.
- Rollback lab: `1.9.15`. GI5 **parcial** (GI5.3 = RADIUS/DC, 20.19+).
- Defaults LDAP **OFF**; MITM permanece DEFER.

## [1.9.15] — 2026-08-07

### Added

- **Identity LDAP client (IM4 / 20.17):** `identity_ldap` no daemon — worker
  pthread (ADR-0028), cache TTL, fail-mode OK/DEGRADED/DOWN (ADR-0027),
  expansão de grupos aninhados e `memberOf` via OpenLDAP.
- `LIB_DEPENDS`: `openldap26-client`.
- Teste `tests/functional/test_identity_ldap.c`.

### Notes

- Canal `latest`: **`1.9.15`**. Produção enforce permanece **`1.9.8`** até GO.
- SHA256: `6fc33b5e33446cd61e0b07820afb2e3237ecd91f7fcbb9189aae38ed976ae465`.
- Rollback lab: `1.9.14`. «Testar LDAP» na GUI = **20.18** (`1.9.16`).
- Defaults LDAP **OFF**; MITM permanece DEFER.

## [1.9.14] — 2026-08-07

### Added

- **Identity (IM3–IM4 / 20.11a–20.16):** mapa user↔IP no daemon (entitlement
  `identity`, zero threads OFF); GUI LDAP/LDAPS em Services → Layer 7 →
  Identity (servidor, porto, TLS, bind DN, base DN, filtros, limites
  ADR-0027); palavra-passe de bind em ficheiro `0600` (não no JSON).
- Teste `tests/functional/test_identity_ldap_config.php`.

### Notes

- Canal `latest`: **`1.9.14`**. Produção enforce permanece **`1.9.8`** até GO.
- SHA256: `e76ffae92edf1a7b9ade3239d3b8132939dc7716f5dcbf6e5d055658022e1403`.
- Rollback lab: `1.9.13`. MITM permanece DEFER (20.7a). Cliente LDAP C /
  “Testar LDAP” = 20.17–20.18 (ainda não neste pacote).
- Defaults Identity/LDAP **OFF**; sem entitlement = upsell apenas.

## [1.9.13] — 2026-08-05

### Added

- **Guia Acesso Remoto:** página GUI com catálogo de softwares (ícone +
  bloqueado/permitido), sincroniza o perfil `remote-access` e a política
  `profile-remote-access`. Catálogo em `remote-access-catalog.json`
  (consumidor, protocolos, gaming, RMM, regionais).

### Notes

- Canal `latest`: **`1.9.13`**. Produção enforce permanece **`1.9.8`** até GO.
- SHA256: `69ac0f2799a80a4dcea447b71321fabd115a2bc34c1c693c9a9e79ef16996a3d`.
- Rollback lab: `1.9.12`. Smoke: catalog 32 itens; apply AnyDesk/TeamViewer/RDP.
- Menu: Services → Layer 7 → **Acesso Remoto**.

## [1.9.12] — 2026-08-05

### Changed

- **BG-100:** teto de blacklists `L7_BL_MAX_TOTAL` **5 000 000** (hard-cap);
  orçamento de memória configurável por **% da RAM do appliance**
  (`hw.physmem`, GUI 5–50%, default **25%**, clamp 128–1536 MB). O load para
  no primeiro limite (contagem ou bytes) com WARN claro e continua a correr.
  Campos em `blacklists/config.json`: `max_entries`, `mem_percent`. Aviso na
  GUI se a soma das categorias activas exceder o teto.

### Notes

- Canal `latest`: **`1.9.12`**. Produção enforce permanece **`1.9.8`** até GO.
- SHA256: `f9742b27d87d0bd8f9d4c450ac82bd555a299e650e9aebab176ca5ad8e4b6278`.
- Rollback lab: `1.9.11`. Smoke appliance: pkg `1.9.12`, daemon up, GUI recursos
  e budget clamp PASS (16 GB → orçamento 1536 MB).
- Em appliances ≤4 GB RAM, preferir poucas categorias; `adult` sozinha ~4.6 M.

## [1.9.11] — 2026-08-05

### Security

- **BG-103:** self-heal `rules.debug` sem TOCTOU — `open(O_NOFOLLOW)` +
  `fstat` (regular, uid 0, sem write group/other) e `pfctl -f -` via stdin
  (daemon, `layer7.inc`, diagnostics, `layer7-pfctl`).
- **BG-104:** DNS observe exige correlação
  `(client, txid, resolver, qname)` + allowlist de resolvers (auto-seed dos
  IPs das ifaces de captura + `dns_observe_resolvers[]` opcional; fail-open
  se lista vazia). Residual prático de BG-095 fechado; spoof-as-resolver na
  mesma L2 permanece fora do âmbito observe passivo.

### Notes

- Canal `latest`: **`1.9.11`**. Produção enforce permanece **`1.9.8`** até GO.
- SHA256: `dde1e17b00820d56bbee231087f92a4552da73dc00ea18820d17750ad35cd1be`.
- Rollback lab: `1.9.10`. Smoke appliance: pkg `1.9.11`, daemon up, DNS local OK,
  `layer7_rules_debug_trusted` PASS.

## [1.9.10] — 2026-08-05

### Fixed

- **BG-102:** `match inet6 to <layer7_allow_dst> tag L7ALLOW` em
  `layer7_pf_default_rules_text()` e `layer7-pfctl` (fecho allowlist dual-stack
  no plano PF; daemon já populava v6 desde `1.9.9`).
- **BG-094 residual:** progress/lock blacklists, cache GitHub releases e
  nDPI protos passam a `/var/db/layer7/` (fora de `/tmp`).

### Notes

- Canal `latest`: **`1.9.10`**. Produção enforce permanece **`1.9.8`** até GO.
- SHA256: `323e3a74dd41eaa31210de47ef94b13e9d5c1ca9c1b5b54b04d2353cf527f2f2`.
- Rollback lab: `1.9.9`. Smoke appliance: `match inet6` confirmado em `pfctl -sr`.

## [1.9.9] — 2026-08-05

### Security

- **BG-093:** self-heal/`pfctl -f /tmp/rules.debug` só se ficheiro regular,
  uid 0 e sem world-write (daemon, `layer7-pfctl`, `layer7.inc`, diagnostics).
- **BG-094:** stats JSON, activate/check-in tmp e update `.pkg` saem de `/tmp`
  para `/var/db/layer7/` (stats com `O_EXCL|O_NOFOLLOW`).
- **BG-095:** DNS observe exige QR=1 e correlação query↔resposta (txid+cliente,
  TTL 10s); parser rejeita QR=0.
- **BG-096:** allowlist PF aceita IPv6 host/CIDR via `layer7_pf_table_entry_ok`
  + `layer7_pf_exec_table_add_entry` (fecha lacuna pós-trilha IPv6).
- **BG-097:** curl activate/check-in com `--connect-timeout 10 --max-time 30`.
- **BG-098:** `waitpid` com retry em `EINTR` no caminho `pfctl`.
- **BG-099:** updater GUI restringe URL a `https://github.com/pablomichelin/Layer7/`.

### Fixed

- Buffers `customer`/`features` da licença inicializados (evita lixo em logs).

### Notes

- Canal `latest`: **`1.9.9`**. Produção enforce permanece **`1.9.8`** até GO.
- SHA256: `8ad13256a9b1c4b976e1ecb69bce90d779f212911649ae004daaf033f51219ae`.
- Rollback lab: `1.9.8`. Residual DNS: spoof com query ID observado.
- BG-100 (teto 8M blacklist) e BG-101 (janela offline ADR-0021) documentados
  sem mudança de código neste bloco.
- Build FreeBSD + smoke appliance (`192.168.100.254`) PASS `2026-08-05`.

## [Unreleased] — License server + daemon (S07 / F3)

### Added

- **docs:** `ESTADO-PRODUTO-E-PLANOS-FECHADOS.md` — congelamento das filas
  fecho P0–J + IPv6; mapa de navegação; porta para planos novos (§6).

### Changed

- START-HERE / CORTEX / README / classificação alinhados ao pós-fecho `1.9.8`.
- **F6 H5:** raiz `00-`…`16-` → `docs/archive/raiz-legado/`; planos
  fecho+IPv6 → `docs/archive/planos-fechados/`; stubs + banners
  `【ARQUIVO】` / `【FECHADO】`; índice `docs/02-roadmap/README.md`.

## [1.9.8] — 2026-08-05

### Added

- **trilha-ipv6/12.11 (V5 Opção A completa):** HTTP/HTTPS `rdr inet6`
  portal → `[::1]:8099` quando existe `portal_ipv6`/GUA; blockpage dual-listen
  `127.0.0.1:8099` + `[::1]:8099`; VIP Unbound ACL IPv6 (`/128` + CIDR6);
  `get_interface_ipv6` no helper portal6; banner Diagnostics; testes
  `test_dns_force_inet6` (HTTP inet6) + `test_vip_dns_exempt` (ACL v6).

### Notes

- Fecha residual V5 (BG-083); núcleo + V5 dual-stack **completos** no lab.
- **GV7.4 PASS (`2026-08-05`):** promovido a **produção enforce** (alinhado com
  `latest`). Rollback enforce: `1.9.0`. Evidência
  `20260805T150500Z-gv7.4-promocao-1.9.8`.
- SHA256: `229639243fc31333251fa286690bf87db9f20b644039b857ca283d16501a99ec`.
- Rollback lab intermédio: `1.9.7`.
- Porta webgui (ex. 443) continua excluída do rdr (ADR-0017).

## [1.9.7] — 2026-08-05

### Added

- **trilha-ipv6/12.10 (V5 Opção A):** DNS forçado dual-stack —
  `rdr inet6` :53 → `::1` (blacklist `force_dns` + `block_page.force_dns`);
  AF-split CIDR v4/v6; `layer7_blockpage_portal_ipv6` + Unbound `IN AAAA`;
  banner Diagnostics actualizado; `test_dns_force_inet6.php`.

### Notes

- HTTP/HTTPS portal `rdr inet6` e VIP Unbound ACL v6 ficam no passo **12.11**.
- Candidato lab; produção enforce permanece `1.9.0` (GV7.4 PENDENTE).
- SHA256: `4a00b40226fb0d92d974c3156d0c6881aa07fde2fe96e8d1821548157cd4fb50`.
- Rollback lab: `1.9.6`.
- ADR-0024 emenda GO Opção A `2026-08-05`.

## [1.9.6] — 2026-08-05

### Fixed

- **GV4 / A3 (endurecimento):** observação DNS A/AAAA independente da
  disponibilidade da hash de fluxos (não silenciar aprendizagem sob pressão).
- Parser A+AAAA (`dns_observe.h`) + hint cache IPv6; validado com RRs AAAA
  em tráfego real (`dns_resolved`).

### Notes

- Candidato lab; producao enforce permanece `1.9.0` ate GO.
- SHA256: `fc2d7fce624f8ac0afaf68ee9b2c0850b1e956767baeb16dfc11498517e3c6e4`.
- Rollback lab: `1.9.5`.
- Limitação: respostas DNS do unbound **local** podem ser intermitentes no
  pcap (plataforma); aprendizagem OK quando a resposta é visível (LAN/WAN
  forwarded ou captura outbound).

## [1.9.5] — 2026-08-05

### Fixed

- **GV4 / A3:** DNS hint passa a processar RRs **AAAA** (além de A) no
  caminho de captura (`dns_observe.h` + `capture.c`), incluindo transporte
  DNS sobre IPv6. O `dns_cb` alimenta `pdst`/enforce com destinos IPv6 sem
  `pfctl -T add` manual. Hint cache IPv6 para correlação fluxo↔hostname.

### Notes

- Candidato lab; producao enforce permanece `1.9.0` ate GO.
- SHA256: `9278d5d61b55aad1a4b158cf8fa49b39ed6b4d4c7ab7be36f663e2547386da6f`.
- Rollback lab: `1.9.4`.
- Teste local: `test_dns_aaaa_wire` + `tests/run-local.sh`.
- Revalidar GV4 two-client IPv6 **sem** `pfctl -T add` manual.

## [1.9.4] — 2026-08-05

### Fixed

- **GV1.6 / S-02:** deixar de depender do alias nativo `<localsubnets>`
  (ausente no pfSense Plus). O pacote emite e popula `<layer7_localnets>`
  com redes das interfaces (IPv4+IPv6) + `fe80::/10`, e todas as regras de
  quarentena / anti-DoT / anti-QUIC / `exc_allow` / `psrc` usam
  `to !<layer7_localnets>`.
- Corrigido uso de `$scope_global` indefinido no emissor `psrc` (passa a
  `!empty($m["scope_global"])`).

### Notes

- Candidato lab; producao enforce permanece `1.9.0` ate GO.
- SHA256: `43f754613da16ab377f2b4258b3d5a924ef20d9171cab9ed78ca1995d6cee816`.
- Rollback lab: `1.9.3`.
- Confirmar no appliance: `pfctl -t layer7_localnets -T show` apos install.

## [1.9.3] — 2026-08-05

### Fixed

- **QA D1:** politicas `block` com `src_hosts`/`src_cidrs` deixam de alimentar o
  sinkhole Unbound global — isolamento two-client via PF (`pdst`/`psrc`).
- **QA D2:** politicas catch-all (ex. «Monitor geral») ja nao sombreiam
  bloqueios especificos com priority menor; regras PF `pdst` para politicas
  so com hosts (sem `src_*`) passam a ser emitidas em `scoped_hybrid`.
- **QA D3:** licenca validada antes do primeiro `apply_config` — `enforce_cfg=1`
  no arranque sem depender de SIGHUP.
- **QA D4/D5:** `layer7-pfctl ensure` apos resync; flush da ancora orfa
  `layer7_g5_test` em `flush-all` / resync.
- **QA D6:** `blacklists/config.json.sample` + criacao de `config.json` na
  primeira instalacao (feature activavel via GUI/config).

### Notes

- Candidato lab; producao enforce permanece `1.9.0` ate GO.
- HTTPS na pagina de bloqueio (sem MITM) continua limitacao conhecida (D7).

## [1.9.2] — 2026-08-05

### Added

- **Release lab IPv6 (passos 12.6–12.9 + banner V5):** policy CIDR IPv6,
  PF tabelas/kill states v6 (S-03), allowlist host/CIDR v6, validação GUI
  dual-stack (`layer7.inc` + páginas), banner Diagnostics (núcleo v6 OK;
  DNS forçado / block page / VIP DNS ainda IPv4 — V5 Opção B temporária,
  ADR-0024). Canal `latest` = `1.9.2`.
  **Produção enforce permanece `1.9.0`** até GV7 + GO humano.
  SHA256: `a3bda092f35b63f7559f1cee95e6abfd50a4338f6591a6c2b7f478722c9e0d34`.
  Rollback imediato: `1.9.1`.

### Notes

- Próximo: gates appliance (GV1/GV3/GV4) em `1.9.2`; retomar **12.10** (V5
  Opção A) só com GO humano após gates.

### Included (já na árvore pré-release; agora no `.pkg`)

- **V5 Opção B temporária (`2026-08-05`):** adiar DNS/portal/VIP DNS v6 agora;
  retomar obrigatório após gates + GO. Não é abandono de V5.
- **12.9 (V4):** GUI + validação IPv6 — helpers dual-stack; `test_ipv6_gui_inc.php` PASS.
- **12.8 (V3):** allowlist IPv6 host/CIDR; Onda V3 completa.
- **12.7 (V3):** enforce/main PF tabelas + kill states v6.
- **12.6 (V3):** policy CIDR IPv6 dual-stack.

## [1.9.1] — 2026-08-05

### Added

- **Release lab IPv6 (passos 12.1–12.5):** PF scoped `inet`+`inet6` (REV-018),
  captura EtherType `0x86DD` + extension headers, flow key dual-stack, nDPI
  sobre IPv6, métricas AF `cap_pkts/active/classified` v4/v6 no JSON de stats,
  banner Diagnostics IPv4-only, ADR-0024. Canal `latest` = `1.9.1`.
  **Produção enforce permanece `1.9.0`** até GV7 + GO humano.
  SHA256: `c7c6b755cedfc2b8aacfc39b95129442499e2ced133c0ac5666fa962962844fd`.

### Notes

- Próximo passo de código: **12.7** (`enforce.c`/`main.c` PF tabelas + kill states v6).
  Passo **12.6** (policy CIDR v6) concluído na árvore sem bump nesta entrega.

## [Unreleased notes moved — historical trail entries]

### Added (trilha, pré-release)

- **Trilha IPv6 / passo 12.5 (V2):** métricas AF `cap_pkts_v4/v6`,
  `cap_active_v4/v6`, `cap_classified_v4/v6` em `capture.c` + JSON stats;
  nDPI sobre IPv6 já entregue em 12.4; 12.5 fecha contadores por família de
  endereço. GV2 builder PASS. Publicado em **`1.9.1`**. Produção
  `1.9.0` inalterada.
- **Trilha IPv6 / passo 12.4 (V2):** parser EtherType `0x86DD`, extension
  headers (S-06), flow key/hash IPv6, tabela de fluxos dual-stack, nDPI sobre
  pacotes v6; `test_capture_flow_key` + `layer7d` build/`-t` PASS no builder.
  DNS hint AAAA e métricas `cap_*` v6 ficam para **12.5**. Sem bump `1.9.1`
  (só na release). Produção `1.9.0` inalterada.
- **Versionamento pós-1.9.0:** série patch `1.9.0` → `1.9.1` → `1.9.2` → …;
  `PORTREVISION=0` sempre (sem sufixo `_N`). Próximo `.pkg` da trilha IPv6:
  **`1.9.1`**. Produção enforce permanece `1.9.0` até GV7.
- **Trilha IPv6 / passo 12.3 (V1):** paridade PF scoped `inet`+`inet6` (REV-018);
  validadores IPv6 (S-03); `test_scoped_pf_inc` PASS no builder. Código na
  árvore sob `1.9.0` até release `1.9.1`. GV1.3/`pfctl -nf` appliance pendente.
  Próximo: **12.4** (captura).
- **Trilha IPv6 / passo 12.2 (V0):** banner IPv4-only em Diagnostics + secção
  dual-stack em `pf-enforcement.md`; i18n EN; **GV0 PASS** / BG-078 concluído.
  Próximo: **12.3** (PF `inet6` scoped — início do controlo). Produção `1.9.0`
  inalterada.
- **Trilha IPv6 / passo 12.1 (V0):** ADR-0024 aceite; mapa M-xx + §8; índices;
  `matriz-limitacoes-dpi.md` + unificada (FP-010/REV-018) alinhadas — **GV0.4 PASS**.
  Sem código produto. Próximo: **12.2** (banner GUI). Produção `1.9.0` inalterada.
- **Trilha IPv6 (pós-fecho, rev. c):** plano Ondas V0–V6, passos 12.1–12.13;
  ADR-0024; mapa (+ salvaguardas §8); gates GV0–GV7; arranque **único**
  `START-HERE-fecho-producao.md` (sem segundo START-HERE);
  desambiguação vs `test-matrix` §12; backlog BG-078..084.
  Passo actual: **12.4** (V2). Produção `1.9.0` inalterada até GV7.
- **Onda E (passo 6.1):** evidência `20260804T234500Z-ondaE-ce-parity` — veredicto
  **LIMITAÇÃO CE** (VM CE indisponível); ADR-0022; script
  `tests/lab/run-ondaE-ce-parity-appliance.sh`; matriz compatibilidade actualizada.
- **Pós-Veeam:** removido `g5-test-bl` do appliance `254`; reteste paridade
  `20260805T004800Z-ondaE-ce-parity-retest` (G2.3 PASS em monitor).
- **Onda G (8.1):** mapa backlog ↔ testes em
  `docs/tests/evidence/20260805T005000Z-ondaG-f5-mapa/`; `test-matrix.md` actualizada.
- **Onda G (8.2):** checklist `docs/tests/f5-smoke-checklist.md` +
  `tests/lab/run-f5-smoke-checklist.sh`; evidência PASS
  `20260805T005650Z-ondaG-f5-smoke-82`.
- **Onda J (11.1):** auditoria R1–R12 PASS com excepções ADR-0022/0023;
  evidência `20260805T012500Z-ondaJ-r1-r12-audit`; plano mestre fechado.
- **Onda I (10.1–10.2):** `RELEASE-CHECKLIST.md` + ADR-0023 (BG-028 fase 0).
- **Onda H (9.0–9.4):** F6 mapa + lotes H1–H4; evidências `20260805T011500Z` / `011800Z`.
- **BG-077 — check-in online (Bloco 1):** `POST /api/license/check-in` no
  license-server (`check-in.js`, `check_ins_log`); deploy em `192.168.100.244`.
- **BG-077 — check-in online (Bloco 2):** `layer7d` com `--check-in`,
  scheduler periódico, persistência `/var/db/layer7-checkin.json`, flag
  `check_in_enabled` em `layer7.json`, stats JSON (`license_check_in_*`).

### Fixed

- **License server — activação de licença expirada (S07):** `isLicenseExpired()`
  em `license-server/backend/src/crud-validation.js` normaliza `expiry` quando o
  PostgreSQL devolve `Date` (comparação `Date < string` falhava silenciosamente
  e `POST /api/activate` aceitava licenças expiradas com `HTTP 200`). Deploy
  em `192.168.100.244` / `license.systemup.inf.br` (`2026-08-04`); reteste S07
  **PASS** (`20260804T234000Z-ondaC-s07-retest`).
- **Daemon — activate com resposta HTTP do servidor:** `layer7d --activate` deixa
  de usar `curl -f` cego; distingue falha de rede de rejeição HTTP (`409 Licenca
  expirada.`, etc.), não grava `.lic` em rejeição e remove o ficheiro se a
  verificação local falhar após download.

### Tests

- `license-server/backend/src/crud-validation.test.js` — `Date` PostgreSQL.
- `license-server/backend/src/license-state.test.js` — estado efectivo expirado.

## [1.9.0] - 2026-08-05 — fecho plano mestre (semver limpa)

### Added

- Release **`1.9.0`**: marca de fecho das Ondas A–J do plano mestre. **Sem alteração
  funcional** face a `1.8.11_69` (mesmo binário; `PORTVERSION` limpo).

### Changed

- `PORTVERSION` `1.8.11`/`PORTREVISION` `69` → **`1.9.0`** / `0`.
- Referência de produção enforce e canal `latest` actualizados para `1.9.0`.
- Rollback imediato documentado: `1.8.11_69`.

## [1.8.11_69] - 2026-08-04 — fix SIGHUP updater blacklists (F4.2)

### Fixed

- **`update-blacklists.sh` — `send_sighup` com pidfile sem newline:** `daemon(8)`
  grava `/var/run/layer7d.pid` sem `\n` final; `read` preenchia o PID mas
  devolvia rc≠0, gerando falso `WARN: cannot read pidfile`. Alinhado a
  `rc.d/layer7d` e `layer7-stats-collect.sh`. Onda D sec. **10b** PASS.

### Tests

- Evidência lab: `docs/tests/evidence/20260804T212200Z-ondaD-f4-10b-PASS/`

## [1.8.11_68] - 2026-08-04 — BG-077: check-in online e revogação remota (daemon)

### Added

- **`layer7d --check-in`:** consulta `POST /api/license/check-in`; revogação/
  expiração remota remove `.lic` e desliga enforce.
- **Scheduler:** quando `check_in_enabled=true` em `layer7.json`; estado em
  `/var/db/layer7-checkin.json`; chave guardada na activação.
- **Stats:** `license_check_in_enabled`, `license_check_in_ok`,
  `license_last_check_in`, `license_next_check_in`, `license_check_in_error`.

## [1.8.11_67] - 2026-08-04 — Fix: mensagens activate + defesa .lic inválido (S07 daemon)

### Fixed

- **`layer7d --activate`:** mensagem clara quando o servidor responde HTTP 4xx/5xx
  (ex.: `activation rejected by license server (HTTP 409): Licenca expirada.`);
  ficheiro temporário + promoção só em `2xx`; `unlink` se verificação local falhar.

## [1.8.11_66] - 2026-08-04 — Fix: precedência PF Layer7 vs pass LAN (G5 / pfnearly)

### Fixed

- **Blocks Layer7 ignorados em enforce:** regras do pacote eram injectadas via
  `discover_pkg_rules("filter")` no fim de `rules.debug`, depois do
  `pass in quick on $LAN inet from any to any` default do pfSense — contadores
  `layer7:pdst:*` ficavam a zero e clientes bloqueados continuavam com HTTP 200
  (G5 two-client FAIL, lab 2026-08-04).
- **Hook `pfearly`:** `layer7_generate_rules("pfearly")` passa a emitir match +
  block (core, anti-QUIC, blacklist, scoped); `layer7_generate_rules("filter")`
  em enforce devolve apenas schema de tabelas (`layer7_pf_schema_rules_text`),
  evitando duplicar regras no fim do ruleset.

### Tests

- `tests/functional/test_scoped_pf_inc.php` — asserções pfnearly vs filter.

### Docs

- `docs/05-daemon/pf-enforcement.md` — secção precedência pfnearly/filter.

## [1.8.11_65] - 2026-08-04 — GUI i18n EN/PT + ícones FA6 + Mensagens (BG-076)

### Changed

- **i18n EN completo para strings `l7_t()` da GUI e catálogo de perfis:**
  dicionário `lang/en.php` passa a cobrir chaves usadas nas páginas do pacote
  (Estado, Dispositivos, Políticas, Blacklists, Allowlist, Eventos, Relatórios,
  Definições, Diagnósticos, Remoção, Excepções, Grupos, Categorias, Teste) e
  nomes/descrições/grupos de `profiles.json` — elimina labels PT residuais com
  `language=en`.
- **Ícones Perfis rápidos em pfSense FA6:** marcas (Facebook, LinkedIn, Reddit,
  Pinterest, Snapchat, Telegram, WhatsApp, YouTube, etc.) passam a renderizar
  com prefixo `fab`; aliases FA4 (`fa-comments-o`, `fa-youtube-play`,
  `fa-video-camera`, …) mapeados na apresentação via
  `layer7_profile_icon_render_spec()` / `layer7_profile_icon_html()`. IDs de
  política e nomes FA4 guardados no JSON permanecem válidos.
- **Label PT «Mensageria» → «Mensagens»** (grupo e tile agregado); id interno
  `mensageria` preservado; EN «Messages»; overlays legados com grupo
  «Mensageria» continuam a agrupar correctamente.

### Tests

- Extensão `tests/functional/test_profile_icon_valid.php` (render FA6).
- Suite/build no builder FreeBSD 15 + artefacto `.pkg`
  (`SHA256=e7c8ca44f34e19da3a2958eacfd09fce5c77c77d5acd6d8633e9ca9d42cdd48e`).

### Docs

- CORTEX, MANUAL-INSTALL (`_65`; rollback → `_64`), backlog BG-076.

## [1.8.11_64] - 2026-08-04 — Materializar tabelas VIP/excepções no PF live (BG-075)

### Fixed

- **VIP/`L7ALLOW` inoperante no PF live:** `table <layer7_exc_allow_N> persist
  { … }` aparecia em `/tmp/rules.debug` e no match `exc-allow`, mas
  `pfctl -t layer7_exc_allow_N -T show` respondia **Table does not exist**.
  Sem a marca `L7ALLOW`, o VIP caía no `block drop` global `layer7_block_dst`
  (enforcement `legacy_global`) — sintoma típico: Facebook “sem CSS” com
  DNS sinkhole VIP OK.
- **Causa raiz:** no FreeBSD/pfSense, declarações `persist` no ruleset (e
  membros iniciais em reloads) **não garantem** materialização acessível via
  `pfctl -t`; `layer7_resync()` / `layer7-pfctl ensure` materializavam
  `layer7_block*`, `allow_dst`, `pdst`/`psrc`/`pallow`, mas **omitiam**
  `layer7_exc_allow_*` (e o mesmo padrão afectava `pexc`/`blsrc` estáticos).
  Após flush lifecycle, nada repunha os IPs VIP no PF live.
- **Correcção:** `layer7_pf_table_replace_entries()` +
  `layer7_static_origin_tables_apply_to_pf()` — ensure + flush + add dos
  membros estáticos de `exc_allow` / `pexc` / `blsrc`; chamado em
  `layer7_resync()` (enforce) e em `layer7_pf_config_resync()`; helper
  `layer7-pfctl ensure` passa a cobrir `layer7_exc_allow_0..15`.

### Tests

- `tests/unit/test_flush_coverage.sh` PASS (contrato ensure/apply/flush).
- Extensão `tests/functional/test_vip_exception.php` (meta + helper apply).
- Suite completa no builder FreeBSD 15 + artefacto `.pkg`
  (`SHA256=79d348c26b20080520121cb32e521a89cdc4639dcb7b2787e46a26f0dd48fa76`).

### Docs

- CORTEX, MANUAL-INSTALL (`_64`; rollback → `_63`), backlog BG-075,
  `docs/05-daemon/pf-enforcement.md`.

## [1.8.11_63] - 2026-07-31 — Redesign compacto grelha Perfis rápidos (BG-074)

### Changed

- **UX (apenas apresentação):** grelha **Perfis rápidos** compacta estilo
  UniFi/UDM — cartões horizontais (~64px), ícone 36px, meta
  apps/hosts/hits, descrição em tooltip no cartão inteiro.
- **Toggle CSS:** switches estilo iOS/UniFi (verde quando ligado) substituem
  botões Ligar/Desligar; ícones `fa-cog` / `fa-pencil` para Opções/Editar.
- **Grupos accordion:** cabeçalho colapsável com contador e badge «N ligados»;
  estado inicial aberto quando o grupo tem perfis activos; persistência
  `localStorage`.
- **Pesquisa:** barra «Pesquisar perfil…» + filtro «Só ligados»; auto-expande
  grupos com resultados; secção **Perfis ocultos** adaptada e colapsada por
  defeito.
- **Badges:** «personalizado»/«editado» como pontos coloridos com tooltip.
- **Presets:** fundo ligeiramente tintado no grupo.

### Unchanged

- Handlers POST intactos (`toggle_profile_on/off`, modais, ocultos, VIP,
  export/import). Zero alteração funcional ou no daemon.

### Tests

- `./tests/run-local.sh local` PASS (macOS, PHP SKIP); builder FreeBSD 15:
  `php -l layer7_policies.php`, suite completa PASS. `.pkg` extraído validado
  (`layer7_policies.php` idêntico ao repo; `+MANIFEST` = `1.8.11_63`).

### Docs

- CORTEX, MANUAL-INSTALL (_63; rollback sec. 12 → `_62`), backlog BG-074,
  guia-completo 7.3.1.

## [1.8.11_62] - 2026-07-31 — Fix `$data` indefinido no rdr CIDR (BG-073)

### Fixed

- **Correcção pontual (zero mudança funcional):**
  `layer7_generate_rdr_rules_snippet()` chamava
  `layer7_vip_dns_rdr_from_cidr($data, $cidr)` com `$data` inexistente na
  função (bug pré-existente da `_60`); passa agora `$l7config` já carregado.
  Elimina o warning PHP 8 «Undefined variable $data» a cada
  `filter_configure` com regras `force_dns` por CIDR e concretiza o ganho de
  performance da `_61` no caminho CIDR (sem releitura de `layer7.json` por
  linha rdr). O fallback `null` recarregava a mesma config; as regras rdr
  geradas são idênticas.

### Tests

- `./tests/run-local.sh` PASS (macOS, PHP SKIP); builder FreeBSD 15:
  `php -l layer7.inc`, `test_vip_dns_exempt.php`, `test_vip_exception.php` e
  suite completa PASS. `.pkg` extraído validado (`layer7.inc` idêntico ao
  repo; `+MANIFEST` = `1.8.11_62`).

### Docs

- CORTEX, MANUAL-INSTALL (_62; rollback sec. 12 → `_61`), backlog BG-073.

## [1.8.11_61] - 2026-07-31 — Performance VIP DNS fallback (BG-073)

### Changed

- **Performance (sem alteração funcional):** `layer7_vip_dns_rdr_from_any()` e
  `layer7_vip_dns_rdr_from_cidr()` passam `$data` a
  `layer7_vip_dns_rdr_fallback_enabled()`, evitando releituras redundantes de
  `layer7.json` em cada `filter_configure` (interface × CIDR).
- **Cosmético:** ramo redundante removido em `layer7_vip_dns_mode_get()`.

### Tests

- `./tests/run-local.sh` PASS; builder FreeBSD 15: `php -l layer7.inc`, suite
  completa incl. `test_vip_dns_exempt.php` e `test_vip_exception.php` PASS.

### Docs

- CORTEX, MANUAL-INSTALL (_61; rollback sec. 12 → `_60`), backlog BG-073.

## [1.8.11_60] - 2026-07-31 — Pós-auditoria Lista VIP (BG-073 fix)

### Fixed

- **P1:** `layer7_vip_dns_rdr_fallback_enabled()` passa a derivar do estado
  persistente (`layer7_vip_dns_should_apply` + ausência de `L7_VIP_DNS_MARKER_*`
  em Unbound `custom_options`), em vez de `$GLOBALS` só definido em
  `layer7_vip_dns_sync()`. Corrige regeneração silenciosa de rdr `:53`
  `from any` em `filter_configure` (save regra, boot, evento interface) que
  reintroduzia sinkhole para VIPs em modo fallback.
- **P3:** docblock `layer7_vip_validate_limits` alinhado com
  `LAYER7_VIP_MAX_HOSTS` / `LAYER7_VIP_MAX_CIDRS` (32+16).

### Changed

- `layer7_vip_dns_rdr_fallback_set()` mantido apenas como override em testes;
  removidas chamadas redundantes em `layer7_vip_dns_sync()`.

### Tests

- `test_vip_dns_exempt.php`: estado persistente (marker presente/ausente) +
  overrides de teste; builder FreeBSD 15 PASS.

### Docs

- CORTEX, MANUAL-INSTALL (_60; rollback sec. 12 → `_59`), backlog.

## [1.8.11_59] - 2026-07-31 — BG-073 isenção VIP caminho DNS (Bloco D)

### Added

- **ADR-0020 opção (a):** view Unbound `layer7-vip-exempt` (sem `view-first`) via
  `access-control-view` para IPs/CIDRs de `vip-isentos`; markers
  `L7_VIP_DNS_MARKER_*` idempotentes em `custom_options`.
- Regeneração em `layer7_vip_dns_sync()` / `layer7_blockpage_sync()` /
  `layer7_pf_config_resync()`; validação `unbound-checkconf` antes de gravar.
- **Fallback opção (b):** se a view falhar validação, rdr `:53` global e por
  blacklist passam a `from !<layer7_exc_allow_N>` (e `from {cidr} !<table>`);
  limitação sinkhole documentada na GUI.
- `layer7_remove_unbound_vip_dns()` em `pkg-deinstall`; testes
  `test_vip_dns_exempt.php`.

### Notes

- SSOT `vip-isentos` inalterado; sem chaves novas no objecto excepção.
- Sinkhole bypass **completo** com opção (a); parcial (só rdr) com fallback (b).
- Host overrides nativos para VIPs: trade-off documentado (gate Bloco E).

### Tests

- `test_vip_dns_exempt.php`: snippet Unbound, strip, fallback rdr, `pfctl -nf`
  quando disponível.
- `php -l` nos PHP tocados; suite local/builder.

### Docs

- CORTEX, backlog BG-073, checklist Bloco D, ADR-0020, MANUAL-INSTALL (_59),
  `gui-validation.md`.

## [1.8.11_58] - 2026-07-31 — BG-072 limites daemon Lista VIP

### Changed

- `L7_EXC_MAX_HOSTS` 8→32 e `L7_EXC_MAX_CIDRS` 8→16 em `policy.h` (unica
  alteracao C do Bloco C).
- Constantes PHP `LAYER7_VIP_MAX_HOSTS` / `LAYER7_VIP_MAX_CIDRS` alinhadas
  (32 / 16); validacao e upsert VIP coerentes com o daemon.

### Notes

- Memoria estatica maxima excepcoes: 16 × `struct layer7_exception` ≈ +19 KiB
  vs limites 8+8 (+1216 B por excepcao nos arrays hosts/cidrs).
- Parser ingenuo inalterado; isencao DNS permanece Bloco D (BG-073).

### Tests

- `test_config_parse.c`: 32 hosts em excepcao VIP parseados.
- `test_policy_decide.c`: host 10 em excepcao VIP obtem allow (nao truncado).
- `test_vip_exception.php`: limites 32/16.
- Builder FreeBSD 15: suite C, `php -l`, smoke — PASS.

### Docs

- CORTEX, backlog BG-072, checklist Bloco C, MANUAL-INSTALL (_58),
  `gui-validation.md`.

## [1.8.11_57] - 2026-07-31 — BG-071 Lista VIP global

### Added

- Secção **Lista VIP (isencão total)** em `layer7_exceptions.php`: tabela
  Descrição | IP/CIDR | acções, formulário **Adicionar isento**.
- Labels em `layer7.vip_meta.labels` (mapa IP/CIDR → descrição; daemon nunca lê).
- Export/import JSON da Lista VIP (padrão BG-070).
- Link **Gerir Lista VIP** no modal Perfis rápidos.
- Constantes PHP `LAYER7_VIP_MAX_HOSTS` / `LAYER7_VIP_MAX_CIDRS` (=8) com
  rejeição visível (sem truncamento silencioso).
- Avisos DHCP static mapping e sinkhole DNS (Bloco D / ADR-0020).

### Tests

- `tests/functional/test_vip_exception.php` estendido: labels, limites,
  export/import round-trip.
- Builder FreeBSD 15: `php -l`, teste funcional PASS.

### Docs

- CORTEX, backlog BG-071, checklist Bloco B, MANUAL-INSTALL (_57),
  `gui-validation.md`.

## [1.8.11_56] - 2026-07-31 — BG-070 integral + correcções pós-_55 defeituoso

### Fixed

- **Rebuild obrigatório:** `1.8.11_55` foi compilada no builder **antes** do
  commit completo de BG-070 — o `.pkg` publicado continha apenas o merge em
  `layer7.inc`/`Makefile`, **sem** GUI de edição (`l7showProfileEditModal`),
  export/import `profiles_custom`, nem scripts `+INSTALL`/`+DEINSTALL` para
  `profiles-custom.json`. **`1.8.11_55` não deve ser instalada**; usar `_56`.
- **`pkg-deinstall.in`:** condição `PKG_UPGRADE` corrigida — `pkg(8)` define
  `"true"`, não `"YES"`; upgrades passam a preservar `profiles-custom.json`.
- **Perfis ocultos:** secção discreta **Perfis ocultos** no fim da grelha com
  botões **Mostrar** e **Editar** (antes o cartão desaparecia sem forma de
  reverter).
- **`layer7_profile_icon_valid()`:** validação contra lista FontAwesome 4.7
  embebida (`layer7-fa47-icons.inc`); ícones fora da lista rejeitados (fallback
  `fa-cube` na gravação).

### Added

- Artefacto integral BG-070: GUI editar/criar perfis, `profiles_custom` em
  export/import, skeleton/preservação em install/deinstall.

### Tests

- `tests/functional/test_profile_icon_valid.php` + extensão de
  `test_profiles_json.sh`.
- Builder FreeBSD 15: suite local, validação do `.pkg` extraído (4 checks
  BG-070), simulação upgrade — PASS.

### Docs

- CORTEX, backlog BG-070, MANUAL-INSTALL (_56; _55 marcada defeituosa;
  rollback → `_54`, **não** `_55`), guia 7.3.2 secção Perfis ocultos.

## [1.8.11_55] - 2026-07-31 — Perfis editáveis e personalizados (BG-070) — **DEFEITUOSO, NÃO INSTALAR**

> **Atenção:** esta release foi publicada com artefacto incompleto (build no
> builder desalinhado do repositório). **Não instalar.** Substituída por
> `1.8.11_56`.

### Added

- **`/usr/local/etc/layer7/profiles-custom.json`** (overlay cliente; **fora** do
  `pkg-plist` / Makefile): `overrides` para perfis de fábrica
  (`hosts_add/remove`, `apps_add/remove`, `hidden`) e `custom_profiles` com ids
  prefixo `c-`.
- **`layer7_load_profiles()`** passa a fazer merge: fábrica → overrides →
  personalizados; grupo GUI **Personalizados** no fim da grelha.
- GUI **Politicas > Perfis rápidos**: botão **Criar perfil**, **Editar** por
  cartão, badges `personalizado` / `editado`, modal de edição (apps só do
  catálogo de fábrica; hosts texto livre validado), auto-reconnect da política
  ligada com aviso.
- Export/Import em **Definições** inclui `profiles_custom`.
- `pkg-install.in` cria skeleton vazio na 1.ª instalação; upgrade preserva
  `profiles-custom.json` (padrão UT1/blacklists).

### Changed

- `pkg-deinstall.in` / remoção GUI: opção **Manter configuração** preserva
  também `profiles-custom.json`.

### Tests

- `tests/functional/test_profiles_custom_merge.php` + extensão de
  `test_profiles_json.sh` (merge overlay).
- Builder FreeBSD 15: `php -l`, suite local, pacote sem `profiles-custom.json`
  no `.pkg`, simulação upgrade com ficheiro intacto e merge activo — PASS.

### Docs

- CORTEX, backlog BG-070, guia completo 7.3.2, MANUAL-INSTALL (rollback → `_54`).

## [1.8.11_54] - 2026-07-31 — Correcção visual da grelha Perfis rápidos (BG-069)

### Fixed

- **Cabeçalhos de grupo quebrados:** os títulos de grupo eram emitidos com
  `grid-column:1/-1` dentro de um container `display:flex` — propriedade de CSS
  grid ignorada em flex, o que deixava o cabeçalho "flutuando" inline no meio
  dos cartões. Cada grupo passa a ser uma secção própria (`.l7-profile-group`)
  com cabeçalho full-width (título + contador de perfis, linha separadora) e a
  sua própria grelha de cartões.
- **Ícones em falta (55 de 72 perfis):** a GUI ignorava o campo `icon` do
  `profiles.json` e usava um mapa SVG hardcoded com apenas 17 ids antigos; os
  restantes caíam no fallback de letra em quadrado cinzento. O cartão passa a
  renderizar o ícone FontAwesome 4.7 (incluído no pfSense) declarado em
  `profiles.json`, sanitizado (`^fa-[a-z0-9-]{1,40}$`), com cor de fundo por
  marca (`$l7_brand_colors`, ~55 marcas) ou por grupo (`$l7_group_colors`).
  O mapa SVG inline foi removido (~15 KB de HTML a menos por página).
- **Cartões desalinhados:** cartões passam a flex column com `min-height`
  uniforme, descrição truncada a 3 linhas (`-webkit-line-clamp` + `max-height`
  fallback, texto completo no `title`) e botões Ligar/Desligar/Opções ancorados
  ao fundo do cartão (`.l7-profile-cta { margin-top:auto }`).

### Changed

- `profiles.json`: `ai-tools` passa de `fa-robot` (inexistente no FA 4.7) para
  `fa-magic` — único dos 72 ícones fora da lista oficial FA 4.7.

### Tests

- `tests/fixtures/fa47-icon-names.txt`: lista oficial dos 782 nomes (com
  aliases) do FontAwesome 4.7; `test_profiles_json.sh` passa a falhar se algum
  `icon` do `profiles.json` não existir no FA 4.7.

### Docs

- CORTEX, backlog BG-069, guia completo 7.3.1, MANUAL-INSTALL (rollback → `_53`).

## [1.8.11_53] - 2026-07-31 — Expansão catálogo Perfis rápidos Bloco 2 (BG-068)

### Added

- **34 novos perfis** (38 → **72**): videoconferência (Zoom, Teams, Meet, Webex,
  TeamSpeak, agregado), redes alternativas (Threads, Bluesky, Kick, Rumble,
  Mastodon/Tumblr/VK/Weibo), streaming (Deezer, SoundCloud, DAZN, Paramount+,
  Hulu, Vimeo/Dailymotion, futebol pirata), jogos (Roblox, Free Fire, Cloud
  Gaming), produtividade (empregos, notícias, desporto, viagens, speedtest),
  segurança (anonymizers, publicidade, malware, mining) e **3 presets**
  (distrações, proteção infantil, higiene de rede).
- Grupos GUI novos: **Comunicação e reuniões** e **Presets**.

### Changed

- Agregados **Redes Sociais**, **Jogos**, **VPN/Proxy** e **Criptomoedas**
  reforçados (Threads/Bluesky, NetEaseGames/Garena, Psiphon/UltraSurf/Warp/Relay,
  categoria Mining).
- `$l7_group_order` em `layer7_policies.php` actualizado com os 2 grupos novos.

### Docs

- CORTEX, backlog BG-068, guia completo 7.3.1, MANUAL-INSTALL (rollback → `_52`).

## [1.8.11_52] - 2026-07-30 — Catálogo Perfis rápidos nível UniFi/UDM (BG-067)

### Added

- **38 perfis rápidos** (antes 18): novos perfis Telegram, Discord, Kwai, Mensageria,
  Marketplaces, Torrent/P2P, Apostas, Prime Video, Disney+, Max, Globoplay,
  Crunchyroll, Cloud Storage, Webmail pessoal, Reddit, Pinterest, Snapchat,
  Criptomoedas, Namoro e atalho Conteúdo adulto.
- Campo opcional `group` em `profiles.json` com cabeçalhos na GUI (Redes sociais,
  Mensageria, Streaming, Jogos, Produtividade, Segurança e bypass).
- Primeiro uso de `ndpi_categories` nos perfis (Gambling, Chat, Shopping, FileSharing,
  Game, Dating, AdultContent, etc.).
- Teste `tests/unit/test_profiles_json.sh` + fixtures nDPI validadas no builder FreeBSD 15.

### Changed

- Correcções de hosts desactualizados (ai-tools, netflix, tiktok, gaming).
- Nomes nDPI alinhados ao builder (`NetFlix`, `Github`, `Playstation`, `IPSec`, …).
- Agregados **Redes Sociais** e **Streaming** actualizados.
- Perfil **Jogos** reforçado (Steam, Xbox, PlayStation, Epic, Blizzard, Nintendo, Riot, Roblox).
- Modal Opções: slice de apps corrigido de 12 para **64** (igual ao toggle directo).

### Docs

- CORTEX, backlog BG-067, guia completo, MANUAL-INSTALL.

## [1.8.11_51] - 2026-07-30 — Fix: ordem PF da exclusao por politica (BG-066)

### Fixed

- **PF scoped (`scoped_hybrid`):** a regra `match from <layer7_pexc_N> to
  <layer7_pdst_N> tag L7ALLOW` era emitida **depois** dos `block drop quick`
  da mesma politica em `layer7_policy_enforcement_rules_text()`. Como `quick`
  e terminal, o pacote da origem excluida era dropado antes de receber a tag
  `L7ALLOW` e a exclusao do `_50` era inoperante sempre que o destino tinha
  entrado em `layer7_pdst_N` por trafego de outro cliente. O match passa a
  preceder os blocks da politica (mesma semantica do allowlist/pallow).

### Tests

- `test_scoped_pf_inc.php`: nova assercao de **ordem** — o match `pexc` tem
  de vir antes do primeiro `block drop quick` (o teste do `_50` so validava
  presenca, por isso nao apanhou a regressao).

## [1.8.11_50] - 2026-07-30 — Exclusao por politica `src_exclude_*` (BG-066)

### Added

- ADR-0019: campos `match.src_exclude_cidrs` e `match.src_exclude_groups`.
- Daemon: parse, expansao de grupos excluidos e nao-match em `src_matches_rule`.
- PF scoped: tabela `layer7_pexc_N` + regra `match from pexc to pdst tag L7ALLOW`.
- GUI: **Excluir origens (so este perfil)** no modal Avancado e formulario manual;
  validacao include/exclude conflituoso.
- Flush/self-heal/deinstall incluem `layer7_pexc_0..23`.
- Testes: `test_policy_decide.c`, `test_config_parse.c`, `test_scoped_pf_inc.php`,
  `test_flush_coverage.sh`.

### Docs

- `docs/core/policy-matrix.md`, `precedence.md`, `pf-enforcement.md`, CORTEX, backlog.

## [1.8.11_49] - 2026-07-30 — UX modal Perfis rapidos + verificador (BG-065)

### Added

- Progressive disclosure no modal: essencial (Accao, Aplicar a, Isentos) vs
  **Avancado** recolhido (CIDRs manuais).
- Atalho **Criar grupo (ex.: Gestores)** quando nao existem grupos.
- Link **Verificador de politica efectiva** para `layer7_test.php`.
- Veredicto destacado no teste: PERMITIDO / BLOQUEADO / MONITORIZADO com
  motivo legivel (ex.: `PERMITIDO — excepcao vip-isentos`).

### Changed

- Excepcoes ordenadas por prioridade na simulacao; grupos incluem
  `device_ips` no match de origem.

## [1.8.11_48] - 2026-07-30 — Isencao VIP nos Perfis rapidos (BG-064)

> **Nota:** `_48` nunca foi construido nem publicado como artefacto proprio —
> o codigo deste bloco foi consolidado e distribuido no pacote `1.8.11_49`
> (tag `v1.8.11_49`). Nao existe `.pkg` nem tag `v1.8.11_48`.

### Added

- Modal **Opções** dos Perfis rapidos: secção **Isentos (nunca bloqueados)**
  que cria/actualiza a excepcao canonica `vip-isentos` (allow global,
  prioridade alta). Suporta grupos (expandidos para IPs/CIDRs na gravacao),
  IPs e CIDRs manuais.
- Badge **Perfis rapidos** em `layer7_exceptions.php` para a excepcao gerida.
- Funcoes `layer7_upsert_vip_exception()` e helpers em `layer7.inc`.
- Teste funcional `tests/functional/test_vip_exception.php`.

### Changed

- `toggle_profile_off` documentado: desligar perfil **nao** remove a
  excepcao VIP partilhada.

### Docs

- Plano SSOT `docs/02-roadmap/plano-isencao-vip-e-ux-gui.md`, modelo
  conceptual GUI, backlog BG-064/065/066.

## [1.8.11_47] - 2026-07-30 — HTTPS ao portal com erro imediato (UX block page)

### Added

- Com a block page activa, HTTPS ao IP portal ficava "a carregar" ate ao
  timeout sem erro visivel: o SYN a <portal>:443 era aceite por regras
  `pass` anteriores (anti-lockout / allow LAN) e o
  `net.inet.tcp.blackhole=2` do pfSense dropava em silencio a porta
  fechada. Correccao: rdr tambem para TCP 443 -> servico local da pagina
  (rdr precede o filtro, mesmo caminho do :80 que ja funciona); o cliente
  TLS recebe resposta HTTP invalida e o browser mostra o erro de ligacao
  de imediato. Salvaguarda: a porta efectiva do webConfigurator
  (`layer7_webgui_port()`) nunca e redireccionada. Refactor:
  `layer7_blockpage_portal_and_ifaces()` partilhado.
- Nota: `_46` (candidato interno, nunca publicado) tentava resolver com
  `block return-rst`; nao funcionava porque as regras `pass` anteriores
  venciam pela ordem — supersedido por `_47`.

## [1.8.11_45] - 2026-07-30 — rdr da block page e DNS forcado agora efectivos

### Fixed

- As regras rdr (block page :80 e DNS forcado :53) eram carregadas no
  anchor `natrules/layer7_nat`, mas o ruleset principal do pfSense so
  declara `nat-anchor "natrules/*"` (sem `rdr-anchor`) — em PF, regras
  `rdr` num anchor sem ponto `rdr-anchor` **nunca sao avaliadas**. Na
  pratica o redirect HTTP para a pagina de bloqueio e o anti-bypass DNS
  estavam mortos: quem respondia no :80 era o nginx do webConfigurator
  (301). Correccao: as regras rdr passam a ser devolvidas por
  `layer7_generate_rules("nat")` (hook `filter_rule_function` /
  `discover_pkg_rules` — mesmo mecanismo do proxy transparente do Squid)
  e entram no ruleset principal em cada filter reload. O anchor legado e
  flushado; `layer7_inject_nat_to_anchor()` removido.

## [1.8.11_44] - 2026-07-30 — CRITICO: daemon nunca bloqueia IPs do firewall

### Fixed

- O sinkhole da block page resolve dominios bloqueados para o IP portal
  (interface do firewall); o daemon via a resposta DNS e adicionava o
  **proprio IP do pfSense** a `layer7_block_dst` — cortando GUI/SSH de
  todas as redes para esse IP (observado em lab: 192.168.100.254
  inacessivel a partir da VLAN 95). Novo guard `ip_is_local_iface_addr()`
  (getifaddrs, cache 60s) em todos os caminhos de insercao block
  (politica DNS/fluxo + blacklist DNS/SNI).

## [1.8.11_43] - 2026-07-30 — rc.d block page: dedup por porta e status robusto

### Fixed

- `layer7-blockpage` rc.d: segundo arranque com porta ocupada fazia o
  daemon(8) sair e apagar o pidfile da instancia activa (status errado e
  risco de duplicados). Start deduplica pela porta 8099; status tem
  fallback via sockstat.

## [1.8.11_42] - 2026-07-30 — Fix rdr block page (label) e arranque do servico

### Fixed

- pf rejeita `label` em regras rdr: o rdr :80 da block page nunca carregava
  no anchor (syntax error silencioso desde `_35`). Labels removidos.
- `layer7-blockpage` helper saia de imediato sob daemon(8): o pidfile do
  supervisor ja existia e o self-check interpretava-o como instancia activa.
  Check removido do helper (deduplicacao fica no rc.d).

## [1.8.11_41] - 2026-07-30 — Fix IP portal com interfaces por nome real

### Fixed

- `layer7_blockpage_portal_ip()`: quando `layer7.interfaces` guarda nomes
  reais (`vmx0`, `vmx0.95`), o portal nao era detectado (config.xml indexa
  por lan/optN). Novo mapeamento inverso pelo campo `if`. Sem portal, o
  sinkhole e o rdr da block page nunca eram gerados.

## [1.8.11_40] - 2026-07-30 — DNS forcado global (anti-bypass sinkhole)

### Added

- `block_page.force_dns` (opt-in, GUI Definições): rdr UDP/TCP :53 de todas as
  interfaces de captura para o Unbound local — clientes com DNS hardcoded
  (8.8.8.8/1.1.1.1) deixam de contornar o sinkhole. Activa anti-DoH
  automaticamente (NXDOMAIN resolvers DoH + canario Firefox). ADR-0018.

## [1.8.11_39] - 2026-07-30 — Fix bloqueio YouTube vs allowlist

### Fixed

- Removido `youtube.com` da allowlist-seed (conflitava com politicas block).
- `layer7d`: politica block prevalece sobre allowlist (DNS + fluxo nDPI).
- Ao aplicar block PF, revoga IP em `layer7_allow_dst` (CDN Google partilhado).

## [1.8.11_38] - 2026-07-30 — Updater AJAX em ficheiro externo (CSP pfSense Plus)

### Fixed

- «Verificar actualizacao» movido para `layer7_settings_update.js` (externo).
  pfSense Plus bloqueia scripts inline e `onclick`; POST continuava a
  funcionar. Config via `data-l7-update-cfg` no bloco `#l7_pkg_update`.

## [1.8.11_37] - 2026-07-30 — Fix updater: bind apos DOM pronto

Publicada em `pablomichelin/Layer7`.
Artefacto `pfSense-pkg-layer7-1.8.11_37.pkg`
(`SHA256=58e0b4a1ee58df70e9755e40cf6a3f6d26a623e354dcf521dff3c707f0df4a4a`).

### Fixed

- «Verificar actualizacao» nao respondia: script no fim da pagina corria
  depois de `DOMContentLoaded`; `l7BindCheckUpdateButton()` nunca era
  chamado. Agora executa imediatamente se `document.readyState !== "loading"`.

## [1.8.11_36] - 2026-07-30 — Fix updater GUI (Verificar actualizacao)

Publicada em `pablomichelin/Layer7` (candidato interno).
Artefacto `pfSense-pkg-layer7-1.8.11_36.pkg`
(`SHA256=227d8058b28ba030789b197b7cb118d444860c1890ce1311507ddfa398f1fce3`).

### Fixed

- Botao «Verificar actualizacao» deixou de depender de `onclick` inline (CSP
  pfSense Plus); usa `addEventListener` + re-bind apos render AJAX.
- Link «Modo compatibilidade» (POST) quando JavaScript falhar.
- `fetch` ao GitHub API com `--user-agent` explicito.

### Risco e rollback

- Alteracao PHP/JS da GUI; rollback: `_34` ou `_35`.

## [1.8.11_35] - 2026-07-30 — Pagina de bloqueio utilizador final (DNS sinkhole)

Publicada em `pablomichelin/Layer7` (candidato interno).
Artefacto `pfSense-pkg-layer7-1.8.11_35.pkg`
(`SHA256=86d0939d9fa81f4f3aa4fdf967fa06647e02e94b3afba73447c19cfb98c764a4`).
Release: `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_35`

### Added

- **Pagina de bloqueio** (ADR-0017 / BG-058): toggle opt-in nas Definições;
  mensagem/titulo/contacto customizaveis; IP portal auto ou manual.
- **DNS sinkhole Unbound** para dominios de politicas `block` activas (+ blacklists
  UT1 opcional, limite configuravel).
- Servico `layer7-blockpage` (PHP built-in em `127.0.0.1:8099`) + NAT `rdr`
  HTTP porta 80 no IP portal.
- Teste shell `tests/test_blockpage_config.sh`.

### Documentacao

- `docs/03-adr/ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md`
- `docs/05-daemon/pf-enforcement.md` — secao pagina de bloqueio
- `docs/04-package/validacao-lab.md` — secao **14**

### Risco, teste e rollback

- **HTTP:** pagina visivel quando dominio esta no sinkhole.
- **HTTPS:** erro TLS (sem MITM) — documentado no UI e ADR.
- Enforcement PF inalterado com toggle OFF.
- Rollback: desactivar pagina ou reinstalar `_34`.

## [1.8.11_34] - 2026-07-30 — GUI updater sem reload da pagina

Publicada em `pablomichelin/Layer7` (candidato interno).
Artefacto `pfSense-pkg-layer7-1.8.11_34.pkg`
(`SHA256=1401ce8f74a40b72c53fdf0414a92f523447ef3eb6d611c0036e16be136ca232`).
Release: `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_34`

### Changed

- Botao «Verificar actualizacao» passa a usar AJAX: resultado aparece no
  proprio bloco de actualizacao sem recarregar a pagina nem perder a posicao
  de scroll.
- Instalacao do pacote (`Actualizar para X`) mantem POST; apos concluir,
  auto-scroll para a secao de actualizacao.
- Logica de consulta ao GitHub consolidada em `layer7_check_for_update()`.

### Risco, teste e rollback

- Alteracao apenas PHP/JS da GUI; sem impacto em enforcement.
- Rollback: reinstalar `1.8.11_33` ou anterior.

## [1.8.11_33] - 2026-07-30 — GUI blacklists: progresso de download visível

Publicada em `pablomichelin/Layer7` (candidato interno).
Artefacto `pfSense-pkg-layer7-1.8.11_33.pkg`
(`SHA256=b55f0c310ff70012862a6f717a89542289a406c54eea6c004648ca88bb37032e`).
Release: `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_33`

### Fixed

- Log de download de blacklists movido para imediatamente abaixo do botão
  «Download snapshot assinada» (deixava de ser visível após a secção trust chain).
- Linha de progresso inicial escrita em PHP ao disparar o download; detecção
  de lock activo; invocação do script via `/bin/sh` para maior robustez no
  `mwexec_bg`.
- Polling AJAX do log continua após refresh enquanto download em curso; indicador
  visual (spinner / concluído / erro HTTP) e auto-scroll para a secção de log.

### Risco, teste e rollback

- Alteração apenas em PHP da GUI e helpers; sem impacto em enforcement PF.
- Rollback: reinstalar `1.8.11_32` ou anterior via GUI/pkg.

## [1.8.11_32] - 2026-07-30 — flush PF lifecycle e auditoria pré-gate

Publicada em `pablomichelin/Layer7` (candidato interno; Gate B1 pendente).
Artefacto `pfSense-pkg-layer7-1.8.11_32.pkg`
(`SHA256=c36ab91ef66504671e109009bdce9df3bb81c75d580b83313dee52f8c3b9640e`).
Release: `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_32`

### Fixed

- Flush de `layer7_exc_allow_*` em `layer7_flush_dynamic_tables()` e
  `layer7-pfctl flush-all` (B-002).
- `layer7_bl_apply()` passa a flushar tabelas dinâmicas antes de
  `filter_configure()` (B-003).
- `pkg-deinstall`: `flush-all` em PRE-DEINSTALL; fallback POST alinhado com
  helper (B-004).

### Added

- Testes R-21 (`test_flush_coverage.sh`) e contrato FP-015 em
  `test_config_parse.c`.
- Documentação auditoria multitask e matriz unificada REV/FP/AUD.

### Risco, teste e rollback

- Breve gap de bloqueio durante flush em mutação de blacklist.
- Suite local/builder C/shell e smoke `layer7d`: PASS
  (`SHA256=c36ab91ef66504671e109009bdce9df3bb81c75d580b83313dee52f8c3b9640e`).
- Appliance Gate B1: pendente.
- Rollback: `_24` passivo + `layer7-pfctl flush-all`.

---

## [1.8.11_31] - Unreleased — decisão somente após classificação nDPI final

Pacote candidato, não publicado e não aprovado para produção.

### Fixed

- O primeiro protocolo parcial deixa de congelar app/categoria/SNI do fluxo.
  A decisão aguarda `NDPI_STATE_CLASSIFIED`.
- Ao atingir o orçamento de 48 pacotes sem estado final, a captura chama
  `ndpi_detection_giveup()` antes de emitir o resultado.

### Risco, teste e rollback

- Impacto limitado ao momento de finalização da captura nDPI; política e PF
  não mudam.
- Suite local/builder, PHP/shell, build nDPI e pacote extraído: PASS
  (`SHA256=dc5118dd01193a83a6c6d15cc3ae4ca300647294a5b188e1991a363b4c453e33`).
- Appliance passivo: pendente.
- Rollback: `_30` passivo; `_24` continua rollback público conhecido.

---

## [1.8.11_30] - Unreleased — fluxo nDPI resiliente a colisões

Pacote candidato, não publicado e não aprovado para produção.

### Fixed

- O lookup percorre toda a janela antes de reutilizar um slot expirado,
  impedindo dois estados nDPI para a mesma conversa após colisão/expiração.
- Janela cheia deixa de descartar silenciosamente o fluxo e passa a evictar
  deterministicamente o menos recente.

### Added

- JSON de status recebe `cap_pkts`, `cap_active`, `cap_classified`,
  `cap_expired`, `cap_evicted`, `cap_dropped` e `captures`.
- Regressões cobrem buraco antes do match, primeiro livre, janela cheia e
  lookup read-only sob pressão.

### Risco, teste e rollback

- Impacto limitado ao subsistema de captura nDPI; sem mudança de política/PF.
- Suite local/builder, PHP/shell, build nDPI e pacote extraído: PASS
  (`SHA256=3a54c667a601e29995562714691f4ee3e9e8e78a02fcd3e600955ae90d2e9b40`).
- Appliance passivo: pendente.
- Rollback: `_29` passivo; `_24` continua rollback público conhecido.

---

## [1.8.11_29] - Unreleased — sintaxe anti-QUIC aceita pelo PF

Pacote candidato, **não publicado** e **não aprovado para produção**.
O pré-gate read-only no pfSense Plus 26.03.1 encontrou FP-018 antes da
instalação; nenhuma regra ou configuração do appliance foi alterada.

### Fixed

- Anti-QUIC por interface passa de `block ... inet on <if>` para
  `block ... on <if> inet` (e equivalente `inet6`), ordem aceite pelo parser
  PF do appliance.
- Geração anti-QUIC foi isolada em função pura e ganhou regressão PHP para
  rejeitar a ordem inválida e nomes de interface não sanitizados.

### Gates e rollback

- `_24` instalado está passivo, íntegro e com ruleset actual válido.
- Snippet autocontido com `L7ALLOW`, `pallow`, `blsrc`, anti-DoT e anti-QUIC
  corrigido: `pfctl -nf -` PASS no FreeBSD 16.
- Suite C/PHP/shell, build nDPI e validação do pacote extraído no FreeBSD 15:
  PASS (`SHA256=bea385ddb6f61bb6a9bffde0b781cea7a852b3956f620b8b004c914b0ab01840`).
- Ruleset completo instalado, toggle anti-QUIC e two-client: pendentes.
- `_28` está supersedido; rollback permanece `_24` passivo + flush + reload.

---

## [1.8.11_28] - Unreleased — allow PF sem bypass do pfSense

Pacote candidato **supersedido por `_29`**, não publicado e não aprovado para
produção. Não instalar: FP-018 invalida o ruleset se anti-QUIC por interface
estiver activo.
BG-056/FP-017 é corrigido em código sob a decisão ADR-0016; build do `.pkg`
passou e o gate no appliance continua pendente.

### Added

- Tabela dinâmica `layer7_pallow_N` por política `allow`, populada pelo
  daemon somente quando essa política vence DNS/SNI/nDPI e expirada por TTL.
- Tabela estática `layer7_exc_allow_N` por excepção `allow`.
- Marca interna PF `L7ALLOW` e escopo negativo por regra de blacklist
  `layer7_blsrc_N`.
- Cobertura C/PHP e smoke de appliance para precedência, modo monitor,
  ausência de `pass quick` e exception `block`.

### Fixed

- Allow explícito passa a vencer destino já presente numa tabela de block do
  Layer7, sem retirar o block do outro cliente.
- Allowlist, políticas e excepções deixam de usar `pass quick`: `match/tag`
  não autoriza tráfego e mantém as regras nativas do pfSense efectivas.
- `except_ips` de blacklist deixa de criar bypass geral e passa a ser
  subtraído da origem efectiva da regra UT1 em `layer7_blsrc_N`.
- Exception `block`, que casa pela origem, passa a usar `layer7_block` e kill
  de estados do host; antes podia tentar um destino inexistente.
- Flush/resync/self-heal incluem `layer7_pallow_0..23`; mutações de excepção
  limpam tabelas dinâmicas antes de regenerar o filtro.
- `smoke-layer7d.sh` volta a espelhar o daemon real incluindo `log_store.c`;
  sem isso o gate oficial falhava no link desde a introdução do logging L1.
- O diagnóstico `-e` aceita `-d DST` para validar o enforcement moderno por
  destino sem tocar no PF; o smoke usa um IPv4 de documentação.
- `bl_config.c` inclui `<stdint.h>` explicitamente; antes dependia de header
  transitivo no FreeBSD e quebrava o smoke Linux ao usar `uint32_t`.

### Gates e rollback

- Suite local, builder C/PHP/shell, smoke, build nDPI e `.pkg`: PASS
  (`SHA256=62dd9ae5923ade45b0bb484dca4e835b29b139f7a2aaa0a3624272ba07e59dc6`).
- `pfctl -nf`, instalação passiva e two-client: pendentes.
- Produção permanece intocada. Rollback: `_24` passivo +
  `layer7-pfctl flush-all` + reload do filtro.

---

## [1.8.11_27] - Unreleased — estabilização funcional pré-produção

Pacote candidato, **não publicado** e **não aprovado para produção**. Revisão
end-to-end documentada em
`docs/09-blocking/revisao-funcional-pre-producao-2026-07-29.md`.
Build isolado no FreeBSD 15:
`pfSense-pkg-layer7-1.8.11_27.pkg`,
`SHA256=8eae978d8d3120f050be21d2fdf511aacbf03ba0ad2c9c350c15100818ed5388`.

### Fixed

- **Classificação bidireccional:** canonicaliza os endpoints antes do hash;
  ida e volta da mesma conversa passam ao mesmo `ndpi_flow_struct`.
- **App sem quarentena:** aplicação/categoria normal usa
  `layer7_pdst_N` e bloqueia somente o destino observado.
  `layer7_psrc_N` fica reservado a `quarantine_origin=true`.
- **Bloqueio imediato:** após `pfctl -T add`, invalida o estado PF afectado;
  par cliente/destino em `pdst`, host inteiro só na quarentena e destino
  inteiro apenas no modelo legado global.
- **Precedência no callback:** política/excepção `allow` explícita impede nova
  inserção pela blacklist; default allow continua a avaliar blacklist.
  Precedência sobre entradas PF já existentes permanece gate/FP-017.
- **TTL SNI:** entradas de blacklist criadas pelo caminho SNI entram no cache
  de expiração.
- **Self-heal scoped:** a recuperação valida a tabela que falhou e o helper
  não declara sucesso com `pdst/psrc` ausente.
- **DNS CNAME:** preserva o QNAME original ao percorrer answer RRs.
- **Expiração de fluxos:** sweep também ocorre em tráfego já classificado.
- **Mutação de políticas:** add/edit/toggle/enable passa a limpar tabelas
  dinâmicas antes de regenerar regras, evitando destinos herdados por outro
  índice, origem ou acção.

### Added

- `capture_flow_key.h` e `test_capture_flow_key.c`.
- BG-055 e revisão funcional pré-produção de 2026-07-29.

### Gates e rollback

- Suite local completa no builder (C, PHP e shell), build nDPI, `pkg info`,
  conteúdo, versão e smoke `layer7d -t`: PASS.
- Gate appliance: pendente.
- Produção permanece intocada. Rollback operacional continua `_24` passivo +
  `layer7-pfctl flush-all`; preservar evidência.

---

## [1.8.11_26] - Unreleased — contenção L1 de logs

Pacote candidato, **não publicado** e **não aprovado para produção**. O bloco
BG-054 corrige crescimento ilimitado e ruído observados no appliance sem
alterar a lógica de decisão ou regras PF. Build isolado no FreeBSD 15:
`pfSense-pkg-layer7-1.8.11_26.pkg`,
`SHA256=c536cf879721d3bfad0097df9cf9f5ee45f217738c80ceaed9568acaf88b2f69`.

### Added

- `/var/log/layer7-events.log` separado do operacional
  `/var/log/layer7d.log`.
- Rotação interna limitada para ambos: 5 MiB e três cópias por default,
  configuráveis na GUI.
- Limite do SQLite de relatórios: 100 MiB por default.
- Painel de consumo dos três armazenamentos e fontes separadas na página
  Eventos.
- Testes `test_log_store.c` e `test_logging_reports.php`.

### Changed

- Detalhe de tráfego passa a opt-in (`event_log_enabled=false` quando ausente);
  bloqueios continuam auditados mesmo com o detalhe desligado.
- Idle, recheck de licença sem transição, SIGUSR1 e falhas esperadas ao limpar
  tabelas opcionais deixam de poluir `info`.
- Stats continuam actualizadas a cada minuto; resumo operacional no máximo
  uma vez por hora.
- Colector atravessa ficheiros rotacionados por inode antes de continuar no
  activo; retenção detalhada default passa a 7 dias.
- `enforce_block` confirma aplicação PF sem duplicar o KPI de bloqueio.
- “Limpar visualização” não afirma apagar disco; “Limpar histórico” informa
  que limpa SQLite/cursores e preserva os logs rotativos.

### Gates e rollback

- Suite local, lint, PHP/SQLite isolado e build `.pkg`: **PASS**.
- Instalação e observação no appliance: pendentes; nenhuma mudança foi
  aplicada ao pfSense de produção.
- Rollback: reinstalar `_24` em modo passivo e restaurar o JSON anterior; não
  apagar evidência de logs antes da recolha.

---

## [1.8.11_25] - Unreleased — candidato de estabilização antes do gate

Pacote candidato, **não publicado** e ainda **não aprovado para produção**.
Build isolado no FreeBSD 15 concluído com
`SHA256=c4e9c197f79ad00d7ddb68f8ececcd391455e86011e558596102877c325d388d`.
Nasce do diagnóstico read-only feito em `2026-07-29` no appliance
`192.168.100.254`, onde `_24` estava instalado, mas intencionalmente
`enabled=false`, `mode=monitor`.

### Fixed

- **rc.d / PID sem newline:** `daemon(8)` grava `/var/run/layer7d.pid` sem
  newline; `read` preenchia o PID mas retornava erro. `status` dizia
  indevidamente “not running” e `reload` podia iniciar outra instância.
- **Interface real de captura:** IDs amigáveis (`lan`, `optN`) passam por
  `get_real_interface()` antes de chegar a libpcap/PF. Upgrade migra também
  interfaces de políticas, excepções, anti-QUIC e relatórios.
- **Scoped `psrc`:** origem estática, `scope_global` ou
  `quarantine_origin` autorizam a inclusão dinâmica da origem em
  `layer7_psrc_N`; quarentena explícita agora emite regra PF executável.
- **App+host híbrido:** o tipo de enforcement segue o critério que realmente
  casou: app/categoria usa `psrc`; host/SNI/DNS usa `pdst`.
- **Validação GUI:** em `scoped_hybrid`, políticas block sem origem,
  `scope_global` ou quarentena são recusadas. O toggle de perfil em um clique
  não cria escopo global implícito.

### Added

- Regressões `test_rc_pidfile.sh`, `test_interface_normalization.php` e casos
  adicionais em `test_policy_decide.c` / `test_scoped_pf_inc.php`.

### Gates e rollback

- Gates locais/PHP e build do pacote no builder FreeBSD: PASS.
- Instalação e gate appliance two-client: **PENDENTES**.
- O appliance de destino é pfSense Plus `26.03.1` / FreeBSD `16.0-CURRENT`,
  enquanto o builder gera ABI FreeBSD 15; compatibilidade real faz parte do
  gate e continua sem declaração de suporte geral.
- Rollback do candidato: reinstalar a release pública `_24`, manter
  `enabled=false`/`mode=monitor` e confirmar tabelas dinâmicas vazias.

---

## [1.8.11_24] - 2026-06-16 — Caminho B E0–E3 + estabilização pós-revisão

Release publica. Artefacto `pfSense-pkg-layer7-1.8.11_24.pkg`
(`SHA256=1d5573f0a0c7803a87d8cb536ad9eee43e85daa9bf98bf7edc84ef554e2c7818`),
build no builder FreeBSD (`192.168.100.12`). Consolida o **Caminho B E0–E3**
(enforcement escopado por política) e correcções da revisão pré-instalação de
2026-06-15. Testes locais (`tests/run-local.sh`) PASS; **gate two-client no
appliance** (`validacao-lab.md` sec. 12) continua **PENDENTE**.

### Caminho B / E0–E3 — enforcement escopado (`PORTREVISION=24`)

#### Added

- **E0 (BG-045):** `layer7.enforcement_model` — `legacy_global` (default) |
  `scoped_hybrid` (experimental); parse em `config_parse`; selector em Settings.
- **E1 (BG-046):** `layer7_decide_for_client()` unifica decisão DNS/fluxo;
  `struct layer7_decision` com `enforce_kind`, `policy_table_idx`,
  `scope_global`, `quarantine_origin`; testes em `test_policy_decide.c`.
- **E2 (BG-047):** regras PF escopadas em `layer7.inc` (`layer7_pdst_N` /
  `layer7_psrc_N`); `scope_global` JSON+GUI; `test_scoped_pf_inc.php`.
- **E3 (BG-048):** runtime daemon popula `pdst_N`/`psrc_N` (não
  `layer7_block_dst` em scoped); cache TTL por `(table, ip)`;
  `test_enforce_scoped.c`.

#### Fixed (pós-revisão pré-instalação)

- **REV-002:** licença inválida no recheck horário chama
  `enforce_ge_downgrade()` → `enforcement_flush_all_tables()` (sem bloqueio PF
  residual).
- **REV-003:** allowlist rejeita CIDR `/0` (`prefix < 1`) em parse e em
  `l7_allowlist_contains_ip()`.
- **REV-015 (parcial):** mudança de `enforcement_model` incluída em
  `pf_relevant_changed` → `filter_configure()` + flush dinâmico.
- **REV-016 (parcial):** `layer7_pf_config_resync()` após saves de
  políticas, grupos, excepções e dispositivos → `filter_configure()` + SIGHUP.
- **Allowlist PF:** `layer7_dst_allowlist_apply_to_pf()` repovoa
  `layer7_allow_dst` no resync quando enforce activo (flush + adds estáticos).
- **DNS disabled:** `layer7_on_dns_resolved()` respeita `cfg_disabled()`
  (`enabled=false`), alinhado a fluxos nDPI.
- **`quarantine_origin`:** parse JSON/GUI + decisão app-only com quarentena de
  origem (`psrc_N`) em scoped.
- **`scope_global`:** parse no daemon; políticas block vazias exigem
  `scope_global` ou `quarantine_origin` (rejeição/warning coerente PHP+daemon).
- **`except_ips` (blacklists):** `l7_bl_rule_matches_src()` exclui IPs em
  `except_ips`; teste `test_bl_src_match.c`.
- **TTL blacklist:** adds DNS/SNI a `layer7_bld_N` passam por
  `enforce_cache_add()` / sweep (TTL clamped ≥60s).

#### Notes

- `legacy_global` permanece **default** — imposição global por destino
  (`layer7_block_dst`) é comportamento intencional até gate E8 (**REV-001 by
  design**).
- `scoped_hybrid` é **experimental**; não activar em produção sem gate
  two-client (sec. 12) e validação lab.
- E4–E8 (BG-049..BG-052) permanecem pendentes.
- Rollback = `1.8.11_23` (`v1.8.11_23`).

---

## [1.8.11_23] - 2026-05-30 — Caminho A completo (A0–A5)

Release publica que consolida todo o **Caminho A** (UX e eficacia tipo UDM Pro)
sobre a base estavel da Fase 1 (`1.8.11_18`): perfil GitHub e alinhamento de
limites (A0), inventario de dispositivos (A1), politicas por dispositivo
MAC->IP (A2), bloqueio por SNI/Host via nDPI opt-in (A3), UX de perfis com
toggle on/off e contadores (A4) e suite de regressao do Caminho A (A5).
Artefacto `pfSense-pkg-layer7-1.8.11_23.pkg`
(`SHA256=3c9e488d48c441a9859a1d953b603e9cecb242fc9d2e93ce144e05cdacb8d7d4`).
Validado no appliance: `smoke-monitor-mode.sh` e `smoke-caminho-a.sh` exit 0;
toggle de perfil cria/remove politica que o daemon carrega; SNI a alimentar
`flow_decide`. Sem MITM; monitor continua passivo (gate da Fase 1 intacto);
limitacao honesta: TLS 1.3 ECH cifra o SNI.

### Caminho A / A4 + A5 — UX tipo UDM e F5 alargada (`PORTREVISION=23`)

UX de perfis com toggle directo e contadores (BG-043) + suite de regressao do
Caminho A (BG-044).

#### Added (A4 — UX)

- **Toggle on/off directo por perfil** nos "Perfis rapidos" (Politicas): um
  clique liga (cria politica `profile-<id>`, accao block — em monitor fica
  apenas observado) ou desliga (remove a politica). O modal "Opcoes" mantem-se
  para escolha de accao/interfaces/sub-redes/grupos.
- **Estado visual por perfil** (ponto verde Ligado / cinza Desligado, moldura
  verde quando activo) e **contador de hits por perfil** a partir das stats do
  daemon (`top_apps_blocked`), via novo `layer7_profile_hit_counts()`.
- **Top clientes bloqueados** (Estado) agora mostra o **nome/alias do
  dispositivo** (inventario A1) ao lado do IP de origem.
- Traducoes EN das novas strings.

#### Added (A5 — testes/F5 alargada)

- `tests/functional/test_config_parse.c`: teste unitario do parser do daemon,
  cobrindo `sni_inspection` antes/depois de `policies` (regressao do bug do A3),
  `false`, ausente, e `enabled`/`mode`. Ligado ao `tests/run-local.sh`.
- `tests/lab/smoke-caminho-a.sh`: suite de regressao do Caminho A no appliance
  (A0 perfis+github, A1 inventario, A2 helpers MAC->IP, A3 parse sni, A4
  contadores), read-only de enforcement.

#### Notes

- `PORTREVISION` -> `23`. A4 reutiliza a estrutura de politicas existente (o
  toggle apenas cria/remove `profile-<id>`); rollback = desligar o perfil ou
  remover a politica. Sem alteracao do daemon em A4 (so PHP/GUI). A5 nao altera
  o produto (apenas testes).

### Caminho A / A3 — bloqueio por SNI/Host via nDPI (em curso, `PORTREVISION=22`)

Eficacia tipo UDM contra CDNs e DNS cifrado/cache (BG-042). Decisao em
`docs/03-adr/ADR-0013-bloqueio-por-sni-via-ndpi.md`.

#### Added

- Toggle **`sni_inspection`** (opt-in, OFF por defeito) em Definicoes. Quando
  ligado, o daemon usa o **SNI (TLS)** / **Host (HTTP)** que o nDPI ja extrai
  (`flow->host_server_name`) como host para matching de politicas, preferido
  sobre o DNS reverso, e alimenta a cache de hints por IP de destino.
- `capture.c/.h`: `layer7_capture_set_sni()` + validacao `sni_host_plausible()`.
- `config_parse.c/.h`: parsing de `sni_inspection`. `main.c` aplica o flag a
  cada captura (e no reload SIGHUP, que reabre capturas).
- `layer7.inc` (bare_config) + `layer7_settings.php`: toggle e persistencia.

#### Notes

- `PORTREVISION` -> `22`. **Sem parser TLS proprio** (reutiliza o do nDPI) e
  **sem MITM/decifragem**. Continua passivo e por destino
  (`layer7_block_dst`). Limitacao honesta: TLS 1.3 **ECH** cifra o SNI. Default
  inalterado (opt-in) para previsibilidade.

#### Fixed

- Parsing de `sni_inspection` no daemon nao podia depender do gate
  `< "policies"` (a GUI grava a chave depois de `policies` no JSON). Removido o
  gate; bug apanhado em validacao no appliance.

#### Validacao no appliance (`1.8.11_22`, `SHA256=4f0d42b5f8f9b3ddcda297477149b58b4d18e0d29673b0671c27bec6d6b1302c`)

- `capture: opened vmx0 (nDPI active, sni_inspection=1)` (flag aplicado);
- debug de `flow_decide` mostrou host extraido em uso, ex.:
  `host=pfs-monitor.systemup.inf.br ... reason=policy_match` (SNI/Host via nDPI
  a alimentar o motor de politicas);
- `smoke-monitor-mode.sh` exit 0 (gate de monitor intacto); default `sni=off`.

### Caminho A / A2 — politicas por dispositivo (em curso, `PORTREVISION=21`)

Regras por dispositivo estilo UDM "client rules" (BG-041). Decisao em
`docs/03-adr/ADR-0012-politicas-por-dispositivo-mac-para-ip.md`.

#### Added

- Grupos aceitam **dispositivos por MAC** (`device_macs`). O pacote resolve
  MAC -> IP actual (DHCP leases online + ARP) e grava `device_ips`; o daemon
  le `device_ips` como hosts de origem do grupo (`policy.c parse_group`),
  retrocompativel. Imposicao continua por IP em PF.
- GUI Grupos: campo "Dispositivos (MAC)" em adicionar/editar, coluna com
  contagem dispositivos -> IPs, e botao **"Resync IPs dos dispositivos"**
  (`layer7_devices_resync()`).
- GUI Dispositivos: checkboxes + **"Atribuir selecionados a grupo"** (fluxo
  natural de associar clientes a grupos).
- `layer7.inc`: `layer7_resolve_macs_to_ips()`, `layer7_normalize_macs()`,
  `layer7_devices_resync()`.

#### Changed

- `L7_MAX_GROUP_HOSTS` e `L7_MAX_SRC_HOSTS` `16 -> 64` (acomodar uma turma de
  dispositivos por grupo/origem). Para escala maior usar grupo por CIDR.

#### Notes

- `PORTREVISION` -> `21`. Fail-safe: grupo so com dispositivos offline nao gera
  hosts (nao bloqueia o que nao localiza). Drift de IP dinamico mitigado por
  resync + recomendacao de DHCP static mapping.
- Validacao no appliance (`1.8.11_21`,
  `SHA256=5e0789dab274a756ea6da0c1fbc493a343789ffad4d3cc481cc5d1d18611ba21`):
  MAC `7c:aa:de:4a:5e:8d` -> IP `10.0.85.89` resolvido e gravado em
  `device_ips`; daemon carregou `policies=1` sem erro de parse (grupo com
  `device_ips` aceite); regras PF de enforce presentes; `smoke-monitor-mode.sh`
  exit 0 (gate de monitor intacto). Nota: o enforce ao vivo e **license-gated**
  e este appliance de teste nao tem `layer7.lic` (`valid=0`), logo corre
  monitor-only; o pipeline de configuracao/parse/PF foi validado.

### Caminho A / A1 — inventario de dispositivos (em curso, `PORTREVISION=20`)

Base de identidade tipo UDM (BG-040). Decisao em
`docs/03-adr/ADR-0011-fonte-de-identidade-de-dispositivo.md`.

#### Added

- Nova pagina GUI **Services > Layer 7 > Dispositivos** (`layer7_devices.php`),
  **read-only** (so o alias e editavel): lista IP, MAC, hostname, fabricante
  (OUI), interface, estado online e fonte.
- `layer7.inc`: `layer7_device_inventory()` combina `system_get_dhcpleases()`
  (ISC+Kea) com a tabela ARP (`arp -an`), enriquece com vendor OUI (best-effort,
  unica passagem) e alias por MAC.
- Alias persistente do operador em `layer7.json` (`device_aliases`, MAC->alias);
  **ignorado pelo daemon** (estritamente observacional). Item no nav "Dispositivos".

#### Notes

- A1 **nao altera enforcement** (so observa). Base para A2 (politicas por
  dispositivo). `PORTREVISION` -> `20`. Limites honestos: so dispositivos
  adjacentes L2; MAC pode ser aleatorizado; vendor depende de base OUI no sistema.
- Build + validacao no appliance (`1.8.11_20`,
  `SHA256=ae02b1abb7d48a6bac8a792fb770a20c9dc28ca3a9f0d1c2bbd022f1b545621b`):
  `layer7_device_inventory()` devolveu 470 dispositivos (469 com MAC, 230 com
  fabricante OUI, 29 hostname); alias save/load/remove OK; `smoke-monitor-mode.sh`
  exit 0 (sem regressao).

### Caminho A / A0 — higiene (em curso, `PORTREVISION=19`)

Primeiro bloco do Caminho A (UX/eficacia tipo UDM Pro). Quick wins de baixo
risco; plano em `docs/09-blocking/caminho-a-plano-de-implementacao.md`
(BG-039).

#### Added

- Perfil **GitHub** em `profiles.json` (estava prometido no plano mestre mas
  ausente): `ndpi_apps=[GitHub]` + hosts (github.com, api/codeload/raw,
  githubusercontent/assets, github.io, ghcr.io, copilot). Total de perfis: 18.

#### Fixed

- **Limite de hosts por politica alinhado em 64** (eliminado truncamento
  silencioso): daemon `L7_MAX_HOSTS_PER_POLICY` `32 -> 64` (`policy.h`);
  formulario manual da GUI passava o default `16` ao `layer7_parse_host_textarea`
  — agora passa `64` nos quatro pontos de match de hosts (`layer7_policies.php`);
  aplicacao de perfil ja usava `64`. Texto de ajuda da GUI indica o limite.
- **Docs:** `docs/05-daemon/pf-enforcement.md` clarifica no topo que
  `action=block` em runtime bloqueia o **destino** (`layer7_block_dst`), nao a
  origem; `tag` e o caminho `-e` e que usam origem.

#### Notes

- `PORTREVISION` -> `19`. Build no builder FreeBSD concluido
  (`SHA256=a89f280714b984ad1dec8823185c18d7d1b73c37e45aafc76d33171e160945bb`),
  instalado e validado no appliance (`layer7d -V=1.8.11_19`, 18 perfis com
  `github`, `smoke-monitor-mode.sh` exit 0). Release publica do Caminho A sera
  agrupada num milestone (alvo: apos A2, primeiro incremento com enforcement
  por dispositivo), para evitar churn de releases por bloco. Builds `_19`/`_20`
  ja validados no appliance e em `main`.

## [1.8.11_18] - 2026-05-30

### Released

- **`pfSense-pkg-layer7-1.8.11_18.pkg`** em
  `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_18`
  (`SHA256=98374806be31094a3835bcae0c96164369860aef82db3bfb4255f44c9d60b876`).
  Build no builder FreeBSD `192.168.100.12`; **validado no appliance pfSense
  Plus 26.03** (`192.168.100.254`) com `tests/lab/smoke-monitor-mode.sh`
  (exit 0), incluindo enforce, transicao enforce->monitor e CLI
  `--license-status`.

Fase 1 da estabilizacao da V1 comercial — corrige os erros que provocavam
bloqueio indevido em modo monitor (incluindo bancos/servicos) e adiciona a
primeira camada de allowlist de destinos.

#### Fixed (Bloco 1) — monitor e monitor de verdade

- `layer7.inc`: `layer7_pf_default_rules_text()` agora emite **apenas as
  tabelas** `persist` quando `enabled=false` ou `mode!=enforce`; nenhum
  `block drop` do core e injectado em modo passivo. Causa-raiz do
  "bloqueia bancos em monitor".
- `layer7_generate_rules()` suprime anti-QUIC, blacklists e injeccao no
  anchor NAT em modo passivo, e limpa `natrules/layer7_nat` para nao deixar
  forcing de DNS residual.
- `layer7_settings.php`: `filter_configure()` passa a ser disparado tambem
  quando mudam `mode`, `enabled` ou `block_dot_doq` (nao so QUIC).
- Novo helper `layer7_pf_should_enforce($data)` — gate auditavel unico.
- **Correccao apanhada na validacao do appliance:** `layer7_generate_rules()`
  retornava `layer7_pf_rules_text()`, que prefere o `pf.conf` em disco
  (escrito por `layer7-pfctl write_rules()` com os blocks sempre presentes),
  contornando o gate. Agora, em modo passivo retorna so `layer7_pf_tables_text()`
  e, em enforce, constroi a partir de `layer7_pf_default_rules_text()`
  (mode-aware) em vez do `pf.conf` em disco. Smoke no appliance confirmou
  0 `block drop` em monitor.

#### Changed (Bloco 2) — anti-bypass como toggle, OFF por defeito

- Anti-DoT/DoQ (porta 853) passa a ser **toggle explicito** `block_dot_doq`
  em **Settings > Servico**, desligado por defeito. Antes era injectado
  incondicionalmente, podendo quebrar "DNS privado" em Android / apps
  moveis. Anti-QUIC ja era opcional por interface; defeito mantido OFF.
- Sample `pf.conf.sample` actualizado para o novo layout.

#### Added (Bloco 3) — allowlist de destinos

- Novo campo `layer7.dst_allowlist[]` em `layer7.json` (dominios, IPv4 host
  ou CIDRs IPv4) + lista-semente embutida em
  `/usr/local/etc/layer7/allowlist-seed.txt` (bancos BR, gov, pagamentos,
  push Apple/Google, Microsoft 365). Editor em
  **Services > Layer 7 > Allowlist** (`layer7_allowlist.php`).
- Novo modulo daemon `allowlist.{c,h}` — antes de adicionar IP a
  `layer7_block_dst` ou `layer7_bld_N`, o `layer7d` verifica se o
  dominio/IP esta na allowlist. Em DNS hint, popula `layer7_allow_dst`
  com o IP resolvido.
- Nova tabela PF `layer7_allow_dst` + regra `pass quick inet to
  <layer7_allow_dst>` emitida **antes** de qualquer `block drop`.
- 24 testes unitarios em `tests/functional/test_allowlist.c` (todos PASS).

#### Fixed (Bloco 5) — flush fiavel de tabelas dinamicas

- `dst_cache_flush()` reforcado com `pfctl -T flush` defensivo no fim.
- Nova `enforcement_flush_all_tables()` no daemon: limpa
  `layer7_block_dst`, `layer7_block` e `layer7_bld_*` na transicao
  enforce -> passivo (via SIGHUP) e no shutdown limpo (SIGTERM).
- `rc.d/layer7d stop` chama `layer7-pfctl flush-all` como defesa em
  profundidade caso o daemon seja morto com SIGKILL.
- `layer7_resync()` flush automatico das tabelas dinamicas quando o
  pacote esta em modo passivo (`layer7_flush_dynamic_tables()`).

#### Fixed (Bloco 6 / BG-032) — CLI `--license-status`

- `layer7d --license-status` impressao em `chave=valor` (compativel com
  `awk -F=`), exit `0` se valida (inclui grace) e `1` caso contrario.
  Sai sem inicializar nDPI/captura.

#### Added (F5 minima)

- `tests/functional/test_allowlist.c` (24 casos, todos PASS local).
- `tests/lab/smoke-monitor-mode.sh` — smoke para o appliance pfSense
  validar "monitor nao bloqueia, tabelas vazias, daemon vivo".
- `tests/run-local.sh` — runner local (`cc` + `php -l` + `sh -n`).

#### Notes

- **PORTREVISION** -> `18`. Build no builder FreeBSD (`192.168.100.12`),
  caminho oficial (`AGENTS.md > Fluxo de build padrao`). Validacao real no
  appliance pfSense Plus 26.03 (`192.168.100.254`) com instalacao via
  `IGNORE_OSVERSION=yes pkg add -f` (builder FreeBSD 15 vs appliance 16):
  matriz `monitor` / `enforce` / transicao / CLI licenca toda em PASS.
- A allowlist e a base para a Fase 2 (Caminho A — UX tipo UDM Pro, listas
  selecionaveis, identificacao por dispositivo), que so arranca depois
  desta release validada (gate documental em `docs/02-roadmap/`).

## [1.8.11_17] - 2026-04-27

### Released

- **`pfSense-pkg-layer7-1.8.11_17.pkg`** em
  `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_17`
  (`SHA256=787fcad80f00c085040a38745cf55ccf5870261f5d3ebc762f8ab643c3d81735`).
  Commit: `0b9717e`.

### Fixed

- **`layer7_removal.php`:** erro de sintaxe PHP (`unexpected token "<<"`) — o
  nowdoc estava escrito como `<<'EOSH'` em vez de `<<<'EOSH'`. O script shell
  embutido passou a ser gerado com `implode()` (sem heredoc), para a pagina
  **Remocao do pacote** voltar a carregar na GUI.

## [1.8.11_16] - 2026-04-27

### Released

- **`pfSense-pkg-layer7-1.8.11_16.pkg`** publicado em
  `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_16`
  (`SHA256=a46e710692b466a6a5573d38c42cc686eb1a6bd4fc93684f288147dd96402425`).
  Commit de referencia do port: `b3e0ccb`.

### Added — remocao completa do pacote (GUI + hooks)

- **Nova pagina** `layer7_removal.php` (**Services > Layer 7 > Removal**):
  desinstalacao com confirmacao **REMOVER**, opcoes para preservar
  `layer7.lic` e/ou `layer7.json`, e `pkg delete` em segundo plano (com
  `flush-all` antes do delete quando o helper existe).
- **`pkg-deinstall`**: PRE remove cron BL/relatorios; POST `filter_configure`,
  flush `layer7_*` / `layer7_bld_*` / `layer7_bl_except`, residuos e
  `/usr/local/etc/layer7`.
- **`layer7-pfctl flush-all`**; **`uninstall.sh`** chama `flush-all` quando
  disponivel. **Backlog:** `BG-033`.

### Changed

- **`rc.d/layer7d`**: `layer7d_stop()` com TERM + `pkill` TERM/KILL (`BG-031`).
- **`Makefile`**: `PORTREVISION` `16`; `do-install` inclui `layer7_removal.php`
  no stage (build `make package`).

## [1.8.11_14] - 2026-04-24

### Released

- **`pfSense-pkg-layer7-1.8.11_14.pkg`** publicado em
  `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_14`
  (`SHA256=f9fb1217780bfb90e83821c2652d7177d92eaf5b83f3dfa1fe29d85eaf284705`).
  Hotfix do **GUI updater** sobre `1.8.11_13`. **Sem alteracao de logica
  de bloqueio** (PF, nDPI, force_dns, anti-QUIC, blacklists), **sem
  rotacao de chave** (a chave Ed25519 de blacklists embutida e a mesma
  da `1.8.11_13`, fingerprint
  `6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`),
  **sem republicacao de snapshot UT1** (a snapshot publicada em
  `pablomichelin/Layer7 / blacklists-ut1-current` continua valida e e
  aceite por esta release).
- O trust chain F1.2 do **pacote** continua **nao activado** (`BG-028`);
  esta release publica apenas `.pkg` + `.pkg.sha256` (mesmo padrao de
  `v1.7.8` a `v1.8.11_13`).

### Fixed — GUI "Verificar actualizacao" entrava em loop em `1.8.11_13`

Sintoma observado em `1.8.11_13`: clicar **Verificar actualizacao**
mostrava `latest=1.8.11_13` mas `Versao instalada=1.8.11`, oferecia o
update, o `pkg add -f` reinstalava o mesmo `.pkg`, o daemon reiniciava
com banner `1.8.11`, e o ciclo recomecava. Causa raiz: o `version.str`
gerado pelo Makefile do port estava a usar apenas `${PORTVERSION}` (sem
`${PORTREVISION}`), pelo que `layer7d -V` ficou eternamente preso em
`1.8.11`; o updater do GUI usava esse banner como fonte de "versao
instalada" e comparava-o contra a tag GitHub `v1.8.11_13`.

### Changed — `pfSense-pkg-layer7` (`1.8.11_14`)

- **`package/pfSense-pkg-layer7/Makefile`**
  - `PORTREVISION` `13` -> `14`.
  - `do-build`: `version.str` passa a conter `${PKGVERSION}` em vez de
    `${PORTVERSION}` (= `PORTVERSION_PORTREVISION`, formato canonico do
    `bsd.port.mk` ja usado para `info.xml` e `layer7.xml` na linha 137).
    Resultado: `layer7d -V` passa a imprimir a versao real do pacote
    (ex.: `1.8.11_14`).
- **`files/usr/local/pkg/layer7.inc`**
  - nova `layer7_pkg_version()` — devolve
    `pkg query %v pfSense-pkg-layer7` (fonte canonica do pkg manager
    pfSense). E a unica funcao em que o updater do GUI passa a confiar
    para "versao instalada"; o banner do daemon
    (`layer7_daemon_version()`) fica como fallback cosmetico.
- **`files/usr/local/www/packages/layer7/layer7_settings.php`**
  - `check_update`: `current` passa a vir de `layer7_pkg_version()`
    (com fallback para `layer7_daemon_version()`); o display mostra a
    versao do pkg, e exibe o banner do daemon entre parenteses *so se
    divergir* da versao do pkg.
  - `do_update`: a mensagem verde de sucesso passa a usar
    `layer7_pkg_version()` (no caminho antigo, devolvia o banner que
    nao tinha sido recompilado).
  - **Defesa em profundidade (`BG-030`):** o updater **ignora** releases
    cujo `tag_name` nao case com `/^v?\d+\.\d+/` (ex.:
    `blacklists-ut1-current`), mesmo que o GitHub as devolva como
    `latest` por engano. Reforca a convencao operacional registada na
    `1.8.11_13` (releases nao-pacote sao publicadas como `prerelease`).
- **`files/usr/local/etc/layer7/lang/en.php`**
  - novas keys `daemon` e
    `Release mais recente nao e uma versao do pacote (tag ignorada): `;
    `pt` continua como lingua base.

### Backlog — atendidos

- **`BG-030`** marcado como **Concluido em `1.8.11_14`** (ver
  `docs/02-roadmap/backlog.md`).

### Documentation — release `1.8.11_14`

- **`docs/06-releases/release-notes-1.8.11_14.md`** — notas dedicadas.
- **`docs/10-license-server/MANUAL-INSTALL.md`** — links e comandos das
  seccoes **1**/**4**/**5**/**12** actualizados para `1.8.11_14`;
  novo addendum operacional desta release. A seccao **11b** (activar
  blacklists UT1) continua valida sem alteracao porque a chave nao
  rodou.
- **`CORTEX.md`** — `Ultima versao do pacote publicada em release` passa
  a `1.8.11_14`; checkpoint canonico actualizado.
- **`docs/02-roadmap/backlog.md`** — `BG-030` Concluido.

## [1.8.11_13] - 2026-04-24

### Released

- **`pfSense-pkg-layer7-1.8.11_13.pkg`** publicado em
  `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_13`
  (`SHA256=041e1ace4611ebb1cebd7bfadc22e0bb2c9b2b24b99900e3034f107b534351ae`).
  Esta release publica apenas `.pkg` + `.pkg.sha256` (mesmo padrao de
  `v1.7.8` a `v1.8.11_12`); o trust chain F1.2/F1.4 do **pacote**
  (`release-manifest`/`install.sh` carimbado) **continua nao activado**
  (gate `BG-028`).
- **Primeira publicacao real da trilha F1.3 (blacklists assinadas).**
  Release rolling `blacklists-ut1-current` em
  `https://github.com/pablomichelin/Layer7/releases/tag/blacklists-ut1-current`
  com `layer7-blacklists-manifest.v1.txt` (823 B),
  `layer7-blacklists-manifest.v1.txt.sig` (64 B),
  `blacklists-signing-public-key.pem` (113 B) e
  `layer7-blacklists-ut1.tar.gz` (31 169 229 B,
  `SHA256=4191e2ebdc13e3c87d777103528bab4fda6b273bc40c62a2c39cb820ad493d36`,
  `snapshot_id=ut1-2026-04-25`, 69 categorias, 6 623 069 dominios). Upstream
  (autoridade de conteudo): UT1 / Universite Toulouse Capitole
  (`https://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz`).
- Comportamento `update-blacklists.sh`: **so aceita** snapshots assinadas
  pela chave embutida na `1.8.11_13` (fingerprint
  `6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`); os
  pacotes anteriores recusam este manifesto por fingerprint mismatch
  (`fail-closed` F1.4, comportamento correcto).

### Changed — `pfSense-pkg-layer7` (`1.8.11_13`)

- **`package/pfSense-pkg-layer7/Makefile`** — `PORTREVISION=13`.
- **`package/pfSense-pkg-layer7/files/usr/local/share/pfSense-pkg-layer7/blacklists-signing-public-key.pem`**
  — chave publica Ed25519 rotacionada de
  `e501f5635bf56c6dfc6891ee969ef04ff193ed3afc879997bd4066b6ba3cb064` para
  `6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`. A
  chave anterior nunca foi usada para assinar uma snapshot publica; a
  rotacao e gratuita e nao afecta nenhuma instalacao em campo. A **chave
  privada** correspondente ficou em custodia humana, fora do builder e fora
  do repositorio (alinhado com F1.3 / `AGENTS.md`).

### Documentation — release `1.8.11_13`

- **`docs/10-license-server/MANUAL-INSTALL.md`** — actualizado com **Links
  da versao actual** `1.8.11_13`, comandos `fetch + pkg add -f` para Command
  Prompt nas seccoes **1** (instalar), **4** (upgrade), **5** (reinstalar),
  **6** (desinstalar manual). Adicionado novo addendum operacional da
  release `1.8.11_13` (rotacao chave F1.3) e nova **seccao 11b: activar
  blacklists UT1 apos `1.8.11_13`**.
- **`docs/06-releases/release-notes-1.8.11_13.md`** — notas dedicadas a esta
  release.
- **`docs/02-roadmap/backlog.md`** — observacoes na **BG-020/BG-022**: F1.3
  passou a estar **realmente activa** com primeira snapshot publica
  assinada.
- **`CORTEX.md`** — **Ultima versao do pacote publicada em release** passa
  para `1.8.11_13`; checkpoint canonico actualizado.

## [1.8.11_12] - 2026-04-24

### Released

- **`pfSense-pkg-layer7-1.8.11_12.pkg`** publicado em
  `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_12`
  (`SHA256=902736db23fc94ae5f52d9aeaf71fcf5e75c723799209b55e5e51dcb00138dc7`).
  Esta release publica apenas `.pkg` + `.pkg.sha256` (mesmo padrao de
  `v1.7.8` a `v1.8.3`); o trust chain F1.2/F1.4 (manifesto assinado +
  `install.sh` carimbado fail-closed) nao esta activo nesta release. Ver
  `docs/02-roadmap/backlog.md` **BG-028** para activacao formal num bloco
  futuro com ADR.
- **`docs/10-license-server/MANUAL-INSTALL.md`** — actualizado com **Links
  da versao actual** `1.8.11_12`, comandos `fetch + pkg add -f` para Command
  Prompt nas seccoes **1** (instalar), **4** (upgrade), **5** (reinstalar),
  **6** (desinstalar manual), **12** (rollback). Adicionado **Addendum
  operacional pos-upgrade** com `/etc/rc.filter_configure_sync` para garantir
  que as regras `block drop quick` da trilha Layer7 entram em
  `/tmp/rules.debug` apos `pkg add`.

### Changed — `pfSense-pkg-layer7` (`1.8.11_12`)

- **`package/pfSense-pkg-layer7/Makefile`** — `PORTREVISION=12`.
- **`layer7.inc`** — anti-QUIC por interface: validação de nome com
  `layer7_pf_ifname_for_rules()` em vez de regex duplicada em
  `layer7_generate_rules()` (DRY; sem alteração de comportamento). Docblock da
  função actualizado.

### Documentation — anti-QUIC e `layer7_pf_ifname_for_rules`

- **`docs/05-daemon/pf-enforcement.md`** — secção **Anti-QUIC por interface**
  (`layer7_generate_rules`, DRY `1.8.11_12`).

### Documentation — arquitectura alvo, README do port e índice `docs/`

- **`docs/01-architecture/target-architecture.md`** — item enforcement: pacote,
  NAT `force_dns`, anti-QUIC, `layer7_pf_ifname_for_rules`; ligação a
  `pf-enforcement.md`.
- **`package/pfSense-pkg-layer7/README.md`** — tabela: papel de `layer7.inc` na
  geração PF.
- **`docs/README.md`** — área Releases: DRAFT vs port no branch / `CORTEX`.

### Documentation — governança F4.3 / BG-011

- **`docs/02-roadmap/backlog.md`** — observações **BG-011**: `_11`/`_12` e DRY
  explícitos; docs de enforcement/arquitectura.
- **`docs/02-roadmap/checklist-mestre.md`** — gate F4.3: anti-bypass inclui
  anti-QUIC e referência ao port em branch.

### Documentation — roteiro lab F4.3 (anti-QUIC opcional)

- **`docs/04-package/validacao-lab.md`** — gates, índice F4, checklist #13 e
  secção **11**: evidência opcional `pfctl -s rules` / labels `layer7:anti-quic`;
  nota `1.8.11_12` / `layer7_pf_ifname_for_rules`.
- **`docs/tests/test-matrix.md`** — teste **6.7** alinhado ao mesmo critério.
- **`docs/tests/README.md`** — parágrafo da matriz: F4.3 inclui anti-QUIC.
- **`docs/04-package/checklist-validacao-lab.md`** — remissão à sec. **11**
  (anti-QUIC opcional).

### Documentation — SSOT F4.3 (CORTEX, roadmap, plano F4)

- **`CORTEX.md`** — pontos 7 e 10: sec. **11** com anti-QUIC opcional.
- **`docs/02-roadmap/roadmap.md`** — bloco F4.3 (doc lab) e *Seguinte*.
- **`docs/02-roadmap/f4-plano-de-implementacao.md`** — bloco documental F4.3 e
  teste mínimo.
- **`docs/02-roadmap/f5-preparacao-malha.md`** — pré-requisitos e docs vivas:
  sec. **11** com anti-QUIC opcional.

### Documentation — índices e addenda (sec. **11**, anti-QUIC opcional)

- **`docs/tests/test-matrix.md`** — parágrafo intro: sec. **11** com anti-QUIC.
- **`docs/tests/README.md`** — roteiros F4 / **6.7**: anti-QUIC na **11**.
- **`docs/13-runbooks/README.md`**, **`docs/04-package/deploy-github-lab.md`** —
  remissão ao `validacao-lab`.
- **`docs/00-overview/handoff-chat-novo.md`** — prompt F4.
- **`docs/08-lab/README.md`**, **`docs/08-lab/quick-start-lab.md`** — lab e
  passo **6** (F4).
- **`docs/05-daemon/pf-enforcement.md`** — evidência `force_dns`.
- **`docs/11-blacklists/PLANO-BLACKLISTS-UT1.md`** — addendum F4.3.
- **`docs/10-license-server/MANUAL-INSTALL.md`** — addendum F4.3 (roteiro **11**).
- **`docs/06-releases/release-notes-1.8.11_10-DRAFT.md`** — remissão à sec. **11**.
- **`docs/04-package/README.md`** — entrada `validacao-lab`: sec. **11**.

### Documentation — F4.3 remissões (checklist, backlog, scripts, DIRETRIZES)

- **`docs/02-roadmap/checklist-mestre.md`** — gate F4.3: anti-QUIC opcional na
  sec. **11**.
- **`docs/02-roadmap/backlog.md`** — **BG-011**: observações.
- **`docs/06-releases/release-notes-1.8.11_10-DRAFT.md`** — bloco de evidência F4.
- **`scripts/package/README.md`**, **`scripts/build/BUILDER.md`** — ligação aos
  roteiros **10a**–**11**.
- **`docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md`** — addendum F4.3
  normativo.

### Documentation — package-skeleton e CI (remissão F4)

- **`docs/04-package/package-skeleton.md`** — critério “pacote OK”: gates F4,
  matriz e checklist mestre.
- **`.github/workflows/smoke-layer7d.yml`** — comentário: não substitui roteiros
  **10a**–**11**.

### Documentation — builder, topologia, deploy e DRAFT (F4)

- **`docs/08-lab/builder-freebsd.md`** — após verificação mínima do port:
  não substitui roteiros F4 no appliance.
- **`docs/08-lab/lab-topology.md`** — trilha pós-topologia: gates F4 no link ao
  `validacao-lab`.
- **`docs/04-package/deploy-github-lab.md`** — próximos passos no pfSense.
- **`docs/06-releases/release-notes-1.8.11_10-DRAFT.md`** — checklist pré-publicação.

### Documentation — inventário de lab, quick-start, CI/tests e guia Windows

- **`docs/08-lab/lab-inventory.template.md`** — colunas de validação / gate F4.
- **`docs/08-lab/quick-start-lab.md`** — introdução e passo **6** (F4).
- **`docs/tests/README.md`** — limitações do workflow: não cobre **10a**–**11**.
- **`docs/08-lab/guia-windows.md`** (legado) — fonte vigente: `validacao-lab` com F4.

### Changed — `pfSense-pkg-layer7` (`1.8.11_11`)

- **`package/pfSense-pkg-layer7/Makefile`** — `PORTREVISION=11`.
- **`layer7.inc`** — em `layer7_generate_rdr_rules_snippet()`, o fallback quando
  `get_real_interface()` não preenche o nome reutiliza `layer7_pf_ifname_for_rules()`
  em vez de duplicar a regex (DRY; sem alteração de comportamento).

### Documentation — F4.3 / BG-011: roteiro VLAN multi-interface e rastreabilidade

- **`docs/04-package/validacao-lab.md`** (secção **11**) — cenário de lab sugerido
  **multi-interface / VLAN** para evidência de `force_dns` / `natrules/layer7_nat`.
- **`docs/tests/test-matrix.md`** — ponto **6.7** remete a esse parágrafo.
- **`docs/02-roadmap/f4-plano-de-implementacao.md`** — checkpoint documental
  (continuação) ligando `validacao-lab` §11 e matriz **6.7**.
- **`docs/10-license-server/MANUAL-INSTALL.md`** — addendum F4.3: remissão ao
  roteiro (secção **11**).
- **`docs/08-lab/quick-start-lab.md`** — passo **6** (F4): secção **11** com
  cenário opcional multi-interface / VLAN.
- **`docs/11-blacklists/PLANO-BLACKLISTS-UT1.md`** — addendum F4.3: mesma pista
  no roteiro de evidência.
- **`docs/04-package/README.md`** — índice: nota na entrada `validacao-lab`.
- **`docs/04-package/checklist-validacao-lab.md`**, **`docs/08-lab/README.md`**,
  **`docs/tests/README.md`**, **`docs/tests/test-matrix.md`** (intro),
  **`docs/00-overview/handoff-chat-novo.md`**,
  **`docs/00-overview/document-classification.md`**,
  **`docs/02-roadmap/roadmap.md`** — remissões / checkpoint F4.3 ao cenário
  opcional multi-interface / VLAN na secção **11** / teste **6.7**.
- **`docs/13-runbooks/README.md`**, **`docs/02-roadmap/checklist-mestre.md`**,
  **`docs/02-roadmap/f5-preparacao-malha.md`** — gates F4 / preparação F5 com a
  mesma pista (sec. **11** / VLAN opcional); **`validacao-lab.md`** checklist
  #**13** qualificado.
- **`docs/04-package/validacao-lab.md`** (topo, *Gates oficiais F4*) —
  qualificação da secção **11** / **BG-011**.
- **`docs/04-package/deploy-github-lab.md`** — referências: gates F4 e cenário
  VLAN opcional na **11**.
- **`docs/02-roadmap/backlog.md`** — **BG-011**, observações alinhadas ao roteiro.
- **`docs/05-daemon/pf-enforcement.md`** — secção **DNS forcado** (`force_dns`,
  anchor `natrules/layer7_nat`, remissões MANUAL / `validacao-lab` §11 / **6.7**).
- **`docs/05-daemon/README.md`** — bullet *Enforcement*: pista F4.3 / `force_dns`.

### Documentation — `scripts/package/README` (ordem canónica no índice)

- **`scripts/package/README.md`** — parágrafo inicial com check → smoke →
  `make package` e ligação a `builder-freebsd.md`.
- **`document-classification.md`** — linha `scripts/package/README.md`.

### Documentation — `lab-topology` e `src/layer7d/README` (ordem de build)

- **`08-lab/lab-topology.md`** — trilha builder: `check-port-files` antes de
  smoke e `make package`.
- **`src/layer7d/README.md`** — bloco *Pacote pfSense* + smoke; ligação ao
  README do port.
- **`document-classification.md`** — linha `src/layer7d/README.md`.

### Documentation — README do port e do daemon (ordem de build)

- **`package/pfSense-pkg-layer7/README.md`** — passo `check-port-files` antes do
  smoke e do `make package`.
- **`docs/05-daemon/README.md`** — secção *Build* alinhada a check → smoke →
  port, com `builder-freebsd.md`.
- **`document-classification.md`** — linha `package/pfSense-pkg-layer7/README.md`.

### Documentation — `scripts/release/README.md` (ADR-0003 + build frota)

- **`scripts/release/README.md`** — bloco inicial: repositório de desenvolvimento
  vs canal público `pablomichelin/Layer7` (ADR-0003); compilação na frota com
  `check-port-files` e `smoke-layer7d` antes de `make package`.
- **`document-classification.md`** — linha `scripts/release/README` actualizada.

### Documentation — `scripts/build/BUILDER.md` (ordem canónica)

- **`scripts/build/BUILDER.md`** — passo 5: `check-port-files` antes de smoke e
  `make package`; remissão a *Verificação mínima* em `builder-freebsd.md`.
- **`document-classification.md`** — linha `scripts/build/BUILDER.md` actualizada.

### Documentation — `AGENTS.md` (ponte para builder)

- **`AGENTS.md`** — *Dados do builder:* remissao a `docs/08-lab/builder-freebsd.md`
  (verificacao minima, smoke, SSH por chave).

### Documentation — `builder-freebsd` (verificacao + SSH)

- **`08-lab/builder-freebsd.md`** — seccao *Verificacao minima do port* (check,
  smoke, `make package`); nota macOS/CI; *Acesso SSH* (chave publica vs
  `publickey`).
- **`document-classification.md`** — linha `builder-freebsd` actualizada.

### Documentation — `quick-start-lab` (passo F4 após gate base)

- **`08-lab/quick-start-lab.md`** — passo **6** (*F4*): remissão a Gates,
  **10a/10b/11**, `test-matrix` e `checklist-mestre` após a sequência builder →
  `.pkg` → serviço.
- **`document-classification.md`** e **`08-lab/README.md`** — linha do
  quick-start alinhada ao passo 6 (F4).

### Documentation — runbooks e handoff (F4 / `validacao-lab`)

- **`13-runbooks/README.md`** — validacao em lab: *Gates oficiais F4* no
  inicio de `validacao-lab`.
- **`00-overview/handoff-chat-novo.md`** — prompt de continuacao: pista F4
  (gates + 10a/10b/11, `checklist-mestre`, `test-matrix`).

### Documentation — lab e `tests/README` (Gates F4 no `validacao-lab`)

- **`08-lab/README.md`** — remissão ao início de `validacao-lab` (*Gates
  oficiais F4*); tabela do `validacao-lab` ajustada.
- **`docs/tests/README.md`** — gate de pacote e roteiros F4 alinhados ao
  início de `validacao-lab` (Gates + índice 10a/10b/11).

### Documentation — F4: índice package + plano + checklist

- **`04-package/README.md`** — nota no item `validacao-lab` sobre o parágrafo
  *Gates oficiais F4*.
- **`f4-plano-de-implementacao.md`** — `checklist-mestre` e remissão ao início
  de `validacao-lab` nas referências obrigatórias.
- **`checklist-validacao-lab.md`** — bloco F4 alinhado ao início de
  `validacao-lab` (DRAFT / CORTEX quando aplicável).

### Documentation — ligação `validacao-lab` / DRAFT `1.8.11_10`

- **`validacao-lab.md`** — parágrafo *Gates oficiais F4*: remissões a
  `checklist-mestre`, `test-matrix`, seções **10a/10b/11**, `CORTEX` ponto 7 e
  `release-notes-1.8.11_10-DRAFT.md`.
- **`release-notes-1.8.11_10-DRAFT.md`** — bloco de estado com ligação inversa
  aos mesmos gates de evidência de lab.

### Documentation — CORTEX e `docs/README` (F4 lab + rascunho F7)

- **`CORTEX.md`** — ponto 7 (*Proximos passos*): liga F4 a evidencia minima
  (`validacao-lab` **10a**/**10b**/**11**, `test-matrix` **3.8**/**12.1–12.2**/**6.7**)
  e ao rascunho de release `1.8.11_10`.
- **`docs/README.md`** — area **Releases**: liga o DRAFT `1.8.11_10` (pre-tag).

### Documentation — rascunho de release 1.8.11_10 (F7)

- **`docs/06-releases/release-notes-1.8.11_10-DRAFT.md`** — rascunho
  pre-publicacao com resumo F4, checklist de fecho, ligacoes a
  `MANUAL-INSTALL` e `validacao-lab` (ate existir tag e `.pkg`).
- **`docs/06-releases/README.md`** — listagem do rascunho; **`document-classification.md`**
  — linha *Placeholder* para o mesmo ficheiro.

### Documentation — checklist validação lab (F4)

- **`docs/04-package/checklist-validacao-lab.md`** — nota no topo com roteiros
  F4 (10a/10b/11), `test-matrix` e ligação ao `checklist-mestre`.

### Documentation — `13-runbooks/README` (validação F4 no lab)

- **`docs/13-runbooks/README.md`** — descrição da ligação a `validacao-lab`
  explicita roteiros **F4** no appliance (secções **10a** / **10b** / **11**).

### Documentation — `docs/tests/README` (gate pacote / lab)

- **`docs/tests/README.md`** — secção CI: gate de pacote referencia tambem
  `04-package/README` e `08-lab/README` para navegacao coerente.

### Documentation — `04-package/README` (ligação ao lab)

- **`docs/04-package/README.md`** — paragrafo introdutorio com ligacoes a
  `MANUAL-INSTALL`, `08-lab/README` e `quick-start-lab` (artefacto `.pkg`).

### Documentation — `docs/README` (área Lab)

- **`docs/README.md`** — tabela *Mapa das areas documentais*: entrada **Lab**
  referencia o indice, o `quick-start-lab` e marca `guia-windows` como legado.

### Documentation — classificação `08-lab` (matriz)

- **`docs/00-overview/document-classification.md`** — `quick-start-lab.md`
  reclassificado como **suplementar** (antes “histórico”); `guia-windows.md`
  com coluna *Substitui* actualizada ao indice do lab e a `deploy-github-lab`.

### Documentation — guia Windows (legado) / índice lab

- **`docs/08-lab/guia-windows.md`** — em *Fonte vigente*, ligação ao
  `docs/08-lab/README.md` e a `docs/04-package/deploy-github-lab.md`, para
  quem cair neste ficheiro legado ser desviado de imediato para o fluxo
  canónico.

### Documentation — equivalência release / índice releases

- **`docs/00-overview/document-equivalence-map.md`** — linha release/distribuição
  actualizada: **ADR-0003** como norma do **`.pkg`**; ADR-0002 como histórico;
  removida a nota obsoleta «precisa de ADR substituto».
- **`docs/06-releases/README.md`** — ligação a `deploy-github-lab.md` e ao
  `scripts/release/README.md` na lista de ficheiros da área.

### Documentation — release notes v0.1.0 (instalação)

- **`docs/06-releases/release-notes-v0.1.0.md`** — comando de instalação e
  rollback alinhados a **`install.sh`** + **`.pkg`**; nota de contexto
  documental; removida dependência de `install-lab.sh` na secção de primeira
  instalação.

### Documentation — deploy lab / GitHub Releases (artefacto `.pkg`)

- **`docs/04-package/deploy-github-lab.md`** — reescrito para o fluxo actual:
  `deployz.sh` gera **`.pkg`**, checksum, `install.sh` / `uninstall.sh` e
  manifesto; no pfSense usa-se **`install.sh`** do release (como em
  `scripts/release/README.md`), não `.txz` nem `install-lab.sh` como caminho
  principal. Secção de legado para `install-lab.sh.template`.
- **`docs/00-overview/document-classification.md`** — `deploy-github-lab.md`
  reclassificado como **suplementar** (antes historico pendente de harmonia).

### Changed — F4.3 DNS forcado (`1.8.11_10`)

- **`layer7.inc`** — `layer7_generate_rdr_rules_snippet` prepara por regra a
  lista de CIDRs IPv4 validos (unicos, ordenados) antes de cruzar com
  interfaces, reduzindo validacao repetida e estabilizando a ordem das linhas
  `rdr` face a reordenacao accidental de `src_cidrs` no JSON.
- **`package/pfSense-pkg-layer7/Makefile` (`PORTREVISION`)** — `10` (rebuild
  `1.8.11_10`).

### Changed — F4.3 DNS forcado (`1.8.11_9`)

- **`layer7.inc`** — `layer7_generate_rdr_rules_snippet` ordena alfabeticamente
  as interfaces efectivas antes de emitir `rdr`, para ordem estavel no anchor
  `natrules/layer7_nat` entre reloads com a mesma configuracao.
- **`package/pfSense-pkg-layer7/Makefile` (`PORTREVISION`)** — `9` (rebuild
  `1.8.11_9`).

### Changed — F4.3 DNS forcado (`1.8.11_8`)

- **`layer7.inc`** — `layer7_generate_rdr_rules_snippet` deduplica pares
  **(interface, CIDR)** entre regras de blacklist com `force_dns`, evitando
  regras `rdr` UDP/TCP redundantes no sub-anchor `natrules/layer7_nat`.
- **`package/pfSense-pkg-layer7/Makefile` (`PORTREVISION`)** — `8` (rebuild
  `1.8.11_8`).

### Documentation / CI — saneamento do fluxo Windows/macOS

- **`docs/08-lab/guia-windows.md`** — reclassificado como documento legado,
  sem comandos activos de WSL/PowerShell/smoke local.
- **`docs/08-lab/README.md`** e **`document-classification.md`** — Windows deixa
  de aparecer como fluxo suplementar vigente.
- **`validacao-lab.md`** e **`scripts/package/README.md`** — macOS fica
  explicitamente limitado a workspace de edicao/git/docs; build/smoke tecnico
  ficam no builder FreeBSD e validacao funcional no pfSense appliance.
- **`scripts/package/smoke-layer7d.sh`** — macOS/Darwin passa a falhar fechado
  por defeito para impedir falso gate local.
- **`.github/workflows/smoke-layer7d.yml`** — removido job Windows para nao
  sugerir validacao fora do fluxo real.

### Fixed — F4.2/F4.3 blacklists runtime (`1.8.11_7`)

- **`layer7d` DNS blacklist** — respostas DNS passam a transportar o IP do
  cliente para o callback e a validar `src_cidrs` por regra antes de popular
  `layer7_bld_N`, evitando vazamento de bloqueio entre redes/regras.
- **`layer7d` reload blacklists** — SIGHUP deixa de limpar regras/tabelas antes
  de validar a nova carga; falha de carga preserva blacklist e tabelas
  anteriores.
- **`blacklist.c`** — dominios presentes em multiplas categorias passam a
  guardar mascara de categorias; DNS/SNI fazem lookup contra as categorias da
  regra, corrigindo falso negativo em categoria sobreposta.
- **GUI/package blacklists** — `pkg-install` prepara
  `/usr/local/etc/layer7/blacklists` e `_custom` para `www:wheel`; saves da GUI
  passam a reportar erro quando `config.json` ou overlays nao puderem ser
  gravados.
- **Auto-update cron** — `update_interval_hours` passa a ser convertido para
  campos cron coerentes, em vez de inverter intervalos curtos/longos.
- **Activacao CLI** — removido fallback `fetch` que fazia GET sem payload; URL
  customizada de activacao passa a exigir HTTPS e caracteres seguros para shell.
- **CI** — workflow smoke passa a incluir syntax check dos scripts shell do
  pacote.

### Documentation — conflitos visiveis

- **`README.md`** — versao publica e install rapido alinhados para `1.8.3`.
- **`docs/README.md`** — hierarquia de leitura renumerada sem duplicar item.
- **`docs/08-lab/guia-windows.md`** — build de lab alinhado para `.pkg`.
- **Blacklists docs** — versao/caminho F4 alinhados ao branch `1.8.11_7` e ao
  consumo assinado.

### Documentation — scripts de pacote / CI

- **`scripts/package/README.md`** — `smoke-layer7d.sh`: nota Darwin/macOS vs
  FreeBSD canónico e CI Linux
- **`.github/workflows/smoke-layer7d.yml`** — comentário no job Windows (só
  `check-port-files.ps1`)

### Documentation — validação lab / CI

- **`validacao-lab.md`** — secção **3** (Build): nota Darwin/macOS vs smoke
  canónico no FreeBSD e CI Linux
- **`.github/workflows/smoke-layer7d.yml`** — comentário: artefacto oficial
  `.pkg` (não `.txz`)

### Changed — smoke layer7d (mensagem em Darwin)

- **`scripts/package/smoke-layer7d.sh`** — aviso em **Darwin/macOS** de que o
  link com `-lcrypto` pode falhar; smoke canónico no builder FreeBSD

### Documentation — F5 (preparação / ponte F4)

- **`f5-preparacao-malha.md`** — pré-requisitos e ordem de trabalho alinhados aos
  três gates F4 (10a / 10b / 11, matriz 3.8, 12.x, 6.7) e ao índice em
  `validacao-lab.md`

### Documentation — validação lab (índice F4)

- **`validacao-lab.md`** — *Índice dos roteiros F4*: nota única sobre
  pré-requisito builder (secção 3) antes do appliance para 10a / 10b / 11

### Documentation — validação lab (F4.1 / roteiro 10a)

- **`validacao-lab.md`** — secção **10a**: pré-requisito builder (`check-port-files`,
  `smoke-layer7d`, `make package`) antes da evidência no appliance
- **`f4-plano-de-implementacao.md`** — teste mínimo F4.1 alinhado a 10b/11

### Documentation — validação lab (F4.3 / roteiro 11)

- **`validacao-lab.md`** — secção **11**: pré-requisito builder (`check-port-files`,
  `smoke-layer7d`, `make package` com F4.3) antes da evidência `pfctl` no appliance
- **`f4-plano-de-implementacao.md`** — teste mínimo F4.3 alinhado

### Documentation — validação lab (F4.2 / roteiro 10b)

- **`validacao-lab.md`** — secção **10b**: pré-requisito explícito
  (`check-port-files.sh`, `smoke-layer7d.sh`, `make package`) antes da
  evidência no appliance

### Documentation — roadmap / testes (gates F4)

- **`roadmap.md`** — checkpoint F4: gates do `checklist-mestre` para F4.1, F4.2
  e F4.3 (secções / pontos da matriz)
- **`docs/tests/README.md`** — ligação ao *Índice dos roteiros F4* em
  `validacao-lab.md`

### Documentation — validação lab / matriz (F4.1, PHP pidfile)

- **`validacao-lab.md`** — secção **10a** e índice F4: critérios e versão mínima
  (`PORTREVISION` ≥ 6) para paridade PHP (`layer7_daemon_pid_from_file`) com
  scripts/`rc.d`
- **`test-matrix.md`** — teste **3.8** explicita verificação na GUI para pacote
  ≥ `1.8.11_6`

### Changed — F4.1 / PHP (pidfile)

- **`layer7.inc`** — `layer7_daemon_pid_from_file()` (primeira linha, trim,
  só dígitos); uso em `layer7_ensure_daemon_running`, `layer7_restart_service`,
  `layer7_signal_reload`, `layer7_read_stats`
- **`layer7_status.php`**, **`layer7_diagnostics.php`** — leitura do pidfile
  via helper (alinhado a `rc.d` / scripts sh)
- **`Makefile` (`PORTREVISION`)** — `6` (rebuild `1.8.11_6`)

### Documentation — gates F4 e índice de roteiros (lab)

- **`validacao-lab.md`** — tabela **Índice dos roteiros F4** (10a / 10b / 11 ↔
  BG-009 / BG-010 / BG-011 ↔ matriz)
- **`checklist-mestre.md`** — itens de evidência mínima para **F4.1** e **F4.2**
  (paralelos ao gate já existente da **F4.3**)
- **`CORTEX.md`** — ponto 10 dos próximos passos alinhado aos três gates F4.1–F4.3

### Documentation — validação lab / matriz (F4.2 BG-010)

- **`validacao-lab.md`** — secção **10b**: roteiro do updater, log, SIGHUP,
  `fallback.state` (healthy / degraded / fail-closed); checklist **#15**
- **`test-matrix.md`** — secção **12** (blacklists F4.2), testes **12.1–12.2**
  pendentes; **Resumo** alinhado (82 totais, daemon 8/7/1, 8 pendentes)
- **`docs/tests/README.md`** — contagens 82 / 8 pendentes + menção F4.2
- **`f4-plano-de-implementacao.md`**, **`roadmap.md`**, **`backlog.md`**,
  **`PLANO-BLACKLISTS-UT1.md`** — referências cruzadas ao roteiro 10b

### Documentation — validação lab / matriz (F4.1 BG-009)

- **`validacao-lab.md`** — secção **10a**: roteiro objectivo no appliance para
  pidfile, `rc.d`, permissões 0644 e critérios mínimos de PASS; checklist
  rápido com item 14
- **`test-matrix.md`** — teste **3.8** (daemon) pendente, ligado à secção 10a
- **`docs/tests/README.md`** — contagens 80 testes / 6 pendentes
- **`f4-plano-de-implementacao.md`** — teste mínimo F4.1 referencia a secção 10a

### Documentation — blacklists (alinhamento F4.1 / pidfile)

- **`PLANO-BLACKLISTS-UT1.md`** — pseudo-código do fluxo de update: passo 12
  deixa de sugerir `cat` cru no pidfile; descreve `send_sighup` e
  `service layer7d reload`

### Documentation — MANUAL-INSTALL (F4.1)

- **`docs/10-license-server/MANUAL-INSTALL.md`** — addendum F4.1 (BG-009):
  validacao do pidfile no `rc.d` e alinhamento com scripts do pacote;
  aviso para nao editar `/var/run/layer7d.pid`; referencia a
  `CORTEX.md`/`Makefile` para `PORTVERSION`/`PORTREVISION` de trabalho vs
  `.pkg` publico

### Documentation — contrato do pidfile do daemon

- **`docs/05-daemon/README.md`** — secção *Pidfile* (`/var/run/layer7d.pid`):
  formato esperado, consumidores (GUI, `layer7.inc`, updater, cron, helpers F3)
  e referência às entregas F4 / `f4-plano`

### Changed — helpers F3 (pidfile no appliance)

- **`scripts/license-validation/export-appliance-evidence.sh`** — no bloco
  remoto que força `USR1` para refrescar `layer7-stats.json`, leitura de
  `/var/run/layer7d.pid` alinhada aos scripts do pacote (`read -r`, trim,
  PID numerico, `kill -0` antes de `USR1`)

### Changed — F4.1 / rc.d (pidfile)

- **`files/usr/local/etc/rc.d/layer7d`** — `layer7d_pid_from_file` (trim,
  PID numerico) usado em `start`, `stop`, `status` e `reload`, alinhado a
  `update-blacklists.sh` / `layer7-stats-collect.sh`
- **`Makefile` (`PORTREVISION`)** — `5` (rebuild `1.8.11_5`)

### Changed — F4.1 / cron (pidfile)

- **`layer7-stats-collect.sh`** — leitura de `/var/run/layer7d.pid` alinhada a
  `update-blacklists.sh` (`send_sighup`): `read -r`, trim com `sed`, rejeicao
  de PID nao numerico antes de `kill -0` / `USR1` (`PORTREVISION` `4` / build
  `1.8.11_4` nesse bloco)

### Documentation — F5 (preparacao) alinhada a F4

- **`f5-preparacao-malha.md`** — prerequisitos com gates do `checklist-mestre`
  (F4 / F4.3) e ligação a `validacao-lab` / `test-matrix` 6.7; passo 0 na
  ordem de trabalho (evidencia F4 antes de prometer F5 plena); secção 5 com
  referencia a checklist e roteiros de lab

### Documentation — gates F4 no checklist mestre

- **`checklist-mestre.md`** — checklist de testes e gates: itens F4 (paralelismo
  com F3) e F4.3 / BG-011 (evidência `validacao-lab` sec. 11 e `test-matrix`
  6.7); gate resumido F4 com referência a evidência por subfase
- **`CORTEX.md`** — `Proximos passos` ponto 10 aponta para estes gates

### Documentation — blacklists (F4.3) e índice de testes

- **`PLANO-BLACKLISTS-UT1.md`** — addendum F4.3: links a `f4-plano`,
  `validacao-lab` sec. 11, `test-matrix` 6.7, `MANUAL-INSTALL`, **BG-011**
- **`docs/tests/README.md`** — contagem 79/74/5; menção explícita ao **6.7**
  (F4.3) na matriz

### Documentation — matriz de testes (F4.3)

- **`docs/tests/test-matrix.md`** — ponto **6.7** (anchor NAT `force_dns` /
  `pfctl`); resumo 79/74/5; título e referência ao `validacao-lab` sec. 11

### Documentation — validacao de lab (F4.3)

- **`docs/04-package/validacao-lab.md`** — secção 11: roteiro e criterio PASS
  para o anchor NAT `natrules/layer7_nat` / `force_dns`; linha 13 no checklist
  rapido; ligacao ao addendum F4.3 do `MANUAL-INSTALL`

### Changed — F4.3 enforcement / DNS forcado (BG-011)

- **`layer7.inc` (`layer7_generate_rdr_rules_snippet`)** — deduplica nomes de
  interface apos `get_real_interface` / fallback VLAN, evitando linhas `rdr`
  repetidas; so emite `rdr` para `src_cidrs` que passam `layer7_cidr_valid`
  ou `layer7_ipv4_valid` (evita `pfctl` a rejeitar o anchor NAT por texto
  invalido)
- **`layer7.inc` (`layer7_get_pfsense_interfaces`)** — retorna lista vazia se
  `get_configured_interface_list` ou `get_real_interface` nao existirem
  (contexto nao-pfSense / testes), em vez de erro fatal
- **`layer7.inc` (`layer7_pf_ifname_for_rules` / `layer7_log_pkg_warn`)** —
  nomes de interface em `rdr` alinham-se ao padrao do anti-QUIC; interfaces
  filtradas antes de gerar o snippet; falha de `tempnam`, escrita do ficheiro
  temp ou `pfctl -N -f` no anchor `natrules/layer7_nat` regista aviso via
  `log_error` / `error_log`
- **`Makefile` (`PORTREVISION`)** — `2` (rebuild; `1.8.11_2`)

### Documentation — F4.3 (BG-011) e manual operacional

- **`MANUAL-INSTALL.md`** — addendum F4.3: `force_dns` injectado no anchor NAT
  `natrules/layer7_nat`, comando de verificacao `pfctl -a natrules/layer7_nat
  -s nat`, validacao/dedup de origens, ambito **inet** (IPv4) sem `rdr` IPv6
  nesta trilha

### Documentation — F4.1 (BG-009) e roadmap F4

- **`MANUAL-INSTALL.md`** — addendum operacional F4.1: `POST-INSTALL` com
  `onestop` antes de `onestart` no upgrade, pidfile e `status`, alinhamento
  do reload da GUI com o `rc.d`; nota de que a referencia de `.pkg` publica
  segue a versao listada em **Links da versao actual** ate nova release
- **`roadmap.md`** (checkpoint F4) — proximo passo explicito: evidencia em
  lab/appliance e F4.3, em paralelo ao **DR-05** para a F3

### Changed — F4.2 blacklists (BG-010)

- **`update-blacklists.sh` (`send_sighup`)** — leitura segura do pidfile
  (`read -r`); normalizacao de espacos em branco à volta do PID (`sed`) antes
  da validacao numerica; rejeita PID nao numerico; `kill -0` antes de `HUP`;
  regista WARN quando o daemon nao esta a correr em vez de `HUP` silencioso a
  PID invalido
- **`update-blacklists.sh` (`--restore-lkg`)** — adquire o mesmo lock exclusivo
  que `do_download`, impedindo restauracao LKG concorrente com um update
  (evita corrida em `promote_candidate`)
- **`layer7-pfctl`** — todas as invocacoes de `pfctl` passam a usar
  `/sbin/pfctl` (PATH minimo em cron/rc alinhado a `table_ready` / `pfctl -sr`)
- **`PORTVERSION` / `PORTREVISION`** — `1.8.11` com incrementos de
  `PORTREVISION` em blocos F4.2 (ex.: `3` com trim em `send_sighup`; ver
  entradas mais recentes no topo de `[Unreleased]` para o número actual);
  artefacto publico de referencia continua `1.8.3` ate nova release

### Changed — F4.1 package/daemon (BG-009)

- **`rc.d/layer7d`** — apos `daemon -p`, o pidfile fica `0644` para
  `service layer7d status` nao falhar por permissoes quando o ficheiro era
  `0600 root:wheel`
- **`pkg-install` (`POST-INSTALL`)** — `service layer7d onestop` antes de
  `onestart` para upgrades aplicarem o binario do pacote recém-instalado
  (antes, `onestart` com processo vivo saia cedo sem reiniciar)
- **`layer7.inc` (`layer7_signal_reload`)** — se o pidfile estiver ausente,
  invalido ou o processo nao existir, passa a invocar
  `layer7_ensure_daemon_running()` (sobe o daemon quando `layer7.enabled` no
  JSON), em linha com o `reload` do `rc.d` (HUP apenas quando o processo esta
  vivo); leitura do pidfile com `@file_get_contents` para evitar avisos
- **`layer7.inc` (`layer7_restart_service`, `layer7_read_stats`)** — leitura do
  pidfile com `@file_get_contents`; `kill -0` antes de `USR1` nas estatisticas;
  verificacao pos-restart com `kill -0` redireccionado para `/dev/null`
- **`pkg-deinstall`** — `PRE-DEINSTALL`: `service layer7d onestop`; `POST-DEINSTALL`:
  remover `/var/run/layer7d.pid` stale e `sysrc layer7d_enable=NO` antes do
  reload PF (evita processo orfao e arranque pendente apos `pkg delete`)
- **`layer7_status.php`** — `kill -0` com stderr para `/dev/null` (alinhado ao
  resto da trilha F4.1)

### Added — continuidade entre chats longos

- [`docs/00-overview/handoff-chat-novo.md`](../00-overview/handoff-chat-novo.md) — quando mudar para um chat novo no Cursor, sinais práticos e **prompt modelo** para colar na primeira mensagem; referência no `CORTEX`, `docs/README` e `AGENTS.md`

### Changed — F4 e F5 (governança) em 2026-04-24

- **F4.0 aberta** com [`docs/02-roadmap/f4-plano-de-implementacao.md`](../02-roadmap/f4-plano-de-implementacao.md) — subfases
  F4.1 (package/daemon, BG-009), F4.2 (blacklists, BG-010), F4.3 (enforcement,
  BG-011); **paralelismo** explicito com a F3 ainda aberta (pendência DR-05)
  **sem** alterar o contrato de licenciamento em blocos F4
- **F5 (preparacao)** com [`docs/02-roadmap/f5-preparacao-malha.md`](../02-roadmap/f5-preparacao-malha.md) — roteiro
  para malha de testes antes da execução plena (BG-012 a BG-014)
- **`CORTEX`**, **roadmap** e **backlog** actualizados: tabela de fases,
  `Proximos passos` (F4 e F5), estados de BG-009/BG-010; `docs/README` indexa
  os planos

### Changed — governanca e license-server (2026-04-24)

- **Politica reutilizavel do download administrativo do `.lic` (`GET /api/licenses/:id/download`)** —
  a validacao (licenca activada, hardware, estado) passa a concentrar-se em
  `license-server/backend/src/license-download-policy.js`, com testes
  `license-download-policy.test.js` e reutilizacao na rota em
  `routes/licenses.js` para alinhar ao padrao de politicas testaveis
  (`activation-policy`, `license-update-policy`)
- **`npm test` no backend do license-server** — o script passa a incluir
  todos os ficheiros `src/**/*.test.js` (nao so `src/*.test.js`), garantindo
  que modulos em subpastas com testes associados entram na suite
- **Documentacao (`CORTEX.md`)** — checkpoint fixo, bloco de
  "ultimo status" e riscos actualizados: F3 como fase aberta, distincao
  explicita entre versao .pkg publicada (`1.8.3`) e `PORTVERSION` de
  trabalho no repositorio (`1.8.4`), e paragrafo operacional alinhado ao
  estado pos-F1.4 (sem pedir reabertura de F1.4)
- **Integridade de ficheiros do port** — se ficheiros canónicos do pacote
  (ex. `layer7.inc`, `layer7-pfctl`, `pf.conf.sample`) aparecerem vazios ou
  truncados no disco, restaurar a partir de `origin/main` antes de
  qualquer build; o estado "0 bytes" local nao e commitavel nem releasavel

### Changed — alinhamento do license-server live

- **License-server live alinhado ao contrato administrativo actual** —
  o ambiente activo em `192.168.100.244:/opt/layer7-license` passa a expor
  `admin_sessions`, `admin_audit_log` e `admin_login_guards`, responde
  `GET /api/auth/session`, mantem a bridge Bearer administrativa e volta a
  falhar fechado para `Origin` externo em `/api/auth/login`
- **DR-05 do appliance passa a ter baseline real e SSH funcional** —
  o utilizador temporario `codex` em `192.168.100.254` passa a permitir
  exportar baseline canónico do appliance, confirmar fingerprint/licenca
  actual e validar restart de `layer7d`; os cenarios mutaveis continuam
  dependentes de permissao de escrita em `/usr/local/etc/layer7.lic`
- **Baseline canónica do appliance ganha novo run real via helper** —
  `scripts/license-validation/export-appliance-evidence.sh` foi executado com
  sucesso no `run_id` `20260414T000000Z-appliance254-continue`, materializando
  `40-preflight-appliance.txt`, `50-appliance-cli.txt`,
  `60-appliance-license.json` e `70-local-hashes.txt` com o estado real do
  appliance sob o utilizador `codex`
- **Trilha GUI autenticada do pfSense ganha helper canónico de campanha** —
  `scripts/license-validation/run-pfsense-gui-license-flow.sh` passa a
  materializar `probe`, `register` e `revoke` com captura de `headers`,
  `HTML`, `cookie jar` e notas por `run_id`, incluindo execucao via
  `--ssh-target` quando a GUI util so responde em
  `https://127.0.0.1:9999/` no proprio appliance, reduzindo improviso
  operacional no `DR-05`
- **Painel administrativo passa a editar licencas existentes** —
  a SPA passa a expor `/licenses/:id/edit`, reutiliza o endpoint
  `PUT /api/licenses/:id`, bloqueia a troca de cliente quando a licenca ja
  esta activada/bindada e cobre a normalizacao do formulario com teste puro
- **Contrato de rejeicao de activacao passa a ter regressao dedicada** —
  a politica do `POST /api/activate` para licenca revogada, licenca expirada
  e hardware divergente passa a ficar isolada em helper testavel e coberta
  por testes que preservam `409`, reduzindo o risco de reintroduzir o drift
  cosmetico `403` observado anteriormente no live
- **Auditoria de emissao/reemissao do `.lic` passa a ter regressao dedicada** —
  a metadata auditada dos artefactos emitidos por `activate` e por download
  administrativo passa a ser coberta por testes puros, preservando
  `flow`, `emission_kind`, binding, customer/features e hashes SHA-256 de
  payload, assinatura e envelope
- **Estado efectivo de licencas passa a ter regressao dedicada** —
  `license-state` passa a cobrir por testes o contrato `active` /
  `expired` / `revoked`, expiracao por data, precedencia de revogacao,
  normalizacao de hardware e predicados SQL usados por listagens e dashboard
- **Payload publico de activacao passa a ter regressao dedicada** —
  `parseActivatePayload` passa a cobrir normalizacao de `key` e
  `hardware_id`, rejeicao de campos inesperados e erros `400` para chave ou
  hardware invalidos antes de tocar na transacao de activacao
- **Guardrail de update administrativo de licenca passa a ter regressao dedicada** —
  a deteccao de campos alterados e o bloqueio `409` contra troca de
  `customer_id` em licenca activada/bindada passam a ficar isolados em helper
  testavel, preservando a proteccao contra transferencia silenciosa de
  ownership

### Fixed — auth bridge do painel administrativo

- **Helpers shell da F3 deixam de falhar no bash 3.2 do macOS quando `SSH_OPTIONS` esta vazio** —
  `scripts/license-validation/export-appliance-evidence.sh`,
  `scripts/license-validation/run-appliance-activation-scenario.sh` e
  `scripts/license-validation/prepare-f3-preflight.sh` passam a proteger os
  loops de `SSH_OPTIONS` sob `set -u`, evitando erro `unbound variable`
  antes de qualquer tentativa real de SSH

- **Bootstrap da sessao sincroniza a ponte Bearer sem storage persistente** —
  `license-server/frontend/src/auth.jsx` continua a absorver o token
  devolvido por `GET /api/auth/session`, mas a credencial de compatibilidade
  passa a ficar apenas em memoria, evitando reintroduzir `localStorage`
- **Estado autenticado consolidado num helper pequeno** —
  `license-server/frontend/src/auth-session-state.js` centraliza aplicar e
  limpar sessao/token no frontend, reduzindo duplicacao e risco de esquecer
  a limpeza da credencial transitória em falhas/logout
- **Payload autenticado do frontend passa a exigir coerencia minima** —
  `license-server/frontend/src/auth-payload.js` passa a normalizar respostas
  de auth e a rejeitar payload parcial sem `admin` e `session`, evitando
  manter token em memoria quando o backend devolve estado malformado
- **Controller puro da auth do frontend agora e testavel em isolamento** —
  `license-server/frontend/src/auth-controller.js` passa a concentrar
  bootstrap, login, refresh, logout e limpeza do estado autenticado, enquanto
  `auth-controller.test.js` cobre sucesso, falha e view inactiva sem exigir
  harness React mais pesado
- **Login e refresh do frontend rejeitam payload parcial sem reter estado velho** —
  `auth-controller.test.js` passa a provar que respostas malformadas de
  `/auth/login` e `/auth/session` limpam `admin/session` locais em vez de
  manter estado stale ao lado de um token transitório
- **Login deixa de prosseguir com sessao parcial de sucesso** —
  `loginWithPassword()` passa a falhar explicitamente quando o backend devolve
  `200` com payload de auth incoerente, evitando navegar para a area privada
  com estado local ja limpo
- **Refresh deixa de tratar sessao parcial como sucesso silencioso** —
  `refreshAuthSession()` passa a falhar explicitamente quando `/auth/session`
  devolve payload incoerente, evitando revalidacao enganosa com estado local
  previamente limpo
- **Regra de consistencia de sessao do frontend vira helper puro** —
  `license-server/frontend/src/auth-payload.js` passa a centralizar tambem a
  validacao que levanta erro para payload incoerente, evitando drift entre
  `loginWithPassword()` e `refreshAuthSession()`
- **Aplicar e validar sessao autenticada vira operacao unica** —
  `license-server/frontend/src/auth-session-state.js` passa a expor
  `syncAuthenticatedSession()`, reduzindo duplicacao entre `login` e `refresh`
  ao aplicar estado e validar coerencia no mesmo helper
- **Flags canonicas da auth administrativa deixam de ficar repetidas** —
  `license-server/frontend/src/auth-request-options.js` passa a centralizar
  `skipAuthRedirect: true`, reduzindo drift entre bootstrap, login, refresh e
  logout do frontend
- **Caminhos de auth do frontend passam a ser canónicos** —
  `license-server/frontend/src/auth-paths.js` passa a concentrar os endpoints
  de login, logout e sessao, reduzindo risco de drift entre controller e
  camada API
- **Rotas principais do painel passam a ter destino canónico unico** —
  `license-server/frontend/src/panel-routes.js` passa a concentrar os
  destinos de login e dashboard usados por `App`, `Login` e `Sidebar`,
  reduzindo drift entre navegação protegida e navegação pós-login
- **Links principais da navegação lateral também passam a usar rotas canónicas** —
  `license-server/frontend/src/panel-routes.js` passa a concentrar também os
  destinos de licenças e clientes usados pela `Sidebar`, reduzindo mais um
  ponto de drift entre a navegação lateral e as rotas oficiais da SPA
- **Detalhe, criação e edição do painel passam a usar builders canónicos de rota** —
  `license-server/frontend/src/panel-routes.js` passa a expor também os
  destinos `new`, detalhe e `edit` de licenças/clientes, reduzindo drift
  entre listagens, formulários, detalhe e navegação de retorno do painel
- **Redirect de sessao invalida passa a reutilizar a rota canónica de login** —
  `license-server/frontend/src/api.js` passa a consumir
  `ADMIN_LOGIN_ROUTE` em vez de repetir `'/login'`, alinhando a camada API
  ao mesmo destino oficial já usado pelo restante fluxo de navegação do painel
- **Logout do frontend preserva a resposta do backend sem perder limpeza local** —
  `logoutAuthSession()` passa a devolver o payload de sucesso de
  `/auth/logout` quando existir, mantendo a limpeza defensiva do estado
  autenticado tanto em sucesso quanto em erro
- **Escuta do evento de sessao invalida sai do componente e vira helper puro** —
  `license-server/frontend/src/auth-invalid-listener.js` passa a concentrar
  a inscricao e limpeza do `layer7:auth-invalid`, com cobertura dedicada para
  estado activo, inactivo e ausencia de target de eventos
- **Provider de auth deixa de declarar autenticacao com estado parcial** —
  `license-server/frontend/src/auth-context-value.js` passa a exigir
  `admin + session` para `isAuthenticated`, evitando falso positivo quando o
  estado local estiver parcialmente hidratado ou limpo
- **Gate de auth do frontend passa a usar decisao unica de estado** —
  `license-server/frontend/src/auth-gate.js` centraliza a leitura
  `loading` / `authenticated` / `anonymous`, reduzindo drift entre `App` e
  `Login` na hora de mostrar loading ou redirecionar
- **Fluxo de sessao invalida da API sai do corpo da request** —
  `license-server/frontend/src/api-auth-redirect.js` passa a centralizar a
  limpeza do token em memoria, a emissao do evento e o redirect para login,
  reduzindo acoplamento na camada `api`
- **Evento de sessao invalida passa a ter nome canónico unico** —
  `license-server/frontend/src/auth-events.js` passa a concentrar o nome do
  evento usado por `api` e pelo listener de auth, reduzindo risco de drift
  entre emissao e subscricao
- **Mensagens criticas de auth do frontend passam a ser canónicas** —
  `license-server/frontend/src/auth-messages.js` passa a concentrar as
  mensagens de sessao expirada e sessao incoerente, reduzindo drift entre
  controller, payload, camada API e testes
- **Fallback de erro no login também passa a ter mensagem canónica** —
  `license-server/frontend/src/auth-messages.js` passa a concentrar também a
  mensagem padrão de falha do formulário de login, reduzindo mais um literal
  solto dentro da tela administrativa
- **Mensagem de validacao da sessao tambem passa a ser partilhada** —
  `App` e `Login` passam a reutilizar a mesma constante de loading da auth,
  evitando drift visual pequeno entre as duas entradas principais do painel
- **Cobertura automatizada leve da trilha** —
  `license-server/frontend/src/auth-session-state.test.js` passa a provar que
  o token de compatibilidade vive apenas em memoria e e limpo junto com o
  estado autenticado, sem exigir infra adicional nem tocar no contrato do backend
- **Camada API agora tem smoke tests locais repetiveis** —
  `license-server/frontend/src/api.test.js` e o script `npm test` do frontend
  passam a verificar injecao do header Bearer em memoria, limpeza do token em
  `401` e o comportamento de `skipAuthRedirect`, reduzindo regressao silenciosa na SPA
- **Redirect 401 e parsing da API viram helpers puros** —
  `license-server/frontend/src/api-response.js` passa a concentrar a decisao
  de sessao invalida, o parsing de erro e o parsing de sucesso da camada API,
  com cobertura dedicada para `401`, fallback de erro, `204`, JSON e texto
- **Headers da camada API ficam robustos a casing misto** —
  `license-server/frontend/src/api.js` passa a tratar `Authorization` e
  `Content-Type` de forma case-insensitive, evitando injectar Bearer extra ou
  sobrescrever `content-type` custom quando o caller usa chaves em lowercase
- **Login deixa de reutilizar Bearer herdado por acidente** —
  `license-server/frontend/src/api.js` passa a nunca injectar a credencial
  transitória em `POST /api/auth/login`, evitando enviar token antigo para o
  endpoint que deve depender apenas das credenciais fornecidas no momento
- **Bridge Bearer do backend ganha segredo dedicado e nao vaza token cru** —
  `license-server/backend/src/bearer-session-token.js` extrai a logica pura
  de assinatura/verificacao do token administrativo para um modulo pequeno;
  a emissao passa a depender so de `ADMIN_BEARER_JWT_SECRET`, sem fallback
  para `ED25519_PRIVATE_KEY` nem para o token opaco cru da sessao
- **Resposta de auth do backend passa a ter montagem unica e testavel** —
  `license-server/backend/src/auth-response.js` centraliza o payload comum de
  `login` e `session`, reduzindo drift entre as duas rotas e cobrindo quando
  o token Bearer de compatibilidade deve ou nao aparecer
- **Precedencia Bearer/cookie do backend passa a ser helper puro** —
  `license-server/backend/src/auth-access.js` centraliza a seleccao e a fila
  de candidatos de acesso administrativo, deixando explicita a prioridade do
  Bearer validado sobre o cookie e cobrindo deduplicacao em teste local leve
- **Middleware de auth administrativa ganha cobertura dedicada** —
  `license-server/backend/src/auth.test.js` passa a cobrir sessao valida,
  sessao invalida e erro interno do resolvedor, enquanto
  `license-server/backend/src/auth-middleware.js` isola o factory puro para
  injeção de dependências e teste sem DB
- **Login failure e logout audit do backend ganham helpers puros** —
  `license-server/backend/src/auth-route-helpers.js` centraliza a montagem
  de `lockout_scopes`, `admin_id` opcional e do payload de auditoria do
  logout, reduzindo duplicacao e cobrindo a regra em teste local leve
- **Ciclo de vida da sessao administrativa vira regra pura e testavel** —
  `license-server/backend/src/session-lifecycle.js` passa a centralizar a
  decisao de expirar, renovar ou apenas actualizar `last_seen_at`, reduzindo
  risco de drift na janela de renovacao e no timeout absoluto
- **Falhas de login do backend passam a ter payload de auditoria centralizado** —
  `license-server/backend/src/auth-route-helpers.js` passa a montar tambem os
  eventos de `login_rejected`, `login_locked`, `login_failed` e `login_error`,
  reduzindo repeticao na rota de auth e deixando a trilha negativa mais
  previsivel em teste local
- **Eventos positivos e erro de logout da auth tambem saem da rota** —
  `license-server/backend/src/auth-route-helpers.js` passa a centralizar
  tambem `login_succeeded`, `session_created` e `logout_error`, deixando a
  auditoria administrativa da auth concentrada num unico ponto testavel
- **Middleware de sessao passa a usar payloads de auditoria centralizados** —
  `license-server/backend/src/auth-middleware.js` passa a consumir helpers
  para `admin_access_denied` e `session_validation_error`, fechando a trilha
  de auditoria da auth administrativa num unico modulo puro
- **Respostas HTTP da rota de auth deixam de ser montadas inline** —
  `license-server/backend/src/auth-route-response.js` passa a centralizar
  payloads de erro e a resposta de sucesso do logout, reduzindo repeticao e
  deixando a rota administrativa mais previsivel em manutencao futura
- **Middleware de auth passa a reutilizar o mesmo contrato de erro** —
  `license-server/backend/src/auth-middleware.js` passa a consumir
  `buildAuthErrorResponse()`, evitando drift entre a rota de auth e a
  proteccao das rotas privadas quando devolvem `401` ou `500`
- **Helper de appliance entra no pack da F3** —
  `scripts/license-validation/export-appliance-evidence.sh` passa a recolher
  baseline local, stats JSON, fingerprint, `.lic` e hash local do appliance
  por SSH, reduzindo atrito operacional em `S07` a `S13` sem tocar no produto
- **Campanha F3 nasce com preflight estruturado** —
  `scripts/license-validation/init-f3-validation-campaign.sh` passa a criar
  tambem `10-preflight-deploy.txt`, `20-preflight-schema.txt`,
  `30-preflight-admin.txt`, `40-preflight-appliance.txt` e
  `50-preflight-inventory.md`, alinhando o helper ao runbook canónico da
  F3.10 antes de qualquer `S01`
- **Baseline do appliance sobe para o preflight da campanha** —
  `scripts/license-validation/export-appliance-evidence.sh` passa a aceitar
  `--update-root-preflight`, consolidando `50-appliance-cli.txt`,
  `60-appliance-license.json` e `70-local-hashes.txt` no
  `40-preflight-appliance.txt` do `run_id`
- **Deploy/admin do live ganham helper de preflight** —
  `scripts/license-validation/export-live-preflight.sh` passa a materializar
  `10-preflight-deploy.txt` e `30-preflight-admin.txt` com health publico,
  origin observado, probes de CORS e, quando houver credenciais, login e
  sessao administrativa via `curl`
- **Schema do live ganha helper de preflight** —
  `scripts/license-validation/export-schema-preflight.sh` passa a
  materializar `20-preflight-schema.txt` com identidade da base, presenca das
  tabelas canónicas, contagem minima e colunas administrativas via
  `docker compose exec` read-only
- **Preflight completo ganha orquestrador leve** —
  `scripts/license-validation/prepare-f3-preflight.sh` passa a inicializar a
  campanha e encadear os helpers de live, schema e appliance no mesmo
  `run_id`, reduzindo cola manual antes da abertura real da F3.11
- **DR-05 ganha helper de orquestracao para cenarios do appliance** —
  `scripts/license-validation/run-appliance-activation-scenario.sh` passa a
  encadear snapshot inicial/final do backend, passo local de `layer7d
  --activate` e baseline do appliance no mesmo `run_id`, reduzindo o atrito
  operacional para executar `S01`, `S02` e `S07` no pfSense real
- **Upgrade do license-server antigo ganha compatibilidade conservadora de Bearer bridge** —
  `license-server/backend/src/session.js` passa a preferir
  `ADMIN_BEARER_JWT_SECRET`, mas aceita `JWT_SECRET` como fallback de
  compatibilidade para deploys antigos; `docker-compose.yml` passa a expor
  ambos ao container da API e `.env.example` documenta a transicao esperada

### Changed — F3.8 gate de fechamento e relatorio final de campanha

- **Gate canónico da F3.8** —
  `docs/01-architecture/f3-gate-fechamento-validacao.md` passa a fixar o
  gate oficial de fechamento da F3, a matriz objectiva de `PASS` / `FAIL` /
  `INCONCLUSIVE` / `BLOCKED` por cenario e a classificacao explicita de
  pendencias bloqueantes vs nao bloqueantes
- **Relatorio final unico da campanha** —
  `docs/tests/templates/f3-validation-campaign-report.md` passa a servir
  como artefacto final canónico da execucao real da F3, com resumo
  executivo, ambiente, veredito por cenario, riscos remanescentes e decisao
  explicita `F3 pode fechar` / `F3 nao pode fechar`
- **Helper shell opcional e barato** —
  `scripts/license-validation/init-f3-validation-campaign.sh` passa a
  materializar a directoria de campanha por `run_id`, o manifest inicial, os
  directórios dos cenarios e o template do relatorio, sem tocar produto,
  daemon, runtime, schema ou contrato externo

### Changed — F3.7 pack operacional da validacao manual

- **Pack canónico da F3.7** —
  `docs/01-architecture/f3-pack-operacional-validacao.md` passa a
  operacionalizar a matriz da F3.6 com directoria por `run_id`, nomes fixos
  para outputs, classificacao uniforme `PASS` / `FAIL` / `INCONCLUSIVE` /
  `BLOCKED` e politica conservadora de recolha/retencao de evidencias
- **Helper shell barato fora do produto** —
  `scripts/license-validation/export-license-evidence.sh` passa a exportar
  snapshot da licenca, `activations_log` e `admin_audit_log` de forma
  reproduzivel, sem mudar endpoints, schema, `.lic` ou daemon
- **Template minimo por cenario** —
  `docs/tests/templates/f3-scenario-evidence.md` passa a servir como molde
  para registo operacional por cenario, reduzindo ambiguidade sem criar suite
  nova nem automacao pesada

### Changed — F3.6 validacao manual controlada e evidencias

- **Matriz canónica da F3.6** —
  `docs/01-architecture/f3-validacao-manual-evidencias.md` passa a registar
  de forma factual o que ja esta robusto em codigo, o que so pode ser provado
  em backend, o que exige appliance/relogio/fingerprint real e o que continua
  impossivel comprovar sem mudar o modelo actual
- **Politica oficial de "validacao suficiente"** — roadmap, backlog,
  checklist, manual de licencas e docs de testes passam a exigir cenarios
  obrigatorios, evidencias minimas e outputs reais antes de tratar a F3 como
  substancialmente validada
- **Fecho honesto sem mudar codigo** — a F3.6 nao adiciona feature nova nem
  mexe em `.lic`, daemon ou fingerprint; ela transforma os pendentes de
  appliance/lab em matriz operacional explicita, incluindo grace, revogacao
  com `.lic` antigo, coexistencia de artefactos e drift real de fingerprint

### Changed — F3.5 emissao, reemissao e rastreabilidade do artefacto

- **Trilha canónica do `.lic` na F3.5** —
  `docs/01-architecture/f3-emissao-reemissao-rastreabilidade.md` passa a
  registar de forma factual onde o artefacto e emitido, como a activacao
  publica difere do download administrativo, qual o risco de coexistencia de
  multiplos artefactos validos e o que continua impossivel resolver sem
  mudar formato, daemon ou revogacao offline
- **Emissao publica auditavel sem mudar o contrato** — `POST /api/activate`
  continua a devolver `{ data, sig }`, mas passa a deixar rasto adicional do
  artefacto emitido com `flow`, `emission_kind`, contexto da licenca e hashes
  baratos do payload/assinatura/envelope
- **Download administrativo com contexto do artefacto** — o evento
  `license_downloaded` passa a registar metadados suficientes para
  investigacao futura, sem schema novo, sem versionamento obrigatorio e sem
  mudar o formato do `.lic`

### Changed — F3.4 mutacao administrativa, reemissao e guardrails

- **Superficie administrativa canónica da F3.4** —
  `docs/01-architecture/f3-mutacao-admin-reemissao-guardrails.md` passa a
  registar de forma factual quais campos de licenca sao mutaveis via CRUD
  normal, quais mutacoes continuam seguras antes/depois do bind e onde a
  reemissao administrativa se torna perigosa por coexistir com `.lic` antigo
  ainda valido offline
- **Transferencia silenciosa de licenca bindada bloqueada** — o backend passa
  a negar com `409` a mudanca de `customer_id` em licenca ja
  activada/bindada, reduzindo o risco de mover ownership comercial sem trilha
  dedicada de rebind/transferencia
- **Auditoria minima de update reforcada** — `license_updated` passa a
  registar os campos alterados e flags de bind/activacao, melhorando
  previsibilidade operacional sem criar workflow novo nem mudar o formato do
  `.lic`

### Changed — F3.3 expiracao, revogacao, grace e validade offline

- **Semantica canónica da F3.3** —
  `docs/01-architecture/f3-expiracao-revogacao-grace.md` passa a registar de
  forma factual a diferenca entre estado persistido e estado efectivo, o papel
  exacto do grace local, o limite real da revogacao actual e as condicoes em
  que um `.lic` antigo continua valido offline
- **Risco de rebind explicitado** — a trilha documental passa a declarar de
  forma objectiva que um eventual rebind administrativo e perigoso nesta fase,
  porque o `.lic` antigo pode continuar operativo offline no hardware antigo
  ate `expiry + grace`
- **Estado efectivo centralizado no backend** — o backend passa a usar um
  helper minimo comum para derivar `active`, `expired` e `revoked` em
  `activate`, `licenses`, `customers` e `dashboard`, reduzindo ambiguidade
  sem mudar schema, formato `.lic` ou algoritmo de fingerprint

### Changed — F3.2 fingerprint, binding e cenarios reais de appliance

- **Matriz canónica de fingerprint/binding** —
  `docs/01-architecture/f3-fingerprint-e-binding.md` passa a registar a
  formula real do fingerprint observada no daemon, as dependencias de
  `kern.hostuuid` e da primeira MAC Ethernet nao-loopback, os riscos de falso
  bloqueio em reinstall/NIC/VM/restore/migracao e a politica conservadora da
  fase para primeira activacao, reactivacao legitima, reactivacao suspeita e
  mudanca que exige accao administrativa
- **Compatibilidade preservada** — a F3.2 nao muda a formula do fingerprint,
  nao abre tolerancia ampla, nao quebra `.lic` existente e nao altera o
  contrato publico de `POST /api/activate`
- **Normalizacao defensiva do bind persistido** — o backend passa a
  canonicalizar `hardware_id` legacy por `trim + lowercase` antes de comparar
  e assinar o `.lic`, reduzindo falso bloqueio por drift de formato sem
  alterar o fingerprint real

### Changed — F3.1 abertura formal da robustez de licenciamento/activacao

- **Contrato canónico da F3 aberto** — `docs/01-architecture/f3-arquitetura-licenciamento-ativacao.md`
  passa a registar o estado real observado no backend e no daemon, os
  estados/transicoes do licenciamento e a diferenca entre expiracao online e
  grace local
- **Compatibilidade preservada** — `POST /api/activate` continua a responder
  `{"data","sig"}` e a usar os mesmos codigos `400` / `404` / `409`, sem
  mudar o formato `.lic` nem o algoritmo de fingerprint
- **Idempotencia defensiva na activacao** — a reactivacao do mesmo hardware
  deixa de mutar a licenca sem necessidade, o `.lic` passa a ser assinado a
  partir do `hardware_id` efectivamente persistido, e o `UPDATE` do bind fica
  reforcado pela propria condicao de `hardware_id`
- **Trilha documental alinhada** — `CORTEX`, roadmap, backlog, checklist,
  manual de licencas e matriz de testes passam a tratar a F3 como aberta e a
  reservar a F3.2 para grace/offline/fingerprint em appliance

### Changed — F2.5 segredos, bootstrap, backup/restore e runbooks do license server

- **Segredos e ownership minimo materializados** — o stack passa a declarar
  oficialmente a custodia de `POSTGRES_PASSWORD`, `ED25519_PRIVATE_KEY` e
  `ADMIN_BOOTSTRAP_PASSWORD`, com suporte a `ED25519_PRIVATE_KEY_FILE` no
  backend e runbook canónico para uso/rotacao operacional minima
- **Bootstrap administrativo endurecido** — `bootstrap-admin.js` passa a ser o
  fluxo oficial para `status`, `init` e `reset-password`, com auditoria em
  banco e revogacao de sessoes no reset; `seed.js` fica apenas como wrapper
  de compatibilidade
- **Backup/restore minimo executavel** — o repositório passa a incluir
  `backup-postgres.sh` e `restore-postgres.sh`, e a operacao oficial do banco
  deixa de depender apenas de memoria oral
- **F2 encerrada documentalmente** — arquitetura, roadmap, backlog, manuais e
  runbooks passam a tratar a F2 como concluida e a apontar a F3 como proxima
  fase elegivel

### Changed — F2.4 integridade transacional e validacao do CRUD do license server

- **Validacao forte por rota** — `activate`, `customers` e `licenses` passam a
  operar com schema fechado para payload e query, rejeicao explicita de
  campos inesperados, IDs/paginacao invalidos e `JSON` malformado com `400`
- **CRUD administrativo coerente** — mutacoes e downloads passam a distinguir
  payload invalido (`400`), recurso inexistente (`404`) e conflito logico
  (`409`) sem vazar detalhe interno do banco
- **Atomicidade minima materializada** — activacao passa a usar
  `SELECT ... FOR UPDATE` com bind/timestamps/log de sucesso na mesma
  transacao, e create/update/revoke/archive administrativos passam a commitar
  junto com a auditoria em banco
- **Delete seguro no painel** — clientes e licencas deixam de sofrer delete
  fisico no fluxo administrativo normal e passam a usar arquivo logico com
  `archived_at` / `archived_by_admin_id`, ocultando historico das listagens
  sem o destruir

### Changed — F2.3 protecao da superficie administrativa do license server

- **CORS same-origin oficial** — o backend deixa de aplicar `cors()` aberto
  e passa a aceitar apenas o origin administrativo oficial em producao,
  falhando fechado para requests de browser fora da allowlist
- **Login endurecido contra abuso** — `POST /api/auth/login` passa a operar
  com limiter dedicado por IP e por `email + IP`, lockout temporario por
  falhas repetidas e respostas `401`/`429` genericas sem enumeracao de
  credenciais
- **Auditoria minima persistida** — auth/sessao e mutacoes administrativas
  passam a gerar rasto minimo em `admin_audit_log`, enquanto os guardas de
  brute force/lockout passam a viver em `admin_login_guards`

### Changed — F2.2 autenticacao e sessao administrativa do license server

- **Sessao stateful oficial** — o painel administrativo deixa de depender de
  JWT em `localStorage` e passa a operar com sessao stateful em
  `admin_sessions`, cookie `HttpOnly + Secure + SameSite=Strict`,
  expiracao ociosa/absoluta, renovacao controlada e logout com invalidacao
  real no backend
- **Contrato frontend/backend alinhado** — a SPA passa a fazer bootstrap por
  `GET /api/auth/session`, chamadas autenticadas same-origin por cookie e
  tratamento consistente de sessao invalida/expirada sem bearer manual
- **Documentacao operacional** — runbook, manuais e arquitetura passam a
  tratar `https://license.systemup.inf.br` como canal oficial tambem para
  login administrativo, deixando CORS/rate limit/brute force explicitamente
  para a F2.3

### Changed — F2.1 publicacao segura do license server

- **Canal publico oficial** — `https://license.systemup.inf.br` em `443/TCP`
  passa a ser o unico caminho normativo para painel administrativo e
  activacao online; o origin `8445` deixa de ser tratado como endpoint
  publico
- **Origin privado por defeito** — `docker-compose.yml` passa a prender
  `8445` ao loopback do host por defeito, mantendo override apenas para rede
  privada controlada com ACL/firewall explicitos
- **Borda e documentacao operacional** — `nginx.conf` interno passa a
  rejeitar hosts inesperados e a publicar headers basicos de seguranca, e o
  runbook/manual de licencas passam a exigir edge proxy com certificado
  valido, redirect `HTTP -> HTTPS` e troubleshooting controlado do origin

### Changed — F1.1 contrato oficial de distribuicao

- **Canal oficial de instalacao** — `install.sh` e `uninstall.sh` passam a ser
  consumidos por URLs versionadas de GitHub Releases, retirando `main` mutavel
  da trilha normativa
- **Contrato operacional de release** — o conjunto minimo vigente da F1.1
  fica alinhado em `.pkg`, `.pkg.sha256`, `install.sh` e `uninstall.sh`
  versionados; manifesto e assinatura continuam reservados para a F1.2
- **Documentacao canónica e operacional** — manuais, runbooks, roadmap e
  arquitectura passam a tratar `.txz` apenas como legado historico

### Changed — F1.2 manifesto, checksum e assinatura de release

- **Trust chain de release** — builder passa a preparar stage dir sem assinar;
  signer passa a assinar o manifesto fora do builder; publicacao passa a
  aceitar apenas stage dir ja assinado
- **Manifesto oficial** — `release-manifest.v1.txt` passa a listar metadados
  de origem, papeis builder/signer e hashes SHA256 dos assets oficiais
- **Assinatura oficial** — `release-manifest.v1.txt.sig` passa a usar
  Ed25519 com OpenSSL (`pkeyutl -sign -rawin`) e a public key correspondente
  passa a integrar o conjunto oficial da release

### Changed — F1.3 origem confiavel, mirror/cache e last-known-good de blacklists

- **Origem oficial de blacklists** — o pacote deixa de tratar UT1 directo
  como origem de auto-update e passa a consumir apenas
  `layer7-blacklists-manifest.v1.txt` assinado em HTTPS por canal oficial
  Layer7/Systemup
- **Mirror/cache controlado** — GitHub Releases entra como mirror controlado
  da mesma snapshot assinada, enquanto o appliance passa a guardar cache local
  por `snapshot_id` em `/usr/local/etc/layer7/blacklists/.cache/`
- **Last-known-good materializada** — a ultima snapshot validada passa a ser
  preservada em `/usr/local/etc/layer7/blacklists/.last-known-good/` com
  estado activo rastreavel em `.state/active-snapshot.state` e restauro
  explicito via `update-blacklists.sh --restore-lkg`

### Changed — F1.4 matriz de fallback e degradacao segura

- **Install/update fail-closed** — o `install.sh` versionado passa a validar
  `release-manifest.v1.txt`, assinatura destacada e checksum do `.pkg` antes
  do `pkg add`; release suspeita deixa de ser instalada
- **Signer carimba o trust anchor do instalador** — `sign-release.sh` passa a
  embutir a public key oficial e o fingerprint esperado no `install.sh`
  staged, mantendo a validacao ancorada fora do builder
- **Blacklists com estado degradado explicito** — `update-blacklists.sh`
  passa a escrever `.state/fallback.state` com `healthy`, `degraded` e
  `fail-closed`, sempre preservando apenas material previamente validado

## [1.8.3] — 2026-04-01

### Changed — Bloqueio de QUIC (UDP 443) por interface seleccionável

- **Nova funcionalidade**: o bloqueio de QUIC deixa de ser um checkbox global e passa a ser uma **lista de interfaces seleccionáveis** em `Layer7 → Configurações Gerais`
- Cada interface pode ser activada/desactivada independentemente para bloqueio QUIC
- Regras PF geradas com `on <iface>` por cada interface seleccionada, mantendo `to !<localsubnets>`
- **Retrocompatibilidade**: instalações com `block_quic: true` no JSON (formato antigo) continuam a funcionar com regra global até o utilizador gravar pela nova GUI
- Novo campo no schema de config: `"block_quic_interfaces": ["em0", "em1.46"]`
- **PORTVERSION** bumped para 1.8.3

## [1.8.2] — 2026-04-01

### Fixed — Regras de bloqueio afectavam tráfego interno (impressoras, bancos locais)

- **Arquitectura corrigida**: Layer7 passa a bloquear **apenas tráfego com destino externo à rede local**. Tráfego entre hosts da LAN não é afectado.
- **`layer7_pf_default_rules_text()`** (`layer7.inc`): regras anti-DoT/DoQ (porta 853 TCP/UDP) e block:src (`<layer7_block>`) agora incluem `to !<localsubnets>` em inet e inet6
- **`layer7_generate_rules()`** (`layer7.inc`): regra anti-QUIC (UDP 443) agora inclui `to !<localsubnets>` em inet e inet6
- **`write_rules()`** (`layer7-pfctl`): sincronizado com as mesmas correcções
- **`pf.conf.sample`**: sincronizado com as mesmas correcções
- `<localsubnets>` é o alias nativo do pfSense que contém todas as sub-redes directamente conectadas (LAN, VLANs, etc.)
- **Impacto**: impressoras locais, serviços bancários em rede corporativa e qualquer serviço interno que use UDP 443 (QUIC) voltam a funcionar normalmente
- **PORTVERSION** bumped para 1.8.2

## [1.8.0] — 2026-04-01

### Fixed — `label` em regras `rdr` causa syntax error no FreeBSD 15

- **`layer7_generate_rdr_rules_snippet()`**: o keyword `label "..."` nas regras `rdr` causa "syntax error" no pfctl do FreeBSD 15 quando carregado num anchor via `pfctl -a anchor -N -f`. Removido `label` das regras geradas
- Regras agora no formato válido: `rdr on <iface> inet proto {udp|tcp} from <cidr> to !127.0.0.1 port 53 -> 127.0.0.1`
- Ambas as regras (UDP + TCP port 53) carregam em `natrules/layer7_nat`
- **PORTVERSION** bumped para 1.8.0

## [1.7.9] — 2026-04-01

### Fixed — Sintaxe `rdr pass` inválida em pfSense 2.8 / FreeBSD 15

- **`layer7_generate_rdr_rules_snippet()`**: as regras `rdr` eram geradas com o keyword `pass` (`rdr pass on <iface> ...`), que causa "syntax error" no pfctl do FreeBSD 15 (pfSense 2.8). Apenas `rdr on <iface> ...` (sem `pass`) é válido. O pfctl normaliza o output para `rdr pass on ...` mas a sintaxe de INPUT deve ser `rdr on`
- Correcção: removido `pass` das strings geradas em `layer7_generate_rdr_rules_snippet()`
- Resultado: ambas as regras (UDP port 53 e TCP port 53) carregam correctamente no anchor `natrules/layer7_nat`
- **PORTVERSION** bumped para 1.7.9

## [1.7.8] — 2026-04-01

### Fixed — Regras `rdr` (force_dns) agora injectadas via pfctl directo

#### Bug Crítico — pfSense CE não processa `nat_rules_needed` do XML do package

- **Root cause**: o tag `<nat_rules_needed>layer7_generate_nat_rules</nat_rules_needed>` em `layer7.xml` nunca é processado por pfSense CE. O `pkg-utils.inc` do pfSense só processa `filter_rules_needed` (guardado como `filter_rule_function`) — não existe equivalente para NAT. As regras `rdr` de DNS forçado geradas por `layer7_generate_rdr_rules_snippet()` nunca chegavam ao PF
- **Tag XML errado**: `<custom_php_resync_command>` não existe no pfSense CE — o correcto é `<custom_php_resync_config_command>` com valor PHP executável via `eval()` (ex: `layer7_resync();`); por isso `layer7_resync()` nunca era chamado automaticamente via `sync_package()`
- **Solução**: nova função `layer7_inject_nat_to_anchor()` que injeta as regras `rdr` directamente no sub-anchor `natrules/layer7_nat` via `pfctl -a natrules/layer7_nat -N -f <tmp>`. pfSense CE usa `pfctl -f` sem `-F flush` → sub-anchor persiste entre reloads
- **Integração**: chamada em `layer7_generate_rules()` (chamada em todo reload PF via `filter_rule_function`) e em `layer7_resync()` (chamada no save de config)
- **Tag XML**: corrigido para `<custom_php_resync_config_command>layer7_resync();</custom_php_resync_config_command>`
- **PORTVERSION** bumped para 1.7.8

## [1.7.7] — 2026-04-01

### Fixed — Regras rdr (force_dns) nunca geradas em interfaces VLAN

#### Bug Crítico — Regex não aceitava interfaces VLAN com ponto (ex: `em1.46`)

- **Root cause**: `layer7_generate_rdr_rules_snippet()` em `layer7.inc` tentava obter o device real via `get_real_interface($ifid)`. Quando o layer7 é configurado com uma interface VLAN cujo ID já é o device name (ex: `"em1.46"`), o pfSense retorna `NULL` porque `em1.46` não é um friendly name (é o device). O fallback regex `/^[a-z][a-z0-9]+$/i` **não aceita pontos** → interface ignorada → `$real_ifaces` vazio → função retorna `""` → **zero regras `rdr` geradas**, mesmo com `force_dns: true` na blacklist
- **Correcção**: regex actualizado para `/^[a-z][a-z0-9]*(\.[0-9]+)?$/i`
  - Aceita: `lan`, `wan`, `em0`, `em1`, `em1.46`, `igb0.100`, `vtnet0`, `vtnet0.200`, `lagg0.10`
  - Rejeita: strings inválidas como `../../etc`, `; rm -rf`, etc. (segurança mantida)
- **Ficheiro**: `package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc`, linha 108
- **PORTVERSION** bumped para 1.7.7

## [1.7.6] — 2026-03-31

### Fixed — Monitor ao vivo acumulativo (comportamento tipo Squid)

- **`layer7_events.php`**: monitor substituía o conteúdo inteiro a cada poll (a cada 2s); quando as últimas N linhas do log já não continham a IP filtrada (empurrada por novos eventos de outros dispositivos), o monitor mostrava "Sem eventos recentes" e o histórico desaparecia
- **Nova lógica JS — buffer acumulativo**: o monitor mantém um buffer de até 500 linhas em memória; a cada poll detecta quais linhas são novas (usando sobreposição com a última linha vista) e **apenas acrescenta**; nunca apaga o histórico existente
- **Botão "Limpar"**: reset manual do buffer sem sair da página
- **Contador de linhas**: mostra quantas linhas estão acumuladas no buffer
- **Servidor**: aumentado tail de 100→300 linhas e retorno de 40→60 linhas por poll para melhor cobertura histórica
- **PORTVERSION** bumped para 1.7.6

## [1.7.5] — 2026-03-31

### Fixed — Botão "Aplicar" nos Perfis Rápidos não funcionava

- **`layer7_policies.php`**: `json_encode($prof_id)` e `json_encode($prof_name)` produzem strings com aspas duplas (`"youtube"`) que eram inseridas directamente no atributo `onclick="..."` sem escaping HTML; o browser terminava o atributo na primeira `"`, truncando o handler para `l7showProfileModal(` (JavaScript inválido); o clique não fazia nada
- **Correcção**: envolver em `htmlspecialchars(..., ENT_QUOTES)` → as `"` tornam-se `&quot;` no HTML (válido em atributos) e o browser converte de volta para `"` ao executar o JS; `onclick` resultante: `l7showProfileModal(&quot;youtube&quot;, &quot;YouTube&quot;)` → executa `l7showProfileModal("youtube", "YouTube")` correctamente

- **PORTVERSION** bumped para 1.7.5

## [1.7.4] — 2026-03-31

### Fixed — Segunda revisão: 3 bugs adicionais

#### Bug Médio — `generate_rdr_rules()` código morto em `layer7-pfctl`
- Após o fix v1.7.3, a função `generate_rdr_rules()` (40 linhas de PHP inline) permanecia no script mas nunca era chamada — `write_rules()` foi alterado e não a invoca; removida para evitar confusão e facilitar manutenção

#### Bug Menor — `s_bl_lookups` não incrementado no SNI check
- **`main.c`**: `l7_blacklist_lookup()` era chamado no SNI check (`layer7_on_classified_flow()`) sem incrementar `s_bl_lookups`; o stat `bl_lookups` no JSON ficava subestimado (representava apenas lookups DNS); corrigido com `s_bl_lookups++` antes do lookup SNI

#### Bug Menor — `force_dns` activo sem `src_cidrs` não gerava aviso
- **`layer7_blacklists.php`**: utilizador podia activar "Forçar DNS local" sem definir CIDRs de origem; o backend ignorava silenciosamente a regra (sem gerar nenhuma regra `rdr`); adicionada validação que bloqueia o formulário com mensagem de erro clara

- **PORTVERSION** bumped para 1.7.4

## [1.7.3] — 2026-03-31

### Fixed — Correcção de 3 bugs nas melhorias de Bloqueio Total

#### Bug Crítico — `rdr` rules no filter anchor
- **`layer7.inc`**: `layer7_pf_default_rules_text()` deixou de concatenar o snippet `rdr` com as filter rules — no FreeBSD PF, `rdr` só é válido na secção NAT; tê-las no filter anchor causava rejeição do ruleset inteiro (`rdr rule not allowed in filter ruleset`)
- **`layer7-pfctl`**: `write_rules()` deixou de incluir as regras `rdr` no ficheiro `/usr/local/etc/layer7/pf.conf` (filter rules); as `rdr` continuam a ser injectadas correctamente via o hook `nat_rules_needed` → `layer7_generate_nat_rules()` registado no `layer7.xml`

#### Bug Médio — Regex de fallback de interface incorrecto
- **`layer7-pfctl`** e **`layer7.inc`**: regex `^[a-z][a-z0-9]+[0-9]$` alterado para `^[a-z][a-z0-9]+$/i`; o regex anterior não cobria interfaces como `lan`, `wan`, `opt2` (último caractere não dígito); o novo cobre todos os nomes de interface válidos do pfSense

#### Bug Menor — `s_bl_sni_hits` incrementado por pfctl-add em vez de por host-match
- **`main.c`**: `s_bl_hits++` e `s_bl_sni_hits++` movidos para antes do loop de regras no SNI check, tornando o comportamento consistente com o DNS callback (onde os contadores são incrementados uma vez por domínio encontrado na blacklist, não por pfctl-add)

- **PORTVERSION** bumped para 1.7.3

## [1.7.2] — 2026-03-31

### Added — Bloqueio Total: 3 melhorias para fechar brechas de bypass DNS

#### Melhoria A — DNS Forçado via PF `rdr`
- **`bl_config.h` / `bl_config.c`**: campo `int force_dns` adicionado à `struct l7_bl_rule`; `parse_one_rule()` lê `"force_dns"` do JSON; retrocompatível (ausência = `false`)
- **`layer7-pfctl`**: nova função `generate_rdr_rules()` que lê `config.json` e `layer7.json`; `write_rules()` passa a incluir regras `rdr pass on <iface> inet proto udp/tcp from <cidr> to !127.0.0.1 port 53 -> 127.0.0.1 label "layer7:force_dns"` para cada regra com `force_dns: true` e respectivos src_cidrs
- **`layer7.inc`**: nova função `layer7_generate_rdr_rules_snippet()` que gera regras rdr dinamicamente (acesso a `get_real_interface()`); `layer7_pf_default_rules_text()` passa a ser dinâmica incluindo o snippet rdr; nova função `layer7_generate_nat_rules()` registada como `nat_rules_needed` no `layer7.xml`
- **`layer7.xml`**: adicionado `<nat_rules_needed>layer7_generate_nat_rules</nat_rules_needed>` para injectar regras rdr na secção NAT do pfSense
- **`layer7_blacklists.php`**: nova checkbox "Forçar DNS local para estes CIDRs" no formulário de regras (activada por defeito em novas regras); gravada como `"force_dns": true` no `config.json`

#### Melhoria B — Bloqueio por TLS SNI via nDPI
- **`main.c`**: include `<arpa/inet.h>` adicionado; variáveis `s_bl_dns_hits` e `s_bl_sni_hits`; nova função `ip_in_cidr(src_ip, cidr_str)` com parse manual + CIDR matching (sem dependências); nova função `bl_rule_matches_src(rule, src_ip)` para verificar se origem está no src_cidrs da regra (sem restrição = aplica a todos); check SNI blacklist em `layer7_on_classified_flow()` — após decisão de política manual — adiciona dst_ip à tabela `layer7_bld_N` correcta quando o SNI/host casa com a blacklist

#### Melhoria C — Estatísticas DNS vs SNI
- **`main.c`**: `s_bl_dns_hits` incrementado no DNS callback; `s_bl_sni_hits` incrementado no SNI callback; ambos expostos em `write_stats_json()` como `"bl_dns_hits"` e `"bl_sni_hits"`

- **PORTVERSION** bumped para 1.7.2

## [1.6.7] — 2026-03-31

### Fixed

- **SIGSEGV no daemon ao gerar stats com blacklists activas** — `blacklist.c`: `l7_blacklist_get_cat_hits()` fazia cast inválido `(const char **)bl->cats`; `bl->cats` é `char[64][48]` (array 2D), não `char**`; os primeiros 8 bytes de cada categoria eram interpretados como ponteiro → crash ao imprimir nomes de categorias via SIGUSR1
- **Bug estava oculto** desde v1.1.0 porque `s_blacklist` era sempre NULL antes de v1.6.6; a correção do parser (v1.6.6) activou o código e expôs o crash
- **Correcção**: API substituída por `l7_blacklist_get_cat_name(bl, idx)` e `l7_blacklist_get_cat_hit_count(bl, idx)` — acesso seguro por índice
- **PORTVERSION** bumped para 1.6.7

## [1.6.6] — 2026-03-31

### Fixed

- **BUG CRÍTICO: blacklists nunca carregavam no daemon** — `bl_config.c`: `match_key()` avançava o ponteiro além do `"` ao falhar comparação de chave JSON; todas as chaves após `"enabled"` (incluindo `"rules"`) eram ignoradas; `n_rules=0` → `bl_enabled: false` → tabelas PF `layer7_bld_N` sempre vazias → bloqueio por categorias web sem efeito
- **Correcção**: `match_key()` salva o ponteiro antes de avançar e restaura-o em qualquer falha de validação
- **PORTVERSION** bumped para 1.6.6

## [1.6.5] — 2026-03-31

### Fixed

- **CI smoke layer7d** — workflow Linux falhava com `Makefile:20: *** missing separator`
- **Causa raiz**: job usava `make` (GNU make no Ubuntu), mas `src/layer7d/Makefile` usa sintaxe BSD make (`.if`)
- **scripts/package/smoke-layer7d.sh** agora detecta `bmake` e prioriza BSD make; fallback para `make`
- **.github/workflows/smoke-layer7d.yml** agora instala `bmake` no runner Ubuntu
- **PORTVERSION** bumped para 1.6.5

## [1.6.4] — 2026-03-31

### Fixed

- **Auto-start após reboot** — daemon layer7d não reiniciava automaticamente após reboot do pfSense
- **rc.d**: `REQUIRE: LOGIN` alterado para `REQUIRE: DAEMON NETWORKING` (facility `LOGIN` não existe no pfSense)
- **layer7_resync()**: nova função `layer7_ensure_daemon_running()` inicia o daemon se o serviço estiver enabled mas o processo não estiver a correr (hook chamado pelo pfSense em cada boot e reload do filtro)
- **PORTVERSION** bumped para 1.6.4

## [1.6.3] — 2026-03-26

### Fixed

- **Scroll fix** — adicionadas âncoras HTML (`id` + `action`) a todos os formulários POST em todas as páginas do pacote; ao submeter um form a página volta à secção relevante em vez de saltar para o topo
- Páginas afectadas: Settings, Blacklists, Policies, Diagnostics, Reports, Status, Groups, Exceptions, Test
- **PORTVERSION** bumped para 1.6.3

## [1.6.2] — 2026-03-26

### Fixed

- **Categorias custom editáveis** — restaurado botão de editar para categorias personalizadas criadas pelo utilizador; campo ID fica readonly ao editar
- **PORTVERSION** bumped para 1.6.2

## [1.6.1] — 2026-03-26

### Changed

- **Blacklists: removida opção de editar categorias** — mantém apenas criar novas e apagar; datalist de categorias UT1 removida para evitar confusão
- **Backup completo** — export/import passa a incluir configuração de blacklists (regras, whitelist, categorias personalizadas, definições de update); permite restaurar TODAS as configurações do pacote após formatação
- **PORTVERSION** bumped para 1.6.1

## [1.6.0] — 2026-03-25

### Changed

- **Navegação consolidada: 11 → 7 abas** — removidas Grupos, Excepções, Categorias e Teste da barra principal; acessíveis via links rápidos em Políticas
- **Dashboard simplificado** — removidos bloco "Validação da configuração" e contadores PF duplicados (pertencem a Diagnósticos)
- **Definições reorganizadas em 3 blocos** — "Configuração do serviço" (com logging avançado colapsável), "Relatórios" (presets com custom toggle), "Sistema" (licença + backup + update compactos)
- **Eventos limpos** — removidos blocos duplicados "Eventos de enforcement", "Classificações nDPI" e "Dicas"; mantidos Monitor ao vivo + Filtro + Todos os logs
- **Relatórios limpos** — alertas colapsados em 1 único; removido resumo executivo em prosa (cards já mostram os dados)
- **Diagnósticos limpos** — secções PF verbose convertidas em acordeões colapsáveis; removida lista "Comandos úteis"
- **Blacklists limpos** — removidos textos introdutórios verbosos; formulário "Nova categoria" agora colapsável
- **Políticas limpos** — texto introdutório reduzido; zona "Remover política" agora colapsável; barra de links rápidos para Grupos/Excepções/Categorias/Teste
- **i18n padronizado** — "Events" → "Eventos", "Diagnostics" → "Diagnósticos"; novas chaves EN adicionadas
- **PORTVERSION** bumped para 1.6.0

## [1.5.3] — 2026-03-26

### Fixed

- **Tabelas PF persistentes após reload** — novo hook `custom_php_resync_command` materializa todas as tabelas PF obrigatórias (`layer7_block`, `layer7_block_dst`, `layer7_tagged`, `layer7_bld_N`) adicionando e removendo um IP dummy (127.0.0.254) após cada `filter_configure()`
- **Causa raiz**: no FreeBSD 15 / pfSense 2.8.1, tabelas declaradas com `table <name> persist` no ruleset existem internamente no PF mas não são listadas por `pfctl -s Tables` nem acessíveis por `pfctl -t <name> -T show` até terem pelo menos uma entrada. Isso causava falsos negativos recorrentes na página de Diagnósticos
- **Nova função `layer7_resync()`** chamada automaticamente pelo pfSense após cada reload do filtro

### Changed

- **PORTVERSION** bumped para 1.5.3

## [1.5.2] — 2026-03-26

### Fixed

- **Cursor de ingestão na limpeza de relatórios** — ao limpar todos os dados, o cursor agora é posicionado no fim do ficheiro de log actual (`/var/log/layer7d.log`) em vez de ser apagado, evitando que a função de ingestão incremental reimporte todo o histórico na mesma carga da página

### Changed

- **PORTVERSION** bumped para 1.5.2

## [1.5.1] — 2026-03-26

### Added

- **Limpar todos os dados de relatórios** — novo botão na página de Relatórios permite apagar toda a base SQLite (eventos, identity_map, daily_kpi), o histórico JSONL e o cursor de ingestão, resolvendo travamentos em servidores com milhares de páginas acumuladas
- **Confirmação obrigatória** — acção protegida com `confirm()` informando que é irreversível

### Changed

- **PORTVERSION** bumped para 1.5.1
- Traduções EN actualizadas para novas strings

## [1.5.0] — 2026-03-26

### Security

- **FIX CRITICO: blacklists no arranque** — daemon passa a carregar blacklists UT1/custom no startup (antes exigia SIGHUP manual para activar bloqueio)
- **FIX CRITICO: injecção em layer7_activate** — chaves com aspas, backslash ou control chars são rejeitadas antes de interpolar em JSON/shell
- **FIX CRITICO: password removida do seed.js** — admin password do license server agora é lida da variável `ADMIN_PASSWORD`
- **FIX ALTO: validação de octetos CIDR** — `layer7_cidr_valid()` passa a rejeitar octetos > 255 em endereços de rede
- **FIX ALTO: sanitização PF** — `except_ips` e `src_cidrs` de blacklist validados com `layer7_ipv4_valid()`/`layer7_cidr_valid()` antes de interpolar em regras PF
- **FIX ALTO: XSS/JS em confirm()** — 7 instâncias de `confirm('<?= l7_t(...) ?>')` e 3 labels Chart.js + 1 profileModal corrigidas para usar `json_encode()`

### Fixed

- **NULL safety no daemon** — `json_escape_fprint()`, `json_escape_print()` e `dst_cache_add()` protegidos contra ponteiro NULL
- **Swap de blacklists seguro** — reload falhado preserva blacklist anterior funcional em vez de destruí-la
- **Warning de categoria vazia** — log restaurado quando ambos ficheiros (UT1 base + custom overlay) falham para uma categoria
- **Whitelist normalizada** — domínios da whitelist de blacklists passam por `layer7_bl_domains_normalize()` (validação + dedup)
- **source_url validada** — apenas esquemas HTTP/HTTPS aceites na URL de download de blacklists
- **Simulação por priority** — `layer7_test.php` ordena políticas por `priority` desc (consistente com o daemon)
- **Lock atómico no update-blacklists.sh** — `mkdir` atómico substitui padrão TOCTOU `test -f` + `echo $$`
- **Numeração install.sh** — passos corrigidos de [1/5]-[3/5] para [1/6]-[3/6]
- **Help text excepções** — "max. 8" corrigido para "max. 16" (alinhado com o parser real)
- **rename() stats** — verificação de retorno com log de erro

### Changed

- **PORTVERSION** bumped para 1.5.0

### Documentation

- CORTEX.md, MANUAL-INSTALL.md e CHANGELOG actualizado para v1.5.0
- Traduções EN actualizadas para novas strings

## [1.4.17] — 2026-03-26

### Added

- **Categorias customizadas no mesmo fluxo UT1** — pagina `Blacklists` passa a permitir criar categorias locais com lista propria de dominios, sem nova tela
- **Extensao de categorias UT1 existentes** — operador pode usar o mesmo ID da categoria da Capitole e adicionar dominios proprios que nao existem no feed original
- **Mescla operacional de categorias** — seletor de categorias das regras passa a mostrar lista combinada (UT1 + custom), mantendo o modelo per-rule existente

### Changed

- **Carga de blacklists no daemon** — cada categoria ativa passa a carregar `domains` da UT1 e o overlay local em `_custom/<categoria>.domains`, suportando enriquecimento por cliente
- **Persistencia de configuracao** — `config.json` passa a guardar `category_custom`, com sincronizacao automatica para ficheiros de overlay antes do reload
- **PORTVERSION** bumped para 1.4.17

### Documentation

- **Documentacao de cliente atualizada** — `MANUAL-INSTALL.md`, `README.md` e `CORTEX.md` alinhados ao novo fluxo de categorias customizadas/UT1 e a versao 1.4.17

## [1.4.16] — 2026-03-26

### Fixed

- **PF helper sem falso negativo de tabela** — `layer7-pfctl` passa a considerar tabela pronta quando já está referenciada no filtro activo (`pfctl -sr`), mesmo sem materialização imediata em `pfctl -s Tables`
- **Diagnostics alinhado ao estado real do PF** — verificação de “tabelas obrigatórias” usa estado combinado (existência em `pfctl -s Tables` OU referência activa em regra), eliminando falso erro recorrente em `layer7_block/layer7_tagged/layer7_bld_*`
- **Mensagens operacionais mais claras** — tabelas sem entradas mas referenciadas deixam de aparecer como “não existe” e passam a estado de observação, reduzindo troubleshooting redundante
- **PORTVERSION** bumped para 1.4.16

### Documentation

- **Runbook de troubleshooting consolidado** — `pf-enforcement.md` e `MANUAL-INSTALL.md` passam a documentar explicitamente o critério combinado de tabela pronta (existente ou referenciada), com leitura operacional para evitar retrabalho de diagnóstico

## [1.4.15] — 2026-03-26

### Fixed

- **Enforcement/licença consistente** — `enforce_cfg` passa a ser recomputado por helper único após parse e validação de licença (startup + recheck), eliminando estado preso em monitor com licença válida
- **Parser resiliente à ordem do JSON** — `enabled`, `mode` e `log_level` deixam de depender da posição relativa a `policies`, alinhando daemon e GUI
- **Robustez PF com visibilidade real** — `layer7-pfctl` e `rc.d` deixam de mascarar falhas críticas de criação/validação de tabelas e registram estado degradado de forma explícita
- **Diagnostics sem falso verde** — “Enforcement real” agora exige regras `layer7:block:*` ativas + tabelas obrigatórias presentes, distinguindo cenário apenas anti-bypass
- **Conformidade operacional/documental** — `MANUAL-INSTALL` alinhado ao `rc.d` real (`service layer7d reload`), com redução de exposição operacional e flush dinâmico de tabelas `layer7_bld_*`
- **Consistência GUI/i18n** — endpoint AJAX alinhado ao bootstrap padrão (`guiconfig.inc`) e dicionário EN sem duplicidade de chave
- **PORTVERSION** bumped para 1.4.15

## [1.4.14] — 2026-03-25

### Fixed

- **Autorreparo no daemon** — falhas de `pfctl -T add` por tabela ausente agora disparam recuperação controlada (`layer7-pfctl ensure` + fallback opcional por `rules.debug`) com retry único, cobrindo caminhos DNS e nDPI
- **Reload consistente (SIGHUP)** — após recarregar a configuração, o daemon valida tabelas base (`layer7_block`, `layer7_block_dst`) e tenta recuperação automática quando necessário
- **Helper PF sem falso sucesso** — `layer7-pfctl ensure` passa a validar tabelas obrigatórias no estado final e retorna erro real se ainda estiverem ausentes
- **Diagnostics fiel ao estado real** — novo estado de “enforcement real” exige simultaneamente regra Layer7 ativa (`pfctl -sr`) e tabelas PF obrigatórias presentes
- **PORTVERSION** bumped para 1.4.14

## [1.4.13] — 2026-03-25

### Changed

- **GUI administrativa expandida** — as páginas `Politicas`, `Grupos`, `Events`, `Diagnostics` e `Blacklist` passam a usar blocos visuais separados com cabeçalhos fortes, seguindo o padrão administrativo do pfSense
- **Leitura operacional mais clara** — filtros, listagens, formulários e áreas de acção ficam segmentados por contexto, reduzindo o efeito de painel único nas telas maiores
- **PT/EN preservado** — a reorganização visual reutiliza as legendas existentes e mantém o selector bilingue sem alteração funcional
- **Sem mudanças funcionais** — handlers POST, persistência, licenciamento, relatórios, upgrade e enforcement continuam com o mesmo comportamento
- **PORTVERSION** bumped para 1.4.13

## [1.4.12] — 2026-03-25

### Changed

- **GUI Settings em blocos** — a página `Definicoes` passa a seguir uma organização por blocos com cabeçalhos fortes, aproximando-se do padrão visual do pfSense
- **Separação visual por área** — definições gerais, logging/debug, captura/interfaces, licença, backup/restore, relatórios e actualização agora ficam em blocos distintos
- **Bilingue preservado** — novas legendas visuais traduzidas para inglês, mantendo o selector PT/EN funcional
- **Sem mudanças funcionais** — handlers POST, persistência, licenciamento, relatórios e upgrade permanecem com o mesmo comportamento
- **PORTVERSION** bumped para 1.4.12

## [1.4.11] — 2026-03-25

### Changed

- **Controle de versão** — nova release patch para manter o histórico após a entrega funcional da v1.4.10
- **Documentação operacional** — `MANUAL-INSTALL.md`, `README.md`, `release-body.md` e scripts de release sincronizados com a nova versão pública
- **Links públicos** — comandos, URLs do `.pkg` e exemplos com `--version` passam a apontar para `v1.4.11`
- **PORTVERSION** bumped para 1.4.11

## [1.4.10] — 2026-03-25

### Changed

- **Relatorios estilo NGFW** — histórico executivo e log detalhado passam a ser tratados separadamente no appliance
- **Log detalhado opcional** — operador pode activar/desactivar a ingestão detalhada em SQLite
- **Escopo por interface** — log detalhado pode ser limitado a uma ou mais interfaces
- **Retenção separada** — histórico executivo e log detalhado passam a ter janelas próprias de retenção
- **Paginação compacta** — a tela de eventos detalhados deixa de renderizar milhares de páginas no HTML
- **Contexto de interface nos logs** — eventos `dns_query`, `dns_block` e `enforce_*` passam a incluir `iface=` para melhorar pesquisa e filtragem
- **Settings mais seguro** — guardar apenas a seção de relatórios preserva correctamente as demais definições globais
- **PORTVERSION** bumped para 1.4.10

## [1.4.9] — 2026-03-25

### Changed

- **Canal público de distribuição** — `install.sh`, `uninstall.sh`, documentação operacional e release notes passam a usar o repositório público `pablomichelin/Layer7`
- **Actualização via GUI** — a página Definições passa a consultar a última release e o `.pkg` no novo repositório público, preservando o fluxo actual de upgrade
- **PORTVERSION** bumped para 1.4.9

## [1.4.2] — 2026-03-24

### Fix criação robusta de tabelas PF

- **Causa raiz:** `pfctl -t TABLE -T add` não cria tabelas no FreeBSD se não
  estiverem declaradas no ruleset carregado; `ensure_table()` falhava
  silenciosamente; `filter_configure()` pode ser assíncrono no pfSense CE
- **layer7-pfctl ensure:** `write_rules()` agora executa antes de `ensure_table`;
  nova verificação `tables_missing()` com fallback `pfctl -f /tmp/rules.debug`
- **Reparar tabelas PF:** handler na página Diagnósticos agora chama ensure
  primeiro, depois `filter_configure()`, espera 800ms, verifica tabelas, e se
  ainda em falta força `pfctl -f /tmp/rules.debug`; resultado reflecte estado real
- **layer7_bl_apply():** mesma lógica robusta (ensure→filter_configure→verify→force)
- **install.sh:** usa `layer7-pfctl ensure` + `pfctl -f rules.debug` em vez de
  tentativas individuais `pfctl -T add` que falhavam

## [1.0.0] — 2026-03-23

### Release V1 Comercial

Primeira versao estavel e completa do Layer7 para pfSense CE. Inclui todas as
funcionalidades planeadas para a V1 comercial.

### Funcionalidades incluidas na V1

- **Classificacao L7 em tempo real** — ~350 apps/protocolos via nDPI
- **Politicas granulares** — por interface, IP/CIDR, app nDPI, categoria, hostname, grupo de dispositivos
- **Enforcement PF** — bloqueio por destino (DNS + nDPI) com tabela `layer7_block_dst`, bloqueio por origem com `layer7_block`
- **Anti-bypass DNS** — bloqueio DoT/DoQ (porta 853), deteccao nDPI DoH, NXDOMAIN via Unbound para dominios de bypass
- **Perfis de servico** — 15 perfis built-in (YouTube, Facebook, Instagram, TikTok, WhatsApp, Twitter/X, LinkedIn, Netflix, Spotify, Twitch, Redes Sociais, Streaming, Jogos, VPN/Proxy, AI Tools) com criacao de politica por 1 clique
- **Pagina de categorias nDPI** — todas as apps organizadas por categoria com pesquisa
- **Dashboard operacional** — contadores em tempo real, top 10 apps bloqueadas, top 10 clientes
- **Agendamento por horario** — politicas com dias da semana e faixa horaria (suporte overnight)
- **Grupos de dispositivos** — grupos nomeados (ex: "Funcionarios") com CIDRs/IPs, reutilizaveis em politicas
- **Bloqueio QUIC selectivo** — toggle para forcar fallback TCP/TLS e melhorar visibilidade SNI
- **Teste de politica** — simulacao completa na GUI com veredicto visual
- **Backup e restore** — export/import de configuracao completa em JSON
- **Licenciamento Ed25519** — fingerprint de hardware, verificacao offline, grace period 14 dias, CLI de activacao
- **Actualizacao via GUI** — verificacao e instalacao directa do GitHub Releases
- **GUI completa** — 10 paginas (Estado, Definicoes, Politicas, Grupos, Categorias, Teste, Excecoes, Events, Diagnostics)
- **Fleet management** — scripts para 50+ firewalls (update, protos sync)
- **Logs locais + syslog remoto** — `/var/log/layer7d.log` + UDP syslog configuravel
- **EULA proprietaria** — licenca comercial com proteccao por chave

### Changed
- **PORTVERSION** bumped para 1.0.0
- **install.sh** — versao default actualizada para 1.0.0
- **CORTEX.md** — actualizado para v1.0
- **README.md** — actualizado com funcionalidades v1.0
- **blocking-master-plan.md** — todas as fases marcadas como concluidas
- Removido `docs/09-blocking/phase-a-option1-package-rules-plan.md` (obsoleto)
- Removido `docs/09-blocking/plano-v1-comercial.md` (plano concluido)
- **Branding Systemup** — propriedade Systemup Solucao em Tecnologia (www.systemup.inf.br) em todas as 9 paginas GUI (rodape com hyperlink), LICENSE/EULA, README, Makefile, info.xml e install.sh
- **Desenvolvedor principal** — Pablo Michelin registado em LICENSE, README e GitHub Release

## [0.9.0] — 2026-03-23

### Added
- **Fingerprint de hardware** — funcao `layer7_hw_fingerprint()` em `license.c` que gera ID unico a partir de `kern.hostuuid` + MAC da primeira interface via SHA256.
- **Verificacao de licenca Ed25519** — ficheiro `/usr/local/etc/layer7.lic` com payload JSON assinado com Ed25519. Chave publica embutida no binario. Verificacao via OpenSSL EVP API (`libcrypto`).
- **Proteccao por licenca no daemon** — sem licenca valida o daemon opera apenas em modo monitor-only (sem enforce/block). Verificacao no arranque e periodica (cada 1h). Grace period de 14 dias apos expiracao.
- **CLI `--fingerprint`** — mostra o hardware ID da maquina actual para facilitar geracao de licencas.
- **CLI `--activate KEY [URL]`** — tenta activacao online enviando fingerprint + chave ao servidor de licencas. Guarda `.lic` recebido. Pronto para uso quando servidor estiver disponivel.
- **Seccao de licenca na GUI** — pagina Definicoes mostra estado da licenca (valida/expirada/grace/dev mode), hardware ID, cliente, data de expiracao e dias restantes.
- **Estado da licenca no stats JSON** — campos `license_valid`, `license_expired`, `license_grace`, `license_dev_mode`, `license_days_left`, `license_customer`, `license_expiry`, `license_hardware_id` exportados em `/tmp/layer7-stats.json`.
- **Script de geracao de licencas** — `scripts/license/generate-license.py` com comandos `keygen` (gera par Ed25519), `sign` (cria `.lic` assinado) e `c-pubkey` (mostra chave publica como array C).
- **EULA proprietaria** — licenca BSD-2-Clause substituida por End-User License Agreement. Software requer chave de licenca para funcionalidade completa.

## [0.8.0] — 2026-03-23

### Added
- **Pagina de teste de politica** — nova pagina "Teste" na GUI onde o utilizador introduz um dominio/IP de destino, IP de origem, app nDPI e categoria nDPI, e ve qual politica casaria, qual a accao e o motivo. Simula excepcoes, groups, schedule e matching de hosts/subdominios em PHP.
- **Resolucao DNS na pagina de teste** — dominios sao resolvidos automaticamente e os IPs resolvidos mostrados no resultado.
- **Veredicto visual** — resultado do teste com indicador colorido (block=vermelho, allow=verde, monitor=azul) e tabela detalhada de cada politica avaliada.
- **Backup e restore de configuracao** — botoes "Exportar configuracao" e "Importar configuracao" na pagina Definicoes. Export gera ficheiro JSON com definicoes, politicas, excepcoes e grupos. Import valida o JSON, substitui a configuracao e envia SIGHUP + filter_configure.
- **GUI passa a ter 10 paginas** — Estado, Definicoes, Politicas, Grupos, Categorias, Teste, Excecoes, Events, Diagnostics.

## [0.7.0] — 2026-03-23

### Added
- **Grupos de dispositivos** — nova seccao `groups[]` no JSON config para criar grupos nomeados de dispositivos (ex.: "Funcionarios", "Visitantes") com CIDRs e/ou IPs individuais.
- **Referencia a grupos nas politicas** — campo `match.groups` nas politicas permite seleccionar grupos em vez de digitar CIDRs manualmente. O daemon expande os grupos para CIDRs/IPs no parse.
- **Nova pagina GUI "Grupos"** — CRUD completo para criar, editar e remover grupos de dispositivos. Proteccao contra remocao de grupo em uso por politica.
- **Dropdown de grupos nos formularios de politicas** — seleccao de grupos disponivel nos formularios de adicionar, editar e perfis rapidos.
- **Visualizacao de grupos na politica** — "Ver listas" e resumo de correspondencia mostram os grupos associados.
- **Bloqueio QUIC selectivo** — toggle "Bloquear QUIC (UDP 443)" na pagina Definicoes. Quando activo, adiciona regra PF `block drop quick proto udp to port 443` que forca apps a usar HTTPS (TCP 443) onde o SNI e visivel ao nDPI. Melhora eficacia do bloqueio por DNS/SNI. Regra PF injectada dinamicamente via `layer7_generate_rules()`.
- **GUI passa a ter 9 paginas** — Estado, Definicoes, Politicas, Grupos, Categorias, Excecoes, Events, Diagnostics.

## [0.3.2] — 2026-03-23

### Added
- **Actualizacao via GUI** — botao "Verificar actualizacao" na pagina Definicoes que consulta o GitHub Releases e permite instalar a versao mais recente com um clique. O daemon e parado/reiniciado automaticamente e todas as configuracoes sao preservadas.

## [0.3.1] — 2026-03-23

### Added
- **Anti-bypass DNS multi-camada** — estrategia para impedir que dispositivos contornem bloqueio via DNS cifrado (DoH/DoT/DoQ) ou iCloud Private Relay.
- **Regras PF anti-DoT/DoQ** — bloqueio automatico de TCP/UDP porta 853 no snippet do pacote, cortando DNS over TLS e DNS over QUIC.
- **Politica nDPI anti-bypass** — politica built-in `anti-bypass-dns` no sample config que bloqueia fluxos classificados como `DoH_DoT` e `iCloudPrivateRelay` pelo nDPI.
- **Script Unbound anti-DoH** — `/usr/local/libexec/layer7-unbound-anti-doh` configura NXDOMAIN para dominios de bypass DNS conhecidos (Apple Private Relay, Firefox canary, resolvers DoH publicos). iOS desativa Private Relay automaticamente quando `mask.icloud.com` retorna NXDOMAIN.
- **Instalacao automatica** — `install.sh` agora executa o script anti-DoH automaticamente durante a instalacao.

## [0.3.0] — 2026-03-23

### Added
- **Bloqueio por destino (sites/apps)** — o daemon agora adiciona IPs de DESTINO a `layer7_block_dst` em vez de quarentenar o cliente. Sites/apps bloqueados ficam inacessiveis; o resto do trafego funciona normalmente.
- **Bloqueio DNS** — daemon observa respostas DNS e bloqueia automaticamente IPs de dominios que casam com politicas `block` (campo `Sites/hosts`).
- **Bloqueio nDPI por destino** — classificacoes nDPI com `action=block` adicionam o IP de destino do fluxo a `layer7_block_dst`.
- **Expiracao automatica** — cache com TTL (minimo 5 min) + sweep periodico para remover IPs expirados da tabela de destino.
- **Nova tabela PF** — `layer7_block_dst` com regras `block drop quick inet to <layer7_block_dst>` no snippet do pacote.
- **Diagnostics actualizado** — GUI mostra contadores e entradas da tabela `layer7_block_dst`.

## [0.2.7] — 2026-03-23

### Added
- **Enforcement PF integrado ao filtro pfSense** — o XML do pacote agora declara `<filter_rules_needed>layer7_generate_rules</filter_rules_needed>`, fazendo o pfSense CE incluir automaticamente as regras de bloqueio do Layer7 no ruleset ativo via `discover_pkg_rules()` durante cada `filter reload`.
- **Bloqueio operacional por origem** — IPs em `<layer7_block>` passam a ser bloqueados automaticamente sem necessidade de regra PF manual externa.

## Historico pre-release (consolidado na v1.0.0)

### Added
- **Plano mestre de bloqueio total** — nova trilha documental em `docs/09-blocking/blocking-master-plan.md`, cobrindo arquitetura, fases, riscos, testes e rollout para bloquear aplicações, sites, serviços e funções no pfSense CE.
- **Sites/hosts manuais nas políticas** — novo campo `match.hosts[]` na GUI e no daemon; regras agora podem casar por hostname/domínio observado nos eventos, com suporte a subdomínios.
- **Seleção em massa na GUI** — políticas e exceções passam a ter botões para selecionar tudo/limpar interfaces; listas de apps e categorias nDPI ganham seleção dos itens visíveis após o filtro.
- **Visualização das listas existentes** — políticas ganham ação `Ver listas` para inspeccionar todos os apps, categorias, sites, IPs e CIDRs já gravados sem entrar direto em edição.
- **Hostname e destino nos eventos** — `flow_decide` passa a incluir `dst=` e `host=`; o `host=` é inferido por correlação de respostas DNS observadas na captura, quando disponíveis.
- **Monitor ao vivo na GUI** — a aba `Events` agora possui um painel com auto-refresh dos ultimos eventos do `layer7d`, com suporte a pausa, refresh manual e reaproveitamento do filtro atual.
- **Log local do daemon** — `layer7d` agora grava eventos em `/var/log/layer7d.log`; GUI `Events` e `Diagnostics` passam a ler esse arquivo diretamente, eliminando dependência do syslog do pfSense para observabilidade.
- **Labels amigaveis de interface na GUI** — `layer7_get_pfsense_interfaces()` agora prioriza a descricao configurada em `config['interfaces'][ifid]['descr']`, com fallback seguro; Settings, Policies e Exceptions deixam de exibir `OPT1/OPT2/...` quando houver descricoes customizadas.
- **Empacotamento autocontido do nDPI** — o build do `layer7d` no port agora usa `/usr/local/lib/libndpi.a` e falha se a biblioteca estática não existir no builder, evitando pacote que peça `libndpi.so` adicional no pfSense.
- **Validação de release** — `scripts/release/update-ndpi.sh` agora aborta se o binário staged ainda depender de `libndpi.so` em runtime.
- **Guia Completo Layer7** (`docs/tutorial/guia-completo-layer7.md`) — tutorial com 18 secções: instalação, configuração, todos os menus da GUI, formato JSON, exemplos práticos de políticas, CLI do daemon, sinais, protocolos customizados, gestão de frota (fleet), troubleshooting e glossário.

- **Motor Multi-Interface (2026-03-18):**
  - GUI Settings: checkboxes dinâmicos de interfaces pfSense (substituiu campo CSV)
  - `layer7d --list-protos`: enumera todos os protocolos e categorias nDPI em JSON
  - GUI Policies: multi-select com pesquisa para apps e categorias nDPI (populados por `--list-protos`)
  - Políticas: campo `interfaces[]` para regras por interface (vazio = todas)
  - Políticas: campo `match.src_hosts[]` e `match.src_cidrs[]` para filtro granular por IP de origem
  - Exceções: suporte a múltiplos hosts (`hosts[]`) e CIDRs (`cidrs[]`) por exceção
  - Exceções: campo `interfaces[]` para limitar a interfaces específicas
  - Callback de captura `layer7_flow_cb` agora inclui nome da interface
  - `layer7_flow_decide` filtra por interface, IP de origem e CIDR
  - Compatibilidade retroactiva: campos antigos `host`/`cidr` continuam a funcionar
  - Helpers PHP: `layer7_ndpi_list()`, `layer7_get_pfsense_interfaces()`, `layer7_parse_ip_textarea()`, `layer7_parse_cidr_textarea()`

- **Enforce end-to-end validado (2026-03-23)** — pipeline nDPI → policy engine → pfctl comprovado em pfSense CE real:
  - `pf_add_ok=7`, zero falhas, 6 IPs adicionados à tabela `layer7_tagged`
  - Protocolos detectados: TuyaLP (IoT), SSDP (System), MDNS (Network)
  - Exceções respeitadas: IPs .195 e .129 não foram afetados
  - CLI `-e` validou: BitTorrent→block, HTTP→monitor, IP excecionado→allow
- **Daemon: logging diferenciado** — block/tag decisions logadas a `LOG_NOTICE` (sempre visíveis); allow/monitor a `LOG_DEBUG` (sem poluir logs)
- **Daemon: safeguard monitor mode** — `layer7_on_classified_flow` verifica modo global antes de chamar `pfctl`; em modo monitor, decisão logada mas nunca executada.
- **Scripts lab** — `sync-to-builder.py` (SFTP sync), `transfer-and-install.py` (builder→pfSense), scripts de teste enforce
- **Deploy lab via GitHub Releases** — `scripts/release/deployz.sh` (build + publish), `scripts/release/install-lab.sh.template` (instalação no pfSense com `fetch + sh`), `scripts/release/README.md`, `docs/04-package/deploy-github-lab.md`.
- **Rollback doc** — `docs/13-runbooks/rollback.md` (procedimento completo com limpeza manual).
- **Release notes template** — `docs/06-releases/release-notes-template.md`.
- **Checklist mestre alinhado** — `14-CHECKLIST-MESTRE.md` atualizado para refletir o estado real do projeto: fases 0, 3, 5, 7, 8 marcadas como completas.
- **Matriz de testes** — `docs/tests/test-matrix.md` com 58 testes em 10 categorias (47 OK, 11 pendentes no appliance).
- **Smoke test melhorado** — `smoke-layer7d.sh` com cenários adicionais: exception por host (whitelist IP), exception por CIDR.
- **Validação lab completa (2026-03-22)** — 57/58 testes OK no pfSense CE 2.8.1-dev (FreeBSD 15.0-CURRENT):
  - Instalação via GitHub Release (`fetch` + `pkg add -f`) OK
  - Daemon start/stop/SIGUSR1/SIGHUP OK
  - pfctl enforce: dry-run, real add, show, delete OK
  - Whitelist: exception host impede enforce OK
  - GUI: 6 páginas HTTP 200 OK
  - Rollback: `pkg delete` remove pacote, preserva config, dashboard OK
  - Reinstalação do `.pkg` do GitHub Release OK

- **Syslog remoto validado (2026-03-22)** — `nc -ul 5514` + daemon SIGUSR1, mensagens BSD syslog recebidas.
- **nDPI integrado (0.1.0-alpha1, 2026-03-22):**
  - Novo módulo `capture.c`/`capture.h`: pcap live capture + nDPI flow classification
  - Tabela de fluxos hash (65536 slots, linear probing, expiração 120s)
  - `main.c`: loop de captura integrado, `layer7_on_classified_flow` conectado ao nDPI
  - `config_parse.c/h`: parsing de `interfaces[]` do JSON
  - Makefile: auto-detect nDPI (`HAVE_NDPI`), compilação condicional, `NDPI=0` para CI
  - Port Makefile: PORTVERSION 0.1.0.a1, link com libndpi + libpcap
  - Validado no pfSense: `cap_pkts=360`, `cap_classified=8`, captura estável em `em0`
  - Suporte a custom protocols file (`/usr/local/etc/layer7-protos.txt`) para regras por host/porta/IP sem recompilar
- **Estratégia de atualização nDPI** — `docs/core/ndpi-update-strategy.md`: comparação com SquidGuard, fluxo de atualização, cadência recomendada, roadmap
- **Script update-ndpi.sh** — `scripts/release/update-ndpi.sh`: atualiza nDPI no builder e reconstrói pacote
- **Fleet update** — `scripts/release/fleet-update.sh`: distribui `.pkg` para N firewalls via SSH (compila 1x, instala em todos)
- **Fleet protos sync** — `scripts/release/fleet-protos-sync.sh`: sincroniza `protos.txt` para N firewalls + SIGHUP (sem recompilação)
- **Resolução automática de interfaces** — GUI Settings converte nomes pfSense (`lan`, `opt1`) para device real (`em0`, `igb1`) ao gravar JSON via `convert_friendly_interface_to_real_interface_name()`; exibição reversa ao carregar
- **Custom protos sample** — `layer7-protos.txt.sample` incluído no pacote com exemplos de regras por host/porta/IP/nBPF
- **Release notes V1** — `docs/06-releases/release-notes-v0.1.0.md` (draft)
- **GUI Diagnostics melhorado** — stats live (SIGUSR1 button), PF tables (layer7_block, layer7_tagged com contagem e entradas), custom protos status, interfaces configuradas, SIGHUP button, logs recentes do layer7d
- **GUI Events melhorado** — filtro de texto, seções separadas para eventos de enforcement e classificações nDPI, todos os logs do layer7d com filtro
- **GUI Status melhorado** — resumo operacional com modo (badge colorido), interfaces, políticas ativas/block count, estado do daemon
- **protos_file configurável** — campo `protos_file` no JSON config (`config_parse.c/h`), passado a `layer7_capture_open`, mostrado em `layer7d -t`
- **pkg-install melhorado** — copia `layer7-protos.txt.sample` para `layer7-protos.txt` se não existir
- **Port Makefile** — PORTVERSION bumped para 0.1.0, instalação de `layer7-protos.txt.sample`

### Changed
- **CORTEX.md** — nDPI integrado, Fase 10 em progresso, gates atualizados, estratégia de atualização nDPI documentada, fleet management.
- **README.md** — seção Distribuição com link para deploy lab via GitHub Releases.
- **14-CHECKLIST-MESTRE.md** — fases 6 e 9 fechadas com evidência de lab.
- **docs/tests/test-matrix.md** — 58/58 testes OK.

### Previously added
- **GUI save no appliance** - CSRF customizado removido de `Settings`, `Policies` e `Exceptions`; `pkg-install` passa a criar `layer7.json` a partir do sample e aplicar `www:wheel` + `0664`; save real em `Settings` validado no pfSense com persistencia em `/usr/local/etc/layer7.json`.
- **Guia Windows** — `docs/08-lab/guia-windows.md` (CI, WSL, lab); **`scripts/package/check-port-files.ps1`** (PowerShell, equivalente ao `.sh`); referência em `docs/08-lab/README.md` e `validacao-lab.md`.
- **Quick-start lab** — `docs/08-lab/quick-start-lab.md` (fluxo encadeado builder→pfSense→validação); referência em `docs/08-lab/README.md`.
- **main.c** — comentário TODO(Fase 13) no loop indicando ponto de integração nDPI→`layer7_on_classified_flow`.
- **BUILDER.md** — port pronto para `make package`; referências validacao-lab e quick-start.
- **CI** — job `check-windows` em `smoke-layer7d.yml` (PowerShell `check-port-files.ps1`).
- **docs/13-runbooks/README.md** — links para validacao-lab e quick-start-lab.
- **docs/README.md** — entrada `04-package` no índice.
- **Decisão documentada:** instalação no pfSense apenas quando o pacote estiver totalmente completo (`00-LEIA-ME-PRIMEIRO.md` regra 8, `CORTEX.md` decisões congeladas).
- **README** — estado e estrutura atualizados (daemon, pacote, GUI, CI; lab pendente).
- **`scripts/package/check-port-files.sh`** — valida **`pkg-plist`** contra **`files/`**; integrado no workflow CI + **`validacao-lab.md`** (§3, troubleshooting).
- **GitHub Actions** — [`.github/workflows/smoke-layer7d.yml`](../../.github/workflows/smoke-layer7d.yml) (Ubuntu + `smoke-layer7d.sh`); **`docs/tests/README.md`**; badge no **`README.md`**.
- **`smoke-layer7d.sh`** passa a compilar via **`src/layer7d/Makefile`** (`OUT`, **`VSTR_DIR`**); Makefile valida **`version.str`** e uma única linha **`$(CC)`** para dev + smoke.
- **`src/layer7d/Makefile`** — `make` / `make check` / `make clean` no builder (flags alinhadas ao port); **`.gitignore`** — binário `src/layer7d/layer7d`; **`builder-freebsd.md`** + **`layer7d/README.md`** — instruções.
- **Docs lab:** `lab-topology.md` — trilha pós-topologia (smoke, `validacao-lab`, snapshots, PoC); **`lab-inventory.template.md`** — campos de validação pacote; **`docs/08-lab/README.md`** — link **`validacao-lab`**. **Daemon README** — `layer7_on_classified_flow`, quatro `.c`, enforcement alinhado a `pf-enforcement.md`.
- **Smoke / lab:** `smoke-layer7d.sh` valida cenário **monitor** (sem add PF) e **enforce** (`grep dry-run pfctl`); **`validacao-lab.md` §6c** — procedimento **`layer7d -e`** / **`-n`** no appliance.
- **0.0.31:** **Settings** — editar **`interfaces[]`** (CSV validado, máx. 8); **`layer7_parse_interfaces_csv()`** em `layer7.inc`; **PORTVERSION 0.0.31**.
- **0.0.30:** **Settings** — bloco **Interfaces (só leitura)** (`interfaces[]` do JSON); nota nDPI; **PORTVERSION 0.0.30**.
- **0.0.29:** **`layer7_daemon_version()`** em `layer7.inc`; página **Estado** mostra `layer7d -V`; Diagnostics reutiliza o helper.
- **0.0.28:** **`layer7d -V`** e **`version.str`** (build port = PORTVERSION); **`layer7d -t`** imprime `layer7d_version`; syslog **`daemon_start version=…`** e SIGUSR1 com **`ver=`**; Diagnostics mostra `layer7d -V`; smoke com include temporário; **PORTVERSION 0.0.28**.
- **0.0.27:** Validação **syslog remoto**: host = IPv4 ou hostname seguro (`layer7_syslog_remote_host_valid` em `layer7.inc`); doc **`docs/04-package/gui-validation.md`**.
- **0.0.26:** **Exceptions — editar** na GUI (`?edit=N`): host **ou** CIDR, prioridade, ação, ativa; **id** só via JSON; redirect após gravar.
- **0.0.25:** **Policies — editar** na GUI (`?edit=N`): nome, prioridade, ação, apps/cat CSV, `tag_table`, ativa; **id** só via JSON; após gravar redireciona à lista.
- **0.0.24:** **Exceptions — remover** na GUI (dropdown + confirmação, CSRF, SIGHUP).
- **0.0.23:** **Policies — remover** na GUI (dropdown + confirmação, CSRF, SIGHUP); link **Events** na página **Settings**.
- **0.0.22:** GUI **Events** em `layer7.xml` (tab), **`pkg-plist`**, página `layer7_events.php` (já no repo); README do port.
- **0.0.21:** **`layer7_pf_enforce_decision(dec, ip, dry_run)`**; **`layer7d -e IP APP [CAT]`** (lab) e **`-n`** (dry sem pfctl); **`layer7_on_classified_flow`** para integração nDPI; smoke **`layer7-enforce-smoke.json`**; docs `pf-enforcement` + `layer7d/README`.
- **0.0.20:** **`debug_minutes`** (0–720): após SIGHUP/reload, daemon usa **LOG_DEBUG** durante N minutos; `effective_ll()`; campo em **Settings**; parser `config_parse`.
- **0.0.19:** **Syslog remoto:** `layer7d` duplica logs por UDP (RFC 3164) para `syslog_remote_host`:`syslog_remote_port`; parser JSON; **Settings** (checkbox + host + porta); `layer7d -t` mostra campos; `config-model` + `docs/14-logging` atualizados.
- **0.0.18:** Página GUI **Diagnostics** (`layer7_diagnostics.php`): estado do serviço (PID), comandos SIGHUP/SIGUSR1, onde ver logs, comandos úteis (service, sysrc); tab + links nas outras páginas.
- **0.0.17:** **docs/14-logging/README.md** — formato de logs (destino syslog, log_level, mensagens atuais, syslog remoto planeado, ligação a event-model).
- **0.0.16:** GUI **adicionar exceção** (`layer7_exceptions.php`): id, host (IPv4) ou CIDR, prioridade, ação, ativa; limite 16; helpers `layer7_ipv4_valid` / `layer7_cidr_valid` em `layer7.inc`.
- **0.0.15:** **`runtime_pf_add(table, ip)`** em `main.c` — chama `layer7_pf_exec_table_add`, incrementa `pf_add_ok`/`pf_add_fail`, loga falha; ponto de chamada único para o fluxo pós-nDPI (ainda não invocada).
- **0.0.14:** **Adicionar política** na GUI (`layer7_policies.php`): id, nome, prioridade, ação (monitor/allow/block/tag), apps/categorias nDPI (CSV), `tag_table` se tag; limites alinhados ao daemon (24 regras, etc.). Helpers em `layer7.inc`.
- **0.0.13:** GUI **`layer7_exceptions.php`** — lista `exceptions[]`, ativar/desativar, gravar JSON + SIGHUP; tab **Exceptions** em `layer7.xml`; `pkg-plist`; links nas outras páginas Layer7.
- **0.0.12:** `enforce.c` — **`layer7_pf_exec_table_add`** / **`layer7_pf_exec_table_delete`** (`fork`+`execv` `/sbin/pfctl`, sem shell); loop do daemon ainda não invoca (pendente nDPI). `layer7d -t` menciona `pf_exec`.
- **0.0.11:** `layer7d` — contadores **SIGUSR1** (`reload_ok`, `snapshot_fail`, `sighup`, `usr1`, `loop_ticks`, `have_parse`, `pf_add_ok`/`pf_add_fail` reservados); contagem de falhas ao falhar parse de policies/exceptions no reload; **aviso degraded** no arranque se ficheiro existe mas snapshot não carrega; **log periódico** (~1 h) `periodic_state` quando `enabled` ativo.
- Roadmap estendido: **Fases 13–22** (V2+) em `03-ROADMAP-E-FASES.md`; checklist em `14-CHECKLIST-MESTRE.md`; tabela Blocos 13–22 em `07-PLANO-DE-IMPLEMENTACAO-PASSO-A-PASSO.md`; ponte em `00-LEIA-ME-PRIMEIRO.md` e `CORTEX.md`.
- **0.0.10:** `enforce.c` — nomes de tabela PF, `pfctl -t … -T add <ip>`; parse **`tag_table`**; campo **`pf_table`** na decisão; daemon guarda policies/exceptions após reload; **SIGUSR1** → syslog (reloads, ticks, N políticas/exceções); **`layer7d -t`** mostra `pfctl_suggest` quando enforce+block/tag; doc `docs/05-daemon/pf-enforcement.md`.
- **0.0.9:** `exceptions[]` no motor — `host` (IPv4) e `cidr` `a.b.c.d/nn`; `match.ndpi_category[]` (AND com `ndpi_app`); API `layer7_flow_decide()`; `layer7d -t` lista exceções e dry-run com src/app/cat; sample JSON com exceções + política Web.
- **0.0.8:** `policy.c` / `policy.h` — parse de `policies[]` (id, enabled, action, priority, `match.ndpi_app`), ordenação (prioridade desc, id), decisão first-match, reason codes, `would_enforce` para block/tag em modo enforce; **`layer7d -t`** imprime políticas e dry-run (BitTorrent / HTTP / não classificado). Port Makefile e smoke compilam `policy.c` (`-I` para `src/common`).
- `scripts/package/README.md`; `smoke-layer7d.sh` verifica presença de `cc`; `validacao-lab.md` — localização do `.txz`, troubleshooting de build, notas serviço/`daemon_start`.
- **0.0.7:** `layer7_policies.php` — ativar/desativar políticas por linha; `layer7.inc` partilhado (load/save/CSRF); `layer7d` respeita `log_level` (L7_NOTE/L7_INFO/L7_DBG).
- **0.0.6:** `layer7_settings.php`, tabs Settings, CSRF, SIGHUP.
- **0.0.5:** `log_level` no parser; idle se `enabled=false`; `layer7_status.php` com `layer7d -t`.
- **0.0.4:** `config_parse.c` — `enabled`/`mode`; `layer7d -t`; SIGHUP; `smoke-layer7d.sh`.

### Added (anterior)
- Scaffold do port `package/pfSense-pkg-layer7/` (Makefile, plist, XML, PHP informativo, rc.d, sample JSON, hooks pkg) — **código no repo; lab não validado**.
- `src/layer7d/main.c` (daemon mínimo: syslog, stat em path de config, loop).
- `docs/04-package/package-skeleton.md`, `docs/04-package/validacao-lab.md`, `docs/05-daemon/README.md`.
- `package/pfSense-pkg-layer7/LICENSE` para build do port isolado.

### Changed
- **Roadmap e índice de documentação** — passam a apontar explicitamente para a trilha complementar de bloqueio total (`docs/09-blocking/`).
- **CORTEX** — passa a registrar explicitamente o estado real do enforcement atual e o próximo bloco recomendado: enforcement PF automático do pacote.
- Documentação alinhada: nada de build/install/GUI marcado como validado sem evidência de lab.
- Port compila `layer7d` em C (`PORTVERSION` conforme Makefile).

### Fixed (código)
- `rc.d/layer7d` usa `daemon(8)` para arranque em background.

## [0.0.1] - 2026-03-17

### Added
- Documentação-mestre na raiz (`00-`…`16-`, `AGENTS.md`, `CORTEX.md`) e primeiro push ao GitHub.
