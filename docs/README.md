# Documentacao Canónica

Este directorio e o **centro documental canónico** do projecto Layer7.
Nem todo ficheiro aqui dentro tem o mesmo peso: alguns sao SSOT, outros sao
suplementares, historicos, placeholders ou preservados por compatibilidade.

Quando houver conflito, seguir esta ordem:

1. [`../CORTEX.md`](../CORTEX.md)
2. [`README.md`](README.md)
3. [`02-roadmap/roadmap.md`](02-roadmap/roadmap.md)
4. [`02-roadmap/backlog.md`](02-roadmap/backlog.md)
5. [`02-roadmap/checklist-mestre.md`](02-roadmap/checklist-mestre.md)
6. [`00-overview/document-classification.md`](00-overview/document-classification.md)
7. [`00-overview/document-equivalence-map.md`](00-overview/document-equivalence-map.md)
8. [`03-adr/README.md`](03-adr/README.md)
9. [`01-architecture/f1-arquitetura-de-confianca.md`](01-architecture/f1-arquitetura-de-confianca.md)
10. [`02-roadmap/f1-plano-de-implementacao.md`](02-roadmap/f1-plano-de-implementacao.md)
11. documentacao canónica por area

---

## Documentos canónicos principais

| Tema | Documento canónico | Papel |
|------|--------------------|-------|
| Estado global do projecto | [`../CORTEX.md`](../CORTEX.md) | SSOT operacional, fase actual e checkpoint |
| Uso desta arvore documental | [`README.md`](README.md) | indice oficial e ordem de leitura |
| Fases aprovadas | [`02-roadmap/roadmap.md`](02-roadmap/roadmap.md) | roadmap F0-F7 com gates |
| Priorizacao | [`02-roadmap/backlog.md`](02-roadmap/backlog.md) | backlog unico priorizado |
| Gates e disciplina de execucao | [`02-roadmap/checklist-mestre.md`](02-roadmap/checklist-mestre.md) | checklist de entrada, execucao e saida |
| Classificacao dos documentos | [`00-overview/document-classification.md`](00-overview/document-classification.md) | diz o que e canónico, historico, placeholder ou preservado |
| Equivalencia raiz <-> docs | [`00-overview/document-equivalence-map.md`](00-overview/document-equivalence-map.md) | resolve sobreposicoes e conflitos |
| Decisoes formais | [`03-adr/README.md`](03-adr/README.md) | indice e politica de ADR |
| Instalacao/upgrade/uninstall | [`10-license-server/MANUAL-INSTALL.md`](10-license-server/MANUAL-INSTALL.md) | referencia operacional canónica |
| Linha temporal de releases | [`changelog/CHANGELOG.md`](changelog/CHANGELOG.md) | changelog oficial |

---

## Mapa das areas documentais

