# F3.11 - Registro Mestre de Execucao

## Finalidade

Este documento e o painel manual central da trilha F3.11.

Objectivo:

- consolidar, num unico ponto, o estado operacional dos cinco insumos
  externos;
- evitar leitura dispersa de varios documentos para entender o estado geral;
- ligar cada insumo ao blocker, evidencia, decisao, proximo passo e gate;
- manter a trilha pronta para operar assim que qualquer insumo real comecar a
  chegar.

Estado formal preservado nesta rodada:

- `F3 continua aberta`;
- `F3.11 continua bloqueada`;
- `esta rodada e apenas documental-operacional`;
- `nenhum ficheiro de codigo foi alterado`;
- `nao houve push`;
- `nao houve reabertura da readiness`;
- `nao houve abertura de campanha`.

Leitura complementar obrigatoria:

- [`../00-overview/f3-11-start-here.md`](../00-overview/f3-11-start-here.md)
- [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md)
- [`f3-11-drift-registry.md`](f3-11-drift-registry.md)
- [`f3-11-input-acceptance-matrix.md`](f3-11-input-acceptance-matrix.md)
- [`f3-11-readiness-reopen-gate.md`](f3-11-readiness-reopen-gate.md)
- [`f3-11-readiness-scorecard.md`](f3-11-readiness-scorecard.md)
- [`f3-11-state-machine.md`](f3-11-state-machine.md)
- [`f3-11-document-sync-protocol.md`](f3-11-document-sync-protocol.md)
- [`f3-11-operational-responsibility-matrix.md`](f3-11-operational-responsibility-matrix.md)
- [`f3-11-operational-decisions-ledger.md`](f3-11-operational-decisions-ledger.md)
- [`../05-runbooks/f3-11-input-triage-runbook.md`](../05-runbooks/f3-11-input-triage-runbook.md)
- [`../05-runbooks/f3-11-evidence-intake-template.md`](../05-runbooks/f3-11-evidence-intake-template.md)
- [`../05-runbooks/f3-11-cycle-report-template.md`](../05-runbooks/f3-11-cycle-report-template.md)
- [`../05-runbooks/f3-11-cycle-closure-criteria.md`](../05-runbooks/f3-11-cycle-closure-criteria.md)

---

## 1. Painel consolidado

| Campo | Estado actual |
|-------|---------------|
| Fase | `F3 aberta` |
| Subtrilha | `F3.11 bloqueada` |
| Readiness | `NO-GO` |
| Campanha | `NO-GO` |
| Insumos validos | `0/5` |
| Insumos parciais | `0/5` |
| Insumos ausentes | `5/5` |
| Drifts abertos relevantes | `DR-01` a `DR-07` |
| Ultima natureza de rodada | `documental-operacional estrita` |
| Codigo alterado nesta rodada | `nao` |
| Push efectuado nesta rodada | `nao` |

Leitura operacional:

- existe processo canónico para solicitar, receber, triar, aceitar/rejeitar
  e reavaliar os cinco insumos;
- nenhum dos cinco insumos entrou ainda em `entregue valido`;
- qualquer nova entrega deve nascer primeiro no intake e no ledger, e so
  depois actualizar scorecard e gate.

---

## 2. Registro mestre por insumo

