# Estado Actual — Portal Admin

> Actualizar em todo deploy ou mudança relevante do live.  
> Snapshot: **2026-08-08** (baseline `0.0.1`).

## Ambiente

| Item | Valor |
|------|-------|
| Host | `192.168.100.244` (Ubuntu) |
| Directório | `/opt/layer7-license` |
| URL pública | `https://license.systemup.inf.br` |
| Origin | porta host `8445` → nginx container |
| Compose | `layer7-license-{web,api,db,nginx}` |
| Rede Docker | `layer7-license-net` (isolada) |

## Imagens / idade observada (`2026-08-08`)

| Contentor | Observação |
|-----------|------------|
| `layer7-license-web` | Criado **2026-04-14** — SPA antiga |
| `layer7-license-api` | Criado **2026-08-04** — API recente (check-in, F3…) |
| `layer7-license-db` | Postgres 17, dados persistentes |
| `layer7-license-nginx` | Proxy interno |

**Drift:** frontend live ≠ backend live. Prioridade P0 do plano de melhoria.

## Inventário de dados (live, `2026-08-08`)

| Métrica | Valor |
|---------|-------|
| Clientes activos | 5 |
| Licenças (não arquivadas) | 6 |
| Bindadas | 6 |
| Unbound | 0 |
| Features | todas `full` (legado) |
| Activations log | 38 |
| Admin audit log | 56 |
| Check-ins log | 4 |
| Admins | 1 |
| Expirando em 30d | 0 |
| Expiradas efectivas (`active` + `expiry` passado) | 1 (cliente Lasalle) |

## Capacidade do painel (baseline `0.0.1`)

| Área | Estado |
|------|--------|
| Login sessão cookie | OK |
| Dashboard contadores + últimas activações | OK (mínimo) |
| CRUD clientes | OK |
| CRUD licenças + revogar + arquivar | OK |
| Download `.lic` (se bindada) | OK |
| Presets SKU na UI live | Provável **ausente** (SPA Abril) |
| UI auditoria | Ausente |
| UI check-in | Ausente |
| Rebind admin | Ausente (API também sem rota dedicada) |
| Renovação rápida | Ausente |
| Versão visual na UI | Ausente |

## Plano activo

[`planos/2026-08-08-melhoria-total-portal.md`](planos/2026-08-08-melhoria-total-portal.md) — estado `ACTIVO`.

## Próximo checkpoint esperado

Após bloco P0 → bump para `0.1.0`, actualizar este ficheiro com datas de
imagens Docker e confirmação de SPA alinhada.
