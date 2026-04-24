# Mapa de Equivalencia Documental

## Finalidade

Este mapa mostra como os documentos da raiz se relacionam com o centro
documental canónico em `docs/`.

Ele existe para evitar tres erros comuns:

1. abrir um documento historico e tratá-lo como SSOT;
2. assumir que raiz e `docs/` dizem exactamente a mesma coisa;
3. mover ficheiros cedo demais sem entender o impacto.

---

## Legenda de relacao

- **Equivalencia**: o documento em `docs/` cobre o mesmo papel de forma mais actual.
- **Complementaridade**: ambos sao uteis, mas o canónico tem prioridade.
- **Sobreposicao**: os dois tratam o tema com intersecao parcial.
- **Conflito**: existe divergencia material; seguir a fonte canónica indicada.
- **Sem par directo**: o documento fica preservado por contexto, sem espelho claro.

---

## Mapa raiz -> docs

| Documento da raiz | Correspondente em `docs/` | Fonte canónica | Relacao | Onde ha conflito/sobreposicao | Accao futura |
|-------------------|---------------------------|----------------|---------|-------------------------------|--------------|
| `CORTEX.md` | `docs/README.md`, `docs/02-roadmap/*` | `CORTEX.md` | Complementaridade | `CORTEX` manda no estado global; `docs/` organiza a arvore | manter assim |
| `AGENTS.md` | `docs/README.md`, `docs/02-roadmap/checklist-mestre.md` | `AGENTS.md` | Complementaridade | `AGENTS` governa o comportamento do agente; `docs/` governa o sistema documental | manter assim |
| `README.md` | `docs/README.md`, `docs/tutorial/guia-completo-layer7.md` | `docs/README.md` para navegacao interna; `README.md` para apresentacao publica | Complementaridade | `README.md` e externo; `docs/README.md` e interno/canónico | preservar ambos |
| `00-LEIA-ME-PRIMEIRO.md` | `docs/README.md` + `CORTEX.md` | `CORTEX.md` / `docs/README.md` | Sobreposicao | onboarding antigo vs onboarding canónico novo | preservar ate F6 |
| `01-VISAO-GERAL-E-ESCOPO.md` | `docs/00-overview/product-charter.md` | `docs/00-overview/product-charter.md` | Equivalencia | o ficheiro da raiz e mais detalhado; o `docs/` e o resumo normativo | preservar como expandido |
| `02-ARQUITETURA-ALVO.md` | `docs/01-architecture/target-architecture.md` + `docs/core/README.md` | `docs/01-architecture/target-architecture.md` | Equivalencia | raiz detalha; `docs/` sintetiza e aponta para o core | preservar como expandido |
| `03-ROADMAP-E-FASES.md` | `docs/02-roadmap/roadmap.md` | `docs/02-roadmap/roadmap.md` | Conflito | a raiz fala em fases V0/V1/V2; o canónico agora usa F0-F7 aprovadas | preservar como historico |
| `04-BACKLOG-MVP-E-VERSOES.md` | `docs/02-roadmap/backlog.md` | `docs/02-roadmap/backlog.md` | Conflito | backlog antigo centrado em V0/V1; backlog novo prioriza F1-F7 | preservar como historico |
| `05-ESTRUTURA-REPOSITORIO-CURSOR-GITHUB.md` | `docs/README.md`, classificacao e equivalencia | `docs/README.md` + mapas F0 | Sobreposicao | o documento antigo sugere estrutura; F0 declara o que e canónico agora | reavaliar na F6 |
| `06-PADROES-DE-DESENVOLVIMENTO-E-SEGURANCA.md` | `AGENTS.md` + checklist mestre | `AGENTS.md` | Equivalencia | principios continuam validos, mas a governanca activa mudou de lugar | preservar |
| `07-PLANO-DE-IMPLEMENTACAO-PASSO-A-PASSO.md` | `docs/02-roadmap/roadmap.md` + `docs/02-roadmap/backlog.md` | roadmap/backlog canónicos | Sobreposicao | a raiz descreve a trilha V1; o novo roadmap rege a continuidade pos-F0 | preservar |
| `08-PLANO-DE-TESTES-E-HOMOLOGACAO.md` | `docs/tests/README.md` + `docs/tests/test-matrix.md` | `docs/tests/*` | Equivalencia | o plano antigo continua util como detalhe expandido | preservar |
| `09-EMPACOTAMENTO-PFSENSE-E-DISTRIBUICAO.md` | `docs/10-license-server/MANUAL-INSTALL.md` + `docs/06-releases/README.md` | `MANUAL-INSTALL.md` / releases README | Sobreposicao | o texto da raiz traz contexto antigo de artefacto e fluxo | preservar |
| `10-RUNBOOK-OPERACIONAL-E-ROLLBACK.md` | `docs/05-runbooks/README.md` + `docs/05-runbooks/rollback.md` + `MANUAL-INSTALL.md` | `MANUAL-INSTALL.md` para operacao base; runbooks para detalhe | Equivalencia | runbook expandido da raiz permanece util como contexto | preservar |
| `11-RISCOS-LIMITACOES-E-DECISOES.md` | `CORTEX.md` + `docs/02-roadmap/backlog.md` + ADR index | `CORTEX.md` / backlog | Sobreposicao | os riscos activos migraram para a governanca viva | preservar |
| `12-PLANO-DE-DOCUMENTACAO-E-GITHUB.md` | `docs/README.md` + roadmap + checklist + classificacao | docs F0 | Equivalencia | o plano antigo inspirou a F0, mas ja nao e SSOT | preservar |
| `13-MODELOS-DE-ISSUES-E-PRS.md` | `.github/pull_request_template.md` + `docs/03-adr/README.md` | template de PR / ADR index | Complementaridade | o documento da raiz e uma biblioteca de modelos | manter como apoio |
| `14-CHECKLIST-MESTRE.md` | `docs/02-roadmap/checklist-mestre.md` | `docs/02-roadmap/checklist-mestre.md` | Conflito | o checklist antigo parou em fases e versoes antigas | preservar como historico |
| `15-PROMPT-MESTRE-CURSOR.md` | `AGENTS.md` + `CORTEX.md` + `docs/07-prompts/README.md` | `AGENTS.md` / `CORTEX.md` | Conflito | o prompt antigo nao governa mais a execucao | preservar como historico |
| `16-REFERENCIAS-OFICIAIS.md` | sem par directo | `16-REFERENCIAS-OFICIAIS.md` | Sem par directo | e apenas lista de referencias | manter como suplementar |
| `release-body.md` | `docs/06-releases/*` + `docs/changelog/CHANGELOG.md` | docs de release/changelog | Sobreposicao | artefacto auxiliar, nao SSOT | rever na F7 |
| `logica.md` | sem par directo | nenhum | Sem par directo | notas avulsas fora da governanca formal | preservar sem expandir |

