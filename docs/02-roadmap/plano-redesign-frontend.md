# Plano governado — redesenho do frontend Layer7

**Backlog:** BG-174.
**Estado:** GUI0 documental `3563757`, ADR-0037 `f44a14b` e emenda visual
`c429be3` concluídos após gates PASS. Emenda frontend-only **FEITA no git**
(`3b18f82`) após gates PASS. GUI1 piloto Status **PUBLICADO `v1.9.80`**
(`2026-08-31`; `releases/latest`; SHA256
`f7186ee3c58d6ad948b322e45098adaf06f03b0400eab29ed1dd112c2c908782`;
tag `7bd1fd0`; source/build `b84634c`). `v1.9.79` retirada do download
(latest-only / BG-164; tag git preservada). Appliance/visual **pendentes**
(`.254` SSH timeout; **não** homologado no appliance). GUI3 Policies V4-A
**implementado, revisão local gerente PASS, pendente commit** (modos
list/edit/view/new; handlers intactos; harness/handlers baseline/jsdom;
biblioteca/modais = V4-B; sem `PORTVERSION`). GUI4 Devices (bloco V1, GO visual `2026-09-04`)
**implementado, pendente de gates/commit** (consulta paginada + modo
batch com conjunto completo do filtro + editor individual; sem
`PORTVERSION`). GUI4 Groups (bloco V2, GO visual integral local
`2026-09-04`) **implementado, pendente de gates/commit**. Catálogo
nDPI (bloco V3, GO visual integral local `2026-09-04`)
**implementado, pendente de gates/commit**. **V6a Exceptions revisão independente final gerente PASS local** (`2026-09-05`; pendente commit/visual/CE/CSRF/appliance). **V6b1 Lista VIP revisão independente final gerente PASS local** (`2026-09-05`; **213** `PASS:` VIP + contrato **10**; pendente commit/visual/CE/CSRF/appliance). **V6b2a DHCP exclusivo revisão independente final gerente PASS local** (`2026-09-05`; **162** `PASS:` DHCP + json audit **36**; POST limite 32 preserva retry; pendente commit/visual/CE/CSRF/appliance); **V6b2b lote/import/export implementado, revisão local Composer 2.5 PASS** (`2026-09-05`; modos exclusivos bulk/import; export Form nativo; avisos estáticos grupos/vazio; effects **34** + json **56** + export **72** + harness **32** + jsdom **46** = **240** `PASS:`; headers HTTP export pendentes; handlers intactos; pendente revisão independente final/commit/visual/CE/CSRF/appliance); **V6c fechamento visual revisão gerencial local PASS** (`2026-09-05`; evidência `revisao-gerente-v6c.md`; pendente commit/visual real/CE/CSRF/HTTP export/appliance); **V7 Eventos implementado, gates locais Composer 2.5 PASS** (`2026-09-05`; native_view **61** + freeze **10** + render_row **13** + payload **12** + jsdom **18** + humanize **31**; pendente revisão independente final/commit/visual/CE/CSRF/appliance)). GUI2 e
GUI5–GUI7 ainda não. Sem homologação appliance.
**Canal `latest`:** `1.9.80`.
**Nota de fase:** `GUI0…GUI7` são ondas internas deste plano, não substituem as
fases canónicas F0…F7. A execução técnica cruza F4/F5/F7 e obedece aos seus
gates.

**Contrato humano reafirmado (`2026-08-31`):** o produto está funcional. Esta
trilha não autoriza desenvolver, refatorar ou reinterpretar a ferramenta. O
único problema a resolver é a apresentação e a organização operacional da GUI:
encontrar, compreender e executar as funções existentes com menos confusão,
usando a WebGUI que o próprio pfSense já fornece. Emenda documental
**FEITA no git** (`3b18f82`) após gates PASS.

## 1. Escopo e não-escopo

Objectivo: reorganizar arquitectura da informação, fluxos, formulários,
linguagem, feedback, acessibilidade e responsividade preservando integralmente
as funções inventariadas e a sua semântica de servidor.

### 1.1 Envelope permitido — somente frontend

Pode mudar, uma página por bloco:

- ordem e agrupamento visual de conteúdo já existente;
- divisão de uma página longa em vistas/modos de apresentação nativos;
- HTML/PHP de renderização depois de concluída a lógica do handler;
- labels, help text e mensagens PT/EN/ES, sem alterar o significado técnico;
- tabs/subtabs, filtros, foco, retorno, paginação e progressive disclosure,
  desde que sejam apenas apresentação sobre os mesmos dados e que rotas,
  privilégios, parâmetros e deep links permaneçam compatíveis; se exigirem
  mudar o handler, a onda para e volta ao GO humano;
