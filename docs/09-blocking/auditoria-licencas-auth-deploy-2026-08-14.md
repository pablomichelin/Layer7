# Auditoria técnica — licenças, auth, daemon, package e deploy HEAD↔`.244`

**Data:** 2026-08-14  
**Tipo:** auditoria **somente leitura** (código + docs + evidência já no repo)  
**Empresa:** Systemup Solução em Tecnologia  
**Âmbito:** check-in assinado / nonce / revogação; validação de licenças; daemon C; API Node; autenticação / autorização; entrada no confiável; concorrência / erros; instalação / upgrade / rollback pfSense; composição Docker; divergência operacional HEAD ↔ live `.244`  
**HEAD de referência:** `566375a` (docs); código API `30.13` = `5754bfa`  
**Pacote vivo:** `1.9.63` no `.254` (`mode=monitor`, MITM OFF)  
**API live `.244`:** overlay cirúrgico `30.13` sobre stack de conteúdo `30.11` (evidência `20260814T142739Z-30.13-api-244`)  
**Código / hosts / `.env` / builder:** **não** alterados nesta auditoria. Segredos **não** reproduzidos.

---

## Veredicto executivo

| Critério | Estado |
|----------|--------|
| Bypass de assinatura Ed25519 / replay de envelope / escalada de features via check-in | **Sem achado** (evidência na secção *Áreas sem achado*) |
| Dual-mode 30.13 no HEAD e no overlay live | **Alinhado** (smoke + GA5.9 campo PASS) |
| Deploy **integral** do HEAD sobre `/opt/layer7-license` | **NO-GO** — **P0-1** |
| Serving autenticado `30.11` versionado no git | **NO-GO** — ausente do HEAD; presente no live e no worktree sujo |
| Superfície admin (TOTP / rate-limit / bootstrap) | **P0-2 + P1** — não é bloqueio de deploy de conteúdo; é hardening da API |
| Revogação autenticada quando a linha **não** está arquivada | **OK** (GA5.9 campo PASS) |
| Revogação após replace/arquivo | **P1-1 FEITO no git** (`2026-08-14`) — check-in devolve 409 envelope `revoked`/`expired`; **não** deployado |
| Worktree local `30.11` | **Preservar** — não commitar neste bloco |

**P0-1 é bloqueio operacional explícito:** é **proibido** rsync/rebuild/playbook integral do HEAD contra o `.244` enquanto o serving `30.11` live não estiver reconciliado **e** versionado no git (allowlist, sem snapshot). Runbook: [`../13-runbooks/bloqueio-deploy-integral-head-30.11.md`](../13-runbooks/bloqueio-deploy-integral-head-30.11.md).

**P0-2, P1-1 e P1-3 FEITOS no git** (`2026-08-14`; sem deploy). P2-5
(reset/lock no 2FA) ficou absorvido no P1-3. Próximo código com GO: **P1-2**
(XFF / rate-limit IP). Não mistura 30.11, não toca `.244`.

---

## Limites desta auditoria

- Nenhum host (`.244` / `.254` / `.253` / builder) foi contactado nesta rodada.
- O gap HEAD↔live `30.11` assenta em evidência já canónica (`20260814T142739Z`, `20260812T002500Z`, `20260812T011217Z`) e no working tree local **não commitado**. Não se reconfirmou cksum live em 14-08 após o overlay `30.13`.
- Testes C de `test_checkin_signed.c` **não** recompilaram neste Mac (OpenSSL x86_64 vs arm64). Evidência canónica permanece a do builder FreeBSD / campanhas 30.13.
- Testes JS locais de check-in/policy/crud já tinham PASS `31/31` na evidência do deploy `30.13`.
- Material de chave, `.env`, tokens e licenças **não** foi lido nem impresso.
- Achados são do **código HEAD** (e, para P0-1, do gap documentado HEAD↔live). O worktree sujo `30.11` **não** foi tratado como HEAD.

---

## P0 — Bloqueios

### P0-1 — Deploy integral do HEAD apaga o serving `30.11` live

