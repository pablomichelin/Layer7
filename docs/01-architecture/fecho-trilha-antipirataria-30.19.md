# Fecho da trilha Anti-pirataria / Anti-tamper — `30.19`

**Estado:** FECHADO documental (`20260812T025741Z`) — **engenharia** AP0–AP4

**Trilha:** AP0–AP4 (`30.0`…`30.19`) — **não** reaberta
**Escopo deste passo:** **apenas documentação** — sem código, PORTVERSION,
publish/release, alteração de tags GitHub, produção/`.254`, CF/DNS,
license-server.  
**Evidência:** [`../tests/evidence/20260812T025741Z-30.19-fecho/`](../tests/evidence/20260812T025741Z-30.19-fecho/)  
**Plano SSOT:** [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md)  
**Gates:** GA6.7–GA6.12

> **Addendum `2026-08-14` (não reabre este fecho):** o ciclo de **evidência
> operacional** passou a **BG-127** (GO humano). Este documento continua a
> ser o fecho de **engenharia**. Estado vivo do soak `.254`: **`1.9.63`**
> `mode=monitor` MITM **OFF** —
> [`20260814T034904Z-20.36-soak-align-163-254`](../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/).
> O apontador `1.9.54` abaixo é o estado **à data do fecho** (`20260812T025741Z`),
> não o estado vivo.

---

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| Objectivo | Fechar a trilha com honestidade: EULA agenda (dec. 6), reavaliação de ameaças, RR-1…RR-5, R-L, execução documental da decisão 8 (RR-3), registo CORTEX + ESTADO |
| Impacto | Docs canónicos + aviso RR-3; apontadores oficiais permanecem `1.9.54` / enforce `1.9.8`; tags antigas **mantidas** |
| Risco | Baixo (documental); residual RR-3 (tags descarregáveis) e R-A (root) permanecem declarados |
| Teste | Checklist GA6.7–6.12 + revisão cruzada CORTEX/roadmap/backlog/gates/ADR/EULA (evidência) |
| Rollback | Reverter commit `30.19`; tags e produção intactas |

---

## GA6.7 — Revisão jurídica da EULA (decisão 6)

**Veredicto:** **PASS (agenda registada)** — não é parecer de advogado.

