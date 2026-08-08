# Plano: Melhoria Total do Portal Admin de Licenças

| Campo | Valor |
|-------|-------|
| **ID** | `PORTAL-PLAN-001` |
| **Estado** | `ACTIVO` |
| **Criado** | `2026-08-08` |
| **Baseline** | portal visual **`0.0.1`** |
| **Alvo** | portal visual **`1.0.0`** (completo para operador único) |
| **Código** | `license-server/` |
| **Live** | `192.168.100.244:/opt/layer7-license` |
| **URL** | `https://license.systemup.inf.br` |

## 1. Objectivo

Tornar o painel administrativo **completo para gestão individual** das
licenças Layer7: ciclo de vida, suporte (rebind), renovação, auditoria e
check-ins visíveis, SKU coerente, SPA alinhada ao backend — **sem** abrir
escala comercial (MSP, self-service, multi-admin de vendas, billing).

Critérios de fecho = [`../OBJECTIVOS.md`](../OBJECTIVOS.md) § `1.0.0`.

## 2. Fora de escopo (explícito)

Até GO separado (ideias `FUTURA` em `IDEIAS.md`):

- portal cliente / MSP
- multi-admin com papéis
- faturação / alertas email em massa
- redesign de marca completo (pode ser pós-`1.0.0`)
- alterações ao pacote pfSense **excepto** se um bloco do portal exigir
  contrato novo no daemon (aí: bloco conjunto com gates)

## 3. Princípios de execução

1. Seguir [`../GOVERNANCE.md`](../GOVERNANCE.md) em todo bloco.
2. Um bloco pequeno por vez; docs + código no mesmo bloco.
3. Não tocar Zabbix/Grafana/Apache/MySQL/outras stacks no `244`.
4. Respeitar F2 (segurança) e F3 (guardrails; rebind **só** com workflow
   dedicado — nunca CRUD silencioso de `hardware_id`).
5. Versionamento visual: P0 → `0.1.0`; P1 por sub-blocos `0.2.0`…; fecho → `1.0.0`.
6. Actualizar `ACOES.md` / `ESTADO.md` / `CHANGELOG.md` em cada entrega.

## 4. Situação de partida (`0.0.1`)

Ver [`../ESTADO.md`](../ESTADO.md) e
[`../historico/2026-08-08-baseline-0.0.1.md`](../historico/2026-08-08-baseline-0.0.1.md).

Resumo:

- CRUD Dashboard / Licenças / Clientes
- Backend rico (audit, check-in, SKU) **sub-exposto** na UI
- Drift SPA Abril vs API Agosto
- Dados live: 5 clientes, 6 licenças `full`, 1 expirada efectiva

## 5. Arquitectura de entregas

```text
0.0.1  baseline documental
  │
  ├─ P0  →  0.1.0   fundação utilizável (deploy + UX + dashboard + SKU base)
  │
  ├─ P1a →  0.2.0   renovação + filtros avançados + ficha cliente leve
  ├─ P1b →  0.3.0   auditoria UI + check-ins UI
  ├─ P1c →  0.4.0   rebind governado (+ API)
  ├─ P1d →  0.5.0   pós-revogação (desrevogar ou substituir)
  │
  └─ P1e →  1.0.0   polish gates + docs + inventário SKU fechado
```

Números `0.2–0.5` são **guia**; podem juntar-se ou fatiar-se se o risco
o exigir — desde que `CHANGELOG` e `ACOES` reflitam o real.

---

## 6. Bloco P0 — Fundação (`0.1.0`)

**Objectivo:** painel fiável e alinhado ao código actual; operação diária
sem SSH para tarefas básicas.

| # | Item | IDEA | Notas |
|---|------|------|-------|
| P0.1 | Rebuild + redeploy frontend (e API se necessário) no `244` | IDEA-003 | Backup DB antes; smoke health + login |
| P0.2 | Mostrar versão visual na UI (sidebar/rodapé) | IDEA-014 | Fonte: constante alinhada a `VERSION.md` |
| P0.3 | Pós-criação: chave completa + botão copiar + link detalhe | IDEA-004 | Evitar chave só truncada |
| P0.4 | Lista licenças: copiar chave, badge Bound/Unbound, SKU legível | IDEA-005 | |
| P0.5 | Filtros: status, cliente, a expirar (ex. 30d), search HW parcial se barato | IDEA-005 | API já tem `customer_id` / search |
| P0.6 | Dashboard: cards “a expirar 30d” + “expiradas efectivas” + atalho | IDEA-006 | Extender `/api/dashboard` se preciso |
| P0.7 | Procedimento + acção controlada `full` → `base` no inventário | IDEA-007 | ADR-0025 T1; evidência em ACOES/ESTADO |

**Teste mínimo P0:** login; criar licença lab; copiar chave; filtros;
dashboard reflecte expiry; UI mostra `0.1.0`; health OK.

**Rollback P0:** imagens Docker anteriores + restore DB se migração de
dados SKU tiver sido aplicada.

**Risco:** médio no deploy (mitigar backup + smoke); baixo na UX.

---