| Area | Documento(s) de entrada | Estado |
|------|--------------------------|--------|
| Overview e navegacao | [`00-overview/product-charter.md`](00-overview/product-charter.md), [`00-overview/document-classification.md`](00-overview/document-classification.md), [`00-overview/document-equivalence-map.md`](00-overview/document-equivalence-map.md), [`00-overview/handoff-chat-novo.md`](00-overview/handoff-chat-novo.md) (mudar de chat quando o contexto fica longo), [`00-overview/f3-11-start-here.md`](00-overview/f3-11-start-here.md), [`00-overview/f3-organizacao-local-e-fecho.md`](00-overview/f3-organizacao-local-e-fecho.md), [`00-overview/f3-11-document-traceability-map.md`](00-overview/f3-11-document-traceability-map.md) | canónico |
| Arquitectura | [`01-architecture/target-architecture.md`](01-architecture/target-architecture.md), [`01-architecture/f1-arquitetura-de-confianca.md`](01-architecture/f1-arquitetura-de-confianca.md), [`01-architecture/f2-arquitetura-license-server.md`](01-architecture/f2-arquitetura-license-server.md), [`01-architecture/f3-arquitetura-licenciamento-ativacao.md`](01-architecture/f3-arquitetura-licenciamento-ativacao.md), [`01-architecture/f3-fingerprint-e-binding.md`](01-architecture/f3-fingerprint-e-binding.md), [`01-architecture/f3-expiracao-revogacao-grace.md`](01-architecture/f3-expiracao-revogacao-grace.md), [`01-architecture/f3-mutacao-admin-reemissao-guardrails.md`](01-architecture/f3-mutacao-admin-reemissao-guardrails.md), [`01-architecture/f3-emissao-reemissao-rastreabilidade.md`](01-architecture/f3-emissao-reemissao-rastreabilidade.md), [`01-architecture/f3-validacao-manual-evidencias.md`](01-architecture/f3-validacao-manual-evidencias.md), [`01-architecture/f3-pack-operacional-validacao.md`](01-architecture/f3-pack-operacional-validacao.md), [`01-architecture/f3-gate-fechamento-validacao.md`](01-architecture/f3-gate-fechamento-validacao.md), [`01-architecture/f3-fecho-operacional-restante.md`](01-architecture/f3-fecho-operacional-restante.md), [`01-architecture/f3-11-readiness-check.md`](01-architecture/f3-11-readiness-check.md), [`01-architecture/f3-11-readiness-saneamento.md`](01-architecture/f3-11-readiness-saneamento.md), [`01-architecture/f3-11-access-enablement-package.md`](01-architecture/f3-11-access-enablement-package.md), [`01-architecture/f3-11-drift-registry.md`](01-architecture/f3-11-drift-registry.md), [`01-architecture/f3-11-external-input-request-package.md`](01-architecture/f3-11-external-input-request-package.md), [`01-architecture/f3-11-input-acceptance-matrix.md`](01-architecture/f3-11-input-acceptance-matrix.md), [`01-architecture/f3-11-execution-master-register.md`](01-architecture/f3-11-execution-master-register.md), [`01-architecture/f3-11-operational-decisions-ledger.md`](01-architecture/f3-11-operational-decisions-ledger.md), [`01-architecture/f3-11-readiness-scorecard.md`](01-architecture/f3-11-readiness-scorecard.md), [`01-architecture/f3-11-readiness-reopen-gate.md`](01-architecture/f3-11-readiness-reopen-gate.md), [`01-architecture/f3-11-state-machine.md`](01-architecture/f3-11-state-machine.md), [`01-architecture/f3-11-document-sync-protocol.md`](01-architecture/f3-11-document-sync-protocol.md), [`01-architecture/f3-11-operational-responsibility-matrix.md`](01-architecture/f3-11-operational-responsibility-matrix.md), [`core/README.md`](core/README.md) | canónico |
| Roadmap e execucao | [`02-roadmap/roadmap.md`](02-roadmap/roadmap.md), [`02-roadmap/backlog.md`](02-roadmap/backlog.md), [`02-roadmap/checklist-mestre.md`](02-roadmap/checklist-mestre.md), [`02-roadmap/f1-plano-de-implementacao.md`](02-roadmap/f1-plano-de-implementacao.md), [`02-roadmap/f2-plano-de-implementacao.md`](02-roadmap/f2-plano-de-implementacao.md), [`02-roadmap/f4-plano-de-implementacao.md`](02-roadmap/f4-plano-de-implementacao.md), [`02-roadmap/f5-preparacao-malha.md`](02-roadmap/f5-preparacao-malha.md) | canónico |
| ADRs | [`03-adr/README.md`](03-adr/README.md) | canónico |
| Package e validacao | [`04-package/README.md`](04-package/README.md), [`04-package/validacao-lab.md`](04-package/validacao-lab.md) | suplementar/operacional |
| Daemon e enforcement | [`05-daemon/README.md`](05-daemon/README.md), [`05-daemon/pf-enforcement.md`](05-daemon/pf-enforcement.md) | suplementar com partes historicas |
| Runbooks | [`05-runbooks/README.md`](05-runbooks/README.md), [`05-runbooks/license-server-publicacao-segura.md`](05-runbooks/license-server-publicacao-segura.md), [`05-runbooks/license-server-auth-sessao.md`](05-runbooks/license-server-auth-sessao.md), [`05-runbooks/license-server-segredos-bootstrap.md`](05-runbooks/license-server-segredos-bootstrap.md), [`05-runbooks/license-server-backup-restore.md`](05-runbooks/license-server-backup-restore.md), [`05-runbooks/f3-11-live-access-checklist.md`](05-runbooks/f3-11-live-access-checklist.md), [`05-runbooks/f3-11-input-triage-runbook.md`](05-runbooks/f3-11-input-triage-runbook.md), [`05-runbooks/f3-11-evidence-intake-template.md`](05-runbooks/f3-11-evidence-intake-template.md), [`05-runbooks/f3-11-cycle-report-template.md`](05-runbooks/f3-11-cycle-report-template.md), [`05-runbooks/f3-11-cycle-closure-criteria.md`](05-runbooks/f3-11-cycle-closure-criteria.md) | misto: indice suplementar + runbooks canónicos da publicacao segura, da sessao administrativa, dos segredos/bootstrap, do backup/restore do license server e do kit de triagem/recepcao/desbloqueio/execucao operacional da F3.11 |
| Releases | [`06-releases/README.md`](06-releases/README.md), [`06-releases/RELEASE-SIGNING.md`](06-releases/RELEASE-SIGNING.md) | canónico para governanca de release |
| Lab | [`08-lab/README.md`](08-lab/README.md) (indice), [`08-lab/quick-start-lab.md`](08-lab/quick-start-lab.md) (sequencia minima); [`08-lab/guia-windows.md`](08-lab/guia-windows.md) so **legado** (ver classificacao) | suplementar / legado |
| Bloqueio | [`09-blocking/README.md`](09-blocking/README.md) | historico/preservado |
| Licencas | [`10-license-server/MANUAL-INSTALL.md`](10-license-server/MANUAL-INSTALL.md), [`10-license-server/MANUAL-USO-LICENCAS.md`](10-license-server/MANUAL-USO-LICENCAS.md) | canónico por area |
| Blacklists UT1 | [`11-blacklists/PLANO-BLACKLISTS-UT1.md`](11-blacklists/PLANO-BLACKLISTS-UT1.md), [`11-blacklists/DIRETRIZES-IMPLEMENTACAO.md`](11-blacklists/DIRETRIZES-IMPLEMENTACAO.md) | canónico da trilha F4 |
| Testes | [`tests/README.md`](tests/README.md), [`tests/test-matrix.md`](tests/test-matrix.md), [`tests/templates/f3-validation-campaign-report.md`](tests/templates/f3-validation-campaign-report.md) | canónico por area |
| Tutorial longo | [`tutorial/guia-completo-layer7.md`](tutorial/guia-completo-layer7.md) | preservado por compatibilidade; nao e SSOT de instalacao |

