# Evidência — ciclo B+D MITM (NO-GO)

| Campo | Valor |
|-------|--------|
| **Run ID** | `20260809T185035Z` |
| **Veredicto** | **NO-GO** |
| **Pacote** | `1.9.42` |
| **Escopo** | B (rota `198.18.0.10/32` via `.54`) + D (MITM source `.24/32` ∧ dest `198.18.0.10/32`, SNI `mitm-lab.test`) |
| **License server** | **não tocado** (só leitura de entitlement `mitm` no appliance) |

## Resultado

| Gate | Estado |
|------|--------|
| Baseline pós-fail-safe | **PASS** (sem rota 198.18, MITM OFF) |
| Entitlement `mitm` (RO) | **PASS** (`base,identity,mitm`) |
| Snapshot + fail-safe ≤10 min | **PASS** (validado antes da mutação) |
| Rota B + MITM effective | **PASS** temporário |
| PF rdr `from <src> to <dst> → 127.0.0.1:8443` | **PASS** (vmx0 + vmx0.95; zero `from any` MITM; zero IPv6) |
| Edge bloqueio HTTPS Layer7 | **FAIL** — DOM = página de erro Chromium (não marcador «acesso bloqueado») |
| Rollback completo | **PASS** (MITM OFF, rota/rdr/`8443` ausentes; `.24` hosts+CAs; `.54` endpoint) |

## Causa do NO-GO

O Edge headless abriu `https://mitm-lab.test/` **sem** o body da block page Layer7. O DOM capturado corresponde a página de erro do Chromium (provável falha de confiança/cadeia TLS no caminho MITM), não ao HTML `Layer7 — acesso bloqueado`. Abort sem bypass de alerta, conforme runbook.

## Ficheiros

- `07-pf-audit.txt` — rdr/tabelas durante a janela
- `08-edge-*` / `remote/` — consola, screenshot, snip DOM
- `11-VERDICT.txt` — veredicto compacto
- `06-mitm-ca.crt` — CA pública lab (sem chave privada no git)

## Notas

- `.234` / `.235` intocados.
- Fail-safe BD cancelado após rollback.
- Próximo: diagnosticar trust da CA MITM no cliente vs page MITM antes de novo GO.
