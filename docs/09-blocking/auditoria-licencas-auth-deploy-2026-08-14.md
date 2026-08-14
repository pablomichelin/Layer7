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
| Serving autenticado `30.11` versionado no git | **FEITO no git** (`2026-08-14`; 7 paths) — **P0-1 NÃO encerrado** |
| Superfície admin (TOTP / rate-limit / bootstrap) | **P0-2 + P1** — não é bloqueio de deploy de conteúdo; é hardening da API |
| Revogação autenticada quando a linha **não** está arquivada | **OK** (GA5.9 campo PASS) |
| Revogação após replace/arquivo | **P1-1 FEITO no git** (`2026-08-14`) — check-in devolve 409 envelope `revoked`/`expired`; **não** deployado |
| Worktree local `30.11` | Allowlist de 7 paths **versionada**; snapshot/`.env`/SPA **fora do git** |

**P0-1 é bloqueio operacional explícito:** é **proibido** rsync/rebuild/playbook integral do HEAD contra o `.244`. O serving `30.11` **já está versionado** (allowlist, sem snapshot); o freeze **não** caiu — falta GO do primeiro rebuild `api` + smoke. Um rebuild integral injectaria também P0-2…P1-4. Runbook: [`../13-runbooks/bloqueio-deploy-integral-head-30.11.md`](../13-runbooks/bloqueio-deploy-integral-head-30.11.md).

**P0-2, P1-1, P1-2, P1-3, P1-4, P2-1, allowlist `30.11` e P1-5…P1-8 + P2-12
FEITOS no git** (`2026-08-14`; `c2b9fdb` + governação após gates; sem
deploy). P2-5 ficou absorvido no P1-3.
**P2-7+P2-8+P2-10 FEITOS no git** (`2026-08-14`; sem deploy / `PORTVERSION`).
**P2-11 FEITO no git** (`2026-08-14`; sem deploy / `PORTVERSION`).
**P0-1 permanece ACTIVO** — versionar ≠ publicar. Sem `.244` / rebuild /
GitHub Release / `PORTVERSION`. Próximo código com GO: P2 restantes
(exceto P2-9 sem GO; sem P2-7/8/10/11).

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
| **Estado** | **BLOQUEIO OPERACIONAL ACTIVO** — serving versionado no git; **não** encerrado |
| **Evidência** | Inventário read-only `2026-08-14T15:31:37Z`; hashes dos 7 paths no runbook de freeze. Histórico pré-commit: evidência `20260814T142739Z`; HEAD sem `content-auth.js` / `routes/content.js` / volume / vhost `downloads`. |
| **Cenário** | `rsync` / `docker compose` rebuild / playbook genérico do clone HEAD sobre `/opt/layer7-license`. Também: `rsync --delete`; substituir compose/nginx live pelo HEAD; `git add -A` (tarball ~31 MB + risco de `.env`). |
| **Impacto** | Primary autenticado de blacklists cai. Clientes `1.9.54+` com token falham o GET current; o espelho GitHub já está `asset_count=0` (último recheck `20260812T013145Z`). Actualização de conteúdo parte. `rsync --delete` pode ainda apagar snapshot e `.env` (crash-loop histórico). Rebuild integral injecta também P0-2…P1-4. |
| **Correcção mínima** | 1) Inventário **FEITO**. 2) Commit allowlist **FEITO** (7 paths; sem `index.js` — mount já no HEAD). 3) Gate de deploy: nunca sync integral; overlay por paths. 4) GO humano + smoke **PENDENTE**. |
| **Testes** | Hashes = inventário; `npm test` content + backend `173/173`; `git archive` resolve `routes/content`; `check-ignore` do snapshot. Smoke live **só com GO**. **Não** repetir GA5.9 sem GO. |
| **Até lá** | Proibido deploy integral do HEAD. Overlay `30.13` permanece o único padrão autorizado. Snapshot/`.env` **não** entram no git. |

Facetas do mesmo bloqueio (não são P0 independentes):

- Volume `CONTENT_BLACKLISTS_DIR` + vhost `downloads` + ignore do snapshot: **versionados** neste bloco.
- Bind compose HEAD = `127.0.0.1:8445`; live restaurou `0.0.0.0:8445` para o edge `.253` (**P1-9**). **Não** versionar o bind live.
- Rebuild integral HEAD→`.244` continua a injectar P0-2…P1-4 (live `index.js` ≠ HEAD).

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

