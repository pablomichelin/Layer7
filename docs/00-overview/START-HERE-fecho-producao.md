# START HERE — Arranque único (fecho + trilha IPv6)

**Este é o único ficheiro de arranque de chat** para o plano mestre e para a
trilha IPv6. Colar apenas o caminho deste ficheiro num chat limpo.

```text
docs/00-overview/START-HERE-fecho-producao.md
```

| Trilha | Estado | SSOT de execução |
|--------|--------|------------------|
| Fecho produção P0–J | **FECHADO** (`1.9.0`, `2026-08-05`) | [`plano-fecho-producao-e-consolidacao.md`](../02-roadmap/plano-fecho-producao-e-consolidacao.md) (histórico) |
| **IPv6 completo V0–V6** | Núcleo + V5 **FECHADOS**; GV7.4 promoção PENDENTE | [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md) |

**Não criar** outros ficheiros `START-HERE-*.md` para esta fila — este é o único.

---

## Estado actual (verificar no CORTEX antes de executar)

| Campo | Valor |
|-------|-------|
| **Trilha IPv6** | Núcleo + V5 **FECHADOS** (12.1–12.11) |
| **Passo residual autorizado** | **Promoção enforce** com GO (GV7.4) |
| **BG residual** | BG-083 **concluído**; BG-084 concluído |
| Produção enforce | **`1.9.0`** (inalterada — GV7.4 PENDENTE) |
| Candidato lab / `latest` | **`1.9.8`**; SHA256 `22963924…` |
| Plano fecho P0–J | **FECHADO** |
| ADR IPv6 | ADR-0024 — Opção A **12.10+12.11 CONCLUÍDOS** |
| Ressalva | Sem promoção enforce automática; CE físico ADR-0022 |
| Última evidência GV | `20260805T143000Z-gv5-12.11-smoke-1.9.8` — **GV5 PASS** |
| F6 / F7 (fecho) | F6 fechada (H5 diferido); F7 checklist + ADR-0023 fase 0 |

### Desambiguação — «12.x»

| Referência | Significado |
|------------|-------------|
| Passos **12.1–12.11** | Núcleo + V5 dual-stack — **concluídos** |
| Passos **12.12–12.13** | GV6 + GV7 fecho — **concluídos** (exceto GV7.4) |
| test-matrix **12.1 / 12.2** | Blacklists UT1 — **outra coisa** |

### Continuidade

1. Este ficheiro → `CORTEX.md` (Trilha IPv6) → [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md)
2. Mapa: [`f4-ipv6-mapa-rastreabilidade.md`](../01-architecture/f4-ipv6-mapa-rastreabilidade.md)
3. Gates: [`plano-gates-ipv6.md`](../09-blocking/plano-gates-ipv6.md)
4. ADR: [`ADR-0024`](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md)

Produção `1.9.0` mantém-se até GO de promoção.

---

## Prompt — residual GV7.4 (promoção)

```text
Contexto: V5/12.11 FECHADO (1.9.8). Arranque:
docs/00-overview/START-HERE-fecho-producao.md

Leitura: START-HERE, CORTEX, AGENTS, plano-ipv6-completo.md, mapa, gates, ADR-0024.

Regras:
- Executar SOMENTE promoção enforce (GV7.4) com GO humano explícito.
- Não regressão; produção permanece 1.9.0 até esse GO.
- Responder em português.

Tarefa: executa SOMENTE promoção enforce se houver GO. Não avances automaticamente.
```

---

## O que este arranque NÃO autoriza

- Criar outro `START-HERE-*.md`.
- Reabrir P0–J.
- Promover produção sem GO (GV7.4).
- Confundir 12.x IPv6 com test-matrix §12.

---

## Ligação rápida

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | **Único arranque de chat** |
| [plano-ipv6-completo.md](../02-roadmap/plano-ipv6-completo.md) | Fila 12.x |
| [CORTEX.md](../../CORTEX.md) | SSOT estado |
| [ADR-0024](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md) | Decisão faseada |
| [handoff-chat-novo.md](handoff-chat-novo.md) | Chat esgotado |
