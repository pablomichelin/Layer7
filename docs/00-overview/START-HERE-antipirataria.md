# START HERE — Anti-pirataria e Anti-tamper 【`30.11` FECHADO · GA4.10/15 **PASS** · GA4.12 **N/A** · **Composer 2.5**】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **`30.10` FECHADO** — cliente token (`1.9.53`) + fix `fetch_authed` (`1.9.54`) +
> **e2e `.254` PASS** (`20260811T114320Z`); GA4.4 **PASS**.
> **`30.9` live PASS** — `license.systemup.inf.br` emite `content_subscription`.
> **Produção `.254`:** **`1.9.54`**. Canal lab/`latest`: `1.9.54`. Rollback lab: `1.9.53`.
> **`30.11` FECHADO** — cut espelho (`20260812T011217Z`, `delete-asset` ×4);
> API `asset_count=0`; GA4.10/15 **PASS**; GA4.12 **N/A**.
> Residual CDN @cut documentado; recheck `012017Z` anónimo **404×4**.
> Evidência: [`../tests/evidence/20260812T011217Z-30.11-cut-mirror/`](../tests/evidence/20260812T011217Z-30.11-cut-mirror/).
> Prep/fecho: [`../09-blocking/prep-cut-30.11-espelho.md`](../09-blocking/prep-cut-30.11-espelho.md).
> **Próximo passo:** AP3 **`30.12`** (não misturar enforce/MITM/IPv6).
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
6. **Não** recriar assets do espelho `blacklists-ut1-current` sem GO de rollback GA4.11.
7. **Não** alterar produção `.254` / CF / DNS / license-server sem GO do passo activo.

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque de chat + estado + prompt Composer |
| [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md) | Diagnóstico A-01…A-10 — ACEITE |
| [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) | **SSOT** (ondas AP0–AP4, passos `30.x`, §8 Composer) |
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — GA4.4/10/15 **PASS**; GA4.12 **N/A** |
| Prep/fecho cut `30.11` | [`../09-blocking/prep-cut-30.11-espelho.md`](../09-blocking/prep-cut-30.11-espelho.md) |
| Evidência e2e `.254` | [`../tests/evidence/20260811T114320Z-30.10-e2e-154-254/`](../tests/evidence/20260811T114320Z-30.10-e2e-154-254/) |
| Evidência primary auth `30.11` | [`../tests/evidence/20260812T003214Z-30.11-auth-get-254/`](../tests/evidence/20260812T003214Z-30.11-auth-get-254/) |
| Evidência cut `30.11` | [`../tests/evidence/20260812T011217Z-30.11-cut-mirror/`](../tests/evidence/20260812T011217Z-30.11-cut-mirror/) |
| [ADR-0030](../03-adr/ADR-0030-postura-anti-tamper-layer7d.md) … [0033](../03-adr/ADR-0033-anti-rollback-relogio.md) | **`Aceito`** |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

**Não criar** outro `START-HERE-*` para subtarefas desta fila.

---

## Estado actual (actualizar a cada passo)

