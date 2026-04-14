# F3.11 - Protocolo Canonico de Sincronizacao Documental

## Finalidade

Este documento define a ordem obrigatoria de actualizacao dos artefactos da
F3.11 quando surgir um novo insumo, uma nova rodada interna ou uma readiness
repetida.

Objectivo:

- impedir divergencia entre intake, triagem, ledger, registro mestre,
  scorecard e gate;
- deixar claro qual documento recebe o primeiro registo e qual fica com o
  estado corrente;
- impedir que scorecard ou gate sejam actualizados antes da evidencia e da
  decisao estarem consolidadas;
- transformar o cockpit da F3.11 num sistema unico e reproduzivel.

Estado formal preservado:

- `F3 continua aberta`;
- `F3.11 alinhada no license-server live`;
- `sem codigo`;
- `sem push`;
- `sem campanha`;
- `DR-05 continua como unico blocker real para fechar a F3`.

Leitura complementar obrigatoria:

- [`../00-overview/f3-11-start-here.md`](../00-overview/f3-11-start-here.md)
- [`f3-11-state-machine.md`](f3-11-state-machine.md)
- [`f3-11-execution-master-register.md`](f3-11-execution-master-register.md)
- [`f3-11-operational-decisions-ledger.md`](f3-11-operational-decisions-ledger.md)
- [`f3-11-readiness-scorecard.md`](f3-11-readiness-scorecard.md)
- [`f3-11-readiness-reopen-gate.md`](f3-11-readiness-reopen-gate.md)
- [`../05-runbooks/f3-11-cycle-closure-criteria.md`](../05-runbooks/f3-11-cycle-closure-criteria.md)

---

## 1. Papel canónico de cada artefacto

| Artefacto | Papel canónico | Usa-se para | Nao usar para |
|-----------|----------------|-------------|---------------|
| `f3-11-external-input-request-package.md` | especificar o pedido minimo historico dos cinco insumos | pedir e conferir o que deve chegar se drift novo reabrir insumo externo | registar estado corrente |
| `f3-11-input-acceptance-matrix.md` | definir estados de aceite objectivos | decidir `nao entregue`, `entregue invalido`, `entregue parcial`, `entregue valido` | registar historia da rodada |
| `f3-11-readiness-reopen-gate.md` | definir a regra formal de `GO/NO-GO` para repetir a readiness | reavaliar readiness quando o estado corrente ou o `DR-05` mudar | receber primeira evidencia |
| `f3-11-input-triage-runbook.md` | governar a triagem | validar ou rejeitar o que chegou | consolidar estado executivo |
| `f3-11-evidence-intake-template.md` | primeira aterragem da evidencia | registar o que chegou e o que foi observado | decidir estado macro da trilha sozinho |
| `f3-11-execution-master-register.md` | SSOT corrente do estado operacional da F3.11 | ler e manter estado actual, incluindo `DR-05` | substituir a trilha historica |
| `f3-11-operational-decisions-ledger.md` | consolidar microdecisoes e historico cumulativo | registar aceite, rejeicao, parcial, blockers e `GO/NO-GO` | funcionar como painel de estado corrente isolado |
| `f3-11-readiness-scorecard.md` | espelho executivo do estado corrente | mostrar `GO/NO-GO` e contagem de insumos | substituir o gate formal |
| `f3-11-cycle-report-template.md` | registar e fechar cada ciclo | consolidar a rodada completa | substituir intake ou ledger |
| `f3-11-document-traceability-map.md` | mapa de navegacao e rastreabilidade | orientar consulta e local de registo | decidir estado |
| `f3-11-live-access-checklist.md` | executar a readiness repetida com ambiente real | validar evidencia nova sem reabrir blockers ja saneados | substituir evidencia do appliance |
| `f3-11-drift-registry.md` | registo cumulativo de drifts | guardar divergencias e blockers estruturais | resumir o estado corrente sozinho |

---

## 2. Ordem obrigatoria de actualizacao por ciclo

### 2.1 Quando nao chegou nada novo

1. abrir [`../00-overview/f3-11-start-here.md`](../00-overview/f3-11-start-here.md);
2. confirmar o estado corrente no registro mestre e no scorecard;
3. se a rodada for apenas de manutencao documental, abrir um ciclo curto;
4. registar no ledger apenas se houver microdecisao real;
5. fechar o ciclo com blockers mantidos e nota de publicacao actualizada.

### 2.2 Quando chegou um novo insumo

1. abrir o ciclo operacional da rodada;
2. registar **primeiro** a entrega num intake derivado de
   [`../05-runbooks/f3-11-evidence-intake-template.md`](../05-runbooks/f3-11-evidence-intake-template.md);
3. executar a triagem com
   [`../05-runbooks/f3-11-input-triage-runbook.md`](../05-runbooks/f3-11-input-triage-runbook.md);
4. classificar objectivamente o resultado com base na
   [`f3-11-input-acceptance-matrix.md`](f3-11-input-acceptance-matrix.md);
5. consolidar a decisao no
   [`f3-11-operational-decisions-ledger.md`](f3-11-operational-decisions-ledger.md);
6. actualizar o estado corrente no
   [`f3-11-execution-master-register.md`](f3-11-execution-master-register.md);
7. actualizar o [`f3-11-drift-registry.md`](f3-11-drift-registry.md) se a
   entrega revelar drift novo ou mudar drift antigo;
