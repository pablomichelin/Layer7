# Manual de Uso — Sistema de Licencas Layer7

> Documento operacional para gerar, gerir, instalar e manter licencas
> do produto Layer7 para pfSense CE.

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
│   192.168.100.244:8445   │        │       (cliente)              │
│   license.systemup.inf.br│        │                              │
│                          │        │   layer7d (daemon)           │
│   - Painel web (React)   │  HTTP  │   - Pede activacao           │
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

- **URL interna (LAN):** `http://192.168.100.244:8445`
- **URL externa (quando ISPConfig configurado):** `https://license.systemup.inf.br`

### 2.2 Login

- **Email:** `pablo@systemup.inf.br`
- **Password:** `P@blo.147`

Ao fazer login, o sistema gera um token JWT valido por 24 horas.
Apos 24h, sera necessario fazer login novamente.

### 2.3 Paginas do painel

| Pagina | Funcao |
|--------|--------|
| **Dashboard** | Resumo: licencas activas/expiradas/revogadas, total clientes, ultimas 10 activacoes |
| **Licencas** | Lista paginada, filtro por status, criar/ver/revogar |
| **Clientes** | Lista paginada, busca por nome/email, criar/editar/remover |

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
# Obter token
TOKEN=$(curl -s http://192.168.100.244:8445/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"pablo@systemup.inf.br","password":"P@blo.147"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")

# Criar cliente
curl -s http://192.168.100.244:8445/api/customers \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
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
curl -s http://192.168.100.244:8445/api/licenses \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
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
# Activacao usando URL interna (LAN)
layer7d --activate a1b2c3d4e5f6789012345678abcdef01 http://192.168.100.244:8445/api/activate

# Activacao usando URL publica (quando ISPConfig configurado)
layer7d --activate a1b2c3d4e5f6789012345678abcdef01 https://license.systemup.inf.br/api/activate

# Activacao usando URL default (embutida no binario)
layer7d --activate a1b2c3d4e5f6789012345678abcdef01
```

**Saida esperada (sucesso):**
```
layer7d: activating...
  server:       http://192.168.100.244:8445/api/activate
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
curl -s -X POST http://192.168.100.244:8445/api/licenses/3/revoke \
  -H "Authorization: Bearer $TOKEN"
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
  erro 403 (`Licenca revogada`)

---

## 8. Renovar uma licenca

Para renovar (estender a data de expiracao):

### Via painel web

1. Aceder a **Licencas** → clicar na licenca → **Detalhes**
2. (Actualmente: editar via API — ver abaixo)

### Via API (curl)

```bash
# Estender expiracao da licenca ID 3 para 2028-12-31
curl -s -X PUT http://192.168.100.244:8445/api/licenses/3 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"expiry":"2028-12-31"}'
```

### Apos renovar no servidor

O cliente precisa **re-activar** no pfSense para obter o novo `.lic`:

```bash
# No pfSense
layer7d --activate a1b2c3d4e5f6789012345678abcdef01 http://192.168.100.244:8445/api/activate
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
curl -s http://192.168.100.244:8445/api/licenses/3/download \
  -H "Authorization: Bearer $TOKEN" \
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
   curl -s http://192.168.100.244:8445/api/activate \
     -H "Content-Type: application/json" \
     -d '{"key":"a1b2c3d4e5f6789012345678abcdef01","hardware_id":"7209217784b0ca2c..."}'
   ```
   Isso retorna o `.lic` assinado. Salve-o:
   ```bash
   curl -s http://192.168.100.244:8445/api/activate \
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

### Endpoints autenticados (Bearer JWT)

| Metodo | Rota | Descricao |
|--------|------|-----------|
| `POST` | `/api/auth/login` | Login → JWT (24h) |
| `GET` | `/api/dashboard` | Metricas e ultimas activacoes |
| `GET` | `/api/licenses?status=&page=&limit=` | Listar licencas |
| `POST` | `/api/licenses` | Criar licenca |
| `GET` | `/api/licenses/:id` | Detalhes + historico activacoes |
| `PUT` | `/api/licenses/:id` | Editar (expiry, features, notes) |
| `POST` | `/api/licenses/:id/revoke` | Revogar licenca |
| `GET` | `/api/licenses/:id/download` | Download ficheiro .lic |
| `GET` | `/api/customers?search=&page=&limit=` | Listar clientes |
| `POST` | `/api/customers` | Criar cliente |
| `GET` | `/api/customers/:id` | Detalhes + licencas |
| `PUT` | `/api/customers/:id` | Editar cliente |
| `DELETE` | `/api/customers/:id` | Remover (so se sem licencas) |

### Rate limiting

O endpoint `/api/activate` tem limite de **10 requisicoes por minuto
por IP** para prevenir abuso.

---

## 12. Troubleshooting

### "Licenca nao encontrada" (404)

A chave de licenca esta incorrecta ou nao existe no servidor.
- Verificar a chave com `layer7d --activate CHAVE_CORRECTA`
- Verificar no painel web se a licenca existe

### "Hardware ID nao corresponde" (403)

A licenca ja foi activada noutro hardware.
- Cada licenca e vinculada ao primeiro hardware que a activa
- Para mudar de hardware: criar nova licenca ou pedir ao admin
  para limpar o hardware_id (via base de dados)

### "Licenca revogada" (403)

A licenca foi revogada pelo admin. Contactar o admin.

### "Licenca expirada" (403)

A data de expiracao ja passou. O admin precisa renovar (editar
expiry) e o cliente precisa re-activar.

### "Ed25519 signature verification failed"

O ficheiro `.lic` foi corrompido ou gerado com chave diferente.
- Re-activar: `layer7d --activate CHAVE URL`
- Verificar que o binario `layer7d` contem a chave publica correcta

### "could not reach license server"

O pfSense nao consegue contactar o servidor.
- Verificar conectividade: `curl http://192.168.100.244:8445/api/health`
- Verificar firewall/rotas entre pfSense e o servidor
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
| Chave privada Ed25519 | `.env` no servidor (variavel `ED25519_PRIVATE_KEY`) | Nunca sai do servidor. Nao commitar. |
| Chave publica Ed25519 | Embutida no binario `layer7d` (compile-time) | Distribuida com o pacote |
| JWT secret | `.env` no servidor (variavel `JWT_SECRET`) | Nunca sai do servidor |
| Password admin | Armazenada com bcrypt (salt 12) no PostgreSQL | Nunca em texto limpo |

### Modelo de confianca

- O `.lic` e **assinado** com Ed25519 — nao pode ser forjado
- O `.lic` contem o **hardware_id** — nao pode ser copiado para outro pfSense
- O daemon verifica **offline** — nao precisa de conexao permanente
- A activacao requer **conexao unica** ao servidor

### Backup das chaves

**CRITICO:** Fazer backup seguro de:

```
ED25519_PRIVATE_KEY=3a54c7423b182bdce4007fda43aa4ba5826d1c9c58082a657f7971f3c1e253b3
```

Se esta chave for perdida, todas as licencas existentes continuam
validas (verificacao offline), mas nao sera possivel gerar novas
licencas nem re-activar as existentes ate gerar novo par de chaves e
recompilar o binario.

---

## Exemplo completo: novo cliente do zero

```bash
# 1. Login no servidor
TOKEN=$(curl -s http://192.168.100.244:8445/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"pablo@systemup.inf.br","password":"P@blo.147"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")

# 2. Criar cliente
curl -s http://192.168.100.244:8445/api/customers \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"Escola Municipal XYZ","email":"ti@escolaxyz.com.br","phone":"21988887777"}'
# Resposta: {"id":2, ...}

# 3. Criar licenca (1 ano)
curl -s http://192.168.100.244:8445/api/licenses \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"customer_id":2,"expiry":"2027-03-24","features":"full","notes":"Contrato anual"}'
# Resposta: {"license_key":"abcdef1234567890abcdef1234567890", ...}

# 4. Enviar a chave ao cliente: abcdef1234567890abcdef1234567890

# 5. No pfSense do cliente (via SSH):
layer7d --activate abcdef1234567890abcdef1234567890 http://192.168.100.244:8445/api/activate
# Saida: license valid — customer=Escola Municipal XYZ expiry=2027-03-24 features=full

# 6. Verificar no dashboard do servidor — a activacao aparece na lista
```

---

*Documento criado em 2026-03-24. Layer7 License Server v1.0 — Systemup Solucao em Tecnologia.*
