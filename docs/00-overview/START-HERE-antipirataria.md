# START HERE — Anti-pirataria e Anti-tamper 【`30.10` FECHADO · GA4.12 **N/A** · cut **PENDENTE** · **Composer 2.5**】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **`30.10` FECHADO** — cliente token (`1.9.53`) + fix `fetch_authed` (`1.9.54`) +
> **e2e `.254` PASS** (`20260811T114320Z`); GA4.4 **PASS**.
> **`30.9` live PASS** — `license.systemup.inf.br` emite `content_subscription`.
> **Produção `.254`:** **`1.9.54`**. Canal lab/`latest`: `1.9.54`. Rollback lab: `1.9.53`.
> **`30.11` preflight:** primary `downloads.systemup.inf.br` — GET autenticado
> manifesto/`.sig` **200/200** + sem token **401** (`20260812T003214Z`) **PASS**.
> **GA4.12:** **N/A** (`2026-08-12`) — sem coms externas; decisão interna.
> **Cut do espelho:** **não executado** — exige GO gestor (GA4.15).
> Prep: [`../09-blocking/prep-cut-30.11-espelho.md`](../09-blocking/prep-cut-30.11-espelho.md).
> **Próximo passo:** **`30.11` cut** — **confirmação explícita** do gestor.
> **Proibido:** fail-closed por rede · kill-switch remoto · ofuscação pesada · anti-debug.
> **Proibido:** misturar um passo `30.x` com promoção de enforce, MITM (`20.x`) ou IPv6.
> **Honestidade:** root no appliance **pode** contornar verificação local. Não prometer o contrário.
> **Artefacto:** **`.pkg`** FreeBSD/pfSense (não APK Android).
> **Rev. plano:** `2026-08-10c` — protocolo Composer §8 + RR-1…RR-5.
> **Contrato 30.8:** [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md)

```text
docs/00-overview/START-HERE-antipirataria.md
```

### Continuidade em chat limpo (obrigatório)

1. Colar **apenas** o caminho acima **ou** o prompt de pré-requisito abaixo.
2. O agente **deve** seguir a *Leitura obrigatória* **antes** de editar.
3. O **passo actual** está na tabela *Estado actual* e no progresso compacto — deve coincidir com o plano §0 e o CORTEX.
4. **Um passo por chat** (plano §8). Não usar `START-HERE-identity-mitm.md` nem `START-HERE-fecho-producao.md` para esta trilha.
5. Se CORTEX / plano / este ficheiro divergirem no passo actual → **parar** e declarar conflito.
6. **Não** executar o **cut** de `30.11` (espelho) sem **GO humano** explícito (RR-1 / GA4.15).

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque de chat + estado + prompt Composer |
| [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md) | Diagnóstico A-01…A-10 — ACEITE |
| [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) | **SSOT** (ondas AP0–AP4, passos `30.x`, §8 Composer) |
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — GA4.4 **PASS**; GA4.12 **N/A**; falta cut (GA4.10/15) |
| Prep cut `30.11` | [`../09-blocking/prep-cut-30.11-espelho.md`](../09-blocking/prep-cut-30.11-espelho.md) |
| Evidência e2e `.254` | [`../tests/evidence/20260811T114320Z-30.10-e2e-154-254/`](../tests/evidence/20260811T114320Z-30.10-e2e-154-254/) |
| Evidência primary auth `30.11` | [`../tests/evidence/20260812T003214Z-30.11-auth-get-254/`](../tests/evidence/20260812T003214Z-30.11-auth-get-254/) |
| [ADR-0030](../03-adr/ADR-0030-postura-anti-tamper-layer7d.md) … [0033](../03-adr/ADR-0033-anti-rollback-relogio.md) | **`Aceito`** |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

**Não criar** outro `START-HERE-*` para subtarefas desta fila.

---

## Estado actual (actualizar a cada passo)

