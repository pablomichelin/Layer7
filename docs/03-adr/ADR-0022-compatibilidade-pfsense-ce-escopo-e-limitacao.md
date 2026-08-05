# ADR-0022 — Compatibilidade pfSense CE: escopo e limitação (Onda E)

**Estado:** Aceito (limitação operacional)  
**Data:** 2026-08-04  
**Contexto:** Onda E do plano mestre de fecho (`passo 6.1`); candidato lab `1.8.11_69`

---

## Contexto

O produto declara alvo **pfSense CE** (Community Edition). O lab Systemup
actual valida gates G2–G7 no host `192.168.100.254`, que é **pfSense Plus
26.03.1** sobre FreeBSD 16.0-CURRENT — não CE.

A matriz canónica (`matriz-compatibilidade-ce-plus-freebsd.md`) exige, como
segundo gate de plataforma, VM CE dedicada com install passivo + smoke monitor
mínimo antes de claim CE-only no artefacto em validação.

A Onda E (`20260804T234500Z-ondaE-ce-parity`) confirmou:

- ausência de VM CE na malha `192.168.100.0/24`;
- lab CE histórico (`192.168.0.195`, CE 2.8.1) inacessível;
- paridade Plus/FB16 para `_69`: ABI OK, G3 PASS; smoke monitor não limpo
  nesta sessão (resíduo PF Onda B).

---

## Decisão

1. **Veredicto Onda E:** **LIMITAÇÃO** — não CE PASS no candidato `1.8.11_69`.
2. **Evidência Plus/FB16** (Ondas A–D) conta como **proxy de runtime**
   FreeBSD/pacote, **não** substitui validação CE física.
3. **Evidência histórica** CE 2.8.1 (`validacao-lab.md`, 2026-03-22) cobre
   versões antigas do port; **não** estende automaticamente o veredicto a `_69`.
4. **GO enforce (Onda F)** exige CE PASS **ou** aceite humano explícito desta
   ADR com ressalva comercial documentada no CORTEX.
5. **Comunicação externa:** até CE PASS, materiais comerciais devem manter
   «compatível com pfSense CE 2.7.x/2.8.x» como *claim de desenho*, com nota
   de que validação física CE do build actual está **pendente** (Plus validado
   em lab).

---

## Alternativas consideradas

| Alternativa | Motivo de rejeição |
|-------------|-------------------|
| Declarar CE PASS só com Plus | Viola matriz e plano mestre; risco comercial |
| Bloquear todo o projecto | Ondas A–D já provam runtime; CE é gate de plataforma |
| Provisionar CE no builder sem GO | Risco operacional no builder; requer decisão humana |

---

## Consequências

### Positivas

- Honestidade técnica e rastreabilidade (evidência `ONDA-E-LIMITACAO.md`).
- Caminho claro: VM CE → reexecutar passo 6.1 → CE PASS.

### Negativas

- Onda F bloqueada para GO automático até CE ou aceite humano.
- Claim CE-only não fechado no artefacto `_69`.

---

## Impacto em compatibilidade

- **Instalação:** inalterada; `MANUAL-INSTALL.md` mantém alvo CE.
- **Lab:** adicionar VM CE à malha é pré-requisito operacional documentado.
- **Release:** sem novo `.pkg`; sem alteração de `PORTREVISION`.

---

## Referências

- `docs/09-blocking/matriz-compatibilidade-ce-plus-freebsd.md`
- `docs/02-roadmap/plano-fecho-producao-e-consolidacao.md` (Onda E, §5)
- `docs/tests/evidence/20260804T234500Z-ondaE-ce-parity/`
- `tests/lab/run-ondaE-ce-parity-appliance.sh`
