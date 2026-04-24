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
| F4 | Confiabilidade package/daemon/blacklists | **F4.0 aberta** em `2026-04-24` (plano: [`f4-plano-de-implementacao.md`](f4-plano-de-implementacao.md); F3 pode permanecer aberta com DR-05 em paralelo) | runtime e operacao ficam mais previsiveis |
| F5 | Malha de testes e regressao | preparacao em [`f5-preparacao-malha.md`](f5-preparacao-malha.md); execucao plena apos saida F4 | gates de nao regressao ficam executaveis e repetiveis |
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
- **F3.1 executada de forma conservadora em `2026-04-01`:** o contrato
  canónico passa a distinguir estado
  persistido (`active`, `revoked`, `expired`) de estado derivado por
  expiracao, a clarificar a diferenca entre activacao online e grace local do
  daemon, e a materializar um primeiro endurecimento de idempotencia segura
  em `POST /api/activate`.
- **F3.2 executada de forma conservadora em `2026-04-01`:**
  `docs/01-architecture/f3-fingerprint-e-binding.md` passa a formalizar a
  formula real do fingerprint, a dependencia de `kern.hostuuid` e da primeira
  MAC Ethernet nao-loopback, a matriz de cenarios reais do appliance e a
  politica de compatibilidade da fase; o unico ajuste tecnico aceite fica
  restrito a normalizacao defensiva do `hardware_id` persistido no servidor
  antes de comparacao/emissao do `.lic`, sem mudar o algoritmo base.
- **F3.3 executada de forma conservadora em `2026-04-01`:**
  `docs/01-architecture/f3-expiracao-revogacao-grace.md` passa a formalizar a
  semantica real de expiracao, revogacao, grace e validade offline do `.lic`,
  incluindo o limite real da revogacao actual e o risco objectivo de um `.lic`
  antigo continuar valido offline; o unico ajuste tecnico aceite fica
  restrito a centralizacao do estado efectivo da licenca no backend para
  alinhar `activate`, `licenses`, `customers` e `dashboard`.
- **F3.4 executada de forma conservadora em `2026-04-01`:**
  `docs/01-architecture/f3-mutacao-admin-reemissao-guardrails.md` passa a
  formalizar a superficie administrativa real da licenca, a diferenca entre
  mutacoes seguras e perigosas apos bind e o risco de reemissao com artefacto
  antigo ainda valido offline; o unico ajuste tecnico aceite fica restrito a
  bloquear mudanca de `customer_id` em licenca activada/bindada e a enriquecer
  a auditoria minima do update, sem abrir workflow novo nem mexer em `.lic`.
- **F3.5 executada de forma conservadora em `2026-04-01`:**
  `docs/01-architecture/f3-emissao-reemissao-rastreabilidade.md` passa a
  formalizar a trilha real de emissao/reemissao do `.lic`, a diferenca entre
  emissao inicial, reemissao legitima e reemissao administrativa e o risco de
  multiplos artefactos coexistirem sem exclusividade forte; o unico ajuste
  tecnico aceite fica restrito a enriquecer a auditoria do artefacto emitido
  em `activate` e `download`, sem mudar payload, formato `.lic` ou daemon.
- **F3.6 formalizada documentalmente em `2026-04-01`:**
  `docs/01-architecture/f3-validacao-manual-evidencias.md` passa a consolidar
  a leitura factual da validabilidade actual, a matriz de cenarios
  obrigatorios/desejaveis, os comandos objectivos de recolha de evidencia e a
  politica de "validacao suficiente" da F3, sem fingir que a validacao real
  em lab/appliance ja ocorreu.
- **F3.7 formalizada de forma conservadora em `2026-04-02`:**
  `docs/01-architecture/f3-pack-operacional-validacao.md` passa a
  operacionalizar a F3.6 com estrutura por `run_id`, convencao de ficheiros,
  classificacao `PASS` / `FAIL` / `INCONCLUSIVE` / `BLOCKED`, template
  markdown por cenario e helper shell barato para exportar evidencias do
  backend sem tocar no contrato do produto.
- **F3.8 formalizada de forma conservadora em `2026-04-02`:**
  `docs/01-architecture/f3-gate-fechamento-validacao.md` passa a fixar o
  gate oficial de fechamento da F3, a matriz objectiva de `PASS` / `FAIL` /
  `INCONCLUSIVE` / `BLOCKED` por cenario, a classificacao de pendencias
  bloqueantes vs nao bloqueantes e o relatorio final unico de campanha em
  `docs/tests/templates/f3-validation-campaign-report.md`, sem declarar a F3
  fechada antes da execucao real.
- **F3.9 executada como primeira campanha real controlada em `2026-04-02`:**
  a rodada `20260402T130015Z-deploy244` produziu evidencias reais de backend,
  relatorio final unico de campanha e veredito explicito `F3 nao pode
  fechar`, com `0 PASS`, `3 FAIL`, `1 INCONCLUSIVE` e `9 BLOCKED`. A campanha
  revelou drift do deploy real face ao contrato canónico do repositório
  (schema live sem `admin_sessions`, `admin_audit_log` e
  `admin_login_guards`, e `activate` live a responder `403` onde a F3.8 exige
  `409`), alem de falta de appliance pfSense autenticavel e de credencial
  administrativa autorizada para os cenarios administrativos.
