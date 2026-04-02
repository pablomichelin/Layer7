# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

## [Unreleased]

### Changed — F3.7 pack operacional da validacao manual

- **Pack canónico da F3.7** —
  `docs/01-architecture/f3-pack-operacional-validacao.md` passa a
  operacionalizar a matriz da F3.6 com directoria por `run_id`, nomes fixos
  para outputs, classificacao uniforme `PASS` / `FAIL` / `INCONCLUSIVE` /
  `BLOCKED` e politica conservadora de recolha/retencao de evidencias
- **Helper shell barato fora do produto** —
  `scripts/license-validation/export-license-evidence.sh` passa a exportar
  snapshot da licenca, `activations_log` e `admin_audit_log` de forma
  reproduzivel, sem mudar endpoints, schema, `.lic` ou daemon
- **Template minimo por cenario** —
  `docs/tests/templates/f3-scenario-evidence.md` passa a servir como molde
  para registo operacional por cenario, reduzindo ambiguidade sem criar suite
  nova nem automacao pesada

### Changed — F3.6 validacao manual controlada e evidencias

- **Matriz canónica da F3.6** —
  `docs/01-architecture/f3-validacao-manual-evidencias.md` passa a registar
  de forma factual o que ja esta robusto em codigo, o que so pode ser provado
  em backend, o que exige appliance/relogio/fingerprint real e o que continua
  impossivel comprovar sem mudar o modelo actual
- **Politica oficial de "validacao suficiente"** — roadmap, backlog,
  checklist, manual de licencas e docs de testes passam a exigir cenarios
  obrigatorios, evidencias minimas e outputs reais antes de tratar a F3 como
  substancialmente validada
- **Fecho honesto sem mudar codigo** — a F3.6 nao adiciona feature nova nem
  mexe em `.lic`, daemon ou fingerprint; ela transforma os pendentes de
  appliance/lab em matriz operacional explicita, incluindo grace, revogacao
  com `.lic` antigo, coexistencia de artefactos e drift real de fingerprint

### Changed — F3.5 emissao, reemissao e rastreabilidade do artefacto

- **Trilha canónica do `.lic` na F3.5** —
  `docs/01-architecture/f3-emissao-reemissao-rastreabilidade.md` passa a
  registar de forma factual onde o artefacto e emitido, como a activacao
  publica difere do download administrativo, qual o risco de coexistencia de
  multiplos artefactos validos e o que continua impossivel resolver sem
  mudar formato, daemon ou revogacao offline
- **Emissao publica auditavel sem mudar o contrato** — `POST /api/activate`
  continua a devolver `{ data, sig }`, mas passa a deixar rasto adicional do
  artefacto emitido com `flow`, `emission_kind`, contexto da licenca e hashes
  baratos do payload/assinatura/envelope
- **Download administrativo com contexto do artefacto** — o evento
  `license_downloaded` passa a registar metadados suficientes para
  investigacao futura, sem schema novo, sem versionamento obrigatorio e sem
  mudar o formato do `.lic`

### Changed — F3.4 mutacao administrativa, reemissao e guardrails

- **Superficie administrativa canónica da F3.4** —
  `docs/01-architecture/f3-mutacao-admin-reemissao-guardrails.md` passa a
  registar de forma factual quais campos de licenca sao mutaveis via CRUD
  normal, quais mutacoes continuam seguras antes/depois do bind e onde a
  reemissao administrativa se torna perigosa por coexistir com `.lic` antigo
  ainda valido offline
- **Transferencia silenciosa de licenca bindada bloqueada** — o backend passa
  a negar com `409` a mudanca de `customer_id` em licenca ja
  activada/bindada, reduzindo o risco de mover ownership comercial sem trilha
  dedicada de rebind/transferencia
- **Auditoria minima de update reforcada** — `license_updated` passa a
  registar os campos alterados e flags de bind/activacao, melhorando
  previsibilidade operacional sem criar workflow novo nem mudar o formato do
  `.lic`

### Changed — F3.3 expiracao, revogacao, grace e validade offline

- **Semantica canónica da F3.3** —
  `docs/01-architecture/f3-expiracao-revogacao-grace.md` passa a registar de
  forma factual a diferenca entre estado persistido e estado efectivo, o papel
  exacto do grace local, o limite real da revogacao actual e as condicoes em
  que um `.lic` antigo continua valido offline
- **Risco de rebind explicitado** — a trilha documental passa a declarar de
  forma objectiva que um eventual rebind administrativo e perigoso nesta fase,
  porque o `.lic` antigo pode continuar operativo offline no hardware antigo
  ate `expiry + grace`
- **Estado efectivo centralizado no backend** — o backend passa a usar um
  helper minimo comum para derivar `active`, `expired` e `revoked` em
  `activate`, `licenses`, `customers` e `dashboard`, reduzindo ambiguidade
  sem mudar schema, formato `.lic` ou algoritmo de fingerprint

### Changed — F3.2 fingerprint, binding e cenarios reais de appliance

- **Matriz canónica de fingerprint/binding** —
  `docs/01-architecture/f3-fingerprint-e-binding.md` passa a registar a
  formula real do fingerprint observada no daemon, as dependencias de
  `kern.hostuuid` e da primeira MAC Ethernet nao-loopback, os riscos de falso
  bloqueio em reinstall/NIC/VM/restore/migracao e a politica conservadora da
  fase para primeira activacao, reactivacao legitima, reactivacao suspeita e
  mudanca que exige accao administrativa
- **Compatibilidade preservada** — a F3.2 nao muda a formula do fingerprint,
  nao abre tolerancia ampla, nao quebra `.lic` existente e nao altera o
  contrato publico de `POST /api/activate`
- **Normalizacao defensiva do bind persistido** — o backend passa a
  canonicalizar `hardware_id` legacy por `trim + lowercase` antes de comparar
  e assinar o `.lic`, reduzindo falso bloqueio por drift de formato sem
  alterar o fingerprint real

### Changed — F3.1 abertura formal da robustez de licenciamento/activacao

- **Contrato canónico da F3 aberto** — `docs/01-architecture/f3-arquitetura-licenciamento-ativacao.md`
  passa a registar o estado real observado no backend e no daemon, os
  estados/transicoes do licenciamento e a diferenca entre expiracao online e
  grace local
- **Compatibilidade preservada** — `POST /api/activate` continua a responder
  `{"data","sig"}` e a usar os mesmos codigos `400` / `404` / `409`, sem
  mudar o formato `.lic` nem o algoritmo de fingerprint
- **Idempotencia defensiva na activacao** — a reactivacao do mesmo hardware
  deixa de mutar a licenca sem necessidade, o `.lic` passa a ser assinado a
  partir do `hardware_id` efectivamente persistido, e o `UPDATE` do bind fica
  reforcado pela propria condicao de `hardware_id`
- **Trilha documental alinhada** — `CORTEX`, roadmap, backlog, checklist,
  manual de licencas e matriz de testes passam a tratar a F3 como aberta e a
  reservar a F3.2 para grace/offline/fingerprint em appliance

### Changed — F2.5 segredos, bootstrap, backup/restore e runbooks do license server

- **Segredos e ownership minimo materializados** — o stack passa a declarar
  oficialmente a custodia de `POSTGRES_PASSWORD`, `ED25519_PRIVATE_KEY` e
  `ADMIN_BOOTSTRAP_PASSWORD`, com suporte a `ED25519_PRIVATE_KEY_FILE` no
  backend e runbook canónico para uso/rotacao operacional minima
- **Bootstrap administrativo endurecido** — `bootstrap-admin.js` passa a ser o
  fluxo oficial para `status`, `init` e `reset-password`, com auditoria em
  banco e revogacao de sessoes no reset; `seed.js` fica apenas como wrapper
  de compatibilidade
- **Backup/restore minimo executavel** — o repositório passa a incluir
  `backup-postgres.sh` e `restore-postgres.sh`, e a operacao oficial do banco
  deixa de depender apenas de memoria oral
- **F2 encerrada documentalmente** — arquitetura, roadmap, backlog, manuais e
  runbooks passam a tratar a F2 como concluida e a apontar a F3 como proxima
  fase elegivel

### Changed — F2.4 integridade transacional e validacao do CRUD do license server

- **Validacao forte por rota** — `activate`, `customers` e `licenses` passam a
  operar com schema fechado para payload e query, rejeicao explicita de
  campos inesperados, IDs/paginacao invalidos e `JSON` malformado com `400`
- **CRUD administrativo coerente** — mutacoes e downloads passam a distinguir
  payload invalido (`400`), recurso inexistente (`404`) e conflito logico
  (`409`) sem vazar detalhe interno do banco
- **Atomicidade minima materializada** — activacao passa a usar
  `SELECT ... FOR UPDATE` com bind/timestamps/log de sucesso na mesma
  transacao, e create/update/revoke/archive administrativos passam a commitar
  junto com a auditoria em banco
- **Delete seguro no painel** — clientes e licencas deixam de sofrer delete
  fisico no fluxo administrativo normal e passam a usar arquivo logico com
  `archived_at` / `archived_by_admin_id`, ocultando historico das listagens
  sem o destruir

### Changed — F2.3 protecao da superficie administrativa do license server

- **CORS same-origin oficial** — o backend deixa de aplicar `cors()` aberto
  e passa a aceitar apenas o origin administrativo oficial em producao,
  falhando fechado para requests de browser fora da allowlist
- **Login endurecido contra abuso** — `POST /api/auth/login` passa a operar
  com limiter dedicado por IP e por `email + IP`, lockout temporario por
  falhas repetidas e respostas `401`/`429` genericas sem enumeracao de
  credenciais
