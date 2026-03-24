# Plano Completo: Servidor de Licencas Layer7

> **DOCUMENTO AUTO-SUFICIENTE** — Este documento contem TODO o contexto
> necessario para implementar o servidor de licencas. Nao e necessario
> contexto anterior. Leia este documento do inicio ao fim antes de comecar.

---

## 1. O que e o projecto Layer7

Layer7 e um produto comercial da **Systemup Solucao em Tecnologia**
(www.systemup.inf.br), desenvolvido por **Pablo Michelin**.

E um daemon (C + nDPI) para pfSense CE que classifica trafego de rede na
camada 7 e aplica politicas de bloqueio/monitoramento. O produto inclui
uma GUI PHP integrada ao pfSense com 10 paginas.

**Versao actual: 1.0.0** — V1 Comercial concluida e publicada.

O daemon verifica licencas via ficheiros `.lic` assinados com Ed25519.
Sem licenca valida, o daemon opera em modo monitor-only (sem bloqueio).

**O que falta**: um servidor web para gerir licencas, clientes e
activacoes online. E isso que este plano descreve.

---

## 2. Objectivo

Criar um **servidor de licenciamento** que:

1. Permita ao admin (Pablo) gerir clientes e licencas via browser
2. Receba pedidos de activacao do daemon pfSense (`layer7d --activate KEY`)
3. Gere e assine ficheiros `.lic` com Ed25519 automaticamente
4. Rode em Docker Compose no servidor `192.168.100.244`
5. Fique acessivel via `https://license.systemup.inf.br`

---

## 3. Servidor de deploy (192.168.100.244)

### 3.1 Dados de acesso

- **IP**: 192.168.100.244
- **SSH**: root / @sp@2020Sp
- **OS**: Ubuntu 24.04 LTS, 24GB RAM, 42GB livres
- **Docker + Docker Compose**: ja instalados

### 3.2 Servicos em execucao — NAO TOCAR

**REGRA CRITICA: E EXPRESSAMENTE PROIBIDO danificar, mexer, alterar,
derrubar ou modificar qualquer outro aplicativo ou servico que esteja
rodando no servidor.**

| Servico              | Porta  | Status      |
|----------------------|--------|-------------|
| SSH                  | 22     | NAO TOCAR   |
| DNS (systemd-resolved)| 53   | NAO TOCAR   |
| Apache2 (Zabbix GUI)| 80     | NAO TOCAR   |
| Grafana              | 3000   | NAO TOCAR   |
| MySQL 8.0 (Zabbix)  | 3306   | NAO TOCAR   |
| Docker: monitor-pfsense | 8088 | NAO TOCAR |
| Zabbix Agent         | 10050  | NAO TOCAR   |
| Zabbix Server        | 10051  | NAO TOCAR   |

### 3.3 Porta para o license server

**Porta 8445** — livre, sem conflito com nenhum servico existente.

### 3.4 Rede Docker existente

```
NETWORK: bridge, host, monitor-pfsense_default, none
```

O license server criara sua propria rede `layer7-license-net` (bridge
isolado). NAO usar nem conectar-se a nenhuma rede Docker existente.

---

## 4. Dominio e acesso externo

- **Dominio**: `license.systemup.inf.br`
- Um **ISPConfig de borda** fara proxy reverso HTTPS (porta 443) para
  `192.168.100.244:8445`
- O TLS/HTTPS e terminado no ISPConfig; a comunicacao interna e HTTP
- O Docker Compose expoe apenas a porta **8445** no host

**Fluxo:**
```
Browser/pfSense --> license.systemup.inf.br:443 (HTTPS, ISPConfig)
    --> proxy_pass --> 192.168.100.244:8445 (HTTP, Nginx no Docker)
        --> /api/* --> Node.js API
        --> /* --> React SPA (build estatico)
```

---

## 5. Credenciais do admin

O sistema tem um script `seed.js` que cria o utilizador admin no
primeiro deploy:

- **Email**: `pablo@systemup.inf.br`
- **Password**: `P@blo.147`

