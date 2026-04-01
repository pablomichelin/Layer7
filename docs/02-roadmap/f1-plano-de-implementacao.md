# F1 - Plano de implementacao futura

## Finalidade

Este documento organiza a implementacao futura da F1 em subfases seguras.
Nao implementa nada. Apenas define ordem, dependencias, gates, rollback
conceitual e testes minimos.

Referencias obrigatorias:

- [`roadmap.md`](roadmap.md)
- [`../03-adr/ADR-0003-hierarquia-oficial-de-distribuicao.md`](../03-adr/ADR-0003-hierarquia-oficial-de-distribuicao.md)
- [`../03-adr/ADR-0004-cadeia-de-confianca-dos-artefatos.md`](../03-adr/ADR-0004-cadeia-de-confianca-dos-artefatos.md)
- [`../03-adr/ADR-0005-pipeline-seguro-de-blacklists.md`](../03-adr/ADR-0005-pipeline-seguro-de-blacklists.md)
- [`../03-adr/ADR-0006-fallback-e-degradacao-segura.md`](../03-adr/ADR-0006-fallback-e-degradacao-segura.md)
- [`../01-architecture/f1-arquitetura-de-confianca.md`](../01-architecture/f1-arquitetura-de-confianca.md)

---

## 1. Pre-requisitos

- F0 documental consolidada
- ADRs da F1 aceites
- backlog actualizado
- inventario das dependencias externas criticas conhecido
- acordo claro sobre:
  - repositório de origem
  - canal publico de distribuicao
  - papel do builder
  - politica de signing

---

## 2. Ordem segura de implementacao

### Subfase F1.1 - Contrato de distribuicao

**Objectivo:** fechar primeiro a hierarquia oficial de distribuicao.

**Inclui:**

- alinhar docs e scripts ao `.pkg` como artefacto oficial;
- deixar explicito o papel do repositorio de origem e do canal publico;
- remover a dependencia conceptual de `.txz` como default.

**Risco principal:** continuar com ambiguidade operacional e publicar por
caminho errado.

**Rollback conceitual:** manter documentacao historica preservada, sem apagar
o legado durante a transicao.

**Teste minimo esperado no futuro:**

- um maintainer novo consegue apontar o artefacto oficial e o URL oficial sem
  ambiguidade.

**Checkpoint de execucao:** concluida em `2026-04-01` com `.pkg` como
artefacto oficial, scripts `install.sh`/`uninstall.sh` publicados por tag e
o legado `.txz` explicitamente removido da trilha normativa.

### Subfase F1.2 - Manifesto, checksum e assinatura de release

**Objectivo:** tornar autenticidade e integridade verificaveis.

**Inclui:**

- formato de manifesto;
- geracao de hash oficial;
- assinatura destacada;
- chave publica oficial de release;
- separacao builder/signer.

**Dependencia:** F1.1 concluida.

**Risco principal:** criar assinatura sem contrato claro de publicacao.

**Rollback conceitual:** nao promover nova release sem o conjunto completo;
continuar a usar a ultima release valida conhecida.

**Teste minimo esperado no futuro:**

- validacao bem-sucedida de `.pkg` correcta;
- rejeicao de manifesto adulterado;
- rejeicao de checksum divergente;
- rejeicao com chave publica errada.

**Checkpoint de execucao:** concluida em `2026-04-01` com manifesto
`release-manifest.v1.txt`, assinatura destacada Ed25519 via OpenSSL,
public key de verificacao publicada no release e separacao builder/signer
materializada em scripts distintos.

### Subfase F1.3 - Politica de origem e mirror de blacklists

**Objectivo:** cortar a dependencia directa de feed insegura.

**Inclui:**

- definir origem confiavel de snapshots aprovadas;
- definir mecanismo de mirror/cache;
- definir metadados de snapshot;
- definir ultima versao valida.

**Dependencia:** F1.2 concluida ou, no minimo, esquema de manifesto decidido.

**Risco principal:** manter HTTP directo como caminho “temporario” e nunca
  fechar o risco.

**Rollback conceitual:** manter feed antiga fora do caminho automatico e usar
apenas snapshot aprovada ou ultima valida.

**Teste minimo esperado no futuro:**

- rejeicao de origem nao-HTTPS;
- rejeicao de snapshot sem assinatura;
- preservacao da ultima snapshot valida em falha.

