# Testes (referência)

## CI (GitHub Actions)

O workflow **[`.github/workflows/smoke-layer7d.yml`](../../.github/workflows/smoke-layer7d.yml)** em **push/PR** para `main` ou `master`:

- **`scripts/package/check-port-files.sh`** — `pkg-plist` alinhado a **`files/`**;
- instala toolchain no **Ubuntu**;
- executa **`scripts/package/smoke-layer7d.sh`** (compilação completa, incluindo
  módulos Identity, + `-t` + cenários **`-e -n`**).
  Header de entitlements: **`l7_features.h`** (não `features.h`) — evita
  colisão com a glibc no Ubuntu do Actions.

**Limitações:** não compila o **port** `.pkg`, não corre no **pfSense**, não executa **pfctl**, não cobre os roteiros **10a** / **10b** / **11** no appliance. Gate de pacote: [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md). Matriz de regressão: [`layer7-regression-matrix.md`](layer7-regression-matrix.md) (R-01..R-21; gates G0–G7 em [`../09-blocking/plano-gates-producao.md`](../09-blocking/plano-gates-producao.md)).

## Local

- **Harness MITM activação (hang legado + timeout fix + CA-as-peer; `.254` só RO):**
  [`tests/harness/mitm-activate-hang/README.md`](../../tests/harness/mitm-activate-hang/README.md) —
  `run-local-hang.sh`, `run-local-timeout-fix.sh`,
  `run-local-tls-ca-peer.sh`, `run-local-tls-leaf-fix.sh` (sem escrita na `.254`).
- **Harness P4 soak / auth `-T` (P4.2; sem activar MITM):**
  [`tests/harness/mitm-p4-soak/README.md`](../../tests/harness/mitm-p4-soak/README.md) —
  `run-local-auth-fix.sh`, `p4-validate-local.sh`. Novo soak só com GO lab.
- **Regressões próximas ao código (`1.9.46`):**
  - `make -C src/layer7-tlsproxy test-regress` — leaf D1 + política sem bypass
  - `php package/pfSense-pkg-layer7/tests/test_mitm_regress.php` — scope/rdr/anti-QUIC/lifecycle/`filter_configure_safe`
  - Gate C lab: [`evidence/20260809T210753Z-phaseBD-d1-254/`](evidence/20260809T210753Z-phaseBD-d1-254/) (Edge sem `--disable-quic`)
  - GO teste controlado `.254`: [`evidence/20260809T215442Z-phaseBD-d1-254/`](evidence/20260809T215442Z-phaseBD-d1-254/) (`quic_mode=block`; rollback OK; permanente NO-GO)
- **Control-plane timeout:** `php tests/functional/test_ctrl_exec_timeout.php`.
- **Checklist F5 repetível (Onda G 8.2):** [`f5-smoke-checklist.md`](f5-smoke-checklist.md) —
  `sh tests/lab/run-f5-smoke-checklist.sh` (local + builder + appliance).
- `sh tests/lab/run-im9-20.31-identity-mesh.sh` — malha IM9 / 20.31 (Identity
  OFF + ADR-0029 GUI; não activa módulos). Evidência exemplo:
  `docs/tests/evidence/20260808T135500Z-im9-20.31-identity-mesh/`.
- `sh tests/lab/run-gi7-identity-policies.sh` — checklist lab GI7 (AD residual).
- `sh scripts/package/smoke-layer7d.sh` (requer `cc`; compila também
  `log_store.c`, como o port real, e usa `-d` para cobrir enforcement por
  destino).
- `sh tests/run-local.sh` — suite F5 mínima (ver tabela abaixo) + lint PHP/sh
  do pacote.

### Testes em `tests/run-local.sh` (Caminho B / pós-revisão)

