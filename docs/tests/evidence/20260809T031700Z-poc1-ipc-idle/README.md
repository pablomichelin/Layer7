# Evidência — PoC-1 IPC (`layer7-tlsproxy`)

**Data:** `2026-08-09`  
**Host:** Mac de desenvolvimento (não appliance produção)  
**Versão binário:** `0.0.1-poc1`  
**Resultado:** **PASS**

## O que foi validado

| Check | Resultado |
|-------|-----------|
| `--health` → `intercept:false`, `mitm_effective_claim:false` | PASS |
| IPC sem `LAYER7_TLSPROXY_LAB=1` | recusado (exit 3) |
| Socket `/var/run/layer7/mitm.sock` | recusado (exit 3) |
| `PING` lab em socket relativo + `/tmp/...` | `ok:true`, `mitm_effective:false` |
| Bind TCP / intercept | **não** implementado |

## Comando

```bash
cd src/layer7-tlsproxy && make test
LAYER7_TLSPROXY_LAB=1 ./layer7-tlsproxy --ipc-serve --sock /tmp/layer7-tlsproxy-poc.sock --oneshot &
LAYER7_TLSPROXY_LAB=1 ./layer7-tlsproxy --ipc-ping --sock /tmp/layer7-tlsproxy-poc.sock
```

## Limites honestos

- **Não** altera `layer7d` / `mitm_runtime_available` (continua `false` no produto).  
- **Não** correu em `.254` / `.234` / `.235`.  
- **Não** é passo 20.10.
