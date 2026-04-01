# CORTEX.md

## Finalidade

Este ficheiro e o **SSOT operacional e documental** do projecto Layer7.
Qualquer agente, maintainer ou novo chat deve conseguir retomar o contexto
do projecto a partir daqui sem depender de memoria implícita.

Se houver conflito entre documentos, a ordem de prevalencia e:

1. `CORTEX.md`
2. `docs/README.md`
3. `docs/02-roadmap/roadmap.md`
4. `docs/02-roadmap/backlog.md`
5. `docs/02-roadmap/checklist-mestre.md`
6. `docs/00-overview/document-classification.md`
7. `docs/00-overview/document-equivalence-map.md`
8. Documentacao canónica por area
9. Documentos historicos preservados na raiz e em subpastas

---

## Visao executiva do projecto

**Produto:** Layer7 para pfSense CE
**Empresa:** Systemup Solucao em Tecnologia
**Estado funcional conhecido:** V1 Comercial concluida e publicada
**Versao segura conhecida do pacote:** `1.8.2`
**Data-base deste checkpoint:** `2026-04-01`

O Layer7 e um pacote proprietario para pfSense CE com daemon `layer7d`,
GUI integrada, classificacao Layer 7 via nDPI, politicas granulares,
enforcement PF, anti-bypass DNS, blacklists UT1, relatorios locais e
licenciamento baseado em ficheiro `.lic` assinado com Ed25519.

O produto **nao esta em fase de descoberta funcional**. A prioridade agora
e preservar o que ja funciona, reduzir risco tecnico e evoluir por fases
controladas, com governanca forte e zero regressao desnecessaria.

---

## Estado actual

### Estado funcional

- V1 Comercial ja foi concluida e publicada.
- O pacote publico de referencia continua a ser o `.pkg` distribuido via
  GitHub Releases.
- O estado tecnico seguro conhecido continua associado ao pacote `1.8.2`,
  com correccao arquitectural que restringe todos os bloqueios Layer7 a
  destinos externos (`to !<localsubnets>`), eliminando impacto em trafego interno.
- O license server existe e esta operacional como componente separado,
  mas a sua cadeia de confianca e o seu hardening ainda pertencem as
  proximas fases planeadas.

### Estado documental

- A Fase 0 documental foi usada para consolidar governanca, canonicidade,
  backlog, roadmap, checklist mestre e mapas de classificacao/equivalencia.
- O planeamento detalhado da F1 foi consolidado sem implementacao tecnica,
  com ADRs formais para distribuicao, cadeia de confianca, blacklists e
  fallback/degradacao segura.
- O directorio `docs/` passa a ser o **centro documental canónico**.
- A raiz actual do repositório continua preservada como **legado importante**.
- **Nao houve reorganizacao fisica** do repositório nesta fase.

### Estado de governanca

- O projecto passa a trabalhar pela ordem segura **F0 -> F7**.
- Nenhuma reorganizacao fisica de pastas esta autorizada antes da **F6**.
- Nenhuma alteracao tecnica deve avancar sem impacto, risco, teste e rollback
  declarados.

---

## Objectivo estrategico

Manter o Layer7 operacionalmente previsivel e tecnicamente auditavel,
priorizando:

- preservacao da V1 ja entregue;
- cadeia de confianca real entre codigo, builder, servidor de licencas e
  artefacto distribuido;
- hardening de componentes pos-V1 sem inflar escopo;
- continuidade entre chats e entre mantenedores;
- documentacao viva, rastreavel e com canonicidade explicita.

---

## Principios obrigatorios

1. **Nao regredir comportamento existente.**
2. **Um bloco pequeno por vez.**
3. **Documentacao e execucao caminham juntas.**
4. **Sem reestruturacao fisica antes da hora.**
5. **Sem “solucoes magicas” nem refactors impulsivos.**
6. **Tudo relevante precisa de gate, risco, teste e rollback.**
7. **Se existir conflito documental, ele deve ser declarado e classificado.**
8. **Na duvida, conservar e documentar.**