**Checkpoint de execucao:** concluida em `2026-04-01` com manifesto dedicado
`layer7-blacklists-manifest.v1.txt`, public key propria empacotada no pacote,
origem oficial `downloads.systemup.inf.br`, mirror controlado em GitHub
Releases, cache local em `.cache`, estado activo em `.state` e
`--restore-lkg` para reutilizar a ultima snapshot validada.

### Subfase F1.4 - Matriz de fallback e degradacao segura

**Objectivo:** traduzir a filosofia de seguranca em comportamento por componente.

**Inclui:**

- matriz fail-open/fail-closed;
- logs de degradacao;
- regras de “nao promover”;
- integracao com runbooks e checklist.

**Resultado materializado em `2026-04-01`:**

- `install.sh` versionado passa a validar manifesto, assinatura e checksum
  antes do `pkg add`, falhando em `fail-closed` perante release suspeita;
- `update-blacklists.sh` passa a materializar `healthy`, `degraded` e
  `fail-closed` em `.state/fallback.state`, preservando apenas material
  previamente validado;
- a arquitectura e os manuais passam a ter matriz explicita por componente.

**Dependencia:** F1.1 a F1.3 desenhadas.

**Risco principal:** implementar validacao sem politica uniforme de falha.

**Rollback conceitual:** manter comportamento actual documentado ate que o novo
comportamento esteja fechado por componente.

**Teste minimo esperado no futuro:**

- artefacto invalido nao instala;
- blacklist suspeita nao substitui a ultima valida;
- licenca nova invalida nao substitui estado seguro anterior;
- logs deixam claro se foi indisponibilidade ou rejeicao por integridade.

### Subfase F1.5 - Fecho documental e gates da F1

**Objectivo:** consolidar docs, runbooks e gates antes de abrir F2/F4.

**Inclui:**

- actualizar manuais operacionais afectados;
- actualizar checklist e runbooks;
- fechar pendencias de backlog F1;
- registar estado seguro em `CORTEX.md`.

**Dependencia:** subfases anteriores concluídas.

**Risco principal:** alterar a base tecnica e deixar governanca desfasada.

**Rollback conceitual:** se a documentacao final nao fechar, a F1 nao e dada
como concluida.

**Teste minimo esperado no futuro:**

- novo agente explica a cadeia de confianca e o comportamento em falha lendo
  poucos documentos canónicos.

**Estado final:** o fecho documental previsto para esta subfase foi absorvido
no mesmo bloco da F1.4 para evitar abrir uma subfase separada apenas
burocratica. Com isso, a F1 fica encerrada e a proxima fase elegivel passa a
ser a F2.

---

## 3. Gates da F1

### Gate de entrada

- artefacto oficial do produto esta definido;
- ambiguidades historicas principais foram identificadas;
- backlog F1 priorizado;
- ADRs F1 aceites.

### Gate de execucao

- nenhuma implementacao da F1 mistura distribuicao, blacklists e fallback sem
  ordem segura;
- cada subfase tem teste minimo definido;
- builder, signer e canal publico nao sao confundidos.

### Gate de saida

- cadeia de confianca do artefacto fica auditavel ponta a ponta;
- contrato oficial de distribuicao fica inequívoco;
- blacklists deixam de depender de feed automatica insegura;
- politica de fallback segura fica explicita por componente;
- runbooks e docs canónicas ficam sincronizados.

---

## 4. Riscos por implementacao fora de ordem

| Implementar fora de ordem | Risco |
|---------------------------|-------|
| assinar release antes de fechar contrato de distribuicao | assinar o canal errado ou o conjunto errado |
| mexer em blacklists antes de fechar origem confiavel | trocar HTTP directo por outra dependencia nao governada |
| mexer em fallback antes da matriz por componente | comportamento inconsistente e dificil de auditar |
| misturar F1 com F2/F4 na mesma entrega | perda de isolamento de risco e rollback confuso |

---

## 5. Dependencias entre fases

- F1 prepara F2 ao definir fronteiras de confianca e segredos
- F1 prepara F4 ao definir pipeline de blacklists e autenticidade de artefactos
- F1 prepara F5 ao explicitar o que precisa de teste de integridade e rejeicao

---

## 6. Criterio pratico de encerramento do planejamento

O planejamento da F1 pode ser dado como fechado quando:

- ADRs obrigatorios existem e estao coerentes;
- arquitectura consolidada existe;
- plano de implementacao em subfases existe;
- roadmap, backlog, checklist e `CORTEX.md` apontam para a mesma ordem segura.
