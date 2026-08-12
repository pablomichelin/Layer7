# Versão Visual — Portal Admin de Licenças

## Versão actual

| Campo | Valor |
|-------|-------|
| **Versão** | `2.1.0` |
| **Codinome** | 30.15 — alerta multi-appliance |
| **Estado** | Entregue no git (**sem** deploy live neste passo) |
| **Data** | `2026-08-12` |

## Inclui

- Fila «Abuso multi-appliance (30d)» no Dashboard
- Contagem `licenses.multi_appliance_abuse` na API `/dashboard`
- Política fase 1: **só alerta** (decisão 7); sem `max_activations`
- Rebind autorizado não gera falso positivo

## Próxima

Deploy live sob GO operacional; ideias de escala só com GO.

Histórico: [`CHANGELOG.md`](CHANGELOG.md).
