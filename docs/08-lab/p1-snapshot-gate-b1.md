# P1 — Snapshot appliance antes do Gate B1 (`_65`)

**Plano mestre:** passo **1.2** (Onda P1)  
**Data preparação:** 2026-08-04  
**Appliance:** `192.168.100.254` (`systemupfw.system.up`)  
**Candidato lab (próximo upgrade):** `1.8.11_65`  
**Produção enforce (rollback de referência):** `1.8.11_24`

---

## Estado observado antes do snapshot (read-only)

| Campo | Valor |
|-------|-------|
| OS | pfSense **Plus** `26.03.1` / FreeBSD `16.0-CURRENT` |
| Pacote instalado | `pfSense-pkg-layer7-1.8.11_62` |
| `layer7d` | vivo (pid `34928`) |
| `enabled` | `false` |
| `mode` | `monitor` |
| `enforcement_model` | `legacy_global` |
| Licença | válida (`customer=Systemup`, expira `2028-07-08`) |
| Regras PF Layer7 block | **zero** |
| Diagnose baseline | [`docs/tests/evidence/20260804T220000Z-p1-baseline-appliance254/diagnose-baseline.txt`](../tests/evidence/20260804T220000Z-p1-baseline-appliance254/diagnose-baseline.txt) |

> **Importante:** o appliance **não** está em `_65`. O snapshot deve capturar o estado
> **antes** de `pkg add` do candidato `_65` (Gate B1 / Onda A).

---

## Registo do snapshot (preencher após acção humana no hipervisor)

| Campo | Valor |
|-------|-------|
| **Snapshot ID / nome** | `PENDENTE — criar no hipervisor` |
| **Nome sugerido** | `p1-pre-gate-b1-65-2026-08-04` |
| **Hipervisor** | `PENDENTE` (Proxmox / VMware / Hyper-V / outro) |
| **VM / host** | `systemupfw.system.up` |
| **Data/hora UTC** | `PENDENTE` |
| **Operador** | `PENDENTE` |
| **Restaurável?** | `PENDENTE — testar restore antes de Onda A` |
| **Rollback documentado para `_24`?** | Sim — [`MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md) |

### Procedimento (humano)

1. Confirmar SSH ao appliance: `ssh root@192.168.100.254` (testado `2026-08-04`).
2. No hipervisor, criar snapshot da VM **com Layer7 parado ou em estado estável**
   (estado actual: `enabled=false`, `mode=monitor`, daemon vivo).
3. Registar **Snapshot ID** na tabela acima.
4. (Recomendado) Testar restore numa janela de manutenção antes de instalar `_65`.
5. Avisar o coordenador para actualizar `CORTEX.md` (passo 1.2 → PASS).

### Critério de aceite (passo 1.2)

- [ ] Snapshot ID registado neste ficheiro
- [ ] Data/hora e hipervisor preenchidos
- [ ] Operador confirma restaurável (ou nota de risco)
- [ ] CORTEX actualizado pelo coordenador

---

## Rollback

- **Pré-gate:** restaurar snapshot registado acima.
- **Pós-upgrade `_65` com falha:** restaurar snapshot **ou** reinstalar `_24` passivo
  conforme [`MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md).

---

## Ligações

- [`snapshots-e-gate.md`](snapshots-e-gate.md) — convenções gerais
- [`plano-gates-producao.md`](../09-blocking/plano-gates-producao.md) — Gate B1
- [`validacao-lab.md`](../04-package/validacao-lab.md) — roteiros appliance
