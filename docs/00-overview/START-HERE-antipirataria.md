# START HERE — Anti-pirataria e Anti-tamper 【`30.18` FECHADO · GA6.5 processo / GA6.6 **PASS** · **Composer 2.5**】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **AP3 código FECHADA** (`30.12`–`30.15`). **`30.16`/`30.17` FECHADOS**.
> **`30.18` FECHADO** — cadeia F1.2 obrigatória no *processo* de release
> (BG-123); dry-run sign/verify **PASS**; **sem** GitHub Release neste bloco.
> GA6.5 **PASS (processo)** + residual campo até BG-028/ADR-0023 Fase 1;
> GA6.6 **PASS**. Evidência:
> [`../tests/evidence/20260812T024826Z-30.18-release-signing/`](../tests/evidence/20260812T024826Z-30.18-release-signing/).
> **Produção `.254`:** **`1.9.54`** (intocada). Candidato **`1.9.58`** (**sem** release).
> **Próximo passo:** AP4 **`30.19`** — **só** com pedido explícito (**não** aberto).
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
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — GA6.1–6.6 (6.5 processo); restante → `30.19` |
| F1.2 release | [`../06-releases/RELEASE-SIGNING.md`](../06-releases/RELEASE-SIGNING.md) |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

---

## Estado actual (actualizar a cada passo)

| Campo | Valor |
|-------|-------|
| Onda | **AP4 em curso** |
| Passo | **`30.18` FECHADO** (F1.2 processo / BG-123) |
| GA6.1–6.6 | **PASS** (6.5 = processo + residual campo/BG-028) |
| GA5.9 | **PENDENTE campo** (sem `.254`/release) |
| Campo / e2e | produção `.254` = `1.9.54` intocada |
| Próxima acção | AP4 **`30.19`** — **sob pedido** (**não** iniciado) |
| Código produto | candidato **`1.9.58`**; release **não** publicada; `30.18` sem bump |
| Baseline enforce | **`1.9.8`** |
| Rev. do plano | **`2026-08-10c`** |

---

## Leitura obrigatória (chat novo → `30.19` sob pedido)

1. **Este ficheiro**
2. [`AGENTS.md`](../../AGENTS.md)
3. [`CORTEX.md`](../../CORTEX.md) — *Trilha Anti-pirataria*
4. [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) — §0, passo `30.19`
5. [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) — GA6.7+
6. Evidência [`../tests/evidence/20260812T024826Z-30.18-release-signing/`](../tests/evidence/20260812T024826Z-30.18-release-signing/)

---

## Prompt — passo `30.19` (chat novo · só com pedido explícito)

```text
Modelo: Composer 2.5.
Contexto: trilha Anti-pirataria; 30.18 FECHADO (F1.2 processo; GA6.5 processo + residual BG-028; GA6.6 PASS);
produção .254 = 1.9.54; candidato 1.9.58 sem release.
Arranque: docs/00-overview/START-HERE-antipirataria.md
AGORA: implementar SOMENTE 30.19 se e só se o humano pediu esse cartão.
Proibido: fail-closed rede; MITM/IPv6; ofuscação; mexer .254/CF/DNS sem obrigação do cartão.
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
| AP4 | GA6 | **PARCIAL** — 6.1–6.6 **PASS** (6.5 processo + residual); restante → `30.19` |

---

## Progresso compacto

```text
TRILHA ANTI-PIRATARIA — progresso
- Diagnóstico: ACEITE 2026-08-10 (A-01..A-10)
- Rev. plano: 2026-08-10c
- Onda: AP4 em curso
- Passo: 30.18 FECHADO (F1.2 processo; GA6.5 processo + residual BG-028; GA6.6 PASS)
- Evidência: 20260812T024826Z-30.18-release-signing
- Produção .254: 1.9.54; candidato 1.9.58 (sem release)
- Próximo: AP4 30.19 (só com pedido explícito; NÃO aberto)
- Agente: Composer 2.5 — um passo / chat (plano §8)
```