**FEITO no git** (`2026-08-14`). `getClientIp` deixa de ler o primeiro hop
de `X-Forwarded-For` e usa `req.ip` (`trust proxy: 1`) ou o socket.
O nginx de origin substitui `X-Forwarded-For` por `$remote_addr` em todos
os `proxy_pass` (já não usa `$proxy_add_x_forwarded_for`). O mapa
`X-Forwarded-Proto` **não** foi alterado (P2-3). Lock/reset TOTP já estava
no P1-3. **Não** deployado (P0-1).

**Topologia validada (HEAD, sem contactar hosts):**

```text
cliente → edge TLS → origin 127.0.0.1:8445 (compose default)
       → nginx interno → Express trust proxy: 1
```

Com `ports:` Docker, `$remote_addr` no origin é o hop do publisher
(tipicamente gateway da bridge), não o IP público. Substituir XFF por
`$remote_addr` é seguro nas duas variantes documentadas (loopback HEAD e
bind live `0.0.0.0` / edge `.253` da P1-9): o cliente deixa de escolher a
chave. **Não** se adivinhou o IP do edge live nem se activou `real_ip` em
RFC1918. Residual: a chave de rate-limit/lock IP no origin é o hop
confiável, não o IP público; recuperar o IP público exige PROXY protocol
ou caminho edge-only confirmado — fora deste bloco. P2-2 (CSRF) **não**
foi este bloco.

| Campo | Valor |
|-------|--------|
| **Evidência** | `session.js` `getClientIp`; `admin-surface.js` limiters/lock; `nginx/nginx.conf` `X-Forwarded-For $remote_addr`; `index.js` `trust proxy: 1` |
| **Cenário** | Pedido ao origin com `X-Forwarded-For` falso. Nginx faz `$proxy_add_x_forwarded_for`. `getClientIp` usa o **primeiro** hop e ignora `X-Real-IP`. Em `/login/totp` não há lock de conta. |
| **Impacto** | Rate-limit IP e lock IP tornam-se irrelevantes. Spray online do TOTP (`window=1` ⇒ 3 códigos / 30s). Encadeado com P0-2: takeover sem password. Lock de **conta** no password ainda funciona. |
| **Correcção mínima** | IP de confiança = `req.ip` (`trust proxy: 1`) ou só `X-Real-IP`. Nginx: `proxy_set_header X-Forwarded-For $remote_addr;`. Em `/login/totp`: `registerLoginFailure` + lock por `admin_id`. |
| **Implementado** | `getClientIp` = `req.ip` / socket; origin XFF = `$remote_addr`; testes `session-client-ip.test.js` + `nginx-xff-config.test.js`. Lock TOTP = P1-3. Sem Proto (P2-3). Sem `.244`. |
| **Testes** | XFF spoof vs hop confiável → mesma chave. Nginx sem `$proxy_add_x_forwarded_for` nas directivas. Proto map intacto. Suite backend `166/166` PASS. |

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

**FEITO no git** (`2026-08-14`; opção A). P2-1 absorvido no mesmo bloco.
**Não** deployado (P0-1). Sem unique index, sem demotion, sem transferência.

| Campo | Valor |
|-------|--------|
| **Evidência** | `bootstrap-admin-init.js`; `users-rbac-schema.js` |
| **Cenário** | BD vazia. Dois `node bootstrap-admin.js init` em paralelo, emails distintos. Unique só no email. No start, `ensureUsersRbacSchema` promovia **todos** a owner. |
| **Impacto** | Dois owners com `*`. Não é remoto (precisa env/DB); é corrida de trusted entry. |
| **Correcção mínima** | `LOCK TABLE admins IN SHARE ROW EXCLUSIVE MODE` no `BEGIN`. Gravar `is_owner=TRUE` no mesmo INSERT. Promover **um** row (`ORDER BY id LIMIT 1`). Alertar se `COUNT>1` sem promover/demover. |
| **Implementado** | Lock transacional no init; primeiro admin já owner activo com `*`; promoção legado `ORDER BY id ASC LIMIT 1`; `console.warn` se houver vários owners. Sem unique, sem demotion, sem compose/.env/seed/SPA. |
| **Testes** | Dois inits concorrentes → um sucesso, um “já existe”, um só owner. Restart com 2+ admins sem owner → só `id` mínimo. Restart com owner existente → zero promoções extra. Vários owners → alerta, sem mutação. Suite backend `173/173` PASS. |