## 7. Bloco P1 — Ciclo de vida completo

### P1a — Renovação e ficha (`0.2.0` guia)

| # | Item | IDEA |
|---|------|------|
| P1a.1 | Botão renovar (+30 / +90 / +365 dias) no detalhe | IDEA-008 |
| P1a.2 | Após renovar: oferta de download `.lic` se bindada | IDEA-008 |
| P1a.3 | Campos opcionais cliente: CNPJ / tags / nota estruturada leve | IDEA-013 |

### P1b — Visibilidade operacional (`0.3.0` guia)

| # | Item | IDEA |
|---|------|------|
| P1b.1 | Página **Auditoria** (lista `admin_audit_log`, filtros data/tipo) | IDEA-010 |
| P1b.2 | Secção check-ins no detalhe da licença (+ última vez) | IDEA-011 |
| P1b.3 | API read-only se ainda não existir endpoint admin para audit/check-ins | — |

### P1c — Rebind governado (`0.4.0` guia) — **gate humano**

| # | Item | IDEA |
|---|------|------|
| P1c.1 | Desenhar contrato API `POST /licenses/:id/rebind` (motivo obrigatório) | IDEA-009 |
| P1c.2 | UI com confirmação explícita + aviso sobre `.lic` antigo / grace | IDEA-009 |
| P1c.3 | Auditoria `license_rebound` com HW antigo/novo | IDEA-009 |
| P1c.4 | Doc em MANUAL-USO + runbook curto | — |

**Risco:** alto (dois artefactos válidos offline). Só avançar com GO no
chat. Alinhar a F3.2/F3.4; **não** rebind silencioso.

### P1d — Pós-revogação (`0.5.0` guia)

| # | Item | IDEA |
|---|------|------|
| P1d.1 | Escolher: **desrevogar** governado **ou** fluxo “criar substituta + arquivar” | IDEA-012 |
| P1d.2 | UI + auditoria + testes de política | IDEA-012 |

Preferência conservadora se houver dúvida: fluxo **substituir** (nova
chave) em vez de reactivar revogada — declarar a escolha em `ACOES.md`.

### P1e — Fecho `1.0.0`

| # | Item |
|---|------|
| P1e.1 | Percorrer critérios `OBJECTIVOS.md` § 1.0.0 |
| P1e.2 | Inventário live sem `full` órfão (ou legado explicitamente marcado) |
| P1e.3 | `VERSION.md` = `1.0.0`; changelog; ESTADO; plano → `CONCLUIDO` |
| P1e.4 | Entrada em `historico/` de fecho |

---

## 8. Ordem segura recomendada

1. **P0** (obrigatório primeiro — sem isto o resto assenta em SPA stale)
2. P1a (valor imediato na renovação)
3. P1b (visibilidade — dados já existem)
4. P1c (rebind — só com GO)
5. P1d
6. P1e

Não misturar P1c com outras mutações perigosas no mesmo deploy.

## 9. Matriz documental por tipo de mudança

| Mudança | Docs |
|---------|------|
| Qualquer bloco | `ACOES.md`, plano (progresso), `checklist.md` |
| Bump visual | `VERSION.md`, `CHANGELOG.md`, UI |
| Deploy live | `ESTADO.md` |
| Rebind / desrevogar | `MANUAL-USO-LICENCAS.md`, possivelmente ADR/emenda F3 |
| Contrato daemon | CORTEX + MANUAL-INSTALL + backlog/ADR conforme AGENTS |
| Ideia nova | `IDEIAS.md` antes de código |

## 10. Testes mínimos por família

| Família | Teste |
|---------|-------|
| Deploy | health, login, listagem |
| Licença | criar → copiar → (lab) activate se ambiente permitir |
| Renovação | expiry avança; audit event |
| Rebind | HW antigo falha activate; novo OK; audit |
| Auditoria | evento recente visível após acção |
| Check-in | com flag/appliance quando aplicável; senão evidência API |

## 11. Rollback global

- Imagens Docker tag/previous
- `restore-postgres.sh` se dados corrompidos
- Reverter commit do repo
- Nunca “fix” em produção sem entrada em `ACOES.md`

## 12. Progresso

| Bloco | Estado | Versão | Data |
|-------|--------|--------|------|
| Baseline docs | **FEITO** | `0.0.1` | 2026-08-08 |
| P0 | **FEITO** | `0.1.0` | 2026-08-08 |
| P1a | **FEITO** | `0.2.0` | 2026-08-08 |
| P1b | Pendente | → `0.3.0` | — |
| P1c | Pendente (GO) | → `0.4.0` | — |
| P1d | Pendente | → `0.5.0` | — |
| P1e / `1.0.0` | Pendente | → `1.0.0` | — |

Actualizar esta tabela a cada fecho de bloco.

## 13. Próxima acção imediata

Quando o operador autorizar implementação:

1. Executar **P0.1–P0.7** como primeiro bloco de código/deploy.
2. Bump para **`0.1.0`**.
3. Registar em `ACOES.md` + `ESTADO.md`.

Até lá: este plano permanece a SSOT de execução da trilha portal.
