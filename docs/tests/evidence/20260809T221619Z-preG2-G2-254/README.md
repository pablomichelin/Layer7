# Pré-G2 / G2 — `1.9.46` em `192.168.100.254`

| Campo | Valor |
|-------|--------|
| `run_id` | `20260809T221619Z-preG2-G2-254` |
| GO humano | Confirmado (Pré-G2/G2 passivo) |
| Artefacto | `v1.9.46` SHA256 `10998477ef7ae966e6c3566baeb973f922858fc72cc4d3a2dcdd0fb17bae72f5` |
| Pacote instalado | **`1.9.46`** (sem install/upgrade neste bloco) |
| Veredicto | **NO-GO G2 passivo** |
| Motivo | Appliance **não** está passivo: `enabled=true`, `mode=enforce`, `layer7_block_dst` com membros |

---

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| **Objectivo** | Snapshot recuperável + confirmar `1.9.46` + G2 estritamente passivo |
| **Impacto** | Apenas backup em `/tmp` + evidência documental; **sem** alteração de `enabled`/PF/pacote |
| **Risco** | Baixo neste bloco (sem mutação de enforce). Risco operacional se alguém forçar passivo sem GO — bloquearia produção |
| **Teste** | Inventário pkg/`layer7d -V`/JSON/PF/NAT/tabelas/stats; `pfctl -nf` RC=0 |
| **Rollback deste bloco** | N/A em runtime (config live intocada). Snapshot config em `/tmp` (ver abaixo). VM: Veeam. Pacote lab: `1.9.42` |

---

## (1) Snapshot

| Campo | Valor |
|-------|--------|
| Mecanismo | Runbook `1.9.46` §1 — cópia `config.xml` + `layer7.json` |
| ID / dir | `/tmp/l7-preg2-snap-20260809T221619Z-preG2-G2-254` |
| Hora UTC | `2026-08-09T22:16:20Z` |
| `config.xml` SHA256 | `9addeb5c6198158f61463c1377be4138818d4458e13bbaf5c15d9cd12567fc28` |
| `layer7.json` SHA256 | `328942019de00a9af5044fb33486465a55ff23be2315a64f5973b81188225fb4` |
| Como restaurar (config) | Ver `RESTORE.txt` no dir do snapshot (`cp -a` dos `.bak`) |
| VM-level | Veeam diário `.254` — [`p1-snapshot-gate-b1.md`](../../../08-lab/p1-snapshot-gate-b1.md) |

**Nota:** o snapshot captura o estado **actual** (já em enforce). Restaurá-lo **não** torna o host passivo.

---

## (2) Artefacto / versão

| Check | Resultado |
|-------|-----------|
| GitHub `v1.9.46` SHA | `10998477…ae72f5` |
| `/tmp/...1.9.46.pkg` no appliance | **mesmo SHA** |
| `pkg query` / `layer7d -V` | **1.9.46** |
| Install/upgrade neste bloco | **não executado** (versão já correcta) |

---

## (3) G2 passivo — critérios vs observado

| Critério G2 passivo | Esperado | Observado | |
|---------------------|----------|-----------|---|
| Pacote `1.9.46` | sim | sim | OK |
| `layer7d` status / PID | vivo | pid `96485` | OK |
| `layer7-tlsproxy` | parado | not running | OK |
| `ldd` binário | OK | libs resolvidas (incl. ldap) | OK |
| `enabled` | `false` | **`true`** | **FAIL** |
| `mode` | monitor / não-enforce | **`enforce`** | **FAIL** |
| MITM effective | OFF | `mitm_effective=false`; sem rdr/8443/tabelas mitm/rota 198.18 | OK effective |
| MITM CA metadata | ausente (ideal) | `mitm.ca.present=true` (PhaseD lab; dir `mitm/` vazio) | aviso |
| Enforcement / tabelas dinâmicas | vazias / sem block activo | `enforce_mode=1`; `block_dst`=2 IPs; `allow_dst`=31; `total_blocked=57` | **FAIL** |
| NAT/rdr MITM / anti-QUIC MITM | zero | zero | OK |
| `pfctl -nf` rules.debug | RC=0 | RC=0 | OK |

Ficheiros: `remote/02-snapshot-and-inventory.txt`, `remote/03-G2-passive.txt`, `remote/04-config-deep.txt`.

---

## Acção de rollback neste bloco

**Não** foi forçado `enabled=false` nem flush PF nem downgrade para `1.9.42`: isso alteraria enforce de produção sem GO dedicado.

Desvio = critérios G2 passivo não cumpridos → **parar** com **NO-GO** (sem mutação correctiva).

---

## Próximo gate

1. **GO humano** para um de: (A) aceitar inventário em enforce e redefinir G2 para “revalidate installed 1.9.46” sem exigir passivo; ou (B) janela controlada para passar a passivo (`enabled=false` + flush documentado) e reexecutar G2; ou (C) outro alvo de lab.  
2. Só depois: **G3** (já parcialmente OK via `pfctl -nf`) / **G4** monitor — **não** G5 neste trilho até passivo ou GO explícito.

---

## Restrições respeitadas

- Sem tocar `.24` / `.54`
- Sem gerar CA / activar regras / publicar / apagar
- Sem install (versão já `1.9.46`)
- Dirs untracked abort `212230Z` / `212234Z` preservados