- JavaScript progressivo apenas para contexto, foco, confirmação, filtro e
  apresentação; tudo deve continuar seguro e compreensível sem JavaScript;
- CSS estritamente funcional quando não existir primitivo equivalente no
  pfSense, mediante excepção documentada e teste de tema/responsividade.

### 1.2 Envelope proibido — lógica funcional congelada

É proibido nesta trilha:

- alterar handlers, decisões, defaults, validações, clamps ou contratos de
  dados para acomodar o novo layout;
- alterar formato/persistência de `layer7.json`, migrações ou secrets;
- alterar comandos, sinais, reload/restart, PF, Unbound, cron, daemon, nDPI,
  licença, update, backup, importação, remoção ou Captive Portal;
- alterar ordem ou condição dos efeitos produzidos pelos POSTs;
- alterar privilégios, CSRF, método HTTP ou transformar acção destrutiva em GET;
- introduzir feature, comportamento, automatismo ou “melhoria” do motor;
- refatorar backend junto com uma mudança visual;
- tocar funcionalmente Identity/MITM; apenas a apresentação existente entra na
  paridade, mantendo os gates e o NO-GO;
- criar componente visual quando `head.inc`, `foot.inc`, `Form.class.php`,
  `Form_*`, Bootstrap/pfSense, `print_info_box()` ou a tabela nativa já resolvem.

Não-escopo sem novo GO:

- mudar daemon, política, PF, licença, defaults ou segurança;
- reabrir Identity/MITM; MITM continua NO-GO permanente;
- criar SPA/framework pesado;
- criar shell, sidebar, paleta, tipografia ou design system concorrente com o
  pfSense;
- reorganizar amplamente tabs/rotas existentes com base apenas na taxonomia de
  cinco áreas;
- mover/renomear rotas/ficheiros antes do lote permitido e sem compatibilidade;
- alterar `PORTVERSION` durante GUI0;
- executar um big bang ou publicar duas mudanças críticas juntas.

## 2. Gates transversais

| Gate | Critério |
|---|---|
| G-UX0 | matriz de paridade revista e sem função sem destino |
| G-UX1 | protótipo/wireframe revisto por operador e GO humano |
| G-UX2 | rotas antigas/deep links mantidos ou redireccionados |
| G-UX3 | PHP lint + JS syntax + testes funcionais afectados PASS |
| G-UX4 | CSRF, privilégios, POST/PRG e validação de servidor PASS |
| G-UX5 | PT/EN/ES sem fallback/literal novo não catalogado |
| G-UX6 | teclado, foco, labels, contraste e 320/768/desktop PASS |
| G-UX7 | paridade por linha tocada, config antiga e rollback PASS |
| G-UX8 | appliance pfSense CE/lab: monitor primeiro; nenhum PF inesperado |
| G-UX9 | diff revisto, staging explícito, versão nova e release checklist |
| G-UX10 | conformidade ADR-0037: barras escuras de secção, linhas planas, label esquerda, controlo/help direita; sem cards/sticky/design paralelo |
| G-UX11 | integração nativa: `head.inc`/`foot.inc` e primitivos pfSense; zero `<style>` inline e zero duplicação de tema na página tocada, salvo excepção formal |
| G-UX12 | congelamento funcional: mesmos inputs/defaults/POSTs/handlers/efeitos, comprovados por matriz e comparação antes/depois |
| G-UX13 | orçamento frontend: nenhum framework novo; recursos próprios só na página que usa; bytes/requests próprios medidos e não aumentados sem GO |

### 2.1 Contrato técnico de renderização nativa

O pfSense é o design system e o runtime visual. O package não desenha uma cópia
dele.

1. Toda página entra pelo `head.inc` e termina pelo `foot.inc`.
2. Formulários usam prioritariamente `Form`, `Form_Section`, `Form_Input`,
   `Form_Select`, `Form_Checkbox`, `Form_StaticText` e equivalentes disponíveis
   na versão alvo. HTML manual só quando a API nativa não expressar o caso.
3. Listas usam tabelas/classes nativas; mensagens usam `print_info_box()`,
   `print_input_errors()` e alerts do host; navegação usa tabs do host.
4. Cor, tipografia, espaçamento, borda, foco, responsividade e estado de botão
   vêm de `/css/pfSense.css` e dos assets carregados pelo `head.inc`.
