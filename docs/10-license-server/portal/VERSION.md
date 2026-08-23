# Versão Visual — Portal Admin de Licenças

## Versão actual

| Campo | Valor |
|-------|-------|
| **Versão** | `2.2.0` |
| **Codinome** | BG-162 — Instalações |
| **Estado** | Entregue no git (**sem** deploy live neste passo; P0-1) |
| **Data** | `2026-08-22` |

## Inclui

- Menu **Instalações** (lista + detalhe)
- Cartões no Dashboard: vistas / sem serial / stale 7d
- Bloco «esta caixa no inventário» na ficha da licença
- API `GET /api/installations` + `POST /api/license/install-ping`

## Próxima

Deploy live sob GO (P0-1). Canal `latest` do pacote **não** muda neste bump.

Histórico: [`CHANGELOG.md`](CHANGELOG.md).
