# ADR-0026 — MITM TLS inspection opt-in (certificado / CA)

**Estado:** Aceito — **runtime shipped** (`1.9.39`–`1.9.47`); P3 janela PASS; P1+P2 docs PASS; **activar piloto/permanente NO-GO** até ficha+soak; ficha site = **gate activação** ≠ lacuna eng. (rev. `q`)  
**Data:** 2026-08-05  
**Aceite:** `2026-08-05` — passo **20.2** / GI0  
**Deferral implementação:** `2026-08-06` — passo **20.7a** (GO operador: PME Identity-first)  
**Reopen GO:** `2026-08-08` — passo **20.8** scaffolding; **20.9 PASS** (intenção/bypass/IPC)  
**Decisores:** Operador  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**Spike:** [`../09-blocking/spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md)  
**Desenho opção E:** [`../01-architecture/desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md)  
**Contrato IPC 20.9:** [`../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md`](../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md)  
**Posicionamento:** [`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md)  
**Relação:** emenda controlada a [`ADR-0017`](ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md) (válida com `!mitm_effective`)

---

## Contexto

- ADR-0017 rejeitou MITM universal na V1: block page HTML só em HTTP.
- O operador quer **opção** de MITM com CA no domínio (SKU + plano).
- O runtime actual (`layer7d` = pcap + nDPI + PF) **não** termina TLS — MITM é arquitecturalmente um **segundo produto** (proxy/terminação), não uma extensão trivial.
- Spike 20.7: Squid **rejeitado** como caminho pfSense; PoC de intercept **não** iniciada; nicho PME priorizou Identity (fechada em 20.33/GI9).
- Em `2026-08-06` este ADR congelou política + **implementação diferida**.
- Em `2026-08-08` o operador deu **GO de reabertura** para scaffolding seguro (20.8), **sem** autorizar intercept nem runtime `layer7-tlsproxy`.

---

## Decisão

1. Introduzir **MITM TLS opt-in** no modelo de produto/SKU (`mitm` em ADR-0025), default **`mitm.enabled = false`**. A partir de **20.9**, `mitm.enabled` é **intenção** do operador; **`mitm_effective`** é a única condição de activação e permanece **false** sem runtime (ver contrato IPC).
2. Requer entitlement `mitm`. Sem token: módulo inerte.
3. Com `!mitm_effective`: **ADR-0017 permanece a verdade**.
4. **Spike 20.7** obrigatório antes de 20.8–20.11 — **cumprido com veredicto DEFER** (ver spike); reopen GO `2026-08-08` autoriza scaffolding + intenção, **não** PoC de intercept.
5. Critérios S1–S8 (rev. `c`) permanecem obrigatórios **antes** de runtime / intercept / GI2–GI3 lab:

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

6. **DEFER formal (`2026-08-06`):** implementação diferida; Identity avançou; Squid rejeitado.
7. **Reopen GO (`2026-08-08`):** este ADR passa a **Aceito — implementação em curso**. Escopo **20.8:** schema `mitm.*`, gestão CA, bypass GUI, `mitm_entitled`. Escopo **20.9:** intenção `mitm.enabled`, bypass endurecido, `quic_mode`, contrato IPC; **`mitm_effective` sempre false** sem runtime produto. **Squid rejeitado** permanentemente.
8. **GO lab (`2026-08-09`):** autoriza PoC idle/IPC de `layer7-tlsproxy` em lab **isolado** (ver `poc-layer7-tlsproxy-lab.md`). **Não** autoriza intercept em `.254`/`.234`/`.235`, empacotar no `.pkg` público, nem claim `mitm_effective=true`. **20.10** exige S1–S4/S6 medidos + **GO produto**.
9. Se **GO produto** (após S1–S8): intercept **selectivo** (não universal), página HTTPS legível; emenda explícita a ADR-0017; alinhamento PME (sem overclaim NGFW).
10. Segredos da CA **nunca** no git.
11. MITM **não** é pré-requisito de Identity (ortogonal); Identity rede permanece **FECHADA**.
12. **Fora do objectivo:** paridade de motor TLS com NGFW enterprise (Fortinet / Palo Alto / Check Point).

---

## O que isto NÃO decide

- Activar MITM por defeito.
- Autorizar intercept ou runtime neste bloco (20.8/20.9) — `mitm_effective` permanece false.
- Qual biblioteca TLS exacta no helper (excepto: **não** Squid; processo separado).
- Interceptar gestão do pfSense / VIP sem regra explícita.
- Substituir Identity (ADR-0027).
- Datas comerciais de GO produto / produção MITM.

---

## Consequências

- **Histórico (rev. e–f):** GI2–GI3 runtime diferidos até S1–S8 + GO lab.  
- **Estado vivo (`2026-08-09`, rev. q):** GI2/GI3 **PASS**; Gate C + GO teste (`1.9.46` / `215442Z`); **P3 PASS** (`1.9.47` — max_window/deadline/auto-disable/audit/GUI); ficha site = **gate activação** (≠ gap eng.); activação **piloto/permanente** = **NO-GO** até ficha + soak — [`mapa`](../09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md).  
- Runtime `layer7-tlsproxy` **presente** no `.pkg` (default OFF); `mitm_effective` só com gates.  
- Identity rede **fechada** — não reabrir nesta fila.

---

## Alternativas

| Alternativa | Motivo |
|-------------|--------|
| Manter forever só ADR-0017 | Rejeitado como *exclusão permanente* — MITM fica no SKU/plano |
| MITM sempre ON | Inaceitável |
| Implementar MITM sem spike | Rejeitado (rev. `b`) |
| Bloquear Identity até MITM pronto | Rejeitado (rev. `b`); Identity fechou sem MITM |
| Usar Squid / pfSense-pkg-squid | **Rejeitado** (`2026-08-05` / confirmado no defer e no reopen) |
| Implementar runtime sem S1–S8 | Rejeitado — reopen GO cobre scaffolding 20.8 + intenção 20.9; não intercept |

---

## Rollback

1. `mitm.enabled=false` + reload.  
2. Remover entitlement `mitm`.  
3. Sem processo `layer7-tlsproxy` empacotado a parar (PoC fora do `.pkg`).  
4. Remover CA dos clientes via GPO (ops) — só se alguma vez instalada.

---

## Histórico de revisões

| Rev. | Data | Nota |
|------|------|------|
| a/b | 2026-08-05 | Política opt-in + spike obrigatório |
| c | 2026-08-05 | Critérios S1–S8 mensuráveis |
| **d** | **2026-08-06** | **Implementação diferida (20.7a)**; Squid rejeitado; Identity avança |
| **e** | **2026-08-08** | **Aceito — scaffolding 20.8**; reopen GO; runtime diferido até S1–S8; desenho `layer7-tlsproxy` |
| **f** | **2026-08-08** | **20.9 PASS** — `mitm.enabled` = **intenção**; `mitm_effective` **sempre false** sem runtime; bypass/`quic_mode`/contrato IPC; 20.10 bloqueado |
| **g** | **2026-08-09** | S5 PASS doc; S7 PASS doc; S8 PASS real ADR-0017 |
| **h** | **2026-08-09** | **GO lab** — PoC-0 idle `src/layer7-tlsproxy/` |
| **i** | **2026-08-09** | **PoC-1 PASS** — IPC PING lab; path produto recusado; 20.10 ainda exige S1–S4/S6 + GO produto |
| **j** | **2026-08-09** | Corrida lab 20.11 (`.54` / `1.9.41`); docs iniciais overclaimaram S3/S6 |
| **k** | **2026-08-09** | Rev. gerencial interina: S3 pendente (curl ≠ browser); S6 NA |
| **l** | **2026-08-09** | **S3 PASS** Edge Windows `192.168.100.24`; **20.11 / GI3 PASS**; S6 permanece **NA/limite**; sem GO produção |
| **m** | **2026-08-09** | **`1.9.42` source_cidr** — rdr MITM exige origem **e** destino; proibido `from any`; produção `.254` continua sem activação automática |
| **n** | **2026-08-09** | **`1.9.46` + mapa piloto** — Gate C / teste controlado PASS; runtime shipped; piloto **NÃO PRONTO**; permanente NO-GO; próximo P3 failsafe após GO escopo |
| **o** | **2026-08-09** | **P1+P2 docs** — escopo D1–D9 (CA cliente/GPO, metadados 30d, allow explícito, explicabilidade, break-glass+auto-disable); activação ainda NO-GO até P3 |
| **p** | **2026-08-09** | Ficha site nomeada = **gate activação externa** (≠ gap eng.); aceite P3.1–P3.8 no mapa |
| **q** | **2026-08-09** | **P3 PASS** `1.9.47` — janela/deadline/failsafe/audit metadados/GUI; suite builder PASS |

---

## Referências

- ADR-0017, ADR-0013, plano §0.0 R-A/R-B/R-T, posicionamento PME, [`../09-blocking/matriz-limitacoes-dpi.md`](../09-blocking/matriz-limitacoes-dpi.md), [`../01-architecture/desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md), [`../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md`](../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md)