### P1-5 — `.lic` manual sem chave de check-in = sem revogação / sem max-offline

**FEITO no git** (`c2b9fdb`; governação após gates).
`layer7_checkin_enforce_ready()` recusa enforce se `check_in_enabled` e
não houver `license_key`. Air-gap = `check_in_enabled=false`. Sem deploy /
`PORTVERSION`.

| Campo | Valor |
|-------|--------|
| **Evidência** | `license.c` `layer7_checkin_enforce_ready`; `main.c` `enforce_armed` + `refresh_enforce_cfg` |
| **Cenário** | Cópia de `.lic` assinado para `/usr/local/etc/layer7.lic` sem `--activate`. |
| **Impacto** | Sem `license_key` em `/var/db/layer7-checkin.json`, check-in nunca corre. Revogação remota e teto offline (336 h) não aplicam. Enforce até expiry + 14 d de grace. |
| **Correcção mínima** | Recusar enforce se `check_in_enabled` e não existir estado de check-in com chave; ou exigir activate. Air-gap: flag explícita. |
| **Implementado** | Gate no daemon; `--check-in` continua `SKIP`; N3 intacto (rede ≠ sem chave). |
| **Testes** | `test_checkin_config_enabled.c`: enabled+sem estado ⇒ `enforce_ready=0`; air-gap ⇒ 1; chave presente ⇒ 1. |

### P1-6 — Uninstall/revoke deixam chave e tokens em `/var/db`

**FEITO no git** (`c2b9fdb`; governação após gates). Uninstall real apaga
os três paths; keep-config/keep-license/`PKG_UPGRADE` preservam. Revoke
GUI chama `layer7_clear_local_license_state()`. Sem deploy.

| Campo | Valor |
|-------|--------|
| **Evidência** | `pkg-deinstall.in`; `uninstall.sh`; `layer7_clear_local_license_state()` |
| **Cenário** | `pkg delete` / remoção GUI (mesmo sem “manter licença”) / “Revogar licença” na GUI. |
| **Impacto** | Sobrevivem `layer7-checkin.json` (chave em claro, 0600), `clock-mark.json`, `content-subscription.json`. Reinstall retoma check-in sem activate. GUI revoke apaga só o `.lic`. |
| **Correcção mínima** | Apagar os três ficheiros em POST-DEINSTALL e no revoke GUI; documentar no manual. |
| **Implementado** | Wipe só em uninstall real sem keep; revoke limpa os três paths. |
| **Testes** | `test_pkg_deinstall_lifecycle.sh` + `test_license_revoke_state.php`. |

### P1-7 — “Manter configuração” apaga CA MITM e segredos Identity

**FEITO no git** (`c2b9fdb`; governação após gates). keep-config e
`PKG_UPGRADE` fazem backup/restore de `mitm/` e `identity-*.secret`.
Copy da GUI corrigido. Sem deploy.

| Campo | Valor |
|-------|--------|
| **Evidência** | `pkg-deinstall.in` `_preserve_runtime`; `uninstall.sh --keep-config`; `layer7_removal.php` |
| **Cenário** | Uninstall com `keep_config`. |
| **Impacto** | Perdem-se `mitm/ca.key`+`ca.crt` e `identity-*.secret`. Copy da GUI sugere que só o cache de blacklists desaparece. |
| **Correcção mínima** | Se `keep_config`, preservar `mitm/` e `identity-*.secret` (ou não fazer `rm -rf` cego). Corrigir o copy. |
| **Implementado** | Backup/restore no mesmo padrão de `profiles-custom.json`. |
| **Testes** | `test_pkg_deinstall_lifecycle.sh` (contratos de ficheiro). |

### P1-8 — POST-DEINSTALL apaga `layer7.json`/`.lic` sem guarda `PKG_UPGRADE`

**FEITO no git** (`c2b9fdb`; governação após gates). Delete de json/`.lic`
só corre se `PKG_UPGRADE` estiver vazio e não houver keep. Manual e
`rollback.md` alinhados. Sem deploy.

