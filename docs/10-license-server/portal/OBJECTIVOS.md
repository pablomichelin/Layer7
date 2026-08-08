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

- [ ] SPA e API live alinhadas (sem drift de meses)
- [ ] Versão visual visível na UI e no `VERSION.md`
- [ ] Criação de licença com chave copiável e instrução de activação
- [ ] Lista com filtros: status, cliente, bound, a expirar, SKU
- [ ] Dashboard com expiração efectiva e atalhos
- [ ] Renovação rápida (+periodo) no detalhe
- [x] Workflow de rebind governado (motivo + auditoria)
- [ ] UI de auditoria administrativa (leitura)
- [ ] Check-ins visíveis no detalhe da licença
- [ ] Fluxo claro pós-revogação (desrevogar **ou** substituir licença)
- [ ] Inventário SKU coerente (`base` / `base,identity`; legado tratado)
- [ ] Docs da trilha portal actualizados; plano de melhoria P0+P1 fechado
      ou residual só `FUTURA` com GO

## Fora de objectivo até GO explícito

- Portal do cliente final
- Portal MSP / multi-tenant
- Multi-admin com papéis de vendas
- Faturação / billing
- Alertas email em massa / marketing
- Escala de “centenas de operadores”

## Métricas de sucesso (qualitativas)

- Renovar ou revogar uma licença sem SSH
- Trocar hardware de um cliente com um fluxo documentado no painel
- Responder “quem fez o quê?” sem consultar Postgres à mão
- Emitir Identity (SKU Y) sem ambiguidade de `full` vs `base,identity`
