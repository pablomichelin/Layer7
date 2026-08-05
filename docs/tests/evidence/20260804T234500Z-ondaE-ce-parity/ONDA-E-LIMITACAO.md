# Onda E — relatório de limitação CE

**run_id:** `20260804T234500Z-ondaE-ce-parity`  
**Data:** 2026-08-04  
**Passo plano:** 6.1  
**Veredicto:** **LIMITAÇÃO** (não CE PASS)

---

## 1. Resumo executivo

A Onda E exige validação física em **pfSense CE** (VM dedicada). A malha lab
actual (`192.168.100.0/24`) só expõe **pfSense Plus 26.03.1** em
`192.168.100.254`. O lab CE histórico (`192.168.0.195`, CE 2.8.1) está
inacessível. Sem VM CE, o claim CE-only do candidato `1.8.11_69` **não pode**
ser fechado como PASS nesta onda.

Foi executada **validação de paridade** no Plus/FB16 (proxy): pacote `_69`
instalado, binário executável, parser PF OK. Smoke monitor mínimo **não**
fechou limpo nesta sessão por resíduo de regras PF da Onda B (blacklist
`g5-test-bl`) — limpeza em VM CE limpa ou snapshot dedicado recomendada.

---

## 2. Objectivo

| Item | Valor |
|------|-------|
| Objectivo | Validar compatibilidade pfSense CE (install passivo + smoke monitor) |
| SSOT | `docs/09-blocking/matriz-compatibilidade-ce-plus-freebsd.md` |
| Candidato | `1.8.11_69` |
| Modo | Agente único + appliance |

---

## 3. Impacto

- Claim comercial **CE-only** permanece **não provado** no artefacto actual.
- Gates G2–G7 no Plus **mantêm-se válidos** como evidência de runtime FB16.
- **GO Onda F** permanece bloqueado até CE PASS ou decisão humana com ADR-0022.

---

## 4. Risco

| Risco | Severidade | Mitigação |
|-------|------------|-----------|
| Divergência API PHP CE vs Plus | Média | VM CE dedicada antes de GO |
| ABI FB15 pkg em FB16 | Baixa (observado PASS com `IGNORE_OSVERSION`) | Documentado na matriz |
| Regressão GUI só em CE | Média | Teste físico CE pendente |

---

## 5. Teste executado

1. Sonda rede `192.168.100.x` + lab histórico — **sem CE**
2. Inventário Plus `254` — Plus 26.03.1, `_69` instalado
3. Script `run-ondaE-ce-parity-appliance.sh` — monitor temporário + restore
4. G2 passivo `enabled=false` — blocks PF Layer7 ainda presentes (resíduo G5)

---

## 6. Rollback

- Config appliance: restaurada a `enforce` / `enabled=true` após cada bloco.
- Sem alteração de código nem `PORTREVISION`.
- Sem release GitHub (não exigida).

---

## 7. Decisão recomendada

| Opção | Acção |
|-------|-------|
| A (preferida) | Provisionar VM pfSense CE 2.7.x/2.8.x na malha lab; reexecutar 6.1 |
| B | Aceitar ADR-0022 (proxy Plus + histórico CE 2.8.1) com ressalva comercial |
| C | Adiar GO Onda F; avançar Onda G (F5) em paralelo documental |

---

## 8. Referências

- ADR-0022 — `docs/03-adr/ADR-0022-compatibilidade-pfsense-ce-escopo-e-limitacao.md`
- Evidência histórica: `docs/04-package/validacao-lab.md` (2026-03-19, CE 2.8.1)
- Matriz: `docs/09-blocking/matriz-compatibilidade-ce-plus-freebsd.md`
