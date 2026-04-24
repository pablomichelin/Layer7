# Daemon `layer7d`

## Objetivo (futuro, pós-validação do pacote)

Serviço gerido pelo pfSense com config completa, counters, nDPI.

## Estado no repositório

- **Fontes:** `main.c`, `config_parse.c`, **`policy.c`**, **`enforce.c`** (`src/layer7d/`).
- **Parser/motor:** globais; **`exceptions[]`** (host, CIDR); **`policies[]`** (`ndpi_app`, `ndpi_category`). **Runtime:** loop ainda sem nDPI — só config/reload/idle.
- **`-t`:** globais, exceções, políticas, dry-run com IP origem + app + categoria; **`pfctl_suggest`** em enforce+block/tag.
- **Enforcement:** [`pf-enforcement.md`](pf-enforcement.md) — **`layer7_on_classified_flow(src_ip, ndpi_app, ndpi_cat)`** em `main.c` (decisão + `layer7_pf_enforce_decision` + contadores); hoje só **CLI `-e`** / **`-e -n`**; o loop nDPI chamará a mesma função. O mesmo ficheiro inclui **DNS forcado** (`force_dns`, anchor NAT `natrules/layer7_nat`, F4.3 / **BG-011**).
- **Sinais:** **SIGHUP** reload; **SIGUSR1** stats. **Syslog remoto:** UDP para `syslog_remote_host` (ver Settings / `docs/10-logging`).
- **Daemon:** syslog no arranque; aviso **degraded** se ficheiro existe mas policies/exceptions falham no parse; **SIGHUP** re-lê ficheiro; **~1 h** `periodic_state` (info) quando não idle; SIGTERM/SIGINT — `daemon_stop`.

## Pidfile (`/var/run/layer7d.pid`)

Ficheiro com **uma linha**, valor **numérico** (PID do processo `layer7d`).
O **rc.d** regista o PID e (em releases recentes) ajusta permissões para
leitura coerente com `service layer7d status`.

**Consumidores** (leitura defensiva alinhada na F4 / helpers F3): rc.d
`usr/local/etc/rc.d/layer7d` (função `layer7d_pid_from_file` em
`start`/`stop`/`status`/`reload`); PHP em `layer7.inc`
(`layer7_daemon_pid_from_file`: primeira linha, trim, só dígitos) e páginas
(Status, Diagnostics) / `kill -0`; shell em `update-blacklists.sh` (`send_sighup`),
`layer7-stats-collect.sh` (cron de relatórios) e, para evidências de campanha,
`scripts/license-validation/` (p.ex. `export-appliance-evidence.sh`). Não
tratar o pidfile como texto livre: evitar espaços ou caracteres não numéricos;
ver changelog e `f4-plano-de-implementacao.md` (F4.1 / F4.2).

## Build

- **Port (ordem canónica na raiz do clone):** `sh scripts/package/check-port-files.sh`, `sh scripts/package/smoke-layer7d.sh` (precisa de `cc`), depois `make package` em `package/pfSense-pkg-layer7/` (quatro `.c` + `-I` para `src/common`); detalhe em [`../08-lab/builder-freebsd.md`](../08-lab/builder-freebsd.md).
- Manual sem port: compilar `cc … main.c config_parse.c policy.c enforce.c` em `src/layer7d/`.

## Logging

Formato e níveis: [`../10-logging/README.md`](../10-logging/README.md).

## Validação

Pacote no pfSense: [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md). Teste só de código: smoke ou `layer7d -t`.
