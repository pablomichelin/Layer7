# Evidência P4 soak retry2 — `20260813T224009Z-p4-retry2-254`

**Estado: CLOSED PASS** (janela 4 h + rollback limpo). Permanente **NO-GO**. Phase C cliente `.24` = **NA** neste retry.

## Arranque
- GO: continue chat 2026-08-13 + harness P4.2 (`-T`)
- Pacote `.254`: `1.9.59` (sem upgrade a `1.9.62`)
- Escopo: src `192.168.100.24/32` → dst `198.18.0.10/32`, SNI `mitm-lab.test`
- `max_window=240`, `quic_mode=block`, sem `from any`, sem `.234`/`.235`
- Watchdog auto-arm **no mesmo passo** que a activação (sem Skip)
- Health 1–16: **ok tries=1** (`ssh_key_batchmode_254`) — P4.2
- Deadline UTC: `2026-08-14T02:40:19Z`
- Phase C: **NA** (sem cliente `.24` neste retry; soak de failsafe/janela)

## Fecho
- `WINDOW_END_APPROACHING` `now=1786675159` / deadline `1786675219`
- `SOAK_WINDOW_CLOSE 2026-08-14T02:39:19Z samples=16`
- `rollback_clean=1` · `P4_FULL_ROLLBACK_OK 2026-08-14T02:39:29Z`
- `SOAK_PASS samples=16 rollback_clean`
- `.254`: `mitm_enabled=false` `mitm_effective=false` `NO_MITM_RDR` `NO_8443` `1.9.59`
- Phase A `.54`: `ROLLBACK_A_OK` / `NO_LISTEN` / `NO_VIP`
- Phase C `.24`: `AUTH_FAIL` (sem ficheiros 0600) — esperado; bloco era NA
- Watchdog: `post_deadline check effective=false listen8443=0` · `WATCHDOG_DONE 2026-08-14T02:43:21Z`

## Verify live (~15 min após fecho)
`94-live-verify-postclose.txt` — `2026-08-14T02:54:33Z`: `mitm_effective=false` `RDR=0` `LISTEN8443=0` tlsproxy parado; loop/watchdog DEAD.

## Failsafe
- P4.1 cron on-box
- Watchdog Mac até deadline+180 s
- `at` +250 min: Phase A rollback + rota `.254`

## Harness
`tests/harness/mitm-p4-soak/` (probe `-T`; rollback com retry)
