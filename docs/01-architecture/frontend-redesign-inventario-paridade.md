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
| Status — `layer7_status.php` | saúde operacional | versão pkg/daemon, PID, config, stats, modo pedido/efectivo, licença, políticas, top apps/devices | `restart_service` | `layer7_restart_service()`; status recalculado | daemon/config/stats ausentes; licença impede enforce | GUI1 PUBLICADO `v1.9.80`; view nativa pfSense; appliance/visual pendentes; taxonomia Visão geral |
| Devices — `layer7_devices.php` | inventário e identidade legível do cliente | DHCP/ARP, MAC, hostname, vendor, interface, online/source, aliases, grupos | `alias[MAC]`, `assign_macs[]`, `assign_group` | grava aliases; atribui MACs a grupo; resolve IPs; save JSON + PF resync | MAC inválido, grupo inexistente, save falha; filtro GET não altera persistido | GUI4 V1 **implementado, pendente de gates/commit**; list paginado + batch completo + edit individual |
| Policies — `layer7_policies.php` | aplicação, catálogo e autoria de políticas | JSON, 105 perfis factory/custom/override, grupos, VIP, nDPI, stats | activação de perfil; editor de perfil; batch toggles; política nova/editar; delete | save JSON/perfis custom; resync PF; anti-DoH em perfil; redirects após edit | entitlement Identity, scoped gate, conflitos include/exclude, limites, hidden/override | Proteção > Políticas / Biblioteca / Editar |
| Groups — `layer7_groups.php` | agrupar origens | grupos, políticas consumidoras, inventário | id/nome, CIDRs, hosts, MACs; edit/delete/resync | save JSON + PF resync; resolve MAC→IP | id único/válido; CIDR/IPv4/MAC e limites; empty | GUI4 V2 **implementado, pendente de gates/commit**; list + edit/new exclusivos |
| Exceptions — `layer7_exceptions.php` | isenção global e excepções gerais | `vip-isentos`, labels, DHCP static maps, modo DNS VIP, exceptions | VIP directa; picker DHCP; bulk; import file; exception add/edit/delete | save JSON + PF resync(true); export txt | limites 32 IP/16 CIDR, alvo/descrição, host/CIDR, interfaces, managed exception | Proteção > Excepções e VIP |
| Categories — `layer7_categories.php` | referência nDPI | `layer7_ndpi_list()` protocolos/categorias | nenhum | expandir/consultar apenas no browser | nDPI indisponível/vazio | V3 **implementado, pendente de gates/commit**; consulta nativa (`details`/`summary` + tabela); busca `for`/`id`; Enter/Space browser pendentes |
| Simulator — `layer7_test.php` | explicar decisão sem aplicar | políticas, grupos, selectors, precedência | domínio/IP destino, IP origem, app, categoria | avaliação PHP somente; sem save/PF | input inválido; allow/exception/block/monitor/no match | Proteção > Simulador — **V9 view nativa implementada, revisão gerencial local PASS** |
| Identity — `layer7_identity.php` | configurar user↔IP add-on | entitlement, LDAP, RADIUS accounting, DC agent, secrets separados, teste LDAP | enable; LDAP; RADIUS; DC; generate/clear secrets; save/test | valida, save secrets/config, TLS cert DC, disarm se sem entitlement, SIGHUP | bloqueado sem entitlement; teste/secret/runtime errors | tab/rota Identity actual — **V12 view nativa implementada, gates locais PASS; fila 20.37 fechada; pendente revisão independente** |
| MITM — `layer7_mitm.php` | configurar inspeção TLS governada | entitlement, CA, runtime/helper, janela, source/dest/SNI/bypass | gerar/importar/exportar/apagar CA; enable/window/intercept/bypass; break-glass | ficheiros CA 0600, helper sync, PF reload, failsafe rollback | sem entitlement/runtime/CA; expiry; helper falha; NO-GO permanente | tab/rota MITM actual — **V13 view nativa implementada, gates locais PASS; fila 20.37 fechada; NO-GO funcional permanente; pendente revisão** |
| Blacklists — `layer7_blacklists.php` | snapshot assinado, regras e categorias | config, discovered, subscription, runtime/fallback/LKG/stats | download; regra; categoria custom; whitelist domínio; limites/cron | fetch assíncrono, saves auxiliares, `layer7_bl_apply()`, cron/PF | token ausente/expirado; degraded/fail-closed; memória/limites | Proteção > Blacklists — **V14 view nativa implementada, gates locais PASS; backend congelado; pendente revisão** |
| Blacklist progress — `layer7_bl_ajax.php` | polling de download | ficheiro de progresso | nenhum | GET `action=progress`, text/no-store | 400 para acção inválida | componente de Blacklists |
| Allowlist — `layer7_allowlist.php` | destinos na allowlist Layer7 (sem bypass pfSense nativo) | seed + `dst_allowlist` | textarea IPv4/IPv6/CIDR | save JSON, apply tabela PF, SIGHUP, filter reload | entrada inválida/dedup/save/apply | Proteção > Allowlist — **V5 view nativa implementada, gates locais WASM PASS; pendente revisão independente** |
| Events — `layer7_events.php` | investigação operacional/live | `layer7-events.log` ou `layer7d.log/system.log`, storage | GET source/filter; AJAX source/filter | leitura e explicação; pause/update/clear apenas visual | ficheiro ausente; empty; filtro sem resultado | Atividade > Eventos |
| Reports — `layer7_reports.php` | análise executiva | SQLite/history, config de detalhe, timeline/top/events | GET período/src/host/action/q/page; POST clear | ingest incremental; filtros/paginação; clear DB | DB/detail/collector indisponível; chart offline; empty | Atividade > Relatórios — **V10 view nativa implementada, revisão gerencial local PASS** |
| Report export — `layer7_reports_export.php` | exportar visão filtrada | mesmas fontes/filtros | GET `format=html|csv|json` | download até 2.000 eventos | formato/fonte sem dados | acção em Relatórios |
| Settings — `layer7_settings.php` | configurar subsistemas e operar sistema | config, interfaces, enforce efectivo, CP conflict, licença, reports, update | geral 53 campos; reports; register/revoke; export/import; check/update | save/SIGHUP; PF reload; blockpage/Unbound; cron; daemon activate/restart; fetch/pkg add | validações e gates abaixo; mensagens múltiplas | Sistema por secções — **V15 view nativa implementada, gates locais PASS; backend congelado; pendente revisão** |
| Update AJAX — `layer7_settings_ajax.php` + `.js` | verificar release sem reload | GitHub latest e versão pkg | GET check; POST update gerado depois | XHR read-only; update permanece POST/CSRF | HTTP/parse/no pkg/up-to-date | Sistema > Licença e atualização |
| Diagnostics — `layer7_diagnostics.php` | observar e reparar estado | PID/version/config, PF rules/tables, logs, nDPI, anti-DoH, enforce | SIGUSR1, SIGHUP, anti-DoH add/remove, repair tables, issue/copy | sinais, config.xml/Unbound, PF ensure/reload, GitHub URL | status/PF/files ausentes, IPv6 warning | Sistema > Diagnóstico — **V8 view nativa implementada, gates locais PASS; pendente revisão independente** |
| Remote access — `layer7_remote_access.php` | compatibilidade de rota antiga | nenhum | nenhum | 302 para Policies `#l7-ra` | — | redirect preservado para Biblioteca filtrada |
| Removal — `layer7_removal.php` | uninstall total | package/flags/log | keep license/config, texto `REMOVER`, submit | job nohup: stop daemon, flush PF, `pkg delete`; hook limpa artefactos | pacote ausente/job em curso/confirmação errada | Sistema > Zona de perigo — **V11 view nativa implementada, revisão gerencial local PASS** |
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

