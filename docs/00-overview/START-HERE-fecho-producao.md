# START HERE — Arranque único (fecho + trilha IPv6)

**Este é o único ficheiro de arranque de chat** para o plano mestre e para a
trilha IPv6. Colar apenas o caminho deste ficheiro num chat limpo.

```text
docs/00-overview/START-HERE-fecho-producao.md
```

| Trilha | Estado | SSOT de execução |
|--------|--------|------------------|
| Fecho produção P0–J | **FECHADO** (`1.9.0`, `2026-08-05`) | [`plano-fecho-producao-e-consolidacao.md`](../02-roadmap/plano-fecho-producao-e-consolidacao.md) (histórico) |
| **IPv6 completo V0–V6** | **FECHADO (núcleo)** — GV7.1–GV7.3 PASS; V5 **ADIADA (B temp.)**; GV7.4 promoção PENDENTE | [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md) |

**Não criar** outros ficheiros `START-HERE-*.md` para esta fila — este é o único.

---

## Estado actual (verificar no CORTEX antes de executar)

| Campo | Valor |
|-------|-------|
| **Trilha IPv6** | **FECHADA (núcleo dual-stack)** — não «completa comercial» |
| **Passo residual autorizado** | **12.10/V5** com GO **ou** promoção enforce (`1.9.6`) com GO |
| **BG residual** | BG-083 adiado (retomar); BG-084 **concluído** (12.12+12.13) |
| Produção enforce | **`1.9.0`** (inalterada — GV7.4 PENDENTE) |
| Candidato lab / `latest` | **`1.9.6`** (AAAA hint; SHA256 `fc2d7fce…`) |
| Plano fecho P0–J | **FECHADO** |
| ADR IPv6 | ADR-0024 — **V5 Opção B temporária**; retomar Opção A depois |
| Ressalva V5 | DNS/block page/VIP DNS v6 **ainda não**; **voltar a fazer bem** (12.10–12.11) |
| Última evidência GV | `20260805T133000Z-gv7-fecho` — **GV7 fecho documental** |
| F6 / F7 (fecho) | F6 fechada (H5 diferido); F7 checklist + ADR-0023 fase 0 |

### Desambiguação obrigatória — «12.x»

Os passos da trilha IPv6 chamam-se **12.1 … 12.13** (continuação do plano
mestre após 11.1).

**Não confundir** com `docs/tests/test-matrix.md` secção **§12** (testes
**12.1 / 12.2** de blacklists F4.2 — já PASS na Onda D).

| Referência | Significado |
|------------|-------------|
| Passos **12.1–12.9** | Núcleo dual-stack — **concluídos** |
| Passo **12.10–12.11** | DNS/`rdr inet6` / block page / VIP — **residual** (GO) |
| Passos **12.12–12.13** | GV6 + GV7 fecho — **concluídos** |
| test-matrix **12.1 / 12.2** | Blacklists UT1 (F4.2) — **outra coisa** |

Mensagens de commit da trilha: `trilha-ipv6/12.x: …`

### Continuidade

1. Ler **este ficheiro** → `CORTEX.md` (secção *Trilha IPv6*) → residual em
   [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md).
2. Mapa código: [`f4-ipv6-mapa-rastreabilidade.md`](../01-architecture/f4-ipv6-mapa-rastreabilidade.md)
3. Gates: [`plano-gates-ipv6.md`](../09-blocking/plano-gates-ipv6.md)
4. Decisão: [`ADR-0024`](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md)

O fecho P0–J **não foi reaberto**. Produção `1.9.0` mantém-se até GO de promoção.

---

## Antes de colar no chat

1. `git status` limpo ou mudanças conscientes commitadas.
2. Confirmar residual no **CORTEX** (não assumir memória de chat anterior).
3. **Não regressão IPv4:** cada bloco com código exige `tests/run-local.sh` PASS.
4. Pedir **só** o residual autorizado (12.10 ou promoção) — nunca «implementa IPv6 tudo».
5. **V5** proibido sem GO humano + ADR-0024 Opção A.

---

## Prompt — chat limpo (trabalho residual IPv6)

```text
Contexto: executo residual da trilha IPv6 (núcleo já FECHADO). Arranque único:
docs/00-overview/START-HERE-fecho-producao.md

Leitura obrigatória:
1. docs/00-overview/START-HERE-fecho-producao.md
2. CORTEX.md (secção Trilha IPv6)
3. AGENTS.md
4. docs/02-roadmap/plano-ipv6-completo.md
5. docs/01-architecture/f4-ipv6-mapa-rastreabilidade.md
6. docs/09-blocking/plano-gates-ipv6.md
7. docs/03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md

Regras absolutas:
- Núcleo V0–V4 + GV6–GV7 documental já FECHADO; não reabrir P0–J.
- Executar SOMENTE residual autorizado: 12.10/V5 com GO OU promoção enforce com GO.
- Produção enforce permanece 1.9.0 até GO explícito de promoção.
- Não regressão IPv4: run-local.sh quando houver código.
- V5 (NAT/DNS v6) proibido sem decisão humana Opção A na ADR-0024.
- Responder em português.

Estado (confirmar no repo):
- Branch: main
- Trilha: FECHADA (núcleo); residual V5 ou promoção
- Candidato lab / latest: 1.9.6
- Produção enforce: 1.9.0

Tarefa deste chat:
Executa SOMENTE <12.10|promoção enforce> conforme GO explícito.
Não avances automaticamente.
```

---

## O que este arranque NÃO autoriza

- Criar outro `START-HERE-*.md`.
- Reabrir Ondas P0–J do plano de fecho.
- Promover produção enforce sem GO humano (GV7.4).
- V5 sem decisão humana explícita Opção A.
- Afirmar «IPv6 completo comercial» enquanto V5 estiver adiada.
- Confundir passos 12.x IPv6 com test-matrix §12 (blacklists).

---

## Sequência da trilha (resumo)

| Passo | Onda | Objectivo |
|-------|------|-----------|
| **12.1–12.9** | V0–V4 | Núcleo dual-stack — **CONCLUÍDO** |
| **12.10–12.11** | V5 | DNS/NAT/block page v6 — **residual** (GO) |
| **12.12–12.13** | V6 | GV6 + GV7 fecho — **CONCLUÍDO** |

Detalhe: [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md).

---

## Histórico — fecho produção (referência)

O plano P0–J fechou em `2026-08-05` com produção **`1.9.0`**. A trilha IPv6
fechou o **núcleo** em `2026-08-05` (GV7 documental); V5 permanece a retomar.

---

## Ligação rápida

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | **Único arranque de chat** |
| [plano-ipv6-completo.md](../02-roadmap/plano-ipv6-completo.md) | Fila passos 12.x / ondas V0–V6 |
| [f4-ipv6-mapa-rastreabilidade.md](../01-architecture/f4-ipv6-mapa-rastreabilidade.md) | Matriz código × gap + salvaguardas |
| [plano-gates-ipv6.md](../09-blocking/plano-gates-ipv6.md) | GV0–GV7 |
| [ADR-0024](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md) | Decisão faseada |
| [CORTEX.md](../../CORTEX.md) | SSOT estado |
| [plano-fecho-producao-e-consolidacao.md](../02-roadmap/plano-fecho-producao-e-consolidacao.md) | Fecho P0–J (histórico, FECHADO) |
| [handoff-chat-novo.md](handoff-chat-novo.md) | Quando o contexto do chat esgotar |