| Campo | Valor |
|-------|-------|
| Onda | **AP2 cut feito** → próximo **AP3** |
| Passo código | **`30.11` FECHADO** (cut espelho); `30.10` FECHADO |
| Campo / e2e | **PASS** — `1.9.54` em produção `.254` |
| Primary auth | **PASS** — GET 200/200 + 401 (`20260812T003214Z`) |
| Cut espelho | **PASS** — `asset_count=0`; recheck anónimo 404×4 |
| GA4.12 | **N/A** — sem coms externas (`2026-08-12`) |
| Próxima acção | AP3 **`30.12`** |
| Gate activo | **GA4 cut PASS**; próximo **GA5** (AP3) |
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
a execução controlada de `30.14` (check-in default). Cut `30.11` **já executado**.

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
5. [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) — §0, §0.0, §0.1 RR, §1 N1–N8, **§8 Composer**, estado `30.12`
6. [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) — GA4 (fecho) + GA5
7. Evidência e2e [`../tests/evidence/20260811T114320Z-30.10-e2e-154-254/`](../tests/evidence/20260811T114320Z-30.10-e2e-154-254/)
8. Evidência cut [`../tests/evidence/20260812T011217Z-30.11-cut-mirror/`](../tests/evidence/20260812T011217Z-30.11-cut-mirror/)
9. Evidência primary auth [`../tests/evidence/20260812T003214Z-30.11-auth-get-254/`](../tests/evidence/20260812T003214Z-30.11-auth-get-254/)
10. Contrato [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md) + ADR-0031
11. Rollback espelho / rascunho coms: [`../13-runbooks/content-mirror-rollback-ga4.11.md`](../13-runbooks/content-mirror-rollback-ga4.11.md) · [`../13-runbooks/content-mirror-comms-ga4.12-draft.md`](../13-runbooks/content-mirror-comms-ga4.12-draft.md)

Baseline: [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md)

---

## Prompt — passo `30.12` (AP3)

```text
Modelo: Composer 2.5.
Contexto: trilha Anti-pirataria; 30.11 FECHADO (cut espelho);
GA4.10/15 PASS; GA4.12 N/A; BG-117 Concluido; produção .254 = 1.9.54.
Arranque: docs/00-overview/START-HERE-antipirataria.md
AGORA: executar apenas o passo 30.12 do plano (AP3) — um passo;
sem reabrir cut/espelho; sem enforce/MITM/IPv6.
Proibido: ofuscação; fail-closed por rede; recriar assets GitHub sem GO rollback.
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
| AP2 | GA4 | **PASS** cut (GA4.10/15) + GA4.4–4.7/4.9; GA4.12 **N/A** |

**FAIL transversal:** rede a reduzir enforce · conteúdo a desligar enforce · kill-switch ·
cliente sem recuperação · segredo no git · passo misturado · gate só no repo ·
“impossível de contornar” · **trocar pubkey e invalidar licenças em campo**.

---

## Progresso compacto

```text
TRILHA ANTI-PIRATARIA — progresso
- Diagnóstico: ACEITE 2026-08-10 (A-01..A-10)
- Rev. plano: 2026-08-10c
- Onda: AP2 cut feito; próximo AP3
- Passo: 30.11 FECHADO (cut 20260812T011217Z)
- 30.9 live PASS; e2e 1.9.54 PASS (20260811T114320Z)
- Primary auth GET: 200/200 + 401 (20260812T003214Z) PASS
- Cut: delete-asset x4; asset_count=0; residual CDN @cut; recheck 404x4
- GA4.12: N/A; GA4.10/15: PASS
- Evidência cut: 20260812T011217Z-30.11-cut-mirror
- BG-117: Concluido
- Latest / produção .254: 1.9.54
- Próximo: AP3 30.12
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
| Evidência cut `30.11` | [`../tests/evidence/20260812T011217Z-30.11-cut-mirror/`](../tests/evidence/20260812T011217Z-30.11-cut-mirror/) |
| Runbook 30.10 | [`../13-runbooks/content-subscription-update.md`](../13-runbooks/content-subscription-update.md) |
| Rollback espelho GA4.11 | [`../13-runbooks/content-mirror-rollback-ga4.11.md`](../13-runbooks/content-mirror-rollback-ga4.11.md) |
| Prep/fecho cut `30.11` | [`../09-blocking/prep-cut-30.11-espelho.md`](../09-blocking/prep-cut-30.11-espelho.md) |
| Coms GA4.12 (histórico N/A) | [`../13-runbooks/content-mirror-comms-ga4.12-draft.md`](../13-runbooks/content-mirror-comms-ga4.12-draft.md) |
| Contrato 30.8 | [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md) |

---

## Nota de manutenção

Actualizar título, estado, progresso compacto, CORTEX e plano §0 no **mesmo**
commit de cada fecho de passo. Não declarar PASS de gate sem evidência.
