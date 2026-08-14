# ADR-0035 — Ambição de paridade NGFW no tempo + retirada da ficha como gate

**Estado:** Aceito  
**Data:** 2026-08-14  
**Aceite:** `2026-08-14` — GO operador (chat Identity + MITM)  
**Decisores:** Operador (Systemup)  
**Emenda:** [`ADR-0026`](ADR-0026-mitm-tls-inspection-opt-in.md) (ponto 12 + gate ficha)  
**Posicionamento:** [`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md)  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**Arranque:** [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)

---

## Contexto

A trilha MITM usava uma **ficha de site** (P1 §3 / P5) como gate de activação
externa: papel interno com cliente, responsáveis, SOURCE, DEST, SNI, janela e
critérios de saída. O operador rejeitou esse gate: **ninguém o utiliza** e
nenhum NGFW do mercado pede um formulário para ligar SSL inspection.

O posicionamento PME (`2026-08-06`) congelou ainda a frase «não pretender ser
um NGFW enterprise» e «paridade TLS fora do objectivo». Isso foi lido — e
usado pelos agentes — como **tecto permanente**. O operador clarificou:

- o estado **actual** ainda não é paridade Fortinet / Palo Alto / Check Point;
- o **objectivo futuro** é evoluir até ser tão bom quanto, ou melhor;
- recusar evoluir porque «nunca seremos iguais» é um tiro no pé.

---

## Decisão

1. **Ficha RETIRADA como gate.** P5 «aguarda ficha» fica **cancelado**.
   Activar MITM no produto = entitlement `mitm` + GUI + `mitm_effective`
   (controlos de software), **não** um formulário em Markdown.
2. **Ambição canónica:** o Layer7 **persegue paridade de capacidade** com
   NGFW (e, no tempo, superá-la no nicho pfSense/PME/MSP). Evoluir é o
   caminho normal do produto.
3. **Honestidade de estado ≠ tecto.** Materiais e GUI **não** podem afirmar
   que **já** temos paridade App-ID / decrypt universal / ASIC. **Podem e
   devem** afirmar o rumo: melhorar até lá.
4. **Segurança fica no produto**, não no papel:
   - default OFF; sem activar por upgrade;
   - rdr só com `source_cidr` ∧ `dest_cidr` (proibido `from any`);
   - sem persistir payload TLS; CA/privkey fora do git;
   - Squid continua **rejeitado**.
5. Este ADR **não** liga MITM permanente no `.254` / `.234` / `.235`.
   **20.34** = docs. **20.35 PASS** = GUI como política (até desligar +
   copy operador); `1.9.63` é o canal lab/`latest` desta decisão.
6. **Melhorar todos os dias, sem tecto.** Não existe trava documental
   que diga «já chega» ou «não podemos ser melhores amanhã». A barra
   sobe com o produto. Segurança e honestidade de estado **não** são
   tecto — são o chão a partir do qual se evolui.

---

## O que isto NÃO decide

- Ligar intercept agora em lab ou cliente.
- Prometer paridade já atingida em marketing.
- Reabrir Identity de rede ou agente endpoint.
- Aceitar Squid.
- Remover failsafe / supervisor / `max_window` do código (são features, não ficha).

---

## Consequências

- Agentes **proibidos** de bloquear trabalho com «falta a ficha».
- Agentes **proibidos** de tratar «não somos NGFW» como destino permanente
  ou de recusar um bloco com «já somos bons o suficiente».
- D1–D9 do GO-escopo passam a **princípios de produto** (escopo, privacidade,
  break-glass), não a um formulário obrigatório.
- ADR-0026 ponto 12 («fora do objectivo: paridade NGFW») fica **substituído**
  por este ADR.

---

## Rollback

Reverter este ADR + restauro do texto P5/ficha nos SSOT (START-HERE, CORTEX,
plano, mapa). Sem impacto runtime.

---

## Histórico

| Data | Nota |
|------|------|
| 2026-08-14 | Aceite — GO operador: ficha fora; ambição de paridade no tempo |
| 2026-08-14 | **20.35 PASS** — GUI até desligar; copy operador; `1.9.63` |
| 2026-08-14 | Princípio permanente: melhorar todos os dias, sem tecto |
