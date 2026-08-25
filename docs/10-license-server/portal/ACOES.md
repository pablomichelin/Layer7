# Acções — Portal Admin de Licenças

Diário obrigatório. Entrada por bloco (mesmo que só documental).  
Mais recente no topo.

---

## 2026-08-25 — BG-163 cliente install-ping (sem overlay)

| Campo | Valor |
|-------|--------|
| Tipo | só pacote (sem SPA / sem `.244`) |
| Versão | portal live `2.2.0` intacto / candidato pacote `1.9.72` |
| Objectivo | O sinal de instalação voltar a chegar ao endpoint já live |
| Impacto | Helper PHP/libexec + tick 15 min. API e página Instalações **não** mudam |
| Risco | Baixo. Fail-open mantido. Fallback `hardware_id` pode criar 2.ª linha se o fingerprint passar a funcionar depois |
| Teste | `test_install_ping_failopen.sh`; inventário PHP (XML + throttle) |
| Rollback | Reverter o commit do `1.9.72`; caixas voltam ao helper `1.9.71` silencioso |
| Resultado | **Implementado, pendente de gates/commit/publish** — endpoint live 200 confirmado |

## 2026-08-22 — BG-162 Instalações LIVE + `1.9.71`

| Campo | Valor |
|-------|--------|
| Tipo | overlay `.244` (API+SPA) + pacote |
| Versão | `2.2.0` live / pacote `1.9.71` |
| Objectivo | Ver quem instalou mesmo sem serial |
| Impacto | `POST /api/license/install-ping` live; menu Instalações; sem rsync integral |
| Risco | Endpoint público (rate-limit + schema). Rollback: tags `pre-bg162-20260823T022826Z` |
| Teste | health 200; ping 200 `{status:ok}`; garbage 400; GET /api/installations 401; check-in 404 intacto |
| Rollback | `docker tag layer7-license-api:pre-bg162-20260823T022826Z layer7-license-api:latest` (+ web) |
| Resultado | **Overlay PASS**. GitHub Release `v1.9.71` F1.2 no mesmo bloco |

## 2026-08-22 — BG-162 Instalações (sinal sem serial)

| Campo | Valor |
|-------|--------|
| Tipo | backend + SPA (sem deploy) |
| Versão | `2.2.0` git / SPA live intocada |
| Objectivo | Ver quem instalou o pacote mesmo sem serial: FQDN, IP público, IPs/nomes de interfaces, uniqueid |
| Impacto | `POST /api/license/install-ping`, tabelas, `/installations`, dashboard, ADR-0036. Pacote `1.9.71` no mesmo bloco |
| Risco | Endpoint público (rate-limit + schema). P0-1: sem overlay `.244`. Sem `latest` novo |
| Teste | `node --test` parse + CSRF; PHP inventário |
| Rollback | Reverter commit; tabelas só existem após deploy futuro |
| Resultado | **FEITO no git** — superseded pelo overlay live no mesmo dia |

## 2026-08-14 — BG-154 P2-9 upgrade não injecta check-in ON

