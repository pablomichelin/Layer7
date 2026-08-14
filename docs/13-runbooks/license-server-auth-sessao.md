# Runbook — Autenticacao e Sessao Administrativa do License Server

## Finalidade

Este runbook materializa a F2.2, a F2.3 e convive com a F2.4 para definir o contrato operativo
oficial da sessao administrativa e da superficie administrativa do license
server.

Referencias normativas:

- `docs/03-adr/ADR-0008-autenticacao-e-sessao-license-server.md`
- `docs/03-adr/ADR-0009-protecao-superficie-administrativa-license-server.md`
- `docs/03-adr/ADR-0010-integridade-transacional-e-validacao-crud-license-server.md`
- `docs/01-architecture/f2-arquitetura-license-server.md`
- `docs/10-license-server/MANUAL-USO-LICENCAS.md`

---

## 1. Modelo oficial

- Canal administrativo oficial: `https://license.systemup.inf.br`
- Login administrativo: `POST /api/auth/login`
- Sessao activa: `GET /api/auth/session`
- Logout: `POST /api/auth/logout`
- Estado de autenticacao: cookie `layer7_admin_session`
- Atributos do cookie: `HttpOnly`, `Secure`, `SameSite=Strict`, `Path=/`
- Storage de sessao: tabela PostgreSQL `admin_sessions`
- CORS de browser em producao: `same-origin only` para
  `https://license.systemup.inf.br`
- Rate limit de login:
  - `10 requests / 10 minutos` por IP
  - `5 requests / 10 minutos` por `email + IP`
- Lockout de login:
  - `5` falhas por conta alvo em `15 minutos`
  - `10` falhas por IP em `15 minutos`
  - bloqueio temporario de `15 minutos`
  - **P2-4 / BG-128:** o incremento de `failure_count` é atómico no
    `UPSERT` (`failure_count = failure_count + 1` dentro da janela de
    15 min). Falhas paralelas no mesmo email/IP já não perdem
    incrementos nem atrasam o lock. CSRF/proxy/sessão/TOTP intactos.
- **P3-1 / BG-128:** um admin tem no máximo uma linha
  `admin_sessions.revoked_at IS NULL`. `createSession` abre transacção,
  tranca a linha de `admins` (`FOR UPDATE`), revoga as activas e
  insere a nova. `BEGIN`+revoke+insert sem lock **não** serializa.
  Unique parcial `(admin_id) WHERE revoked_at IS NULL` fica fora
  (exigiria limpar duplicados live). Refresh/TTL/TOTP/CSRF intactos.
- **P3-2 / BG-128:** `GET /api/auth/session` inclui `a.totp_enabled`
  no SELECT de `resolveSessionToken`. Admin com TOTP ligado deixa de
  aparecer como `totp_enabled: false`. Auth/TTL/revoke/`createSession`/
  CSRF/TOTP flows intactos.
- **P3-3A / BG-134:** `POST /api/auth/login` trata conta desactivada e
  email inexistente com a mesma semântica `401` `Credenciais invalidas`.
  Ambos fazem trabalho bcrypt (hash real da conta ou hash dummy
  constante) e chamam `registerLoginFailure` (lock 5/15 conta, 10/15
  IP). A auditoria interna pode continuar `account_disabled`; o body
  HTTP não vaza estado. Sucesso, falha de conta activa, lock, TOTP e
  CSRF intactos.
- **P3-3B / BG-136:** `POST`/`PUT /api/users` exige password >=12
  (mesma política do `bootstrap-admin.js`). Password de 10 caracteres
  → `400`. `PUT` sem campo `password` não altera o hash. `/login` **não**
  rejeita password existente de 10 caracteres. `Users.jsx` permanece
  `minLength={10}` neste bloco.
- Auditoria minima:
  - auth/sessao e mutacoes administrativas em `admin_audit_log`
  - guardas de lockout em `admin_login_guards`
- Politica de sessao:
  - expiracao ociosa: `30 minutos`
  - expiracao absoluta: `8 horas`
  - renovacao controlada: apenas perto da expiracao ociosa
- Politica de concorrencia: novo login revoga sessoes activas anteriores do
  mesmo admin

---

## 2. Regras operacionais

