# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

## [1.8.11_64] - 2026-08-04 — Materializar tabelas VIP/excepções no PF live (BG-075)

### Fixed

- **VIP/`L7ALLOW` inoperante no PF live:** `table <layer7_exc_allow_N> persist
  { … }` aparecia em `/tmp/rules.debug` e no match `exc-allow`, mas
  `pfctl -t layer7_exc_allow_N -T show` respondia **Table does not exist**.
  Sem a marca `L7ALLOW`, o VIP caía no `block drop` global `layer7_block_dst`
  (enforcement `legacy_global`) — sintoma típico: Facebook “sem CSS” com
  DNS sinkhole VIP OK.
- **Causa raiz:** no FreeBSD/pfSense, declarações `persist` no ruleset (e
  membros iniciais em reloads) **não garantem** materialização acessível via
  `pfctl -t`; `layer7_resync()` / `layer7-pfctl ensure` materializavam
  `layer7_block*`, `allow_dst`, `pdst`/`psrc`/`pallow`, mas **omitiam**
  `layer7_exc_allow_*` (e o mesmo padrão afectava `pexc`/`blsrc` estáticos).
  Após flush lifecycle, nada repunha os IPs VIP no PF live.
- **Correcção:** `layer7_pf_table_replace_entries()` +
  `layer7_static_origin_tables_apply_to_pf()` — ensure + flush + add dos
  membros estáticos de `exc_allow` / `pexc` / `blsrc`; chamado em
  `layer7_resync()` (enforce) e em `layer7_pf_config_resync()`; helper
  `layer7-pfctl ensure` passa a cobrir `layer7_exc_allow_0..15`.

### Tests

- `tests/unit/test_flush_coverage.sh` PASS (contrato ensure/apply/flush).
- Extensão `tests/functional/test_vip_exception.php` (meta + helper apply).
- Suite completa no builder FreeBSD 15 + artefacto `.pkg`
  (`SHA256=79d348c26b20080520121cb32e521a89cdc4639dcb7b2787e46a26f0dd48fa76`).

### Docs

- CORTEX, MANUAL-INSTALL (`_64`; rollback → `_63`), backlog BG-075,
  `docs/05-daemon/pf-enforcement.md`.

## [1.8.11_63] - 2026-07-31 — Redesign compacto grelha Perfis rápidos (BG-074)

### Changed

- **UX (apenas apresentação):** grelha **Perfis rápidos** compacta estilo
  UniFi/UDM — cartões horizontais (~64px), ícone 36px, meta
  apps/hosts/hits, descrição em tooltip no cartão inteiro.
- **Toggle CSS:** switches estilo iOS/UniFi (verde quando ligado) substituem
  botões Ligar/Desligar; ícones `fa-cog` / `fa-pencil` para Opções/Editar.
- **Grupos accordion:** cabeçalho colapsável com contador e badge «N ligados»;
  estado inicial aberto quando o grupo tem perfis activos; persistência
  `localStorage`.
- **Pesquisa:** barra «Pesquisar perfil…» + filtro «Só ligados»; auto-expande
  grupos com resultados; secção **Perfis ocultos** adaptada e colapsada por
  defeito.
- **Badges:** «personalizado»/«editado» como pontos coloridos com tooltip.
- **Presets:** fundo ligeiramente tintado no grupo.

### Unchanged

- Handlers POST intactos (`toggle_profile_on/off`, modais, ocultos, VIP,
  export/import). Zero alteração funcional ou no daemon.

### Tests

- `./tests/run-local.sh local` PASS (macOS, PHP SKIP); builder FreeBSD 15:
  `php -l layer7_policies.php`, suite completa PASS. `.pkg` extraído validado
  (`layer7_policies.php` idêntico ao repo; `+MANIFEST` = `1.8.11_63`).

### Docs

- CORTEX, MANUAL-INSTALL (_63; rollback sec. 12 → `_62`), backlog BG-074,
  guia-completo 7.3.1.

## [1.8.11_62] - 2026-07-31 — Fix `$data` indefinido no rdr CIDR (BG-073)

### Fixed

- **Correcção pontual (zero mudança funcional):**
  `layer7_generate_rdr_rules_snippet()` chamava
  `layer7_vip_dns_rdr_from_cidr($data, $cidr)` com `$data` inexistente na
  função (bug pré-existente da `_60`); passa agora `$l7config` já carregado.
  Elimina o warning PHP 8 «Undefined variable $data» a cada
  `filter_configure` com regras `force_dns` por CIDR e concretiza o ganho de
  performance da `_61` no caminho CIDR (sem releitura de `layer7.json` por
  linha rdr). O fallback `null` recarregava a mesma config; as regras rdr
  geradas são idênticas.

### Tests

- `./tests/run-local.sh` PASS (macOS, PHP SKIP); builder FreeBSD 15:
  `php -l layer7.inc`, `test_vip_dns_exempt.php`, `test_vip_exception.php` e
  suite completa PASS. `.pkg` extraído validado (`layer7.inc` idêntico ao
  repo; `+MANIFEST` = `1.8.11_62`).

### Docs

- CORTEX, MANUAL-INSTALL (_62; rollback sec. 12 → `_61`), backlog BG-073.

## [1.8.11_61] - 2026-07-31 — Performance VIP DNS fallback (BG-073)

### Changed

- **Performance (sem alteração funcional):** `layer7_vip_dns_rdr_from_any()` e
  `layer7_vip_dns_rdr_from_cidr()` passam `$data` a
  `layer7_vip_dns_rdr_fallback_enabled()`, evitando releituras redundantes de
  `layer7.json` em cada `filter_configure` (interface × CIDR).
- **Cosmético:** ramo redundante removido em `layer7_vip_dns_mode_get()`.

### Tests

- `./tests/run-local.sh` PASS; builder FreeBSD 15: `php -l layer7.inc`, suite
  completa incl. `test_vip_dns_exempt.php` e `test_vip_exception.php` PASS.

### Docs

- CORTEX, MANUAL-INSTALL (_61; rollback sec. 12 → `_60`), backlog BG-073.

## [1.8.11_60] - 2026-07-31 — Pós-auditoria Lista VIP (BG-073 fix)

### Fixed

- **P1:** `layer7_vip_dns_rdr_fallback_enabled()` passa a derivar do estado
  persistente (`layer7_vip_dns_should_apply` + ausência de `L7_VIP_DNS_MARKER_*`
  em Unbound `custom_options`), em vez de `$GLOBALS` só definido em
  `layer7_vip_dns_sync()`. Corrige regeneração silenciosa de rdr `:53`
  `from any` em `filter_configure` (save regra, boot, evento interface) que
  reintroduzia sinkhole para VIPs em modo fallback.
- **P3:** docblock `layer7_vip_validate_limits` alinhado com
  `LAYER7_VIP_MAX_HOSTS` / `LAYER7_VIP_MAX_CIDRS` (32+16).

### Changed

- `layer7_vip_dns_rdr_fallback_set()` mantido apenas como override em testes;
  removidas chamadas redundantes em `layer7_vip_dns_sync()`.

### Tests

- `test_vip_dns_exempt.php`: estado persistente (marker presente/ausente) +
  overrides de teste; builder FreeBSD 15 PASS.

### Docs

- CORTEX, MANUAL-INSTALL (_60; rollback sec. 12 → `_59`), backlog.

## [1.8.11_59] - 2026-07-31 — BG-073 isenção VIP caminho DNS (Bloco D)

### Added

- **ADR-0020 opção (a):** view Unbound `layer7-vip-exempt` (sem `view-first`) via
  `access-control-view` para IPs/CIDRs de `vip-isentos`; markers
  `L7_VIP_DNS_MARKER_*` idempotentes em `custom_options`.
- Regeneração em `layer7_vip_dns_sync()` / `layer7_blockpage_sync()` /
  `layer7_pf_config_resync()`; validação `unbound-checkconf` antes de gravar.
- **Fallback opção (b):** se a view falhar validação, rdr `:53` global e por
  blacklist passam a `from !<layer7_exc_allow_N>` (e `from {cidr} !<table>`);
  limitação sinkhole documentada na GUI.
- `layer7_remove_unbound_vip_dns()` em `pkg-deinstall`; testes
  `test_vip_dns_exempt.php`.

### Notes

- SSOT `vip-isentos` inalterado; sem chaves novas no objecto excepção.
- Sinkhole bypass **completo** com opção (a); parcial (só rdr) com fallback (b).
- Host overrides nativos para VIPs: trade-off documentado (gate Bloco E).

### Tests

- `test_vip_dns_exempt.php`: snippet Unbound, strip, fallback rdr, `pfctl -nf`
  quando disponível.
- `php -l` nos PHP tocados; suite local/builder.

### Docs

- CORTEX, backlog BG-073, checklist Bloco D, ADR-0020, MANUAL-INSTALL (_59),
  `gui-validation.md`.

## [1.8.11_58] - 2026-07-31 — BG-072 limites daemon Lista VIP

### Changed

- `L7_EXC_MAX_HOSTS` 8→32 e `L7_EXC_MAX_CIDRS` 8→16 em `policy.h` (unica
  alteracao C do Bloco C).
- Constantes PHP `LAYER7_VIP_MAX_HOSTS` / `LAYER7_VIP_MAX_CIDRS` alinhadas
  (32 / 16); validacao e upsert VIP coerentes com o daemon.

### Notes

- Memoria estatica maxima excepcoes: 16 × `struct layer7_exception` ≈ +19 KiB
  vs limites 8+8 (+1216 B por excepcao nos arrays hosts/cidrs).
- Parser ingenuo inalterado; isencao DNS permanece Bloco D (BG-073).

### Tests

- `test_config_parse.c`: 32 hosts em excepcao VIP parseados.
- `test_policy_decide.c`: host 10 em excepcao VIP obtem allow (nao truncado).
- `test_vip_exception.php`: limites 32/16.
- Builder FreeBSD 15: suite C, `php -l`, smoke — PASS.

### Docs

- CORTEX, backlog BG-072, checklist Bloco C, MANUAL-INSTALL (_58),
  `gui-validation.md`.

## [1.8.11_57] - 2026-07-31 — BG-071 Lista VIP global

### Added

- Secção **Lista VIP (isencão total)** em `layer7_exceptions.php`: tabela
  Descrição | IP/CIDR | acções, formulário **Adicionar isento**.
- Labels em `layer7.vip_meta.labels` (mapa IP/CIDR → descrição; daemon nunca lê).
- Export/import JSON da Lista VIP (padrão BG-070).
- Link **Gerir Lista VIP** no modal Perfis rápidos.
- Constantes PHP `LAYER7_VIP_MAX_HOSTS` / `LAYER7_VIP_MAX_CIDRS` (=8) com
  rejeição visível (sem truncamento silencioso).
- Avisos DHCP static mapping e sinkhole DNS (Bloco D / ADR-0020).

### Tests

- `tests/functional/test_vip_exception.php` estendido: labels, limites,
  export/import round-trip.
- Builder FreeBSD 15: `php -l`, teste funcional PASS.

### Docs

- CORTEX, backlog BG-071, checklist Bloco B, MANUAL-INSTALL (_57),
  `gui-validation.md`.

## [1.8.11_56] - 2026-07-31 — BG-070 integral + correcções pós-_55 defeituoso

### Fixed

- **Rebuild obrigatório:** `1.8.11_55` foi compilada no builder **antes** do
  commit completo de BG-070 — o `.pkg` publicado continha apenas o merge em
  `layer7.inc`/`Makefile`, **sem** GUI de edição (`l7showProfileEditModal`),
  export/import `profiles_custom`, nem scripts `+INSTALL`/`+DEINSTALL` para
  `profiles-custom.json`. **`1.8.11_55` não deve ser instalada**; usar `_56`.
- **`pkg-deinstall.in`:** condição `PKG_UPGRADE` corrigida — `pkg(8)` define
  `"true"`, não `"YES"`; upgrades passam a preservar `profiles-custom.json`.
- **Perfis ocultos:** secção discreta **Perfis ocultos** no fim da grelha com
  botões **Mostrar** e **Editar** (antes o cartão desaparecia sem forma de
  reverter).
- **`layer7_profile_icon_valid()`:** validação contra lista FontAwesome 4.7
  embebida (`layer7-fa47-icons.inc`); ícones fora da lista rejeitados (fallback
  `fa-cube` na gravação).

### Added

- Artefacto integral BG-070: GUI editar/criar perfis, `profiles_custom` em
  export/import, skeleton/preservação em install/deinstall.

### Tests

- `tests/functional/test_profile_icon_valid.php` + extensão de
  `test_profiles_json.sh`.
- Builder FreeBSD 15: suite local, validação do `.pkg` extraído (4 checks
  BG-070), simulação upgrade — PASS.

### Docs

- CORTEX, backlog BG-070, MANUAL-INSTALL (_56; _55 marcada defeituosa;
  rollback → `_54`, **não** `_55`), guia 7.3.2 secção Perfis ocultos.

## [1.8.11_55] - 2026-07-31 — Perfis editáveis e personalizados (BG-070) — **DEFEITUOSO, NÃO INSTALAR**

> **Atenção:** esta release foi publicada com artefacto incompleto (build no
> builder desalinhado do repositório). **Não instalar.** Substituída por
> `1.8.11_56`.

### Added

- **`/usr/local/etc/layer7/profiles-custom.json`** (overlay cliente; **fora** do
  `pkg-plist` / Makefile): `overrides` para perfis de fábrica
  (`hosts_add/remove`, `apps_add/remove`, `hidden`) e `custom_profiles` com ids
  prefixo `c-`.
- **`layer7_load_profiles()`** passa a fazer merge: fábrica → overrides →
  personalizados; grupo GUI **Personalizados** no fim da grelha.
- GUI **Politicas > Perfis rápidos**: botão **Criar perfil**, **Editar** por
  cartão, badges `personalizado` / `editado`, modal de edição (apps só do
  catálogo de fábrica; hosts texto livre validado), auto-reconnect da política
  ligada com aviso.
- Export/Import em **Definições** inclui `profiles_custom`.
- `pkg-install.in` cria skeleton vazio na 1.ª instalação; upgrade preserva
  `profiles-custom.json` (padrão UT1/blacklists).

### Changed

- `pkg-deinstall.in` / remoção GUI: opção **Manter configuração** preserva
  também `profiles-custom.json`.

### Tests

- `tests/functional/test_profiles_custom_merge.php` + extensão de
  `test_profiles_json.sh` (merge overlay).
- Builder FreeBSD 15: `php -l`, suite local, pacote sem `profiles-custom.json`
  no `.pkg`, simulação upgrade com ficheiro intacto e merge activo — PASS.

### Docs

- CORTEX, backlog BG-070, guia completo 7.3.2, MANUAL-INSTALL (rollback → `_54`).

## [1.8.11_54] - 2026-07-31 — Correcção visual da grelha Perfis rápidos (BG-069)

### Fixed

- **Cabeçalhos de grupo quebrados:** os títulos de grupo eram emitidos com
  `grid-column:1/-1` dentro de um container `display:flex` — propriedade de CSS
  grid ignorada em flex, o que deixava o cabeçalho "flutuando" inline no meio
  dos cartões. Cada grupo passa a ser uma secção própria (`.l7-profile-group`)
  com cabeçalho full-width (título + contador de perfis, linha separadora) e a
  sua própria grelha de cartões.
- **Ícones em falta (55 de 72 perfis):** a GUI ignorava o campo `icon` do
  `profiles.json` e usava um mapa SVG hardcoded com apenas 17 ids antigos; os
  restantes caíam no fallback de letra em quadrado cinzento. O cartão passa a
  renderizar o ícone FontAwesome 4.7 (incluído no pfSense) declarado em
  `profiles.json`, sanitizado (`^fa-[a-z0-9-]{1,40}$`), com cor de fundo por
  marca (`$l7_brand_colors`, ~55 marcas) ou por grupo (`$l7_group_colors`).
  O mapa SVG inline foi removido (~15 KB de HTML a menos por página).
- **Cartões desalinhados:** cartões passam a flex column com `min-height`
  uniforme, descrição truncada a 3 linhas (`-webkit-line-clamp` + `max-height`
  fallback, texto completo no `title`) e botões Ligar/Desligar/Opções ancorados
  ao fundo do cartão (`.l7-profile-cta { margin-top:auto }`).

### Changed

- `profiles.json`: `ai-tools` passa de `fa-robot` (inexistente no FA 4.7) para
  `fa-magic` — único dos 72 ícones fora da lista oficial FA 4.7.

### Tests

- `tests/fixtures/fa47-icon-names.txt`: lista oficial dos 782 nomes (com
  aliases) do FontAwesome 4.7; `test_profiles_json.sh` passa a falhar se algum
  `icon` do `profiles.json` não existir no FA 4.7.

### Docs

- CORTEX, backlog BG-069, guia completo 7.3.1, MANUAL-INSTALL (rollback → `_53`).

## [1.8.11_53] - 2026-07-31 — Expansão catálogo Perfis rápidos Bloco 2 (BG-068)

### Added

