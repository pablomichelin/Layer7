# START HERE — Anti-pirataria e Anti-tamper 【**TRILHA FECHADA** · `30.19` · GA6 **PASS** · **Composer 2.5**】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **Trilha AP0–AP4 FECHADA** em `30.19` (`20260812T025741Z`) — fecho documental
> GA6.7–6.12; **sem** código/PORTVERSION/publish/tags/produção neste passo.
> Doc fecho: [`../01-architecture/fecho-trilha-antipirataria-30.19.md`](../01-architecture/fecho-trilha-antipirataria-30.19.md).
> Evidência: [`../tests/evidence/20260812T025741Z-30.19-fecho/`](../tests/evidence/20260812T025741Z-30.19-fecho/).
> **Produção `.254`:** **`1.9.54`** (intocada neste passo). Candidato Makefile
> **`1.9.58`** (**sem** release) — herança `30.17`.
> **Modo:** manutenção / novos itens só com GO + backlog (não reabrir trilha).
> **Proibido:** fail-closed por rede · kill-switch · ofuscação · overclaim R-A.
> **Honestidade:** root **pode** contornar verificação local (RR-5 / R-A).
> **Artefacto:** **`.pkg`** FreeBSD/pfSense.
> **Rev. plano:** `2026-08-10c` + fecho `30.19`.

```text
docs/00-overview/START-HERE-antipirataria.md
```

### Continuidade em chat limpo

1. Ler este ficheiro + CORTEX + fecho `30.19`.
2. **Não** reabrir AP0–AP4 sem GO humano + item de backlog.
3. Residuais conhecidos: BG-028 Fase 1; GA5.9 campo; parecer EULA externo; RR-3 tags.
4. **Não** misturar com MITM/IPv6/promoção enforce sem GO próprio.

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque pós-fecho |
| [`fecho-trilha-antipirataria-30.19.md`](../01-architecture/fecho-trilha-antipirataria-30.19.md) | Fecho GA6.7–6.12 |
| [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) | SSOT histórico da trilha |
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — GA6 **PASS** |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

---

## Estado actual

| Campo | Valor |
|-------|-------|
| Onda | **AP4 FECHADA** (trilha completa) |
| Passo | **`30.19` FECHADO** |
| GA6 | **PASS** (6.5 residual BG-028; 6.7 residual parecer EULA) |
| Campo / e2e | produção `.254` = `1.9.54` |
| Próxima acção | Manutenção; **não** reabrir sem GO |
| Código produto | candidato **`1.9.58`** sem release; `30.19` só docs |
| Baseline enforce | **`1.9.8`** |

---

## Progresso compacto

```text
TRILHA ANTI-PIRATARIA — FECHADA
- Diagnóstico: ACEITE 2026-08-10 (A-01..A-10)
- Passo final: 30.19 FECHADO (20260812T025741Z)
- GA6.1-6.12 PASS (residuais: BG-028 campo; parecer EULA externo)
- Evidência: 20260812T025741Z-30.19-fecho
- Produção .254: 1.9.54; candidato 1.9.58 (sem release)
- Não reabrir sem GO + backlog
```