- **Auditoria minima persistida** — auth/sessao e mutacoes administrativas
  passam a gerar rasto minimo em `admin_audit_log`, enquanto os guardas de
  brute force/lockout passam a viver em `admin_login_guards`

### Changed — F2.2 autenticacao e sessao administrativa do license server

- **Sessao stateful oficial** — o painel administrativo deixa de depender de
  JWT em `localStorage` e passa a operar com sessao stateful em
  `admin_sessions`, cookie `HttpOnly + Secure + SameSite=Strict`,
  expiracao ociosa/absoluta, renovacao controlada e logout com invalidacao
  real no backend
- **Contrato frontend/backend alinhado** — a SPA passa a fazer bootstrap por
  `GET /api/auth/session`, chamadas autenticadas same-origin por cookie e
  tratamento consistente de sessao invalida/expirada sem bearer manual
- **Documentacao operacional** — runbook, manuais e arquitetura passam a
  tratar `https://license.systemup.inf.br` como canal oficial tambem para
  login administrativo, deixando CORS/rate limit/brute force explicitamente
  para a F2.3

### Changed — F2.1 publicacao segura do license server

- **Canal publico oficial** — `https://license.systemup.inf.br` em `443/TCP`
  passa a ser o unico caminho normativo para painel administrativo e
  activacao online; o origin `8445` deixa de ser tratado como endpoint
  publico
- **Origin privado por defeito** — `docker-compose.yml` passa a prender
  `8445` ao loopback do host por defeito, mantendo override apenas para rede
  privada controlada com ACL/firewall explicitos
- **Borda e documentacao operacional** — `nginx.conf` interno passa a
  rejeitar hosts inesperados e a publicar headers basicos de seguranca, e o
  runbook/manual de licencas passam a exigir edge proxy com certificado
  valido, redirect `HTTP -> HTTPS` e troubleshooting controlado do origin

### Changed — F1.1 contrato oficial de distribuicao

- **Canal oficial de instalacao** — `install.sh` e `uninstall.sh` passam a ser
  consumidos por URLs versionadas de GitHub Releases, retirando `main` mutavel
  da trilha normativa
- **Contrato operacional de release** — o conjunto minimo vigente da F1.1
  fica alinhado em `.pkg`, `.pkg.sha256`, `install.sh` e `uninstall.sh`
  versionados; manifesto e assinatura continuam reservados para a F1.2
- **Documentacao canónica e operacional** — manuais, runbooks, roadmap e
  arquitectura passam a tratar `.txz` apenas como legado historico

### Changed — F1.2 manifesto, checksum e assinatura de release

- **Trust chain de release** — builder passa a preparar stage dir sem assinar;
  signer passa a assinar o manifesto fora do builder; publicacao passa a
  aceitar apenas stage dir ja assinado
- **Manifesto oficial** — `release-manifest.v1.txt` passa a listar metadados
  de origem, papeis builder/signer e hashes SHA256 dos assets oficiais
- **Assinatura oficial** — `release-manifest.v1.txt.sig` passa a usar
  Ed25519 com OpenSSL (`pkeyutl -sign -rawin`) e a public key correspondente
  passa a integrar o conjunto oficial da release

### Changed — F1.3 origem confiavel, mirror/cache e last-known-good de blacklists

- **Origem oficial de blacklists** — o pacote deixa de tratar UT1 directo
  como origem de auto-update e passa a consumir apenas
  `layer7-blacklists-manifest.v1.txt` assinado em HTTPS por canal oficial
  Layer7/Systemup
- **Mirror/cache controlado** — GitHub Releases entra como mirror controlado
  da mesma snapshot assinada, enquanto o appliance passa a guardar cache local
  por `snapshot_id` em `/usr/local/etc/layer7/blacklists/.cache/`
- **Last-known-good materializada** — a ultima snapshot validada passa a ser
  preservada em `/usr/local/etc/layer7/blacklists/.last-known-good/` com
  estado activo rastreavel em `.state/active-snapshot.state` e restauro
  explicito via `update-blacklists.sh --restore-lkg`

### Changed — F1.4 matriz de fallback e degradacao segura

- **Install/update fail-closed** — o `install.sh` versionado passa a validar
  `release-manifest.v1.txt`, assinatura destacada e checksum do `.pkg` antes
  do `pkg add`; release suspeita deixa de ser instalada
- **Signer carimba o trust anchor do instalador** — `sign-release.sh` passa a
  embutir a public key oficial e o fingerprint esperado no `install.sh`
  staged, mantendo a validacao ancorada fora do builder
- **Blacklists com estado degradado explicito** — `update-blacklists.sh`
  passa a escrever `.state/fallback.state` com `healthy`, `degraded` e
  `fail-closed`, sempre preservando apenas material previamente validado

## [1.8.3] — 2026-04-01

### Changed — Bloqueio de QUIC (UDP 443) por interface seleccionável

- **Nova funcionalidade**: o bloqueio de QUIC deixa de ser um checkbox global e passa a ser uma **lista de interfaces seleccionáveis** em `Layer7 → Configurações Gerais`
- Cada interface pode ser activada/desactivada independentemente para bloqueio QUIC
- Regras PF geradas com `on <iface>` por cada interface seleccionada, mantendo `to !<localsubnets>`
- **Retrocompatibilidade**: instalações com `block_quic: true` no JSON (formato antigo) continuam a funcionar com regra global até o utilizador gravar pela nova GUI
- Novo campo no schema de config: `"block_quic_interfaces": ["em0", "em1.46"]`
- **PORTVERSION** bumped para 1.8.3

## [1.8.2] — 2026-04-01

### Fixed — Regras de bloqueio afectavam tráfego interno (impressoras, bancos locais)

- **Arquitectura corrigida**: Layer7 passa a bloquear **apenas tráfego com destino externo à rede local**. Tráfego entre hosts da LAN não é afectado.
- **`layer7_pf_default_rules_text()`** (`layer7.inc`): regras anti-DoT/DoQ (porta 853 TCP/UDP) e block:src (`<layer7_block>`) agora incluem `to !<localsubnets>` em inet e inet6
- **`layer7_generate_rules()`** (`layer7.inc`): regra anti-QUIC (UDP 443) agora inclui `to !<localsubnets>` em inet e inet6
- **`write_rules()`** (`layer7-pfctl`): sincronizado com as mesmas correcções
- **`pf.conf.sample`**: sincronizado com as mesmas correcções
- `<localsubnets>` é o alias nativo do pfSense que contém todas as sub-redes directamente conectadas (LAN, VLANs, etc.)
- **Impacto**: impressoras locais, serviços bancários em rede corporativa e qualquer serviço interno que use UDP 443 (QUIC) voltam a funcionar normalmente
- **PORTVERSION** bumped para 1.8.2

## [1.8.0] — 2026-04-01

### Fixed — `label` em regras `rdr` causa syntax error no FreeBSD 15

- **`layer7_generate_rdr_rules_snippet()`**: o keyword `label "..."` nas regras `rdr` causa "syntax error" no pfctl do FreeBSD 15 quando carregado num anchor via `pfctl -a anchor -N -f`. Removido `label` das regras geradas
- Regras agora no formato válido: `rdr on <iface> inet proto {udp|tcp} from <cidr> to !127.0.0.1 port 53 -> 127.0.0.1`
- Ambas as regras (UDP + TCP port 53) carregam em `natrules/layer7_nat`
- **PORTVERSION** bumped para 1.8.0

## [1.7.9] — 2026-04-01

### Fixed — Sintaxe `rdr pass` inválida em pfSense 2.8 / FreeBSD 15

- **`layer7_generate_rdr_rules_snippet()`**: as regras `rdr` eram geradas com o keyword `pass` (`rdr pass on <iface> ...`), que causa "syntax error" no pfctl do FreeBSD 15 (pfSense 2.8). Apenas `rdr on <iface> ...` (sem `pass`) é válido. O pfctl normaliza o output para `rdr pass on ...` mas a sintaxe de INPUT deve ser `rdr on`
- Correcção: removido `pass` das strings geradas em `layer7_generate_rdr_rules_snippet()`
- Resultado: ambas as regras (UDP port 53 e TCP port 53) carregam correctamente no anchor `natrules/layer7_nat`
- **PORTVERSION** bumped para 1.7.9

## [1.7.8] — 2026-04-01

### Fixed — Regras `rdr` (force_dns) agora injectadas via pfctl directo

#### Bug Crítico — pfSense CE não processa `nat_rules_needed` do XML do package

- **Root cause**: o tag `<nat_rules_needed>layer7_generate_nat_rules</nat_rules_needed>` em `layer7.xml` nunca é processado por pfSense CE. O `pkg-utils.inc` do pfSense só processa `filter_rules_needed` (guardado como `filter_rule_function`) — não existe equivalente para NAT. As regras `rdr` de DNS forçado geradas por `layer7_generate_rdr_rules_snippet()` nunca chegavam ao PF
- **Tag XML errado**: `<custom_php_resync_command>` não existe no pfSense CE — o correcto é `<custom_php_resync_config_command>` com valor PHP executável via `eval()` (ex: `layer7_resync();`); por isso `layer7_resync()` nunca era chamado automaticamente via `sync_package()`
- **Solução**: nova função `layer7_inject_nat_to_anchor()` que injeta as regras `rdr` directamente no sub-anchor `natrules/layer7_nat` via `pfctl -a natrules/layer7_nat -N -f <tmp>`. pfSense CE usa `pfctl -f` sem `-F flush` → sub-anchor persiste entre reloads
- **Integração**: chamada em `layer7_generate_rules()` (chamada em todo reload PF via `filter_rule_function`) e em `layer7_resync()` (chamada no save de config)
- **Tag XML**: corrigido para `<custom_php_resync_config_command>layer7_resync();</custom_php_resync_config_command>`
- **PORTVERSION** bumped para 1.7.8

