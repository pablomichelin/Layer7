# START HERE — Arranque único (fecho + trilha activa)

**Este é o único ficheiro de arranque de chat** para o plano mestre e para a
trilha activa. Colar apenas o caminho deste ficheiro num chat limpo.

```text
docs/00-overview/START-HERE-fecho-producao.md
```

| Trilha | Estado | SSOT de execução |
|--------|--------|------------------|
| Fecho produção P0–J | **FECHADO** (`1.9.0`, `2026-08-05`) | [`plano-fecho-producao-e-consolidacao.md`](../02-roadmap/plano-fecho-producao-e-consolidacao.md) (histórico) |
| **IPv6 completo V0–V6** | **ABERTA** — passo **12.2** (V0) | [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md) |

**Não criar** outros ficheiros `START-HERE-*.md` para esta fila — este é o único.

---

## Estado actual (verificar no CORTEX antes de executar)

| Campo | Valor |
|-------|-------|
| **Trilha activa** | **IPv6** — Ondas V0–V6 |
| **Passo autorizado** | **12.2** (Onda V0 — banner GUI + `pf-enforcement.md`) |
| **BG activo** | BG-078 (em execução; 12.1 concluído) |
| Produção enforce | **`1.9.0`** (inalterada até GV7 + GO humano IPv6) |
| Candidato lab | `1.9.0` (+ PORTREVISION por onda) |
| Plano fecho P0–J | **FECHADO** |
| ADR IPv6 | ADR-0024 **publicado e aceite** (implementação por ondas) |
| Próximo gate | **GV0.3** (banner GUI) → **GV0 completo** |
| F6 / F7 (fecho) | F6 fechada (H5 diferido); F7 checklist + ADR-0023 fase 0 |

### Desambiguação obrigatória — «12.x»

Os passos da trilha IPv6 chamam-se **12.1 … 12.13** (continuação do plano
mestre após 11.1).

**Não confundir** com `docs/tests/test-matrix.md` secção **§12** (testes
**12.1 / 12.2** de blacklists F4.2 — já PASS na Onda D).

| Referência | Significado |
|------------|-------------|
| Passo **12.1** (este START-HERE / CORTEX) | Trilha IPv6, Onda V0 — **concluído** |
| Passo **12.2** (autorizado) | Banner GUI Diagnostics + `pf-enforcement.md` |
| test-matrix **12.1 / 12.2** | Blacklists UT1 (F4.2) — **outra coisa** |

Mensagens de commit da trilha: `trilha-ipv6/12.x: …`

### Continuidade

1. Ler **este ficheiro** → `CORTEX.md` (secção *Trilha IPv6*) → passo actual em
   [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md) secção 4.
2. Mapa código: [`f4-ipv6-mapa-rastreabilidade.md`](../01-architecture/f4-ipv6-mapa-rastreabilidade.md)
3. Gates: [`plano-gates-ipv6.md`](../09-blocking/plano-gates-ipv6.md)
4. Decisão: [`ADR-0024`](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md)

O fecho P0–J **não foi reaberto**. Produção `1.9.0` mantém-se até GV7.

---

## Antes de colar no chat

1. `git status` limpo ou mudanças conscientes commitadas.
2. Confirmar passo no **CORTEX** (não assumir memória de chat anterior).
3. **Não regressão IPv4:** cada bloco com código exige `tests/run-local.sh` PASS.
4. Modo por onda (secção abaixo): V0 pode coordenador+workers; V1–V6 agente único.
5. Pedir **só o passo 12.x autorizado** — nunca «implementa IPv6 tudo».
6. **Antes de V1 (código PF):** salvaguardas IPv6 do mapa (NDP/ICMPv6,
   `localsubnets`, exclusões `fe80::/10`, etc.) têm de estar no desenho.

---

## Modo por onda (trilha IPv6)

| Onda | Passos | Modo | Multitarefa |
|------|--------|------|-------------|
| V0 | 12.1–12.2 | Coordenador + workers docs | Sim (ficheiros disjuntos) |
| V1 | 12.3 | Agente único | Não (`layer7.inc`) |
| V2–V3 | 12.4–12.8 | Agente único | Não (`src/layer7d/*`) |
| V4 | 12.9 | Agente único | Não |
| V5 | 12.10–12.11 | Agente único + **gate humano** | Não |
| V6 | 12.12–12.13 | Agente único + release | Não |

Receitas detalhadas: [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md) §3.

---

## Prompt — chat limpo (trilha activa IPv6)

Copia na **primeira mensagem** do chat novo. Ajusta só `<12.x>` se o CORTEX
já tiver avançado.