| Campo | Valor |
|-------|--------|
| Tipo | docs + cadeado PHP (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | Fechar P2-9 sem invertir o GO `30.14`: upgrade **não** injecta `true` |
| Impacto | `test_check_in_default_30.14.php` + runbook `30.14` §7 + SSOTs. Runtime `layer7.inc` / `pkg-install.in` intactos |
| Risco | Nenhum de runtime. Residual RR-1: base antiga OFF até opt-in; injectar `true` só com GO que emende o `30.14` |
| Teste | Cadeado de fonte (install não chama migration). PHP local ausente neste Mac |
| Rollback | Reverter docs+teste. **Não** injectar `true` |
| Resultado | **AVALIADO neste bloco** — sem `.244` / sem SPA / sem `PORTVERSION` |

## 2026-08-14 — BG-128 P0-2 residual TOTP single-use + bind

| Campo | Valor |
|-------|--------|
| Tipo | backend API (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | O `challenge_token` TOTP deixa de ser stateless: `jti` no HMAC + linha em `admin_totp_challenges`; replay e token forjado sem password falham fechado |
| Impacto | `totp.js` + `totp-challenge.js` + `totp-schema.js` + `/login` + `/login/totp` + `001-init.sql`. Contrato HTTP de sucesso intacto (`totp_required` + token opaco). Sem env/segredo novo, sem SPA, sem bind IP/UA |
| Risco | Baixo no git. Tokens em voo (≤5 min) falham após deploy futuro. Overlay P0-1. Residual: consumir só no sucesso (spray no mesmo token até lock/exp) |
| Teste | Suite backend `256/256` PASS (replay, `jti` forjado/inexistente, corrida paralela, desafio anterior, token antigo sem `jti`, regressões P1-3/P3-3A) |
| Rollback | Reverter o commit; o challenge volta a ser `{admin_id, exp}` sem linha. `DROP TABLE admin_totp_challenges` só se a tabela tiver sido criada no live |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem compose / sem `PORTVERSION` |

## 2026-08-14 — BG-140 P3-4 falha de pool em GET /2fa/status

| Campo | Valor |
|-------|--------|
| Tipo | backend API (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | `GET /api/auth/2fa/status` deixa de rejeitar a Promise sem resposta HTTP quando o pool falha |
| Impacto | Só o handler `/2fa/status` em `routes/auth.js`: try/catch local + log `[AUTH] 2FA status error:` com `err.message` + 500 JSON `buildAuthErrorResponse(ADMIN_INTERNAL_ERROR_MESSAGE)`. Sem wrapper global, sem Express 5, sem dependência nova. `totp.js` / `users.js` / `session.js` / frontend / compose intocados |
| Risco | Baixo. Contrato 200/401/403 intacto. Overlay P0-1. Residual P3-5 |
| Teste | Suite backend `235/235` PASS. Fail-before Express 4: timeout + unhandledRejection, sem 500 JSON. Depois: 500 `{error:'Erro interno.'}`, nenhum unhandledRejection, segundo GET saudável 200 `{totp_enabled:true}` |
| Rollback | Reverter o commit; `/2fa/status` volta a rejeitar sem 500 JSON |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem totp.js / sem users.js / sem session.js |

## 2026-08-14 — BG-138 P3-3C comparação TOTP timing-safe

| Campo | Valor |
|-------|--------|
| Tipo | backend API (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | Comparação HOTP/TOTP deixa de usar `===` (timing teórico de prefixo) |
| Impacto | Só `totp.js`: Buffer UTF-8 + guarda de comprimento + `timingSafeEqual`. Janela/step/HMAC/normalização intactos. `auth.js` / users / sessão / frontend / compose intocados |
| Risco | Baixo. Contrato funcional idêntico (true/false). Overlay P0-1. Residual P3-4 |
| Teste | Suite backend `229/229` PASS. Válido mesmo now → true; 6 dígitos inválidos → false; malformed → false sem throw; guarda evita RangeError |
| Rollback | Reverter o commit; `verifyTotp` volta a `===` |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem auth.js / sem users.js / sem session.js |

## 2026-08-14 — BG-136 P3-3B password de técnico >=12

| Campo | Valor |
|-------|--------|
| Tipo | backend API (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | Criação/edição de técnico alinha a política canónica de password >=12 do bootstrap |
| Impacto | Só `normalizePassword` em `routes/users.js` (10→12 + mensagem). POST/PUT com 10 → 400; POST 12 → 201; PUT sem password inalterado. Authz `users.manage` e owner 409 intactos. `/login` não rejeita 10. `Users.jsx` / bootstrap / auth / TOTP / sessão / compose intocados |
| Risco | Baixo. SPA `Users.jsx` continua `minLength={10}` (fora deste bloco); API passa a recusar 10–11. Residual P3-3C. Overlay P0-1 |
| Teste | Suite backend `225/225` PASS. Antes: POST 10 → 201, PUT 10 → 200. Depois: POST 10 → 400, POST 12 → 201, PUT 10 → 400, PUT sem password inalterado |
| Rollback | Reverter o commit; `users.js` volta a aceitar password >=10 |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem bootstrap / sem auth.js / sem totp.js |

## 2026-08-14 — BG-134 P3-3A enumeração disabled em POST /login

| Campo | Valor |
|-------|--------|
| Tipo | backend API (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | `POST /api/auth/login` deixa de enumerar conta desactivada face a email inexistente |
| Impacto | Só `routes/auth.js`: disabled e unknown passam a 401 genérico, bcrypt (hash real ou dummy) e `registerLoginFailure`. Sucesso, lock 5/15 e 10/15, TOTP e CSRF intactos |
| Risco | Baixo. Operador deixa de ver «Conta desactivada» no formulário; o audit interno continua `account_disabled`. Residual P3-3B/P3-3C. Overlay P0-1 |
| Teste | Suite backend `219/219` PASS. Antes: disabled 403 distinto, sem bcrypt nem guarda. Depois: mesmo 401/body + guardas |
| Rollback | Reverter o commit; `/login` volta a 403 `Conta desactivada.` sem lock |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem `users.js` / sem `totp.js` / sem compose |

## 2026-08-14 — BG-132 P3-2 exactidão TOTP em GET /session

| Campo | Valor |
|-------|--------|
| Tipo | backend API (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | `GET /api/auth/session` reporta `totp_enabled: true` quando o admin tem TOTP ligado |
| Impacto | Só o SELECT de `resolveSessionToken` passa a incluir `a.totp_enabled`. Auth/TTL/revoke/`createSession`/CSRF/TOTP flows intactos |
| Risco | Baixo. Residual: overlay P0-1; não existe `getSessionMetadata` — a query vive em `resolveSessionToken` |
| Teste | Suite backend `212/212` PASS. Antes: SELECT omitia a coluna → `toPublicAdmin` → `false`. Depois: `true` |
| Rollback | Reverter o commit; o SELECT volta a omitir `a.totp_enabled` |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem admin-surface / sem compose |

## 2026-08-14 — BG-131 P3-1 sessão única atómica

| Campo | Valor |
|-------|--------|
| Tipo | backend API (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | Dois logins/TOTP em paralelo deixam uma só sessão `revoked_at IS NULL` por admin |
| Impacto | Só `createSession` (`BEGIN` + `FOR UPDATE` em `admins` + revoke + insert). Refresh/revogação/TTL/TOTP/CSRF intactos |
| Risco | Baixo. Residual: unique parcial não criado (live pode ter duplicados); overlay P0-1 |
| Teste | Suite backend `208/208` PASS. Antes: 2 `createSession` paralelos → 2 activas. Depois: 1. `BEGIN` sem lock também deixava 2 |
| Rollback | Reverter o commit; `createSession` volta a revoke+insert sem transacção |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem admin-surface / sem compose |

## 2026-08-14 — BG-128 P2-4 lock de login atómico

| Campo | Valor |
|-------|--------|
| Tipo | backend API (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | Falhas de login paralelas deixam de perder incrementos; o lock 5/15 min aplica-se a tempo |
| Impacto | Só `updateLoginGuard` (`failure_count = failure_count + 1` no UPSERT). CSRF/proxy/sessão/TOTP/compose intactos |
| Risco | Baixo. Residual: live sem overlay (P0-1); limiter em memória continua separado |
| Teste | Suite backend `203/203` PASS. Antes: 10 `registerLoginFailure` paralelos → count=1. Depois: count=10 + lock |
| Rollback | Reverter o commit; o lock volta ao read-modify-write |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem CSRF / sem TOTP / sem compose |

## 2026-08-14 — BG-128 P2-2 CSRF fail-closed

| Campo | Valor |
|-------|--------|
| Tipo | backend API (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | Mutações e emissão de sessão admin exigem prova same-origin; users/search na superfície |
| Impacto | `isAdminApiPath` inclui `/api/users` e `/api/search`; POST/PUT/PATCH/DELETE sem `Origin` allowlist nem `Sec-Fetch-Site: same-origin` → 403; Bearer autenticado exceptuado |
| Risco | Baixo. Residual: GET admin sem Origin; browsers antigos sem os dois sinais falham no login; live sem overlay (P0-1) |
| Teste | Suite backend `199/199` PASS. Antes: `POST /api/users` Origin evil → 201. Depois: 403 |
| Rollback | Reverter o commit; o middleware volta a `next()` sem Origin e users/search saem do path |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem P2-13 / sem proxy / sem lifecycle |

## 2026-08-14 — BG-128 P1-9 prova residual pós-P2-3

| Campo | Valor |
|-------|--------|
| Tipo | prova + cadeado de teste (sem bump visual / sem deploy / sem runtime) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | Verificar se auth HTTP com Host oficial é exposição aberta; se não for, não alterar o canal F2.1 |
| Impacto | `origin-bind-p19.test.js` + docs. Login/TOTP/nginx/compose intactos |
| Risco | Nenhum de runtime. Residual: bind live `0.0.0.0` (não versionado); P2-2 CSRF; live sem overlay (P0-1) |
| Teste | Suite backend `184/184` PASS. Antes=depois: Host oficial + proto http → 200; Host de origin + proto https → 400; bind HEAD `127.0.0.1:8445` |
| Rollback | Remover o teste e a nota documental; o runtime já era o anterior |
| Resultado | **AVALIADO no git** — fluxo não aberto no contrato HEAD; sem `.244` / sem 30.11 / sem SPA / sem CSRF / sem DST |

## 2026-08-14 — BG-128 P2-3 X-Forwarded-Proto

| Campo | Valor |
|-------|--------|
| Tipo | backend API + nginx de origin (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | Cliente externo não força `req.secure` no origin HTTP com `X-Forwarded-Proto: https` |
| Impacto | Origin `X-Forwarded-Proto $scheme`; login só no host F2.1 (localhost só em `development`/`test`) |
| Risco | Baixo. Residual P1-9 **avaliado** (não aberto no HEAD); P2-2 CSRF; live sem overlay (P0-1) |
| Teste | `npm test` no backend — `179/179` PASS (incl. `session-forwarded-proto` + `nginx-xff-config`) |
| Rollback | Reverter o commit; o mapa volta a honrar o proto do cliente e o login volta a `req.secure` |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem CSRF / sem DST |

## 2026-08-14 — BG-128 P1-4 + P2-1 bootstrap owner

| Campo | Valor |
|-------|--------|
| Tipo | backend API (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | Um único owner no bootstrap e no boot; o primeiro admin nasce já como owner activo |
| Impacto | Lock transacional no `init`; INSERT com `is_owner=TRUE`; promoção legado `LIMIT 1`; alerta se `COUNT>1` sem demotion |
| Risco | Baixo (fail-closed na corrida). Residual: owners extra já existentes no live não são reduzidos; P0-1 impede overlay |
| Teste | `npm test` no backend — `173/173` PASS (incl. `bootstrap-admin-init` + `users-rbac-schema`) |
| Rollback | Reverter o commit; o `init` volta ao `COUNT` sem lock e o boot volta a promover todos |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem unique / sem demotion |

---

## 2026-08-14 — BG-128 P1-2 XFF / rate-limit IP

| Campo | Valor |
|-------|--------|
| Tipo | backend API + nginx de origin (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | Cliente externo não escolhe o IP de rate-limit/lock admin enviando `X-Forwarded-For` |
| Impacto | `getClientIp` = `req.ip`/socket; origin `X-Forwarded-For $remote_addr`; Proto intacto (P2-3) |
| Risco | Baixo (hop confiável). Residual: chave ≠ IP público; P2-2 CSRF; live `.244` sem overlay (P0-1) |
| Teste | `npm test` no backend — `166/166` PASS (incl. `session-client-ip` + `nginx-xff-config`) |
| Rollback | Reverter o commit; XFF do cliente volta a mandar no limiter |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem P2-3 / sem P1-4 |

---

## 2026-08-14 — BG-128 P1-3 segundo factor TOTP (`is_active` + lock)

| Campo | Valor |
|-------|--------|
| Tipo | backend API (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | `/api/auth/login/totp` recusar conta desactivada; não resetar protecção antes do TOTP válido; falha TOTP participar do lock existente sem enumerar utilizador |
| Impacto | Só `license-server/backend` auth + testes; contrato de sucesso intacto; `/login` 403 de conta desactivada **não** alterado (P3-3) |
| Risco | Baixo (fail-closed no 2FA). Residual P0-2 single-use/bind fechado neste bloco; live `.244` sem overlay (P0-1) |
| Teste | `npm test` no backend — `159/159` PASS (incl. `auth-totp-login.test.js`) |
| Rollback | Reverter o commit; o 2FA volta a emitir sessão a conta `is_active=false` e a resetar guardas após password OK |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA / sem P1-2 |

---

## 2026-08-14 — BG-128 P1-1 check-in arquivada → 409 revoked/expired

| Campo | Valor |
|-------|--------|
| Tipo | backend API (sem bump visual / sem deploy) |
| Versão | `2.1.0` git / SPA live `2.0.0` intocada |
| Objectivo | Chave antiga arquivada por replace com status `revoked`/`expired` deixa de devolver 404/`fail` no check-in com nonce |
| Impacto | Só `license-server/backend` lookup + testes; envelope v2 409; chave inexistente continua 404 |
| Risco | Baixo (SELECT extra; sem mutação). Residual: live `.244` ainda sem este overlay (P0-1) |
| Teste | `npm test` no backend (lookup + C11 + suite) |
| Rollback | Reverter o commit; o SELECT volta a exigir `archived_at IS NULL` |
| Resultado | **FEITO no git** — sem `.244` / sem 30.11 / sem SPA |

---

## 2026-08-14 — GA5.9 campo PASS (licença teste id 15)

| Campo | Valor |
|-------|--------|
| Tipo | operação live (sem código / sem bump visual) |
| Versão | `2.1.0` (sem alteração) |
| Objectivo | Repetir só GA5.9 no `.254` com API `30.13` live |
| Impacto | Cliente 9; licença id **15** expiry `2026-08-16` `base`; activada no FP `.254`; **revogada** |
| Risco | Médio (soak `.254`); mitigado: backup novo, id 13 intocado, restore integral |
| Teste | check-in 1 OK assinado; revoke 15; check-in 2 `revoked` assinado; `valid=0`; restore hashes |
| Rollback | `.lic`/JSON de produção restaurados; sessão admin temporária revogada |
| Resultado | **FEITO** — GA5.9 **PASS**; evidência `20260814T143406Z-bg127-ga59`; produção restaurada; MITM OFF |

---

## 2026-08-14 — Deploy controlado API `30.13` no `.244`

| Campo | Valor |
|-------|--------|
| Tipo | operação live (sem código novo / sem bump visual / sem frontend) |
| Versão | `2.1.0` no git; SPA live `2.0.0` **intocada** |
| Objectivo | Corrigir GA5.9 residual: API live aceitar `nonce` 30.13 (dual-mode) |
| Impacto | Só serviço `api` em `192.168.100.244`; check-in sintético 404 em vez de 400 nonce |
| Risco | Médio (API de licenças); mitigado: backup SQL, tag `pre-30.13`, sem `--delete`, sem `down -v` |
| Teste | health 200; POST+nonce → 404; POST sem nonce → legado; admin+id13 activos; stacks intactas |
| Rollback | (histórico deste deploy) `docker tag layer7-license-api:pre-30.13-20260814T142739Z layer7-license-api:latest` + `up -d --no-deps --no-build api` + `restart nginx` |
| Resultado | **FEITO** — evidência `20260814T142739Z-30.13-api-244`; `.254` e licenças **não** tocados |

**P2-16 (`2026-08-14`):** o rollback da linha acima é **histórico do overlay
`30.13`**. **Não** é o rollback padrão/`latest` actual. Preferir a imagem
pós-overlay `bbc74a5…`. A tag `pre-30.13` só cabe em incidente específico
de `30.13` (reabre rejeição de `nonce` / GA5.9 FAIL).

---

## 2026-08-14 — BG-127 licença de teste `BG-127-TEST`

| Campo | Valor |
|-------|--------|
| Tipo | operação live (sem código / sem bump visual) |
| Versão | `2.1.0` (sem alteração) |
| Objectivo | Emitir licença de teste dedicada para GA2.7 / GA5.9 sem tocar id 13 |
| Impacto | Cliente id **9** `BG-127-TEST`; licença id **14** expiry `2026-08-16` `base`; depois **revogada** no GA5.9 |
| Risco | Médio (soak `.254`); mitigado: backup, bind só ao FP do `.254`, restore integral |
| Teste | GA2.7 PASS; GA5.9 FAIL campo (API live rejeita `nonce`); id 13 intacto |
| Rollback | `.lic`/JSON de produção restaurados; sessão admin temporária revogada |
| Resultado | **FEITO** — evidência `20260814T053905Z-bg127`; produção restaurada; MITM OFF |

---

## 2026-08-12 — 30.15 alerta multi-appliance (`2.1.0`)

| Campo | Valor |
|-------|-------|
| Tipo | código + docs (sem deploy) |
| Versão | `2.1.0` |
| Objectivo | BG-121 / GA5.12 — sinal T1 no painel; só alerta (decisão 7) |
| Impacto | API dashboard + UI fila; sem package/daemon; sem hard-limit |
| Risco | Baixo — alerta informativo; rebind filtrado |
| Teste | `multi-appliance-abuse.test.js` 5/5; `npm test` backend 133/133 |
| Rollback | Reverter commit; em deploy futuro, imagem anterior |
| Resultado | **FEITO** no git; evidência `20260812T020331Z-30.15-multi-appliance-abuse`; **sem** deploy `.244`/live |

---

## 2026-08-08 — Alinhamento pós-commit + handoff

| Campo | Valor |
|-------|-------|
| Tipo | docs / governação / segurança |
| Versão | `2.0.0` (sem bump) |
| Objectivo | Fechar dívida de continuidade após push e corrigir drift SSOT |
| Impacto | Só docs; SPA hash; próximo passo = S1–S8 docs (não 20.10) |
| Risco | Baixo (docs) |
| Teste | health live OK; SPA `index-DwHpvSVY.js`; `origin/main` @ `657d7f4` |
| Rollback | Reverter esta entrada |
| Resultado | **FEITO** — commits `5fb1009` + `657d7f4` em `origin/main`; alinhamento SSOT + runbook S1–S8; S8 PASS parcial lab; builder sync `657d7f4`; `20.10` **não** iniciado |

Notas de segurança:

- Live `https://license.systemup.inf.br` = portal **`2.0.0`** / health `ok`.
- Técnicos **não** recebem `users.manage`; `/api/users` exige `users.manage`.
- Contas `is_active=false` rejeitadas no login/sessão.
- MITM: **20.10** BLOQUEADO até S1–S8 + GO lab; sem runtime / sem intercept.

---

## 2026-08-08 — PORTAL-PLAN-004 fecho `2.0.0` (U0–U2)

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `2.0.0` |
| Objectivo | Técnicos com permissões seleccionáveis (RBAC) |
| Impacto | Schema admins; API /users; gates UI/API; major visual |
| Risco | Médio (authz); owners preservados na migração |
| Teste | backend+frontend unit; health; SPA v2.0.0 |
| Rollback | imagens anteriores; colunas aditivas |
| Resultado | **FEITO** — health OK; SPA `index-DwHpvSVY.js`; plano 004 CONCLUIDO |

---

## 2026-08-08 — Abrir PORTAL-PLAN-004 (técnicos RBAC)

| Campo | Valor |
|-------|-------|
| Tipo | docs / governação |
| Versão | `1.9.0` (sem bump — só plano) |
| Objectivo | GO multi-admin técnicos + permissões seleccionáveis |
| Impacto | Docs; código em U0–U2 |
| Risco | Baixo (docs) |
| Teste | Leitura cruzada plano/IDEIAS |
| Rollback | Reverter docs |
| Resultado | **FEITO** — plano ACTIVO |

---

## 2026-08-08 — PORTAL-PLAN-003 fecho `1.9.0` (D0–D5)

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `1.9.0` |
| Objectivo | Entregar plano 003 completo (fila/contexto/entrega/2FA) |
| Impacto | UI+API admin; TOTP opcional até activar em Segurança |
| Risco | Médio (auth 2FA); mutações de negócio inalteradas |
| Teste | backend+frontend unit; health; SPA v1.9.0 |
| Rollback | imagens anteriores; desactivar 2FA se necessário |
| Resultado | **FEITO** — health OK; SPA `index-CUM8HiMh.js`; plano 003 CONCLUIDO |

---

## 2026-08-08 — Abrir PORTAL-PLAN-003 (fila/contexto/entrega)

| Campo | Valor |
|-------|-------|
| Tipo | docs / governação |
| Versão | `1.3.2` (sem bump — só plano) |
| Objectivo | Registar IDEA-040…051 + plano ordenado D0→D5 |
| Impacto | Docs portal; nenhum código runtime nesta entrada |
| Risco | Baixo |
| Teste | Leitura cruzada plano/IDEIAS/planos README |
| Rollback | Reverter commits docs |
| Resultado | **FEITO** — plano ACTIVO; execução D0 em seguida |

---

## 2026-08-08 — Nomenclatura equipamento `1.3.2`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `1.3.2` |
| Objectivo | Substituir Bound/Unbound/Rebind por termos claros em PT |
| Impacto | Só labels UI; API `bound` inalterada |
| Risco | Baixo |
| Teste | license-display; health; SPA v1.3.2 |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-Jyz4bwKJ.js`; labels Vinculada/Por activar |

---

## 2026-08-08 — Hotfix revisão `1.3.1`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `1.3.1` |
| Objectivo | Corrigir todos os achados da revisão defect-first |
| Impacto | UI datas/download/busca/select; policies update/download |
| Risco | Baixo–médio (mutações só bloqueio revoked) |
| Teste | update/download/format-date/panel-routes; health SPA |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-B-Ms_YV_.js`; `TZ=UTC` api/db; residuals P2/P3 fechados; `.env` preservado |

---

## 2026-08-08 — C2 Lista operacional + fecho plano `1.3.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `1.3.0` |
| Objectivo | Lista clientes com CNPJ/tags/activas; fechar PORTAL-PLAN-002 |
| Impacto | API list customers; UI lista; plano CONCLUIDO |
| Risco | Baixo |
| Teste | health; lista com activas/total; clique → ficha |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-CvZ07LNK.js`; plano CONCLUIDO |

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `1.2.0` |
| Objectivo | Ficha cliente útil: licenças ricas + nova licença no contexto |
| Impacto | CustomerDetail + LicenseForm query; sem mutações de negócio novas |
| Risco | Baixo |
| Teste | customer-license-summary; panel-routes; health; SPA v1.2.0 |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-BghDhRtJ.js`; testes summary/routes PASS |

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `1.1.0` |
| Objectivo | Descoberta Cliente↔Licença (clique + links) |
| Impacto | UX frontend; sem mudança de API de negócio |
| Risco | Baixo |
| Teste | lista→ficha→licença→cliente; health; SPA v1.1.0 |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-DkPpXfUZ.js`; `.env` preservado |

---

## 2026-08-08 — Abrir PORTAL-PLAN-002 (ficha cliente 360)

| Campo | Valor |
|-------|-------|
| Tipo | docs / governação |
| Versão | `1.0.0` (sem bump — só plano) |
| Objectivo | Registar IDEA-015/016 + plano ordenado C0→C1→C2 |
| Impacto | Docs portal; nenhum código runtime |
| Risco | Baixo |
| Teste | Leitura cruzada plano/IDEIAS/planos README |
| Rollback | Reverter commits docs |
| Resultado | **FEITO** — plano ACTIVO; C0 aguarda GO |

---

## 2026-08-08 — P1e Fecho `1.0.0`

| Campo | Valor |
|-------|-------|
| Tipo | docs + bump visual + deploy |
| Versão | `1.0.0` |
| Objectivo | Fechar critérios operador único e `PORTAL-PLAN-001` |
| Impacto | VERSION/CHANGELOG/historico; sidebar `v1.0.0`; plano CONCLUIDO |
| Risco | Baixo (sem mutação de negócio nova) |
| Teste | inventário SKU sem `full`; health; SPA 1.0.0 |
| Rollback | imagens anteriores / VERSION 0.5.0 |
| Resultado | **FEITO** — health OK; SPA `index-CzikQc2x.js`; inventário sem `full`; plano CONCLUIDO |

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.5.0` |
| Objectivo | Pós-revogação via substituição (nova chave + arquivar) |
| Impacto | API/UI replace; MANUAL-USO §7.1; decisão: sem desrevogar |
| Risco | Médio (chave nova + .lic antigo offline); mitigado por aviso + audit |
| Teste | license-replace-policy; health; UI substituir |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-_t9yOdDK.js`; build web `0.5.0`; `.env` restaurado pós-rsync |

### Decisão

- IDEA-012: escolhido fluxo **substituir** (não desrevogar).

### Incidente

- `rsync --delete` apagou `.env`; API em crash-loop; restore do backup
  `20260414T105834Z` + cópia fresca `20260808T220404Z`. Dados Postgres OK
  (volume). Regra: **sempre** `--exclude .env` no rsync.

---

## 2026-08-08 — P1c Rebind `0.4.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.4.0` |
| Objectivo | Workflow rebind com motivo + auditoria (GO humano) |
| Impacto | API/UI rebind; MANUAL-USO §5.6; risco .lic antigo explícito |
| Risco | Alto residual offline (grace); mitigado por aviso + audit |
| Teste | license-rebind-policy; health; UI rebind |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-C1foUQne.js`; build web `0.4.0`; nginx restarted |

---

## 2026-08-08 — P1b Auditoria `0.3.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.3.0` |
| Objectivo | UI/API de auditoria e check-ins visíveis |
| Impacto | nova rota admin `/api/audit`; detalhe licença |
| Risco | Baixo (read-only) |
| Teste | parseAuditListQuery; health; página /audit |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-haIeulBq.js`; `/audit` no ar |

---

## 2026-08-08 — P1a Renovação `0.2.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.2.0` |
| Objectivo | Renovação rápida + ficha cliente (CNPJ/tags) |
| Impacto | API renew; UI detalhe; schema customers.cnpj/tags |
| Risco | Baixo; `.lic` antigo pode coexistir até reemissão/download |
| Teste | `license-renew-policy` + crud-validation; health pós-deploy |
| Rollback | imagens anteriores; colunas novas são aditivas |
| Resultado | **FEITO** — health OK após restart nginx; SPA `index-XzglZKGO.js`; colunas cnpj/tags OK |

---

## 2026-08-08 — P0 Fundação `0.1.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.1.0` |
| Objectivo | Fechar drift SPA/API, UX chave/filtros/dashboard expiry, normalizar SKU `full`→`base` |
| Impacto | `license-server/` frontend+API; live `244`; inventário features |
| Risco | Médio no deploy (mitigado backup); baixo na UX; `.lic` antigos com `full` podem coexistir até reemissão |
| Teste | testes `crud-validation`; health; login; dashboard `expiring_30d`; lista filtros |
| Rollback | imagens Docker anteriores + restore Postgres se necessário |
| Resultado | **FEITO** — deploy live PASS; health OK; SPA nova; `full`→`base` (6 rows) |

### Notas

- Bloco P0 do `PORTAL-PLAN-001`
- Sem escala/MSP

---

## 2026-08-08 — Baseline documental `0.0.1`

| Campo | Valor |
|-------|-------|
| Tipo | Documentação / governação |
| Versão | `0.0.1` (baseline formal) |
| Objectivo | Criar organização documental da trilha portal + plano de melhoria total (operador único) |
| Impacto | Docs em `docs/10-license-server/portal/`; ligações CORTEX/docs/AGENTS; sem alteração de código runtime |
| Risco | Baixo (só docs); evitar conflito com SSOT global — hierarquia declarada em GOVERNANCE |
| Teste | Leitura cruzada dos índices; links relativos; versão `0.0.1` coerente |
| Rollback | Remover/reverter commits dos ficheiros da trilha |
| Resultado | **FEITO** — estrutura criada; plano activo de melhoria total aberto |

### Ficheiros tocados (bloco)

- `docs/10-license-server/README.md`
- `docs/10-license-server/portal/**` (governação + plano + histórico)
- `license-server/README.md`
- Ligações em `CORTEX.md`, `docs/README.md`, `document-classification.md`,
  `AGENTS.md`, `docs/02-roadmap/README.md`

### Decisões registadas

- Versão visual do portal começa em **0.0.1**
- Escopo imediato: **completude para operador único** (sem escala/vendas)
- Escala/MSP/billing → `IDEIAS.md` como `FUTURA`

---

## Modelo de entrada futura

```markdown
## YYYY-MM-DD — título curto

| Campo | Valor |
|-------|-------|
| Tipo | código / docs / deploy |
| Versão | x.y.z (ou Unreleased) |
| Objectivo | … |
| Impacto | … |
| Risco | … |
| Teste | … |
| Rollback | … |
| Resultado | FEITO / PARCIAL / BLOQUEADO |

### Notas
…
```
