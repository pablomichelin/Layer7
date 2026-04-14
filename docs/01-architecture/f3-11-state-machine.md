# F3.11 - Maquina de Estados Operacional

## Finalidade

Este documento define a maquina de estados canonica da trilha F3.11.

Objectivo:

- eliminar ambiguidade sobre quando a trilha esta apenas bloqueada,
  parcialmente abastecida, pronta para nova readiness ou elegivel para
  campanha;
- impedir saltos informais do tipo "ja pode" ou "quase pode";
- ligar cada estado a evidencias minimas, documentos obrigatorios e
  transicoes permitidas;
- manter explicito que esta rodada continua sem codigo, sem push, sem
  campanha e sem reabertura da readiness.

Estado formal preservado:

- `F3 continua aberta`;
- `F3.11 alinhada no license-server live`;
- `readiness = GO condicional`;
- `campanha = GO condicional`;
- `DR-05 continua bloqueante para o fecho da F3`.

Nota de actualizacao em `2026-04-14`:

- o modelo original de cinco insumos permanece abaixo como historico de
  governanca e compatibilidade documental;
- o gate corrente deixou de ser `5/5 insumos entregue valido`;
- o estado corrente e `SM-10 - ALINHADA_COM_DR05_PENDENTE`, descrito na
  secao 4.

Leitura complementar obrigatoria:

- [`f3-11-document-sync-protocol.md`](f3-11-document-sync-protocol.md)
- [`f3-11-execution-master-register.md`](f3-11-execution-master-register.md)
- [`f3-11-operational-decisions-ledger.md`](f3-11-operational-decisions-ledger.md)
- [`f3-11-readiness-scorecard.md`](f3-11-readiness-scorecard.md)
- [`f3-11-readiness-reopen-gate.md`](f3-11-readiness-reopen-gate.md)
- [`../00-overview/f3-11-start-here.md`](../00-overview/f3-11-start-here.md)
- [`../05-runbooks/f3-11-cycle-closure-criteria.md`](../05-runbooks/f3-11-cycle-closure-criteria.md)

---

## 1. Regras invariaveis da maquina

1. Depois do checkpoint de `2026-04-14`, a F3.11 nao deve regressar ao gate
   de cinco insumos sem evidencia nova que invalide o live/admin/inventario
   ja saneados.
2. Nenhum estado autoriza fecho da F3 por inferencia; a fase so fecha depois
   de `DR-05` ter evidencia real suficiente e relatorio final binario.
3. Nenhum estado pode ser alterado apenas por conversa, memoria oral ou
   expectativa de entrega. E preciso evidenciar o evento.
4. Intake, triagem, ledger, registro mestre, scorecard e gate devem estar
   sincronizados na ordem canonica definida no protocolo documental.
5. `start-here` e ponto de entrada, mas nao e o documento de estado corrente.
   O estado corrente vive no registro mestre e no scorecard.
6. Se um drift novo ou um blocker novo surgir, a maquina pode regressar a
   estado bloqueado sem reabrir decisao de escopo.

---

## 2. Estados canonicos

Nota: os estados `SM-01` a `SM-09` documentam o modelo operacional anterior
da F3.11. Para a rodada actual, usar a leitura da secao 4.

### `SM-01 - BLOQUEADA_SEM_INSUMOS`

| Campo | Definicao canonica |
|-------|--------------------|
| Definicao objectiva | nenhum dos cinco insumos tem intake aberto e nenhum esta `entregue valido` |
| Condicao de entrada | inicio formal da trilha F3.11 ou fecho de ciclo sem qualquer entrega utilizavel |
| Condicao de saida | chegada de entrega real suficiente para abrir intake, ou consolidacao de ausencia persistente em ciclo formal |
| Evidencia minima exigida | scorecard e registro mestre com `0/5` validos, `0/5` parciais e blockers integrais mantidos |
| Documentos que devem estar actualizados | `f3-11-readiness-scorecard.md`, `f3-11-execution-master-register.md`, `f3-11-operational-decisions-ledger.md` |
| Transicoes permitidas | `SM-02`, `SM-03` |
| Transicoes proibidas | `SM-05`, `SM-06`, `SM-08` |
| Impacto sobre F3.11 | trilha formalmente bloqueada, sem progresso operacional material |
| Impacto sobre campanha | `NO-GO` automatico |

