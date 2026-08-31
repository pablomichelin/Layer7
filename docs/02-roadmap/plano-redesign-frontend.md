# Plano governado — redesenho do frontend Layer7

**Backlog:** BG-174.
**Estado:** GUI0 documental concluído no commit `3563757` após gates PASS;
GUI1–GUI7 bloqueados até GO humano.
**Baseline:** `1.9.79`, `main@4354aec`.
**Nota de fase:** `GUI0…GUI7` são ondas internas deste plano, não substituem as
fases canónicas F0…F7. A execução técnica cruza F4/F5/F7 e obedece aos seus
gates.

## 1. Escopo e não-escopo

Objectivo: redefinir arquitectura da informação, fluxos, componentes,
linguagem, feedback, acessibilidade e responsividade preservando todas as
funções inventariadas.

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

- **Objectivo:** reutilizar os primitivos nativos pfSense e extrair apenas
  helpers funcionais mínimos, sem mudar rotas, organização ou comportamento.
- **Ficheiros previstos:** `layer7.inc` ou includes novos no mesmo package,
  CSS/JS externos, páginas piloto, testes e docs.
- **Preserva:** tabs, handlers, campos, POSTs, mensagens e defaults.
- **Impacto:** consistência de `Form_Section`/`Form_*`, linhas planas, `table`,
  `nav-tabs`, `alert`, `btn` e `print_info_box()`; remoção gradual de cards,
  sombras, chips e barras sticky.
- **Risco:** CSS global/pfSense Plus/CE e CSP.
- **Testes:** PHP/JS, snapshots visuais manuais, sem-JS, teclado, PT/EN/ES.
- **Gate:** uma página piloto read-only renderizada no mesmo padrão de formulário
  da referência pfSense, sem cards e sem regressão.
- **Rollback:** include/style antigo por página; pacote anterior.
- **Versão/build:** sim; novo `PORTVERSION/PKGVERSION`, build/pkg/release.

### GUI2 — organização nativa e subtabs controladas

- **Objectivo:** melhorar tabs/subtabs dentro da organização actual do package;
  usar `Visão geral/Proteção/Clientes/Atividade/Sistema` apenas como taxonomia
  de conteúdo e linguagem.
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
- nenhum formulário aberto fora da área visível sem foco/indicação;
- estado salvo/daemon/PF/restart distinto;
- filtros/contexto preservados;
- funções avançadas encontráveis e recolhidas;
- PT/EN/ES e teclado equivalentes;
- zero mudança de segurança causada apenas pela apresentação.

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
