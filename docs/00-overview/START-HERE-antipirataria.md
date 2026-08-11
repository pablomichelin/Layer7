# START HERE — Anti-pirataria e Anti-tamper 【`30.5` FECHADO · próximo `30.6` · **Composer 2.5**】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **`30.5` FECHADO** — strip explícito + `-fvisibility=hidden`; GA2.4/2.5/2.11 **PASS**; BG-115 **Concluido**.
> **Passo actual a executar: `30.6`** — anti-rollback de relógio (BG-116).
> **Proibido:** fail-closed por rede · kill-switch remoto · ofuscação pesada · anti-debug.
> **Proibido:** misturar um passo `30.x` com promoção de enforce, MITM (`20.x`) ou IPv6.
> **Honestidade:** root no appliance **pode** contornar verificação local. Não prometer o contrário.
> **Artefacto:** **`.pkg`** FreeBSD/pfSense (não APK Android).
> **Rev. plano:** `2026-08-10c` — protocolo Composer §8 + RR-1…RR-5.
> **Evidência 30.5:** [`../tests/evidence/20260810T200329Z-30.5-strip/`](../tests/evidence/20260810T200329Z-30.5-strip/)
> **Canal lab/`latest`:** `1.9.50` (publicado). Rollback lab: `1.9.49`.

```text
docs/00-overview/START-HERE-antipirataria.md
```

### Continuidade em chat limpo (obrigatório)

