# F3.11 - Readiness Scorecard

## Finalidade

Este scorecard e a pagina executiva de estado da trilha F3.11.

Objectivo:

- responder em leitura rapida se a readiness pode ou nao ser reaberta;
- mostrar, sem ambiguidade, o estado dos cinco insumos;
- destacar blockers e drifts ainda activos;
- apontar a proxima accao unica mais importante.

Estado formal preservado:

- `F3 continua aberta`;
- `F3.11 continua bloqueada`;
- `readiness = NO-GO`;
- `campanha = NO-GO`;
- `sem codigo`;
- `sem push`;
- `sem campanha`;
- `sem reabertura da readiness`.

---

## 1. Estado executivo

| Campo | Valor |
|-------|-------|
| Ultima actualizacao | `2026-04-02` |
| Readiness status geral | `NO-GO` |
| Campanha status geral | `NO-GO` |
| Fase | `F3 aberta` |
| Subtrilha | `F3.11 bloqueada` |
| Insumos ausentes | `5` |
| Insumos parciais | `0` |
| Insumos validos | `0` |
| Insumos bloqueantes | `5` |
| Drifts abertos com impacto directo | `DR-01`, `DR-02`, `DR-03`, `DR-04`, `DR-05`, `DR-06`, `DR-07` |
| Push nesta rodada | `nao` |

Leitura binaria:

- enquanto qualquer insumo ficar fora de `entregue valido`, a readiness
  continua `NO-GO`;
- enquanto a readiness continuar `NO-GO`, a campanha continua `NO-GO`.

---

## 2. Resumo por insumo

| Insumo | Estado executivo | Categoria | Bloqueante? | Razao imediata | Documento de referencia |
|--------|------------------|-----------|-------------|----------------|-------------------------|
| acesso read-only ao host `192.168.100.244` | `ausente` | `nao entregue` | `sim` | existe saude HTTP do origin, mas continua a faltar `ssh` read-only com prova de host, directorio, revisao e compose | [`f3-11-execution-master-register.md`](f3-11-execution-master-register.md), [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md) |
| query read-only ao PostgreSQL live | `ausente` | `nao entregue` | `sim` | schema live continua sem prova objectiva no ambiente observado | [`f3-11-execution-master-register.md`](f3-11-execution-master-register.md), [`f3-11-input-acceptance-matrix.md`](f3-11-input-acceptance-matrix.md) |
| credencial admin autorizada com escopo formal | `ausente` | `nao entregue` | `sim` | endpoint existe, mas nao ha credencial, owner e sessao formalmente exercitaveis | [`f3-11-execution-master-register.md`](f3-11-execution-master-register.md), [`f3-11-drift-registry.md`](f3-11-drift-registry.md) |
| appliance pfSense com SSH, baseline e controlos legitimos | `ausente` | `nao entregue` | `sim` | nao existe appliance real verificavel com baseline e controlos do lab | [`f3-11-execution-master-register.md`](f3-11-execution-master-register.md), [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md) |
| inventario real `LIC-A` a `LIC-F` | `ausente` | `nao entregue` | `sim` | o pool real por cenario continua inexistente do ponto de vista auditavel | [`f3-11-execution-master-register.md`](f3-11-execution-master-register.md), [`f3-11-input-acceptance-matrix.md`](f3-11-input-acceptance-matrix.md) |

---

## 3. Resumo por blocker

| Blocker | Estado | Efeito | Documento de referencia |
|---------|--------|--------|-------------------------|
| shell read-only ao host live inexistente | `aberto` | impede provar revisao exacta, directorio real e stack activa | [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md) |
| query read-only ao PostgreSQL inexistente | `aberto` | impede provar schema, sessao e auditoria no live | [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md) |
| credencial administrativa autorizada inexistente | `aberto` | impede metade administrativa da readiness e da campanha | [`f3-11-external-input-request-package.md`](f3-11-external-input-request-package.md) |
| appliance pfSense verificavel inexistente | `aberto` | impede metade local da campanha e todos os cenarios que dependem de baseline/controlos | [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md) |
| inventario real `LIC-A` a `LIC-F` inexistente | `aberto` | impede campanha limpa, revalidacao do contrato `409` vs `403` e reserva por cenario | [`f3-11-external-input-request-package.md`](f3-11-external-input-request-package.md) |

---

## 4. Drifts abertos

| Drift | Estado | Leitura executiva | Documento de referencia |
|-------|--------|-------------------|-------------------------|
| `DR-01` schema live | `aberto` | schema live continua sem prova read-only no ambiente observado | [`f3-11-drift-registry.md`](f3-11-drift-registry.md) |
| `DR-02` contrato `409` vs `403` em `activate` | `aberto` | continua sem revalidacao real por falta de inventario/controlos | [`f3-11-drift-registry.md`](f3-11-drift-registry.md) |
| `DR-03` governanca administrativa | `aberto` | falta credencial admin com escopo formal | [`f3-11-drift-registry.md`](f3-11-drift-registry.md) |
| `DR-04` inventario de licencas | `aberto` | nao existe pool real `LIC-A` a `LIC-F` comprovado | [`f3-11-drift-registry.md`](f3-11-drift-registry.md) |
| `DR-05` ambiente de appliance | `aberto` | nao existe appliance pfSense elegivel e verificavel | [`f3-11-drift-registry.md`](f3-11-drift-registry.md) |
| `DR-06` CORS / same-origin | `aberto` | live continua observado com `Access-Control-Allow-Origin: *` em auth/admin | [`f3-11-drift-registry.md`](f3-11-drift-registry.md) |
| `DR-07` publicacao / revisao | `aberto` | equivalencia entre live, remoto e local continua nao demonstrada | [`f3-11-drift-registry.md`](f3-11-drift-registry.md) |

---

## 5. Proxima accao unica mais importante

`Aguardar o primeiro insumo real e, no momento da recepcao, abrir o primeiro
ciclo operacional padronizado com intake, triagem, ledger e actualizacao do
registro mestre antes de qualquer nova verificacao de readiness.`

Ordem obrigatoria dessa accao:

1. usar o
   [`../05-runbooks/f3-11-cycle-report-template.md`](../05-runbooks/f3-11-cycle-report-template.md);
2. abrir intake do insumo recebido;
3. triar com o runbook;
4. registar a microdecisao no ledger;
5. actualizar este scorecard e o registro mestre.

---

## 6. Nota operacional de publicacao

- o branch local entrou nesta rodada como `main...origin/main [ahead 21]`;
- nao houve push;
- qualquer push agora publicaria historico local acumulado;
- este scorecard nao altera o estado de publicacao; apenas o torna visivel.

---

## 7. Objectivo, impacto, risco, teste e rollback deste bloco

- **Objectivo:** criar uma visao executiva, rapida e inequivoca do `GO/NO-GO`
  da F3.11.
- **Impacto:** documental-operacional apenas; nenhum ficheiro de codigo foi
  alterado.
- **Risco:** baixo; o scorecard resume o estado sem alterar gates.
- **Teste minimo:** coerencia cruzada com o registro mestre, o drift
  registry e o gate de reabertura.
- **Rollback:** `git revert <commit-deste-bloco>`.
