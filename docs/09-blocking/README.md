# Trilha de Bloqueio

Documentos desta pasta descrevem a evolucao do Layer7 para bloqueio operacional
real de aplicacoes, sites, servicos e funcoes de produto no pfSense CE.

## Estado

**Caminho A (A0–A5):** concluido em `1.8.11_23`.  
**Caminho B (Enforcement 100%):** em execucao — ver plano abaixo.

## Documentos

- **[`plano-enforcement-100-porcento.md`](plano-enforcement-100-porcento.md)** — **SSOT Caminho B:** corrigir enforcement PF escopado por cliente (prioridade actual)
- [`caminho-a-plano-de-implementacao.md`](caminho-a-plano-de-implementacao.md) — Caminho A (UX/dispositivos/SNI); concluido
- [`blocking-master-plan.md`](blocking-master-plan.md) — plano mestre historico (v1.0.0, 2026-03-23)
- [`revisao-codigo-pre-install-2026-06-15.md`](revisao-codigo-pre-install-2026-06-15.md) — revisao geral pre-instalação (50 achados REV-001..050)
- [`revisao-funcional-pre-producao-2026-07-29.md`](revisao-funcional-pre-producao-2026-07-29.md) — auditoria end-to-end actual, correcções `_27` e riscos que ainda impedem produção

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
