# Enforcement PF (V1 — repo)

## Objetivo

Ligar decisões **block** / **tag** a **tabelas PF** no pfSense, sem MITM.

## Estado atual

O enforcement atual do produto já faz:

- decisão `block` / `tag` no `layer7d`;
- `pfctl -T add` do **IP de origem** em PF table;
- logs e counters de enforcement;
- helper do pacote para materializar assets PF (`/usr/local/libexec/layer7-pfctl`);
- snippet de ruleset gerado em `/usr/local/etc/layer7/pf.conf`;
- hook `layer7_generate_rules("filter")` em `/usr/local/pkg/layer7.inc`;
- **tag `<filter_rules_needed>` no XML do pacote** para registar a função
  geradora de regras no ciclo oficial do filtro do pfSense;
- reload do filtro oficial no install/deinstall do pacote.

O enforcement total do produto ainda está em evolução para entregar, de forma
automática e fechada:

- bloqueio real por domínio/destino;
- perfis compostos de serviço/função.

Plano mestre desta trilha:
[`../09-blocking/blocking-master-plan.md`](../09-blocking/blocking-master-plan.md)

## Tabelas

| Uso | Nome default | Config |
|-----|--------------|--------|
| Block (origem/quarentena) | `layer7_block` | Fixo no código (`enforce.h`) |
| Block (destino/sites/apps) | `layer7_block_dst` | Fixo no código (`enforce.h`) |
| Tag | `layer7_tagged` ou **`tag_table`** na política | Por política `action=tag` |

## Assets do pacote

O pacote passa a concentrar o bootstrap PF em:

```text
/usr/local/libexec/layer7-pfctl
/usr/local/etc/layer7/pf.conf
/usr/local/etc/layer7/pf.conf.sample
```

Responsabilidades do helper:

- garantir que `layer7_block`, `layer7_block_dst` e `layer7_tagged` existem;
- gerar o snippet PF gerido pelo pacote;
- permitir flush controlado das tables no rollback/deinstall.

### Robustez operacional (v1.4.14)

Para evitar estado inconsistente apos reloads externos do filtro, o runtime do
daemon aplica auto-recuperacao quando um `pfctl -T add` falha por tabela
ausente:

1. tenta `layer7-pfctl ensure`;
2. valida as tabelas base;
3. se necessario, aplica fallback com `pfctl -f /tmp/rules.debug`;
4. repete o `add` uma unica vez.

No ciclo de `SIGHUP`, o daemon tambem valida tabelas base apos reload e tenta
recuperacao quando detectar ausencia.

### Diagnostico sem falso negativo (v1.4.16)

Durante validacoes em appliance real, foi confirmado um comportamento de PF em
que uma tabela pode estar **referenciada no filtro ativo** (`pfctl -sr`) antes
de aparecer materializada em `pfctl -s Tables` no mesmo ciclo operacional.

Isso gerava falso negativo operacional em troubleshootings anteriores
(`"Tabela nao existe"`) mesmo com enforcement funcional por destino.

Correcao aplicada na v1.4.16:

1. `layer7-pfctl` passou a considerar tabela "pronta" quando:
   - existe em `pfctl -s Tables`; **ou**
   - ja esta referenciada em regra ativa (`<table:...>` em `pfctl -sr`).
2. Diagnostics passou a usar o mesmo criterio combinado.
3. A GUI diferencia:
   - tabela realmente ausente; de
   - tabela referenciada no filtro ativo (sem entradas no momento).

Criterio objetivo de estado saudavel (apos v1.4.16):

- `layer7d` em execucao com `enforce_mode=1`;
- regras `layer7:block:*` presentes em `pfctl -sr`;
- contadores de bloqueio/enforcement evoluindo em log/stats;
- tabelas avaliadas pelo criterio combinado acima.

Com isso, o foco do diagnostico volta para falha real de enforcement, e nao
para estado cosmetico/transitorio de materializacao de tabela.

O pacote expoe a regra minima via `layer7_generate_rules("filter")`, no padrao
que o pfSense usa em `discover_pkg_rules()` para montar regras de pacotes
durante o `filter reload`.

