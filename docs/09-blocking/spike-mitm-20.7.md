# Spike MITM 20.7 — desenho e critérios (IM2)

**Estado:** `EM CURSO` (`2026-08-05`) — **Squid rejeitado** (decisão operador); caminho a escolher  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) passo **20.7**  
**ADR:** [`../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md)  
**Baseline enforce doc:** `1.9.8` · **Appliance:** `1.9.13` passivo em `192.168.100.254`  
**Evidência preflight:** [`../tests/evidence/20260805T205900Z-appliance254-mitm-preflight/`](../tests/evidence/20260805T205900Z-appliance254-mitm-preflight/)  
**Regra:** Identity (IM3+) **não** bloqueia se este spike for DEFER/NO-GO.

---

## 1. Objectivo do spike

Decidir com critérios **mensuráveis S1–S8** se o Layer7 pode oferecer MITM TLS
opt-in no mesmo produto, sem regressão do caminho ADR-0017 quando OFF.

Veredicto possível: **GO** | **DEFER** | **NO-GO**.

---

## 2. Contexto técnico (baseline)

| Facto | Implicação |
|-------|------------|
| `layer7d` = pcap + nDPI + PF | **Não** termina TLS hoje |
| Block page = DNS sinkhole + HTTP (ADR-0017) | MITM OFF deve permanecer byte-a-byte |
| Entitlement `mitm` já gated (GI1 no git) | Token pode existir sem código activo |
| Appliance `192.168.100.254` | Plus 26.03.1; `1.9.13` passivo; sem `.lic`; nginx presente; idle ~97% |
| **Squid / pfSense** | **Fora** — operador: *não existe mais Squid habilitado para pfSense* como caminho de produto |

Nota: o repo pkg do Plus ainda lista `pfSense-pkg-squid` / `squid-7.4`, mas **não** é caminho canónico aceite para este projecto.

---

## 3. Opções de arquitectura (revistas)

| ID | Abordagem | Estado | Notas |
|----|-----------|--------|-------|
| A | Squid sslbump (`pfSense-pkg-squid`) | **REJEITADA** | Decisão operador 2026-08-05 — sem Squid habilitado para pfSense |
| B | **nginx** (já no appliance) + stream/ssl + CA | Investigável | Não é “MITM pfSense” oficial; PoC frágil; muito glue PF/NAT |
| C | TLS in-process no `layer7d` | **REJEITADA** MVP | Conflita ADR-0028 (hot path) |
| E | **Ferramenta Layer7 própria** (`layer7-tlsproxy` ou similar) — processo auxiliar + CA + intercept selectivo | **Candidata de produto** | Alinha ADR-0028 (fora do loop de captura); empacotável no mesmo `.pkg`; esforço = segundo produto |
| D | **DEFER** — entitlement + GUI upsell; sem runtime MITM | **Recomendada agora** | Fecha 20.7a; Identity IM3 avança; MITM volta quando E tiver desenho+PoC |

### Recomendação (2026-08-05)

1. **Imediato:** fechar spike como **DEFER (20.7a)** — Squid morto; desenvolver proxy próprio é grande demais para bloquear Identity.  
2. **Depois (backlog MITM):** desenhar **opção E** (helper dedicado, não Squid) com spike próprio e S1–S8.  
3. **Não** instalar Squid no `192.168.100.254`.  
4. Opção B (nginx) só se quiseres PoC descartável de laboratório — **não** como arquitectura alvo.

---

## 4. Critérios S1–S8 (inalterados; aplicam-se a E ou B se houver PoC)

| # | Critério | Limiar | Evidência |
|---|----------|--------|-----------|
| S1 | CPU overhead intercept selectivo | ≤ +25% vs baseline | _n/a até PoC E/B_ |
| S2 | Latência handshake TLS | ≤ 150 ms p95 | _n/a_ |
| S3 | Block page HTTPS com CA | ≥ 1 browser | _n/a_ |
| S4 | Bypass list | prova | _n/a_ |
| S5 | QUIC/HTTP3 caminho definido | escrito | _n/a_ |
| S6 | ECH previsível | lab | _n/a_ |
| S7 | Sem payload em disco por defeito | auditoria | _n/a_ |
| S8 | MITM OFF ≡ ADR-0017 | smoke | _n/a_ |

Com **DEFER**: GI2/GI3 → `DEFERRED`; S1–S8 ficam para o spike da opção E.

---

## 5. Esboço da opção E (se GO desenvolver)

```text
Cliente --TLS--> [ layer7-tlsproxy ] --TLS--> Internet
                      | CA local
                      | SNI/policy → allow | block(página HTTPS) | bypass
                      v
                 layer7d (mapa/policy)  ← IPC leve; sem IO bloqueante no pcap
```

Requisitos mínimos (alinhar ADR-0026/0028):

- Processo **separado** do loop de captura (`layer7d`).  
- Opt-in + entitlement `mitm`; default OFF.  
- CA fora do git; bypass list; privacidade (S7).  
- Empacotado no `pfSense-pkg-layer7` **ou** ADR para segundo artefacto (fora do escopo actual “um pacote”).

Isto **não** começa neste passo sem GO humano explícito de investimento.

---

## 6. Veredicto

| Campo | Valor |
|-------|-------|
| Data | 2026-08-05 |
| Squid (A) | **REJEITADA** |
| Resultado spike | **Aguardando GO:** **DEFER (20.7a)** *(recomendado)* **ou** abrir desenho da opção **E** |
| Próximo se DEFER | Emenda ADR-0026 “implementação diferida”; IM3 Identity |
| Próximo se E | Doc de arquitectura do helper + estimativa; PoC lab depois |

---

## 7. Histórico

| Data | Evento |
|------|--------|
| 2026-08-05 | Criação do spike doc (pós-GI1) |
| 2026-08-05 | Preflight appliance `254` |
| 2026-08-05 | Operador: Squid **não** é caminho pfSense → A rejeitada; recomenda-se DEFER ou ferramenta própria (E) |
