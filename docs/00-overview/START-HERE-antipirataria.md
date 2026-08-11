# START HERE — Anti-pirataria e Anti-tamper 【`30.10` FECHADO · próximo `30.11` · **Composer 2.5**】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **`30.10` FECHADO** — cliente update-blacklists + token; GUI; runbook; `.pkg` `1.9.53`.
> **Passo actual a executar: `30.11`** — retirada do espelho (exige **GO humano**).
> **Proibido:** fail-closed por rede · kill-switch remoto · ofuscação pesada · anti-debug.
> **Proibido:** misturar um passo `30.x` com promoção de enforce, MITM (`20.x`) ou IPv6.
> **Honestidade:** root no appliance **pode** contornar verificação local. Não prometer o contrário.
> **Artefacto:** **`.pkg`** FreeBSD/pfSense (não APK Android).
> **Rev. plano:** `2026-08-10c` — protocolo Composer §8 + RR-1…RR-5.
> **Contrato 30.8:** [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md)
> **Canal lab/`latest`:** `1.9.53` (publicado). Rollback lab: `1.9.52`.

```text
docs/00-overview/START-HERE-antipirataria.md
```

### Continuidade em chat limpo (obrigatório)

1. Colar **apenas** o caminho acima **ou** o *Prompt — 30.11* abaixo.
2. O agente **deve** seguir a *Leitura obrigatória* **antes** de editar.
3. O **passo actual** está na tabela *Estado actual* e no progresso compacto — deve coincidir com o plano §0 e o CORTEX.
4. **Um passo por chat** (plano §8). Não usar `START-HERE-identity-mitm.md` nem `START-HERE-fecho-producao.md` para esta trilha.
5. Se CORTEX / plano / este ficheiro divergirem no passo actual → **parar** e declarar conflito.

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque de chat + estado + prompt Composer |
| [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md) | Diagnóstico A-01…A-10 — ACEITE |
| [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) | **SSOT** (ondas AP0–AP4, passos `30.x`, §8 Composer) |
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — GA0/GA1/GA3 **PASS**; GA4.1–4.9/4.13/4.14 **PASS**; GA2 parcial |
| [`decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md) | 8 decisões — **GO `2026-08-10`** |
| [ADR-0030](../03-adr/ADR-0030-postura-anti-tamper-layer7d.md) … [0033](../03-adr/ADR-0033-anti-rollback-relogio.md) | **`Aceito`** |
| [`builder-freebsd.md`](../08-lab/builder-freebsd.md) | Builder — SoT pubkey (30.2) |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

**Não criar** outro `START-HERE-*` para subtarefas desta fila.

---

## Estado actual (actualizar a cada passo)

| Campo | Valor |
|-------|-------|
| Onda | **AP2 em curso** (AP0/AP1 higiene FECHADAS; GA1 PASS) |
| Passo actual | **`30.10` FECHADO** — próximo a executar: **`30.11`** (GO espelho) |
| Decisão 4 (pubkey) | **Fora do git (builder)** — SoT / chave licença inalterada |
| Gate activo | **GA4 parcial** — GA4.1–4.9/4.13/4.14 **PASS**; falta `30.11` (GA4.10–4.12/4.15) |
| Código de produto | **`.pkg` lab `1.9.53`** + license-server (`30.9`) |
| Agente | **Composer 2.5** — um passo por chat (plano §8) |
| Canal lab/`latest` | **`1.9.53`** — rollback lab **`1.9.52`** |
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
5. [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) — §0, §0.0, §0.1 RR, §1 N1–N8, **§8 Composer**, passo actual (`30.11`)
6. [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) — GA4.10+
7. Contrato [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md) + ADR-0031
8. Runbook cliente: [`../13-runbooks/content-subscription-update.md`](../13-runbooks/content-subscription-update.md)

Baseline: [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md)

---

## Prompt — Composer 2.5 (passo `30.11`)

```text
Modelo: Composer 2.5.
Contexto: trilha Anti-pirataria / Anti-tamper; baseline enforce 1.9.8; lab/latest 1.9.53 (.pkg).
Arranque: docs/00-overview/START-HERE-antipirataria.md
Plano SSOT: docs/02-roadmap/plano-antipirataria-anti-tamper.md (rev 2026-08-10c)
Contrato: docs/01-architecture/contrato-token-subscricao-conteudo-30.8.md
Gates: docs/09-blocking/plano-gates-antipirataria.md (GA4.10–GA4.12/GA4.15)
Ler na ordem do START-HERE; cumprir plano §8 e contrato 30.8.
Estado: 30.10 FECHADO; executar SOMENTE 30.11 — EXIGE GO HUMANO PRÓPRIO.
AGORA: 30.11 — retirar/limitar espelho anónimo de conteúdo corrente; comunicação;
procedimento de reposição (GA4.11); SEM misturar enforce/MITM/IPv6/pubkey.
Proibido: ofuscação; misturar passos; falhar enforce; executar sem GO 30.11.
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
| AP1 | GA2 | **PARCIAL** — GA2.1–2.5 + GA2.8–2.11 PASS; faltam GA2.6–2.7 (lab) |
| AP1 | GA3 | **PASS** (`30.6`; GA3.7 DEFERRED) |
| AP2 | GA4 | **PARCIAL** — GA4.1–4.9/4.13/4.14 PASS; falta espelho `30.11` |
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
- Onda: AP2 em curso
- Passo: 30.10 FECHADO; PRÓXIMO EXECUTAR = 30.11 (GO espelho)
- ADRs 0030-0033: Aceito
- Gate GA0/GA1/GA3: PASS; GA4.1–4.9/4.13/4.14 PASS
- Contrato 30.8 + emissão 30.9 + cliente 30.10
- BG-114/BG-115/BG-116/BG-120: Concluido; BG-117 em curso (falta 30.11)
- Latest: 1.9.53 (.pkg; rollback lab 1.9.52)
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
| Runbook 30.6 | [`../13-runbooks/anti-rollback-relogio.md`](../13-runbooks/anti-rollback-relogio.md) |
| Runbook 30.10 | [`../13-runbooks/content-subscription-update.md`](../13-runbooks/content-subscription-update.md) |
| Contrato 30.8 | [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md) |
| Teste 30.10 | [`../../tests/functional/test_content_subscription_update.sh`](../../tests/functional/test_content_subscription_update.sh) |
| Teste 30.7 | [`../../tests/functional/test_entitlements_gui.php`](../../tests/functional/test_entitlements_gui.php) |
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
