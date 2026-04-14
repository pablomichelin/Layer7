# F3.11 - Ledger de Decisoes Operacionais

## Finalidade

Este ledger e o registo cumulativo e auditavel das microdecisoes
operacionais da F3.11.

Objectivo:

- registar decisoes pequenas sem poluir `CORTEX.md`;
- manter trilha historica de aceite, rejeicao, parcialidade, blockers e
  `go/no-go`;
- impedir que microdecisoes relevantes fiquem apenas na memoria do operador;
- evitar ADR desnecessaria para detalhe operacional que nao reabre decisao de
  arquitectura.

Regras deste ledger:

1. registar apenas o que foi realmente observado ou decidido;
2. se a hora exacta nao estiver documentada no artefacto de origem, declarar
   isso explicitamente;
3. cada nova rodada operacional deve acrescentar entradas, nunca reescrever
   historico;
4. toda entrada deve apontar para evidencia e fundamento documental.

Estado formal preservado:

- `F3 continua aberta`;
- `F3.11 alinhada no license-server live`;
- `este ledger nao fecha a F3`;
- `este ledger nao abre campanha`.

---

## 1. Formato canonico das entradas

| Campo | Conteudo obrigatorio |
|-------|----------------------|
| ID | identificador sequencial `ODL-XXX` |
| Data/hora | UTC exacto ou nota explicita de hora nao registada |
| Assunto | microdecisao ou facto operacional decidido |
| Evidencia associada | comando, documento, artefacto ou output que sustenta a decisao |
| Decisao tomada | `aceito`, `rejeitado`, `parcial`, `mantido`, `NO-GO`, `GO` ou equivalente objectivo |
| Fundamento documental | documentos canónicos usados para decidir |
| Impacto na readiness | efeito binario ou operacional na readiness da F3.11 |
| Proximo passo | accao seguinte obrigatoria |

---

## 2. Entradas cumulativas

