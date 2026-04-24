# Runbooks

Operacao e rollback: [`../../10-RUNBOOK-OPERACIONAL-E-ROLLBACK.md`](../../10-RUNBOOK-OPERACIONAL-E-ROLLBACK.md).

Validacao em lab (inicio: *Gates oficiais F4*; build `.pkg`, `pkg add`, servico;
roteiros F4 no appliance **10a** / **10b** / **11**; na **11**, cenário opcional
multi-interface / VLAN para **BG-011** / teste **6.7**):
[`../04-package/validacao-lab.md`](../04-package/validacao-lab.md).

Indice da area **package** (lab + `MANUAL-INSTALL`): [`../04-package/README.md`](../04-package/README.md).

Indice do **laboratorio**: [`../08-lab/README.md`](../08-lab/README.md).

Quick start do lab: [`../08-lab/quick-start-lab.md`](../08-lab/quick-start-lab.md).

Deploy lab / GitHub Release (`.pkg`, `install.sh`; suplementar ao release oficial): [`../04-package/deploy-github-lab.md`](../04-package/deploy-github-lab.md), [`../../scripts/release/README.md`](../../scripts/release/README.md).

Seguranca da WebGUI do pfSense durante testes do pacote: [`pfsense-webgui-safety.md`](pfsense-webgui-safety.md).

Rollback do pacote Layer7: [`rollback.md`](rollback.md).

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
