# F3.11 - Mapa de Rastreabilidade Documental

## Finalidade

Este mapa mostra como os artefactos canónicos da F3.11 se ligam entre si e
em que ordem devem ser consultados.

Objectivo:

- evitar dispersao operacional;
- indicar qual documento consultar primeiro e qual actualizar depois;
- deixar explicito onde cada accao deve ser registada;
- tornar a trilha F3.11 navegavel para qualquer operador sem depender de
  memoria tacita.

Estado formal preservado:

- `F3 continua aberta`;
- `F3.11 continua bloqueada`;
- `este mapa nao reabre readiness`;
- `este mapa nao abre campanha`.

---

## 1. Ordem recomendada de uso

1. [`../01-architecture/f3-11-readiness-scorecard.md`](../01-architecture/f3-11-readiness-scorecard.md)
   para leitura executiva imediata de `GO/NO-GO`.
2. [`../01-architecture/f3-11-execution-master-register.md`](../01-architecture/f3-11-execution-master-register.md)
   para ler o estado detalhado dos cinco insumos.
3. [`../01-architecture/f3-11-drift-registry.md`](../01-architecture/f3-11-drift-registry.md)
   para entender os drifts ainda abertos.
4. [`../01-architecture/f3-11-external-input-request-package.md`](../01-architecture/f3-11-external-input-request-package.md)
   para saber exactamente o que precisa ser pedido ao exterior.
5. [`../05-runbooks/f3-11-evidence-intake-template.md`](../05-runbooks/f3-11-evidence-intake-template.md)
   quando um insumo real chegar.
6. [`../05-runbooks/f3-11-input-triage-runbook.md`](../05-runbooks/f3-11-input-triage-runbook.md)
   para aceitar, rejeitar ou classificar como parcial.
7. [`../01-architecture/f3-11-input-acceptance-matrix.md`](../01-architecture/f3-11-input-acceptance-matrix.md)
   para marcar o status objectivo do insumo.
8. [`../01-architecture/f3-11-operational-decisions-ledger.md`](../01-architecture/f3-11-operational-decisions-ledger.md)
   para registar a microdecisao da rodada.
9. [`../05-runbooks/f3-11-cycle-report-template.md`](../05-runbooks/f3-11-cycle-report-template.md)
   para consolidar a rodada completa.
10. [`../01-architecture/f3-11-readiness-reopen-gate.md`](../01-architecture/f3-11-readiness-reopen-gate.md)
    para decidir se a readiness pode ser repetida.
11. [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md)
    apenas se o gate da readiness estiver em `GO`.

---

## 2. Mapa de relacoes

| Documento | Papel canónico | Consulta-se quando | Actualiza-se quando | Alimenta qual decisao seguinte |
|-----------|----------------|--------------------|---------------------|--------------------------------|
| [`../01-architecture/f3-11-external-input-request-package.md`](../01-architecture/f3-11-external-input-request-package.md) | define o pedido formal dos cinco insumos | antes de qualquer contacto externo | quando o pedido minimo por insumo mudar formalmente | o que deve ser recebido |
| [`../01-architecture/f3-11-input-acceptance-matrix.md`](../01-architecture/f3-11-input-acceptance-matrix.md) | define `nao entregue`, `invalido`, `parcial`, `valido` | durante a triagem | quando o criterio objectivo de aceite mudar | se o insumo liberta ou nao o subgate |
| [`../05-runbooks/f3-11-evidence-intake-template.md`](../05-runbooks/f3-11-evidence-intake-template.md) | regista a recepcao de cada insumo | no momento em que algo chega | em cada nova entrega real | qual foi exactamente a evidencia recebida |
| [`../05-runbooks/f3-11-input-triage-runbook.md`](../05-runbooks/f3-11-input-triage-runbook.md) | governa a validacao de cada entrega | depois do intake | nao se actualiza a cada rodada; usa-se | se a entrega deve ser aceite, rejeitada ou tratada como parcial |
| [`../01-architecture/f3-11-readiness-reopen-gate.md`](../01-architecture/f3-11-readiness-reopen-gate.md) | decide `GO/NO-GO` para repetir a readiness | depois de actualizar os cinco insumos | quando o gate formal mudar | se a readiness pode reabrir |
| [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md) | valida o ambiente real depois de `GO` de readiness | so depois de `5/5` insumos validos | nao se usa antes do gate | se a readiness repetida se sustenta no ambiente real |
| [`../01-architecture/f3-11-drift-registry.md`](../01-architecture/f3-11-drift-registry.md) | lista cumulativa de drifts abertos | sempre que houver duvida sobre blocker residual | quando um drift novo aparece ou muda de estado | se existe blocker estrutural independente do insumo |
| [`../01-architecture/f3-11-execution-master-register.md`](../01-architecture/f3-11-execution-master-register.md) | cockpit detalhado do estado dos cinco insumos | sempre que se quer ler o estado geral | depois de cada ciclo real | quais blockers e accoes pendentes continuam abertos |
| [`../01-architecture/f3-11-operational-decisions-ledger.md`](../01-architecture/f3-11-operational-decisions-ledger.md) | historico auditavel das microdecisoes | depois de cada triagem ou decisao operacional | em toda nova microdecisao relevante | como a rodada actual afecta readiness/campanha |
| [`../01-architecture/f3-11-readiness-scorecard.md`](../01-architecture/f3-11-readiness-scorecard.md) | pagina executiva de estado | na abertura de qualquer rodada | depois de cada mudanca material | leitura rapida de `GO/NO-GO` |
| [`../05-runbooks/f3-11-cycle-report-template.md`](../05-runbooks/f3-11-cycle-report-template.md) | registo padronizado de cada rodada | sempre que houver ciclo operacional real | em toda rodada real | fecho formal da rodada e proximo passo |