| Campo | Valor |
|-------|--------|
| **Evidência** | `pkg-deinstall.in` `_is_upgrade`; `MANUAL-INSTALL.md` §5; `docs/13-runbooks/rollback.md` |
| **Cenário** | `pkg delete` (reinstall oficial: delete + `pkg add -f`). Se o ramo POST-DEINSTALL correr sem flags keep, apaga json **e** `.lic`. |
| **Impacto** | Reinstall oficial pode deixar o appliance sem licença; POST-INSTALL copia o sample e perde políticas. |
| **Correcção mínima** | `if [ -z "${PKG_UPGRADE:-}" ]` à volta do delete de json/lic. Alinhar o manual. |
| **Implementado** | Upgrade não apaga; `pkg delete` sem keep apaga; reinstall preferido = `pkg add -f` sem delete. |
| **Testes** | `test_pkg_deinstall_lifecycle.sh`. |

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
| **P2-1 FEITO no git** (`2026-08-14`; absorvido no P1-4) | `users-rbac-schema.js` | Restart sem owner | `UPDATE` sem `LIMIT` promovia **todos** a owner | Promover só `ORDER BY id LIMIT 1`; alertar se `COUNT>1` sem demover | 3 admins `is_owner=false` → exactamente 1 owner. **Não** deployado. |
| **P2-2** | `admin-surface.js:39-45`, `:188-196`; `index.js:47-48` | Sem `Origin` → `next()`; `/api/users` e `/api/search` fora de `isAdminApiPath` | CSRF clássico mitigado por SameSite=strict; defesa em profundidade inconsistente | Incluir users/search; state-changing fail-closed (`Origin` ou `Sec-Fetch-Site`) | `POST /api/users` com Origin evil → 403 |
| **P2-3** | `nginx.conf:6-9`, `:47`; `session.js:372-374`; `index.js:31` | HTTP ao origin + `X-Forwarded-Proto: https` | `req.secure===true`; password/TOTP/Bearer em HTTP se bind ≠ loopback | Nginx: `X-Forwarded-Proto $scheme` | HTTP + header https → login 400 no origin HTTP |
| **P2-4** | `admin-surface.js:267-295` | N falhas paralelas no mesmo email | Lock (5/15 min) atrasa-se (read-then-write) | `failure_count = … + 1` atómico ou `FOR UPDATE` | 10 `registerLoginFailure` concorrentes → count=10 + lock |
| **P2-5 FEITO no git** (`2026-08-14`; absorvido no P1-3) | `auth.js` + `auth-totp-login.js` | Password OK em `/login` chamava `resetLoginProtection` **antes** do TOTP | 2FA não herdava lockout; agrava P1-2 | Reset só após TOTP OK; falhas TOTP incrementam o guard | Quase locked + password OK + TOTP falho → lock mantém-se. **Não** deployado. Residual XFF = P1-2 **FEITO** no git. |
| **P2-6** | `backend/Dockerfile`; `frontend/Dockerfile`; compose | Root; sem healthcheck; sem `.dockerignore`; `COPY . .` | RCE = root; `.env` no layer se estiver no contexto; API sobe antes do PG | `USER node`; `.dockerignore` (`.env`); `pg_isready` + `depends_on` healthy | `docker inspect` User ≠ root; build com `.env` no contexto → ausente na imagem |
| **P2-7 FEITO no git** (`2026-08-14`) | `license.c` `layer7_checkin_store_key` | `store_key` mantinha `features` da licença anterior | Negação de SKU pago após replace no mesmo HW até check-in activo novo | Em `store_key`, limpar só `features` / `features_set` | store_key(nova) → `features_set==0`; intervalos preservados. **Não** deployado. |
| **P2-8 FEITO no git** (`2026-08-14`) | `license.c` `checkin_save_state` vs clock-mark | `fopen(..., "w")` truncava; crash a meio | Após P1-5, estado vazio recusa enforce; `.lic` intacto | tmp + `chmod 0600` + `rename`; escape JSON | Falha de tmp preserva o ficheiro anterior; aspas/barra re-lidas. **Não** deployado. |
| **P2-9** | `layer7.inc:2552-2593`; `pkg-install.in:43-44` | Upgrade de frota pré-30.14 | `load_or_default` **não** chama a migration; chave ausente ⇒ check-in OFF. Documentado (RR-1), residual comercial | Só com GO: migração opt-in ou injectar `true` | Já existe `test_check_in_default_30.14.php`; falta teste de install a **não** migrar |
| **P2-10 FEITO no git** (`2026-08-14`) | `license.c` `promote_activate_body` / `write_bytes_0600` | `--activate` gravava `.lic` sem `chmod` | umask 022 → 0644; payload assinado legível localmente | `fchmod`/`chmod 0600` | `stat` do `.lic` = 0600. **Não** deployado. |
| **P2-11 FEITO no git** (`2026-08-14`) | `layer7.inc` `layer7_entitlements()` / `layer7_license_binding_ok()`; `layer7-mitm-entitle-ok` | Drop de `.lic` só assinado (HW/expiry errados) | GUI Identity/MITM abre; daemon **não** arma enforce | `layer7_entitlements()` + helper exigem HW + expiry/grace 14d | `.lic` HW errado / expiry além da graça → GUI locked; graça/válido → unlock; stats forjados → locked. **Não** deployado. |
| **P2-12 FEITO no git** (`c2b9fdb`; governação após gates) | `pkg-deinstall.in` PRE | `pkg delete` | Overrides NXDOMAIN DoH ficam no Unbound | Chamar `layer7_remove_unbound_anti_doh()` no PRE-DEINSTALL (não em `PKG_UPGRADE`) | Contrato no `test_pkg_deinstall_lifecycle.sh`. **Não** deployado. |
| **P2-13** | `license.c:238-247`, `518-520`, `568-573` | `expiry=YYYY-MM-DD` + `mktime` hora 0 | “Válido até D” acaba à meia-noite de D; `tm_isdst=0` pode desviar 1 h | Fim do dia UTC / `tm_isdst=-1` | Relógio no dia D 12:00; hoje cai para grace |
| **P2-14** | `layer7_settings.php:156-157`; `install.sh:314` | Updater / `install.sh` forçam `.pkg` 15 em Plus/16 | Bypass ABI (BG-106, documentado) | Fora deste bloco (builder 16) | Gate operacional: recusar add se ABI ≠ salvo override |
| **P2-15 FEITO no git** (`2026-08-14`) | Worktree local vs MATCH `20260812T002500Z` | Serving allowlist versionado; snapshot continua só em disco | Gap de código 30.11 no git fechado; snapshot/`.env` continuam fora | Inventário + commit allowlist **FEITO**; P0-1 **não** encerrado | Hashes = inventário; `check-ignore` do tarball |
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
- Residual: P1-4 / P2-1 **FEITOS no git** (`2026-08-14`; sem unique/demotion; sem deploy).

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

