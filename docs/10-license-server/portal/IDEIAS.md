# Ideias — Portal Admin de Licenças

Estados: `ACEITE` | `FUTURA` | `DIFERIDA` | `REJEITADA`.  
Registar aqui **antes** de implementar. Ligar a plano/versão quando existir.

---

## Aceites (entrar no plano activo / versões 0.x)

| ID | Ideia | Origem | Ligação |
|----|-------|--------|---------|
| IDEA-001 | Completar o portal para **operador único** (não escala) | Chat `2026-08-08` | Plano melhoria total |
| IDEA-002 | Versionamento visual próprio (`0.0.1` baseline) + docs obrigatórias | Chat `2026-08-08` | `VERSION.md` |
| IDEA-003 | Redeploy SPA alinhada à API (fechar drift) | Análise `2026-08-08` | P0 → `0.1.0` |
| IDEA-004 | UX: chave completa + copiar após criar | Análise `2026-08-08` | P0 |
| IDEA-005 | Lista: Bound/Unbound, SKU, filtros a expirar / cliente | Análise `2026-08-08` | P0 |
| IDEA-006 | Dashboard: a expirar 30d + expiradas efectivas | Análise `2026-08-08` | P0 |
| IDEA-007 | Normalizar inventário `full` → `base` (política T1) | ADR-0025 + análise | P0 |
| IDEA-008 | Renovação rápida (+1 ano) | Análise `2026-08-08` | P1 |
| IDEA-009 | Workflow **rebind** (motivo + auditoria) | F3.4 reserva + análise | P1 |
| IDEA-010 | Página Auditoria (`admin_audit_log`) | Backend já grava | P1 |
| IDEA-011 | Check-ins no detalhe da licença | BG-077 / ADR-0021 | P1 |
| IDEA-012 | Desrevogar **ou** fluxo “substituir licença” | Análise `2026-08-08` | P1 |
| IDEA-013 | Ficha cliente mínima extra (CNPJ/tags/notas úteis) | Análise `2026-08-08` | P1 (leve) |
| IDEA-014 | Mostrar versão visual na sidebar/rodapé | Governação portal | P0 |

---

## Aceites pós-`1.0.0` (plano `PORTAL-PLAN-002`)

| ID | Ideia | Origem | Ligação |
|----|-------|--------|---------|
| IDEA-015 | Navegação Cliente↔Licença (clique linha, links cruzados) | Chat `2026-08-08` | Plano ficha 360 — **C0** |
| IDEA-016 | Ficha cliente 360: licenças ricas + nova licença no contexto | Chat `2026-08-08` | Plano ficha 360 — **C1** |

---

## Futuras (escala / vendas — **não** no plano actual)

| ID | Ideia | Nota |
|----|-------|------|
| IDEA-020 | Portal self-service do cliente | GO futuro |
| IDEA-021 | Portal MSP multi-cliente | GO futuro |
| IDEA-022 | Multi-admin + papéis | GO futuro |
| IDEA-023 | Integração faturação | GO futuro |
| IDEA-024 | Alertas email de expiração em massa | GO futuro |
| IDEA-025 | Quotas N appliances / seats por contrato | GO futuro |
| IDEA-026 | Link one-time de entrega de chave | GO futuro |

---

## Diferidas

| ID | Ideia | Motivo |
|----|-------|--------|
| IDEA-030 | Redesign visual completo (branding Systemup) | Depois da completude P0/P1; não bloquear ciclo de vida |
| IDEA-031 | 2FA no login admin | Desejável; não bloqueia `1.0.0` operador único se sessão F2 mantida |

---

## Rejeitadas (nesta fase)

| ID | Ideia | Motivo |
|----|-------|--------|
| IDEA-090 | Rebind silencioso via CRUD (`PUT hardware_id`) | Viola F3.4 / risco de dois `.lic` válidos sem governação |
| IDEA-091 | Apagar fisicamente licenças/histórico no painel | F2.4 exige arquivo lógico |
| IDEA-092 | Expor origin `8445` como URL humana | F2.1 — só `443` público |

---

## Como acrescentar

1. Novo `IDEA-XXX` com estado.
2. Se `ACEITE` → referenciar no plano activo e em `ACOES.md` quando
   implementada.
3. Não implementar `FUTURA`/`DIFERIDA` sem GO no chat + nota em `ACOES.md`.
