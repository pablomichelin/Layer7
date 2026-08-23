# Contrato — Sinal de instalação / heartbeat (BG-162)

**Estado:** implementado no git (`2026-08-22`) · **ADR-0036**  
**Código:** license-server `POST /api/license/install-ping` + portal
`/installations` · pacote `1.9.71` (libexec + tick `layer7d`)  
**Live `.244` / GitHub Release:** **não** neste bloco (P0-1 + GO publish)

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| Objectivo | Ver instalações mesmo sem serial: IP público, interfaces, nomes pfSense |
| Impacto | Endpoint público novo; tabelas; página portal; ping fail-open no pacote |
| Risco | Spam no endpoint aberto; PII de equipamento (EULA §8); P0-1 |
| Teste | `node --test` parse/schema/CSRF; PHP inventário; helper classifica evento |
| Rollback | Reverter commit; pacote anterior; `DROP TABLE` só se o live as tiver |

## Endpoint público

`POST https://license.systemup.inf.br/api/license/install-ping`

- Fora de `isAdminApiPath` (como activate/check-in)
- Rate-limit 10/min
- Payload ≤ 16 KiB; campos extra rejeitados
- Resposta `{ "status": "ok" }` — sem dados internos
- Falha de rede no cliente = fail-open (N3)

## Payload do appliance

Campos obrigatórios: `hardware_id` (64 hex), `event`
(`install` \| `upgrade` \| `heartbeat`).

Opcionais: `install_id`, `package_version`, `hostname`, `domain`, `fqdn`,
`pfsense_version`, `pfsense_version_patch`, `platform`, `uniqueid`,
`system_serial`, `os_release`, `hw_model`, `ncpu`, `mem_mb`, `wan_ipv4`,
`wan_ipv6`, `gateway_v4`, `license_key` (32 hex se existir),
`interfaces[]` (≤32; `id`, `real`, `descr`, `ipv4`, `ipv6`).

**Não enviar:** tráfego, contas GUI/VPN, políticas, MAC/UUID em claro,
ARP/DHCP, `config.xml` completo.

## Servidor

- `egress_ip` = `getClientIp()` (hop TLS confiável)
- Upsert `install_instances` por `hardware_id`
- Heartbeat do mesmo `hardware_id` em < 6 h actualiza `last_seen_at`
  sem nova linha de log
- Admin: `GET /api/installations` e `GET /api/installations/:id`
  (`licenses.read`)

## Cliente

- Inventário PHP (`layer7-install-ping.inc`)
- Libexec `/usr/local/libexec/layer7-install-ping` (curl 10/30 s)
- `pkg-install.in` dispara em background após `onestart`
- `layer7d` dispara a cada 24 h (e no primeiro tick)
- Sem interruptor GUI
