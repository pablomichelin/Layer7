# ADR-0026 — MITM TLS inspection opt-in (certificado / CA)

**Estado:** Aceito (rev. `c` — critérios GO/NO-GO mensuráveis; implementação condicionada ao spike 20.7)  
**Data:** 2026-08-05  
**Aceite:** `2026-08-05` — passo **20.2** / GI0  
**Decisores:** Operador (GO aceitação no passo 20.2)  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**Relação:** emenda controlada a [`ADR-0017`](ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md) (válida com MITM OFF)

---

## Contexto

- ADR-0017 rejeitou MITM universal na V1: block page HTML só em HTTP.
- O operador quer **opção** de MITM com CA no domínio (SKU + plano).
- O runtime actual (`layer7d` = pcap + nDPI + PF) **não** termina TLS — MITM é arquitecturalmente um **segundo produto** (proxy/terminação), não uma extensão trivial.
- Por isso este ADR congela a **política de produto**, não a viabilidade — a viabilidade decide-se no **spike 20.7**.

---

## Decisão (proposta)

1. Introduzir **MITM TLS opt-in** no modelo de produto/SKU (`mitm` em ADR-0025), default **`mitm.enabled = false`**.
2. Requer entitlement `mitm`. Sem token: módulo inerte.
3. Com MITM OFF: **ADR-0017 permanece a verdade**.
4. **Spike obrigatório (passo 20.7)** antes de 20.8–20.11:
   - Desenho concreto em pfSense (sslbump/proxy/outro) + PoC mínima.
   - Limites: ECH, QUIC/HTTP3, pinning, CPU, caminhos de bypass.
   - Privacidade: o que é logado do conteúdo desencriptado; retenção mínima; proibição de exfiltrar payload para fora do appliance por defeito.
   - Resultado: **GO** | **NO-GO** | **DEFER** (registado no plano + CORTEX).
   - **Critérios mensuráveis (rev. `c`)** — o veredicto não pode ser
     subjectivo; GO exige **todos** os seguintes na PoC de lab:

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

     Falhar S1–S2 por margem pequena com caminho claro de optimização →
     **DEFER** (não NO-GO). Falhar S7 ou S8 → **NO-GO** até redesenho.
5. Se **NO-GO/DEFER:** este ADR fica **Aceito — implementação diferida**; Identity (IM3–IM6) **não** bloqueia; token `mitm` pode existir no SKU mas sem código activo em release.
6. Se **GO:** implementar CA (gerar/importar/export GPO), intercept **selectivo**, bypass, página HTTPS legível nos fluxos interceptados; emenda explícita a ADR-0017.
7. Segredos da CA **nunca** no git.
8. MITM **não** é pré-requisito de Identity (ortogonal).

---

## O que isto NÃO decide

- Activar MITM por defeito.
- Qual biblioteca/proxy exacta (fica no spike).
- Interceptar gestão do pfSense / VIP sem regra explícita.
- Substituir Identity (ADR-0027).

---

## Consequências

- Gates GI2–GI3 só após GO do spike; DEFER marca GI2/GI3 como `DEFERRED`.
- Matriz DPI / MANUAL actualizados quando houver implementação ou deferral formal.
- Superfície de ataque: código MITM no `.pkg` (se existir) deve permanecer morto sem entitlement.

---

## Alternativas

| Alternativa | Motivo |
|-------------|--------|
| Manter forever só ADR-0017 | Rejeitado como *exclusão permanente* — MITM fica no SKU/plano |
| MITM sempre ON | Inaceitável |
| Implementar MITM sem spike | Rejeitado (rev. `b`) — risco de caminho morto |
| Bloquear Identity até MITM pronto | Rejeitado (rev. `b`) |

---

## Rollback

1. `mitm.enabled=false` + reload.  
2. Remover entitlement `mitm`.  
3. DEFER / não publicar código MITM.  
4. Remover CA dos clientes via GPO (ops).

---

## Referências

- ADR-0017, ADR-0013, plano §0.0 R-A/R-B, [`../09-blocking/matriz-limitacoes-dpi.md`](../09-blocking/matriz-limitacoes-dpi.md)