A password e armazenada com **bcrypt salt 12**. O seed verifica se o
utilizador ja existe antes de criar (nao duplica).

O login no frontend usa email + password.

---

## 6. Arquitectura

```
                    ┌─────────────────────────────────────────┐
                    │        192.168.100.244 (Docker)          │
                    │                                         │
                    │   ┌─── layer7-license (ISOLADO) ────┐   │
                    │   │                                  │   │
  ISPConfig:443 ──────► │  Nginx (:8445) ─────► API (:3001)│  │
                    │   │     │                    │       │   │
                    │   │     └──► React SPA       │       │   │
                    │   │         (build)    PostgreSQL     │   │
                    │   │                    (:5432 int)    │   │
                    │   └──────────────────────────────────┘   │
                    │                                         │
                    │   ┌─── SERVICOS EXISTENTES (NAO TOCAR)──┐│
                    │   │ Apache:80  Grafana:3000  MySQL:3306 ││
                    │   │ Zabbix:10050/10051  Monitor:8088    ││
                    │   └─────────────────────────────────────┘│
                    └─────────────────────────────────────────┘
```

### Docker Compose — 4 servicos, rede isolada

| Servico             | Imagem base    | Porta host | Porta interna |
|---------------------|----------------|------------|---------------|
| layer7-license-db   | postgres:17    | NENHUMA    | 5432          |
| layer7-license-api  | node:22-slim   | NENHUMA    | 3001          |
| layer7-license-web  | node+nginx     | NENHUMA    | 80            |
| layer7-license-nginx| nginx:alpine   | **8445**   | 80            |

- Nome do projecto Docker Compose: `layer7-license`
- Rede: `layer7-license-net` (bridge isolado)
- Volume: `layer7-license-pgdata` (dados PostgreSQL persistentes)
- PostgreSQL **SEM porta exposta** ao host (apenas rede interna Docker)

---

## 7. Stack tecnica

| Componente | Tecnologia |
|------------|------------|
| Backend    | Node.js 22 + Express |
| Database   | PostgreSQL 17 |
| ORM/Query  | node-postgres (pg) |
| Crypto     | tweetnacl (Ed25519 sign/verify) |
| Auth       | jsonwebtoken + bcryptjs |
| Rate limit | express-rate-limit |
| Frontend   | React 18 + Vite + React Router v6 |
| CSS        | TailwindCSS 3 |
| Proxy      | Nginx (Alpine) |
| Deploy     | Docker Compose |

---

## 8. Estrutura de directoria

Criar tudo dentro de `license-server/` na raiz do projecto:

```
license-server/
├── docker-compose.yml
├── .env.example
├── .env                      (criado no deploy, NAO commitar)
├── .gitignore
│
├── backend/
│   ├── Dockerfile
│   ├── package.json
│   ├── seed.js               (criar admin default)
│   ├── migrations/
│   │   └── 001-init.sql      (schema PostgreSQL)
│   └── src/
│       ├── index.js           (Express server, porta 3001)
│       ├── db.js              (PostgreSQL pool via pg)
│       ├── auth.js            (JWT middleware)
│       ├── crypto.js          (Ed25519 sign/verify via tweetnacl)
│       └── routes/
│           ├── activate.js    (POST /api/activate — publico)
│           ├── auth.js        (POST /api/auth/login)
│           ├── licenses.js    (CRUD licencas — autenticado)
│           ├── customers.js   (CRUD clientes — autenticado)
│           └── dashboard.js   (GET /api/dashboard — autenticado)
│
├── frontend/
│   ├── Dockerfile             (multi-stage: Vite build + copia para Nginx)
│   ├── package.json
│   ├── vite.config.js
│   ├── index.html
│   ├── postcss.config.js
│   ├── tailwind.config.js
│   └── src/
│       ├── main.jsx
│       ├── App.jsx            (React Router)
│       ├── api.js             (fetch wrapper com JWT)
│       ├── pages/
│       │   ├── Login.jsx
│       │   ├── Dashboard.jsx
│       │   ├── Licenses.jsx
│       │   ├── LicenseDetail.jsx
│       │   ├── LicenseForm.jsx
│       │   ├── Customers.jsx
│       │   └── CustomerForm.jsx
│       └── components/
│           ├── Layout.jsx      (sidebar + main content)
│           ├── Sidebar.jsx     (menu lateral com branding Systemup)
│           ├── StatsCard.jsx   (card de metrica para dashboard)
│           ├── DataTable.jsx   (tabela generica paginada)
│           └── StatusBadge.jsx (badge colorido: verde/amarelo/vermelho)
│
└── nginx/
    └── nginx.conf             (reverse proxy: /api/* -> api:3001, /* -> web:80)
```