---

## Fase actual

**Fase actual consolidada:** `F1 — Implementacao em curso (F1.3 concluida)`

**Resultado actual conhecido da F1:** a F1.1 fechou o contrato oficial de
distribuicao sobre `.pkg`, URLs versionadas de release e scripts oficiais de
install/uninstall no canal publico. A F1.2 materializou manifesto versionado,
assinatura destacada Ed25519, public key de verificacao e separacao
builder/signer na cadeia de release. A F1.3 materializou manifesto dedicado
de blacklists, public key propria, origem oficial HTTPS, mirror controlado,
cache local e last-known-good restauravel no appliance. A matriz de fallback
ampla por componente continua **pendente da F1.4**.

**Proxima subfase elegivel:** `F1.4 — Matriz de fallback e degradacao segura`

### Ordem segura das fases

| Fase | Nome | Estado | Intencao |
|------|------|--------|----------|
| F0 | Governanca documental | consolidada em `2026-04-01` | fixar canonicidade, continuidade e backlog |
| F1 | Cadeia de confianca e seguranca critica | em execucao; F1.3 concluida | fechar contrato oficial de distribuicao, autenticidade de artefactos, blacklists e fallback |
| F2 | Hardening do license server | planeada | endurecer deploy, segredos, backup e fronteiras operacionais |
| F3 | Robustez de licenciamento/activacao | planeada | tornar activacao, revogacao e modo offline previsiveis |
| F4 | Confiabilidade package/daemon/blacklists | planeada | reduzir falhas operacionais e alinhar runtime com docs e gates |
| F5 | Malha de testes e regressao | planeada | formalizar cobertura, evidencias e gates de nao regressao |
| F6 | Reorganizacao estrutural controlada | planeada | mover/normalizar estrutura apenas com mapa e rollback |
| F7 | Observabilidade e release engineering | planeada | fortalecer telemetria, verificacao de artefactos e governanca de release |

---

## Proximos passos autorizados

1. Executar F1.4 para aplicar a matriz de fallback/degradacao segura sem
   permitir conteudo suspeito.
2. Usar o backlog canónico e o plano F1 como fila unica antes de tocar em
   codigo, empacotamento, daemon, frontend ou scripts operacionais.

---

## Riscos abertos

- A cadeia actual entre repositório, builder com ficheiros sensiveis locais,
  chave publica embutida e artefacto publicado ainda precisa de formalizacao.
- O fluxo oficial ja saiu de `main`, mas referencias historicas a
  `raw.githubusercontent.com/.../main` ainda coexistem em material legado e
  precisam continuar classificadas como nao normativas.
- O canal oficial passa a publicar `.pkg`, `.pkg.sha256`, `install.sh`,
  `uninstall.sh`, `release-manifest.v1.txt`, assinatura destacada e public key
  de verificacao, mas a integracao directa dessa validacao no instalador e no
  GUI updater ainda permanece pendente.
- A trilha de blacklists deixou o feed HTTP directo, mas a disponibilidade
  ainda depende de publicar o manifesto assinado na origem oficial
  `downloads.systemup.inf.br` e no mirror controlado em GitHub Releases.
- GitHub, builder e origem UT1 continuam como dependencias externas fortes;
  a F1.3 reduziu o risco do lado do consumidor com mirror/cache/LKG, mas nao
  elimina a necessidade operacional de publicar snapshots aprovadas.
- O `docs/` tem areas canónicas e areas apenas suplementares/historicas;
  sem ler a classificacao, um agente pode seguir um documento antigo.
- Existem documentos antigos ainda a mencionar `.txz`, `v0.x` e estados
  pre-V1; isso esta agora classificado, mas a limpeza fisica fica para a F6.
- O tutorial longo e alguns guias de lab continuam uteis, mas nao devem ser
  tratados como SSOT para instalacao ou governanca.
- O builder possui alteracoes locais de producao que **nao podem ser
  commitadas**, exigindo disciplina operacional.

---

## Restricoes