| Campo | Valor |
|-------|--------|
| **Estado** | **BLOQUEIO OPERACIONAL ACTIVO** |
| **Evidência** | `docs/tests/evidence/20260814T142739Z-30.13-api-244/README.md:35-44`; `git show HEAD:license-server/backend/src/index.js` (termina rotas em `/api/users`, sem `content`); `git ls-files` **não** lista `content-auth.js` nem `routes/content.js`; HEAD `docker-compose.yml` sem `CONTENT_BLACKLISTS_DIR`; HEAD `nginx/nginx.conf` sem `downloads.systemup.inf.br` / `location /layer7/` |
| **Cenário** | `rsync` / `docker compose` rebuild / playbook genérico do clone HEAD sobre `/opt/layer7-license`. Também: `rsync --delete`; substituir compose/nginx live pelo HEAD; `git add -A` do worktree (tarball ~31 MB + risco de `.env`). |
| **Impacto** | Primary autenticado de blacklists cai. Clientes `1.9.54+` com token falham o GET current; o espelho GitHub já está `asset_count=0` (último recheck `20260812T013145Z`). Actualização de conteúdo parte. `rsync --delete` pode ainda apagar snapshot e `.env` (crash-loop histórico). |
| **Correcção mínima** | 1) Inventário allowlist (docs + cksum, sem rsync). 2) Commit **só** serving `30.11` (index/content-auth/content.js + volume compose + bloco nginx `downloads` + `.gitignore` do snapshot). **Excluir** `.env`, tarball/manifest/sig/pem, SPA live, bind `0.0.0.0`, vhost `.253`. 3) Gate de deploy: nunca sync integral; overlay por paths. |
| **Testes** | Cksum allowlist vs evidência `20260812T002500Z` / imagem live; `npm test` content-auth + check-in; smoke **só com GO**: health license+downloads; GET sem token = 401; com token = 200 no host que já tem o ficheiro; check-in com e sem nonce. **Não** repetir GA5.9 sem GO. |
| **Até lá** | Proibido deploy integral do HEAD. Overlay `30.13` (já feito) permanece o único padrão autorizado. Worktree sujo **não** se commita neste bloco. |

Facetas do mesmo bloqueio (não são P0 independentes):

- HEAD sem volume `CONTENT_BLACKLISTS_DIR` → contentor sem snapshot.
- HEAD nginx sem vhost `downloads.systemup.inf.br`.
- HEAD `.gitignore` **ainda não** ignora `content/blacklists/ut1/current/*` (só o worktree).
- Bind compose HEAD = `127.0.0.1:8445`; live restaurou `0.0.0.0:8445` para o edge `.253` (**P1-9**).

### P0-2 — HMAC do desafio TOTP com fallback hardcoded

**FEITO no git** (`2026-08-14`) para o fallback/arranque. Residual: challenge
single-use + bind à tentativa de login. **Não** deployado (P0-1).

| Campo | Valor |
|-------|--------|
| **Evidência** | `license-server/backend/src/routes/auth.js` (antes `:55-59`); agora `admin-bearer-secret.js` + `totp.js` |
| **Cenário** | `ADMIN_BEARER_JWT_SECRET` e `JWT_SECRET` vazios (default do compose: `${…:-}`). Conta com TOTP ligado. Atacante forja o challenge HMAC com o literal de desenvolvimento no código e `admin_id` sequencial (tipicamente `1`) e faz `POST /api/auth/login/totp`. **Não passa por `/login` nem pela password.** |
| **Impacto** | 2FA vira 1FA (só TOTP). Encadeado com P1-2 (brute TOTP sem rate-limit efectivo) → takeover admin sem credenciais. O Bearer JWT é fail-closed sem segredo; o TOTP **não**. |
| **Correcção mínima** | Remover o literal. Exigir segredo forte no boot (`process.exit(1)` se vazio). Challenge single-use + bind à tentativa de login. Segredo TOTP distinto do Bearer, ou o mesmo **sem** default. |
| **Implementado** | Literal removido. `getTotpHmacSecret()` reutiliza `ADMIN_BEARER_JWT_SECRET`/`JWT_SECRET` sem default. Arranque produção/`NODE_ENV` vazio recusa segredos vazios. `NODE_ENV=development`/`test` explícitos podem arrancar sem esses valores; create/parse do challenge recusam segredo vazio. Sem variável nova. Residual: single-use + bind. |
| **Testes** | Boot sem env → recusa start. `getTotpHmacSecret()` nunca devolve default. Challenge forjado com o literal antigo → rejeitado. Suite backend `148/148` PASS. |
| **Nota** | Remediação live implica restart da API e confirmação dos segredos no `.env` — **proibido** neste bloco (P0-1). |

---

## P1 — Alto

### P1-1 — Check-in trata licença arquivada como 404/`fail` (não `revoked`)

**FEITO no git** (`2026-08-14`). `loadLicenseForCheckIn` vê linhas arquivadas
`revoked`/`expired` e a rota devolve 409 + envelope v2. Chave inexistente
continua 404. **Não** deployado (P0-1).

