# Versão Visual — Portal Admin de Licenças

## Versão actual

| Campo | Valor |
|-------|-------|
| **Versão** | `1.3.1` |
| **Codinome** | Hotfix revisão defect-first |
| **Estado** | Entregue |
| **Data** | `2026-08-08` |

## Inclui

- Datas de calendário sem off-by-one BRT
- Download `.lic` só se `active` + bound
- Auditoria update: `expiry` normalizado; PUT bloqueado se revogada
- Busca com debounce + abort; select de clientes com busca servidor
- Voltar à ficha do cliente (`from_customer`); avisos SKU/archive
- Checklist: `TZ=UTC` + `--exclude .env`

## Próxima

Nova revisão pós-hotfix; residual só ideias com GO.

Histórico: [`CHANGELOG.md`](CHANGELOG.md).
