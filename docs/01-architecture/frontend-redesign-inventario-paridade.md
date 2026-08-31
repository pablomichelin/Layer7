# Inventário e matriz de paridade — GUI Layer7

**Estado:** baseline funcional de `1.9.79`; obrigatório antes de qualquer
redesign.
**Regra:** nenhuma linha pode ser removida da proposta sem decisão explícita,
teste e registo no backlog/ADR quando aplicável.
**Inspecção:** fonte do repositório + GUI autenticada no appliance, somente
leitura, em `2026-08-31`.

## 1. Fronteiras técnicas comuns

- As páginas administrativas incluem `guiconfig.inc`, usam os privilégios
  `page-services-layer7-*` declarados nos cabeçalhos e recebem CSRF nativo do
  pfSense (`__csrf_magic`).
- Estado principal: `/usr/local/etc/layer7.json`; carregado/normalizado por
  `layer7_load_or_default()` e gravado com `LOCK_EX` por `layer7_save_json()`.
- Estado auxiliar: blacklists em `/usr/local/etc/layer7/blacklists/`, perfis
  custom em `profiles-custom.json`, relatórios/SQLite e logs, secrets Identity
  e CA MITM em ficheiros separados.
- Mutação PF relevante passa por `layer7_pf_config_resync()` ou
  `layer7_filter_configure_safe()`; reload simples usa `layer7_signal_reload()`;
  licença/update podem reiniciar `layer7d`.
- Endpoints GET/AJAX são de leitura: progresso blacklist, consulta de update,
  eventos e exports. Edição por GET só escolhe o formulário. Nenhum GET
  destrutivo foi identificado.
- A página pública `layer7-blockpage/index.php` não exige login, não possui
  formulário, lê apenas configuração e emite `no-store/no-cache`.
- PT/EN/ES passam por `l7_t()`. A página pública tem defaults próprios nos três
  idiomas. Novas strings devem satisfazer os gates de cobertura i18n.

### 1.1 Superfícies de implementação auditadas

| Superfície | Papel no frontend |
|---|---|
| `.../www/packages/layer7/layer7_*.php` | rotas administrativas, handlers GET/POST, HTML e estilos/scripts locais |
| `.../www/packages/layer7/layer7_settings_update.js` | check de update, estados XHR e geração do POST protegido |
| `.../www/packages/layer7/js/chart.umd.min.js` | gráficos locais de Reports; sem CDN |
| `.../www/layer7-blockpage/index.php` + `router.php` | página pública de bloqueio e roteamento de paths |
| `.../pkg/layer7.inc` | defaults, load/save, navegação, i18n, licença, PF, daemon, blockpage e helpers UI |
| `.../pkg/layer7.xml` e `etc/inc/priv/layer7.priv.inc` | menu/package e privilégios pfSense |
| `.../etc/layer7/profiles.json` + `remote-access-catalog.json` | biblioteca factory e catálogo Remote Access |
| `.../etc/layer7/lang/{pt,en,es}.php` | catálogos administrativos PT/EN/ES |
| `.../etc/layer7/blacklists/` e scripts auxiliares | config, estado, progresso, LKG e aplicação de blacklists |

Não existe framework frontend adicional nem pipeline de bundling próprio. O
CSS comum nasce sobretudo de `layer7_render_styles()` no include e há CSS/JS
inline por página; esta dispersão é uma superfície de regressão a reduzir de
forma incremental, sem alterar os handlers de segurança.

## 2. Inventário por rota

