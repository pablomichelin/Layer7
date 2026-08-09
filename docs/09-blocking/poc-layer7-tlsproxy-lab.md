# PoC lab — `layer7-tlsproxy`

**Estado:** **PoC-4 PASS** (upstream allow) em `192.168.100.54`  
**NÃO é** 20.10.

| Campo | Valor |
|-------|--------|
| Lab | `root@192.168.100.54` |
| Binário | `0.0.4-poc4` · `/opt/layer7-poc/` |
| Produção | **PROIBIDO** intercept |

## Fases

| Fase | Estado |
|------|--------|
| PoC-0…3 | PASS |
| PoC-4 upstream localhost | **PASS** — `20260809T042600Z-poc4-upstream-54` |
| 20.10 | BLOQUEADO |

## Smoke (no hang)

```bash
cd /opt/layer7-poc/src
make STUB=/opt/layer7-poc/scripts/poc-upstream-stub.py test test-poc3 test-poc4
```

Harness: `run-poc-tests.sh` + `timeout` (make não espera daemons em background).