| Campo | Valor |
|-------|-------|
| Onda | **AP2 em curso** |
| Passo código | **`30.10` FECHADO**; **`30.11` preflight auth PASS** (cut **PENDENTE**) |
| Campo / e2e | **PASS** — `1.9.54` em produção `.254` |
| Primary auth | **PASS** — GET 200/200 + 401 (`20260812T003214Z`) |
| GA4.12 | **N/A** — sem coms externas (`2026-08-12`) |
| Próxima acção | **`30.11` cut** — confirmação explícita (GA4.15); prep pronto |
| Gate activo | **GA4 parcial** — GA4.4–4.7/4.9 PASS; GA4.12 N/A; falta GA4.10/15 |
| Código de produto | **`.pkg` lab/`latest` = produção `.254` = `1.9.54`** |
| Canal lab/`latest` | **`1.9.54`** — rollback lab **`1.9.53`** |
| Baseline produção enforce | **`1.9.8`** — rollback enforce `1.9.0` |
| License-server 30.9 | **live PASS** |
| Rev. do plano | **`2026-08-10c`** |

### Desambiguação

| Referência | Significado |
|------------|-------------|
| Passos **`30.x`** / ondas **AP0–AP4** | Esta trilha |
| **`.pkg`** | Pacote FreeBSD/pfSense publicado — **não** APK Android |
| Gates **GA0–GA6** / achados **A-01…A-10** / **RR-1…RR-5** | Gates, achados, riscos residuais |
| Passos **`20.x`** / **`12.x`** | Identity+MITM / IPv6 — **outra coisa** |

---

## O que esta trilha é (e o que protege de facto)

1. **AP1** — higiene do binário (dev key ✓, strip ✓, anti-rollback ✓, GUI ✓).
2. **AP2** — entitlement de conteúdo *(valor real)* — cópia degrada sozinha.
3. **AP3** — check-in obrigatório/assinado — revogação com efeito.
4. **AP4** — atribuição + release assinada + EULA.

**RR-1 (ler):** GOs de princípio em `30.1b` (dec. 1 e 3 = Sim) **não** substituem
a execução controlada de `30.11` e `30.14`.

### Honestidade obrigatória

- Root **pode** contornar verificação local (R-A).
- Nunca afirmar “impossível de contornar”.

## O que esta trilha **não** é

- Ofuscação / packers / anti-debug · fail-closed por rede · kill-switch · CRL offline · telemetria · reabrir fecho/IPv6/MITM · tornar o repo público.

---

## Leitura obrigatória (chat novo)

1. **Este ficheiro**
2. [`AGENTS.md`](../../AGENTS.md)
3. [`CORTEX.md`](../../CORTEX.md) — secção *Trilha Anti-pirataria*
4. [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md)
5. [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) — §0, §0.0, §0.1 RR, §1 N1–N8, **§8 Composer**, estado `30.11`
6. [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) — GA4 (esp. GA4.10+)
7. Evidência e2e [`../tests/evidence/20260811T114320Z-30.10-e2e-154-254/`](../tests/evidence/20260811T114320Z-30.10-e2e-154-254/)
8. Evidência primary auth [`../tests/evidence/20260812T003214Z-30.11-auth-get-254/`](../tests/evidence/20260812T003214Z-30.11-auth-get-254/)
9. Contrato [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md) + ADR-0031
10. Rollback espelho / rascunho coms: [`../13-runbooks/content-mirror-rollback-ga4.11.md`](../13-runbooks/content-mirror-rollback-ga4.11.md) · [`../13-runbooks/content-mirror-comms-ga4.12-draft.md`](../13-runbooks/content-mirror-comms-ga4.12-draft.md)

Baseline: [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md)

---

## Prompt — passo `30.11` (só com GO humano)

```text
Modelo: Composer 2.5.
Contexto: trilha Anti-pirataria; 30.10 FECHADO; GA4.12 N/A;
primary auth PASS (20260812T003214Z); prep cut em
docs/09-blocking/prep-cut-30.11-espelho.md;
próximo = cut 30.11 só com confirmação explícita (GA4.15).
Arranque: docs/00-overview/START-HERE-antipirataria.md
Estado: produção .254 = 1.9.54; latest = 1.9.54.
AGORA (só com GO cut explícito): executar delete-asset dos 4 assets
blacklists-ut1-current — um passo; sem e-mail.
Proibido: misturar enforce/MITM/IPv6; ofuscação; fail-closed por rede.
Português.
```