- **F3.10 concluida como saneamento documental-operacional em `2026-04-02`:**
  `docs/01-architecture/f3-matriz-prerequisitos-campanha.md` passa a fixar o
  minimo obrigatorio de credenciais, acessos, estado de deploy/schema,
  appliance e inventario de licencas para a proxima campanha;
  `docs/01-architecture/f3-matriz-drift-operacional.md` passa a classificar
  os drifts observados na F3.9; e
  `docs/01-architecture/f3-runbook-proxima-campanha-real.md` passa a definir
  a ordem sequencial, os criterios de aborto antecipado e as evidencias
  obrigatorias da proxima rodada, sem mudar o gate da F3.
- **F3.11 readiness check executado em `2026-04-02`:**
  `docs/01-architecture/f3-11-readiness-check.md` passou a registar a
  verificacao real do ambiente. O resultado foi `F3.11 bloqueada por
  pre-requisitos nao satisfeitos`: backend publico e origin responderam, mas
  nao houve shell/DB access ao deploy observado, nao houve credencial
  administrativa autorizada, nao houve appliance pfSense autenticavel e nao
  houve inventario real `LIC-A` a `LIC-F`.
- **Alinhamento do license-server live confirmado em `2026-04-14`:**
  o ambiente activo em `192.168.100.244:/opt/layer7-license` passa a expor
  `admin_sessions`, `admin_audit_log` e `admin_login_guards`, `/api/auth/session`
  volta a responder no contrato stateful actual e `/api/auth/login` volta a
  falhar fechado para `Origin` externo. Com isso, o unico blocker real
  remanescente da F3 fica reduzido a `DR-05` no appliance.
- **Contrato de rejeicao do activate coberto no branch em `2026-04-14`:**
  o repositório passa a ter testes dedicados para preservar `409` em
  licenca revogada, licenca expirada e hardware divergente no
  `POST /api/activate`, mantendo `DR-02` como alinhamento de deploy e nao
  como blocker da F3.
- **Proximo passo seguro dentro da F3:** manter a F3.11 fechada ate os
  cenarios do appliance (`DR-05`) ficarem verdes em nova verificacao de
  readiness; sem isso, a campanha seguinte nao deve ser tratada como rodada
  valida de fechamento.
- **Nota 2026-04-24:** `CORTEX`, backlog e `CHANGELOG` explicitam
  publicado `1.8.3` vs `PORTVERSION` de trabalho `1.8.11`; download
  administrativo do `.lic` concentra-se em `license-download-policy.js` com
  teste e rota a reusar a politica.

### Criterios de saida

- fluxo de licenciamento explicado por estado e transicao;
- casos de falha principais cobertos por matriz manual e evidencias minimas;
- comportamento offline deixa de depender de tentativa e erro documental;
- os cenarios obrigatorios da F3.6/F3.7/F3.8 ficam executados com outputs
  reais antes do fecho formal da fase;
- existe relatorio final unico de campanha com decisao explicita `F3 pode
  fechar` ou `F3 nao pode fechar`.

### Gate

- activacao, revogacao, expiracao e modo offline ficam previsiveis e
  rastreaveis;
- nenhum "OK" de licenciamento e aceite sem evidencia minima de backend e,
  quando aplicavel, de appliance;
- nenhum cenario obrigatorio da F3 fecha com `FAIL`, `INCONCLUSIVE` ou
  `BLOCKED`.

### Documentacao obrigatoria da fase

- `CORTEX.md`
- `docs/01-architecture/f3-arquitetura-licenciamento-ativacao.md`
- `docs/01-architecture/f3-fingerprint-e-binding.md`
- `docs/01-architecture/f3-expiracao-revogacao-grace.md`
- `docs/01-architecture/f3-mutacao-admin-reemissao-guardrails.md`
- `docs/01-architecture/f3-emissao-reemissao-rastreabilidade.md`
- `docs/01-architecture/f3-validacao-manual-evidencias.md`
- `docs/01-architecture/f3-pack-operacional-validacao.md`
- `docs/01-architecture/f3-gate-fechamento-validacao.md`
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

- (Normativo) F3 concluida; (Execução) em `2026-04-24` autorizou-se
  **paralelismo** documentado: F4.0+ pode avançar enquanto a F3 permanece
  aberta com pendência **DR-05** e relatorio de campanha, **sem** declarar
  F3 fechada e **sem** alterar contrato de licenciamento em blocos F4
  (ver seccao 0 de [`f4-plano-de-implementacao.md`](f4-plano-de-implementacao.md)).
- Backlog e riscos priorizados.

### Checkpoint actual (F4)

- **F4.0 `2026-04-24`:** `f4-plano-de-implementacao.md` e
  `f5-preparacao-malha.md` criados; CORTEX e roadmap actualizados; política
  de trabalho F4 em paralelo ao fecho de evidência F3 (DR-05) explícita.
- **F4.2 bloco `2026-04-24`:** `update-blacklists.sh` — `send_sighup` valida
  PID e exige `kill -0` antes de `HUP` (alinhado à trilha F4.1 no daemon);
  `--restore-lkg` partilha lock com o update; `layer7-pfctl` usa `/sbin/pfctl`
  de forma consistente; `PORTVERSION` `1.8.11`.
- **Seguinte:** evidencia em appliance/lab (BG-009/010: validacao segundo
  [`validacao-lab.md`](../04-package/validacao-lab.md) e
  `scripts/package/smoke-layer7d.sh` no builder); F4.3 (BG-011) em bloco
  proprio; fecho formal de BG-009/010 quando a matriz de saida F4 tiver
  evidencia minima; em paralelo, **DR-05** no appliance `192.168.100.254` para
  fechar a F3 sob gate F3.8.

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
