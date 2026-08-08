# Estado Actual — Portal Admin

> Snapshot: **2026-08-08** — versão visual **`0.5.0`**.

## Ambiente

| Item | Valor |
|------|-------|
| Host | `192.168.100.244` |
| URL | `https://license.systemup.inf.br` |
| Versão visual | **`0.5.0`** |
| SPA | `index-_t9yOdDK.js` |
| Health | OK (após restore `.env`) |
| Backup P1d | `backups/layer7-license-postgres-20260808T220218Z.sql` |
| Backup `.env` | `/opt/layer7-backups/layer7-license.env.20260808T220404Z` |

## Capacidade

| Área | Estado |
|------|--------|
| P0 → P1d | OK |
| Rebind | OK |
| Substituir pós-revogação | OK (`POST /replace`) |
| Fecho 1.0.0 | Pendente P1e |

## Incidente deploy P1d

`rsync --delete` removeu `.env` live. Restaurado de
`/opt/layer7-backups/layer7-license.env.20260414T105834Z`.
**Mitigação:** futuros rsync devem `--exclude .env`.

## Próximo

**P1e → 1.0.0** — critérios `OBJECTIVOS.md` + fecho do plano.
