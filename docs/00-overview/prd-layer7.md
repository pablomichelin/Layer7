# PRD — Layer7 para pfSense CE

**Pack:** [`pack-produto-layer7.md`](pack-produto-layer7.md) · **UML:** [`uml-layer7.md`](uml-layer7.md) · **Catálogo:** [`catalogo-funcionalidades.md`](catalogo-funcionalidades.md)  
**Classificação:** Canónico · **SSOT vivo:** [`../../CORTEX.md`](../../CORTEX.md)  
**Referência:** `latest` = `1.9.47` · pin enforce = `1.9.8` · candidato Report = `1.9.48`

> Não inventa capacidades. Em conflito, prevalece o `CORTEX.md`.

---

## Índice

1. [Visão e problema](#1-visão-e-problema)
2. [Personas](#2-personas)
3. [Escopo](#3-escopo)
4. [Requisitos funcionais](#4-requisitos-funcionais)
5. [Requisitos não-funcionais](#5-requisitos-não-funcionais)
6. [Fora de escopo](#6-fora-de-escopo)
7. [Aceitação](#7-critérios-de-aceitação)
8. [Riscos](#8-riscos-e-dependências)
9. [Métricas](#9-métricas-de-sucesso)
10. [Rastreabilidade](#10-rastreabilidade)
11. [Bloco RF-09](#11-bloco-rf-09--reportar-erro)

---

## 1. Visão e problema

O pfSense CE não oferece, de fábrica, classificação Layer 7 orientada a
aplicação com políticas simples, GUI própria, licenciamento comercial e
enforcement previsível.

**Layer7** identifica apps/protocolos (nDPI), aplica políticas
(`monitor` / `tag` / `allow` / `block`), enforce via PF (DNS forçado /
anti-bypass onde aplicável), e opera com `.pkg`, GUI no padrão pfSense e
license server Ed25519.

Nicho PME (trilha activa): Identity-first —
[`posicionamento-pme-identity-first.md`](posicionamento-pme-identity-first.md).

---

## 2. Personas

| Persona | Necessidade |
|---------|-------------|
| Operador PME / MSP pequeno | Controlo de apps sem complexidade enterprise |
| Admin pfSense | Install/upgrade/rollback seguro; diagnósticos claros |
| Operador de licenças (Systemup) | Emitir/activar/revogar no portal admin |
| Engenharia / lab | Gates, evidências, rollback; MITM só com GO |

---

## 3. Escopo

### 3.1 Núcleo V1 (produção enforce `1.9.8`)

| Área | Capacidade |
|------|------------|
| Runtime | Pacote + `layer7d` + nDPI |
| Modos | `monitor` / `enforce`; `legacy_global` (default) · `scoped_hybrid` (experimental) |
| Políticas | App / categoria / host · iface / CIDR / grupo / horário |
| Anti-bypass | Allowlist · UT1 fail-closed · DNS forçado · anti-DoH/DoT · página HTTP (MITM OFF) |
| Stack | Dual-stack IPv4/IPv6 (trilha IPv6 **FECHADA**) |
| GUI | Estado, Definições, Políticas, Grupos, Categorias, Teste, Excepções, Events, Diagnósticos, Relatórios, Blacklists, Identity, MITM, … |
| Licença | Ed25519 + fingerprint · activação online · grace local |
| Distribuição | Updater via GitHub Releases `pablomichelin/Layer7` |
| Observabilidade local | Logs L1 (ADR-0015) · reports SQLite · **Reportar erro** opt-in (candidato `1.9.48`) |

### 3.2 Add-ons / trilhas

| Trilha | Estado | Nota |
|--------|--------|------|
| Identity (User-ID rede) | **FECHADA** | RADIUS + agente DC; sem captive |
| MITM TLS (opt-in) | GO produto; lab; permanente **NO-GO** | P4 FAIL/ABORT; Squid rejeitado |
| Portal admin licenças | Activo | Operador único; sem MSP sem GO |

---

## 4. Requisitos funcionais

| ID | Requisito | Pri | Estado |
|----|-----------|-----|--------|
| RF-01 | Classificar fluxos (nDPI) | Must | Cumprido |
| RF-02 | Políticas monitor/tag/allow/block | Must | Cumprido |
| RF-03 | Enforcement PF previsível | Must | Cumprido (F4 endurece) |
| RF-04 | GUI administrativa pfSense | Must | Cumprido |
| RF-05 | Licença offline + check-in online | Must | Cumprido (F3) |
| RF-06 | Install/upgrade/rollback `.pkg`+SHA256 | Must | Cumprido |
| RF-07 | Blacklists UT1 assinadas fail-closed | Must | Cumprido |
| RF-08 | Diagnósticos locais | Should | Cumprido |
| RF-09 | Reportar erro sem segredos (GitHub pré-preenchido) | Should | **Candidato `1.9.48`** |
| RF-10 | Identity User-ID de rede | Should | Cumprido (âmbito IM) |
| RF-11 | MITM TLS opt-in escopado | Could | Lab; não permanente |

---

## 5. Requisitos não-funcionais

| ID | Requisito |
|----|-----------|
| RNF-01 | Não-regressão núcleo enforce/monitor |
| RNF-02 | Fail-closed em integridade (blacklists, licença) |
| RNF-03 | Sem telemetria sem GO — Report = opt-in manual |
| RNF-04 | Sem suavizar validação TLS / ignore-certificate-errors |
| RNF-05 | Blocos pequenos, auditáveis, reversíveis |
| RNF-06 | Docs vivos alinhados a `PORTVERSION` / release |
| RNF-07 | Segredos fora do repo e fora de issues |

---

## 6. Fora de escopo

- MITM permanente / piloto externo sem ficha + GO
- Squid / MITM baseado em Squid
- Console multi-firewall / MSP multi-tenant
- Feature-paridade com vendors enterprise
- Rebind automático de licença
- Analytics / SIEM pesado
- Captive portal como Identity
- Agente endpoint em PC (MVP Identity)

---

## 7. Critérios de aceitação

1. Instalar `latest` com SHA256 e ver `layer7d -V`.
2. Em `monitor`, sem blocks Layer7 por defeito.
3. Em `enforce` (pin/GO correcto), blocks sem “bloquear tudo” por default.
4. Updater só considera releases de pacote (BG-030).
5. Blacklist com fingerprint errado não aplica conteúdo suspeito.
6. **Reportar erro** abre GitHub com versão/modo e **sem** `.lic`/chaves/logs/dumps.
7. MITM OFF por defeito; activação só com gates/runbook.

---

## 8. Riscos e dependências

| Risco | Mitigação |
|-------|-----------|
| Lab Plus/FB16 ≠ CE (ADR-0022) | Disclosure; CE pendente |
| Trust chain pacote fase 0 | SHA256 manual |
| MITM P4 FAIL/ABORT | Permanente NO-GO; P5 só com ficha |
| Confusão `latest` vs enforce | CORTEX + MANUAL-PRODUTO |
| Limites DPI (ECH/QUIC/…) | [`matriz-limitacoes-dpi.md`](../09-blocking/matriz-limitacoes-dpi.md) |

---

## 9. Métricas de sucesso

- Gates G2–G7 / GV7.4 / two-client PASS no pin enforce
- Canal `latest` com `.pkg` + `.sha256`
- Zero regressão de lifecycle nos smokes F4
- Issues de campo sem exposição de segredos (RF-09)

---

## 10. Rastreabilidade

| Artefacto | Papel |
|-----------|-------|
| [`pack-produto-layer7.md`](pack-produto-layer7.md) | Índice do pack |
| [`product-charter.md`](product-charter.md) | Charter curto |
| [`catalogo-funcionalidades.md`](catalogo-funcionalidades.md) | Inventário status |
| [`uml-layer7.md`](uml-layer7.md) | Classes + sequências |
| [`../02-roadmap/roadmap.md`](../02-roadmap/roadmap.md) | Fases F0–F7 |
| [`../MANUAL-PRODUTO.md`](../MANUAL-PRODUTO.md) | Hub operador |

---

## 11. Bloco RF-09 — Reportar erro

| Campo | Valor |
|-------|-------|
| **Objectivo** | Reportar bugs a partir da GUI sem telemetria |
| **Fluxo** | Descrever → pré-visualizar metadados seguros → abrir GitHub (ou copiar URL) |
| **Onde** | Diagnósticos (`layer7_diagnostics.php`) |
| **Anexa** | pkg/daemon version, daemon state, enabled, mode, model, iface count, mitm flag |
| **Nunca anexa** | `.lic`, chaves, logs, dumps, hostnames, IPs de clientes, PF completo |
| **Impacto** | GUI + helpers PHP; docs; candidato `1.9.48` |
| **Risco** | Baixo — opt-in; sem upload; sem backend novo |
| **Teste** | `tests/test_error_report.php` + smoke manual Diagnósticos |
| **Rollback** | Remover painel/helpers; reverter `PORTVERSION` se não publicado |

Fluxo narrativo completo: [`pack-produto-layer7.md`](pack-produto-layer7.md#como-funciona-reportar-erro).
