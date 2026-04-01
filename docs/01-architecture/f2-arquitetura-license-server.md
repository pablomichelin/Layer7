# Arquitetura de Seguranca da F2 — License Server

## Finalidade

Este documento consolidou o desenho completo da F2 e passa a registar os
checkpoints materializados da F2.1 e da F2.2. Ele traduz o estado real observado em
`license-server/` para uma arquitetura de hardening simples, auditável e
operacionalmente viável.

Documentos normativos desta arquitetura:

- [`../03-adr/ADR-0007-publicacao-segura-license-server.md`](../03-adr/ADR-0007-publicacao-segura-license-server.md)
- [`../03-adr/ADR-0008-autenticacao-e-sessao-license-server.md`](../03-adr/ADR-0008-autenticacao-e-sessao-license-server.md)
- [`../03-adr/ADR-0009-protecao-superficie-administrativa-license-server.md`](../03-adr/ADR-0009-protecao-superficie-administrativa-license-server.md)
- [`../03-adr/ADR-0010-integridade-transacional-e-validacao-crud-license-server.md`](../03-adr/ADR-0010-integridade-transacional-e-validacao-crud-license-server.md)

---

## 1. Estado real de partida observado no planeamento

### Publicação

- `license-server/docker-compose.yml` publica `8445:80` no host
- `license-server/nginx/nginx.conf` escuta apenas em HTTP interno
- a documentação histórica assume TLS terminado na borda e origin HTTP privado

### Backend

- `license-server/backend/src/index.js` usa `cors()` globalmente aberto
- `/api/auth/login` não possui `rate limit`
- `/api/activate` possui limiter simples por IP
- autenticação administrativa usa JWT bearer
- tratamento de erro é mínimo e genérico

### Sessão/frontend

- `license-server/frontend/src/api.js` guarda token em `localStorage`
- `Sidebar.jsx` faz logout apenas limpando token no browser
- `App.jsx` protege rotas apenas por presença de token

### CRUD e banco

- `customers.js` e `licenses.js` validam pouco além de campos obrigatórios
- deletes e activação executam múltiplas queries sem transação única
- `customers.js` e `licenses.js` fazem delete físico de histórico
- `001-init.sql` não traz estrutura de sessão, lockout nem arquivo lógico

### Segredos e operação

- `.env.example` concentra `POSTGRES_PASSWORD`, `JWT_SECRET` e
  `ED25519_PRIVATE_KEY`
- `seed.js` bootstrapa admin por variáveis de ambiente
- não existe política documental fechada para rotação, ownership e incidentes

## 1.1 Estado materializado apos a F2.1

### Publicação

- `license-server/docker-compose.yml` passa a prender `8445` ao loopback por
  defeito via `LICENSE_SERVER_ORIGIN_BIND_IP=127.0.0.1`
- `license-server/nginx/nginx.conf` passa a rejeitar hosts inesperados,
  preservar `license.systemup.inf.br`/`localhost`, normalizar headers
  `X-Forwarded-*` e publicar headers basicos de seguranca
- a operacao oficial passa a tratar `443/TLS` como unico canal publico e
  `8445` apenas como origin privado/troubleshooting controlado

### Operação

- `docs/10-license-server/MANUAL-USO-LICENCAS.md` passa a usar
  `https://license.systemup.inf.br` como caminho normativo
- `docs/05-runbooks/license-server-publicacao-segura.md` passa a concentrar
  certificado, edge proxy, ACL do origin e validacoes minimas da F2.1

## 1.2 Estado materializado apos a F2.2

### Autenticacao e sessao

- `license-server/backend/src/session.js` passa a concentrar o contrato de
  sessao stateful com `admin_sessions` em PostgreSQL
- `license-server/backend/src/routes/auth.js` passa a expor
  `POST /api/auth/login`, `GET /api/auth/session` e `POST /api/auth/logout`
