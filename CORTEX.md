# CORTEX.md

## Finalidade

Este ficheiro e o **SSOT operacional e documental** do projecto Layer7.
Qualquer agente, maintainer ou novo chat deve conseguir retomar o contexto
do projecto a partir daqui sem depender de memoria implícita.

Se houver conflito entre documentos, a ordem de prevalencia e:

1. `CORTEX.md`
2. `docs/README.md`
3. `docs/02-roadmap/roadmap.md`
4. `docs/02-roadmap/backlog.md`
5. `docs/02-roadmap/checklist-mestre.md`
6. `docs/00-overview/document-classification.md`
7. `docs/00-overview/document-equivalence-map.md`
8. Documentacao canónica por area
9. Documentos historicos preservados na raiz e em subpastas

---

## Visao executiva do projecto

**Produto:** Layer7 para pfSense CE
**Empresa:** Systemup Solucao em Tecnologia
**Estado funcional conhecido:** V1 Comercial concluida e publicada;
**Fase 1 de estabilizacao** (`1.8.11_18`) e **Caminho A completo (A0-A5)**
publicado como **`1.8.11_23`**. A Fase 1 corrigiu a causa-raiz de "bloqueia
bancos em modo monitor" e adicionou a allowlist de destinos; o Caminho A
aproxima a UX e a eficacia de um UDM Pro (inventario e politicas por
dispositivo, SNI/Host via nDPI opt-in, UX de perfis com toggle e contadores)
**sem MITM** e mantendo o monitor passivo. **Build + validacao reais** em
`2026-05-30`: build no builder FreeBSD (`192.168.100.12`), validado no
appliance (`192.168.100.254`) com `smoke-monitor-mode.sh` e `smoke-caminho-a.sh`
(ambos exit 0).
**Ultima versao do pacote publicada em release (canal publico/updater):**
`1.9.0` (GitHub Releases `pablomichelin/Layer7`, tag `v1.9.0`,
`SHA256=cde469a105db0b9f07dee1bf65838494ce209a1e86912d2169b0f124d631569f`;
comandos e links em `docs/10-license-server/MANUAL-INSTALL.md`).
**Nota:** `1.8.11_55` foi publicada com artefacto incompleto (BG-070 a meio) —
**nao instalar**; usar `_56` ou superior.
**Referencia de producao enforce:** **`1.9.0`** (fecho plano mestre `2026-08-05`;
equivalente funcional a `1.8.11_69`; rollback imediato `_69`; historico `_24`).
CE fisico pendente — ADR-0022 aceite. Gates G2–G7 **PASS**.

### Release `1.8.11_24` — Caminho B E0–E3 + pos-revisao (publicada `2026-06-16`)

Artefacto `pfSense-pkg-layer7-1.8.11_24.pkg`
(`SHA256=1d5573f0a0c7803a87d8cb536ad9eee43e85daa9bf98bf7edc84ef554e2c7818`),
build no builder FreeBSD. Consolida enforcement escopado (E0–E3) e correcoes
pos-revisao 2026-06-15 (detalhes em `CHANGELOG [1.8.11_24]`; BG-045..BG-048
`Concluido`). `legacy_global` permanece default; `scoped_hybrid` experimental
OFF por defeito. Gate two-client (validacao-lab sec. 12) **PENDENTE** — nao
avancar E4 sem PASS no appliance.

### Candidato `1.8.11_25` — estabilização pré-gate (não publicado)

Diagnóstico read-only no appliance em `2026-07-29` confirmou `_24` instalado
e intencionalmente passivo (`enabled=false`, `mode=monitor`), licença válida,
zero regras PF Layer7 de block e zero capturas. Foram reproduzidas quatro
falhas no código publicado: PID sem newline quebrava `status`/`reload`; `lan`
era gravado onde libpcap exige `vmx0`; políticas scoped podiam gerar tabelas
sem regra PF; e match app+host escolhia sempre `pdst`.

O branch prepara `1.8.11_25` como **candidato interno** com correcções de
ciclo de vida, migração para interface real e `psrc` executável. A escolha
app=`psrc`/host=`pdst` deste candidato foi refinada no `_27`, reservando
`psrc` à quarentena explícita. Gates locais, PHP e build FreeBSD: PASS; artefacto
local `pfSense-pkg-layer7-1.8.11_25.pkg`,
`SHA256=c4e9c197f79ad00d7ddb68f8ececcd391455e86011e558596102877c325d388d`.
Instalação e gate two-client no appliance: **PENDENTES**. A release
pública continua `_24`; não activar nem publicar `_25` antes desses gates.
O appliance observado é pfSense Plus `26.03.1` / FreeBSD `16.0-CURRENT`, não
pfSense CE; essa compatibilidade real deve ser validada explicitamente.

### Candidato `1.8.11_26` — contenção L1 de logs (não publicado)

A inspeção read-only de `2026-07-29` encontrou
`/var/log/layer7d.log` sem rotação, com 4.663 linhas; 2.490 eram idle
repetitivo e 1.656 rechecks de licença sem transição. Com detalhe ligado,
cada consulta DNS entrava no mesmo ficheiro em `info`. A base SQLite tinha
retenção, mas o texto bruto não tinha limite.

BG-054/ADR-0015 introduzem separação entre operação e tráfego, rotação interna
limitada, perfil de detalhe opt-in, auditoria de bloqueios sempre activa,
redução de ruído e limite do SQLite. O colector atravessa rotações por inode e
a GUI diferencia limpar vista, histórico e expiração dos logs. Este é um
bloco F4.1 de contenção; filtros/exclusão selectiva L2/L3 permanecem F7.
Suite local, PHP/SQLite isolado e build FreeBSD: PASS. Artefacto local
`pfSense-pkg-layer7-1.8.11_26.pkg`,
`SHA256=c536cf879721d3bfad0097df9cf9f5ee45f217738c80ceaed9568acaf88b2f69`.
Nenhuma alteração foi instalada no appliance; release pública permanece `_24`.

### Candidato `1.8.11_27` — estabilização funcional pré-produção (não publicado)

A revisão end-to-end de `2026-07-29` encontrou causas directas para os sintomas
“não bloqueia” e “bloqueia tudo”: o hash separava ida/volta do mesmo fluxo nDPI;
app/categoria normal usava quarentena `psrc`; e o PF mantinha estados já
estabelecidos após a entrada na tabela.

BG-055 corrige esses pontos e, no mesmo bloco de coerência do enforcement,
restaura precedência de `allow` no callback de blacklist, TTL no caminho SNI, self-heal
da tabela scoped alvo, QNAME original em CNAME e sweep de fluxos classificados.
A emenda da ADR-0014 reserva `psrc` a `quarantine_origin=true`; app normal usa
`pdst` por cliente/destino. Suite completa C/PHP/shell, build nDPI, validação
do pacote e smoke `layer7d -t` passaram no builder FreeBSD 15. Artefacto
`pfSense-pkg-layer7-1.8.11_27.pkg`,
`SHA256=8eae978d8d3120f050be21d2fdf511aacbf03ba0ad2c9c350c15100818ed5388`.
Gates no appliance ainda estão pendentes e produção segue intocada. Auditoria:
`docs/09-blocking/revisao-funcional-pre-producao-2026-07-29.md`.
FP-017 foi tratado no candidato `_28`; o gate físico continua pendente.

### Candidato `1.8.11_28` — allow PF sem bypass do pfSense (não publicado)

BG-056/ADR-0016 adicionam `layer7_pallow_N` por política e tabelas estáticas
por excepção. O pacote usa `match ... tag L7ALLOW`: somente os blocks Layer7
ignoram a marca, enquanto regras nativas do pfSense continuam a decidir.
Também substitui o `pass quick` histórico da allowlist e das excepções UT1,
fecha o caminho inoperante de exception `block` por origem e inclui
`pallow_0..23` em self-heal/flush. O gate `smoke-layer7d.sh` também volta a
incluir `log_store.c`, eliminando a falha de link introduzida no `_26`, e
`bl_config.c` deixa de depender de inclusão transitiva para `uint32_t`.

Suite local, builder C/PHP/shell, smoke completo, build nDPI e validação do
`.pkg`: PASS. Artefacto `pfSense-pkg-layer7-1.8.11_28.pkg`,
`SHA256=62dd9ae5923ade45b0bb484dca4e835b29b139f7a2aaa0a3624272ba07e59dc6`.
A validação sintática final do PF, instalação passiva e teste two-client ainda
são gates.
Produção permanece intocada. O limite app-only/cold-start e os riscos
FP-009..FP-016 permanecem documentados.

### Candidato `1.8.11_29` — sintaxe anti-QUIC validada no PF (não publicado)

O pré-gate read-only no pfSense Plus 26.03.1 / FreeBSD 16 confirmou `_24`
instalado, `enabled=false`, `mode=monitor`, daemon parado, pacote íntegro,
ruleset actual válido e tabelas de block vazias. Uma amostra `_28` submetida
a `pfctl -nf -` revelou FP-018: anti-QUIC por interface era emitido como
`inet on <if>`, ordem rejeitada pelo parser. A forma `on <if> inet` passou
com as regras `L7ALLOW`, `pallow`, `blsrc`, anti-DoT e anti-QUIC no mesmo
snippet. Nenhuma regra foi carregada e a produção permaneceu inalterada.

BG-057 corrige a ordem em função pura testável e incrementa o port para
`1.8.11_29`. `_28` fica supersedido e não deve ser instalado. Build/artefacto
`_29` e validação do pacote extraído passaram no builder FreeBSD 15:
`pfSense-pkg-layer7-1.8.11_29.pkg`,
`SHA256=bea385ddb6f61bb6a9bffde0b781cea7a852b3956f620b8b004c914b0ab01840`.
O parser do ruleset completo instalado e o gate two-client continuam
pendentes; produção permanece intocada.

### Candidato `1.8.11_30` — continuidade do fluxo sob colisão (não publicado)

A revisão seguinte encontrou FP-019 no lookup open-addressing da captura:
depois da expiração de uma entrada, um slot livre podia ser reutilizado antes
de procurar o mesmo fluxo no restante da janela. Isso criava outro estado
nDPI para a mesma conversa e tornava a classificação intermitente. Se os 64
slots da janela estivessem ocupados, o novo fluxo era descartado sem métrica.

BG-058 passa a exigir probe completo antes de inserir, reutilização do slot
livre somente após a busca e evicção determinística do fluxo menos recente sob
pressão. `cap_active`, `cap_evicted`, `cap_dropped`, `cap_pkts`,
`cap_classified`, `cap_expired` e `captures` passam ao JSON de status.
Regressões locais/builder, suite C/PHP/shell, build nDPI e pacote extraído:
PASS. Artefacto `pfSense-pkg-layer7-1.8.11_30.pkg`,
`SHA256=3a54c667a601e29995562714691f4ee3e9e8e78a02fcd3e600955ae90d2e9b40`.
Gate passivo permanece pendente; produção não foi alterada.

### Candidato `1.8.11_32` — flush PF lifecycle pós-auditoria (não publicado)

Rodada multitask (`2026-07-30`) consolidou ledger REV/FP/AUD
(`docs/09-blocking/diagnostico-multitask-2026-07-30.md`) com veredicto
**NO-GO** mantido para enforce/publicação. **BG-061** fecha B-002/B-003/B-004:
flush `layer7_exc_allow_*`, flush em `layer7_bl_apply()` e `pkg-deinstall`
alinhado a `layer7-pfctl flush-all`. Testes R-21 e contrato FP-015 em
`test_config_parse.c`. Suite local/builder C/PHP/shell e smoke: PASS. Artefacto
`pfSense-pkg-layer7-1.8.11_32.pkg`,
`SHA256=c36ab91ef66504671e109009bdce9df3bb81c75d580b83313dee52f8c3b9640e`.
Publicado em `pablomichelin/Layer7` (`v1.8.11_32`) para download e botao
**Verificar actualizacao**. Gate B1 passivo pendente; produção não alterada.
`_31` supersedido — não instalar `_31` se `_32` disponível.

### Plano isencoes VIP e UX GUI (`2026-07-30`, documental)

Plano SSOT [`docs/02-roadmap/plano-isencao-vip-e-ux-gui.md`](docs/02-roadmap/plano-isencao-vip-e-ux-gui.md)
e modelo conceptual [`docs/00-overview/modelo-conceptual-gui.md`](docs/00-overview/modelo-conceptual-gui.md)
registados. Backlog **BG-064** (isenção VIP nos Perfis rápidos, candidato
`_48`), **BG-065** (UX modal + verificador, `_49`), **BG-066** (exclusão por
política + ADR-0019, `_50`). Regra de ouro: campos GUI são atalhos, não
armazenamentos paralelos. Execução em blocos A→E; NO-GO produção inalterado.

### Lista VIP global — Bloco A governança (`2026-07-31`, documental)

