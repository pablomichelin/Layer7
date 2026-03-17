# Prompt Mestre para Cursor

Use este prompt no início de cada bloco relevante do projeto.

---

Você está trabalhando no projeto **Layer7 para pfSense CE**, um pacote open source instalável, sem dependência de software pago obrigatório, desenvolvido para rodar no ecossistema do **pfSense CE**.

## Objetivo do projeto
Construir um pacote `pfSense-pkg-layer7` com:
- daemon `layer7d`;
- classificação Layer 7;
- policy engine;
- enforcement básico;
- GUI no padrão pfSense;
- logs e runbooks;
- build reproduzível;
- distribuição inicial por artefato de pacote.

## Regras obrigatórias
1. Trabalhe **somente no bloco atual**.
2. **Não reestruture o projeto inteiro** sem pedido explícito.
3. **Não invente features fora do escopo da V1**.
4. Sempre leia antes:
   - `CORTEX.md`
   - `AGENTS.md`
   - `docs/00-overview/product-charter.md`
   - `docs/01-architecture/target-architecture.md`
   - `docs/02-roadmap/roadmap.md`
5. Toda mudança deve deixar claro:
   - objetivo;
   - arquivos alterados;
   - risco;
   - teste mínimo;
   - docs a atualizar.
6. Se houver incerteza técnica, registre a incerteza em vez de mascará-la.
7. Atualize a documentação correspondente no mesmo bloco.
8. Preserve compatibilidade com o escopo V1.

## Saída esperada
Sempre responda com esta estrutura:
1. **Resumo do bloco**
2. **Arquivos que pretende criar/alterar**
3. **Implementação proposta**
4. **Riscos**
5. **Teste mínimo**
6. **Rollback**
7. **Documentação a atualizar**

## Contexto técnico resumido
- Plataforma-alvo: pfSense CE
- Pacote: `pfSense-pkg-layer7`
- Daemon: `layer7d`
- Engine recomendada: nDPI
- Enforcement V1: aliases/tables + políticas por host/domínio quando aplicável
- Logs: locais mínimos + syslog remoto
- Distribuição V1: artefato `.txz`
- Não objetivos da V1:
  - TLS MITM universal
  - console central
  - analytics pesado
  - equivalência com NGFW enterprise

## Tarefa deste bloco
[SUBSTITUIR AQUI PELO BLOCO ATUAL]

## Restrições adicionais
- Faça mudanças pequenas e auditáveis.
- Evite código “mágico”.
- Prefira clareza a esperteza.
- Não altere arquivos não relacionados sem necessidade real.
- Se criar scripts, comente o propósito.
- Se criar config, defina default e validação.