- o login administrativo passa a exigir `req.secure` e o canal oficial da
  F2.1, falhando fechado fora de HTTPS/TLS
- o estado de autenticacao deixa de usar JWT bearer e passa a usar cookie
  `HttpOnly + Secure + SameSite=Strict`
- a expiracao passa a ser dupla: `30 minutos` de ociosidade e `8 horas` de
  vida absoluta, com renovacao controlada perto da janela ociosa
- novo login passa a revogar sessoes activas anteriores do mesmo admin

### Frontend

- `license-server/frontend/src/auth.jsx` passa a centralizar bootstrap de
  sessao, login, logout e tratamento de sessao invalida/expirada
- `license-server/frontend/src/api.js` deixa de usar `localStorage` e bearer
  manual; as chamadas autenticadas passam a usar cookie same-origin
- `license-server/frontend/src/App.jsx` passa a proteger rotas por sessao
  resolvida no backend em vez de apenas testar presenca de token local

### Operacao

- `docs/05-runbooks/license-server-auth-sessao.md` passa a concentrar o
  contrato operativo da F2.2
- `docs/10-license-server/MANUAL-USO-LICENCAS.md` passa a usar `cookie jar`
  e `GET /api/auth/session` como referencia operacional de auth/sessao

---

## 2. Visão alvo da F2

```text
Internet / pfSense
  -> 443/TLS publico (edge proxy oficial)
  -> origin privado permitido (8445/TCP, ACL restrita)
  -> nginx interno do stack
  -> frontend SPA same-origin
  -> backend API
  -> PostgreSQL interno

Operador administrativo
  -> painel same-origin com cookie HttpOnly Secure
  -> rotas administrativas autenticadas, auditadas e rate-limited

pfSense cliente
  -> /api/activate via HTTPS publico oficial
  -> sem sessao administrativa, sem CORS aberto
```

---

## 3. Fronteiras de confiança

### Fronteira A — Internet para edge proxy

Canal público. Exige:

- TLS válido
- host oficial
- redirect HTTP -> HTTPS
- headers mínimos

### Fronteira B — edge proxy para origin privado

Canal privado. Aceitável apenas com:

- ACL/restrição de origem
- ausência de exposição pública directa de `8445`
- forwarding controlado de headers

### Fronteira C — Nginx interno para backend/frontend

Canal interno Docker. Não é público e não deve carregar semântica de canal
oficial.

### Fronteira D — backend para PostgreSQL

Canal de dados interno. Exige:

- segredo de conexão controlado
- ausência de exposição de porta no host
- atomicidade transacional

### Fronteira E — operador administrativo

Superfície mais sensível. Exige:

- autenticação real
- sessão revogável
- auditoria
- CORS same-origin
- rate limit/brute force protection

---

## 4. Superfícies de ataque da F2

| Superfície | Estado actual | Risco |
|-----------|---------------|-------|
| `8445` no host | origin HTTP publicado no host | exposição directa indevida ou uso administrativo fora do canal oficial |
| `cors()` global | produção sem restrição explícita | abertura desnecessária da API ao browser |
| JWT em `localStorage` | token acessível ao JS | roubo de sessão por XSS/extensão |
| login sem limiter | brute force administrativo | abuso e enumeração operacional |
| CRUD sem transação | multi-query parcial | estado inconsistente e perda de integridade |
| delete físico | remoção de histórico | perda de auditoria e troubleshooting |
| `seed` por `.env` | bootstrap sensível | risco operacional se tratado como credencial permanente |
| segredo Ed25519 no `.env` | signing centralizado | alto impacto se host ou env forem expostos |

---

## 5. Política oficial por domínio

### 5.1 Publicação/TLS

- canal público oficial: `443/TLS`
- `8445`: origin privado apenas
- `3001` e `5432`: nunca expostos
- sem fallback para HTTP público

### 5.2 Autenticação e sessão

