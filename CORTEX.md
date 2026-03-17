# CORTEX.md

## Projeto
Layer7 para pfSense CE

## Status atual
Planejamento mestre concluído.
Execução ainda não iniciada.

## Fase atual
Fase 0 / Fase 1

## Última entrega
Criação do conjunto inicial de documentação-mestre:
- visão geral
- arquitetura
- roadmap
- backlog
- estrutura de repositório
- padrões
- plano de implementação
- testes
- empacotamento
- runbook
- riscos
- prompts

## Objetivo imediato
1. criar repositório no GitHub;
2. subir estrutura inicial;
3. montar builder + lab;
4. iniciar PoC do engine.

## Próximos 3 passos
1. criar repositório local e estrutura de pastas;
2. escrever `README.md` real do projeto;
3. criar ADR-0001 escolhendo o engine e ADR-0002 definindo distribuição por `.txz`.

## Decisões congeladas
- foco em pfSense CE;
- pacote open source;
- distribuição inicial por artefato;
- sem software pago obrigatório;
- V1 sem TLS MITM universal;
- V1 com modo monitor e enforce;
- documentação viva obrigatória.

## Riscos ativos
- escopo crescer cedo demais;
- empacotamento ficar mais complexo que o core;
- tentar resolver QUIC/ECH cedo demais;
- dedicar esforço à GUI antes do runtime.

## Itens adiados
- console central;
- identidade avançada;
- TLS inspection seletiva;
- integração profunda com Suricata;
- console multi-firewall.

## Política de trabalho
- um bloco por vez;
- uma validação por vez;
- docs no mesmo commit;
- sem refactor amplo sem necessidade.

## Definition of Done da V1
- pacote instalável
- daemon funcional
- GUI básica
- policy engine
- enforcement mínimo
- observabilidade básica
- rollback validado
- docs completas

