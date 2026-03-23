# Trilha de Bloqueio

Documentos desta pasta descrevem a evolucao do Layer7 para bloqueio operacional
real de aplicacoes, sites, servicos e funcoes de produto no pfSense CE.

## Estado

**Todas as fases concluidas na v1.0.0 (2026-03-23).**

## Documentos

- [`blocking-master-plan.md`](blocking-master-plan.md) — plano mestre de
  implementacao, riscos, fases, testes e rollout (todas as fases concluidas)

## Funcionalidades de bloqueio na V1

- Classificacao nDPI funcional (~350 apps)
- Enforcement PF automatico (regras integradas ao filtro pfSense)
- Bloqueio por destino (DNS + nDPI) com tabela `layer7_block_dst`
- Bloqueio por origem com tabela `layer7_block`
- Perfis de servico (15 built-in) para bloqueio com 1 clique
- Politicas granulares: app/categoria/interface/IP/CIDR/grupo/horario/host
- Anti-bypass DNS multi-camada (DoT/DoQ/DoH)
- Bloqueio QUIC selectivo
- GUI completa com 10 paginas
- Dashboard operacional com contadores
- Teste de politica com simulacao
