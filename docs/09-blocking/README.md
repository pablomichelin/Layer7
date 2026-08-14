# Trilha de Bloqueio

Documentos desta pasta descrevem a evolucao do Layer7 para bloqueio operacional
real de aplicacoes, sites, servicos e funcoes de produto no pfSense CE.

## Estado

**Caminho A (A0–A5):** concluido em `1.8.11_23`.  
**Caminho B (enforcement):** linhagem `_24`…`_69` → `1.9.0`…`1.9.8` (G2–G7
PASS / produção enforce). Base lab/`latest`: **`1.9.46`**.  
**Reconciliação `_24`…`_65` vs `1.9.46` (`2026-08-09`):** **NO-GO** reinstalar
candidatos históricos — ver artefacto abaixo.

## Documentos

- **[`auditoria-licencas-auth-deploy-2026-08-14.md`](auditoria-licencas-auth-deploy-2026-08-14.md)** — **SSOT auditoria `2026-08-14`:** P0–P3 licenças/auth/daemon/package/Docker; **P0-1 ACTIVO** (serving `30.11` versionado no git; freeze **não** encerrado; proibido deploy integral HEAD); BG-128
- **[`mapa-prontidao-mitm-piloto-2026-08-09.md`](mapa-prontidao-mitm-piloto-2026-08-09.md)** — **SSOT prontidão MITM piloto**: **NÃO PRONTO activar**; P1+P2+**P3 PASS** (`1.9.47`); ficha site = **gate activação** (≠ gap eng.)
- **[`GO-escopo-piloto-mitm-generico.md`](GO-escopo-piloto-mitm-generico.md)** — P1: decisões D1–D9 + formulário site + norma de gate
- **[`runbook-piloto-mitm-generico.md`](runbook-piloto-mitm-generico.md)** — P2: ops piloto (CA/GPO, metadados 30d, break-glass, auto-disable)
- **[`auditoria-reconciliacao-enforcement-1.8.11_24-_65-vs-1.9.46-2026-08-09.md`](auditoria-reconciliacao-enforcement-1.8.11_24-_65-vs-1.9.46-2026-08-09.md)** — **SSOT decisão `2026-08-09`:** inventário, correções absorvidas, único pacote elegível **`1.9.46`**, gates pré-install, conflitos documentais
- **[`plano-enforcement-100-porcento.md`](plano-enforcement-100-porcento.md)** — plano Caminho B (histórico de execução; não reinstalar `_NN`)
- [`caminho-a-plano-de-implementacao.md`](caminho-a-plano-de-implementacao.md) — Caminho A (UX/dispositivos/SNI); concluido
- [`blocking-master-plan.md`](blocking-master-plan.md) — plano mestre historico (v1.0.0, 2026-03-23)
- [`revisao-codigo-pre-install-2026-06-15.md`](revisao-codigo-pre-install-2026-06-15.md) — revisao geral pre-instalação (50 achados REV-001..050)
- [`revisao-funcional-pre-producao-2026-07-29.md`](revisao-funcional-pre-producao-2026-07-29.md) — revisão funcional, correcções `_27` (histórico)
- **[`auditoria-end-to-end-2026-07-29.md`](auditoria-end-to-end-2026-07-29.md)** — auditoria Etapa 1 (histórico `_31` NO-GO)
- [`matriz-cadeia-de-enforcement.md`](matriz-cadeia-de-enforcement.md) — cadeia config→daemon→PF
- [`matriz-state-table.md`](matriz-state-table.md) — `pfctl -k`, flush e limitações stateful
- [`matriz-compatibilidade-ce-plus-freebsd.md`](matriz-compatibilidade-ce-plus-freebsd.md) — CE vs Plus vs FreeBSD 15/16
- [`matriz-limitacoes-dpi.md`](matriz-limitacoes-dpi.md) — nDPI, captura passiva, ECH/CDN/QUIC
- **[`plano-gates-producao.md`](plano-gates-producao.md)** — gates **G0–G7** (PASS na linhagem `_69`/`1.9.x`; B1 `_31` supersedido)
- **[`matriz-unificada-rev-fp-aud.md`](matriz-unificada-rev-fp-aud.md)** — ledger REV/FP/AUD/BG (histórico defeitos `_31`)
- **[`diagnostico-multitask-2026-07-30.md`](diagnostico-multitask-2026-07-30.md)** — consolidação multitask (histórico)

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