## [1.7.7] — 2026-04-01

### Fixed — Regras rdr (force_dns) nunca geradas em interfaces VLAN

#### Bug Crítico — Regex não aceitava interfaces VLAN com ponto (ex: `em1.46`)

- **Root cause**: `layer7_generate_rdr_rules_snippet()` em `layer7.inc` tentava obter o device real via `get_real_interface($ifid)`. Quando o layer7 é configurado com uma interface VLAN cujo ID já é o device name (ex: `"em1.46"`), o pfSense retorna `NULL` porque `em1.46` não é um friendly name (é o device). O fallback regex `/^[a-z][a-z0-9]+$/i` **não aceita pontos** → interface ignorada → `$real_ifaces` vazio → função retorna `""` → **zero regras `rdr` geradas**, mesmo com `force_dns: true` na blacklist
- **Correcção**: regex actualizado para `/^[a-z][a-z0-9]*(\.[0-9]+)?$/i`
  - Aceita: `lan`, `wan`, `em0`, `em1`, `em1.46`, `igb0.100`, `vtnet0`, `vtnet0.200`, `lagg0.10`
  - Rejeita: strings inválidas como `../../etc`, `; rm -rf`, etc. (segurança mantida)
- **Ficheiro**: `package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc`, linha 108
- **PORTVERSION** bumped para 1.7.7

## [1.7.6] — 2026-03-31

### Fixed — Monitor ao vivo acumulativo (comportamento tipo Squid)

- **`layer7_events.php`**: monitor substituía o conteúdo inteiro a cada poll (a cada 2s); quando as últimas N linhas do log já não continham a IP filtrada (empurrada por novos eventos de outros dispositivos), o monitor mostrava "Sem eventos recentes" e o histórico desaparecia
- **Nova lógica JS — buffer acumulativo**: o monitor mantém um buffer de até 500 linhas em memória; a cada poll detecta quais linhas são novas (usando sobreposição com a última linha vista) e **apenas acrescenta**; nunca apaga o histórico existente
- **Botão "Limpar"**: reset manual do buffer sem sair da página
- **Contador de linhas**: mostra quantas linhas estão acumuladas no buffer
- **Servidor**: aumentado tail de 100→300 linhas e retorno de 40→60 linhas por poll para melhor cobertura histórica
- **PORTVERSION** bumped para 1.7.6

## [1.7.5] — 2026-03-31

### Fixed — Botão "Aplicar" nos Perfis Rápidos não funcionava

- **`layer7_policies.php`**: `json_encode($prof_id)` e `json_encode($prof_name)` produzem strings com aspas duplas (`"youtube"`) que eram inseridas directamente no atributo `onclick="..."` sem escaping HTML; o browser terminava o atributo na primeira `"`, truncando o handler para `l7showProfileModal(` (JavaScript inválido); o clique não fazia nada
- **Correcção**: envolver em `htmlspecialchars(..., ENT_QUOTES)` → as `"` tornam-se `&quot;` no HTML (válido em atributos) e o browser converte de volta para `"` ao executar o JS; `onclick` resultante: `l7showProfileModal(&quot;youtube&quot;, &quot;YouTube&quot;)` → executa `l7showProfileModal("youtube", "YouTube")` correctamente

- **PORTVERSION** bumped para 1.7.5

## [1.7.4] — 2026-03-31

### Fixed — Segunda revisão: 3 bugs adicionais

#### Bug Médio — `generate_rdr_rules()` código morto em `layer7-pfctl`
- Após o fix v1.7.3, a função `generate_rdr_rules()` (40 linhas de PHP inline) permanecia no script mas nunca era chamada — `write_rules()` foi alterado e não a invoca; removida para evitar confusão e facilitar manutenção

#### Bug Menor — `s_bl_lookups` não incrementado no SNI check
- **`main.c`**: `l7_blacklist_lookup()` era chamado no SNI check (`layer7_on_classified_flow()`) sem incrementar `s_bl_lookups`; o stat `bl_lookups` no JSON ficava subestimado (representava apenas lookups DNS); corrigido com `s_bl_lookups++` antes do lookup SNI

#### Bug Menor — `force_dns` activo sem `src_cidrs` não gerava aviso
- **`layer7_blacklists.php`**: utilizador podia activar "Forçar DNS local" sem definir CIDRs de origem; o backend ignorava silenciosamente a regra (sem gerar nenhuma regra `rdr`); adicionada validação que bloqueia o formulário com mensagem de erro clara

- **PORTVERSION** bumped para 1.7.4

## [1.7.3] — 2026-03-31

### Fixed — Correcção de 3 bugs nas melhorias de Bloqueio Total

#### Bug Crítico — `rdr` rules no filter anchor
- **`layer7.inc`**: `layer7_pf_default_rules_text()` deixou de concatenar o snippet `rdr` com as filter rules — no FreeBSD PF, `rdr` só é válido na secção NAT; tê-las no filter anchor causava rejeição do ruleset inteiro (`rdr rule not allowed in filter ruleset`)
- **`layer7-pfctl`**: `write_rules()` deixou de incluir as regras `rdr` no ficheiro `/usr/local/etc/layer7/pf.conf` (filter rules); as `rdr` continuam a ser injectadas correctamente via o hook `nat_rules_needed` → `layer7_generate_nat_rules()` registado no `layer7.xml`

#### Bug Médio — Regex de fallback de interface incorrecto
- **`layer7-pfctl`** e **`layer7.inc`**: regex `^[a-z][a-z0-9]+[0-9]$` alterado para `^[a-z][a-z0-9]+$/i`; o regex anterior não cobria interfaces como `lan`, `wan`, `opt2` (último caractere não dígito); o novo cobre todos os nomes de interface válidos do pfSense

#### Bug Menor — `s_bl_sni_hits` incrementado por pfctl-add em vez de por host-match
- **`main.c`**: `s_bl_hits++` e `s_bl_sni_hits++` movidos para antes do loop de regras no SNI check, tornando o comportamento consistente com o DNS callback (onde os contadores são incrementados uma vez por domínio encontrado na blacklist, não por pfctl-add)

- **PORTVERSION** bumped para 1.7.3

## [1.7.2] — 2026-03-31

### Added — Bloqueio Total: 3 melhorias para fechar brechas de bypass DNS

#### Melhoria A — DNS Forçado via PF `rdr`
- **`bl_config.h` / `bl_config.c`**: campo `int force_dns` adicionado à `struct l7_bl_rule`; `parse_one_rule()` lê `"force_dns"` do JSON; retrocompatível (ausência = `false`)
- **`layer7-pfctl`**: nova função `generate_rdr_rules()` que lê `config.json` e `layer7.json`; `write_rules()` passa a incluir regras `rdr pass on <iface> inet proto udp/tcp from <cidr> to !127.0.0.1 port 53 -> 127.0.0.1 label "layer7:force_dns"` para cada regra com `force_dns: true` e respectivos src_cidrs
- **`layer7.inc`**: nova função `layer7_generate_rdr_rules_snippet()` que gera regras rdr dinamicamente (acesso a `get_real_interface()`); `layer7_pf_default_rules_text()` passa a ser dinâmica incluindo o snippet rdr; nova função `layer7_generate_nat_rules()` registada como `nat_rules_needed` no `layer7.xml`
- **`layer7.xml`**: adicionado `<nat_rules_needed>layer7_generate_nat_rules</nat_rules_needed>` para injectar regras rdr na secção NAT do pfSense
- **`layer7_blacklists.php`**: nova checkbox "Forçar DNS local para estes CIDRs" no formulário de regras (activada por defeito em novas regras); gravada como `"force_dns": true` no `config.json`

#### Melhoria B — Bloqueio por TLS SNI via nDPI
- **`main.c`**: include `<arpa/inet.h>` adicionado; variáveis `s_bl_dns_hits` e `s_bl_sni_hits`; nova função `ip_in_cidr(src_ip, cidr_str)` com parse manual + CIDR matching (sem dependências); nova função `bl_rule_matches_src(rule, src_ip)` para verificar se origem está no src_cidrs da regra (sem restrição = aplica a todos); check SNI blacklist em `layer7_on_classified_flow()` — após decisão de política manual — adiciona dst_ip à tabela `layer7_bld_N` correcta quando o SNI/host casa com a blacklist

#### Melhoria C — Estatísticas DNS vs SNI
- **`main.c`**: `s_bl_dns_hits` incrementado no DNS callback; `s_bl_sni_hits` incrementado no SNI callback; ambos expostos em `write_stats_json()` como `"bl_dns_hits"` e `"bl_sni_hits"`

- **PORTVERSION** bumped para 1.7.2

## [1.6.7] — 2026-03-31

### Fixed

- **SIGSEGV no daemon ao gerar stats com blacklists activas** — `blacklist.c`: `l7_blacklist_get_cat_hits()` fazia cast inválido `(const char **)bl->cats`; `bl->cats` é `char[64][48]` (array 2D), não `char**`; os primeiros 8 bytes de cada categoria eram interpretados como ponteiro → crash ao imprimir nomes de categorias via SIGUSR1
- **Bug estava oculto** desde v1.1.0 porque `s_blacklist` era sempre NULL antes de v1.6.6; a correção do parser (v1.6.6) activou o código e expôs o crash
- **Correcção**: API substituída por `l7_blacklist_get_cat_name(bl, idx)` e `l7_blacklist_get_cat_hit_count(bl, idx)` — acesso seguro por índice
- **PORTVERSION** bumped para 1.6.7

## [1.6.6] — 2026-03-31

### Fixed

