# Plano governado — redesenho do frontend Layer7

**Backlog:** BG-174.
**Estado:** GUI0 documental `3563757`, ADR-0037 `f44a14b` e emenda visual
`c429be3` concluídos após gates PASS. Emenda frontend-only **FEITA no git**
(`3b18f82`) após gates PASS. GUI1 piloto Status **candidato `1.9.80` —
build/sign/verify PASS; pendente de commit/tag/publicação/appliance**
(`2026-08-31`; `layer7_status.php` + `PORTVERSION` 1.9.80; SHA256
`f7186ee3c58d6ad948b322e45098adaf06f03b0400eab29ed1dd112c2c908782`;
source/build `b84634c`). **Não publicado.** **`latest` continua `1.9.79`**.
GUI2–GUI7 bloqueados até GO humano.
**Baseline publicada:** `1.9.79`. **Candidato:** `1.9.80`.
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
- **Limite funcional:** os handlers não mudam; payloads, defaults, perfis,
  seletores, ordem de avaliação e resync permanecem semanticamente equivalentes;
  a separação é de vista e contexto, não de motor.
- **Ficheiros:** `layer7_policies.php` e possivelmente novas rotas de modo,
  includes/JS/i18n, profiles UI tests/docs.
- **Preserva:** todas as linhas Policies/Profiles da matriz, inclusive
  hidden/override, batch, opções, selectors, schedule, AD e VIP.
- **Risco:** maior superfície de regressão; PF resync indevido; perda de
  contexto; 105 perfis.
- **Testes:** CRUD completo, PRG, dirty state, filtros/scroll, perfil
  Pornografia ≤3 interações, monitor/enforce, licença/Identity, no-JS.
- **Gate:** G-UX0…G-UX9 + appliance monitor; comparação JSON antes/depois.
- **Rollback:** rotas antigas permanecem; feature switch de apresentação se
  formalmente aprovado; pacote anterior/config export.
- **Versão/build:** sim, isolada de Devices/Settings.

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

### GUI5 — Settings e Diagnostics

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
documentação de candidato. O segundo bloco (artefacto existente) actualiza
URLs operacionais para `v1.9.80` com SHA canónico
`f7186ee3c58d6ad948b322e45098adaf06f03b0400eab29ed1dd112c2c908782`.
Estado canónico: **build/sign/verify PASS; pendente de
commit/tag/publicação/appliance**. **Não publicado.** `latest` continua
`1.9.79`.
