# Evidência P4 soak — `20260809T234042Z-p4-soak-254`

**Veredicto: ABORT** (não PASS).

## Escopo GO
- Upgrade passivo `.254` `1.9.46` → `1.9.47`
- MITM scoped: src `192.168.100.24/32` → dst `198.18.0.10/32` (`.54`), SNI `mitm-lab.test`
- CA efémera, `max_window=240`, `quic_mode=block`, sem payload TLS
- Sem `.234`/`.235`, sem destinos externos, sem `from any`

## O que passou
- Preflight MONITOR + MITM OFF
- Upgrade passivo OK
- Activação scoped + materialização PF/tlsproxy + auditoria `activate` (`payload_tls:false`)
- Health T0 / amostra 01: escopo OK, GUI 200
- Failsafe ops `.54` limpo para job intencional; predicado abort MITM corrigido (falso positivo NAT)

## Porquê ABORT
1. Phase C no `.24` (hosts/CA/probes/Edge) — aprovação nativa **Skip**
2. Soak ≥4 h **não** concluído (interrupção humana para fecho/rollback)

## Rollback
Ver `90-rollback-254.txt`, `91-rollback-24.txt`, `92-rollback-54.txt`, `93-post-state.txt`.
Pós-estado: MITM OFF, `mode=monitor`, pkg `1.9.47`, sem rota 198.18, GUI OK.

## Seguinte
- **P5** continua a aguardar **ficha de site de cliente** — não activar piloto externo/permanente
- Manual público do produto = bloco documental separado após este fecho
