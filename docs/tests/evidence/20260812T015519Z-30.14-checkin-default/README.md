# Evidência — `30.14` check-in default ON (bateria final)

| Campo | Valor |
|-------|--------|
| Implementação | `cb86766` |
| GO | ficha `docs/09-blocking/decisoes-humanas-30.1.md` addendum 30.14 |
| Runbook | `docs/13-runbooks/check-in-migration-30.14.md` |
| Builder UTC | `20260811T220030Z` |

## Artefactos

| Ficheiro | Conteúdo |
|----------|----------|
| `02-default-sample.txt` | grep sample `true` |
| `03-c-config-enabled.out` | true/false/ausente |
| `05-n3-static.out` | N3 estático PASS |
| `06-builder-final-battery.out` | PHP+C+smoke builder |
| `07-scope-review.txt` | diff vs escopo |

## Comandos

```sh
php tests/functional/test_check_in_default_30.14.php
cc ... tests/functional/test_checkin_config_enabled.c ... && /tmp/t_ci_cfg
sh tests/functional/test_check_in_n3_30.14.sh
sh scripts/package/smoke-layer7d.sh   # FreeBSD builder
```
