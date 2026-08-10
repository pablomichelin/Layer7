# Catálogo de funcionalidades — Layer7

**Pack:** [`pack-produto-layer7.md`](pack-produto-layer7.md) · **PRD:** [`prd-layer7.md`](prd-layer7.md) · **UML:** [`uml-layer7.md`](uml-layer7.md)  
**Classificação:** Canónico · **SSOT vivo:** [`../../CORTEX.md`](../../CORTEX.md)  
**Actualizado:** `2026-08-10`

---

## Índice

1. [Legenda](#legenda)
2. [Classificação e políticas](#1-classificação-e-políticas)
3. [Enforcement e anti-bypass](#2-enforcement-e-anti-bypass)
4. [GUI e operação](#3-gui-e-operação)
5. [Licenciamento](#4-licenciamento-e-distribuição)
6. [Blacklists UT1](#5-blacklists-ut1)
7. [Identity + MITM](#6-identity--mitm)
8. [Observabilidade / release](#7-observabilidade-e-release)
9. [Mapa rápido](#8-mapa-rápido--docs)

---

## Legenda

| Status | Significado |
|--------|-------------|
| **Produção** | Pin enforce `1.9.8` (e superiores com GO) |
| **Lab / latest** | Canal `latest` (`1.9.47+`); pode não estar no pin |
| **Experimental** | Existe; OFF por defeito |
| **Candidato** | Local / não publicado |
| **NO-GO** | Activação bloqueada |
| **Planeado** | Backlog sem produto |
| **Fora de escopo** | Explicitamente excluído |

---

## 1. Classificação e políticas

| ID | Funcionalidade | Status | Notas |
|----|----------------|--------|-------|
| F-CLS-01 | Classificação nDPI (~350 apps) | Produção | ADR-0001 |
| F-CLS-02 | Protocolos custom | Produção | `layer7-protos.txt` |
| F-POL-01 | Acções monitor / tag / allow / block | Produção | |
| F-POL-02 | Interface / CIDR / grupo / horário | Produção | |
| F-POL-03 | Match app, categoria, host/SNI | Produção | Limites DPI |
| F-POL-04 | Perfis rápidos | Lab / latest | Caminho A |
| F-POL-05 | Inventário / políticas por dispositivo | Lab / latest | Caminho A |
| F-POL-06 | Allowlist de destinos | Produção | |
| F-POL-07 | Excepções granulares | Produção | |
| F-POL-08 | Teste de política (simulação) | Produção | |
| F-POL-09 | Enforcement `legacy_global` | Produção | Default |
| F-POL-10 | Enforcement `scoped_hybrid` | Experimental | Não default prod |

---

## 2. Enforcement e anti-bypass

| ID | Funcionalidade | Status | Notas |
|----|----------------|--------|-------|
| F-ENF-01 | Tabelas PF + lifecycle flush | Produção | F4 endurece |
| F-ENF-02 | DNS forçado | Produção | Dual-stack GV |
| F-ENF-03 | Anti-DoT/DoQ / anti-DoH | Produção | |
| F-ENF-04 | Anti-QUIC por interface | Produção | |
| F-ENF-05 | Página de bloqueio HTTP | Produção | ADR-0017; MITM OFF |
| F-ENF-06 | Dual-stack IPv4/IPv6 | Produção | Trilha IPv6 FECHADA |
| F-ENF-07 | Isenção VIP (Unbound ACL) | Produção | BG-064+ |

---

## 3. GUI e operação

| ID | Funcionalidade | Status | Notas |
|----|----------------|--------|-------|
| F-GUI-01 | Páginas Estado / Definições / Políticas / … | Produção | Padrão pfSense |
| F-GUI-02 | GUI bilingue PT/EN | Produção | `l7_t()` |
| F-GUI-03 | Dashboard / Events / Relatórios | Produção | ADR-0015 |
| F-GUI-04 | Diagnósticos (PID, PF, sinais, logs) | Produção | |
| F-GUI-05 | **Reportar erro** (GitHub pré-preenchido) | Candidato `1.9.48` | Opt-in; sem segredos — [fluxo](pack-produto-layer7.md#como-funciona-reportar-erro) |
| F-GUI-06 | Verificar / instalar actualização | Produção | BG-030 |
| F-GUI-07 | Backup/restore JSON | Produção | |
| F-GUI-08 | Removal GUI + pkg-deinstall limpo | Produção | BG-033 |
| F-GUI-09 | Acesso Remoto (guia) | Lab / latest | `1.9.13+` |

---

## 4. Licenciamento e distribuição

| ID | Funcionalidade | Status | Notas |
|----|----------------|--------|-------|
| F-LIC-01 | `.lic` Ed25519 + fingerprint | Produção | |
| F-LIC-02 | Activação online | Produção | F3 fechada |
| F-LIC-03 | Grace local / validade offline | Produção | |
| F-LIC-04 | Check-in / revogação remota | Produção (flag) | ADR-0021 |
| F-LIC-05 | Portal admin (operador único) | Produção ops | Visual `2.0.0` |
| F-LIC-06 | Distribuição `.pkg` GitHub Releases | Produção | ADR-0003 |
| F-LIC-07 | Manifesto/assinatura trust chain | Planeado | BG-028 / ADR-0023 |
| F-LIC-08 | MSP / self-service / billing | Fora de escopo | Sem GO |

---

## 5. Blacklists UT1

| ID | Funcionalidade | Status | Notas |
|----|----------------|--------|-------|
| F-BL-01 | Snapshot assinada + mirror | Produção | F1.3 |
| F-BL-02 | Fail-closed fingerprint mismatch | Produção | F1.4 |
| F-BL-03 | Categorias custom / extensão UT1 | Produção | |
| F-BL-04 | Cron / reload / LKG | Produção | F4.2 em curso |

---

## 6. Identity + MITM

| ID | Funcionalidade | Status | Notas |
|----|----------------|--------|-------|
| F-ID-01 | User-ID de rede (IP→user) | Lab / latest (FECHADA) | ADR-0027 |
| F-ID-02 | RADIUS / DC agent / LDAP | Lab / latest | Sem captive |
| F-ID-03 | Agente endpoint PC | Fora de escopo MVP | ADR-0029 |
| F-MITM-01 | Control-plane + `intercept_ready` | Lab / latest | Default OFF |
| F-MITM-02 | rdr escopado source∧dest | Lab / latest | Proibido `from any` |
| F-MITM-03 | Janela failsafe / auto-disable | Lab / latest | P3 PASS `1.9.47` |
| F-MITM-04 | Piloto externo / permanente | **NO-GO** | P4 FAIL/ABORT |
| F-MITM-05 | Squid MITM | Fora de escopo | Rejeitado |

---

## 7. Observabilidade e release

| ID | Funcionalidade | Status | Notas |
|----|----------------|--------|-------|
| F-OBS-01 | Logs locais + syslog UDP | Produção | |
| F-OBS-02 | Contenção L1 rotação logs | Produção | ADR-0015 |
| F-OBS-03 | Telemetria operacional mínima | Planeado | F7 / BG-018 |
| F-REL-01 | RELEASE-CHECKLIST | Produção docs | |
| F-REL-02 | Fleet update scripts SSH | Produção ops | Externo ao runtime |

---

## 8. Mapa rápido → docs

| Necessidade | Documento |
|-------------|-----------|
| Índice do pack | [`pack-produto-layer7.md`](pack-produto-layer7.md) |
| Requisitos / aceitação | [`prd-layer7.md`](prd-layer7.md) |
| Diagramas | [`uml-layer7.md`](uml-layer7.md) |
| Manual operador | [`../MANUAL-PRODUTO.md`](../MANUAL-PRODUTO.md) |
| Install | [`../10-license-server/MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md) |
| MITM / Identity | [`START-HERE-identity-mitm.md`](START-HERE-identity-mitm.md) |
| Fecho filas | [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md) |
| Limitações DPI | [`../09-blocking/matriz-limitacoes-dpi.md`](../09-blocking/matriz-limitacoes-dpi.md) |