| Campo | Valor |
|-------|--------|
| **Evidência** | `license-server/backend/src/routes/check-in.js:128-139`; cliente `src/layer7d/license.c:1504-1531`; replace arquiva em `licenses.js:756-764`; DELETE arquiva pós-revoga `:854-860` |
| **Cenário** | Appliance com `.lic` + chave em `/var/db/layer7-checkin.json` + `check_in_enabled=true`. Portal: revoke e depois replace/arquivo. `layer7d --check-in` → SELECT exige `archived_at IS NULL` → 404. Envelope assinado leva `status: "fail"`. Cliente só invalida `revoked`/`expired` → `L7_CHECKIN_NETWORK`, `.lic` intacto. |
| **Impacto** | O caminho oficial pós-revogação **desliga** o corte 30.13. O `.lic` antigo vive até `max_offline_hours` (check-in ON) ou `expiry+grace` (check-in OFF). Reabre o sintoma S09 (ADR-0021). |
| **Correcção mínima** | No check-in, ver também linhas arquivadas com `status` `revoked`/`expired` e devolver envelope v2 com esse status (409), não 404. |
| **Testes** | JS: `check-in-lookup.test.js` + C11 em `check-in-signed.test.js` (arquivada revoked + nonce → 409/`revoked`/sig; inexistente → 404). C: C7 vivo inalterado; C11 cobre o caso arquivado no lookup+envelope. |
| **Fora deste bloco** | Deploy `.244`; commit 30.11; P0-2; daemon C. |

### P1-2 — `X-Forwarded-For` cliente-controlado anula rate-limit / lock IP

| Campo | Valor |
|-------|--------|
| **Evidência** | `session.js:91-98`; `admin-surface.js:239-254`, `305-317`, `353-357`; `nginx/nginx.conf:6-9`, `:45`, `:86`; `index.js:31`; `auth.js:171` (`/login/totp` só tem `loginIpLimiter`) |
| **Cenário** | Pedido ao origin com `X-Forwarded-For` falso. Nginx faz `$proxy_add_x_forwarded_for`. `getClientIp` usa o **primeiro** hop e ignora `X-Real-IP`. Em `/login/totp` não há lock de conta. |
| **Impacto** | Rate-limit IP e lock IP tornam-se irrelevantes. Spray online do TOTP (`window=1` ⇒ 3 códigos / 30s). Encadeado com P0-2: takeover sem password. Lock de **conta** no password ainda funciona. |
| **Correcção mínima** | IP de confiança = `req.ip` (`trust proxy: 1`) ou só `X-Real-IP`. Nginx: `proxy_set_header X-Forwarded-For $remote_addr;`. Em `/login/totp`: `registerLoginFailure` + lock por `admin_id`. |
| **Testes** | XFF spoof vs IP real → mesma chave de limiter. 11.º TOTP no mesmo IP → 429. 6.ª falha TOTP na mesma conta → lock. |

### P1-3 — `/login/totp` não verifica `is_active`

**FEITO no git** (`2026-08-14`). `/api/auth/login/totp` recusa
`is_active === false`; password OK com TOTP ligado **não** faz
`resetLoginProtection`; falha TOTP incrementa as guardas existentes
(`account` por email + `ip`) e o lock activo devolve o mesmo `429` do
`/login`. Respostas de falha do segundo factor são `401` genéricas
(`Credenciais invalidas`) — sem enumerar conta/desafio/código. Contrato de
sucesso inalterado. **Não** deployado (P0-1). **Fora:** P1-2 XFF/rate-limit;
residual P0-2 single-use/bind.

| Campo | Valor |
|-------|--------|
| **Evidência** | `auth.js` + `auth-totp-login.js`; vs `session.js` (`is_active === false`) |
| **Cenário** | Owner desactiva conta (`is_active=false`, sessões revogadas). Challenge TOTP residual (5 min) ou forjado (P0-2) → `POST /login/totp` cria sessão nova. |
| **Impacto** | Disable não é enforce no segundo factor. Reset prematuro no `/login` anulava o lock antes do TOTP. |
| **Correcção mínima** | Após carregar o admin: recusar se `is_active === false`. Reset só após TOTP OK. Falhas TOTP chamam `registerLoginFailure`. |
| **Testes** | Admin TOTP + `is_active=false` + challenge/código válidos → 401 genérico, sem sessão, sem reset, com incremento de guarda. Falha TOTP e desafio inválido → mesmo 401. Quase locked + password OK + TOTP falho → lock. Suite backend `159/159` PASS. |

### P1-4 — Bootstrap `init` sem lock: dois owners

