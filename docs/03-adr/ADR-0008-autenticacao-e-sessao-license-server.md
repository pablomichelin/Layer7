# ADR-0008 - Autenticacao e sessao do license server

## Status

Aceito

## Contexto

O estado actual observado em `license-server/` é:

- `POST /api/auth/login` devolve JWT assinado com `jsonwebtoken`;
- o frontend guarda o token em `localStorage`;
- as rotas privadas verificam apenas `Authorization: Bearer ...`;
- logout remove o token apenas no browser;
- não existe revogação de sessão no backend;
- a validade actual é `24h`.

Este modelo funciona, mas deixa a superfície administrativa exposta a riscos
desnecessários:

- roubo de token por XSS ou extensão de browser;
- ausência de logout com invalidação real;
- sessão longa e sem rotação;
- impossibilidade de revogar sessões activas do lado do servidor.

## Problema

É preciso definir um modelo oficial, simples e seguro para:

- login administrativo;
- armazenamento de sessão/token;
- expiração e renovação;
- logout;
- compatibilidade entre frontend e backend;
- tratamento explícito de `JWT`, `localStorage` e cookies.

## Decisão

### 1. Modelo oficial de sessão administrativa

O modelo normativo da F2 para o painel administrativo passa a ser:

- **sessão stateful no servidor**
- identificador de sessão **opaco**
- cookie `HttpOnly`, `Secure`, `SameSite=Strict`

O backend passa a ser a autoridade de sessão. O browser deixa de carregar
credenciais administrativas em `localStorage` ou `sessionStorage`.

### 2. Política para JWT

JWT deixa de ser o mecanismo normativo de sessão do painel web.

Regra oficial:

- JWT em `localStorage`: **proibido**
- JWT em `sessionStorage`: **proibido**
- JWT em cookie legível por JavaScript: **proibido**

Se JWT for mantido transitoriamente durante a implementação, só é aceitável:

- em cookie `HttpOnly`;
- com expiração curta;
- sem exposição ao frontend;
- como detalhe de compatibilidade transitória, não como contrato final.

### 3. Cookie de sessão

O cookie administrativo deve ser:

- `HttpOnly`
- `Secure`
- `SameSite=Strict`
- com `Path=/`
- sem valor previsível

O cookie só deve ser emitido no canal HTTPS público oficial.

### 4. Persistência de sessão

Cada sessão administrativa deve ser persistida no backend com pelo menos:

- `admin_id`
- `session_id` ou hash do token opaco
- `created_at`
- `expires_at`
- `last_seen_at`
- `revoked_at`
- `ip_address`
- `user_agent`

Isto permite:

- logout real;
- revogação;
- auditoria;
- expiração forte;
- encerramento de sessões comprometidas.

### 5. Expiração e renovação

Política oficial:

- expiração absoluta curta para sessão administrativa
- renovação controlada por actividade
- sem sessão “quase permanente”

Baseline da F2:

- idle timeout: `30 minutos`
- expiração absoluta: `8 horas`
- renovação apenas se a sessão ainda estiver válida e próxima do idle timeout

### 6. Login

O login administrativo permanece:

- email + password

Regras:

- respostas não enumeram utilizador inexistente;
- credenciais inválidas devolvem erro genérico;
- emissão de sessão só ocorre após validação completa;
- credenciais bootstrap do seed deixam de ser consideradas caminho operacional
  permanente e passam a ser tratadas como mecanismo inicial de arranque.

### 7. Logout

Logout passa a exigir:

- invalidação explícita da sessão no backend;
- limpeza do cookie no browser;
- revogação de sessões antigas/rotacionadas conforme política.

“Apagar token do frontend” deixa de ser suficiente.

### 8. Impacto frontend/backend

Frontend:

- deixa de gerir `Authorization: Bearer` manualmente;
- deixa de usar `localStorage` para credenciais;
- passa a depender de `fetch` com credenciais/cookie no mesmo origin;
- passa a ter fluxo explícito de logout.

Backend:

- passa a gerir criação, validação, renovação e revogação de sessão;
- precisa de storage de sessão e auditoria associada;
- passa a distinguir autenticação administrativa do endpoint público de
  activação.

### 9. Política de falha

- cookie inválido, expirado ou revogado: **fail-closed**
- ausência de sessão válida: **fail-closed**
- indisponibilidade do storage de sessão: **fail-closed** para login e rotas
  administrativas

Não é permitido fallback para:

- token velho no browser;
- `localStorage`;
- reuso silencioso de sessão revogada.

## Alternativas consideradas

### A. Manter JWT em `localStorage`

Rejeitada. Simples, mas inadequada para painel administrativo exposto via web.

### B. JWT stateless em cookie `HttpOnly`

Considerada aceitável como transição, mas rejeitada como alvo final da F2
porque dificulta revogação fina e auditoria de sessão.

### C. Sessão stateful com cookie `HttpOnly`

Aceita. É a opção mais conservadora e operacionalmente simples para uma SPA
administrativa do projecto.

## Consequências

- a sessão administrativa passa a ser revogável e auditável;
- o frontend fica mais simples do ponto de vista de credenciais;
- XSS deixa de ter acesso directo ao token administrativo;
- logout passa a ser comportamento real e não apenas cosmético.

## Riscos

- implementação exige pequena migração de modelo frontend/backend;
- cookies `SameSite=Strict` exigem coerência total de origin;
- sessões persistidas sem limpeza/rotação podem acumular lixo operacional.

## Impacto em compatibilidade

- não altera a activação pública do daemon;
- altera a forma como o painel administrativo autentica;
- Bearer token manual no frontend actual passa a ser legado a remover na F2.

## Impacto operacional

- exige política de criação e revogação de sessão;
- exige tabela/armazenamento de sessão;
- exige runbook simples para reset de sessão e bootstrap do admin inicial.

## Impacto em documentação

Devem alinhar-se a este ADR:

- `CORTEX.md`
- `docs/02-roadmap/roadmap.md`
- `docs/02-roadmap/backlog.md`
- `docs/01-architecture/f2-arquitetura-license-server.md`
- `docs/02-roadmap/f2-plano-de-implementacao.md`
- `docs/10-license-server/MANUAL-USO-LICENCAS.md`

## Próximos passos

1. Implementar storage de sessão e cookie seguro numa subfase própria da F2.
2. Remover `localStorage` do frontend administrativo.
3. Introduzir endpoint explícito de logout e rotação controlada de sessão.
