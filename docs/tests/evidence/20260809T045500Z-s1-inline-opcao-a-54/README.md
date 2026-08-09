# Evidência — S1/S2 inline Opção A (`192.168.100.54`)

**Data:** `2026-08-09`  
**Binário:** `0.0.5-poc5` (`--lab-transparent`)  
**GO:** [`docs/09-blocking/GO-opcao-A-inline-lab-54.md`](../../../09-blocking/GO-opcao-A-inline-lab-54.md)  
**Host:** `root@192.168.100.54` (netns `l7poccli` + REDIRECT 443→8443)

## Resultados

| Gate | Resultado |
|------|-----------|
| Path | netns → iptables REDIRECT → tlsproxy `0.0.0.0:8443` → stub `127.0.0.1:19080` |
| **S2 inline** n=50 | p50 **14.77 ms**; p95 **15.60 ms**; errors=0 **PASS** |
| **SO_ORIGINAL_DST** | `orig_dst=1.1.1.1:443` **PASS** |
| **S1 inline** | CPU busy **12.96%** durante 50 reqs **PASS lab** |
| IPC mock | PING/STATUS/DECIDE; `mitm_effective=false` **PASS** |
| Produto | `mitm_effective` / `mitm_runtime_available` **não** alterados |

## Honestidade

Mede path **inline só no lab `.54`**, não pfSense `.254`. Bind `0.0.0.0:8443` + `--lab-allow-any` necessário porque REDIRECT entrega no IP da iface (`10.67.67.1`), não em `127.0.0.1`.

## Rollback

`/opt/layer7-poc/src/lab-inline-down.sh` (executado no fim da medição).
