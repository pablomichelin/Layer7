# Estado Actual — Portal Admin

> Snapshot: **2026-08-22** — overlay BG-162 (`20260823T022826Z`); SPA visual **`2.2.0`**.
> **P0-1 ACTIVO:** freeze **não** encerrado; este bloco foi overlay por allowlist
> (API + SPA Instalações), **não** rsync integral do HEAD.

## Ambiente

| Item | Valor |
|------|-------|
| Host | `192.168.100.244` |
| URL | `https://license.systemup.inf.br` |
| Versão visual | **`2.2.0`** (Instalações) |
| API live | overlay `30.13` + BG-162 install-ping |
| Imagem API | `layer7-license-api` pós-BG-162 (`2f27f47f…`) |
| Rollback | tags `pre-bg162-20260823T022826Z` (api+web); dump `/var/backups/layer7/license-server-pre-bg162-20260823T022826Z.sql` |
| Tag `pre-30.13` | `layer7-license-api:pre-30.13-20260814T142739Z` — **só** incidente específico do overlay `30.13`; **não** é rollback padrão/`latest` (P2-16) |
| Health | `ok` (público + origin `8445`) |
| TZ | `UTC` |
| Plano | nenhum activo (004 CONCLUIDO) |

## Notas

- Owner gere técnicos em **Utilizadores**
- Técnicos: permissões explícitas; sem `users.manage`
- Rsync: sempre `--exclude .env`; **nunca** `--delete`
- Compose/nginx live **preservados** (volume content 30.11 + rotas content)
- Frontend/web, db, Apache, Grafana, Zabbix: **intocados**
- **P2-16:** rollback preferido = overlay conhecido (`bbc74a5…`); a tag
  `pre-30.13` **não** é `latest` — só incidente específico de `30.13`

## Próximo

1. **P0-1:** sem rsync/rebuild integral HEAD→`.244`. Runbook
   [`../../13-runbooks/bloqueio-deploy-integral-head-30.11.md`](../../13-runbooks/bloqueio-deploy-integral-head-30.11.md).
2. **P0-2 residual single-use/bind FEITO** no git (`2026-08-14`) —
   **não** deployar neste host (P0-1). HMAC fail-closed já estava
   no git. **P2-9 AVALIADO** neste bloco (BG-154; upgrade **não**
   injecta `true`). Próximo: P0-1 rebuild `api` + smoke só com GO.
3. Novo plano portal só com ideias **ACEITE** + GO (nenhum plano `ACTIVO`).
4. Não misturar com MITM permanente (fila 20.37 fechada).
5. Não sincronizar SPA `2.1.0` sem GO.
