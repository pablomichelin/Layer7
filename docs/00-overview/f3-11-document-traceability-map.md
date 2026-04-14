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
- `F3.11 alinhada no license-server live`;
- `campanha/readiness permanecem condicionais ao DR-05`;
- `este mapa nao fecha a F3`;
- `este mapa nao executa campanha`.

---

## 1. Ordem recomendada de uso

1. [`f3-11-start-here.md`](f3-11-start-here.md)
   como porta de entrada unica da trilha.
2. [`f3-organizacao-local-e-fecho.md`](f3-organizacao-local-e-fecho.md)
   para separar pasta local, contrato do repo e alvos reais antes de agir.
3. [`../01-architecture/f3-fecho-operacional-restante.md`](../01-architecture/f3-fecho-operacional-restante.md)
   para ler, por alvo, o que ainda falta para o fecho real da F3.
4. [`../01-architecture/f3-11-execution-master-register.md`](../01-architecture/f3-11-execution-master-register.md)
   para ler o estado detalhado da F3.11 e confirmar se resta apenas `DR-05`.
5. [`../01-architecture/f3-11-readiness-scorecard.md`](../01-architecture/f3-11-readiness-scorecard.md)
   para leitura executiva imediata de `GO/NO-GO`.
6. [`../01-architecture/f3-11-document-sync-protocol.md`](../01-architecture/f3-11-document-sync-protocol.md)
   para seguir a ordem obrigatoria de actualizacao.
7. [`../01-architecture/f3-11-state-machine.md`](../01-architecture/f3-11-state-machine.md)
   para entender os estados e as transicoes permitidas.
8. [`../01-architecture/f3-11-drift-registry.md`](../01-architecture/f3-11-drift-registry.md)
   para entender os drifts ainda abertos.
9. [`../01-architecture/f3-11-external-input-request-package.md`](../01-architecture/f3-11-external-input-request-package.md)
   para saber exactamente o que precisa ser pedido ao exterior.
10. [`../05-runbooks/f3-11-evidence-intake-template.md`](../05-runbooks/f3-11-evidence-intake-template.md)
   quando um insumo real chegar.
11. [`../05-runbooks/f3-11-input-triage-runbook.md`](../05-runbooks/f3-11-input-triage-runbook.md)
   para aceitar, rejeitar ou classificar como parcial.
12. [`../01-architecture/f3-11-input-acceptance-matrix.md`](../01-architecture/f3-11-input-acceptance-matrix.md)
    para marcar o status objectivo do insumo.
13. [`../01-architecture/f3-11-operational-decisions-ledger.md`](../01-architecture/f3-11-operational-decisions-ledger.md)
    para registar a microdecisao da rodada.
14. [`../05-runbooks/f3-11-cycle-report-template.md`](../05-runbooks/f3-11-cycle-report-template.md)
    e [`../05-runbooks/f3-11-cycle-closure-criteria.md`](../05-runbooks/f3-11-cycle-closure-criteria.md)
    para consolidar e fechar a rodada.
15. [`../01-architecture/f3-11-readiness-reopen-gate.md`](../01-architecture/f3-11-readiness-reopen-gate.md)
    para decidir se a readiness pode ser repetida.
16. [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md)
    apenas se o gate da readiness estiver em `GO`.

---

## 2. Mapa de relacoes