8. actualizar o [`f3-11-readiness-scorecard.md`](f3-11-readiness-scorecard.md);
9. reavaliar o [`f3-11-readiness-reopen-gate.md`](f3-11-readiness-reopen-gate.md)
   **apenas** se o scorecard e o registro mestre estiverem sincronizados;
10. fechar o ciclo com
    [`../05-runbooks/f3-11-cycle-report-template.md`](../05-runbooks/f3-11-cycle-report-template.md).

### 2.3 Quando a readiness ou o DR-05 puderem avancar

1. confirmar no scorecard e no registro mestre que o unico blocker corrente
   continua a ser `DR-05`;
2. confirmar que ha permissao suficiente no appliance, snapshot/rollback e
   `run_id` de evidencias;
3. executar apenas os cenarios locais do appliance definidos na F3.6/F3.7;
4. registar resultados no ciclo, no ledger, no registro mestre, no scorecard
   e no drift registry;
5. so depois classificar se a F3 pode ou nao seguir para relatorio final.

---

## 3. Respostas canonicas as dez perguntas operacionais

| Pergunta | Resposta canonica |
|----------|-------------------|
| 1. Qual documento recebe o primeiro registo? | o intake derivado de `f3-11-evidence-intake-template.md` |
| 2. Qual documento consolida a decisao? | `f3-11-operational-decisions-ledger.md` |
| 3. Qual documento reflecte o estado corrente? | `f3-11-execution-master-register.md`; o scorecard apenas espelha em leitura executiva |
| 4. Qual documento define `GO/NO-GO`? | `f3-11-readiness-reopen-gate.md` define a regra formal; `f3-11-readiness-scorecard.md` mostra o valor corrente dessa regra |
| 5. Qual documento guarda a trilha historica? | o ledger guarda a trilha micro; o ciclo guarda a trilha macro da rodada |
| 6. Quando o ciclo pode ser considerado fechado? | so depois de intake, triagem, ledger, registro mestre, scorecard, gate e nota operacional de publicacao estarem sincronizados |
| 7. Quando a scorecard deve ser actualizada? | depois do registro mestre e antes da reavaliacao do gate |
| 8. Quando o gate deve ser reavaliado? | so depois de scorecard e registro mestre estarem consistentes; nunca antes |
| 9. Como evitar divergencia entre documentos? | nao saltar a ordem canónica, usar o mesmo `cycle_id`, referenciar os mesmos intake/evidencias e rever a sincronizacao no fecho do ciclo |
| 10. Qual documento prevalece em caso de inconsistancia? | para estado macro prevalece `CORTEX.md`; dentro da F3.11 prevalecem evidencia bruta do intake para facto observado, ledger para decisao tomada, registro mestre para estado corrente e gate para `GO/NO-GO` formal |

---

## 4. Regra de prevalencia e resolucao de inconsistancias

### 4.1 Hierarquia dentro da F3.11

1. evidencia bruta e intake
2. triagem aplicada com a matriz de aceite
3. ledger de decisoes
4. registro mestre de execucao
5. scorecard executivo
6. gate formal
7. traceability map e start-here como navegacao

### 4.2 Como resolver divergencia

1. parar a actualizacao seguinte;
2. voltar ao intake e ao output bruto;
3. corrigir primeiro o ledger se a decisao estiver errada;
4. corrigir depois o registro mestre;
5. corrigir depois o scorecard;
6. reavaliar o gate apenas no fim;
7. registar no ciclo que houve saneamento de sincronizacao.

### 4.3 Regra anti-ambiguidade

Se qualquer artefacto da F3.11 mostrar um atalho diferente desta ordem, este
protocolo prevalece. Sequencias mais curtas existentes noutros documentos
devem ser lidas como resumo local, nao como exceccao.

---

## 5. Eventos que obrigam actualizacao imediata

Actualizacao obrigatoria do protocolo de sincronizacao real de uma rodada
ocorre quando houver:

- recepcao de insumo novo;
- complemento de insumo ja recebido;
- rejeicao formal de entrega;
- aceite parcial ou aceite valido;
- drift novo;
- mudanca do numero de insumos validos;
- fecho de ciclo;
- reavaliacao do gate;
- repeticao da readiness.

Sem um destes eventos, nao se deve actualizar scorecard ou gate por rotina.

---

## 6. Criterio exacto de fecho de ciclo

Um ciclo so fecha validamente quando:

1. o `cycle_id` foi aberto;
2. todos os insumos tocados na rodada tem intake ou registo explicito de "nao
   recebido";
3. a triagem foi concluida para cada entrega tratada;
4. o ledger recebeu a microdecisao da rodada;
5. o registro mestre reflecte o novo estado corrente;
6. o scorecard reflecte a nova contagem e o novo `GO/NO-GO`;
7. o gate foi revisto ou foi registado que nao havia condicao para o rever;
8. o ciclo documenta se houve ou nao push;
9. o ciclo documenta se a readiness e a campanha continuam proibidas ou nao.

Se qualquer um destes nove pontos faltar, o ciclo e considerado aberto,
incompleto ou invalido conforme o criterio especifico do documento de fecho.

---

## 7. Regra de sincronizacao com publicacao

Para esta trilha:

- o estado do branch local deve ser anotado em cada ciclo;
- o facto de `main` continuar `ahead` do remoto deve ser mantido visivel;
- esta rodada e as proximas rodadas documentais da F3.11 nao implicam push;
- ausencia de push nao invalida o ciclo, mas omitir essa nota invalida a
  rastreabilidade operacional.
