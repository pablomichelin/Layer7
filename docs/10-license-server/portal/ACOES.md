# Acções — Portal Admin de Licenças

Diário obrigatório. Entrada por bloco (mesmo que só documental).  
Mais recente no topo.

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