```text
Contexto: executo a trilha activa do Layer7. Arranque único:
docs/00-overview/START-HERE-fecho-producao.md

Leitura obrigatória (nesta ordem):
1. docs/00-overview/START-HERE-fecho-producao.md
2. CORTEX.md (secção Trilha IPv6)
3. AGENTS.md
4. docs/02-roadmap/plano-ipv6-completo.md
5. docs/01-architecture/f4-ipv6-mapa-rastreabilidade.md
6. docs/09-blocking/plano-gates-ipv6.md
7. docs/03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md

Regras absolutas:
- Executar SOMENTE o passo 12.x autorizado no CORTEX (agora: 12.2).
- Não confundir passo 12.x da trilha IPv6 com test-matrix §12 (blacklists F4.2).
- Não reabrir Ondas P0–J do fecho; produção enforce permanece 1.9.0 até GV7.
- Não regressão IPv4: run-local.sh (+ smoke IPv4) quando houver código.
- Actualizar mapa M-xx + CORTEX + changelog no mesmo bloco.
- PORTREVISION + release GitHub só se o passo exigir .pkg no appliance.
- Multitarefa só em V0 com ficheiros disjuntos; V1–V6 agente único.
- V1+ exige salvaguardas NDP/ICMPv6/localsubnets/endereços especiais (mapa §8).
- V5 (NAT/DNS v6) proibido sem decisão humana na ADR-0024.
- Responder em português. Objectivo, impacto, risco, teste, rollback em cada entrega.

Estado (confirmar no repo):
- Branch: main
- Passo actual: 12.2
- Onda: V0
- BG: BG-078
- Candidato lab: 1.9.0
- Produção enforce: 1.9.0
- Rollback imediato: 1.8.11_69

Tarefa deste chat:
Executa SOMENTE o passo <12.x> do plano-ipv6-completo.md.
No fim: (1) o que fizeste, (2) evidência/teste/gate, (3) mapa M-xx se aplicável,
(4) CORTEX actualizado, (5) próximo passo autorizado.
Não avances automaticamente para o passo seguinte sem eu pedir.
```

---

## Prompt — COORDENADOR (só Onda V0, se multitarefa)

```text
Contexto: COORDENADOR da trilha IPv6 (Onda V0). Arranque:
docs/00-overview/START-HERE-fecho-producao.md

Leitura: START-HERE-fecho-producao.md, CORTEX, AGENTS, plano-ipv6-completo.md,
ADR-0024, f4-ipv6-mapa-rastreabilidade.md.

Regras:
- Único agente que edita: CORTEX.md, backlog.md, CHANGELOG.md, índice ADR.
- Workers só com ficheiros disjuntos (ex.: mapa ∥ GUI strings ∥ matriz-limitacoes).
- Integrar num commit de fecho do passo; marcar GV0 no CORTEX quando V0 completa.
- Não avançar para V1 sem eu pedir.
- Responder em português.

Tarefa: orquestrar passo <12.1|12.2>.
```

---

## Prompt — WORKER (só se o coordenador autorizar)

```text
És WORKER da trilha IPv6 (arranque START-HERE-fecho-producao.md).
Onda: V0. Passo: <12.x>.
Ficheiros que PODES editar: <lista explícita do coordenador>.
Ficheiros PROIBIDOS: CORTEX.md, backlog.md, CHANGELOG.md, MANUAL-INSTALL.md,
Makefile, layer7.inc, src/layer7d/* (salvo inclusão explícita).
Não cries passos novos. Não toques no appliance.
Entrega: diff resumido + texto para o coordenador gravar no CORTEX.
Responde em português.
```

---

## O que este arranque NÃO autoriza

- Criar outro `START-HERE-*.md` para IPv6 ou fecho (usar **só este**).
- Reabrir Ondas P0–J do plano de fecho.
- Promover produção enforce sem GV7 + GO humano.
- Implementar V2 (daemon) antes de V1 (PF scoped) sem emenda ADR-0024.
- V5 (NAT/DNS v6) sem decisão humana explícita.
- Paralelizar `layer7.inc` e `src/layer7d/*`.
- Confundir passos 12.x IPv6 com test-matrix §12 (blacklists).
- «Consolidar / implementar IPv6 tudo» num único PR.

---

## Sequência da trilha activa (resumo)

| Passo | Onda | Objectivo |
|-------|------|-----------|
| **12.1** | V0 | ADR/índices/matriz/mapa vivo — **CONCLUÍDO** |
| **12.2** | V0 | Banner GUI Diagnostics + `pf-enforcement.md` — **actual** |
| 12.3 | V1 | Paridade PF `inet6` scoped (REV-018) |
| 12.4–12.5 | V2 | Captura + nDPI IPv6 |
| 12.6–12.8 | V3 | Policy / enforce / allowlist v6 |
| 12.9 | V4 | GUI + validação IPv6 |
| 12.10–12.11 | V5 | DNS/NAT/block page v6 **ou** exclusão ADR |
| 12.12–12.13 | V6 | Gates lab + release (sugestão `1.10.0`) |

Detalhe, versionamento e STOP: [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md).

---

## Histórico — fecho produção (referência)

O plano P0–J fechou em `2026-08-05` com produção **`1.9.0`**. A trilha IPv6
foi aberta em governança em `2026-08-04` (durante o fecho documental) e
**activada como fila seguinte** após Onda J — não altera o veredicto do fecho.

Prompts antigos de coordenador/workers das Ondas P0–H do fecho ficam no
[`plano-fecho-producao-e-consolidacao.md`](../02-roadmap/plano-fecho-producao-e-consolidacao.md)
§3.6 — **não usar** enquanto a trilha activa for IPv6.

---

## Ligação rápida

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | **Único arranque de chat** |
| [plano-ipv6-completo.md](../02-roadmap/plano-ipv6-completo.md) | Fila passos 12.x / ondas V0–V6 |
| [f4-ipv6-mapa-rastreabilidade.md](../01-architecture/f4-ipv6-mapa-rastreabilidade.md) | Matriz código × gap + salvaguardas |
| [plano-gates-ipv6.md](../09-blocking/plano-gates-ipv6.md) | GV0–GV7 |
| [ADR-0024](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md) | Decisão faseada |
| [CORTEX.md](../../CORTEX.md) | SSOT estado + passo 12.x |
| [plano-fecho-producao-e-consolidacao.md](../02-roadmap/plano-fecho-producao-e-consolidacao.md) | Fecho P0–J (histórico, FECHADO) |
| [handoff-chat-novo.md](handoff-chat-novo.md) | Quando o contexto do chat esgotar |