**Residual V6a — limites de hosts (pré-existente; não corrigido neste bloco):**

| Camada | Limite efectivo | Notas |
|--------|-----------------|-------|
| Handler/GUI exceções gerais | **16 excepções** na lista | `layer7_exceptions.php` L123–125 (`count($exceptions) >= 16`) |
| `layer7_parse_ip_textarea()` (`layer7.inc` ~L5245) | **64** hosts válidos por textarea | `count($out) < 64` no parser PHP partilhado |
| Daemon `L7_EXC_MAX_HOSTS` (`policy.h` L131) | **32** hosts por excepção | `policy.c` ~L775 parse com `L7_EXC_MAX_HOSTS` — entradas além disso não entram no motor (BG-072) |

A view V6a neutralizou apenas o help da área **exceções gerais** para
«Hosts (IP)» e «Um IP por linha. Pode combinar com CIDRs.» (sem prometer
homogeneidade 64). VIP/DHCP/import mantêm copy e limites próprios até V6b/V6c.
Alinhamento UI↔PHP↔daemon **não** foi corrigido neste bloco — ver BG-072.
**Risco V6a (view exceções gerais):** médio — monta POST, modos exclusivos
list/new/edit e retry raw; rollback = delta view/testes/i18n/docs vs baseline V6a.
**Revisão independente final gerente PASS local** (`2026-09-05`); pendente commit/visual/CE/CSRF/appliance.

