# Daemon `layer7d`

## Objetivo (futuro, pós-validação do pacote)

Serviço gerido pelo pfSense com config completa, counters, nDPI.

## Estado no repositório

- **Fontes:** `main.c`, `config_parse.c`, **`policy.c`**, **`enforce.c`** (`src/layer7d/`).
- **Parser/motor:** globais; **`exceptions[]`** (host, CIDR); **`policies[]`** (`ndpi_app`, `ndpi_category`). **Runtime:** loop ainda sem nDPI — só config/reload/idle.
- **`-t`:** globais, exceções, políticas, dry-run com IP origem + app + categoria; **`pfctl_suggest`** em enforce+block/tag.
- **Enforcement:** [`pf-enforcement.md`](pf-enforcement.md) — **`layer7_on_classified_flow(src_ip, ndpi_app, ndpi_cat)`** em `main.c` (decisão + `layer7_pf_enforce_decision` + contadores); hoje só **CLI `-e`** / **`-e -n`**; o loop nDPI chamará a mesma função.
- **Sinais:** **SIGHUP** reload; **SIGUSR1** stats. **Syslog remoto:** UDP para `syslog_remote_host` (ver Settings / `docs/10-logging`).
- **Daemon:** syslog no arranque; aviso **degraded** se ficheiro existe mas policies/exceptions falham no parse; **SIGHUP** re-lê ficheiro; **~1 h** `periodic_state` (info) quando não idle; SIGTERM/SIGINT — `daemon_stop`.

## Build

- Port: `package/pfSense-pkg-layer7` → `make package` (quatro `.c` + `-I` para `src/common`).
- Smoke: `sh scripts/package/smoke-layer7d.sh` (precisa de `cc`).
- Manual: `cc … main.c config_parse.c policy.c enforce.c` em `src/layer7d/`.

## Logging

Formato e níveis: [`../10-logging/README.md`](../10-logging/README.md).

## Validação

Pacote no pfSense: [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md). Teste só de código: smoke ou `layer7d -t`.
