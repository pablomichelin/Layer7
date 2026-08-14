# Runbooks

Operacao e rollback: [`../../10-RUNBOOK-OPERACIONAL-E-ROLLBACK.md`](../../10-RUNBOOK-OPERACIONAL-E-ROLLBACK.md).

Validacao em lab (inicio: *Gates oficiais F4*; build `.pkg`, `pkg add`, servico;
roteiros F4 no appliance **10a** / **10b** / **11**; na **11**, `force_dns` /
NAT, anti-QUIC opcional e cenário opcional multi-interface / VLAN para **BG-011**
/ teste **6.7**):
[`../04-package/validacao-lab.md`](../04-package/validacao-lab.md).

Indice da area **package** (lab + `MANUAL-INSTALL`): [`../04-package/README.md`](../04-package/README.md).

Indice do **laboratorio**: [`../08-lab/README.md`](../08-lab/README.md).

Quick start do lab: [`../08-lab/quick-start-lab.md`](../08-lab/quick-start-lab.md).

Deploy lab / GitHub Release (`.pkg`, `install.sh`; suplementar ao release oficial): [`../04-package/deploy-github-lab.md`](../04-package/deploy-github-lab.md), [`../../scripts/release/README.md`](../../scripts/release/README.md).

Seguranca da WebGUI do pfSense durante testes do pacote: [`pfsense-webgui-safety.md`](pfsense-webgui-safety.md).

Rollback do pacote Layer7: [`rollback.md`](rollback.md).

Anti-rollback de relógio (30.6 / ADR-0033 — recuperação N6, limites RR-4):
[`anti-rollback-relogio.md`](anti-rollback-relogio.md).

Subscrição de conteúdo / update de blacklists (30.10 — token Bearer, R-D/R-J):
[`content-subscription-update.md`](content-subscription-update.md).

Check-in default ON + migração / isolados (30.14 / BG-118 — N3, R-J):
[`check-in-migration-30.14.md`](check-in-migration-30.14.md).

Evidência operacional anti-pirataria em campo (BG-127 / GO `2026-08-14` —
GA2.6, GA2.7, GA3.7, GA4.8, GA5.9; ordem `.54`→`.254`):
[`evidencia-operacional-antipirataria-bg127.md`](evidencia-operacional-antipirataria-bg127.md).

Reposição do espelho público de conteúdo corrente (GA4.11 / `30.11` — rollback
comercial, sem tocar enforce):
[`content-mirror-rollback-ga4.11.md`](content-mirror-rollback-ga4.11.md).

Prep/fecho cut espelho anónimo (`30.11` — **CUT EXECUTADO** `20260812T011217Z`):
[`../09-blocking/prep-cut-30.11-espelho.md`](../09-blocking/prep-cut-30.11-espelho.md);
evidência [`../tests/evidence/20260812T011217Z-30.11-cut-mirror/`](../tests/evidence/20260812T011217Z-30.11-cut-mirror/).
404 anónimo **esperado** (P3-9 opção A; espelho legado / fallback;
**não** é canal de pacote nem motivo para GA4.11):
[`../09-blocking/nota-404-esperado-cut-30.11.md`](../09-blocking/nota-404-esperado-cut-30.11.md).

Coms GA4.12 (histórico; gate **N/A** — não emitir):
[`content-mirror-comms-ga4.12-draft.md`](content-mirror-comms-ga4.12-draft.md).

**P0-1 ACTIVO** — proibido deploy integral do HEAD sobre o `.244`. Serving
`30.11` versionado no git; freeze **não** encerrado:
[`bloqueio-deploy-integral-head-30.11.md`](bloqueio-deploy-integral-head-30.11.md).
Auditoria: [`../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md`](../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md).

Publicacao segura do license server (TLS, edge proxy, origin privado `8445`):
[`license-server-publicacao-segura.md`](license-server-publicacao-segura.md).

Autenticacao e sessao administrativa do license server (login, cookie seguro,
expiracao, logout e troubleshooting):
[`license-server-auth-sessao.md`](license-server-auth-sessao.md).

Segredos, ownership operacional e bootstrap administrativo do license server:
[`license-server-segredos-bootstrap.md`](license-server-segredos-bootstrap.md).

Backup/restore e recuperacao minima do PostgreSQL do license server:
[`license-server-backup-restore.md`](license-server-backup-restore.md).

Checklist live de desbloqueio da F3.11 (host, DB, admin, appliance e
inventario reais):
[`f3-11-live-access-checklist.md`](f3-11-live-access-checklist.md).

Runbook historico/compatibilidade de triagem de entrega dos cinco insumos
externos da F3.11; no estado corrente, usar apenas se drift novo reabrir
essa necessidade:
[`f3-11-input-triage-runbook.md`](f3-11-input-triage-runbook.md).

Template canónico de intake para registar recepcao, validacao e conclusao
de evidencia nova da F3.11:
[`f3-11-evidence-intake-template.md`](f3-11-evidence-intake-template.md).

Template canónico de ciclo operacional para consolidar cada rodada inteira
de recepcao, triagem, aceite/rejeicao e actualizacao do gate da F3.11:
[`f3-11-cycle-report-template.md`](f3-11-cycle-report-template.md).

Criterio canonico de fecho, invalidacao e numeracao de ciclos da F3.11:
[`f3-11-cycle-closure-criteria.md`](f3-11-cycle-closure-criteria.md).

Runbooks especificos por release entram aqui quando existirem.
