# license-server/

Código do **servidor de licenças Layer7** (API Node, React SPA, Nginx,
PostgreSQL via Docker Compose).

## Documentação (obrigatória)

**Não** tratar este README como SSOT. Seguir:

1. [`../CORTEX.md`](../CORTEX.md)
2. [`../docs/10-license-server/README.md`](../docs/10-license-server/README.md)
3. **Trilha portal (versão visual + planos):**  
   [`../docs/10-license-server/portal/README.md`](../docs/10-license-server/portal/README.md)
4. Governação:  
   [`../docs/10-license-server/portal/GOVERNANCE.md`](../docs/10-license-server/portal/GOVERNANCE.md)

| Tema | Doc |
|------|-----|
| Versão visual actual | [`portal/VERSION.md`](../docs/10-license-server/portal/VERSION.md) |
| Plano de melhoria | [`portal/planos/2026-08-08-melhoria-total-portal.md`](../docs/10-license-server/portal/planos/2026-08-08-melhoria-total-portal.md) |
| Uso operacional | [`MANUAL-USO-LICENCAS.md`](../docs/10-license-server/MANUAL-USO-LICENCAS.md) |
| Deploy seguro / host | [`PLANO-LICENSE-SERVER.md`](../docs/10-license-server/PLANO-LICENSE-SERVER.md) + runbooks em `docs/13-runbooks/` |

## Versão visual

Baseline formal: **`0.0.1`**. Independente do `PORTVERSION` do pacote pfSense.

## Deploy live

- Host: `192.168.100.244`
- Dir: `/opt/layer7-license`
- URL: `https://license.systemup.inf.br`

**Proibido** afectar outros serviços no host (Zabbix, Grafana, etc.).
