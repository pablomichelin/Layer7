# Acções — Portal Admin de Licenças

Diário obrigatório. Entrada por bloco (mesmo que só documental).  
Mais recente no topo.

---

## 2026-08-08 — P1d Substituir `0.5.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.5.0` |
| Objectivo | Pós-revogação via substituição (nova chave + arquivar) |
| Impacto | API/UI replace; MANUAL-USO §7.1; decisão: sem desrevogar |
| Risco | Médio (chave nova + .lic antigo offline); mitigado por aviso + audit |
| Teste | license-replace-policy; health; UI substituir |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-_t9yOdDK.js`; build web `0.5.0`; `.env` restaurado pós-rsync |

### Decisão

- IDEA-012: escolhido fluxo **substituir** (não desrevogar).

### Incidente

- `rsync --delete` apagou `.env`; API em crash-loop; restore do backup
  `20260414T105834Z` + cópia fresca `20260808T220404Z`. Dados Postgres OK
  (volume). Regra: **sempre** `--exclude .env` no rsync.

---

## 2026-08-08 — P1c Rebind `0.4.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.4.0` |
| Objectivo | Workflow rebind com motivo + auditoria (GO humano) |
| Impacto | API/UI rebind; MANUAL-USO §5.6; risco .lic antigo explícito |
| Risco | Alto residual offline (grace); mitigado por aviso + audit |
| Teste | license-rebind-policy; health; UI rebind |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-C1foUQne.js`; build web `0.4.0`; nginx restarted |

---

## 2026-08-08 — P1b Auditoria `0.3.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.3.0` |
| Objectivo | UI/API de auditoria e check-ins visíveis |
| Impacto | nova rota admin `/api/audit`; detalhe licença |
| Risco | Baixo (read-only) |
| Teste | parseAuditListQuery; health; página /audit |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-haIeulBq.js`; `/audit` no ar |

---

## 2026-08-08 — P1a Renovação `0.2.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.2.0` |
| Objectivo | Renovação rápida + ficha cliente (CNPJ/tags) |
| Impacto | API renew; UI detalhe; schema customers.cnpj/tags |
| Risco | Baixo; `.lic` antigo pode coexistir até reemissão/download |
| Teste | `license-renew-policy` + crud-validation; health pós-deploy |
| Rollback | imagens anteriores; colunas novas são aditivas |
| Resultado | **FEITO** — health OK após restart nginx; SPA `index-XzglZKGO.js`; colunas cnpj/tags OK |

---

## 2026-08-08 — P0 Fundação `0.1.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.1.0` |
| Objectivo | Fechar drift SPA/API, UX chave/filtros/dashboard expiry, normalizar SKU `full`→`base` |
| Impacto | `license-server/` frontend+API; live `244`; inventário features |
| Risco | Médio no deploy (mitigado backup); baixo na UX; `.lic` antigos com `full` podem coexistir até reemissão |
| Teste | testes `crud-validation`; health; login; dashboard `expiring_30d`; lista filtros |
| Rollback | imagens Docker anteriores + restore Postgres se necessário |
| Resultado | **FEITO** — deploy live PASS; health OK; SPA nova; `full`→`base` (6 rows) |

### Notas

- Bloco P0 do `PORTAL-PLAN-001`
- Sem escala/MSP

---

## 2026-08-08 — Baseline documental `0.0.1`

| Campo | Valor |
|-------|-------|
| Tipo | Documentação / governação |
| Versão | `0.0.1` (baseline formal) |
| Objectivo | Criar organização documental da trilha portal + plano de melhoria total (operador único) |
| Impacto | Docs em `docs/10-license-server/portal/`; ligações CORTEX/docs/AGENTS; sem alteração de código runtime |
| Risco | Baixo (só docs); evitar conflito com SSOT global — hierarquia declarada em GOVERNANCE |
| Teste | Leitura cruzada dos índices; links relativos; versão `0.0.1` coerente |
| Rollback | Remover/reverter commits dos ficheiros da trilha |
| Resultado | **FEITO** — estrutura criada; plano activo de melhoria total aberto |

### Ficheiros tocados (bloco)

- `docs/10-license-server/README.md`
- `docs/10-license-server/portal/**` (governação + plano + histórico)
- `license-server/README.md`
- Ligações em `CORTEX.md`, `docs/README.md`, `document-classification.md`,
  `AGENTS.md`, `docs/02-roadmap/README.md`

### Decisões registadas

- Versão visual do portal começa em **0.0.1**
- Escopo imediato: **completude para operador único** (sem escala/vendas)
- Escala/MSP/billing → `IDEIAS.md` como `FUTURA`

---

## Modelo de entrada futura

```markdown
## YYYY-MM-DD — título curto

| Campo | Valor |
|-------|-------|
| Tipo | código / docs / deploy |
| Versão | x.y.z (ou Unreleased) |
| Objectivo | … |
| Impacto | … |
| Risco | … |
| Teste | … |
| Rollback | … |
| Resultado | FEITO / PARCIAL / BLOQUEADO |

### Notas
…
```
