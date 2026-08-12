# START HERE — Anti-pirataria e Anti-tamper 【`30.14` FECHADO · GA5.7/5.8/5.10/5.11 **PASS** · **Composer 2.5**】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **`30.13` FECHADO** — check-in assinado; GA5.1–5.6 **PASS**.
> **`30.14` FECHADO** — GO humano explícito; `check_in_enabled` default **true**
> em instalações novas; upgrade **não regressivo**; runbook isolados; **N3** intacto.
> GO literal: [`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md) (addendum `30.14`).
> Runbook: [`../13-runbooks/check-in-migration-30.14.md`](../13-runbooks/check-in-migration-30.14.md).
> Evidência: [`../tests/evidence/20260812T015519Z-30.14-checkin-default/`](../tests/evidence/20260812T015519Z-30.14-checkin-default/).
> **Produção `.254`:** **`1.9.54`** (intocada). Candidato Makefile **`1.9.56`** (**sem** release).
> **Próximo passo:** **`30.15`** (abuso multi-appliance) — chat novo.
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
7. **Não** alterar `.254` / CF / DNS / license-server sem GO do passo activo.

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque de chat + estado + prompt |
| [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) | **SSOT** passos `30.x` |
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — GA5.7/5.8/5.10/5.11 **PASS**; GA5.9 campo PENDENTE |
| Runbook `30.14` | [`../13-runbooks/check-in-migration-30.14.md`](../13-runbooks/check-in-migration-30.14.md) |
| ADR-0032 | [`../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md`](../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md) |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

---

## Estado actual (actualizar a cada passo)

| Campo | Valor |
|-------|-------|
| Onda | **AP3 em curso** |
| Passo | **`30.14` FECHADO** |
| GO 30.14 | **PASS** — texto literal na ficha `decisoes-humanas-30.1.md` |
| Campo / e2e | produção `.254` = `1.9.54` intocada |
| GA5.7/5.8/5.10/5.11 | **PASS** (unit + runbook) |
| GA5.9 | **PENDENTE campo** (sem `.254`/release neste passo) |
| Próxima acção | **`30.15`** (BG-121) |
| Código produto | candidato **`1.9.56`**; release **não** publicada |
| Baseline enforce | **`1.9.8`** |
| Rev. do plano | **`2026-08-10c`** |

---

## Leitura obrigatória (chat novo → `30.15`)

1. **Este ficheiro**
2. [`AGENTS.md`](../../AGENTS.md)
3. [`CORTEX.md`](../../CORTEX.md) — *Trilha Anti-pirataria*
4. [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) — §0, passo `30.15`
5. [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) — GA5.12
6. Evidência [`../tests/evidence/20260812T015519Z-30.14-checkin-default/`](../tests/evidence/20260812T015519Z-30.14-checkin-default/)

---

## Prompt — passo `30.15` (chat novo)

```text
Modelo: Composer 2.5.
Contexto: trilha Anti-pirataria; 30.14 FECHADO; GO check-in default registado;
produção .254 = 1.9.54; candidato 1.9.56 sem release.
Arranque: docs/00-overview/START-HERE-antipirataria.md
AGORA: implementar SOMENTE 30.15 (alerta abuso multi-appliance / BG-121).
Proibido: fail-closed rede; enforce/MITM/IPv6; ofuscação; mexer .254/CF/DNS.
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
| AP3 | GA5 | **PARCIAL** — 5.1–5.8 + 5.10–5.11 **PASS**; 5.9 campo **PENDENTE**; 5.12 → `30.15` |

---

## Progresso compacto

```text
TRILHA ANTI-PIRATARIA — progresso
- Diagnóstico: ACEITE 2026-08-10 (A-01..A-10)
- Rev. plano: 2026-08-10c
- Onda: AP3 em curso
- Passo: 30.14 FECHADO (check-in default ON + migração)
- GO 30.14: registado (priorizar segurança anti-pirataria)
- GA5.7/5.8/5.10/5.11 PASS; GA5.9 campo PENDENTE
- Evidência: 20260812T015519Z-30.14-checkin-default
- Produção .254: 1.9.54; candidato 1.9.56 (sem release)
- Próximo: 30.15 (abuso multi-appliance)
- Agente: Composer 2.5 — um passo / chat (plano §8)
```