- sessão administrativa stateful
- cookie `HttpOnly + Secure + SameSite=Strict`
- sem `localStorage` para credenciais
- logout com invalidação real no backend

### 5.3 Superfície administrativa

- CORS same-origin em produção
- limiter dedicado para login
- brute force protection
- logging mínimo obrigatório
- erros genéricos e sem leak

### 5.4 CRUD e activação

- validação explícita de payload
- transações para operações multi-query
- delete administrativo preferencialmente por arquivo lógico
- activação com `SELECT ... FOR UPDATE` e log atómico

### 5.5 Segredos e operação

- segredos fora do Git
- owner claro da credencial bootstrap, do cookie seguro em HTTPS/TLS, da
  `ED25519_PRIVATE_KEY` e da password bootstrap
- rotação planejada
- backup/restore e bootstrap documentados

---

## 6. O que deve ser fail-closed

- acesso administrativo sem sessão válida
- login acima do limite definido
- uso de canal público sem TLS
- CORS fora da allowlist same-origin
- payload inválido em CRUD/activate
- conflito de estado em revogação/delete/archive
- falha transacional em activação e operações multi-query
- ausência de segredos obrigatórios para arranque seguro

---

## 7. O que pode degradar com segurança

- `/api/health` e leituras administrativas podem devolver erro explícito sem
  abrir bypass
- indisponibilidade temporária do painel não autoriza fallback para HTTP
- manutenção do origin pode degradar disponibilidade, mas sem alterar a
  fronteira de confiança
- bootstrap inicial do admin pode continuar via seed controlado enquanto a
  credencial inicial for rotacionada e tratada como transitória

---

## 8. Pontos frágeis actuais que a F2 precisa fechar

1. Publicação administrativa ainda depende de `8445` host + convenção oral.
2. CORS permissivo contradiz o modelo real same-origin.
3. Sessão administrativa em `localStorage` é inadequada para hardening.
4. Login não tem `rate limit` nem trilha formal de brute force.
5. CRUD e activação podem deixar estado parcial por falta de transação.
6. Delete físico actual apaga histórico operacional sensível.
7. Segredos e bootstrap ainda não têm contrato operacional claro.

---

## 9. Decisões em aberto

Estas decisões ficam abertas para implementação, não para filosofia:

- calibragem exacta de limits/lockouts;
- shape final da política de arquivo lógico;
- detalhe final do logging/auditoria administrativa;
- runbook final de backup/restore e rotação de segredos.

---

## 10. Trade-offs assumidos

| Escolha | Ganho | Custo |
|--------|-------|-------|
| TLS público na borda + origin privado restrito | simplicidade operacional | exige ACL e disciplina para não expor `8445` |
| sessão stateful com cookie HttpOnly | revogação e auditabilidade | exige storage de sessão |
| CORS same-origin | superfície menor | menos flexibilidade para frontends externos |
| arquivo lógico em vez de delete físico | preserva histórico | exige filtros e migração de schema |
| transações explícitas | consistência forte | código ligeiramente mais verboso |

---

## 11. Ordem segura de endurecimento

1. Publicação/TLS e fronteiras de rede
2. Sessão administrativa e remoção de `localStorage`
3. Rate limit, brute force, CORS e logging mínimo
4. Validação de payload e transações do CRUD/activate
5. Segredos, bootstrap, backup/restore e runbooks

Esta ordem evita:

- endurecer auth sobre um canal ainda ambíguo;
- validar CRUD enquanto a sessão continua frágil;
- mexer em operação/segredos sem fronteira de publicação definida.

---

## 12. Resultado esperado da F2

Ao fim da F2 implementada, deve ficar claro e verificável:

- como o license server é publicado com segurança;
- qual é o único canal público oficial;
- como a sessão administrativa funciona e expira;
- quais rotas são protegidas e auditadas;
- como o CRUD falha fechado sem corromper estado;
- como o operador recupera o serviço sem improvisação.
