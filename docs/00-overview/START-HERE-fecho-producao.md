# START HERE — Fecho de produção e consolidação

**Usar este ficheiro** para abrir um chat novo (ou Multitarefa) que execute o
plano mestre. O plano completo está em:

[`../02-roadmap/plano-fecho-producao-e-consolidacao.md`](../02-roadmap/plano-fecho-producao-e-consolidacao.md)

---

## Estado actual (checkpoint — verificar no CORTEX antes de executar)

| Campo | Valor esperado (`2026-08-04`) |
|-------|-------------------------------|
| Passo actual | `2.2` (Onda A — G3 PF parser) após passo 2.1 PASS |
| Candidato lab (gates) | **`1.8.11_65`** |
| Canal `latest` | `1.8.11_65` |
| Produção enforce | `1.8.11_24` (até GO Onda F) |
| Appliance lab | `192.168.100.254` (Plus/FB16 — CE na Onda E) |
| Builder | `192.168.100.12` |

---

## Antes de colar no chat

1. `git status` limpo ou mudanças conscientes commitadas.
2. Confirmar dual-canal: **`latest` (`_65`) ≠ produção enforce (`_24`)**.
3. Escolher modo conforme a **onda** (secção 3.5 do plano):
   - **Multitarefa (preferido em P0, P1, H):** 1 coordenador + workers com ficheiros **disjuntos** e **sem dependência de estado** entre si.
   - **Agente único (obrigatório em A–G appliance, F, I–J):** qualquer passo que mexe no appliance, ficheiros quentes ou GO.
4. Nunca pedir “melhora o que puderes”: pedir **o passo actual do plano**.
5. **Nunca paralelizar** duas ondas que mudam o mesmo appliance (ex.: B ∥ C).

---

## Modo recomendado por onda

| Onda | Modo | Chats |
|------|------|-------|
| P0, P1 | **Coordenador + workers** | 1 coordenador + até 2 workers |
| A–E (lab) | **Agente único** + humano | 1 chat por onda |
| F–G, I–J | **Agente único** | 1 chat por onda |
| H (F6) | **Coordenador + Docs-A/B/C** | 1 coordenador + workers por lote |

Receitas detalhadas: plano secção **3.6**.

---

## Prompt — COORDENADOR (P0, P1, H — modo preferido)

Copia na **primeira mensagem** do chat coordenador. Ajusta só `<passo>` se necessário.

```text
Contexto: sou o COORDENADOR do PLANO MESTRE de fecho de produção Layer7.

Leitura obrigatória:
1. CORTEX.md (checklist plano na secção Plano mestre)
2. AGENTS.md
3. docs/02-roadmap/plano-fecho-producao-e-consolidacao.md (secções 3 e 7)
4. docs/00-overview/START-HERE-fecho-producao.md

Regras do coordenador:
- ÚNICO agente que edita: CORTEX.md, backlog.md, CHANGELOG.md, MANUAL-INSTALL.md, Makefile.
- Autorizo workers APENAS com ficheiros disjuntos da secção 3.6 do plano.
- Se worker precisar de ficheiro quente ou de resultado de outro worker ainda pendente: PARAR.
- Não avançar passo sem eu pedir. Integrar entregas → um commit de fecho da onda.
- Responder em português.

Estado (confirmar no repo):
- Branch: main
- Passo actual: <1.1>
- Onda: P1
- Candidato lab: 1.8.11_65
- Produção enforce: 1.8.11_24
- Latest: 1.8.11_65

Tarefa deste chat:
Orquestrar o passo <id> do plano. Se multitarefa autorizada, define workers
(ficheiros permitidos) e só integra quando todas as entregas estiverem completas.
No fim: (1) resumo integrado, (2) commits, (3) checklist CORTEX actualizado,
(4) próximo passo autorizado.
```

---

## Prompt — WORKER (só quando o coordenador autorizar)

```text
És WORKER do plano docs/02-roadmap/plano-fecho-producao-e-consolidacao.md.
Onda: <X>. Trilha: <nome definido pelo coordenador>.
Ficheiros que PODES editar: <lista explícita do coordenador>.
Ficheiros PROIBIDOS: CORTEX.md, backlog.md, CHANGELOG.md, MANUAL-INSTALL.md,
Makefile, layer7.inc, src/layer7d/* (salvo inclusão explícita na lista).
Não cries passos novos. Não toques no appliance sem autorização explícita.
Entrega: diff resumido + teste + texto para o coordenador gravar no CORTEX.
Se precisares de ficheiro proibido ou de output de outro worker: PARA e reporta.
Responde em português.
```

