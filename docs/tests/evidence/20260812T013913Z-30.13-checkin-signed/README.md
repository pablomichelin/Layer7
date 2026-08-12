# Evidência — `30.13` check-in assinado (implementação)

| Campo | Valor |
|-------|--------|
| Passo | `30.13` / BG-119 |
| Contrato | [`../../../01-architecture/contrato-check-in-assinado-30.12.md`](../../../01-architecture/contrato-check-in-assinado-30.12.md) |
| UTC | `20260812T013913Z` |
| Candidato pkg | `1.9.55` (**não** publicado neste passo) |
| Produção `.254` | `1.9.54` intocada |

## Artefactos

- `00-meta.txt` / `00-verdict.txt`
- `node-tests.out` — `check-in-signed` + policy + content-subscription
- `c-validate.out` — `tests/functional/test_checkin_signed.c`

## Notas

- Deploy do license-server e GitHub Release **fora** deste bloco.
- Ordem ops futura: deploy servidor (dual-mode) **antes** do `.pkg` `1.9.55`.
