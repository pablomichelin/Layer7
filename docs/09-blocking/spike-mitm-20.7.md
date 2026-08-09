# Spike MITM 20.7 — desenho e critérios (IM2)

**Estado:** `DEFER FORMAL` (`2026-08-06` — passo **20.7a PASS**) + **reopen GO** (`2026-08-08` → passo **20.8**)  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) passo **20.7 / 20.7a / 20.8**  
**ADR:** [`../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md) — **Aceito — implementação em curso (scaffolding 20.8)**  
**Desenho opção E:** [`../01-architecture/desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md) — runtime **AUSENTE**  
**Posicionamento PME:** [`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md)  
**Baseline enforce doc:** `1.9.8` · **lab/`latest`:** `1.9.36`  
**Evidência preflight:** [`../tests/evidence/20260805T205900Z-appliance254-mitm-preflight/`](../tests/evidence/20260805T205900Z-appliance254-mitm-preflight/)  
**Regra:** Identity rede **FECHADA** (20.33/GI9). Este spike **não** autoriza intercept; Squid **rejeitado**.


---

## 1. Objectivo do spike

Decidir com critérios **mensuráveis S1–S8** se o Layer7 pode oferecer MITM TLS
opt-in no mesmo produto, sem regressão do caminho ADR-0017 quando OFF.

Veredicto possível: **GO** | **DEFER** | **NO-GO**.

**Veredicto registado:** **DEFER** (`2026-08-06`).

---

## 2. Contexto técnico (baseline)

| Facto | Implicação |
|-------|------------|
| `layer7d` = pcap + nDPI + PF | **Não** termina TLS hoje |
| Block page = DNS sinkhole + HTTP (ADR-0017) | MITM OFF permanece a verdade (estado diferido) |
| Entitlement `mitm` já gated (GI1) | Token pode existir sem código activo |
| Appliance `192.168.100.254` | Plus 26.03.1; `1.9.13` passivo; sem `.lic`; nginx presente; idle ~97% |
| **Squid / pfSense** | **Fora** — operador: *não existe Squid habilitado para pfSense* como caminho de produto |
| Nicho PME | Posicionamento: Identity-first; MITM não é o valor imediato |

Nota: o repo pkg do Plus ainda lista `pfSense-pkg-squid` / `squid-7.4`, mas **não** é caminho canónico aceite.

---

## 2.1 Preflight appliance `2026-08-05` (só-leitura)

| Check | Resultado |
|-------|-----------|
| SSH `root@192.168.100.254` | OK |
| Pacote | `pfSense-pkg-layer7-1.9.13` |
| Layer7 | passivo (`enabled=false`, `mode=monitor`) |
| Licença `.lic` | ausente |
| `pfSense-pkg-squid` / `squid-7.4` | disponível no repo, **não** instalado; **rejeitado como produto** |
| `nginx` | instalado (`1.28.0`) |
| Páginas Identity/MITM | ausentes no pacote instalado (IM1 no git, não nesta release) |
| Baseline CPU | idle ~97% |
| Alterações nesta sessão de preflight | **nenhuma** |

---

## 3. Opções de arquitectura (estado final do spike)

| ID | Abordagem | Estado | Notas |
|----|-----------|--------|-------|
| A | Squid sslbump | **REJEITADA** | Decisão operador 2026-08-05 |
| B | nginx stream/ssl | **Não alvo** | Só PoC lab descartável; não arquitectura de produto |
| C | TLS in-process no `layer7d` | **REJEITADA** MVP | Conflita ADR-0028 |
| E | Helper Layer7 próprio (`layer7-tlsproxy`) | **Candidata reopen** | Desenho: [`desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md); runtime **AUSENTE**; S1–S8 ainda não medidos |
| D | **DEFER** | **ESCOLHIDA (20.7a)** | Entitlement + GUI upsell; sem runtime; Identity IM3+ fechou |

---

## 4. Critérios S1–S8

Com **DEFER**: S1–S8 **não** foram medidos em PoC de intercept (não houve PoC).
Ficam obrigatórios **se** IM2 for reaberto com opção E (ou outra candidata aprovada).