O compose/nginx **HEAD** descrevem o contrato F2.1 (`127.0.0.1:8445`) **e**, após o commit allowlist, o vhost `downloads` + volume `CONTENT_BLACKLISTS_DIR`. O live `.244` corre serving `30.11` + overlay `30.13` + bind de edge `0.0.0.0`. **Fonte canónica do gap restante:** evidência `20260814T142739Z-30.13-api-244` + este relatório + runbook de bloqueio. **Não** tratar o compose HEAD como descrição do live (bind/P0-2…P1-4) enquanto P0-1 estiver activo.

Registado também em [`../00-overview/document-equivalence-map.md`](../00-overview/document-equivalence-map.md) (conflito n.º 5).

---

## Fila de remediação (ordem; não implementar neste commit)

1. **P0-1 (ops, ACTIVO)** — freeze de deploy integral **mantém-se**. Inventário + commit allowlist **FEITOS** no git (`2026-08-14`); **não** levanta o bloqueio. Falta GO do primeiro rebuild `api` + smoke.
2. **P1-1 FEITO no git** (`2026-08-14`) — SELECT de check-in + testes JS; **sem** deploy `.244`.
3. **P0-2 FEITO no git** (`2026-08-14`) — fallback TOTP removido + arranque fail-closed; residual single-use/bind. **sem** deploy `.244`.
4. **P1-3 + P2-5 FEITOS no git** (`2026-08-14`) — `is_active` + reset/lock no 2FA.
5. **P1-2 FEITO no git** (`2026-08-14`) — XFF / rate-limit IP; origin substitui XFF; `getClientIp` = `req.ip`. **Não** deployado. Residual: IP público no origin (PROXY/P1-9); P2-3 Proto; P2-2 CSRF.
6. **P1-4 + P2-1 FEITOS no git** (`2026-08-14`) — lock no init; primeiro admin já owner; promoção `LIMIT 1`; alerta se `COUNT>1`. **sem** deploy `.244`.
7. **Commit allowlist 30.11 FEITO no git** (`2026-08-14`) — **não** levanta P0-1. Sem snapshot/SPA/bind/`.env`/host.
8. **P1-5…P1-8 + P2-12 FEITOS no git** (`c2b9fdb` + governação após gates) — package/daemon; **sem** deploy / `PORTVERSION`.
9. **P2-7+P2-8+P2-10 FEITOS no git** (`2026-08-14`) — daemon local; **sem** deploy / `PORTVERSION`.
10. **P2-11 FEITO no git** (`2026-08-14`) — GUI/helper binding HW + expiry/grace; **sem** deploy / `PORTVERSION`.
11. **P2 / P3 restantes** — por severidade; P2-13 **não** neste bloco; P2-3 Proto e P2-2 CSRF ficam na fila; P2-9 só com GO.

