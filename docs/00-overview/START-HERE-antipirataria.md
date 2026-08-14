# START HERE — Anti-pirataria e Anti-tamper 【**ENGENHARIA FECHADA** · `30.19` · **EVIDÊNCIA OPERACIONAL ABERTA** · BG-127】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **Engenharia AP0–AP4 FECHADA** em `30.19` (`20260812T025741Z`) — fecho
> documental GA6.7–6.12. **Não** reabrir código / PORTVERSION / AP0–AP4.
> Doc fecho: [`../01-architecture/fecho-trilha-antipirataria-30.19.md`](../01-architecture/fecho-trilha-antipirataria-30.19.md).
> Evidência fecho: [`../tests/evidence/20260812T025741Z-30.19-fecho/`](../tests/evidence/20260812T025741Z-30.19-fecho/).
> **GO `2026-08-14`:** ciclo de **evidência operacional** aberto — **BG-127**
> (GA2.6, GA2.7, GA3.7, GA4.8, GA5.9). **GA6.7** = parecer jurídico **externo**.
> **`.254` vivo:** **`1.9.63`** `mode=monitor` MITM **OFF**
> ([`20260814T034904Z-20.36-soak-align-163-254`](../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/)).
> Histórico e2e AP2: `.254` = `1.9.54` (`20260811T114320Z`) — **não** é o estado vivo.
> Hosts `.54` / `.254`: **fora do horário comercial**. Publicar **só** se
> necessidade técnica verificada.
> **Proibido neste ciclo:** MITM · enfraquecer segurança · falsear · apagar
> dados · reset/rebase/stash · reabrir engenharia.
> **Honestidade:** root **pode** contornar verificação local (RR-5 / R-A).
> **Artefacto:** **`.pkg`** FreeBSD/pfSense.
> **Rev. plano:** `2026-08-10c` + fecho `30.19` + GO evidência `2026-08-14`.

```text
docs/00-overview/START-HERE-antipirataria.md
```

### Continuidade em chat limpo

1. Ler este ficheiro + CORTEX + fecho `30.19` + **BG-127**.
2. **Não** reabrir AP0–AP4 / código. O GO `2026-08-14` autoriza **só** evidência.
3. Residuais: ciclo **BG-127**; parecer EULA externo (GA6.7); RR-3 tags.
4. **Não** misturar com MITM/IPv6/promoção enforce sem GO próprio.

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque (engenharia fechada / evidência aberta) |
| [`fecho-trilha-antipirataria-30.19.md`](../01-architecture/fecho-trilha-antipirataria-30.19.md) | Fecho de engenharia GA6.7–6.12 |
| [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) | SSOT histórico da trilha |
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — engenharia PASS; campo = BG-127 |
| [`backlog.md`](../02-roadmap/backlog.md) | **BG-127** (ciclo evidência) |
| [`evidencia-operacional-antipirataria-bg127.md`](../13-runbooks/evidencia-operacional-antipirataria-bg127.md) | Runbook de campo (`.54`→`.254`) |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

---

## Estado actual

| Campo | Valor |
|-------|-------|
| Engenharia | **AP4 / `30.19` FECHADA** |
| Ciclo evidência | **ABERTO** — **BG-127** (GO `2026-08-14`) |
| Gates de campo | GA2.6, GA2.7, GA3.7, GA4.8, GA5.9 — **PENDENTE/DEFERRED**; **nenhum** PASS neste bloco |
| Fora deste ciclo | **GA6.7** (parecer EULA externo) |
| `.254` vivo | **`1.9.63`** `mode=monitor` MITM **OFF** (`20260814T034904Z-20.36-soak-align-163-254`) |
| Histórico e2e AP2 | `.254` = `1.9.54` (`20260811T114320Z`) |
| lab/`latest` | **`1.9.63`** |
| Baseline enforce | **`1.9.8`** |
| Próxima acção | Evidência operacional BG-127 (**não** executada neste bloco documental) |

---

## Progresso compacto

```text
ANTI-PIRATARIA — ENGENHARIA FECHADA / EVIDÊNCIA OPERACIONAL ABERTA
- Diagnóstico: ACEITE 2026-08-10 (A-01..A-10)
- Engenharia: 30.19 FECHADO (20260812T025741Z)
- Evidência fecho: 20260812T025741Z-30.19-fecho
- Ciclo evidência: BG-127 ABERTO (GO 2026-08-14)
- Gates campo: GA2.6, GA2.7, GA3.7, GA4.8, GA5.9
- GA6.7: parecer EULA externo (fora do BG-127)
- .254 vivo: 1.9.63 mode=monitor MITM OFF (20260814T034904Z-20.36-soak-align-163-254)
- Histórico e2e: .254=1.9.54 (20260811T114320Z)
```
