# Matriz de política (V1)

## Ações

| Ação | Comportamento |
|------|----------------|
| `allow` | Ignora blocks geridos pelo Layer7, sem autorizar nem contornar regras nativas do pfSense |
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
| `match.src_exclude_cidrs` | CIDR[] | origem isenta **desta** política (BG-066) |
| `match.src_exclude_groups` | id[] | grupos isentos desta política (expandidos) |
| `match.hosts` | domínio[] | exacto ou subdomínio; DNS/SNI/HTTP Host |
| `match.ndpi_category` | string[] | match exacto; quando presente funciona como condição obrigatória |
| `match.ndpi_app` | string[] | match exacto |

`ndpi_master`, `dst_net` e `dst_port` **não estão implementados** no motor
actual e não devem ser apresentados como critérios disponíveis.

### AND/OR e enforcement scoped

- interface/origem/schedule são condições obrigatórias;
- quando `ndpi_app` e `hosts` coexistem, a relação actual é **OR**;
- match por app/categoria ou host usa `layer7_pdst_N` por defeito;
- allow explícito aprende o destino em `layer7_pallow_N` e aplica
  `match ... tag L7ALLOW` no mesmo escopo de origem/interface;
- exclusão por política (`src_exclude_*`): origem não casa no daemon;
  em `scoped_hybrid`, `layer7_pexc_N` + `match from pexc to pdst tag L7ALLOW`
  (ADR-0019);
- `layer7_psrc_N` só é usado com `quarantine_origin=true` (corte total
  deliberado da origem);
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
Em modo enforce     tag      drop     log        table
```

`tag` na coluna `allow` significa marca interna consumida exclusivamente
pelos blocks Layer7 (`! tagged L7ALLOW`). A decisão final `pass`/`block`
continua pertencendo ao ruleset completo do pfSense.
