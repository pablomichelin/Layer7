# Changelog — Portal Admin de Licenças

Formato: Keep a Changelog (resumo). Versão = versão **visual** do portal
(ver `VERSION.md`), não `PORTVERSION` do pacote.

---

## [Unreleased]

- (vazio — alterações em curso registam-se aqui antes do bump)

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
[0.0.1]: #001---2026-08-08