---

## 3. Onde registar cada tipo de accao

| Tipo de accao | Documento certo para consultar | Documento certo para registar |
|---------------|--------------------------------|-------------------------------|
| entender o estado geral da trilha | [`../01-architecture/f3-11-readiness-scorecard.md`](../01-architecture/f3-11-readiness-scorecard.md) e [`../01-architecture/f3-11-execution-master-register.md`](../01-architecture/f3-11-execution-master-register.md) | nenhum; leitura apenas |
| pedir formalmente um insumo externo | [`../01-architecture/f3-11-external-input-request-package.md`](../01-architecture/f3-11-external-input-request-package.md) | ciclo da rodada ou comunicacao externa controlada |
| receber um insumo | [`../05-runbooks/f3-11-evidence-intake-template.md`](../05-runbooks/f3-11-evidence-intake-template.md) | intake do insumo |
| triar um insumo recebido | [`../05-runbooks/f3-11-input-triage-runbook.md`](../05-runbooks/f3-11-input-triage-runbook.md) | intake + ledger |
| marcar aceite/rejeicao/parcial | [`../01-architecture/f3-11-input-acceptance-matrix.md`](../01-architecture/f3-11-input-acceptance-matrix.md) | intake + ledger + registro mestre |
| actualizar o estado dos cinco insumos | [`../01-architecture/f3-11-execution-master-register.md`](../01-architecture/f3-11-execution-master-register.md) | registro mestre |
| registar microdecisao operacional | [`../01-architecture/f3-11-operational-decisions-ledger.md`](../01-architecture/f3-11-operational-decisions-ledger.md) | ledger |
| consolidar a rodada inteira | [`../05-runbooks/f3-11-cycle-report-template.md`](../05-runbooks/f3-11-cycle-report-template.md) | relatorio do ciclo |
| decidir se a readiness pode reabrir | [`../01-architecture/f3-11-readiness-reopen-gate.md`](../01-architecture/f3-11-readiness-reopen-gate.md) | ledger + scorecard + ciclo |
| executar a verificacao live pos-gate | [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md) | artefactos da rodada real |
| registar drift novo | [`../01-architecture/f3-11-drift-registry.md`](../01-architecture/f3-11-drift-registry.md) | drift registry + ledger |

---

## 4. Fluxo minimo ponta a ponta

1. ler scorecard;
2. ler registro mestre;
3. se faltar pedir algo, usar o pacote de solicitacao externa;
4. quando chegar algo, abrir intake;
5. triar;
6. actualizar matriz, ledger e registro mestre;
7. fechar o ciclo da rodada;
8. actualizar scorecard;
9. verificar gate;
10. so depois usar o checklist live.

Qualquer atalho que salte intake, triagem, ledger ou scorecard reintroduz
ambiguidade e nao deve ser tratado como progresso valido da F3.11.

---

## 5. Objectivo, impacto, risco, teste e rollback deste bloco

- **Objectivo:** tornar explicita a navegacao entre todos os artefactos da
  F3.11 e o ponto correcto de registo de cada accao.
- **Impacto:** documental-operacional apenas; nenhum ficheiro de codigo foi
  alterado.
- **Risco:** baixo; o mapa apenas organiza o fluxo canónico ja aprovado.
- **Teste minimo:** coerencia cruzada com os artefactos F3.11 ja existentes e
  com o cockpit novo desta rodada.
- **Rollback:** `git revert <commit-deste-bloco>`.