Extensão da isenção VIP para «director isento de tudo»: GUI de primeira
classe com descrição por entrada, limites daemon coerentes e isenção no
caminho DNS (sinkhole Unbound + `force_dns`). **ADR-0020** registado.
Backlog **BG-071** (GUI Lista VIP, candidato `_57`), **BG-072** (limites
`L7_EXC_MAX_HOSTS` 32 / `L7_EXC_MAX_CIDRS` 16, `_58`), **BG-073** (isenção
DNS, `_59`). Decisões fechadas: **D1** `vip-isentos` permanece SSOT (sem
mecanismo paralelo); **D2** labels em `layer7["vip_meta"]["labels"]` (daemon
nunca lê); **D4** isenção DNS — opção (a) view Unbound preferida, fallback
(b) rdr `from !<layer7_exc_allow_N>` com limitação sinkhole honesta. Ordem
A→B→C→D→E; Bloco A concluído; **Bloco B concluído** (`1.8.11_57` publicado);
**Bloco C concluído** (`1.8.11_58` publicado); **Bloco D concluído** (`1.8.11_59` publicado; pós-auditoria `_60`; perf `_61`);
**Bloco E concluído documental** (roteiro lab sec.
**20** em `validacao-lab.md` actualizado para **`>= 1.8.11_61`** recomendado /
**`>= 1.8.11_60`** minimo + gate **20.4** persistencia `filter_configure`). Feature Lista VIP global **A–E** fechada em
codigo + documentacao; **gate appliance** (execucao sec. 20) pendente validacao
humana. NO-GO produção inalterado (referência enforce `1.8.11_24` até G2–G7).

### Release `1.8.11_47` — HTTPS ao portal com erro imediato (publicada `2026-07-30`)

UX da block page: HTTPS a dominio bloqueado (sinkhole → portal) deixava o
browser "a carregar" ate ao timeout sem pagina de erro — o SYN a portal:443
era aceite por regras `pass` anteriores (anti-lockout / allow LAN) e o
`net.inet.tcp.blackhole=2` do pfSense dropava em silencio a porta fechada.
Fix: rdr TCP 443 → servico local da pagina (rdr precede o filtro, mesmo
caminho do :80); o cliente TLS recebe resposta HTTP invalida e o browser
mostra o erro de ligacao de imediato. Salvaguarda `layer7_webgui_port()`: a
porta efectiva do webConfigurator nunca e redireccionada. Candidato `_46`
(`block return-rst`, nunca publicado) supersedido — as `pass` anteriores
venciam pela ordem. Validado no appliance: HTTPS falha <1s, HTTP devolve a
pagina, GUI :9999 intacta. Artefacto
`SHA256=878f8d54b828f3d57236b565b6c55fcfae9eb3e6965e1eafadab42792801195b`,
tag `v1.8.11_47` em `pablomichelin/Layer7`.

`_47`.

### Release `1.8.11_65` — GUI i18n + ícones FA6 + Mensagens / BG-076 (publicado `2026-08-04`)

Apenas apresentação: dicionário EN completo para `l7_t()`/catálogo de
perfis; render de ícones compatível com Font Awesome 6 do pfSense (`fab` +
aliases FA4); label PT «Mensageria» → «Mensagens» (id `mensageria`
preservado). Daemon/enforcement inalterados. Artefacto `SHA256=e7c8ca44f34e19da3a2958eacfd09fce5c77c77d5acd6d8633e9ca9d42cdd48e`, tag
`v1.8.11_65` em `pablomichelin/Layer7` (ver `MANUAL-INSTALL.md`).
Rollback: `_64`. Produção enforce continua `_24`.

### Release `1.8.11_64` — Materializar VIP/excepções PF live / BG-075 (publicado `2026-08-04`)

Corrige VIP isento no JSON/rules.debug mas **ausente** no PF live
(`pfctl -t layer7_exc_allow_N` → Table does not exist): sem `L7ALLOW` o
cliente VIP caía no block global `layer7_block_dst`. Causa: materialização
`persist` incompleta — `layer7_resync` omitia `exc_allow`/`pexc`/`blsrc`.
Fix: `layer7_static_origin_tables_apply_to_pf()` + ensure no helper.
Artefacto `SHA256=79d348c26b20080520121cb32e521a89cdc4639dcb7b2787e46a26f0dd48fa76`, tag `v1.8.11_64` em
`pablomichelin/Layer7`. Rollback: `_63`. Produção enforce continua `_24`.

### Release `1.8.11_63` — Redesign compacto grelha Perfis rápidos / BG-074 (publicado `2026-07-31`)

Redesign **apenas de apresentação** da grelha **Perfis rápidos** em
`layer7_policies.php`: cartões horizontais compactos (~64px), switches CSS
estilo iOS/UniFi (mantêm POST `toggle_profile_on/off`), grupos accordion com
badge «N ligados» e persistência `localStorage`, barra de pesquisa + filtro
«Só ligados», badges personalizado/editado como pontos coloridos, descrição em
tooltip, grupo Presets com fundo tintado. Secção **Perfis ocultos** adaptada.
Zero alteração funcional (modais, hits, VIP, handlers POST intactos). Daemon
inalterado. Artefacto
`SHA256=71325e6bd48db681406fa1d27d6a63720e61e566fd4bf4ae651fa164b37d8f6d`,
tag `v1.8.11_63` em `pablomichelin/Layer7`. Rollback: `_62`.

### Release `1.8.11_62` — Fix `$data` indefinido no rdr CIDR / BG-073 (publicado `2026-07-31`)

Correcção pontual encontrada em auditoria da `_61`:
`layer7_generate_rdr_rules_snippet()` chamava
`layer7_vip_dns_rdr_from_cidr($data, $cidr)` com `$data` inexistente na função
(bug pré-existente da `_60`); passa agora `$l7config` já carregado. Elimina o
warning PHP 8 «Undefined variable $data» em cada `filter_configure` com regras
`force_dns` por CIDR e concretiza o ganho de performance da `_61` no caminho
CIDR (sem releitura de `layer7.json` por linha rdr). **Zero alteração
funcional** — o fallback `null` recarregava a mesma config; regras rdr
idênticas. Artefacto
`SHA256=162598edcc10e84a2648d7c7bbfb797edcdb41ae7a3eca8718c2bb0c78997cc9`,
tag `v1.8.11_62` em `pablomichelin/Layer7`. Rollback: `_61`.

### Release `1.8.11_61` — Performance VIP DNS fallback / BG-073 (publicado `2026-07-31`)

Micro-release de qualidade/performance: `layer7_vip_dns_rdr_from_any()` e
`layer7_vip_dns_rdr_from_cidr()` passam `$data` a
`layer7_vip_dns_rdr_fallback_enabled()` (evita releituras de `layer7.json` em
cada interface×CIDR no `filter_configure`). Ramo redundante removido em
`layer7_vip_dns_mode_get()`. **Zero alteração funcional observável.**
Artefacto `SHA256=a5feacb90eda8d6f07920a8e104fe4d66f65dc672c59a22458ad2ff6ec5345e5`,
tag `v1.8.11_61` em `pablomichelin/Layer7`. Rollback: `_60`.

### Release `1.8.11_60` — Pós-auditoria Lista VIP / BG-073 fix (publicado `2026-07-31`)

Corrige regressão em que `layer7_vip_dns_rdr_fallback_enabled()` dependia de
`$GLOBALS` definido apenas em `layer7_vip_dns_sync()` — em qualquer
`filter_configure` externo o rdr `:53` voltava a `from any` e VIPs em fallback
eram silenciosamente enviados ao sinkhole. Passa a derivar do estado
persistente (mesmos critérios que `layer7_vip_dns_mode_get`: `should_apply` +
marker Unbound ausente). Docblock limites VIP (32+16). Artefacto
`SHA256=19ddb638d3449d26fab5188d4472cc9427af99832eb96c3bd8cbf9a740d09fb8`,
tag `v1.8.11_60` em `pablomichelin/Layer7`. Rollback: `_59`.

### Release `1.8.11_59` — BG-073 isenção VIP caminho DNS (publicado `2026-07-31`)

**ADR-0020 opção (a):** view Unbound `layer7-vip-exempt` (sem `view-first`) via
`access-control-view` para IPs/CIDRs de `vip-isentos`; markers idempotentes;
`unbound-checkconf` antes de gravar. **Fallback (b):** rdr `:53`
`from !<layer7_exc_allow_N>` se a view falhar. Sinkhole bypass **completo** com
(a); parcial com (b). GUI Lista VIP reflecte modo efectivo. Artefacto
`SHA256=579114d8583e8ca01d3888d0cd308121640d8cee2e8f88a4c250c0b2a35de004`,
tag `v1.8.11_59` em `pablomichelin/Layer7`. Rollback: `_58`.

### Release `1.8.11_58` — BG-072 limites daemon Lista VIP (publicado `2026-07-31`)

Alarga `L7_EXC_MAX_HOSTS` 8→32 e `L7_EXC_MAX_CIDRS` 8→16; validacao PHP
`LAYER7_VIP_MAX_*` alinhada (32+16). Memoria estatica maxima excepcoes:
16 × `struct layer7_exception` ≈ +19 KiB vs limites 8+8 (arrays hosts/cidrs
+1216 B/excepcao). Parser ingenuo inalterado; isencao DNS permanece Bloco D.
Artefacto `SHA256=b5bc99db8ac8bf3b4aca871c3f56b6a5e9029109773791a08081ac16917a2b84`,
tag `v1.8.11_58` em `pablomichelin/Layer7`. Rollback: `_57`.

### Candidato `1.8.11_57` — BG-071 Lista VIP global (publicado `2026-07-31`)

Secção **Lista VIP (isencão total)** em Excepções: descricao por entrada
(`layer7.vip_meta.labels`), validacao 8+8, export/import JSON, link no modal
Perfis rapidos. SSOT `vip-isentos` inalterado; daemon inalterado (Bloco C
alarga limites). Aviso sinkhole DNS honesto (Bloco D / ADR-0020).
Artefacto `SHA256=0018de424180f8fb2ec845f6e885dcd7aa0c898d20d0a7b14f5f2883a6ac45b9`,
tag `v1.8.11_57` em `pablomichelin/Layer7`. Rollback: `_56`.

### Candidato `1.8.11_56` — BG-070 integral + correcções pós-_55 defeituoso

Rebuild obrigatório: `1.8.11_55` foi compilada no builder antes do commit
completo de BG-070 — faltavam GUI (`l7showProfileEditModal`), export/import
`profiles_custom` e scripts install/deinstall para `profiles-custom.json`.
`_56` entrega BG-070 integral mais: secção **Perfis ocultos** na GUI (reverter
ocultar); fix `PKG_UPGRADE` em `pkg-deinstall.in` (`true` vs `YES`);
validação FA 4.7 em `layer7_profile_icon_valid()`. **`_55` nao instalar.**
Testes builder + validação artefacto (4 checks) PASS. Artefacto
`pfSense-pkg-layer7-1.8.11_56.pkg`,
`SHA256=8641c3c8dae6a46148c934f138f64be1121064ee676907955c60379699147357`.
Release publicada `v1.8.11_56`. Gate appliance pendente;
producao enforce continua `_24`.

### Candidato `1.8.11_55` — perfis editáveis e personalizados (BG-070) — **DEFEITUOSO**

Overlay cliente em `/usr/local/etc/layer7/profiles-custom.json` (**nunca** no
`pkg-plist`): overrides de fábrica (apps/hosts add/remove, ocultar cartão) e
perfis novos (`c-*`) no grupo **Personalizados**. `layer7_load_profiles()` faz
merge fábrica → overrides → custom; GUI com criar/editar/apagar, badges
`personalizado`/`editado`, reconnect automático de política ligada; export/import
e preservação em upgrade/uninstall (keep-config). Apps restritos ao catálogo
nDPI dos 72 perfis de fábrica; hosts texto livre validado. Daemon inalterado.
**Artefacto publicado incompleto** (só merge em `layer7.inc`; GUI/scripts em
falta). **`NAO INSTALAR`** — usar `_56`. SHA256 publicado
`616e72b104a69e9721203f897acfa3e3f8176191c8b8f7dc73b93cfe8d3c7b8f`.
Gate appliance pendente; produção enforce continua `_24`.

### Candidato `1.8.11_54` — correcção visual grelha Perfis rápidos (BG-069)