### Prompt — desvio / ADR

```text
Contexto: trilha Anti-pirataria (START-HERE-antipirataria.md).
Proposta de desvio: <descrição>
Impacto / risco / teste / rollback / passo:
Alinhamento R-A..R-L e RR-1..RR-5:
FAIL transversais dos gates:
Não implementar até GO. Português.
```

---

## TESTES / GATES

SSOT: [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md)

| Onda | Gate | Estado |
|------|------|--------|
| AP0 | GA0 | **PASS** |
| AP0 | GA1 | **PASS** |
| AP1 | GA2 | **PARCIAL** |
| AP1 | GA3 | **PASS** |
| AP2 | GA4 | **PARCIAL** — GA4.4–4.7/4.9 **PASS**; primary auth **PASS**; GA4.12 **N/A**; falta cut (GA4.10/15) |

**FAIL transversal:** rede a reduzir enforce · conteúdo a desligar enforce · kill-switch ·
cliente sem recuperação · segredo no git · passo misturado · gate só no repo ·
“impossível de contornar” · **trocar pubkey e invalidar licenças em campo**.

---

## Progresso compacto

```text
TRILHA ANTI-PIRATARIA — progresso
- Diagnóstico: ACEITE 2026-08-10 (A-01..A-10)
- Rev. plano: 2026-08-10c
- Onda: AP2 em curso
- Passo: 30.10 FECHADO; 30.11 preflight auth PASS; cut PENDENTE
- 30.9 live PASS; e2e 1.9.54 PASS (20260811T114320Z)
- Primary auth GET: 200/200 + 401 (20260812T003214Z) PASS
- GA4.12: N/A (sem coms externas 2026-08-12)
- Gate: GA4.4–4.7/4.9 PASS; falta GA4.10/15 (cut)
- Prep: docs/09-blocking/prep-cut-30.11-espelho.md
- Latest / produção .254: 1.9.54
- NÃO cut espelho sem GO gestor explícito
- Agente: Composer 2.5 — um passo / chat (plano §8)
```

Actualizar este bloco **e** o CORTEX **e** o plano §0 no mesmo commit de cada fecho.

---

## Ligação rápida

| Tema | Documento |
|------|-----------|
| Plano + §8 Composer | [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) |
| Gates | [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) |
| Evidência e2e `1.9.54` | [`../tests/evidence/20260811T114320Z-30.10-e2e-154-254/`](../tests/evidence/20260811T114320Z-30.10-e2e-154-254/) |
| Evidência primary auth `30.11` | [`../tests/evidence/20260812T003214Z-30.11-auth-get-254/`](../tests/evidence/20260812T003214Z-30.11-auth-get-254/) |
| Runbook 30.10 | [`../13-runbooks/content-subscription-update.md`](../13-runbooks/content-subscription-update.md) |
| Rollback espelho GA4.11 | [`../13-runbooks/content-mirror-rollback-ga4.11.md`](../13-runbooks/content-mirror-rollback-ga4.11.md) |
| Prep cut `30.11` | [`../09-blocking/prep-cut-30.11-espelho.md`](../09-blocking/prep-cut-30.11-espelho.md) |
| Coms GA4.12 (histórico N/A) | [`../13-runbooks/content-mirror-comms-ga4.12-draft.md`](../13-runbooks/content-mirror-comms-ga4.12-draft.md) |
| Contrato 30.8 | [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md) |

---

## Regras invioláveis (resumo)

1. Plano manda — um passo por entrega.
2. Honestidade — root contorna local; RR-1…RR-5 não são opcionais nos ADRs.
3. Nunca fail-closed por rede; conteúdo ≠ enforce; sem kill-switch.
4. Daemon é autoridade; sem ofuscação/anti-debug.
5. Gates validam o **`.pkg` publicado** — e2e de campo é distinto de teste local.
6. Sem segredos no git; erro honesto tem recuperação (R-J).
7. Isolamento de trilhas; SoT pubkey fora do git (`30.2`).
8. Composer **não** marca ADR `Aceito` sem ditado humano.
