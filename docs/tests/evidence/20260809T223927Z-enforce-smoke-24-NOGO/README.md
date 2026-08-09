# Smoke enforcement `.24` → NO-GO (sem tráfego / sem desactivar)

| Campo | Valor |
|-------|--------|
| `run_id` | `20260809T223927Z-enforce-smoke-24-NOGO` |
| GO humano | Testar enforcement actual; MITM OFF; desactivar só se testes PASS |
| Veredicto | **NO-GO** |
| Mutações | **nenhuma** (sem tráfego sintético, sem disable, sem flush, sem PF) |

---

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| **Objectivo** | Validar semântica allow/block do enforce actual a partir de `.24`, depois desactivar Layer7 se PASS |
| **Impacto** | Zero runtime; só evidência + CORTEX |
| **Risco** | Evitado: não gerar tráfego arbitrário nem desactivar enforce de produção sem prova |
| **Teste** | Passo 2 (derivação read-only) **FAIL** → paragem antes do passo 3/4 |
| **Rollback** | N/A (config live intocada). Snapshot prévio: `/tmp/l7-preg2-snap-20260809T221619Z-preG2-G2-254`. Veeam. Pacote lab `1.9.42` |

---

## Passo 1 — Docs relidos

- `CORTEX.md` (checkpoint: `.254` em enforce `1.9.46` na evidência anterior)
- `plano-gates-producao.md` (G2/G5 contexto)
- Evidência Pré-G2: `20260809T221619Z-preG2-G2-254`

---

## Passo 2 — Derivação de alvos (fonte `.24`) — **FAIL**

### Estado actual do appliance (leitura)

| Item | Valor |
|------|--------|
| Pacote / `layer7d` | **`1.9.44`** (drift vs Pré-G2 `1.9.46`) |
| `enabled` / `mode` | `true` / `enforce` / `legacy_global` |
| `mitm.enabled` / `mitm_effective` | `false` / `false` |
| `layer7_block_dst` | **tabela inexistente** (0 IPs bloqueados live) |
| `layer7_block` | inexistente |
| `layer7_allow_dst` | 3 membros (controlo potencial, mas sem par block live) |
| MITM rdr / anti-QUIC | regras presentes; tabelas `mitm_src/dst` **vazias**; tlsproxy **parado** |
| Último block observado | `192.168.95.7` → `17.250.105.4/11` (AppleiCloud) — **não** origem `.24`; tabela já não materializa |

### Fonte de teste `.24`

| Check | Resultado |
|-------|-----------|
| ICMP `.24` | OK |
| SSH `root/admin/Administrator/pablo/michelle/systemup` | **Permission denied** (chave) |
| `.234` / `.235` | **não usados** (proibidos) |

### Alvos inequívocos?

| Tipo | Candidato | Seguro? |
|------|-----------|---------|
| Block já materializado em PF | — | **Não** — `block_dst` ausente |
| Block histórico Apple DNS | `17.250.105.4` | **Não** — não está na tabela; política `anti-bypass-dns` pode ter efeitos colaterais; origem histórica ≠ `.24` |
| Allow live | 3 IPs em `allow_dst` | Insuficiente **sem** par block inequívoco + sem shell em `.24` |

**Decisão canónica do pedido:** sem alvos inequivocamente seguros → **NO-GO sem gerar tráfego arbitrário**.

---

## Passo 3 — Testes allow/block

**Não executados.**

## Passo 4 — Desactivar Layer7

**Não executado** (só permitido se passo 3 PASS).

---

## Observações para próximo GO

1. Restaurar acesso SSH/WinRM ao `.24` (ou credencial documentada).  
2. Confirmar versão alvo (`1.9.46` vs drift `1.9.44`).  
3. Dispor de destino **já** em `layer7_block_dst` (ou janela que materialize um IP de lab inócuo) **e** um controlo em `allow_dst`/allowlist.  
4. Limpar resíduo MITM (rdr/anti-QUIC com tabelas vazias) só com GO — fora deste bloco.  
5. Só então repetir smoke + disable.

---

## Ficheiros

- `remote/01-inventory.txt`
- `remote/02-source24-access.txt`
- `11-VERDICT.txt`

Abort untracked `212230Z` / `212234Z` preservados.
