# F3.10 — Matriz de Drift Operacional Pos-F3.9

## Finalidade

Este documento fecha a parte canónica da **F3.10** sem abrir F4/F5/F6/F7.

Objectivo desta subfase:

- classificar de forma objectiva os **drifts operacionais reais** observados
  na campanha F3.9;
- separar drift de deploy, drift de contrato HTTP e blocker de ambiente;
- indicar o impacto desses desvios sobre os cenarios da F3;
- definir a forma correcta de saneamento **sem corrigir codigo no escuro**.

**Regra central da F3.10:** drift observado vira artefacto de governanca,
nao desculpa para reinterpretar `FAIL` como `PASS` nem para alterar o live
sem plano.

**Nota de estado actual (`2026-04-14`):** esta matriz preserva os drifts
observados na F3.9. O checkpoint posterior saneou os drifts administrativos
do live e deixou `DR-05` do appliance como blocker real remanescente da F3.

---

## 1. Matriz canónica de drift observada na F3.9

| ID | Categoria | Evidencia observada na F3.9 | Cenarios impactados | Severidade | Bloqueia campanha | Bloqueia fechamento da F3 | Forma correcta de saneamento |
|----|-----------|-----------------------------|---------------------|------------|-------------------|---------------------------|------------------------------|
| DO-01 | Drift de schema | o schema live observado nao contem `admin_sessions`, `admin_audit_log` nem `admin_login_guards` | S02, S04, S05, S06, S10 e S11 perdem auditoria/sessao; toda a leitura administrativa fica sob duvida | Critica | sim | sim | alinhar o deploy ao contrato canónico da F2.2/F2.3 ou substituir o ambiente por outro que prove esse schema antes da F3.11 |
| DO-02 | Drift de rotas/contrato HTTP | `POST /api/activate` respondeu `403` onde a F3.8 exige `409` em cenarios obrigatorios | S03 falhou; S07 tambem ficou fora do contrato esperado; qualquer leitura de bloqueio online fica comprometida | Critica | sim | sim | provar qual revisao/ambiente esta a servir `activate` e alinhar o deploy escolhido ao contrato canónico antes de nova campanha |
| DO-03 | Drift de autenticacao/admin | a campanha nao dispunha de credencial administrativa autorizada para exercer S04-S06/S10 no deploy real | S04, S05, S06 e S10 ficaram bloqueados; consultas administrativas ficaram limitadas | Critica | sim | sim | obter credencial administrativa autorizada com escopo explicito ou usar ambiente alternativo autorizado; sem isso a F3.11 nao deve abrir |
| DO-04 | Drift de inventario de licencas | nao havia no inventario live uma licenca activa sem bind para S01 nem pool minimo em estados dedicados por cenario | S01 ficou bloqueado; S07-S09, S11-S13 ficaram sem pool confiavel para prova limpa | Alta | sim | sim | montar inventario minimo dedicado por cenario antes da campanha e registar o mapeamento licenca->cenario no manifesto |
| DO-05 | Drift de ambiente de appliance | nao havia appliance pfSense autenticavel, com SSH valido, baseline recolhivel e controlo de relogio/offline/NIC | S01, S02, S07, S08, S09, S11, S12 e S13 ficaram bloqueados ou inconclusivos | Critica | sim | sim | disponibilizar appliance/lab autenticavel com snapshot e controlo de ambiente antes da F3.11 |

---

## 2. Detalhe operativo por drift

### DO-01 — Drift de schema

- **Evidencia objectiva:** ausencia observada de `admin_sessions`,
  `admin_audit_log` e `admin_login_guards` no banco live.
- **Impacto directo:** impede provar sessao stateful, auditoria
  administrativa e trilha de reemissao exigidas pela F2.2/F2.3/F3.5.
- **Leitura correcta:** isto nao e "lacuna documental"; e divergencia real
  entre deploy observado e contrato canónico do repositorio.
- **Saneamento correcto:** alinhar o ambiente de campanha ao schema esperado
  ou declarar explicitamente que o live nao e elegivel para a F3.11.

### DO-02 — Drift de rotas/contrato HTTP

- **Evidencia objectiva:** `activate` respondeu `403` em cenarios onde o gate
  F3.8 exige `409`.
- **Impacto directo:** S03 e S07 deixam de ser interpretaveis pelo gate
  actual; qualquer `FAIL` adicional no caminho online pode ser falso se o
  ambiente continuar divergente.
- **Leitura correcta:** o contrato da F3.8 nao pode ser rebaixado para
  encaixar no live; o live e que precisa de ser alinhado ou descartado para a
  campanha de fechamento.
- **Saneamento correcto:** capturar a revisao efectiva do deploy e provar a
  aderencia do endpoint antes de reexecutar cenarios obrigatorios.

### DO-03 — Drift de autenticacao/admin

- **Evidencia objectiva:** ausencia de credencial administrativa autorizada
  para login e mutacoes reais na campanha.
- **Impacto directo:** metade administrativa da F3 torna-se inexecutavel sem
  forcar accoes em producao.
- **Leitura correcta:** isto e blocker de ambiente/governanca, nao bug
  automaticamente atribuivel ao produto.
- **Saneamento correcto:** obter credencial autorizada com registo de escopo
  e janela de uso; sem isso a campanha deve ser abortada antes dos cenarios
  administrativos.

### DO-04 — Drift de inventario de licencas

- **Evidencia objectiva:** ausencia de licenca activa sem bind no inventario
  live e ausencia de pool minimo em estados dedicados por cenario.
- **Impacto directo:** S01 nao arranca limpo; cenarios de expiracao,
  revogacao, coexistencia e fingerprint ficam contaminados por reuso arriscado
  de licencas.
- **Leitura correcta:** sem inventario dedicado nao existe baseline confiavel
  para a campanha final.
- **Saneamento correcto:** preparar licencas dedicadas por pool e registar o
  mapeamento antes da F3.11.

### DO-05 — Drift de ambiente de appliance

- **Evidencia objectiva:** ausencia de appliance pfSense autenticavel e de
  controlo legitimo sobre SSH, relogio, offline e mudanca de NIC/UUID.
- **Impacto directo:** a metade local da F3 fica por provar; S09 ficou apenas
  `INCONCLUSIVE` e varios outros cenarios nem chegaram a ser exercitados.
- **Leitura correcta:** isto nao se corrige no backend nem por interpretacao
  optimista do relatorio.
- **Saneamento correcto:** preparar o lab/appliance antes da F3.11, com
  snapshot e baseline recolhivel.

---

## 3. Priorizacao de saneamento

Ordem minima para remover os drifts antes da F3.11:

1. **DO-02** e **DO-01**: sem contrato HTTP e schema coerentes, qualquer nova
   rodada gera falso `FAIL`.
2. **DO-03**: sem credencial administrativa autorizada, a metade obrigatoria
   da F3 continua bloqueada por governanca.
3. **DO-05**: sem appliance real e controlado, a metade local da F3 continua
   bloqueada.
4. **DO-04**: sem inventario minimo, a campanha fica dependente de improviso.

---

## 4. Regra formal de uso

- nenhum drift aqui registado deve ser "corrigido" durante a campanha;
- se um drift persistir no dia da F3.11, o correcto e abortar a campanha ou
  marcar os cenarios afectados como `BLOCKED`;
- a F3 so pode fechar quando os drifts criticos deixarem de existir no
  ambiente efectivamente usado para a campanha real.
