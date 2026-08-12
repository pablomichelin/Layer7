# Evidência — 30.17 / GA6.3–GA6.4

**UTC:** `20260812T024235Z`  
**Passo:** marcação por cliente (atribuição local; sem telemetria)

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| Objectivo | RR-2 — cópia de conteúdo atribuível à origem |
| Impacto | Sidecars em `update-blacklists.sh`; candidato `1.9.58`; sem LS/release/`.254` |
| Risco | Médio (privacidade) — mitigado (opaco, sem PII, sem phone-home) |
| Teste | `test_content_attribution_30.17.sh` + regressão CS update **PASS** |
| Rollback | Reverter commit / remover sidecars / `.pkg` anterior |

## Desenho

Ver [`../../01-architecture/marcacao-cliente-30.17.md`](../../01-architecture/marcacao-cliente-30.17.md).

## Saídas

- `unit-attribution.txt` — GA6.3/6.4 PASS
- `regression-cs-update.txt` — 30.10 gate intacto
- `ga6.4-no-telemetry.txt` — declaração factual
