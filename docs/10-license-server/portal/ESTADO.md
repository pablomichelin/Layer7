# Estado Actual — Portal Admin

> Snapshot: **2026-08-08** — versão visual **`2.0.0`** (PORTAL-PLAN-004).

## Ambiente

| Item | Valor |
|------|-------|
| Host | `192.168.100.244` |
| URL | `https://license.systemup.inf.br` |
| Versão visual | **`2.0.0`** |
| SPA | `assets/index-DwHpvSVY.js` |
| Health | `ok` |
| TZ | `UTC` |
| Plano | nenhum activo (004 CONCLUIDO) |

## Notas

- Owner gere técnicos em **Utilizadores**
- Técnicos: permissões explícitas; sem `users.manage`
- Rsync: sempre `--exclude .env`

## Próximo

1. **Commit local + push** do working tree portal `2.0.0` (e, em bloco separado, pacote `1.9.38`) — **pendente GO humano**.
2. Novo plano portal só com ideias **ACEITE** + GO (nenhum plano `ACTIVO`).
3. Não misturar com MITM `20.10` (bloqueado até S1–S8 + GO lab).