### `SM-02 - BLOQUEADA_COM_INSUMOS_PARCIAIS`

| Campo | Definicao canonica |
|-------|--------------------|
| Definicao objectiva | existe pelo menos uma entrega recebida, mas ainda sem triagem completa ou sem minimo canónico satisfeito |
| Condicao de entrada | abertura de intake com evidencia real insuficiente para fechar subgate |
| Condicao de saida | triagem formal da entrega, rejeicao formal, complemento que abra nova triagem ou promocao para aceite parcial |
| Evidencia minima exigida | intake aberto com classificacao provisoria e referencia cruzada no ciclo |
| Documentos que devem estar actualizados | intake derivado do template, ciclo operacional em curso, ledger com registo da recepcao |
| Transicoes permitidas | `SM-03`, `SM-04`, `SM-01` |
| Transicoes proibidas | `SM-05`, `SM-06`, `SM-08` |
| Impacto sobre F3.11 | existe movimento documental, mas a trilha continua bloqueada |
| Impacto sobre campanha | `NO-GO` automatico |

### `SM-03 - BLOQUEADA_COM_INSUMOS_EM_TRIAGEM`

| Campo | Definicao canonica |
|-------|--------------------|
| Definicao objectiva | pelo menos um intake esta em validacao activa pelo runbook de triagem |
| Condicao de entrada | inicio formal da triagem de um ou mais insumos recebidos |
| Condicao de saida | classificacao final do insumo em `entregue invalido`, `entregue parcial` ou `entregue valido` |
| Evidencia minima exigida | intake preenchido, verificacoes executadas, classificacao objectiva preparada para ledger |
| Documentos que devem estar actualizados | intake, `f3-11-input-acceptance-matrix.md` como criterio, ledger com microdecisao em preparacao, ciclo operacional aberto |
| Transicoes permitidas | `SM-01`, `SM-04`, `SM-05` |
| Transicoes proibidas | `SM-06`, `SM-08` antes de scorecard e gate |
| Impacto sobre F3.11 | a trilha continua bloqueada ate a triagem ser consolidada e sincronizada |
| Impacto sobre campanha | `NO-GO` automatico |

### `SM-04 - BLOQUEADA_COM_ACEITE_PARCIAL`

| Campo | Definicao canonica |
|-------|--------------------|
| Definicao objectiva | um ou mais insumos ja estao `entregue valido`, mas o conjunto ainda nao chegou a `5/5` validos |
| Condicao de entrada | fecho de triagem com aceite valido de subgate isolado, mantendo outros blockers abertos |
| Condicao de saida | novos aceites que completem `5/5`, regressao por expiracao/perda de insumo, ou rejeicao posterior de evidencia insuficiente |
| Evidencia minima exigida | ledger actualizado, registro mestre com contagem real de validos/parciais/ausentes e scorecard sincronizado |
| Documentos que devem estar actualizados | ledger, registro mestre, scorecard, ciclo operacional fechado ou em fecho |
| Transicoes permitidas | `SM-02`, `SM-03`, `SM-05`, `SM-01` |
| Transicoes proibidas | `SM-06` sem `5/5` validos e sem gate revisto |
| Impacto sobre F3.11 | progresso real existe, mas a trilha continua bloqueada |
| Impacto sobre campanha | `NO-GO` automatico |

### `SM-05 - PRONTA_PARA_REPETIR_READINESS`