- **34 novos perfis** (38 → **72**): videoconferência (Zoom, Teams, Meet, Webex,
  TeamSpeak, agregado), redes alternativas (Threads, Bluesky, Kick, Rumble,
  Mastodon/Tumblr/VK/Weibo), streaming (Deezer, SoundCloud, DAZN, Paramount+,
  Hulu, Vimeo/Dailymotion, futebol pirata), jogos (Roblox, Free Fire, Cloud
  Gaming), produtividade (empregos, notícias, desporto, viagens, speedtest),
  segurança (anonymizers, publicidade, malware, mining) e **3 presets**
  (distrações, proteção infantil, higiene de rede).
- Grupos GUI novos: **Comunicação e reuniões** e **Presets**.

### Changed

- Agregados **Redes Sociais**, **Jogos**, **VPN/Proxy** e **Criptomoedas**
  reforçados (Threads/Bluesky, NetEaseGames/Garena, Psiphon/UltraSurf/Warp/Relay,
  categoria Mining).
- `$l7_group_order` em `layer7_policies.php` actualizado com os 2 grupos novos.

### Docs

- CORTEX, backlog BG-068, guia completo 7.3.1, MANUAL-INSTALL (rollback → `_52`).

## [1.8.11_52] - 2026-07-30 — Catálogo Perfis rápidos nível UniFi/UDM (BG-067)

### Added

- **38 perfis rápidos** (antes 18): novos perfis Telegram, Discord, Kwai, Mensageria,
  Marketplaces, Torrent/P2P, Apostas, Prime Video, Disney+, Max, Globoplay,
  Crunchyroll, Cloud Storage, Webmail pessoal, Reddit, Pinterest, Snapchat,
  Criptomoedas, Namoro e atalho Conteúdo adulto.
- Campo opcional `group` em `profiles.json` com cabeçalhos na GUI (Redes sociais,
  Mensageria, Streaming, Jogos, Produtividade, Segurança e bypass).
- Primeiro uso de `ndpi_categories` nos perfis (Gambling, Chat, Shopping, FileSharing,
  Game, Dating, AdultContent, etc.).
- Teste `tests/unit/test_profiles_json.sh` + fixtures nDPI validadas no builder FreeBSD 15.

### Changed

- Correcções de hosts desactualizados (ai-tools, netflix, tiktok, gaming).
- Nomes nDPI alinhados ao builder (`NetFlix`, `Github`, `Playstation`, `IPSec`, …).
- Agregados **Redes Sociais** e **Streaming** actualizados.
- Perfil **Jogos** reforçado (Steam, Xbox, PlayStation, Epic, Blizzard, Nintendo, Riot, Roblox).
- Modal Opções: slice de apps corrigido de 12 para **64** (igual ao toggle directo).

### Docs

- CORTEX, backlog BG-067, guia completo, MANUAL-INSTALL.

## [1.8.11_51] - 2026-07-30 — Fix: ordem PF da exclusao por politica (BG-066)

### Fixed

- **PF scoped (`scoped_hybrid`):** a regra `match from <layer7_pexc_N> to
  <layer7_pdst_N> tag L7ALLOW` era emitida **depois** dos `block drop quick`
  da mesma politica em `layer7_policy_enforcement_rules_text()`. Como `quick`
  e terminal, o pacote da origem excluida era dropado antes de receber a tag
  `L7ALLOW` e a exclusao do `_50` era inoperante sempre que o destino tinha
  entrado em `layer7_pdst_N` por trafego de outro cliente. O match passa a
  preceder os blocks da politica (mesma semantica do allowlist/pallow).

### Tests

- `test_scoped_pf_inc.php`: nova assercao de **ordem** — o match `pexc` tem
  de vir antes do primeiro `block drop quick` (o teste do `_50` so validava
  presenca, por isso nao apanhou a regressao).

## [1.8.11_50] - 2026-07-30 — Exclusao por politica `src_exclude_*` (BG-066)

### Added

- ADR-0019: campos `match.src_exclude_cidrs` e `match.src_exclude_groups`.
- Daemon: parse, expansao de grupos excluidos e nao-match em `src_matches_rule`.
- PF scoped: tabela `layer7_pexc_N` + regra `match from pexc to pdst tag L7ALLOW`.
- GUI: **Excluir origens (so este perfil)** no modal Avancado e formulario manual;
  validacao include/exclude conflituoso.
- Flush/self-heal/deinstall incluem `layer7_pexc_0..23`.
- Testes: `test_policy_decide.c`, `test_config_parse.c`, `test_scoped_pf_inc.php`,
  `test_flush_coverage.sh`.

### Docs

- `docs/core/policy-matrix.md`, `precedence.md`, `pf-enforcement.md`, CORTEX, backlog.

## [1.8.11_49] - 2026-07-30 — UX modal Perfis rapidos + verificador (BG-065)

### Added

- Progressive disclosure no modal: essencial (Accao, Aplicar a, Isentos) vs
  **Avancado** recolhido (CIDRs manuais).
- Atalho **Criar grupo (ex.: Gestores)** quando nao existem grupos.
- Link **Verificador de politica efectiva** para `layer7_test.php`.
- Veredicto destacado no teste: PERMITIDO / BLOQUEADO / MONITORIZADO com
  motivo legivel (ex.: `PERMITIDO — excepcao vip-isentos`).

### Changed

- Excepcoes ordenadas por prioridade na simulacao; grupos incluem
  `device_ips` no match de origem.

## [1.8.11_48] - 2026-07-30 — Isencao VIP nos Perfis rapidos (BG-064)

> **Nota:** `_48` nunca foi construido nem publicado como artefacto proprio —
> o codigo deste bloco foi consolidado e distribuido no pacote `1.8.11_49`
> (tag `v1.8.11_49`). Nao existe `.pkg` nem tag `v1.8.11_48`.

### Added

- Modal **Opções** dos Perfis rapidos: secção **Isentos (nunca bloqueados)**
  que cria/actualiza a excepcao canonica `vip-isentos` (allow global,
  prioridade alta). Suporta grupos (expandidos para IPs/CIDRs na gravacao),
  IPs e CIDRs manuais.
- Badge **Perfis rapidos** em `layer7_exceptions.php` para a excepcao gerida.
- Funcoes `layer7_upsert_vip_exception()` e helpers em `layer7.inc`.
- Teste funcional `tests/functional/test_vip_exception.php`.

### Changed

- `toggle_profile_off` documentado: desligar perfil **nao** remove a
  excepcao VIP partilhada.

### Docs

- Plano SSOT `docs/02-roadmap/plano-isencao-vip-e-ux-gui.md`, modelo
  conceptual GUI, backlog BG-064/065/066.

## [1.8.11_47] - 2026-07-30 — HTTPS ao portal com erro imediato (UX block page)

### Added

- Com a block page activa, HTTPS ao IP portal ficava "a carregar" ate ao
  timeout sem erro visivel: o SYN a <portal>:443 era aceite por regras
  `pass` anteriores (anti-lockout / allow LAN) e o
  `net.inet.tcp.blackhole=2` do pfSense dropava em silencio a porta
  fechada. Correccao: rdr tambem para TCP 443 -> servico local da pagina
  (rdr precede o filtro, mesmo caminho do :80 que ja funciona); o cliente
  TLS recebe resposta HTTP invalida e o browser mostra o erro de ligacao
  de imediato. Salvaguarda: a porta efectiva do webConfigurator
  (`layer7_webgui_port()`) nunca e redireccionada. Refactor:
  `layer7_blockpage_portal_and_ifaces()` partilhado.
- Nota: `_46` (candidato interno, nunca publicado) tentava resolver com
  `block return-rst`; nao funcionava porque as regras `pass` anteriores
  venciam pela ordem — supersedido por `_47`.

## [1.8.11_45] - 2026-07-30 — rdr da block page e DNS forcado agora efectivos

### Fixed

- As regras rdr (block page :80 e DNS forcado :53) eram carregadas no
  anchor `natrules/layer7_nat`, mas o ruleset principal do pfSense so
  declara `nat-anchor "natrules/*"` (sem `rdr-anchor`) — em PF, regras
  `rdr` num anchor sem ponto `rdr-anchor` **nunca sao avaliadas**. Na
  pratica o redirect HTTP para a pagina de bloqueio e o anti-bypass DNS
  estavam mortos: quem respondia no :80 era o nginx do webConfigurator
  (301). Correccao: as regras rdr passam a ser devolvidas por
  `layer7_generate_rules("nat")` (hook `filter_rule_function` /
  `discover_pkg_rules` — mesmo mecanismo do proxy transparente do Squid)
  e entram no ruleset principal em cada filter reload. O anchor legado e
  flushado; `layer7_inject_nat_to_anchor()` removido.

## [1.8.11_44] - 2026-07-30 — CRITICO: daemon nunca bloqueia IPs do firewall

### Fixed

- O sinkhole da block page resolve dominios bloqueados para o IP portal
  (interface do firewall); o daemon via a resposta DNS e adicionava o
  **proprio IP do pfSense** a `layer7_block_dst` — cortando GUI/SSH de
  todas as redes para esse IP (observado em lab: 192.168.100.254
  inacessivel a partir da VLAN 95). Novo guard `ip_is_local_iface_addr()`
  (getifaddrs, cache 60s) em todos os caminhos de insercao block
  (politica DNS/fluxo + blacklist DNS/SNI).

## [1.8.11_43] - 2026-07-30 — rc.d block page: dedup por porta e status robusto

### Fixed

- `layer7-blockpage` rc.d: segundo arranque com porta ocupada fazia o
  daemon(8) sair e apagar o pidfile da instancia activa (status errado e
  risco de duplicados). Start deduplica pela porta 8099; status tem
  fallback via sockstat.

## [1.8.11_42] - 2026-07-30 — Fix rdr block page (label) e arranque do servico

### Fixed

- pf rejeita `label` em regras rdr: o rdr :80 da block page nunca carregava
  no anchor (syntax error silencioso desde `_35`). Labels removidos.
- `layer7-blockpage` helper saia de imediato sob daemon(8): o pidfile do
  supervisor ja existia e o self-check interpretava-o como instancia activa.
  Check removido do helper (deduplicacao fica no rc.d).

## [1.8.11_41] - 2026-07-30 — Fix IP portal com interfaces por nome real

### Fixed

- `layer7_blockpage_portal_ip()`: quando `layer7.interfaces` guarda nomes
  reais (`vmx0`, `vmx0.95`), o portal nao era detectado (config.xml indexa
  por lan/optN). Novo mapeamento inverso pelo campo `if`. Sem portal, o
  sinkhole e o rdr da block page nunca eram gerados.

## [1.8.11_40] - 2026-07-30 — DNS forcado global (anti-bypass sinkhole)

### Added

- `block_page.force_dns` (opt-in, GUI Definições): rdr UDP/TCP :53 de todas as
  interfaces de captura para o Unbound local — clientes com DNS hardcoded
  (8.8.8.8/1.1.1.1) deixam de contornar o sinkhole. Activa anti-DoH
  automaticamente (NXDOMAIN resolvers DoH + canario Firefox). ADR-0018.

## [1.8.11_39] - 2026-07-30 — Fix bloqueio YouTube vs allowlist

### Fixed

- Removido `youtube.com` da allowlist-seed (conflitava com politicas block).
- `layer7d`: politica block prevalece sobre allowlist (DNS + fluxo nDPI).
- Ao aplicar block PF, revoga IP em `layer7_allow_dst` (CDN Google partilhado).

## [1.8.11_38] - 2026-07-30 — Updater AJAX em ficheiro externo (CSP pfSense Plus)

### Fixed

- «Verificar actualizacao» movido para `layer7_settings_update.js` (externo).
  pfSense Plus bloqueia scripts inline e `onclick`; POST continuava a
  funcionar. Config via `data-l7-update-cfg` no bloco `#l7_pkg_update`.

## [1.8.11_37] - 2026-07-30 — Fix updater: bind apos DOM pronto

Publicada em `pablomichelin/Layer7`.
Artefacto `pfSense-pkg-layer7-1.8.11_37.pkg`
(`SHA256=58e0b4a1ee58df70e9755e40cf6a3f6d26a623e354dcf521dff3c707f0df4a4a`).

### Fixed

- «Verificar actualizacao» nao respondia: script no fim da pagina corria
  depois de `DOMContentLoaded`; `l7BindCheckUpdateButton()` nunca era
  chamado. Agora executa imediatamente se `document.readyState !== "loading"`.

## [1.8.11_36] - 2026-07-30 — Fix updater GUI (Verificar actualizacao)

Publicada em `pablomichelin/Layer7` (candidato interno).
Artefacto `pfSense-pkg-layer7-1.8.11_36.pkg`
(`SHA256=227d8058b28ba030789b197b7cb118d444860c1890ce1311507ddfa398f1fce3`).

### Fixed

- Botao «Verificar actualizacao» deixou de depender de `onclick` inline (CSP
  pfSense Plus); usa `addEventListener` + re-bind apos render AJAX.
- Link «Modo compatibilidade» (POST) quando JavaScript falhar.
- `fetch` ao GitHub API com `--user-agent` explicito.

### Risco e rollback

- Alteracao PHP/JS da GUI; rollback: `_34` ou `_35`.

## [1.8.11_35] - 2026-07-30 — Pagina de bloqueio utilizador final (DNS sinkhole)

Publicada em `pablomichelin/Layer7` (candidato interno).
Artefacto `pfSense-pkg-layer7-1.8.11_35.pkg`
(`SHA256=86d0939d9fa81f4f3aa4fdf967fa06647e02e94b3afba73447c19cfb98c764a4`).
Release: `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_35`

### Added

- **Pagina de bloqueio** (ADR-0017 / BG-058): toggle opt-in nas Definições;
  mensagem/titulo/contacto customizaveis; IP portal auto ou manual.
- **DNS sinkhole Unbound** para dominios de politicas `block` activas (+ blacklists
  UT1 opcional, limite configuravel).
- Servico `layer7-blockpage` (PHP built-in em `127.0.0.1:8099`) + NAT `rdr`
  HTTP porta 80 no IP portal.
- Teste shell `tests/test_blockpage_config.sh`.

### Documentacao

- `docs/03-adr/ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md`
- `docs/05-daemon/pf-enforcement.md` — secao pagina de bloqueio
- `docs/04-package/validacao-lab.md` — secao **14**

### Risco, teste e rollback

- **HTTP:** pagina visivel quando dominio esta no sinkhole.
- **HTTPS:** erro TLS (sem MITM) — documentado no UI e ADR.
- Enforcement PF inalterado com toggle OFF.
- Rollback: desactivar pagina ou reinstalar `_34`.

## [1.8.11_34] - 2026-07-30 — GUI updater sem reload da pagina

Publicada em `pablomichelin/Layer7` (candidato interno).
Artefacto `pfSense-pkg-layer7-1.8.11_34.pkg`
(`SHA256=1401ce8f74a40b72c53fdf0414a92f523447ef3eb6d611c0036e16be136ca232`).
Release: `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_34`

### Changed

- Botao «Verificar actualizacao» passa a usar AJAX: resultado aparece no
  proprio bloco de actualizacao sem recarregar a pagina nem perder a posicao
  de scroll.
- Instalacao do pacote (`Actualizar para X`) mantem POST; apos concluir,
  auto-scroll para a secao de actualizacao.
- Logica de consulta ao GitHub consolidada em `layer7_check_for_update()`.

### Risco, teste e rollback

- Alteracao apenas PHP/JS da GUI; sem impacto em enforcement.
- Rollback: reinstalar `1.8.11_33` ou anterior.

## [1.8.11_33] - 2026-07-30 — GUI blacklists: progresso de download visível

Publicada em `pablomichelin/Layer7` (candidato interno).
Artefacto `pfSense-pkg-layer7-1.8.11_33.pkg`
(`SHA256=b55f0c310ff70012862a6f717a89542289a406c54eea6c004648ca88bb37032e`).
Release: `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_33`

### Fixed

- Log de download de blacklists movido para imediatamente abaixo do botão
  «Download snapshot assinada» (deixava de ser visível após a secção trust chain).
- Linha de progresso inicial escrita em PHP ao disparar o download; detecção
  de lock activo; invocação do script via `/bin/sh` para maior robustez no
  `mwexec_bg`.
- Polling AJAX do log continua após refresh enquanto download em curso; indicador
  visual (spinner / concluído / erro HTTP) e auto-scroll para a secção de log.

### Risco, teste e rollback

- Alteração apenas em PHP da GUI e helpers; sem impacto em enforcement PF.
- Rollback: reinstalar `1.8.11_32` ou anterior via GUI/pkg.

## [1.8.11_32] - 2026-07-30 — flush PF lifecycle e auditoria pré-gate

Publicada em `pablomichelin/Layer7` (candidato interno; Gate B1 pendente).
Artefacto `pfSense-pkg-layer7-1.8.11_32.pkg`
(`SHA256=c36ab91ef66504671e109009bdce9df3bb81c75d580b83313dee52f8c3b9640e`).
Release: `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_32`

### Fixed

- Flush de `layer7_exc_allow_*` em `layer7_flush_dynamic_tables()` e
  `layer7-pfctl flush-all` (B-002).