Correcção só de GUI sobre o catálogo de 72 perfis do `_53`. Três defeitos
visuais corrigidos em `layer7_policies.php`: cabeçalhos de grupo "flutuavam"
inline no meio dos cartões (usavam `grid-column:1/-1` dentro de um container
`display:flex` — propriedade de CSS grid ignorada em flex); 55 dos 72 perfis
mostravam letra em quadrado cinzento porque a GUI ignorava o campo `icon` do
`profiles.json` e usava um mapa SVG hardcoded de 17 ids; cartões com alturas
irregulares. Agora cada grupo é secção própria (cabeçalho full-width com
contador + grelha), todos os cartões renderizam o ícone FontAwesome 4.7 do
`profiles.json` (cor por marca ~55 ids ou por grupo) e têm altura uniforme
(descrição truncada a 3 linhas, botões ancorados ao fundo). `profiles.json`:
`ai-tools` troca `fa-robot` (só FA5) por `fa-magic` — único ícone fora do
FA 4.7. Fixture `tests/fixtures/fa47-icon-names.txt` (782 nomes oficiais) e
validação no `test_profiles_json.sh`. Zero mudança funcional (toggle, modal
Opções, hits, VIP isentos intactos); daemon inalterado. Suite local/builder e
`php -l` PASS; pacote extraído validado. Artefacto
`pfSense-pkg-layer7-1.8.11_54.pkg`,
`SHA256=36317498e5d2671a2c0a1d42825169a5b7f709e6fa89e27a3660acf8672e87f4`.
Release publicada `v1.8.11_54`. Gate appliance pendente;
produção enforce continua `_24`.

### Candidato `1.8.11_53` — expansão catálogo Perfis rápidos Bloco 2 (BG-068)

Expansão de **38 para 72 perfis**: videoconferência (Zoom, Teams, Meet, Webex,
TeamSpeak), redes alternativas (Threads, Bluesky, Kick, Rumble), streaming
(Deezer, SoundCloud, DAZN, Paramount+, Hulu, futebol pirata), jogos (Roblox,
Free Fire, Cloud Gaming), produtividade (empregos, notícias, desporto, viagens,
speedtest), segurança (anonymizers, publicidade, malware, mining) e 3 presets
(distrações, proteção infantil, higiene de rede). Grupos GUI novos:
**Comunicação e reuniões** e **Presets**. Reforço de vpn-proxy (Psiphon,
UltraSurf, CloudflareWarp, iCloudPrivateRelay) e agregados social/gaming/cripto.
Só `profiles.json` + `layer7_policies.php`; daemon inalterado. Teste
`test_profiles_json.sh` (72 perfis, 202 refs nDPI) + suite local/builder PASS.
Artefacto `pfSense-pkg-layer7-1.8.11_53.pkg`,
`SHA256=3ea425b8f8e9564c52eb8e30190a9c77b7772d2b319dd2da8280753a76384bbe`.
Release publicada `v1.8.11_53`. Gate appliance pendente;
produção enforce continua `_24`.

### Candidato `1.8.11_52` — catálogo Perfis rápidos nível UniFi/UDM (BG-067)

Reforma do catálogo `profiles.json` de 18 para **38 perfis** com grupos na GUI,
correcções de hosts/apps desactualizados, primeiro uso de `ndpi_categories`,
modal Opções corrigido (slice apps 12→64) e validação nDPI no builder FreeBSD 15.
Só `profiles.json` + PHP/GUI; daemon inalterado. Teste
`tests/unit/test_profiles_json.sh` + suite local/builder PASS. Artefacto
`pfSense-pkg-layer7-1.8.11_52.pkg`,
`SHA256=5a3ee6fa1f8a0cf486feb896464401629aeafac394089929a5634200c76278b7`.
Release publicada `v1.8.11_52`. Gate appliance pendente;
produção enforce continua `_24`.

### Candidato `1.8.11_51` — fix ordem PF da exclusão por política (BG-066)

Auditoria pós-execução (`2026-07-30`) encontrou defeito no `_50`: em
`layer7_policy_enforcement_rules_text()`, o `match from <layer7_pexc_N> to
<layer7_pdst_N> tag L7ALLOW` era emitido **depois** dos `block drop quick` da
mesma política. Como `quick` é terminal, a origem excluída era dropada antes
de receber a tag e a exclusão PF era inoperante quando o destino entrara em
`pdst_N` por outro cliente. `_51` move o match para antes dos blocks (mesmo
padrão do allowlist/pallow) e `test_scoped_pf_inc.php` ganha asserção de
**ordem** (o teste do `_50` só validava presença). Só `layer7.inc` + teste;
daemon inalterado. `_50` fica supersedido para a feature de exclusão em
scoped. Testes PHP no builder: PASS; pacote extraído validado (match precede
blocks). Release publicada `v1.8.11_51`
(`SHA256=9ef8e7f1006f093d6c1c37281c1231ca030f5be879d986055d1b300cc30b7f18`),
`releases/latest` confirmado. Gate appliance §19.3 pendente.

### Candidato `1.8.11_49` — UX modal + verificador (BG-065)

Progressive disclosure no modal Perfis rapidos; grupos-first com atalho criar
grupo; verificador em `layer7_test.php` com veredicto PERMITIDO/BLOQUEADO e
motivo legivel. So PHP/GUI. Release publicada.

### Candidato `1.8.11_50` — exclusão por política (BG-066 / ADR-0019)

Campos `match.src_exclude_*` no daemon e PF (`layer7_pexc_N` + `L7ALLOW` em
scoped). GUI Avançado + validação conflito include/exclude. Testes C/PHP/shell
locais e builder: PASS. Release publicada `v1.8.11_50`
(`SHA256=e2e388b33fdd63b4439e7ca7c9a8e101aa41b87848fd06c41e02edb1211abfea`).
**Defeito conhecido:** a regra PF `match pexc` era emitida depois dos blocks
`quick` — exclusão inoperante na camada PF; corrigido no `_51`.
Gate appliance §19.3 pendente.

### Candidato `1.8.11_48` — isencao VIP nos Perfis rapidos (BG-064)

Modal **Opções** dos Perfis rapidos passa a gerir a excepcao canonica
`vip-isentos` (allow global): grupos expandem para IPs/CIDRs na gravacao;
badge em Excecoes; `toggle_profile_off` nao remove a excepcao partilhada.
So PHP/GUI — sem alteracao ao daemon. Teste funcional
`tests/functional/test_vip_exception.php`. `_48` **nunca teve build/release
proprio** — o codigo foi consolidado e distribuido no pacote `1.8.11_49`
(tag `v1.8.11_49`); nao existe artefacto nem tag `v1.8.11_48`. Rollback:
reinstalar `_47`.

### Release `1.8.11_45` — rdr da block page e DNS forcado agora efectivos (publicada `2026-07-30`)

Descoberto em lab que o redirect HTTP :80 para a pagina de bloqueio e o DNS
forcado :53 **nunca funcionaram**: as regras `rdr` eram carregadas no anchor
`natrules/layer7_nat`, mas o ruleset principal do pfSense so declara
`nat-anchor "natrules/*"` (sem `rdr-anchor`) e, em PF, regras `rdr` num
anchor sem ponto `rdr-anchor` nao sao avaliadas — quem respondia no :80 era
o nginx do webConfigurator (301). Fix: `layer7_generate_rules("nat")`
devolve o snippet rdr ao `discover_pkg_rules` do pfSense (hook
`filter_rule_function`, mesmo mecanismo do Squid transparente) e as regras
entram no **ruleset principal** em cada filter reload;
`layer7_inject_nat_to_anchor()` removido e anchor legado flushado.
Validado no appliance: `http://youtube.com` de cliente LAN devolve
`<title>Acesso bloqueado</title>`; `drill youtube.com @8.8.8.8` e
interceptado e respondido pelo sinkhole local. HTTPS continua a mostrar erro
TLS (sem MITM, ADR-0017). Verificacao operacional passa a ser `pfctl -s nat`
(comando antigo com anchor e obsoleto). Artefacto
`SHA256=a76cfb9b5fa352ce3989fd073801fcddcba098640933c96b68be76144d04531a`,
tag `v1.8.11_45` em `pablomichelin/Layer7`.

### Release `1.8.11_44` — CRITICO: daemon nunca bloqueia IPs do firewall (publicada `2026-07-30`)

Bug de desenho descoberto em lab: o sinkhole da block page resolve dominios
bloqueados para o IP portal (interface do firewall). O daemon via essa
resposta DNS/fluxo e adicionava o **proprio IP do pfSense** a
`layer7_block_dst` — a regra `block drop quick from any to <layer7_block_dst>`
cortava GUI/SSH a `192.168.100.254` a partir de **todas** as redes (sintoma
reportado: VLAN 95 sem acesso ao firewall pelo IP LAN). Fix: guard
`ip_is_local_iface_addr()` (getifaddrs, cache 60s) em todos os caminhos de
insercao block do daemon (politica DNS/fluxo + blacklist DNS/SNI). Validado
no appliance: consulta sinkhole `youtube.com`→portal a partir da LAN deixa a
tabela vazia e o log regista `enforce_block: skip IP local do firewall`.
`_44` e **obrigatoria** onde a block page esteja activa. Artefacto
`SHA256=efa4f0d5f8e55cae319ecc27343c83604947e85f13062f1512c8f77d90789df2`,
tag `v1.8.11_44` em `pablomichelin/Layer7`.

### Releases `1.8.11_36`–`_43` — updater CSP, precedência de bloqueio e plano DNS (publicadas `2026-07-30`)

**Updater GUI (`_36`–`_38`):** «Verificar actualizacao» AJAX corrigido para o
CSP do pfSense Plus — JS movido para ficheiro externo
`layer7_settings_update.js` + config via `data-l7-update-cfg`. Validado no
appliance (`_38`).

**BG-063 / ADR-0018 (`_39`–`_43`):** teste YouTube em lab revelou que a
allowlist-seed continha `youtube.com` e anulava a política block do admin;
IPs de CDN Google partilhados em `layer7_allow_dst` furavam o PF. Correcções:

- `_39` (daemon): política manual block **prevalece** sobre allowlist-seed
  (DNS + fluxo nDPI); `allow_cache_revoke_ip` remove o IP de
  `layer7_allow_dst` ao aplicar block; `youtube.com` removido da seed.
- `_40`: `block_page.force_dns` opt-in — rdr global UDP/TCP :53 → Unbound
  local (anti-bypass sinkhole) + anti-DoH automático (NXDOMAIN + canário).
- `_41`: fix detecção IP portal quando `layer7.interfaces` usa nomes reais
  (`vmx0`, `vmx0.95`) — sem portal, sinkhole e rdr nunca eram gerados.
- `_42`: pf rejeita `label` em regras rdr — o rdr :80 da block page nunca
  carregava desde `_35` (erro silencioso); helper `layer7-blockpage` saía de
  imediato sob daemon(8) (self-check de pidfile).
- `_43`: rc.d deduplica pela porta 8099 e status com fallback sockstat.

Cadeia completa validada no appliance `192.168.100.254` (`_43`): anchor
`natrules/layer7_nat` com 6 rdr, sinkhole `youtube.com`→portal, página
«Acesso bloqueado» servida em `127.0.0.1:8099`. Artefacto `_43`
`SHA256=65264b6411dc4be06be5c887bc821904892b6f7cba68228ed09ce1a4dc9a0efc`.
Todas as tags em `pablomichelin/Layer7`. Produção enforce de referência
permanece `_24` até gates G2–G7.

### Release `1.8.11_35` — pagina de bloqueio utilizador final (publicada `2026-07-30`)

**BG-062 / ADR-0017:** pagina informativa para o utilizador final via DNS
sinkhole (Unbound) + servico HTTP local `layer7-blockpage` + NAT rdr :80.
Toggle opt-in OFF nas Definições; mensagem/titulo/contacto customizaveis.
Enforcement PF inalterado com feature desactivada. HTTP: pagina visivel;
HTTPS: erro TLS (sem MITM) — documentado. Teste:
`tests/test_blockpage_config.sh`; roteiro `validacao-lab` sec. **18**.
Artefacto `pfSense-pkg-layer7-1.8.11_35.pkg` (SHA256
`86d0939d9fa81f4f3aa4fdf967fa06647e02e94b3afba73447c19cfb98c764a4`).
Canal: `pablomichelin/Layer7` tag `v1.8.11_35`. Produção enforce de
referência permanece `_24` ate gates G2–G7.

### Candidatos `1.8.11_33` / `1.8.11_34` — GUI (publicados `2026-07-30`)

`_33`: progresso de download blacklists visivel na GUI.
`_34`: botao «Verificar actualizacao» via AJAX sem reload.
Ambos publicados em `pablomichelin/Layer7`; `_34` = `releases/latest` antes
de `_35`.

### Candidato `1.8.11_31` — classificação nDPI até estado final (não publicado)

A continuação da revisão encontrou FP-020: a captura marcava o fluxo como
concluído no primeiro resultado não-Unknown de `ndpi_is_protocol_detected()`.
No nDPI 5.x esse resultado pode estar em `NDPI_STATE_PARTIAL`; aplicação,
categoria e SNI ainda podem ser refinados em pacotes seguintes. Isso explica
políticas de aplicação que funcionavam em algumas sessões e falhavam noutras.
Quando o limite de 48 pacotes era atingido, também faltava chamar
`ndpi_detection_giveup()`.

