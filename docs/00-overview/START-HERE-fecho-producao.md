# START HERE — Arranque único (fecho + trilha IPv6)

**Este é o único ficheiro de arranque de chat** para o plano mestre e para a
trilha IPv6. Colar apenas o caminho deste ficheiro num chat limpo.

```text
docs/00-overview/START-HERE-fecho-producao.md
```

| Trilha | Estado | SSOT de execução |
|--------|--------|------------------|
| Fecho produção P0–J | **FECHADO** (`1.9.0`, `2026-08-05`) | [`plano-fecho-producao-e-consolidacao.md`](../02-roadmap/plano-fecho-producao-e-consolidacao.md) (histórico) |
| **IPv6 completo V0–V6** | **FECHADA** (`1.9.8`, GV7.4 PASS) | [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md) |

**Não criar** outros ficheiros `START-HERE-*.md` para esta fila — este é o único.

---

## Estado actual (verificar no CORTEX antes de executar)

| Campo | Valor |
|-------|-------|
| **Trilha IPv6** | **FECHADA** (V0–V6; 12.1–12.13 + GV7.4) |
| **Passo residual autorizado** | Nenhum nesta fila — manutenção contínua |
| **BG residual IPv6** | BG-078…BG-084 **concluídos** |
| Produção enforce | **`1.9.8`** (GV7.4 PASS `2026-08-05`) |
| Canal `latest` | **`1.9.8`** — alinhado; SHA256 `22963924…` |
| Rollback enforce | **`1.9.0`** |
| Plano fecho P0–J | **FECHADO** |
| ADR IPv6 | ADR-0024 — Opção A completa; GV7.4 promovido |
| Ressalva | CE físico ADR-0022; BG-028 fase 0 |
| Última evidência | `20260805T150500Z-gv7.4-promocao-1.9.8` — **GV7.4 PASS** |
| F6 / F7 (fecho) | F6 fechada (H5 diferido); F7 checklist + ADR-0023 fase 0 |

### Desambiguação — «12.x»

| Referência | Significado |
|------------|-------------|
| Passos **12.1–12.13** | Trilha IPv6 — **concluídos** (inclui GV7.4) |
| test-matrix **12.1 / 12.2** | Blacklists UT1 — **outra coisa** |

### Continuidade

1. Este ficheiro → `CORTEX.md` → manutenção / backlog geral
2. Histórico IPv6: [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md)
3. Gates: [`plano-gates-ipv6.md`](../09-blocking/plano-gates-ipv6.md)
4. ADR: [`ADR-0024`](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md)

---

## Prompt — manutenção (pós-fecho)

```text
Contexto: trilha IPv6 FECHADA; produção enforce 1.9.8. Arranque:
docs/00-overview/START-HERE-fecho-producao.md

Leitura: START-HERE, CORTEX, AGENTS, backlog.

Regras:
- Não reabrir P0–J nem V0–V6 sem GO.
- Trabalho novo só via backlog / GO explícito.
- Responder em português.

Tarefa: <descrever pedido de manutenção>.
```

---

## O que este arranque NÃO autoriza

- Criar outro `START-HERE-*.md`.
- Reabrir P0–J ou trilha IPv6 sem GO.
- Confundir 12.x IPv6 com test-matrix §12.

---

## Ligação rápida

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | **Único arranque de chat** |
| [plano-ipv6-completo.md](../02-roadmap/plano-ipv6-completo.md) | Histórico fila 12.x |
| [CORTEX.md](../../CORTEX.md) | SSOT estado |
| [ADR-0024](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md) | Decisão faseada |
| [handoff-chat-novo.md](handoff-chat-novo.md) | Chat esgotado |
