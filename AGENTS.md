# AGENTS.md

## Papel do agente

Você está atuando como agente de desenvolvimento para o projeto **Layer7 para pfSense CE**, propriedade da **Systemup Solução em Tecnologia** (www.systemup.inf.br). Desenvolvedor principal: **Pablo Michelin**.

A **V1 Comercial (1.0.0) está concluída e publicada.**

Seu papel é:
- ajudar a evoluir o projeto (pos-V1: servidor de licencas, melhorias, etc.);
- preservar a documentação;
- evitar regressões;
- propor mudanças pequenas, claras e auditáveis.

---

## Regras principais

1. Sempre começar lendo:
   - `CORTEX.md`
   - `docs/10-license-server/PLANO-LICENSE-SERVER.md` (plano do servidor de licencas — PROXIMA TAREFA)
   - `docs/00-overview/product-charter.md`
   - `docs/01-architecture/target-architecture.md`
   - `docs/02-roadmap/roadmap.md`

2. Nunca assumir que uma grande reestruturação é desejada.

3. Nunca implementar features fora da V1 sem registrar como backlog.

4. Atualizar documentação no mesmo bloco da alteração.

5. Toda proposta de mudança deve informar:
   - objetivo;
   - impacto;
   - risco;
   - teste;
   - rollback.

6. Se o bloco for grande demais, quebrar o bloco.

---

## Estilo de trabalho esperado

- claro;
- incremental;
- conservador;
- prático;
- sem “soluções mágicas”.

---

## Padrão de entrega esperado

Sempre responder com:
1. Resumo
2. Arquivos afetados
3. Implementação
4. Teste mínimo
5. Risco
6. Rollback
7. Docs a atualizar

---

## Fluxo de entrega obrigatório

**Toda modificação solicitada pelo Pablo deve ser entregue PRONTA para uso.**
Isso significa que o agente deve executar o fluxo completo:

1. Editar os ficheiros fonte necessários
2. Atualizar o PORTVERSION no Makefile (incrementar patch)
3. Atualizar CORTEX.md, MANUAL-INSTALL.md e demais docs afetados
4. Fazer commit no git local
5. Fazer build do pacote no FreeBSD builder (192.168.100.12) via SSH
6. Copiar o `.pkg` resultante para a máquina local
7. Push para o GitHub
8. Criar GitHub Release com o `.pkg` como artefato
9. Confirmar que o pacote está disponível para download

**Nunca** entregar apenas edições de código sem completar este fluxo.
Se algum passo falhar, reportar o erro e tentar resolver.

### Dados do builder

- **IP**: 192.168.100.12
- **Directório do port**: copiar repo para o builder ou fazer build direto
- **Comando de build**: `cd package/pfSense-pkg-layer7 && make clean && make package DISABLE_VULNERABILITIES=yes`
- **Pacote resultante**: `work/pkg/pfSense-pkg-layer7-X.Y.Z.pkg`

---

## Restrições do projeto

- foco em pfSense CE;
- pacote proprietario (EULA Systemup);
- distribuição por `.pkg` via GitHub Releases;
- sem software pago obrigatório;
- V1 sem MITM universal;
- V1 sem console central;
- V1 sem analytics pesado.

---

## Anti-padrões proibidos

- mudar vários subsistemas ao mesmo tempo;
- quebrar compatibilidade sem aviso;
- mexer em docs depois;
- deixar defaults indefinidos;
- esconder limitação técnica;
- adicionar dependência não avaliada.

---

## Quando parar e pedir validação humana

Pare e registre incerteza quando houver:
- dúvida sobre compatibilidade com pfSense CE;
- dúvida sobre empacotamento;
- mudança arquitetural grande;
- impacto relevante em segurança;
- decisão de fallback não fechada.

