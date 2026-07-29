# Matriz de política (V1)

## Ações

| Ação | Comportamento |
|------|----------------|
| `allow` | Fluxo permitido explicitamente (útil após regras genéricas `block`) |
| `block` | Negar; enforcement via PF (table + regra de bloqueio) |
| `monitor` | Apenas log/evento; sem alteração PF |
| `tag` | Incluir endpoint em PF table (alias) para uso em regras manuais ou encadeadas |

## Objeto de política actual

| Campo | Tipo | Semântica implementada |
|-------|------|------------------------|
| `interfaces` | string[] (top-level) | nomes reais de captura/PF; `lan`/`optN` são migrados |
| `match.src_hosts` | IPv4[] | origem exacta |
| `match.src_cidrs` | CIDR[] | origem na rede |
| `match.groups` | id[] | expande hosts/CIDRs/dispositivos do grupo |
| `match.hosts` | domínio[] | exacto ou subdomínio; DNS/SNI/HTTP Host |
| `match.ndpi_category` | string[] | match exacto; quando presente funciona como condição obrigatória |
| `match.ndpi_app` | string[] | match exacto |

`ndpi_master`, `dst_net` e `dst_port` **não estão implementados** no motor
actual e não devem ser apresentados como critérios disponíveis.

### AND/OR e enforcement scoped

- interface/origem/schedule são condições obrigatórias;
- quando `ndpi_app` e `hosts` coexistem, a relação actual é **OR**;
- match por app/categoria usa `layer7_psrc_N`;
- match por host usa `layer7_pdst_N`;
- block em `scoped_hybrid` exige origem efectiva, `scope_global` explícito ou
  `quarantine_origin`; a GUI do candidato `_25` recusa a ausência de escopo;
- `match_mode` configurável continua backlog E4 e `_25` não fecha E4.

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
