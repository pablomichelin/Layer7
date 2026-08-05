# Pós-Veeam — cleanup G5 + reteste paridade CE (proxy)

**run_id:** `20260805T004800Z-ondaE-ce-parity-retest`  
**Pré-requisito:** backup Veeam confirmado pelo operador (`2026-08-04`)

## Acções

1. Removida regra `g5-test-bl` de `blacklists/config.json` (permanente)
2. `layer7-pfctl flush-all` + `/etc/rc.filter_configure`
3. Reteste `run-ondaE-ce-parity-appliance.sh`
4. G2 passivo `enabled=false`

## Resultados

| Teste | Resultado |
|-------|-----------|
| g5-test-bl removido do PF | **PASS** |
| G2.3 monitor (`mode=monitor`) | **PASS** — zero block drop layer7 |
| G2.3 passivo (`enabled=false`) | **PASS** |
| smoke-monitor PF | **PASS** |
| smoke-monitor tabelas estáticas | **FAIL** — tabelas ausentes após `filter_configure` em monitor (não bloqueante para paridade ABI) |
| Veredicto CE Onda E | **LIMITAÇÃO** inalterada — sem VM CE |

## Estado final appliance

`mode=enforce`, `enabled=true`, `legacy_global` — restaurado.
