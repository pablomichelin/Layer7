# START HERE — Anti-pirataria e Anti-tamper 【`30.10` código OK · campo **BLOCKED** · **Composer 2.5**】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **`30.10` código/build/release FECHADO** — `.pkg` `1.9.53` + testes locais/builder PASS.
> **`30.9` live PASS** — `license.systemup.inf.br` emite `content_subscription` (`20260811T110043Z`).
> **Campo STOP/BLOCKED:** revalidação `.254` — check-in+token **PASS**, update autenticado
> **FAIL** (`fetch_authed` HTTP 302 no mirror GitHub; primary CDN sem DNS). Rollback PASS → **`1.9.47`**.
> Evidência: [`../tests/evidence/20260811T110638Z-30.10-revalidate-254/`](../tests/evidence/20260811T110638Z-30.10-revalidate-254/).
> **Não** declarar GA/e2e de campo concluído. **Não** iniciar `30.11` neste estado.
> **Próxima decisão humana:** corrigir `fetch_authed` (seguir redirect) num candidato `.pkg` + nova janela `.254`.
> **Proibido:** fail-closed por rede · kill-switch remoto · ofuscação pesada · anti-debug.
> **Proibido:** misturar um passo `30.x` com promoção de enforce, MITM (`20.x`) ou IPv6.
> **Honestidade:** root no appliance **pode** contornar verificação local. Não prometer o contrário.
> **Artefacto:** **`.pkg`** FreeBSD/pfSense (não APK Android).
> **Rev. plano:** `2026-08-10c` — protocolo Composer §8 + RR-1…RR-5.
> **Contrato 30.8:** [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md)
> **Canal lab/`latest`:** `1.9.53` (publicado). Produção observada: **`1.9.47`**. Rollback lab: `1.9.52`.

```text
docs/00-overview/START-HERE-antipirataria.md
```

### Continuidade em chat limpo (obrigatório)

1. Colar **apenas** o caminho acima **ou** o prompt de pré-requisito abaixo.
2. O agente **deve** seguir a *Leitura obrigatória* **antes** de editar.
3. O **passo actual** está na tabela *Estado actual* e no progresso compacto — deve coincidir com o plano §0 e o CORTEX.
4. **Um passo por chat** (plano §8). Não usar `START-HERE-identity-mitm.md` nem `START-HERE-fecho-producao.md` para esta trilha.
5. Se CORTEX / plano / este ficheiro divergirem no passo actual → **parar** e declarar conflito.
6. **Não** iniciar `30.11` enquanto GA4.4 estiver **BLOCKED**.

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque de chat + estado + prompt Composer |
| [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md) | Diagnóstico A-01…A-10 — ACEITE |
| [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) | **SSOT** (ondas AP0–AP4, passos `30.x`, §8 Composer) |
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — GA4.4 **BLOCKED**; GA4.5–4.7/4.9 PASS |
| Evidência `.254` (revalidação) | [`../tests/evidence/20260811T110638Z-30.10-revalidate-254/`](../tests/evidence/20260811T110638Z-30.10-revalidate-254/) |
| [ADR-0030](../03-adr/ADR-0030-postura-anti-tamper-layer7d.md) … [0033](../03-adr/ADR-0033-anti-rollback-relogio.md) | **`Aceito`** |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

**Não criar** outro `START-HERE-*` para subtarefas desta fila.

---

## Estado actual (actualizar a cada passo)

| Campo | Valor |
|-------|-------|
| Onda | **AP2 em curso** |
| Passo código | **`30.10` FECHADO** (cliente `1.9.53` publicado) |
| Campo / e2e | **BLOCKED** — token OK; update autenticado FAIL (302) |
| Próxima acção | **Fix `fetch_authed` + candidato `.pkg` + revalidação `.254`** — **não** `30.11` |
| Gate activo | **GA4 parcial** — GA4.4 **BLOCKED**; GA4.5–4.7/4.9 PASS; falta `30.11` |
| Código de produto | **`.pkg` lab/`latest` `1.9.53`**; produção `.254` = **`1.9.47`** |
| Canal lab/`latest` | **`1.9.53`** — rollback lab **`1.9.52`** |
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
5. [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) — §0, §0.0, §0.1 RR, §1 N1–N8, **§8 Composer**, estado `30.10`
6. [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) — GA4 (esp. GA4.4 BLOCKED)
7. Evidência [`../tests/evidence/20260811T110638Z-30.10-revalidate-254/`](../tests/evidence/20260811T110638Z-30.10-revalidate-254/)
8. Contrato [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md) + ADR-0031

Baseline: [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md)

---

## Prompt — pré-requisito operacional (antes de `30.11`)

```text
Modelo: Composer 2.5.
Contexto: trilha Anti-pirataria; 30.10 código/release 1.9.53 FECHADO; 30.9 live PASS;
campo BLOCKED por fetch_authed HTTP 302 no mirror.
Arranque: docs/00-overview/START-HERE-antipirataria.md
Estado: token/check-in OK em .254; update autenticado FAIL; produção 1.9.47.
Evidência: docs/tests/evidence/20260811T110638Z-30.10-revalidate-254/
AGORA (só com GO humano): corrigir fetch_authed (seguir 302) num candidato .pkg;
depois nova janela de validação appliance (check-in→token→update).
Proibido neste chat sem GO: 30.11; promover 1.9.53 em produção; ofuscação.
NÃO declarar GA4.4 PASS sem e2e de campo com update real.
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
| AP2 | GA4 | **PARCIAL** — GA4.4 **BLOCKED**; GA4.5–4.7/4.9 PASS; falta e2e + `30.11` |

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
- Passo: 30.10 código/release FECHADO (1.9.53); campo BLOCKED
- 30.9 live: PASS; check-in+token .254 PASS
- Bloqueio e2e: fetch_authed HTTP 302 + primary CDN DNS
- Gate: GA4.4 BLOCKED; GA4.5–4.7/4.9 PASS; NÃO e2e campo completo
- Evidência: 20260811T110638Z-30.10-revalidate-254 — rollback PASS → 1.9.47
- Latest publicado: 1.9.53; produção observada: 1.9.47
- NÃO iniciar 30.11
- Agente: Composer 2.5 — um passo / chat (plano §8)
```

Actualizar este bloco **e** o CORTEX **e** o plano §0 no mesmo commit de cada fecho.

---

## Ligação rápida

| Tema | Documento |
|------|-----------|
| Plano + §8 Composer | [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) |
| Gates | [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) |
| Evidência revalidação | [`../tests/evidence/20260811T110638Z-30.10-revalidate-254/`](../tests/evidence/20260811T110638Z-30.10-revalidate-254/) |
| Runbook 30.10 | [`../13-runbooks/content-subscription-update.md`](../13-runbooks/content-subscription-update.md) |
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