1. Colar **apenas** o caminho acima **ou** o *Prompt — 30.6* abaixo.
2. O agente **deve** seguir a *Leitura obrigatória* **antes** de editar.
3. O **passo actual** está na tabela *Estado actual* e no progresso compacto — deve coincidir com o plano §0 e o CORTEX.
4. **Um passo por chat** (plano §8). Não usar `START-HERE-identity-mitm.md` nem `START-HERE-fecho-producao.md` para esta trilha.
5. Se CORTEX / plano / este ficheiro divergirem no passo actual → **parar** e declarar conflito.

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque de chat + estado + prompt Composer |
| [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md) | Diagnóstico A-01…A-10 — ACEITE |
| [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) | **SSOT** (ondas AP0–AP4, passos `30.x`, §8 Composer) |
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — **GA0/GA1 PASS**; GA2.1–2.5 + GA2.11 PASS |
| [`decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md) | 8 decisões — **GO `2026-08-10`** |
| [ADR-0030](../03-adr/ADR-0030-postura-anti-tamper-layer7d.md) … [0033](../03-adr/ADR-0033-anti-rollback-relogio.md) | **`Aceito`** |
| [`builder-freebsd.md`](../08-lab/builder-freebsd.md) | Builder — SoT pubkey (30.2) |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

**Não criar** outro `START-HERE-*` para subtarefas desta fila.

---

## Estado actual (actualizar a cada passo)

| Campo | Valor |
|-------|-------|
| Onda | **AP1 em curso** (AP0 FECHADA; GA1 PASS) |
| Passo actual | **`30.5` FECHADO** — próximo a executar: **`30.6`** |
| Decisão 4 (pubkey) | **Fora do git (builder)** — SoT aplicada; **inalterada** em 30.5 |
| Gate activo | **GA2 parcial** — GA2.1–2.5 + GA2.11 PASS; GA2.6–2.10 lab/`30.7`; **GA3** com `30.6` |
| Código de produto | **`30.5` aplicado** (Makefile strip + visibility); BG-115 Concluido |
| Agente | **Composer 2.5** — um passo por chat (plano §8) |
| Canal lab/`latest` | **`1.9.50`** — rollback lab **`1.9.49`** |
| Baseline produção enforce | **`1.9.8`** — rollback enforce `1.9.0` |
| Prioridade de valor | **AP2** — GOs comerciais em princípio; execução `30.11`/`30.14` |
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

1. **AP1** — higiene do binário (dev key ✓, strip ✓, anti-rollback, GUI).
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
5. [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) — §0, §0.0, §0.1 RR, §1 N1–N8, **§8 Composer**, passo actual (`30.6`)
6. [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) — GA3
7. [ADR-0033](../03-adr/ADR-0033-anti-rollback-relogio.md)
8. Evidência `30.5`: [`../tests/evidence/20260810T200329Z-30.5-strip/`](../tests/evidence/20260810T200329Z-30.5-strip/)

Baseline: [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md)

---

## Prompt — Composer 2.5 (passo `30.6`)

```text
Modelo: Composer 2.5.
Contexto: trilha Anti-pirataria / Anti-tamper; baseline enforce 1.9.8; lab/latest 1.9.50 (.pkg).
Arranque: docs/00-overview/START-HERE-antipirataria.md
Plano SSOT: docs/02-roadmap/plano-antipirataria-anti-tamper.md (rev 2026-08-10c)
Gates: docs/09-blocking/plano-gates-antipirataria.md (GA3)
Ler na ordem do START-HERE; cumprir plano §8.
Estado: 30.5 FECHADO + 1.9.50 publicado; GA2.4/2.5/2.11 PASS; executar SOMENTE 30.6.
AGORA: 30.6 — anti-rollback de relógio (BG-116 / ADR-0033); marcar estado
temporal suspeito → monitor + evento; N6 recuperável; declarar RR-4;
NÃO iniciar 30.7 neste chat salvo pedido explícito.
Proibido: ofuscação/packers/anti-debug; misturar passos; MITM/IPv6/enforce; trocar pubkey;
fail-closed por rede; crash do daemon.
Imprimir cartão PASSO/PERMITIDO/PROIBIDO/STOP/GATE/DoD antes de editar.
Resposta final: Resumo, Arquivos, Implementação, Teste, Risco, Rollback, Docs.
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
| AP0 | GA1 | **PASS** (`30.2`+`30.3`) |
| AP1 | GA2 | **PARCIAL** — GA2.1–2.5 + GA2.11 PASS (`30.4`/`30.5`); falta lab N1/N2 + `30.7` |
| AP1 | GA3 | PENDENTE (`30.6`) |
| AP2 | GA4 | PENDENTE |
| AP3 | GA5 | PENDENTE |
| AP4 | GA6 | PENDENTE |

**FAIL transversal:** rede a reduzir enforce · conteúdo a desligar enforce · kill-switch ·
cliente sem recuperação · segredo no git · passo misturado · gate só no repo ·
“impossível de contornar” · **trocar pubkey e invalidar licenças em campo**.

---

## Progresso compacto

```text
TRILHA ANTI-PIRATARIA — progresso
- Diagnóstico: ACEITE 2026-08-10 (A-01..A-10)
- Rev. plano: 2026-08-10c
- Onda: AP1 em curso
- Passo: 30.5 FECHADO; PRÓXIMO EXECUTAR = 30.6
- ADRs 0030-0033: Aceito
- Gate GA0/GA1: PASS; GA2.1–2.5 + GA2.11 PASS
- Evidência 30.5: 20260810T200329Z-30.5-strip
- BG-114/BG-115: Concluido
- Latest: 1.9.50 (.pkg; rollback lab 1.9.49)
- SoT pubkey: /root/layer7-build-secrets/
- Agente: Composer 2.5 — um passo / chat (plano §8)
```

Actualizar este bloco **e** o CORTEX **e** o plano §0 no mesmo commit de cada fecho.

---

## Ligação rápida

| Tema | Documento |
|------|-----------|
| Plano + §8 Composer | [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) |
| Gates | [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) |
| Teste 30.5 | [`../../scripts/package/test-prod-strip.sh`](../../scripts/package/test-prod-strip.sh) |
| Teste 30.4 | [`../../scripts/package/test-prod-no-dev-bypass.sh`](../../scripts/package/test-prod-no-dev-bypass.sh) |
| Auditoria .pkg | [`../../scripts/package/audit-release-dev-bypass.sh`](../../scripts/package/audit-release-dev-bypass.sh) |
| Builder | [`../08-lab/builder-freebsd.md`](../08-lab/builder-freebsd.md) |

---

## Regras invioláveis (resumo)

1. Plano manda — um passo por entrega.
2. Honestidade — root contorna local; RR-1…RR-5 não são opcionais nos ADRs.
3. Nunca fail-closed por rede; conteúdo ≠ enforce; sem kill-switch.
4. Daemon é autoridade; sem ofuscação/anti-debug.
5. Gates validam o **`.pkg` publicado**.
6. Sem segredos no git; erro honesto tem recuperação (R-J).
7. Isolamento de trilhas; SoT pubkey fora do git (`30.2`).
8. Composer **não** marca ADR `Aceito` sem ditado humano.
