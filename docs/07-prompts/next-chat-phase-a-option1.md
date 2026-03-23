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
- o pacote ja ganhou helper PF, hook `layer7_generate_rules("filter")`,
  reload via `filter_configure()` e diagnostics melhores;
- o pacote foi compilado e instalado no appliance pfSense CE;
- o appliance ja provou que:
  - a politica `block` casa com `Github`;
  - o daemon regista `action=block reason=policy_match`;
  - o IP entra em `layer7_block`;
- o appliance ainda NAO provou que:
  - a regra `layer7:block:src` entra no ruleset ativo;
  - `pfctl -sr` mostra a regra do pacote;
  - o bloqueio automatico acontece sem regra manual externa.

Objetivo deste novo chat:
- continuar a Fase A sem reabrir trabalho ja validado;
- focar especificamente no gap atual:
  descobrir como o pfSense CE 25.11.1 realmente inclui regras de pacote no
  filtro ativo, porque `layer7d -> layer7_block` ja funciona mas a regra
  `layer7:block:src` ainda nao aparece em `pfctl -sr`;
- validar isso com evidencias objetivas no appliance:
  `pfctl -sr`, `rules.debug`, codigo/hook esperado pelo pfSense;
- implementar o menor ajuste reversivel necessario para que a regra do pacote
  realmente entre no ruleset ativo;
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
