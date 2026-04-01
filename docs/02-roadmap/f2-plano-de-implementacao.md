# F2 - Plano de implementacao futura

## Finalidade

Este documento organiza a implementação da F2 em blocos pequenos, seguros e
reversíveis. Ele continua a definir ordem, dependências, gates, rollback
conceitual e testes mínimos, e passa a registar os checkpoints
materializados da F2.1 e da F2.2.

Referências obrigatórias:

- [`roadmap.md`](roadmap.md)
- [`../03-adr/ADR-0007-publicacao-segura-license-server.md`](../03-adr/ADR-0007-publicacao-segura-license-server.md)
- [`../03-adr/ADR-0008-autenticacao-e-sessao-license-server.md`](../03-adr/ADR-0008-autenticacao-e-sessao-license-server.md)
- [`../03-adr/ADR-0009-protecao-superficie-administrativa-license-server.md`](../03-adr/ADR-0009-protecao-superficie-administrativa-license-server.md)
- [`../03-adr/ADR-0010-integridade-transacional-e-validacao-crud-license-server.md`](../03-adr/ADR-0010-integridade-transacional-e-validacao-crud-license-server.md)
- [`../01-architecture/f2-arquitetura-license-server.md`](../01-architecture/f2-arquitetura-license-server.md)
- [`../10-license-server/PLANO-LICENSE-SERVER.md`](../10-license-server/PLANO-LICENSE-SERVER.md)
- [`../10-license-server/MANUAL-USO-LICENCAS.md`](../10-license-server/MANUAL-USO-LICENCAS.md)

---

## 1. Pré-requisitos

- F1 encerrada no checkpoint `af6b770`
- arquitetura F2 consolidada
- ADRs 0007 a 0010 aceitos
- inventário real do deploy atual conhecido
- clareza sobre:
  - edge proxy público
  - origin privado `8445`
  - segredos em uso
  - bootstrap do admin
  - rotas públicas vs administrativas

## 1.1 Checkpoint actual de execução

- **F2.1 concluída em `2026-04-01`:** `443/TLS` passa a ser o unico canal
  publico oficial, `8445` fica preso ao loopback por defeito, e a
  documentacao operacional passa a explicitar edge proxy, certificado,
  headers minimos e troubleshooting do origin privado.
- **F2.2 concluída em `2026-04-01`:** a autenticacao administrativa passa a
  operar com sessao stateful no backend, cookie `HttpOnly + Secure +
  SameSite=Strict`, expiracao ociosa/absoluta, renovacao controlada, logout
  com invalidacao real e remocao do JWT em `localStorage` da trilha activa.
- **Próxima subfase elegível:** `F2.3 — Proteção da superfície administrativa`

---

## 2. Ordem segura de implementação

### Subfase F2.1 - Publicação segura, TLS e fronteiras de rede

**Status actual:** concluida em `2026-04-01`

**Objectivo:** fechar primeiro o canal oficial de publicação.

**Inclui:**

- política real de `443/TLS` público;
- redirect `HTTP -> HTTPS` na borda;
- restrição de `8445` a origin privado;
- revisão de headers mínimos;
- prova de que `3001` e `5432` não ficam expostos.

**Dependência:** arquitetura F2 e ADR-0007.

**Risco principal:** endurecer auth/session em canal ainda ambíguo.

**Rollback conceitual:** manter o origin privado apenas para a borda
controlada; nunca abrir fallback público directo.

**Teste mínimo esperado:**

- `https://license.systemup.inf.br` responde com TLS válido;
- `http://license.systemup.inf.br` redireciona para HTTPS;
- `8445` não fica acessível publicamente;
- `/api/health` continua funcional conforme política definida.

### Subfase F2.2 - Autenticação e sessão administrativa

**Status actual:** concluida em `2026-04-01`

**Objectivo:** substituir token em `localStorage` por sessão segura.

**Inclui:**

- storage de sessão no backend;
- cookie `HttpOnly/Secure/SameSite=Strict`;
- endpoint de logout;
- expiração e renovação controladas;
- remoção do bearer manual no frontend.

**Dependência:** F2.1 concluída.

**Risco principal:** proteger sessão sobre publicação ainda não endurecida.

**Rollback conceitual:** manter login indisponível em vez de cair para modelo
inseguro.

**Teste mínimo esperado:**

- sessão nasce apenas em HTTPS;
- cookie não é legível por JavaScript;
- logout invalida sessão no servidor;
- sessão expirada deixa rota privada em fail-closed.

### Subfase F2.3 - Proteção da superfície administrativa

**Objectivo:** endurecer login, rotas administrativas, CORS e auditoria.