| Área/rota | Finalidade e público | Dados/estado | Formulários e campos principais | Ações e efeitos | Condições/erros/empty | Destino proposto |
|---|---|---|---|---|---|---|
| Status — `layer7_status.php` | saúde operacional | versão pkg/daemon, PID, config, stats, modo pedido/efectivo, licença, políticas, top apps/devices | `restart_service` | `layer7_restart_service()`; status recalculado | daemon/config/stats ausentes; licença impede enforce | GUI1 piloto: mesma rota; view nativa pfSense (pendente gates/commit); taxonomia Visão geral |
| Devices — `layer7_devices.php` | inventário e identidade legível do cliente | DHCP/ARP, MAC, hostname, vendor, interface, online/source, aliases, grupos | `alias[MAC]`, `assign_macs[]`, `assign_group` | grava aliases; atribui MACs a grupo; resolve IPs; save JSON + PF resync | MAC inválido, grupo inexistente, save falha; lista grande sem paginação | Clientes > Dispositivos |
| Policies — `layer7_policies.php` | aplicação, catálogo e autoria de políticas | JSON, 105 perfis factory/custom/override, grupos, VIP, nDPI, stats | activação de perfil; editor de perfil; batch toggles; política nova/editar; delete | save JSON/perfis custom; resync PF; anti-DoH em perfil; redirects após edit | entitlement Identity, scoped gate, conflitos include/exclude, limites, hidden/override | Proteção > Políticas / Biblioteca / Editar |
| Groups — `layer7_groups.php` | agrupar origens | grupos, políticas consumidoras, inventário | id/nome, CIDRs, hosts, MACs; edit/delete/resync | save JSON + PF resync; resolve MAC→IP | id único/válido; CIDR/IPv4/MAC e limites; empty | Clientes > Grupos |
| Exceptions — `layer7_exceptions.php` | isenção global e excepções gerais | `vip-isentos`, labels, DHCP static maps, modo DNS VIP, exceptions | VIP directa; picker DHCP; bulk; import file; exception add/edit/delete | save JSON + PF resync(true); export txt | limites 32 IP/16 CIDR, alvo/descrição, host/CIDR, interfaces, managed exception | Proteção > Excepções e VIP |
| Categories — `layer7_categories.php` | referência nDPI | `layer7_ndpi_list()` protocolos/categorias | nenhum | expandir/consultar apenas no browser | nDPI indisponível/vazio | Proteção > Catálogo nDPI |
| Simulator — `layer7_test.php` | explicar decisão sem aplicar | políticas, grupos, selectors, precedência | domínio/IP destino, IP origem, app, categoria | avaliação PHP somente; sem save/PF | input inválido; allow/exception/block/monitor/no match | Proteção > Simulador |
| Identity — `layer7_identity.php` | configurar user↔IP add-on | entitlement, LDAP, RADIUS accounting, DC agent, secrets separados, teste LDAP | enable; LDAP; RADIUS; DC; generate/clear secrets; save/test | valida, save secrets/config, TLS cert DC, disarm se sem entitlement, SIGHUP | bloqueado sem entitlement; teste/secret/runtime errors | tab/rota Identity actual; **fila fechada** |
| MITM — `layer7_mitm.php` | configurar inspeção TLS governada | entitlement, CA, runtime/helper, janela, source/dest/SNI/bypass | gerar/importar/exportar/apagar CA; enable/window/intercept/bypass; break-glass | ficheiros CA 0600, helper sync, PF reload, failsafe rollback | sem entitlement/runtime/CA; expiry; helper falha; NO-GO permanente | tab/rota MITM actual; **sem mudança funcional** |
| Blacklists — `layer7_blacklists.php` | snapshot assinado, regras e categorias | config, discovered, subscription, runtime/fallback/LKG/stats | download; regra; categoria custom; whitelist domínio; limites/cron | fetch assíncrono, saves auxiliares, `layer7_bl_apply()`, cron/PF | token ausente/expirado; degraded/fail-closed; memória/limites | Proteção > Blacklists |
| Blacklist progress — `layer7_bl_ajax.php` | polling de download | ficheiro de progresso | nenhum | GET `action=progress`, text/no-store | 400 para acção inválida | componente de Blacklists |
| Allowlist — `layer7_allowlist.php` | destinos nunca bloqueados pelo Layer7 | seed + `dst_allowlist` | textarea IPv4/IPv6/CIDR | save JSON, apply tabela PF, SIGHUP, filter reload | entrada inválida/dedup/save/apply | Proteção > Allowlist |
| Events — `layer7_events.php` | investigação operacional/live | `layer7-events.log` ou `layer7d.log/system.log`, storage | GET source/filter; AJAX source/filter | leitura e explicação; pause/update/clear apenas visual | ficheiro ausente; empty; filtro sem resultado | Atividade > Eventos |
| Reports — `layer7_reports.php` | análise executiva | SQLite/history, config de detalhe, timeline/top/events | GET período/src/host/action/q/page; POST clear | ingest incremental; filtros/paginação; clear DB | DB/detail/collector indisponível; chart offline; empty | Atividade > Relatórios |
| Report export — `layer7_reports_export.php` | exportar visão filtrada | mesmas fontes/filtros | GET `format=html|csv|json` | download até 2.000 eventos | formato/fonte sem dados | acção em Relatórios |
| Settings — `layer7_settings.php` | configurar subsistemas e operar sistema | config, interfaces, enforce efectivo, CP conflict, licença, reports, update | geral 53 campos; reports; register/revoke; export/import; check/update | save/SIGHUP; PF reload; blockpage/Unbound; cron; daemon activate/restart; fetch/pkg add | validações e gates abaixo; mensagens múltiplas | Sistema por secções |
| Update AJAX — `layer7_settings_ajax.php` + `.js` | verificar release sem reload | GitHub latest e versão pkg | GET check; POST update gerado depois | XHR read-only; update permanece POST/CSRF | HTTP/parse/no pkg/up-to-date | Sistema > Licença e atualização |
| Diagnostics — `layer7_diagnostics.php` | observar e reparar estado | PID/version/config, PF rules/tables, logs, nDPI, anti-DoH, enforce | SIGUSR1, SIGHUP, anti-DoH add/remove, repair tables, issue/copy | sinais, config.xml/Unbound, PF ensure/reload, GitHub URL | status/PF/files ausentes, IPv6 warning | Sistema > Diagnóstico |
| Remote access — `layer7_remote_access.php` | compatibilidade de rota antiga | nenhum | nenhum | 302 para Policies `#l7-ra` | — | redirect preservado para Biblioteca filtrada |
| Removal — `layer7_removal.php` | uninstall total | package/flags/log | keep license/config, texto `REMOVER`, submit | job nohup: stop daemon, flush PF, `pkg delete`; hook limpa artefactos | pacote ausente/job em curso/confirmação errada | Sistema > Zona de perigo |
| Block page — `/layer7-blockpage/index.php` + `router.php` | explicar bloqueio ao utilizador final | idioma, copy, host, política opcional, contacto | nenhum | leitura somente; qualquer path serve a página | config ausente; host/policy/contact condicionais | preservada; preview em Sistema > Página de bloqueio |

