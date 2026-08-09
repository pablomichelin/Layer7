# Evidência — PoC-2 TLS lab (`192.168.100.54`)

**Data:** `2026-08-09`  
**Host:** `root@192.168.100.54` (Ubuntu 24.04 — **lab descartável**)  
**Binário:** `layer7-tlsproxy 0.0.2-poc2` em `/opt/layer7-poc/src/`  
**Resultado:** **PoC-2 smoke PASS** (TLS localhost); S2 lab PASS; S1 produto ainda parcial

## Isolamento

| Host | Uso neste bloco |
|------|-----------------|
| `.54` | **Autorizado** — TLS listen + medições |
| `.254` / `.234` / `.235` | **Não tocados** |

## Gates de segurança observados

| Check | Resultado |
|-------|-----------|
| Sem `LAYER7_TLSPROXY_LAB=1` | TLS/IPC recusados |
| Bind default | `127.0.0.1:8443` |
| `0.0.0.0` sem `--lab-allow-any` | recusado |
| Resposta HTTP JSON | `mitm_effective:false` |
| Chaves CA/lab | só em `/opt/layer7-poc/lab-certs/` na VM (**não** no git) |

## S2 (handshake, localhost)

| Métrica | Valor |
|---------|-------|
| n | 50 |
| p50 | 2.59 ms |
| p95 | **2.80 ms** (limiar ≤ 150 ms) |
| max | 5.59 ms |

**Veredicto S2 lab:** **PASS** (acceptor local; não é ainda path inline no gateway).

## S1 (CPU)

| Métrica | Valor |
|---------|-------|
| CPU busy durante 200 handshakes | ~13.25% |
| Nota | Medição do **acceptor lab**, **não** overhead MITM inline no caminho LAN→WAN |

**Veredicto S1 produto:** **PENDING** até PoC com tráfego interceptado no caminho (ainda bloqueado em produção).

## Comandos (repetir no `.54`)

```bash
cd /opt/layer7-poc/src
export LAYER7_TLSPROXY_LAB=1
./layer7-tlsproxy --lab-tls-listen 127.0.0.1:8443 \
  --cert /opt/layer7-poc/lab-certs/server.crt \
  --key /opt/layer7-poc/lab-certs/server.key
# outro terminal:
curl -k https://127.0.0.1:8443/
```