5. O alvo por página é **zero CSS visual próprio**. CSS permitido deve resolver
   apenas uma lacuna funcional comprovada, ficar escopado, sem cores/medidas de
   tema duplicadas e com justificativa na revisão.
6. São proibidos `<style>` inline, atributo `style=`, cards, chips, sombras,
   grids decorativos, tabs redesenhadas e helpers que recriem `Form_*`.
7. JavaScript nativo já carregado é preferido. Biblioteca adicional só pode ser
   carregada na rota que a utiliza e por necessidade funcional existente; não
   pode ser adicionada para decoração ou transição visual.
8. Helpers Layer7 podem normalizar dados para a view ou reduzir repetição
   semântica, mas não formar um design system próprio.

### 2.2 Método obrigatório por página

Cada bloco técnico futuro deve seguir esta sequência:

1. congelar a rota, privilege, GET/POST, campos, defaults, validações, mensagens,
   ficheiros alterados e efeitos externos na matriz;
2. capturar baseline visual/DOM e recursos carregados no appliance;
3. reorganizar somente a camada de renderização;
4. comparar requests e estado persistido antes/depois com os mesmos inputs;
5. provar que nenhuma chamada, comando, sinal, PF ou serviço mudou;
6. validar sem JavaScript, teclado, PT/EN/ES, 320/768/desktop e tema pfSense;
7. validar bytes/requests e ausência de estilo visual duplicado;
8. submeter o bloco à revisão humana antes de avançar para outra página.

### 2.3 Regras de organização operacional

A melhoria deve vir da ordem e do fluxo, não de decoração:

- cada página declara uma finalidade principal e uma acção primária;
- estado e leitura aparecem antes de configuração; configuração aparece antes
  de diagnóstico/reparação; perigo fica por último e isolado;
- tarefas frequentes ficam abertas; opções avançadas ficam recolhidas, mas
  continuam localizáveis por título e help text;
- listas, criação, edição e biblioteca não competem na mesma área visível;
- `Editar` abre imediatamente o formulário no topo de uma vista dedicada ou
  move foco de forma inequívoca; nunca recarrega no topo deixando-o abaixo;
- filtros, paginação, categoria aberta, retorno e posição são preservados na
  camada de apresentação sem alterar o conjunto de dados;
- o operador vê separadamente: alteração local, validação, gravação, reload do
  daemon, aplicação PF e restart; a GUI descreve os efeitos existentes, não
  cria novos efeitos;
- nomes começam pela linguagem operacional; paths, tabelas PF, sinais e detalhe
  cru ficam disponíveis sob detalhe técnico;
- botões usam verbo + objecto + consequência quando houver ambiguidade;
- estados vazio, carregando, erro, aviso, pendente e sucesso ocupam o ponto
  normal da página pfSense e sempre indicam a próxima acção segura;
- nenhuma função é removida, escondida sem destino ou condicionada apenas por
  JavaScript; a matriz decide onde cada função reaparece.

## 3. Ondas

### GUI0 — inventário, auditoria e desenho

- **Objectivo:** conhecer GUI e handlers reais; definir IA, paridade,
  wireframes, componentes, riscos e testes.
- **Ficheiros:** somente documentação canónica.
- **Funções preservadas:** todas; zero alteração runtime.
- **Impacto:** cria a porta de decisão.
- **Risco:** omissão documental; mitigado por fonte + GUI real + matriz.
- **Teste:** links, coerência CORTEX/roadmap/backlog/checklist/classificação/mapa,
  revisão de diff e confirmação de nenhum código.
- **Gate:** G-UX0 + G-UX1; GUI1 só após GO.
- **Rollback:** reverter o commit documental.
- **Versão/build:** não.

### GUI1 — fundação nativa pfSense e componentes mínimos

- **Objectivo:** remover a camada visual paralela numa página piloto e provar
  que o pfSense sozinho entrega o padrão, sem mudar comportamento.
- **Ficheiros previstos:** secção de apresentação da página piloto
  (`layer7_status.php`). `layer7.inc` **não** foi tocado neste piloto (include
  partilhado; retirar `layer7_render_styles()` só nesta página). CSS/JS novo
  não é resultado esperado.
- **Preserva:** tabs, handlers, campos, POSTs, mensagens e defaults.
- **Impacto:** consistência de `Form_Section`/`Form_*`, linhas planas, `table`,
  `nav-tabs`, `alert`, `btn` e `print_info_box()`; retirada gradual de
  `layer7_render_styles()`, estilos inline, cards, sombras, chips e sticky bars.
