# Roadmap Canónico — F0 a F7

Este roadmap substitui o papel de SSOT que antes estava diluido entre a raiz
e resumos curtos. A ordem segura aprovada para evolucao do Layer7 e:

`F0 -> F1 -> F2 -> F3 -> F4 -> F5 -> F6 -> F7`

**Regra principal:** nao pular fases e nao misturar reorganizacao estrutural
com hardening tecnico ou com release engineering.

---

## Visao geral

| Fase | Nome | Estado | Gate central |
|------|------|--------|--------------|
| F0 | Governanca documental | consolidada | novo agente consegue retomar o projecto sem drift critico |
| F1 | Cadeia de confianca e seguranca critica | concluida | confianca entre distribuicao, builder, artefactos, blacklists e fallback fica auditavel |
| F2 | Hardening do license server | concluida em `2026-04-01` | stack de licencas opera com segredos, bootstrap, backup e fronteiras sob controlo |
| F3 | Robustez de licenciamento/activacao | aberta em `2026-04-01` | activacao, revogacao e offline deixam de depender de suposicoes |
| F4 | Confiabilidade package/daemon/blacklists | planeada | runtime e operacao ficam mais previsiveis |
| F5 | Malha de testes e regressao | planeada | gates de nao regressao ficam executaveis e repetiveis |
| F6 | Reorganizacao estrutural controlada | planeada | reorganizacao fisica acontece com mapa, links e rollback |
| F7 | Observabilidade e release engineering | planeada | release e operacao passam a ter governanca forte e verificavel |

---

## F0 — Governanca documental

### Objectivo

Consolidar a base canónica do projecto sem tocar em codigo, logica, package,
daemon, license server, frontend, scripts operacionais ou estrutura fisica.

### Escopo

- consolidar `CORTEX.md` como SSOT;
- consolidar `AGENTS.md`;
- criar indice oficial em `docs/README.md`;
- criar roadmap, backlog e checklist mestre canónicos;
- criar ADR index canónico;
- classificar os documentos actuais;
- criar mapa de equivalencia entre raiz e `docs/`;
- explicitar continuidade entre chats.

### Exclusoes

- sem mudanca de codigo;
- sem mexer em `PORTVERSION`;
- sem build, release ou empacotamento;
- sem mover/apagar/renomear ficheiros;
- sem limpar legado por forca.

### Dependencias

- documentacao existente na raiz e em `docs/`;
- estado funcional seguro ja conhecido da V1.

### Criterios de entrada

- V1 Comercial concluida;
- drift documental identificado;
- necessidade de preparar continuidade segura para fases tecnicas.

### Criterios de saida

- canonicidade declarada;
- novo agente consegue localizar SSOT e backlog sem ambiguidade;
- conflitos documentais principais ficam mapeados;
- nenhuma alteracao acidental no produto.

### Gate

- `CORTEX`, `AGENTS`, indice, roadmap, backlog, checklist, classificacao e
  equivalencia coerentes entre si;
- `git diff` apenas documental.

### Documentacao obrigatoria da fase

- `CORTEX.md`
- `AGENTS.md`
- `docs/README.md`
- `docs/02-roadmap/roadmap.md`
- `docs/02-roadmap/backlog.md`
- `docs/02-roadmap/checklist-mestre.md`
- `docs/00-overview/document-classification.md`
- `docs/00-overview/document-equivalence-map.md`
- `docs/03-adr/README.md`

---

## F1 — Cadeia de confianca e seguranca critica

### Objectivo

Tornar auditavel a cadeia de confianca entre:

- repositório de codigo;
- canal oficial de distribuicao;
- builder;
- checksum e assinatura de release;
- artefacto publico distribuido;
- instalacao/actualizacao;
- pipeline de blacklists;
- politicas de fallback e degradacao segura.

### Escopo

- formalizar a hierarquia oficial de distribuicao;
- declarar `.pkg` como artefacto oficial e o papel historico de `.txz`;
- definir contrato de checksums, manifesto e assinatura;
- separar papeis de origem do codigo, builder, assinante e canal publico;
- definir o pipeline seguro de blacklists com origem, mirror/cache e rejeicao;
- definir politica de fallback e degradacao segura por componente;
- mapear dependencias externas criticas e estrategia de reducao de risco;
- formalizar ADRs e documento consolidado de arquitectura F1;
- formalizar o plano de implementacao futura da F1.

### Exclusoes

- sem reorganizacao estrutural;
- sem redesign do license server como produto;
- sem adicionar funcionalidades novas ao utilizador final;
- sem misturar F1 com hardening do license server, blacklists fora da ordem
  segura ou fallback antes da subfase correspondente;
- sem alterar codigo funcional do produto para alem do estritamente necessario
  ao contrato de distribuicao e trust chain da release;