- `layer7_bl_apply()` passa a flushar tabelas dinâmicas antes de
  `filter_configure()` (B-003).
- `pkg-deinstall`: `flush-all` em PRE-DEINSTALL; fallback POST alinhado com
  helper (B-004).

### Added

- Testes R-21 (`test_flush_coverage.sh`) e contrato FP-015 em
  `test_config_parse.c`.
- Documentação auditoria multitask e matriz unificada REV/FP/AUD.

### Risco, teste e rollback

- Breve gap de bloqueio durante flush em mutação de blacklist.
- Suite local/builder C/shell e smoke `layer7d`: PASS
  (`SHA256=c36ab91ef66504671e109009bdce9df3bb81c75d580b83313dee52f8c3b9640e`).
- Appliance Gate B1: pendente.
- Rollback: `_24` passivo + `layer7-pfctl flush-all`.

---

## [1.8.11_31] - Unreleased — decisão somente após classificação nDPI final

Pacote candidato, não publicado e não aprovado para produção.

### Fixed

- O primeiro protocolo parcial deixa de congelar app/categoria/SNI do fluxo.
  A decisão aguarda `NDPI_STATE_CLASSIFIED`.
- Ao atingir o orçamento de 48 pacotes sem estado final, a captura chama
  `ndpi_detection_giveup()` antes de emitir o resultado.

### Risco, teste e rollback

- Impacto limitado ao momento de finalização da captura nDPI; política e PF
  não mudam.
- Suite local/builder, PHP/shell, build nDPI e pacote extraído: PASS
  (`SHA256=dc5118dd01193a83a6c6d15cc3ae4ca300647294a5b188e1991a363b4c453e33`).
- Appliance passivo: pendente.
- Rollback: `_30` passivo; `_24` continua rollback público conhecido.

---

## [1.8.11_30] - Unreleased — fluxo nDPI resiliente a colisões

Pacote candidato, não publicado e não aprovado para produção.

### Fixed

- O lookup percorre toda a janela antes de reutilizar um slot expirado,
  impedindo dois estados nDPI para a mesma conversa após colisão/expiração.
- Janela cheia deixa de descartar silenciosamente o fluxo e passa a evictar
  deterministicamente o menos recente.

### Added

- JSON de status recebe `cap_pkts`, `cap_active`, `cap_classified`,
  `cap_expired`, `cap_evicted`, `cap_dropped` e `captures`.
- Regressões cobrem buraco antes do match, primeiro livre, janela cheia e
  lookup read-only sob pressão.

### Risco, teste e rollback

- Impacto limitado ao subsistema de captura nDPI; sem mudança de política/PF.
- Suite local/builder, PHP/shell, build nDPI e pacote extraído: PASS
  (`SHA256=3a54c667a601e29995562714691f4ee3e9e8e78a02fcd3e600955ae90d2e9b40`).
- Appliance passivo: pendente.
- Rollback: `_29` passivo; `_24` continua rollback público conhecido.

---

## [1.8.11_29] - Unreleased — sintaxe anti-QUIC aceita pelo PF

Pacote candidato, **não publicado** e **não aprovado para produção**.
O pré-gate read-only no pfSense Plus 26.03.1 encontrou FP-018 antes da
instalação; nenhuma regra ou configuração do appliance foi alterada.

### Fixed

- Anti-QUIC por interface passa de `block ... inet on <if>` para
  `block ... on <if> inet` (e equivalente `inet6`), ordem aceite pelo parser
  PF do appliance.
- Geração anti-QUIC foi isolada em função pura e ganhou regressão PHP para
  rejeitar a ordem inválida e nomes de interface não sanitizados.

### Gates e rollback

- `_24` instalado está passivo, íntegro e com ruleset actual válido.
- Snippet autocontido com `L7ALLOW`, `pallow`, `blsrc`, anti-DoT e anti-QUIC
  corrigido: `pfctl -nf -` PASS no FreeBSD 16.
- Suite C/PHP/shell, build nDPI e validação do pacote extraído no FreeBSD 15:
  PASS (`SHA256=bea385ddb6f61bb6a9bffde0b781cea7a852b3956f620b8b004c914b0ab01840`).
- Ruleset completo instalado, toggle anti-QUIC e two-client: pendentes.
- `_28` está supersedido; rollback permanece `_24` passivo + flush + reload.

---

## [1.8.11_28] - Unreleased — allow PF sem bypass do pfSense

Pacote candidato **supersedido por `_29`**, não publicado e não aprovado para
produção. Não instalar: FP-018 invalida o ruleset se anti-QUIC por interface
estiver activo.
BG-056/FP-017 é corrigido em código sob a decisão ADR-0016; build do `.pkg`
passou e o gate no appliance continua pendente.

### Added

- Tabela dinâmica `layer7_pallow_N` por política `allow`, populada pelo
  daemon somente quando essa política vence DNS/SNI/nDPI e expirada por TTL.
- Tabela estática `layer7_exc_allow_N` por excepção `allow`.
- Marca interna PF `L7ALLOW` e escopo negativo por regra de blacklist
  `layer7_blsrc_N`.
- Cobertura C/PHP e smoke de appliance para precedência, modo monitor,
  ausência de `pass quick` e exception `block`.

### Fixed

- Allow explícito passa a vencer destino já presente numa tabela de block do
  Layer7, sem retirar o block do outro cliente.
- Allowlist, políticas e excepções deixam de usar `pass quick`: `match/tag`
  não autoriza tráfego e mantém as regras nativas do pfSense efectivas.
- `except_ips` de blacklist deixa de criar bypass geral e passa a ser
  subtraído da origem efectiva da regra UT1 em `layer7_blsrc_N`.
- Exception `block`, que casa pela origem, passa a usar `layer7_block` e kill
  de estados do host; antes podia tentar um destino inexistente.
- Flush/resync/self-heal incluem `layer7_pallow_0..23`; mutações de excepção
  limpam tabelas dinâmicas antes de regenerar o filtro.
- `smoke-layer7d.sh` volta a espelhar o daemon real incluindo `log_store.c`;
  sem isso o gate oficial falhava no link desde a introdução do logging L1.
- O diagnóstico `-e` aceita `-d DST` para validar o enforcement moderno por
  destino sem tocar no PF; o smoke usa um IPv4 de documentação.
- `bl_config.c` inclui `<stdint.h>` explicitamente; antes dependia de header
  transitivo no FreeBSD e quebrava o smoke Linux ao usar `uint32_t`.

### Gates e rollback

- Suite local, builder C/PHP/shell, smoke, build nDPI e `.pkg`: PASS
  (`SHA256=62dd9ae5923ade45b0bb484dca4e835b29b139f7a2aaa0a3624272ba07e59dc6`).
- `pfctl -nf`, instalação passiva e two-client: pendentes.
- Produção permanece intocada. Rollback: `_24` passivo +
  `layer7-pfctl flush-all` + reload do filtro.

---

## [1.8.11_27] - Unreleased — estabilização funcional pré-produção

Pacote candidato, **não publicado** e **não aprovado para produção**. Revisão
end-to-end documentada em
`docs/09-blocking/revisao-funcional-pre-producao-2026-07-29.md`.
Build isolado no FreeBSD 15:
`pfSense-pkg-layer7-1.8.11_27.pkg`,
`SHA256=8eae978d8d3120f050be21d2fdf511aacbf03ba0ad2c9c350c15100818ed5388`.

### Fixed

- **Classificação bidireccional:** canonicaliza os endpoints antes do hash;
  ida e volta da mesma conversa passam ao mesmo `ndpi_flow_struct`.
- **App sem quarentena:** aplicação/categoria normal usa
  `layer7_pdst_N` e bloqueia somente o destino observado.
  `layer7_psrc_N` fica reservado a `quarantine_origin=true`.
- **Bloqueio imediato:** após `pfctl -T add`, invalida o estado PF afectado;
  par cliente/destino em `pdst`, host inteiro só na quarentena e destino
  inteiro apenas no modelo legado global.
- **Precedência no callback:** política/excepção `allow` explícita impede nova
  inserção pela blacklist; default allow continua a avaliar blacklist.
  Precedência sobre entradas PF já existentes permanece gate/FP-017.
- **TTL SNI:** entradas de blacklist criadas pelo caminho SNI entram no cache
  de expiração.
- **Self-heal scoped:** a recuperação valida a tabela que falhou e o helper
  não declara sucesso com `pdst/psrc` ausente.
- **DNS CNAME:** preserva o QNAME original ao percorrer answer RRs.
- **Expiração de fluxos:** sweep também ocorre em tráfego já classificado.
- **Mutação de políticas:** add/edit/toggle/enable passa a limpar tabelas
  dinâmicas antes de regenerar regras, evitando destinos herdados por outro
  índice, origem ou acção.

### Added

- `capture_flow_key.h` e `test_capture_flow_key.c`.
- BG-055 e revisão funcional pré-produção de 2026-07-29.

### Gates e rollback

- Suite local completa no builder (C, PHP e shell), build nDPI, `pkg info`,
  conteúdo, versão e smoke `layer7d -t`: PASS.
- Gate appliance: pendente.
- Produção permanece intocada. Rollback operacional continua `_24` passivo +
  `layer7-pfctl flush-all`; preservar evidência.

---

## [1.8.11_26] - Unreleased — contenção L1 de logs

Pacote candidato, **não publicado** e **não aprovado para produção**. O bloco
BG-054 corrige crescimento ilimitado e ruído observados no appliance sem
alterar a lógica de decisão ou regras PF. Build isolado no FreeBSD 15:
`pfSense-pkg-layer7-1.8.11_26.pkg`,
`SHA256=c536cf879721d3bfad0097df9cf9f5ee45f217738c80ceaed9568acaf88b2f69`.

### Added

- `/var/log/layer7-events.log` separado do operacional
  `/var/log/layer7d.log`.
- Rotação interna limitada para ambos: 5 MiB e três cópias por default,
  configuráveis na GUI.
- Limite do SQLite de relatórios: 100 MiB por default.
- Painel de consumo dos três armazenamentos e fontes separadas na página
  Eventos.
- Testes `test_log_store.c` e `test_logging_reports.php`.

### Changed

- Detalhe de tráfego passa a opt-in (`event_log_enabled=false` quando ausente);
  bloqueios continuam auditados mesmo com o detalhe desligado.
- Idle, recheck de licença sem transição, SIGUSR1 e falhas esperadas ao limpar
  tabelas opcionais deixam de poluir `info`.
- Stats continuam actualizadas a cada minuto; resumo operacional no máximo
  uma vez por hora.
- Colector atravessa ficheiros rotacionados por inode antes de continuar no
  activo; retenção detalhada default passa a 7 dias.
- `enforce_block` confirma aplicação PF sem duplicar o KPI de bloqueio.
- “Limpar visualização” não afirma apagar disco; “Limpar histórico” informa
  que limpa SQLite/cursores e preserva os logs rotativos.

### Gates e rollback

- Suite local, lint, PHP/SQLite isolado e build `.pkg`: **PASS**.
- Instalação e observação no appliance: pendentes; nenhuma mudança foi
  aplicada ao pfSense de produção.
- Rollback: reinstalar `_24` em modo passivo e restaurar o JSON anterior; não
  apagar evidência de logs antes da recolha.

---

## [1.8.11_25] - Unreleased — candidato de estabilização antes do gate

Pacote candidato, **não publicado** e ainda **não aprovado para produção**.
Build isolado no FreeBSD 15 concluído com
`SHA256=c4e9c197f79ad00d7ddb68f8ececcd391455e86011e558596102877c325d388d`.
Nasce do diagnóstico read-only feito em `2026-07-29` no appliance
`192.168.100.254`, onde `_24` estava instalado, mas intencionalmente
`enabled=false`, `mode=monitor`.

### Fixed

- **rc.d / PID sem newline:** `daemon(8)` grava `/var/run/layer7d.pid` sem
  newline; `read` preenchia o PID mas retornava erro. `status` dizia
  indevidamente “not running” e `reload` podia iniciar outra instância.
- **Interface real de captura:** IDs amigáveis (`lan`, `optN`) passam por
  `get_real_interface()` antes de chegar a libpcap/PF. Upgrade migra também
  interfaces de políticas, excepções, anti-QUIC e relatórios.
- **Scoped `psrc`:** origem estática, `scope_global` ou
  `quarantine_origin` autorizam a inclusão dinâmica da origem em
  `layer7_psrc_N`; quarentena explícita agora emite regra PF executável.
- **App+host híbrido:** o tipo de enforcement segue o critério que realmente
  casou: app/categoria usa `psrc`; host/SNI/DNS usa `pdst`.
- **Validação GUI:** em `scoped_hybrid`, políticas block sem origem,
  `scope_global` ou quarentena são recusadas. O toggle de perfil em um clique
  não cria escopo global implícito.

### Added

- Regressões `test_rc_pidfile.sh`, `test_interface_normalization.php` e casos
  adicionais em `test_policy_decide.c` / `test_scoped_pf_inc.php`.

### Gates e rollback

- Gates locais/PHP e build do pacote no builder FreeBSD: PASS.
- Instalação e gate appliance two-client: **PENDENTES**.
- O appliance de destino é pfSense Plus `26.03.1` / FreeBSD `16.0-CURRENT`,
  enquanto o builder gera ABI FreeBSD 15; compatibilidade real faz parte do
  gate e continua sem declaração de suporte geral.
- Rollback do candidato: reinstalar a release pública `_24`, manter
  `enabled=false`/`mode=monitor` e confirmar tabelas dinâmicas vazias.

---

## [1.8.11_24] - 2026-06-16 — Caminho B E0–E3 + estabilização pós-revisão

Release publica. Artefacto `pfSense-pkg-layer7-1.8.11_24.pkg`
(`SHA256=1d5573f0a0c7803a87d8cb536ad9eee43e85daa9bf98bf7edc84ef554e2c7818`),
build no builder FreeBSD (`192.168.100.12`). Consolida o **Caminho B E0–E3**
(enforcement escopado por política) e correcções da revisão pré-instalação de
2026-06-15. Testes locais (`tests/run-local.sh`) PASS; **gate two-client no
appliance** (`validacao-lab.md` sec. 12) continua **PENDENTE**.

### Caminho B / E0–E3 — enforcement escopado (`PORTREVISION=24`)

#### Added

- **E0 (BG-045):** `layer7.enforcement_model` — `legacy_global` (default) |
  `scoped_hybrid` (experimental); parse em `config_parse`; selector em Settings.
- **E1 (BG-046):** `layer7_decide_for_client()` unifica decisão DNS/fluxo;
  `struct layer7_decision` com `enforce_kind`, `policy_table_idx`,
  `scope_global`, `quarantine_origin`; testes em `test_policy_decide.c`.
- **E2 (BG-047):** regras PF escopadas em `layer7.inc` (`layer7_pdst_N` /
  `layer7_psrc_N`); `scope_global` JSON+GUI; `test_scoped_pf_inc.php`.
- **E3 (BG-048):** runtime daemon popula `pdst_N`/`psrc_N` (não
  `layer7_block_dst` em scoped); cache TTL por `(table, ip)`;
  `test_enforce_scoped.c`.

#### Fixed (pós-revisão pré-instalação)

- **REV-002:** licença inválida no recheck horário chama
  `enforce_ge_downgrade()` → `enforcement_flush_all_tables()` (sem bloqueio PF
  residual).
- **REV-003:** allowlist rejeita CIDR `/0` (`prefix < 1`) em parse e em
  `l7_allowlist_contains_ip()`.
- **REV-015 (parcial):** mudança de `enforcement_model` incluída em
  `pf_relevant_changed` → `filter_configure()` + flush dinâmico.
- **REV-016 (parcial):** `layer7_pf_config_resync()` após saves de
  políticas, grupos, excepções e dispositivos → `filter_configure()` + SIGHUP.
- **Allowlist PF:** `layer7_dst_allowlist_apply_to_pf()` repovoa
  `layer7_allow_dst` no resync quando enforce activo (flush + adds estáticos).
- **DNS disabled:** `layer7_on_dns_resolved()` respeita `cfg_disabled()`
  (`enabled=false`), alinhado a fluxos nDPI.
- **`quarantine_origin`:** parse JSON/GUI + decisão app-only com quarentena de
  origem (`psrc_N`) em scoped.
- **`scope_global`:** parse no daemon; políticas block vazias exigem
  `scope_global` ou `quarantine_origin` (rejeição/warning coerente PHP+daemon).
- **`except_ips` (blacklists):** `l7_bl_rule_matches_src()` exclui IPs em
  `except_ips`; teste `test_bl_src_match.c`.
- **TTL blacklist:** adds DNS/SNI a `layer7_bld_N` passam por
  `enforce_cache_add()` / sweep (TTL clamped ≥60s).

#### Notes

- `legacy_global` permanece **default** — imposição global por destino
  (`layer7_block_dst`) é comportamento intencional até gate E8 (**REV-001 by
  design**).
- `scoped_hybrid` é **experimental**; não activar em produção sem gate
  two-client (sec. 12) e validação lab.
