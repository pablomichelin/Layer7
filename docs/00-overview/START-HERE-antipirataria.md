# START HERE — Anti-pirataria e Anti-tamper 【`30.16` FECHADO · GA6.1/6.2 **PASS** · **Composer 2.5**】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **AP3 código FECHADA** (`30.12`–`30.15`).
> **`30.16` FECHADO** — decisão de licença distribuída (BG-122 / A-02); gates A/B
> + `enforce_armed`; GA6.1/6.2 **PASS** (unit); candidato **`1.9.57`** (**sem** release).
> Evidência: [`../tests/evidence/20260812T023529Z-30.16-license-enforce-gate/`](../tests/evidence/20260812T023529Z-30.16-license-enforce-gate/).
> **Produção `.254`:** **`1.9.54`** (intocada).
> **Próximo passo:** AP4 **`30.17`** — **só** com pedido explícito (não abrir neste chat).
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
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — GA6.1/6.2 **PASS**; restante GA6 → `30.17+` |
| Evidência `30.16` | [`../tests/evidence/20260812T023529Z-30.16-license-enforce-gate/`](../tests/evidence/20260812T023529Z-30.16-license-enforce-gate/) |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

---

## Estado actual (actualizar a cada passo)

| Campo | Valor |
|-------|-------|
| Onda | **AP4 em curso** |
| Passo | **`30.16` FECHADO** |
| GA6.1 / GA6.2 | **PASS** (unit) |
| GA5.9 | **PENDENTE campo** (sem `.254`/release) |
| Campo / e2e | produção `.254` = `1.9.54` intocada |
| Próxima acção | AP4 **`30.17`** (marcação por cliente) — **sob pedido** |
| Código produto | candidato **`1.9.57`**; release **não** publicada |
| Baseline enforce | **`1.9.8`** |
| Rev. do plano | **`2026-08-10c`** |

---

## Leitura obrigatória (chat novo → `30.17` sob pedido)

1. **Este ficheiro**
2. [`AGENTS.md`](../../AGENTS.md)
3. [`CORTEX.md`](../../CORTEX.md) — *Trilha Anti-pirataria*
4. [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) — §0, passo `30.17`
5. [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) — GA6.3/6.4
6. Evidência [`../tests/evidence/20260812T023529Z-30.16-license-enforce-gate/`](../tests/evidence/20260812T023529Z-30.16-license-enforce-gate/)

---

## Prompt — passo `30.17` (chat novo · só com pedido explícito)

```text
Modelo: Composer 2.5.
Contexto: trilha Anti-pirataria; 30.16 FECHADO (decisão licença distribuída); GA6.1/6.2 PASS;
produção .254 = 1.9.54; candidato 1.9.57 sem release.
Arranque: docs/00-overview/START-HERE-antipirataria.md
AGORA: implementar SOMENTE 30.17 se e só se o humano pediu esse cartão.
Proibido: fail-closed rede; MITM/IPv6; ofuscação; mexer .254/CF/DNS/release sem obrigação do cartão.
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
| AP4 | GA6 | **PARCIAL** — 6.1/6.2 **PASS**; restante → `30.17+` |

---

## Progresso compacto

```text
TRILHA ANTI-PIRATARIA — progresso
- Diagnóstico: ACEITE 2026-08-10 (A-01..A-10)
- Rev. plano: 2026-08-10c
- Onda: AP4 em curso
- Passo: 30.16 FECHADO (decisão licença distribuída)
- GA6.1/6.2 PASS; GA5.9 campo PENDENTE
- Evidência: 20260812T023529Z-30.16-license-enforce-gate
- Produção .254: 1.9.54; candidato 1.9.57 (sem release)
- Próximo: AP4 30.17 (só com pedido explícito)
- Agente: Composer 2.5 — um passo / chat (plano §8)
```