## 3. Campos, defaults e validações que não podem desaparecer

### 3.1 Configuração geral

| Grupo | Campos | Default/limite | Efeito |
|---|---|---|---|
| serviço | `language`, `enabled`, `mode` | PT; false; monitor | JSON; SIGHUP; PF se estado/mode muda |
| captura | `iface_sel[]` | `lan`; máximo 8; nome real validado | captura daemon |
| detecção | `sni_inspection` | false | SIGHUP, sem MITM |
| anti-QUIC | `block_quic_iface_sel[]` | vazio | PF `block drop` por interface, só enforce efectivo |
| DoT/DoQ | `block_dot_doq` | false | PF porta 853; risco Android/apps |
| enforcement | `enforcement_model` | `legacy_global`; `scoped_hybrid` experimental | flush dinâmico + PF reload se muda |
| log | nível, debug 0–720 min, 1–100 MB, 1–10 cópias | info, 0, 5, 3 | daemon/logrotate |
| syslog | enable/host/port | off, vazio, 514; port 1–65535 | valida IPv4/hostname |
| reports | enable, 1–365 dias, intervalo 5/10/15/30/60 | on, 30, 5 | cron/history |
| eventos detalhe | enable, 1–365 dias, 25–1000 MB, interfaces ≤8 | off, 7, 100, todas | SQLite/log por interface |

### 3.2 Página de bloqueio

Campos preservados: enable, portal IPv4 (auto/manual), título ≤120, mensagem
≤2.000, contacto ≤500, mostrar host, mostrar política, incluir blacklists,
limite 1–4.096 e forçar DNS. Defaults: off, título/mensagem localizados,
mostrar host on, política off, blacklists on, limite 256, force DNS off. Deve
continuar a detectar conflito com Captive Portal nativo antes de aplicar.

### 3.3 Políticas

Campos preservados para criar/editar: id imutável (novo validado), nome ≤160,
prioridade 0–99.999, enabled, action `monitor|allow|block|tag`, tabela tag,
interfaces, hosts/CIDRs de origem, grupos, utilizadores/grupos AD quando
entitled, exclusões CIDR/grupo, hosts destino, apps/categorias nDPI, escopo
global, `quarantine_origin`, dias/horário. Block/tag exige selector; scoped
block exige origem válida; include/exclude conflitantes são recusados.

Perfis preservam: factory/custom/override, id, nome, descrição, ícone, apps,
categorias, hosts, hidden/unhide/delete custom, ligar/desligar individual,
rascunho batch, opções de interface/origem/isentos/acção e reconexão de
política quando o perfil é editado.

### 3.4 Clientes, grupos e excepções

- Devices: selecção múltipla, alias por MAC e atribuição a grupo; IP/MAC,
  hostname, vendor, interface, status e source continuam visíveis.
