# START HERE — Anti-pirataria e Anti-tamper 【`30.15` FECHADO · GA5.12 **PASS** · **Composer 2.5**】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **`30.13`/`30.14` FECHADOS** — check-in assinado + default ON.
> **`30.15` FECHADO** — alerta multi-appliance (fase 1 = **só alerta**; decisão 7);
> rebind autorizado sem falso positivo; GA5.12 **PASS** (unit); **sem** deploy live.
> Evidência: [`../tests/evidence/20260812T020331Z-30.15-multi-appliance-abuse/`](../tests/evidence/20260812T020331Z-30.15-multi-appliance-abuse/).
> **Produção `.254`:** **`1.9.54`** (intocada). Candidato Makefile **`1.9.56`** (**sem** release).
> **Próximo passo:** AP4 **`30.16`** — **só** com pedido explícito (não abrir neste chat).
> **Proibido:** fail-closed por rede · kill-switch · ofuscação · misturar enforce/MITM/IPv6.
> **Honestidade:** root **pode** contornar verificação local (RR-5 / R-A).
> **Artefacto:** **`.pkg`** FreeBSD/pfSense (não APK Android).
> **Rev. plano:** `2026-08-10c`.

```text
docs/00-overview/START-HERE-antipirataria.md
```

### Continuidade em chat limpo (obrigatório)

1. Colar **apenas** o caminho acima **ou** o prompt abaixo.
2. Seguir a *Leitura obrigatória* **antes** de editar.
3. Passo actual = tabela *Estado actual* = plano §0 = CORTEX.
4. **Um passo por chat** (plano §8).
5. Divergência CORTEX/plano/START-HERE → **parar**.
6. **Não** recriar assets do espelho sem GO rollback GA4.11.
7. **Não** alterar `.254` / CF / DNS / deploy license-server sem GO do passo activo.

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque de chat + estado + prompt |
| [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) | **SSOT** passos `30.x` |
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — GA5.12 **PASS**; GA5.9 campo PENDENTE |
| Evidência `30.15` | [`../tests/evidence/20260812T020331Z-30.15-multi-appliance-abuse/`](../tests/evidence/20260812T020331Z-30.15-multi-appliance-abuse/) |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

---

## Estado actual (actualizar a cada passo)

| Campo | Valor |
|-------|-------|
| Onda | **AP3 FECHADA no código** (campo/release pendentes) |
| Passo | **`30.15` FECHADO** |
| Decisão 7 | **Só alerta** — sem `max_activations` |
| Campo / e2e | produção `.254` = `1.9.54` intocada |
| GA5.12 | **PASS** (unit; evidência `20260812T020331Z`) |
| GA5.9 | **PENDENTE campo** (sem `.254`/release) |
| Próxima acção | AP4 **`30.16`** (BG-122) — **sob pedido** |
| Código produto | candidato **`1.9.56`**; release **não** publicada |
| License-server 30.15 | código no git; **deploy live não** feito |
| Baseline enforce | **`1.9.8`** |
| Rev. do plano | **`2026-08-10c`** |

---

## Leitura obrigatória (chat novo → `30.16` sob pedido)

1. **Este ficheiro**
2. [`AGENTS.md`](../../AGENTS.md)
3. [`CORTEX.md`](../../CORTEX.md) — *Trilha Anti-pirataria*
4. [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) — §0, passo `30.16`
5. [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) — GA6
6. Evidência [`../tests/evidence/20260812T020331Z-30.15-multi-appliance-abuse/`](../tests/evidence/20260812T020331Z-30.15-multi-appliance-abuse/)

---

## Prompt — passo `30.16` (chat novo · só com pedido explícito)

```text
Modelo: Composer 2.5.
Contexto: trilha Anti-pirataria; 30.15 FECHADO (alerta multi-appliance); AP3 código fechado;
produção .254 = 1.9.54; candidato 1.9.56 sem release; license-server 30.15 sem deploy.
Arranque: docs/00-overview/START-HERE-antipirataria.md
AGORA: implementar SOMENTE 30.16 (BG-122) se e só se o humano pediu AP4/30.16.
Proibido: fail-closed rede; enforce/MITM/IPv6; ofuscação; mexer .254/CF/DNS sem GO.
Português.
```

---

## TESTES / GATES

| Onda | Gate | Estado |
|------|------|--------|
| AP0 | GA0/GA1 | **PASS** |
| AP1 | GA2 | **PARCIAL** |
| AP1 | GA3 | **PASS** |
| AP2 | GA4 | **PASS** cut; GA4.12 **N/A** |
| AP3 | GA5 | **PARCIAL** — 5.1–5.8 + 5.10–5.12 **PASS**; 5.9 campo **PENDENTE** |

---

## Progresso compacto

```text
TRILHA ANTI-PIRATARIA — progresso
- Diagnóstico: ACEITE 2026-08-10 (A-01..A-10)
- Rev. plano: 2026-08-10c
- Onda: AP3 FECHADA no código (campo/release pendentes)
- Passo: 30.15 FECHADO (alerta multi-appliance; só alerta)
- Decisão 7: só alerta; sem max_activations
- GA5.1-5.8+5.10-5.12 PASS; GA5.9 campo PENDENTE
- Evidência: 20260812T020331Z-30.15-multi-appliance-abuse
- Produção .254: 1.9.54; candidato 1.9.56 (sem release)
- Próximo: AP4 30.16 (só com pedido explícito)
- Agente: Composer 2.5 — um passo / chat (plano §8)
```