- **BUG CRÍTICO: blacklists nunca carregavam no daemon** — `bl_config.c`: `match_key()` avançava o ponteiro além do `"` ao falhar comparação de chave JSON; todas as chaves após `"enabled"` (incluindo `"rules"`) eram ignoradas; `n_rules=0` → `bl_enabled: false` → tabelas PF `layer7_bld_N` sempre vazias → bloqueio por categorias web sem efeito
- **Correcção**: `match_key()` salva o ponteiro antes de avançar e restaura-o em qualquer falha de validação
- **PORTVERSION** bumped para 1.6.6

## [1.6.5] — 2026-03-31

### Fixed

- **CI smoke layer7d** — workflow Linux falhava com `Makefile:20: *** missing separator`
- **Causa raiz**: job usava `make` (GNU make no Ubuntu), mas `src/layer7d/Makefile` usa sintaxe BSD make (`.if`)
- **scripts/package/smoke-layer7d.sh** agora detecta `bmake` e prioriza BSD make; fallback para `make`
- **.github/workflows/smoke-layer7d.yml** agora instala `bmake` no runner Ubuntu
- **PORTVERSION** bumped para 1.6.5

## [1.6.4] — 2026-03-31

### Fixed

- **Auto-start após reboot** — daemon layer7d não reiniciava automaticamente após reboot do pfSense
- **rc.d**: `REQUIRE: LOGIN` alterado para `REQUIRE: DAEMON NETWORKING` (facility `LOGIN` não existe no pfSense)
- **layer7_resync()**: nova função `layer7_ensure_daemon_running()` inicia o daemon se o serviço estiver enabled mas o processo não estiver a correr (hook chamado pelo pfSense em cada boot e reload do filtro)
- **PORTVERSION** bumped para 1.6.4

## [1.6.3] — 2026-03-26

### Fixed

- **Scroll fix** — adicionadas âncoras HTML (`id` + `action`) a todos os formulários POST em todas as páginas do pacote; ao submeter um form a página volta à secção relevante em vez de saltar para o topo
- Páginas afectadas: Settings, Blacklists, Policies, Diagnostics, Reports, Status, Groups, Exceptions, Test
- **PORTVERSION** bumped para 1.6.3

## [1.6.2] — 2026-03-26

### Fixed

- **Categorias custom editáveis** — restaurado botão de editar para categorias personalizadas criadas pelo utilizador; campo ID fica readonly ao editar
- **PORTVERSION** bumped para 1.6.2

## [1.6.1] — 2026-03-26

### Changed

- **Blacklists: removida opção de editar categorias** — mantém apenas criar novas e apagar; datalist de categorias UT1 removida para evitar confusão
- **Backup completo** — export/import passa a incluir configuração de blacklists (regras, whitelist, categorias personalizadas, definições de update); permite restaurar TODAS as configurações do pacote após formatação
- **PORTVERSION** bumped para 1.6.1

## [1.6.0] — 2026-03-25

### Changed

- **Navegação consolidada: 11 → 7 abas** — removidas Grupos, Excepções, Categorias e Teste da barra principal; acessíveis via links rápidos em Políticas
- **Dashboard simplificado** — removidos bloco "Validação da configuração" e contadores PF duplicados (pertencem a Diagnósticos)
- **Definições reorganizadas em 3 blocos** — "Configuração do serviço" (com logging avançado colapsável), "Relatórios" (presets com custom toggle), "Sistema" (licença + backup + update compactos)
- **Eventos limpos** — removidos blocos duplicados "Eventos de enforcement", "Classificações nDPI" e "Dicas"; mantidos Monitor ao vivo + Filtro + Todos os logs
- **Relatórios limpos** — alertas colapsados em 1 único; removido resumo executivo em prosa (cards já mostram os dados)
- **Diagnósticos limpos** — secções PF verbose convertidas em acordeões colapsáveis; removida lista "Comandos úteis"
- **Blacklists limpos** — removidos textos introdutórios verbosos; formulário "Nova categoria" agora colapsável
- **Políticas limpos** — texto introdutório reduzido; zona "Remover política" agora colapsável; barra de links rápidos para Grupos/Excepções/Categorias/Teste
- **i18n padronizado** — "Events" → "Eventos", "Diagnostics" → "Diagnósticos"; novas chaves EN adicionadas
- **PORTVERSION** bumped para 1.6.0

## [1.5.3] — 2026-03-26

### Fixed

- **Tabelas PF persistentes após reload** — novo hook `custom_php_resync_command` materializa todas as tabelas PF obrigatórias (`layer7_block`, `layer7_block_dst`, `layer7_tagged`, `layer7_bld_N`) adicionando e removendo um IP dummy (127.0.0.254) após cada `filter_configure()`
- **Causa raiz**: no FreeBSD 15 / pfSense 2.8.1, tabelas declaradas com `table <name> persist` no ruleset existem internamente no PF mas não são listadas por `pfctl -s Tables` nem acessíveis por `pfctl -t <name> -T show` até terem pelo menos uma entrada. Isso causava falsos negativos recorrentes na página de Diagnósticos
- **Nova função `layer7_resync()`** chamada automaticamente pelo pfSense após cada reload do filtro

### Changed

- **PORTVERSION** bumped para 1.5.3

## [1.5.2] — 2026-03-26

### Fixed

- **Cursor de ingestão na limpeza de relatórios** — ao limpar todos os dados, o cursor agora é posicionado no fim do ficheiro de log actual (`/var/log/layer7d.log`) em vez de ser apagado, evitando que a função de ingestão incremental reimporte todo o histórico na mesma carga da página

### Changed

- **PORTVERSION** bumped para 1.5.2

## [1.5.1] — 2026-03-26

### Added

- **Limpar todos os dados de relatórios** — novo botão na página de Relatórios permite apagar toda a base SQLite (eventos, identity_map, daily_kpi), o histórico JSONL e o cursor de ingestão, resolvendo travamentos em servidores com milhares de páginas acumuladas
- **Confirmação obrigatória** — acção protegida com `confirm()` informando que é irreversível

### Changed

- **PORTVERSION** bumped para 1.5.1
- Traduções EN actualizadas para novas strings

## [1.5.0] — 2026-03-26

### Security

- **FIX CRITICO: blacklists no arranque** — daemon passa a carregar blacklists UT1/custom no startup (antes exigia SIGHUP manual para activar bloqueio)
- **FIX CRITICO: injecção em layer7_activate** — chaves com aspas, backslash ou control chars são rejeitadas antes de interpolar em JSON/shell
- **FIX CRITICO: password removida do seed.js** — admin password do license server agora é lida da variável `ADMIN_PASSWORD`
- **FIX ALTO: validação de octetos CIDR** — `layer7_cidr_valid()` passa a rejeitar octetos > 255 em endereços de rede
- **FIX ALTO: sanitização PF** — `except_ips` e `src_cidrs` de blacklist validados com `layer7_ipv4_valid()`/`layer7_cidr_valid()` antes de interpolar em regras PF
- **FIX ALTO: XSS/JS em confirm()** — 7 instâncias de `confirm('<?= l7_t(...) ?>')` e 3 labels Chart.js + 1 profileModal corrigidas para usar `json_encode()`

### Fixed

- **NULL safety no daemon** — `json_escape_fprint()`, `json_escape_print()` e `dst_cache_add()` protegidos contra ponteiro NULL
- **Swap de blacklists seguro** — reload falhado preserva blacklist anterior funcional em vez de destruí-la
- **Warning de categoria vazia** — log restaurado quando ambos ficheiros (UT1 base + custom overlay) falham para uma categoria
- **Whitelist normalizada** — domínios da whitelist de blacklists passam por `layer7_bl_domains_normalize()` (validação + dedup)
- **source_url validada** — apenas esquemas HTTP/HTTPS aceites na URL de download de blacklists
- **Simulação por priority** — `layer7_test.php` ordena políticas por `priority` desc (consistente com o daemon)
- **Lock atómico no update-blacklists.sh** — `mkdir` atómico substitui padrão TOCTOU `test -f` + `echo $$`
- **Numeração install.sh** — passos corrigidos de [1/5]-[3/5] para [1/6]-[3/6]
- **Help text excepções** — "max. 8" corrigido para "max. 16" (alinhado com o parser real)
- **rename() stats** — verificação de retorno com log de erro

### Changed

- **PORTVERSION** bumped para 1.5.0

### Documentation

- CORTEX.md, MANUAL-INSTALL.md e CHANGELOG actualizado para v1.5.0
- Traduções EN actualizadas para novas strings

## [1.4.17] — 2026-03-26

### Added

- **Categorias customizadas no mesmo fluxo UT1** — pagina `Blacklists` passa a permitir criar categorias locais com lista propria de dominios, sem nova tela
- **Extensao de categorias UT1 existentes** — operador pode usar o mesmo ID da categoria da Capitole e adicionar dominios proprios que nao existem no feed original
- **Mescla operacional de categorias** — seletor de categorias das regras passa a mostrar lista combinada (UT1 + custom), mantendo o modelo per-rule existente

### Changed

- **Carga de blacklists no daemon** — cada categoria ativa passa a carregar `domains` da UT1 e o overlay local em `_custom/<categoria>.domains`, suportando enriquecimento por cliente
- **Persistencia de configuracao** — `config.json` passa a guardar `category_custom`, com sincronizacao automatica para ficheiros de overlay antes do reload
- **PORTVERSION** bumped para 1.4.17

### Documentation

- **Documentacao de cliente atualizada** — `MANUAL-INSTALL.md`, `README.md` e `CORTEX.md` alinhados ao novo fluxo de categorias customizadas/UT1 e a versao 1.4.17

## [1.4.16] — 2026-03-26

### Fixed

