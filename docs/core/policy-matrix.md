# Matriz de política (V1)

## Ações

| Ação | Comportamento |
|------|----------------|
| `allow` | Fluxo permitido explicitamente (útil após regras genéricas `block`) |
| `block` | Negar; enforcement via PF (table + regra de bloqueio) |
| `monitor` | Apenas log/evento; sem alteração PF |
| `tag` | Incluir endpoint em PF table (alias) para uso em regras manuais ou encadeadas |

## Objeto `match`

Todos os campos são **opcionais**; ausência = “qualquer”. Pelo menos um critério deve existir (validação GUI).

| Campo | Tipo | Exemplo |
|-------|------|---------|
| `interfaces` | string[] | `["lan"]` |
| `src_net` | CIDR[] | `["10.0.0.0/24"]` |
| `dst_net` | CIDR[] | |
| `ndpi_category` | string[] | nDPI category name |
| `ndpi_app` | string[] | app protocol name (substring ou exato — ver implementação) |
| `ndpi_master` | string[] | master protocol |
| `dst_port` | int / range | opcional |

## Modo global `monitor` vs `enforce`

- **`monitor`:** todas as políticas geram eventos; ações `block`/`tag` **não** aplicam enforcement (ou aplicam só log — decisão implementação: recomenda-se **não** aplicar block em monitor).
- **`enforce`:** `block` e `tag` ativos; `monitor` e `allow` conforme matriz.

*(Documentar na GUI claramente.)*

## Matriz resumo

```text
                    allow    block    monitor    tag
Fluxo matching      sim      sim      sim        sim
Em modo monitor     log      log      log        log (sem table)
Em modo enforce     pass     drop     log        table
```