| ID | Data/hora (UTC) | Assunto | Evidencia associada | Decisao tomada | Fundamento documental | Impacto na readiness | Proximo passo |
|----|-----------------|---------|---------------------|----------------|-----------------------|----------------------|---------------|
| `ODL-001` | `2026-04-02T20:03Z` | readiness inicial da F3.11 executada contra o ambiente observado | [`f3-11-readiness-check.md`](f3-11-readiness-check.md) + respostas `200` em `api/health` + falha de `ssh` ao host observado | `NO-GO`; readiness bloqueada por falta de shell/DB, credencial admin, appliance e inventario real | [`f3-11-readiness-check.md`](f3-11-readiness-check.md), [`f3-matriz-prerequisitos-campanha.md`](f3-matriz-prerequisitos-campanha.md) | readiness mantida bloqueada; campanha nao pode abrir | saneamento minimo antes de qualquer nova tentativa |
| `ODL-002` | `2026-04-02T23:37:26Z` | saneamento minimo da readiness executado sem abrir campanha | [`f3-11-readiness-saneamento.md`](f3-11-readiness-saneamento.md) + `git ls-remote origin` + observacao do drift de CORS/same-origin | `parcial`; houve melhoria de evidencia, mas blockers mantidos e drift adicional registado | [`f3-11-readiness-saneamento.md`](f3-11-readiness-saneamento.md), [`f3-11-drift-registry.md`](f3-11-drift-registry.md) | readiness continua `NO-GO`; campanha continua `NO-GO` | transformar blockers e drifts em kit canónico de desbloqueio |
| `ODL-003` | `2026-04-02 (hora exacta nao registada no artefacto de origem)` | formalizacao do pacote de habilitacao de acessos e do drift registry da F3.11 | [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md) + [`f3-11-drift-registry.md`](f3-11-drift-registry.md) + [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md) | blockers mantidos e convertidos em pre-requisitos operacionais canonicos; campanha nao aberta | [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md), [`f3-11-drift-registry.md`](f3-11-drift-registry.md) | readiness continua `NO-GO` ate existirem acessos e evidencias reais | formalizar a trilha de solicitacao, intake, aceite e reabertura |
| `ODL-004` | `2026-04-02 (hora exacta nao registada no artefacto de origem)` | transformacao dos cinco insumos externos em processo canonico de solicitacao, aceite, intake, triagem e gate | [`f3-11-external-input-request-package.md`](f3-11-external-input-request-package.md) + [`f3-11-input-acceptance-matrix.md`](f3-11-input-acceptance-matrix.md) + [`../05-runbooks/f3-11-evidence-intake-template.md`](../05-runbooks/f3-11-evidence-intake-template.md) + [`../05-runbooks/f3-11-input-triage-runbook.md`](../05-runbooks/f3-11-input-triage-runbook.md) + [`f3-11-readiness-reopen-gate.md`](f3-11-readiness-reopen-gate.md) | nenhum insumo foi dado por entregue; gate de reabertura mantido em `NO-GO` | [`f3-11-input-acceptance-matrix.md`](f3-11-input-acceptance-matrix.md), [`f3-11-readiness-reopen-gate.md`](f3-11-readiness-reopen-gate.md) | readiness e campanha permanecem bloqueadas ate `5/5` insumos validos | criar cockpit unico de acompanhamento e execucao ponta a ponta |
| `ODL-005` | `2026-04-02 (hora exacta nao registada neste ledger)` | cockpit documental da F3.11 consolidado em registro mestre, ledger, scorecard, template de ciclo e mapa de rastreabilidade | [`f3-11-execution-master-register.md`](f3-11-execution-master-register.md) + [`f3-11-readiness-scorecard.md`](f3-11-readiness-scorecard.md) + [`../05-runbooks/f3-11-cycle-report-template.md`](../05-runbooks/f3-11-cycle-report-template.md) + [`../00-overview/f3-11-document-traceability-map.md`](../00-overview/f3-11-document-traceability-map.md) + `git status --short --branch` com `main...origin/main [ahead 21]` no inicio da rodada | processo interno pronto; nenhum insumo real validado; sem push; sem campanha; sem reabertura da readiness | [`f3-11-execution-master-register.md`](f3-11-execution-master-register.md), [`f3-11-readiness-scorecard.md`](f3-11-readiness-scorecard.md), [`f3-11-readiness-reopen-gate.md`](f3-11-readiness-reopen-gate.md) | readiness continua `NO-GO` com cockpit completo e sem ambiguidade operacional | aguardar primeiro insumo real e abrir o primeiro ciclo padronizado de intake/triagem |
| `ODL-006` | `2026-04-02 (hora exacta nao registada neste ledger)` | governanca operacional final da F3.11 consolidada com entrada unica, maquina de estados, protocolo de sincronizacao, matriz de responsabilidades e criterio de fecho de ciclo | [`../00-overview/f3-11-start-here.md`](../00-overview/f3-11-start-here.md) + [`f3-11-state-machine.md`](f3-11-state-machine.md) + [`f3-11-document-sync-protocol.md`](f3-11-document-sync-protocol.md) + [`f3-11-operational-responsibility-matrix.md`](f3-11-operational-responsibility-matrix.md) + [`../05-runbooks/f3-11-cycle-closure-criteria.md`](../05-runbooks/f3-11-cycle-closure-criteria.md) + `git status --short --branch` com `main...origin/main [ahead 22]` no inicio da rodada | sistema documental-operacional da F3.11 fechado sem mudar escopo, sem reabrir readiness, sem campanha e sem push | [`f3-11-document-sync-protocol.md`](f3-11-document-sync-protocol.md), [`f3-11-state-machine.md`](f3-11-state-machine.md), [`../00-overview/f3-11-start-here.md`](../00-overview/f3-11-start-here.md), [`../05-runbooks/f3-11-cycle-closure-criteria.md`](../05-runbooks/f3-11-cycle-closure-criteria.md) | readiness continua `NO-GO`; campanha continua `NO-GO`; estado formal mantido em `F3 aberta` e `F3.11 bloqueada` | aguardar o primeiro insumo real e operar a partir do `start-here` com sincronizacao obrigatoria de ciclo |
| `ODL-007` | `2026-04-14 (hora exacta nao registada neste ledger)` | checkpoint live reclassifica a F3.11: license-server, auth/admin, same-origin e inventario ficam alinhados para a F3; resta apenas DR-05 no appliance | [`f3-11-drift-registry.md`](f3-11-drift-registry.md) + [`f3-11-readiness-scorecard.md`](f3-11-readiness-scorecard.md) + [`f3-11-execution-master-register.md`](f3-11-execution-master-register.md) + [`../00-overview/f3-11-start-here.md`](../00-overview/f3-11-start-here.md) | `GO condicional`; gate antigo de `5/5` insumos deixa de ser o caminho corrente; `DR-05` fica como unico blocker real | [`../00-overview/f3-organizacao-local-e-fecho.md`](../00-overview/f3-organizacao-local-e-fecho.md), [`f3-11-drift-registry.md`](f3-11-drift-registry.md), [`f3-fecho-operacional-restante.md`](f3-fecho-operacional-restante.md) | readiness/campanha ficam condicionais apenas aos cenarios mutaveis do appliance; F3 continua aberta | executar `DR-05` com permissao suficiente, snapshot/rollback e evidencias por `run_id` |

---

## 3. Instrucoes de uso nas proximas rodadas

Quando houver nova evidencia real:

1. se for evidencia do `DR-05`, registar o resultado directamente neste
   ledger, no registro mestre, no scorecard e no drift registry;
2. se for um insumo externo antigo, abrir intake e executar triagem apenas
   quando ele mudar materialmente o estado corrente;
3. nao regressar ao gate de `5/5` sem drift novo objectivo.

Exemplos de assuntos validos para novas entradas:

- insumo recebido e classificado como `entregue parcial`;
- insumo rejeitado por ausencia de evidencia minima;
- inventario aceite, mas ainda insuficiente para `GO`;
- blocker mantido;
- drift novo registado;
- readiness continua `NO-GO`;
- campanha continua `NO-GO`.

---

## 4. Objectivo, impacto, risco, teste e rollback deste bloco

- **Objectivo:** criar trilha cumulativa, auditavel e reutilizavel para as
  microdecisoes operacionais da F3.11.
- **Impacto:** documental-operacional apenas; nenhum ficheiro de codigo foi
  alterado.
- **Risco:** baixo; o ledger apenas regista decisoes e nao reinterpreta
  gates.
- **Teste minimo:** coerencia cruzada com o readiness check, o saneamento, o
  drift registry e o kit canónico de insumos da F3.11.
- **Rollback:** `git revert <commit-deste-bloco>`.
