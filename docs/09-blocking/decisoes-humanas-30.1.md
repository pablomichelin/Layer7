# Ficha — Decisões humanas do passo 30.1

**Trilha:** Anti-pirataria / Anti-tamper  
**Passo:** `30.1a` criou esta ficha; **`30.1b` FECHADO** (`2026-08-10`) — GO humano aplicado  
**Plano SSOT:** [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) §5  
**ADRs:**  
[ADR-0030](../03-adr/ADR-0030-postura-anti-tamper-layer7d.md) ·
[ADR-0031](../03-adr/ADR-0031-entitlement-entrega-conteudo.md) ·
[ADR-0032](../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md) ·
[ADR-0033](../03-adr/ADR-0033-anti-rollback-relogio.md) — estado **`Aceito`**  
**Gates:** GA0.4 / GA0.5 / GA0.7 → **PASS**  
**Código:** nenhum neste passo

> **GO humano:** operador confirmou «concordo com tudo» (recomendações do plano)
> em `2026-08-10`. Agente aplicou apenas esse ditado.  
> **RR-1:** decisões **1** e **3** = **Sim** — a trilha **não** se limita a higiene;
> protecção T1/T2 continua condicionada à execução dos GOs próprios `30.11`/`30.14`.

---

## Decisão 1 — Dependência de rede para o produto

| Campo | Valor |
|-------|--------|
| Natureza | **Comercial** |
| Passo afectado | `30.14` (check-in default ON) |
| Pergunta | Aceitar criar dependência periódica de rede (HTTPS ao license server) para o produto em instalações novas? |
| Recomendação do plano | **Sim**, com caminho de excepção para isolados (**R-J**) e **nunca** fail-closed (**R-C**) |
| Opções | `Sim` · `Não` · `Sim com condições (especificar em Notas)` |

**Decisão humana:** Sim  
**Data:** 2026-08-10  
**Notas:** Aceite a recomendação do plano. Excepção para isolados (R-J); nunca fail-closed (R-C). GO próprio de execução permanece em `30.14`.

---

## Decisão 2 — Política de migração / clientes isolados

| Campo | Valor |
|-------|--------|
| Natureza | Comercial + suporte |
| Passo afectado | `30.14` |
| Pergunta | Qual a política de migração de clientes já instalados para check-in activo? Existem clientes genuinamente isolados (air-gap) que exigem opt-out permanente? |
| Recomendação do plano | **Novos = ON**; existentes = migração anunciada + opt-out documentado se isolados |
| Opções | `Novos ON + migração anunciada` · `Só novos; existentes ficam OFF até opt-in` · `Outro (Notas)` |

**Decisão humana:** Novos ON + migração anunciada  
**Data:** 2026-08-10  
**Notas:** Aceite a recomendação. Opt-out documentado para isolados. Detalhe operacional no passo `30.14`.

---

## Decisão 3 — Retirar o espelho público de conteúdo corrente

| Campo | Valor |
|-------|--------|
| Natureza | Comercial + suporte |
| Passo afectado | `30.11` (GO próprio AP2) |
| Pergunta | Retirar ou limitar o espelho público (ex.: GitHub `blacklists-ut1-current`) de conteúdo **corrente**, de modo que o token de AP2 não seja decorativo? |
| Recomendação do plano | **Sim** — sem isto AP2 é teatro (**RR-1**) |
| Opções | `Sim` · `Não` · `Sim com janela de transição (Notas)` |

**Decisão humana:** Sim  
**Data:** 2026-08-10  
**Notas:** Aceite a recomendação. GO próprio de execução permanece em `30.11` (janela de transição e comunicação a clientes nesse passo).

---

## Decisão 4 — Residência da chave pública de produção

| Campo | Valor |
|-------|--------|
| Natureza | Técnica + segurança |
| Passo afectado | `30.2` (**bloqueio duro** antes de `license.c`) |
| Pergunta | Onde vive a chave pública de produção usada no build: versionada no repo privado, ou fora do git (ficheiro/flag no builder)? |
| Recomendação do plano | Preferir **fora do git** (ficheiro/flag de build no builder) até haver processo de rotação; versionar só o *procedimento* |
| Opções | `Fora do git (builder)` · `Repo privado versionada` · `Híbrido (Notas)` |