---

## 9. Schema PostgreSQL (migrations/001-init.sql)

```sql
-- Tabela de administradores
CREATE TABLE admins (
    id          SERIAL PRIMARY KEY,
    email       VARCHAR(255) UNIQUE NOT NULL,
    name        VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT NOW()
);

-- Tabela de clientes
CREATE TABLE customers (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255),
    phone       VARCHAR(50),
    notes       TEXT,
    created_at  TIMESTAMP DEFAULT NOW(),
    updated_at  TIMESTAMP DEFAULT NOW()
);

-- Tabela de licencas
CREATE TABLE licenses (
    id          SERIAL PRIMARY KEY,
    customer_id INTEGER REFERENCES customers(id) ON DELETE RESTRICT,
    hardware_id VARCHAR(64),
    license_key VARCHAR(64) UNIQUE NOT NULL,
    expiry      DATE NOT NULL,
    features    VARCHAR(64) DEFAULT 'full',
    status      VARCHAR(20) DEFAULT 'active'
                CHECK (status IN ('active', 'revoked', 'expired')),
    activated_at TIMESTAMP,
    revoked_at  TIMESTAMP,
    notes       TEXT,
    created_at  TIMESTAMP DEFAULT NOW(),
    updated_at  TIMESTAMP DEFAULT NOW()
);

-- Log de activacoes (chamadas do daemon pfSense)
CREATE TABLE activations_log (
    id          SERIAL PRIMARY KEY,
    license_id  INTEGER REFERENCES licenses(id) ON DELETE CASCADE,
    hardware_id VARCHAR(64),
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(255),
    result      VARCHAR(20) CHECK (result IN ('success', 'fail', 'revoked')),
    error_message TEXT,
    created_at  TIMESTAMP DEFAULT NOW()
);

-- Indices
CREATE INDEX idx_licenses_key ON licenses(license_key);
CREATE INDEX idx_licenses_status ON licenses(status);
CREATE INDEX idx_licenses_customer ON licenses(customer_id);
CREATE INDEX idx_activations_license ON activations_log(license_id);
CREATE INDEX idx_activations_created ON activations_log(created_at);
```

---

## 10. API Endpoints

### 10.1 Publico (chamado pelo daemon pfSense)

| Metodo | Rota            | Body                        | Resposta                    |
|--------|-----------------|-----------------------------|-----------------------------|
| POST   | /api/activate   | `{key, hardware_id}`        | Ficheiro `.lic` (JSON assinado) |

**Rate limit**: 10 requisicoes por minuto por IP.

**Logica do activate**:
1. Buscar licenca por `license_key = key`
2. Se nao encontrar: retorna 404 + log fail
3. Se `status = revoked`: retorna 403 + log revoked
4. Se `status = expired`: retorna 403 + log fail
5. Se `hardware_id` da licenca esta vazio: gravar o hardware_id (primeira activacao)
6. Se `hardware_id` nao bate: retorna 403 + log fail (hardware mismatch)
7. Gerar data payload JSON: `{"hardware_id", "expiry", "customer", "features", "issued"}`
8. Assinar com Ed25519 (chave privada do `.env`)
9. Retornar `{"data": "<json-string>", "sig": "<hex-64-bytes>"}` como `application/json`
10. Gravar activacao em `activations_log` com result=success
11. Actualizar `activated_at` na licenca

### 10.2 Autenticado (painel admin, JWT)