- **PF helper sem falso negativo de tabela** — `layer7-pfctl` passa a considerar tabela pronta quando já está referenciada no filtro activo (`pfctl -sr`), mesmo sem materialização imediata em `pfctl -s Tables`
- **Diagnostics alinhado ao estado real do PF** — verificação de “tabelas obrigatórias” usa estado combinado (existência em `pfctl -s Tables` OU referência activa em regra), eliminando falso erro recorrente em `layer7_block/layer7_tagged/layer7_bld_*`
- **Mensagens operacionais mais claras** — tabelas sem entradas mas referenciadas deixam de aparecer como “não existe” e passam a estado de observação, reduzindo troubleshooting redundante
- **PORTVERSION** bumped para 1.4.16

### Documentation

- **Runbook de troubleshooting consolidado** — `pf-enforcement.md` e `MANUAL-INSTALL.md` passam a documentar explicitamente o critério combinado de tabela pronta (existente ou referenciada), com leitura operacional para evitar retrabalho de diagnóstico

## [1.4.15] — 2026-03-26

### Fixed

- **Enforcement/licença consistente** — `enforce_cfg` passa a ser recomputado por helper único após parse e validação de licença (startup + recheck), eliminando estado preso em monitor com licença válida
- **Parser resiliente à ordem do JSON** — `enabled`, `mode` e `log_level` deixam de depender da posição relativa a `policies`, alinhando daemon e GUI
- **Robustez PF com visibilidade real** — `layer7-pfctl` e `rc.d` deixam de mascarar falhas críticas de criação/validação de tabelas e registram estado degradado de forma explícita
- **Diagnostics sem falso verde** — “Enforcement real” agora exige regras `layer7:block:*` ativas + tabelas obrigatórias presentes, distinguindo cenário apenas anti-bypass
- **Conformidade operacional/documental** — `MANUAL-INSTALL` alinhado ao `rc.d` real (`service layer7d reload`), com redução de exposição operacional e flush dinâmico de tabelas `layer7_bld_*`
- **Consistência GUI/i18n** — endpoint AJAX alinhado ao bootstrap padrão (`guiconfig.inc`) e dicionário EN sem duplicidade de chave
- **PORTVERSION** bumped para 1.4.15

## [1.4.14] — 2026-03-25

### Fixed

- **Autorreparo no daemon** — falhas de `pfctl -T add` por tabela ausente agora disparam recuperação controlada (`layer7-pfctl ensure` + fallback opcional por `rules.debug`) com retry único, cobrindo caminhos DNS e nDPI
- **Reload consistente (SIGHUP)** — após recarregar a configuração, o daemon valida tabelas base (`layer7_block`, `layer7_block_dst`) e tenta recuperação automática quando necessário
- **Helper PF sem falso sucesso** — `layer7-pfctl ensure` passa a validar tabelas obrigatórias no estado final e retorna erro real se ainda estiverem ausentes
- **Diagnostics fiel ao estado real** — novo estado de “enforcement real” exige simultaneamente regra Layer7 ativa (`pfctl -sr`) e tabelas PF obrigatórias presentes
- **PORTVERSION** bumped para 1.4.14

## [1.4.13] — 2026-03-25

### Changed

- **GUI administrativa expandida** — as páginas `Politicas`, `Grupos`, `Events`, `Diagnostics` e `Blacklist` passam a usar blocos visuais separados com cabeçalhos fortes, seguindo o padrão administrativo do pfSense
- **Leitura operacional mais clara** — filtros, listagens, formulários e áreas de acção ficam segmentados por contexto, reduzindo o efeito de painel único nas telas maiores
- **PT/EN preservado** — a reorganização visual reutiliza as legendas existentes e mantém o selector bilingue sem alteração funcional
- **Sem mudanças funcionais** — handlers POST, persistência, licenciamento, relatórios, upgrade e enforcement continuam com o mesmo comportamento
- **PORTVERSION** bumped para 1.4.13

## [1.4.12] — 2026-03-25

### Changed

- **GUI Settings em blocos** — a página `Definicoes` passa a seguir uma organização por blocos com cabeçalhos fortes, aproximando-se do padrão visual do pfSense
- **Separação visual por área** — definições gerais, logging/debug, captura/interfaces, licença, backup/restore, relatórios e actualização agora ficam em blocos distintos
- **Bilingue preservado** — novas legendas visuais traduzidas para inglês, mantendo o selector PT/EN funcional
- **Sem mudanças funcionais** — handlers POST, persistência, licenciamento, relatórios e upgrade permanecem com o mesmo comportamento
- **PORTVERSION** bumped para 1.4.12

## [1.4.11] — 2026-03-25

### Changed

- **Controle de versão** — nova release patch para manter o histórico após a entrega funcional da v1.4.10
- **Documentação operacional** — `MANUAL-INSTALL.md`, `README.md`, `release-body.md` e scripts de release sincronizados com a nova versão pública
- **Links públicos** — comandos, URLs do `.pkg` e exemplos com `--version` passam a apontar para `v1.4.11`
- **PORTVERSION** bumped para 1.4.11

## [1.4.10] — 2026-03-25

### Changed

- **Relatorios estilo NGFW** — histórico executivo e log detalhado passam a ser tratados separadamente no appliance
- **Log detalhado opcional** — operador pode activar/desactivar a ingestão detalhada em SQLite
- **Escopo por interface** — log detalhado pode ser limitado a uma ou mais interfaces
- **Retenção separada** — histórico executivo e log detalhado passam a ter janelas próprias de retenção
- **Paginação compacta** — a tela de eventos detalhados deixa de renderizar milhares de páginas no HTML
- **Contexto de interface nos logs** — eventos `dns_query`, `dns_block` e `enforce_*` passam a incluir `iface=` para melhorar pesquisa e filtragem
- **Settings mais seguro** — guardar apenas a seção de relatórios preserva correctamente as demais definições globais
- **PORTVERSION** bumped para 1.4.10

## [1.4.9] — 2026-03-25

### Changed

- **Canal público de distribuição** — `install.sh`, `uninstall.sh`, documentação operacional e release notes passam a usar o repositório público `pablomichelin/Layer7`
- **Actualização via GUI** — a página Definições passa a consultar a última release e o `.pkg` no novo repositório público, preservando o fluxo actual de upgrade
- **PORTVERSION** bumped para 1.4.9

## [1.4.2] — 2026-03-24

### Fix criação robusta de tabelas PF

- **Causa raiz:** `pfctl -t TABLE -T add` não cria tabelas no FreeBSD se não
  estiverem declaradas no ruleset carregado; `ensure_table()` falhava
  silenciosamente; `filter_configure()` pode ser assíncrono no pfSense CE
- **layer7-pfctl ensure:** `write_rules()` agora executa antes de `ensure_table`;
  nova verificação `tables_missing()` com fallback `pfctl -f /tmp/rules.debug`
- **Reparar tabelas PF:** handler na página Diagnósticos agora chama ensure
  primeiro, depois `filter_configure()`, espera 800ms, verifica tabelas, e se
  ainda em falta força `pfctl -f /tmp/rules.debug`; resultado reflecte estado real
- **layer7_bl_apply():** mesma lógica robusta (ensure→filter_configure→verify→force)
- **install.sh:** usa `layer7-pfctl ensure` + `pfctl -f rules.debug` em vez de
  tentativas individuais `pfctl -T add` que falhavam

## [1.0.0] — 2026-03-23

### Release V1 Comercial

Primeira versao estavel e completa do Layer7 para pfSense CE. Inclui todas as
funcionalidades planeadas para a V1 comercial.

### Funcionalidades incluidas na V1

- **Classificacao L7 em tempo real** — ~350 apps/protocolos via nDPI
- **Politicas granulares** — por interface, IP/CIDR, app nDPI, categoria, hostname, grupo de dispositivos
- **Enforcement PF** — bloqueio por destino (DNS + nDPI) com tabela `layer7_block_dst`, bloqueio por origem com `layer7_block`
- **Anti-bypass DNS** — bloqueio DoT/DoQ (porta 853), deteccao nDPI DoH, NXDOMAIN via Unbound para dominios de bypass
- **Perfis de servico** — 15 perfis built-in (YouTube, Facebook, Instagram, TikTok, WhatsApp, Twitter/X, LinkedIn, Netflix, Spotify, Twitch, Redes Sociais, Streaming, Jogos, VPN/Proxy, AI Tools) com criacao de politica por 1 clique
- **Pagina de categorias nDPI** — todas as apps organizadas por categoria com pesquisa
- **Dashboard operacional** — contadores em tempo real, top 10 apps bloqueadas, top 10 clientes
- **Agendamento por horario** — politicas com dias da semana e faixa horaria (suporte overnight)
- **Grupos de dispositivos** — grupos nomeados (ex: "Funcionarios") com CIDRs/IPs, reutilizaveis em politicas
- **Bloqueio QUIC selectivo** — toggle para forcar fallback TCP/TLS e melhorar visibilidade SNI
- **Teste de politica** — simulacao completa na GUI com veredicto visual
- **Backup e restore** — export/import de configuracao completa em JSON
- **Licenciamento Ed25519** — fingerprint de hardware, verificacao offline, grace period 14 dias, CLI de activacao
- **Actualizacao via GUI** — verificacao e instalacao directa do GitHub Releases
- **GUI completa** — 10 paginas (Estado, Definicoes, Politicas, Grupos, Categorias, Teste, Excecoes, Events, Diagnostics)
- **Fleet management** — scripts para 50+ firewalls (update, protos sync)
- **Logs locais + syslog remoto** — `/var/log/layer7d.log` + UDP syslog configuravel
- **EULA proprietaria** — licenca comercial com proteccao por chave