**Fora:** reabrir AP0–AP4; MITM permanente; deploy SPA `2.1.0`; GA4.11 reupload; contactar `.244`/`.254`/builder neste bloco.

---

## Objectivo / impacto / risco / teste / rollback — P2-11 (`2026-08-14`)

| Campo | Valor |
|-------|--------|
| Objectivo | Impedir que a GUI Identity/MITM e `layer7-mitm-entitle-ok` desbloqueiem com um `.lic` apenas assinado quando HW ou validade/grace não batem com o daemon |
| Impacto | `layer7.inc` (fingerprint local + binding + entitlements), helper `layer7-mitm-entitle-ok`, `test_entitlements_gui.php`, `run-local.sh`, docs canónicas. Sem P2-9/P2-13/P3, sem daemon `enforce_armed`, sem license server/SPA/compose, sem `PORTVERSION`/build/release/hosts |
| Risco | Baixo. Grace de 14 dias alinhada ao daemon (evita false-lock comercial). Fingerprint = `sysctl kern.hostuuid` + primeira `ifconfig -l ether` (mesmo filtro IFT_ETHER). Residual: anti-rollback 30.6 continua só no daemon; DST ±1 h = P2-13 (fora) |
| Teste | `php tests/functional/test_entitlements_gui.php` — HW errado, expiry além da graça, dentro da graça, válido, stats forjados + helper/rc.d PATH |
| Rollback | Reverter o commit; a GUI/helper voltam a desbloquear só com assinatura Ed25519 |

## Objectivo / impacto / risco / teste / rollback — P2-7+P2-8+P2-10 (`2026-08-14`)

| Campo | Valor |
|-------|--------|
| Objectivo | Persistência atómica do estado de check-in; limpar só o cache de features ao substituir a chave; gravar `.lic` com modo 0600 |
| Impacto | Só `src/layer7d/license.c` / `license.h` + teste C + runner + docs canónicas. Sem P2-9/P2-11/P2-13, sem license server/SPA/compose, sem `PORTVERSION`/build/release/hosts |
| Risco | Baixo. P1-5 e N3 intactos. `store_key` não mexe em intervalos. Residual: P2-13 (expiry) fora deste bloco; P2-11 fechado noutro commit |
| Teste | `test_checkin_state_persist` (sucesso, JSON, falha tmp, SKU, 0600) + `sh tests/run-local.sh` |
| Rollback | Reverter o commit; o save volta a truncar no sítio, `store_key` herda features e o `.lic` volta ao umask |

## Governação — estado vs commit (`2026-08-14`)

SSOTs e checklist **não** antecipam «FEITO no git». Código do bloco:
`c2b9fdb`. Estado canónico **após** gates PASS + staging + este commit:
**FEITO no git**. `P0-1` permanece ACTIVO. Sem deploy / `PORTVERSION`.

## Objectivo / impacto / risco / teste / rollback — P1-5…P1-8 + P2-12 (`2026-08-14`)