- Groups: id/nome, até 8 CIDRs, 16 IPs e 64 MACs; resolução actual MAC→IP,
  resync manual e contagem de políticas consumidoras.
- VIP: descrição sanitizada ≤64, IPv4/IPv6/CIDR único, limites daemon,
  reserva DHCP por interface, editor texto, export `.txt`, import `.txt` e
  JSON legado, remoção e modo DNS efectivo.
- Exception: id, enabled, priority, action, hosts ou CIDRs, interfaces,
  edit/delete; `vip-isentos` gerida não pode perder tratamento especial.

### 3.5 Identity e MITM preservados, congelados

Identity mantém enable global; LDAP server/port/TLS/bind DN/password/base,
filtros, depth/members e teste; RADIUS enable/address/port/NAS ACL/secret; DC
agent enable/address/port/ACL/skew/token/secret/TLS. Secrets não entram no JSON
nem no backup.

MITM mantém CA generate/import/export cert-only/delete, status de CA/runtime,
enable, duração timed/permanent conforme contrato, janela, source/destination,
SNI a interceptar, bypass SNI/CIDR, QUIC mode e break-glass. O redesign não
altera gates, helper, CA, PF, entitlement ou veredicto NO-GO.

## 4. Matriz de paridade funcional

`P` significa preservação obrigatória; o teste indicado deve existir antes de
migrar a função.

