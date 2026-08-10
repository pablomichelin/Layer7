# ADR-0033 — Anti-rollback de relógio e estado temporal suspeito

**Estado:** Aceito  
**Data:** 2026-08-10  
**Aceite:** `2026-08-10` — passo **`30.1b`**; GO humano («concordo com tudo» / recomendações do plano); ficha [`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md)  
**Trilha:** Anti-pirataria / Anti-tamper (AP1 / passo `30.6`)  
**Plano:** [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md)  
**Diagnóstico:** [`../01-architecture/modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md) (A-03)  
**Emenda documental prevista:** `f3-expiracao-revogacao-grace.md` (comportamento temporal)  
**Gates:** GA3  
**Backlog:** BG-116  
**RR obrigatórios neste texto:** RR-4, R-J

---

## Contexto

A validade da licença usa `time(NULL)`/`mktime` sem marca do maior timestamp já
observado (achado **A-03**). Com o grace de 14 dias, um ex-cliente (T2) pode
prolongar licença expirada com `date` — custo de exploração trivial.

A limitação já era reconhecida em `f3-expiracao-revogacao-grace.md`. Este ADR
propõe o controlo de baixo custo da onda AP1 e declara honestamente o que
**não** fecha.

---

## Decisão

### 1. Marca persistente do maior timestamp observado (`30.6`)

1. Persistir em `/var/db/` (ou caminho equivalente canónico) o maior timestamp
   já observado pelo daemon.
2. Relógio a avançar normalmente ⇒ zero efeito.
3. Retrocesso **pequeno** (ajuste NTP legítimo) ⇒ tolerado, sem alarme.
4. Retrocesso **grande** (acima de limiar conservador) ⇒ estado temporal
   **suspeito**: degradação para **monitor** + evento de auditoria.
5. Daemon **nunca** termina nem entra em crash por estado temporal (**R-C** /
   alinhamento N2).

### 2. Recuperação para erro honesto (**R-J**)

1. Relógio corrigido (NTP / operador) + reinício do serviço ⇒ recuperação
   documentada e testável sem contactar suporte (**N6**).
2. Runbook obrigatório antes de declarar GA3 PASS.
3. Rollback ao `.pkg` anterior ignora o ficheiro de estado sem erro (**N7**).

### 3. Limites e riscos residuais (obrigatório) — **RR-4**

Este mecanismo **encarece** o truque casual do `date`. **Não** contém o T2
técnico. Evasões conhecidas a declarar no ADR, no runbook e no gate GA3.9:

| Evasão | Porquê |
|--------|--------|
| (a) Root apaga o ficheiro de estado em `/var/db/` | Verificação local sob controlo do adversário (**R-A**) |
| (b) Relógio **congelado/atrasado desde a instalação** | A marca detecta *retrocesso*, não um clock que nunca avançou o suficiente |

**Fecho real do vector:** AP3 (check-in obrigatório/assinado) — o servidor
conhece a hora real e o estado da licença. Sem AP3, `30.6` é higiene temporal.

---

## Consequências

### Positivas

- Prolongar licença com `date` deixa de ser trivial para T2 casual.
- Erro honesto de relógio permanece recuperável (**R-J**).
- Sem crash / sem fail-closed.

### Negativas / riscos

- Falso positivo (NTP agressivo / VM suspensa) pode degradar cliente legítimo —
  mitiga-se com limiar conservador.
- Evasões RR-4 permanecem; overclaim é proibido.
- Dependência de AP3 para fecho real do vector.

---

## Alternativas consideradas

| Alternativa | Rejeitada porque |
|-------------|------------------|
| Ignorar A-03 (só grace por data) | Exploração trivial T2 permanece |
| Matar o daemon se relógio suspeito | Viola disponibilidade / R-C |
| Afirmar que anti-rollback “resolve” clock skew malicioso | Viola **RR-4** |
| Exigir NTP obrigatório fail-closed | Ambientes legítimos sem NTP / air-gap (**R-J**) |

---

## Implementação prevista

- Passo `30.6` após `30.2` FECHADO (toca `license.c`).
- Ficheiros: `license.c` / `license.h`, estado em `/var/db/`, GUI de estado, runbook.
- Gate GA3; GA3.9 exige declaração explícita das evasões RR-4.
- Rollback: `.pkg` anterior.

## Referências

- Plano §0.1 (RR-4), §2 passo 30.6, §1 N6/N7
- `docs/01-architecture/f3-expiracao-revogacao-grace.md`
- ADR-0032 (fecho real via check-in)
- Ficha [`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md)