- foco em **pfSense CE**;
- pacote **proprietario** com EULA Systemup;
- distribuicao publica por **`.pkg` via GitHub Releases**;
- sem software pago obrigatorio;
- V1 sem MITM TLS universal;
- V1 sem console central multi-firewall;
- V1 sem analytics pesado;
- sem reorganizacao fisica antes da F6;
- sem alterar codigo-fonte, package, daemon, license server, frontend,
  scripts operacionais ou logica funcional durante a F0 e durante o
  planeamento documental da F1.

---

## Regras de nao regressao

1. Nenhuma fase tecnica pode alterar mais de um subsistema critico ao mesmo
   tempo sem justificacao e rollback explicitos.
2. Nenhuma alteracao funcional entra sem declarar:
   - objectivo;
   - impacto;
   - risco;
   - teste;
   - rollback.
3. `docs/10-license-server/MANUAL-INSTALL.md` e a referencia canónica para
   instalacao, upgrade, reinstall e desinstalacao do pacote.
4. `docs/changelog/CHANGELOG.md` e a linha temporal oficial de releases e
   correccoes; o `CORTEX.md` nao deve voltar a carregar changelog detalhado.
5. Nenhum agente deve assumir que documentos da raiz ainda sao canónicos so
   porque foram a base original do projecto.
6. Antes da F6, conflitos estruturais resolvem-se por **classificacao e
   equivalencia**, nao por mover/apagar ficheiros.

---

## Hierarquia documental

### Documentos canónicos de governanca

- [`docs/README.md`](docs/README.md)
- [`docs/02-roadmap/roadmap.md`](docs/02-roadmap/roadmap.md)
- [`docs/02-roadmap/backlog.md`](docs/02-roadmap/backlog.md)
- [`docs/02-roadmap/checklist-mestre.md`](docs/02-roadmap/checklist-mestre.md)
- [`docs/02-roadmap/f1-plano-de-implementacao.md`](docs/02-roadmap/f1-plano-de-implementacao.md)
- [`docs/00-overview/document-classification.md`](docs/00-overview/document-classification.md)
- [`docs/00-overview/document-equivalence-map.md`](docs/00-overview/document-equivalence-map.md)
- [`docs/03-adr/README.md`](docs/03-adr/README.md)

### Documentos canónicos por area

- Produto/escopo: [`docs/00-overview/product-charter.md`](docs/00-overview/product-charter.md)
- Arquitectura alvo: [`docs/01-architecture/target-architecture.md`](docs/01-architecture/target-architecture.md)
- Arquitectura de confianca F1: [`docs/01-architecture/f1-arquitetura-de-confianca.md`](docs/01-architecture/f1-arquitetura-de-confianca.md)
- Instalacao/operacao do pacote: [`docs/10-license-server/MANUAL-INSTALL.md`](docs/10-license-server/MANUAL-INSTALL.md)
- Changelog: [`docs/changelog/CHANGELOG.md`](docs/changelog/CHANGELOG.md)
- Core tecnico: [`docs/core/README.md`](docs/core/README.md)
- Testes: [`docs/tests/README.md`](docs/tests/README.md)

### Legado preservado

- Os documentos `00-` a `16-` na raiz continuam preservados para contexto,
  rastreabilidade e compatibilidade de links, mas deixaram de ser a fonte
  primaria de decisao.

---

## Ordem de leitura obrigatoria

### Para qualquer novo chat ou agente

1. `CORTEX.md`
2. [`docs/README.md`](docs/README.md)
3. [`docs/02-roadmap/roadmap.md`](docs/02-roadmap/roadmap.md)
4. [`docs/02-roadmap/backlog.md`](docs/02-roadmap/backlog.md)
5. [`docs/02-roadmap/checklist-mestre.md`](docs/02-roadmap/checklist-mestre.md)
6. [`docs/00-overview/document-classification.md`](docs/00-overview/document-classification.md)
7. [`docs/00-overview/document-equivalence-map.md`](docs/00-overview/document-equivalence-map.md)
8. [`docs/03-adr/README.md`](docs/03-adr/README.md)
9. [`docs/01-architecture/f1-arquitetura-de-confianca.md`](docs/01-architecture/f1-arquitetura-de-confianca.md)
10. [`docs/02-roadmap/f1-plano-de-implementacao.md`](docs/02-roadmap/f1-plano-de-implementacao.md)