**V6b1 Lista VIP (consulta `?vip=1`, adição `?vip_add=1`, modos exclusivos):**

- Rotas dedicadas; consulta geral sem bloco VIP inline; ponte bookmark `#l7-vip-list` GET-only.
- Tabela nativa 0/1/48 entradas; add manual com retry raw; confirm remover com `JSON_HEX_*`.
- DHCP/lote/import/export **migrados em V6b2a/V6b2b** (modos exclusivos); handlers intactos.
- Provas: **213** asserções VIP (`PASS:` only) + contrato bootstrap **10**; revisão gerente `2026-09-05` PASS.
- **Risco V6b1:** apresentação de formulários e navegação; validação visual/teclado/CSRF efectivos pendentes.
- Rollback: delta V6b1 vs `baseline-v6b1-vip` (SHA `b0efcd8…`), preservando V1–V6a.
- **Pendente commit/visual/CE/CSRF/appliance**.

**V6b2a DHCP exclusivo (`?vip_dhcp=1#l7-vip-list`):**

- Modo dedicado; consulta VIP sem tabela DHCP embutida; link «Adicionar de reservas DHCP».
- `Form_Section` por interface; POST `add_vip_from_dhcp` + `vip_dhcp_ip[]`; filtro/seleção JS real (`l7setVisibleDhcpChecks`, `l7filterDhcpIface`).
- POST erro (incl. limite 32) preserva seleção disponível + formulário retry; GET no limite bloqueia entrada inicial.
- Lote/import/export migrados V6b2b; freeze cumulativo (**21** `PASS:`).
- Provas: **162** `PASS:` DHCP (`effects` **19**, json audit **36**, harness **39**, jsdom **38** padrão / **46** com pin CSS, payload **30**); gate portátil `test_exceptions_vip_dhcp_json_audit.php` (**não** grava evidência gerencial). Evidência independente do gerente: `/private/tmp/layer7-coordenacao-20260904/evidencia-gerente/v6b2a-json-independente.json` (12 cenários). Regressão VIP cumulativa intacta.
- **Revisão independente final gerente PASS local** (`2026-09-05`; fonte revista SHA `c72a5db5…`).
- **Risco V6b2a:** apresentação/navegação; adição parcial no limite 32 (helper existente); visual/teclado/CSRF/appliance pendentes.
- Rollback: delta V6b2a vs `baseline-v6b2a-dhcp` (SHA `e3d2169…`), preservando V1–V6b1.
- **Pendente commit/visual/CE/CSRF/appliance**.

**V6b2b lote/import/export (`?vip_bulk=1` / `?vip_import=1` / export POST na consulta):**

- Consulta `?vip=1`: links nativos bulk/import + export em `Form_Section` nativo (sem toolbar/margens inline).
- Modos dedicados bulk/import com `Form`/`Form_Textarea` (12 linhas, sem style inline) e multipart import.
- Avisos estáticos no lote: substitui entradas directas + limpa `source_groups`; lote vazio remove entradas (sem depender só de JS).
- Confirmação `onsubmit` + `JSON_HEX_*` (bulk/import); POST erro abre modo correspondente; sucesso volta consulta.
- Handlers VIP 15–114 byte-idênticos; lote/import via `layer7_vip_import_from_raw` limpam `source_groups` (efeito original preservado).
- Provas: bulk/import effects **34**; json audit portátil **56** (gate `test_exceptions_vip_bulk_json_audit.php`; evidência gerente externa **não** copiada); export Node **72** (handler real + exit instrumentado); harness **32**; jsdom payload **26** + confirm **20**; freeze **21**; bootstrap **18**; total V6b2b **240** `PASS:`.
- **Revisão local Composer 2.5 PASS** (`2026-09-05`); correção export pós-FAIL gerente WASM; pendente revisão independente final/commit/visual/CE/CSRF/appliance.
- **Risco V6b2b:** copy/UX substituição total; upload requer re-selecção após erro; HTTP download/CSRF pendentes.
- Rollback: delta V6b2b vs `baseline-v6b2b-vip-import` (SHA `c72a5db5…`), preservando V1–V6b2a.
- **Pendente commit/visual/CE/CSRF/appliance** — apresentação local concluída (**V6c**); homologação integral pendente.

