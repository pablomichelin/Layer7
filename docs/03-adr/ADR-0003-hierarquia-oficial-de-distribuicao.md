# ADR-0003 - Hierarquia oficial de distribuicao

## Status

Aceito

## Contexto

O projecto convive hoje com uma ambiguidade operacional relevante:

- parte da documentacao antiga ainda fala em artefacto `.txz`;
- a base canónica actual ja reconhece o `.pkg` como artefacto publico;
- o repositório de trabalho actual e `pfsense-layer7`, mas a distribuicao
  publica de instalacao aponta para `pablomichelin/Layer7`;
- scripts e guias antigos ainda misturam repositorio de origem, repositorio
  de distribuicao, builder e canal de instalacao.

Sem um contrato formal, o projecto fica exposto a drift documental,
publicacao inconsistente e dificuldade para provar qual canal e oficial.

## Problema

E necessario definir, de forma inequívoca:

- qual e o artefacto oficial do produto;
- qual e o papel de cada repositório;
- qual e o papel do builder;
- qual e o papel do instalador;
- quais URLs sao oficiais;
- qual e o estatuto do legado `.txz`;
- quais metadados minimos de integridade devem acompanhar cada release.

## Decisao

### 1. Artefacto oficial

O artefacto oficial do produto Layer7 para distribuicao publica passa a ser:

- **`pfSense-pkg-layer7-<versao>.pkg`**

O `.pkg` e o unico artefacto instalavel considerado oficial para clientes,
operacao e documentacao canónica.

### 2. Estatuto do legado `.txz`

O `.txz` passa a ter estatuto de:

- **legado historico**
- **artefacto local de builder/lab, quando existir**
- **nao oficial para distribuicao publica actual**

Nenhum documento canónico novo deve recomendar `.txz` como canal primario de
instalacao, upgrade ou rollback.

### 3. Papel do repositorio de origem

O repositorio de origem e manutencao (`pfsense-layer7`) e a fonte de verdade
para:

- codigo;
- revisao;
- historico de commits;
- documentacao canónica;
- definicao do que deve ser construido.

### 4. Papel do repositorio/canal publico de distribuicao

O canal publico de distribuicao passa a ser o repositório publico:

- `https://github.com/pablomichelin/Layer7`

As **GitHub Releases** desse repositorio sao o canal oficial de publicacao de:

- `.pkg`;
- ficheiro de checksum;
- manifesto de release;
- assinatura do manifesto;
- scripts de instalacao/desinstalacao versionados, quando existirem como
  artefactos de release.

### 5. Papel do builder

O builder:

- compila o candidato a artefacto;
- nao e a autoridade final de distribuicao;
- nao e o trust anchor publico;
- nao publica por si so um artefacto como oficial sem gate de release.

### 6. Papel do instalador

O instalador:

- consome apenas artefactos versionados do canal oficial de distribuicao;
- nao deve depender de URL mutavel em `main` como raiz de confianca;
- deve operar sobre URLs de release/tag ou manifestos versionados.

### 7. URLs oficiais

Passam a existir dois niveis de URL oficial:

- **origem de manutencao**: repositório de origem usado por maintainers
- **origem de distribuicao**: GitHub Releases do repositório publico

Para instalacao e upgrade, o contrato oficial passa a aceitar apenas URLs
versionadas do canal de distribuicao.

### 8. Checksums

Na **F1.1**, toda release oficial deve publicar pelo menos:

- `.pkg`
- `.pkg.sha256`
- `install.sh`
- `uninstall.sh`

### 9. Assinatura

A **F1.2** passa a introduzir, alem do checksum:

- um **manifesto de release versionado**
- uma **assinatura destacada** desse manifesto

O detalhe da cadeia de assinatura fica definido pelo ADR-0004.

### 10. Politica de publicacao

Uma release so e considerada oficial quando publicar o conjunto minimo da
subfase correspondente:

- **F1.1:** tag versionada, `.pkg`, `.pkg.sha256`, `install.sh`,
  `uninstall.sh`, changelog/release notes sincronizados e manual de
  instalacao sincronizado.
- **F1.2 em diante:** todo o conjunto acima, mais manifesto de release e
  assinatura destacada do manifesto.

### 11. Compatibilidade historica

Documentos e fluxos antigos que mencionem `.txz` ficam preservados por
compatibilidade e rastreabilidade, mas deixam de ter valor normativo actual.

## Alternativas consideradas

### A. Manter `.txz` e `.pkg` como canais oficiais paralelos

Rejeitada. Mantem ambiguidade, duplica operacao e enfraquece o contrato de
distribuicao.

### B. Tratar o builder como canal oficial de distribuicao

Rejeitada. O builder compila, mas nao deve ser confundido com trust anchor
publico nem com repositório oficial de publicacao.

### C. Usar URLs mutaveis de `main` como contrato principal de instalacao

Rejeitada. URLs mutaveis tornam a distribuicao dificil de auditar e validar.

## Consequencias

- a documentacao canónica deve convergir para `.pkg`;
- fluxos historicos com `.txz` passam a ser explicitamente legados;
- a release oficial passa a exigir manifesto e assinatura, nao apenas asset
  solto;
- instalacao e upgrade devem convergir para referencias versionadas.

## Riscos

- documentacao historica continuara a coexistir durante algum tempo;
- a operacao actual ainda usa alguns caminhos mutaveis e precisara de
  transicao controlada;
- ha dependencia operacional do canal publico em GitHub enquanto nao houver
  espelhamento formal.

## Impacto em compatibilidade

- **compatibilidade historica preservada**: nao apaga `.txz` antigo nem
  invalida registos historicos;
- **compatibilidade normativa alterada**: `.txz` deixa de ser recomendado
  como artefacto oficial futuro.

## Impacto operacional

- reduz ambiguidade entre origem de codigo e canal publico de distribuicao;
- simplifica auditoria de instalacao e upgrade;
- prepara validacao automatizavel de artefacto versionado.

## Impacto em documentacao

Devem convergir para este ADR:

- `CORTEX.md`
- `docs/10-license-server/MANUAL-INSTALL.md`
- `docs/06-releases/README.md`
- `docs/03-adr/README.md`
- docs historicas de package/lab quando entrarem em revisao futura

## Proximos passos

1. Definir a cadeia de confianca detalhada dos artefactos no ADR-0004.
2. Substituir a dependencia de URLs mutaveis por assets/tag versionados.
3. Rever documentos historicos que ainda tratam `.txz` como default.
