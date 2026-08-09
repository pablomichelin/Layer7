# Evidência — PoC-4 upstream allow (`192.168.100.54`)

**Data:** `2026-08-09`  
**Binário:** `0.0.4-poc4`  
**Resultado:** **PASS**

## O que passou

| Check | Resultado |
|-------|-----------|
| Gate IPC/health | PASS |
| PoC-3 S3/S4 (harness sem hang) | PASS |
| PoC-4 allow → `--upstream 127.0.0.1:19080` | `UPSTREAM_OK` |

## Segurança

- Upstream **só** `127.0.0.1` (recusa outros hosts)
- `mitm_effective=false`
- Testes com `timeout` + `trap` + `kill -9` (make em foreground)
- Produção `.254`/`.234`/`.235` não tocada