---

## Sobreposicoes internas relevantes em `docs/`

| Area | Sobreposicao | Fonte canónica actual | Observacao |
|------|--------------|-----------------------|------------|
| testes | `docs/04-tests/README.md` vs `docs/tests/README.md` | `docs/tests/README.md` | a area `04-tests` fica historica ate F6 |
| roadmap/backlog/checklist | resumos antigos em raiz vs docs novos | `docs/02-roadmap/*` | raiz fica historica |
| instalacao | tutorial longo vs manual install | `docs/10-license-server/MANUAL-INSTALL.md` | tutorial fica preservado por compatibilidade |
| release/distribuicao | ADR-0002 `.txz` (historico) vs ADR-0003 **`.pkg`** (canonico) | `docs/03-adr/ADR-0003-hierarquia-oficial-de-distribuicao.md` + `CORTEX.md` + `docs/06-releases/README.md` + `MANUAL-INSTALL.md` | ADR-0002 preservado; confusao resolvida na hierarquia oficial |
| prompts/continuidade | `docs/07-prompts/next-chat-phase-a-option1.md` vs checkpoint do `CORTEX.md` | `CORTEX.md` | prompt antigo fica historico |

---

## Conflitos documentais formais registados na F0

1. **Roadmap**: a raiz fala em uma sequencia antiga de fases; o canónico
   actual passa a ser F0-F7.
2. **Backlog**: a raiz reflecte backlog V0/V1; o backlog canónico actual passa
   a ser o backlog por severidade/componente/fase sugerida.
3. **Artefacto de distribuicao**: ADR historico e varias docs antigas falam em
   `.txz`; o estado operacional conhecido e `.pkg`.
4. **Continuidade entre chats**: prompts antigos existiam em `docs/07-prompts`,
   mas a continuidade oficial passa a viver no `CORTEX.md`.

---

## Regra de uso antes da F6

Enquanto a reorganizacao fisica nao acontecer:

- nao mover ficheiros para “corrigir” sobreposicao;
- usar este mapa para decidir qual documento ler;
- registrar aqui novas equivalencias ou novos conflitos;
- preservar o legado em vez de o apagar.
