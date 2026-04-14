# Classificacao Documental

## Finalidade

Este documento regista formalmente o papel de cada documento Markdown actual
do repositório apos a consolidacao da F0.

### Classes usadas

- **Canónico**: fonte activa de verdade para o tema.
- **Suplementar**: apoio valido, mas nao e a primeira fonte de decisao.
- **Historico**: preservado por contexto/rastreabilidade; pode conter estado antigo.
- **Placeholder**: template, esqueleto ou stub para uso futuro.
- **Preservado por compatibilidade**: mantido no caminho actual porque ainda
  serve navegacao, links, clientes ou processos, embora nao seja SSOT.

**Regra:** se houver conflito entre um documento canónico e um historico,
vence o canónico.

---

## 1. Raiz do repositório

| Arquivo | Tema | Papel actual | Classificacao | Substitui ou e substituido por | Accao futura sugerida | Fase |
|---------|------|--------------|---------------|--------------------------------|-----------------------|------|
| `CORTEX.md` | estado global | SSOT operacional e checkpoint | Canónico | substitui status disperso da raiz e resumos antigos | manter vivo a cada fase | F0+ |
| `AGENTS.md` | execucao por agentes | regra de actuacao do agente | Canónico | substitui prompt operativo disperso | manter vivo a cada fase | F0+ |
| `README.md` | visao publica do repo | porta de entrada publica do projecto | Preservado por compatibilidade | complementar a `docs/README.md` | rever apenas quando o posicionamento publico mudar | F7 |
| `00-LEIA-ME-PRIMEIRO.md` | onboarding original | contexto historico do arranque do projecto | Historico | substituido por `CORTEX.md` + `docs/README.md` | preservar; nao usar como SSOT | F6 |
| `01-VISAO-GERAL-E-ESCOPO.md` | escopo V1 detalhado | base expandida do charter | Historico | complementar a `docs/00-overview/product-charter.md` | preservar e referenciar so quando precisar de contexto longo | F6 |
| `02-ARQUITETURA-ALVO.md` | arquitectura detalhada | contexto expandido da arquitectura | Historico | complementar a `docs/01-architecture/target-architecture.md` + `docs/core/` | preservar | F6 |
| `03-ROADMAP-E-FASES.md` | roadmap V1-V2 original | referencia historica do plano antigo | Historico | substituido por `docs/02-roadmap/roadmap.md` | preservar | F6 |
| `04-BACKLOG-MVP-E-VERSOES.md` | backlog antigo | backlog historico pre-F0 | Historico | substituido por `docs/02-roadmap/backlog.md` | preservar | F6 |
| `05-ESTRUTURA-REPOSITORIO-CURSOR-GITHUB.md` | estrutura desejada do repo | contexto historico de organizacao | Historico | complementar a `docs/README.md` e equivalencia documental | preservar | F6 |
| `06-PADROES-DE-DESENVOLVIMENTO-E-SEGURANCA.md` | padroes gerais | base historica de disciplina | Historico | complementar a `AGENTS.md` + checklist mestre | preservar | F6 |
| `07-PLANO-DE-IMPLEMENTACAO-PASSO-A-PASSO.md` | plano V1 original | trilha historica de implementacao | Historico | complementar ao roadmap canónico | preservar | F6 |
| `08-PLANO-DE-TESTES-E-HOMOLOGACAO.md` | testes V1 originais | plano expandido historico | Historico | complementar a `docs/tests/README.md` e `docs/tests/test-matrix.md` | preservar | F6 |
| `09-EMPACOTAMENTO-PFSENSE-E-DISTRIBUICAO.md` | distribuicao antiga | contexto expandido de empacotamento | Historico | complementar a `docs/10-license-server/MANUAL-INSTALL.md` e `docs/06-releases/README.md` | preservar | F6 |
| `10-RUNBOOK-OPERACIONAL-E-ROLLBACK.md` | operacao e rollback | runbook expandido legado | Historico | complementar a `docs/05-runbooks/` e `MANUAL-INSTALL.md` | preservar | F6 |
| `11-RISCOS-LIMITACOES-E-DECISOES.md` | riscos originais | contexto historico de risco | Historico | complementar ao `CORTEX.md` e backlog | preservar | F6 |
| `12-PLANO-DE-DOCUMENTACAO-E-GITHUB.md` | plano de docs antigo | origem da governanca documental | Historico | substituido por `docs/README.md` + roadmap/checklist | preservar | F6 |
| `13-MODELOS-DE-ISSUES-E-PRS.md` | modelos de governance | referencia util, nao activa | Suplementar | complementar a `.github/pull_request_template.md` e ADR index | preservar e reutilizar conforme necessidade | F7 |
| `14-CHECKLIST-MESTRE.md` | checklist antigo | base historica de gates V1 | Historico | substituido por `docs/02-roadmap/checklist-mestre.md` | preservar | F6 |
| `15-PROMPT-MESTRE-CURSOR.md` | prompt antigo | memoria de abordagem anterior | Historico | complementar a `AGENTS.md` | preservar; nao usar como SSOT | F6 |
| `16-REFERENCIAS-OFICIAIS.md` | links externos | lista curta de referencias | Suplementar | sem equivalente directo | manter enquanto util | F7 |
| `release-body.md` | texto de release | artefacto auxiliar de publicacao | Preservado por compatibilidade | complementar a `docs/06-releases/` e changelog | rever quando release engineering entrar | F7 |
| `logica.md` | notas avulsas | material fora da governanca formal | Placeholder | sem equivalente canónico | preservar sem expandir | F6 |
| `.github/pull_request_template.md` | template de PR | apoio leve a mudancas | Suplementar | complementar a `AGENTS.md` e `13-MODELOS-DE-ISSUES-E-PRS.md` | manter alinhado quando fluxo de PR mudar | F7 |

