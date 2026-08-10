# Evidência P4 soak — `20260809T234042Z-p4-soak-254`

**Estado: CLOSED — FAIL/ABORT** (`2026-08-10T00:08:50Z`).

## Resultado
- Veredicto: **FAIL** (outcome `ABORT` operacional) — **não** PASS de soak 4h
- Motivo: supervisor remoto/failsafe **não armado** (aprovação Skip); P4 não pode ficar ativo sem supervisor; P3 auto-expiry sozinho é insuficiente como failsafe operacional
- Rollback completo: **OK** (`.254` MONITOR/MITM OFF; `.54` OFF; `.24` CA/hosts limpos)
- Auth: chave BatchMode (`.254`/`.54`) + ficheiros `0600` `/tmp` (`.24`) — sem segredos no repo

## Escopo GO (respeitado enquanto esteve ON)
- Upgrade `.254` `1.9.46` → `1.9.47`
- MITM scoped: src `192.168.100.24/32` → dst `198.18.0.10/32` (`.54`), SNI `mitm-lab.test`
- Sem `.234`/`.235`, sem destinos externos, sem `from any`
- Phase C interna tinha PASS (issuer `Layer7-P4-Soak-CA`)

## Pós-rollback (verificado)
| Host | Estado |
|------|--------|
| `.254` | `mode=monitor` · `mitm_enabled=false` · `mitm_effective=false` · RDR=0 · sem 8443 · sem rota host lab · sem CA |
| `.54` | `NO_LISTEN` · `NO_VIP` |
| `.24` | CA P4 removida · hosts sem `mitm-lab.test` |

## Segurança
- Scripts ops sem `sshpass -p` literal — `remote/p4-lib-auth.sh`
- Scan local: `07-failsafe-validate-local.txt` PASS
- Relatório: `07-secrets-scan.txt`

## P5
**Proibido** piloto externo/permanente — aguarda ficha site + GO.
