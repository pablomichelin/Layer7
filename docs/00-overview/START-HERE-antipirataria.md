# START HERE — Anti-pirataria e Anti-tamper 【`30.13` FECHADO · GA5.1–5.6 **PASS** · **Composer 2.5**】

> **Diagnóstico ACEITE** `2026-08-10` — [`modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md).
> **`30.10`/`30.11` FECHADOS** — e2e `1.9.54`; cut espelho (`asset_count=0`);
> GA4.10/15 **PASS**; GA4.12 **N/A**.
> **`30.12` FECHADO** — contrato check-in assinado/nonce; GA5.1 **PASS**.
> **`30.13` FECHADO** — servidor assina + cliente exige; dual-mode legado;
> GA5.2–5.6 **PASS** (unit); BG-119 **Concluido**.
> Contrato: [`../01-architecture/contrato-check-in-assinado-30.12.md`](../01-architecture/contrato-check-in-assinado-30.12.md).
> Evidência: [`../tests/evidence/20260812T013913Z-30.13-checkin-signed/`](../tests/evidence/20260812T013913Z-30.13-checkin-signed/).
> **Produção `.254`:** **`1.9.54`**. Candidato Makefile **`1.9.55`** (**sem** release).
> **Próximo passo:** **`30.14`** (**GO humano** — default check-in).
> **Proibido:** fail-closed por rede · kill-switch · ofuscação · misturar enforce/MITM/IPv6.
> **Proibido:** abrir `30.14` sem GO humano.
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
| [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) | Gates — GA5.1–5.6 **PASS**; GA5.7+ PENDENTE |
| Contrato `30.12` | [`../01-architecture/contrato-check-in-assinado-30.12.md`](../01-architecture/contrato-check-in-assinado-30.12.md) |
| Contrato `30.8` | [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md) |
| ADR-0032 | [`../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md`](../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md) |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional vivo |

---

## Estado actual (actualizar a cada passo)

| Campo | Valor |
|-------|-------|
| Onda | **AP3 em curso** |
| Passo | **`30.13` FECHADO** (implementação) |
| Campo / e2e | **PASS** — `1.9.54` em `.254` (intocada neste passo) |
| Cut espelho | **PASS** — `asset_count=0`; anónimo 404×4 |
| GA5.1–5.6 | **PASS** — contrato + unit assinatura/nonce/N3 |
| Próxima acção | **`30.14`** com **GO humano** (default check-in) |
| Gate activo | GA5 parcial (5.1–5.6 PASS; 5.7+ PENDENTE) |
| Código produto | candidato **`1.9.55`** (Makefile); release **não** publicada |
| Baseline enforce | **`1.9.8`** |
| License-server 30.13 | código no git (dual-mode); **deploy ops separado** |
| Rev. do plano | **`2026-08-10c`** |

---

## Leitura obrigatória (chat novo → `30.14`)

1. **Este ficheiro**
2. [`AGENTS.md`](../../AGENTS.md)
3. [`CORTEX.md`](../../CORTEX.md) — *Trilha Anti-pirataria*
4. [`plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) — §0, §0.1, §1, §5, §8, passo `30.14`
5. [`contrato-check-in-assinado-30.12.md`](../01-architecture/contrato-check-in-assinado-30.12.md)
6. [`plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) — GA5.7+
7. ADR-0032 + ADR-0021 + ficha [`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md)
8. Evidência [`../tests/evidence/20260812T013913Z-30.13-checkin-signed/`](../tests/evidence/20260812T013913Z-30.13-checkin-signed/)

---

## Prompt — passo `30.14` (chat novo — **só com GO**)

```text
Modelo: Composer 2.5.
Contexto: trilha Anti-pirataria; 30.13 FECHADO; GA5.1-5.6 PASS;
contrato docs/01-architecture/contrato-check-in-assinado-30.12.md;
produção .254 = 1.9.54; candidato 1.9.55 sem release.
Arranque: docs/00-overview/START-HERE-antipirataria.md
AGORA: SOMENTE 30.14 se GO humano explícito (check_in_enabled default +
migração + runbook). Sem GO → parar.
Proibido: fail-closed rede; enforce/MITM/IPv6; ofuscação.
Português.
```

### Prompt — desvio / ADR

```text
Contexto: trilha Anti-pirataria (START-HERE-antipirataria.md).
Proposta de desvio: <descrição>
Impacto / risco / teste / rollback / passo:
Alinhamento R-A..R-L e RR-1..RR-5:
Não implementar até GO. Português.
```

---

## TESTES / GATES

| Onda | Gate | Estado |
|------|------|--------|
| AP0 | GA0/GA1 | **PASS** |
| AP1 | GA2 | **PARCIAL** |
| AP1 | GA3 | **PASS** |
| AP2 | GA4 | **PASS** cut (GA4.10/15); GA4.12 **N/A** |
| AP3 | GA5 | **PARCIAL** — GA5.1–5.6 **PASS**; GA5.7+ **PENDENTE** |

---

## Progresso compacto

```text
TRILHA ANTI-PIRATARIA — progresso
- Diagnóstico: ACEITE 2026-08-10 (A-01..A-10)
- Rev. plano: 2026-08-10c
- Onda: AP3 em curso
- Passo: 30.13 FECHADO (check-in assinado implementado)
- GA5.1-5.6 PASS; GA5.7+ PENDENTE
- Contrato: docs/01-architecture/contrato-check-in-assinado-30.12.md
- Evidência: 20260812T013913Z-30.13-checkin-signed
- 30.11 cut FECHADO; asset_count=0; anon 404x4
- Produção .254: 1.9.54; candidato 1.9.55 (sem release)
- Próximo: 30.14 (GO humano) — sem default check-in sem GO
- Agente: Composer 2.5 — um passo / chat (plano §8)
```

---

## Ligação rápida

| Tema | Documento |
|------|-----------|
| Contrato `30.12` | [`../01-architecture/contrato-check-in-assinado-30.12.md`](../01-architecture/contrato-check-in-assinado-30.12.md) |
| Plano + §8 | [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) |
| Gates | [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) |
| Impl. `30.13` | [`../tests/evidence/20260812T013913Z-30.13-checkin-signed/`](../tests/evidence/20260812T013913Z-30.13-checkin-signed/) |
| Desenho `30.12` | [`../tests/evidence/20260812T013200Z-30.12-protocol-design/`](../tests/evidence/20260812T013200Z-30.12-protocol-design/) |
| ADR-0032 | [`../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md`](../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md) |

---

## Nota de manutenção

Actualizar título, estado, progresso, CORTEX e plano §0 no **mesmo** commit
de cada fecho de passo. Não declarar PASS de gate de código sem evidência de runtime.