### Changed
- **PORTVERSION** bumped para 1.0.0
- **install.sh** — versao default actualizada para 1.0.0
- **CORTEX.md** — actualizado para v1.0
- **README.md** — actualizado com funcionalidades v1.0
- **blocking-master-plan.md** — todas as fases marcadas como concluidas
- Removido `docs/09-blocking/phase-a-option1-package-rules-plan.md` (obsoleto)
- Removido `docs/09-blocking/plano-v1-comercial.md` (plano concluido)
- **Branding Systemup** — propriedade Systemup Solucao em Tecnologia (www.systemup.inf.br) em todas as 9 paginas GUI (rodape com hyperlink), LICENSE/EULA, README, Makefile, info.xml e install.sh
- **Desenvolvedor principal** — Pablo Michelin registado em LICENSE, README e GitHub Release

## [0.9.0] — 2026-03-23

### Added
- **Fingerprint de hardware** — funcao `layer7_hw_fingerprint()` em `license.c` que gera ID unico a partir de `kern.hostuuid` + MAC da primeira interface via SHA256.
- **Verificacao de licenca Ed25519** — ficheiro `/usr/local/etc/layer7.lic` com payload JSON assinado com Ed25519. Chave publica embutida no binario. Verificacao via OpenSSL EVP API (`libcrypto`).
- **Proteccao por licenca no daemon** — sem licenca valida o daemon opera apenas em modo monitor-only (sem enforce/block). Verificacao no arranque e periodica (cada 1h). Grace period de 14 dias apos expiracao.
- **CLI `--fingerprint`** — mostra o hardware ID da maquina actual para facilitar geracao de licencas.
- **CLI `--activate KEY [URL]`** — tenta activacao online enviando fingerprint + chave ao servidor de licencas. Guarda `.lic` recebido. Pronto para uso quando servidor estiver disponivel.
- **Seccao de licenca na GUI** — pagina Definicoes mostra estado da licenca (valida/expirada/grace/dev mode), hardware ID, cliente, data de expiracao e dias restantes.
- **Estado da licenca no stats JSON** — campos `license_valid`, `license_expired`, `license_grace`, `license_dev_mode`, `license_days_left`, `license_customer`, `license_expiry`, `license_hardware_id` exportados em `/tmp/layer7-stats.json`.
- **Script de geracao de licencas** — `scripts/license/generate-license.py` com comandos `keygen` (gera par Ed25519), `sign` (cria `.lic` assinado) e `c-pubkey` (mostra chave publica como array C).
- **EULA proprietaria** — licenca BSD-2-Clause substituida por End-User License Agreement. Software requer chave de licenca para funcionalidade completa.

## [0.8.0] — 2026-03-23

### Added
- **Pagina de teste de politica** — nova pagina "Teste" na GUI onde o utilizador introduz um dominio/IP de destino, IP de origem, app nDPI e categoria nDPI, e ve qual politica casaria, qual a accao e o motivo. Simula excepcoes, groups, schedule e matching de hosts/subdominios em PHP.
- **Resolucao DNS na pagina de teste** — dominios sao resolvidos automaticamente e os IPs resolvidos mostrados no resultado.
- **Veredicto visual** — resultado do teste com indicador colorido (block=vermelho, allow=verde, monitor=azul) e tabela detalhada de cada politica avaliada.
- **Backup e restore de configuracao** — botoes "Exportar configuracao" e "Importar configuracao" na pagina Definicoes. Export gera ficheiro JSON com definicoes, politicas, excepcoes e grupos. Import valida o JSON, substitui a configuracao e envia SIGHUP + filter_configure.
- **GUI passa a ter 10 paginas** — Estado, Definicoes, Politicas, Grupos, Categorias, Teste, Excecoes, Events, Diagnostics.

## [0.7.0] — 2026-03-23

### Added
- **Grupos de dispositivos** — nova seccao `groups[]` no JSON config para criar grupos nomeados de dispositivos (ex.: "Funcionarios", "Visitantes") com CIDRs e/ou IPs individuais.
- **Referencia a grupos nas politicas** — campo `match.groups` nas politicas permite seleccionar grupos em vez de digitar CIDRs manualmente. O daemon expande os grupos para CIDRs/IPs no parse.
- **Nova pagina GUI "Grupos"** — CRUD completo para criar, editar e remover grupos de dispositivos. Proteccao contra remocao de grupo em uso por politica.
- **Dropdown de grupos nos formularios de politicas** — seleccao de grupos disponivel nos formularios de adicionar, editar e perfis rapidos.
- **Visualizacao de grupos na politica** — "Ver listas" e resumo de correspondencia mostram os grupos associados.
- **Bloqueio QUIC selectivo** — toggle "Bloquear QUIC (UDP 443)" na pagina Definicoes. Quando activo, adiciona regra PF `block drop quick proto udp to port 443` que forca apps a usar HTTPS (TCP 443) onde o SNI e visivel ao nDPI. Melhora eficacia do bloqueio por DNS/SNI. Regra PF injectada dinamicamente via `layer7_generate_rules()`.
- **GUI passa a ter 9 paginas** — Estado, Definicoes, Politicas, Grupos, Categorias, Excecoes, Events, Diagnostics.

## [0.3.2] — 2026-03-23

### Added
- **Actualizacao via GUI** — botao "Verificar actualizacao" na pagina Definicoes que consulta o GitHub Releases e permite instalar a versao mais recente com um clique. O daemon e parado/reiniciado automaticamente e todas as configuracoes sao preservadas.

## [0.3.1] — 2026-03-23

### Added
- **Anti-bypass DNS multi-camada** — estrategia para impedir que dispositivos contornem bloqueio via DNS cifrado (DoH/DoT/DoQ) ou iCloud Private Relay.
- **Regras PF anti-DoT/DoQ** — bloqueio automatico de TCP/UDP porta 853 no snippet do pacote, cortando DNS over TLS e DNS over QUIC.
- **Politica nDPI anti-bypass** — politica built-in `anti-bypass-dns` no sample config que bloqueia fluxos classificados como `DoH_DoT` e `iCloudPrivateRelay` pelo nDPI.
- **Script Unbound anti-DoH** — `/usr/local/libexec/layer7-unbound-anti-doh` configura NXDOMAIN para dominios de bypass DNS conhecidos (Apple Private Relay, Firefox canary, resolvers DoH publicos). iOS desativa Private Relay automaticamente quando `mask.icloud.com` retorna NXDOMAIN.
- **Instalacao automatica** — `install.sh` agora executa o script anti-DoH automaticamente durante a instalacao.

## [0.3.0] — 2026-03-23

### Added
- **Bloqueio por destino (sites/apps)** — o daemon agora adiciona IPs de DESTINO a `layer7_block_dst` em vez de quarentenar o cliente. Sites/apps bloqueados ficam inacessiveis; o resto do trafego funciona normalmente.
- **Bloqueio DNS** — daemon observa respostas DNS e bloqueia automaticamente IPs de dominios que casam com politicas `block` (campo `Sites/hosts`).
- **Bloqueio nDPI por destino** — classificacoes nDPI com `action=block` adicionam o IP de destino do fluxo a `layer7_block_dst`.
- **Expiracao automatica** — cache com TTL (minimo 5 min) + sweep periodico para remover IPs expirados da tabela de destino.
- **Nova tabela PF** — `layer7_block_dst` com regras `block drop quick inet to <layer7_block_dst>` no snippet do pacote.
- **Diagnostics actualizado** — GUI mostra contadores e entradas da tabela `layer7_block_dst`.

## [0.2.7] — 2026-03-23

### Added
- **Enforcement PF integrado ao filtro pfSense** — o XML do pacote agora declara `<filter_rules_needed>layer7_generate_rules</filter_rules_needed>`, fazendo o pfSense CE incluir automaticamente as regras de bloqueio do Layer7 no ruleset ativo via `discover_pkg_rules()` durante cada `filter reload`.
- **Bloqueio operacional por origem** — IPs em `<layer7_block>` passam a ser bloqueados automaticamente sem necessidade de regra PF manual externa.

## Historico pre-release (consolidado na v1.0.0)

### Added
- **Plano mestre de bloqueio total** — nova trilha documental em `docs/09-blocking/blocking-master-plan.md`, cobrindo arquitetura, fases, riscos, testes e rollout para bloquear aplicações, sites, serviços e funções no pfSense CE.
- **Sites/hosts manuais nas políticas** — novo campo `match.hosts[]` na GUI e no daemon; regras agora podem casar por hostname/domínio observado nos eventos, com suporte a subdomínios.
- **Seleção em massa na GUI** — políticas e exceções passam a ter botões para selecionar tudo/limpar interfaces; listas de apps e categorias nDPI ganham seleção dos itens visíveis após o filtro.
- **Visualização das listas existentes** — políticas ganham ação `Ver listas` para inspeccionar todos os apps, categorias, sites, IPs e CIDRs já gravados sem entrar direto em edição.
- **Hostname e destino nos eventos** — `flow_decide` passa a incluir `dst=` e `host=`; o `host=` é inferido por correlação de respostas DNS observadas na captura, quando disponíveis.
- **Monitor ao vivo na GUI** — a aba `Events` agora possui um painel com auto-refresh dos ultimos eventos do `layer7d`, com suporte a pausa, refresh manual e reaproveitamento do filtro atual.
- **Log local do daemon** — `layer7d` agora grava eventos em `/var/log/layer7d.log`; GUI `Events` e `Diagnostics` passam a ler esse arquivo diretamente, eliminando dependência do syslog do pfSense para observabilidade.
- **Labels amigaveis de interface na GUI** — `layer7_get_pfsense_interfaces()` agora prioriza a descricao configurada em `config['interfaces'][ifid]['descr']`, com fallback seguro; Settings, Policies e Exceptions deixam de exibir `OPT1/OPT2/...` quando houver descricoes customizadas.
- **Empacotamento autocontido do nDPI** — o build do `layer7d` no port agora usa `/usr/local/lib/libndpi.a` e falha se a biblioteca estática não existir no builder, evitando pacote que peça `libndpi.so` adicional no pfSense.
- **Validação de release** — `scripts/release/update-ndpi.sh` agora aborta se o binário staged ainda depender de `libndpi.so` em runtime.
- **Guia Completo Layer7** (`docs/tutorial/guia-completo-layer7.md`) — tutorial com 18 secções: instalação, configuração, todos os menus da GUI, formato JSON, exemplos práticos de políticas, CLI do daemon, sinais, protocolos customizados, gestão de frota (fleet), troubleshooting e glossário.

