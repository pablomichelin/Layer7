# Enforcement PF (V1 — repo)

## Objetivo

Ligar decisões **block** / **tag** a **tabelas PF** no pfSense, sem MITM.

> **Semantica actual de `block`:** em `legacy_global`, o IP de destino entra
> em `layer7_block_dst` e afecta todos os clientes. Em `scoped_hybrid`, match
> por host/DNS/SNI ou app/categoria normal usa `layer7_pdst_N`; somente
> quarentena explícita usa `layer7_psrc_N`. Em
> `mode=monitor`/`enabled=false` não há qualquer
> `block drop`.

## Estado atual

O enforcement atual do produto já faz:

- decisão `block` / `tag` no `layer7d`;
- `block` -> `pfctl -T add` do **IP de destino** em `layer7_block_dst`;
  `tag` -> `pfctl -T add` do **IP de origem** em `layer7_tagged`;
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

## DNS forcado (`force_dns`, F4.3 / BG-011)

Para regras de **blacklist** com *Forçar DNS local*, o pacote gera `rdr` **inet**
(UDP/TCP porta 53). Desde **`1.8.11_45`**, as regras rdr entram no **ruleset
principal** do pfSense via `layer7_generate_rules("nat")` (hook
`filter_rule_function` / `discover_pkg_rules`, mesmo mecanismo do Squid
transparente) — verificar com `pfctl -s nat`. O sub-anchor
`natrules/layer7_nat` usado até à `_44` é **legado**: o ruleset principal só
declara `nat-anchor "natrules/*"` (sem `rdr-anchor`) e as regras `rdr` lá
dentro nunca eram avaliadas pelo PF. Caminho **distinto** do
snippet em `/usr/local/etc/layer7/pf.conf` usado para tabelas de bloqueio.
Isto alinha anti-bypass DNS ao enforcement sem MITM. Detalhe operacional:
[`../10-license-server/MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md)
(addendum F4.3); evidência no appliance: [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md)
(secção **11**, incl. anti-QUIC opcional no mesmo roteiro e cenário opcional
multi-interface / VLAN); matriz:
[`../tests/test-matrix.md`](../tests/test-matrix.md) ponto **6.7**.

## Anti-QUIC por interface (`layer7.inc`)

Com **interfaces seleccionadas** na GUI (bloquear QUIC só nesses segmentos),
`layer7_generate_rules()` em `layer7.inc` emite linhas
`block drop quick on <interface> inet|inet6 …`. Desde **`1.8.11_12`**, a
validação do nome PF reutiliza a mesma
função **`layer7_pf_ifname_for_rules()`** que o trilho **DNS forcado** / `rdr`
(refactor DRY). Em `_29`, FP-018 corrige a ordem anterior `inet on <if>`,
rejeitada pelo parser PF quando o toggle estava activo.

## Inspecao por SNI (Caminho A / A3)

Toggle `layer7.sni_inspection` (opt-in, OFF por defeito). Quando ON, o daemon
usa o **SNI (TLS)** / **Host (HTTP)** que o nDPI ja extrai
(`flow->host_server_name`) como host para matching de politicas, preferido
sobre o DNS reverso. Melhora bloqueio em CDNs (IPs partilhados) e quando o DNS
do cliente esta em cache ou cifrado. Continua passivo e por destino (o IP de
destino entra em `layer7_block_dst`); sem MITM. Limitacao: TLS 1.3 **ECH**
cifra o SNI. Decisao: `docs/03-adr/ADR-0013-bloqueio-por-sni-via-ndpi.md`.

## Politicas por dispositivo (Caminho A / A2)

Um **grupo** pode conter `device_macs` (dispositivos por MAC). O pacote resolve
MAC -> IP actual (DHCP leases + ARP) e grava `device_ips` no grupo; o daemon le
`device_ips` como hosts de origem do grupo (`parse_group`), alem dos `hosts`
manuais. A imposicao continua por IP em PF (o PF nao faz match por MAC).
Re-resolucao via "Resync IPs dos dispositivos" e ao gravar/atribuir. Ver
`docs/03-adr/ADR-0012-politicas-por-dispositivo-mac-para-ip.md`. Recomenda-se
DHCP static mapping para IP estavel. Limite: 64 hosts de origem por grupo.

## Tabelas

| Uso | Nome default | Config |
|-----|--------------|--------|
| Block (origem/quarentena) | `layer7_block` | Fixo no código (`enforce.h`) |
| Block (destino/sites/apps) | `layer7_block_dst` | Fixo no código (`enforce.h`); **legacy_global** |
| Block destino escopado por politica | `layer7_pdst_N` | Caminho B / E2; indice N = ordem `layer7_policies_sort()` |
| Quarentena origem escopada por politica | `layer7_psrc_N` | Caminho B / E2; somente opt-in explícito |
| Allow destino por política | `layer7_pallow_N` | BG-056 / ADR-0016; destino aprendido com TTL |
| Excepção allow por origem | `layer7_exc_allow_N` | BG-056 / ADR-0016; conteúdo estático do JSON |
| Tag | `layer7_tagged` ou **`tag_table`** na política | Por política `action=tag` |

### Caminho B / E2 — PF escopado no pacote (`scoped_hybrid`)

Com `layer7.enforcement_model=scoped_hybrid` e `mode=enforce`, o pacote deixa de
emitir `block drop … to <layer7_block_dst>` global e passa a gerar, por cada
politica `enabled`+`block`:

- `table <layer7_pdst_N> persist` + `block drop quick inet from {src} to <layer7_pdst_N>`
  quando a politica tem hosts (sites/SNI) ou app/categoria normal;
- `table <layer7_psrc_N> persist` + `block drop quick inet from <layer7_psrc_N> to !<localsubnets>`
  somente quando `quarantine_origin=true`;
- politica sem origem (`src_hosts`/`src_cidrs`/grupos): regra global **so** com
  `scope_global: true` (checkbox na GUI Politicas); para app/categoria,
  `quarantine_origin: true` também cria regra `psrc` executável.

Funcao geradora: `layer7_policy_enforcement_rules_text()` em `layer7.inc`,
invocada por `layer7_generate_rules("filter")`. Flush/resync: `layer7_resync()`,
`layer7-pfctl flush-all`, `enforcement_flush_all_tables()` em `main.c` (0..23).

**Nota:** com E3 (2026-06-15), em `scoped_hybrid` o daemon popula
`layer7_pdst_N` / `layer7_psrc_N` conforme `enforce_kind`; `layer7_block_dst`
so e usada em `legacy_global`. Default permanece `legacy_global` ate E8.

### Caminho B / E3 — Runtime daemon escopado

Com `enforcement_model=scoped_hybrid` e `mode=enforce`:

| Decisao | Tabela PF populada | IP |
|---------|-------------------|-----|
| block + `dst_scoped` (DNS/SNI/host/app normal) | `layer7_pdst_{idx}` | destino resolvido/observado |
| block + `src_scoped` (quarentena explícita) | `layer7_psrc_{idx}` | origem do fluxo |
| block + `legacy_global` | `layer7_block_dst` | destino |

Funcoes: `layer7_pf_resolve_block_target()`, `layer7_apply_block_enforcement()`
em `main.c`; cache TTL indexado por `(table_name, ip)`. Allowlist gate mantido
antes de qualquer add a pdst/block_dst. Logs incluem `kind`, `table`, `policy`.

CLI lab `-e` alinhado: `layer7_pf_enforce_decision(dec, src, dst, scoped, dry)`.

**Gate appliance obrigatorio (two-client):** ver
[`validacao-lab.md`](../04-package/validacao-lab.md) secao **12** — pendente
ate execucao no appliance `192.168.100.254`.

### BG-056 — allow por marca interna (`1.8.11_28`)

Allow não é uma autorização de firewall. O pacote emite
`match ... tag L7ALLOW`; os blocks geridos pelo Layer7 usam
`! tagged L7ALLOW`. O PF continua a processar regras nativas do pfSense.

- política allow: marca apenas origem/interface -> `<layer7_pallow_N>`;
- excepção allow: marca tráfego externo da origem em
  `<layer7_exc_allow_N>`;
- allowlist global: marca destino em `<layer7_allow_dst>`;
- `except_ips` UT1: usa `layer7_blsrc_N`, com origens positivas e entradas
  negadas `!IP`; somente o block daquela regra consulta essa origem efectiva.

O daemon popula `pallow_N` depois de uma vitória explícita da política e usa o
cache TTL existente. `pallow_0..23` entra em flush, resync e self-heal.
Mutation de política ou excepção limpa as tabelas antes de reordenar índices.
Não existe `pass quick` de allow no ruleset actual.

Limite: allow app-only precisa observar DNS ou um primeiro fluxo classificável
para aprender o destino; o produto não cria um bypass global para mascarar
essa limitação.

### Candidato `_25` — integração e pré-condições

- política normal app/host usa `pdst`; `psrc` fica reservada à quarentena;
- `quarantine_origin=true` é necessário para inclusão dinâmica em `psrc`;
- a GUI recusa block scoped sem uma dessas três condições;
- IDs `lan`/`optN` são migrados para interfaces reais antes de libpcap/PF;
- default continua `legacy_global`; `_25` não está publicado e depende do
  gate two-client.

Plano: [`../09-blocking/plano-enforcement-100-porcento.md`](../09-blocking/plano-enforcement-100-porcento.md).

## Pagina de bloqueio utilizador final (`block_page`, ADR-0017 / BG-062)

Toggle opt-in `layer7.block_page.enabled` (Definições). Quando activo com
`mode=enforce`:

1. **Unbound sinkhole:** dominios de politicas `block` activas (+ blacklists UT1
   opcional, limite configuravel) resolvem para o **IP portal** (auto ou manual).
2. **NAT rdr:** TCP porta 80 no IP portal → `127.0.0.1:8099`.
3. **Servico `layer7-blockpage`:** PHP built-in server serve HTML informativo;
   header `Host:` identifica o site bloqueado.

Enforcement PF (`layer7_block_dst`, blacklists, scoped) **nao e substituido**.
Com toggle OFF, comportamento identico ao anterior (drop silencioso).

### Limitacoes honestas

| Cenario | Comportamento |
|---------|---------------|
| HTTP | Pagina «Acesso bloqueado» visivel |
| HTTPS | Erro TLS / certificado (sem MITM) |
| CDN / IP directo | PF drop se IP nao sinkhole |
| QUIC / DoH | Anti-bypass existente; pagina nao garantida |

Rollback: desactivar toggle ou reinstalar versao anterior. ADR:
[`ADR-0017`](../03-adr/ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md).

### DNS forcado global (`block_page.force_dns`, ADR-0018 / BG-063, `_40`)

Opt-in adicional na mesma seccao da GUI. Com block page activa:

- rdr UDP/TCP porta 53 em cada interface de captura → Unbound local
  (`127.0.0.1`), no ruleset principal desde `_45` (verificar com
  `pfctl -s nat`) — clientes com DNS externo hardcoded recebem as respostas
  do sinkhole;
- anti-DoH Unbound aplicado automaticamente se ainda nao configurado
  (NXDOMAIN para resolvers DoH conhecidos + canario `use-application-dns.net`);
- desactivar `force_dns` nao remove anti-DoH (remocao explicita em
  Diagnostics).

Recomendado combinar com `block_dot_doq` (porta 853) e anti-QUIC por
interface. Precedencia do daemon desde `_39`: politica manual block prevalece
sobre a allowlist-seed e revoga o IP de `layer7_allow_dst` ao aplicar block
(`allow_cache_revoke_ip`). ADR:
[`ADR-0018`](../03-adr/ADR-0018-plano-dns-forcado-e-precedencia-bloqueio.md).

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
block drop quick inet from <layer7_block> to !<localsubnets> ! tagged L7ALLOW label "layer7:block:src"
block drop quick inet6 from <layer7_block> to !<localsubnets> ! tagged L7ALLOW label "layer7:block:src6"
block drop quick inet to <layer7_block_dst> ! tagged L7ALLOW label "layer7:block:dst"
block drop quick inet6 to <layer7_block_dst> ! tagged L7ALLOW label "layer7:block:dst6"
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
| `layer7_pf_resolve_block_target(dec, src, dst, scoped, …)` | Resolve tabela/IP para block (pdst/psrc ou block_dst) |
| `layer7_pf_enforce_decision(dec, src, dst, scoped, dry_run)` | Se `dec` exige block/tag: add (ou simula se `dry_run`) |

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

Ordem típica: **`-c`**, **`-n`** (opcional), **`-e IP APP [categoria]`**. Com
`enforcement_model=scoped_hybrid`, block app-only normal adiciona o destino a
`layer7_pdst_N`; com `quarantine_origin=true`, adiciona a origem a
`layer7_psrc_N`; legacy usa `layer7_block_dst`. No runtime, **nDPI** chama
`layer7_on_classified_flow` (decidir + enforce escopado).

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

## Actualizacoes Caminho B / estabilizacao (2026-06-16)

- **Licenca invalida:** recheck periodico faz flush de todas as tabelas PF
  dinamicas — monitor-only real.
- **Allowlist:** CIDR `0.0.0.0/0` rejeitado (daemon + GUI); apenas `/1`–`/32`.
- **DNS callback:** respeita `layer7.enabled=false`; legacy e scoped usam
  `layer7_decide_for_client()` (excepcoes antes de block).
- **scoped_hybrid (experimental, nao default):** `quarantine_origin` obrigatorio
  para quarentena app-only; politicas block vazias rejeitadas salvo
  `scope_global`/`quarantine`.
- **TTL dinamico:** cache enforcement/allowlist DNS expira (60s–3600s).
- **Flush centralizado:** indices orfaos ate limites 24 (`pdst`/`psrc`) e 32
  (`bld_*`).
