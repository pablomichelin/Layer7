# Prompt de continuidade — Fase A / Opcao 1

Use este prompt no proximo chat para continuar o trabalho sem precisar repetir
o contexto manualmente.

```text
Estamos no projeto Layer7 para pfSense CE.

Leia obrigatoriamente, nesta ordem:
1. CORTEX.md
2. docs/00-overview/product-charter.md
3. docs/01-architecture/target-architecture.md
4. docs/02-roadmap/roadmap.md
5. docs/09-blocking/blocking-master-plan.md
6. docs/09-blocking/phase-a-option1-package-rules-plan.md

Contexto atual:
- o produto ja classifica trafego com nDPI;
- o daemon layer7d ja decide allow/monitor/tag/block;
- o daemon ja adiciona IPs a PF tables (`layer7_block`, `layer7_tagged`);
- o pacote ja ganhou um helper PF e diagnostics melhores;
- a decisao arquitetural para a Fase A e usar a Opcao 1:
  publicar regras do pacote no ciclo oficial do filtro do pfSense.

Objetivo deste novo chat:
- implementar a Opcao 1 em blocos pequenos, sem grande reestruturacao;
- comecar pelo Passo 1 do plano:
  confirmar no appliance/repositorio qual hook real do pacote deve ser usado
  para gerar regras do filtro e como validar isso em diagnostics;
- se houver confianca suficiente, implementar o menor bloco reversivel que
  publique a regra minima de block para `<layer7_block>`;
- atualizar documentacao no mesmo bloco;
- registrar claramente objetivo, impacto, risco, teste e rollback.

Restrições:
- foco em pfSense CE;
- nada fora da V1 sem registrar como backlog;
- sem solucoes magicas;
- se houver duvida relevante de compatibilidade do pfSense CE, parar e registrar
  a incerteza antes de seguir.

Entrega esperada:
1. Resumo
2. Arquivos afetados
3. Implementacao
4. Teste minimo
5. Risco
6. Rollback
7. Docs a atualizar
```
