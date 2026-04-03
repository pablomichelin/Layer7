# F3.11 - Start Here

## Finalidade

Este e o ponto unico de entrada operacional da F3.11.

Use este documento primeiro em qualquer rodada da trilha.

---

## 1. Estado actual comprovado

- `F3 continua aberta`
- `F3.11 continua bloqueada`
- `readiness = NO-GO`
- `campanha = NO-GO`
- `sem codigo`
- `sem push`
- `sem reabertura da readiness`
- `sem abertura de campanha`

Leitura objectiva desta rodada:

- o branch local entrou na rodada `main...origin/main [ahead 22]`;
- esta rodada continua apenas documental-operacional;
- roadmap e backlog nao mudaram porque o estado formal nao mudou.

---

## 2. Os cinco insumos externos

1. acesso read-only ao host `192.168.100.244`
2. query read-only ao PostgreSQL live
3. credencial admin autorizada com escopo formal
4. appliance pfSense com SSH, baseline e controlos legitimos
5. inventario real `LIC-A` a `LIC-F`

Enquanto qualquer um deles nao estiver `entregue valido`, a readiness continua
proibida.

---

## 3. Qual documento abrir primeiro depois deste

Abra:

1. [`../01-architecture/f3-11-execution-master-register.md`](../01-architecture/f3-11-execution-master-register.md)
2. [`../01-architecture/f3-11-readiness-scorecard.md`](../01-architecture/f3-11-readiness-scorecard.md)
3. [`../01-architecture/f3-11-document-sync-protocol.md`](../01-architecture/f3-11-document-sync-protocol.md)

Isto da o estado corrente, o resumo executivo e a ordem certa de
actualizacao.

---

## 4. Sequencia quando chegar evidencia real

1. abrir um ciclo;
2. abrir intake do insumo recebido;
3. triar com o runbook;
4. classificar com a matriz de aceite;
5. registar a microdecisao no ledger;
6. actualizar o registro mestre;
7. actualizar o drift registry se houver drift novo;
8. actualizar o scorecard;
9. reavaliar o gate so se tudo estiver sincronizado;
10. fechar o ciclo.

Nao saltar directamente para o scorecard, para o gate ou para o checklist
live.

---

## 5. Sequencia quando nada chegou ainda

1. confirmar no scorecard e no registro mestre que o estado continua bloqueado;
2. usar o pacote de solicitacao externa para cobrar o insumo certo;
3. abrir ciclo apenas se houver microdecisao real, ajuste documental interno
   ou saneamento de sincronizacao;
4. fechar o ciclo com blockers mantidos, sem reabrir readiness.

---

## 6. Quando a readiness continua proibida

A readiness continua proibida quando houver qualquer um dos casos abaixo:

- menos de `5/5` insumos `entregue valido`;
- intake inexistente ou incompleto;
- triagem nao concluida;
- scorecard e registro mestre divergentes;
- gate ainda em `NO-GO`;
- drift estrutural ainda activo sem classificacao.

---

## 7. Quando a campanha continua proibida

A campanha continua proibida quando houver qualquer um dos casos abaixo:

- readiness ainda nao reaberta;
- readiness repetida mas concluida sem `GO`;
- drift blocker ainda activo;
- qualquer cenario obrigatorio estruturalmente `BLOCKED`;
- hold operacional ou de governanca ainda nao removido.

---

## 8. Documentos operacionais vs documentos de referencia

### Operacionais e actualizaveis

- `../01-architecture/f3-11-execution-master-register.md`
- `../01-architecture/f3-11-operational-decisions-ledger.md`
- `../01-architecture/f3-11-readiness-scorecard.md`
- `../01-architecture/f3-11-drift-registry.md`
- ciclos e intakes derivados dos templates em `../05-runbooks/`

### De referencia

- `../01-architecture/f3-11-external-input-request-package.md`
- `../01-architecture/f3-11-input-acceptance-matrix.md`
- `../01-architecture/f3-11-readiness-reopen-gate.md`
- `../01-architecture/f3-11-state-machine.md`
- `../01-architecture/f3-11-document-sync-protocol.md`
- `../01-architecture/f3-11-operational-responsibility-matrix.md`
- `../05-runbooks/f3-11-live-access-checklist.md`
- `../05-runbooks/f3-11-cycle-closure-criteria.md`
- `f3-11-document-traceability-map.md`

---

## 9. Regra final de arranque

Se houver duvida sobre por onde comecar:

1. comece aqui;
2. depois abra o registro mestre;
3. siga o protocolo de sincronizacao;
4. trate qualquer atalho como invalido.
