# ADR-0004 - Cadeia de confianca dos artefatos

## Status

Aceito

## Contexto

O Layer7 distribui um artefacto instalavel `.pkg` e depende hoje de uma
cadeia incompleta de confianca:

- o builder compila, mas ainda se mistura com publicacao;
- existe checksum publico, mas nao ha contrato formal de assinatura;
- ha repositório de origem e repositório/canal publico distintos;
- scripts de instalacao ainda podem depender de URLs mutaveis;
- a chave publica de licenciamento nao deve ser confundida com a chave de
  autenticidade de release.

Sem uma cadeia formal, um artefacto pode ser publicado sem prova forte de
autenticidade e sem uma resposta clara para suspeita de builder comprometido.

## Problema

E preciso definir:

- quem gera o artefacto candidato;
- quem aprova e publica;
- quem assina;
- quem valida;
- onde nascem checksum e assinatura;
- como a chave publica de release e distribuida;
- como espelhamento e mirrors sao tratados;
- o que fazer se o builder estiver comprometido ou sob suspeita.

## Decisao

### 1. Principio de separacao de papeis

A cadeia oficial passa a separar quatro papeis:

1. **Origem de codigo**: define o que deve ser construido
2. **Builder**: gera o artefacto candidato
3. **Assinante de release**: assina o manifesto fora do builder
4. **Canal publico de distribuicao**: publica o conjunto de release

O builder nao e o assinante nem o trust anchor publico.

### 2. Quem gera

O builder oficial gera:

- o `.pkg` candidato;
- metadados de build necessarios para rastreabilidade;
- artefactos auxiliares locais de validacao.

### 3. Quem assina

A assinatura oficial de release deve ser gerada por um **signer separado do
builder**, controlado pelo maintainer/responsavel de release.

A chave privada de release:

- e dedicada a artefactos de distribuicao;
- nao se mistura com a chave privada de licenciamento;
- nao reside no builder;
- nao e commitada;
- deve ficar sob custodia separada e com backup controlado.

### 4. Quem valida

Ha tres pontos de validacao:

1. **Validacao de release** antes da publicacao
2. **Validacao de instalacao/upgrade** pelo instalador/operador
3. **Validacao documental e operacional** por checklist de release

### 5. Onde nasce o checksum

O checksum oficial nasce sobre o **conjunto exacto de bytes do artefacto que
sera publicado**, depois da geracao do candidato e antes da publicacao.

O contrato minino exige `sha256` do `.pkg`.

### 6. Onde nasce a assinatura

A assinatura nasce fora do builder, sobre um **manifesto de release** que
contem pelo menos:

- versao;
- tag;
- commit de origem;
- repositorio de origem;
- canal publico de distribuicao;
- nome do `.pkg`;
- tamanho do `.pkg`;
- `sha256` do `.pkg`;
- hashes dos scripts de instalacao/desinstalacao versionados, se forem parte
  da release;
- data de assinatura;
- versao do esquema de assinatura.

### 7. Esquema de assinatura

O contrato criptografico da release passa a ser:

- **assinatura destacada Ed25519 sobre manifesto versionado**

O mecanismo concreto pode ser implementado depois com ferramenta operacional
simples, desde que preserve este contrato e publique o fingerprint da chave.

### 7.1. Entrada em vigor operacional

- **F1.1:** remove a dependencia de `main` mutavel e fixa a instalacao em
  assets versionados do release (`install.sh`, `uninstall.sh`, `.pkg`,
  `.pkg.sha256`).
- **F1.2:** transforma manifesto versionado e assinatura destacada em gate
  operacional obrigatorio do release oficial.

### 8. Distribuicao da chave publica

A chave publica de verificacao de release deve ser distribuida em pelo menos:

- documentacao canónica do projecto;
- repositorio de origem;
- repositório/canal publico de distribuicao;
- manifestos/release notes com fingerprint publicado;
- futura integracao no instalador/actualizador, quando a implementacao
  ocorrer.

### 9. Como evitar adulteracao

Um artefacto so e confiavel quando:

- veio do canal oficial de distribuicao;
- o `.pkg` coincide com o `sha256` do manifesto;
- o manifesto possui assinatura valida da chave publica oficial;
- a tag/versao batem com a documentacao canónica e o changelog.

### 10. Espelhamento

Mirrors podem existir apenas como **origens de disponibilidade**, nunca como
origens autonomas de confianca.

Um mirror so e aceite se distribuir exactamente:

- o mesmo `.pkg`;
- o mesmo manifesto;
- a mesma assinatura;
- os mesmos hashes publicados.

### 11. Builder comprometido ou suspeito

Se o builder estiver comprometido ou sob suspeita:

- o artefacto candidato nao pode ser promovido;
- a release nao pode ser assinada;
- o builder entra em quarentena;
- credenciais e segredos associados sao revistos;
- o artefacto precisa ser recompilado em ambiente limpo;
- se ja houve publicacao, a release passa a ser tratada como suspeita e deve
  ser substituida por nova release assinada de ambiente confiavel.

## Alternativas consideradas

### A. O builder gerar e assinar tudo

Rejeitada. Concentraria risco excessivo num unico ponto comprometivel.

### B. Publicar apenas checksum

Rejeitada. Checksum sem assinatura nao resolve autenticidade do manifesto nem
do canal.

### C. Reutilizar a chave de licenciamento para assinar releases

Rejeitada. Mistura dominios de confianca diferentes e aumenta impacto de
compromisso de chave.

### D. Confiar apenas no GitHub como autenticidade

Rejeitada. GitHub e canal de distribuicao, nao substituto da assinatura
criptografica do projecto.

## Consequencias

- o processo de release fica mais disciplinado;
- a assinatura de release passa a depender de um signer separado;
- o instalador e a GUI de update precisarao convergir para manifesto
  versionado e chave publica pinned;
- builder suspeito deixa de ser “apenas um problema operacional” e passa a ter
  resposta formal.

## Riscos

- operacao de signing separada introduz um passo adicional;
- documentacao e scripts actuais ainda nao cumprem o contrato completo;
- enquanto a implementacao nao ocorrer, o risco actual continua aberto.

## Impacto em compatibilidade

- nao altera o formato do `.pkg`;
- altera o contrato de confianca em torno do `.pkg`;
- instaladores e fluxos antigos que nao validem manifesto/assinatura passam a
  ficar em regime legado/transitorio.

## Impacto operacional

- exige gestao separada de chave de release;
- exige manifesto de release como artefacto oficial;
- exige procedimento de quarentena para builder suspeito.

## Impacto em documentacao

Devem alinhar-se a este ADR:

- `CORTEX.md`
- `docs/10-license-server/MANUAL-INSTALL.md`
- `docs/06-releases/README.md`
- `docs/02-roadmap/checklist-mestre.md`
- runbooks e docs de release futuras

## Proximos passos

1. Formalizar a arquitectura completa da F1 com trust chain e superficies de ataque.
2. Definir, na implementacao da F1, o formato final do manifesto e do ficheiro
   de assinatura.
3. Integrar a validacao desse contrato no instalador e no fluxo de update.
