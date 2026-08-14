# Evidência P4 soak — `20260813T170000Z-p4-retry-254`

**Estado: CLOSED — FAIL** (`2026-08-13T20:12:00Z`).

## Resultado
- Veredicto: **FAIL**
- Motivo: health_ssh_fail sample=14 (`AUTH_FAIL no_key_no_SSHPASS_no_passfile`)
- Health samples: 15
- Rollback limpo: 0
- MITM OFF verificado no fecho: 0

## Pós-fail (bloco documental `223009Z`)
Verificação read-only posterior: MITM **OFF**; P4.1 cron live; pacote `1.9.59`.
[`../20260813T223009Z-p4-postfail-verify-254/`](../20260813T223009Z-p4-postfail-verify-254/)

## Escopo GO (respeitado)
- Upgrade `.254` `1.9.54` → `1.9.59`
- MITM scoped: src `192.168.100.24/32` → dst `198.18.0.10/32` (`.54`), SNI `mitm-lab.test`
- CA efémera, `max_window=240`, `quic_mode=block`, sem payload TLS
- Sem `.234`/`.235`, sem destinos externos, sem `from any`

## Nota Skip
Aprovação nativa **Skip** = só recusa de `example.com` como negativo; **não** abortou o P4.

## Artefactos
- Health: `07-health-*.txt`, `07-soak-loop.log`
- Rollback: `90-rollback-*.txt`, `91-rollback-24.txt`, `92-rollback-54.txt`, `93-post-state.txt`
- Veredicto: `11-VERDICT.txt`
