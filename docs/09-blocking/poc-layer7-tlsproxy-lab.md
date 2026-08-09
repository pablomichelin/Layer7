# PoC lab — `layer7-tlsproxy` (pós GO lab)

**Estado:** `ACTIVO` — **PoC-3 PASS** (S3/S4) em `192.168.100.54`  
**NÃO é** passo **20.10**.  

| Campo | Valor |
|-------|--------|
| GO lab | **SIM** (`2026-08-09`) |
| Fase actual | **PoC-3 PASS** — próximo: splice/upstream ou S1 inline **só** em `.54` |
| Lab descartável | **`192.168.100.54`** (`root`) |
| Produção `.254`/`.234`/`.235` | **PROIBIDO** intercept |
| Binário | `0.0.3-poc3` · `/opt/layer7-poc/` no `.54` |
| `mitm_effective` | **false** |

## Fases

| Fase | Gate |
|------|------|
| PoC-0 idle | **PASS** |
| PoC-1 IPC | **PASS** |
| PoC-2 TLS | **PASS** (S2 lab) |
| PoC-3 SNI bypass/block + página HTTPS | **PASS** — `20260809T041800Z-poc3-sni-s3s4-54` |
| → 20.10 | S1 inline + GO produto |

## Hardening

```text
LAYER7_TLSPROXY_LAB=1
TLS default 127.0.0.1
--block-sni / --bypass-sni (lab)
mitm_effective=false sempre
chaves só em /opt/layer7-poc/lab-certs (não git)
```

## Histórico

| Data | Nota |
|------|------|
| 2026-08-09 | PoC-0…2 |
| 2026-08-09 | **PoC-3 PASS** S3/S4 em `.54` |