| Metodo | Rota                      | Descricao                               |
|--------|---------------------------|-----------------------------------------|
| POST   | /api/auth/login           | Body: `{email, password}` → JWT (24h)   |
| GET    | /api/dashboard            | Metricas: totais, activas, expiradas, revogadas, clientes, activacoes 24h |
| GET    | /api/licenses             | Lista paginada. Query: `?status=&customer_id=&page=&limit=` |
| POST   | /api/licenses             | Criar licenca. Body: `{customer_id, expiry, features, notes}`. Auto-gera `license_key` (32 hex random) |
| GET    | /api/licenses/:id         | Detalhes + activations_log              |
| PUT    | /api/licenses/:id         | Editar: expiry, features, customer_id, notes |
| POST   | /api/licenses/:id/revoke  | Revogar: muda status para 'revoked', grava revoked_at |
| GET    | /api/customers            | Lista paginada. Query: `?search=&page=&limit=` |
| POST   | /api/customers            | Criar cliente. Body: `{name, email, phone, notes}` |
| PUT    | /api/customers/:id        | Editar cliente                          |
| GET    | /api/customers/:id        | Detalhes + licencas associadas          |
| DELETE | /api/customers/:id        | Remover (so se sem licencas)            |

---

## 11. Formato do ficheiro .lic (compatibilidade com o daemon)

O daemon C (`src/layer7d/license.c`) espera este formato exacto:

```json
{
  "data": "{\"hardware_id\":\"abc123...\",\"expiry\":\"2027-01-01\",\"customer\":\"Empresa\",\"features\":\"full\",\"issued\":\"2026-03-23\"}",
  "sig": "hexadecimal_64_bytes_ed25519_signature"
}
```

**IMPORTANTE:**
- O campo `data` e uma **string JSON serializada** (nao um objecto)
- A assinatura Ed25519 e sobre os bytes exactos dessa string
- O campo `sig` e a assinatura em hexadecimal (128 caracteres hex = 64 bytes)
- A chave privada para assinar fica em `ED25519_PRIVATE_KEY` no `.env`
- A chave publica correspondente deve ser embutida no binario `layer7d` (em `license.c`)

### Como gerar chaves Ed25519

O script existente `scripts/license/generate-license.py` pode ser usado:

```bash
# Gerar par de chaves
python3 scripts/license/generate-license.py keygen

# Resultado: layer7-private.key e layer7-public.key (hex, 64 chars cada)
```

O conteudo de `layer7-private.key` (64 hex chars) vai para `ED25519_PRIVATE_KEY` no `.env`.

### Compatibilidade no backend Node.js

O backend deve usar **tweetnacl** para assinar:

```javascript
const nacl = require('tweetnacl');

function signLicense(dataString, privateKeyHex) {
    const privateKey = Buffer.from(privateKeyHex, 'hex');
    // tweetnacl usa chave de 64 bytes (seed 32 + public 32)
    // Se a chave tem 32 bytes (seed only), derivar o keypair
    const keyPair = nacl.sign.keyPair.fromSeed(privateKey);
    const message = Buffer.from(dataString, 'utf8');
    const signature = nacl.sign.detached(message, keyPair.secretKey);
    return Buffer.from(signature).toString('hex');
}
```

---

## 12. Frontend (React) — Paginas

### 12.1 Login
- Campos: email, password
- Logo Systemup no topo
- Redirect para Dashboard apos login
- JWT guardado no localStorage

### 12.2 Dashboard
- 4 cards de metricas: Licencas Activas (verde), Expiradas (amarelo), Revogadas (vermelho), Total Clientes (azul)
- Tabela das ultimas 10 activacoes com: data, cliente, resultado (badge colorido), IP
- Branding Systemup no rodape

### 12.3 Licencas
- Tabela paginada com colunas: Key (truncada), Cliente, Expiry, Status (badge), Criada em
- Filtro por status (dropdown: todos, activas, expiradas, revogadas)
- Busca por customer name ou license key
- Botao "Nova Licenca"
- Cada linha: botoes Ver / Revogar