- **Risco:** CSS global/pfSense Plus/CE e CSP.
- **Testes:** gate estático `tests/functional/test_status_native_view.php`
  (texto da view; sem guiconfig/daemon/PF); PHP, DOM/assets antes/depois,
  zero alteração de request/efeito, snapshots visuais manuais, sem-JS,
  teclado, PT/EN/ES e orçamento frontend. Estado do piloto: **implementado,
  pendente de gates/commit**.
- **Gate:** uma página piloto read-only renderizada no mesmo padrão de formulário
  da referência pfSense, com G-UX10…G-UX13 PASS e sem regressão.
- **Rollback:** include/style antigo por página; pacote anterior.
- **Versão/build:** sim; novo `PORTVERSION/PKGVERSION`, build/pkg/release.

### GUI2 — organização nativa e subtabs controladas

- **Objectivo:** melhorar tabs/subtabs dentro da organização actual do package;
  usar `Visão geral/Proteção/Clientes/Atividade/Sistema` apenas como taxonomia
  de conteúdo e linguagem.
- **Regra:** organizar primeiro dentro de cada rota. Mover, fundir ou esconder
  tabs primárias não faz parte desta onda sem GO humano específico.
- **Ficheiros:** nav helpers/XML, páginas, i18n, testes/docs.
- **Preserva:** privilégios, URLs, deep links, Identity/MITM visíveis conforme
  contrato e redirect Remote Access.
- **Risco:** tabs customizadas divergirem do pfSense ou operadores/bookmarks/ACL
  perderem destino.
- **Testes:** cada URL/privilege, active state, 320 px, teclado, back/forward.
- **Gate:** G-UX2/G-UX4/G-UX5/G-UX6.
- **Rollback:** helper de nav anterior; pacote anterior.
- **Versão/build:** sim.

### GUI3 — Policies e Biblioteca de perfis

