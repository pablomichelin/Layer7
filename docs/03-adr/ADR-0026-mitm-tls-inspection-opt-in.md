# ADR-0026 — MITM TLS inspection opt-in (certificado / CA)

**Estado:** Aceito — **implementação diferida** (rev. `d` — DEFER formal 20.7a `2026-08-06`)  
**Data:** 2026-08-05  
**Aceite:** `2026-08-05` — passo **20.2** / GI0  
**Deferral implementação:** `2026-08-06` — passo **20.7a** (GO operador: PME Identity-first)  
**Decisores:** Operador  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**Spike:** [`../09-blocking/spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md)  
**Posicionamento:** [`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md)  
**Relação:** emenda controlada a [`ADR-0017`](ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md) (válida com MITM OFF — estado actual)

---

## Contexto

- ADR-0017 rejeitou MITM universal na V1: block page HTML só em HTTP.
- O operador quer **opção** de MITM com CA no domínio (SKU + plano).
- O runtime actual (`layer7d` = pcap + nDPI + PF) **não** termina TLS — MITM é arquitecturalmente um **segundo produto** (proxy/terminação), não uma extensão trivial.
- Spike 20.7: Squid **rejeitado** como caminho pfSense; PoC de intercept **não** iniciada; nicho PME prioriza Identity.
- Por isso este ADR congela a **política de produto** (SKU `mitm` opt-in) e, desde `2026-08-06`, a **implementação fica diferida**.

---

## Decisão

1. Introduzir **MITM TLS opt-in** no modelo de produto/SKU (`mitm` em ADR-0025), default **`mitm.enabled = false`**.
2. Requer entitlement `mitm`. Sem token: módulo inerte.
3. Com MITM OFF: **ADR-0017 permanece a verdade**.
4. **Spike 20.7** obrigatório antes de 20.8–20.11 — **cumprido com veredicto DEFER** (ver spike).
5. Critérios S1–S8 (rev. `c`) permanecem obrigatórios **se** a implementação for reaberta:

| # | Critério de GO | Limiar |
|---|----------------|--------|
| S1 | Overhead de CPU do appliance com intercept selectivo em tráfego de referência | ≤ **25%** acima da baseline `1.9.8` registada (ADR-0028 §5) |
| S2 | Latência adicional por handshake TLS interceptado | ≤ **150 ms** p95 no lab |
| S3 | Cliente com CA via GPO vê block page HTTPS legível | Funcional em ≥ 1 browser Windows corporativo |
| S4 | Bypass list (IP/CIDR/SNI) exclui fluxo do intercept | Prova de pacotes não terminados |
| S5 | QUIC/HTTP3: caminho definido (bloquear/downgrade p/ TCP ou bypass documentado) | Decisão escrita, sem “ver depois” |
| S6 | ECH: limite documentado + comportamento previsível (não crash, não fail-closed da LAN) | Prova em lab |
| S7 | Privacidade: nenhum payload desencriptado persiste em disco por defeito; logging só de metadados | Auditoria da PoC |
| S8 | MITM OFF ≡ ADR-0017 byte-a-byte (sem regressão com módulo presente e OFF) | Smoke comparativo |

6. **DEFER formal (`2026-08-06`):** este ADR fica **Aceito — implementação diferida**; Identity (IM3–IM6) **não** bloqueia; token `mitm` pode existir no SKU mas **sem código activo** em release; passos 20.8–20.11 saltados; GI2/GI3 `DEFERRED`.
7. **Reabertura:** GO humano explícito + novo spike S1–S8. Candidata preferida: helper próprio (`layer7-tlsproxy` / opção E). **Squid rejeitado** permanentemente como caminho de produto neste ADR.
8. Se **GO** futuro: implementar CA (gerar/importar/export GPO), intercept **selectivo** (não universal), bypass, página HTTPS legível; emenda explícita a ADR-0017; alinhamento ao posicionamento PME (sem overclaim NGFW).
9. Segredos da CA **nunca** no git.
10. MITM **não** é pré-requisito de Identity (ortogonal).
11. **Fora do objectivo de deferral:** paridade de motor TLS com NGFW enterprise (Fortinet / Palo Alto / Check Point).

---

## O que isto NÃO decide

- Activar MITM por defeito.
- Qual biblioteca/proxy exacta no futuro (excepto: **não** Squid).
- Interceptar gestão do pfSense / VIP sem regra explícita.
- Substituir Identity (ADR-0027).
- Datas comerciais de reabertura.

---

## Consequências

- Gates GI2–GI3 = **`DEFERRED`** até reabertura.  
- Matriz DPI / MANUAL: sem mudanças de procedimento MITM enquanto diferido.  
- GUI pode manter upsell MITM; daemon sem intercept.  
- Investimento de engenharia imediato = **Identity IM3+**.

---

## Alternativas

| Alternativa | Motivo |
|-------------|--------|
| Manter forever só ADR-0017 | Rejeitado como *exclusão permanente* — MITM fica no SKU/plano (implementação diferida) |
| MITM sempre ON | Inaceitável |
| Implementar MITM sem spike | Rejeitado (rev. `b`) |
| Bloquear Identity até MITM pronto | Rejeitado (rev. `b`); confirmado no defer |
| Usar Squid / pfSense-pkg-squid | **Rejeitado** (`2026-08-05` / confirmado no defer) |
| Implementar agora helper próprio | Adiado — custo/meses vs valor PME Identity-first |

---

## Rollback

1. `mitm.enabled=false` + reload (quando existir código).  
2. Remover entitlement `mitm`.  
3. **Estado actual:** DEFER / não publicar código MITM.  
4. Remover CA dos clientes via GPO (ops) — só se alguma vez instalada.

---

## Histórico de revisões

| Rev. | Data | Nota |
|------|------|------|
| a/b | 2026-08-05 | Política opt-in + spike obrigatório |
| c | 2026-08-05 | Critérios S1–S8 mensuráveis |
| **d** | **2026-08-06** | **Implementação diferida (20.7a)**; Squid rejeitado; Identity avança |

---

## Referências

- ADR-0017, ADR-0013, plano §0.0 R-A/R-B/R-T, posicionamento PME, [`../09-blocking/matriz-limitacoes-dpi.md`](../09-blocking/matriz-limitacoes-dpi.md)