| Insumo | Estado actual | Ultimo evento conhecido | Evidencia ja disponivel | Proxima evidencia esperada | Documento fonte aplicavel | Blocker associado | Responsavel externo esperado | Accao interna pendente | Condicao de aceite | Condicao de rejeicao | Efeito sobre readiness | Efeito sobre campanha |
|--------|---------------|-------------------------|-------------------------|----------------------------|---------------------------|-------------------|------------------------------|------------------------|--------------------|----------------------|------------------------|-----------------------|
| acesso read-only ao host `192.168.100.244` | `nao entregue` | readiness inicial e saneamento confirmaram `HTTP 200` no origin, mas `ssh` read-only continua indisponivel | `http://192.168.100.244:8445/api/health` respondeu `200`; tentativa de `ssh` em `BatchMode=yes` falhou com `Permission denied` | `ssh` read-only legitimado com hostname real, directorio real, `git rev-parse HEAD`, `docker compose ps` e `docker compose config --services` | [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md), [`f3-11-external-input-request-package.md`](f3-11-external-input-request-package.md), [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md) | ausencia de shell live + `DR-07` | owner operacional do host/deploy live | abrir intake quando o acesso chegar, executar triagem e actualizar ledger/scorecard | output bruto prova host, directorio, revisao e stack efectiva sem ambiguidade | output parcial, sem Git, sem compose ou sem prova do host observado | continua `NO-GO` enquanto nao ficar `entregue valido` | continua `NO-GO` enquanto nao ficar `entregue valido` |
| query read-only ao PostgreSQL live | `nao entregue` | readiness e saneamento mantiveram o schema live sem prova directa | contrato canónico do repo continua a exigir `admin_sessions`, `admin_audit_log` e `admin_login_guards`; nao existe query live nova | query read-only com identidade da base, listagem das cinco tabelas chave e `count(*)` nas tres tabelas administrativas | [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md), [`f3-11-input-acceptance-matrix.md`](f3-11-input-acceptance-matrix.md), [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md) | ausencia de DB read-only + `DR-01` + `DR-07` | owner operacional do banco/stack live | abrir intake, validar queries objectivas, decidir aceite/rejeicao e actualizar scorecard | identidade da base e schema real ficam provados no ambiente observado | falta de SQL, falta de identidade da base, export antigo ou print sem origem | continua `NO-GO` enquanto o schema live permanecer sem prova | continua `NO-GO` enquanto o schema live permanecer sem prova |
| credencial admin autorizada com escopo formal | `nao entregue` | readiness e saneamento provaram que `/api/auth/login` esta activo, mas nao houve credencial real; drift de CORS continua registado | `POST /api/auth/login` respondeu `400/401`; `/api/auth/login` aceitou `Origin` externo com `Access-Control-Allow-Origin: *` | credencial real com owner, escopo, janela de uso, login bem sucedido e sessao valida em `/api/auth/session` | [`f3-11-external-input-request-package.md`](f3-11-external-input-request-package.md), [`f3-11-drift-registry.md`](f3-11-drift-registry.md), [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md) | ausencia de credencial autorizada + `DR-03` + `DR-06` | owner administrativo do painel / gestor da campanha | abrir intake, testar login no fluxo oficial, registar escopo formal e microdecisao no ledger | credencial entra no fluxo oficial, gera sessao valida e tem escopo documentado | password informal, sem owner, sem escopo ou sem sessao verificavel | continua `NO-GO` enquanto a metade administrativa estiver fechada | continua `NO-GO` enquanto a metade administrativa estiver fechada |
| appliance pfSense com SSH, baseline e controlos legitimos | `nao entregue` | readiness e saneamento mantiveram apenas placeholders; nao ha host real nem baseline do appliance | ausencia de `PF_SSH`, ausencia de inventario de lab preenchido e existencia de `<PFSENSE_IP>` nos artefactos antigos | host/IP real com `ssh` funcional, baseline recolhida e prova de snapshot/restore, relogio, offline e controlo de NIC/UUID | [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md), [`f3-11-external-input-request-package.md`](f3-11-external-input-request-package.md), [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md) | ausencia de appliance verificavel + `DR-05` | owner do lab/appliance + owner do hypervisor | abrir intake, recolher baseline, validar controlos do lab e actualizar scorecard | SSH, baseline e controlos legitimam a metade local da campanha sem improviso | placeholder, consola informal, baseline incompleta ou sem snapshot/controlos | continua `NO-GO` para os cenarios locais | continua `NO-GO` para os cenarios locais |
| inventario real `LIC-A` a `LIC-F` | `nao entregue` | readiness, saneamento e kit de insumos confirmaram ausencia de pool real; ainda so existem placeholders | placeholders de licenca no repo; nao existe artefacto versionado de preflight com `LIC-A` a `LIC-F` reais | inventario preenchido com prova em backend live e mapeamento `licenca -> cliente -> appliance -> cenario` | [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md), [`f3-11-external-input-request-package.md`](f3-11-external-input-request-package.md), [`f3-11-input-acceptance-matrix.md`](f3-11-input-acceptance-matrix.md) | ausencia de inventario real + `DR-04` e dependencia operacional de `DR-02` | owner administrativo do licenciamento | abrir intake, cruzar inventario com backend e decidir aceite/rejeicao | seis pools reais ficam reservados e comprovados no backend observado | placeholders, IDs incoerentes, sem prova cruzada ou com reuso opaco | continua `NO-GO` enquanto nao houver pool real auditavel | continua `NO-GO` enquanto nao houver pool real auditavel |

---

## 3. Leitura de blockers

### Blockers de readiness

1. falta de shell read-only ao host observado;
2. falta de query read-only ao PostgreSQL live;
3. falta de credencial administrativa autorizada com escopo formal;
4. falta de appliance pfSense verificavel com controlos legitimos;
5. falta de inventario real `LIC-A` a `LIC-F`.

### Blockers de campanha

1. todos os blockers de readiness acima;
2. `DR-02` continua sem revalidacao real do contrato `409` vs `403`;
3. `DR-06` continua aberto como drift da superficie administrativa;
4. qualquer insumo ausente, parcial ou invalido mantem campanha em `NO-GO`.

---

## 4. Regra de actualizacao deste registro

Sempre que houver nova entrega real:

1. abrir [`../00-overview/f3-11-start-here.md`](../00-overview/f3-11-start-here.md)
   e seguir o
   [`f3-11-document-sync-protocol.md`](f3-11-document-sync-protocol.md);
2. abrir um registo com base em
   [`../05-runbooks/f3-11-evidence-intake-template.md`](../05-runbooks/f3-11-evidence-intake-template.md);
3. aplicar a triagem em
   [`../05-runbooks/f3-11-input-triage-runbook.md`](../05-runbooks/f3-11-input-triage-runbook.md);
4. registar a microdecisao em
   [`f3-11-operational-decisions-ledger.md`](f3-11-operational-decisions-ledger.md);
5. actualizar este registro mestre;
6. actualizar o
   [`f3-11-readiness-scorecard.md`](f3-11-readiness-scorecard.md);
7. so depois verificar o
   [`f3-11-readiness-reopen-gate.md`](f3-11-readiness-reopen-gate.md).

Sem estes sete passos, nao existe progresso operacional valido da F3.11.

---

## 5. Nota operacional de publicacao

Leitura factual desta rodada:

- o branch local entrou na rodada como `main...origin/main [ahead 21]`;
- esta rodada continua sem push;
- qualquer push agora teria consequencia operacional nao trivial por publicar
  historico local acumulado;
- este registro mestre e documental-operacional e nao altera o estado de
  publicacao do repositório.

---

## 6. Objectivo, impacto, risco, teste e rollback deste bloco

- **Objectivo:** concentrar o estado operacional dos cinco insumos em um
  cockpit canónico unico.
- **Impacto:** documental-operacional apenas; nenhum ficheiro de codigo foi
  alterado.
- **Risco:** baixo; o documento apenas consolida estado, blockers e fluxo de
  actualizacao.
- **Teste minimo:** coerencia cruzada com o kit F3.11 ja existente,
  especialmente a matriz de aceite, o gate de reabertura e o drift registry.
- **Rollback:** `git revert <commit-deste-bloco>`.
