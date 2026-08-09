# Evidência — 20.11 GI2/GI3 lab (`1.9.41`)

**Data:** `2026-08-09T060000Z`  
**Host lab:** `192.168.100.54` (descartável; Opção A autorizada)  
**Pacote referência:** `1.9.41`  
**SHA256:** `1518ad6825aad51bb97897335e441ac630be0ce6af74b80738ec06e77ca0c1f4`  
**Commit:** `bc076f0`  
**Produção `.254/.234/.235`:** **não tocada** (pfctl `-nf` reutilizado da auditoria 20.10b)  
**CA/segredos:** efémeros só em `.54`; **apagados** após prova; **ausentes** desta pasta

## Veredicto

| Gate | Estado |
|------|--------|
| **20.11** | **PASS** |
| **GI2** (2.0–2.5) | **PASS** |
| **GI3** (3.1–3.5) | **PASS** |
| S1–S8 (lab `.54` + docs) | **PASS** (S6 = limite ECH documentado) |
| Intercept produção | **não** |
| Claim `mitm_effective=true` | **não** (health/proxy: false) |

## Matriz GI2

| # | Critério | Prova | Resultado |
|---|----------|-------|-----------|
| GI2.0 | Spike GO/DEFER | histórico + reopen | PASS (prévio) |
| GI2.1 | default OFF | rc `enable:=NO`; sem env LAB/PRODUCT → refuse listen | **PASS** |
| GI2.2 | OFF ≡ sem intercept / claim honesto | health `intercept=false`, `mitm_effective_claim=false` | **PASS** |
| GI2.3 | sem gate/entitlement ⇒ zero intercept | rc gate absent; PHP `test_mitm_config.php` PASS (builder) | **PASS** |
| GI2.4 | CA fora do git | CA efémera `.54` + wipe; scan evidência sem private key | **PASS** |
| GI2.5 | bypass SNI | `bank.example` → verdict bypass | **PASS** |

## Matriz GI3

| # | Critério | Prova | Resultado |
|---|----------|-------|-----------|
| GI3.1 | página HTTPS com CA | HTML «Acesso bloqueado» + trust CA | **PASS** |
| GI3.2 | sem CA → falha TLS | curl verify fail rc=60 | **PASS** |
| GI3.3 | pinning/bypass | bypass funcional; apps com pinning → bypass ou limite (não NGFW) | **PASS** (limite honesto) |
| GI3.4 | IPv4 + rdr selectivo isolado | product loopback + Opção A REDIRECT ns | **PASS** |
| GI3.5 | CPU/latência anotados | ver S1/S2 abaixo | **PASS** |

## S1–S8 (esta corrida)

| # | Limiar | Medido | Resultado |
|---|--------|--------|-----------|
| S1 localhost | lab CPU razoável | busy ≈ **13.03%** (n=100) | **PASS** |
| S1 inline | ≤80% script / ≤+25% ADR | busy ≈ **12.72%** (n=50) | **PASS** |
| S2 localhost | p95 ≤ 150 ms | **3.14 ms** (n=100, errors=0) | **PASS** |
| S2 inline | p95 ≤ 150 ms | **15.48 ms** (n=50, errors=0) | **PASS** |
| S3 | block page HTTPS | GI3.1 | **PASS** |
| S4 | bypass | GI2.5 | **PASS** |
| S5 | QUIC | `bypass` default; sem terminate UDP | **PASS** |
| S6 | ECH | limite: política SNI; sem ECH exercitado; sem fail-closed LAN | **PASS** (limite) |
| S7 | sem payload | sem ficheiros payload; keys 600; wipe CA | **PASS** |
| S8 | OFF sem listener | processo ausente pós-rollback | **PASS** |

## Negativos

| Caso | Resultado |
|------|-----------|
| product bind `0.0.0.0` | refuse (rc=3) |
| lab listen sem env | refuse |
| health claim effective | sempre false |

## Artefactos

- `00-pkg-rc-defaults.txt` — defaults do `.pkg`
- `01-php-test-mitm.txt` — builder
- `02-health.json` … `13-pfctl-nf-readonly-254-reused.txt`
- `11-VERDICT.txt` — runner
- `12-s1-s2-metrics.txt` — métricas frescas n=100/50

## Rollback lab

```bash
# .54
/opt/layer7-poc/src-19141/lab-inline-down.sh
pkill -x layer7-tlsproxy || true
rm -rf /opt/layer7-poc/lab-certs-ephemeral
```

Pacote: permanece `1.9.41` (sem bump). Rollback release: `1.9.40` (NO-GO histórico) ou `1.9.39`.
