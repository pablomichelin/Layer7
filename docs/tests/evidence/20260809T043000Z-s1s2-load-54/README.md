# Evidência — S1/S2 load + smokes (`192.168.100.54`)

**Data:** `2026-08-09`  
**Binário:** `0.0.4-poc4`  
**Host:** `root@192.168.100.54`

## Resultados

| Gate | Resultado |
|------|-----------|
| Regressão test/poc3/poc4 | PASS |
| **S2** proxy n=500 | p95 **3.39 ms** (≤150) **PASS** |
| S2 baseline direct | p95 0.67 ms |
| **S1 lab** | CPU busy ~12.9% durante 500 reqs — **PASS lab**; inline gateway **PENDING** |
| Concurrent allow+block | **PASS** |
| Sem `LAYER7_TLSPROXY_LAB` | exit 3 |
| Upstream `8.8.8.8` | **recusado** |

## Honestidade

S1/S2 aqui medem **TLS lab localhost + upstream loopback**, não MITM inline no pfSense.
S5/S7/S8 já PASS noutros artefactos; S6 ECH não exercitado (SNI em claro).

## Lição operacional

Não usar `pkill -f layer7-tlsproxy` no mesmo comando SSH — o padrão casa com a linha de comando do `sshd` e mata a sessão.