- E4–E8 (BG-049..BG-052) permanecem pendentes.
- Rollback = `1.8.11_23` (`v1.8.11_23`).

---

## [1.8.11_23] - 2026-05-30 — Caminho A completo (A0–A5)

Release publica que consolida todo o **Caminho A** (UX e eficacia tipo UDM Pro)
sobre a base estavel da Fase 1 (`1.8.11_18`): perfil GitHub e alinhamento de
limites (A0), inventario de dispositivos (A1), politicas por dispositivo
MAC->IP (A2), bloqueio por SNI/Host via nDPI opt-in (A3), UX de perfis com
toggle on/off e contadores (A4) e suite de regressao do Caminho A (A5).
Artefacto `pfSense-pkg-layer7-1.8.11_23.pkg`
(`SHA256=3c9e488d48c441a9859a1d953b603e9cecb242fc9d2e93ce144e05cdacb8d7d4`).
Validado no appliance: `smoke-monitor-mode.sh` e `smoke-caminho-a.sh` exit 0;
toggle de perfil cria/remove politica que o daemon carrega; SNI a alimentar
`flow_decide`. Sem MITM; monitor continua passivo (gate da Fase 1 intacto);
limitacao honesta: TLS 1.3 ECH cifra o SNI.

### Caminho A / A4 + A5 — UX tipo UDM e F5 alargada (`PORTREVISION=23`)

UX de perfis com toggle directo e contadores (BG-043) + suite de regressao do
Caminho A (BG-044).

#### Added (A4 — UX)

- **Toggle on/off directo por perfil** nos "Perfis rapidos" (Politicas): um
  clique liga (cria politica `profile-<id>`, accao block — em monitor fica
  apenas observado) ou desliga (remove a politica). O modal "Opcoes" mantem-se
  para escolha de accao/interfaces/sub-redes/grupos.
- **Estado visual por perfil** (ponto verde Ligado / cinza Desligado, moldura
  verde quando activo) e **contador de hits por perfil** a partir das stats do
  daemon (`top_apps_blocked`), via novo `layer7_profile_hit_counts()`.
- **Top clientes bloqueados** (Estado) agora mostra o **nome/alias do
  dispositivo** (inventario A1) ao lado do IP de origem.
- Traducoes EN das novas strings.

#### Added (A5 — testes/F5 alargada)

- `tests/functional/test_config_parse.c`: teste unitario do parser do daemon,
  cobrindo `sni_inspection` antes/depois de `policies` (regressao do bug do A3),
  `false`, ausente, e `enabled`/`mode`. Ligado ao `tests/run-local.sh`.
- `tests/lab/smoke-caminho-a.sh`: suite de regressao do Caminho A no appliance
  (A0 perfis+github, A1 inventario, A2 helpers MAC->IP, A3 parse sni, A4
  contadores), read-only de enforcement.

#### Notes

- `PORTREVISION` -> `23`. A4 reutiliza a estrutura de politicas existente (o
  toggle apenas cria/remove `profile-<id>`); rollback = desligar o perfil ou
  remover a politica. Sem alteracao do daemon em A4 (so PHP/GUI). A5 nao altera
  o produto (apenas testes).

### Caminho A / A3 — bloqueio por SNI/Host via nDPI (em curso, `PORTREVISION=22`)

Eficacia tipo UDM contra CDNs e DNS cifrado/cache (BG-042). Decisao em
`docs/03-adr/ADR-0013-bloqueio-por-sni-via-ndpi.md`.

#### Added

- Toggle **`sni_inspection`** (opt-in, OFF por defeito) em Definicoes. Quando
  ligado, o daemon usa o **SNI (TLS)** / **Host (HTTP)** que o nDPI ja extrai
  (`flow->host_server_name`) como host para matching de politicas, preferido
  sobre o DNS reverso, e alimenta a cache de hints por IP de destino.
- `capture.c/.h`: `layer7_capture_set_sni()` + validacao `sni_host_plausible()`.
- `config_parse.c/.h`: parsing de `sni_inspection`. `main.c` aplica o flag a
  cada captura (e no reload SIGHUP, que reabre capturas).
- `layer7.inc` (bare_config) + `layer7_settings.php`: toggle e persistencia.

#### Notes

- `PORTREVISION` -> `22`. **Sem parser TLS proprio** (reutiliza o do nDPI) e
  **sem MITM/decifragem**. Continua passivo e por destino
  (`layer7_block_dst`). Limitacao honesta: TLS 1.3 **ECH** cifra o SNI. Default
  inalterado (opt-in) para previsibilidade.

#### Fixed

- Parsing de `sni_inspection` no daemon nao podia depender do gate
  `< "policies"` (a GUI grava a chave depois de `policies` no JSON). Removido o
  gate; bug apanhado em validacao no appliance.

#### Validacao no appliance (`1.8.11_22`, `SHA256=4f0d42b5f8f9b3ddcda297477149b58b4d18e0d29673b0671c27bec6d6b1302c`)

- `capture: opened vmx0 (nDPI active, sni_inspection=1)` (flag aplicado);
- debug de `flow_decide` mostrou host extraido em uso, ex.:
  `host=pfs-monitor.systemup.inf.br ... reason=policy_match` (SNI/Host via nDPI
  a alimentar o motor de politicas);
- `smoke-monitor-mode.sh` exit 0 (gate de monitor intacto); default `sni=off`.

### Caminho A / A2 — politicas por dispositivo (em curso, `PORTREVISION=21`)

Regras por dispositivo estilo UDM "client rules" (BG-041). Decisao em
`docs/03-adr/ADR-0012-politicas-por-dispositivo-mac-para-ip.md`.

#### Added

- Grupos aceitam **dispositivos por MAC** (`device_macs`). O pacote resolve
  MAC -> IP actual (DHCP leases online + ARP) e grava `device_ips`; o daemon
  le `device_ips` como hosts de origem do grupo (`policy.c parse_group`),
  retrocompativel. Imposicao continua por IP em PF.
- GUI Grupos: campo "Dispositivos (MAC)" em adicionar/editar, coluna com
  contagem dispositivos -> IPs, e botao **"Resync IPs dos dispositivos"**
  (`layer7_devices_resync()`).
- GUI Dispositivos: checkboxes + **"Atribuir selecionados a grupo"** (fluxo
  natural de associar clientes a grupos).
- `layer7.inc`: `layer7_resolve_macs_to_ips()`, `layer7_normalize_macs()`,
  `layer7_devices_resync()`.

#### Changed

- `L7_MAX_GROUP_HOSTS` e `L7_MAX_SRC_HOSTS` `16 -> 64` (acomodar uma turma de
  dispositivos por grupo/origem). Para escala maior usar grupo por CIDR.

#### Notes

- `PORTREVISION` -> `21`. Fail-safe: grupo so com dispositivos offline nao gera
  hosts (nao bloqueia o que nao localiza). Drift de IP dinamico mitigado por
  resync + recomendacao de DHCP static mapping.
- Validacao no appliance (`1.8.11_21`,
  `SHA256=5e0789dab274a756ea6da0c1fbc493a343789ffad4d3cc481cc5d1d18611ba21`):
  MAC `7c:aa:de:4a:5e:8d` -> IP `10.0.85.89` resolvido e gravado em
  `device_ips`; daemon carregou `policies=1` sem erro de parse (grupo com
  `device_ips` aceite); regras PF de enforce presentes; `smoke-monitor-mode.sh`
  exit 0 (gate de monitor intacto). Nota: o enforce ao vivo e **license-gated**
  e este appliance de teste nao tem `layer7.lic` (`valid=0`), logo corre
  monitor-only; o pipeline de configuracao/parse/PF foi validado.

### Caminho A / A1 — inventario de dispositivos (em curso, `PORTREVISION=20`)

Base de identidade tipo UDM (BG-040). Decisao em
`docs/03-adr/ADR-0011-fonte-de-identidade-de-dispositivo.md`.

#### Added

- Nova pagina GUI **Services > Layer 7 > Dispositivos** (`layer7_devices.php`),
  **read-only** (so o alias e editavel): lista IP, MAC, hostname, fabricante
  (OUI), interface, estado online e fonte.
- `layer7.inc`: `layer7_device_inventory()` combina `system_get_dhcpleases()`
  (ISC+Kea) com a tabela ARP (`arp -an`), enriquece com vendor OUI (best-effort,
  unica passagem) e alias por MAC.
- Alias persistente do operador em `layer7.json` (`device_aliases`, MAC->alias);
  **ignorado pelo daemon** (estritamente observacional). Item no nav "Dispositivos".

#### Notes

- A1 **nao altera enforcement** (so observa). Base para A2 (politicas por
  dispositivo). `PORTREVISION` -> `20`. Limites honestos: so dispositivos
  adjacentes L2; MAC pode ser aleatorizado; vendor depende de base OUI no sistema.
- Build + validacao no appliance (`1.8.11_20`,
  `SHA256=ae02b1abb7d48a6bac8a792fb770a20c9dc28ca3a9f0d1c2bbd022f1b545621b`):
  `layer7_device_inventory()` devolveu 470 dispositivos (469 com MAC, 230 com
  fabricante OUI, 29 hostname); alias save/load/remove OK; `smoke-monitor-mode.sh`
  exit 0 (sem regressao).

### Caminho A / A0 — higiene (em curso, `PORTREVISION=19`)

Primeiro bloco do Caminho A (UX/eficacia tipo UDM Pro). Quick wins de baixo
risco; plano em `docs/09-blocking/caminho-a-plano-de-implementacao.md`
(BG-039).

#### Added

- Perfil **GitHub** em `profiles.json` (estava prometido no plano mestre mas
  ausente): `ndpi_apps=[GitHub]` + hosts (github.com, api/codeload/raw,
  githubusercontent/assets, github.io, ghcr.io, copilot). Total de perfis: 18.

#### Fixed

- **Limite de hosts por politica alinhado em 64** (eliminado truncamento
  silencioso): daemon `L7_MAX_HOSTS_PER_POLICY` `32 -> 64` (`policy.h`);
  formulario manual da GUI passava o default `16` ao `layer7_parse_host_textarea`
  — agora passa `64` nos quatro pontos de match de hosts (`layer7_policies.php`);
  aplicacao de perfil ja usava `64`. Texto de ajuda da GUI indica o limite.
- **Docs:** `docs/05-daemon/pf-enforcement.md` clarifica no topo que
  `action=block` em runtime bloqueia o **destino** (`layer7_block_dst`), nao a
  origem; `tag` e o caminho `-e` e que usam origem.

#### Notes

- `PORTREVISION` -> `19`. Build no builder FreeBSD concluido
  (`SHA256=a89f280714b984ad1dec8823185c18d7d1b73c37e45aafc76d33171e160945bb`),
  instalado e validado no appliance (`layer7d -V=1.8.11_19`, 18 perfis com
  `github`, `smoke-monitor-mode.sh` exit 0). Release publica do Caminho A sera
  agrupada num milestone (alvo: apos A2, primeiro incremento com enforcement
  por dispositivo), para evitar churn de releases por bloco. Builds `_19`/`_20`
  ja validados no appliance e em `main`.

## [1.8.11_18] - 2026-05-30

### Released

- **`pfSense-pkg-layer7-1.8.11_18.pkg`** em
  `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_18`
  (`SHA256=98374806be31094a3835bcae0c96164369860aef82db3bfb4255f44c9d60b876`).
  Build no builder FreeBSD `192.168.100.12`; **validado no appliance pfSense
  Plus 26.03** (`192.168.100.254`) com `tests/lab/smoke-monitor-mode.sh`
  (exit 0), incluindo enforce, transicao enforce->monitor e CLI
  `--license-status`.

Fase 1 da estabilizacao da V1 comercial — corrige os erros que provocavam
bloqueio indevido em modo monitor (incluindo bancos/servicos) e adiciona a
primeira camada de allowlist de destinos.

#### Fixed (Bloco 1) — monitor e monitor de verdade

- `layer7.inc`: `layer7_pf_default_rules_text()` agora emite **apenas as
  tabelas** `persist` quando `enabled=false` ou `mode!=enforce`; nenhum
  `block drop` do core e injectado em modo passivo. Causa-raiz do
  "bloqueia bancos em monitor".
- `layer7_generate_rules()` suprime anti-QUIC, blacklists e injeccao no
  anchor NAT em modo passivo, e limpa `natrules/layer7_nat` para nao deixar
  forcing de DNS residual.
- `layer7_settings.php`: `filter_configure()` passa a ser disparado tambem
  quando mudam `mode`, `enabled` ou `block_dot_doq` (nao so QUIC).
- Novo helper `layer7_pf_should_enforce($data)` — gate auditavel unico.
- **Correccao apanhada na validacao do appliance:** `layer7_generate_rules()`
  retornava `layer7_pf_rules_text()`, que prefere o `pf.conf` em disco
  (escrito por `layer7-pfctl write_rules()` com os blocks sempre presentes),
  contornando o gate. Agora, em modo passivo retorna so `layer7_pf_tables_text()`
  e, em enforce, constroi a partir de `layer7_pf_default_rules_text()`
  (mode-aware) em vez do `pf.conf` em disco. Smoke no appliance confirmou
  0 `block drop` em monitor.

#### Changed (Bloco 2) — anti-bypass como toggle, OFF por defeito

- Anti-DoT/DoQ (porta 853) passa a ser **toggle explicito** `block_dot_doq`
  em **Settings > Servico**, desligado por defeito. Antes era injectado
  incondicionalmente, podendo quebrar "DNS privado" em Android / apps
  moveis. Anti-QUIC ja era opcional por interface; defeito mantido OFF.
- Sample `pf.conf.sample` actualizado para o novo layout.

#### Added (Bloco 3) — allowlist de destinos

- Novo campo `layer7.dst_allowlist[]` em `layer7.json` (dominios, IPv4 host
  ou CIDRs IPv4) + lista-semente embutida em
  `/usr/local/etc/layer7/allowlist-seed.txt` (bancos BR, gov, pagamentos,
  push Apple/Google, Microsoft 365). Editor em
  **Services > Layer 7 > Allowlist** (`layer7_allowlist.php`).
- Novo modulo daemon `allowlist.{c,h}` — antes de adicionar IP a
  `layer7_block_dst` ou `layer7_bld_N`, o `layer7d` verifica se o
  dominio/IP esta na allowlist. Em DNS hint, popula `layer7_allow_dst`
  com o IP resolvido.
- Nova tabela PF `layer7_allow_dst` + regra `pass quick inet to
  <layer7_allow_dst>` emitida **antes** de qualquer `block drop`.
- 24 testes unitarios em `tests/functional/test_allowlist.c` (todos PASS).

#### Fixed (Bloco 5) — flush fiavel de tabelas dinamicas

- `dst_cache_flush()` reforcado com `pfctl -T flush` defensivo no fim.
- Nova `enforcement_flush_all_tables()` no daemon: limpa
  `layer7_block_dst`, `layer7_block` e `layer7_bld_*` na transicao
  enforce -> passivo (via SIGHUP) e no shutdown limpo (SIGTERM).
- `rc.d/layer7d stop` chama `layer7-pfctl flush-all` como defesa em
  profundidade caso o daemon seja morto com SIGKILL.
- `layer7_resync()` flush automatico das tabelas dinamicas quando o
  pacote esta em modo passivo (`layer7_flush_dynamic_tables()`).

#### Fixed (Bloco 6 / BG-032) — CLI `--license-status`

- `layer7d --license-status` impressao em `chave=valor` (compativel com
  `awk -F=`), exit `0` se valida (inclui grace) e `1` caso contrario.
  Sai sem inicializar nDPI/captura.

#### Added (F5 minima)

- `tests/functional/test_allowlist.c` (24 casos, todos PASS local).
- `tests/lab/smoke-monitor-mode.sh` — smoke para o appliance pfSense
  validar "monitor nao bloqueia, tabelas vazias, daemon vivo".
- `tests/run-local.sh` — runner local (`cc` + `php -l` + `sh -n`).

#### Notes

- **PORTREVISION** -> `18`. Build no builder FreeBSD (`192.168.100.12`),
  caminho oficial (`AGENTS.md > Fluxo de build padrao`). Validacao real no
  appliance pfSense Plus 26.03 (`192.168.100.254`) com instalacao via
  `IGNORE_OSVERSION=yes pkg add -f` (builder FreeBSD 15 vs appliance 16):
  matriz `monitor` / `enforce` / transicao / CLI licenca toda em PASS.
- A allowlist e a base para a Fase 2 (Caminho A — UX tipo UDM Pro, listas
  selecionaveis, identificacao por dispositivo), que so arranca depois
  desta release validada (gate documental em `docs/02-roadmap/`).

## [1.8.11_17] - 2026-04-27

### Released

- **`pfSense-pkg-layer7-1.8.11_17.pkg`** em
  `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_17`
  (`SHA256=787fcad80f00c085040a38745cf55ccf5870261f5d3ebc762f8ab643c3d81735`).
  Commit: `0b9717e`.

### Fixed

