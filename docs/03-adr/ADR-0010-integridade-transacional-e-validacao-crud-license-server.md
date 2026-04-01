# ADR-0010 - Integridade transacional e validacao do CRUD do license server

## Status

Aceito

## Contexto

O backend actual do license server executa o CRUD administrativo de forma
funcional, mas com lacunas claras:

- validação de payload é mínima e pontual;
- `page`, `limit`, `customer_id` e outros campos usam `parseInt` sem política
  uniforme;
- criação e actualização aceitam payloads pouco restritos;
- deletes de clientes/licenças executam múltiplas queries sem transação;
- activação pública faz bind de `hardware_id`, actualiza timestamps e grava log
  sem transação única;
- o modelo actual permite apagar historial relevante do ponto de vista
  operacional.

## Problema

É preciso definir:

- política mínima de validação de payload;
- constraints lógicas do CRUD;
- atomicidade obrigatória;
- política de delete seguro;
- tratamento consistente de erro;
- o que deve falhar fechado e o que pode apenas degradar.

## Decisão

### 1. Validação de input

Todo endpoint mutável da F2 deve validar payload antes de tocar no banco.

Regras mínimas:

- rejeitar campos inesperados quando o schema for fechado;
- validar tipos, formato, comprimento e domínio permitido;
- normalizar apenas o que for seguro e explícito;
- devolver `400` para payload inválido e `409` para conflito lógico.

Baseline por domínio:

- `email`: formato válido e comprimento limitado
- `name`: obrigatório, trim, tamanho máximo
- `phone`: formato e comprimento limitados
- `notes`: tamanho máximo
- `customer_id`: inteiro positivo existente
- `expiry`: data válida e coerente
- `features`: enum ou lista controlada
- `hardware_id`: formato esperado e imutabilidade controlada
- `license_key`: imutável depois de emitida

### 2. Constraints lógicas

Regras obrigatórias:

- `license_key` é imutável;
- `hardware_id` é write-once por activação, salvo fluxo formal futuro da F3;
- licença revogada não pode voltar para activa por edição ad hoc;
- download de `.lic` exige licença em estado logicamente consistente;
- cliente não pode ser removido se ainda houver histórico/licenças activas sem
  política explícita de arquivo.

### 3. Transações obrigatórias

Passam a exigir transação explícita:

- activação (`SELECT ... FOR UPDATE`, bind de `hardware_id`, timestamps, log);
- revogação com auditoria associada;
- delete/archive de licença;
- delete/archive de cliente e respectivos vínculos;
- qualquer operação multi-query que altere mais de uma tabela.

Regra:

- ou tudo confirma;
- ou tudo faz rollback.

### 4. Delete seguro

Política oficial:

- delete físico de entidades com valor de auditoria deixa de ser o caminho
  padrão do painel;
- o caminho preferencial administrativo passa a ser **arquivo lógico**;
- activações históricas não devem desaparecer por convenience delete.

Consequência normativa:

- licenças com activação, `hardware_id` ou histórico associado não devem ser
  apagadas fisicamente pelo fluxo normal do painel;
- clientes com histórico/licenças associadas devem ser arquivados, não
  removidos em cascata.

Delete físico fica reservado para:

- dados de teste;
- limpeza controlada e excepcional;
- procedimentos operacionais específicos fora do fluxo normal do painel.

### 5. Tratamento de erro

O backend deve distinguir:

- `400` payload inválido
- `401` não autenticado
- `403` proibido/estado não permitido
- `404` recurso inexistente
- `409` conflito lógico/estado incompatível
- `429` limite excedido
- `500` erro interno

Erros de banco não devem vazar SQL ou detalhes internos ao cliente.

### 6. Política de falha

Para mutações administrativas e activação:

- falha de validação: **fail-closed**
- falha de transação: **fail-closed**
- conflito de estado: **fail-closed**

Degradação segura só é aceitável em superfícies de leitura:

- dashboard/listagens podem devolver `503` ou erro explícito;
- nunca devem “inventar sucesso”.

## Alternativas consideradas

### A. Manter deletes físicos com verificações ad hoc

Rejeitada. Continua frágil, pouco auditável e sujeita a perda de histórico.

### B. Confiar apenas em constraints do PostgreSQL

Rejeitada. Constraints ajudam, mas não substituem validação de payload nem
regras de negócio.

### C. Introduzir arquivo lógico e transações explícitas

Aceita. É a opção mais conservadora para integridade operacional sem
arquitetura pesada.

## Consequências

- CRUD deixa de depender de comportamento incidental do código;
- activação ganha atomicidade;
- histórico administrativo deixa de poder desaparecer por delete casual;
- conflitos lógicos passam a ser explícitos.

## Riscos

- arquivo lógico exige filtros consistentes nas consultas;
- transações mal desenhadas podem aumentar contenção se forem largas demais;
- validação insuficiente ou inconsistente entre rotas pode reabrir risco.

## Impacto em compatibilidade

- não altera formato do `.lic`;
- pode alterar semântica de delete no painel administrativo;
- pode alterar alguns códigos HTTP observados pelo frontend actual;
- mantém o modelo funcional de clientes, licenças e activações.

## Impacto operacional

- exige política clara de arquivo e retenção;
- exige migração de schema se o arquivo lógico for implementado;
- exige runbook de recuperação para falhas de transação.

## Impacto em documentação

Devem alinhar-se a este ADR:

- `CORTEX.md`
- `docs/02-roadmap/roadmap.md`
- `docs/02-roadmap/backlog.md`
- `docs/01-architecture/f2-arquitetura-license-server.md`
- `docs/02-roadmap/f2-plano-de-implementacao.md`
- `docs/10-license-server/MANUAL-USO-LICENCAS.md`

## Próximos passos

1. Introduzir schemas de validação e matriz de códigos HTTP da F2.
2. Aplicar transações explícitas às rotas multi-query e à activação.
3. Substituir delete casual por arquivo lógico no fluxo administrativo.
