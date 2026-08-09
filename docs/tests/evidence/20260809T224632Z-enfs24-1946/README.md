# Restore `1.9.46` + acesso `.24` — NO-GO parcial (sem smoke externo)

| Campo | Valor |
|-------|--------|
| `run_id` | `20260809T224632Z-enfs24-1946` |
| Veredicto | **NO-GO parcial** |
| Confirmado | Upgrade oficial `1.9.46` + acesso SSH `.24` |
| Bloqueio | Smoke allow/block **não** pode prosseguir sem par de destinos **LAB local** aprovado |
| Disable / flush | **não executados** |

---

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| **Objectivo** | Corrigir drift `1.9.44`→`1.9.46` e preparar smoke enforce no-MITM a partir de `.24` |
| **Impacto** | Pacote `.254` = `1.9.46`; config enforce **intacta**; sem tráfego de teste canónico |
| **Risco** | Evitado: não gerar tráfego para destinos públicos; não desactivar sem smoke PASS |
| **Teste** | Leituras de estado/health apenas (`remote/10*`) |
| **Rollback** | Snapshot `/tmp/l7-preg2-snap-20260809T221619Z-preG2-G2-254`. Pacote lab `1.9.42` |

---

## Confirmado (PASS)

| Item | Evidência |
|------|-----------|
| SSH `.24` (`administrador`) | `remote/01-ssh24.txt` |
| Drift pré-upgrade `1.9.44` | `remote/02-pre-upgrade.txt` |
| Fetch GitHub `v1.9.46` + SHA `10998477…ae72f5` | `remote/03-fetch-sha.txt` |
| Upgrade canónico `pkg add -f` → `1.9.46` | `remote/04-upgrade-1946.txt` |
| Pós-upgrade MITM OFF, daemon up | `remote/05-post-upgrade-health.txt` |
| Health RO pós-decisão humana | `remote/10-health-ro-nogo-parcial.txt` |
| Reachability `.24` (ICMP/22) sem probes outbound | `remote/10b-ssh24-reachability-ro.txt` |
| Snapshot pré-G2 conservado no appliance | `remote/10-health-ro-nogo-parcial.txt` |

### Estado live (leitura `2026-08-09T22:50:44Z`)

- `pkg` / `layer7d`: **1.9.46**
- `enabled=true` / `mode=enforce` / `legacy_global` / `mitm=false`
- `layer7_block_dst`: `17.250.105.4`, `17.250.105.11` (**público** — **não** usar como alvo de smoke)
- `layer7_allow_dst`: inclui DNS/Google públicos (**não** usar como alvo de smoke)
- Tabelas MITM inexistentes/vazias; tlsproxy não em serviço de intercept
- Layer7 **não** desactivado

---

## Bloqueio canónico do smoke

GO humano (`PARE`): `17.250.105.4`, `anydesk.com`, `example.com` (e quaisquer
destinos externos equivalentes, incl. controlos públicos tipo `8.8.8.8`) **não**
são alvos de laboratório previamente aprovados.

| Acção | Estado |
|-------|--------|
| Tráfego para destinos públicos | **proibido** neste bloco e no fecho |
| Smoke allow/block | **não prossegue** até existir par LAB local/isolado definido e aprovado |
| Disable Layer7 + flush | **não executado** |

Nota: `remote/06-smoke-24.txt` / `08-smoke-verdict.txt` reflectem tentativa
anterior inválida (sem payload útil) e **não** constituem prova de enforce;
ficam como artefacto histórico do erro de selecção de alvos.

---

## Preservações

- `.234` / `.235`: não usados
- `.54`: não mutado neste bloco
- Untracked abortados `20260809T212230Z-*` / `20260809T212234Z-*`: intocados
- `docs/changelog/CHANGELOG.md` (outra sessão): fora do commit

---

## Próximo bloco

Ver [`REQUIREMENTS-NEXT-LAB-PAIR.md`](REQUIREMENTS-NEXT-LAB-PAIR.md) — requisitos
mínimos para um par allow/block **inteiramente local/isolado**.