- sem inventar PKI complexa ou operacao dificil de manter.

### Dependencias

- F0 consolidada;
- backlog priorizado;
- capacidade de descrever risco real sem ambiguidade.

### Criterios de entrada

- base canónica pronta;
- lista de riscos criticos aprovada.

### Criterios de saida

- cadeia de confianca documentada ponta a ponta para artefactos e blacklists;
- artefacto oficial, canal oficial e legado historico ficam inequívocos;
- politica de checksum, assinatura, espelhamento e rejeicao fica definida;
- politica de fallback e degradacao segura fica definida por componente;
- dependencias externas criticas ficam mapeadas com reducao de risco;
- ADRs criticos da F1 ficam criados e coerentes entre si;
- ordem segura de implementacao futura fica declarada com gates e rollback conceitual.

### Gate

- nenhuma dependencia critica de confianca, distribuicao, blacklists ou fallback fica implícita;
- qualquer maintainer consegue explicar qual e o artefacto oficial, como ele sera validado e o que acontece quando a validacao falha.

### Checkpoint actual

- **F1.1 concluida em `2026-04-01`:** `.pkg` oficial, URLs versionadas e
  scripts `install.sh`/`uninstall.sh` alinhados ao canal oficial.
- **F1.2 concluida em `2026-04-01`:** manifesto `release-manifest.v1.txt`,
  `sha256`, assinatura destacada Ed25519, public key de verificacao e
  separacao builder/signer/publisher materializadas na trilha de release.
- **F1.3 concluida em `2026-04-01`:** manifesto dedicado
  `layer7-blacklists-manifest.v1.txt`, public key propria de blacklists,
  origem oficial HTTPS, mirror controlado em GitHub Releases, cache local e
  last-known-good restauravel passam a existir na trilha do consumidor.
- **F1.4 concluida em `2026-04-01`:** install/update da release passa a
  operar em fail-closed perante manifesto/assinatura/checksum suspeitos;
  blacklists passam a registar `healthy`, `degraded` e `fail-closed` com
  estado seguro preservado e acao do operador explicita.
- **Planejamento detalhado da F2 concluido em `2026-04-01`:** arquitetura
  consolidada do license server, ADRs 0007 a 0010 e plano
  `f2-plano-de-implementacao.md` passam a definir a ordem segura de hardening,
  sem implementacao tecnica ainda.
- **F2.1 concluida em `2026-04-01`:** `443/TLS` passa a ser o unico canal
  publico oficial do license server; `8445` passa a operar como origin
  privado com bind local por defeito, headers basicos publicados pelo nginx
  interno e runbook explicito para edge proxy, certificado e validacao.
- **F2.2 concluida em `2026-04-01`:** autenticacao administrativa deixa de
  depender de JWT em `localStorage`; a stack passa a operar com sessao
  stateful no backend, cookie `HttpOnly + Secure + SameSite=Strict`,
  expiracao ociosa/absoluta, renovacao controlada, logout com invalidacao
  real e bootstrap de sessao via `/api/auth/session`.
- **F2.3 concluida em `2026-04-01`:** a superficie administrativa passa a
  operar com same-origin only em producao, sem `cors()` aberto; o login
  administrativo passa a ter limiter dedicado por IP e por `email + IP`,
  lockout temporario por conta/IP, politica minima de erro sem enumeracao de
  credenciais e trilha minima de auditoria em banco para auth e mutacoes
  administrativas.
- **F2.4 concluida em `2026-04-01`:** `activate`, `licenses` e `customers`
  passam a operar com validacao forte de payload e query, respostas `400` /
  `404` / `409` coerentes, transacoes explicitas nas mutacoes multi-query e
  arquivo logico no fluxo administrativo normal, preservando historico em vez
  de delete fisico.
- **F2.5 concluida em `2026-04-01`:** ownership minimo de segredos,
  bootstrap administrativo explicito, backup/restore minimo do PostgreSQL e
  runbooks essenciais passam a ficar materializados; a F2 fica encerrada e a
  proxima fase elegivel passa a ser a F3.

### Documentacao obrigatoria da fase

- `CORTEX.md`
- `docs/01-architecture/f1-arquitetura-de-confianca.md`
- `docs/02-roadmap/backlog.md`
- `docs/02-roadmap/checklist-mestre.md`
- `docs/02-roadmap/f1-plano-de-implementacao.md`
- `docs/03-adr/README.md`
- `docs/03-adr/ADR-0003-hierarquia-oficial-de-distribuicao.md`
- `docs/03-adr/ADR-0004-cadeia-de-confianca-dos-artefatos.md`
- `docs/03-adr/ADR-0005-pipeline-seguro-de-blacklists.md`
- `docs/03-adr/ADR-0006-fallback-e-degradacao-segura.md`

---

