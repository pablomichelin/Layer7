# Spike MITM 20.7 — desenho e critérios (IM2)

**Estado:** `EM CURSO` (`2026-08-05`) — desenho + **preflight appliance**; PoC intercept **PENDENTE** (GO humano)  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) passo **20.7**  
**ADR:** [`../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md)  
**Baseline enforce doc:** `1.9.8` · **Appliance observado:** `1.9.13` passivo em `192.168.100.254`  
**Evidência preflight:** [`../tests/evidence/20260805T205900Z-appliance254-mitm-preflight/`](../tests/evidence/20260805T205900Z-appliance254-mitm-preflight/)  
**Regra:** Identity (IM3+) **não** bloqueia se este spike for DEFER/NO-GO.

---

## 1. Objectivo do spike

Decidir com critérios **mensuráveis S1–S8** se o Layer7 pode oferecer MITM TLS
opt-in no mesmo `.pkg`, sem regressão do caminho ADR-0017 quando OFF.

Veredicto possível: **GO** | **DEFER** | **NO-GO**.

---

## 2. Contexto técnico (baseline)

| Facto | Implicação |
|-------|------------|
| `layer7d` = pcap + nDPI + PF | Não termina TLS hoje |
| Block page = DNS sinkhole + HTTP (ADR-0017) | MITM OFF deve permanecer byte-a-byte |
| Entitlement `mitm` já gated (GI1 no git) | Código MITM pode existir morto sem token |
| pfSense CE vs lab Plus | Appliance actual = **Plus 26.03.1**; prova CE exigida para GO produção (ADR-0022) |
| Appliance `192.168.100.254` (preflight) | Pacote **1.9.13**; `enabled=false` / `monitor`; **sem `.lic`**; **sem squid** instalado; **nginx** presente; idle CPU ~97%; IM1 GUI/binário **ainda não instalados** |

---

## 2.1 Preflight appliance `2026-08-05` (só-leitura)

| Check | Resultado |
|-------|-----------|
| SSH `root@192.168.100.254` | OK |
| Pacote | `pfSense-pkg-layer7-1.9.13` |
| Layer7 | passivo (`enabled=false`, `mode=monitor`) |
| Licença `.lic` | ausente |
| `pfSense-pkg-squid` / `squid-7.4` | **disponível no repo**, não instalado |
| `nginx` | instalado (`1.28.0`) |
| Páginas `layer7_identity.php` / `layer7_mitm.php` | ausentes (IM1 não empacotado nesta release) |
| Baseline CPU (vmstat) | idle ~97% — headroom para S1 se PoC avançar |
| Alterações nesta sessão | **nenhuma** (sem install squid / sem mudar enforce) |

---

## 3. Opções de arquitectura (candidatas)

| ID | Abordagem | Prós | Contras |
|----|-----------|------|---------|
| A | **squid** sslbump + CA no appliance | Maduro; bypass ACL; docs abundantes | Processo extra; CPU; integração pfSense; packaging |
| B | **nginx** stream/ssl_preread + sub-proxy | Flexível | Mais glue; menos “sslbump” nativo |
| C | Biblioteca in-process no `layer7d` | Um binário | Alto risco; conflita ADR-0028/hot path; **rejeitada para MVP** |
| D | **DEFER** — só entitlement + GUI upsell | Zero risco runtime; Identity avança | Sem UX HTTPS legível até spike futuro |

**Recomendação de investigação no lab:** opção **A (squid sslbump)** como PoC mínima;
se inviável em CE, **D (DEFER)**.

---

## 4. Critérios S1–S8 (cópia operacional ADR-0026)

| # | Critério | Limiar | Evidência lab |
|---|----------|--------|---------------|
| S1 | CPU overhead intercept selectivo | ≤ +25% vs baseline `1.9.8` | _PENDENTE_ |
| S2 | Latência handshake TLS interceptado | ≤ 150 ms p95 | _PENDENTE_ |
| S3 | Block page HTTPS com CA via GPO | ≥ 1 browser Windows | _PENDENTE_ |
| S4 | Bypass list exclui fluxo | prova pacotes | _PENDENTE_ |
| S5 | QUIC/HTTP3 caminho definido | escrito + testado | _PENDENTE_ |
| S6 | ECH: comportamento previsível | lab | _PENDENTE_ |
| S7 | Privacidade: sem payload em disco por defeito | auditoria PoC | _PENDENTE_ |
| S8 | MITM OFF ≡ ADR-0017 | smoke comparativo | _PENDENTE_ |

- Falhar S1–S2 por margem pequena com caminho de optimização → **DEFER**.  
- Falhar S7 ou S8 → **NO-GO** até redesenho.

---

## 5. PoC mínima (checklist lab)

1. Baseline CPU/throughput/latência `1.9.8` (partilha com 20.11a se já registada).  
2. Instalar candidato PoC **só em lab** (não produção).  
3. CA de teste (nunca no git); export trust para cliente lab.  
4. Intercept **selectivo** (1 SNI/CIDR de teste).  
5. Medir S1–S8; guardar evidências em `docs/tests/evidence/<run_id>/`.  
6. Registar veredicto neste ficheiro + CORTEX + plano.

**Ambiente preferido:** appliance lab; prova CE antes de GO produção.

---

## 6. Veredicto

| Campo | Valor |
|-------|-------|
| Data | 2026-08-05 (preflight) |
| Resultado | **PENDENTE PoC** — preflight PASS; intercept não iniciado |
| Opção preferida para PoC | A — `pfSense-pkg-squid` / squid sslbump (disponível no repo) |
| Nota nginx | B fica como alternativa (nginx já instalado) |
| Fallback | **DEFER** (20.7a) → avançar IM3 Identity |
| Bloqueio | GO humano para instalar squid neste host (documentado como produção Systemup) |

---

## 7. Histórico

| Data | Evento |
|------|--------|
| 2026-08-05 | Criação do spike doc (passo 20.7 iniciado pós-GI1) |
| 2026-08-05 | Preflight só-leitura em `192.168.100.254` — evidência `20260805T205900Z-appliance254-mitm-preflight` |