| Campo | Valor |
|-------|--------|
| Objectivo | Recusar enforce sem estado de check-in quando a flag está ON; preservar json/`.lic`/CA/secrets/check-in em upgrade e keep-config; limpar `/var/db` + anti-DoH no deinstall real |
| Impacto | Daemon (`license.c`/`main.c`), hooks `pkg-deinstall.in`, `uninstall.sh`, revoke GUI, copy de remoção, docs de install/rollback. Sem P2-9, sem license server/SPA, sem `PORTVERSION`/build/release/hosts |
| Risco | Médio-baixo. Air-gap continua `check_in_enabled=false`. N3 intacto (falha de rede ≠ recusar enforce). Upgrade deixa de apagar licença/políticas. Residual: P2-9 (migração check-in em upgrade) só com GO |
| Teste | `test_checkin_config_enabled` + `test_license_enforce_gate` + `test_pkg_deinstall_lifecycle.sh` + `test_license_revoke_state.php` + `sh tests/run-local.sh` |
| Rollback | Reverter o commit; `enforce_armed` volta a armar sem chave; POST-DEINSTALL volta a apagar json/`.lic` em upgrade e a deixar leftovers em `/var/db` |

## Objectivo / impacto / risco / teste / rollback — allowlist `30.11` / P0-1 git (`2026-08-14`)

| Campo | Valor |
|-------|--------|
| Objectivo | Versionar no git o serving autenticado `30.11` (7 paths) para clone limpo resolver `routes/content` |
| Impacto | Só allowlist + docs; sem `index.js`/auth/TOTP/SPA/package/daemon/`.env`/bind/snapshot; sem host |
| Risco | Baixo no git. **P0-1 não encerrado** — rebuild integral continua proibido |
| Teste | Hashes = inventário; backend `173/173`; `git archive`; `check-ignore`; diff compose/nginx exacto; scan de segredo |
| Rollback | `git revert` do commit allowlist; live intocado |

## Objectivo / impacto / risco / teste / rollback — P1-4 + P2-1 (`2026-08-14`)

| Campo | Valor |
|-------|--------|
| Objectivo | Um único owner no bootstrap e no boot; o primeiro admin nasce já como owner activo |
| Impacto | Só `license-server/backend` init/RBAC + testes + docs; sem unique index; sem demotion; sem compose/.env/seed/SPA/nginx/package/daemon; sem `.244` |
| Risco | Baixo (fail-closed na corrida de trusted entry). Residual: owners extra já existentes no live não são reduzidos; P0-1 impede overlay |
| Teste | Suite backend `173/173` PASS (`bootstrap-admin-init` + `users-rbac-schema`) |
| Rollback | Reverter o commit; o `init` volta ao `COUNT` sem lock e o boot volta a promover todos os não-owners |

## Objectivo / impacto / risco / teste / rollback — P1-2 (`2026-08-14`)

| Campo | Valor |
|-------|--------|
| Objectivo | Cliente externo não escolhe o IP de rate-limit/lock admin via `X-Forwarded-For` |
| Impacto | `getClientIp` + nginx de origin no git; sem Proto (P2-3); sem TOTP single-use; sem package/daemon/SPA; sem host/deploy |
| Risco | Baixo (fail-closed no hop confiável). Residual: chave = hop do origin (não IP público); live `.244` sem overlay (P0-1); P2-2 CSRF aberto |
| Teste | Suite backend `166/166` PASS (`session-client-ip` + `nginx-xff-config`); `nginx` local ausente — asserções de ficheiro cobrem a sintaxe do contrato |
| Rollback | Reverter o commit; `getClientIp` volta ao primeiro hop de XFF e o origin volta a `$proxy_add_x_forwarded_for` |

## Objectivo / impacto / risco / teste / rollback deste registo documental

| Campo | Valor |
|-------|--------|
| Objectivo | Tornar a auditoria canónica e o freeze P0-1 visível a qualquer chat novo |
| Impacto | Só docs de governação; nenhum código; worktree 30.11 intocado |
| Risco | Baixo (documental). Risco residual: alguém ignorar o freeze — mitigado por runbook + CORTEX + checklist |
| Teste | Revisão cruzada CORTEX / backlog / checklist / START-HERE / runbook / classificação |
| Rollback | Reverter o commit documental; o freeze deixa de ser SSOT (não recomendado enquanto o gap existir) |