## F2 — Hardening do license server

### Objectivo

Endurecer o servidor de licencas sem misturar esse trabalho com novas
features comerciais.

### Escopo

- segredos e variaveis de ambiente;
- fronteira HTTP/HTTPS e terminacao TLS;
- backup e restore;
- health checks e verificabilidade;
- logs operacionais minimos;
- rate limiting e superficies de abuso;
- operacao administrativa segura;
- modelo de sessao/token do painel;
- politica de CORS;
- validacao de input, transacoes e delete seguro do CRUD.

### Exclusoes

- sem refactor visual amplo do frontend;
- sem mudar o modelo comercial;
- sem reorganizacao fisica do repositório.

### Dependencias

- F1 concluida;
- ADRs de confianca criados;
- runbooks de producao revistos.

### Criterios de entrada

- fronteiras de confianca conhecidas;
- riscos criticos do server priorizados.

### Criterios de saida

- stack opera com segredos, bootstrap e backup sob controlo;
- publicacao/TLS, auth/sessao, CORS, brute force e CRUD ficam sob politica
  explicita e implementada;
- operacao e incidentes basicos ficam documentados;
- risco de exposicao ou recuperacao manual improvisada e reduzido.

### Gate

- um incidente operacional simples consegue ser tratado por runbook;
- credenciais, segredos e processo de recuperacao deixam de ser conhecimento
  oral;
- o canal oficial do painel e do endpoint publico fica inequívoco;
- a sessao administrativa deixa de depender de `localStorage` e de CORS
  permissivo;
- CRUD e activacao deixam de depender de multi-query sem transacao.

### Documentacao obrigatoria da fase

- `CORTEX.md`
- `docs/01-architecture/f2-arquitetura-license-server.md`
- `docs/02-roadmap/f2-plano-de-implementacao.md`
- backlog
- ADR-0007 a ADR-0010
- docs de licencas e runbooks operacionais afectados

---

## F3 — Robustez de licenciamento/activacao

### Objectivo

Tornar o comportamento de licenciamento previsivel em cenarios normais e de
falha.

### Escopo

- activacao online;
- reactivacao;
- revogacao;
- renovacao;
- activacao offline;
- grace period;
- consistencia do hardware fingerprint;
- estados e mensagens de erro.

### Exclusoes

- sem expandir produto comercial;
- sem mudar arquitectura de release;
- sem reorganizacao fisica.

### Dependencias

- F1 e F2 concluidas;
- stack de licencas sob hardening minimo.

### Criterios de entrada

- risco critico do servidor estabilizado;
- modelo de confianca conhecido.

### Checkpoint actual

- **F3 aberta em `2026-04-01`:** a arquitectura canónica passa a viver em
  `docs/01-architecture/f3-arquitetura-licenciamento-ativacao.md`, com
  mapeamento factual do fluxo actual de activacao/licenciamento no backend e
  no daemon.
- **F3.1 em execucao:** o contrato canónico passa a distinguir estado
  persistido (`active`, `revoked`, `expired`) de estado derivado por
  expiracao, a clarificar a diferenca entre activacao online e grace local do
  daemon, e a materializar um primeiro endurecimento de idempotencia segura
  em `POST /api/activate`.
- **Proxima subfase elegivel:** `F3.2 — expiracao, grace, offline e matriz de
  fingerprint em cenarios reais`.

### Criterios de saida

- fluxo de licenciamento explicado por estado e transicao;
- casos de falha principais cobertos por testes/registo;
- comportamento offline deixa de depender de tentativa e erro.

### Gate

- activacao, revogacao, expiracao e modo offline ficam previsiveis e
  rastreaveis.

### Documentacao obrigatoria da fase

- `CORTEX.md`
- `docs/01-architecture/f3-arquitetura-licenciamento-ativacao.md`
- backlog
- ADRs afectados
- `docs/10-license-server/MANUAL-USO-LICENCAS.md`
- matriz de testes afectada

---

## F4 — Confiabilidade package/daemon/blacklists

### Objectivo

Reduzir fragilidade operacional no pacote, no daemon e no subsistema de
blacklists, sem misturar ainda reorganizacao estrutural.

### Escopo

- confiabilidade de boot/reload/restart;
- convergencia entre runtime, PF e docs;
- update/reload de blacklists com fallback seguro;
- forcing DNS e excepcoes em cenarios reais;
- install/upgrade/remove/rollback com previsibilidade;
- correcao de drift documental operacional remanescente.

### Exclusoes

- sem reforma estrutural ampla;
- sem observabilidade “pesada” ainda;
- sem abrir multiplas frentes de funcionalidade nova.

### Dependencias

- F3 concluida;
- backlog e risks priorizados.

### Criterios de entrada

- cadeia de confianca e licenciamento mais estaveis;
- documentos operacionais de base actualizados.

