# P1 — Plano de rollback antes do Gate B1 (`_65`)

**Plano mestre:** passo **1.2** (Onda P1)  
**Data:** 2026-08-04  
**Topologia real do lab:**

| Onde | O quê |
|------|--------|
| **MacBook** | Pasta do projecto Layer7 (documentação + código) |
| **FreeBSD** `192.168.100.12` | Builder — compila `.pkg`; backup **Veeam diário** |
| **pfSense** `192.168.100.254` | Servidor de **produção** Systemup — testes manuais Layer7; backup **Veeam diário** |
| **License server** `192.168.100.244` | Stack live PostgreSQL/Docker; backup **Veeam diário** |

Rollback: **Veeam** (diário) + reinstalação de pacote via MANUAL-INSTALL.

---

## Estado actual do appliance (baseline 2026-08-04)

| Campo | Valor |
|-------|-------|
| Host | `systemupfw.system.up` (`192.168.100.254`) |
| OS | pfSense Plus `26.03.1` / FreeBSD `16.0-CURRENT` |
| Pacote instalado | `pfSense-pkg-layer7-1.8.11_68` (candidato lab `2026-08-04`) |
| Layer7 | `enabled=false`, `mode=monitor`, `legacy_global` |
| Licença | válida |
| Diagnose | [`docs/tests/evidence/20260804T220000Z-p1-baseline-appliance254/`](../tests/evidence/20260804T220000Z-p1-baseline-appliance254/) |

---

## Rollback aceite para este lab

### 1. Veeam (principal)

Backup diário do pfSense `192.168.100.254`, do builder `192.168.100.12` e do
license server `192.168.100.244`.

**Estado:** **PASS** — confirmado operador `2026-08-04`; reconfirmado
`2026-08-04` (evidência `20260804T211800Z-veeam-prerequisite-PASS`).

### 2. Rollback por pacote (complementar)

Reinstalar versão anterior via [`MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md):

- **Volta ao estado pré-upgrade:** `_62` ou `_65` conforme necessário
- **Referência enforce:** `_24` passivo

### 3. Backup XML pfSense (opcional)

**Diagnostics → Backup & Restore** na GUI — complemento pontual antes de upgrades.

---

## Registo passo 1.2

| Campo | Valor |
|-------|-------|
| **Método de rollback** | Veeam diário + reinstalação pacote (MANUAL-INSTALL) |
| **Backup infra** | Veeam — pfSense `254` + builder `12` + license server `244` |
| **Versão pré-upgrade (baseline)** | `1.8.11_62` |
| **Versão enforce de referência** | `1.8.11_24` |
| **Backup XML pfSense** | Opcional — operador pode fazer antes da Onda A |
| **Estado passo 1.2** | **PASS** — Veeam OK (`254`+`12`+`244`); evidência `211800Z-veeam-prerequisite-PASS` |

---

## Antes da Onda A (instalar `_65`)

1. Confirmar SSH: `ssh root@192.168.100.254`
2. (Opcional) Backup XML na GUI do pfSense
3. Instalar `_65` em modo passivo (`enabled=false`, `mode=monitor`)
4. Se falhar: reinstalar `_62` ou `_24` conforme MANUAL-INSTALL

---

## Ligações

- [`MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md) — install / upgrade / rollback
- [`plano-gates-producao.md`](../09-blocking/plano-gates-producao.md) — Gate B1 (G2–G4)
- [`validacao-lab.md`](../04-package/validacao-lab.md) — roteiros appliance
