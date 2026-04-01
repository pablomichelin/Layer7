# Runbook — Autenticacao e Sessao Administrativa do License Server

## Finalidade

Este runbook materializa a F2.2 e define o contrato operativo oficial da
sessao administrativa do license server.

Referencias normativas:

- `docs/03-adr/ADR-0008-autenticacao-e-sessao-license-server.md`
- `docs/03-adr/ADR-0009-protecao-superficie-administrativa-license-server.md`
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
- Politica de sessao:
  - expiracao ociosa: `30 minutos`
  - expiracao absoluta: `8 horas`
  - renovacao controlada: apenas perto da expiracao ociosa
- Politica de concorrencia: novo login revoga sessoes activas anteriores do
  mesmo admin

---

## 2. Regras operacionais

- O login administrativo deve falhar fechado fora de HTTPS/TLS real.
- O cookie de sessao nao deve ser lido nem persistido pelo JavaScript da SPA.
- O frontend deve manter apenas estado transitório em memoria.
- O origin privado `127.0.0.1:8445` nao e canal oficial para operacao humana.
- Troubleshooting no origin privado e aceite apenas no host, com `Host`
  correcto e sem degradar o contrato oficial de TLS.

---

## 3. Fluxo oficial

1. O operador faz `POST /api/auth/login` em `https://license.systemup.inf.br`.
2. O backend valida email/password e cria um registo em `admin_sessions`.
3. O servidor devolve `Set-Cookie: layer7_admin_session=...; HttpOnly; Secure; SameSite=Strict`.
4. O frontend faz bootstrap via `GET /api/auth/session`.
5. Cada rota administrativa autenticada valida a sessao no backend.
6. Perto da expiracao ociosa, o backend renova `last_seen_at` e reemite o
   cookie.
7. Em logout ou expiracao, a sessao e invalidada no backend e o cookie e
   limpo.

---

## 4. Exemplos operacionais

```bash
COOKIE_JAR=/tmp/layer7-license.cookies.txt

# Login
curl -s -c "$COOKIE_JAR" \
  https://license.systemup.inf.br/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"pablo@systemup.inf.br","password":"P@blo.147"}'

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

### Testar o origin privado no host

```bash
curl -s \
  -H 'Host: license.systemup.inf.br' \
  http://127.0.0.1:8445/api/health
```

Este teste serve apenas para o origin privado. O fluxo oficial de auth/sessao
permanece `HTTPS/TLS` na borda.

---

## 6. Fora de escopo da F2.2

Os pontos abaixo permanecem explicitamente reservados para a F2.3 ou fases
posteriores:

- CORS same-origin em producao
- rate limit dedicado para login
- brute force protection e lockout
- logging/auditoria minima da superficie administrativa
- validacao forte de payload, transacoes e CRUD

---

## 7. Rollback

- Rollback de codigo/docs: `git revert <commit-da-f2.2>`
- Rollback operacional: redeploy do stack com a revisao anterior
- Regra de rollback: nao reintroduzir JWT em `localStorage`; se houver
  incidente, manter o login indisponivel ate restaurar o modelo stateful
  sobre HTTPS/TLS
