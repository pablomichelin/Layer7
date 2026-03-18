# Enforcement PF (V1 — repo)

## Objetivo

Ligar decisões **block** / **tag** a **tabelas PF** no pfSense, sem MITM.

## Tabelas

| Uso | Nome default | Config |
|-----|--------------|--------|
| Block | `layer7_block` | Fixo no código (`enforce.h`) até haver campo JSON |
| Tag | `layer7_tagged` ou **`tag_table`** na política | Por política `action=tag` |

Nomes de tabela: apenas `[A-Za-z0-9_]`, máx. 63 caracteres.

## Comando sugerido

Para **adicionar** o IP de origem a uma tabela (exemplo):

```sh
pfctl -t layer7_block -T add 10.0.0.42
pfctl -t layer7_http_users -T add 10.0.0.42
```

O **`layer7d -t`** imprime `pfctl_suggest=...` no dry-run quando `mode=enforce` e a decisão seria block/tag.

## API C (exec real)

| Função | Comando |
|--------|---------|
| `layer7_pf_exec_table_add(table, ip)` | `/sbin/pfctl -t TABLE -T add IP` |
| `layer7_pf_exec_table_delete(table, ip)` | `/sbin/pfctl -t TABLE -T delete IP` |
| `layer7_pf_enforce_decision(dec, ip, dry_run)` | Se `dec` exige block/tag e IP válido: add (ou só simula se `dry_run`) |

- Validação igual a `layer7_pf_snprint_add` (nome de tabela + IPv4).
- Implementação: **fork** + **execv**(`/sbin/pfctl`, …) + **waitpid** (sem shell).
- **Root** obrigatório no pfSense.

## CLI lab (`-e`)

```sh
layer7d -c /usr/local/etc/layer7.json -e 10.0.0.99 BitTorrent
layer7d -n -c ... -e 10.0.0.99 BitTorrent   # dry: não chama pfctl
```

Ordem típica: **`-c`**, **`-n`** (opcional), **`-e IP APP [categoria]`**. No runtime, **nDPI** deve chamar `layer7_on_classified_flow(src, app, cat)` (equivalente a decidir + `layer7_pf_enforce_decision(..., 0)`).

## Estado atual

- **`layer7d -t`**: `pfctl_suggest=…` onde aplicável.
- **`-e` / `-e -n`**: um fluxo sintético → decisão → add real ou dry.
- **SIGHUP**: snapshot; **SIGUSR1**: `pf_add_ok` / `pf_add_fail` quando o loop nDPI (ou testes) executarem adds.

## Próximo passo (lab)

1. Tabelas PF + regras que usem `layer7_block` / tag.  
2. **`layer7d -e …`** como root no appliance (sem **`-n`**).  
3. Ligar **nDPI** ao loop chamando `layer7_on_classified_flow`.