BG-059 faz a decisão aguardar `NDPI_STATE_CLASSIFIED`; ao esgotar o orçamento,
usa o fallback oficial do nDPI antes de emitir a única classificação do fluxo.
Suite C/PHP/shell, build nDPI e pacote extraído: PASS. Artefacto
`pfSense-pkg-layer7-1.8.11_31.pkg`,
`SHA256=dc5118dd01193a83a6c6d15cc3ae4ca300647294a5b188e1991a363b4c453e33`.
Gate passivo permanece pendente; produção não foi alterada. Supersedido por `_32`
para testes de flush/lifecycle.

### Release `1.8.11_23` — Caminho A completo A0-A5 (publicada `2026-05-30`)

Artefacto `pfSense-pkg-layer7-1.8.11_23.pkg`
(`SHA256=3c9e488d48c441a9859a1d953b603e9cecb242fc9d2e93ce144e05cdacb8d7d4`),
build no builder FreeBSD e validado no appliance. Consolida o Caminho A sobre
a Fase 1 (detalhes em `docs/changelog/CHANGELOG.md > [1.8.11_23]`; backlog
BG-039..BG-044 todos `Concluido`):

- **A0 (BG-039) higiene:** perfil `github`, limite de hosts GUI/daemon alinhado
  a 64, docs clarificam `block`=destino. ADR n/a.
- **A1 (BG-040) inventario de dispositivos:** pagina `Dispositivos` read-only
  (DHCP leases + ARP + OUI + alias). ADR-0011.
- **A2 (BG-041) politicas por dispositivo:** grupo aceita `device_macs`, pacote
  resolve MAC->IP (`device_ips`), daemon le como src hosts. ADR-0012.
- **A3 (BG-042) SNI/Host via nDPI:** toggle `sni_inspection` opt-in OFF; usa o
  SNI/Host ja extraido pelo nDPI (sem parser proprio, sem MITM); por destino.
  ADR-0013. Limite: TLS 1.3 ECH.
- **A4 (BG-043) UX tipo UDM:** toggle on/off directo por perfil + estado visual
  + hit counters por perfil; nome de dispositivo no top clientes.
- **A5 (BG-044) F5 alargada:** `test_config_parse.c` (run-local) +
  `smoke-caminho-a.sh` (appliance), cobrindo A0-A4 e a regressao do parse SNI.

`PORTVERSION=1.8.11`, `PORTREVISION=23`. Monitor continua passivo (gate da Fase
1 intacto); enforce ao vivo exige licenca valida no appliance.

### Caminho B (E0 iniciado — 2026-06-15)

O **Caminho A** nao alterou a imposicao PF global (`layer7_block_dst`). O
**Caminho B** (Enforcement 100%) corrige enforcement escopado por politica.
Plano SSOT: `docs/09-blocking/plano-enforcement-100-porcento.md`; ADR-0014.

- **E0 (BG-045) fundacao:** flag `layer7.enforcement_model`
  (`legacy_global` default | `scoped_hybrid` experimental); parse em
  `config_parse`; selector em Settings; **sem alteracao de runtime PF** ate E2.
- **E1 (BG-046) decisao unificada:** `layer7_decide_for_client()` em
  `policy.c`; `struct layer7_decision` estendida (`enforce_kind`,
  `policy_table_idx`); DNS e nDPI usam a mesma cadeia (excepcoes → politicas
  → default) em **ambos** os modos; a diferenca e runtime PF: `legacy_global`
  popula `layer7_block_dst` (global por destino); `scoped_hybrid` popula
  `layer7_pdst_N` / `layer7_psrc_N`; `layer7_domain_is_blocked()` permanece
  no codigo mas **nao** e chamado em runtime desde E1/E3;
  7 testes em `test_policy_decide.c` (run-local).
- **E2 (BG-047) PF escopado no pacote:** `layer7_policy_enforcement_rules_text()`
  em `layer7.inc` (so `scoped_hybrid`+enforce); tabelas `layer7_pdst_N` /
  `layer7_psrc_N` por politica block; regras `from src to pdst` e
  `from psrc to !localsubnets`; `scope_global` JSON+GUI; flush em
  `layer7_resync`, `layer7-pfctl flush-all`, `enforcement_flush_all_tables()`;
  `legacy_global` inalterado; `test_scoped_pf_inc.php` (run-local).
- **E3 (BG-048) daemon enforcement escopado:** runtime segue decisao —
  `layer7_on_dns_resolved` / `layer7_on_classified_flow` populam
  `layer7_pdst_N` / `layer7_psrc_N` (nao `layer7_block_dst` em scoped);
  cache TTL por `(table, ip)`; `enforce.c` + CLI `-e` alinhados;
  `test_enforce_scoped.c` (run-local). **Gate two-client appliance
  pendente** (nao avancar E4 sem gate).
- **E4/E5/E7 (BG-049/BG-050/BG-052):** parcialmente endereçados no candidato
  `_25` (validação de escopo GUI e regressões; semântica app/host substituída
  pela emenda `_27`);
  gate appliance, semântica completa, CDN/ECH e release continuam pendentes.

O plano mestre historico `docs/09-blocking/blocking-master-plan.md` (fases A–F,
v1.0.0) esta **concluido**; a trilha activa pos-V1 e Caminho B sobre
`1.8.11_23`.

### Release `1.8.11_18` — Fase 1 de estabilizacao (publicada `2026-05-30`)

Artefacto `pfSense-pkg-layer7-1.8.11_18.pkg`
(`SHA256=98374806be31094a3835bcae0c96164369860aef82db3bfb4255f44c9d60b876`),
build no builder FreeBSD e validado no appliance pfSense Plus 26.03.
Bloqueios resolvidos (Blocos 1-3, 5 e 6 do plano `Layer7 estabilizacao e
Caminho A`); detalhes completos em
`docs/changelog/CHANGELOG.md > [Unreleased]` e `docs/02-roadmap/backlog.md`
(BG-032 fechado; novos BG-034..BG-038 registados):

- **BG-034 (Bloco 1) — Monitor passivo de verdade:** em `mode=monitor` ou
  `enabled=false` o pacote deixa de injectar qualquer `block drop`; novo
  `layer7_pf_should_enforce()` gate em `layer7.inc`.
- **BG-035 (Bloco 2) — Anti-DoT/DoQ como toggle (OFF por defeito):** novo
  campo `block_dot_doq` em **Settings > Servico**.
- **BG-036 (Bloco 3) — Allowlist de destinos:** novo modulo `allowlist.{c,h}`
  no daemon, pagina `Services > Layer 7 > Allowlist`, seed embutida
  (`allowlist-seed.txt`: bancos BR, gov, push Apple/Google, MS 365), tabela
  PF `layer7_allow_dst`; o candidato `_28` substitui o `pass quick` histórico
  por marca interna `L7ALLOW`, sem autorizar perante regras nativas.
- **BG-037 (Bloco 5) — Flush fiavel de tabelas:** `enforcement_flush_all_tables()`
  no daemon (transicao enforce->passivo + shutdown); `rc.d/layer7d stop`
  chama `layer7-pfctl flush-all` como defesa em profundidade.
- **BG-032 (Bloco 6) — CLI `--license-status`:** restaurado em formato
  `chave=valor`, exit 0 se valida (incl. grace).
- **BG-038 (F5 minima):** 24 testes unitarios da allowlist (PASS local) +
  smoke `tests/lab/smoke-monitor-mode.sh` para o appliance.

`PORTVERSION=1.8.11`, `PORTREVISION=18` no `package/pfSense-pkg-layer7/Makefile`.
Caminho B (inline/divert) e qualquer reorganizacao F6 continuam fora desta
fase. Caminho A (UX tipo UDM Pro, CDN-aware) fica **desbloqueado** agora que
`1.8.11_18` esta validado no appliance.

**Plano do Caminho A:** `docs/09-blocking/caminho-a-plano-de-implementacao.md`
(blocos A0-A5; mapeia fases V2 15/17/18; backlog BG-039 a BG-044). O produto
**ja tem** perfis de servico, politicas nDPI/host/CIDR e bloqueio por destino;
os gaps reais face ao UDM Pro sao identidade de dispositivo (MAC/DHCP/ARP),
SNI real (parser ClientHello) e UX de toggle/vista unificada. Recomenda-se
arrancar pelo bloco **A0** (higiene, baixo risco) e seguir A1 (inventario de
dispositivos, read-only). A F5 alargada (BG-044) protege contra regressao.

O hotfix **`1.8.11_14`** (GUI updater / `BG-030`) permanece descrito no
`CHANGELOG`; linha `1.8.11_17` herda esse comportamento no updater.

A trilha **F1.3 de blacklists** continua activa desde `1.8.11_13` com a
mesma chave: fingerprint da chave publica embutida
`6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`,
snapshot publica `pablomichelin/Layer7 / blacklists-ut1-current`
(`snapshot_id=ut1-2026-04-25`,
`SHA256=4191e2ebdc13e3c87d777103528bab4fda6b273bc40c62a2c39cb820ad493d36`).
A chave **privada** correspondente fica em custodia humana, fora do
builder e fora do repositorio.
**Versao do port no branch actual (`package/pfSense-pkg-layer7` / `PORTVERSION`
+ `PORTREVISION`):** `1.8.11_65` (`PORTVERSION=1.8.11`, `PORTREVISION=65`),
publicada no canal de teste/lab (`v1.8.11_65`). **`1.8.11_55` defeituosa — nao
instalar.** A referencia de producao enforce permanece `v1.8.11_24`; gate
two-client pendente.
**Data-base deste checkpoint:** `2026-08-04`

O Layer7 e um pacote proprietario para pfSense CE com daemon `layer7d`,
GUI integrada, classificacao Layer 7 via nDPI, politicas granulares,
enforcement PF, anti-bypass DNS, blacklists UT1, relatorios locais e
licenciamento baseado em ficheiro `.lic` assinado com Ed25519.

O produto **nao esta em fase de descoberta funcional**. A prioridade agora
e preservar o que ja funciona, reduzir risco tecnico e evoluir por fases
controladas, com governanca forte e zero regressao desnecessaria.

---

## Estado actual

### Estado funcional

- V1 Comercial ja foi concluida e publicada.
- O pacote publico de referencia continua a ser o `.pkg` distribuido via
  GitHub Releases.
- A ultima publicacao conhecida no canal publico e o pacote **`1.8.11_24`**
  (GitHub `pablomichelin/Layer7`, tag `v1.8.11_24`,
  `SHA256=1d5573f0a0c7803a87d8cb536ad9eee43e85daa9bf98bf7edc84ef554e2c7818`).
  Caminho B E0–E3 publicado; **gate two-client** (`validacao-lab.md` sec. 12)
  **PENDENTE** — nao activar `scoped_hybrid` em producao sem gate PASS. Rollback
  imediato: **`1.8.11_23`**. A linha **`1.8.3`** permanece como referencia
  historica de V1 comercial estavel; rollbacks adicionais: `1.8.11_18`,
  `1.8.11_17`, `1.8.11_16`, `1.8.11_14`, `1.8.11_13`, `1.8.11_12`, `1.8.3`.
- Bloqueio QUIC configuravel por interface na GUI e restricao
  `to !<localsubnets>` em bloqueios permanecem como base funcional conhecida.
- O license server existe e esta operacional como componente separado,
  com F2 concluida e a F3 aberta para endurecer o contrato real de
  licenciamento/activacao sem regressao.

### Estado documental

- A Fase 0 documental foi usada para consolidar governanca, canonicidade,
  backlog, roadmap, checklist mestre e mapas de classificacao/equivalencia.
- O planeamento detalhado da F1 foi consolidado sem implementacao tecnica,
  com ADRs formais para distribuicao, cadeia de confianca, blacklists e
  fallback/degradacao segura.
- O directorio `docs/` passa a ser o **centro documental canónico**.
- A raiz actual do repositório continua preservada como **legado importante**.
- **Nao houve reorganizacao fisica** do repositório nesta fase.

### Estado de governanca

- O projecto passa a trabalhar pela ordem segura **F0 -> F7**.
- Nenhuma reorganizacao fisica de pastas esta autorizada antes da **F6**.
- Nenhuma alteracao tecnica deve avancar sem impacto, risco, teste e rollback
  declarados.

---

## Objectivo estrategico

Manter o Layer7 operacionalmente previsivel e tecnicamente auditavel,
priorizando:

- preservacao da V1 ja entregue;
- cadeia de confianca real entre codigo, builder, servidor de licencas e
  artefacto distribuido;
- hardening de componentes pos-V1 sem inflar escopo;
- continuidade entre chats e entre mantenedores;
- documentacao viva, rastreavel e com canonicidade explicita.

---

## Principios obrigatorios