### Para trabalho tecnico numa area especifica

- Instalacao, upgrade, desinstalacao, rollback operacional:
  [`docs/10-license-server/MANUAL-INSTALL.md`](docs/10-license-server/MANUAL-INSTALL.md)
- Arquitectura/config/policy:
  [`docs/01-architecture/target-architecture.md`](docs/01-architecture/target-architecture.md)
  e [`docs/core/README.md`](docs/core/README.md)
- License server e licenciamento:
  [`docs/10-license-server/PLANO-LICENSE-SERVER.md`](docs/10-license-server/PLANO-LICENSE-SERVER.md)
  e [`docs/10-license-server/MANUAL-USO-LICENCAS.md`](docs/10-license-server/MANUAL-USO-LICENCAS.md)
- Distribuicao, builder, blacklists e fallback seguro:
  [`docs/01-architecture/f1-arquitetura-de-confianca.md`](docs/01-architecture/f1-arquitetura-de-confianca.md),
  [`docs/02-roadmap/f1-plano-de-implementacao.md`](docs/02-roadmap/f1-plano-de-implementacao.md),
  [`docs/03-adr/README.md`](docs/03-adr/README.md)
- Blacklists UT1:
  [`docs/11-blacklists/PLANO-BLACKLISTS-UT1.md`](docs/11-blacklists/PLANO-BLACKLISTS-UT1.md),
  [`docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md`](docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md),
  [`docs/11-blacklists/REGRAS-QUALIDADE.md`](docs/11-blacklists/REGRAS-QUALIDADE.md)
- Testes e validacao:
  [`docs/tests/README.md`](docs/tests/README.md)
  e [`docs/04-package/validacao-lab.md`](docs/04-package/validacao-lab.md)

---

## Mapa rapido do repositorio

- `docs/` -> centro documental canónico progressivo
- `src/` -> codigo-fonte do daemon, PoC e modulos de runtime
- `package/pfSense-pkg-layer7/` -> port e ficheiros do pacote pfSense
- `webgui/` -> documentacao curta da GUI e referencias auxiliares
- `license-server/` -> backend, frontend e nginx do servidor de licencas
- `scripts/` -> build, package, lab, diagnostico e release
- `tests/` -> material de teste e fixtures
- `samples/` -> amostras de configuracao e politicas
- raiz `00-16` -> legado documental preservado

---

## Componentes principais

1. **Pacote pfSense**
   - entrega o pacote instalavel, metadados, hooks e GUI integrada
2. **Daemon `layer7d`**
   - carrega configuracao, classifica trafego, decide politicas e alimenta PF
3. **Engine de classificacao**
   - nDPI como decisao congelada de V1
4. **Policy engine**
   - regras por interface, IP/CIDR, grupo, horario, host, app e categoria
5. **Enforcement PF**
   - bloqueio por origem/destino, forcing DNS, tabelas de excepcoes e blacklist
6. **Servidor de licencas**
   - stack separada para activacao, gestao administrativa e emissao de `.lic`
7. **Sistema documental**
   - governanca, roadmap, backlog, ADRs, runbooks, changelog e guias por area

---

## Decisoes congeladas

- foco exclusivo em **pfSense CE**
- produto comercial/proprietario da **Systemup**
- artefacto publico de distribuicao: **`.pkg`**
- activacao/licenciamento via ficheiro `.lic` assinado com **Ed25519**
- nDPI continua como engine de classificacao
- sem MITM universal na V1
- sem console central nesta etapa
- `docs/` e o centro canónico progressivo
- a raiz actual e **legado importante**, nao lixo
- reorganizacao fisica so na **F6**

---

## Politica de continuidade entre chats

Cada novo chat deve conseguir responder, sem ambiguidade:

