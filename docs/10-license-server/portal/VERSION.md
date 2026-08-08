# Versão Visual — Portal Admin de Licenças

## Versão actual

| Campo | Valor |
|-------|-------|
| **Versão** | `0.2.0` |
| **Codinome** | Renovação P1a |
| **Estado** | Entregue |
| **Data** | `2026-08-08` |
| **Código** | `license-server/` |
| **Deploy live** | `192.168.100.244:/opt/layer7-license` |

## Inclui

- `POST /api/licenses/:id/renew` (`days` 30/90/365)
- Botões de renovação no detalhe + oferta de download `.lic` se bound
- Cliente: campos opcionais `cnpj` e `tags`

## Próxima

| Versão | Conteúdo |
|--------|----------|
| `0.3.0` | P1b — auditoria UI + check-ins |

Histórico: [`CHANGELOG.md`](CHANGELOG.md).