| Ficheiro | Bloco | Cobertura |
|----------|-------|-----------|
| `tests/functional/test_allowlist.c` | Fase 1 | allowlist, rejeição `/0`, seed |
| `tests/functional/test_identity_map.c` | IM3 / 20.12–20.14 | API + multi_user + save/load snap + stale skip |
| `tests/functional/test_config_parse.c` | A3 / E0 / FP-015 | parse JSON; fragilidade `enabled` em policies (#12–15) |
| `tests/unit/test_flush_coverage.sh` | BG-061 | contract flush exc_allow, bl_apply, pkg-deinstall |
| `tests/unit/test_rc_pidfile.sh` | BG-053 | pidfile `daemon(8)` sem newline |
| `tests/functional/test_capture_flow_key.c` | BG-055/BG-058/BG-059 | hash bidireccional, probe sem duplicação e finalização nDPI sem aceitar parcial |
| `tests/functional/test_log_store.c` | BG-054 | rotação por tamanho e limite de cópias |
| `tests/functional/test_policy_decide.c` | E1/E5/BG-056 | decisão, escopo, app/host=`pdst`, quarentena=`psrc`, allow preserva índice |
| `tests/functional/test_enforce_scoped.c` | E3/BG-056 | runtime PF (`pdst_N` / `psrc_N` / `pallow_N`), exception block e cache TTL |
| `tests/unit/test_sinkhole_local_guard.sh` | BG-105 | destino local do portal/sinkhole é filtrado antes da decisão; DNS bloqueado mantém auditoria `outcome=sinkhole` |
| `tests/functional/test_checkin_state_persist.c` | BG-128 P2-7/P2-8/P2-10 | save atómico check-in, escape JSON, falha tmp, troca SKU, `.lic` 0600 |
| `tests/functional/test_activate_promote_atomic.c` | BG-128 P3-5 / BG-142 | promote atómico do `.lic`: stop-after-write, candidato inválido, sucesso 0600; nunca `/usr/local/etc` real |
| `tests/functional/test_verify_prod_pubkey.sh` | BG-128 P3-6 / BG-144 | gate PEM do port == SoT: alinhado PASS; outra Ed25519 / em falta / inválido / SoT≠C FAIL |
| `tests/functional/test_bl_src_match.c` | pós-REV-007 | `except_ips` em `l7_bl_rule_matches_src()` |
| `tests/functional/test_scoped_pf_inc.php` | E2/E4/BG-056 | regras PF scoped, quarentena, allow por tag sem `pass quick` e flush |
| `tests/functional/test_interface_normalization.php` | BG-053 | `lan`/`optN` → interface real em todos os consumidores |
| `tests/functional/test_logging_reports.php` | BG-054 | parser de auditoria, sem dupla contagem e cursor através da rotação |
| `tests/functional/test_anti_bypass_migrate.php` | BG-173 | anti-bypass sem `AppleiCloud`; migração idempotente |
| `tests/functional/test_captive_portal_guard.php` | BG-173 | localnets + L7ALLOW; sem rdr no IP do Captive Portal nativo |
| `tests/functional/test_status_native_view.php` | BG-174 / GUI1 | gate estático da view nativa de Status; lê o PHP como texto; sem guiconfig/daemon/PF |
| `tests/functional/test_policies_native_view.php` | BG-174 / GUI3 / V4-B2c Policies | gate estático dos modos list/edit/view/new/library/profile_options/profile_edit; handlers/POST/deep links; contrato `Form_Input` editor (`edit_profile_id`, `l7EditProfileId`); ausência de `layer7_render_styles()`, wrappers `layer7-page`/`layer7-content`/`layer7-lead`, `style=` e `layer7_render_footer()`; crédito nativo; **88 PASS**; sem guiconfig/daemon/PF |
| `tests/functional/test_policies_subnav_native.php` | BG-174 / GUI2a Policies | gate funcional isolado da função real `layer7_render_policies_subnav()` extraída do checkout; `nav` + `ul.nav.nav-tabs` + `li.active` + `aria-current="page"`; prova de índice activo exacto (não só contagem); mutação always-policies rejeitada; escaping adversarial via DOM (sem script, aria-label decodificado, cinco anchors); redirect Remote Access lido em `layer7_remote_access.php` (Location `#l7-ra` + IDENT/MATCH); chamadas nas cinco páginas + `nav_items`/`nav_secondary_items`/`render_tabs` por leitura; **auditoria opcional** `LAYER7_GUI2A_BASELINE` (SHA256 pinado `3ed7f3e2…`; SKIP explícito sem env; FAIL se path inválido); incluído em `tests/run-local.sh`; PHPWASM 8.3; não é pfSense/visual/ACL runtime |
| `tests/functional/test_policies_handlers_baseline.php` | BG-174 / V4-A Policies | prefixo handlers pinado (`source-baseline.php` + SHA256); HEAD opcional se git real |
| `tests/functional/harness-policies-view/` | BG-174 / V4-A Policies | executa list/new/edit/view com stubs; profiles=[]; Form_* pinados; HTML em `generated/` (gitignored); não é pfSense |
| `tests/functional/test_policies_filters.js` | BG-174 / V4-A Policies | jsdom; HTML edit/new; dispara `onkeyup`/`onclick` inline; ocultos preservados; não é visual |
| `tests/functional/harness-policies-library/` | BG-174 / V4-B1 Policies | executa biblioteca com catálogo real (105 perfis); baseline V4-B pinado; navegação/limite24/catálogo vazio; 105 forms payload integral; hidden activo; modais/JS byte-idênticos; HTML em `generated/` (gitignored); incluído em `tests/run-local.sh`; não é pfSense |
| `tests/functional/test_policies_library.js` | BG-174 / V4-B1 Policies | jsdom cumulativo (93 checks): navegação, details, rascunho, apply/fetch/fallback, hidden activo; incluído em `tests/run-local.sh`; não é visual/CSRF real |
| `tests/functional/harness-policies-options/` | BG-174 / V4-B2a Policies | executa modal/página opções com grupos0/2/16, VIP vazio/preenchido, GET `?profile_options=` (limite24/catálogo vazio/ID inválido/oculto), POST erro integral + VIP limpo, escaping/labels, modal sem `aria-hidden` estático; Form nativo; baseline V4-B2 pinado; HTML em `generated/` (gitignored); **158 PASS**; incluído em `tests/run-local.sh`; não é pfSense |
| `tests/functional/test_policies_profile_options.js` | BG-174 / V4-B2a Policies | jsdom cumulativo (**226 PASS** padrão): FormData paridade, inspect DOM original (sem stripScripts), onclick real + hide/reabrir com draft/filtro/contadores rede, stub Bootstrap sem ARIA inventado; prova opcional pin Bootstrap (**+11** via `LAYER7_JQUERY_PIN_JS`/`LAYER7_BOOTSTRAP_PIN_JS`, HTML completo, `runScripts: outside-only`, sem `resources`, VirtualConsole controlado, eventos `shown`/`hidden.bs.modal`) = **237** cumulativos; incluído em `tests/run-local.sh`; não é visual/foco/teclado |
| `tests/functional/harness-policies-edit/` | BG-174 / V4-B2b Policies | editor/criação: modal Bootstrap + GET `?profile_edit=`/`?profile_new=1`, factory/custom/novo/conectado/oculto, POST erro integral, filtro com limpar, handlers/draft byte-idênticos; baseline V4-B2b pinado (`456e855c…`); **135 PASS**; HTML em `generated/` (gitignored); incluído em `tests/run-local.sh`; não é pfSense |
| `tests/functional/test_policies_profile_edit.js` | BG-174 / V4-B2b Policies | jsdom: FormData paridade baseline/candidato (JS original + DOM), confirmações guardar/apagar, POST erro, filtro/limpar, fallback sem jQuery, sync hidden/classe; **142 PASS**; incluído em `tests/run-local.sh`; não é visual/foco/teclado |
| `tests/functional/test_policies_profile_edit_hidden_css.js` | BG-174 / V4-B2b Policies | `getComputedStyle` com Bootstrap/pfSense pin via `LAYER7_BOOTSTRAP_PIN_CSS` (+ `LAYER7_PFSENSE_PIN_CSS` opcional, falha se path inválido); **21 PASS**; runner local SKIP explícito sem env; evidência gerente pós-correção em `/private/tmp/layer7-coordenacao-20260904/evidencia-gerente/b2b-css-corrigido.txt`; incluído em `tests/run-local.sh` |
| `tests/functional/test_devices_native_view.php` | BG-174 / GUI4 Devices | gate estático dos modos list/edit/batch; POST/contexto GET; sem guiconfig/daemon/PF; não prova visual |
| `tests/functional/test_devices_handlers_baseline.php` | BG-174 / V1 Devices | compara o texto dos handlers save_aliases/assign_to_group com HEAD |
| `tests/functional/test_devices_batch_payload.php` | BG-174 / V1 Devices | mede inputs **renderizados** nos dois forms batch (0/1/60/674) + csrf simulado; não estima n+1 |
| `tests/functional/harness-devices-view/` | BG-174 / V1 Devices | executa a view com stubs + Form_* oficiais pinadas; HTML gerado em `generated/` (gitignored); não é pfSense |
| `tests/functional/test_groups_native_view.php` | BG-174 / V2 Groups | gate estático list/edit/new; handlers/âncoras; sem guiconfig |
| `tests/functional/test_groups_handlers_baseline.php` | BG-174 / V2 Groups | handlers add/delete/edit/resync iguais a HEAD |
| `tests/functional/harness-groups-view/` | BG-174 / V2 Groups | executa a view com stubs; save_json false; Form_* oficiais; não é pfSense |
| `tests/functional/test_categories_native_view.php` | BG-174 / V3 Catálogo | gate estático da view nativa; dados/PRIV; sem jQuery; sem guiconfig |
| `tests/functional/test_categories_data_baseline.php` | BG-174 / V3 Catálogo | bloco `layer7_ndpi_list`/ksort/PRIV igual ao HEAD |
| `tests/functional/harness-categories-view/` | BG-174 / V3 Catálogo | executa a view com stubs; nDPI injectado; 0/1/472+; não é pfSense |
| `tests/functional/test_categories_search.js` | BG-174 / V3 Catálogo | jsdom via `LAYER7_JSDOM` ou `require("jsdom")`; PHP via `LAYER7_PHP` ou `php`; HTML da fonte real; busca/click; Enter/Space browser pendentes; não é visual |
| `tests/functional/test_allowlist_native_view.php` | BG-174 / V5 Allowlist | gate estático Form_* nativo; seed fora do form; copy revisada; retry POST sem `$savemsg`; sem guiconfig |
| `tests/functional/test_allowlist_handlers_baseline.php` | BG-174 / V5 Allowlist | prefixo byte-idêntico até `$seed_entries` (SHA256 pinado `b2643919…`); SHA256 ficheiro baseline `f36e0a42…` |
| `tests/functional/test_allowlist_payload.js` | BG-174 / V5 Allowlist | FormData jsdom; `Array.from(fd.entries())` completo (multiplicidade); exige 2 pares + 1 submit; render real baseline PHP pinado + candidato (`render-parity.php`) com mesmos dados; retry POST separado; `form-original.html` = fixture manual auxiliar (não evidência) |
| `tests/functional/test_allowlist_payload.php` | BG-174 / V5 Allowlist | **obsoleto** — imprime SKIP e aponta `.js`; fora do runner; sem PASS |
| `tests/functional/harness-allowlist-view/` | BG-174 / V5 Allowlist | executa view com stubs; validadores extraídos de `layer7.inc`; omite redeclare de helpers do produto; não é suite C de validação |
| `tests/functional/test_form_buttons_payload.js` | BG-174 / V1–V2 | `FormData(form, submitter)`: Form_Button com legenda real PT/EN/ES (nunca `"1"`); lote manual `value=1`; payloads separados; sem Save extra |
| `tests/functional/test_exceptions_native_view.php` | BG-174 / V6a–V6c Exceptions | gate estático list/edit/new/VIP; modos exclusivos; retry POST; `Form_*` manual `value=1`; sem wrappers custom; `Form.class.php` posição original; `.hidden` Bootstrap; **84** `PASS:`; incluído em `tests/run-local.sh` |
| `tests/functional/test_exceptions_handlers_baseline.php` | BG-174 / V6a Exceptions | prefixo handlers VIP 15–114 + exceções 115–303 byte-idêntico ao baseline pinado; incluído em `tests/run-local.sh` |
| `tests/functional/harness-exceptions-view/` | BG-174 / V6a Exceptions | render com stubs; funções puras reais de `layer7.inc` (`inc-pure.php`); rastreio save/resync; HTML em `generated/` (gitignored); incluído em `tests/run-local.sh`; não é pfSense |
| `tests/functional/test_exceptions_effects.php` | BG-174 / V6a Exceptions | contagem + objeto JSON completo save_json; GET/POST inválido e savefalse sem resync; stub `get_real_interface` lan→em0; incluído em `tests/run-local.sh` |
| `tests/functional/test_exceptions_target_summary.php` | BG-174 / V6a Exceptions | `layer7_exc_target_summary` extraída da página (sem cópia no bootstrap); paridade baseline vs candidato |
| `tests/functional/test_exceptions_vip_pure.php` | BG-174 / V6b1 VIP | constantes reais `LAYER7_VIP_MAX_HOSTS=32` / `CIDRS=16` de `layer7.inc`; helpers VIP extraídos em `inc-pure.php`; stubs determinísticos `layer7_daemon_version` e `layer7_resolve_macs_to_ips`; **48** `PASS:` (revisão gerente `2026-09-05`) |
| `tests/functional/test_exceptions_vip_freeze.php` | BG-174 / V6b1–V6c VIP | prefixo/handlers V6a; migração DHCP V6b2a + bulk/import V6b2b + visual V6c intencional; baseline V6c SHA pinado; **32** `PASS:` |
| `tests/functional/test_exceptions_vip_effects.php` | BG-174 / V6b1 VIP | save/resync; JSON completo baseline V6b1 vs candidato; `source_groups` + props raiz; limite 48; **19** `PASS:` |
| `tests/functional/harness-exceptions-view/run-vip.php` | BG-174 / V6b1 VIP | harness render modos `?vip=1` / `?vip_add=1`; links bulk/import; export Form nativo; **56** `PASS:` |
| `tests/functional/test_exceptions_vip_js.js` | BG-174 / V6b1 VIP | jsdom: ponte bookmark; filtro interface; confirm true/false adversarial; zero fetch/submit; **37** `PASS:` |
| `tests/functional/test_exceptions_vip_payload.js` | BG-174 / V6b1 VIP | FormData baseline V6b1 pinada vs candidato; add4/add6/CIDR/remove0/1/48; export consulta; retry raw; **41** `PASS:` |
| `tests/functional/harness-exceptions-view/render-vip-parity.php` | BG-174 / V6b1 VIP | render pareado baseline V6b1 + candidato; contrato `l7he_render_v6b1_baseline` separado de V6a |
| `tests/functional/test_exceptions_vip_dhcp_effects.php` | BG-174 / V6b2a DHCP | save/resync; JSON completo baseline V6b2a vs candidato; limite 32 POST retry zero save; **19** `PASS:`; incluído em `tests/run-local.sh` (bloco PHP) |
| `tests/functional/test_exceptions_vip_dhcp_json_audit.php` | BG-174 / V6b2a DHCP | gate portátil: paridade JSON/efeitos 12 cenários baseline V6b2a vs candidato; **36** `PASS:`; **não** grava evidência gerencial (ver `/private/tmp/layer7-coordenacao-20260904/evidencia-gerente/v6b2a-json-independente.json`); opcional `generated/v6b2a-json-audit-portable.json` com `LAYER7_DHCP_JSON_AUDIT_WRITE=1`; incluído em `tests/run-local.sh` (bloco PHP) |
| `tests/functional/harness-exceptions-view/run-vip-dhcp.php` | BG-174 / V6b2a DHCP | harness render `?vip_dhcp=1`; limite32 GET bloqueado / POST retry; fixture 33; XSS; **39** `PASS:`; incluído em `tests/run-local.sh` (bloco PHP) |
| `tests/functional/test_exceptions_vip_dhcp_js.js` | BG-174 / V6b2a DHCP | jsdom: funções reais da view; filtro/seleção; pin CSS opcional (`LAYER7_BOOTSTRAP_PIN_CSS`); **38** `PASS:` padrão (**46** com pin); incluído em `tests/run-local.sh` (bloco Node) |
| `tests/functional/test_exceptions_vip_dhcp_payload.js` | BG-174 / V6b2a DHCP | FormData baseline V6b2a vs candidato; lista exacta 33 IPs; WAN após limpar; **30** `PASS:`; incluído em `tests/run-local.sh` (bloco Node) |
| `tests/functional/harness-exceptions-view/render-vip-dhcp-parity.php` | BG-174 / V6b2a DHCP | render pareado baseline V6b2a + candidato; contrato `l7he_render_v6b2a_baseline` |
| `tests/functional/test_exceptions_vip_bulk_effects.php` | BG-174 / V6b2b bulk/import | save/resync; bulk TXT/CSV/JSON/vazio/limite33/limite17 CIDR; import upload/parser/BOM; `source_groups` limpos; export movido para Node; **34** `PASS:`; incluído em `tests/run-local.sh` |
| `tests/functional/test_exceptions_vip_bulk_json_audit.php` | BG-174 / V6b2b bulk/import | gate portátil: paridade JSON/efeitos 14 cenários baseline V6b2b vs candidato; **56** `PASS:`; **não** grava/copia evidência gerencial (referência externa `v6b2b-json-independente.json`); opcional `generated/v6b2b-json-audit-portable.json` com `LAYER7_BULK_JSON_AUDIT_WRITE=1`; incluído em `tests/run-local.sh` |
| `tests/functional/test_exceptions_vip_bulk_export.js` | BG-174 / V6b2b export | Node/`runPhp`: `export-subprocess.php` (handler exit, corpo) + `export-probe.php` (handler real, exit instrumentado, zero efeitos); linhas 0/1/48 completas; **72** `PASS:`; headers HTTP **pendentes**; incluído em `tests/run-local.sh` |
| `tests/functional/harness-exceptions-view/run-vip-bulk.php` | BG-174 / V6b2b bulk/import | modos `?vip_bulk=1` / `?vip_import=1`; avisos estáticos; export Form nativo; bookmark ausente bulk/import; **32** `PASS:`; incluído em `tests/run-local.sh` |
| `tests/functional/test_exceptions_vip_bulk_payload.js` | BG-174 / V6b2b bulk/import | FormData/File baseline V6b2b vs candidato; import multipart sintético (nome/tipo/tamanho/conteúdo); bulk/vazio/export; **26** `PASS:`; incluído em `tests/run-local.sh` |
| `tests/functional/test_exceptions_vip_bulk_js.js` | BG-174 / V6b2b bulk/import | jsdom: evento `submit` real + confirm 1× false/true; **20** `PASS:`; incluído em `tests/run-local.sh` |
| `tests/functional/harness-exceptions-view/render-vip-bulk-parity.php` | BG-174 / V6b2b bulk/import | render pareado baseline V6b2b + candidato; contrato `l7he_render_v6b2b_baseline` |
| `tests/functional/harness-exceptions-view/export-handler-lib.php` | BG-174 / V6b2b export | extraccao handler `export_vip_list` da fonte congelada; instrumentação exit (probe) |
| `tests/functional/harness-exceptions-view/export-subprocess.php` | BG-174 / V6b2b export | handler real `export_vip_list` (exit com texto); argv JSON; orquestrado por Node |
| `tests/functional/harness-exceptions-view/export-probe.php` | BG-174 / V6b2b export | eval handler real; exit→`L7heExportProbeExit`; JSON texto + contadores save/resync |
| `tests/functional/harness-exceptions-view/export-fixtures.php` | BG-174 / V6b2b export | fixtures JSON empty/one/full + `expected_lines` para gates export |
| `tests/functional/baseline-v6b2b-vip-import/` | BG-174 / V6b2b | baseline pinada pré-modos bulk/import (SHA `c72a5db5…`) |
| `tests/functional/test_exceptions_harness_bootstrap.php` | BG-174 / V6a–V6b2b | contrato harness: baselines V6a/V6b1/V6b2a/V6b2b distintas + SHA pinado; **18** `PASS:` |
| `tests/functional/harness-exceptions-view/inc-pure.php` | BG-174 / V6a–V6b | extrai funções puras e constantes VIP de `layer7.inc` (brace-match com strings); `L7HE_VIP_MAX_HOSTS_WRONG_FIXTURE=64` documenta valor errado do bootstrap V6a; fronteiras externas stubadas antes da extração |
| `tests/functional/test_exceptions_payload.js` | BG-174 / V6a Exceptions | FormData jsdom; method/action exactos; quatro ações; edit completo; lista16 eon; retry savefalse; render-parity baseline+candidato; incluído em `tests/run-local.sh` |
| `tests/functional/test_exceptions_js.js` | BG-174 / V6a Exceptions | onclick real `l7setChecks` (new/edit, todas as caixas); confirm delete via onsubmit; zero fetch/submit; incluído em `tests/run-local.sh` |
| `tests/functional/test_events_humanize.php` | BG-172 / Eventos | frases operador + filtros humanos/raw; **31** `PASS:` |
| `tests/functional/test_events_native_view.php` | BG-174 / V7 Eventos | gate estático view nativa; monitor `pre-scrollable`; linhas `list-group-item`; **61** `PASS:` |
| `tests/functional/test_events_freeze.php` | BG-174 / V7 Eventos | prefixo congelado salvo `layer7_events_render_row`; baseline V7 SHA pinado; **10** `PASS:` |
| `tests/functional/test_events_render_row.php` | BG-174 / V7 Eventos | HTML adversarial escapado; ordem when/title/summary/raw; **13** `PASS:` |
| `tests/functional/test_events_payload.php` | BG-174 / V7 Eventos | GET filter/source/action/maxlength/ajaxUrl; **12** `PASS:` |
| `tests/functional/test_events_js.js` | BG-174 / V7 Eventos | jsdom: pause/refresh/clear, detalhe técnico, buffer, XHR simulado; **18** `PASS:` |
| `tests/functional/baseline-v7-events/` | BG-174 / V7 Eventos | baseline pinada pré-migração visual (SHA `0e146d6…`) |
| `tests/functional/test_diagnostics_native_view.php` | BG-174 / V8 Diagnósticos | gate estático view nativa; painéis/anchors/handlers; **74** `PASS:` |
| `tests/functional/test_diagnostics_freeze.php` | BG-174 / V8 Diagnósticos | prefixo congelado até `$pgtitle`; baseline V8 SHA pinado; **11** `PASS:` |
| `tests/functional/test_diagnostics_payload.php` | BG-174 / V8 Diagnósticos | gate estático strings submitter/action (não prova mesmo form); **25** `PASS:` |
| `tests/functional/test_diagnostics_payload.js` | BG-174 / V8 Diagnósticos | FormData jsdom; render real baseline/candidato; 7 submitters + summary vazio/adversarial; **57** `PASS:` |
| `tests/functional/test_diagnostics_js.js` | BG-174 / V8 Diagnósticos | jsdom: handler `submit` instalado; `dispatchEvent` cancelável; `defaultPrevented`/confirm 1×; HTML fonte + atributo decodificado; **28** `PASS:` |
| `tests/functional/harness-diagnostics-view/` | BG-174 / V8 Diagnósticos | render view isolada (`render-parity.php`); sem prefixo/exec/rede |
| `tests/functional/baseline-v8-diagnostics/` | BG-174 / V8 Diagnósticos | baseline pinada pré-migração visual (SHA `8d47d5e…`) |
| `tests/functional/test_test_native_view.php` | BG-174 / V9 Teste | gate estático view nativa + labels DOM; **51** `PASS:` |
| `tests/functional/test_test_freeze.php` | BG-174 / V9 Teste | prefixo congelado até `$pgtitle`; baseline V9 SHA pinado; **10** `PASS:` |
| `tests/functional/test_test_payload.php` | BG-174 / V9 Teste | gate estático strings Form (não prova mesmo form); **13** `PASS:` |
| `tests/functional/test_test_payload.js` | BG-174 / V9 Teste | FormData jsdom; fixture nDPI V3 **472**/**20** (`l7hc_fixture_catalog_472`); todas options/ordem; vazio/retry/adversarial domínio; **31** `PASS:` |
| `tests/functional/test_test_results.php` | BG-174 / V9 Teste | render fixtures block/allow/monitor/error/semresultado; **21** `PASS:` |
| `tests/functional/harness-test-view/` | BG-174 / V9 Teste | render view isolada (`render-parity.php`); sem DNS/exec |
| `tests/functional/baseline-v9-test/` | BG-174 / V9 Teste | baseline pinada pré-migração visual (SHA `a255060…`) |
| `tests/functional/test_reports_freeze.php` | BG-174 / V10 Relatórios | prefixo SQLite/ingestão congelado até `$pgtitle`; baseline V10 SHA pinado; **10** `PASS:` |
| `tests/functional/test_reports_native_view.php` | BG-174 / V10 Relatórios | gate estático view nativa + labels DOM; **33** `PASS:` |
| `tests/functional/test_reports_render.php` | BG-174 / V10 Relatórios | render fixtures vazio/detalhe/erro/adversarial; **11** `PASS:` |
| `tests/functional/test_reports_payload.js` | BG-174 / V10 Relatórios | FormData jsdom + paridade export hrefs baseline/candidato; **8** `PASS:` |
| `tests/functional/test_reports_js.js` | BG-174 / V10 Relatórios | Chart stub sem rede + confirmação `clear_all_reports` submit; **10** `PASS:` |
| `tests/functional/harness-reports-view/` | BG-174 / V10 Relatórios | render view isolada (`render-parity.php`); **sem** SQLite/ingestão/rede |
| `tests/functional/baseline-v10-reports/` | BG-174 / V10 Relatórios | baseline pinada pré-migração visual (SHA `17137833…`) |
| `tests/functional/test_removal_freeze.php` | BG-174 / V11 Remoção | prefixo handler/job congelado até `$pgtitle`; baseline V11 SHA pinado; **14** `PASS:` |
| `tests/functional/test_removal_native_view.php` | BG-174 / V11 Remoção | gate estático view nativa + campos/checkboxes; **35** `PASS:` |
| `tests/functional/test_removal_render.php` | BG-174 / V11 Remoção | render fixtures installed/notinstalled/running/adversarial; **11** `PASS:` |
| `tests/functional/test_removal_payload.js` | BG-174 / V11 Remoção | FormData jsdom 4 combinações checkboxes + confirmação; **26** `PASS:` |
| `tests/functional/harness-removal-view/` | BG-174 / V11 Remoção | render view isolada (`render-parity.php`); **sem** remoção real/rede |
| `tests/functional/baseline-v11-removal/` | BG-174 / V11 Remoção | baseline pinada pré-migração visual (SHA `342d6eb6…`) |
| `tests/functional/test_identity_freeze.php` | BG-174 / V12 Identity | prefixo handlers congelado até `$pgtitle`; baseline V12 SHA pinado; **14** `PASS:` |
| `tests/functional/test_identity_native_view.php` | BG-174 / V12 Identity | gate estático view nativa + campos/submitters; **32** `PASS:` |
| `tests/functional/test_identity_render.php` | BG-174 / V12 Identity | render fixtures locked/unlocked/secrets/token/ldap test; **16** `PASS:` |
| `tests/functional/test_identity_payload.js` | BG-174 / V12 Identity | FormData jsdom 3 submitters + combinações checkboxes/clear; **16** `PASS:` |
| `tests/functional/harness-identity-view/` | BG-174 / V12 Identity | render view isolada (`render-parity.php`); **sem** LDAP/RADIUS/DC/rede; fila 20.37 fechada |
| `tests/functional/baseline-v12-identity/` | BG-174 / V12 Identity | baseline pinada pré-migração visual (SHA `60cd8162…`) |
| `tests/functional/test_mitm_freeze.php` | BG-174 / V13 MITM | prefixo handlers/gates congelado até `$pgtitle`; baseline V13 SHA pinado; **16** `PASS:` |
| `tests/functional/test_mitm_native_view.php` | BG-174 / V13 MITM | gate estático view nativa + campos/submitters/forms; **43** `PASS:` |
| `tests/functional/test_mitm_render.php` | BG-174 / V13 MITM | render fixtures locked/unlocked/CA/effective/gates/timed/until_off/confirmDOM; **19** `PASS:` |
| `tests/functional/test_mitm_payload.js` | BG-174 / V13 MITM | FormData jsdom 6 submitters + combinações checkboxes/radio; **28** `PASS:` |
| `tests/functional/harness-mitm-view/` | BG-174 / V13 MITM | render view isolada (`render-parity.php`); **sem** CA/rede/appliance; fila 20.37 fechada; NO-GO funcional |
| `tests/functional/baseline-v13-mitm/` | BG-174 / V13 MITM | baseline pinada pré-migração visual (SHA `ee85f080…`) |
| `tests/functional/test_blacklists_freeze.php` | BG-174 / V14 Blacklists | prefixo handlers congelado até `$pgtitle`; baseline V14 SHA pinado; **18** `PASS:` |
| `tests/functional/test_blacklists_native_view.php` | BG-174 / V14 Blacklists | gate estático view nativa + forms/submitters/JS; **40** `PASS:` |
| `tests/functional/test_blacklists_render.php` | BG-174 / V14 Blacklists | render fixtures lista/edição/erro/rawHTML; **15** `PASS:` |
| `tests/functional/test_blacklists_payload.js` | BG-174 / V14 Blacklists | FormData jsdom 7 submitters (value vazio); **27** `PASS:` |
| `tests/functional/test_blacklists_js.js` | BG-174 / V14 Blacklists | filtros/seleção visível + polling XHR stub; **13** `PASS:` |
| `tests/functional/harness-blacklists-view/` | BG-174 / V14 Blacklists | render view isolada (`render-parity.php`); **sem** download/rede/AJAX real |
| `tests/functional/baseline-v14-blacklists/` | BG-174 / V14 Blacklists | baseline pinada pré-migração visual (SHA `926e9099…`) |
| `tests/functional/test_settings_freeze.php` | BG-174 / V15 Settings | prefixo handlers congelado até `$pgtitle`; baseline V15 SHA pinado; **18** `PASS:` |
| `tests/functional/test_settings_native_view.php` | BG-174 / V15 Settings | gate estático view nativa + forms/scopes/update/retention; **52** `PASS:` |
| `tests/functional/test_settings_render.php` | BG-174 / V15 Settings | render fixtures default/custom/erro/update/licença; **20** `PASS:` |
| `tests/functional/test_settings_payload.js` | BG-174 / V15 Settings | FormData jsdom general/reports/import/update (reports `on` vs `1`); **23** `PASS:` |
| `tests/functional/test_settings_js.js` | BG-174 / V15 Settings | IDs update + retention + confirm cancel/aceitar; **18** `PASS:` |
| `tests/functional/harness-settings-view/` | BG-174 / V15 Settings | render view isolada (`render-parity.php`); **sem** rede/licença/update/import real |
| `tests/functional/baseline-v15-settings/` | BG-174 / V15 Settings | baseline pinada pré-migração visual (SHA ficheiro `f6370309…`) |
| `tests/functional/lib/layer7-test-runtime.js` | testes locais/CI | resolve jsdom/PHP; FAIL explícito se faltar; sem dependência no produto |

- `make -C src/layer7d check` após `make` no mesmo diretório.
- `cd license-server/backend && npm test` para smoke tests puros da trilha
  de sessao/Bearer do painel administrativo.
- `cd license-server/frontend && npm test` para smoke tests puros da camada
  `api.js` e do estado autenticado da SPA.
- `cd license-server/frontend && npm run build` para validar que a SPA ainda
  compila apos mudancas na trilha administrativa.
- `bash -n scripts/license-validation/export-license-evidence.sh` e
  `bash -n scripts/license-validation/export-appliance-evidence.sh` e
  `bash -n scripts/license-validation/export-live-preflight.sh` e
  `bash -n scripts/license-validation/export-schema-preflight.sh` e
  `bash -n scripts/license-validation/init-f3-validation-campaign.sh` e
  `bash -n scripts/license-validation/prepare-f3-preflight.sh` e
  `bash -n scripts/license-validation/run-appliance-activation-scenario.sh` e
  `bash -n scripts/license-validation/run-pfsense-gui-license-flow.sh`
  para smoke syntax dos helpers shell da campanha F3.
- `scripts/license-validation/run-pfsense-gui-license-flow.sh --help` para
  validar a interface minima do helper GUI, incluindo o modo
  `--ssh-target` para GUI no loopback do appliance
  (`https://127.0.0.1:9999/`).

## Matriz de testes

[`test-matrix.md`](test-matrix.md) — 120 testes divididos por categoria
(build, instalação, daemon, config, policy engine, enforcement **inclui F4.3
`force_dns` / anchor NAT e anti-QUIC opcional (ponto 6.7 / sec. 11)**, **blacklists F4.2 (12.1–12.2)**,
GUI, observabilidade, rollback e
addendum de licenciamento/activação da F3, estabilização `_25`, logs `_26`,
correcções `_27`, allow PF seguro `_28`, parser anti-QUIC `_29` e captura
resiliente `_30` e finalização nDPI `_31`).
Estado actual após build `_31`: 99 OK e **21** pendentes. A sintaxe
corrigida passa no parser PF read-only e o pacote extraído passou no builder
FreeBSD 15 (`SHA256 bea385dd…01840`); as regressões FP-019 passam localmente,
e o pacote `_30` passou no builder (`SHA256 3a54c667…e9b40`). FP-020 e o
pacote `_31` passaram no builder (`SHA256 dc5118dd…453e33`). Gates instalados
continuam explicitamente pendentes.
Roteiros de evidência **F4** no appliance (10a / 10b / 11 ↔ matriz; **6.7** com
anti-QUIC opcional e cenário multi-interface / VLAN na secção **11**):
parágrafo *Gates oficiais F4* e tabela *Índice dos roteiros F4* em
[`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) (início;
secção 10+).
A F3.6 passa a decompor
esses 4 blocos pendentes numa matriz manual explicita de 13 cenarios,
pre-requisitos, comandos, evidencias minimas e criterios de
aprovacao/reprovacao, descrita em
[`../01-architecture/f3-validacao-manual-evidencias.md`](../01-architecture/f3-validacao-manual-evidencias.md).
A F3.7 operacionaliza essa matriz em
[`../01-architecture/f3-pack-operacional-validacao.md`](../01-architecture/f3-pack-operacional-validacao.md)
e acrescenta um template minimo em
[`templates/f3-scenario-evidence.md`](templates/f3-scenario-evidence.md). A
F3.8 acrescenta o gate oficial de fechamento em
[`../01-architecture/f3-gate-fechamento-validacao.md`](../01-architecture/f3-gate-fechamento-validacao.md)
e o relatorio final unico da campanha em
[`templates/f3-validation-campaign-report.md`](templates/f3-validation-campaign-report.md):
sem esse relatorio e sem todos os obrigatorios em `PASS`, a F3 nao fecha.