**V6c fechamento visual (view nativa pfSense):**

- Removidos `layer7_render_styles()`/`layer7_render_footer()` e wrappers `layer7-page`/`layer7-admin-block`/`layer7-form-card`; painéis `panel panel-default`; crédito nativo local + `foot.inc`.
- Visibilidade JS: `.hidden` Bootstrap (filtro VIP/DHCP); sem `is-hidden` nem `style=` de layout.
- `require_once("classes/Form.class.php")` preservado na posição original (~linha 359); handlers byte-idênticos pre-view (**18324** bytes vs baseline V6c SHA `749b54d…`).
- Provas: native_view **84**; freeze **32**; regressão V6a–V6b2b intacta (handlers **2**, harness VIP **127**, jsdom/payload cumulativos).
- **Revisão gerencial local PASS** (`2026-09-05`; `/private/tmp/layer7-coordenacao-20260904/revisao-gerente-v6c.md`); pendente commit/visual real/CE/CSRF/HTTP export/appliance.
- **Risco V6c:** apresentação/navegação; homologação visual/teclado/browser real **não** alegada por jsdom.
- Rollback: delta V6c vs `baseline-v6c-exceptions` (SHA `749b54d…`), preservando V1–V6b2b.
- Defeito funcional «bloqueio que libera depois» = etapa posterior; headers HTTP export pendentes.

**V7 Eventos (view nativa pfSense — `layer7_events.php`):**

- Removidos `layer7_render_styles()`/`layer7_render_footer()` e wrappers `layer7-page`/`layer7-admin-block`/`layer7-toolbar`; painéis nativos com IDs (`l7-events-storage`, `l7-events-live`, `l7-events-filter`, …).
- Prefixo congelado byte-idêntico salvo `layer7_events_render_row` (**1971** bytes vs baseline V7 SHA `0e146d6…`); exec/tail/ajax/limites **300**/**60** intactos.
- `layer7_events_render_row`: `list-group-item` compacto por tom + `<details>`/`<pre class="pre-scrollable">` (raw acessível sem JS); ordem when/title/summary/raw e escaping preservados.
- Monitor: `#l7-live-view` com `list-group pre-scrollable` (altura/overflow nativos; `scrollTop` efectivo); ajax/pause/refresh/clear visual/buffer **500**/merge/localStorage `l7-events-show-tech` preservados; limpar visualização **não** apaga logs em disco.
- Lista estática `#l7-events-list` também `list-group pre-scrollable`.
- Provas: native_view **61** + freeze **10** + render_row **13** + payload **12** + jsdom **18**; regressão humanize **31**.
- **Implementado, gates locais Composer 2.5 PASS** (`2026-09-05`); pendente revisão independente final/commit/visual/CE/CSRF/appliance.
- **Risco V7:** apresentação/monitor; homologação visual/teclado/browser real **não** alegada por jsdom.
- Rollback: delta V7 vs `baseline-v7-events` (SHA `0e146d6…`).

**V8 Diagnósticos (view nativa pfSense — `layer7_diagnostics.php`):**

- Removidos `layer7_render_styles()`/`layer7_render_footer()` e wrappers `layer7-page`/`layer7-admin-block`/`layer7-form-card`/steps-chips do report; painéis nativos com IDs (`l7-diag-root`, `l7-diag-summary`, `l7-pf`, `l7-dns`, `l7-diag-pf-details`, `l7-actions`, `l7-report-error`, `l7-diag-logs`).
- Prefixo congelado byte-idêntico até `$pgtitle` (**11383** bytes vs baseline V8 SHA `8d47d5e…`); handlers/exec/PF/Unbound/error-report intactos.
- Relato de erro: `error_summary` opcional (`rows=3`, `maxlength=500`); 7 metadados + privacy em `dl-horizontal`/listas; submitters `report_error`/`copy_error_report` independentes; URL readonly preservada.
- `remove_anti_doh`: confirmação `onsubmit` com atributo em **aspas simples** + `json_encode(..., JSON_HEX_*)` (padrão Exceptions); sem `onclick` duplicado.
- Dumps PF: collapse Bootstrap + `<pre class="pre-scrollable">`; logs recentes idênticos em escaping.
- Provas: native_view **74** + freeze **11** + payload_static **25** + payload_dom **57** + jsdom **28** (**195** cumulativos); gate JS: atributo `onsubmit` decodificado + delimitador aspas simples no **HTML fonte** PHP; `dispatchEvent(submit)` com handler instalado (`defaultPrevented`/retorno/`confirm` 1×).
- **Implementado, gates locais Composer 2.5 PASS** (`2026-09-05`); pendente revisão independente final/commit/visual/CE/CSRF/appliance.
- **Risco V8:** apresentação/acções sensíveis; homologação visual/teclado/browser real **não** alegada por jsdom.
- Rollback: delta V8 vs `baseline-v8-diagnostics` (SHA `8d47d5e…`).