| Documento | Papel canónico | Consulta-se quando | Actualiza-se quando | Alimenta qual decisao seguinte |
|-----------|----------------|--------------------|---------------------|--------------------------------|
| [`f3-11-start-here.md`](f3-11-start-here.md) | ponto unico de entrada operacional | no arranque de qualquer rodada | quando o estado formal ou a ordem de arranque mudar | por onde comecar sem ambiguidade |
| [`f3-organizacao-local-e-fecho.md`](f3-organizacao-local-e-fecho.md) | mapa unico da pasta local e da separacao dos alvos reais da F3 | no arranque de qualquer rodada com risco de dispersao entre repo, live e appliance | enquanto a F3 estiver aberta e a ajuda extra ainda for util | qual artefacto local consultar e o que ainda falta para fechar a F3 |
| [`../01-architecture/f3-fecho-operacional-restante.md`](../01-architecture/f3-fecho-operacional-restante.md) | mapa curto do que falta para o fecho real da F3 | quando for preciso distinguir o que ja esta provado do que ainda falta por alvo | enquanto a F3 estiver aberta e existirem blockers reais de fecho | qual a sequencia minima restante para concluir a F3 |
| [`../01-architecture/f3-11-external-input-request-package.md`](../01-architecture/f3-11-external-input-request-package.md) | preserva o pedido formal historico dos cinco insumos | apenas se drift novo reabrir insumo externo | quando o pedido minimo por insumo mudar formalmente | o que deve ser recebido em caso de regressao |
| [`../01-architecture/f3-11-input-acceptance-matrix.md`](../01-architecture/f3-11-input-acceptance-matrix.md) | define `nao entregue`, `invalido`, `parcial`, `valido` | durante a triagem | quando o criterio objectivo de aceite mudar | se o insumo liberta ou nao o subgate |
| [`../01-architecture/f3-11-state-machine.md`](../01-architecture/f3-11-state-machine.md) | define estados e transicoes da trilha | quando houver duvida sobre "ja pode" ou "ainda nao pode" | quando a semantica operacional dos estados mudar formalmente | em que estado a F3.11 fica depois da rodada |
| [`../01-architecture/f3-11-document-sync-protocol.md`](../01-architecture/f3-11-document-sync-protocol.md) | define a ordem obrigatoria de actualizacao | sempre que houver rodada nova ou evidencia nova | quando a ordem canónica entre artefactos mudar | qual documento actualizar a seguir |
| [`../05-runbooks/f3-11-evidence-intake-template.md`](../05-runbooks/f3-11-evidence-intake-template.md) | regista a recepcao de cada insumo | no momento em que algo chega | em cada nova entrega real | qual foi exactamente a evidencia recebida |
| [`../05-runbooks/f3-11-input-triage-runbook.md`](../05-runbooks/f3-11-input-triage-runbook.md) | governa a validacao de cada entrega | depois do intake | nao se actualiza a cada rodada; usa-se | se a entrega deve ser aceite, rejeitada ou tratada como parcial |
| [`../01-architecture/f3-11-readiness-reopen-gate.md`](../01-architecture/f3-11-readiness-reopen-gate.md) | decide `GO/NO-GO` para readiness/campanha | quando o estado corrente ou o `DR-05` mudar | quando o gate formal mudar | se a readiness/campanha continuam condicionais |
| [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md) | valida o ambiente real depois de `GO` de readiness | quando houver nova evidencia real a conferir, sem reabrir blockers ja saneados | nao se usa para substituir evidencia do appliance | se a readiness repetida se sustenta no ambiente real |
| [`../01-architecture/f3-11-drift-registry.md`](../01-architecture/f3-11-drift-registry.md) | lista cumulativa de drifts abertos | sempre que houver duvida sobre blocker residual | quando um drift novo aparece ou muda de estado | se existe blocker estrutural independente do insumo |
| [`../01-architecture/f3-11-execution-master-register.md`](../01-architecture/f3-11-execution-master-register.md) | cockpit detalhado do estado operacional corrente | sempre que se quer ler o estado geral | depois de cada ciclo real | quais blockers e accoes pendentes continuam abertos |
| [`../01-architecture/f3-11-operational-decisions-ledger.md`](../01-architecture/f3-11-operational-decisions-ledger.md) | historico auditavel das microdecisoes | depois de cada triagem ou decisao operacional | em toda nova microdecisao relevante | como a rodada actual afecta readiness/campanha |
| [`../01-architecture/f3-11-readiness-scorecard.md`](../01-architecture/f3-11-readiness-scorecard.md) | pagina executiva de estado | na abertura de qualquer rodada | depois de cada mudanca material | leitura rapida de `GO/NO-GO` |
| [`../05-runbooks/f3-11-cycle-report-template.md`](../05-runbooks/f3-11-cycle-report-template.md) | registo padronizado de cada rodada | sempre que houver ciclo operacional real | em toda rodada real | fecho formal da rodada e proximo passo |
| [`../05-runbooks/f3-11-cycle-closure-criteria.md`](../05-runbooks/f3-11-cycle-closure-criteria.md) | define quando um ciclo esta fechado, incompleto ou invalido | no fecho de cada rodada | quando a semantica de fecho mudar formalmente | se o ciclo pode ser tratado como valido |
| [`../01-architecture/f3-11-operational-responsibility-matrix.md`](../01-architecture/f3-11-operational-responsibility-matrix.md) | define papeis, limites e handoffs | quando houver duvida sobre quem actualiza ou decide | quando a distribuicao de responsabilidades mudar formalmente | quem actua no passo seguinte |