| # | Critério | Limiar | Evidência |
|---|----------|--------|-----------|
| S1 | CPU overhead | ≤ +25% | _adiado — reabrir IM2_ |
| S2 | Latência handshake | ≤ 150 ms p95 | _adiado_ |
| S3 | Block page HTTPS + CA | ≥ 1 browser | _adiado_ |
| S4 | Bypass list | prova | _adiado_ |
| S5 | QUIC/HTTP3 caminho definido | escrito | _adiado_ |
| S6 | ECH previsível | lab | _adiado_ |
| S7 | Sem payload em disco por defeito | auditoria | _adiado_ |
| S8 | MITM OFF ≡ ADR-0017 | smoke | _adiado_ (cumprido por ausência de código MITM) |

---

## 5. Esboço da opção E (futuro — não iniciado)

```text
Cliente --TLS--> [ layer7-tlsproxy ] --TLS--> Internet
                      | CA local
                      | SNI/policy → allow | block(página HTTPS) | bypass
                      v
                 layer7d (mapa/policy)  ← IPC leve; sem IO bloqueante no pcap
```

Requisitos mínimos se reabrir:

- Processo **separado** do loop de captura.  
- Opt-in + entitlement `mitm`; default OFF.  
- Selectivo (não universal); alinhado ao posicionamento PME.  
- CA fora do git; bypass; privacidade S7.  
- Prova S1–S8 + CE (ADR-0022) antes de GO produção.

---

## 6. Veredicto

| Campo | Valor |
|-------|-------|
| Data | **2026-08-06** |
| Resultado | **DEFER** (20.7a PASS) |
| Squid (A) | **REJEITADA** |
| PoC intercept | **Não iniciada** (intencional) |
| GI2 / GI3 | **DEFERRED** |
| Passos 20.8–20.11 | **20.8 EM CURSO** (scaffolding); 20.9+ / intercept bloqueados até S1–S8 + GO lab |
| Próximo plano (histórico) | **20.12 / IM3** mapa daemon — **FEITO**; Identity FECHADA |
| Reabertura | **GO humano `2026-08-08`** — ver §8 |

### Motivos do DEFER (completos)

1. Squid não é caminho habilitado para o produto em pfSense.  
2. Desenvolver motor TLS próprio ≈ segundo produto (estimativa 3–6+ meses para MVP útil; sem paridade NGFW).  
3. Nicho PME: valor imediato = **Identity** (“quem pode o quê”), não decrypt enterprise.  
4. Paridade com Fortinet/Palo/Check Point em TLS **não** é objectivo.  
5. ADR-0026 e o plano já prevêem DEFER sem bloquear IM3–IM6.  
6. GO operador `2026-08-06`: seguir linha PME Identity-first.

---

## 8. Reopen GO humano (`2026-08-08`)

| Campo | Valor |
|-------|--------|
| Data | **2026-08-08** |
| Decisão | **GO reopen IM2** — scaffolding apenas |
| Passo actual | **20.8 EM CURSO** |
| Escopo autorizado | Schema `mitm.*` OFF; gestão CA; bypass GUI; `mitm_entitled` no status daemon; `enabled` forçado **false** |
| Escopo **não** autorizado | Processo `layer7-tlsproxy`; intercept TLS; block page HTTPS via MITM; Squid |
| Desenho | [`desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md) |
| S1–S8 | **Não medidos** — obrigatórios antes de runtime / GI2–GI3 lab |
| Identity rede | **FECHADA** (20.33/GI9) — ortogonal |
| Squid | **REJEITADO** (permanente) |
| Honestidade | **Sem claim de intercept** neste reopen |

---

## 9. Histórico

| Data | Evento |
|------|--------|
| 2026-08-05 | Criação do spike doc (pós-GI1) |
| 2026-08-05 | Preflight appliance `254` |
| 2026-08-05 | Operador: Squid rejeitado; recomenda-se DEFER ou ferramenta própria |
| 2026-08-06 | **DEFER formal 20.7a** — posicionamento PME; ADR-0026 diferido; avançar IM3 |
| 2026-08-08 | **Reopen GO** — passo 20.8 scaffolding; desenho `layer7-tlsproxy`; runtime AUSENTE; ADR-0026 rev. `e` |