- **Objectivo:** separar aplicadas/biblioteca/criar/editar; edição dedicada.
- **Estado do bloco:** **V4-A implementado, revisão local gerente PASS, pendente
  commit.** Mesma rota `layer7_policies.php`. Modos: `list` (aplicadas;
  remoção visível `Form_Select`; `pon[]` acessível), `edit` (`?edit=N` /
  retry), `view` (`?view=N`), `new` (`?new=1` / erro `add_policy`). POST de
  edição mantém `action="layer7_policies.php#l7-edit"`. Biblioteca/modais =
  **V4-B1 biblioteca implementado, gates locais PASS, pendente commit**;
  **V4-B2a opções de perfil implementado, cobertura B2a completa nos gates
  locais, pendente revisão final/commit/visual/CE/CSRF/appliance** (modal
  Bootstrap; Form nativo; fallback GET; limite24/catálogo vazio/oculto/POST
  integral/escaping/onclick real; harness `harness-policies-options/` **158 PASS** + `test_policies_profile_options.js` **226
  PASS** (padrão; prova opcional pin Bootstrap +11 via env do briefing =
  **237** cumulativos); **V4-B2b editor/criação revisão independente gerente PASS** (harness
  **135** + jsdom **142** PASS; CSS **21 PASS** (pin env); regressão cumulativa
  Options **158** / Library **194** / View **685** / Library jsdom **93** PASS;
  pendente commit/visual/CE/CSRF/appliance; **V4-B2c revisão independente gerente PASS**,
  pendente commit/visual/CE/CSRF/appliance; **GUI2a subnav Políticas gate funcional PASS**
  (`test_policies_subnav_native.php` ALL PASS; auditoria byte-identica opcional via `LAYER7_GUI2A_BASELINE`), pendente revisão gerente/commit/visual/CE/CSRF/appliance; V4-B2 modais/CSS
  global pendente). Sem `PORTVERSION`, build ou release neste bloco.
- **Limite funcional:** os handlers não mudam; payloads, defaults, perfis,
  seletores, ordem de avaliação e resync permanecem semanticamente equivalentes;
  a separação é de vista e contexto, não de motor.
- **Ficheiros:** `layer7_policies.php`, i18n EN/ES,
  `tests/functional/test_policies_native_view.php`,
  `tests/functional/test_policies_handlers_baseline.php`,
  `tests/functional/harness-policies-view/`, `tests/functional/test_policies_filters.js`,
  `tests/functional/harness-policies-library/`, `tests/functional/test_policies_library.js`,
  `tests/functional/harness-policies-options/`, `tests/functional/test_policies_profile_options.js`.
- **Preserva:** todas as linhas Policies/Profiles da matriz, inclusive
  hidden/override, batch, opções, selectors, schedule, AD e VIP.
- **Risco:** maior superfície de regressão; PF resync indevido; perda de
  contexto; 105 perfis (V4-B).
- **Testes:** gates locais PASS (harness 685+, handlers baseline pinado,
  jsdom `onkeyup`/`onclick` edit/new); V4-B1 biblioteca + V4-B2a opções
  (harness **158** + jsdom **226** PASS padrão; pin opcional +11 = **237**
  cumulativos); **não** homologado
  visual/CE/CSRF/appliance;
  V4-B2 modais/CSS global ainda pendente.
- **Gate:** G-UX0…G-UX9 + appliance monitor; comparação JSON antes/depois.
- **Rollback:** rotas antigas permanecem; reverter o diff de apresentação;
  pacote anterior/config export.
- **Versão/build:** sim, isolada de Devices/Settings — **não** neste bloco.

### GUI4 — Clientes e Atividade

- **Objectivo:** Devices paginado/filtrável, Groups; Events/Reports com contexto.
- **Limite funcional:** paginação/filtro não podem alterar o conjunto persistido
  nem a semântica de bulk; eventos/exports mantêm a mesma fonte e conteúdo.
- **Ficheiros:** devices/groups/events/reports/export, shared list, JS/i18n/docs.
- **Preserva:** alias/bulk group, resync, live/pause/clear visual, raw detail,
  filtros/paginação/export/clear DB.
- **Risco:** POST parcial de aliases; paginação mudar conjunto; polling/logs.
- **Testes:** 0/1/674+ devices, bulk através de páginas, URL filters, log rotate,
  DB indisponível, export parity e “clear view ≠ clear disk”.
- **Gate:** JSON/aliases e exports equivalentes; sem acção sobre dados no
  primeiro smoke read-only.
- **Rollback:** modo lista antigo por rota; pacote anterior.
- **Versão/build:** sim; dividir Clientes e Atividade se diff crescer.
- **Devices (bloco V1, GO visual `2026-09-04`):** **implementado, pendente
  de gates/commit**. Consulta `list` paginada (50); modo `batch` com o
  conjunto completo do filtro (sem corte de 50). Editor individual
  `?edit=MAC` com `Form_Input`/`for`/`id`. POST/voltar preservam
  `q`/`online`/`page`/`mode`. Erro reapresenta valores digitados (view).
  Duas acções independentes no lote (aliases vs grupo); o copy do
  operador não expõe nomes de campo nem `max_input_vars` (conta e
  limite ficam em docs/testes: forms `l7-form-aliases` /
  `l7-form-assign`; 674 combinado excederia 1000). V1: revisão local
  do harness de render (não fixtures manuais). `Form_Button`
  `save_aliases` (editor) usa a API oficial: `value` = legenda
  traduzida (dispatch truthy; **não** `value="1"`, que apagava o
  nome da acção). Ícone `fa fa-save`. Lote manual já enviava `1`
  com legenda própria — intocado. Sem `PORTVERSION`. Não
  homologado / sem PASS visual.
- **Groups (bloco V2, GO visual integral local `2026-09-04`):**
  **implementado, pendente de gates/commit**. Consulta `list` com
  tabela nativa; `?edit=N` e `?new=1` exclusivos; resync/remoção no
  fim da consulta; âncoras `#l7-groups` / `#l7-edit-group` /
  `#l7-add-group`. Handlers intactos. `layer7_render_footer()`
  substituído localmente pelo mesmo crédito em classes nativas
  (o helper emite `style=` inline; excepção de render, sem editar
  `layer7.inc`). `setHelp` de IPs resolvidos escapa com
  `htmlspecialchars`. Exceptions **ainda não**. Sem `PORTVERSION`.
  `Form_Button` de submit (`save_group_edit` / `add_group` /
  `resync_devices` / `delete_group`) usam `value` = legenda
  traduzida (equivalente booleano nos handlers) e ícones
  `fa fa-save` / `fa fa-plus` / `fa fa-refresh` / `fa fa-trash`.
  Após erro de remoção, a view restaura `delete_group_index` se for
  opção válida (GET continua default `0`); handler intacto.
  Não homologado / sem PASS visual. Risco da view que monta POST:
  médio (não «baixo absoluto»).
- **Catálogo nDPI (bloco V3, GO visual integral local `2026-09-04`):**
  **implementado, pendente de gates/commit**. Consulta de referência
  em `layer7_categories.php`; leitura `layer7_ndpi_list` / `ksort` /
  contagens / privilégios intactos. Busca com `label`/`for`/`id`;
  grupos `details`/`summary` e tabelas nativas (sem chips). Init via
  `events[]` ou `DOMContentLoaded` (sem jQuery antes de `foot.inc`).
  `layer7_render_footer()` substituído localmente pelo crédito em
  classes nativas (emite `style=`). Harness PHP 0/1/472+; jsdom
  26.1.0 isolado para busca/click (Enter/Space no browser
  **pendentes**; sem prova visual). Sem `PORTVERSION`. Não homologado.

### GUI5 — Settings e Diagnostics

- **V8 Diagnósticos (bloco visual, `2026-09-05`):** **revisão gerencial local PASS**; gates `test_diagnostics_*.php|js` (**195** cumulativos); pendente commit/visual/CE/CSRF/appliance; sem `PORTVERSION`.

- **V9 Teste de políticas (bloco visual, `2026-09-05`):** **revisão gerencial local PASS**; gates `test_test_*.php|js` (**126** cumulativos; fixture nDPI V3 **472**/**20**); pendente commit/visual/CE/CSRF/appliance; sem `PORTVERSION`.

- **V10 Relatórios (bloco visual, `2026-09-05`):** **revisão gerencial local PASS**; gates `test_reports_*.php|js` (**72** cumulativos; harness sem SQLite/rede); pendente commit/visual/CE/CSRF/appliance; sem `PORTVERSION`.

- **V11 Remoção (bloco visual, `2026-09-05`):** **revisão gerencial local PASS**; gates `test_removal_*.php|js` (**86** cumulativos; harness sem remoção real); pendente commit/visual/CE/CSRF/appliance; sem `PORTVERSION`.

- **V12 Identity (bloco visual, `2026-09-05`):** **implementado, gates locais Composer 2.5 PASS**; view nativa em `layer7_identity.php` (painéis Bootstrap, form HTML preservado, labels `l7i-*`; prefixo handlers congelado até `$pgtitle` **6682** bytes; fila **20.37 fechada** — só visual); gates `test_identity_freeze.php` **14** + `test_identity_native_view.php` **32** + `test_identity_render.php` **16** + `test_identity_payload.js` **16** (**78** cumulativos; harness sem LDAP/rede); pendente revisão independente/commit/visual/CE/CSRF/appliance; sem `PORTVERSION`.

- **V13 MITM (bloco visual, `2026-09-05`):** **revisão gerencial local PASS**; gates `test_mitm_*.php|js` (**106** cumulativos); pendente commit/visual/CE/CSRF/appliance; sem `PORTVERSION`.

- **V15 Settings (bloco visual, `2026-09-05`):** **FECHADO** revisão gerencial; gates **131** cumulativos; confirmações `revoke`/`import` com `JSON_HEX_*`; **sem** `confirm` em `do_update`; candidato **`1.9.81`** preparado (nao publicado); sem `PORTVERSION` commitado.

- **V15 Settings (bloco visual, `2026-09-05`):** **implementado, gates locais Composer 2.5 PASS**; view nativa em `layer7_settings.php` (painéis Bootstrap, forms POST/anchors preservados; `save_scope` general/reports; reports checkboxes sem `value`; retention `style.display`; prefixo handlers congelado até `$pgtitle` **24306** bytes; backend/licença/update/import **congelados**); gates `test_settings_freeze.php` **18** + `test_settings_native_view.php` **48** + `test_settings_render.php` **16** + `test_settings_payload.js` **23** + `test_settings_js.js` **16** (**121** cumulativos; harness sem rede/licença/update real); pendente revisão gerencial/commit/visual/CE/CSRF/appliance; sem `PORTVERSION`.

- **Objectivo:** separar secções, impacto antes de salvar e resultado por etapa.
- **Limite funcional:** `save_scope`, campos, valores, validações e efeitos são
  congelados. A GUI apenas torna o impacto existente compreensível.
- **Ficheiros:** settings/ajax/js, diagnostics, shared components/i18n/tests/docs.
- **Preserva:** todos os 83 campos/acções, defaults, clamps, anti-bypass,
  blockpage/CP, logs/reports, signals, anti-DoH, repair e support.
- **Risco:** crítico; save parcial, PF/Unbound/cron e feedback falso.
- **Testes:** cada `save_scope`, config antiga, diff JSON, CSRF, SIGHUP/PF
  condicional, CP conflict, daemon parado, Unbound/PF failure.
- **Gate:** matrix campo-a-campo + monitor passivo + revisão humana de impacto.
- **Rollback:** handlers conservados; presentation switch/pacote anterior;
  config export.
- **Versão/build:** sim; preferir Settings e Diagnostics em versões separadas.

### GUI6 — licença, update, backup e remoção

- **Objectivo:** isolar operações de sistema e zona de perigo.
- **Limite funcional:** não reimplementar nenhuma operação; apenas apresentar os
  handlers existentes com separação, explicação e confirmação nativas.
- **Ficheiros:** settings/update JS/AJAX, removal, componentes/i18n/tests/docs.
- **Preserva:** activate/revoke, entitlement transitions, export sem secrets,
  import/disarm, GitHub allowlist, update stop/fetch/pkg/start, keep flags e
  typed uninstall.
- **Risco:** máximo; perda de acesso/config, package parcialmente instalado.
- **Testes:** lic válida/inválida/timeout; backup roundtrip/legacy/no secrets;
  update states; removal matrix em lab descartável; CSRF/ACL.
- **Gate:** janela/appliance de lab + recovery; nenhuma acção destrutiva no
  cliente auditado.
- **Rollback:** backup/config, pacote anterior interno, recovery Package Manager;
  sem republicar mesma versão.
- **Versão/build:** sim; dividir licença/update/backup de removal.

### GUI7 — acessibilidade, i18n, regressão e release

- **Objectivo:** fechar consistência transversal e campanha final.
- **Ficheiros:** GUI/testes/docs/release conforme findings; sem feature nova.
- **Preserva:** matriz completa.
- **Risco:** polish mascarar regressão; conter escopo.
- **Testes:** WCAG prática (teclado/foco/labels/contraste), 320/768/desktop,
  PT/EN/ES, instalação nova/upgrade/config antiga, CE, monitor/enforce,
  licença válida/inválida, daemon parado, CP, blockpage e rollback.
- **Gate:** campanha de paridade completa + revisão humana + release checklist.
- **Rollback:** package anterior de arquivo interno + config export; latest-only
  segue ADR-0003/BG-164.
- **Versão/build:** sim se tocar produto; cada mudança publicada usa nova versão.

## 4. Plano de testes

| Camada | Testes |
|---|---|
| estática | `php -l` em todos PHP; shellcheck/lint aplicável; JS parse; links |
| unit/functional | helpers UI, validators, i18n coverage, operator copy, update, backup, error report, blockpage, VIP, profiles |
| HTTP | GET read-only, POST/CSRF, PRG, status codes, headers/downloads, ACL deny |
| persistência | JSON antes/depois, defaults/migrations, secrets separados, config antiga |
| efeitos | SIGHUP vs restart vs PF/filter vs Unbound/cron, incluindo falha parcial |
| UX | 13 fluxos prioritários; ≤3 interações para Pornografia; contexto/dirty; comparação lado a lado com formulário pfSense e zero cards estruturais |
| assets | `head.inc`/`foot.inc`; CSS/JS/fonts carregados; `<style>`/`style=`; bytes e requests próprios por rota |
| acessibilidade | teclado completo, foco após erro/save, labels, aria-live, zoom/contraste |
| responsivo | 320, 768 e desktop; tabelas/listas, modais curtos, sem clipping |
| i18n | PT/EN/ES mesma função/copy/default; strings dinâmicas e públicas |
| compatibilidade | pfSense CE alvo; Plus apenas evidência adicional; CSP e sem-JS |
| cenários | fresh install, upgrade, config antiga, daemon down, license states,
  monitor/enforce, CP, blockpage, IPv4/IPv6 conforme contrato |
| rollback | downgrade interno, restore config, rotas antigas e ausência de PF stale |

## 5. Critérios de sucesso e stop conditions

Sucesso exige todos os critérios do pedido, com destaque para:

- 100% das linhas da matriz com destino/teste;
- 100% dos handlers, defaults, validações e efeitos preservados;
- nenhum formulário aberto fora da área visível sem foco/indicação;
- estado salvo/daemon/PF/restart distinto;
- filtros/contexto preservados;
- funções avançadas encontráveis e recolhidas;
- PT/EN/ES e teclado equivalentes;
- zero mudança de segurança causada apenas pela apresentação.
- zero framework novo, zero design system Layer7 e zero CSS visual próprio nas
  páginas migradas, salvo excepção formal aprovada;
- cada página migrada carrega o padrão do pfSense, não uma reprodução dele;
- nenhuma onda mistura frontend com refactor ou desenvolvimento funcional.

Parar e pedir validação se surgir incompatibilidade CE, necessidade de novo
framework/build/CSP, alteração de privilege/CSRF, mudança de default/contrato,
efeito PF não previsto, migração de dados, acção sobre segredo/CA/licença ou
qualquer pressão para reabrir Identity/MITM.

## 6. GO requerido

GUI0 não concede autorização para GUI1. O GO humano deve nomear:

1. IA aprovada ou ajustes; **ADR-0037 já fixa:** cinco áreas = taxonomia e
   shell/organização = pfSense.
2. padrão de edição de políticas;
3. onda inicial autorizada;
4. appliance/lab e janela de validação;
5. qualquer proposta futura de mover Identity/MITM nas tabs; por defeito,
   ambas permanecem na posição e rota actuais.

Até esse GO, é proibido alterar código, `PORTVERSION`, build ou release.

**GO GUI1 piloto Status (`2026-08-31`):** o GO inicial autorizou apenas
a view de `layer7_status.php` (apresentação). Sem `layer7.inc`, sem
`PORTVERSION`, sem commit/push/build/appliance nesse bloco.

**GO preparação governada da release `1.9.80` (após aceitação do
piloto pelo manager):** autorizado bump de `PORTVERSION`/`PKGVERSION` e
documentação. **PUBLICADO** `v1.9.80` (`releases/latest`; SHA256
`f7186ee3c58d6ad948b322e45098adaf06f03b0400eab29ed1dd112c2c908782`;
tag `7bd1fd0`). Residual: appliance/visual **pendentes**.

**GO visual integral local (`2026-09-04`):** autoriza o redesenho
visual das views restantes (não operacional; não é GO de
appliance/build/`PORTVERSION`). GUI1 publicado; GUI3 Policies V4-A
**implementados, revisão local gerente PASS, pendente commit** (V4-B1
biblioteca + V4-B2a opções cobertura B2a completa gates locais (harness **158** +
jsdom **226** PASS padrão; pin opcional +11 = **237** cumulativos), pendente revisão final/commit/visual/CE/CSRF/appliance; **V4-B2b revisão independente gerente PASS** (harness **135** + jsdom **142** + CSS **21** PASS), pendente commit/visual/CE/CSRF/appliance; **V4-B2c revisão independente gerente PASS** (`test_policies_native_view.php` **88 PASS**), pendente commit/visual/CE/CSRF/appliance; **GUI2a subnav Políticas gate funcional PASS** (`test_policies_subnav_native.php` ALL PASS; auditoria byte-identica opcional via `LAYER7_GUI2A_BASELINE`), pendente revisão gerente/commit/visual/CE/CSRF/appliance; **V5 Allowlist revisão independente gerente PASS**, pendente commit/visual/CE/CSRF/appliance; **V6a Exceptions revisão independente final gerente PASS local** (`2026-09-05`; pendente commit/visual/CE/CSRF/appliance); **V6b1 Lista VIP revisão independente final gerente PASS local** (`2026-09-05`; **213** `PASS:` VIP + contrato **10**; pendente commit/visual/CE/CSRF/appliance); **V6b2a DHCP exclusivo revisão independente final gerente PASS local** (`2026-09-05`; pendente commit/visual/CE/CSRF/appliance); **V6b2b lote/import/export implementado, revisão local Composer 2.5 PASS** (`2026-09-05`; pendente revisão independente final/commit/visual/CE/CSRF/appliance); **V6c fechamento visual revisão gerencial local PASS** (`2026-09-05`; native_view **84** + freeze **32**; pendente revisão independente final/commit/visual/CE/CSRF/appliance); **V7 Eventos implementado, gates locais Composer 2.5 PASS** (`2026-09-05`; native_view **60** + freeze **10** + render_row **13** + payload **12** + jsdom **18**; pendente revisão independente final/commit/visual/CE/CSRF/appliance); **não** FECHADO)). GUI4 Devices V1 **implementado, pendente de
gates/commit** (revisão local harness). GUI4 Groups V2 **implementado, pendente
de gates/commit**. Catálogo nDPI V3 **implementado, pendente de
gates/commit**. GUI2 e GUI5–GUI7 ainda sem
implementação. O plano **não** está FEITO no conjunto.