---

## 3. Onde registar cada tipo de accao

| Tipo de accao | Documento certo para consultar | Documento certo para registar |
|---------------|--------------------------------|-------------------------------|
| entender o estado geral da trilha | [`../01-architecture/f3-11-readiness-scorecard.md`](../01-architecture/f3-11-readiness-scorecard.md) e [`../01-architecture/f3-11-execution-master-register.md`](../01-architecture/f3-11-execution-master-register.md) | nenhum; leitura apenas |
| pedir formalmente um insumo externo | [`../01-architecture/f3-11-external-input-request-package.md`](../01-architecture/f3-11-external-input-request-package.md), apenas se drift novo reabrir essa necessidade | ciclo da rodada ou comunicacao externa controlada |
| receber um insumo | [`../05-runbooks/f3-11-evidence-intake-template.md`](../05-runbooks/f3-11-evidence-intake-template.md) | intake do insumo |
| triar um insumo recebido | [`../05-runbooks/f3-11-input-triage-runbook.md`](../05-runbooks/f3-11-input-triage-runbook.md) | intake + ledger |
| marcar aceite/rejeicao/parcial | [`../01-architecture/f3-11-input-acceptance-matrix.md`](../01-architecture/f3-11-input-acceptance-matrix.md) | intake + ledger + registro mestre |
| actualizar o estado operacional corrente | [`../01-architecture/f3-11-execution-master-register.md`](../01-architecture/f3-11-execution-master-register.md) | registro mestre |
| registar microdecisao operacional | [`../01-architecture/f3-11-operational-decisions-ledger.md`](../01-architecture/f3-11-operational-decisions-ledger.md) | ledger |
| consolidar a rodada inteira | [`../05-runbooks/f3-11-cycle-report-template.md`](../05-runbooks/f3-11-cycle-report-template.md) | relatorio do ciclo |
| decidir se a readiness pode reabrir | [`../01-architecture/f3-11-readiness-reopen-gate.md`](../01-architecture/f3-11-readiness-reopen-gate.md) | ledger + scorecard + ciclo |
| executar a verificacao live pos-gate | [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md) | artefactos da rodada real |
| registar drift novo | [`../01-architecture/f3-11-drift-registry.md`](../01-architecture/f3-11-drift-registry.md) | drift registry + ledger |

---

## 4. Fluxo minimo ponta a ponta

1. ler `start-here`;
2. ler scorecard;
3. ler registro mestre;
4. seguir o protocolo de sincronizacao;
5. se o assunto for o blocker restante, seguir o roteiro DR-05 do appliance;
6. se chegar evidencia externa nova, abrir intake;
7. triar;
8. actualizar matriz, ledger e registro mestre;
9. actualizar scorecard;
10. verificar gate;
11. fechar o ciclo da rodada;
12. so depois consolidar relatorio final.

Qualquer atalho que salte `start-here`, intake, triagem, ledger, registro
mestre, scorecard ou criterio de fecho reintroduz ambiguidade e nao deve ser
tratado como progresso valido da F3.11.

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
