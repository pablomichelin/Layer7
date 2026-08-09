# Restore `1.9.46` + smoke enforce `.24` — NO-GO (disable não executado)

| Campo | Valor |
|-------|--------|
| `run_id` | `20260809T224632Z-enfs24-1946` |
| GO humano | Concluir bloqueios: acesso `.24` → restaurar `1.9.46` → smoke no-MITM → disable+flush só se PASS |
| Veredicto | **NO-GO** (smoke sem PASS completo) |
| Pacote final `.254` | **`1.9.46`** (SHA oficial OK) |
| Disable / flush | **não executados** |

---

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| **Objectivo** | Resolver drift `1.9.44`→`1.9.46`, provar enforce no-MITM a partir de `.24`, e só então desactivar Layer7 + flush canónico |
| **Impacto** | Upgrade de pacote no `.254` (já aplicado); config enforce mantida; sem disable |
| **Risco** | Médio no upgrade (revertível); risco de desactivar sem prova — **evitado** |
| **Teste** | SSH `.24` PASS; SHA PASS; upgrade PASS; smoke FAIL / re-smoke SKIPPED (aprovação nativa) |
| **Rollback** | Snapshot appliance: `/tmp/l7-preg2-snap-20260809T221619Z-preG2-G2-254` (conservado). Pacote lab `1.9.42`. Veeam |

---

## Passo 1 — Acesso `.24`

| Check | Resultado |
|-------|-----------|
| Canal | **SSH** password (`administrador`) — WinRM não necessário |
| Credencial | usada em memória/`/tmp` agente; **removida** ao fecho; **não** revelada nem commitada |
| Evidência | `remote/01-ssh24.txt` |

---

## Passo 2 — Drift + restore `1.9.46`

| Check | Resultado |
|-------|-----------|
| Snapshot prévio | **conservado** em `/tmp/l7-preg2-snap-20260809T221619Z-preG2-G2-254` |
| Drift pré-upgrade | **`1.9.44`** (`remote/02-pre-upgrade.txt`) |
| Fetch GitHub Release `v1.9.46` | OK |
| SHA256 | `10998477ef7ae966e6c3566baeb973f922858fc72cc4d3a2dcdd0fb17bae72f5` — **SHA_OK** |
| Upgrade canónico | `IGNORE_OSVERSION=yes pkg add -f` — **PASS** → `pkg`/`layer7d`=`1.9.46` |
| Pós-upgrade | `enabled=true` / `mode=enforce` / MITM OFF / `block_dst` materializado |
| Evidência | `remote/03-fetch-sha.txt`, `04-upgrade-1946.txt`, `05-post-upgrade-health.txt` |

---

## Passo 3 — Smoke enforce no-MITM (fonte `.24`)

Par planeado (inequívoco no PF live):

| Papel | Alvo | Base |
|-------|------|------|
| Block | `17.250.105.4` | membro de `layer7_block_dst` |
| Allow controlo | `8.8.8.8` / `example.com` | em `allow_dst` / destino seguro de lab |
| Perfil | AnyDesk (opcional) | perfil `profile-anydesk` block |

| Tentativa | Resultado |
|-----------|-----------|
| Probe PowerShell inicial | `remote/06-smoke-24.txt` — só banner SSH; **sem métricas** |
| Veredicto parse | `SMOKE_VERDICT=FAIL` (`remote/08-smoke-verdict.txt`) |
| Correlação | `remote/07-smoke-correlate.txt` — eventos históricos ≠ prova deste smoke |
| Re-smoke curl/TNC | **não executado** — aprovação nativa Cursor **SKIPPED** (2×) |

**Decisão:** sem PASS completo → **parar**; **não** gerar tráfego arbitrário adicional sem aprovação; **não** desactivar.

---

## Passo 4 — Disable + flush canónico

**Não executado** (pré-condição: smoke PASS completo).

Estado live ao fecho (`remote/09-final-state-nogo.txt`, `09c-json-live.txt`):

- Pacote / `layer7d`: **1.9.46**
- Layer7: **enabled=true**, **mode=enforce**
- `layer7_block_dst`: `17.250.105.4`, `17.250.105.11`
- MITM tabelas vazias; tlsproxy não em execução

---

## Preservações

- `.234` / `.235`: não usados
- `.54`: não tocado
- Untracked abortados `20260809T212230Z-*` / `20260809T212234Z-*`: intocados
- `docs/changelog/CHANGELOG.md` (outra sessão): **não** incluído neste commit

---

## Artefactos

- `00-RUNID.txt`, `00-META.txt`, `11-VERDICT.txt`
- `remote/01`…`09*`