1. **Nao regredir comportamento existente.**
2. **Um bloco pequeno por vez.**
3. **Documentacao e execucao caminham juntas.**
4. **Sem reestruturacao fisica antes da hora.**
5. **Sem “solucoes magicas” nem refactors impulsivos.**
6. **Tudo relevante precisa de gate, risco, teste e rollback.**
7. **Se existir conflito documental, ele deve ser declarado e classificado.**
8. **Na duvida, conservar e documentar.**

---

## Fase actual

**Fase actual consolidada:** F3 aberta em 2026-04-01; F3.1, F3.2, F3.3, F3.4 e F3.5 executadas de forma conservadora em 2026-04-01; F3.6 formalizada documentalmente em 2026-04-01 com matriz canónica de validacao manual/evidencias; F3.7 formalizada de forma conservadora em 2026-04-02 com pack operacional, convencao de evidencias e helper shell barato; F3.8 formalizada de forma conservadora em 2026-04-02 com gate oficial de fechamento, matriz objectiva de decisao por cenario e relatorio final de campanha; F3.9 executada em 2026-04-02 como primeira campanha real controlada (run_id `20260402T130015Z-deploy244`), com evidencias reais de backend e conclusao formal `F3 nao pode fechar`; F3.10 concluida em 2026-04-02 como saneamento documental-operacional da validacao, com matriz de pre-requisitos, matriz de drift operacional e runbook da proxima campanha real; a verificacao de readiness da F3.11 foi executada em 2026-04-02 e resultou em `F3.11 bloqueada por pre-requisitos nao satisfeitos`; o saneamento minimo seguinte da readiness foi registado em `docs/01-architecture/f3-11-readiness-saneamento.md` e concluiu `readiness parcialmente saneada, mas ainda bloqueada`; a rodada documental-operacional seguinte materializou `docs/01-architecture/f3-11-access-enablement-package.md`, `docs/13-runbooks/f3-11-live-access-checklist.md` e `docs/01-architecture/f3-11-drift-registry.md` para transformar os blockers actuais em pacote canonico de desbloqueio, sem abrir campanha e mantendo `F3.11 bloqueada`; a rodada documental-operacional seguinte materializou `docs/01-architecture/f3-11-external-input-request-package.md`, `docs/01-architecture/f3-11-input-acceptance-matrix.md`, `docs/13-runbooks/f3-11-evidence-intake-template.md`, `docs/13-runbooks/f3-11-input-triage-runbook.md` e `docs/01-architecture/f3-11-readiness-reopen-gate.md` para transformar os cinco insumos externos em processo canonico de solicitacao, recepcao, validacao, aceite e `go/no-go`, sem reabrir decisoes, sem campanha e mantendo `F3.11 bloqueada`; a rodada documental-operacional seguinte materializou `docs/01-architecture/f3-11-execution-master-register.md`, `docs/01-architecture/f3-11-operational-decisions-ledger.md`, `docs/01-architecture/f3-11-readiness-scorecard.md`, `docs/13-runbooks/f3-11-cycle-report-template.md` e `docs/00-overview/f3-11-document-traceability-map.md` para converter o kit da F3.11 num cockpit canónico unico de acompanhamento, decisao, score, rastreabilidade e execucao operacional ponta a ponta, sem codigo, sem push, sem campanha e sem reabrir a readiness; a rodada documental-operacional seguinte materializou `docs/01-architecture/f3-11-state-machine.md`, `docs/01-architecture/f3-11-document-sync-protocol.md`, `docs/00-overview/f3-11-start-here.md`, `docs/01-architecture/f3-11-operational-responsibility-matrix.md` e `docs/13-runbooks/f3-11-cycle-closure-criteria.md` para transformar o cockpit da F3.11 num sistema canonico completo de estados, sincronizacao, entrada unica, responsabilidades e fecho de ciclo, mantendo explicitamente `F3 aberta`, `F3.11 bloqueada`, `readiness = NO-GO`, `campanha = NO-GO`, sem codigo, sem push e sem reabrir a readiness; a rodada documental-operacional seguinte materializou `docs/00-overview/f3-organizacao-local-e-fecho.md` e `docs/01-architecture/f3-fecho-operacional-restante.md`, e corrigiu o `start-here`, o mapa de rastreabilidade, o registro mestre, o scorecard e o drift registry para o estado real observado da fase: license-server live alinhado, auth administrativa alinhada, inventario real obtido, DR-01/DR-03/DR-04/DR-06 resolvidos, DR-02 reclassificado como cosmetico nao bloqueante e DR-05 mantido como unico blocker real para fechar a F3; em 2026-04-14, os commits locais foram publicados no `origin/main`, a trilha DR-05 ganhou helpers executaveis para preflight/appliance/GUI, o helper GUI foi exercitado em `probe` com resultado `BLOCKED` esperado sem credencial valida, e o branch passou a cobrir por teste o contrato `409` do `POST /api/activate` para licenca revogada, expirada e hardware divergente.

**Resultado actual conhecido da F1:** a F1.1 fechou o contrato oficial de
distribuicao sobre `.pkg`, URLs versionadas de release e scripts oficiais de
install/uninstall no canal publico. A F1.2 materializou manifesto versionado,
assinatura destacada Ed25519, public key de verificacao e separacao
builder/signer na cadeia de release. A F1.3 materializou manifesto dedicado
de blacklists, public key propria, origem oficial HTTPS, mirror controlado,
cache local e last-known-good restauravel no appliance. A F1.4 materializou a
matriz de fallback por componente: install/update da release passa a validar
manifesto, assinatura e checksum antes do `pkg add`, blacklists passam a
registar `healthy`/`degraded`/`fail-closed` em `.state/fallback.state`, e a
degradacao deixa de ficar implícita na trilha F1.

**Resultado actual conhecido da F2:** o estado real do license server foi
consolidado em `license-server/`, o planejamento detalhado da fase foi
fechado com ADRs normativos para publicacao segura, autenticacao/sessao,
protecao da superficie administrativa e integridade transacional do CRUD, e
a **F2.1** materializou a politica de publicacao segura: `443/TLS` passa a
ser o unico canal publico oficial, `8445` permanece como origin privado com
bind local por defeito, o Nginx interno deixa explicita a fronteira com o
edge proxy, e a documentacao operacional passa a tratar HTTP directo apenas
como troubleshooting controlado. A **F2.2** materializou o contrato de
autenticacao e sessao administrativa: login passa a exigir HTTPS/TLS real,
o frontend deixa de depender de JWT em `localStorage`, a sessao passa a ser
stateful no backend com `admin_sessions`, cookie `HttpOnly + Secure +
SameSite=Strict`, expiracao ociosa/absoluta, renovacao controlada e logout
com invalidacao real no servidor. A **F2.3** materializou a protecao da
superficie administrativa: o backend deixa de operar com `cors()` aberto, o
painel passa a aceitar apenas o origin oficial same-origin em producao, o
login administrativo passa a ter limiter dedicado por IP e por `email + IP`,
lockout temporario por falhas repetidas, politica minima de erro sem
enumeracao de credenciais e trilha minima de auditoria em `admin_audit_log`
e `admin_login_guards` para auth e mutacoes administrativas. A **F2.4**
materializou a integridade do CRUD administrativo: `activate`, `licenses` e
`customers` passam a validar payload com schema fechado, queries/listagens
passam a rejeitar parametros invalidos, mutacoes administrativas passam a
operar com codigos HTTP coerentes (`400`, `404`, `409`, `500`), `activate`
e mutacoes com auditoria passam a usar transacoes explicitas, e o delete
normal do painel passa de remocao fisica para arquivo logico com preservacao
de historico via `archived_at` / `archived_by_admin_id`. A **F2.5**
materializou o fecho operacional da fase: `ED25519_PRIVATE_KEY` passa a
aceitar tambem `_FILE`, o bootstrap administrativo ganha CLI explicito para
`init` e `reset-password`, `seed.js` fica apenas como compatibilidade, o
stack passa a ter scripts minimos de `backup-postgres.sh` /
`restore-postgres.sh`, e a operacao oficial passa a ter runbooks canónicos
de segredos/bootstrap e backup/restore.

**Resultado actual conhecido da F3:** a fase foi formalmente aberta em
`2026-04-01` pela **F3.1**, com mapeamento factual do fluxo actual de
licenciamento/activacao no backend e no daemon, contrato canónico minimo de
estados/transicoes, clarificacao da diferenca entre expiracao online e grace
local do daemon, e um primeiro endurecimento defensivo em `POST /api/activate`
para tornar a reactivacao do mesmo hardware mais idempotente e previsivel sem
quebrar compatibilidade. A **F3.2** fechou de forma conservadora a leitura do
fingerprint/binding em cenarios reais de appliance e a normalizacao defensiva
do `hardware_id` persistido. A **F3.3** fechou a semantica real de expiracao,
revogacao, validade offline e grace em documento canónico proprio, e
materializou um helper minimo de estado efectivo no backend para alinhar
`activate`, `licenses`, `customers` e `dashboard` sem mudar schema, formato
`.lic` ou algoritmo de fingerprint. A **F3.4** fechou a superficie
administrativa real de mutacao/reemissao, formalizou a imutabilidade parcial
de campos criticos apos bind e bloqueou a mudanca de `customer_id` em licenca
activada/bindada no CRUD normal, evitando transferencia silenciosa de
ownership sem abrir workflow novo de rebind. A **F3.5** fechou a trilha real
de emissao/reemissao do `.lic`, passou a distinguir emissao inicial de
reemissao legitima no fluxo publico e reforcou a rastreabilidade minima do
artefacto emitido em `activate` e `download`, sem mudar payload, formato
`.lic` ou criterio de validacao do daemon. A **F3.6** passa a formalizar em
`docs/01-architecture/f3-validacao-manual-evidencias.md` a leitura factual
da validabilidade actual, a matriz de cenarios obrigatorios/desejaveis, os
comandos objectivos de recolha de evidencia e a politica oficial de
"validacao suficiente" da F3, sem fingir que a execucao real em
lab/appliance ja aconteceu. A **F3.7** passa a formalizar em
`docs/01-architecture/f3-pack-operacional-validacao.md` o pack operacional
para essa execucao, com directoria por `run_id`, nomes uniformes de
ficheiros, estados `PASS` / `FAIL` / `INCONCLUSIVE` / `BLOCKED`, template
markdown por cenario e helper shell barato para exportar evidencias de
backend sem mudar o contrato do produto. A **F3.8** passa a formalizar em
`docs/01-architecture/f3-gate-fechamento-validacao.md` o gate oficial de
fechamento da F3, a matriz objectiva de decisao por cenario, a classificacao
de pendencias bloqueantes vs nao bloqueantes e o relatorio final unico de
campanha em `docs/tests/templates/f3-validation-campaign-report.md`, sem
declarar a F3 fechada sem outputs reais. A **F3.9** executou a primeira
campanha real controlada dessa trilha no `run_id`
`20260402T130015Z-deploy244`, usando o backend vivo
`https://license.systemup.inf.br` / `192.168.100.244:8445` como ambiente
observado. O resultado foi objectivo e binario: `0 PASS`, `3 FAIL`,
`1 INCONCLUSIVE` e `9 BLOCKED`, com veredito final `F3 nao pode fechar`.
Os blockers concretos observados foram: drift do deploy real face ao contrato
canónico da F2/F3 (schema live sem `admin_sessions`, `admin_audit_log` e
`admin_login_guards`, e `POST /api/activate` live a responder `403` onde a
F3.8 exige `409`), ausencia de appliance pfSense autenticavel para a metade
local da campanha e ausencia de credencial administrativa autorizada para
S04-S06/S10 sem mexer no deploy vivo. A **F3.10** converteu estes achados em
artefactos canónicos de saneamento: a matriz de pre-requisitos em
`docs/01-architecture/f3-matriz-prerequisitos-campanha.md`, a matriz de
drift operacional em
`docs/01-architecture/f3-matriz-drift-operacional.md` e o runbook sequencial
da proxima campanha em
`docs/01-architecture/f3-runbook-proxima-campanha-real.md`. A verificacao
seguinte, materializada em
`docs/01-architecture/f3-11-readiness-check.md`, nao abriu campanha: o
backend publico e o origin continuaram acessiveis, mas a F3.11 ficou
bloqueada por falta de acesso a shell/DB do deploy observado, falta de
credencial administrativa autorizada, falta de appliance pfSense
autenticavel e falta de inventario real `LIC-A` a `LIC-F`. O saneamento
minimo seguinte, registado em
`docs/01-architecture/f3-11-readiness-saneamento.md`, confirmou ainda que
`origin/main` permanece em `66e00f5a36e78056aae27df6aea0ccbd0ed78553`,
enquanto o branch local continuava `ahead 18`, e observou no live uma
divergencia adicional de politica HTTP/admin: `/api/auth/login` continua a
aceitar `Origin` externo com `Access-Control-Allow-Origin: *`, contrariando o
contrato canónico `same-origin only` da F2.3.