### Como funciona o ciclo do pfSense

1. `filter_configure()` chama `discover_pkg_rules("filter")`.
2. `discover_pkg_rules` itera os pacotes em `config.xml`
   (`installedpackages/package`).
3. Para cada pacote com `<filter_rules_needed>`, inclui o `include_file` e
   chama a funcao indicada (ex.: `layer7_generate_rules`).
4. Valida a saida com `pfctl -nf` antes de incorporar.
5. Se valida, as regras entram em `rules.debug` e sao carregadas no PF.

O tag critico no XML do pacote e:

```xml
<filter_rules_needed>layer7_generate_rules</filter_rules_needed>
```

Sem ele, `discover_pkg_rules` ignora o pacote durante o reload do filtro.

As regras publicadas sao:

```text
block drop quick inet from <layer7_block> to any label "layer7:block:src"
block drop quick inet6 from <layer7_block> to any label "layer7:block:src6"
block drop quick inet to <layer7_block_dst> label "layer7:block:dst"
block drop quick inet6 to <layer7_block_dst> label "layer7:block:dst6"
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

Constantes de tabela em `enforce.h`:

| Constante | Valor | Uso |
|-----------|-------|-----|
| `L7_PF_TABLE_BLOCK` | `layer7_block` | Quarentena por origem (tag/legacy) |
| `L7_PF_TABLE_BLOCK_DST` | `layer7_block_dst` | Bloqueio por destino (sites/apps) |
| `L7_PF_TABLE_TAG_DEFAULT` | `layer7_tagged` | Tag por origem |

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

## Estado real validado em appliance (2026-03-23)

Ja foi comprovado no pfSense CE:

- politica `block` em `mode=enforce` casa com trafego `Github`;
- `layer7d` regista `action=block reason=policy_match`;
- `layer7d` adiciona o IP de origem a `<layer7_block>`;
- `pfctl -t layer7_block -T show` mostra o IP bloqueado.

### Causa raiz do gap anterior

A regra `layer7:block:src` nao aparecia em `pfctl -sr` porque o XML do pacote
(`layer7.xml`) nao tinha o tag `<filter_rules_needed>`. Sem esse tag, a funcao
`discover_pkg_rules()` do pfSense ignora o pacote durante o ciclo de montagem
do filtro. A funcao `layer7_generate_rules()` existia e estava correta, mas
nunca era chamada.

O fix foi adicionar ao XML:

```xml
<filter_rules_needed>layer7_generate_rules</filter_rules_needed>
```

### Pendente de validacao no appliance

Apos instalar o pacote com o XML corrigido:

1. `grep layer7 /tmp/rules.debug` — confirmar presenca das regras;
2. `pfctl -sr | grep layer7` — confirmar regra no ruleset ativo;
3. `pfctl -t layer7_block -T add 10.0.0.1 && curl http://10.0.0.1` — confirmar
   bloqueio real;
4. recarregar filtro (`filter reload`) e confirmar persistencia;
5. reboot e confirmar persistencia.

## Modelo de bloqueio por destino (v0.3.0)

A partir da v0.3.0, o daemon suporta bloqueio por **destino** em vez de
quarentena do cliente. O modelo funciona por dois caminhos:

### Caminho DNS

1. O daemon observa respostas DNS (RR tipo A) em `capture.c`.
2. Para cada IP resolvido, chama o callback `layer7_on_dns_resolved`.
3. O callback verifica se o dominio casa com alguma politica `block` activa
   (`layer7_domain_is_blocked` em `policy.c`).
4. Se casa, adiciona o IP resolvido a `layer7_block_dst` via `pfctl -T add`.
5. A regra PF `block drop quick inet to <layer7_block_dst>` bloqueia trafego
   para esse IP.

### Caminho nDPI

1. nDPI classifica o fluxo (app/categoria).
2. Se a politica decide `block`, o IP de **destino** do fluxo entra em
   `layer7_block_dst`.
3. O IP de origem ja nao e bloqueado (quarentenado) para `action=block`.
4. `action=tag` continua a usar o IP de origem em `layer7_tagged`.

