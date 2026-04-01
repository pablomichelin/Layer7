# ADR-0009 - Protecao da superficie administrativa do license server

## Status

Aceito

## Contexto

O estado actual observado no repositório é:

- `cors()` aberto globalmente no backend;
- login administrativo sem `rate limit`;
- endpoint público `/api/activate` com limiter simples por IP;
- ausência de política formal para brute force em `/api/auth/login`;
- ausência de logging/auditoria normativa para login, sessão e CRUD;
- rotas administrativas expostas sob o mesmo prefixo `/api/*`;
- respostas de erro ainda misturam operacional com detalhe interno.

## Problema

É preciso fechar um contrato simples e auditável para:

- rate limit administrativo;
- protecção contra brute force;
- logging/auditoria;
- política de erro;
- protecção de rotas sensíveis;
- CORS;
- superfícies expostas e respectivas mitigacões.

## Decisão

### 1. Separação de superfícies

As superfícies do license server passam a ser tratadas em três grupos:

1. **Admin web**
   - `/`
   - `/dashboard`
   - `/licenses*`
   - `/customers*`
   - `/api/auth/*`
   - `/api/dashboard`
   - `/api/licenses*`
   - `/api/customers*`

2. **Endpoint público de activação**
   - `/api/activate`

3. **Operação técnica**
   - `/api/health` e endpoints internos equivalentes

Cada grupo recebe política distinta. Não se aceita “uma política única para
tudo”.

### 2. Política de CORS

Produção:

- **same-origin only**
- sem `cors()` aberto globalmente
- sem `*`
- sem múltiplas origins arbitrárias

Regra oficial:

- o painel administrativo deve operar no mesmo origin do canal oficial
  `https://license.systemup.inf.br`
- o endpoint `/api/activate` não depende de CORS para o pfSense CLI e não deve
  abrir CORS amplo por esse motivo
- exceções de desenvolvimento local devem ser explícitas e não podem ser o
  default de produção

### 3. Rate limit

#### Login administrativo

`/api/auth/login` passa a exigir rate limit dedicado, mais restritivo que o
endpoint público de activação.

Baseline F2:

- janela curta por IP
- limitação adicional por par `email + IP`
- resposta `429` genérica
- sem enumerar utilizadores

#### Activação pública

`/api/activate` continua pública, mas com rate limit próprio e separado do
admin. O limiter actual é apenas ponto de partida, não contrato suficiente.

### 4. Brute force protection

Para login administrativo:

- contagem de falhas por IP e por conta alvo;
- bloqueio temporário após repetição anómala;
- reset controlado após autenticação bem-sucedida;
- auditoria de lockouts e tentativas.

Não é permitido depender apenas de bcrypt lento como protecção de brute force.

### 5. Logging e auditoria

Devem gerar rasto mínimo obrigatório:

- login bem-sucedido;
- login falhado;
- sessão criada;
- sessão revogada/logout;
- limite excedido;
- acesso negado por autorização;
- criação/edição/revogação/arquivo de licenças;
- criação/edição/arquivo de clientes;
- activação bem-sucedida e falhada;
- falha de validação relevante.

O log deve registar, no mínimo:

- componente;
- actor administrativo ou endpoint público;
- IP;
- resultado;
- motivo resumido;
- timestamp UTC.

### 6. Política de erro

Regras:

- mensagens externas genéricas e consistentes;
- sem stack traces no cliente;
- sem leak de segredo/configuração;
- sem distinguir “email inexistente” de “password errada”;
- erros de autorização e rate limit são explícitos, mas não verbosos.

### 7. Rotas sensíveis

Exigem autenticação administrativa:

- `/api/dashboard`
- `/api/licenses*`
- `/api/customers*`
- futuros endpoints de sessão/admin

Exigem protecção adicional:

- login administrativo;
- revogação;
- delete/archive;
- download de `.lic`;
- qualquer endpoint de alteração de segredos/operador.

### 8. Política de headers e cache

Para superfícies administrativas autenticadas:

- `Cache-Control: no-store`
- sem cache de respostas de login/sessão

### 9. Política de falha

- CORS fora da allowlist: **fail-closed**
- login acima do limite: **fail-closed**
- sessão/admin inválido: **fail-closed**
- falta de logging: erro operacional a corrigir, não motivo para abrir rotas
- endpoint técnico indisponível: degradado operacional, sem abrir bypass

## Alternativas consideradas

### A. Deixar `cors()` aberto porque o frontend actual usa fetch simples

Rejeitada. Mantém uma superfície desnecessariamente larga.

### B. Reutilizar um único rate limit global

Rejeitada. Login administrativo e activação pública têm perfis de abuso
diferentes.

### C. Não auditar login/logout para manter simplicidade

Rejeitada. Torna incidente administrativo opaco.

## Consequências

- a superfície administrativa deixa de depender de defaults inseguros;
- o endpoint público de activação fica separado da política do admin;
- rate limit, brute force e CORS deixam de ser implícitos;
- a equipa ganha trilha mínima de auditoria.

## Riscos

- rate limit mal calibrado pode bloquear uso legítimo;
- política de CORS demasiado permissiva reabre risco silenciosamente;
- logging excessivo pode capturar dados desnecessários se não houver higiene.

## Impacto em compatibilidade

- não altera o formato do `.lic`;
- não altera o objectivo funcional do painel;
- altera headers, política de origin e comportamento de erro/limite;
- frontends externos não same-origin deixam de ser suportados por default.

## Impacto operacional

- exige configuração clara do edge/origin para origin oficial;
- exige logs utilizáveis em incidente;
- exige decisão explícita sobre `/api/health` público vs restrito.

## Impacto em documentação

Devem alinhar-se a este ADR:

- `CORTEX.md`
- `docs/02-roadmap/roadmap.md`
- `docs/02-roadmap/backlog.md`
- `docs/01-architecture/f2-arquitetura-license-server.md`
- `docs/02-roadmap/f2-plano-de-implementacao.md`
- `docs/10-license-server/MANUAL-USO-LICENCAS.md`

## Próximos passos

1. Implementar CORS same-origin e rate limits distintos na F2.
2. Introduzir trilha mínima de auditoria para auth/admin/activate.
3. Endurecer a política de erro sem vazar detalhes internos.
