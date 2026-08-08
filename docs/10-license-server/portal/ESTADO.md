# Estado Actual — Portal Admin

> Snapshot: **2026-08-08** — versão visual **`0.2.0`**.

## Ambiente

| Item | Valor |
|------|-------|
| Host | `192.168.100.244` |
| Dir | `/opt/layer7-license` |
| URL | `https://license.systemup.inf.br` |
| Versão visual | **`0.2.0`** |
| Backup P1a | `backups/layer7-license-postgres-20260808T215305Z.sql` |

## Deploy

| Contentor | Nota |
|-----------|------|
| web/api | Rebuild `2026-08-08T21:53Z` |
| nginx | **Reiniciado** após recreate (DNS interno) — obrigatório no checklist |
| SPA | `index-XzglZKGO.js` |
| Health | OK |

## Schema

`customers.cnpj`, `customers.tags` presentes.

## Capacidade

| Área | Estado |
|------|--------|
| P0 fundação | OK |
| Renovação +30/90/365 | OK |
| Download .lic pós-renovação | OK (oferta na UI) |
| CNPJ / tags cliente | OK |
| Auditoria / check-in / rebind UI | Pendente P1b+ |

## Próximo

**P1b** → `0.3.0` (auditoria + check-ins).
