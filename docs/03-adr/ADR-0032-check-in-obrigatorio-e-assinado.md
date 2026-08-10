# ADR-0032 — Check-in obrigatório por defeito e resposta assinada com anti-replay

**Estado:** Aceito  
**Data:** 2026-08-10  
**Aceite:** `2026-08-10` — passo **`30.1b`**; GO humano («concordo com tudo» / recomendações do plano); ficha [`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md) — dec. 1/2 = Sim (GO execução `30.14`); BG-101 reaberto  
**Tipo:** **Emenda** a [`ADR-0021-check-in-online-e-revogacao-remota.md`](ADR-0021-check-in-online-e-revogacao-remota.md)  
**Trilha:** Anti-pirataria / Anti-tamper (AP3)  
**Plano:** [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md)  
**Diagnóstico:** [`../01-architecture/modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md) (A-04, A-05, A-08)  
**Gates:** GA5 (passos `30.12`–`30.15`)  
**Backlog:** BG-118, BG-119, BG-121; reclassificação **BG-101** no `30.1b`  
**RR obrigatórios neste texto:** RR-1, RR-5, R-C

---

## Contexto

A ADR-0021 entregou check-in online e revogação remota (**implementado**,
S14 PASS). Dois gaps comerciais/técnicos permaneceram por desenho ou omissão:

1. **A-04 / BG-101:** `check_in_enabled` default **OFF** — revogar no painel
   não afecta appliances já instalados até `expiry + grace`. Classificado hoje
   como “Documentado — não é bug”; o modelo de ameaças propõe reabrir como
   lacuna comercial.
2. **A-05:** a resposta de check-in é JSON simples, **sem assinatura nem nonce**
   — um servidor falso (via `/etc/hosts` ou trust store) pode manter licença viva.
3. **A-08:** sem alerta de abuso multi-appliance (T1).

CRL offline continua **fora de escopo** (rejeitada na ADR-0021).

---

## Decisão

### 1. Emenda à ADR-0021 — default e migração (`30.14`)

1. Instalações **novas:** `check_in_enabled: true` por defeito.
2. Instalações **existentes:** política de migração anunciada; opt-out
   documentado para appliances genuinamente isolados (**R-J**).
3. Exige **GO humano próprio** (decisões n.º 1 e 2 do §5) — impacto de suporte
   e dependência de rede.
4. Relação com BG-101: no `30.1b`, reabrir como lacuna comercial a corrigir
   **ou** manter com justificação escrita (decisão n.º 5).

### 2. Protocolo assinado com anti-replay (`30.12`–`30.13`)

1. Cliente emite nonce; payload inclui `hardware_id` e estado; servidor responde
   com assinatura Ed25519.
2. Resposta **não assinada** ⇒ rejeitada.
3. Replay de resposta anterior ⇒ rejeitado.
4. Servidor falso via DNS/`/etc/hosts` **não** mantém licença viva.
5. Compatibilidade de transição com clientes antigos: explícita no desenho
   `30.12`.

### 3. Nunca fail-closed por rede (**R-C**)

1. Rede/license server indisponível ⇒ **zero** impacto em enforce enquanto
   dentro da janela offline já definida na ADR-0021 (ou refinada com GO).
2. Revogação **confirmada** online continua a cortar; ausência de rede **não**
   equivale a revogação.
3. Sem kill-switch remoto que desligue enforce por comando arbitrário (**R-E**).

### 4. Detecção multi-appliance (`30.15`)

1. Fase 1: **alerta** no license server (mesma chave, múltiplos `hardware_id`/IPs).
2. `max_activations` só após medir falsos positivos (decisão n.º 7 do §5).

### 5. Limites e riscos residuais (obrigatório)

| Regra / RR | Declaração |
|------------|------------|
| **R-C** | Indisponibilidade de rede nunca reduz enforce nem para o daemon. |
| **RR-1** | Sem GO em `30.14` (e sem `30.11` no lado conteúdo), a trilha não entrega protecção comercial real contra T2 — só higiene AP1 + mecanismos opcionais. |
| **RR-5** | Resposta assinada impede **servidor falso**; **não** impede root de patchar o `layer7d` para ignorar o check-in. Valor de AP3: contra T2 *não-técnico* (ex-cliente que não pacha). O técnico é contido por AP2 + `30.15` + AP4. Coberto por **R-A**. |

O que a ADR-0021 **mantém:** formato `.lic`, activação, binding, ausência de
campo `revoked` embutido, rejeição de CRL offline.

---

## Consequências

### Positivas

- Revogar no painel passa a ter efeito real em instalações novas (e migradas).
- Servidor falso deixa de neutralizar a revogação.
- T1 (integrador multi-cliente) torna-se visível (`30.15`).

### Negativas / riscos

- Dependência periódica de HTTPS — suporte a isolados exige excepção (**R-J**).
- Migração de base instalada é o maior risco de suporte da trilha.
- Patch local do binário continua possível (**RR-5** / **R-A**).

---

## Alternativas consideradas

| Alternativa | Rejeitada porque |
|-------------|------------------|
| Manter default OFF (ADR-0021 tal qual) | A-04 / impacto financeiro T2 permanece |
| CRL offline | Já rejeitada na ADR-0021; complexidade sem ganho sob R-A |
| Fail-closed se check-in falhar | Viola **R-C** / **N3** |
| Afirmar que assinatura impede crack | Viola **RR-5** / **R-A** |

---

## Implementação prevista

- Após AP2 estável: `30.12` → `30.13` → `30.14` (GO) → `30.15`.
- Rollback: `.pkg` anterior + flag/config; deploy anterior do servidor.
- Gate GA5.

## Referências

- ADR-0021 (base; este ADR emenda defaults/protocolo)
- Plano §0.1 (RR-1, RR-5), §2 AP3, §5 decisões 1, 2, 5, 7
- Ficha [`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md)