O checkpoint seguinte de `2026-04-14` alinhou o `license-server` live em
`192.168.100.244:/opt/layer7-license`: `admin_sessions`,
`admin_audit_log` e `admin_login_guards` passam a existir no ambiente activo,
`/api/auth/session` volta a responder no contrato stateful actual e
`/api/auth/login` volta a falhar fechado para `Origin` externo. Com isso, os
blockers administrativos do live deixam de bloquear a F3, ficando apenas o
`DR-05` do appliance como blocker real remanescente.

**Trilha activa dentro da F3:** `F3.11 alinhada no license-server live em 2026-04-14 — o ambiente activo em 192.168.100.244:/opt/layer7-license passa a expor admin_sessions, admin_audit_log e admin_login_guards, /api/auth/session volta a responder com sessao stateful e bridge Bearer, e /api/auth/login volta a falhar fechado para Origin externo com 403; DR-01 (schema admin), DR-03 (auth/admin), DR-04 (inventario) e DR-06 (same-origin) ficam resolvidos no ambiente activo; DR-02 (contrato 409 vs 403) fica coberto no branch por testes de regressao do activate e resta apenas como alinhamento de deploy quando houver publicacao; resta como unico blocker real da F3 apenas DR-05 (cenarios locais do appliance); a F3 permanece aberta ate os cenarios do appliance serem executados`

### Ordem segura das fases

| Fase | Nome | Estado | Intencao |
|------|------|--------|----------|
| F0 | Governanca documental | consolidada em `2026-04-01` | fixar canonicidade, continuidade e backlog |
| F1 | Cadeia de confianca e seguranca critica | concluida em `2026-04-01` | fechar contrato oficial de distribuicao, autenticidade de artefactos, blacklists e fallback |
| F2 | Hardening do license server | concluida em `2026-04-01` | endurecer deploy, segredos, backup e fronteiras operacionais |
| F3 | Robustez de licenciamento/activacao | aberta em `2026-04-01` | tornar activacao, revogacao e modo offline previsiveis |
| F4 | Confiabilidade package/daemon/blacklists | **F4.0 aberta** em `2026-04-24` (F3 ainda aberta; ver `f4-plano-de-implementacao.md`) | reduzir falhas operacionais e alinhar runtime com docs e gates |
| F5 | Malha de testes e regressao | preparacao (`f5-preparacao-malha.md`); fase plena apos criterio F4 | formalizar cobertura, evidencias e gates de nao regressao |
| F6 | Reorganizacao estrutural controlada | planeada | mover/normalizar estrutura apenas com mapa e rollback |
| F7 | Observabilidade e release engineering | planeada | fortalecer telemetria, verificacao de artefactos e governanca de release |

---

### Checkpoint F4 adicional (`1.8.11_12`)

Bloco tecnico em curso no branch de trabalho, ainda sem release publica:
reload seguro de blacklists passa a preservar a blacklist anterior e as
tabelas activas se a nova carga falhar; DNS/SNI de blacklists passam a casar
categoria por regra e origem do cliente antes de popular `layer7_bld_N`;
dominios pertencentes a multiplas categorias passam a preservar mascara de
categorias; GUI/package passam a preparar permissoes de
`/usr/local/etc/layer7/blacklists` para `www:wheel` e a falhar visivelmente se
`config.json` ou `_custom/*.domains` nao puderem ser gravados; cron de
auto-update passa a mapear `update_interval_hours` para campos cron coerentes;
CI ganha syntax check shell; `force_dns` deduplica pares (interface, CIDR)
entre regras de blacklist ao injectar `natrules/layer7_nat`; a lista de
interfaces efectivas para `rdr` e ordenada alfabeticamente; CIDRs validos por
regra sao unicos, ordenados e validados uma vez por regra antes do cruzamento
com interfaces. **`1.8.11_11`:** `layer7_generate_rdr_rules_snippet()` passa a
usar `layer7_pf_ifname_for_rules()` no fallback quando `get_real_interface()`
esta vazio (mesma regex, sem mudanca de comportamento; DRY). **`1.8.11_12`:**
`layer7_generate_rules()` (anti-QUIC por interface) reutiliza a mesma funcao
em vez de duplicar a regex (DRY; sem mudanca de comportamento). Gate real
permanece no builder FreeBSD e no appliance pfSense; smoke local em macOS nao
conta como evidencia de fase e fica bloqueado por defeito no script.

**Checkpoint documental-operacional adicional:** `docs/08-lab/guia-windows.md`
foi reclassificado como historico/legado, sem comandos activos. O fluxo
vigente passa a ficar explicito: macOS e apenas workspace de edicao/git/docs;
build e smoke tecnico no builder FreeBSD; validacao funcional no pfSense
appliance. O job Windows do CI foi removido para nao sugerir caminho de
validacao fora da operacao real.

---

## Proximos passos autorizados

1. Manter a **ordem segura** de fases (F0–F7) e o backlog; **nao reabrir** F1/F2
   como trabalho novo; **F6/F7** continuam fora de escopo ate os gates
   respectivos. A **F3** permanece em fecho: **DR-05** (appliance) e relatorio
   de campanha sob gate F3.8. Em **paralelo operacional e documental**,
   a **F4.0+** esta autorizada conforme
   `docs/02-roadmap/f4-plano-de-implementacao.md` (package/daemon/blacklists
   **sem** mudar o contrato de licenciamento salvo bloco aprovado).
2. Usar `docs/00-overview/f3-11-start-here.md`,
   `docs/00-overview/f3-organizacao-local-e-fecho.md` e
   `docs/01-architecture/f3-fecho-operacional-restante.md` como entrada
   curta da rodada actual: o license-server live, a auth administrativa e o
   inventario real ja estao alinhados para a F3; o proximo passo pratico
   seguro e executar somente o `DR-05` no appliance `192.168.100.254`, com
   snapshot/rollback, permissao suficiente para os cenarios mutaveis e
   evidencias por `run_id`.
3. Tratar a
   `docs/01-architecture/f3-matriz-drift-operacional.md` e o novo
   `docs/01-architecture/f3-11-drift-registry.md` como listas canónicas de
   desvios e blockers ainda abertos antes da F3.11, sem corrigir live "no
   escuro".
4. So reexecutar o readiness check da F3.11 depois de os pre-requisitos
   pendentes estarem realmente disponiveis e o
   `docs/00-overview/f3-11-document-traceability-map.md`,
   `docs/13-runbooks/f3-11-input-triage-runbook.md`,
   `docs/13-runbooks/f3-11-evidence-intake-template.md` e
   `docs/13-runbooks/f3-11-cycle-report-template.md` e
   `docs/13-runbooks/f3-11-live-access-checklist.md` poderem ser usados sem
   placeholders e sem saltar registos; sem isso, nao abrir campanha.
5. Reexecutar a campanha real da F3 em novo bloco proprio, usando
   `docs/01-architecture/f3-validacao-manual-evidencias.md` como matriz
   factual, `docs/01-architecture/f3-pack-operacional-validacao.md` como
   pack de recolha/classificacao das evidencias,
   `docs/01-architecture/f3-gate-fechamento-validacao.md` como gate oficial
   de saida, e
   `docs/01-architecture/f3-runbook-proxima-campanha-real.md` e
   `docs/13-runbooks/f3-11-live-access-checklist.md` como ordem sequencial
   minima da F3.11.
6. So declarar a F3 fechada se o relatorio final de campanha indicar
   explicitamente `F3 pode fechar`, com todos os cenarios obrigatorios da
   F3.8 em `PASS`; qualquer `FAIL`, `INCONCLUSIVE` ou `BLOCKED` obrigatorio
   mantem a F3 aberta.
7. **F4:** seguir `docs/02-roadmap/f4-plano-de-implementacao.md` — subfases
   F4.1 (package/daemon), F4.2 (blacklists), F4.3 (enforcement), um bloco de
   risco de cada vez, com `MANUAL-INSTALL` e docs de area actualizados quando
   o pacote for afectado. Nao declarar as trilhas concluidas em relatorio sem
   evidencia minima do `validacao-lab` (**10a**, **10b**, **11** — na **11**,
   `force_dns` / anchor NAT e evidencia **opcional** anti-QUIC; ver inicio e
   secção **11** do doc) e da
   `test-matrix` (**3.8**, **12.1–12.2**, **6.7**), conforme `checklist-mestre`. Para
   o port `1.8.11_12` no branch, a preparacao de release publica segue
   `docs/06-releases/release-notes-1.8.11_10-DRAFT.md` (conteudo alinhado ao
   `PORTREVISION` actual) ate existir tag e `.pkg`.
8. **F5:** tratar `docs/02-roadmap/f5-preparacao-malha.md` como roteiro de
   preparacao; a fase F5 fica “em execucao plena” depois de cumprir os
   criterios de saida da F4 e de actualizar a matriz em `docs/tests/`.
9. Usar o backlog canónico como fila unica antes de tocar em
  codigo, empacotamento, daemon, frontend ou scripts operacionais, salvo
  excepção explícita no plano de fase (F4 paralela a F3).
10. O **checklist mestre** inclui gates de teste para **F4** (paralelismo com
  a F3): **F4.1** / **BG-009** (`validacao-lab` sec. **10a**, `test-matrix`
  **3.8**), **F4.2** / **BG-010** (sec. **10b**, **12.1–12.2**), **F4.3** /
  **BG-011** (sec. **11** com `force_dns` e, onde aplicável, anti-QUIC opcional;
  **6.7**) antes de declarar fechadas as trilhas
  respectivas em relatorio.
11. **Isencoes VIP / UX GUI:** seguir
  [`docs/02-roadmap/plano-isencao-vip-e-ux-gui.md`](docs/02-roadmap/plano-isencao-vip-e-ux-gui.md)
  em ordem A→E; gates **BG-064**/`_48`, **BG-065**/`_49`, **BG-066**/`_50` no
  checklist mestre; modelo conceptual em
  [`docs/00-overview/modelo-conceptual-gui.md`](docs/00-overview/modelo-conceptual-gui.md)
  como gate de revisão para mudanças de GUI.
12. **Lista VIP global:** Blocos A–E concluídos (`2026-07-31`); releases `_57`–`_61`;
  roteiro lab sec. **20** (`validacao-lab.md`; pacote lab **`>= 1.8.11_61`**
  recomendado; gate **20.4** `filter_configure`); **execução física no appliance**
  pendente validação humana — **BG-071**/`_57`, **BG-072**/`_58`, **BG-073**/`_59`–`_61`
  (**ADR-0020**); gates no checklist mestre secção BG-071/072/073; SSOT
  `vip-isentos` inalterado; NO-GO produção `_24` inalterado.

---

## Riscos abertos

- A cadeia actual entre repositório, builder com ficheiros sensiveis locais,
  chave publica embutida e artefacto publicado ainda precisa de formalizacao.
- O fluxo oficial ja saiu de `main`, mas referencias historicas a
  `raw.githubusercontent.com/.../main` ainda coexistem em material legado e
  precisam continuar classificadas como nao normativas.
- O canal oficial passa a publicar `.pkg`, `.pkg.sha256`, `install.sh`,
  `uninstall.sh`, `release-manifest.v1.txt`, assinatura destacada e public key
  de verificacao; a F1.4 integrou esta validacao no `install.sh` publicado,
  mas isso continua a depender de o signer carimbar o asset versionado com a
  public key oficial e o fingerprint esperado.
- A trilha de blacklists deixou o feed HTTP directo, mas a disponibilidade
  ainda depende de publicar o manifesto assinado na origem oficial
  `downloads.systemup.inf.br` e no mirror controlado em GitHub Releases.
- GitHub, builder e origem UT1 continuam como dependencias externas fortes;
  a F1 reduziu o risco do lado do consumidor com manifesto assinado,
  mirror/cache/LKG e install fail-closed, mas nao elimina a necessidade
  operacional de publicar snapshots e releases aprovadas.
- A F2.1 fechou a ambiguidade de publicacao do license server: o canal
  publico oficial passa a ser `https://license.systemup.inf.br`, o origin
  `8445` fica preso ao loopback por defeito e o Nginx interno passa a
  aceitar apenas o host oficial e troubleshooting local controlado.
- A F2.1 passa a depender operacionalmente de certificado valido na borda,
  redirect `HTTP -> HTTPS`, allowlist/firewall coerente para o origin
  `8445` e ausencia de exposicao publica directa desse origin.
- A F2.2 passa a depender operacionalmente de o canal administrativo ficar
  sempre atras de HTTPS/TLS real e de o cookie `Secure` nao ser degradado por
  acessos directos ao origin privado.
- A F2.3 passa a depender operacionalmente de o origin oficial
  `https://license.systemup.inf.br` continuar a ser o unico origin de browser
  permitido em producao, de os limites de login (`10/10 min` por IP e
  `5/10 min` por `email + IP`) permanecerem calibrados para o uso real e de
  os operadores consultarem `admin_audit_log`/`admin_login_guards` em
  incidente em vez de alargarem a superficie administrativa.
