# Evidência — 20.11 GI2/GI3 lab (`1.9.41`)

**Corrida lab:** `2026-08-09T060000Z` (`.54`)  
**Rev. gerencial S3/S6:** `2026-08-09` (`2efca2c`) — curl ≠ browser; S6 NA  
**Gate S3 Windows:** `2026-08-09` — VM `192.168.100.24` Edge real → **PASS**  
**Pacote:** `1.9.41` / SHA `1518ad6825aad51bb97897335e441ac630be0ce6af74b80738ec06e77ca0c1f4`  
**Commit código:** `bc076f0`  
**Produção `.254/.234/.235`:** **não tocada**  
**CA/segredos:** efémeros; wipe `.54`+`.24`; **ausentes** desta pasta (só fingerprint público)

## Veredicto

| Gate | Estado |
|------|--------|
| **20.11** | **PASS** |
| **GI2** (2.0–2.5) | **PASS** |
| **GI3** (3.1–3.5) | **PASS** |
| **S3** (ADR-0026) | **PASS** — Edge Windows 151 + CA trust + screenshot HTML |
| **S6** (ECH) | **NA / limite** (não exercitado; não PASS) |
| Intercept produção | **não** |
| GO produção MITM | **não** neste fecho |

## S3 / GI3.1 (prova canónica)

| Campo | Valor |
|-------|--------|
| VM | `192.168.100.24` Windows Server 2022 descartável |
| Browser | Microsoft Edge **151.0.4129.72** (binário Windows; headless=new) |
| URL/SNI | `https://blocked.test:8443/` |
| Path | Edge → listener lab temporário `.54:8443` (`layer7-tlsproxy`) |
| Trust | CA pública no Root store; HTTP **403** sem ignore-cert |
| Peer | `CN=blocked.test` / issuer `CN=Layer7-S3-Ephemeral-CA` |
| Screenshot | [`s3-windows/02-edge-block-page.png`](s3-windows/02-edge-block-page.png) |
| Log | [`s3-windows/03-run.log`](s3-windows/03-run.log) |
| curl como S3 | **rejeitado** (só prova parcial prévia) |

## Matriz GI2 / GI3 (resto)

| # | Estado | Nota |
|---|--------|------|
| GI2.1–2.5 | **PASS** | rc OFF, refuse, health, gate, CA fora git, bypass |
| GI3.1 | **PASS** | S3 Windows |
| GI3.2–3.5 | **PASS** | TLS fail sem CA; bypass/limite; Opção A; S1/S2 |
| S1/S2/S4/S5/S7/S8 | **PASS** | lab `.54` |
| S6 | **NA/limite** | ECH não exercitado |

## Limpeza

- `.24`: CA removida do Root; hosts; perfil Edge; temp scripts/artefactos  
- `.54`: proxy parado; CA efémera apagada; porto 8443 fechado  
- Helpers locais de autenticação: removidos; credencial **não** persistida

## Rollback

Config/lab: listener OFF + wipe CA. Pacote: `1.9.41` (sem bump). Release inalterada.