- **Motor Multi-Interface (2026-03-18):**
  - GUI Settings: checkboxes dinâmicos de interfaces pfSense (substituiu campo CSV)
  - `layer7d --list-protos`: enumera todos os protocolos e categorias nDPI em JSON
  - GUI Policies: multi-select com pesquisa para apps e categorias nDPI (populados por `--list-protos`)
  - Políticas: campo `interfaces[]` para regras por interface (vazio = todas)
  - Políticas: campo `match.src_hosts[]` e `match.src_cidrs[]` para filtro granular por IP de origem
  - Exceções: suporte a múltiplos hosts (`hosts[]`) e CIDRs (`cidrs[]`) por exceção
  - Exceções: campo `interfaces[]` para limitar a interfaces específicas
  - Callback de captura `layer7_flow_cb` agora inclui nome da interface
  - `layer7_flow_decide` filtra por interface, IP de origem e CIDR
  - Compatibilidade retroactiva: campos antigos `host`/`cidr` continuam a funcionar
  - Helpers PHP: `layer7_ndpi_list()`, `layer7_get_pfsense_interfaces()`, `layer7_parse_ip_textarea()`, `layer7_parse_cidr_textarea()`

- **Enforce end-to-end validado (2026-03-23)** — pipeline nDPI → policy engine → pfctl comprovado em pfSense CE real:
  - `pf_add_ok=7`, zero falhas, 6 IPs adicionados à tabela `layer7_tagged`
  - Protocolos detectados: TuyaLP (IoT), SSDP (System), MDNS (Network)
  - Exceções respeitadas: IPs .195 e .129 não foram afetados
  - CLI `-e` validou: BitTorrent→block, HTTP→monitor, IP excecionado→allow
- **Daemon: logging diferenciado** — block/tag decisions logadas a `LOG_NOTICE` (sempre visíveis); allow/monitor a `LOG_DEBUG` (sem poluir logs)
- **Daemon: safeguard monitor mode** — `layer7_on_classified_flow` verifica modo global antes de chamar `pfctl`; em modo monitor, decisão logada mas nunca executada.
- **Scripts lab** — `sync-to-builder.py` (SFTP sync), `transfer-and-install.py` (builder→pfSense), scripts de teste enforce
- **Deploy lab via GitHub Releases** — `scripts/release/deployz.sh` (build + publish), `scripts/release/install-lab.sh.template` (instalação no pfSense com `fetch + sh`), `scripts/release/README.md`, `docs/04-package/deploy-github-lab.md`.
- **Rollback doc** — `docs/05-runbooks/rollback.md` (procedimento completo com limpeza manual).
- **Release notes template** — `docs/06-releases/release-notes-template.md`.
- **Checklist mestre alinhado** — `14-CHECKLIST-MESTRE.md` atualizado para refletir o estado real do projeto: fases 0, 3, 5, 7, 8 marcadas como completas.
- **Matriz de testes** — `docs/tests/test-matrix.md` com 58 testes em 10 categorias (47 OK, 11 pendentes no appliance).
- **Smoke test melhorado** — `smoke-layer7d.sh` com cenários adicionais: exception por host (whitelist IP), exception por CIDR.
- **Validação lab completa (2026-03-22)** — 57/58 testes OK no pfSense CE 2.8.1-dev (FreeBSD 15.0-CURRENT):
  - Instalação via GitHub Release (`fetch` + `pkg add -f`) OK
  - Daemon start/stop/SIGUSR1/SIGHUP OK
  - pfctl enforce: dry-run, real add, show, delete OK
  - Whitelist: exception host impede enforce OK
  - GUI: 6 páginas HTTP 200 OK
  - Rollback: `pkg delete` remove pacote, preserva config, dashboard OK
  - Reinstalação do `.pkg` do GitHub Release OK

- **Syslog remoto validado (2026-03-22)** — `nc -ul 5514` + daemon SIGUSR1, mensagens BSD syslog recebidas.
- **nDPI integrado (0.1.0-alpha1, 2026-03-22):**
  - Novo módulo `capture.c`/`capture.h`: pcap live capture + nDPI flow classification
  - Tabela de fluxos hash (65536 slots, linear probing, expiração 120s)
  - `main.c`: loop de captura integrado, `layer7_on_classified_flow` conectado ao nDPI
  - `config_parse.c/h`: parsing de `interfaces[]` do JSON
  - Makefile: auto-detect nDPI (`HAVE_NDPI`), compilação condicional, `NDPI=0` para CI
  - Port Makefile: PORTVERSION 0.1.0.a1, link com libndpi + libpcap
  - Validado no pfSense: `cap_pkts=360`, `cap_classified=8`, captura estável em `em0`
  - Suporte a custom protocols file (`/usr/local/etc/layer7-protos.txt`) para regras por host/porta/IP sem recompilar
- **Estratégia de atualização nDPI** — `docs/core/ndpi-update-strategy.md`: comparação com SquidGuard, fluxo de atualização, cadência recomendada, roadmap
- **Script update-ndpi.sh** — `scripts/release/update-ndpi.sh`: atualiza nDPI no builder e reconstrói pacote
- **Fleet update** — `scripts/release/fleet-update.sh`: distribui `.pkg` para N firewalls via SSH (compila 1x, instala em todos)
- **Fleet protos sync** — `scripts/release/fleet-protos-sync.sh`: sincroniza `protos.txt` para N firewalls + SIGHUP (sem recompilação)
- **Resolução automática de interfaces** — GUI Settings converte nomes pfSense (`lan`, `opt1`) para device real (`em0`, `igb1`) ao gravar JSON via `convert_friendly_interface_to_real_interface_name()`; exibição reversa ao carregar
- **Custom protos sample** — `layer7-protos.txt.sample` incluído no pacote com exemplos de regras por host/porta/IP/nBPF
- **Release notes V1** — `docs/06-releases/release-notes-v0.1.0.md` (draft)
- **GUI Diagnostics melhorado** — stats live (SIGUSR1 button), PF tables (layer7_block, layer7_tagged com contagem e entradas), custom protos status, interfaces configuradas, SIGHUP button, logs recentes do layer7d
- **GUI Events melhorado** — filtro de texto, seções separadas para eventos de enforcement e classificações nDPI, todos os logs do layer7d com filtro
- **GUI Status melhorado** — resumo operacional com modo (badge colorido), interfaces, políticas ativas/block count, estado do daemon
- **protos_file configurável** — campo `protos_file` no JSON config (`config_parse.c/h`), passado a `layer7_capture_open`, mostrado em `layer7d -t`
- **pkg-install melhorado** — copia `layer7-protos.txt.sample` para `layer7-protos.txt` se não existir
- **Port Makefile** — PORTVERSION bumped para 0.1.0, instalação de `layer7-protos.txt.sample`

### Changed
- **CORTEX.md** — nDPI integrado, Fase 10 em progresso, gates atualizados, estratégia de atualização nDPI documentada, fleet management.
- **README.md** — seção Distribuição com link para deploy lab via GitHub Releases.
- **14-CHECKLIST-MESTRE.md** — fases 6 e 9 fechadas com evidência de lab.
- **docs/tests/test-matrix.md** — 58/58 testes OK.