**Decisão humana:** Fora do git (builder)  
**Data:** 2026-08-10  
**Notas:** Aceite a recomendação. Procedimento versionado em docs; material da pubkey fora do git. Aplicar em `30.2`.

---

## Decisão 5 — Reabrir BG-101

| Campo | Valor |
|-------|--------|
| Natureza | Classificação / backlog |
| Passo afectado | `30.1b` (GA0.7) |
| Pergunta | Reabrir **BG-101** (hoje `Documentado — não é bug`: check-in default OFF / revogação sem efeito até expiry+grace)? |
| Recomendação do plano | Reabrir como **lacuna comercial a corrigir** via ADR-0032 / BG-118 |
| Opções | `Reabrir como lacuna comercial` · `Manter Documentado (justificar em Notas)` |

**Decisão humana:** Reabrir como lacuna comercial  
**Data:** 2026-08-10  
**Notas:** Corrigir via ADR-0032 / BG-118 (`30.14`). Estado no backlog actualizado neste `30.1b`.

---

## Decisão 6 — Revisão jurídica da EULA

| Campo | Valor |
|-------|--------|
| Natureza | Jurídica (externa) |
| Passo afectado | `30.19` |
| Pergunta | Agendar revisão jurídica da EULA quanto a auditoria e penalidades por instalação excedente? |
| Recomendação do plano | **Agendar**; **não** bloqueia AP1/AP2 |
| Opções | `Agendar (prazo em Notas)` · `Adiar sem data` · `Já coberta (referência em Notas)` |

**Decisão humana:** Agendar  
**Data:** 2026-08-10  
**Notas:** Aceite. Não bloqueia AP1/AP2; execução/fecho no `30.19`.

---

## Decisão 7 — `max_activations` vs só alerta

| Campo | Valor |
|-------|--------|
| Natureza | Comercial |
| Passo afectado | `30.15` |
| Pergunta | Na detecção de abuso multi-appliance, introduzir hard-limit `max_activations` ou manter só alerta na fase 1? |
| Recomendação do plano | **Fase 1 = só alerta**; `max_activations` só após falsos positivos medidos |
| Opções | `Só alerta (fase 1)` · `max_activations já na fase 1 (limite em Notas)` · `Outro` |

**Decisão humana:** Só alerta (fase 1)  
**Data:** 2026-08-10  
**Notas:** Aceite a recomendação. `max_activations` só após medir falsos positivos.

---

## Decisão 8 — Releases antigas com caminho `is_dev_key` (RR-3)

| Campo | Valor |
|-------|--------|
| Natureza | Comercial + segurança |
| Passo afectado | `30.3` (insumo) → `30.19` (execução) |
| Pergunta | Despublicar ou limitar downloads das releases antigas que ainda contêm o caminho de bypass `is_dev_key`? |
| Recomendação do plano | Inventariar em `30.3`; **preferir** deixar de apontar `latest`/docs para versões com bypass e documentar risco residual das tags antigas |
| Opções | `Limitar apontadores (latest/docs); manter tags com aviso` · `Despublicar assets antigos afectados` · `Nada agora; só inventário` · `Outro` |

**Decisão humana:** Limitar apontadores (latest/docs); manter tags com aviso  
**Data:** 2026-08-10  
**Notas:** Inventário em `30.3`; execução/fecho em `30.19`. Risco residual das tags antigas permanece declarado (RR-3).

---

## Fecho do 30.1b (GO)

| Campo | Valor |
|-------|--------|
| ADRs 0030–0033 → `Aceito`? | **Sim** (`2026-08-10`) |
| Emendas registadas? | ADR-0032 emenda ADR-0021; ADR-0033 emenda `f3-expiracao-revogacao-grace.md` (doc) |
| GA0.4 / GA0.5 / GA0.7 | **PASS** |
| Declaração RR-1 (se dec. 1 ou 3 = Não) | N/A — dec. 1 e 3 = **Sim** |
| Operador / data do GO | Operador (chat); «concordo com tudo»; `2026-08-10` |

**Próximo após GO:** `30.2` — reconciliação builder ↔ repo (pubkey fora do git, conforme decisão 4).
**Estado:** `30.2` **FECHADO** (`2026-08-10`) — evidência
`docs/tests/evidence/20260810T231840Z-30.2-builder-pubkey/`. Próximo: **`30.3`**.