| Campo | Valor |
|-------|--------|
| **Evidência** | `bootstrap-admin.js:151-166`; `users-rbac-schema.js:12-21` |
| **Cenário** | BD vazia. Dois `node bootstrap-admin.js init` em paralelo, emails distintos. Unique só no email. No start, `ensureUsersRbacSchema` promove **todos** a owner. |
| **Impacto** | Dois owners com `*`. Não é remoto (precisa env/DB); é corrida de trusted entry. |
| **Correcção mínima** | `LOCK TABLE admins` no `BEGIN`, ou `INSERT … WHERE NOT EXISTS`. Gravar `is_owner=TRUE` no mesmo INSERT. Promover **um** row (`ORDER BY id LIMIT 1`). |
| **Testes** | Dois inits concorrentes → um sucesso, um “já existe”. Restart com 2 admins sem owner → só `id` mínimo vira owner. |

### P1-5 — `.lic` manual sem chave de check-in = sem revogação / sem max-offline

| Campo | Valor |
|-------|--------|
| **Evidência** | `license.c:1163-1172`, `1176-1195`, `771-775`, `972`, `819`; `main.c:1154-1162`; GUI só activa por código (`layer7_settings.php:184-191`) |
| **Cenário** | Cópia de `.lic` assinado para `/usr/local/etc/layer7.lic` sem `--activate`. |
| **Impacto** | Sem `license_key` em `/var/db/layer7-checkin.json`, check-in nunca corre. Revogação remota e teto offline (336 h) não aplicam. Enforce até expiry + 14 d de grace. |
| **Correcção mínima** | Recusar enforce se `check_in_enabled` e não existir estado de check-in com chave; ou exigir activate. Air-gap: flag explícita. |
| **Testes** | `.lic` válido sem `layer7-checkin.json` + `check_in_enabled=true` → `checkin_due`/`offline_expired` = 0; `--check-in` = `L7_CHECKIN_SKIP`. |

### P1-6 — Uninstall/revoke deixam chave e tokens em `/var/db`

| Campo | Valor |
|-------|--------|
| **Evidência** | `pkg-deinstall.in:108-146`; `scripts/release/uninstall.sh:120-137`; `layer7_settings.php:230-242`; paths em `license.c:984-1006` |
| **Cenário** | `pkg delete` / remoção GUI (mesmo sem “manter licença”) / “Revogar licença” na GUI. |
| **Impacto** | Sobrevivem `layer7-checkin.json` (chave em claro, 0600), `clock-mark.json`, `content-subscription.json`. Reinstall retoma check-in sem activate. GUI revoke apaga só o `.lic`. `MANUAL-INSTALL.md` residual **não** lista estes paths. |
| **Correcção mínima** | Apagar os três ficheiros em POST-DEINSTALL e no revoke GUI; documentar no manual. |
| **Testes** | Activate → `pkg delete` → `test ! -f /var/db/layer7-checkin.json`. Revoke GUI → o mesmo. |

### P1-7 — “Manter configuração” apaga CA MITM e segredos Identity

| Campo | Valor |
|-------|--------|
| **Evidência** | `pkg-deinstall.in:109-118` (`rm -rf /usr/local/etc/layer7` **fora** do `if` keep-config); restore só `profiles-custom.json` `:120-124`; GUI `layer7_removal.php:128-129`; CA/secrets em `layer7.inc:8661-8676`, `9161-9174` |
| **Cenário** | Uninstall com `keep_config`. |
| **Impacto** | Perdem-se `mitm/ca.key`+`ca.crt` e `identity-*.secret`. Copy da GUI sugere que só o cache de blacklists desaparece. Reinstall: CA nova (clientes com CA antiga) e LDAP/RADIUS sem secret. |
| **Correcção mínima** | Se `keep_config`, preservar `mitm/` e `identity-*.secret` (ou não fazer `rm -rf` cego). Corrigir o copy. |
| **Testes** | Gerar CA + secrets → uninstall keep-config → paths ainda existem. |

### P1-8 — POST-DEINSTALL apaga `layer7.json`/`.lic` sem guarda `PKG_UPGRADE`

| Campo | Valor |
|-------|--------|
| **Evidência** | `pkg-deinstall.in:101-116` vs guarda `PKG_UPGRADE` só em profiles-custom `:101-103`; manual afirma preservação (`MANUAL-INSTALL.md:2364-2365`, `:1815`) |
| **Cenário** | `pkg delete` (reinstall oficial: delete + `pkg add -f`). Se o ramo POST-DEINSTALL correr sem flags keep, apaga json **e** `.lic`. |
| **Impacto** | Reinstall oficial pode deixar o appliance sem licença; POST-INSTALL copia o sample e perde políticas. Upgrades de campo a sobreviverem sugerem que `pkg add -f` **pode** não correr este ramo — isso **não** está no código do produto. |
| **Correcção mínima** | `if [ -z "${PKG_UPGRADE:-}" ]` à volta do delete de json/lic. Alinhar o manual. |
| **Testes** | POST-DEINSTALL com `PKG_UPGRADE=true` → json/lic ficam. Sem flags → apagar. |

