# Checklist Mestre — Evolucao Segura

Este checklist e o gate operacional das fases F0-F7. Ele nao substitui o
roadmap nem o backlog; ele transforma ambos em disciplina executavel.

---

## Como usar

1. Antes de abrir uma fase, passar pelo checklist de entrada.
2. Durante a execucao, manter checklist documental e de rollback vivos.
3. Antes de encerrar a fase, validar o checklist de saida e o gate.
4. Se a mudanca for documental apenas, aplicar a excepcao documental.

---

## Checklist de entrada de fase

- [ ] a fase actual esta correcta no `CORTEX.md`
- [ ] a fase a abrir esta autorizada no roadmap canónico
- [ ] o backlog foi revisto e os itens puxados para a fase foram marcados
- [ ] riscos abertos da fase anterior foram herdados ou encerrados
- [ ] existe escopo claro e exclusoes claras
- [ ] existe criterio de saida e gate definidos
- [ ] foi verificado se ADR novo e necessario
- [ ] foi verificado se a fase mexe em area com manual/runbook proprio

---

## Checklist de execucao

- [ ] um subsistema critico por vez
- [ ] objectivo, impacto, risco, teste e rollback declarados
- [ ] na F3, contrato canónico de estados/transicoes foi definido antes do primeiro endurecimento de codigo
- [ ] na F3.2, matriz operacional de fingerprint/binding foi formalizada antes de qualquer ajuste adicional de codigo
- [ ] sem refactor amplo nao solicitado
- [ ] sem mover/apagar/renomear antes da F6
- [ ] sem tratar documento historico como SSOT
- [ ] docs afectadas identificadas antes da primeira alteracao
- [ ] backlog e roadmap alinhados ao bloco em execucao

---

## Checklist de saida de fase

- [ ] criterios de saida da fase foram cumpridos
- [ ] gate da fase foi verificado
- [ ] riscos remanescentes ficaram registados no `CORTEX.md`
- [ ] backlog da proxima fase ficou actualizado
- [ ] docs canónicas ficaram coerentes entre si
- [ ] changelog foi actualizado quando houve mudanca tecnica ou release
- [ ] rollback permanece claro depois da alteracao

---

## Checklist documental

- [ ] `CORTEX.md` reflecte fase, estado, proximos passos e riscos
- [ ] `docs/README.md` continua a apontar para as fontes certas
- [ ] roadmap e backlog continuam alinhados
- [ ] checklist mestre reflecte gates reais
- [ ] ADR index foi revisto
- [ ] classificacao documental foi revista se surgiu novo conflito
- [ ] mapa de equivalencia foi revisto se surgiu nova sobreposicao
- [ ] `MANUAL-INSTALL.md` foi actualizado se houve impacto operacional

---

## Checklist de testes e gates

- [ ] o tipo de mudanca tem teste minimo definido
- [ ] existe evidencia suficiente para marcar o bloco como concluido
- [ ] a validacao ocorreu no ambiente correcto para o risco da mudanca
- [ ] gates documentais e gates tecnicos nao foram confundidos
- [ ] nenhum “OK” foi marcado sem prova minima

---

## Checklist de rollback

- [ ] rollback e explicitamente descrito
- [ ] rollback nao depende de memoria oral
- [ ] rollback preserva a ultima versao segura conhecida
- [ ] impacto do rollback sobre docs e artefactos foi considerado
- [ ] para reorganizacao estrutural futura, rollback de links e caminhos foi previsto

---

## Checklist de release interno

Usar apenas quando o bloco envolver artefacto, publicacao ou distribuicao.

- [ ] changelog revisto
- [ ] release notes revistas
- [ ] `MANUAL-INSTALL.md` sincronizado com a versao real
- [ ] artefacto correcto identificado como `.pkg`
- [ ] `.pkg.sha256` publicado para o mesmo artefacto
- [ ] `install.sh` e `uninstall.sh` versionados publicados no release
- [ ] manifesto versionado publicado
- [ ] assinatura destacada do manifesto publicada
- [ ] public key de verificacao publicada
- [ ] disponibilidade do download verificada
- [ ] rollback de release documentado

---

## Excepcao documental

Quando a mudanca for **apenas documental**:

- [ ] nenhum ficheiro de codigo foi alterado
- [ ] nenhum ficheiro de package/build/install/runtime foi alterado
- [ ] nenhuma logica funcional foi tocada
- [ ] revisao de coerencia cruzada foi feita
- [ ] commit local foi feito
- [ ] push para o GitHub foi feito

**Nao exigido nesta situacao:** build, pacote `.pkg`, release e validacao em
appliance, salvo se o proprio pedido disser o contrario.

---

## Gate resumido por fase

| Fase | Gate minimo |
|------|-------------|
| F0 | um novo agente entende o projecto lendo poucos documentos canónicos |
| F1 | cadeia de confianca de distribuicao, blacklists e fallback deixa de depender de conhecimento implícito |
| F2 | license server opera com publicacao segura, sessao administrativa revogavel, bootstrap controlado e operacao sob controlo |
| F3 | activacao, revogacao e offline ficam previsiveis, com estados/transicoes explicitos e semantica documentada para repeticao, concorrencia e expiracao |
| F4 | package, daemon e blacklists operam com runtime mais confiavel |
| F5 | existe malha real de nao regressao por componente |
| F6 | reorganizacao fisica acontece sem perda de contexto nem links quebrados |
| F7 | release e observabilidade deixam de depender de memoria operacional |

---

## Documentacao obrigatoria por fase

| Fase | Docs minimas |
|------|--------------|
| F0 | `CORTEX`, `AGENTS`, indice docs, roadmap, backlog, checklist, classificacao, equivalencia, ADR index |
| F1 | `CORTEX`, `docs/01-architecture/f1-arquitetura-de-confianca.md`, backlog, `docs/02-roadmap/f1-plano-de-implementacao.md`, ADR-0003 a ADR-0006 |
| F2 | `CORTEX`, `docs/01-architecture/f2-arquitetura-license-server.md`, `docs/02-roadmap/f2-plano-de-implementacao.md`, backlog, docs de licencas, runbooks de publicacao/sessao/segredos/backup-restore do servidor, ADR-0007 a ADR-0010 |
| F3 | `CORTEX`, `docs/01-architecture/f3-arquitetura-licenciamento-ativacao.md`, `docs/01-architecture/f3-fingerprint-e-binding.md`, backlog, docs de licencas, matriz de testes, ADRs afectados |
| F4 | `CORTEX`, backlog, `MANUAL-INSTALL`, docs de blacklists, runbooks, changelog |
| F5 | `CORTEX`, backlog, docs de testes, checklist mestre, evidencias |
| F6 | `CORTEX`, backlog, classificacao, equivalencia, changelog estrutural |
| F7 | `CORTEX`, backlog, releases, changelog, `MANUAL-INSTALL`, checklist interno |

---

## Condicoes para pedir validacao humana

- [ ] existe duvida sobre compatibilidade com pfSense CE
- [ ] existe duvida sobre empacotamento ou builder
- [ ] a mudanca afecta seguranca de forma relevante
- [ ] a decisao e arquitecturalmente grande
- [ ] ha necessidade de fallback sem resposta fechada
- [ ] ha necessidade de mover estrutura antes da F6