### 12.4 Detalhe da Licenca
- Todas as informacoes da licenca
- Hardware ID (se activada)
- Historico de activacoes (tabela)
- Botao "Revogar" (com confirmacao)
- Botao "Download .lic" (gera e baixa o ficheiro)

### 12.5 Formulario Nova Licenca
- Dropdown: seleccionar cliente existente
- Campo: data de expiracao (date picker)
- Campo: features (default: "full")
- Campo: notas (textarea)
- License key gerada automaticamente (32 hex)
- Ao salvar, redireciona para lista de licencas

### 12.6 Clientes
- Tabela paginada: Nome, Email, N. Licencas, Criado em
- Busca por nome/email
- Botao "Novo Cliente"
- Cada linha: botoes Ver / Editar

### 12.7 Formulario Cliente
- Campos: nome (obrigatorio), email, telefone, notas
- Usado para criar e editar

### 12.8 Branding
- Sidebar com logo/texto "Layer7 License Manager"
- Subtitulo: "por Systemup"
- Rodape: "Systemup Solucao em Tecnologia — www.systemup.inf.br"
- Cores: tons de azul (#337ab7 como accent)

---

## 13. Variaveis de ambiente (.env)

```env
# PostgreSQL
POSTGRES_DB=layer7_license
POSTGRES_USER=layer7
POSTGRES_PASSWORD=<gerar_password_forte>

# JWT
JWT_SECRET=<gerar_string_aleatoria_32+_chars>

# Ed25519 (chave privada, 64 hex chars)
ED25519_PRIVATE_KEY=<conteudo_de_layer7-private.key>

# Node
NODE_ENV=production
PORT=3001
```

---

## 14. Docker Compose (docker-compose.yml)

```yaml
version: "3.8"

services:
  db:
    container_name: layer7-license-db
    image: postgres:17-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${POSTGRES_DB}
      POSTGRES_USER: ${POSTGRES_USER}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - layer7-license-pgdata:/var/lib/postgresql/data
      - ./backend/migrations/001-init.sql:/docker-entrypoint-initdb.d/001-init.sql
    networks:
      - layer7-license-net
    # SEM ports: — nao expor PostgreSQL ao host

  api:
    container_name: layer7-license-api
    build: ./backend
    restart: unless-stopped
    depends_on:
      - db
    environment:
      DATABASE_URL: postgres://${POSTGRES_USER}:${POSTGRES_PASSWORD}@db:5432/${POSTGRES_DB}
      JWT_SECRET: ${JWT_SECRET}
      ED25519_PRIVATE_KEY: ${ED25519_PRIVATE_KEY}
      NODE_ENV: ${NODE_ENV:-production}
      PORT: 3001
    networks:
      - layer7-license-net
    # SEM ports: — so acessivel via nginx

  web:
    container_name: layer7-license-web
    build: ./frontend
    restart: unless-stopped
    networks:
      - layer7-license-net
    # SEM ports: — so acessivel via nginx

  nginx:
    container_name: layer7-license-nginx
    image: nginx:alpine
    restart: unless-stopped
    depends_on:
      - api
      - web
    ports:
      - "8445:80"
    volumes:
      - ./nginx/nginx.conf:/etc/nginx/nginx.conf:ro
    networks:
      - layer7-license-net

volumes:
  layer7-license-pgdata:

networks:
  layer7-license-net:
    driver: bridge
```

---

## 15. Nginx config (nginx/nginx.conf)

```nginx
events {
    worker_connections 256;
}

http {
    upstream api {
        server api:3001;
    }
    upstream web {
        server web:80;
    }

    server {
        listen 80;
        server_name _;

        # API
        location /api/ {
            proxy_pass http://api;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        # Frontend SPA
        location / {
            proxy_pass http://web;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
        }
    }
}
```

---

## 16. Integracao com o daemon layer7d

Apos deploy e geracao das chaves Ed25519:

### 16.1 Actualizar URL de activacao

No ficheiro `src/layer7d/license.c`, linha 418, mudar:

```c
// DE:
url = "https://license.layer7-pfsense.com/activate";

// PARA:
url = "https://license.systemup.inf.br/api/activate";
```

### 16.2 Embutir chave publica no binario

```bash
# Gerar array C da chave publica
python3 scripts/license/generate-license.py c-pubkey --public-key layer7-public.key
```

Copiar o output e substituir o bloco `l7_ed25519_pubkey[32]` em
`src/layer7d/license.c` (linhas 30-35).

### 16.3 Recompilar o pacote pfSense

Compilar no FreeBSD builder (192.168.100.12) e gerar novo `.pkg`.

---

## 17. Seguranca

- JWT com expiracao de 24h para o painel admin
- Endpoint `/api/activate` publico mas rate-limited (10 req/min por IP)
- Password do admin com bcrypt (salt rounds 12)
- Chave privada Ed25519 apenas no `.env` (nunca no codigo)
- `.env` no `.gitignore`
- PostgreSQL sem porta exposta ao host
- HTTPS terminado no ISPConfig
- Nomes de containers com prefixo `layer7-license-` para evitar conflitos

---

## 18. Ordem de execucao (checklist)

### Bloco 1: Estrutura do projecto ✓ (2026-03-23)
- [x] Criar directoria `license-server/`
- [x] Criar `docker-compose.yml`
- [x] Criar `.env.example`
- [x] Criar `.gitignore` (com `.env`, `node_modules/`)
- [x] Criar `backend/Dockerfile`
- [x] Criar `frontend/Dockerfile` (multi-stage)
- [x] Criar `nginx/nginx.conf`

### Bloco 2: Backend — Database e crypto ✓ (2026-03-23)
- [x] Criar `backend/package.json` (dependencias: express, pg, jsonwebtoken, bcryptjs, tweetnacl, express-rate-limit, cors, dotenv)
- [x] Criar `backend/migrations/001-init.sql`
- [x] Criar `backend/src/db.js` (pool PostgreSQL)
- [x] Criar `backend/src/crypto.js` (Ed25519 sign com tweetnacl)
- [x] Criar `backend/seed.js` (criar admin pablo@systemup.inf.br / P@blo.147)

### Bloco 3: Backend — API ✓ (2026-03-23)
- [x] Criar `backend/src/index.js` (Express server)
- [x] Criar `backend/src/auth.js` (JWT middleware)
- [x] Criar `backend/src/routes/auth.js` (login)
- [x] Criar `backend/src/routes/activate.js` (activacao publica)
- [x] Criar `backend/src/routes/licenses.js` (CRUD)
- [x] Criar `backend/src/routes/customers.js` (CRUD)
- [x] Criar `backend/src/routes/dashboard.js` (metricas)

### Bloco 4: Frontend ✓ (2026-03-23)
- [x] Criar `frontend/package.json` (dependencias: react, react-dom, react-router-dom, vite, tailwindcss, postcss, autoprefixer)
- [x] Criar `frontend/vite.config.js`
- [x] Criar `frontend/tailwind.config.js` + `postcss.config.js`
- [x] Criar `frontend/index.html`
- [x] Criar `frontend/src/main.jsx` + `App.jsx`
- [x] Criar `frontend/src/api.js`
- [x] Criar componentes: Layout, Sidebar, StatsCard, DataTable, StatusBadge
- [x] Criar paginas: Login, Dashboard, Licenses, LicenseDetail, LicenseForm, Customers, CustomerForm

### Bloco 5: Deploy ✓ (2026-03-23)
- [x] Conectar ao servidor 192.168.100.244 via SSH
- [x] Copiar `license-server/` para o servidor (`/opt/layer7-license/`)
- [x] Gerar par de chaves Ed25519 (seed: `3a54c...`, pubkey: `8c52b...`)
- [x] Criar `.env` com as variaveis
- [x] Executar `docker compose -p layer7-license up -d --build`
- [x] Executar seed: `docker exec layer7-license-api node seed.js`
- [x] Verificar que a porta 8445 responde (health OK)
- [x] Verificar que os servicos existentes continuam funcionando (Apache:80 OK, Grafana:3000 OK, Monitor:8088 OK)
- [x] Validacao extra: login, criar cliente, criar licenca, simular activacao pela LAN, testar falhas (404/403)

### Bloco 6: Integracao ✓ (2026-03-23)
- [x] Actualizar URL no `license.c` (para `https://license.systemup.inf.br/api/activate`)
- [x] Gerar chave publica C array e embutir no `license.c` (pubkey: `8c52b677...`)
- [x] Corrigir parser JSON no `license.c` (suporte a aspas escapadas `\"`)
- [x] Adicionar `license.c` e `-lcrypto` ao Makefile standalone
- [x] Recompilar pacote no FreeBSD builder (192.168.100.12) — `pfSense-pkg-layer7-1.0.1.pkg` (791KB)
- [x] Testar activacao end-to-end: `layer7d --activate <key> http://192.168.100.244:8445/api/activate`
- [x] Resultado: `license valid — customer=Empresa Teste Lab expiry=2027-12-31 features=full`

---

## 19. Comandos de deploy

```bash
# SSH no servidor
sshpass -p '@sp@2020Sp' ssh root@192.168.100.244

# No servidor, clonar ou copiar o projecto
cd /opt
# (copiar license-server/ para /opt/layer7-license/)

# Criar .env a partir do exemplo
cd /opt/layer7-license
cp .env.example .env
# editar .env com as variaveis reais

# Subir a stack
docker compose -p layer7-license up -d --build

# Criar admin
docker exec layer7-license-api node seed.js

# Ver logs
docker compose -p layer7-license logs -f

# Verificar saude
curl -s http://localhost:8445/api/dashboard  # deve retornar 401
curl -s http://localhost:80                   # Zabbix (deve continuar OK)
curl -s http://localhost:3000                 # Grafana (deve continuar OK)
```

---

## 20. Rollback

Se algo der errado, o rollback e seguro porque o sistema e 100% isolado:

```bash
# Parar e remover apenas a stack de licencas
docker compose -p layer7-license down

# Para remover tambem os dados:
docker compose -p layer7-license down -v

# Isso NAO afecta nenhum outro servico no servidor
```

---

## 21. Ficheiros de referencia no repositorio

| Ficheiro | Descricao |
|----------|-----------|
| `src/layer7d/license.c` | Codigo C do daemon que verifica `.lic` com Ed25519 (OpenSSL EVP). Contem a chave publica placeholder (all-zeros = dev mode). A URL de activacao esta na linha 418. |
| `src/layer7d/license.h` | Header com struct `l7_license_info`: hardware_id, customer, expiry, features, valid, expired, grace, dev_mode, days_left, error. Define `L7_LIC_PATH = /usr/local/etc/layer7.lic` e `L7_LIC_GRACE_DAYS = 14`. |
| `scripts/license/generate-license.py` | CLI Python para: keygen (Ed25519), sign (gerar .lic), c-pubkey (array C). Usa PyNaCl ou cryptography. |
| `CORTEX.md` | Status geral do projecto. Actualizar com o progresso do license server. |
| `AGENTS.md` | Regras para o agente AI. Ja referencia pos-V1 e servidor de licencas. |

---

## 22. Formato de dados — resumo rapido

**License key**: 32 caracteres hexadecimais aleatorios (ex: `a1b2c3d4e5f6...`)

**Hardware ID**: 64 caracteres hexadecimais = SHA256(kern.hostuuid + ":" + MAC)

**Ficheiro .lic**:
```json
{
  "data": "{\"hardware_id\":\"...\",\"expiry\":\"YYYY-MM-DD\",\"customer\":\"...\",\"features\":\"full\",\"issued\":\"YYYY-MM-DD\"}",
  "sig": "128_chars_hex_ed25519_signature"
}
```

**JWT payload**: `{id, email, name, iat, exp}`

---

## 23. Manual de uso

Manual operacional completo (gerar, gerir, instalar, troubleshooting):
[`MANUAL-USO-LICENCAS.md`](MANUAL-USO-LICENCAS.md)

---

*Documento criado em 2026-03-23. Projecto Layer7 — Systemup Solucao em Tecnologia.*