### Expiracao de entradas

IPs na tabela de destino sao registados com TTL (minimo 300s). A cada ~60s,
entradas expiradas sao removidas automaticamente via `pfctl -T delete`.
Em SIGHUP (reload), toda a cache e a tabela sao limpas.

### Limitacoes

- **Primeiros pacotes**: nDPI precisa de alguns pacotes para classificar.
- **IPs partilhados**: CDNs podem partilhar IPs entre sites.
- **DNS cache do cliente**: bloqueio DNS so funciona apos TTL expirar.

## Estrategia anti-bypass DNS (v0.3.1)

Dispositivos modernos usam DNS cifrado (DoH, DoT, DoQ) e servicos como
iCloud Private Relay para contornar bloqueio baseado em observacao DNS.
A partir da v0.3.1, o Layer7 aplica uma estrategia multi-camada.

### Camada 1: Bloqueio de DoT/DoQ (porta 853)

Regras PF no snippet do pacote bloqueiam TCP e UDP na porta 853:

```text
block drop quick inet proto tcp to port 853 label "layer7:anti-dot"
block drop quick inet proto udp to port 853 label "layer7:anti-doq"
```

Eficacia: 100% — porta 853 serve exclusivamente para DoT/DoQ.

### Camada 2: Politica nDPI anti-bypass

O nDPI classifica fluxos como `DoH_DoT` (protocolo 196) e
`iCloudPrivateRelay` (protocolo 277). O sample config inclui uma politica
built-in `anti-bypass-dns` com `action=block` para esses protocolos.

Quando o nDPI classifica um fluxo como DoH, o IP de destino entra em
`layer7_block_dst`, impedindo conexoes futuras ao resolver DoH.

Limitacao: o nDPI precisa de 3-10 pacotes para classificar. Os primeiros
pacotes de uma sessao DoH podem passar antes da detecao.

### Camada 3: Unbound NXDOMAIN

O script `/usr/local/libexec/layer7-unbound-anti-doh` configura o Unbound
do pfSense para devolver NXDOMAIN para dominios de bypass conhecidos:

- **Apple Private Relay**: `mask.icloud.com`, `mask-h2.icloud.com`
  (metodo oficial Apple — iOS desativa Relay automaticamente)
- **Firefox canary**: `use-application-dns.net`
  (Firefox desativa DoH quando este dominio retorna NXDOMAIN)
- **Resolvers DoH publicos**: `dns.google`, `cloudflare-dns.com`,
  `dns.quad9.net`, `dns.adguard.com`, `doh.opendns.com`, etc.

Eficacia: Alta. Forca fallback para DNS convencional na maioria dos casos.

### Camada 4: DNS forçado (recomendacao manual)

Para forcar todo o DNS pelo pfSense, o administrador pode configurar
uma regra NAT redirect na GUI do pfSense:

1. Firewall > NAT > Port Forward
2. Redirecionar TCP/UDP porta 53 de qualquer origem LAN para o pfSense
3. Isso impede que clientes usem DNS externo (8.8.8.8, 1.1.1.1, etc.)

Nao e configurado automaticamente pelo pacote porque envolve NAT.

### Limitacoes honestas

- **DoH hardcoded**: apps que usam IP de DoH hardcoded (sem resolucao
  DNS) nao sao afectadas pelo NXDOMAIN do Unbound. Dependem do nDPI.
- **Novos provedores**: a lista de dominios precisa de manutencao.
- **ECH**: TLS 1.3 com Encrypted Client Hello esconde o SNI.
  Nao se resolve sem MITM (fora do escopo V1).

## Risco aberto

A maior incerteza restante e a ordem/precedencia real da regra no ruleset final
do appliance. A regra usa `block drop quick`, o que garante match imediato, mas
a posicao exata em `rules.debug` depende de onde `PFCONFIG_PACKAGE_FILTER` e
inserido pelo pfSense. A confirmacao em `rules.debug` e `pfctl -sr` continua
obrigatoria antes de fechar a fase.
