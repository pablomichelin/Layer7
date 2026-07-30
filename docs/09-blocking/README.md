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
- [`revisao-funcional-pre-producao-2026-07-29.md`](revisao-funcional-pre-producao-2026-07-29.md) — revisão funcional, correcções `_27` e riscos que ainda impedem produção
- **[`auditoria-end-to-end-2026-07-29.md`](auditoria-end-to-end-2026-07-29.md)** — **SSOT auditoria Etapa 1:** inventário, veredicto **NO-GO**, registo **AUD-001..015**
- [`matriz-cadeia-de-enforcement.md`](matriz-cadeia-de-enforcement.md) — cadeia config→daemon→PF
- [`matriz-state-table.md`](matriz-state-table.md) — `pfctl -k`, flush e limitações stateful
- [`matriz-compatibilidade-ce-plus-freebsd.md`](matriz-compatibilidade-ce-plus-freebsd.md) — CE vs Plus vs FreeBSD 15/16
- [`matriz-limitacoes-dpi.md`](matriz-limitacoes-dpi.md) — nDPI, captura passiva, ECH/CDN/QUIC
- **[`plano-gates-producao.md`](plano-gates-producao.md)** — gates **G0–G7** e **Bloco B1** (install passivo `_31`)
- **[`matriz-unificada-rev-fp-aud.md`](matriz-unificada-rev-fp-aud.md)** — ledger REV/FP/AUD/BG (SSOT defeitos)
- **[`diagnostico-multitask-2026-07-30.md`](diagnostico-multitask-2026-07-30.md)** — consolidação rodada multitask; veredicto **NO-GO**

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