### Criterios de saida

- runtime e package comportam-se de forma consistente em reboot, reload,
  upgrade e rollback;
- trilha de blacklists deixa de depender de suposicoes operacionais.

### Gate

- os principais cenarios operacionais de package/daemon/blacklists possuem
  evidencia minima e rollback claro.

### Documentacao obrigatoria da fase

- `CORTEX.md`
- backlog
- `docs/10-license-server/MANUAL-INSTALL.md`
- docs de blacklists afectadas
- runbooks e changelog afectados

---

## F5 — Malha de testes e regressao

### Objectivo

Transformar evidencias dispersas numa malha real de nao regressao.

### Escopo

- matriz de testes canónica;
- separacao entre smoke, builder, appliance e operacao;
- cobertura minima por fase/area;
- gates repetiveis;
- registo de evidencias e pendencias.

### Exclusoes

- sem reorganizacao estrutural ainda;
- sem tratar observabilidade avancada como substituto de testes.

### Dependencias

- F4 concluida;
- fluxos operacionais minimamente estaveis.

### Criterios de entrada

- principais frentes funcionais estabilizadas;
- backlog de risco traduzido em casos de teste.

### Criterios de saida

- existe malha minima de regressao por componente;
- gates deixam de ser opinativos;
- qualquer mudanca tecnica sabe que suite minima deve executar.

### Gate

- falhar um gate deixa de ser ambiguidade documental e passa a ser sinal claro.

### Documentacao obrigatoria da fase

- `CORTEX.md`
- backlog
- `docs/tests/README.md`
- `docs/tests/test-matrix.md`
- checklist mestre

---

## F6 — Reorganizacao estrutural controlada

### Objectivo

Reorganizar fisicamente o repositório apenas quando a base tecnica e
documental ja estiver segura para isso.

### Escopo

- mover ou consolidar documentos da raiz;
- normalizar duplicidades (`docs/tests` vs `docs/04-tests`, etc.);
- racionalizar pastas e readmes historicos;
- actualizar links, indices e referencias cruzadas.

### Exclusoes

- sem alterar logica funcional na mesma entrega;
- sem hardening tecnico misturado com reestrutura estrutural.

### Dependencias

- F5 concluida;
- mapas de classificacao e equivalencia maduros;
- backlog estrutural aprovado.

### Criterios de entrada

- canonicidade ja estabilizada por varias fases;
- risco de perda de contexto suficientemente baixo.

### Criterios de saida

- estrutura fisica mais limpa sem perda de rastreabilidade;
- links afectados mapeados;
- rollback estrutural praticavel.

### Gate

- qualquer ficheiro movido ou renomeado tem justificacao, equivalencia e
  rollback documentados.

### Documentacao obrigatoria da fase

- `CORTEX.md`
- backlog
- checklist mestre
- classificacao documental
- equivalencia documental
- changelog estrutural da fase

---

## F7 — Observabilidade e release engineering

### Objectivo

Fechar a camada de governanca operacional da distribuicao e da observabilidade.

### Escopo

- telemetria operacional relevante;
- checklist interno de release;
- verificacao de artefactos;
- alinhamento entre changelog, release notes e disponibilidade de download;
- governanca de publicacao.

### Exclusoes

- sem inflar escopo funcional do produto;
- sem usar automacao para mascarar falta de disciplina documental.

### Dependencias

- F6 concluida;
- estrutura e malha de testes suficientemente previsiveis.

### Criterios de entrada

- package, daemon e licenciamento estabilizados;
- reorganizacao estrutural encerrada ou congelada.

### Criterios de saida

- releases deixam de depender de memoria operacional;
- observabilidade passa a apoiar incidentes e validacoes;
- disponibilidade de pacote e rastreabilidade documental ficam acopladas.

### Gate

- uma release interna ou publica consegue ser executada por checklist, com
  verificacao de artefacto e docs sincronizadas.

### Documentacao obrigatoria da fase

- `CORTEX.md`
- backlog
- checklist mestre
- changelog
- docs de releases
- `docs/10-license-server/MANUAL-INSTALL.md`

---

## Regras de actualizacao documental por fase

### Ao entrar numa fase

- actualizar `CORTEX.md`;
- marcar a fase no roadmap;
- rever backlog e checklist mestre;
- confirmar se ADR novo e necessario.

### Durante a fase

- manter backlog e docs da area sincronizados com as decisoes tomadas;
- registar conflitos e riscos abertos no `CORTEX.md`;
- actualizar runbooks/manuais quando houver impacto operacional.

### Ao sair da fase

- actualizar checkpoint no `CORTEX.md`;
- rever criterios de saida e gate;
- consolidar pendencias para a fase seguinte no backlog;
- registar changelog quando houver mudanca tecnica ou release.
