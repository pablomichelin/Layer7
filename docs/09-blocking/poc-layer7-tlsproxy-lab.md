# PoC lab — `layer7-tlsproxy`

**Estado:** PoC-4 + **S1/S2 lab PASS** em `192.168.100.54`  
**NÃO é** 20.10.

| Lab | `root@192.168.100.54` |
| Binário | `0.0.4-poc4` |
| Produção `.254/.234/.235` | **PROIBIDO** intercept |

## Gates S* (lab)

| # | Estado |
|---|--------|
| S1 | **PASS lab** (localhost+upstream); inline produto **PENDING** — `20260809T043000Z-s1s2-load-54` |
| S2 | **PASS** p95≈3.4 ms (n=500) |
| S3/S4 | **PASS** (PoC-3) |
| S5/S7/S8 | PASS (pré-runtime / real prod OFF) |
| S6 | Nota: ECH não exercitado |

## Smoke

```bash
cd /opt/layer7-poc/src
make STUB=./poc-upstream-stub.py test test-poc3 test-poc4
N=100 sh ./measure-s1-s2.sh
sh ./conc-smoke.sh
```

**Não** usar `pkill -f layer7…` dentro do comando SSH (mata a sessão).