- **`layer7_removal.php`:** erro de sintaxe PHP (`unexpected token "<<"`) — o
  nowdoc estava escrito como `<<'EOSH'` em vez de `<<<'EOSH'`. O script shell
  embutido passou a ser gerado com `implode()` (sem heredoc), para a pagina
  **Remocao do pacote** voltar a carregar na GUI.

## [1.8.11_16] - 2026-04-27

### Released

- **`pfSense-pkg-layer7-1.8.11_16.pkg`** publicado em
  `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_16`
  (`SHA256=a46e710692b466a6a5573d38c42cc686eb1a6bd4fc93684f288147dd96402425`).
  Commit de referencia do port: `b3e0ccb`.

### Added — remocao completa do pacote (GUI + hooks)

- **Nova pagina** `layer7_removal.php` (**Services > Layer 7 > Removal**):
  desinstalacao com confirmacao **REMOVER**, opcoes para preservar
  `layer7.lic` e/ou `layer7.json`, e `pkg delete` em segundo plano (com
  `flush-all` antes do delete quando o helper existe).
- **`pkg-deinstall`**: PRE remove cron BL/relatorios; POST `filter_configure`,
  flush `layer7_*` / `layer7_bld_*` / `layer7_bl_except`, residuos e
  `/usr/local/etc/layer7`.
- **`layer7-pfctl flush-all`**; **`uninstall.sh`** chama `flush-all` quando
  disponivel. **Backlog:** `BG-033`.

### Changed

- **`rc.d/layer7d`**: `layer7d_stop()` com TERM + `pkill` TERM/KILL (`BG-031`).
- **`Makefile`**: `PORTREVISION` `16`; `do-install` inclui `layer7_removal.php`
  no stage (build `make package`).

## [1.8.11_14] - 2026-04-24

### Released

- **`pfSense-pkg-layer7-1.8.11_14.pkg`** publicado em
  `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_14`
  (`SHA256=f9fb1217780bfb90e83821c2652d7177d92eaf5b83f3dfa1fe29d85eaf284705`).
  Hotfix do **GUI updater** sobre `1.8.11_13`. **Sem alteracao de logica
  de bloqueio** (PF, nDPI, force_dns, anti-QUIC, blacklists), **sem
  rotacao de chave** (a chave Ed25519 de blacklists embutida e a mesma
  da `1.8.11_13`, fingerprint
  `6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`),
  **sem republicacao de snapshot UT1** (a snapshot publicada em
  `pablomichelin/Layer7 / blacklists-ut1-current` continua valida e e
  aceite por esta release).
- O trust chain F1.2 do **pacote** continua **nao activado** (`BG-028`);
  esta release publica apenas `.pkg` + `.pkg.sha256` (mesmo padrao de
  `v1.7.8` a `v1.8.11_13`).

### Fixed — GUI "Verificar actualizacao" entrava em loop em `1.8.11_13`

Sintoma observado em `1.8.11_13`: clicar **Verificar actualizacao**
mostrava `latest=1.8.11_13` mas `Versao instalada=1.8.11`, oferecia o
update, o `pkg add -f` reinstalava o mesmo `.pkg`, o daemon reiniciava
com banner `1.8.11`, e o ciclo recomecava. Causa raiz: o `version.str`
gerado pelo Makefile do port estava a usar apenas `${PORTVERSION}` (sem
`${PORTREVISION}`), pelo que `layer7d -V` ficou eternamente preso em
`1.8.11`; o updater do GUI usava esse banner como fonte de "versao
instalada" e comparava-o contra a tag GitHub `v1.8.11_13`.

### Changed — `pfSense-pkg-layer7` (`1.8.11_14`)

- **`package/pfSense-pkg-layer7/Makefile`**
  - `PORTREVISION` `13` -> `14`.
  - `do-build`: `version.str` passa a conter `${PKGVERSION}` em vez de
    `${PORTVERSION}` (= `PORTVERSION_PORTREVISION`, formato canonico do
    `bsd.port.mk` ja usado para `info.xml` e `layer7.xml` na linha 137).
    Resultado: `layer7d -V` passa a imprimir a versao real do pacote
    (ex.: `1.8.11_14`).
- **`files/usr/local/pkg/layer7.inc`**
  - nova `layer7_pkg_version()` — devolve
    `pkg query %v pfSense-pkg-layer7` (fonte canonica do pkg manager
    pfSense). E a unica funcao em que o updater do GUI passa a confiar
    para "versao instalada"; o banner do daemon
    (`layer7_daemon_version()`) fica como fallback cosmetico.
- **`files/usr/local/www/packages/layer7/layer7_settings.php`**
  - `check_update`: `current` passa a vir de `layer7_pkg_version()`
    (com fallback para `layer7_daemon_version()`); o display mostra a
    versao do pkg, e exibe o banner do daemon entre parenteses *so se
    divergir* da versao do pkg.
  - `do_update`: a mensagem verde de sucesso passa a usar
    `layer7_pkg_version()` (no caminho antigo, devolvia o banner que
    nao tinha sido recompilado).
  - **Defesa em profundidade (`BG-030`):** o updater **ignora** releases
    cujo `tag_name` nao case com `/^v?\d+\.\d+/` (ex.:
    `blacklists-ut1-current`), mesmo que o GitHub as devolva como
    `latest` por engano. Reforca a convencao operacional registada na
    `1.8.11_13` (releases nao-pacote sao publicadas como `prerelease`).
- **`files/usr/local/etc/layer7/lang/en.php`**
  - novas keys `daemon` e
    `Release mais recente nao e uma versao do pacote (tag ignorada): `;
    `pt` continua como lingua base.

### Backlog — atendidos

- **`BG-030`** marcado como **Concluido em `1.8.11_14`** (ver
  `docs/02-roadmap/backlog.md`).

### Documentation — release `1.8.11_14`