- A F2.5 fechou o ownership minimo de segredos, bootstrap administrativo e
  backup/restore do banco, mas a rotacao formal da chave Ed25519 em incidente
  e a automacao ampliada de retention/observabilidade continuam fora da F2.
- A F3.3 passou a centralizar o estado efectivo da licenca no backend, mas o
  modelo continua deliberadamente hibrido: `licenses.status = 'expired'`
  continua opcional/legado e a expiracao efectiva continua a ser derivada
  tambem por `expiry < CURRENT_DATE`.
- O daemon aceita `.lic` expirado em grace local de `14` dias, enquanto a
  activacao online recusa imediatamente licencas expiradas; a diferenca esta
  agora formalizada, mas ainda exige validacao manual/appliance na F3.6.
- A revogacao actual corta activacao e download no servidor, mas **nao**
  invalida imediatamente um `.lic` ja emitido que continue em uso offline; o
  risco operacional fica explicito e bloqueia qualquer rebind administrativo
  precoce.
- A F3.4 bloqueia a mudanca de `customer_id` em licenca bindada no CRUD
  normal, mas reemissao legitima da mesma licenca continua a poder coexistir
  com um `.lic` antigo ainda valido offline ate data/grace.
- A F3.5 melhora a trilha auditada do artefacto emitido, mas o sistema ainda
  nao tem contador/versionamento consumido pelo daemon nem enforcement de
  "artefacto mais recente unico"; em 2026-04-14, a metadata auditada de
  emissao/reemissao passou a ter regressao dedicada para `flow`,
  `emission_kind`, binding, customer/features e hashes SHA-256 do artefacto.
- A F3.6 formaliza a matriz de evidencias, a F3.7 operacionaliza essa
  recolha com pack e helper shell baratos, e a F3.8 formaliza o gate de
  fechamento e o relatorio final de campanha; ainda assim, a robustez da F3
  continua dependente de executar em lab/appliance os cenarios obrigatorios
  de grace, revogacao com `.lic` antigo, coexistencia de artefactos e drift
  real de fingerprint sem abrir escopo tecnico novo.
- A F3.9 revelou drifts reais no ambiente observado, mas o checkpoint de
  `2026-04-14` reclassificou o estado corrente: `DR-01` schema/admin,
  `DR-03` auth/admin, `DR-04` inventario e `DR-06` same-origin foram
  saneados no `license-server` live activo; `DR-02` ficou como divergencia
  cosmetica de codigo HTTP (`403` vs `409`) sem bloquear a F3, e o branch
  actual passou a cobrir o contrato `409` do `activate` com testes de
  regressao.
- O contrato de estado efectivo do license-server (`active`, `expired`,
  `revoked`, expiracao por data, precedencia de revogacao, binding e
  predicados SQL) passou a ter regressao dedicada em 2026-04-14.
- O payload publico de activacao (`key` + `hardware_id`) passou a ter
  regressao dedicada em 2026-04-14, cobrindo normalizacao, campos
  inesperados e rejeicoes `400` antes da transacao de activacao.
- O guardrail de update administrativo que bloqueia mudanca de `customer_id`
  em licenca activada/bindada passou a ter regressao dedicada em
  2026-04-14, preservando a proteccao contra transferencia silenciosa de
  ownership.
- O unico blocker real remanescente da F3 e `DR-05`: cenarios locais do
  appliance `192.168.100.254` que ainda exigem permissao suficiente para
  reescrever `/usr/local/etc/layer7.lic`, controlar o daemon, executar
  offline/online, grace/relogio, snapshot/restore e NIC/UUID/clone com
  evidencias por `run_id`.
- Em `2026-04-14`, o run read-only
  `20260414T123526Z-appliance254-permissions` confirmou que `codex` nao tem
  escrita no `.lic`; o rc.d passou a aplicar `chmod 0644` ao pidfile apos
  arranque (F4.1) para `service layer7d status` nao falhar por falta de
  leitura do PID; o daemon continua auditavel por `pgrep` e stats JSON; isto
  melhora a evidencia de DR-05, mas nao fecha os cenarios mutaveis.
- Os pacotes antigos de cinco insumos da F3.11 permanecem como memoria
  documental-operacional, mas nao sao mais o gate corrente para continuar a
  F3. O caminho actual e consultar `f3-11-start-here.md`,
  `f3-organizacao-local-e-fecho.md`, `f3-fecho-operacional-restante.md`,
  o scorecard, o registro mestre e o drift registry, e executar apenas o
  bloco `DR-05`.
- `DR-07` proveniencia exacta do deploy continua aberto como governanca
  operacional/F7: nao autoriza inferir live = local = remoto, mas tambem nao
  bloqueia os cenarios de licenciamento do appliance na F3.
- Apos cada bloco de alteracoes, confirmar `git status` e `main` alinhado a
  `origin/main` antes de assumir o estado alheio; commits/push em bloco unico
  (sem misturar saneamento documental com mudanca funcional nao relacionada).
- Nao existe ainda trilha dedicada para transferencia entre clientes,
  desrevogacao ou rebind seguro com governanca explicita.
- O fingerprint continua dependente de `SHA256(kern.hostuuid + ":" + primeira
  MAC Ethernet nao-loopback)`; mudanca de NIC, VM, reinstall ou ordem de
  interfaces ainda pode exigir validacao dedicada na F3.
- O `docs/` tem areas canónicas e areas apenas suplementares/historicas;
  sem ler a classificacao, um agente pode seguir um documento antigo.
- Existem documentos antigos ainda a mencionar `.txz`, `v0.x` e estados
  pre-V1; isso esta agora classificado, mas a limpeza fisica fica para a F6.
- O tutorial longo e alguns guias de lab continuam uteis, mas nao devem ser
  tratados como SSOT para instalacao ou governanca.
- O builder possui alteracoes locais de producao que **nao podem ser
  commitadas**, exigindo disciplina operacional.

---

## Restricoes

- foco em **pfSense CE**;
- pacote **proprietario** com EULA Systemup;
- distribuicao publica por **`.pkg` via GitHub Releases**;
- sem software pago obrigatorio;
- V1 sem MITM TLS universal;
- V1 sem console central multi-firewall;
- V1 sem analytics pesado;
- sem reorganizacao fisica antes da F6;
- sem alterar codigo-fonte, package, daemon, license server, frontend,
  scripts operacionais ou logica funcional durante a F0 e durante o
  planeamento documental da F1.

---

## Regras de nao regressao

1. Nenhuma fase tecnica pode alterar mais de um subsistema critico ao mesmo
   tempo sem justificacao e rollback explicitos.
2. Nenhuma alteracao funcional entra sem declarar:
   - objectivo;
   - impacto;
   - risco;
   - teste;
   - rollback.
3. `docs/10-license-server/MANUAL-INSTALL.md` e a referencia canónica para
   instalacao, upgrade, reinstall e desinstalacao do pacote.
4. `docs/changelog/CHANGELOG.md` e a linha temporal oficial de releases e
   correccoes; o `CORTEX.md` nao deve voltar a carregar changelog detalhado.
5. Nenhum agente deve assumir que documentos da raiz ainda sao canónicos so
   porque foram a base original do projecto.
6. Antes da F6, conflitos estruturais resolvem-se por **classificacao e
   equivalencia**, nao por mover/apagar ficheiros.

---

## Hierarquia documental

### Documentos canónicos de governanca

- [`docs/README.md`](docs/README.md)
- [`docs/02-roadmap/roadmap.md`](docs/02-roadmap/roadmap.md)
- [`docs/02-roadmap/backlog.md`](docs/02-roadmap/backlog.md)
- [`docs/02-roadmap/checklist-mestre.md`](docs/02-roadmap/checklist-mestre.md)
- [`docs/02-roadmap/f1-plano-de-implementacao.md`](docs/02-roadmap/f1-plano-de-implementacao.md)
- [`docs/02-roadmap/f4-plano-de-implementacao.md`](docs/02-roadmap/f4-plano-de-implementacao.md)
- [`docs/02-roadmap/f5-preparacao-malha.md`](docs/02-roadmap/f5-preparacao-malha.md)
- [`docs/00-overview/document-classification.md`](docs/00-overview/document-classification.md)
- [`docs/00-overview/document-equivalence-map.md`](docs/00-overview/document-equivalence-map.md)
- [`docs/03-adr/README.md`](docs/03-adr/README.md)

### Documentos canónicos por area

- Produto/escopo: [`docs/00-overview/product-charter.md`](docs/00-overview/product-charter.md)
- Modelo conceptual GUI: [`docs/00-overview/modelo-conceptual-gui.md`](docs/00-overview/modelo-conceptual-gui.md)
- Plano isencoes VIP/UX: [`docs/02-roadmap/plano-isencao-vip-e-ux-gui.md`](docs/02-roadmap/plano-isencao-vip-e-ux-gui.md)
- Arquitectura alvo: [`docs/01-architecture/target-architecture.md`](docs/01-architecture/target-architecture.md)
- Arquitectura de confianca F1: [`docs/01-architecture/f1-arquitetura-de-confianca.md`](docs/01-architecture/f1-arquitetura-de-confianca.md)
- Arquitectura de seguranca F2 do license server: [`docs/01-architecture/f2-arquitetura-license-server.md`](docs/01-architecture/f2-arquitetura-license-server.md)
- Plano F2: [`docs/02-roadmap/f2-plano-de-implementacao.md`](docs/02-roadmap/f2-plano-de-implementacao.md)
- Plano F4: [`docs/02-roadmap/f4-plano-de-implementacao.md`](docs/02-roadmap/f4-plano-de-implementacao.md)
- Preparacao F5: [`docs/02-roadmap/f5-preparacao-malha.md`](docs/02-roadmap/f5-preparacao-malha.md)
- Instalacao/operacao do pacote: [`docs/10-license-server/MANUAL-INSTALL.md`](docs/10-license-server/MANUAL-INSTALL.md)
- Changelog: [`docs/changelog/CHANGELOG.md`](docs/changelog/CHANGELOG.md)
- Core tecnico: [`docs/core/README.md`](docs/core/README.md)
- Testes: [`docs/tests/README.md`](docs/tests/README.md)

### Legado preservado

- Os documentos `00-` a `16-` na raiz continuam preservados para contexto,
  rastreabilidade e compatibilidade de links, mas deixaram de ser a fonte
  primaria de decisao.

---

## Plano mestre de fecho / consolidacao (2026-08-04)

Trilha operacional unica para fechar pontas soltas (software + documentacao +
versionamento) ate o produto estar pronto para utilizacao com enforce:

- **Plano (inicio/meio/fim, gates, multitarefa, R1–R12):**
  [`docs/02-roadmap/plano-fecho-producao-e-consolidacao.md`](docs/02-roadmap/plano-fecho-producao-e-consolidacao.md)
- **Arranque de chat (prompt para colar):**
  [`docs/00-overview/START-HERE-fecho-producao.md`](docs/00-overview/START-HERE-fecho-producao.md)

**Modo preferido:** coordenador + workers em P0/P1/H; agente unico em ondas
com appliance (A–G) e GO/release (F, I–J). Ver grafo de dependencias no plano
(sec. 3.3).

**Nao** iniciar F6 (mover pastas em `docs/`) antes da Onda H do plano.
**Onda F (GO enforce):** **PASS** (`2026-08-05`) — promovido a **`1.9.0`** (fecho plano)
(ADR-0022 aceite; CE fisico pendente).
**Congelamento P0.2:** sem novo polish GUI (i18n, redesign, icones) ate Onda A
PASS — apenas fixes bloqueantes descobertos em gate.

```text
PLANO FECHO/CONSOLIDAÇÃO — progresso
- Passo actual: **PLANO FECHADO** — manutenção via backlog normal
- Onda: H **PASS** (F6 H1–H4); I **PASS**; J **PASS** (R1–R12 com excepções ADR-0022/0023)
- Candidato lab: 1.9.0
- Produção enforce: 1.9.0
- Canal latest: 1.9.0 (alinhado)
- F5 mínima: **PASS**
- F6: **FECHADA** (H5 raiz legado diferido)
- F7/BG-028: **Fase 0** (ADR-0023); BG-017 checklist PASS
- R1-R12: **11/12 verdes + 2 excepções assinadas** (R7, CE)
- CE: LIMITAÇÃO (ADR-0022 aceite)
- Evidência Onda J: `docs/tests/evidence/20260805T012500Z-ondaJ-r1-r12-audit/`
- Produto pronto enforce: **SIM** (com ressalvas documentadas)
- Modo: manutenção contínua
```

---

## Ordem de leitura obrigatoria

### Para qualquer novo chat ou agente

