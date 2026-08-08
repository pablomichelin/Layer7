# Versão Visual — Portal Admin de Licenças

## Versão actual

| Campo | Valor |
|-------|-------|
| **Versão** | `0.5.0` |
| **Codinome** | Pós-revogação P1d |
| **Estado** | Entregue |
| **Data** | `2026-08-08` |

## Inclui

- `POST /api/licenses/:id/replace` — substituir licença revogada (nova chave + arquivar)
- UI **Substituir licença** no detalhe (motivo obrigatório; expiry opcional)
- Auditoria `license_replaced`
- Política: **não desrevogar** (preferência conservadora do plano)

## Próxima (ordem fixa)

| Versão | Bloco |
|--------|-------|
| `1.0.0` | **P1e** — fecho critérios operador único |

Histórico: [`CHANGELOG.md`](CHANGELOG.md).