- O login administrativo deve falhar fechado fora de HTTPS/TLS real.
- Browser com `Origin` fora da allowlist same-origin deve falhar fechado.
- **P2-2 / BG-128:** mutações administrativas (`POST`/`PUT`/`PATCH`/`DELETE`
  em `/api/auth/*`, `/api/licenses*`, `/api/customers*`, `/api/users*`)
  e emissão de sessão falham fechadas sem `Origin` na allowlist nem
  `Sec-Fetch-Site: same-origin`. APIs autenticadas com `Authorization:
  Bearer` ficam exceptuadas. GET sem `Origin` (ops/curl) continua.
  `/api/activate`, `/api/license/check-in`, content e `/api/health`
  estão fora desta superfície.
- Respostas de auth devem permanecer genericas:
  - `401` para credenciais invalidas (conta inexistente, desactivada ou password errada)
  - `429` para limite/lockout
  - `403` para origin administrativo nao autorizado
- O cookie de sessao nao deve ser lido nem persistido pelo JavaScript da SPA.
- O frontend deve manter apenas estado transitório em memoria.
- O HMAC do `challenge_token` TOTP reutiliza `ADMIN_BEARER_JWT_SECRET` ou
  `JWT_SECRET`. Nao existe fallback estatico. Sem esses valores o challenge
  nao e emitido nem aceite.
- Segundo factor (`POST /api/auth/login/totp`, P1-3):
  - recusa `is_active === false` sem emitir cookie/sessao;
  - password OK com TOTP ligado **nao** faz reset das guardas;
  - falha TOTP incrementa `admin_login_guards` (conta por email + IP) e o
    lock activo devolve o mesmo `429` do `/login`;
  - respostas de falha do 2FA sao `401` genericas (`Credenciais invalidas`),
    sem distinguir conta desactivada, desafio invalido ou codigo errado;
  - o contrato de sucesso (cookie + payload `buildAdminAuthResponse`)
    permanece o do login sem TOTP.
- Em producao a API recusa arrancar se ambos os segredos estiverem vazios.
- A ponte Bearer administrativa, quando activada para compatibilidade, deve:
  - preferir `ADMIN_BEARER_JWT_SECRET`;
  - aceitar `JWT_SECRET` apenas como compatibilidade transitória de upgrade
    para stacks antigas;
  - usar token assinado e de curta duracao derivado da sessao stateful;
  - ficar apenas em memoria transitoria no frontend;
  - nunca reutilizar `ED25519_PRIVATE_KEY`;
  - nunca cair para token opaco cru em `Authorization`.
  - nunca ser reenviada automaticamente no `POST /api/auth/login`.
- O origin privado `127.0.0.1:8445` nao e canal oficial para operacao humana.
- Troubleshooting no origin privado e aceite apenas no host, com `Host`
  correcto e sem degradar o contrato oficial de TLS.

---

## 3. Fluxo oficial

1. O operador faz `POST /api/auth/login` em `https://license.systemup.inf.br`.
2. O backend valida email/password e cria um registo em `admin_sessions`.
3. O servidor devolve `Set-Cookie: layer7_admin_session=...; HttpOnly; Secure; SameSite=Strict`.
4. Quando `ADMIN_BEARER_JWT_SECRET` estiver configurada, ou quando um deploy
   antigo ainda expuser apenas `JWT_SECRET`, o backend pode devolver tambem
   um Bearer assinado de compatibilidade, sem alterar o papel normativo do
   cookie stateful.
5. O frontend faz bootstrap via `GET /api/auth/session`.
6. Cada rota administrativa autenticada valida a sessao no backend.
7. Perto da expiracao ociosa, o backend renova `last_seen_at` e reemite o
   cookie.
8. Em logout ou expiracao, a sessao e invalidada no backend e o cookie e
   limpo.

---

## 4. Exemplos operacionais