### P1-9 — Compose/nginx/bind live ≠ HEAD

| Campo | Valor |
|-------|--------|
| **Evidência** | HEAD compose default `127.0.0.1:8445`; evidência `20260811T135140Z-pre-30.11-primary-cdn` restaurou `0.0.0.0:8445` para edge `.253`; HEAD nginx sem `downloads.systemup.inf.br` |
| **Cenário** | Aplicar só o compose/nginx do git no `.244`. |
| **Impacto** | Primary público parte mesmo que o código 30.11 fosse copiado à mão. Edge `.253` deixa de alcançar o origin. |
| **Correcção mínima** | Bind `0.0.0.0` é ajuste **live/edge**, não default do repo. Deploy por allowlist; nunca substituir compose/nginx live sem diff. |
| **Testes** | Diff compose/nginx live vs HEAD antes de qualquer up; smoke origin via edge. |

### P1-10 — Git e live divergem nos dois sentidos

| Campo | Valor |
|-------|--------|
| **Evidência** | `.244` tem 30.11 que o HEAD não tem; HEAD tem portal `2.1.0` / API `30.15` que o `.244` não tem (`portal/VERSION.md`, `ESTADO.md`: SPA `2.0.0` intocada) |
| **Cenário** | Sync unidireccional sem allowlist (HEAD→live **ou** live→git). |
| **Impacto** | HEAD→live: P0-1. Live→git: regressão do portal `2.1.0` se se copiar a SPA `2.0.0`; risco de gravar bind `0.0.0.0` como default. |
| **Correcção mínima** | Allowlist nos dois sentidos. Não sincronizar `web`/frontend sem GO de portal. Não misturar commit 30.11 com deploy SPA. |
| **Testes** | Diff path-a-path antes de qualquer sync; `VERSION.md` vs `ESTADO.md`. |

---

## P2 — Médio

