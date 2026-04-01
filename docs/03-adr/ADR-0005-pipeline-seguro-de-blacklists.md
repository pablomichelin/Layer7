# ADR-0005 - Pipeline seguro de blacklists

## Status

Aceito

## Contexto

O Layer7 usa blacklists UT1 como componente funcional relevante do produto,
mas o estado actual conhecido tem riscos severos:

- URL default em HTTP;
- ausencia de autenticidade criptografica do feed;
- dependencia forte de origem externa unica;
- ausencia de contrato formal para mirror, cache, checksum e rejeicao;
- ausencia de definicao do que fazer quando o feed esta indisponivel,
  adulterado ou suspeito.

As blacklists influenciam comportamento de bloqueio. Logo, disponibilidade do
feed nao pode se sobrepor a integridade do conteudo.

## Problema

E necessario definir:

- origem oficial do conteudo e do transporte;
- requisito de HTTPS;
- requisito de autenticidade/integridade;
- politica de mirror/cache;
- politica de update;
- politica de rejeicao;
- comportamento perante feed indisponivel ou suspeito;
- preservacao da ultima versao valida;
- auditoria e rastreabilidade do ciclo de update.

## Decisao

### 1. Separacao entre autoridade de conteudo e origem confiavel de ingestao

Passam a existir dois conceitos:

- **autoridade de conteudo**: a base UT1 enquanto origem tematica do dataset
- **origem confiavel de ingestao do Layer7**: um snapshot aprovado e publicado
  pelo proprio ecossistema Layer7 em canal confiavel

O pfSense/Layer7 nao deve consumir directamente o upstream UT1 em modo de
auto-update confiavel.

### 2. Origem oficial para auto-update confiavel

A origem oficial para consumo automatizado do produto deve ser:

- **snapshot versionado publicado em canal controlado pelo Layer7/Systemup**
- **acessivel por HTTPS**
- **acompanhado de checksum e assinatura**

### 3. HTTP, FTP e rsync

Passam a ter o seguinte estatuto:

- **HTTP directo para auto-update**: proibido
- **FTP directo para auto-update**: proibido
- **rsync directo para auto-update**: proibido

Podem existir apenas no dominio de aquisicao controlada e fora do firewall do
cliente, nunca como origem confiavel de aplicacao automatica.

### 4. Artefacto de blacklist aprovado

O pacote lógico de blacklist aprovada deve conter:

- snapshot versionado;
- identificador da snapshot;
- `sha256` do ficheiro;
- manifesto com origem upstream e data de aquisicao;
- assinatura destacada do manifesto;
- metadados minimos de estrutura/validacao.

### 5. Politica de mirror/cache

O pipeline deve operar com:

- **origem primaria controlada pelo Layer7/Systemup**
- **mirrors secundarios opcionais apenas para disponibilidade**

Um mirror nunca e raiz de confianca independente. O que valida o conteudo e o
manifesto assinado, nao o mirror.

### 6. Politica de update

O update de blacklists so pode promover novo conteudo quando:

- origem de download usa HTTPS;
- manifesto tem assinatura valida;
- checksum confere;
- estrutura basica do snapshot confere;
- regras locais de sanidade passam;
- a versao e registada para auditoria.

### 7. Politica de rejeicao

O novo snapshot deve ser rejeitado se ocorrer qualquer um:

- transporte nao-HTTPS;
- assinatura invalida ou ausente;
- checksum divergente;
- estrutura inesperada;
- snapshot incompleta;
- origem fora da hierarquia oficial;
- metadados inconsistentes.

### 8. Feed indisponivel ou suspeito

Se o feed estiver indisponivel ou suspeito:

- o sistema **nao aplica** o novo conteudo;
- mantem a **ultima versao valida**;
- regista erro/auditoria;
- nao troca automaticamente para upstream directo inseguro.

### 9. Ultima versao valida

O sistema deve tratar a ultima snapshot validada como:

- **last known good**
- referencia segura para continuidade operacional
- fallback permitido para disponibilidade

### 10. Auditoria e rastreabilidade

Cada ciclo de update deve produzir registo minimo de:

- snapshot pretendida;
- origem de download;
- checksum observado;
- resultado da validacao;
- motivo de rejeicao, se houver;
- snapshot activa apos a tentativa.

## Alternativas consideradas

### A. Consumir UT1 directamente via HTTP

Rejeitada. Fere integridade e abre superficie clara para adulteracao.

### B. Confiar apenas em checksum baixado da mesma origem

Rejeitada. Checksum do mesmo canal comprometido nao resolve autenticidade.

### C. Consumir GitHub mirror publico como unica raiz de confianca

Rejeitada. Pode ser espelho util, mas nao deve substituir o contrato de
snapshot aprovada e assinada pelo projecto.

### D. Fazer fallback automatico para qualquer origem disponivel

Rejeitada. Prioriza disponibilidade sobre integridade e pode aplicar conteudo
suspeito.

## Consequencias

- a dependencia upstream UT1 fica encapsulada por uma etapa de aprovacao;
- o firewall deixa de aceitar feed nova sem prova minima de autenticidade;
- a trilha de blacklists ganha comportamento previsivel em falha;
- o trabalho de implementacao da F4 passa a ter contrato claro desde a F1.

## Riscos

- o projecto passa a depender de manter um canal confiavel proprio para
  snapshots aprovadas;
- existe custo operacional adicional para aprovar snapshots;
- enquanto o novo pipeline nao for implementado, o risco actual permanece.

## Impacto em compatibilidade

- preserva o modelo funcional de blacklists;
- altera a origem e o contrato de ingestao confiavel;
- o default historico em HTTP deixa de ser aceitavel como politica futura.

## Impacto operacional

- exige cache/mirror controlado ou canal aprovado equivalente;
- exige reter a ultima versao valida;
- exige logs e rastreabilidade do processo de update.

## Impacto em documentacao

Devem alinhar-se a este ADR:

- `docs/11-blacklists/PLANO-BLACKLISTS-UT1.md`
- `docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md`
- `docs/10-license-server/MANUAL-INSTALL.md` se houver impacto operacional
- docs de F1 e F4

## Proximos passos

1. Desenhar o trust chain completo blacklist -> snapshot aprovada -> validacao -> aplicacao.
2. Definir na implementacao da F1/F4 o formato do manifesto e a persistencia
   da ultima versao valida.
3. Integrar a politica de rejeicao e auditoria na trilha operacional futura.