| Campo | Definicao canonica |
|-------|--------------------|
| Definicao objectiva | os cinco insumos estao `entregue valido`, as evidencias minimas existem e o scorecard ja reflecte `5/5` validos |
| Condicao de entrada | ultimo subgate fechado, scorecard e registro mestre sincronizados e ciclo encerrado com precondicoes completas |
| Condicao de saida | reabertura formal da readiness ou regressao por expirar/invalidar qualquer insumo |
| Evidencia minima exigida | cinco intakes aceites, ledger actualizado, registro mestre actualizado, scorecard em `readiness = GO para repetir`, nota de publicacao sem push e gate pronto para ser verificado |
| Documentos que devem estar actualizados | intakes, ledger, registro mestre, scorecard, ciclo, nota operacional de publicacao |
| Transicoes permitidas | `SM-06`, `SM-04`, `SM-01` |
| Transicoes proibidas | `SM-08`, `SM-09` sem passar pela readiness |
| Impacto sobre F3.11 | readiness torna-se elegivel para repeticao; a trilha deixa de estar bloqueada por insumos |
| Impacto sobre campanha | continua `NO-GO` ate a readiness terminar |

### `SM-06 - READINESS_REABERTA`

| Campo | Definicao canonica |
|-------|--------------------|
| Definicao objectiva | o gate formal autorizou repetir a readiness e o checklist live ja foi iniciado |
| Condicao de entrada | `SM-05` validado e inicio efectivo do `f3-11-live-access-checklist.md` |
| Condicao de saida | conclusao da readiness com `GO` ou `NO-GO`, ou abortar por perda de evidencia durante a execucao |
| Evidencia minima exigida | gate revisto, ciclo operacional registado, checklist live em execucao com output real do ambiente observado |
| Documentos que devem estar actualizados | gate, ledger, ciclo operacional, artefactos da readiness repetida |
| Transicoes permitidas | `SM-07`, `SM-08`, `SM-09`, `SM-04` |
| Transicoes proibidas | `SM-01` por simples inercia sem registo de abortar/regredir |
| Impacto sobre F3.11 | a trilha sai do bloqueio por insumos e entra em verificacao live controlada |
| Impacto sobre campanha | continua `NO-GO` ate a readiness concluir |

### `SM-07 - READINESS_CONCLUIDA_SEM_GO`

| Campo | Definicao canonica |
|-------|--------------------|
| Definicao objectiva | a readiness repetida terminou, mas concluiu `NO-GO` para campanha ou para continuidade imediata |
| Condicao de entrada | checklist live ou gate final detectou drift, blocker estrutural ou contrato incompativel |
| Condicao de saida | reclassificacao dos blockers, novo ciclo com saneamento e retorno a estado bloqueado apropriado |
| Evidencia minima exigida | artefactos reais da readiness repetida, scorecard actualizado, ledger com decisao `NO-GO`, ciclo fechado |
| Documentos que devem estar actualizados | ledger, registro mestre, scorecard, gate, drift registry se necessario, ciclo operacional |
| Transicoes permitidas | `SM-01`, `SM-02`, `SM-04`, `SM-09` |
| Transicoes proibidas | `SM-08` sem nova readiness ou sem nova evidencia |
| Impacto sobre F3.11 | a trilha permanece aberta e regressa a bloqueio governado |
| Impacto sobre campanha | `NO-GO` formal |

### `SM-08 - READINESS_CONCLUIDA_COM_GO_PARA_CAMPANHA`

| Campo | Definicao canonica |
|-------|--------------------|
| Definicao objectiva | a readiness repetida terminou com evidencia suficiente e sem blocker nao negociavel para abrir campanha |
| Condicao de entrada | conclusao do checklist live e do gate final com `GO` formal |
| Condicao de saida | abertura controlada da campanha em bloco proprio ou retorno a `SM-09` se surgir impedimento operacional antes da abertura |
| Evidencia minima exigida | scorecard actualizado com `GO`, ledger com decisao `GO`, ciclo fechado com avancos reais e gate positivo |
| Documentos que devem estar actualizados | scorecard, gate, ledger, registro mestre, ciclo operacional, artefactos da readiness |
| Transicoes permitidas | `SM-09` |
| Transicoes proibidas | `SM-01`, `SM-02`, `SM-04` sem registo de regressao objectiva |
| Impacto sobre F3.11 | readiness deixa de ser blocker; a subtrilha fica elegivel para transicao operacional seguinte |
| Impacto sobre campanha | campanha fica tecnicamente autorizada, mas ainda nao aberta por este documento |