| Área | Função actual | Arquivo/rota | Estado/condição | Destino proposto | Preservada? | Teste mínimo |
|---|---|---|---|---|---|---|
| Estado | versão pkg/daemon/PID/uptime | status | sempre/read-only | Visão geral | P | daemon on/off |
| Estado | modo pedido vs efectivo e razão | status/settings | licença + `enforce_mode` | Visão geral | P | monitor/enforce/licença inválida |
| Estado | top apps e devices | status | stats disponíveis | Visão geral | P | dados/empty/error |
| Estado | restart daemon | status | POST/CSRF | Sistema/saúde, acção explícita | P | sucesso/falha/timeout |
| Policies | lista, ordem/prioridade e match summary | policies | config | Políticas aplicadas | P | configuração antiga |
| Policies | enable/disable em lote | policies | `pon[]` | Políticas aplicadas | P | save+PF resync |
| Policies | edit por índice/id imutável | policies `?edit=` | índice válido | página exclusiva | P | PRG + retorno/contexto |
| Policies | view lists/detalhes | policies `?view=` | índice válido | painel de detalhe | P | apps/cats/hosts completos |
| Policies | add custom completo | policies | validações | Criar política | P | todos selectors/actions |
| Policies | delete com confirmação | policies | POST/CSRF | menu nomeado/zona perigosa local | P | cancel/confirm/índice inválido |
| Profiles | catálogo 105 e grupos | profiles.json/policies | factory | Biblioteca | P | contagem/id por grupo |
| Profiles | busca, collapse/expand | JS policies | client-side | Biblioteca, URL/local preference | P | teclado/reload |
| Profiles | toggle e rascunho batch | policies | JS + POST | Biblioteca | P | dirty/discard/apply/error |
| Profiles | opções ao activar | policies | modal actual | painel/modal curto | P | action/interface/source/VIP |
| Profiles | criar/editar/override/hide/unhide/delete | policies | custom/factory | Biblioteca > editor | P | limites/reconnect |
| Groups | list/add/edit/delete | groups | config | Clientes > Grupos | P | validação e consumidor |
| Groups | resync MAC→IP | groups | inventário | Grupos | P | DHCP/ARP missing |
| Devices | refresh | devices | GET | Dispositivos | P | preserva filtros |
| Devices | busca/filtros/paginação novos | — | proposta | Dispositivos | novo sem perda | 674+ linhas/320 px |
| Devices | alias por MAC | devices | POST | row/detail edit | P | save parcial/erro |
| Devices | select + assign group | devices | grupo existe | bulk action | P | 0/N selected |
| Exceptions | add/remove VIP directa | exceptions | limites | Excepções/VIP | P | v4/v6/CIDR/duplicado |
| Exceptions | DHCP picker/filtro/select all | exceptions | static maps | Excepções/VIP > DHCP | P | interfaces/empty |
| Exceptions | bulk save | exceptions | texto | Excepções/VIP > lote | P | erro por linha |
| Exceptions | export/import txt + JSON legado | exceptions | POST/file | Excepções/VIP | P | roundtrip/invalid |
| Exceptions | modo DNS VIP | exceptions/inc | Unbound/fallback | status explicativo | P | modo a/b |
| Exceptions | CRUD geral | exceptions | managed vs normal | Excepções | P | edit/delete/managed |
| Catálogo | categorias e apps expansíveis | categories | nDPI list | Catálogo nDPI | P | empty/list |
| Simulador | domain/IP/source/app/category | test | read-only POST | Simulador | P | precedência completa |
| Simulator | veredicto e explicação | test/inc | policies/groups/exc | Simulador | P | allow/block/monitor/no match |
| Allowlist | seed + textarea v4/v6 | allowlist | config | Allowlist | P | validation/dedup |
| Allowlist | apply PF + reload | allowlist | POST | barra de resultado | P | save/PF/SIGHUP separados |
| Blacklists | download snapshot assinado/progresso | blacklists/ajax | token/manifest | Blacklists | P | healthy/degraded/fail-closed |
| Blacklists | regras CRUD e categorias | blacklists | config | Blacklists > Regras | P | add/edit/delete/apply |
| Blacklists | custom domains e whitelist | blacklists | aux files | Blacklists | P | normalize/write failure |
| Blacklists | cron/interval/memory/max | blacklists | limits | Blacklists > Avançado | P | clamps/cron |
| Events | source events/operational | events | log files | Eventos | P | missing/rotate |
| Events | live update/pause/clear visual | events AJAX | JS | Eventos | P | sem apagar disco |
| Events | filter + raw technical toggle | events | client/GET | Eventos | P | context preserved |
| Reports | presets/date/filter/search | reports | GET | Relatórios | P | URL/back/refresh |
| Reports | chart/tops/detail/pagination | reports | DB/history | Relatórios | P | offline lib/empty |
| Reports | export HTML/CSV/JSON | export | GET/read-only | Relatórios | P | filtro/paridade |
| Reports | clear report data | reports | POST/CSRF | zona de perigo local | P | confirm/cancel/logs intactos |
| Settings | todos campos/defaults §3 | settings | POST/CSRF | secções Sistema | P | roundtrip/config antiga |
| Settings | dirty state e validação | parcial | proposta | barra comum | novo sem perda | unload/erro/foco |
| Settings | licença status/register | settings | daemon/server | Licença | P | válida/inválida/timeout |
| Settings | revoke | settings | destrutiva POST | Licença > perigo | P | confirm/disarm/restart |
| Settings | export backup sem secrets | settings | POST download | Backup | P | roundtrip/secret absent |
| Settings | import backup + disarm | settings | upload JSON | Restaurar | P | invalid/old/partial/apply |
| Settings | check update AJAX/fallback POST | settings/ajax/js | GitHub | Atualização | P | HTTP/parse/no asset |
| Settings | install update | settings | URL allowlist | Atualização > confirmação | P | stop/fetch/pkg/start/fail |
| Diagnostics | status/files/rules/tables/logs | diagnostics | read-only | Diagnóstico | P | missing/healthy |
| Diagnostics | SIGUSR1/SIGHUP | diagnostics | daemon up | acções explicadas | P | PID inválido/timeout |
| Diagnostics | anti-DoH add/remove | diagnostics | Unbound | ferramenta avançada | P | config/write/restart |
| Diagnostics | repair PF tables | diagnostics | PF unhealthy | reparação confirmada | P | before/after/failure |
| Diagnostics | GitHub issue/copy URL | diagnostics | summary ≤500 | Suporte | P | privacy/no internet |
| Identity | todas funções §3.5 | identity | entitlement | tab/rota Identity actual; taxonomia Clientes apenas documental | P | gates/secrets/fail-safe |
| MITM | todas funções §3.5 | mitm | entitlement/runtime/CA | tab/rota MITM actual; taxonomia Sistema apenas documental | P | gates/break-glass; NO-GO |
| Removal | keep licence/config precedence | removal | installed | Zona de perigo | P | 4 combinations |
| Removal | typed confirmation/job/status/log | removal | POST/CSRF | Zona de perigo | P | wrong text/running/complete |
| Block page | PT/EN/ES copy/host/policy/contact | blockpage | public | página pública | P | locale/XSS/empty |
| Navegação | rotas e deep links antigas | inc/XML/redirect | bookmarks | redirects compatíveis | P | cada URL antiga |
| Segurança | privilégios por página | headers | pfSense ACL | mesmas/fronteiras menores | P | RBAC deny/allow |
| Segurança | CSRF e server validation | guiconfig/handlers | mutações | inalterado | P | token missing/invalid |
| Segurança | nenhuma mutação GET | todas | contrato | inalterado | P | crawler GET read-only |
| Plataforma | shell `Services > Layer7` e breadcrumb pfSense | XML/header/footer | todas as páginas | preservar nativamente | P | comparação com package nativo |
| Plataforma | tabs/subtabs, ordem e rotas existentes | `layer7.inc`/XML | navegação actual | organização baseline | P | cada tab/deep link/active state |
| Plataforma | secções escuras, linhas planas, label esquerda, campo/help direita | `Form_*`/PHP/CSS/include | formulário WebGUI | reproduzir estrutura nativa, sem cards decorativos | P | comparação lado a lado com formulário pfSense |
| Plataforma | tabelas, alerts e botões pfSense | PHP/CSS/include | tema WebGUI | primitivos nativos, sem design system paralelo | P | tema/desktop/320/CSS próprio mínimo e escopado |
| Plataforma | ausência de cards/sombras/chips/sticky bars | todas | contrato ADR-0037 | estrutura plana e densa | P | revisão visual + busca CSS/classes |
| Plataforma | `head.inc`/`foot.inc` e assets WebGUI | todas | pfSense pré-carrega tema/runtime | consumir sem duplicar | P | DOM: CSS/JS/fontes por rota |
| Plataforma | `Form.class.php`/`Form_*` antes de HTML manual | formulários | API disponível na versão alvo | componente nativo | P | revisão de fonte + DOM comparado |
| Plataforma | zero `<style>` inline/`style=` visual na página migrada | `layer7.inc`/PHP | legado actual injeta CSS | remover; excepção só formal | P | busca estática + contagem DOM |
| Plataforma | orçamento de recursos próprios | CSS/JS/libs | somente necessidade funcional | não aumentar sem GO | P | bytes/requests antes/depois |
| Fronteira | lógica funcional congelada | handlers/includes/daemon | trilha frontend-only | mesmos inputs/defaults/efeitos | P | diff de handler + requests/JSON/efeitos |