**Inclui:**

- rate limit de login;
- brute force protection;
- CORS same-origin;
- política de erro;
- logging mínimo de auth/admin/activate.

**Dependência:** F2.2 concluída.

**Risco principal:** deixar sessão segura, mas expor a superfície a abuso e
cross-origin desnecessário.

**Rollback conceitual:** negar acesso e devolver erro explícito; sem abrir
origins ou reduzir limites silenciosamente.

**Teste mínimo esperado:**

- login recebe `429` ao exceder limite;
- origin fora da allowlist falha fechado;
- erros de auth não enumeram utilizador;
- eventos mínimos aparecem no log.

### Subfase F2.4 - Validação de input e integridade transacional

**Objectivo:** impedir estado parcial e CRUD ambíguo.

**Inclui:**

- schemas de validação por rota;
- códigos HTTP coerentes;
- transações em activate, delete/archive e operações multi-query;
- política de delete seguro/arquivo lógico.

**Dependência:** F2.3 concluída.

**Risco principal:** continuar a corromper estado ou apagar histórico mesmo com
auth endurecida.

**Rollback conceitual:** falhar fechado e manter o estado anterior do banco.

**Teste mínimo esperado:**

- payload inválido não altera banco;
- activação concorrente mantém consistência;
- delete/archive não remove histórico indevidamente;
- erro numa query faz rollback do conjunto.

### Subfase F2.5 - Segredos, bootstrap, backup/restore e runbooks

**Objectivo:** fechar o hardening operacional da stack.

**Inclui:**

- política de segredos e ownership;
- tratamento do `seed` e da credencial bootstrap;
- backup/restore mínimo do PostgreSQL e segredos;
- runbooks de arranque, incidente e recuperação.

**Dependência:** F2.1 a F2.4 concluídas.

**Risco principal:** serviço tecnicamente endurecido, mas operacionalmente
dependente de memória oral.

**Rollback conceitual:** rollback documental e operacional para o estado
anterior conhecido, sem expor segredos nem reabrir canais inseguros.

**Teste mínimo esperado:**

- backup gera artefacto restaurável;
- restore mínimo recompõe ambiente;
- bootstrap do admin fica claro e rotacionável;
- runbook permite tratar incidente simples sem improviso.

---

## 3. Gates da F2

### Gate de entrada

- F1 concluída e estável
- canal público e origin do license server identificados
- riscos de auth/session, CORS e CRUD mapeados
- ADRs 0007 a 0010 aceitos

### Gate de execução

- nenhuma subfase da F2 mistura hardening do server com F3/F4/F5
- sessão/admin não são endurecidos antes da fronteira de publicação
- CRUD não é endurecido sem política de auth e auditoria minimamente fechada

### Gate de saída

- canal público seguro e inequívoco;
- sessão administrativa segura e revogável;
- login e rotas sensíveis protegidos por rate limit/auditoria/CORS coerente;
- CRUD e activação com validação e transação suficientes;
- segredos, bootstrap e recuperação deixam de depender de memória oral.

---

## 4. Riscos por implementação fora de ordem

| Implementar fora de ordem | Risco |
|---------------------------|-------|
| endurecer sessão antes de fechar publicação/TLS | autenticação forte sobre canal ainda ambíguo |
| mexer em CRUD antes de auth/session/CORS | superfície administrativa continua exposta apesar de regras novas |
| fechar segredos/runbooks antes de decidir modelo de sessão | runbook e operação ficam desalinhados do comportamento real |
| misturar F2 com F3 | modelo de activação/licenciamento entra cedo demais e confunde rollback |

---

## 5. O que fica explicitamente fora da F2

- estados completos de activação/revogação/offline/grace da F3;
- alterações no `.lic` ou na chave pública embutida no daemon;
- hardening de blacklists, package, daemon ou release chain;
- reorganização estrutural do repositório;
- telemetria ampla e release engineering.

---

## 6. Dependências entre fases

- F2 prepara F3 ao deixar o server e a superfície administrativa confiáveis
- F2 prepara F5 ao explicitar testes de auth, rate limit, CORS e transação
- F2 não substitui F3: apenas endurece o servidor e a operação

---

## 7. Critério prático de encerramento do planejamento

O planejamento da F2 pode ser dado como fechado quando:

- os ADRs obrigatórios existem e estão coerentes;
- a arquitetura consolidada da F2 existe;
- a ordem segura de implementação está explicitada;
- roadmap, backlog, checklist, ADR index e `CORTEX.md` apontam para a mesma
  fase e para o mesmo gate;
- fica claro o que deve falhar fechado, o que pode apenas degradar e o que
  está fora de escopo.
