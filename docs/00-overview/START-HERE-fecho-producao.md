# START HERE — Arranque único (fecho + trilha IPv6)

**Este é o único ficheiro de arranque de chat** para o plano mestre e para a
trilha IPv6. Colar apenas o caminho deste ficheiro num chat limpo.

```text
docs/00-overview/START-HERE-fecho-producao.md
```

| Trilha | Estado | SSOT de execução |
|--------|--------|------------------|
| Fecho produção P0–J | **FECHADO** (`1.9.0`, `2026-08-05`) | [`plano-fecho-producao-e-consolidacao.md`](../02-roadmap/plano-fecho-producao-e-consolidacao.md) (histórico) |
| **IPv6 completo V0–V6** | Núcleo **FECHADO**; **12.10 CONCLUÍDO**; **12.11** residual; GV7.4 PENDENTE | [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md) |

**Não criar** outros ficheiros `START-HERE-*.md` para esta fila — este é o único.

---

## Estado actual (verificar no CORTEX antes de executar)

| Campo | Valor |
|-------|-------|
| **Trilha IPv6** | Núcleo FECHADO; V5 parcial (12.10 done) |
| **Passo residual autorizado** | **12.11** com GO **ou** promoção enforce com GO |
| **BG residual** | BG-083 parcial (12.11); BG-084 concluído |
| Produção enforce | **`1.9.0`** (inalterada — GV7.4 PENDENTE) |
| Candidato lab / `latest` | **`1.9.7`** (DNS `rdr inet6` / AAAA) |
| Plano fecho P0–J | **FECHADO** |
| ADR IPv6 | ADR-0024 — **GO Opção A** 12.10; 12.11 pendente |
| Ressalva | Block page HTTP + VIP Unbound ACL ainda IPv4-only (12.11) |
| Última evidência GV | GV7 fecho; 12.10 código em `1.9.7` |
| F6 / F7 (fecho) | F6 fechada (H5 diferido); F7 checklist + ADR-0023 fase 0 |

### Desambiguação — «12.x»

| Referência | Significado |
|------------|-------------|
| Passos **12.1–12.10** | Núcleo + DNS force dual-stack — **concluídos** |
| Passo **12.11** | HTTP portal `rdr inet6` + VIP ACL v6 — **residual** |
| Passos **12.12–12.13** | GV6 + GV7 fecho — **concluídos** |
| test-matrix **12.1 / 12.2** | Blacklists UT1 — **outra coisa** |

### Continuidade

1. Este ficheiro → `CORTEX.md` (Trilha IPv6) → [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md)
2. Mapa: [`f4-ipv6-mapa-rastreabilidade.md`](../01-architecture/f4-ipv6-mapa-rastreabilidade.md)
3. Gates: [`plano-gates-ipv6.md`](../09-blocking/plano-gates-ipv6.md)
4. ADR: [`ADR-0024`](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md)

Produção `1.9.0` mantém-se até GO de promoção.

---

## Prompt — residual 12.11

```text
Contexto: residual V5 após 12.10. Arranque:
docs/00-overview/START-HERE-fecho-producao.md

Leitura: START-HERE, CORTEX, AGENTS, plano-ipv6-completo.md, mapa, gates, ADR-0024.

Regras:
- Executar SOMENTE 12.11 (HTTP rdr inet6 + VIP ACL v6) com GO, OU promoção enforce com GO.
- Não regressão IPv4; run-local.sh se houver código.
- Produção enforce permanece 1.9.0 até GO promoção.
- Responder em português.

Tarefa: executa SOMENTE <12.11|promoção>. Não avances automaticamente.
```

---

## O que este arranque NÃO autoriza

- Criar outro `START-HERE-*.md`.
- Reabrir P0–J.
- Promover produção sem GO (GV7.4).
- Afirmar «IPv6 completo comercial» antes de 12.11 PASS.
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
