# Evidência — 20.11 GI2/GI3 lab (`1.9.41`) — revisão gerencial

**Data corrida:** `2026-08-09T060000Z`  
**Revisão gerencial:** `2026-08-09` (pós-`8939ddb`) — **corrige overclaim S3/S6**  
**Host lab:** `192.168.100.54` (descartável)  
**Pacote:** `1.9.41` / SHA `1518ad6825aad51bb97897335e441ac630be0ce6af74b80738ec06e77ca0c1f4`  
**Commit código:** `bc076f0`  
**Produção `.254/.234/.235`:** **não tocada**  
**CA/segredos:** efémeros só em `.54`; wipe; **ausentes** desta pasta

## Veredicto (canónico)

| Gate | Estado |
|------|--------|
| **20.11** | **PARCIAL / NO-GO de fecho** |
| **GI2** (2.0–2.5) | **PASS** |
| **GI3** (3.1–3.5) | **PENDENTE** — bloqueado em **S3 / GI3.1** (browser Windows) |
| S3 (ADR-0026) | **PENDENTE** — prova desta corrida = `curl`+CA; **curl ≠ browser** |
| S6 (ECH) | **NA / limite** — não exercitado; **não** é PASS experimental |
| Intercept produção | **não** |
| GO produção MITM | **proibido** até fecho 20.11 / GI3 |

## O que esta corrida prova (válido)

| Item | Prova | Estado |
|------|-------|--------|
| GI2.1–2.5 | rc OFF, refuse sem env, health honesto, gate, CA fora git, bypass SNI | **PASS** |
| GI3.2 | TLS fail sem CA (`curl` rc=60) | **PASS** (negativo TLS) |
| GI3.4 | IPv4 loopback + Opção A REDIRECT ns | **PASS** |
| GI3.5 / S1–S2 | CPU/latência lab anotados (p95≪150 ms) | **PASS** |
| S4 / GI2.5 | bypass SNI | **PASS** |
| S5 | `quic_mode=bypass` documentado | **PASS** documental |
| S7 | sem payload; wipe CA | **PASS** |
| S8 | OFF sem listener pós-rollback | **PASS** |
| HTML block via `curl --cacert` | resposta 403 HTML | **prova parcial só** — **não** fecha S3/GI3.1 |

## O que **não** está provado

| Critério canónico | Evidência real | Classificação |
|-------------------|----------------|---------------|
| **S3 / GI3.1** — ≥1 **browser Windows corporativo** com CA instalada vê HTML legível (ADR-0026 / desenho) | apenas `curl` + trust CA em Linux `.54` | **PENDENTE** |
| **S6** — ECH: prova em lab de comportamento previsível | não exercitado; só nota de limite | **NA / limite** (não PASS) |

## Gate humano/lab restante (exacto)

1. Em lab isolado (**.54** ou VM Windows descartável — **não** `.254`/`.234`/`.235`):  
   - instalar CA lab no store do utilizador/máquina Windows;  
   - abrir **browser Windows** (Edge/Chrome/Firefox) a destino block SNI via path MITM lab;  
   - capturar evidência: screenshot + nota de browser/versão + HTML legível (sem private keys).  
2. Registar S3/GI3.1 **PASS** com essa prova.  
3. Só então reavaliar fecho **20.11 / GI3**.  
4. **S6** permanece **NA/limite** até existir prova ECH em lab (ou ADR a aceitar NA permanente).  
5. **Não** avançar para GO produção MITM neste estado.

## Histórico de classificação

| Momento | Classificação | Nota |
|---------|---------------|------|
| Commit `8939ddb` | 20.11/GI3 PASS (overclaim) | tratou `curl` como S3 e limite ECH como PASS |
| Esta revisão | **20.11 PARCIAL**; GI2 PASS; GI3 PENDENTE S3; S6 NA | alinhado ADR-0026 |

## Rollback lab

```bash
/opt/layer7-poc/src-19141/lab-inline-down.sh
pkill -x layer7-tlsproxy || true
rm -rf /opt/layer7-poc/lab-certs-ephemeral
```
