# Evidência P4 soak — `20260809T234042Z-p4-soak-254`

**Estado: SOAK_IN_PROGRESS** (GO P4 válido; retomado `2026-08-09T23:57:30Z`).

## Nota sobre Skip / ABORT

A aprovação nativa **Skip** recusou apenas o uso de `example.com` como negativo.
**Não** foi decisão humana de abortar o P4 nem de negar acesso `.24`.
Um rollback prematuro ocorreu por interpretação incorrecta; o soak foi **reativado**
com Phase C **corrigida** (só destinos internos).

## Escopo GO
- Upgrade passivo `.254` `1.9.46` → `1.9.47`
- MITM scoped: src `192.168.100.24/32` → dst `198.18.0.10/32` (`.54`), SNI `mitm-lab.test`
- CA efémera, `max_window=240`, `quic_mode=block`, sem payload TLS
- Sem `.234`/`.235`, sem destinos externos, sem `from any`

## Phase C corrigida (PASS)
- hosts `mitm-lab.test` → `198.18.0.10`
- CA `Layer7-P4-Soak-CA` importada
- Peer TLS: subject `CN=mitm-lab.test`, issuer `CN=Layer7-P4-Soak-CA`
- HTTP in-scope: **403** (página de bloqueio MITM)
- Fora de escopo: tabelas PF SRC/DST + `MITM_SRC_SCOPED_OK` (sem tráfego externo)
- Evidência: `05-phaseC-24-internal-only.txt`, `07-pf-audit-resume.txt`

## Soak
- Health loop 15 min / ~4 h — `07-soak-loop.pid`
- **Sem rollback** até PASS/FAIL/abort por predicado ou GO humano

## Ficheiros chave
- Resume log: `00-resume-phaseC-internal.log`
- Activate: `06-phaseD-activate-resume.txt`
- Status: `11-STATUS.txt` / `11-VERDICT.txt`
