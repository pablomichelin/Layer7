# Logging Layer7 (V1)

## Objetivo

Documentar o **formato e destino** dos logs do `layer7d` e a relação com `log_level` e (futuro) syslog remoto.

## Destino atual

- **Facilidade:** syslog **LOG_DAEMON**
- **Ident:** `layer7d` (com `LOG_PID` no arranque)
- **Onde aparece:** conforme a configuração do syslog do sistema (ex.: pfSense → `/var/log/system.log`, `clog` para leitura). Não há ficheiro dedicado do pacote.

## Níveis (log_level)

O daemon filtra mensagens conforme `layer7.log_level` no JSON:

| Valor JSON | Nível interno | O que passa |
|------------|----------------|-------------|
| `error`    | 0 | Apenas LOG_ERR (erros graves) |
| `warn`     | 1 | error + LOG_WARNING |
| `info`     | 2 (default) | error + warn + LOG_NOTICE + LOG_INFO |
| `debug`    | 3 | Tudo acima + LOG_DEBUG |

## Mensagens atuais (texto livre)

Exemplos do que o daemon envia hoje (formato livre, uma linha por evento):

| Situação | Exemplo de linha |
|----------|-------------------|
| Arranque | `daemon_start version=…` (versão do binário) |
| Paragem | `daemon_stop` |
| Config presente | `config file present: /usr/local/etc/layer7.json (1234 bytes)` |
| Config ausente | `config absent: ... — copy layer7.json.sample` |
| Reload OK | `config: policies=N exceptions=M enforce_cfg=K reload#X (...)` |
| Parse falhou | `policies[] parse failed (...)` / `exceptions[] parse failed (...)` |
| Degraded | `degraded: políticas/exceções inválidas — snapshot não carregado (...)` |
| SIGHUP | `SIGHUP: reload config` / `SIGHUP: missing ...` |
| SIGUSR1 | `SIGUSR1 stats: ver=… reload_ok=... snapshot_fail=...` (resto igual) |
| Idle | `layer7.enabled=false — still idle` / `periodic_state: ...` (a cada ~1 h se ativo) |
| PF falhou | `pfctl add failed table=TAB ip=IP` |

Não há formato estruturado (JSON/key=value) nas linhas atuais; são mensagens legíveis para operador.

## Syslog remoto (implementado)

- **Config:** `syslog_remote` (bool), `syslog_remote_host` (string), `syslog_remote_port` (int, default 514).
- **Comportamento:** cada mensagem que o daemon envia ao syslog local (via `l7_log`) é **também** enviada por **UDP** ao `host:porta`, formato **RFC 3164** (`<PRI>timestamp hostname layer7d: mensagem`).
- **Requisito:** com `syslog_remote=true`, **host** não vazio; caso contrário o daemon regista aviso e não envia remoto.
- **Firewall:** o pfSense deve permitir UDP de saída para o coletor.
- **Eventos JSON futuros** (pós-nDPI): podem acrescentar linhas dedicadas; ver [`../core/event-model.md`](../core/event-model.md).

## Eventos futuros (pós-nDPI)

Para tipos de evento quando houver classificação (flow, policy match, enforce), ver **[modelo de evento](../core/event-model.md)**. Os logs de aplicação (block/tag) poderão então incluir `flow_id`, `policy_id`, `action`, etc., em formato a definir (JSON por linha ou key=value).

## Resumo

| Aspeto | Estado V1 |
|--------|-----------|
| Destino | Syslog local (LOG_DAEMON) |
| Formato | Texto livre, uma linha por mensagem |
| log_level | Respeitado (error / warn / info / debug) |
| debug_minutes | `1–720`: após reload, equivale a **debug** até expirar; `0` desliga |
| syslog_remote | UDP para `syslog_remote_host` (Settings) |
| Eventos estruturados | Planeados em event-model.md |