```bash
COOKIE_JAR=/tmp/layer7-license.cookies.txt
ADMIN_EMAIL='admin@systemup.inf.br'
ADMIN_PASSWORD='substituir_por_segredo_real'

# Login
curl -s -c "$COOKIE_JAR" \
  https://license.systemup.inf.br/api/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${ADMIN_EMAIL}\",\"password\":\"${ADMIN_PASSWORD}\"}"

# Sessao activa
curl -s -b "$COOKIE_JAR" \
  https://license.systemup.inf.br/api/auth/session

# Dashboard administrativo
curl -s -b "$COOKIE_JAR" \
  https://license.systemup.inf.br/api/dashboard

# Logout
curl -s -X POST -b "$COOKIE_JAR" \
  https://license.systemup.inf.br/api/auth/logout
```

---

## 5. Troubleshooting

### Login falha com mensagem de HTTPS/TLS

- Validar que o acesso esta a passar pelo canal oficial
  `https://license.systemup.inf.br`
- Validar forwarding `X-Forwarded-Proto=https` na borda
- Nao usar `http://IP:8445` como URL administrativa

### Login falha com `429`

- Verificar se o IP excedeu `10 requests / 10 minutos`
- Verificar se o par `email + IP` excedeu `5 requests / 10 minutos`
- Verificar lockout temporario em `admin_login_guards`:

```sql
SELECT
  scope_type,
  scope_key,
  failure_count,
  locked_until,
  last_failure_at,
  last_success_at
FROM admin_login_guards
WHERE locked_until IS NOT NULL
ORDER BY locked_until DESC;
```

- Se o bloqueio for legitimo, aguardar a expiracao; nao alargar limites nem
  reabrir origins como medida de incidente

### Sessao invalida ou expirada

- O frontend deve voltar ao ecrã de login
- O operador deve autenticar novamente
- Se houver suspeita operacional, invalidar as sessoes activas no banco:

```sql
UPDATE admin_sessions
SET revoked_at = NOW()
WHERE revoked_at IS NULL;
```

### Ver sessoes activas

```sql
SELECT
  id,
  admin_id,
  created_at,
  last_seen_at,
  expires_at,
  revoked_at,
  ip_address,
  user_agent
FROM admin_sessions
ORDER BY created_at DESC;
```

### Ver trilha minima de auditoria

```sql
SELECT
  created_at,
  component,
  event_type,
  actor_identifier,
  ip_address,
  result,
  reason
FROM admin_audit_log
ORDER BY created_at DESC
LIMIT 50;
```

### Validar fail-closed de origin administrativo

```bash
curl -s -o /tmp/layer7-origin-check.out -w '%{http_code}\n' \
  https://license.systemup.inf.br/api/auth/login \
  -H 'Content-Type: application/json' \
  -H 'Origin: https://evil.example' \
  -d '{"email":"admin@systemup.inf.br","password":"invalid"}'
```

Resultado esperado: `403`

```bash
curl -s -o /tmp/layer7-csrf-users.out -w '%{http_code}\n' \
  https://license.systemup.inf.br/api/users \
  -H 'Content-Type: application/json' \
  -H 'Origin: https://evil.example' \
  -H 'Cookie: layer7_admin_session=dummy' \
  -d '{"email":"tech@example.com","name":"x","password":"0123456789ab"}'
```

Resultado esperado: `403` (P2-2; users na superfície admin).

### Testar o origin privado no host

```bash
curl -s \
  -H 'Host: license.systemup.inf.br' \
  http://127.0.0.1:8445/api/health
```

Este teste serve apenas para o origin privado. O fluxo oficial de auth/sessao
permanece `HTTPS/TLS` na borda.

---

## 6. Integracao com a F2.5

Os pontos abaixo passam a ter runbook proprio:

- ownership e custodia minima de segredos:
  `docs/13-runbooks/license-server-segredos-bootstrap.md`
- bootstrap administrativo e recuperacao de password:
  `docs/13-runbooks/license-server-segredos-bootstrap.md`
- backup/restore e recuperacao operacional do PostgreSQL:
  `docs/13-runbooks/license-server-backup-restore.md`

---

## 7. Rollback

- Rollback de codigo/docs: `git revert <commit-da-f2.4>`
- Rollback operacional: redeploy do stack com a revisao anterior
- Regra de rollback: nao reintroduzir JWT em `localStorage`, nao reabrir
  `cors()` global e nao remover limiter/lockout silenciosamente; se houver
  incidente, manter o login indisponivel ate restaurar o modelo stateful
  sobre HTTPS/TLS e same-origin oficial