**V9 Teste de políticas (view nativa pfSense — `layer7_test.php`):**

- Removidos `layer7_render_styles()`/`layer7_render_footer()` e wrappers `layer7-page`/`layer7-admin-block`/`layer7-lead`; formulário via `Form(false)` + `Form_Section`/`Form_Input`/`Form_Select`; botão `run_test` manual em `Form_StaticText`.
- Prefixo congelado byte-idêntico até `$pgtitle` (**10225** bytes vs baseline V9 SHA `a255060…`); motor `l7_run_policy_test`/handlers/DNS simulado intactos.
- Resultados: painéis/alerts/tabela nativos; veredicto com classes semânticas (`alert-danger`/`success`/`info`); notas enforce/monitor e aviso de simulação preservados; sem `style=` inline.
- Provas: native_view **51** + freeze **10** + payload_static **13** + payload_dom **31** + results **21** (**126** cumulativos); fixture nDPI V3 (`l7hc_fixture_catalog_472`: **472** protocolos / **20** categorias; paridade todas `<option>` baseline/candidato; não catálogo appliance).
- **Implementado, gates locais Composer 2.5 PASS** (`2026-09-05`); **revisão gerencial local PASS** (`revisao-gerente-v9.md`); pendente commit/visual/CE/CSRF/appliance.
- **Risco V9:** simulação/explicação de veredicto; homologação visual/teclado/browser real **não** alegada por jsdom.
- Rollback: delta V9 vs `baseline-v9-test` (SHA `a255060…`).

**V10 Relatórios (view nativa pfSense — `layer7_reports.php`):**

- Removidos `layer7_render_styles()`/`layer7_render_footer()` e wrappers `layer7-page`/`layer7-admin-block`/`l7-kpi-card`; painéis nativos com IDs (`l7-reports-root`, `l7-reports-filters`, `l7-reports-summary`, `l7-reports-chart`, `l7-reports-tops`, `l7-reports-events`).
- Prefixo SQLite/ingestão/handlers/export-backend congelado byte-idêntico até `$pgtitle` (**5496** bytes vs baseline V10 SHA `17137833…`); `resolveIdentityByIp`/filtros/paginação/timeline/tops intactos.
- Filtro GET: `form-horizontal` `#l7r-filters-form`; labels `for`/`id` (`l7r-filter-from`..`l7r-filter-q`); links período/export HTML|CSV|JSON com mesmos queryparams; `layer7_reports_export.php` intocado.
- `clear_all_reports`: confirmação `onsubmit` com atributo em **aspas simples** + `json_encode(..., JSON_HEX_*)` (padrão V8); sem `onclick` duplicado.
- Resumo: seis métricas em `well`/`lead`; Chart.js local (`chart.umd.min.js`) preservado; canvas `height="85"`; fallback `#l7r-chart-empty` com `hidden` + toggle `classList` (sem `style.display` inline).
- Provas: freeze **10** + native_view **33** + render **11** + payload_dom **8** + jsdom **10** (**72** cumulativos); harness view isolada sem SQLite/ingestão/rede; paridade export hrefs baseline/candidato.
- **Implementado, gates locais Composer 2.5 PASS** (`2026-09-05`); **revisão gerencial local PASS** (`revisao-gerente-v10.md`); pendente commit/visual/CE/CSRF/appliance.
- **Risco V10:** apresentação/análise histórica; ingestão SQLite e homologação visual/teclado/browser real **não** alegadas por jsdom; prefixo **nunca** executado nos testes.
- Rollback: delta V10 vs `baseline-v10-reports` (SHA `17137833…`).

