# Versão Visual — Portal Admin de Licenças

## Versão actual

| Campo | Valor |
|-------|-------|
| **Versão** | `0.4.0` |
| **Codinome** | Rebind P1c |
| **Estado** | Entregue |
| **Data** | `2026-08-08` |

## Inclui

- `POST /api/licenses/:id/rebind` (`unbind` | `set` + motivo obrigatório)
- UI **Rebind hardware** no detalhe com aviso de grace/.lic antigo
- Auditoria `license_rebound`
- MANUAL-USO §5.6 actualizado

## Próxima (ordem fixa)

| Versão | Bloco |
|--------|-------|
| `0.5.0` | **P1d** — pós-revogação / substituir licença |

Histórico: [`CHANGELOG.md`](CHANGELOG.md).
