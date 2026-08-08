# Changelog — Portal Admin de Licenças

Formato: Keep a Changelog (resumo). Versão = versão **visual** do portal
(ver `VERSION.md`), não `PORTVERSION` do pacote.

---

## [Unreleased]

- (vazio)

---

## [1.0.0] — 2026-08-08

### Added

- Fecho do plano `PORTAL-PLAN-001` (operador único completo)
- Critérios `OBJECTIVOS.md` § 1.0.0 todos satisfeitos
- Entrada `historico/2026-08-08-fecho-1.0.0.md`

### Notes

- Sem features novas além do fecho documental/verificação P1e
- Escala/MSP/billing permanece `FUTURA`

---

## [0.5.0] — 2026-08-08

### Added

- Pós-revogação: `POST /licenses/:id/replace` (nova chave + arquiva a revogada)
- UI **Substituir licença** no detalhe com motivo e aviso de .lic antigo
- Evento de auditoria `license_replaced`

### Decision

- **Não** implementar desrevogar — fluxo conservador “substituir” (IDEA-012 / P1d)

### Changed

- `MANUAL-USO-LICENCAS.md` §7 — política oficial de substituição pós-revogação

---

## [0.4.0] — 2026-08-08

### Added

- Rebind governado: `POST /licenses/:id/rebind` (modos `unbind` / `set`)
- UI no detalhe com motivo obrigatório e aviso sobre `.lic` antigo / grace
- Evento de auditoria `license_rebound`

### Changed

- `MANUAL-USO-LICENCAS.md` §5.6 — política oficial de rebind
- Governação: regra de executar blocos **em ordem** sem saltar funções

---

## [0.3.0] — 2026-08-08

### Added

- Página Auditoria com filtros (`event_type`, `result`, busca)
- API `GET /api/audit`
- Secção check-ins + último check-in no detalhe da licença

---

## [0.2.0] — 2026-08-08

### Added

- Renovação rápida no detalhe (+30 / +90 / +365 dias) via `POST /licenses/:id/renew`
- Banner pós-renovação com oferta de download `.lic` se a licença estiver bound
- Campos opcionais de cliente: CNPJ e tags

### Notes

- Licenças revogadas não renovam (409); usar fluxo P1d no futuro
- Base da renovação: `max(hoje, expiry actual)`

---

## [0.1.0] — 2026-08-08

### Added

- Versão visual na sidebar (`v0.1.0`)
- Ecrã pós-criação com chave completa + copiar + atalho ao detalhe
- Lista: chave completa, badge Bound/Unbound, SKU legível
- Filtros: cliente, bound, a expirar (30/60/90 dias); search inclui hardware_id
- Dashboard: card “A expirar (30d)” + atalhos para listas filtradas
- API: `bound`, `expiring_within_days` na listagem; `expiring_30d` no dashboard

### Changed

- Inventário live: `features=full` normalizado para `base` (ADR-0025 T1)
- Redeploy SPA + API no host `244` (fecha drift de imagens)

---

## [0.0.1] — 2026-08-08

### Formalizado

- Baseline documental da trilha portal (`docs/10-license-server/portal/`)
- Governação, objectivos, ideias, acções, estado e checklist
- Plano activo de melhoria total do portal (operador único; sem escala)

### Estado funcional herdado (já em produção antes desta versão)

- React SPA: Dashboard, Licenças, Clientes (CRUD + revogar/arquivar)
- API Node: activate, check-in, licenses, customers, dashboard, auth sessão
- PostgreSQL: clientes, licenças, activations_log, admin_audit_log, check_ins_log
- Deploy Docker Compose em `192.168.100.244` atrás de ISPConfig

### Limitações conhecidas nesta baseline

- SPA live desactualizada face à API (drift Abril vs Agosto 2026)
- Sem UI de auditoria / check-ins
- Sem workflow de rebind
- Sem renovação rápida / UX de cópia de chave
- Inventário live ainda com `features=full` (legado)
- Sem versão visual mostrada na UI

---

[Unreleased]: #unreleased
[0.1.0]: #010---2026-08-08
[0.0.1]: #001---2026-08-08