| ID | Evidência | Cenário | Impacto | Correcção mínima | Testes |
|----|-----------|---------|---------|------------------|--------|
| **P2-1** | `users-rbac-schema.js:12-21` | Restart sem owner | `UPDATE` sem `LIMIT` promove **todos** a owner | Promover só `ORDER BY id LIMIT 1`; alertar se `COUNT>1` | 3 admins `is_owner=false` → exactamente 1 owner |
| **P2-2** | `admin-surface.js:39-45`, `:188-196`; `index.js:47-48` | Sem `Origin` → `next()`; `/api/users` e `/api/search` fora de `isAdminApiPath` | CSRF clássico mitigado por SameSite=strict; defesa em profundidade inconsistente | Incluir users/search; state-changing fail-closed (`Origin` ou `Sec-Fetch-Site`) | `POST /api/users` com Origin evil → 403 |
| **P2-3** | `nginx.conf:6-9`, `:47`; `session.js:372-374`; `index.js:31` | HTTP ao origin + `X-Forwarded-Proto: https` | `req.secure===true`; password/TOTP/Bearer em HTTP se bind ≠ loopback | Nginx: `X-Forwarded-Proto $scheme` | HTTP + header https → login 400 no origin HTTP |
| **P2-4** | `admin-surface.js:267-295` | N falhas paralelas no mesmo email | Lock (5/15 min) atrasa-se (read-then-write) | `failure_count = … + 1` atómico ou `FOR UPDATE` | 10 `registerLoginFailure` concorrentes → count=10 + lock |
| **P2-5 FEITO no git** (`2026-08-14`; absorvido no P1-3) | `auth.js` + `auth-totp-login.js` | Password OK em `/login` chamava `resetLoginProtection` **antes** do TOTP | 2FA não herdava lockout; agrava P1-2 | Reset só após TOTP OK; falhas TOTP incrementam o guard | Quase locked + password OK + TOTP falho → lock mantém-se. **Não** deployado. Residual P1-2 = só XFF/rate-limit. |
| **P2-6** | `backend/Dockerfile`; `frontend/Dockerfile`; compose | Root; sem healthcheck; sem `.dockerignore`; `COPY . .` | RCE = root; `.env` no layer se estiver no contexto; API sobe antes do PG | `USER node`; `.dockerignore` (`.env`); `pg_isready` + `depends_on` healthy | `docker inspect` User ≠ root; build com `.env` no contexto → ausente na imagem |
| **P2-7** | `license.c:1104-1115`, `:922-932` | `store_key` mantém `features` da licença anterior | Negação de SKU pago após replace no mesmo HW até check-in activo novo | Em `store_key`, limpar `features` / `features_set` | store_key(nova) → `features_set==0`; intersect só do `.lic` novo |
| **P2-8** | `license.c:975-1007` vs clock-mark `:345-374` | `fopen(..., "w")` trunca; crash a meio | Estado vazio → check-in SKIP + offline morto; `.lic` intacto (fail-open comercial) | tmp + `fsync` + `rename`; escape JSON | Kill após fopen → ficheiro anterior sobrevive |
| **P2-9** | `layer7.inc:2552-2593`; `pkg-install.in:43-44` | Upgrade de frota pré-30.14 | `load_or_default` **não** chama a migration; chave ausente ⇒ check-in OFF. Documentado (RR-1), residual comercial | Só com GO: migração opt-in ou injectar `true` | Já existe `test_check_in_default_30.14.php`; falta teste de install a **não** migrar |
| **P2-10** | `license.c:678-703` | `--activate` grava `.lic` sem `chmod` | umask 022 → 0644; payload assinado legível localmente | `chmod 0600` / `fchmod` como no check-in | `stat` do `.lic` = 0600 |
| **P2-11** | `layer7.inc:6992-7035`, `7213-7234` | Drop de `.lic` só assinado (HW/expiry errados) | GUI Identity/MITM abre; daemon **não** arma enforce | `layer7_entitlements()` exigir HW + expiry | `.lic` HW errado / expiry passado → GUI locked, daemon `valid=0` |
| **P2-12** | `pkg-deinstall.in:42-51` vs `layer7.inc:2843` | `pkg delete` | Overrides NXDOMAIN DoH ficam no Unbound | Chamar `layer7_remove_unbound_anti_doh()` no PRE-DEINSTALL | anti-DoH ON → delete → `configured() === false` |
| **P2-13** | `license.c:238-247`, `518-520`, `568-573` | `expiry=YYYY-MM-DD` + `mktime` hora 0 | “Válido até D” acaba à meia-noite de D; `tm_isdst=0` pode desviar 1 h | Fim do dia UTC / `tm_isdst=-1` | Relógio no dia D 12:00; hoje cai para grace |
| **P2-14** | `layer7_settings.php:156-157`; `install.sh:314` | Updater / `install.sh` forçam `.pkg` 15 em Plus/16 | Bypass ABI (BG-106, documentado) | Fora deste bloco (builder 16) | Gate operacional: recusar add se ABI ≠ salvo override |
| **P2-15** | Worktree local vs MATCH `20260812T002500Z` | 30.11 não commitado continua a editar-se | Gap HEAD↔live cresce sem aparecer no `git log` | Inventário + commit allowlist (P0-1) | Cksum path-a-path vs evidência |
| **P2-16** | Tag `layer7-license-api:pre-30.13-20260814T142739Z` | Rollback da imagem pré-30.13 | Reabre rejeição de `nonce` (GA5.9 FAIL); **mantém** 30.11 | Não usar essa tag salvo incidente 30.13; preferir overlay | Smoke nonce → não pode voltar a 400 |

---

## P3 — Baixo

| ID | Evidência | Cenário | Impacto | Correcção mínima | Testes |
|----|-----------|---------|---------|------------------|--------|
| **P3-1** | `session.js:203-231` | Dois logins/TOTP em paralelo | Política “uma sessão” pode deixar 2 tokens | `BEGIN` + revoke + insert + `COMMIT` | Dois `createSession` → 1 `revoked_at IS NULL` |
| **P3-2** | `session.js:256-275` vs `:112` | `GET /api/auth/session` | `totp_enabled` omitido no SELECT → UI vê `false` | Incluir `a.totp_enabled` | Sessão com TOTP → `totp_enabled: true` |
| **P3-3** | `auth.js:107-114`; `users.js:36-38` vs `bootstrap-admin.js:94-106`; `totp.js:81` | Email disabled → 403 distinto; users aceita password ≥10; TOTP com `===` | Enumeração; lock não aplica a disabled; política inconsistente; timing TOTP teórico | Mesma 401 + bcrypt dummy; mínimo 12; `timingSafeEqual` | Disabled vs unknown → mesmo status/body |
| **P3-4** | `auth.js:222-230`; Express `^4.21.2` | Falha de BD em `GET /2fa/status` | Promise rejeitada sem error handler | `try/catch` ou wrapper async | Pool a rejeitar → 500 JSON; processo vivo |
| **P3-5** | `license.c:803-833` | Activate escreve `.lic` **antes** de verificar | Janela de ficheiro lixo se crash; verify falha ⇒ unlink (sem bypass) | Verificar tmp e `rename` atómico | Crash entre write e verify → sem `.lic` inválido permanente |
| **P3-6** | `license.c:43-48`; PEM do port; `verify-prod-pubkey.sh` só C vs SoT | Rotação desalinha PEM vs array C | Daemon e GUI podem discordar no mesmo `.lic` | Estender o gate ao PEM do port | Gate FAIL se PEM ≠ SoT |
| **P3-7** | `license.c:518-520` vs `crud-validation.js:647-654` | Appliance UTC−3 vs expiry UTC no servidor | Cliente mais estrito (grace local antes do servidor); não é bypass | Interpretar expiry como UTC (`timegm`) | `TZ=America/Sao_Paulo` vs `TZ=UTC` no dia fronteira |
| **P3-8** | `20260812T013145Z` último `asset_count=0` | Sem recheck em 14-08 | Outros PoPs/TTL não observados | Recheck só com pedido do gestor (esta auditoria não contactou GitHub) | `asset_count` + 404 anónimo |
| **P3-9** | `update-blacklists.sh:38-39`; `layer7.inc:10659-10668`; `config.json.sample:1-4` | Cliente ainda aponta espelho GitHub / tarball anónimo | Cut = 404 esperado; confunde ops; risco de reupload GA4.11 sem GO | Documentar «404 esperado» vs remover URL (bloco separado) | Sample/docs alinhados ao cut |