**V11 Remoção (view nativa pfSense — `layer7_removal.php`):**

- Removidos `layer7_render_styles()`/`layer7_render_footer()` e wrappers `layer7-page`/`layer7-admin-block`/`layer7-lead`; painéis nativos com IDs (`l7-removal-root`, `l7-removal-warning`, `l7-removal-state`, `l7-removal-after`).
- Prefixo handler/job/flags/script congelado byte-idêntico até `$pgtitle` (**2801** bytes vs baseline V11 SHA `342d6eb6…`); lógica `layer7_pkg_remove_do`/validação `REMOVER`/precedência keep intactas no prefixo.
- Formulário: `Form(false)` + `Form_Checkbox` (`keep_license`, `keep_config`, ambos desmarcados) + `Form_Input` (`layer7_remove_confirm`, placeholder `REMOVER`, `autocomplete="off"`) + botão `layer7_pkg_remove_do` vermelho; form **não** renderizado quando pacote ausente ou job em curso.
- Provas: freeze **14** + native_view **35** + render **11** + payload_dom **26** (**86** cumulativos); 3 estados sintéticos (installed/notinstalled/running); FormData paridade 4 combinações checkboxes + confirmação; **sem** remoção real/appliance.
- **Implementado, gates locais Composer 2.5 PASS** (`2026-09-05`); **revisão gerencial local PASS** (`revisao-gerente-v11.md`); pendente commit/visual/CE/CSRF/appliance; teste destrutivo **não** autorizado.
- **Risco V11:** operação destrutiva na apresentação; validação operacional/CSRF/appliance **não** alegada; teste destrutivo só em clone isolado com autorização futura.
- Rollback: delta V11 vs `baseline-v11-removal` (SHA `342d6eb6…`).

**V12 Identity (view nativa pfSense — `layer7_identity.php`; fila 20.37 FECHADA — só visual):**

- Removidos `layer7_render_styles()`/`layer7_render_footer()` e wrappers `layer7-page`/`layer7-admin-block`/`layer7-lead`; painéis nativos com IDs (`l7-identity-root`, `l7-identity-form`, secções LDAP/RADIUS/DC/limites/teste).
- Prefixo handlers save/testLDAP/token/secrets/limits congelado byte-idêntico até `$pgtitle` (**6682** bytes vs baseline V12 SHA `60cd8162…`); **sem** reabrir trilha funcional Identity/MITM.
- Formulário HTML `form-horizontal` preservado (não `Form_*`); todos os campos/checkboxes/passwords/submitters (`save_identity`, `test_ldap`, `dc_generate_token`) e flags clear intactos; passwords sempre vazios na view; token once só na mesma condição, escapado, sem `user-select` inline.
- Provas: freeze **14** + native_view **32** + render **16** + payload_dom **16** (**78** cumulativos); estados locked/unlocked, segredos “definidos” com campos vazios, token sintético, teste LDAP fixture; FormData paridade 3 submitters + combinações checkboxes/clear; **sem** LDAP/rede/appliance.
- **Implementado, gates locais Composer 2.5 PASS** (`2026-09-05`); pendente revisão independente final/commit/visual/CE/CSRF/appliance.
- **Risco V12:** apresentação de campos sensíveis; validação operacional/CSRF/appliance **não** alegada; fila Identity/MITM permanece fechada funcionalmente.
- Rollback: delta V12 vs `baseline-v12-identity` (SHA `60cd8162…`).

**V13 MITM (view nativa pfSense — `layer7_mitm.php`; fila 20.37 FECHADA — só visual; NO-GO funcional permanente):**

- Removidos `layer7_render_styles()`/`layer7_render_footer()` e wrappers `layer7-page`/`layer7-admin-block`/`layer7-lead`; painéis nativos com IDs (`l7-mitm-root`, `l7-mitm-form`, secções CA/inspeção/status).
- Prefixo handlers/gates/CA/download/import/salvar/breakglass congelado byte-idêntico até `$pgtitle` (**8391** bytes vs baseline V13 SHA `ee85f080…`); **sem** reabrir MITM funcional; GET da página **não** executado nos testes (expiração de janela).
- Formulários POST preservados (5 forms): `mitm_break_glass`, `mitm_save_bypass`, `mitm_ca_generate`/`import`/`export`/`delete`; campos PEM/key sempre vazios; radios `class="radio"`; confirmações `json_encode`; disabled/checked/conditions intactos.
- Provas: freeze **16** + native_view **43** + render **19** + payload_dom **28** (**106** cumulativos); estados locked/unlocked, CA presente/ausente, effective/gates off, timed/until_off; FormData paridade 6 submitters; **sem** CA/rede/appliance.
- **Implementado, gates locais Composer 2.5 PASS** (`2026-09-05`); pendente revisão gerencial/commit/visual/CE/CSRF/appliance.
- **Risco V13:** apresentação de campos sensíveis (PEM/key); validação operacional/CSRF/appliance **não** alegada; MITM funcional permanece NO-GO.
- Rollback: delta V13 vs `baseline-v13-mitm` (SHA `ee85f080…`).