### Previously added
- **GUI save no appliance** - CSRF customizado removido de `Settings`, `Policies` e `Exceptions`; `pkg-install` passa a criar `layer7.json` a partir do sample e aplicar `www:wheel` + `0664`; save real em `Settings` validado no pfSense com persistencia em `/usr/local/etc/layer7.json`.
- **Guia Windows** — `docs/08-lab/guia-windows.md` (CI, WSL, lab); **`scripts/package/check-port-files.ps1`** (PowerShell, equivalente ao `.sh`); referência em `docs/08-lab/README.md` e `validacao-lab.md`.
- **Quick-start lab** — `docs/08-lab/quick-start-lab.md` (fluxo encadeado builder→pfSense→validação); referência em `docs/08-lab/README.md`.
- **main.c** — comentário TODO(Fase 13) no loop indicando ponto de integração nDPI→`layer7_on_classified_flow`.
- **BUILDER.md** — port pronto para `make package`; referências validacao-lab e quick-start.
- **CI** — job `check-windows` em `smoke-layer7d.yml` (PowerShell `check-port-files.ps1`).
- **docs/05-runbooks/README.md** — links para validacao-lab e quick-start-lab.
- **docs/README.md** — entrada `04-package` no índice.
- **Decisão documentada:** instalação no pfSense apenas quando o pacote estiver totalmente completo (`00-LEIA-ME-PRIMEIRO.md` regra 8, `CORTEX.md` decisões congeladas).
- **README** — estado e estrutura atualizados (daemon, pacote, GUI, CI; lab pendente).
- **`scripts/package/check-port-files.sh`** — valida **`pkg-plist`** contra **`files/`**; integrado no workflow CI + **`validacao-lab.md`** (§3, troubleshooting).
- **GitHub Actions** — [`.github/workflows/smoke-layer7d.yml`](../../.github/workflows/smoke-layer7d.yml) (Ubuntu + `smoke-layer7d.sh`); **`docs/tests/README.md`**; badge no **`README.md`**.
- **`smoke-layer7d.sh`** passa a compilar via **`src/layer7d/Makefile`** (`OUT`, **`VSTR_DIR`**); Makefile valida **`version.str`** e uma única linha **`$(CC)`** para dev + smoke.
- **`src/layer7d/Makefile`** — `make` / `make check` / `make clean` no builder (flags alinhadas ao port); **`.gitignore`** — binário `src/layer7d/layer7d`; **`builder-freebsd.md`** + **`layer7d/README.md`** — instruções.
- **Docs lab:** `lab-topology.md` — trilha pós-topologia (smoke, `validacao-lab`, snapshots, PoC); **`lab-inventory.template.md`** — campos de validação pacote; **`docs/08-lab/README.md`** — link **`validacao-lab`**. **Daemon README** — `layer7_on_classified_flow`, quatro `.c`, enforcement alinhado a `pf-enforcement.md`.
- **Smoke / lab:** `smoke-layer7d.sh` valida cenário **monitor** (sem add PF) e **enforce** (`grep dry-run pfctl`); **`validacao-lab.md` §6c** — procedimento **`layer7d -e`** / **`-n`** no appliance.
- **0.0.31:** **Settings** — editar **`interfaces[]`** (CSV validado, máx. 8); **`layer7_parse_interfaces_csv()`** em `layer7.inc`; **PORTVERSION 0.0.31**.
- **0.0.30:** **Settings** — bloco **Interfaces (só leitura)** (`interfaces[]` do JSON); nota nDPI; **PORTVERSION 0.0.30**.
- **0.0.29:** **`layer7_daemon_version()`** em `layer7.inc`; página **Estado** mostra `layer7d -V`; Diagnostics reutiliza o helper.
- **0.0.28:** **`layer7d -V`** e **`version.str`** (build port = PORTVERSION); **`layer7d -t`** imprime `layer7d_version`; syslog **`daemon_start version=…`** e SIGUSR1 com **`ver=`**; Diagnostics mostra `layer7d -V`; smoke com include temporário; **PORTVERSION 0.0.28**.
- **0.0.27:** Validação **syslog remoto**: host = IPv4 ou hostname seguro (`layer7_syslog_remote_host_valid` em `layer7.inc`); doc **`docs/package/gui-validation.md`**.
- **0.0.26:** **Exceptions — editar** na GUI (`?edit=N`): host **ou** CIDR, prioridade, ação, ativa; **id** só via JSON; redirect após gravar.
- **0.0.25:** **Policies — editar** na GUI (`?edit=N`): nome, prioridade, ação, apps/cat CSV, `tag_table`, ativa; **id** só via JSON; após gravar redireciona à lista.
- **0.0.24:** **Exceptions — remover** na GUI (dropdown + confirmação, CSRF, SIGHUP).
- **0.0.23:** **Policies — remover** na GUI (dropdown + confirmação, CSRF, SIGHUP); link **Events** na página **Settings**.
- **0.0.22:** GUI **Events** em `layer7.xml` (tab), **`pkg-plist`**, página `layer7_events.php` (já no repo); README do port.
- **0.0.21:** **`layer7_pf_enforce_decision(dec, ip, dry_run)`**; **`layer7d -e IP APP [CAT]`** (lab) e **`-n`** (dry sem pfctl); **`layer7_on_classified_flow`** para integração nDPI; smoke **`layer7-enforce-smoke.json`**; docs `pf-enforcement` + `layer7d/README`.
- **0.0.20:** **`debug_minutes`** (0–720): após SIGHUP/reload, daemon usa **LOG_DEBUG** durante N minutos; `effective_ll()`; campo em **Settings**; parser `config_parse`.
- **0.0.19:** **Syslog remoto:** `layer7d` duplica logs por UDP (RFC 3164) para `syslog_remote_host`:`syslog_remote_port`; parser JSON; **Settings** (checkbox + host + porta); `layer7d -t` mostra campos; `config-model` + `docs/10-logging` atualizados.
- **0.0.18:** Página GUI **Diagnostics** (`layer7_diagnostics.php`): estado do serviço (PID), comandos SIGHUP/SIGUSR1, onde ver logs, comandos úteis (service, sysrc); tab + links nas outras páginas.
- **0.0.17:** **docs/10-logging/README.md** — formato de logs (destino syslog, log_level, mensagens atuais, syslog remoto planeado, ligação a event-model).
- **0.0.16:** GUI **adicionar exceção** (`layer7_exceptions.php`): id, host (IPv4) ou CIDR, prioridade, ação, ativa; limite 16; helpers `layer7_ipv4_valid` / `layer7_cidr_valid` em `layer7.inc`.
- **0.0.15:** **`runtime_pf_add(table, ip)`** em `main.c` — chama `layer7_pf_exec_table_add`, incrementa `pf_add_ok`/`pf_add_fail`, loga falha; ponto de chamada único para o fluxo pós-nDPI (ainda não invocada).
- **0.0.14:** **Adicionar política** na GUI (`layer7_policies.php`): id, nome, prioridade, ação (monitor/allow/block/tag), apps/categorias nDPI (CSV), `tag_table` se tag; limites alinhados ao daemon (24 regras, etc.). Helpers em `layer7.inc`.
- **0.0.13:** GUI **`layer7_exceptions.php`** — lista `exceptions[]`, ativar/desativar, gravar JSON + SIGHUP; tab **Exceptions** em `layer7.xml`; `pkg-plist`; links nas outras páginas Layer7.
- **0.0.12:** `enforce.c` — **`layer7_pf_exec_table_add`** / **`layer7_pf_exec_table_delete`** (`fork`+`execv` `/sbin/pfctl`, sem shell); loop do daemon ainda não invoca (pendente nDPI). `layer7d -t` menciona `pf_exec`.
- **0.0.11:** `layer7d` — contadores **SIGUSR1** (`reload_ok`, `snapshot_fail`, `sighup`, `usr1`, `loop_ticks`, `have_parse`, `pf_add_ok`/`pf_add_fail` reservados); contagem de falhas ao falhar parse de policies/exceptions no reload; **aviso degraded** no arranque se ficheiro existe mas snapshot não carrega; **log periódico** (~1 h) `periodic_state` quando `enabled` ativo.
- Roadmap estendido: **Fases 13–22** (V2+) em `03-ROADMAP-E-FASES.md`; checklist em `14-CHECKLIST-MESTRE.md`; tabela Blocos 13–22 em `07-PLANO-DE-IMPLEMENTACAO-PASSO-A-PASSO.md`; ponte em `00-LEIA-ME-PRIMEIRO.md` e `CORTEX.md`.
- **0.0.10:** `enforce.c` — nomes de tabela PF, `pfctl -t … -T add <ip>`; parse **`tag_table`**; campo **`pf_table`** na decisão; daemon guarda policies/exceptions após reload; **SIGUSR1** → syslog (reloads, ticks, N políticas/exceções); **`layer7d -t`** mostra `pfctl_suggest` quando enforce+block/tag; doc `docs/05-daemon/pf-enforcement.md`.
- **0.0.9:** `exceptions[]` no motor — `host` (IPv4) e `cidr` `a.b.c.d/nn`; `match.ndpi_category[]` (AND com `ndpi_app`); API `layer7_flow_decide()`; `layer7d -t` lista exceções e dry-run com src/app/cat; sample JSON com exceções + política Web.
- **0.0.8:** `policy.c` / `policy.h` — parse de `policies[]` (id, enabled, action, priority, `match.ndpi_app`), ordenação (prioridade desc, id), decisão first-match, reason codes, `would_enforce` para block/tag em modo enforce; **`layer7d -t`** imprime políticas e dry-run (BitTorrent / HTTP / não classificado). Port Makefile e smoke compilam `policy.c` (`-I` para `src/common`).
- `scripts/package/README.md`; `smoke-layer7d.sh` verifica presença de `cc`; `validacao-lab.md` — localização do `.txz`, troubleshooting de build, notas serviço/`daemon_start`.
- **0.0.7:** `layer7_policies.php` — ativar/desativar políticas por linha; `layer7.inc` partilhado (load/save/CSRF); `layer7d` respeita `log_level` (L7_NOTE/L7_INFO/L7_DBG).
- **0.0.6:** `layer7_settings.php`, tabs Settings, CSRF, SIGHUP.
- **0.0.5:** `log_level` no parser; idle se `enabled=false`; `layer7_status.php` com `layer7d -t`.
- **0.0.4:** `config_parse.c` — `enabled`/`mode`; `layer7d -t`; SIGHUP; `smoke-layer7d.sh`.

### Added (anterior)
- Scaffold do port `package/pfSense-pkg-layer7/` (Makefile, plist, XML, PHP informativo, rc.d, sample JSON, hooks pkg) — **código no repo; lab não validado**.
- `src/layer7d/main.c` (daemon mínimo: syslog, stat em path de config, loop).
- `docs/04-package/package-skeleton.md`, `docs/04-package/validacao-lab.md`, `docs/05-daemon/README.md`.
- `package/pfSense-pkg-layer7/LICENSE` para build do port isolado.

### Changed
- **Roadmap e índice de documentação** — passam a apontar explicitamente para a trilha complementar de bloqueio total (`docs/09-blocking/`).
- **CORTEX** — passa a registrar explicitamente o estado real do enforcement atual e o próximo bloco recomendado: enforcement PF automático do pacote.
- Documentação alinhada: nada de build/install/GUI marcado como validado sem evidência de lab.
- Port compila `layer7d` em C (`PORTVERSION` conforme Makefile).

### Fixed (código)
- `rc.d/layer7d` usa `daemon(8)` para arranque em background.

## [0.0.1] - 2026-03-17

### Added
- Documentação-mestre na raiz (`00-`…`16-`, `AGENTS.md`, `CORTEX.md`) e primeiro push ao GitHub.