1. Em que fase o projecto esta.
2. O que e canónico e o que e historico.
3. Qual e o ultimo estado seguro conhecido.
4. O que pode e o que nao pode ser mexido agora.
5. Qual e o proximo passo autorizado.

Para isso:

- o `CORTEX.md` deve ser lido antes de qualquer accao;
- o estado de fase deve ser mantido alinhado ao roadmap canónico;
- backlog, ADR index e checklist mestre devem ser actualizados sempre que
  houver mudanca real de prioridade, decisao ou gate;
- no fim de cada bloco relevante, registrar um checkpoint seguro.

---

## Politica de documentacao viva

| Quando algo mudar | Documentos obrigatorios |
|-------------------|-------------------------|
| fase actual, gate ou sequencia aprovada | `CORTEX.md`, `docs/02-roadmap/roadmap.md`, `docs/02-roadmap/backlog.md`, `docs/02-roadmap/checklist-mestre.md` |
| decisao arquitectural, de seguranca ou de distribuicao | `docs/03-adr/README.md`, ADR novo/actualizado, `CORTEX.md` |
| cadeia de confianca, distribuicao, blacklists ou fallback seguro | `docs/01-architecture/f1-arquitetura-de-confianca.md`, `docs/02-roadmap/f1-plano-de-implementacao.md`, backlog, ADR index |
| instalacao, upgrade, uninstall, rollback, caminhos, comandos ou versao publicada | `docs/10-license-server/MANUAL-INSTALL.md`, runbooks afectados, changelog, release docs |
| mudanca funcional relevante | changelog, docs da area, `CORTEX.md`, backlog/status da fase |
| reorganizacao estrutural | mapa de equivalencia, classificacao documental, roadmap/checklist da F6 |
| release publicada | changelog, release notes, `MANUAL-INSTALL.md`, `CORTEX.md` |

---

## Bloco fixo de checkpoint

```text
CHECKPOINT CANONICO
- Data base: 2026-04-01
- Produto: Layer7 para pfSense CE
- Versao segura conhecida: 1.8.2
- Estado funcional: V1 Comercial concluida e publicada
- Estado documental: governanca F0 consolidada + F1.3 de blacklists seguras concluida
- Fase actual: F1 (implementacao controlada)
- Proxima subfase elegivel: F1.4
- Reorganizacao fisica autorizada: nao
- Artefacto publico actual: .pkg via GitHub Releases
- Fonte canónica de instalacao: docs/10-license-server/MANUAL-INSTALL.md
- Fonte canónica de prioridade: docs/02-roadmap/backlog.md
- Fonte canónica de gates: docs/02-roadmap/checklist-mestre.md
```

---

## Ultimo status seguro conhecido

### Tecnico

- A referencia tecnica segura e o pacote `1.8.2`.
- O produto ja contem enforcement PF, forcing DNS, blacklists UT1,
  relatorios locais e licenciamento funcional.
- v1.8.2: correccao arquitectural — todos os bloqueios Layer7 passam
  a usar `to !<localsubnets>`, sem impacto em trafego interno (impressoras, LAN).

### Documental

- A canonicidade passou a estar explicitamente declarada.
- O projecto ja nao depende da raiz como fonte principal de governanca.
- O backlog, o roadmap, o checklist mestre e o mapa de equivalencia passam a
  servir de ponte segura entre chats e entre fases.
- A F1.2 passou a ter manifesto versionado, assinatura destacada e cadeia
  builder -> signer -> publish documentada e executavel.
- A F1.3 passou a ter manifesto dedicado de blacklists, public key propria,
  origem oficial `downloads.systemup.inf.br`, mirror controlado em GitHub
  Releases, cache local em `.cache`, estado activo em `.state` e
  last-known-good em `.last-known-good`.

### Operacional

- Qualquer proxima intervencao tecnica deve partir deste checkpoint e abrir a
  F1.4 antes de qualquer trabalho em hardening do license server,
  licenciamento dependente da cadeia de confianca ou reorganizacao
  estrutural.