- **`docs/06-releases/release-notes-1.8.11_14.md`** — notas dedicadas.
- **`docs/10-license-server/MANUAL-INSTALL.md`** — links e comandos das
  seccoes **1**/**4**/**5**/**12** actualizados para `1.8.11_14`;
  novo addendum operacional desta release. A seccao **11b** (activar
  blacklists UT1) continua valida sem alteracao porque a chave nao
  rodou.
- **`CORTEX.md`** — `Ultima versao do pacote publicada em release` passa
  a `1.8.11_14`; checkpoint canonico actualizado.
- **`docs/02-roadmap/backlog.md`** — `BG-030` Concluido.

## [1.8.11_13] - 2026-04-24

### Released

- **`pfSense-pkg-layer7-1.8.11_13.pkg`** publicado em
  `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_13`
  (`SHA256=041e1ace4611ebb1cebd7bfadc22e0bb2c9b2b24b99900e3034f107b534351ae`).
  Esta release publica apenas `.pkg` + `.pkg.sha256` (mesmo padrao de
  `v1.7.8` a `v1.8.11_12`); o trust chain F1.2/F1.4 do **pacote**
  (`release-manifest`/`install.sh` carimbado) **continua nao activado**
  (gate `BG-028`).
- **Primeira publicacao real da trilha F1.3 (blacklists assinadas).**
  Release rolling `blacklists-ut1-current` em
  `https://github.com/pablomichelin/Layer7/releases/tag/blacklists-ut1-current`
  com `layer7-blacklists-manifest.v1.txt` (823 B),
  `layer7-blacklists-manifest.v1.txt.sig` (64 B),
  `blacklists-signing-public-key.pem` (113 B) e
  `layer7-blacklists-ut1.tar.gz` (31 169 229 B,
  `SHA256=4191e2ebdc13e3c87d777103528bab4fda6b273bc40c62a2c39cb820ad493d36`,
  `snapshot_id=ut1-2026-04-25`, 69 categorias, 6 623 069 dominios). Upstream
  (autoridade de conteudo): UT1 / Universite Toulouse Capitole
  (`https://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz`).
- Comportamento `update-blacklists.sh`: **so aceita** snapshots assinadas
  pela chave embutida na `1.8.11_13` (fingerprint
  `6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`); os
  pacotes anteriores recusam este manifesto por fingerprint mismatch
  (`fail-closed` F1.4, comportamento correcto).

### Changed — `pfSense-pkg-layer7` (`1.8.11_13`)

- **`package/pfSense-pkg-layer7/Makefile`** — `PORTREVISION=13`.
- **`package/pfSense-pkg-layer7/files/usr/local/share/pfSense-pkg-layer7/blacklists-signing-public-key.pem`**
  — chave publica Ed25519 rotacionada de
  `e501f5635bf56c6dfc6891ee969ef04ff193ed3afc879997bd4066b6ba3cb064` para
  `6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`. A
  chave anterior nunca foi usada para assinar uma snapshot publica; a
  rotacao e gratuita e nao afecta nenhuma instalacao em campo. A **chave
  privada** correspondente ficou em custodia humana, fora do builder e fora
  do repositorio (alinhado com F1.3 / `AGENTS.md`).

### Documentation — release `1.8.11_13`

- **`docs/10-license-server/MANUAL-INSTALL.md`** — actualizado com **Links
  da versao actual** `1.8.11_13`, comandos `fetch + pkg add -f` para Command
  Prompt nas seccoes **1** (instalar), **4** (upgrade), **5** (reinstalar),
  **6** (desinstalar manual). Adicionado novo addendum operacional da
  release `1.8.11_13` (rotacao chave F1.3) e nova **seccao 11b: activar
  blacklists UT1 apos `1.8.11_13`**.
- **`docs/06-releases/release-notes-1.8.11_13.md`** — notas dedicadas a esta
  release.
- **`docs/02-roadmap/backlog.md`** — observacoes na **BG-020/BG-022**: F1.3
  passou a estar **realmente activa** com primeira snapshot publica
  assinada.
- **`CORTEX.md`** — **Ultima versao do pacote publicada em release** passa
  para `1.8.11_13`; checkpoint canonico actualizado.

## [1.8.11_12] - 2026-04-24

### Released

- **`pfSense-pkg-layer7-1.8.11_12.pkg`** publicado em
  `https://github.com/pablomichelin/Layer7/releases/tag/v1.8.11_12`
  (`SHA256=902736db23fc94ae5f52d9aeaf71fcf5e75c723799209b55e5e51dcb00138dc7`).
  Esta release publica apenas `.pkg` + `.pkg.sha256` (mesmo padrao de
  `v1.7.8` a `v1.8.3`); o trust chain F1.2/F1.4 (manifesto assinado +
  `install.sh` carimbado fail-closed) nao esta activo nesta release. Ver
  `docs/02-roadmap/backlog.md` **BG-028** para activacao formal num bloco
  futuro com ADR.
- **`docs/10-license-server/MANUAL-INSTALL.md`** — actualizado com **Links
  da versao actual** `1.8.11_12`, comandos `fetch + pkg add -f` para Command
  Prompt nas seccoes **1** (instalar), **4** (upgrade), **5** (reinstalar),
  **6** (desinstalar manual), **12** (rollback). Adicionado **Addendum
  operacional pos-upgrade** com `/etc/rc.filter_configure_sync` para garantir
  que as regras `block drop quick` da trilha Layer7 entram em
  `/tmp/rules.debug` apos `pkg add`.

### Changed — `pfSense-pkg-layer7` (`1.8.11_12`)

- **`package/pfSense-pkg-layer7/Makefile`** — `PORTREVISION=12`.
- **`layer7.inc`** — anti-QUIC por interface: validação de nome com
  `layer7_pf_ifname_for_rules()` em vez de regex duplicada em
  `layer7_generate_rules()` (DRY; sem alteração de comportamento). Docblock da
  função actualizado.

### Documentation — anti-QUIC e `layer7_pf_ifname_for_rules`

- **`docs/05-daemon/pf-enforcement.md`** — secção **Anti-QUIC por interface**
  (`layer7_generate_rules`, DRY `1.8.11_12`).

### Documentation — arquitectura alvo, README do port e índice `docs/`

- **`docs/01-architecture/target-architecture.md`** — item enforcement: pacote,
  NAT `force_dns`, anti-QUIC, `layer7_pf_ifname_for_rules`; ligação a
  `pf-enforcement.md`.
- **`package/pfSense-pkg-layer7/README.md`** — tabela: papel de `layer7.inc` na
  geração PF.
- **`docs/README.md`** — área Releases: DRAFT vs port no branch / `CORTEX`.

### Documentation — governança F4.3 / BG-011

- **`docs/02-roadmap/backlog.md`** — observações **BG-011**: `_11`/`_12` e DRY
  explícitos; docs de enforcement/arquitectura.
- **`docs/02-roadmap/checklist-mestre.md`** — gate F4.3: anti-bypass inclui
  anti-QUIC e referência ao port em branch.

### Documentation — roteiro lab F4.3 (anti-QUIC opcional)

- **`docs/04-package/validacao-lab.md`** — gates, índice F4, checklist #13 e
  secção **11**: evidência opcional `pfctl -s rules` / labels `layer7:anti-quic`;
  nota `1.8.11_12` / `layer7_pf_ifname_for_rules`.
- **`docs/tests/test-matrix.md`** — teste **6.7** alinhado ao mesmo critério.
- **`docs/tests/README.md`** — parágrafo da matriz: F4.3 inclui anti-QUIC.
- **`docs/04-package/checklist-validacao-lab.md`** — remissão à sec. **11**
  (anti-QUIC opcional).

### Documentation — SSOT F4.3 (CORTEX, roadmap, plano F4)

- **`CORTEX.md`** — pontos 7 e 10: sec. **11** com anti-QUIC opcional.
- **`docs/02-roadmap/roadmap.md`** — bloco F4.3 (doc lab) e *Seguinte*.
- **`docs/02-roadmap/f4-plano-de-implementacao.md`** — bloco documental F4.3 e
  teste mínimo.
- **`docs/02-roadmap/f5-preparacao-malha.md`** — pré-requisitos e docs vivas:
  sec. **11** com anti-QUIC opcional.

### Documentation — índices e addenda (sec. **11**, anti-QUIC opcional)

- **`docs/tests/test-matrix.md`** — parágrafo intro: sec. **11** com anti-QUIC.
- **`docs/tests/README.md`** — roteiros F4 / **6.7**: anti-QUIC na **11**.
- **`docs/05-runbooks/README.md`**, **`docs/04-package/deploy-github-lab.md`** —
  remissão ao `validacao-lab`.
- **`docs/00-overview/handoff-chat-novo.md`** — prompt F4.
- **`docs/08-lab/README.md`**, **`docs/08-lab/quick-start-lab.md`** — lab e
  passo **6** (F4).
- **`docs/05-daemon/pf-enforcement.md`** — evidência `force_dns`.
- **`docs/11-blacklists/PLANO-BLACKLISTS-UT1.md`** — addendum F4.3.
- **`docs/10-license-server/MANUAL-INSTALL.md`** — addendum F4.3 (roteiro **11**).
- **`docs/06-releases/release-notes-1.8.11_10-DRAFT.md`** — remissão à sec. **11**.
- **`docs/04-package/README.md`** — entrada `validacao-lab`: sec. **11**.

### Documentation — F4.3 remissões (checklist, backlog, scripts, DIRETRIZES)

- **`docs/02-roadmap/checklist-mestre.md`** — gate F4.3: anti-QUIC opcional na
  sec. **11**.
- **`docs/02-roadmap/backlog.md`** — **BG-011**: observações.
- **`docs/06-releases/release-notes-1.8.11_10-DRAFT.md`** — bloco de evidência F4.
- **`scripts/package/README.md`**, **`scripts/build/BUILDER.md`** — ligação aos
  roteiros **10a**–**11**.
- **`docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md`** — addendum F4.3
  normativo.

### Documentation — package-skeleton e CI (remissão F4)

- **`docs/04-package/package-skeleton.md`** — critério “pacote OK”: gates F4,
  matriz e checklist mestre.
- **`.github/workflows/smoke-layer7d.yml`** — comentário: não substitui roteiros
  **10a**–**11**.

### Documentation — builder, topologia, deploy e DRAFT (F4)

- **`docs/08-lab/builder-freebsd.md`** — após verificação mínima do port:
  não substitui roteiros F4 no appliance.
- **`docs/08-lab/lab-topology.md`** — trilha pós-topologia: gates F4 no link ao
  `validacao-lab`.
- **`docs/04-package/deploy-github-lab.md`** — próximos passos no pfSense.
- **`docs/06-releases/release-notes-1.8.11_10-DRAFT.md`** — checklist pré-publicação.

### Documentation — inventário de lab, quick-start, CI/tests e guia Windows

- **`docs/08-lab/lab-inventory.template.md`** — colunas de validação / gate F4.
- **`docs/08-lab/quick-start-lab.md`** — introdução e passo **6** (F4).
- **`docs/tests/README.md`** — limitações do workflow: não cobre **10a**–**11**.
- **`docs/08-lab/guia-windows.md`** (legado) — fonte vigente: `validacao-lab` com F4.

### Changed — `pfSense-pkg-layer7` (`1.8.11_11`)

- **`package/pfSense-pkg-layer7/Makefile`** — `PORTREVISION=11`.
- **`layer7.inc`** — em `layer7_generate_rdr_rules_snippet()`, o fallback quando
  `get_real_interface()` não preenche o nome reutiliza `layer7_pf_ifname_for_rules()`
  em vez de duplicar a regex (DRY; sem alteração de comportamento).

### Documentation — F4.3 / BG-011: roteiro VLAN multi-interface e rastreabilidade

- **`docs/04-package/validacao-lab.md`** (secção **11**) — cenário de lab sugerido
  **multi-interface / VLAN** para evidência de `force_dns` / `natrules/layer7_nat`.
- **`docs/tests/test-matrix.md`** — ponto **6.7** remete a esse parágrafo.
- **`docs/02-roadmap/f4-plano-de-implementacao.md`** — checkpoint documental
  (continuação) ligando `validacao-lab` §11 e matriz **6.7**.
- **`docs/10-license-server/MANUAL-INSTALL.md`** — addendum F4.3: remissão ao
  roteiro (secção **11**).
- **`docs/08-lab/quick-start-lab.md`** — passo **6** (F4): secção **11** com
  cenário opcional multi-interface / VLAN.
- **`docs/11-blacklists/PLANO-BLACKLISTS-UT1.md`** — addendum F4.3: mesma pista
  no roteiro de evidência.
- **`docs/04-package/README.md`** — índice: nota na entrada `validacao-lab`.
- **`docs/04-package/checklist-validacao-lab.md`**, **`docs/08-lab/README.md`**,
  **`docs/tests/README.md`**, **`docs/tests/test-matrix.md`** (intro),
  **`docs/00-overview/handoff-chat-novo.md`**,
  **`docs/00-overview/document-classification.md`**,
  **`docs/02-roadmap/roadmap.md`** — remissões / checkpoint F4.3 ao cenário
  opcional multi-interface / VLAN na secção **11** / teste **6.7**.
- **`docs/05-runbooks/README.md`**, **`docs/02-roadmap/checklist-mestre.md`**,
  **`docs/02-roadmap/f5-preparacao-malha.md`** — gates F4 / preparação F5 com a
  mesma pista (sec. **11** / VLAN opcional); **`validacao-lab.md`** checklist
  #**13** qualificado.
- **`docs/04-package/validacao-lab.md`** (topo, *Gates oficiais F4*) —
  qualificação da secção **11** / **BG-011**.
- **`docs/04-package/deploy-github-lab.md`** — referências: gates F4 e cenário
  VLAN opcional na **11**.
- **`docs/02-roadmap/backlog.md`** — **BG-011**, observações alinhadas ao roteiro.
- **`docs/05-daemon/pf-enforcement.md`** — secção **DNS forcado** (`force_dns`,
  anchor `natrules/layer7_nat`, remissões MANUAL / `validacao-lab` §11 / **6.7**).
- **`docs/05-daemon/README.md`** — bullet *Enforcement*: pista F4.3 / `force_dns`.

### Documentation — `scripts/package/README` (ordem canónica no índice)

- **`scripts/package/README.md`** — parágrafo inicial com check → smoke →
  `make package` e ligação a `builder-freebsd.md`.
- **`document-classification.md`** — linha `scripts/package/README.md`.

### Documentation — `lab-topology` e `src/layer7d/README` (ordem de build)

- **`08-lab/lab-topology.md`** — trilha builder: `check-port-files` antes de
  smoke e `make package`.
- **`src/layer7d/README.md`** — bloco *Pacote pfSense* + smoke; ligação ao
  README do port.
- **`document-classification.md`** — linha `src/layer7d/README.md`.

### Documentation — README do port e do daemon (ordem de build)

- **`package/pfSense-pkg-layer7/README.md`** — passo `check-port-files` antes do
  smoke e do `make package`.
- **`docs/05-daemon/README.md`** — secção *Build* alinhada a check → smoke →
  port, com `builder-freebsd.md`.
- **`document-classification.md`** — linha `package/pfSense-pkg-layer7/README.md`.

### Documentation — `scripts/release/README.md` (ADR-0003 + build frota)

- **`scripts/release/README.md`** — bloco inicial: repositório de desenvolvimento
  vs canal público `pablomichelin/Layer7` (ADR-0003); compilação na frota com
  `check-port-files` e `smoke-layer7d` antes de `make package`.
- **`document-classification.md`** — linha `scripts/release/README` actualizada.

### Documentation — `scripts/build/BUILDER.md` (ordem canónica)

- **`scripts/build/BUILDER.md`** — passo 5: `check-port-files` antes de smoke e
  `make package`; remissão a *Verificação mínima* em `builder-freebsd.md`.
- **`document-classification.md`** — linha `scripts/build/BUILDER.md` actualizada.

### Documentation — `AGENTS.md` (ponte para builder)

- **`AGENTS.md`** — *Dados do builder:* remissao a `docs/08-lab/builder-freebsd.md`
  (verificacao minima, smoke, SSH por chave).

### Documentation — `builder-freebsd` (verificacao + SSH)

- **`08-lab/builder-freebsd.md`** — seccao *Verificacao minima do port* (check,
  smoke, `make package`); nota macOS/CI; *Acesso SSH* (chave publica vs
  `publickey`).
- **`document-classification.md`** — linha `builder-freebsd` actualizada.

### Documentation — `quick-start-lab` (passo F4 após gate base)

- **`08-lab/quick-start-lab.md`** — passo **6** (*F4*): remissão a Gates,
  **10a/10b/11**, `test-matrix` e `checklist-mestre` após a sequência builder →
  `.pkg` → serviço.
- **`document-classification.md`** e **`08-lab/README.md`** — linha do
  quick-start alinhada ao passo 6 (F4).

### Documentation — runbooks e handoff (F4 / `validacao-lab`)

- **`05-runbooks/README.md`** — validacao em lab: *Gates oficiais F4* no
  inicio de `validacao-lab`.
- **`00-overview/handoff-chat-novo.md`** — prompt de continuacao: pista F4
  (gates + 10a/10b/11, `checklist-mestre`, `test-matrix`).

### Documentation — lab e `tests/README` (Gates F4 no `validacao-lab`)

- **`08-lab/README.md`** — remissão ao início de `validacao-lab` (*Gates
  oficiais F4*); tabela do `validacao-lab` ajustada.
- **`docs/tests/README.md`** — gate de pacote e roteiros F4 alinhados ao
  início de `validacao-lab` (Gates + índice 10a/10b/11).

### Documentation — F4: índice package + plano + checklist

- **`04-package/README.md`** — nota no item `validacao-lab` sobre o parágrafo
  *Gates oficiais F4*.
- **`f4-plano-de-implementacao.md`** — `checklist-mestre` e remissão ao início
  de `validacao-lab` nas referências obrigatórias.
- **`checklist-validacao-lab.md`** — bloco F4 alinhado ao início de
  `validacao-lab` (DRAFT / CORTEX quando aplicável).

### Documentation — ligação `validacao-lab` / DRAFT `1.8.11_10`

- **`validacao-lab.md`** — parágrafo *Gates oficiais F4*: remissões a
  `checklist-mestre`, `test-matrix`, seções **10a/10b/11**, `CORTEX` ponto 7 e
  `release-notes-1.8.11_10-DRAFT.md`.
- **`release-notes-1.8.11_10-DRAFT.md`** — bloco de estado com ligação inversa
  aos mesmos gates de evidência de lab.

### Documentation — CORTEX e `docs/README` (F4 lab + rascunho F7)

- **`CORTEX.md`** — ponto 7 (*Proximos passos*): liga F4 a evidencia minima
  (`validacao-lab` **10a**/**10b**/**11**, `test-matrix` **3.8**/**12.1–12.2**/**6.7**)
  e ao rascunho de release `1.8.11_10`.
- **`docs/README.md`** — area **Releases**: liga o DRAFT `1.8.11_10` (pre-tag).

### Documentation — rascunho de release 1.8.11_10 (F7)

- **`docs/06-releases/release-notes-1.8.11_10-DRAFT.md`** — rascunho
  pre-publicacao com resumo F4, checklist de fecho, ligacoes a
  `MANUAL-INSTALL` e `validacao-lab` (ate existir tag e `.pkg`).
- **`docs/06-releases/README.md`** — listagem do rascunho; **`document-classification.md`**
  — linha *Placeholder* para o mesmo ficheiro.

### Documentation — checklist validação lab (F4)

- **`docs/04-package/checklist-validacao-lab.md`** — nota no topo com roteiros
  F4 (10a/10b/11), `test-matrix` e ligação ao `checklist-mestre`.

### Documentation — `05-runbooks/README` (validação F4 no lab)

- **`docs/05-runbooks/README.md`** — descrição da ligação a `validacao-lab`
  explicita roteiros **F4** no appliance (secções **10a** / **10b** / **11**).

### Documentation — `docs/tests/README` (gate pacote / lab)

- **`docs/tests/README.md`** — secção CI: gate de pacote referencia tambem
  `04-package/README` e `08-lab/README` para navegacao coerente.

### Documentation — `04-package/README` (ligação ao lab)

- **`docs/04-package/README.md`** — paragrafo introdutorio com ligacoes a
  `MANUAL-INSTALL`, `08-lab/README` e `quick-start-lab` (artefacto `.pkg`).

### Documentation — `docs/README` (área Lab)

- **`docs/README.md`** — tabela *Mapa das areas documentais*: entrada **Lab**
  referencia o indice, o `quick-start-lab` e marca `guia-windows` como legado.

### Documentation — classificação `08-lab` (matriz)

- **`docs/00-overview/document-classification.md`** — `quick-start-lab.md`
  reclassificado como **suplementar** (antes “histórico”); `guia-windows.md`
  com coluna *Substitui* actualizada ao indice do lab e a `deploy-github-lab`.

### Documentation — guia Windows (legado) / índice lab

- **`docs/08-lab/guia-windows.md`** — em *Fonte vigente*, ligação ao
  `docs/08-lab/README.md` e a `docs/04-package/deploy-github-lab.md`, para
  quem cair neste ficheiro legado ser desviado de imediato para o fluxo
  canónico.

### Documentation — equivalência release / índice releases

- **`docs/00-overview/document-equivalence-map.md`** — linha release/distribuição
  actualizada: **ADR-0003** como norma do **`.pkg`**; ADR-0002 como histórico;
  removida a nota obsoleta «precisa de ADR substituto».
- **`docs/06-releases/README.md`** — ligação a `deploy-github-lab.md` e ao
  `scripts/release/README.md` na lista de ficheiros da área.

### Documentation — release notes v0.1.0 (instalação)

- **`docs/06-releases/release-notes-v0.1.0.md`** — comando de instalação e
  rollback alinhados a **`install.sh`** + **`.pkg`**; nota de contexto
  documental; removida dependência de `install-lab.sh` na secção de primeira
  instalação.

### Documentation — deploy lab / GitHub Releases (artefacto `.pkg`)

- **`docs/04-package/deploy-github-lab.md`** — reescrito para o fluxo actual:
  `deployz.sh` gera **`.pkg`**, checksum, `install.sh` / `uninstall.sh` e
  manifesto; no pfSense usa-se **`install.sh`** do release (como em
  `scripts/release/README.md`), não `.txz` nem `install-lab.sh` como caminho
  principal. Secção de legado para `install-lab.sh.template`.
- **`docs/00-overview/document-classification.md`** — `deploy-github-lab.md`
  reclassificado como **suplementar** (antes historico pendente de harmonia).

### Changed — F4.3 DNS forcado (`1.8.11_10`)

- **`layer7.inc`** — `layer7_generate_rdr_rules_snippet` prepara por regra a
  lista de CIDRs IPv4 validos (unicos, ordenados) antes de cruzar com
  interfaces, reduzindo validacao repetida e estabilizando a ordem das linhas
  `rdr` face a reordenacao accidental de `src_cidrs` no JSON.
- **`package/pfSense-pkg-layer7/Makefile` (`PORTREVISION`)** — `10` (rebuild
  `1.8.11_10`).

### Changed — F4.3 DNS forcado (`1.8.11_9`)

- **`layer7.inc`** — `layer7_generate_rdr_rules_snippet` ordena alfabeticamente
  as interfaces efectivas antes de emitir `rdr`, para ordem estavel no anchor
  `natrules/layer7_nat` entre reloads com a mesma configuracao.
- **`package/pfSense-pkg-layer7/Makefile` (`PORTREVISION`)** — `9` (rebuild
  `1.8.11_9`).

### Changed — F4.3 DNS forcado (`1.8.11_8`)

- **`layer7.inc`** — `layer7_generate_rdr_rules_snippet` deduplica pares
  **(interface, CIDR)** entre regras de blacklist com `force_dns`, evitando
  regras `rdr` UDP/TCP redundantes no sub-anchor `natrules/layer7_nat`.
- **`package/pfSense-pkg-layer7/Makefile` (`PORTREVISION`)** — `8` (rebuild
  `1.8.11_8`).

### Documentation / CI — saneamento do fluxo Windows/macOS

- **`docs/08-lab/guia-windows.md`** — reclassificado como documento legado,
  sem comandos activos de WSL/PowerShell/smoke local.
- **`docs/08-lab/README.md`** e **`document-classification.md`** — Windows deixa
  de aparecer como fluxo suplementar vigente.
- **`validacao-lab.md`** e **`scripts/package/README.md`** — macOS fica
  explicitamente limitado a workspace de edicao/git/docs; build/smoke tecnico
  ficam no builder FreeBSD e validacao funcional no pfSense appliance.
- **`scripts/package/smoke-layer7d.sh`** — macOS/Darwin passa a falhar fechado
  por defeito para impedir falso gate local.
- **`.github/workflows/smoke-layer7d.yml`** — removido job Windows para nao
  sugerir validacao fora do fluxo real.

### Fixed — F4.2/F4.3 blacklists runtime (`1.8.11_7`)

- **`layer7d` DNS blacklist** — respostas DNS passam a transportar o IP do
  cliente para o callback e a validar `src_cidrs` por regra antes de popular
  `layer7_bld_N`, evitando vazamento de bloqueio entre redes/regras.
- **`layer7d` reload blacklists** — SIGHUP deixa de limpar regras/tabelas antes
  de validar a nova carga; falha de carga preserva blacklist e tabelas
  anteriores.
- **`blacklist.c`** — dominios presentes em multiplas categorias passam a
  guardar mascara de categorias; DNS/SNI fazem lookup contra as categorias da
  regra, corrigindo falso negativo em categoria sobreposta.
- **GUI/package blacklists** — `pkg-install` prepara
  `/usr/local/etc/layer7/blacklists` e `_custom` para `www:wheel`; saves da GUI
  passam a reportar erro quando `config.json` ou overlays nao puderem ser
  gravados.
- **Auto-update cron** — `update_interval_hours` passa a ser convertido para
  campos cron coerentes, em vez de inverter intervalos curtos/longos.
- **Activacao CLI** — removido fallback `fetch` que fazia GET sem payload; URL
  customizada de activacao passa a exigir HTTPS e caracteres seguros para shell.
- **CI** — workflow smoke passa a incluir syntax check dos scripts shell do
  pacote.

### Documentation — conflitos visiveis

- **`README.md`** — versao publica e install rapido alinhados para `1.8.3`.
- **`docs/README.md`** — hierarquia de leitura renumerada sem duplicar item.
- **`docs/08-lab/guia-windows.md`** — build de lab alinhado para `.pkg`.
- **Blacklists docs** — versao/caminho F4 alinhados ao branch `1.8.11_7` e ao
  consumo assinado.

### Documentation — scripts de pacote / CI

- **`scripts/package/README.md`** — `smoke-layer7d.sh`: nota Darwin/macOS vs
  FreeBSD canónico e CI Linux
- **`.github/workflows/smoke-layer7d.yml`** — comentário no job Windows (só
  `check-port-files.ps1`)

### Documentation — validação lab / CI

- **`validacao-lab.md`** — secção **3** (Build): nota Darwin/macOS vs smoke
  canónico no FreeBSD e CI Linux
- **`.github/workflows/smoke-layer7d.yml`** — comentário: artefacto oficial
  `.pkg` (não `.txz`)

### Changed — smoke layer7d (mensagem em Darwin)

- **`scripts/package/smoke-layer7d.sh`** — aviso em **Darwin/macOS** de que o
  link com `-lcrypto` pode falhar; smoke canónico no builder FreeBSD

### Documentation — F5 (preparação / ponte F4)

- **`f5-preparacao-malha.md`** — pré-requisitos e ordem de trabalho alinhados aos
  três gates F4 (10a / 10b / 11, matriz 3.8, 12.x, 6.7) e ao índice em
  `validacao-lab.md`

### Documentation — validação lab (índice F4)

- **`validacao-lab.md`** — *Índice dos roteiros F4*: nota única sobre
  pré-requisito builder (secção 3) antes do appliance para 10a / 10b / 11

### Documentation — validação lab (F4.1 / roteiro 10a)

- **`validacao-lab.md`** — secção **10a**: pré-requisito builder (`check-port-files`,
  `smoke-layer7d`, `make package`) antes da evidência no appliance
- **`f4-plano-de-implementacao.md`** — teste mínimo F4.1 alinhado a 10b/11

### Documentation — validação lab (F4.3 / roteiro 11)

- **`validacao-lab.md`** — secção **11**: pré-requisito builder (`check-port-files`,
  `smoke-layer7d`, `make package` com F4.3) antes da evidência `pfctl` no appliance
- **`f4-plano-de-implementacao.md`** — teste mínimo F4.3 alinhado

### Documentation — validação lab (F4.2 / roteiro 10b)

- **`validacao-lab.md`** — secção **10b**: pré-requisito explícito
  (`check-port-files.sh`, `smoke-layer7d.sh`, `make package`) antes da
  evidência no appliance

### Documentation — roadmap / testes (gates F4)

- **`roadmap.md`** — checkpoint F4: gates do `checklist-mestre` para F4.1, F4.2
  e F4.3 (secções / pontos da matriz)
- **`docs/tests/README.md`** — ligação ao *Índice dos roteiros F4* em
  `validacao-lab.md`

### Documentation — validação lab / matriz (F4.1, PHP pidfile)

- **`validacao-lab.md`** — secção **10a** e índice F4: critérios e versão mínima
  (`PORTREVISION` ≥ 6) para paridade PHP (`layer7_daemon_pid_from_file`) com
  scripts/`rc.d`
- **`test-matrix.md`** — teste **3.8** explicita verificação na GUI para pacote
  ≥ `1.8.11_6`

### Changed — F4.1 / PHP (pidfile)

- **`layer7.inc`** — `layer7_daemon_pid_from_file()` (primeira linha, trim,
  só dígitos); uso em `layer7_ensure_daemon_running`, `layer7_restart_service`,
  `layer7_signal_reload`, `layer7_read_stats`
- **`layer7_status.php`**, **`layer7_diagnostics.php`** — leitura do pidfile
  via helper (alinhado a `rc.d` / scripts sh)
- **`Makefile` (`PORTREVISION`)** — `6` (rebuild `1.8.11_6`)

### Documentation — gates F4 e índice de roteiros (lab)

- **`validacao-lab.md`** — tabela **Índice dos roteiros F4** (10a / 10b / 11 ↔
  BG-009 / BG-010 / BG-011 ↔ matriz)
- **`checklist-mestre.md`** — itens de evidência mínima para **F4.1** e **F4.2**
  (paralelos ao gate já existente da **F4.3**)
- **`CORTEX.md`** — ponto 10 dos próximos passos alinhado aos três gates F4.1–F4.3

### Documentation — validação lab / matriz (F4.2 BG-010)

- **`validacao-lab.md`** — secção **10b**: roteiro do updater, log, SIGHUP,
  `fallback.state` (healthy / degraded / fail-closed); checklist **#15**
- **`test-matrix.md`** — secção **12** (blacklists F4.2), testes **12.1–12.2**
  pendentes; **Resumo** alinhado (82 totais, daemon 8/7/1, 8 pendentes)
- **`docs/tests/README.md`** — contagens 82 / 8 pendentes + menção F4.2
- **`f4-plano-de-implementacao.md`**, **`roadmap.md`**, **`backlog.md`**,
  **`PLANO-BLACKLISTS-UT1.md`** — referências cruzadas ao roteiro 10b

### Documentation — validação lab / matriz (F4.1 BG-009)

- **`validacao-lab.md`** — secção **10a**: roteiro objectivo no appliance para
  pidfile, `rc.d`, permissões 0644 e critérios mínimos de PASS; checklist
  rápido com item 14
- **`test-matrix.md`** — teste **3.8** (daemon) pendente, ligado à secção 10a
- **`docs/tests/README.md`** — contagens 80 testes / 6 pendentes
- **`f4-plano-de-implementacao.md`** — teste mínimo F4.1 referencia a secção 10a

### Documentation — blacklists (alinhamento F4.1 / pidfile)

- **`PLANO-BLACKLISTS-UT1.md`** — pseudo-código do fluxo de update: passo 12
  deixa de sugerir `cat` cru no pidfile; descreve `send_sighup` e
  `service layer7d reload`

### Documentation — MANUAL-INSTALL (F4.1)

- **`docs/10-license-server/MANUAL-INSTALL.md`** — addendum F4.1 (BG-009):
  validacao do pidfile no `rc.d` e alinhamento com scripts do pacote;
  aviso para nao editar `/var/run/layer7d.pid`; referencia a
  `CORTEX.md`/`Makefile` para `PORTVERSION`/`PORTREVISION` de trabalho vs
  `.pkg` publico

### Documentation — contrato do pidfile do daemon

- **`docs/05-daemon/README.md`** — secção *Pidfile* (`/var/run/layer7d.pid`):
  formato esperado, consumidores (GUI, `layer7.inc`, updater, cron, helpers F3)
  e referência às entregas F4 / `f4-plano`

### Changed — helpers F3 (pidfile no appliance)

- **`scripts/license-validation/export-appliance-evidence.sh`** — no bloco
  remoto que força `USR1` para refrescar `layer7-stats.json`, leitura de
  `/var/run/layer7d.pid` alinhada aos scripts do pacote (`read -r`, trim,
  PID numerico, `kill -0` antes de `USR1`)

### Changed — F4.1 / rc.d (pidfile)

- **`files/usr/local/etc/rc.d/layer7d`** — `layer7d_pid_from_file` (trim,
  PID numerico) usado em `start`, `stop`, `status` e `reload`, alinhado a
  `update-blacklists.sh` / `layer7-stats-collect.sh`
- **`Makefile` (`PORTREVISION`)** — `5` (rebuild `1.8.11_5`)

### Changed — F4.1 / cron (pidfile)

- **`layer7-stats-collect.sh`** — leitura de `/var/run/layer7d.pid` alinhada a
  `update-blacklists.sh` (`send_sighup`): `read -r`, trim com `sed`, rejeicao
  de PID nao numerico antes de `kill -0` / `USR1` (`PORTREVISION` `4` / build
  `1.8.11_4` nesse bloco)

### Documentation — F5 (preparacao) alinhada a F4

- **`f5-preparacao-malha.md`** — prerequisitos com gates do `checklist-mestre`
  (F4 / F4.3) e ligação a `validacao-lab` / `test-matrix` 6.7; passo 0 na
  ordem de trabalho (evidencia F4 antes de prometer F5 plena); secção 5 com
  referencia a checklist e roteiros de lab

### Documentation — gates F4 no checklist mestre

- **`checklist-mestre.md`** — checklist de testes e gates: itens F4 (paralelismo
  com F3) e F4.3 / BG-011 (evidência `validacao-lab` sec. 11 e `test-matrix`
  6.7); gate resumido F4 com referência a evidência por subfase
- **`CORTEX.md`** — `Proximos passos` ponto 10 aponta para estes gates

### Documentation — blacklists (F4.3) e índice de testes

- **`PLANO-BLACKLISTS-UT1.md`** — addendum F4.3: links a `f4-plano`,
  `validacao-lab` sec. 11, `test-matrix` 6.7, `MANUAL-INSTALL`, **BG-011**
- **`docs/tests/README.md`** — contagem 79/74/5; menção explícita ao **6.7**
  (F4.3) na matriz

### Documentation — matriz de testes (F4.3)

- **`docs/tests/test-matrix.md`** — ponto **6.7** (anchor NAT `force_dns` /
  `pfctl`); resumo 79/74/5; título e referência ao `validacao-lab` sec. 11

### Documentation — validacao de lab (F4.3)

- **`docs/04-package/validacao-lab.md`** — secção 11: roteiro e criterio PASS
  para o anchor NAT `natrules/layer7_nat` / `force_dns`; linha 13 no checklist
  rapido; ligacao ao addendum F4.3 do `MANUAL-INSTALL`

### Changed — F4.3 enforcement / DNS forcado (BG-011)

- **`layer7.inc` (`layer7_generate_rdr_rules_snippet`)** — deduplica nomes de
  interface apos `get_real_interface` / fallback VLAN, evitando linhas `rdr`
  repetidas; so emite `rdr` para `src_cidrs` que passam `layer7_cidr_valid`
  ou `layer7_ipv4_valid` (evita `pfctl` a rejeitar o anchor NAT por texto
  invalido)
- **`layer7.inc` (`layer7_get_pfsense_interfaces`)** — retorna lista vazia se
  `get_configured_interface_list` ou `get_real_interface` nao existirem
  (contexto nao-pfSense / testes), em vez de erro fatal
- **`layer7.inc` (`layer7_pf_ifname_for_rules` / `layer7_log_pkg_warn`)** —
  nomes de interface em `rdr` alinham-se ao padrao do anti-QUIC; interfaces
  filtradas antes de gerar o snippet; falha de `tempnam`, escrita do ficheiro
  temp ou `pfctl -N -f` no anchor `natrules/layer7_nat` regista aviso via
  `log_error` / `error_log`
- **`Makefile` (`PORTREVISION`)** — `2` (rebuild; `1.8.11_2`)

### Documentation — F4.3 (BG-011) e manual operacional

- **`MANUAL-INSTALL.md`** — addendum F4.3: `force_dns` injectado no anchor NAT
  `natrules/layer7_nat`, comando de verificacao `pfctl -a natrules/layer7_nat
  -s nat`, validacao/dedup de origens, ambito **inet** (IPv4) sem `rdr` IPv6
  nesta trilha

### Documentation — F4.1 (BG-009) e roadmap F4

- **`MANUAL-INSTALL.md`** — addendum operacional F4.1: `POST-INSTALL` com
  `onestop` antes de `onestart` no upgrade, pidfile e `status`, alinhamento
  do reload da GUI com o `rc.d`; nota de que a referencia de `.pkg` publica
  segue a versao listada em **Links da versao actual** ate nova release
- **`roadmap.md`** (checkpoint F4) — proximo passo explicito: evidencia em
  lab/appliance e F4.3, em paralelo ao **DR-05** para a F3

### Changed — F4.2 blacklists (BG-010)

- **`update-blacklists.sh` (`send_sighup`)** — leitura segura do pidfile
  (`read -r`); normalizacao de espacos em branco à volta do PID (`sed`) antes
  da validacao numerica; rejeita PID nao numerico; `kill -0` antes de `HUP`;
  regista WARN quando o daemon nao esta a correr em vez de `HUP` silencioso a
  PID invalido
- **`update-blacklists.sh` (`--restore-lkg`)** — adquire o mesmo lock exclusivo
  que `do_download`, impedindo restauracao LKG concorrente com um update
  (evita corrida em `promote_candidate`)
- **`layer7-pfctl`** — todas as invocacoes de `pfctl` passam a usar
  `/sbin/pfctl` (PATH minimo em cron/rc alinhado a `table_ready` / `pfctl -sr`)
- **`PORTVERSION` / `PORTREVISION`** — `1.8.11` com incrementos de
  `PORTREVISION` em blocos F4.2 (ex.: `3` com trim em `send_sighup`; ver
  entradas mais recentes no topo de `[Unreleased]` para o número actual);
  artefacto publico de referencia continua `1.8.3` ate nova release

### Changed — F4.1 package/daemon (BG-009)

- **`rc.d/layer7d`** — apos `daemon -p`, o pidfile fica `0644` para
  `service layer7d status` nao falhar por permissoes quando o ficheiro era
  `0600 root:wheel`
- **`pkg-install` (`POST-INSTALL`)** — `service layer7d onestop` antes de
  `onestart` para upgrades aplicarem o binario do pacote recém-instalado
  (antes, `onestart` com processo vivo saia cedo sem reiniciar)
- **`layer7.inc` (`layer7_signal_reload`)** — se o pidfile estiver ausente,
  invalido ou o processo nao existir, passa a invocar
  `layer7_ensure_daemon_running()` (sobe o daemon quando `layer7.enabled` no
  JSON), em linha com o `reload` do `rc.d` (HUP apenas quando o processo esta
  vivo); leitura do pidfile com `@file_get_contents` para evitar avisos
- **`layer7.inc` (`layer7_restart_service`, `layer7_read_stats`)** — leitura do
  pidfile com `@file_get_contents`; `kill -0` antes de `USR1` nas estatisticas;
  verificacao pos-restart com `kill -0` redireccionado para `/dev/null`
- **`pkg-deinstall`** — `PRE-DEINSTALL`: `service layer7d onestop`; `POST-DEINSTALL`:
  remover `/var/run/layer7d.pid` stale e `sysrc layer7d_enable=NO` antes do
  reload PF (evita processo orfao e arranque pendente apos `pkg delete`)
- **`layer7_status.php`** — `kill -0` com stderr para `/dev/null` (alinhado ao
  resto da trilha F4.1)

### Added — continuidade entre chats longos

- [`docs/00-overview/handoff-chat-novo.md`](docs/00-overview/handoff-chat-novo.md) — quando mudar para um chat novo no Cursor, sinais práticos e **prompt modelo** para colar na primeira mensagem; referência no `CORTEX`, `docs/README` e `AGENTS.md`

### Changed — F4 e F5 (governança) em 2026-04-24

- **F4.0 aberta** com [`docs/02-roadmap/f4-plano-de-implementacao.md`](docs/02-roadmap/f4-plano-de-implementacao.md) — subfases
  F4.1 (package/daemon, BG-009), F4.2 (blacklists, BG-010), F4.3 (enforcement,
  BG-011); **paralelismo** explicito com a F3 ainda aberta (pendência DR-05)
  **sem** alterar o contrato de licenciamento em blocos F4
- **F5 (preparacao)** com [`docs/02-roadmap/f5-preparacao-malha.md`](docs/02-roadmap/f5-preparacao-malha.md) — roteiro
  para malha de testes antes da execução plena (BG-012 a BG-014)
- **`CORTEX`**, **roadmap** e **backlog** actualizados: tabela de fases,
  `Proximos passos` (F4 e F5), estados de BG-009/BG-010; `docs/README` indexa
  os planos

### Changed — governanca e license-server (2026-04-24)

- **Politica reutilizavel do download administrativo do `.lic` (`GET /api/licenses/:id/download`)** —
  a validacao (licenca activada, hardware, estado) passa a concentrar-se em
  `license-server/backend/src/license-download-policy.js`, com testes
  `license-download-policy.test.js` e reutilizacao na rota em
  `routes/licenses.js` para alinhar ao padrao de politicas testaveis
  (`activation-policy`, `license-update-policy`)
- **`npm test` no backend do license-server** — o script passa a incluir
  todos os ficheiros `src/**/*.test.js` (nao so `src/*.test.js`), garantindo
  que modulos em subpastas com testes associados entram na suite
- **Documentacao (`CORTEX.md`)** — checkpoint fixo, bloco de
  "ultimo status" e riscos actualizados: F3 como fase aberta, distincao
  explicita entre versao .pkg publicada (`1.8.3`) e `PORTVERSION` de
  trabalho no repositorio (`1.8.4`), e paragrafo operacional alinhado ao
  estado pos-F1.4 (sem pedir reabertura de F1.4)
- **Integridade de ficheiros do port** — se ficheiros canónicos do pacote
  (ex. `layer7.inc`, `layer7-pfctl`, `pf.conf.sample`) aparecerem vazios ou
  truncados no disco, restaurar a partir de `origin/main` antes de
  qualquer build; o estado "0 bytes" local nao e commitavel nem releasavel

### Changed — alinhamento do license-server live

- **License-server live alinhado ao contrato administrativo actual** —
  o ambiente activo em `192.168.100.244:/opt/layer7-license` passa a expor
  `admin_sessions`, `admin_audit_log` e `admin_login_guards`, responde
  `GET /api/auth/session`, mantem a bridge Bearer administrativa e volta a
  falhar fechado para `Origin` externo em `/api/auth/login`
- **DR-05 do appliance passa a ter baseline real e SSH funcional** —
  o utilizador temporario `codex` em `192.168.100.254` passa a permitir
  exportar baseline canónico do appliance, confirmar fingerprint/licenca
  actual e validar restart de `layer7d`; os cenarios mutaveis continuam
  dependentes de permissao de escrita em `/usr/local/etc/layer7.lic`
- **Baseline canónica do appliance ganha novo run real via helper** —
  `scripts/license-validation/export-appliance-evidence.sh` foi executado com
  sucesso no `run_id` `20260414T000000Z-appliance254-continue`, materializando
  `40-preflight-appliance.txt`, `50-appliance-cli.txt`,
  `60-appliance-license.json` e `70-local-hashes.txt` com o estado real do
  appliance sob o utilizador `codex`
- **Trilha GUI autenticada do pfSense ganha helper canónico de campanha** —
  `scripts/license-validation/run-pfsense-gui-license-flow.sh` passa a
  materializar `probe`, `register` e `revoke` com captura de `headers`,
  `HTML`, `cookie jar` e notas por `run_id`, incluindo execucao via
  `--ssh-target` quando a GUI util so responde em
  `https://127.0.0.1:9999/` no proprio appliance, reduzindo improviso
  operacional no `DR-05`
- **Painel administrativo passa a editar licencas existentes** —
  a SPA passa a expor `/licenses/:id/edit`, reutiliza o endpoint
  `PUT /api/licenses/:id`, bloqueia a troca de cliente quando a licenca ja
  esta activada/bindada e cobre a normalizacao do formulario com teste puro
- **Contrato de rejeicao de activacao passa a ter regressao dedicada** —
  a politica do `POST /api/activate` para licenca revogada, licenca expirada
  e hardware divergente passa a ficar isolada em helper testavel e coberta
  por testes que preservam `409`, reduzindo o risco de reintroduzir o drift
  cosmetico `403` observado anteriormente no live
- **Auditoria de emissao/reemissao do `.lic` passa a ter regressao dedicada** —
  a metadata auditada dos artefactos emitidos por `activate` e por download
  administrativo passa a ser coberta por testes puros, preservando
  `flow`, `emission_kind`, binding, customer/features e hashes SHA-256 de
  payload, assinatura e envelope
- **Estado efectivo de licencas passa a ter regressao dedicada** —
  `license-state` passa a cobrir por testes o contrato `active` /
  `expired` / `revoked`, expiracao por data, precedencia de revogacao,
  normalizacao de hardware e predicados SQL usados por listagens e dashboard
- **Payload publico de activacao passa a ter regressao dedicada** —
  `parseActivatePayload` passa a cobrir normalizacao de `key` e
  `hardware_id`, rejeicao de campos inesperados e erros `400` para chave ou
  hardware invalidos antes de tocar na transacao de activacao
- **Guardrail de update administrativo de licenca passa a ter regressao dedicada** —
  a deteccao de campos alterados e o bloqueio `409` contra troca de
  `customer_id` em licenca activada/bindada passam a ficar isolados em helper
  testavel, preservando a proteccao contra transferencia silenciosa de
  ownership

### Fixed — auth bridge do painel administrativo

- **Helpers shell da F3 deixam de falhar no bash 3.2 do macOS quando `SSH_OPTIONS` esta vazio** —
  `scripts/license-validation/export-appliance-evidence.sh`,
  `scripts/license-validation/run-appliance-activation-scenario.sh` e
  `scripts/license-validation/prepare-f3-preflight.sh` passam a proteger os
  loops de `SSH_OPTIONS` sob `set -u`, evitando erro `unbound variable`
  antes de qualquer tentativa real de SSH

- **Bootstrap da sessao sincroniza a ponte Bearer sem storage persistente** —
  `license-server/frontend/src/auth.jsx` continua a absorver o token
  devolvido por `GET /api/auth/session`, mas a credencial de compatibilidade
  passa a ficar apenas em memoria, evitando reintroduzir `localStorage`
- **Estado autenticado consolidado num helper pequeno** —
  `license-server/frontend/src/auth-session-state.js` centraliza aplicar e
  limpar sessao/token no frontend, reduzindo duplicacao e risco de esquecer
  a limpeza da credencial transitória em falhas/logout
- **Payload autenticado do frontend passa a exigir coerencia minima** —
  `license-server/frontend/src/auth-payload.js` passa a normalizar respostas
  de auth e a rejeitar payload parcial sem `admin` e `session`, evitando
  manter token em memoria quando o backend devolve estado malformado
- **Controller puro da auth do frontend agora e testavel em isolamento** —
  `license-server/frontend/src/auth-controller.js` passa a concentrar
  bootstrap, login, refresh, logout e limpeza do estado autenticado, enquanto
  `auth-controller.test.js` cobre sucesso, falha e view inactiva sem exigir
  harness React mais pesado
- **Login e refresh do frontend rejeitam payload parcial sem reter estado velho** —
  `auth-controller.test.js` passa a provar que respostas malformadas de
  `/auth/login` e `/auth/session` limpam `admin/session` locais em vez de
  manter estado stale ao lado de um token transitório
- **Login deixa de prosseguir com sessao parcial de sucesso** —
  `loginWithPassword()` passa a falhar explicitamente quando o backend devolve
  `200` com payload de auth incoerente, evitando navegar para a area privada
  com estado local ja limpo
- **Refresh deixa de tratar sessao parcial como sucesso silencioso** —
  `refreshAuthSession()` passa a falhar explicitamente quando `/auth/session`
  devolve payload incoerente, evitando revalidacao enganosa com estado local
  previamente limpo
- **Regra de consistencia de sessao do frontend vira helper puro** —
  `license-server/frontend/src/auth-payload.js` passa a centralizar tambem a
  validacao que levanta erro para payload incoerente, evitando drift entre
  `loginWithPassword()` e `refreshAuthSession()`
- **Aplicar e validar sessao autenticada vira operacao unica** —
  `license-server/frontend/src/auth-session-state.js` passa a expor
  `syncAuthenticatedSession()`, reduzindo duplicacao entre `login` e `refresh`
  ao aplicar estado e validar coerencia no mesmo helper
- **Flags canonicas da auth administrativa deixam de ficar repetidas** —
  `license-server/frontend/src/auth-request-options.js` passa a centralizar
  `skipAuthRedirect: true`, reduzindo drift entre bootstrap, login, refresh e
  logout do frontend
- **Caminhos de auth do frontend passam a ser canónicos** —
  `license-server/frontend/src/auth-paths.js` passa a concentrar os endpoints
  de login, logout e sessao, reduzindo risco de drift entre controller e
  camada API
- **Rotas principais do painel passam a ter destino canónico unico** —
  `license-server/frontend/src/panel-routes.js` passa a concentrar os
  destinos de login e dashboard usados por `App`, `Login` e `Sidebar`,
  reduzindo drift entre navegação protegida e navegação pós-login
- **Links principais da navegação lateral também passam a usar rotas canónicas** —
  `license-server/frontend/src/panel-routes.js` passa a concentrar também os
  destinos de licenças e clientes usados pela `Sidebar`, reduzindo mais um
  ponto de drift entre a navegação lateral e as rotas oficiais da SPA
- **Detalhe, criação e edição do painel passam a usar builders canónicos de rota** —
  `license-server/frontend/src/panel-routes.js` passa a expor também os
  destinos `new`, detalhe e `edit` de licenças/clientes, reduzindo drift
  entre listagens, formulários, detalhe e navegação de retorno do painel
- **Redirect de sessao invalida passa a reutilizar a rota canónica de login** —
  `license-server/frontend/src/api.js` passa a consumir
  `ADMIN_LOGIN_ROUTE` em vez de repetir `'/login'`, alinhando a camada API
  ao mesmo destino oficial já usado pelo restante fluxo de navegação do painel
- **Logout do frontend preserva a resposta do backend sem perder limpeza local** —
  `logoutAuthSession()` passa a devolver o payload de sucesso de
  `/auth/logout` quando existir, mantendo a limpeza defensiva do estado
  autenticado tanto em sucesso quanto em erro
- **Escuta do evento de sessao invalida sai do componente e vira helper puro** —
  `license-server/frontend/src/auth-invalid-listener.js` passa a concentrar
  a inscricao e limpeza do `layer7:auth-invalid`, com cobertura dedicada para
  estado activo, inactivo e ausencia de target de eventos
- **Provider de auth deixa de declarar autenticacao com estado parcial** —
  `license-server/frontend/src/auth-context-value.js` passa a exigir
  `admin + session` para `isAuthenticated`, evitando falso positivo quando o
  estado local estiver parcialmente hidratado ou limpo
- **Gate de auth do frontend passa a usar decisao unica de estado** —
  `license-server/frontend/src/auth-gate.js` centraliza a leitura
  `loading` / `authenticated` / `anonymous`, reduzindo drift entre `App` e
  `Login` na hora de mostrar loading ou redirecionar
- **Fluxo de sessao invalida da API sai do corpo da request** —
  `license-server/frontend/src/api-auth-redirect.js` passa a centralizar a
  limpeza do token em memoria, a emissao do evento e o redirect para login,
  reduzindo acoplamento na camada `api`
- **Evento de sessao invalida passa a ter nome canónico unico** —
  `license-server/frontend/src/auth-events.js` passa a concentrar o nome do
  evento usado por `api` e pelo listener de auth, reduzindo risco de drift
  entre emissao e subscricao
- **Mensagens criticas de auth do frontend passam a ser canónicas** —
  `license-server/frontend/src/auth-messages.js` passa a concentrar as
  mensagens de sessao expirada e sessao incoerente, reduzindo drift entre
  controller, payload, camada API e testes
- **Fallback de erro no login também passa a ter mensagem canónica** —
  `license-server/frontend/src/auth-messages.js` passa a concentrar também a
  mensagem padrão de falha do formulário de login, reduzindo mais um literal
  solto dentro da tela administrativa
- **Mensagem de validacao da sessao tambem passa a ser partilhada** —
  `App` e `Login` passam a reutilizar a mesma constante de loading da auth,
  evitando drift visual pequeno entre as duas entradas principais do painel
- **Cobertura automatizada leve da trilha** —
  `license-server/frontend/src/auth-session-state.test.js` passa a provar que
  o token de compatibilidade vive apenas em memoria e e limpo junto com o
  estado autenticado, sem exigir infra adicional nem tocar no contrato do backend
- **Camada API agora tem smoke tests locais repetiveis** —
  `license-server/frontend/src/api.test.js` e o script `npm test` do frontend
  passam a verificar injecao do header Bearer em memoria, limpeza do token em
  `401` e o comportamento de `skipAuthRedirect`, reduzindo regressao silenciosa na SPA
- **Redirect 401 e parsing da API viram helpers puros** —
  `license-server/frontend/src/api-response.js` passa a concentrar a decisao
  de sessao invalida, o parsing de erro e o parsing de sucesso da camada API,
  com cobertura dedicada para `401`, fallback de erro, `204`, JSON e texto
- **Headers da camada API ficam robustos a casing misto** —
  `license-server/frontend/src/api.js` passa a tratar `Authorization` e
  `Content-Type` de forma case-insensitive, evitando injectar Bearer extra ou
  sobrescrever `content-type` custom quando o caller usa chaves em lowercase
- **Login deixa de reutilizar Bearer herdado por acidente** —
  `license-server/frontend/src/api.js` passa a nunca injectar a credencial
  transitória em `POST /api/auth/login`, evitando enviar token antigo para o
  endpoint que deve depender apenas das credenciais fornecidas no momento
- **Bridge Bearer do backend ganha segredo dedicado e nao vaza token cru** —
  `license-server/backend/src/bearer-session-token.js` extrai a logica pura
  de assinatura/verificacao do token administrativo para um modulo pequeno;
  a emissao passa a depender so de `ADMIN_BEARER_JWT_SECRET`, sem fallback
  para `ED25519_PRIVATE_KEY` nem para o token opaco cru da sessao
- **Resposta de auth do backend passa a ter montagem unica e testavel** —
  `license-server/backend/src/auth-response.js` centraliza o payload comum de
  `login` e `session`, reduzindo drift entre as duas rotas e cobrindo quando
  o token Bearer de compatibilidade deve ou nao aparecer
- **Precedencia Bearer/cookie do backend passa a ser helper puro** —
  `license-server/backend/src/auth-access.js` centraliza a seleccao e a fila
  de candidatos de acesso administrativo, deixando explicita a prioridade do
  Bearer validado sobre o cookie e cobrindo deduplicacao em teste local leve
- **Middleware de auth administrativa ganha cobertura dedicada** —
  `license-server/backend/src/auth.test.js` passa a cobrir sessao valida,
  sessao invalida e erro interno do resolvedor, enquanto
  `license-server/backend/src/auth-middleware.js` isola o factory puro para
  injeção de dependências e teste sem DB
- **Login failure e logout audit do backend ganham helpers puros** —
  `license-server/backend/src/auth-route-helpers.js` centraliza a montagem
  de `lockout_scopes`, `admin_id` opcional e do payload de auditoria do
  logout, reduzindo duplicacao e cobrindo a regra em teste local leve
- **Ciclo de vida da sessao administrativa vira regra pura e testavel** —
  `license-server/backend/src/session-lifecycle.js` passa a centralizar a
  decisao de expirar, renovar ou apenas actualizar `last_seen_at`, reduzindo
  risco de drift na janela de renovacao e no timeout absoluto
- **Falhas de login do backend passam a ter payload de auditoria centralizado** —
  `license-server/backend/src/auth-route-helpers.js` passa a montar tambem os
  eventos de `login_rejected`, `login_locked`, `login_failed` e `login_error`,
  reduzindo repeticao na rota de auth e deixando a trilha negativa mais
  previsivel em teste local
- **Eventos positivos e erro de logout da auth tambem saem da rota** —
  `license-server/backend/src/auth-route-helpers.js` passa a centralizar
  tambem `login_succeeded`, `session_created` e `logout_error`, deixando a
  auditoria administrativa da auth concentrada num unico ponto testavel
- **Middleware de sessao passa a usar payloads de auditoria centralizados** —
  `license-server/backend/src/auth-middleware.js` passa a consumir helpers
  para `admin_access_denied` e `session_validation_error`, fechando a trilha
  de auditoria da auth administrativa num unico modulo puro
- **Respostas HTTP da rota de auth deixam de ser montadas inline** —
  `license-server/backend/src/auth-route-response.js` passa a centralizar
  payloads de erro e a resposta de sucesso do logout, reduzindo repeticao e
  deixando a rota administrativa mais previsivel em manutencao futura
- **Middleware de auth passa a reutilizar o mesmo contrato de erro** —
  `license-server/backend/src/auth-middleware.js` passa a consumir
  `buildAuthErrorResponse()`, evitando drift entre a rota de auth e a
  proteccao das rotas privadas quando devolvem `401` ou `500`
- **Helper de appliance entra no pack da F3** —
  `scripts/license-validation/export-appliance-evidence.sh` passa a recolher
  baseline local, stats JSON, fingerprint, `.lic` e hash local do appliance
  por SSH, reduzindo atrito operacional em `S07` a `S13` sem tocar no produto
- **Campanha F3 nasce com preflight estruturado** —
  `scripts/license-validation/init-f3-validation-campaign.sh` passa a criar
  tambem `10-preflight-deploy.txt`, `20-preflight-schema.txt`,
  `30-preflight-admin.txt`, `40-preflight-appliance.txt` e
  `50-preflight-inventory.md`, alinhando o helper ao runbook canónico da
  F3.10 antes de qualquer `S01`
- **Baseline do appliance sobe para o preflight da campanha** —
  `scripts/license-validation/export-appliance-evidence.sh` passa a aceitar
  `--update-root-preflight`, consolidando `50-appliance-cli.txt`,
  `60-appliance-license.json` e `70-local-hashes.txt` no
  `40-preflight-appliance.txt` do `run_id`
- **Deploy/admin do live ganham helper de preflight** —
  `scripts/license-validation/export-live-preflight.sh` passa a materializar
  `10-preflight-deploy.txt` e `30-preflight-admin.txt` com health publico,
  origin observado, probes de CORS e, quando houver credenciais, login e
  sessao administrativa via `curl`
- **Schema do live ganha helper de preflight** —
  `scripts/license-validation/export-schema-preflight.sh` passa a
  materializar `20-preflight-schema.txt` com identidade da base, presenca das
  tabelas canónicas, contagem minima e colunas administrativas via
  `docker compose exec` read-only
- **Preflight completo ganha orquestrador leve** —
  `scripts/license-validation/prepare-f3-preflight.sh` passa a inicializar a
  campanha e encadear os helpers de live, schema e appliance no mesmo
  `run_id`, reduzindo cola manual antes da abertura real da F3.11
- **DR-05 ganha helper de orquestracao para cenarios do appliance** —
  `scripts/license-validation/run-appliance-activation-scenario.sh` passa a
  encadear snapshot inicial/final do backend, passo local de `layer7d
  --activate` e baseline do appliance no mesmo `run_id`, reduzindo o atrito
  operacional para executar `S01`, `S02` e `S07` no pfSense real
- **Upgrade do license-server antigo ganha compatibilidade conservadora de Bearer bridge** —
  `license-server/backend/src/session.js` passa a preferir
  `ADMIN_BEARER_JWT_SECRET`, mas aceita `JWT_SECRET` como fallback de
  compatibilidade para deploys antigos; `docker-compose.yml` passa a expor
  ambos ao container da API e `.env.example` documenta a transicao esperada

### Changed — F3.8 gate de fechamento e relatorio final de campanha

- **Gate canónico da F3.8** —
  `docs/01-architecture/f3-gate-fechamento-validacao.md` passa a fixar o
  gate oficial de fechamento da F3, a matriz objectiva de `PASS` / `FAIL` /
  `INCONCLUSIVE` / `BLOCKED` por cenario e a classificacao explicita de
  pendencias bloqueantes vs nao bloqueantes
- **Relatorio final unico da campanha** —
  `docs/tests/templates/f3-validation-campaign-report.md` passa a servir
  como artefacto final canónico da execucao real da F3, com resumo
  executivo, ambiente, veredito por cenario, riscos remanescentes e decisao
  explicita `F3 pode fechar` / `F3 nao pode fechar`
- **Helper shell opcional e barato** —
  `scripts/license-validation/init-f3-validation-campaign.sh` passa a
  materializar a directoria de campanha por `run_id`, o manifest inicial, os
  directórios dos cenarios e o template do relatorio, sem tocar produto,
  daemon, runtime, schema ou contrato externo

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