**V14 Blacklists (view nativa pfSense — `layer7_blacklists.php`; backend/download/regras congelados):**

- Removidos `layer7_render_styles()`/`layer7_render_footer()` e wrappers `layer7-page`/`layer7-admin-block`/`layer7-form-card`; painéis nativos com IDs (`l7-blacklists-root`, `l7-download`, `l7-rules`, `l7-custom`, `l7-whitelist`, `l7-bl-settings`).
- Prefixo handlers/download/regras/whitelist/settings congelado byte-idêntico até `$pgtitle` (**10095** bytes vs baseline V14 SHA `926e9099…`); **sem** alterar `layer7.inc`, AJAX, download/update/shell, subscriptions ou backend.
- Forms POST preservados; submitters **sem** `value` (FormData envia string vazia — **não** `1`); hidden `rule_index`/`cat_id`; checkboxes `rule_cats[]`; JS polling `2000`/`300000` ms e filtros/seleção visível literais; confirmações `json_encode`.
- Provas: freeze **18** + native_view **40** + render **15** + payload_dom **27** + js **13** (**113** cumulativos); lista/edição/erro/rawHTML; FormData 7 submitters; XHR stub sem rede; **sem** download/appliance.
- **Implementado, gates locais Composer 2.5 PASS** (`2026-09-05`); pendente revisão gerencial/commit/visual/CE/CSRF/appliance.
- **Risco V14:** apresentação de regras/categorias; defeito de bloqueio relatado **não** declarado resolvido; validação operacional/CSRF/appliance **não** alegada.
- Rollback: delta V14 vs `baseline-v14-blacklists` (SHA `926e9099…`).

**V15 Settings (view nativa pfSense — `layer7_settings.php`; backend/licença/update/import congelados):**