---

## Áreas sem achado (com evidência)

### Check-in assinado / nonce / anti-replay / dual-mode

- Pedido v2: nonce 32 B CSPRNG, base64url 43 — `license.c:1228-1235`, `1410-1411`; `crud-validation.js:572-584`.
- Eco nonce + `hardware_id` + `v==1` + `|now−iat|≤86400` — `license.c:1242-1288`.
- Verify Ed25519 **antes** de aceitar status — `license.c:1334-1339`.
- Replay de envelope antigo falha o nonce do pedido → `L7_CHECKIN_NETWORK`, **sem** invalidar (N3) — `license.c:1267-1268`; teste C4.
- Servidor **não** guarda nonce (D6 / 30.12) — intencional.
- Dual-mode D10: nonce → envelope; sem nonce → JSON legado — `check-in.js:169-182`. Cliente novo **sempre** envia nonce.
- Envelope com `content_subscription` aninhado: o `"sig"` interior serializa-se escapado e **não** é a primeira needle `"sig"` (simulação Python/`json.dumps`). Parser C compatível com GA5.9.

### Validação Ed25519 / HW / grace / gates A∩B

- `l7_ed25519_verify` — `license.c:377-415`; falha ⇒ `valid=0`.
- `is_dev_key` só sob `L7_DEV_BUILD`; Makefile do port **não** define a flag. Gates `test-prod-no-dev-bypass.sh` / `audit-release-dev-bypass.sh`.
- HW: `strcmp` — `license.c:504-508`. Grace 14 d só após sig+HW+anti-rollback.
- `enforce_armed` = gates A∩B — `license_enforce_gate.c:13-44`; `main.c:923-926`.
- Check-in **não** pode acrescentar add-ons (`layer7_features_intersect`; `full` no parse C → só BASE).

### Revogação quando a linha **não** está arquivada

- `getEffectiveLicenseState` / revoke com `FOR UPDATE` — `license-state.js:12-14`; `licenses.js:436-465`.
- Activate recusa revogada — `activate.js:76-78`.
- GA5.9 campo PASS (`20260814T143406Z`): id 15 → `denied`; `valid=0`; id 13 intocado.

### Authn / sessão / CSRF cookie / Bearer

- Login **não** adopta cookie do cliente; token = 32 bytes — `session.js:29-30`, `:203-209`.
- Cookie `httpOnly` + `secure` + `sameSite: 'strict'` + sem `Domain`.
- Pacote `cors` **não** é `require`d.
- JWT Bearer só desembrulha `session_token`; authz relê BD. Sem JWT secret: Bearer não é emitido (fail-closed **só** no bridge).
- `normalizePermissions` remove `*` / `users.manage` para não-owners. `POST /users` força `is_owner=FALSE`. Owner não é editável pela API.

### Trusted entry / default creds

- API **não** cria admin no start. Só `bootstrap-admin.js init` (one-shot se `COUNT>0` no mesmo serial).
- Password bootstrap ≥12, rejeita prefixo `CHANGE_ME`; aceita `ADMIN_BOOTSTRAP_PASSWORD_FILE`.
- Compose **sem** passwords default; `.env` no `.gitignore`; `001-init.sql` sem row seed.
- Residual: P1-4 / P2-1.

### Concorrência activate / check-in / SQLite