---

## O que e historico ou preservado

- Os documentos `00-` a `16-` na raiz continuam preservados como legado
  importante e contexto de origem.
- Alguns readmes curtos em `package/`, `src/`, `webgui/` e `scripts/`
  permanecem preservados para navegacao local, nao para governanca.
- A trilha antiga em `docs/07-prompts/` fica preservada, mas a continuidade
  oficial entre chats passa a viver no `CORTEX.md`.
- Documentos que ainda citam estados pre-V1, `.txz` ou `v0.x` nao devem ser
  usados como fonte primaria sem consultar antes a classificacao documental.

---

## Ordem sugerida de leitura

### Novo agente ou novo chat

1. [`../CORTEX.md`](../CORTEX.md)
2. [`README.md`](README.md)
3. [`02-roadmap/roadmap.md`](02-roadmap/roadmap.md)
4. [`02-roadmap/backlog.md`](02-roadmap/backlog.md)
5. [`02-roadmap/checklist-mestre.md`](02-roadmap/checklist-mestre.md)
6. [`00-overview/document-classification.md`](00-overview/document-classification.md)
7. [`00-overview/document-equivalence-map.md`](00-overview/document-equivalence-map.md)

### Mudanca tecnica em area especifica

1. Ler a base acima.
2. Ler os documentos canónicos da area.
3. Consultar changelog, ADR index e runbooks afectados.
4. So depois abrir documentos historicos ou legados da raiz.

### Instalacao ou operacao real

1. [`../CORTEX.md`](../CORTEX.md)
2. [`10-license-server/MANUAL-INSTALL.md`](10-license-server/MANUAL-INSTALL.md)
3. [`05-runbooks/README.md`](05-runbooks/README.md)
4. [`changelog/CHANGELOG.md`](changelog/CHANGELOG.md)

---

## Como usar esta documentacao

1. Use este ficheiro para localizar a fonte certa antes de ler “qualquer doc”.
2. Em caso de conflito, consulte a classificacao e o mapa de equivalencia.
3. Ao abrir uma nova fase, actualize primeiro `CORTEX`, roadmap, backlog e
   checklist mestre.
4. Ao tocar em distribuicao, builder, blacklists ou fallback, leia antes a
   arquitectura consolidada da F1 e os ADRs 0003 a 0006.
5. Ao tocar em licenciamento/activacao, leia tambem
   `01-architecture/f3-arquitetura-licenciamento-ativacao.md` e
   `01-architecture/f3-fingerprint-e-binding.md` e
   `01-architecture/f3-expiracao-revogacao-grace.md` e
   `01-architecture/f3-mutacao-admin-reemissao-guardrails.md` e
   `01-architecture/f3-emissao-reemissao-rastreabilidade.md` e
   `01-architecture/f3-validacao-manual-evidencias.md` e
   `01-architecture/f3-pack-operacional-validacao.md` e
   `01-architecture/f3-gate-fechamento-validacao.md` e
   `01-architecture/f3-fecho-operacional-restante.md` e, na trilha F3.11,
   `01-architecture/f3-11-access-enablement-package.md`,
   `01-architecture/f3-11-drift-registry.md`,
   `01-architecture/f3-11-external-input-request-package.md`,
   `01-architecture/f3-11-input-acceptance-matrix.md`,
   `01-architecture/f3-11-execution-master-register.md`,
   `01-architecture/f3-11-operational-decisions-ledger.md`,
   `01-architecture/f3-11-readiness-scorecard.md`,
   `01-architecture/f3-11-state-machine.md`,
   `01-architecture/f3-11-document-sync-protocol.md`,
   `01-architecture/f3-11-operational-responsibility-matrix.md`,
   `00-overview/f3-11-start-here.md`,
   `00-overview/f3-organizacao-local-e-fecho.md`,
   `01-architecture/f3-11-readiness-reopen-gate.md`,
   `00-overview/f3-11-document-traceability-map.md`,
   `05-runbooks/f3-11-input-triage-runbook.md`,
   `05-runbooks/f3-11-evidence-intake-template.md`,
   `05-runbooks/f3-11-cycle-report-template.md`,
   `05-runbooks/f3-11-cycle-closure-criteria.md` e
   `05-runbooks/f3-11-live-access-checklist.md`.
6. Ao alterar comportamento tecnico, actualize tambem as docs da area,
   changelog e manuais operacionais afectados.
7. Evite criar ficheiros novos sem necessidade; prefira consolidar.
