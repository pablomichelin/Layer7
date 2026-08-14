# START HERE — Anti-pirataria e Anti-tamper 【**ENGENHARIA FECHADA** · `30.19` · **EVIDÊNCIA OPERACIONAL ABERTA** · BG-127】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **Engenharia AP0–AP4 FECHADA** em `30.19` (`20260812T025741Z`) — fecho
> documental GA6.7–6.12. **Não** reabrir código / PORTVERSION / AP0–AP4.
> Doc fecho: [`../01-architecture/fecho-trilha-antipirataria-30.19.md`](../01-architecture/fecho-trilha-antipirataria-30.19.md).
> Evidência fecho: [`../tests/evidence/20260812T025741Z-30.19-fecho/`](../tests/evidence/20260812T025741Z-30.19-fecho/).
> **GO `2026-08-14`:** ciclo de **evidência operacional** aberto — **BG-127**
> (GA2.6, GA2.7, GA3.7, GA4.8, GA5.9). Campanhas **PARTIAL**
> [`20260814T051611Z-bg127`](../tests/evidence/20260814T051611Z-bg127/) +
> [`20260814T053905Z-bg127`](../tests/evidence/20260814T053905Z-bg127/)
> (GA2.7 **PASS**; GA5.9 **PASS** `20260814T143406Z`).
> **GA6.7** = parecer jurídico **externo**.
> **`.254` vivo:** **`1.9.63`** `mode=monitor` MITM **OFF**
> ([`20260814T034904Z-20.36-soak-align-163-254`](../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/)).
> Histórico e2e AP2: `.254` = `1.9.54` (`20260811T114320Z`) — **não** é o estado vivo.
> Hosts `.54` / `.254`: **fora do horário comercial**. Publicar **só** se
> necessidade técnica verificada.
> **P0-1 ACTIVO (`2026-08-14`):** proibido deploy integral do HEAD sobre o
> `.244` enquanto o serving `30.11` live não estiver versionado —
> [`auditoria-licencas-auth-deploy-2026-08-14.md`](../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md)
> + [`bloqueio-deploy-integral-head-30.11.md`](../13-runbooks/bloqueio-deploy-integral-head-30.11.md).
> Worktree sujo `30.11` **preservar**. **BG-128** aberto; **P0-2, P1-1, P1-2,
> P1-3, P1-4 e P2-1 FEITOS** no git (`2026-08-14`; sem deploy). Próximo
> código com GO = **P1-5** ou commit allowlist `30.11`.
> **Proibido neste ciclo:** MITM · enfraquecer segurança · falsear · apagar
> dados · reset/rebase/stash · reabrir engenharia · rsync/rebuild integral HEAD.
> **Honestidade:** root **pode** contornar verificação local (RR-5 / R-A).
> **Artefacto:** **`.pkg`** FreeBSD/pfSense.
> **Rev. plano:** `2026-08-10c` + fecho `30.19` + GO evidência `2026-08-14`.

```text
docs/00-overview/START-HERE-antipirataria.md
```

### Continuidade em chat limpo

1. Ler este ficheiro + CORTEX + fecho `30.19` + **BG-127** + **BG-128**.
2. **Não** reabrir AP0–AP4. O GO `2026-08-14` autoriza evidência de campo.
   **P0-2, P1-1, P1-2, P1-3, P1-4 e P2-1 FEITOS** no git. Código seguinte
   com GO = **P1-5** ou commit allowlist `30.11` (BG-128), sem `.244`
   neste bloco.
3. **P0-1 ACTIVO:** proibido deploy integral do HEAD. Worktree sujo 30.11
   **preservar**.
4. Residuais campo: ciclo **BG-127**; parecer EULA externo (GA6.7); RR-3 tags.
5. **Não** misturar com MITM/IPv6/promoção enforce sem GO próprio.

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque (engenharia fechada / evidência aberta) |
| [`fecho-trilha-antipirataria-30.19.md`](../01-architecture/fecho-trilha-antipirataria-30.19.md) | Fecho de engenharia GA6.7–6.12 |
| [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) | SSOT histórico da trilha |
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — engenharia PASS; campo = BG-127 |
| [`backlog.md`](../02-roadmap/backlog.md) | **BG-127** (ciclo evidência); **BG-128** (auditoria / P0-1) |
| [`auditoria-licencas-auth-deploy-2026-08-14.md`](../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md) | Achados P0–P3 + freeze P0-1 |
| [`bloqueio-deploy-integral-head-30.11.md`](../13-runbooks/bloqueio-deploy-integral-head-30.11.md) | Runbook: proibido deploy integral HEAD |
| [`evidencia-operacional-antipirataria-bg127.md`](../13-runbooks/evidencia-operacional-antipirataria-bg127.md) | Runbook de campo (`.54`→`.254`) |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

---

## Estado actual

| Campo | Valor |
|-------|-------|
| Engenharia | **AP4 / `30.19` FECHADA** |
| Ciclo evidência | **ABERTO** — **BG-127** (GO `2026-08-14`); PARTIAL `20260814T051611Z` + `20260814T053905Z` |
| Gates de campo | GA2.6 **PASS parcial** (monitor) + enforce **DEFERRED**; GA2.7 **PASS**; GA3.7 **PASS**; GA4.8 **DEFERRED**; GA5.9 **PASS** (`20260814T143406Z`) |
| Fora deste ciclo | **GA6.7** (parecer EULA externo) |
| `.254` vivo | **`1.9.63`** `mode=monitor` MITM **OFF** (`20260814T034904Z-20.36-soak-align-163-254`; reconfirmado `20260814T053905Z`) |
| Histórico e2e AP2 | `.254` = `1.9.54` (`20260811T114320Z`) |
| lab/`latest` | **`1.9.63`** |
| Baseline enforce | **`1.9.8`** |
| Freeze deploy | **P0-1 ACTIVO** — sem rsync/rebuild integral HEAD→`.244` |
| Próxima acção código | **P1-5** ou commit allowlist `30.11` (BG-128). **P0-2, P1-1, P1-2, P1-3, P1-4 e P2-1 FEITOS** no git (sem deploy) |
| Residual campo BG-127 | GA2.6 enforce / GA4.8 só com janela própria que não arrisque tráfego |

---

## Progresso compacto

```text
ANTI-PIRATARIA — ENGENHARIA FECHADA / EVIDÊNCIA OPERACIONAL ABERTA
- Diagnóstico: ACEITE 2026-08-10 (A-01..A-10)
- Engenharia: 30.19 FECHADO (20260812T025741Z)
- Evidência fecho: 20260812T025741Z-30.19-fecho
- Ciclo evidência: BG-127 ABERTO (GO 2026-08-14); PARTIAL 20260814T051611Z + 20260814T053905Z
- Gates campo: GA2.6 PASS parcial (monitor); GA2.7 PASS; GA3.7 PASS; GA4.8 DEFERRED; GA5.9 PASS 20260814T143406Z
- Auditoria 2026-08-14: P0-1 ACTIVO; BG-128 ABERTO; P0-2, P1-1, P1-2, P1-3, P1-4 e P2-1 FEITOS no git; próximo código com GO = P1-5 ou commit allowlist 30.11
- GA6.7: parecer EULA externo (fora do BG-127)
- .254 vivo: 1.9.63 mode=monitor MITM OFF (20260814T034904Z-20.36-soak-align-163-254)
- Histórico e2e: .254=1.9.54 (20260811T114320Z)
```
