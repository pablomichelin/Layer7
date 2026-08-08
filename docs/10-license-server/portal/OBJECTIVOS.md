# Objectivos — Portal Admin de Licenças

## Missão

Permitir ao operador Systemup **gerir com confiança** o ciclo de vida das
licenças Layer7 (clientes, emissão, activação, renovação, revogação,
suporte a hardware e auditoria), via
`https://license.systemup.inf.br`, sem depender de SQL/SSH no dia a dia.

## Objectivos de produto (operador único)

1. **Completude operacional** — tudo o que o suporte precisa fazer a uma
   licença deve ser possível no painel (ou explícito como “só via runbook”).
2. **Previsibilidade** — estados efectivos (active/expired/revoked, bound,
   SKU) claros e alinhados ao contrato F3 / ADR-0025.
3. **Rastreabilidade** — acções administrativas e check-ins consultáveis
   na UI.
4. **Não regressão de segurança** — manter F2 (TLS, sessão, same-origin,
   rate limit, arquivo lógico) e guardrails F3.
5. **Documentação viva** — versão visual, changelog, planos e acções
   sempre actualizados (`GOVERNANCE.md`).

## Critérios de `1.0.0` (completo para operador único)

O portal só sobe a **1.0.0** quando **todos** forem verdade:

- [x] SPA e API live alinhadas (sem drift de meses)
- [x] Versão visual visível na UI e no `VERSION.md`
- [x] Criação de licença com chave copiável e instrução de activação
- [x] Lista com filtros: status, cliente, bound, a expirar, SKU
- [x] Dashboard com expiração efectiva e atalhos
- [x] Renovação rápida (+periodo) no detalhe
- [x] Workflow de rebind governado (motivo + auditoria)
- [x] UI de auditoria administrativa (leitura)
- [x] Check-ins visíveis no detalhe da licença
- [x] Fluxo claro pós-revogação (**substituir** licença; sem desrevogar)
- [x] Inventário SKU coerente (`base` / `base,identity`; legado tratado)
- [x] Docs da trilha portal actualizados; plano de melhoria P0+P1 fechado
      ou residual só `FUTURA` com GO

**Veredicto P1e (`2026-08-08`):** todos os critérios satisfeitos → `1.0.0`.

## Fora de objectivo até GO explícito

- Portal do cliente final
- Portal MSP / multi-tenant
- Multi-admin com papéis de vendas
- Faturação / billing
- Alertas email em massa / marketing
- Escala de “centenas de operadores”

## Métricas de sucesso (qualitativas)

- Operador resolve renovação, rebind e pós-revogação sem SSH/SQL
- Auditoria e check-ins consultáveis na UI após cada acção crítica
- Documentação da trilha reflecte a versão live