1. `CORTEX.md`
2. [`docs/README.md`](docs/README.md)
3. [`docs/02-roadmap/roadmap.md`](docs/02-roadmap/roadmap.md)
4. [`docs/02-roadmap/backlog.md`](docs/02-roadmap/backlog.md)
5. [`docs/02-roadmap/checklist-mestre.md`](docs/02-roadmap/checklist-mestre.md)
6. [`docs/00-overview/document-classification.md`](docs/00-overview/document-classification.md)
7. [`docs/00-overview/document-equivalence-map.md`](docs/00-overview/document-equivalence-map.md)
8. [`docs/03-adr/README.md`](docs/03-adr/README.md)
9. [`docs/01-architecture/f1-arquitetura-de-confianca.md`](docs/01-architecture/f1-arquitetura-de-confianca.md)
10. [`docs/02-roadmap/f1-plano-de-implementacao.md`](docs/02-roadmap/f1-plano-de-implementacao.md)

### Para a trilha de fecho / consolidacao

1. `CORTEX.md` (inclui passo actual do plano mestre)
2. [`docs/02-roadmap/plano-fecho-producao-e-consolidacao.md`](docs/02-roadmap/plano-fecho-producao-e-consolidacao.md)
3. [`docs/00-overview/START-HERE-fecho-producao.md`](docs/00-overview/START-HERE-fecho-producao.md)
4. SSOT da onda em causa (gates, validacao-lab, F3 start-here, etc.)

### Para trabalho tecnico numa area especifica

- Instalacao, upgrade, desinstalacao, rollback operacional:
  [`docs/10-license-server/MANUAL-INSTALL.md`](docs/10-license-server/MANUAL-INSTALL.md)
- Arquitectura/config/policy:
  [`docs/01-architecture/target-architecture.md`](docs/01-architecture/target-architecture.md)
  e [`docs/core/README.md`](docs/core/README.md)
- License server e licenciamento:
  [`docs/10-license-server/PLANO-LICENSE-SERVER.md`](docs/10-license-server/PLANO-LICENSE-SERVER.md)
  e [`docs/10-license-server/MANUAL-USO-LICENCAS.md`](docs/10-license-server/MANUAL-USO-LICENCAS.md)
  e [`docs/01-architecture/f3-validacao-manual-evidencias.md`](docs/01-architecture/f3-validacao-manual-evidencias.md)
- Distribuicao, builder, blacklists e fallback seguro:
  [`docs/01-architecture/f1-arquitetura-de-confianca.md`](docs/01-architecture/f1-arquitetura-de-confianca.md),
  [`docs/02-roadmap/f1-plano-de-implementacao.md`](docs/02-roadmap/f1-plano-de-implementacao.md),
  [`docs/03-adr/README.md`](docs/03-adr/README.md)
- Blacklists UT1:
  [`docs/11-blacklists/PLANO-BLACKLISTS-UT1.md`](docs/11-blacklists/PLANO-BLACKLISTS-UT1.md),
  [`docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md`](docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md),
  [`docs/11-blacklists/REGRAS-QUALIDADE.md`](docs/11-blacklists/REGRAS-QUALIDADE.md)
- Testes e validacao:
  [`docs/tests/README.md`](docs/tests/README.md)
  e [`docs/04-package/validacao-lab.md`](docs/04-package/validacao-lab.md)

---

## Mapa rapido do repositorio

- `docs/` -> centro documental canónico progressivo
- `src/` -> codigo-fonte do daemon, PoC e modulos de runtime
- `package/pfSense-pkg-layer7/` -> port e ficheiros do pacote pfSense
- `webgui/` -> documentacao curta da GUI e referencias auxiliares
- `license-server/` -> backend, frontend e nginx do servidor de licencas
- `scripts/` -> build, package, lab, diagnostico e release
- `tests/` -> material de teste e fixtures
- `samples/` -> amostras de configuracao e politicas
- raiz `00-16` -> legado documental preservado

---

## Componentes principais

1. **Pacote pfSense**
   - entrega o pacote instalavel, metadados, hooks e GUI integrada
2. **Daemon `layer7d`**
   - carrega configuracao, classifica trafego, decide politicas e alimenta PF
3. **Engine de classificacao**
   - nDPI como decisao congelada de V1
4. **Policy engine**
   - regras por interface, IP/CIDR, grupo, horario, host, app e categoria
5. **Enforcement PF**
   - bloqueio por origem/destino, forcing DNS, tabelas de excepcoes e blacklist
6. **Servidor de licencas**
   - stack separada para activacao, gestao administrativa e emissao de `.lic`
7. **Sistema documental**
   - governanca, roadmap, backlog, ADRs, runbooks, changelog e guias por area

---

## Decisoes congeladas

- foco exclusivo em **pfSense CE**
- produto comercial/proprietario da **Systemup**
- artefacto publico de distribuicao: **`.pkg`**
- activacao/licenciamento via ficheiro `.lic` assinado com **Ed25519**
- nDPI continua como engine de classificacao
- sem MITM universal na V1
- sem console central nesta etapa
- `docs/` e o centro canónico progressivo
- a raiz actual e **legado importante**, nao lixo
- reorganizacao fisica so na **F6**

---

## Politica de continuidade entre chats

Cada novo chat deve conseguir responder, sem ambiguidade:

1. Em que fase o projecto esta.
2. O que e canónico e o que e historico.
3. Qual e o ultimo estado seguro conhecido.
4. O que pode e o que nao pode ser mexido agora.
5. Qual e o proximo passo autorizado.

Para isso:

- o `CORTEX.md` deve ser lido antes de qualquer accao;
- o estado de fase deve ser mantido alinhado ao roadmap canónico;
- backlog, ADR index e checklist mestre devem ser actualizados sempre que
  houver mudanca real de prioridade, decisao ou gate;
- no fim de cada bloco relevante, registrar um checkpoint seguro.

### Chat longo no Cursor (handoff)

Quando a conversa se tornar **demasiado longa** (muitas trocas, contexto
difícil de seguir, ou limites do produto), mudar para **um chat novo** em vez
de empurrar todo o historico. O procedimento canónico e o **prompt modelo**
estao em
[`docs/00-overview/handoff-chat-novo.md`](docs/00-overview/handoff-chat-novo.md).
O agente pode sugerir esse movimento; o utilizador pode exigi-lo a qualquer
momento. O estado oficial do projecto continua sempre no **Git** e no
**CORTEX**, nao na memoria do chat. A relacao **CORTEX** / handoff / prompts
historicos de continuidade em `docs/07-prompts` esta resolvida no
[`docs/00-overview/document-equivalence-map.md`](docs/00-overview/document-equivalence-map.md)
(secção *Sobreposicoes internas relevantes em `docs/`* e ponto 4 da lista
*Conflitos documentais formais registados na F0*).

---

## Politica de documentacao viva

| Quando algo mudar | Documentos obrigatorios |
|-------------------|-------------------------|
| fase actual, gate ou sequencia aprovada | `CORTEX.md`, `docs/02-roadmap/roadmap.md`, `docs/02-roadmap/backlog.md`, `docs/02-roadmap/checklist-mestre.md` |
| decisao arquitectural, de seguranca ou de distribuicao | `docs/03-adr/README.md`, ADR novo/actualizado, `CORTEX.md` |
| cadeia de confianca, distribuicao, blacklists ou fallback seguro | `docs/01-architecture/f1-arquitetura-de-confianca.md`, `docs/02-roadmap/f1-plano-de-implementacao.md`, backlog, ADR index |
| instalacao, upgrade, uninstall, rollback, caminhos, comandos ou versao publicada | `docs/10-license-server/MANUAL-INSTALL.md` (addendum, Links da versao actual **e todos os comandos operacionais** — ver Nota de manutencao no manual e Regra especial no `AGENTS.md`), runbooks afectados, changelog, release docs |
| mudanca funcional relevante | changelog, docs da area, `CORTEX.md`, backlog/status da fase |
| reorganizacao estrutural | mapa de equivalencia, classificacao documental, roadmap/checklist da F6 |
| release publicada | changelog, release notes, `MANUAL-INSTALL.md`, `CORTEX.md` |

---

## Bloco fixo de checkpoint

```text
CHECKPOINT CANONICO
- Data base: 2026-08-05
- Produto: Layer7 para pfSense CE — **PRONTO PARA ENFORCE** (excepções ADR-0022 CE, ADR-0023 BG-028 fase 0)
- Canal publico latest: 1.9.0
- Producao enforce: 1.9.0 (fecho plano; rollback _69)
- Plano fecho/consolidacao: **FECHADO** (Ondas A–J)
- F6: H1–H4 PASS; H5 raiz legado diferido
- F7: RELEASE-CHECKLIST.md + ADR-0023
- Proximo trabalho: backlog normal (BG-028 fase 1 quando chaves humanas; VM CE opcional; H5 se aprovado)
- Fonte canonica instalacao: docs/10-license-server/MANUAL-INSTALL.md
- Fonte canonica release: docs/06-releases/RELEASE-CHECKLIST.md
```

---

## Ultimo status seguro conhecido

### Tecnico

- A referencia de **instalacao publica** e o pacote **`1.9.0`**
  publicado em `pablomichelin/Layer7` tag `v1.9.0`
  (`SHA256=cde469a105db0b9f07dee1bf65838494ce209a1e86912d2169b0f124d631569f`).
  Fecho plano mestre (`2026-08-05`); equivalente funcional a `1.8.11_69`.
  Rollback imediato: `v1.8.11_69`.
- A trilha **F1.3 de blacklists** passa a ter primeira snapshot UT1 publica
  assinada em `pablomichelin/Layer7` rolling tag `blacklists-ut1-current`
  (`snapshot_id=ut1-2026-04-25`,
  `SHA256=4191e2ebdc13e3c87d777103528bab4fda6b273bc40c62a2c39cb820ad493d36`).
  A chave publica embutida foi rotacionada em `1.8.11_13` (fingerprint
  `6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`) e
  **continua a mesma em `1.8.11_14` e superiores ate `1.8.11_16`** (hotfix
  GUI / F4.1 nao tocaram na
  trilha F1.3); a privada correspondente fica em custodia humana, fora
  do builder e fora do repositorio. Pacotes `<= 1.8.11_12` recusam este
  manifesto por fingerprint mismatch (fail-closed F1.4 — comportamento
  correcto).
- **Convencao operacional do GitHub Releases (2026-04-24):** releases que
  nao sao versoes do pacote (ex.: `blacklists-ut1-current`, futuras
  `signatures-*`) sao **publicadas como `prerelease`** em
  `pablomichelin/Layer7`. A API `repos/.../releases/latest` (consumida pelo
  GUI Layer7 — `layer7_settings.php > check_update`) ignora prereleases,
  pelo que o `latest` continua a ser sempre a ultima versao do pacote.
  Sem esta convencao o GUI mostra erradamente *"Release encontrado mas sem
  artefacto .pkg."*. Detalhes em
  `docs/changelog/CHANGELOG.md` (Unreleased / Operational),
  `docs/10-license-server/MANUAL-INSTALL.md` §11b.1 e backlog `BG-030`.
- **Hotfix `1.8.11_14` (2026-04-24):** corrige loop do botao **"Verificar
  actualizacao"** que existia em `1.8.11_13`. O `version.str` passa a
  conter `${PKGVERSION}` (= `PORTVERSION_PORTREVISION`), pelo que
  `layer7d -V` agora imprime a versao real do pacote. O updater do GUI
  passa a confiar em `pkg query %v pfSense-pkg-layer7` (fonte canonica
  do pkg manager pfSense) e implementa defesa em profundidade `BG-030`
  (ignora `tag_name` que nao case com `/^v?\d+\.\d+/`). **Sem alteracao
  de logica de bloqueio, sem rotacao de chave de blacklists, sem
  republicacao de snapshot UT1.** `BG-030` marcado como **Concluido**.
- O produto ja contem enforcement PF, forcing DNS, blacklists UT1,
  relatorios locais e licenciamento funcional.
- Na linha 1.8.3+ conhecida: bloqueio QUIC por interface na GUI; retrocompat
  com `block_quic:true` (legado global).

### Documental

- A canonicidade passou a estar explicitamente declarada.
- O projecto ja nao depende da raiz como fonte principal de governanca.
- O backlog, o roadmap, o checklist mestre e o mapa de equivalencia passam a
  servir de ponte segura entre chats e entre fases.
- A F1.2 passou a ter manifesto versionado, assinatura destacada e cadeia
  builder -> signer -> publish documentada e executavel.
- A F1.3 passou a ter manifesto dedicado de blacklists, public key propria,
  origem oficial `downloads.systemup.inf.br`, mirror controlado em GitHub
  Releases, cache local em `.cache`, estado activo em `.state` e
  last-known-good em `.last-known-good`.

### Operacional

- A F1.4 (fallback/fail-closed da distribuicao) ja esta concluida; a
  intervencao corrente prioriza o **fecho da F3** (validacao com evidencia,
  sobretudo `DR-05` no appliance) antes de abrir a F4. Reorganizacao de
  arvore de ficheiros continua proibida antes da F6.