---

## Prompt — AGENTE ÚNICO (Ondas A–G, F, I–J)

Copia na **primeira mensagem** do chat novo.

```text
Contexto: executo o PLANO MESTRE de fecho de produção e consolidação do Layer7.

Leitura obrigatória (nesta ordem):
1. CORTEX.md
2. AGENTS.md
3. docs/02-roadmap/plano-fecho-producao-e-consolidacao.md
4. docs/09-blocking/plano-gates-producao.md (se onda A–B)
5. docs/04-package/validacao-lab.md (se onda A–D)
6. docs/02-roadmap/roadmap.md
7. docs/02-roadmap/backlog.md

Regras absolutas:
- Seguir APENAS o passo autorizado do plano (nunca vontade do agente).
- Não mover/renomear/apagar ficheiros até Onda H (F6).
- Não activar scoped_hybrid/enforce em produção sem gates PASS + GO humano.
- Produção enforce permanece 1.8.11_24 até Onda F (GO).
- Candidato lab para gates: 1.8.11_65 (não substituir sem decisão no CORTEX).
- Versionar: commit a cada bloco; PORTREVISION+release GitHub só quando o passo exigir .pkg.
- Actualizar docs no mesmo bloco (CORTEX, changelog, MANUAL-INSTALL se operacional).
- Multitarefa PROIBIDA nesta onda (appliance / ficheiros quentes).
- Objectivo, impacto, risco, teste e rollback em cada entrega.
- Responder em português.

Estado que assumo até verificares no repo:
- Branch: main
- Passo actual: <ex.: 2.1>
- Onda: <A|B|C|D|E|F|G|I|J>
- Candidato lab: 1.8.11_65
- Produção enforce: 1.8.11_24
- Pendências humanas: <ex.: snapshot appliance / dois clientes LAN / SSH>

Tarefa deste chat:
Executa SOMENTE o passo <id> do plano.
No fim: (1) o que fizeste, (2) evidência/teste, (3) commits/tags se houver,
(4) próximo passo autorizado pelo plano, (5) actualiza checklist no CORTEX.
Não avances automaticamente para o passo seguinte sem eu pedir.
```

---

## Sequência sugerida dos primeiros chats

| Chat | Passo | Modo | Pré-requisito humano |
|------|-------|------|----------------------|
| 1 | 0.1 + 0.2 (P0) | Coordenador + workers | — |
| 2 | 1.0–1.3 (P1) | Coordenador + worker diagnose | Snapshot VM |
| 3 | 2.1–2.4 (Onda A) | Agente único | SSH appliance; rollback `_24` pronto |
| 4 | 3.1–3.2 (Onda B) | Agente único | **Dois clientes** LAN |
| 5+ | 4.x–11.1 | Conforme secção 3.5 do plano | Ver plano |

Abrir **chat novo por onda** (ou quando o contexto ficar longo). Ver também
[`handoff-chat-novo.md`](handoff-chat-novo.md).

---

## O que este arranque NÃO autoriza

- Reescrever a árvore `docs/` agora (isso é Onda H / F6).
- “Consolidar tudo de uma vez” num único PR gigante.
- Publicar GO de produção sem G2–G7 **e** Ondas C–E PASS.
- Multitarefa sem lista de ficheiros disjuntos **e** sem coordenador.
- Paralelizar B e C no mesmo appliance.
- Tratar `latest` (`_65`) como produção enforce antes da Onda F.

---

## Ligação rápida

| Documento | Papel |
|-----------|--------|
| [plano-fecho-producao-e-consolidacao.md](../02-roadmap/plano-fecho-producao-e-consolidacao.md) | Guia completo início/meio/fim |
| [CORTEX.md](../../CORTEX.md) | Estado real + checklist progresso |
| [plano-gates-producao.md](../09-blocking/plano-gates-producao.md) | G0–G7 (candidato `_65`) |
| [validacao-lab.md](../04-package/validacao-lab.md) | Roteiros lab |
| [checklist-mestre.md](../02-roadmap/checklist-mestre.md) | Gates por fase |
| [document-equivalence-map.md](document-equivalence-map.md) | Duplicados docs (preparar F6) |
