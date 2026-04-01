# ADR-0006 - Fallback e degradacao segura

## Status

Aceito

## Contexto

O Layer7 combina componentes com requisitos diferentes de disponibilidade e
integridade:

- distribuicao de artefactos;
- instalacao e upgrade;
- activacao/licenciamento;
- blacklists externas;
- runtime com politicas manuais;
- GUI e canais de update.

Sem uma politica formal, o sistema pode adoptar fallbacks inseguros apenas
para “continuar a funcionar”, aceitando artefactos ou conteudos suspeitos.

## Problema

E preciso definir:

- o que pode ter fallback;
- o que nao pode ter fallback;
- a diferenca entre disponibilidade e integridade;
- quando fail-open e aceitavel;
- quando fail-closed e obrigatorio;
- como registrar e auditar degradacao segura;
- como impedir aplicacao de conteudo suspeito.

## Decisao

### 1. Regra-mestre

**Integridade prevalece sobre disponibilidade** para qualquer entrada que
altere estado de seguranca, de distribuicao ou de bloqueio.

### 2. O que nao pode ter fallback inseguro

Os itens abaixo devem operar em **fail-closed** perante falha de validacao:

- artefacto `.pkg` novo sem prova de autenticidade/integridade;
- manifesto de release invalido ou ausente;
- assinatura de release invalida;
- snapshot de blacklist nova sem validacao completa;
- resposta de activacao/licenca invalida ou adulterada;
- output de builder sob suspeita de compromisso.

Nesses casos, o sistema nao deve aplicar o conteudo novo.

### 3. O que pode operar em modo degradado seguro

Os itens abaixo podem operar em **degradacao segura**, desde que sem aceitar
conteudo suspeito:

- uso da **ultima release valida conhecida** por decisao explicita de rollback;
- uso da **ultima blacklist valida conhecida**;
- operacao com **licenca local valida ja existente** quando o servidor de
  activacao esta indisponivel;
- continuidade das **politicas manuais** quando a trilha de blacklists esta
  indisponivel;
- GUI/operacao administrativa com funcionalidades auxiliares degradadas, desde
  que nao se aplique conteudo nao validado.

### 4. Disponibilidade vs integridade

- **Disponibilidade**: manter o servico a operar de forma previsivel
- **Integridade**: garantir que o que entra no sistema e autentico e nao foi adulterado

Se houver conflito, o sistema preserva integridade e entra em modo degradado
seguro.

### 5. Matriz fail-open vs fail-closed

| Componente | Politica |
|------------|----------|
| instalacao/upgrade de artefacto novo | fail-closed |
| actualizacao de blacklist nova | fail-closed para o novo conteudo; hold-last-known-good para o conteudo activo |
| activacao de licenca nova | fail-closed |
| uso de licenca local ja valida | degradacao segura permitida |
| enforcement de politicas manuais existentes | degradacao segura permitida |
| fallback para origem nao oficial | proibido |

### 6. Logging e auditoria

Toda degradacao segura deve produzir registo com:

- componente afectado;
- motivo da degradacao;
- conteudo rejeitado, quando existir;
- estado mantido como seguro;
- accao requerida do operador, se aplicavel.

### 7. Como evitar aplicar conteudo suspeito

O sistema deve adoptar a regra:

- **nao promover**
- **nao substituir**
- **nao apagar a ultima versao valida**

quando a nova entrada falhar em autenticidade, integridade ou politica de origem.

## Alternativas consideradas

### A. Priorizar disponibilidade e aceitar qualquer fallback disponivel

Rejeitada. Cria risco directo de aceitar artefacto ou feed adulterado.

### B. Desligar completamente o sistema sempre que qualquer dependencia falha

Rejeitada. E excessivamente rigida para componentes auxiliares e ignora o valor
da ultima versao valida.

### C. Aplicar novo conteudo com aviso e corrigir depois

Rejeitada. Um aviso nao compensa a perda de integridade.

## Consequencias

- o projecto passa a ter linguagem comum para degradacao segura;
- fallbacks opportunistas deixam de ser aceitaveis;
- blacklists, artefactos e activacao ganham politica clara de rejeicao;
- o backlog tecnico futuro pode implementar por componente sem rediscutir a
  filosofia de seguranca a cada bloco.

## Riscos

- a politica pode parecer mais conservadora em incidentes de disponibilidade;
- operadores habituados a “forcar para funcionar” precisarao de runbooks claros;
- ate a implementacao, a politica existe no plano mas nao no runtime.

## Impacto em compatibilidade

- preserva comportamento funcional base onde ha ultima versao valida segura;
- altera a expectativa de aceitar entradas novas sem prova de integridade.

## Impacto operacional

- exige retenção de ultima versao valida por componente;
- exige logs e visibilidade de estado degradado;
- exige runbooks que distingam conteudo rejeitado de indisponibilidade simples.

## Impacto em documentacao

Devem alinhar-se a este ADR:

- `CORTEX.md`
- roadmap/backlog/checklist
- docs de licencas
- docs de blacklists
- docs de release e install quando a implementacao acontecer

## Proximos passos

1. Incorporar esta matriz de fallback na arquitectura consolidada da F1.
2. Traduzir a politica em gates de implementacao por subfase.
3. Definir testes minimos para provar fail-closed e degradacao segura em F1/F4.
