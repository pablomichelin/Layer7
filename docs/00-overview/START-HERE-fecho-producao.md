# 【FILAS FECHADAS】 START HERE — Arranque único (pós-fecho)

> **Visual:** as filas P0–J e IPv6 V0–V6 estão **FECHADAS**.  
> Este ficheiro é o **arranque de manutenção** — não um plano aberto.

**Arranque de manutenção** após o fecho das filas produção + IPv6. Colar só este
caminho num chat limpo **se o trabalho for manutenção / não Identity+MITM**:

```text
docs/00-overview/START-HERE-fecho-producao.md
```

**Trilha activa Identity + MITM Add-on** — arranque dedicado (não misturar):

```text
docs/00-overview/START-HERE-identity-mitm.md
```

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque de chat (**manutenção** pós-fecho) |
| [`START-HERE-identity-mitm.md`](START-HERE-identity-mitm.md) | Arranque da trilha **Identity + MITM** (ACTIVA) |
| [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md) | **Congelamento** das filas + mapa + como abrir planos novos |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional **vivo** |

**Não criar** outro `START-HERE-*.md` sem GO e registo no CORTEX/`docs/README.md`
(excepção registada: Identity+MITM).

---

## Estado actual

| Campo | Valor |
|-------|-------|
| Filas fecho P0–J + IPv6 V0–V6 | **FECHADAS** (`2026-08-05`) |
| Produção enforce / `latest` | **`1.9.8`** (alinhados) |
| SHA256 | `229639243fc31333251fa286690bf87db9f20b644039b857ca283d16501a99ec` |
| Rollback enforce | **`1.9.0`** |
| Passo residual nestas filas | **Nenhum** |
| Modo | Manutenção contínua / **novos planos com GO** |
| Organização docs (F6 H5) | **EXECUTADA** — legado em `docs/archive/` |
| Ressalvas | CE ADR-0022; BG-028 fase 0 |
| Última campanha lab | `20260805T162500Z-prod-align-two-client-1.9.8` **PASS** |

### Desambiguação — «12.x»

| Referência | Significado |
|------------|-------------|
| Passos **12.1–12.13** | Trilha IPv6 — **fechada** |
| test-matrix **12.1 / 12.2** | Blacklists UT1 — **outra coisa** |

---

## Leitura obrigatória (chat novo)

1. [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md)  
2. [`CORTEX.md`](../../CORTEX.md)  
3. [`AGENTS.md`](../../AGENTS.md)  
4. [`backlog.md`](../02-roadmap/backlog.md)  
5. Área do pedido (ver mapa no ESTADO-PRODUTO)

---

## Prompt — manutenção

```text
Contexto: fecho+IPv6 FECHADOS; produção 1.9.8.
Arranque: docs/00-overview/START-HERE-fecho-producao.md
Ler: ESTADO-PRODUTO-E-PLANOS-FECHADOS.md, CORTEX, AGENTS, backlog.
Regras: não reabrir filas fechadas sem GO; responder em português.
Tarefa: <manutenção>.
```

## Prompt — novo plano / integração

```text
Contexto: filas fecho+IPv6 FECHADAS (1.9.8).
Ler: docs/00-overview/ESTADO-PRODUTO-E-PLANOS-FECHADOS.md §6, CORTEX, AGENTS, backlog.
Proposta de novo plano: <nome>
Objectivo / impacto / risco / teste / rollback:
...
Não executar até GO e item de backlog. Responder em português.
```

---

## O que este arranque NÃO autoriza

- Reabrir P0–J ou IPv6 V0–V6 sem GO.  
- Tratar Analytics/SIEM pesado ou MITM HTTPS como “em falta” no fecho.  
- Confundir stubs/arquivo com planos activos (ver `docs/02-roadmap/README.md`).  
- Confundir 12.x IPv6 com test-matrix §12.

---

## Ligação rápida

| Documento | Papel |
|-----------|--------|
| [ESTADO-PRODUTO…](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md) | Fecho consolidado |
| [【FECHADO】 plano-ipv6](../archive/planos-fechados/plano-ipv6-completo.md) | Histórico IPv6 (arquivo) |
| [【FECHADO】 plano-fecho](../archive/planos-fechados/plano-fecho-producao-e-consolidacao.md) | Histórico P0–J (arquivo) |
| [Raiz legado](../archive/raiz-legado/README.md) | `00-`…`16-` arquivados |
| [MANUAL-INSTALL.md](../10-license-server/MANUAL-INSTALL.md) | Instalação |
| [handoff-chat-novo.md](handoff-chat-novo.md) | Chat esgotado |
