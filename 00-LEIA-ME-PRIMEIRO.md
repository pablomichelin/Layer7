# Layer7 para pfSense CE - Leitura Inicial

## Objetivo deste pacote documental

Este conjunto de arquivos foi criado para servir como **planejamento mestre**, **SSOT operacional** e **guia de execução por fases** para o desenvolvimento de um **pacote Layer 7 open source** para **pfSense CE**, pensado para ser desenvolvido no **Cursor**, versionado no **GitHub** e distribuído inicialmente como **artefato de pacote** instalável no firewall.

A ideia central é:

- construir um produto de forma incremental;
- evitar saltos de escopo;
- reduzir risco de regressão;
- manter documentação viva desde o primeiro commit;
- separar claramente:
  - o que entra na V1;
  - o que fica para V2;
  - o que é limitação natural da internet moderna;
  - o que é limitação específica do pfSense/FreeBSD.

---

## Leitura recomendada em ordem

1. `01-VISAO-GERAL-E-ESCOPO.md`
2. `02-ARQUITETURA-ALVO.md`
3. `03-ROADMAP-E-FASES.md` *(V1: fases 0–11; transição 12; **V2+ documentadas nas fases 13–22**)*
4. `04-BACKLOG-MVP-E-VERSOES.md`
5. `05-ESTRUTURA-REPOSITORIO-CURSOR-GITHUB.md`
6. `06-PADROES-DE-DESENVOLVIMENTO-E-SEGURANCA.md`
7. `07-PLANO-DE-IMPLEMENTACAO-PASSO-A-PASSO.md`
8. `08-PLANO-DE-TESTES-E-HOMOLOGACAO.md`
9. `09-EMPACOTAMENTO-PFSENSE-E-DISTRIBUICAO.md`
10. `10-RUNBOOK-OPERACIONAL-E-ROLLBACK.md`
11. `11-RISCOS-LIMITACOES-E-DECISOES.md`
12. `12-PLANO-DE-DOCUMENTACAO-E-GITHUB.md`
13. `13-MODELOS-DE-ISSUES-E-PRS.md`
14. `14-CHECKLIST-MESTRE.md`
15. `15-PROMPT-MESTRE-CURSOR.md`
16. `CORTEX.md`
17. `AGENTS.md`

---

## Resultado esperado deste projeto

Ao final da trilha V1, o projeto deve entregar:

- um **pacote próprio para pfSense CE**;
- um **daemon Layer 7** com classificação de tráfego;
- políticas de **monitoramento**, **tag**, **allow** e **block**;
- enforcement por:
  - aliases/tables do PF;
  - integração com DNS/host/domain policy;
- GUI no padrão do ecossistema pfSense;
- logs locais mínimos + exportação para syslog remoto;
- build reproduzível;
- documentação de instalação, upgrade e rollback;
- base sólida para evoluir em V2.

---

## O que este pacote documental NÃO promete

Este projeto não promete, na V1:

- inspeção perfeita de toda a internet moderna;
- equivalência direta com Palo Alto / Fortinet / Check Point;
- MITM universal de TLS;
- console central multi-firewall;
- engine proprietária autoral com cobertura comparável aos grandes vendors;
- atualização automática de assinaturas de terceiros sem infraestrutura própria.

---

## Regras operacionais recomendadas

1. **Uma etapa por vez.**
2. **Uma mudança testada por vez.**
3. **Sem pular da PoC direto para GUI bonita.**
4. **Sem distribuir via repositório não suportado na fase inicial.**
5. **Sem esconder limitações técnicas no marketing do produto.**
6. **Sem deixar documentação para o final.**
7. **Sem merge em `main` sem documentação, teste e rollback definidos.**
8. **Não instalar no pfSense antes do pacote estar totalmente completo** — o pacote só será colocado no firewall quando estiver totalmente desenvolvido.

---

## Estratégia de execução

A execução ideal deste projeto no Cursor deve seguir este ciclo:

1. Ler `CORTEX.md`
2. Ler `AGENTS.md`
3. Escolher 1 bloco da fase atual
4. Implementar apenas o bloco
5. Rodar testes mínimos
6. Atualizar docs
7. Commitar
8. Criar release notes internas
9. Só então avançar

---

## Artefatos centrais ao longo do projeto

Durante a execução, você deve manter sempre atualizados:

- `CORTEX.md`
- `AGENTS.md`
- `docs/changelog/`
- `docs/adr/`
- `docs/releases/`
- `docs/tests/`
- `docs/runbooks/`

---

## Ponto de verdade sobre distribuição

O GitHub deve ser tratado como:

- fonte do código;
- fonte da documentação;
- local das releases;
- local dos artefatos de build;

Mas **a instalação no pfSense não deve ser pensada como “instalar do GitHub diretamente”**.  
O fluxo mais seguro para a V1 é:

1. desenvolver no Cursor;
2. versionar no GitHub;
3. gerar artefato `.txz`;
4. instalar o artefato no pfSense em ambiente controlado;
5. evoluir só depois para estratégia mais sofisticada de distribuição.

Isso reduz risco de colisão com upgrades do pfSense e mantém o projeto mais previsível.

---

## Como usar estes arquivos no dia a dia

### Se estiver começando do zero
Leia tudo na ordem proposta.

### Se estiver retomando depois de alguns dias
Leia:
- `CORTEX.md`
- `14-CHECKLIST-MESTRE.md`
- `03-ROADMAP-E-FASES.md`

### Se estiver trabalhando com IA no Cursor
Comece por:
- `AGENTS.md`
- `15-PROMPT-MESTRE-CURSOR.md`

### Se estiver preparando instalação real
Leia:
- `09-EMPACOTAMENTO-PFSENSE-E-DISTRIBUICAO.md`
- `10-RUNBOOK-OPERACIONAL-E-ROLLBACK.md`

---

## Status do projeto

- Fases completas: **0–10** (58/58 testes OK + nDPI + enforce real validado)
- Fase 11: **release V1 pronta para publicação**
- Escopo ativo: **V1 MVP**
- Objetivo imediato: **publicar release 0.1.0 no GitHub**
- Objetivo proibido neste momento: **feature creep, Fase 13+ antes do gate V1**

**Validação lab (2026-03-23):** **Enforce end-to-end funcional.** Pipeline nDPI → policy engine → pfctl comprovado: `pf_add_ok=7`, 6 IPs automaticamente adicionados à tabela PF (TuyaLP/IoT, SSDP, MDNS), exceções respeitadas, block/tag decisions logadas a NOTICE. CLI `-e` validou BitTorrent→block. Pacote v0.1.0 compilado e instalado.

**Validação lab (2026-03-22):** 58/58 testes OK. nDPI integrado e a classificar tráfego real. GUI completa com 6 páginas. Fleet management. Custom protocols file. Plataforma: pfSense CE 2.8.1-dev (FreeBSD 15.0-CURRENT). O ponto de verdade operacional está em **`CORTEX.md`**.

---

## Entrega ideal da V1

Considere a V1 pronta apenas quando:

- instalar em pfSense CE limpo;
- manter configuração após reboot;
- detectar aplicações/protocolos úteis;
- aplicar políticas previsíveis;
- permitir rollback simples;
- exportar logs;
- possuir documentação de operação;
- ter trilha de build repetível.

