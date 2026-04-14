# F3.11 - Matriz Canonica de Responsabilidade Operacional

## Finalidade

Este documento define, por papel, quem faz o que na trilha F3.11.

Objectivo:

- reduzir ambiguidade operacional;
- impedir que o documento errado seja actualizado pela pessoa errada;
- separar quem entrega insumo, quem valida, quem decide gate e quem executa
  readiness;
- deixar claro o handoff esperado entre papeis.

Estado formal preservado:

- `F3 continua aberta`;
- `F3.11 alinhada no license-server live`;
- `DR-05 pendente`;
- `esta matriz nao abre campanha`;
- `esta matriz nao fecha a F3`.

Nota de actualizacao em `2026-04-14`:

- os papeis ligados a cinco insumos ficam preservados como compatibilidade;
- a responsabilidade operacional corrente concentra-se em executar e
  documentar `DR-05` no appliance, com permissao suficiente,
  snapshot/rollback e evidencias por `run_id`.

---

## 1. Matriz por papel

| Papel | Responsabilidade principal | Artefactos que actualiza | Artefactos que consulta | Decisoes que pode tomar | Decisoes que nao pode tomar | Handoff esperado | Risco de falha se actuar mal |
|-------|----------------------------|--------------------------|-------------------------|-------------------------|-----------------------------|------------------|------------------------------|
| operador documental | abrir ciclo, abrir intake, manter ledger, registro mestre, scorecard e nota operacional sincronizados | intakes derivados do template, ciclo operacional, ledger, registro mestre, scorecard, eventualmente drift registry | `start-here`, protocolo de sincronizacao, matriz de aceite, gate, maquina de estados, traceability map | pode classificar o fluxo documental da rodada, pode marcar blocker mantido, pode fechar ciclo documental completo | nao pode declarar insumo valido sem evidencia e triagem; nao pode reabrir readiness sozinho; nao pode abrir campanha | recebe entrega bruta do fornecedor, passa para o validador tecnico e depois consolida a decisao tomada | perda de rastreabilidade, scorecard divergente, ciclo invalido ou falsa percepcao de progresso |
| validador tecnico | verificar se a evidencia entregue satisfaz o minimo canónico do dominio | intake da sua verificacao, drift registry quando surgir drift tecnico novo, contributo tecnico no ciclo | pacote de solicitacao externa, matriz de aceite, triage runbook, checklist live | pode decidir se a evidencia e tecnica e objectivamente suficiente ou insuficiente | nao pode alterar estado macro da F3; nao pode aprovar campanha; nao pode contornar falta de owner ou escopo formal | devolve ao operador documental uma decisao tecnica com causa objectiva e referencia de evidencia | aceite de prova invalida, triagem frouxa, regressao do gate ou falsa liberacao de subgate |
| fornecedor externo do insumo | entregar acesso, credencial, query, appliance ou inventario no formato pedido | nao actualiza artefactos canónicos internos; apenas fornece output bruto, acesso ou artefacto fonte | pacote de solicitacao externa e, quando necessario, checklist live do seu dominio | pode entregar, complementar ou corrigir a entrega | nao pode marcar a propria entrega como valida; nao pode decidir gate; nao pode substituir output por narrativa | entrega ao operador documental e ao validador tecnico o material bruto e o owner responsavel | evidencia incompleta, ambigua ou sem escopo; readiness reaberta com base fraca |
| decisor de gate | decidir `GO/NO-GO` formal para repetir readiness e, depois, para campanha | ledger, scorecard, gate, ciclo operacional | registro mestre, scorecard, maquina de estados, protocolo de sincronizacao, drift registry, checklist live | pode declarar `GO` ou `NO-GO` quando os pre-requisitos documentais e tecnicos estiverem completos | nao pode saltar intake/triagem; nao pode declarar `GO` so porque "parece suficiente"; nao pode abrir campanha sem bloco proprio | recebe dossie sincronizado do operador documental e do validador tecnico e devolve a decisao formal | gate inconsistente, readiness reaberta cedo demais, campanha aberta com blocker activo |
| custodiante do repositorio | preservar coerencia canónica dos artefactos, estado Git e nota de publicacao | `CORTEX.md`, indices afectados, classificacao documental, nota de publicacao e commit local | todos os artefactos canonicos relevantes da rodada | pode consolidar a documentacao canónica da trilha e fazer commit local | nao pode fazer push sem autorizacao explicita; nao pode confundir historico local com publicacao do produto | recebe o bloco documental fechado e materializa a persistencia local do estado | perda de continuidade entre chats, publicacao indevida, drift documental entre cockpit e SSOT |
| executor da readiness | executar a readiness repetida no ambiente real depois do gate `GO` | artefactos reais da readiness, checklist live, ciclo, ledger, scorecard e registro mestre em conjunto com o operador documental | gate, checklist live, maquina de estados, protocolo, scorecard, registro mestre | pode executar os oito passos da readiness repetida e produzir a evidencia real | nao pode iniciar readiness com gate `NO-GO`; nao pode abrir campanha por conta propria; nao pode ignorar drift ou blocker novo | recebe do decisor de gate a autorizacao formal e devolve evidencia real de readiness concluida | readiness mal executada, conclusao sem lastro, campanha aberta sem prova suficiente |

---

## 2. Regra de separacao minima de papeis

1. quem entrega o insumo nao valida sozinho o proprio insumo;
2. quem valida tecnicamente nao publica `GO` sem o decisor de gate;
3. quem executa a readiness nao deve alterar retroactivamente a triagem do
   insumo para "fazer caber";
4. quem cuida do repositorio nao deve tratar commit local como autorizacao de
   push.

---

## 3. Handoffs obrigatorios

| De | Para | Handoff minimo exigido |
|----|------|------------------------|
| fornecedor externo | operador documental | evidencia bruta, owner responsavel, escopo e janela de uso quando aplicavel |
| operador documental | validador tecnico | intake aberto, artefactos anexados, criterio minimo do insumo e pergunta objectiva de validacao |
| validador tecnico | operador documental | classificacao tecnica objectiva com motivo de aceite, rejeicao ou parcial |
| operador documental | decisor de gate | registro mestre e scorecard sincronizados, ledger actualizado, ciclo pronto para decisao |
| decisor de gate | executor da readiness | decisao formal `GO/NO-GO`, limites de execucao e artefactos necessarios |
| executor da readiness | operador documental e decisor de gate | output bruto da readiness, drifts novos, conclusao final e impactos sobre campanha |

---

## 4. Limite formal desta matriz

Esta matriz:

- nao substitui o protocolo de sincronizacao;
- nao substitui o gate;
- nao atribui nomes pessoais;
- nao autoriza excepcao de processo.

Serve apenas para deixar claro quem actualiza, quem consulta e quem decide em
cada passo da F3.11.
