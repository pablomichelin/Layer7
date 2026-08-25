# Changelog — Portal Admin de Licenças

Formato: Keep a Changelog (resumo). Versão = versão **visual** do portal
(ver `VERSION.md`), não `PORTVERSION` do pacote.

---

## [Unreleased]

## [2.2.0] — 2026-08-22

### Added

- Página **Instalações**: lista e detalhe de caixas que enviaram o sinal
  de instalação/heartbeat (com ou sem serial)
- Dashboard: cartões vistas / sem serial / stale 7d
- Ficha da licença: bloco «esta caixa no inventário»
- Backend: `POST /api/license/install-ping` + `GET /api/installations`

### Notes

- Código no git; overlay live `.244` `20260823T022826Z`
- Pacote correspondente: `1.9.71`; cliente corrigido em `1.9.72`

---

## [2.1.0] — 2026-08-12

### Added

- Dashboard: fila e contagem de abuso multi-appliance (lookback 30d)
- Backend: `evaluateMultiApplianceAbuse` + query em activate/check-in logs
- Anti falso positivo para rebind autorizado (`license_rebound`)
- Política explícita `alert_only` (decisão 7 / 30.15) — sem `max_activations`

### Notes

- Código no git; **deploy live não** incluído neste bump

---

## [2.0.0] — 2026-08-08

### Added

- Gestão de técnicos (owner cria utilizadores com permissões seleccionáveis)
- RBAC: `requirePermission` na API + gates no menu/botões
- Página Utilizadores; catálogo de permissões fechado

---

## [1.9.0] — 2026-08-08

### Added

- 2FA TOTP: setup em Segurança, desafio no login, enable/disable

---

## [1.8.0] — 2026-08-08

### Added

- Busca global na sidebar (`GET /api/search`)
- Export CSV da lista de licenças filtrada
- Clientes recentes (localStorage)

---

## [1.7.0] — 2026-08-08

### Added

- Timeline de auditoria na ficha do cliente (`customer_id` / `license_id`)
- Banners renew/rebind com antes → depois

---

## [1.6.0] — 2026-08-08

### Added

- Dashboard **Precisa de acção** (expirar, expiradas vinculadas, por activar, check-in stale)

---

## [1.5.0] — 2026-08-08

### Added

- Lista: último check-in, notas, filtro sem check-in > N dias

---

## [1.4.0] — 2026-08-08

### Added

- Pacote de entrega copiável
- Confirmações com resumo (cliente/SKU/expiry/equipamento)

---

## [1.3.2] — 2026-08-08

### Changed

- Nomenclatura UI: Bound/Unbound → **Vinculada** / **Por activar** (coluna Equipamento)
- Rebind → **Trocar equipamento**; mensagens sem jargão técnico

---

## [1.3.1] — 2026-08-08

### Fixed

- Off-by-one de datas de expiração em fuso BRT (`formatCalendarDate`)
- Download `.lic` alinhado ao guard (só active+bound)
- Auditoria de update: falso positivo em `expiry` (Date vs string)
- PUT bloqueado em licença revogada
- Race de busca (debounce + AbortController)
- Truncamento do select de clientes (busca no servidor)
- Texto de arquivar na lista alinhado à API
- Voltar do detalhe com `from_customer`; hint SKU em bound

### Changed

- Compose: `TZ=${TZ:-UTC}` em `db` e `api` (alinha `CURRENT_DATE` SQL)
- Checklist portal: nota `TZ=UTC` no compose
- Ciclo Editar preserva `from_customer`; reload pós-mutação via epoch+abort
- Banner rebind só oferece download se `canDownload`

---

## [1.3.0] — 2026-08-08

### Added

- Lista clientes operacional (PORTAL-PLAN-002 / C2)
- Colunas CNPJ, tags, activas/total; busca alargada
- Fecho de `PORTAL-PLAN-002`

### Fixed

- Lista Clientes: clique na linha / nome → ficha (navegação C0)

---

## [1.2.0] — 2026-08-08

### Added

- Ficha cliente 360 (PORTAL-PLAN-002 / C1 / IDEA-016)
- Contadores na ficha; tabela rica (chave completa, SKU, Bound)
- CTA Nova licença com `?customer_id=` pré-preenchido

---

## [1.1.0] — 2026-08-08

### Added

- Navegação cruzada Cliente↔Licença (PORTAL-PLAN-002 / C0 / IDEA-015)
- Clique na linha nas listas de clientes e licenças
- Links: nome do cliente na lista/detalhe de licenças; licenças na ficha do cliente

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