---

## 2. Centro documental `docs/`

| Arquivo | Tema | Papel actual | Classificacao | Substitui ou e substituido por | Accao futura sugerida | Fase |
|---------|------|--------------|---------------|--------------------------------|-----------------------|------|
| `docs/README.md` | indice oficial | porta de entrada canónica da arvore `docs/` | Canónico | substitui a falta de indice formal | manter vivo | F0+ |
| `docs/00-overview/product-charter.md` | charter | resumo canónico de produto/escopo | Canónico | resume `01-VISAO-GERAL-E-ESCOPO.md` | manter vivo | F0+ |
| `docs/00-overview/document-classification.md` | classificacao | matriz de estatuto dos docs | Canónico | novo | manter vivo quando surgirem conflitos | F0+ |
| `docs/00-overview/document-equivalence-map.md` | equivalencia | mapa raiz <-> docs | Canónico | novo | manter vivo ate a F6 | F0-F6 |
| `docs/00-overview/f3-11-start-here.md` | entrada unica F3.11 | ponto unico de entrada operacional da trilha F3.11 | Canónico | novo | manter vivo enquanto a F3.11 estiver aberta e bloqueada | F3 |
| `docs/00-overview/f3-organizacao-local-e-fecho.md` | organizacao local e fecho F3 | mapa canónico da pasta local e do caminho estrito para concluir a F3 sem misturar alvos | Canónico | novo | manter vivo enquanto a F3 estiver aberta; remover ou absorver apos o fecho real da F3 | F3 |
| `docs/00-overview/f3-11-document-traceability-map.md` | rastreabilidade F3.11 | mapa canónico de navegacao e de registo entre os artefactos da trilha F3.11 | Canónico | novo | manter vivo enquanto a F3.11 depender de multiplos artefactos coordenados | F3 |
| `docs/01-architecture/target-architecture.md` | arquitectura | resumo canónico da arquitectura | Canónico | resume `02-ARQUITETURA-ALVO.md` | manter vivo | F0+ |
| `docs/01-architecture/f1-arquitetura-de-confianca.md` | arquitectura F1 | consolidado da cadeia de confianca, blacklists e fallback | Canónico | novo | manter vivo durante F1 e rever quando a implementacao avancar | F1 |
| `docs/01-architecture/f2-arquitetura-license-server.md` | arquitectura F2 | consolidado canónico da publicacao segura, sessao e hardening do license server | Canónico | novo | manter vivo durante a F2 | F2 |
| `docs/01-architecture/f3-arquitetura-licenciamento-ativacao.md` | arquitectura F3 | consolidado canónico do contrato de licenciamento, estados/transicoes e endurecimento minimo da activacao | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-fingerprint-e-binding.md` | fingerprint e binding F3.2 | consolidado canónico da matriz real de fingerprint/binding em appliance e da politica conservadora de compatibilidade | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-expiracao-revogacao-grace.md` | expiracao/revogacao/grace F3.3 | consolidado canónico da semantica real de expiracao, revogacao, validade offline e grace local | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-mutacao-admin-reemissao-guardrails.md` | mutacao admin/reemissao F3.4 | consolidado canónico da superficie administrativa, da imutabilidade parcial apos bind e dos guardrails minimos contra transferencia silenciosa | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-emissao-reemissao-rastreabilidade.md` | emissao/reemissao/rastreabilidade F3.5 | consolidado canónico da trilha real de emissao do `.lic`, da governanca de reemissao e da rastreabilidade minima do artefacto | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-validacao-manual-evidencias.md` | validacao manual/evidencias F3.6 | consolidado canónico da matriz manual de cenarios, evidencias minimas, comandos objectivos e politica de validacao suficiente da F3 | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-pack-operacional-validacao.md` | pack operacional F3.7 | consolidado canónico da estrutura de evidencias, convencoes de nomes, estados de resultado e runbook operacional da validacao manual | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-gate-fechamento-validacao.md` | gate de fechamento F3.8 | consolidado canónico do gate oficial de saida da F3, da matriz objectiva de decisao por cenario e da classificacao de pendencias bloqueantes/nao bloqueantes | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-fecho-operacional-restante.md` | fecho restante da F3 | mapa canónico curto do que falta, por alvo, para concluir a F3 com evidencia real | Canónico | novo | manter vivo enquanto a F3 estiver aberta; remover ou absorver apos o fecho real da F3 | F3 |
| `docs/01-architecture/f3-11-readiness-check.md` | readiness check F3.11 | consolidado canónico da verificacao objectiva inicial da readiness da F3.11 e do bloqueio formal por pre-requisitos | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-11-readiness-saneamento.md` | saneamento minimo da readiness F3.11 | consolidado canónico do saneamento parcial da readiness da F3.11 sem abertura de campanha | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-11-access-enablement-package.md` | pacote de habilitacao de acessos F3.11 | consolidado canónico da lista minima de acessos, evidencias, criterios de liberacao e matriz operacional de bloqueios remanescentes | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-11-drift-registry.md` | drift registry F3.11 | consolidado canónico do registo cumulativo de drifts entre F3.9 e o estado actual da F3.11 | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-11-external-input-request-package.md` | pacote de solicitacao externa F3.11 | consolidado canónico historico/compatibilidade do pedido formal dos cinco insumos externos; no estado corrente so reabre com drift novo | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-11-input-acceptance-matrix.md` | matriz de aceite F3.11 | consolidado canónico historico/compatibilidade do gate de aceite/rejeicao dos cinco insumos; no estado corrente o gate e `DR-05` | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-11-execution-master-register.md` | registro mestre de execucao F3.11 | cockpit canónico central do estado operacional corrente, blockers, evidencias e proximos passos da F3.11 | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-11-operational-decisions-ledger.md` | ledger de decisoes operacionais F3.11 | registo cumulativo e auditavel das microdecisoes operacionais da trilha F3.11 | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-11-readiness-scorecard.md` | scorecard de readiness F3.11 | pagina executiva canónica de `GO/NO-GO`, resumo dos insumos, blockers e drifts da F3.11 | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-11-state-machine.md` | maquina de estados F3.11 | definicao canónica dos estados, entradas, saidas e transicoes permitidas da trilha F3.11 | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-11-document-sync-protocol.md` | protocolo de sincronizacao F3.11 | ordem canónica de actualizacao, prevalencia e fecho de ciclo entre os artefactos da trilha | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-11-operational-responsibility-matrix.md` | responsabilidade operacional F3.11 | matriz canónica de papeis, limites de decisao e handoffs da trilha F3.11 | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/01-architecture/f3-11-readiness-reopen-gate.md` | gate de reabertura da readiness F3.11 | consolidado canónico do `go/no-go` para repetir a readiness e distinguir blockers da readiness vs blockers da campanha | Canónico | novo | manter vivo durante a F3 | F3 |
| `docs/02-roadmap/roadmap.md` | fases F0-F7 | roadmap canónico | Canónico | substitui `03-ROADMAP-E-FASES.md` como SSOT | manter vivo | F0+ |
| `docs/02-roadmap/backlog.md` | backlog | backlog unico priorizado | Canónico | substitui `04-BACKLOG-MVP-E-VERSOES.md` | manter vivo | F0+ |
| `docs/02-roadmap/checklist-mestre.md` | gates | checklist mestre da evolucao segura | Canónico | substitui `14-CHECKLIST-MESTRE.md` | manter vivo | F0+ |
| `docs/02-roadmap/f1-plano-de-implementacao.md` | plano F1 | ordem segura de implementacao futura da F1 | Canónico | novo | manter vivo ate ao fecho tecnico da F1 | F1 |
| `docs/02-roadmap/f2-plano-de-implementacao.md` | plano F2 | ordem segura de implementacao da F2 | Canónico | novo | manter vivo ate ao fecho tecnico da F2 | F2 |
| `docs/03-adr/README.md` | indice ADR | politica e indice de decisoes | Canónico | substitui resumo antigo insuficiente | manter vivo | F0+ |
| `docs/03-adr/ADR-0001-engine-classificacao-ndpi.md` | decisao nDPI | ADR aceite e normativo | Canónico | — | manter | F0+ |
| `docs/03-adr/ADR-0002-distribuicao-artefato-txz.md` | distribuicao antiga | ADR preservado para rastreabilidade | Historico | precisa de ADR substituto | preservar ate ser superado formalmente | F1/F7 |
| `docs/03-adr/ADR-0003-hierarquia-oficial-de-distribuicao.md` | distribuicao oficial | ADR normativo da hierarquia oficial de distribuicao | Canónico | substitui ADR-0002 como referencia operacional | manter vivo | F1+ |
| `docs/03-adr/ADR-0004-cadeia-de-confianca-dos-artefatos.md` | confianca de artefactos | ADR normativo da autenticidade e integridade de artefactos | Canónico | novo | manter vivo | F1+ |
| `docs/03-adr/ADR-0005-pipeline-seguro-de-blacklists.md` | blacklists seguras | ADR normativo do pipeline seguro de blacklists | Canónico | novo | manter vivo | F1+ |
| `docs/03-adr/ADR-0006-fallback-e-degradacao-segura.md` | fallback seguro | ADR normativo da degradacao segura | Canónico | novo | manter vivo | F1+ |
| `docs/03-adr/ADR-0007-publicacao-segura-license-server.md` | publicacao segura | ADR normativo da F2.1 para TLS, edge proxy e fronteiras de rede do license server | Canónico | novo | manter vivo | F2+ |
| `docs/03-adr/ADR-0008-autenticacao-e-sessao-license-server.md` | autenticacao e sessao | ADR normativo da F2.2 para login, sessao e armazenamento do estado administrativo | Canónico | novo | manter vivo | F2+ |
| `docs/03-adr/ADR-0009-protecao-superficie-administrativa-license-server.md` | superficie administrativa | ADR normativo da F2.3 para CORS, brute force e superfícies administrativas | Canónico | novo | manter vivo | F2+ |
| `docs/03-adr/ADR-0010-integridade-transacional-e-validacao-crud-license-server.md` | CRUD e integridade | ADR normativo da F2.4 para validacao e transacoes do CRUD | Canónico | novo | manter vivo | F2+ |
| `docs/04-package/README.md` | package docs | indice local da area | Suplementar | complementar ao roadmap e `MANUAL-INSTALL.md` | manter leve | F4/F5 |
| `docs/04-package/checklist-validacao-lab.md` | validacao rapida | checklist operacional de lab | Suplementar | complementar a `validacao-lab.md` | rever quando a F5 fechar a malha de testes | F5 |
| `docs/04-package/deploy-github-lab.md` | deploy lab antigo | fluxo historico de distribuicao | Historico | parcialmente substituido por docs de release e install | preservar; harmonizar mais tarde | F7 |
| `docs/04-package/package-skeleton.md` | esqueleto do package | memoria util da fase inicial | Historico | complementar a package docs | preservar | F6 |
| `docs/04-package/validacao-lab.md` | evidencia em appliance | runbook de validacao de package/lab | Suplementar | complementar a `docs/tests/` | harmonizar termos de artefacto na F4/F5 | F4/F5 |
| `docs/04-tests/README.md` | tests stub | indice antigo e minimo | Historico | sobreposto por `docs/tests/README.md` | preservar ate consolidacao estrutural | F6 |
| `docs/05-daemon/README.md` | daemon overview | resumo tecnico desactualizado em partes | Historico | complementar a `docs/core/` e changelog | rever na F4 ou F6 | F4/F6 |
| `docs/05-daemon/pf-enforcement.md` | enforcement PF | explicacao detalhada do enforcement | Suplementar | complementar ao changelog e docs core | manter/rever quando F4 mexer em enforcement | F4 |
| `docs/05-runbooks/README.md` | indice de runbooks | agregador operacional local | Suplementar | complementar ao `MANUAL-INSTALL.md` | manter leve | F4/F7 |
| `docs/05-runbooks/license-server-publicacao-segura.md` | publicacao segura do license server | runbook operativo da F2.1 para edge proxy, TLS, origin privado e validacoes de exposicao | Canónico | complementar ao ADR-0007 e ao `MANUAL-USO-LICENCAS.md` | manter vivo enquanto a F2/F7 exigirem governanca da borda | F2/F7 |
| `docs/05-runbooks/license-server-auth-sessao.md` | auth/sessao do license server | runbook operativo da F2.2 para login, sessao administrativa, expiracao, logout e troubleshooting | Canónico | complementar ao ADR-0008 e ao `MANUAL-USO-LICENCAS.md` | manter vivo enquanto a F2/F7 exigirem governanca da superficie administrativa | F2/F7 |
| `docs/05-runbooks/license-server-segredos-bootstrap.md` | segredos e bootstrap do license server | runbook operativo da F2.5 para ownership minimo, segredo bootstrap e recuperacao de password administrativa | Canónico | complementar aos ADRs da F2 e ao `MANUAL-USO-LICENCAS.md` | manter vivo enquanto a operacao do server exigir custodia clara de segredos | F2/F7 |
| `docs/05-runbooks/license-server-backup-restore.md` | backup/restore do license server | runbook operativo da F2.5 para dump SQL, restore controlado e recuperacao minima do PostgreSQL | Canónico | complementar ao runbook de segredos/bootstrap e ao `MANUAL-USO-LICENCAS.md` | manter vivo enquanto a operacao do server exigir recuperacao basica | F2/F7 |
| `docs/05-runbooks/f3-11-live-access-checklist.md` | checklist live F3.11 | runbook operativo canonico para validar acessos, host, schema, admin, appliance e inventario antes de nova readiness/campanha da F3.11 | Canónico | novo | manter vivo enquanto a F3.11 permanecer bloqueada ou em preparacao | F3 |
| `docs/05-runbooks/f3-11-input-triage-runbook.md` | triagem de entrega F3.11 | runbook operativo canonico para receber, validar, aceitar ou rejeitar cada insumo externo antes da readiness | Canónico | novo | manter vivo enquanto a F3.11 permanecer bloqueada ou em preparacao | F3 |
| `docs/05-runbooks/f3-11-evidence-intake-template.md` | intake de evidencias F3.11 | template canónico preenchivel para registar recepcao, validacao e conclusao de cada insumo externo | Canónico | novo | manter vivo enquanto a F3.11 permanecer bloqueada ou em preparacao | F3 |
| `docs/05-runbooks/f3-11-cycle-report-template.md` | ciclo operacional F3.11 | template canónico da rodada completa de recepcao, triagem, actualizacao de blockers e decisao final da F3.11 | Canónico | novo | manter vivo enquanto a F3.11 permanecer bloqueada ou em preparacao | F3 |
| `docs/05-runbooks/f3-11-cycle-closure-criteria.md` | fecho de ciclo F3.11 | criterio canónico do que abre, fecha, invalida ou mantem um ciclo operacional da F3.11 | Canónico | novo | manter vivo enquanto a F3.11 permanecer bloqueada ou em preparacao | F3 |
| `docs/05-runbooks/pfsense-webgui-safety.md` | seguranca de lab | runbook especifico de lab | Suplementar | — | manter | F4/F5 |
| `docs/05-runbooks/rollback.md` | rollback | rollback do pacote Layer7 | Canónico | complementar ao `MANUAL-INSTALL.md` | manter vivo | F4/F7 |
| `docs/06-releases/README.md` | governanca de release | indice canónico de release docs | Canónico | substitui indicacoes antigas dispersas | manter vivo | F7 |
| `docs/06-releases/RELEASE-SIGNING.md` | cadeia de release assinada | guia canónico da F1.2 para manifesto, assinatura e validacao | Canónico | detalha a operacao prevista pelos ADR-0003/0004 | manter vivo durante F1/F7 | F1/F7 |
| `docs/06-releases/release-notes-template.md` | template release | modelo reutilizavel | Suplementar | — | manter | F7 |
| `docs/06-releases/release-notes-v0.1.0.md` | release antiga | release notes historicas | Historico | — | preservar | F7 |
| `docs/07-prompts/README.md` | prompts/IA | area auxiliar de prompts | Suplementar | complementar a `AGENTS.md` | manter leve; nao tratar como SSOT | F6 |
| `docs/07-prompts/next-chat-phase-a-option1.md` | continuidade antiga | prompt historico de fase antiga | Historico | substituido pelo checkpoint do `CORTEX.md` | preservar | F6 |
| `docs/08-lab/README.md` | indice do lab | agregador da area de laboratorio | Suplementar | complementar ao roadmap e package docs | manter leve | F4/F5 |
| `docs/08-lab/builder-freebsd.md` | builder | guia operacional do builder | Suplementar | complementar ao `AGENTS.md` | rever quando F1/F4 mexerem no builder | F1/F4 |
| `docs/08-lab/guia-windows.md` | desenvolvimento Windows | apoio lateral | Suplementar | — | manter se continuar util | F6 |
| `docs/08-lab/lab-inventory.template.md` | inventario de lab | template local | Placeholder | — | preencher localmente quando necessario | F4/F5 |
| `docs/08-lab/lab-topology.md` | topologia de lab | referencia de ambiente | Suplementar | — | manter | F4/F5 |
| `docs/08-lab/quick-start-lab.md` | fluxo rapido antigo | runbook historico com termos pre-V1 | Historico | parcialmente substituido por `MANUAL-INSTALL.md` e validacao-lab | preservar e rever na F5/F6 | F5/F6 |
| `docs/08-lab/snapshots-e-gate.md` | snapshots | runbook de gate de lab | Suplementar | — | manter | F5 |
| `docs/08-lab/syslog-remote.md` | syslog remoto | guia especifico de lab | Suplementar | complementar a docs de logging | manter | F5/F7 |
| `docs/09-blocking/README.md` | trilha de bloqueio | resumo de trilha concluida | Historico | complementar ao changelog | preservar | F6 |
| `docs/09-blocking/blocking-master-plan.md` | plano de bloqueio | referencia historica de implementacao concluida | Historico | — | preservar | F6 |
| `docs/10-license-server/MANUAL-INSTALL.md` | instalacao/upgrade/uninstall | manual operacional principal do pacote | Canónico | substitui instrucoes operacionais dispersas | manter sempre sincronizado | F0+ |
| `docs/10-license-server/MANUAL-USO-LICENCAS.md` | operacao de licencas | manual canónico do uso do sistema de licencas | Canónico | — | manter vivo quando F2/F3 mexerem em licenciamento | F2/F3 |
| `docs/10-license-server/PLANO-LICENSE-SERVER.md` | plano completo server | base detalhada da trilha do servidor | Suplementar | complementar ao backlog/roadmap | manter como referencia da F2 | F2 |
| `docs/10-logging/README.md` | logging | referencia de observabilidade minima | Suplementar | complementar ao changelog e runbooks | rever na F7 | F7 |
| `docs/11-blacklists/PLANO-BLACKLISTS-UT1.md` | plano blacklists | documento canónico da trilha blacklists | Canónico | — | manter para F4 | F4 |
| `docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md` | regras blacklists | documento canónico de implementacao da trilha | Canónico | — | manter para F4 | F4 |
| `docs/11-blacklists/GUIA-PASSO-A-PASSO.md` | execucao detalhada | guia complementar da trilha | Suplementar | complementar ao plano e directrizes | manter para F4 | F4 |
| `docs/11-blacklists/REGRAS-QUALIDADE.md` | qualidade blacklists | apoio tecnico da trilha | Suplementar | complementar as directrizes | manter para F4 | F4 |
| `docs/12-reports/MANUAL-RELATORIOS-EXECUTIVOS.md` | relatorios | manual especifico do modulo de relatorios | Suplementar | — | rever quando a observabilidade entrar forte | F7 |
| `docs/changelog/CHANGELOG.md` | changelog | linha temporal oficial | Canónico | substitui historicos dispersos de entrega | manter vivo | F0+ |
| `docs/commercial/LAYER7-PRODUCT-OVERVIEW-PT.md` | collateral PT | material comercial/publico | Suplementar | — | rever quando docs publicas forem refrescadas | F7 |
| `docs/commercial/LAYER7-PRODUCT-OVERVIEW-EN.md` | collateral EN | material comercial/publico | Suplementar | — | rever quando docs publicas forem refrescadas | F7 |
| `docs/core/README.md` | indice do core | entrada canónica do modelo tecnico | Canónico | resume e aponta para docs core | manter vivo | F0+ |
| `docs/core/categories.md` | categorias | referencia canónica de categorias | Canónico | — | manter/rever quando politica mudar | F4/F5 |
| `docs/core/config-model.md` | configuracao | referencia canónica de persistencia/config | Canónico | — | manter | F4/F5 |
| `docs/core/event-model.md` | eventos | referencia canónica do evento | Canónico | — | manter | F5/F7 |
| `docs/core/ndpi-update-strategy.md` | nDPI | estrategia tecnica especializada | Suplementar | — | rever quando houver mudanca de engine/update | F4/F7 |
| `docs/core/policy-matrix.md` | politica | referencia canónica de decisoes/policy | Canónico | — | manter | F4/F5 |
| `docs/core/precedence.md` | precedencia | referencia canónica de ordem de avaliacao | Canónico | — | manter | F4/F5 |
| `docs/core/runtime-state.md` | estado runtime | referencia canónica do estado interno | Canónico | — | manter | F4/F5 |
| `docs/package/gui-validation.md` | validacao GUI | material auxiliar de validacao | Suplementar | complementar a package docs | rever na F5 | F5 |
| `docs/poc/README.md` | POC index | memoria util da fase POC | Suplementar | — | manter como historico de suporte | F6 |
| `docs/poc/resultados-poc.template.md` | resultados POC | template de evidencias | Placeholder | — | preencher apenas se a POC for retomada | F6 |
| `docs/tests/README.md` | estrategia de testes | entrada canónica da area de testes | Canónico | sobrepoe `docs/04-tests/README.md` | manter vivo | F5 |
| `docs/tests/test-matrix.md` | matriz de testes | matriz canónica de referencia | Canónico | complementar ao plano antigo de testes | manter/rever na F5 | F5 |
| `docs/tests/templates/f3-scenario-evidence.md` | template de evidencia F3 | template minimo para registo operacional por cenario da F3.7 | Placeholder | novo | manter enquanto a F3 estiver aberta | F3/F5 |
| `docs/tests/templates/f3-validation-campaign-report.md` | relatorio final de campanha F3 | template canónico do relatorio final unico da campanha de validacao da F3.8 | Placeholder | novo | manter enquanto a F3 estiver aberta | F3/F5 |
| `docs/tutorial/guia-completo-layer7.md` | tutorial longo | guia amplo preservado para contexto e clientes | Preservado por compatibilidade | nao substitui `MANUAL-INSTALL.md` nem `CORTEX.md` | refrescar numa fase posterior | F7 |

