# CORTEX.md

## Projeto
Layer7 para pfSense CE

## Status atual
**No repositório:** scaffold do port `pfSense-pkg-layer7` (Makefile que compila `layer7d` em C), ficheiros XML/PHP/rc.d/sample, daemon mínimo em `src/layer7d/main.c`.  
**Não validado em lab:** build `.txz`, `pkg add` em pfSense, registo na GUI, serviço em execução no appliance — ver [`docs/04-package/validacao-lab.md`](docs/04-package/validacao-lab.md).

## Fase atual
**Gate:** validação real em FreeBSD (build) + pfSense (instalação, serviço, GUI se aplicável). Nenhuma feature nova até fechar esse gate.

## Última entrega (documental / repo)
- Saneamento: docs alinhadas à realidade; `validacao-lab.md` + checklist executável.
- Código existente sem alteração funcional neste bloco.

## Objetivo imediato
1. Executar [`docs/04-package/validacao-lab.md`](docs/04-package/validacao-lab.md) no builder + pfSense lab e colar evidências.
2. Só depois: próximo bloco técnico (a definir após resultado da validação).

## Próximos 3 passos (após validação OK)
1. Corrigir port/GUI/rc conforme falhas observadas no lab.
2. Então retomar roadmap (ex.: parser JSON) — não antes do gate.

## Decisões congeladas
- foco em pfSense CE;
- pacote open source;
- distribuição inicial por artefato `.txz`;
- sem software pago obrigatório;
- V1 sem TLS MITM universal;
- V1 com modo monitor e enforce;
- documentação viva obrigatória;
- engine classificação: **nDPI** (ADR-0001).

## Riscos ativos
- assumir GUI/menu/serviço OK sem corrida no lab;
- escopo crescer antes da validação;
- empacotamento ficar mais complexo que o core.

## Itens adiados
- console central;
- identidade avançada;
- TLS inspection seletiva;
- integração profunda com Suricata;
- console multi-firewall.

## Política de trabalho
- um bloco por vez;
- uma validação por vez;
- nada marcado como “feito” sem evidência de lab quando o critério for appliance;
- docs no mesmo commit.

## Definition of Done da V1
- pacote instalável *(com evidência)*
- daemon funcional *(com evidência)*
- GUI básica *(com evidência)*
- policy engine
- enforcement mínimo
- observabilidade básica
- rollback validado
- docs completas
