# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

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