Decisão humana (`30.1b`): **Agendar** revisão quanto a auditoria e penalidade por
instalação excedente. Ficha:
[`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md) §Decisão 6.

Agenda canónica:
[`../09-blocking/eula-revisao-juridica-30.19.md`](../09-blocking/eula-revisao-juridica-30.19.md).

**Residual:** texto legal / parecer externo **pendente** de jurisconsulto —
o agente **não** redige cláusulas vinculativas.

---

## GA6.8 — Modelo de ameaças reavaliado

Base: [`modelo-ameacas-antipirataria.md`](modelo-ameacas-antipirataria.md)
(diagnóstico ACEITE `2026-08-10`).

| Achado | Controlo trilha | Estado pós-`30.18` |
|--------|-----------------|---------------------|
| A-01 | `30.4`/`30.5` — sem bypass dev em builds novos | Mitigado em builds ≥`1.9.49`; tags antigas → RR-3 |
| A-02 | `30.16` — decisão distribuída | Mitigado no código candidato; campo pós-release |
| A-03 | `30.6` — anti-rollback | Mitigado parcial (RR-4) |
| A-04 | `30.14` — check-in default ON | Mitigado em instalações novas; upgrade não regressivo |
| A-05 | `30.12`/`30.13` — check-in assinado | Mitigado no código; campo pós-release |
| A-06 | `30.8`–`30.11` — token + cut espelho | Mitigado (cut PASS); CDN residual @cut documentado |
| A-07 | `30.7` — entitlements assinados | Mitigado em `1.9.52`+ |
| A-08 | `30.15` — alerta multi-appliance | Mitigado (só alerta; sem max_activations) |
| A-09 | `30.2` — SoT pubkey builder | Resolvido operacionalmente |
| A-10 | `30.18` — F1.2 processo | Mitigado no processo; residual campo → BG-028 |

**Conclusão:** a trilha **não** elimina T4/root (R-A). Valor principal: barreira
a T1/T2 não-técnicos + proveniência + revogação com check-in + conteúdo com
token. Overclaim «impossível de piratar» = **proibido**.

---

## GA6.9 — O que **continua** possível para root (RR-1…RR-5)

| ID | Residual | Estado após controlos |
|----|----------|------------------------|
| RR-1 | Cut + check-in default | **Mitigado no fluxo** (`30.11`/`30.14` feitos); base instalada com check-in OFF só muda com migração/ops; GA5.9 campo ainda PENDENTE sem release/` .254` |
| RR-2 | Redistribuição de conteúdo por appliance licenciado | **Limite declarado**; `30.17` atribuição local (sem telemetria) — não bloqueia re-serva |
| RR-3 | `.pkg` antigos com caminho dev / pré-`30.4` | **Aceite por escrito** (decisão 8): tags mantidas + apontadores oficiais longe delas + aviso — ver GA6.12 |
| RR-4 | Anti-rollback: apagar estado / relógio congelado | **Limite declarado** (ADR-0033); contido melhor com check-in |
| RR-5 | Patch local a ignorar check-in | **Limite declarado** (R-A / ADR-0032) |

**Declaração honesta:** um atacante com root no appliance **pode** contornar
verificação local. Controlos elevam custo e dão atribuição/revogação/conteúdo
condicionado — **não** garantem incontornabilidade.

---

## GA6.10 — Sem prova em pfSense CE (R-L / ADR-0022)

| Tema | Prova | Nota |
|------|-------|------|
| Lab principal da trilha | pfSense **Plus** / FreeBSD 16 (`.254`) | Gates AP2 e2e em Plus |
| pfSense **CE** físico | **Sem prova** nesta trilha | ADR-0022 continua a valer |
| Builder | FreeBSD **15** | Builds `.pkg` ABI 15; Plus/16 com `pkg add -f` (BG-106) |
| Candidatos `1.9.55`–`1.9.58` | Código no git **sem** GitHub Release | Não validados em campo nesta trilha |

---

## GA6.11 — Fecho registado

- `CORTEX.md` — trilha **FECHADA** (`30.19`)
- [`../00-overview/ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](../00-overview/ESTADO-PRODUTO-E-PLANOS-FECHADOS.md) — entrada da fila
- [`../00-overview/START-HERE-antipirataria.md`](../00-overview/START-HERE-antipirataria.md) — fecho + manutenção
- Plano §0 + gates GA6.7–6.12

---

## GA6.12 — Decisão 8 / RR-3 (execução documental)

**Decisão humana:** *Limitar apontadores (latest/docs); manter tags com aviso*
([ficha](../09-blocking/decisoes-humanas-30.1.md) §Decisão 8).

**Executado neste passo (sem alterar tags):**

1. Apontadores oficiais canónicos: lab/`latest` = **`1.9.54`**; enforce = **`1.9.8`**
   (já vigentes — confirmados; não apontam instalação recomendada para ≤`1.9.48`).
2. Aviso RR-3:
   [`../06-releases/aviso-releases-antigas-rr3-30.19.md`](../06-releases/aviso-releases-antigas-rr3-30.19.md)
   + nota em `MANUAL-INSTALL.md`.
3. Insumo inventário: evidência `30.3`
   [`../tests/evidence/20260810T234552Z-ap0-baseline/`](../tests/evidence/20260810T234552Z-ap0-baseline/).
4. **Não** despublicar assets (proibido neste GO).

**Risco residual aceite:** tags históricas permanecem descarregáveis; um actor
pode fixar-se em build antigo. Desvalorização: conteúdo tokenizado (AP2) +
builds novos sem bypass (AP1).

---

## Fora de escopo (explícito)

MITM · IPv6 · ofuscação · fail-closed por rede · kill-switch · telemetria ·
BG-028 Fase 1 (1ª publish F1.2) · promoção enforce · deploy license-server

---

## Addendum — ciclo de evidência operacional (`2026-08-14`)

Este fecho **permanece**. O GO `2026-08-14` abre **BG-127** (GA2.6, GA2.7,
GA3.7, GA4.8, GA5.9) sem reabrir AP0–AP4. **GA6.7** continua parecer jurídico
externo. Hosts `.54` / `.254` fora do horário comercial; publicar só com
necessidade técnica verificada.
