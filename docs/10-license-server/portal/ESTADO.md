# Estado Actual — Portal Admin

> Snapshot: **2026-08-14** — API live **`30.13`** (`20260814T142739Z`); SPA visual **`2.0.0`** intocada.

## Ambiente

| Item | Valor |
|------|-------|
| Host | `192.168.100.244` |
| URL | `https://license.systemup.inf.br` |
| Versão visual | **`2.0.0`** (SPA **não** rebuild neste bloco) |
| API live | **`30.13`** dual-mode nonce/envelope (`5754bfa`) |
| Imagem API | `sha256:bbc74a53651f835d4dd0b07f2d5f97c2a3cd25e99c8e965309aa6ea018aadb9f` |
| Tag rollback | `layer7-license-api:pre-30.13-20260814T142739Z` |
| Health | `ok` (público + origin `8445`) |
| TZ | `UTC` |
| Plano | nenhum activo (004 CONCLUIDO) |

## Notas

- Owner gere técnicos em **Utilizadores**
- Técnicos: permissões explícitas; sem `users.manage`
- Rsync: sempre `--exclude .env`; **nunca** `--delete`
- Compose/nginx live **preservados** (volume content 30.11 + rotas content)
- Frontend/web, db, Apache, Grafana, Zabbix: **intocados**

## Próximo

1. Repetir **só** GA5.9 no `.254` com GO próprio — **não** neste bloco.
2. Novo plano portal só com ideias **ACEITE** + GO (nenhum plano `ACTIVO`).
3. Não misturar com MITM permanente (fila 20.37 fechada).
