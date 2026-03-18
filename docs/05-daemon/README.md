# Daemon `layer7d`

## Objetivo (futuro, pós-validação do pacote)

Serviço gerido pelo pfSense com config real, reload, counters.

## Estado no repositório

- **Fonte:** `src/layer7d/main.c`.
- **Comportamento actual (código):** syslog `daemon_start`; `stat()` no path de config (omissão `/usr/local/etc/layer7.json` ou `-c`); loop; `SIGHUP` apenas regista que reload não está implementado; `SIGTERM`/`SIGINT` terminam com `daemon_stop`.
- **Não há** parser JSON nem integração com `config.xml` do pfSense.

## Build

- Integrado ao port: `make package` no diretório do port (em host FreeBSD).
- Manual: `cc -Wall -Wextra -O2 -o layer7d main.c` a partir de `src/layer7d/`.

## Validação

O daemon só pode ser considerado **validado** depois de **`pkg add`** no pfSense e execução conforme [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md). Até lá: **implementado no código, não validado em lab**.