### `SM-09 - CAMPANHA_AINDA_BLOQUEADA`

| Campo | Definicao canonica |
|-------|--------------------|
| Definicao objectiva | a readiness ja foi tratada, mas a campanha nao pode abrir por impedimento remanescente, hold operacional ou regressao detectada antes do arranque |
| Condicao de entrada | `SM-06`, `SM-07` ou `SM-08` detectam que a campanha continua sem autorizacao executavel |
| Condicao de saida | bloco proprio de campanha, novo saneamento que volte a readiness ou encerramento formal da subtrilha em estado bloqueado |
| Evidencia minima exigida | decisao registada no ledger, scorecard actualizado, gate/ciclo a explicar porque a campanha continua fechada |
| Documentos que devem estar actualizados | ledger, scorecard, registro mestre, ciclo operacional, gate e drift registry se aplicavel |
| Transicoes permitidas | `SM-02`, `SM-04`, `SM-06` |
| Transicoes proibidas | abertura informal de campanha sem bloco proprio e sem decisao formal |
| Impacto sobre F3.11 | a trilha continua sob governanca activa ate remover o impedimento final |
| Impacto sobre campanha | `NO-GO` mantido, mesmo com readiness ja tratada |

---

## 3. Transicoes permitidas e proibidas em bloco

### Transicoes permitidas

- `SM-01 -> SM-02`
- `SM-01 -> SM-03`
- `SM-02 -> SM-03`
- `SM-02 -> SM-04`
- `SM-03 -> SM-04`
- `SM-03 -> SM-05`
- `SM-04 -> SM-05`
- `SM-05 -> SM-06`
- `SM-06 -> SM-07`
- `SM-06 -> SM-08`
- `SM-06 -> SM-09`
- `SM-07 -> SM-01`
- `SM-07 -> SM-02`
- `SM-07 -> SM-04`
- `SM-08 -> SM-09`
- `SM-09 -> SM-02`
- `SM-09 -> SM-04`
- `SM-09 -> SM-06`

### Transicoes proibidas

- qualquer salto directo de `SM-01`, `SM-02`, `SM-03` ou `SM-04` para
  `SM-06`
- qualquer salto para `SM-08` sem readiness reaberta e concluida
- qualquer transicao para campanha aberta por fora do gate e do ciclo
- qualquer regressao de estado sem ledger, scorecard e ciclo a explicar a
  mudanca
- qualquer classificacao que trate `entregue parcial` como equivalente a
  `entregue valido`

---

## 4. Leitura do estado actual

No estado comprovado da rodada actual, a F3.11 permanece em:

- `SM-10 - ALINHADA_COM_DR05_PENDENTE` como leitura formal corrente do
  cockpit;
- license-server live, PostgreSQL, auth/admin e inventario real ja estao
  suficientes para a F3;
- a unica transicao operacional valida e executar ou complementar `DR-05`
  no appliance com snapshot/rollback, evidencias por `run_id` e control
  plane legitimo observado quando o cenario for mutavel;
- qualquer regresso ao modelo `5/5` exige drift novo objectivo, nao apenas
  leitura de documentos antigos.

Leitura binaria:

- `F3 continua aberta`;
- `F3.11 alinhada no license-server live`;
- `readiness = GO condicional`;
- `campanha = GO condicional`;
- `DR-05 continua bloqueante para o fecho da F3`.

---

## 5. Regra de prevalencia dentro da maquina

Se outro artefacto da F3.11 apresentar fluxo mais curto, esta maquina nao e
substituida automaticamente. Em caso de duvida:

1. prevalece o `CORTEX.md` para o estado macro;
2. prevalece este documento para a leitura formal de estados e transicoes da
   F3.11;
3. prevalece o protocolo de sincronizacao para a ordem documental;
4. prevalece o gate formal para decidir `GO/NO-GO` de readiness.