## 5. Dependências e superfícies alteradas por acção

| Acção | Ficheiros/estado | Serviço/PF/external |
|---|---|---|
| save geral | `layer7.json` | SIGHUP; PF reload se relevante; blockpage/Unbound/cron |
| save policies/groups/exceptions | `layer7.json` | PF resync, tabelas `layer7_*`, SIGHUP |
| save allowlist | `layer7.json` | `layer7_allow_dst`, SIGHUP, PF reload |
| blacklists | config/custom/state/cache/LKG | updater externo, cron, PF/tabelas, SIGHUP |
| Identity | JSON + secrets/cert separados | LDAP/RADIUS/DC, daemon reload; entitlement |
| MITM | JSON + CA/key/gates | tlsproxy, PF/NAT, helper; entitlement/failsafe |
| reports | JSON + cron + SQLite/history/logs | collector/purge; sem PF |
| license | `.lic`/state + JSON mask | license server via daemon, restart, disarm add-ons |
| backup/import | download/upload; JSON + blacklists + custom profiles | import: SIGHUP + PF reload |
| update | `/var/db/layer7/layer7-update.pkg` temporário | GitHub, stop/start daemon, `pkg add -f` |
| diagnostics anti-DoH | pfSense `config.xml`/Unbound | write_config + Unbound; PF repair opcional |
| removal | flags/log/script temporários e hook package | stop daemon, flush `layer7_*`, pkg delete |

## 6. Critério para actualizar esta matriz

Durante GUI1–GUI7, cada PR/bloco deve marcar as linhas tocadas e anexar o
teste de paridade. Uma função só pode mudar de `P` para retirada/substituída
com GO humano, backlog e, se alterar contrato de segurança/arquitectura, ADR.
Até então a ausência de uma linha na nova interface é regressão.

**GUI1 piloto Status (`2026-08-31`, pendente de gates/commit):** a linha Status
mantém todos os dados e a acção `restart_service`. A view passou a
`Form_Section`/`Form_StaticText` (estado + resumo), dois `panel` nativos
(top apps / top clientes) e botões nativos (acções). Licença continua a
aparecer via `layer7_gui_mode_badge_html()` / `reason`; `$n_exceptions`
continua calculado e não renderizado (legado pré-piloto). `layer7.inc` não
foi alterado. Gate estático: `tests/functional/test_status_native_view.php`.
