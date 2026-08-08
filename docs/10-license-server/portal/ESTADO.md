# Estado Actual — Portal Admin

> Snapshot: **2026-08-08** (versão visual **`0.1.0`**).

## Ambiente

| Item | Valor |
|------|-------|
| Host | `192.168.100.244` (Ubuntu) |
| Directório | `/opt/layer7-license` |
| URL pública | `https://license.systemup.inf.br` |
| Origin | porta host `8445` → nginx container |
| Compose | `layer7-license-{web,api,db,nginx}` |
| Rede Docker | `layer7-license-net` (isolada) |
| Versão visual | **`0.1.0`** |
| Backup pré-deploy | `/opt/layer7-license/backups/layer7-license-postgres-20260808T214134Z.sql` |

## Imagens (pós-P0)

| Contentor | Created | Started |
|-----------|---------|---------|
| `layer7-license-web` | `2026-08-08T21:42:32Z` | `2026-08-08T21:42:42Z` |
| `layer7-license-api` | `2026-08-08T21:42:32Z` | `2026-08-08T21:42:42Z` |

SPA asset público: `index-B540Roat.js` (substitui `index-DzAub8GA.js` de Abril).

## Inventário live

| Métrica | Valor |
|---------|-------|
| Features | `base` (6 licenças; 0 `full`) |
| active / revoked | 3 / 3 |

## Capacidade (`0.1.0`)

| Área | Estado |
|------|--------|
| Health API | OK |
| Versão visual na UI | OK |
| Dashboard a expirar 30d | OK |
| Filtros lista + copiar chave | OK |
| SKU presets | OK |
| Auditoria / check-in / rebind UI | Pendente P1 |

## Plano activo

P0 **FEITO**. Próximo: **P1a** (renovação rápida).