- Removidos `layer7_render_styles()`/`layer7_render_footer()` e wrappers `layer7-page`/`layer7-admin-block`; painéis nativos com IDs (`l7-settings-root`, `l7-servico`, `l7-relatorios`, `l7-sistema`, `l7_pkg_update`).
- Prefixo handlers/licença/backup/update congelado byte-idêntico até `$pgtitle` (**24306** bytes vs baseline V15 ficheiro SHA `f6370309…`); **sem** alterar `layer7_settings_update.js`, handlers, `layer7.inc`, daemon, PF ou Identity/MITM.
- Forms POST preservados: `save_scope` general/reports; general submit `name="save" value="1"`; reports hidden `save=1` + botão submit **sem** `name`; checkboxes reports **sem** `value` (FormData `on`); retention presets com `style.display`; licença/backup/update forms separados; `import_file` multipart; IDs update JS intactos.
- Provas: freeze **18** + native_view **52** + render **20** + payload_dom **23** + js **18** (**131** cumulativos); confirm DOM cancel/aceitar; **sem** rede/appliance.
- **FECHADO revisão gerencial** (`2026-09-05`); candidato **`1.9.81`** preparado; pendente diff review/commit/build/publish/GO firewall.
- **Risco V15:** apresentação de secções sensíveis (licença/backup/update); bloqueio funcional relatado **não** declarado resolvido; validação operacional/CSRF/appliance **não** alegada.
- Rollback: delta V15 vs `baseline-v15-settings` (SHA ficheiro `f6370309…`).

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
| Policies | lista, ordem/prioridade e match summary | policies `list` | config | Políticas aplicadas (primeiro na lista) | P | V4-A: harness + native view |
| Policies | enable/disable em lote | policies | `pon[]` | Políticas aplicadas | P | V4-A: `pon[]` acessível; save+PF resync |
| Policies | edit por índice/id imutável | policies `?edit=` | índice válido | modo `edit` na mesma rota; POST `#l7-edit` | P | V4-A: Form_* + jsdom filtros |
| Policies | view lists/detalhes | policies `?view=` | índice válido | modo `view` | P | V4-A: harness view |
| Policies | add custom completo | policies `?new=1` | validações | modo `new` (retry se erro) | P | V4-A: Form_* + jsdom filtros |
| Policies | delete com confirmação | policies | POST | remoção visível `Form_Select` | P | V4-A: sem collapse; confirm |
| Profiles | catálogo 105 e grupos | profiles.json/policies | factory | Biblioteca | P | contagem/id por grupo |
| Profiles | busca, collapse/expand | JS policies | client-side | Biblioteca, URL/local preference | P | teclado/reload |
| Profiles | toggle e rascunho batch | policies | JS + POST | Biblioteca | P | dirty/discard/apply/error |
| Profiles | opções ao activar | policies | modal Bootstrap + GET fallback | painel/modal curto + `?profile_options=` | P | action/interface/source/VIP; **V4-B2a implementado, cobertura B2a completa gates locais (harness 158 + jsdom 226 PASS padrão; pin opcional +11 = 237 cumulativos), pendente revisão final/commit/visual/CE/CSRF/appliance** |
| Profiles | criar/editar/override/hide/unhide/delete | policies | custom/factory | Biblioteca > editor | P | limites/reconnect |
| Groups | list/add/edit/delete | groups | config | Clientes > Grupos | P | validação e consumidor |
| Groups | resync MAC→IP | groups | inventário | Grupos | P | DHCP/ARP missing |
| Devices | refresh | devices | GET | Dispositivos | P | preserva filtros |
| Devices | busca/filtros/paginação novos | devices | GET `q`/`online`/`page` | Dispositivos (consulta) | P (view) | 674+ linhas/320 px |
| Devices | alias por MAC (individual) | devices | POST | `?edit=MAC` | P | save parcial/erro; contexto URL |
| Devices | alias em lote + select/assign | devices | POST `mode=batch` (forms separados) | conjunto completo do filtro (sem corte 50) | P | 0/1/>50; sem MAC; erro restaura valores; payload medido no HTML renderizado; `max_input_vars` só em testes/docs |
| Exceptions | add/remove VIP directa | exceptions | limites | Lista VIP (`?vip=1` / `?vip_add=1`) | P | v4/v6/CIDR/duplicado; **V6b1 revisão gerente PASS local** (`2026-09-05`; **213** `PASS:` VIP) |
| Exceptions | DHCP picker/filtro/select all | exceptions | static maps | Excepções/VIP > DHCP (`?vip_dhcp=1`) | P | interfaces/empty/limite32 retry; **V6b2a revisão gerente PASS local** (`2026-09-05`; **162** `PASS:`) |
| Exceptions | bulk save | exceptions | texto | Excepções/VIP > lote | P | erro por linha; literal congelado V6b1 |
| Exceptions | export/import txt + JSON legado | exceptions | POST/file | Excepções/VIP | P | roundtrip/invalid; literal congelado V6b1 |
| Exceptions | modo DNS VIP | exceptions/inc | Unbound/fallback | status explicativo | P | modo a/b; stub parametrizável em harness V6b1 |
| Exceptions | CRUD geral | exceptions | managed vs normal | Excepções | P | edit/delete/managed; **V6a revisão gerente PASS local** (`2026-09-05`; residual 16/64/32) |
| Catálogo | categorias e apps expansíveis | categories | nDPI list | Catálogo nDPI | P | empty/list; V3 view nativa pendente gates/commit |
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

**GUI1 piloto Status (`2026-08-31`, PUBLICADO `v1.9.80`):** a linha Status
mantém todos os dados e a acção `restart_service`. A view passou a
`Form_Section`/`Form_StaticText` (estado + resumo), dois `panel` nativos
(top apps / top clientes) e botões nativos (acções). Licença continua a
aparecer via `layer7_gui_mode_badge_html()` / `reason`; `$n_exceptions`
continua calculado e não renderizado (legado pré-piloto). `layer7.inc` não
foi alterado. Gate estático: `tests/functional/test_status_native_view.php`
+ lint extraído 27/27 PASS. SHA256
`f7186ee3c58d6ad948b322e45098adaf06f03b0400eab29ed1dd112c2c908782`.
`releases/latest` = `v1.9.80`. Appliance/visual **pendentes**.
