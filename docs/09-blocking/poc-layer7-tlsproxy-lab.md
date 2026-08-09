# PoC lab — `layer7-tlsproxy`

**Estado:** **PoC-5** + **S1/S2 inline Opção A PASS** em `192.168.100.54`  
**NÃO é** 20.10.

| Lab | `root@192.168.100.54` |
| Binário | `0.0.5-poc5` |
| Produção `.254/.234/.235` | **PROIBIDO** intercept |
| GO inline | [`GO-opcao-A-inline-lab-54.md`](GO-opcao-A-inline-lab-54.md) |

## Gates S* (lab)

| # | Estado |
|---|--------|
| S1 | **PASS lab** localhost (`20260809T043000Z-s1s2-load-54`) **e** inline Opção A (`20260809T045500Z-s1-inline-opcao-a-54`) |
| S2 | **PASS** localhost p95≈3.4 ms (n=500); inline p95≈15.6 ms (n=50) |
| S3/S4 | **PASS** (PoC-3) |
| S5/S7/S8 | PASS (pré-runtime / real prod OFF); S7 PoC runtime: [`s6-s7-lab-notas-opcao-a.md`](s6-s7-lab-notas-opcao-a.md) |
| S6 | Nota ECH: não exercitado ([`s6-s7-lab-notas-opcao-a.md`](s6-s7-lab-notas-opcao-a.md)) |

## Smoke

```bash
cd /opt/layer7-poc/src
make STUB=./poc-upstream-stub.py test test-poc3 test-poc4
N=100 sh ./measure-s1-s2.sh
sh ./conc-smoke.sh
N=50 sh ./measure-s1-inline.sh   # Opção A; exige root + iproute2
LAYER7_TLSPROXY_LAB=1 python3 ./ipc-decide-mock.py &  # mock peer; effective=false
```

**Não** usar `pkill -f layer7…` dentro do comando SSH (mata a sessão).