---

## 3. Documentacao embutida em areas de codigo e scripts

| Arquivo | Tema | Papel actual | Classificacao | Substitui ou e substituido por | Accao futura sugerida | Fase |
|---------|------|--------------|---------------|--------------------------------|-----------------------|------|
| `package/pfSense-pkg-layer7/README.md` | package local | orientacao local do port, com drift historico | Preservado por compatibilidade | nao substitui docs canónicas de instalacao/release | rever so na F6/F7 | F6/F7 |
| `samples/README.md` | amostras | apoio leve de navegacao | Suplementar | — | manter leve | F6 |
| `scripts/build/BUILDER.md` | ordem de build | nota util de build | Suplementar | complementar a docs de lab e AGENTS | rever quando builder mudar | F1/F4 |
| `scripts/build/README.md` | build scripts | indice curto | Suplementar | — | manter leve | F6 |
| `scripts/diagnostics/README.md` | diagnostics scripts | stub para uso futuro | Placeholder | — | manter sem expandir ate existir conteudo | F6 |
| `scripts/lab/LAB-SETUP.md` | setup do lab | guia local do lab | Suplementar | complementar a docs de lab | rever se o lab mudar | F5 |
| `scripts/lab/README.md` | automacao lab | indice curto | Suplementar | — | manter leve | F6 |
| `scripts/license-validation/export-license-evidence.sh` | exportacao de evidencias F3 | helper shell barato para exportar snapshot de licenca e trilhas de auditoria sem tocar no produto | Suplementar | novo | manter apenas como apoio operacional conservador | F3/F5 |
| `scripts/license-validation/export-appliance-evidence.sh` | exportacao de baseline/evidencias do appliance F3 | helper shell barato para recolher baseline local, stats JSON, fingerprint e `.lic` do appliance sem tocar no produto | Suplementar | novo | manter apenas como apoio operacional conservador | F3/F5 |
| `scripts/license-validation/export-live-preflight.sh` | exportacao de preflight live F3 | helper shell barato para recolher health/login/CORS e materializar os artefactos raiz de deploy/admin da campanha | Suplementar | novo | manter apenas como apoio operacional conservador | F3/F5 |
| `scripts/license-validation/export-schema-preflight.sh` | exportacao de preflight schema F3 | helper shell barato para materializar o artefacto raiz de schema da campanha a partir do PostgreSQL observado | Suplementar | novo | manter apenas como apoio operacional conservador | F3/F5 |
| `scripts/license-validation/prepare-f3-preflight.sh` | orquestracao de preflight F3 | helper shell barato para inicializar a campanha e encadear os helpers de live/schema/appliance no mesmo `run_id` | Suplementar | novo | manter apenas como apoio operacional conservador | F3/F5 |
| `scripts/license-validation/init-f3-validation-campaign.sh` | inicializacao de campanha F3 | helper shell barato para materializar directoria de campanha, manifest inicial e relatorio final da F3.8 sem tocar no produto | Suplementar | novo | manter apenas como apoio operacional conservador | F3/F5 |
| `scripts/package/README.md` | scripts do pacote | apoio tecnico local | Suplementar | complementar a docs package/tests | rever na F5/F7 | F5/F7 |
| `scripts/release/README.md` | release/frota | referencia local de release | Suplementar | complementar a `docs/06-releases/` | rever quando F7 abrir | F7 |
| `src/README.md` | mapa do codigo | guia local antigo da arvore de codigo | Historico | complementar a docs core | rever na F6 | F6 |
| `src/layer7d/README.md` | daemon source | contexto local do daemon | Suplementar | complementar a docs core/daemon | rever na F4/F6 | F4/F6 |
| `src/poc_ndpi/README.md` | POC nDPI | memoria tecnica da POC | Suplementar | complementar a docs/poc | preservar | F6 |
| `webgui/README.md` | gui local | stub de navegacao | Historico | complementar a docs package/gui | rever na F6 | F6 |

---

## Regras de manutencao desta classificacao

1. Sempre que nascer documento novo de governanca, classificá-lo aqui.
2. Sempre que um documento deixar de ser SSOT, marcar explicitamente a sua
   nova classe.
3. Nao apagar historico apenas porque ficou antigo; primeiro classificar.
4. Mover/renomear so na F6, depois de rever tambem o mapa de equivalencia.