- **SQLite:** não se aplica (`pg.Pool`).
- Activate: `runInTransaction` + `FOR UPDATE OF l` + update condicional de `hardware_id` — um 200, outro 409.
- Check-in: só SELECT + log; não muta binding. Nonce não é single-use no servidor (contrato).
- `s_lic` só escrito no loop principal do daemon; capture no mesmo loop; sinais só flags.

### Erros / fail-open / leak

- Auth middleware: 401 + limpa cookie; excepção → 500 genérico.
- Error handler: `err.message` só em log; cliente recebe mensagem genérica.
- `wrapSignedCheckInEnvelope` throw se sig inválida (fail-closed).
- Content-auth (live, não HEAD) não loga o token.
- N3: rede / unsigned / `status=fail` **não** apaga `.lic`.

### Docker (o que está correcto no HEAD)

- Sem `privileged`, sem `network_mode: host`.
- Portas: só nginx no bind configurável; `db` e `api` não publicados.
- Residual: P2-6.

### Package rc.d / PF

- PRE-DEINSTALL: stop daemons; flush PF; cron off.
- POST-DEINSTALL: `sysrc layer7d_enable=NO`; flush de tabelas.
- Residual: P1-6…P1-8, P2-12.

### Contrato 30.12 / 30.13 no HEAD

| Contrato | HEAD | Nota |
|----------|------|------|
| D1–D5, D7–D11 | Cumprido | endpoint, nonce, envelope, mesma chave, eco hw, N3 |
| D6 sem store de nonce | Cumprido | MVP explícito |
| D8 `content_subscription` em `data` | Cumprido | emissão no git; **serving** GET = P0-1 |
| D10 dual-mode | Cumprido | legado ainda ligado (só remover com GO) |
| D12 / 30.14 default ON | Cumprido no código novo | upgrade **não** injecta `true` (P2-9 / RR-1) |
| ADR-0021 corte imediato online | **P1-1 FEITO no git** | após replace/arquivo; **não** live até overlay |

Stale documental (não é bug de runtime): 30.12 §7 ainda diz default OFF; §8 ainda diz GA5.2–5.13 PENDENTE.

---

## Conflito documental formal

O compose/nginx **HEAD** descrevem o contrato F2.1 (`127.0.0.1:8445`, sem vhost `downloads`). O live `.244` corre serving `30.11` + bind de edge. **Fonte canónica do gap:** evidência `20260814T142739Z-30.13-api-244` + este relatório + runbook de bloqueio. **Não** tratar o compose HEAD como descrição do live enquanto P0-1 estiver activo.

Registado também em [`../00-overview/document-equivalence-map.md`](../00-overview/document-equivalence-map.md) (conflito n.º 5).

---

## Fila de remediação (ordem; não implementar neste commit)

1. **P0-1 (ops, já activo)** — freeze de deploy integral; inventário allowlist quando houver GO de reconciliação.
2. **P1-1 FEITO no git** (`2026-08-14`) — SELECT de check-in + testes JS; **sem** deploy `.244`.
3. **P0-2 FEITO no git** (`2026-08-14`) — fallback TOTP removido + arranque fail-closed; residual single-use/bind. **sem** deploy `.244`.
4. **P1-3 + P2-5 FEITOS no git** (`2026-08-14`) — `is_active` + reset/lock no 2FA. **P1-2** restante = XFF / rate-limit IP. Exige restart API + GO. Sem `.244` neste bloco.
5. **P1-4 + P2-1** — um único owner no bootstrap/boot.
6. **Commit allowlist 30.11** — levanta P0-1; **depois** de P1-1 ou em chat próprio; sem snapshot/SPA/bind.
7. **P1-5…P1-8** — package/daemon (revoke local, leftovers, keep-config, `PKG_UPGRADE`).
8. **P2 / P3 restantes** — por severidade; P2-9 só com GO (muda frota antiga).

**Fora:** reabrir AP0–AP4; MITM permanente; deploy SPA `2.1.0`; GA4.11 reupload; contactar `.244`/`.254`/builder neste bloco.

---

## Objectivo / impacto / risco / teste / rollback deste registo documental

| Campo | Valor |
|-------|--------|
| Objectivo | Tornar a auditoria canónica e o freeze P0-1 visível a qualquer chat novo |
| Impacto | Só docs de governação; nenhum código; worktree 30.11 intocado |
| Risco | Baixo (documental). Risco residual: alguém ignorar o freeze — mitigado por runbook + CORTEX + checklist |
| Teste | Revisão cruzada CORTEX / backlog / checklist / START-HERE / runbook / classificação |
| Rollback | Reverter o commit documental; o freeze deixa de ser SSOT (não recomendado enquanto o gap existir) |
