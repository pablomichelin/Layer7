# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

## [Unreleased]

### Added
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
- Documentação alinhada: nada de build/install/GUI marcado como validado sem evidência de lab.
- Port compila `layer7d` em C (`PORTVERSION` conforme Makefile).

### Fixed (código)
- `rc.d/layer7d` usa `daemon(8)` para arranque em background.

## [0.0.1] - 2026-03-17

### Added
- Documentação-mestre na raiz (`00-`…`16-`, `AGENTS.md`, `CORTEX.md`) e primeiro push ao GitHub.
