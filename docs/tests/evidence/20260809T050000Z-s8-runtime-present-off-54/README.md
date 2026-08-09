# Evidência — S8 runtime presente OFF (lab `.54`)

**Data:** `2026-08-09T04:52:33Z`  
**Host:** `root@192.168.100.54`  
**Binário:** `0.0.5-poc5` em `/opt/layer7-poc/src/layer7-tlsproxy`  
**Draft packaging:** [`docs/09-blocking/drafts/mitm-packaging-20.10/`](../../../09-blocking/drafts/mitm-packaging-20.10/)

## Objectivo

Provar que **ter o runtime no disco** com serviço/path inline **OFF** não
activa intercept (prep-20.10 item 7). **Não** é GO produto nem 20.10.

## Resultado

| Critério | Valor |
|----------|--------|
| Runtime presente | **sim** |
| Processo a correr | **não** |
| Listeners 443/8443 | **nenhum** |
| netns / REDIRECT | **ausentes** (`lab-inline DOWN`) |
| Health `mitm_effective_claim` | **false** |
| Health `intercept` | **false** |
| `http://example.com` | **200** (caminho directo) |
| Veredicto | **PASS** |

## Honestidade

- Medição **só** no lab `.54` (Linux PoC), não pfSense `.254`.
- O `rc.d` FreeBSD do draft **não** foi instalado no `.54` (OS diferente);
  valida-se o contrato “binário presente + OFF ≡ sem intercept”.
- Produto `1.9.38`: `mitm_runtime_available` continua **false** (sem merge packaging).

## Artefactos

- `01-smoke-off.txt` — saída completa do smoke
