# Manual de Uso — Sistema de Licencas Layer7

> Documento operacional para gerar, gerir, instalar e manter licencas
> do produto Layer7 para pfSense CE.

> Estado oficial apos a F2.5: o unico canal publico permitido para painel
> administrativo e activacao online passa a ser
> `https://license.systemup.inf.br`. O origin `8445/TCP` passa a ser privado
> para o reverse proxy e troubleshooting controlado; acesso humano directo por
> HTTP ao IP do host deixa de ser caminho normativo. A autenticacao
> administrativa passa a usar sessao stateful com cookie seguro, sem JWT em
> `localStorage`, same-origin only em producao, limiter dedicado no login e
> trilha minima de auditoria administrativa. O CRUD administrativo passa a
> usar validacao forte de payload/query, transacoes explicitas e arquivo
> logico no fluxo normal do painel. O fecho operacional da F2 passa a exigir
> runbooks especificos para segredos/bootstrap administrativo e para
> backup/restore do PostgreSQL.

---

## Indice

1. [Visao geral](#1-visao-geral)
2. [Painel administrativo (servidor)](#2-painel-administrativo-servidor)
3. [Criar um cliente](#3-criar-um-cliente)
4. [Gerar uma licenca](#4-gerar-uma-licenca)
5. [Activar uma licenca no pfSense](#5-activar-uma-licenca-no-pfsense)
6. [Verificar estado da licenca no pfSense](#6-verificar-estado-da-licenca-no-pfsense)
7. [Revogar uma licenca](#7-revogar-uma-licenca)
8. [Renovar uma licenca](#8-renovar-uma-licenca)
9. [Instalar licenca manualmente (offline)](#9-instalar-licenca-manualmente-offline)
10. [Comportamento do daemon sem licenca](#10-comportamento-do-daemon-sem-licenca)
11. [Referencia da API](#11-referencia-da-api)
12. [Troubleshooting](#12-troubleshooting)
13. [Seguranca](#13-seguranca)

---

## 1. Visao geral

O sistema de licencas Layer7 funciona com dois componentes:

```
┌──────────────────────────┐        ┌──────────────────────────────┐
│   SERVIDOR DE LICENCAS   │        │       pfSense CE             │
│ https://license.systemup │        │       (cliente)              │
│ .inf.br (publico, 443)   │        │                              │
│ origin privado :8445     │        │                              │
│                          │        │   layer7d (daemon)           │
│   - Painel web (React)   │ HTTPS  │   - Pede activacao           │
│   - API (Node.js)        │◄──────►│   - Recebe .lic assinado     │
│   - PostgreSQL           │        │   - Verifica Ed25519         │
│   - Ed25519 signing      │        │   - Enforce se valida        │
│                          │        │   - Monitor-only se invalida │
└──────────────────────────┘        └──────────────────────────────┘
```

**Fluxo resumido:**

1. Admin cria **cliente** e **licenca** no painel web
2. Cliente recebe a **chave de licenca** (32 hex)
3. No pfSense, executa `layer7d --activate CHAVE`
4. O daemon envia chave + hardware ID ao servidor
5. O servidor assina o ficheiro `.lic` com Ed25519 e devolve
6. O daemon grava em `/usr/local/etc/layer7.lic` e valida
7. Com licenca valida: **enforce** (bloqueio activo)
8. Sem licenca valida: **monitor-only** (sem bloqueio)

---

## 2. Painel administrativo (servidor)

### 2.1 Aceder ao painel

- **URL oficial do painel:** `https://license.systemup.inf.br`
- **Origin privado (`8445`)**: apenas reverse proxy de borda e troubleshooting
  controlado no host; nao e URL normativa para operadores humanos

### 2.2 Login

- **Credencial administrativa:** criada e recuperada apenas pelo fluxo oficial
  de bootstrap da F2.5
- **Canal oficial:** apenas `https://license.systemup.inf.br`
- **Estado de autenticacao:** cookie `layer7_admin_session`
- **Atributos do cookie:** `HttpOnly`, `Secure`, `SameSite=Strict`
- **Origin de browser em producao:** apenas `https://license.systemup.inf.br`
- **Rate limit de login:** `10 requests / 10 minutos` por IP e
  `5 requests / 10 minutos` por `email + IP`
- **Lockout de login:** `15 minutos` apos `5` falhas por conta alvo ou
  `10` falhas por IP dentro de `15 minutos`
- **Expiracao ociosa:** `30 minutos`
- **Expiracao absoluta:** `8 horas`
- **Renovacao:** controlada pelo backend perto da expiracao ociosa
- **Logout:** invalida a sessao no backend e limpa o cookie
- **Concorrencia:** novo login revoga sessoes activas anteriores do mesmo
  admin
- **Auditoria minima:** auth/sessao e mutacoes administrativas ficam em
  `admin_audit_log`; guardas de login/lockout ficam em `admin_login_guards`
- **Bootstrap e recuperacao de password:** ver
  `docs/05-runbooks/license-server-segredos-bootstrap.md`

### 2.3 Paginas do painel

| Pagina | Funcao |
|--------|--------|
| **Dashboard** | Resumo: licencas activas/expiradas/revogadas, total clientes, ultimas 10 activacoes |
| **Licencas** | Lista paginada, filtro por status, criar/ver/revogar/arquivar |
| **Clientes** | Lista paginada, busca por nome/email, criar/editar/arquivar |

### 2.4 Regras operacionais do CRUD apos a F2.4

- payloads administrativos passam a usar validacao forte e schema fechado
- campos inesperados, IDs invalidos, paginacao fora da politica e JSON
  malformado passam a falhar com `400`
- conflitos logicos de activacao, revogacao, download e arquivo passam a
  responder com `409`
- o delete normal do painel deixa de apagar historico e passa a arquivar
  clientes/licencas com `archived_at`, ocultando-os das listagens normais

---

## 3. Criar um cliente

Antes de gerar uma licenca, e necessario criar o cliente.

### Via painel web

1. Aceder a pagina **Clientes**
2. Clicar em **Novo Cliente**
3. Preencher:
   - **Nome** (obrigatorio) — ex: `Empresa ABC Ltda`
   - **Email** — ex: `ti@empresaabc.com`
   - **Telefone** — ex: `11999998888`
   - **Notas** — informacoes internas
4. Clicar em **Criar Cliente**

### Via API (curl)

```bash
# Criar sessao administrativa
COOKIE_JAR=/tmp/layer7-license.cookies.txt
ADMIN_EMAIL='admin@systemup.inf.br'
ADMIN_PASSWORD='substituir_por_segredo_real'

curl -s -c "$COOKIE_JAR" https://license.systemup.inf.br/api/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${ADMIN_EMAIL}\",\"password\":\"${ADMIN_PASSWORD}\"}"

# Criar cliente
curl -s -b "$COOKIE_JAR" https://license.systemup.inf.br/api/customers \
  -H "Content-Type: application/json" \
  -d '{"name":"Empresa ABC Ltda","email":"ti@empresaabc.com","phone":"11999998888"}'
```

---

## 4. Gerar uma licenca

### Via painel web

1. Aceder a pagina **Licencas**
2. Clicar em **Nova Licenca**
3. Preencher:
   - **Cliente** — seleccionar da lista (dropdown)
   - **Data de expiracao** — ex: `2027-12-31`
   - **Features** — normalmente `full` (default)
   - **Notas** — informacoes internas
4. Clicar em **Criar Licenca**
5. O sistema gera automaticamente uma **chave de 32 hex** — ex: `a1b2c3d4e5f6789012345678abcdef01`
6. **Anotar/copiar a chave** — sera necessaria para activar no pfSense

### Via API (curl)

```bash
curl -s -b "$COOKIE_JAR" https://license.systemup.inf.br/api/licenses \
  -H "Content-Type: application/json" \
  -d '{"customer_id":1,"expiry":"2027-12-31","features":"full","notes":"Firewall principal"}'
```

Resposta:
```json
{
  "id": 3,
  "license_key": "a1b2c3d4e5f6789012345678abcdef01",
  "expiry": "2027-12-31",
  "status": "active",
  "hardware_id": null
}
```

> **IMPORTANTE:** A licenca e criada sem `hardware_id`. O hardware sera
> vinculado automaticamente na primeira activacao. Depois disso, a
> licenca so funciona naquele hardware especifico.

---

## 5. Activar uma licenca no pfSense

### 5.1 Activacao online (recomendado)

No pfSense, via SSH ou console:

```bash
# Activacao usando URL publica oficial
layer7d --activate a1b2c3d4e5f6789012345678abcdef01 https://license.systemup.inf.br/api/activate

# Activacao usando URL default (embutida no binario)
layer7d --activate a1b2c3d4e5f6789012345678abcdef01
```

**Saida esperada (sucesso):**
```
layer7d: activating...
  server:       https://license.systemup.inf.br/api/activate
  hardware_id:  7209217784b0ca2c9584fa39437e2b001757b5c8a2c2af8835ff0e25ae620966
  key:          a1b2c3d4...
layer7d: license saved to /usr/local/etc/layer7.lic
layer7d: license valid — customer=Empresa ABC Ltda expiry=2027-12-31 features=full
```

### 5.2 O que acontece na activacao

1. O daemon calcula o **hardware ID** do pfSense: `SHA256(kern.hostuuid + ":" + MAC)`
2. Envia `POST /api/activate` com `{"key":"...", "hardware_id":"..."}`
3. O servidor:
   - Valida a chave
   - Se e a primeira activacao, grava o hardware_id na licenca
   - Gera e assina o ficheiro `.lic` com Ed25519
   - Retorna o JSON assinado
4. O daemon grava em `/usr/local/etc/layer7.lic`
5. Verifica a assinatura com a chave publica embutida
6. Se valido, o daemon opera em modo **enforce** (bloqueio activo)

### 5.3 Re-activacao

Pode executar `--activate` novamente a qualquer momento (ex: apos
renovacao). O servidor ira gerar um novo `.lic` com a data de
expiracao actualizada, desde que o hardware_id corresponda.

---

## 6. Verificar estado da licenca no pfSense

### 6.1 Via CLI

```bash
# Ver hardware ID da maquina
layer7d --fingerprint

# Resultado: 7209217784b0ca2c9584fa39437e2b001757b5c8a2c2af8835ff0e25ae620966
```

### 6.2 Via GUI do pfSense

Na pagina **Definicoes** do Layer7, a seccao de licenca mostra:
- Estado: Valida / Expirada / Grace Period / Sem licenca
- Hardware ID
- Cliente
- Data de expiracao
- Features
- Dias restantes

### 6.3 Via ficheiro .lic

```bash
# Ver conteudo do ficheiro de licenca
cat /usr/local/etc/layer7.lic | python3 -m json.tool
```

Resultado:
```json
{
  "data": "{\"hardware_id\":\"7209...\",\"expiry\":\"2027-12-31\",\"customer\":\"Empresa ABC\",\"features\":\"full\",\"issued\":\"2026-03-24\"}",
  "sig": "9c0d5af9...128_hex_chars..."
}
```

### 6.4 Via logs do daemon

```bash
# O daemon loga o estado da licenca ao iniciar
grep -i license /var/log/layer7d.log
```

---

## 7. Revogar uma licenca

### Via painel web

1. Aceder a pagina **Licencas**
2. Encontrar a licenca na lista
3. Clicar em **Revogar** (ou abrir detalhes e clicar em **Revogar**)
4. Confirmar a revogacao

### Via API (curl)

```bash
# Revogar licenca ID 3
curl -s -X POST -b "$COOKIE_JAR" \
  https://license.systemup.inf.br/api/licenses/3/revoke
```

### O que acontece ao revogar

- O status da licenca muda para `revoked` no servidor
- O ficheiro `.lic` existente no pfSense **continua valido** ate
  expirar (o daemon verifica assinatura + data, nao consulta o
  servidor em tempo real)
- Para efeito imediato: remover o `.lic` do pfSense manualmente

```bash
# No pfSense, remover licenca manualmente apos revogacao
rm /usr/local/etc/layer7.lic
service layer7d restart
```

- Qualquer tentativa futura de `--activate` com essa chave retornara
  erro 409 (`Licenca revogada`)

---

## 8. Renovar uma licenca

Para renovar (estender a data de expiracao):

### Via painel web

1. Aceder a **Licencas** → clicar na licenca → **Detalhes**
2. (Actualmente: editar via API — ver abaixo)

### Via API (curl)

```bash
# Estender expiracao da licenca ID 3 para 2028-12-31
curl -s -X PUT -b "$COOKIE_JAR" https://license.systemup.inf.br/api/licenses/3 \
  -H "Content-Type: application/json" \
  -d '{"expiry":"2028-12-31"}'
```

### Apos renovar no servidor

O cliente precisa **re-activar** no pfSense para obter o novo `.lic`:

```bash
# No pfSense
layer7d --activate a1b2c3d4e5f6789012345678abcdef01 https://license.systemup.inf.br/api/activate
```

O novo `.lic` tera a data de expiracao actualizada.

---

## 9. Instalar licenca manualmente (offline)

Se o pfSense nao tem acesso ao servidor de licencas, pode instalar
o ficheiro `.lic` manualmente.

### 9.1 Gerar o .lic no servidor

Prerequisito: a licenca deve ter sido activada pelo menos uma vez
(para que o hardware_id esteja vinculado).

**Via painel web:**
1. Ir a **Licencas** → clicar na licenca → **Detalhes**
2. Clicar em **Download .lic**
3. O browser baixa `layer7-XXXXXXXX.lic`

**Via API:**
```bash
curl -s -b "$COOKIE_JAR" https://license.systemup.inf.br/api/licenses/3/download \
  -o layer7.lic
```

### 9.2 Copiar para o pfSense

```bash
# Via SCP
scp layer7.lic admin@pfsense:/usr/local/etc/layer7.lic

# Ou via SSH + cat
ssh admin@pfsense "cat > /usr/local/etc/layer7.lic" < layer7.lic
```

### 9.3 Reiniciar o daemon

```bash
ssh admin@pfsense "service layer7d restart"
```

### 9.4 Primeira activacao offline (sem servidor)

Se o pfSense nao consegue contactar o servidor, faca assim:

1. No pfSense, obtenha o hardware ID:
   ```bash
   layer7d --fingerprint
   # Resultado: 7209217784b0ca2c...
   ```

2. No servidor, vincule manualmente o hardware_id a licenca:
   ```bash
   # Via API: editar a licenca (nao ha campo directo,
   # mas a activacao via curl simula o daemon)
   curl -s https://license.systemup.inf.br/api/activate \
     -H "Content-Type: application/json" \
     -d '{"key":"a1b2c3d4e5f6789012345678abcdef01","hardware_id":"7209217784b0ca2c..."}'
   ```
   Isso retorna o `.lic` assinado. Salve-o:
   ```bash
   curl -s https://license.systemup.inf.br/api/activate \
     -H "Content-Type: application/json" \
     -d '{"key":"a1b2c3d4...","hardware_id":"7209..."}' \
     -o layer7.lic
   ```

3. Copie o `layer7.lic` para o pfSense e reinicie o daemon.

---

## 10. Comportamento do daemon sem licenca

| Situacao | Comportamento |
|----------|--------------|
| Sem ficheiro `.lic` | **Monitor-only** — classifica trafego mas nao bloqueia |
| `.lic` com assinatura invalida | **Monitor-only** |
| `.lic` com hardware_id diferente | **Monitor-only** |
| `.lic` valida e dentro da data | **Enforce** — bloqueio activo |
| `.lic` expirada ha menos de 14 dias | **Grace period** — enforce activo + aviso no log |
| `.lic` expirada ha mais de 14 dias | **Monitor-only** |
| Licenca revogada no servidor | Sem efeito no pfSense ate proximo `--activate` |

### Grace period

O daemon concede **14 dias de graca** apos a expiracao da licenca.
Durante esse periodo, o enforce continua activo mas o daemon loga
avisos. Apos os 14 dias, volta a monitor-only.

### Verificacao periodica

O daemon verifica a licenca:
- Ao iniciar
- A cada 1 hora durante a execucao

---

## 11. Referencia da API

### Endpoints publicos

| Metodo | Rota | Descricao |
|--------|------|-----------|
| `POST` | `/api/activate` | Activacao de licenca (chamado pelo daemon) |
| `GET` | `/api/health` | Health check do servidor |

### Endpoints autenticados (cookie de sessao administrativa)

| Metodo | Rota | Descricao |
|--------|------|-----------|
| `POST` | `/api/auth/login` | Login administrativo via HTTPS; cria sessao stateful e devolve cookie `HttpOnly` |
| `GET` | `/api/auth/session` | Resolve sessao activa, renova janela ociosa quando aplicavel |
| `POST` | `/api/auth/logout` | Invalida sessao activa e limpa cookie |
| `GET` | `/api/dashboard` | Metricas e ultimas activacoes |
| `GET` | `/api/licenses?status=&customer_id=&search=&page=&limit=` | Listar licencas |
| `POST` | `/api/licenses` | Criar licenca |
| `GET` | `/api/licenses/:id` | Detalhes + historico activacoes |
| `PUT` | `/api/licenses/:id` | Editar (expiry, features, customer_id, notes) |
| `POST` | `/api/licenses/:id/revoke` | Revogar licenca |
| `DELETE` | `/api/licenses/:id` | Arquivar licenca nao activa; preserva historico |
| `GET` | `/api/licenses/:id/download` | Download ficheiro .lic |
| `GET` | `/api/customers?search=&page=&limit=` | Listar clientes |
| `POST` | `/api/customers` | Criar cliente |
| `GET` | `/api/customers/:id` | Detalhes + licencas |
| `PUT` | `/api/customers/:id` | Editar cliente |
| `DELETE` | `/api/customers/:id` | Arquivar cliente; bloqueia se ainda houver licencas activas |

### Rate limiting

O endpoint `/api/activate` tem limite de **10 requisicoes por minuto
por IP** para prevenir abuso.

O endpoint `/api/auth/login` passa a operar com:

- **10 requests / 10 minutos por IP**
- **5 requests / 10 minutos por `email + IP`**
- **lockout de 15 minutos** apos repeticao anomala por conta/IP
- **erro `429` generico** sem enumeracao de credenciais

As mutacoes administrativas e `POST /api/activate` passam a operar em
fail-closed: payload invalido devolve `400`, recurso inexistente devolve
`404` e conflito logico devolve `409`.

---

## 12. Troubleshooting

### "Sessao invalida ou expirada" (401)

- O frontend volta para o ecrã de login
- Repetir o login em `https://license.systemup.inf.br`
- Confirmar que o acesso esta a passar por HTTPS/TLS real
- Se necessario, invalidar sessoes activas e autenticar novamente

### "Licenca nao encontrada" (404)

A chave de licenca esta incorrecta ou nao existe no servidor.
- Verificar a chave com `layer7d --activate CHAVE_CORRECTA`
- Verificar no painel web se a licenca existe

### "Hardware ID nao corresponde" (409)

A licenca ja foi activada noutro hardware.
- Cada licenca e vinculada ao primeiro hardware que a activa
- Para mudar de hardware: criar nova licenca ou pedir ao admin
  para limpar o hardware_id (via base de dados)

### "Licenca revogada" (409)

A licenca foi revogada pelo admin. Contactar o admin.

### "Licenca expirada" (409)

### "Licenca ainda nao foi activada" (409)

O download administrativo do `.lic` exige `hardware_id` ja vinculado.

### "Nao e possivel arquivar licenca activa" (409)

Revogar primeiro a licenca antes de arquivar no painel.

A data de expiracao ja passou. O admin precisa renovar (editar
expiry) e o cliente precisa re-activar.

### "Ed25519 signature verification failed"

O ficheiro `.lic` foi corrompido ou gerado com chave diferente.
- Re-activar: `layer7d --activate CHAVE URL`
- Verificar que o binario `layer7d` contem a chave publica correcta

### "could not reach license server"

O pfSense nao consegue contactar o servidor.
- Verificar conectividade publica: `curl -fsS https://license.systemup.inf.br/api/health`
- Verificar firewall/rotas entre pfSense e o servidor
- Para troubleshooting no host do servidor, validar o origin privado:
  `curl -s -H 'Host: license.systemup.inf.br' http://127.0.0.1:8445/api/health`
- Alternativa: instalar `.lic` manualmente (seccao 9)

### Daemon em monitor-only mesmo com licenca

```bash
# Verificar no pfSense
cat /usr/local/etc/layer7.lic    # ficheiro existe?
layer7d --fingerprint            # hardware_id bate?
grep license /var/log/layer7d.log  # qual o erro?
```

---

## 13. Seguranca

### Chaves criptograficas

| Item | Localizacao | Proteccao |
|------|-------------|-----------|
| Chave privada Ed25519 | `.env` no servidor (`ED25519_PRIVATE_KEY`) ou ficheiro montado (`ED25519_PRIVATE_KEY_FILE`) | Nunca sai do servidor. Nao commitar. |
| Chave publica Ed25519 | Embutida no binario `layer7d` (compile-time) | Distribuida com o pacote |
| Sessao administrativa | Tabela `admin_sessions` + cookie `layer7_admin_session` | Cookie `HttpOnly + Secure + SameSite=Strict`; token opaco so e validado no backend |
| Password admin | Armazenada com bcrypt (salt 12) no PostgreSQL | Nunca em texto limpo; recuperacao apenas via `bootstrap-admin.js` |

### Modelo de confianca

- O `.lic` e **assinado** com Ed25519 — nao pode ser forjado
- O `.lic` contem o **hardware_id** — nao pode ser copiado para outro pfSense
- O daemon verifica **offline** — nao precisa de conexao permanente
- A activacao requer **conexao unica** ao servidor

### Publicacao segura

- o unico canal publico oficial e `https://license.systemup.inf.br`
- `8445/TCP` e origin privado do reverse proxy, nao URL publica normativa
- certificados, redirect `HTTP -> HTTPS` e ACL do origin sao dependencias
  operacionais obrigatorias da F2.1

### Segredos, bootstrap e backup/restore

- a custodia operacional de `POSTGRES_PASSWORD`,
  `ED25519_PRIVATE_KEY`/`ED25519_PRIVATE_KEY_FILE` e
  `ADMIN_BOOTSTRAP_PASSWORD` passa a ser regida por
  `docs/05-runbooks/license-server-segredos-bootstrap.md`
- o backup/restore minimo do banco passa a ser regido por
  `docs/05-runbooks/license-server-backup-restore.md`
- se a `ED25519_PRIVATE_KEY` for perdida, as licencas ja emitidas continuam
  validas offline, mas novas emissoes e re-activacoes ficam bloqueadas ate
  procedimento formal de rotacao

---

## Exemplo completo: novo cliente do zero

```bash
# 1. Login no servidor
COOKIE_JAR=/tmp/layer7-license.cookies.txt
ADMIN_EMAIL='admin@systemup.inf.br'
ADMIN_PASSWORD='substituir_por_segredo_real'

curl -s -c "$COOKIE_JAR" https://license.systemup.inf.br/api/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${ADMIN_EMAIL}\",\"password\":\"${ADMIN_PASSWORD}\"}"

# 2. Criar cliente
curl -s -b "$COOKIE_JAR" https://license.systemup.inf.br/api/customers \
  -H "Content-Type: application/json" \
  -d '{"name":"Escola Municipal XYZ","email":"ti@escolaxyz.com.br","phone":"21988887777"}'
# Resposta: {"id":2, ...}

# 3. Criar licenca (1 ano)
curl -s -b "$COOKIE_JAR" https://license.systemup.inf.br/api/licenses \
  -H "Content-Type: application/json" \
  -d '{"customer_id":2,"expiry":"2027-03-24","features":"full","notes":"Contrato anual"}'
# Resposta: {"license_key":"abcdef1234567890abcdef1234567890", ...}

# 4. Enviar a chave ao cliente: abcdef1234567890abcdef1234567890

# 5. No pfSense do cliente (via SSH):
layer7d --activate abcdef1234567890abcdef1234567890 https://license.systemup.inf.br/api/activate
# Saida: license valid — customer=Escola Municipal XYZ expiry=2027-03-24 features=full

# 6. Verificar no dashboard do servidor — a activacao aparece na lista
```

---

*Documento criado em 2026-03-24. Layer7 License Server v1.0 — Systemup Solucao em Tecnologia.*
