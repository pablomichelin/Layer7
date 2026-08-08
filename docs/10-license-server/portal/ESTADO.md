# Estado Actual — Portal Admin

> Snapshot: **2026-08-08** — versão visual **`1.3.1`** (hotfix revisão).

## Ambiente

| Item | Valor |
|------|-------|
| Host | `192.168.100.244` |
| URL | `https://license.systemup.inf.br` |
| Versão visual | **`1.3.1`** |
| SPA | `assets/index-B-Ms_YV_.js` |
| Health | `ok` (`timestamp` UTC) |
| TZ contentores | `UTC` (`db` + `api`) |
| Dados | volume Postgres intacto (ex.: 8 licenças) |
| Plano | nenhum activo (`PORTAL-PLAN-002` CONCLUIDO) |

## Notas operacionais

- Rsync: **sempre** `--exclude .env`
- Compose: `TZ=${TZ:-UTC}` em `db`/`api`; `.env` live tem `TZ=UTC`
- Smoke BRT: criar/ver licença com expiry amanhã e confirmar data no ecrã

## Próximo

Só ideias com GO (`IDEIAS.md`).
