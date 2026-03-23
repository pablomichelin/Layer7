# Enforcement PF (V1 — repo)

## Objetivo

Ligar decisões **block** / **tag** a **tabelas PF** no pfSense, sem MITM.

## Estado atual

O enforcement atual do produto já faz:

- decisão `block` / `tag` no `layer7d`;
- `pfctl -T add` do **IP de origem** em PF table;
- logs e counters de enforcement.
- helper do pacote para materializar assets PF (`/usr/local/libexec/layer7-pfctl`);
- snippet de ruleset gerado em `/usr/local/etc/layer7/pf.conf`.
- hook `layer7_generate_rules("filter")` em `/usr/local/pkg/layer7.inc`;
- reload do filtro oficial no install/deinstall do pacote.

O enforcement total do produto ainda está em evolução para entregar, de forma
automática e fechada:

- regras/anchors PF geridos pelo próprio pacote;
- injecção validada dessas regras no filtro ativo do pfSense;
- bloqueio real por domínio/destino;
- perfis compostos de serviço/função.

Plano mestre desta trilha:
[`../09-blocking/blocking-master-plan.md`](../09-blocking/blocking-master-plan.md)

## Tabelas

| Uso | Nome default | Config |
|-----|--------------|--------|
| Block | `layer7_block` | Fixo no código (`enforce.h`) até haver campo JSON |
| Tag | `layer7_tagged` ou **`tag_table`** na política | Por política `action=tag` |

## Assets do pacote

O pacote passa a concentrar o bootstrap PF em:

```text
/usr/local/libexec/layer7-pfctl
/usr/local/etc/layer7/pf.conf
/usr/local/etc/layer7/pf.conf.sample
```

Responsabilidades do helper:

- garantir que `layer7_block` e `layer7_tagged` existem;
- gerar o snippet PF gerido pelo pacote;
- permitir flush controlado das tables no rollback/deinstall.

Neste bloco, o pacote passa a expor a regra minima via
`layer7_generate_rules("filter")`, no padrao que o pfSense usa em
`discover_pkg_rules()` para montar regras de pacotes durante o `filter reload`.

A regra publicada neste passo e:

```text
block drop quick inet from <layer7_block> to any label "layer7:block:src"
block drop quick inet6 from <layer7_block> to any label "layer7:block:src6"
```

O helper continua responsavel por gerar o snippet materializado e garantir as
tabelas, enquanto o hook do pacote devolve esse mesmo texto ao ciclo oficial do
filtro.

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

## Validacao minima desta fase

No appliance pfSense CE, validar:

1. install/upgrade do pacote dispara `filter_configure()`;
2. `rules.debug` contem `layer7:block:src`;
3. `pfctl -sr` contem a regra Layer7;
4. IP em `<layer7_block>` passa a ser bloqueado sem regra manual externa.

## Risco aberto

A maior incerteza restante nao e mais o nome do hook, mas sim a ordem/precedencia
real da regra no ruleset final do appliance. Por isso a confirmacao em
`rules.debug` e `pfctl -sr` continua obrigatoria antes de fechar a fase.
