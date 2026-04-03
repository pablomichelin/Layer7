# F3.11 - Gate Formal para Reabrir a Readiness

## Finalidade

Este documento define o gate exacto para decidir se a readiness da F3.11
pode ser reaberta.

Estado formal preservado:

- `F3 continua aberta`;
- `F3.11 continua bloqueada ate cumprimento integral dos pre-requisitos criticos`;
- `esta rodada nao corrige o drift de CORS`;
- `esta rodada nao faz push`.

---

## 1. Pergunta de gate

Pergunta unica:

> Os cinco insumos externos criticos estao `entregue valido`, com evidencia
> minima registada e aceite formal documentado?

Se a resposta for `nao`, a readiness **nao** pode ser repetida.

---

## 2. Insumos que precisam estar validos antes de repetir a readiness

| Insumo critico | Estado exigido antes da readiness | Prova minima exigida |
|----------------|-----------------------------------|----------------------|
| acesso read-only ao host live `192.168.100.244` | `entregue valido` | output bruto de SSH com host, directorio real, revisao Git e stack efectiva |
| query read-only ao PostgreSQL live | `entregue valido` | queries read-only com identidade da base, schema e tabelas administrativas |
| credencial admin autorizada com escopo formal | `entregue valido` | nota de escopo + login real + sessao valida |
| appliance pfSense com SSH, baseline e controlos legitimos | `entregue valido` | SSH funcional + baseline + prova real de snapshot/restore e controlos do lab |
| inventario real `LIC-A` a `LIC-F` | `entregue valido` | artefacto de inventario + prova objectiva em backend |

**Regra:** a readiness nao reabre com `4/5`. O gate e binario.

---

## 3. Evidencias minimas que precisam existir em repositorio/documentacao

Antes de repetir a readiness, devem existir e estar preenchidos:

1. pelo menos um registo de intake por insumo em
   [`../05-runbooks/f3-11-evidence-intake-template.md`](../05-runbooks/f3-11-evidence-intake-template.md)
   ou copia operacional derivada dele;
2. classificacao actualizada de cada insumo na
   [`f3-11-input-acceptance-matrix.md`](f3-11-input-acceptance-matrix.md);
3. referencia ao output bruto usado para validar host, DB, admin, appliance
   e inventario;
4. nota objectiva de escopo autorizado para a credencial administrativa;
5. nota operacional de publicacao/revisao indicando que o branch local
   continua ahead do remoto e que nao houve push nesta rodada.

Sem estes cinco grupos de evidencia, a readiness nao reabre.

---

## 4. Bloqueios que continuam nao negociaveis

Os itens abaixo impedem a readiness automaticamente:

1. qualquer um dos cinco insumos ainda em `nao entregue`,
   `entregue invalido` ou `entregue parcial`;
2. impossibilidade de provar revisao exacta e stack efectiva do ambiente
   observado;
3. impossibilidade de provar schema live e estado das tabelas
   `admin_sessions`, `admin_audit_log` e `admin_login_guards`;
4. credencial administrativa sem owner e sem escopo formal;
5. appliance sem SSH funcional, sem baseline ou sem controlos legitimos;
6. inventario `LIC-A` a `LIC-F` sem prova objectiva no backend.

---

## 5. O que ainda pode permanecer como risco documentado sem impedir a readiness

Os pontos abaixo podem continuar como risco documentado **sem** impedir a
repeticao da readiness, desde que nao sejam tratados como equivalencia nem
como autorizacao para campanha:

1. o branch local continuar ahead do remoto;
2. a inexistencia de push nesta rodada;
3. a necessidade de manter o live observado como ambiente "provado por
   evidencia" e nao "assumido igual ao repositorio".

**Importante:** estes riscos nao impedem repetir a readiness, mas continuam a
proibir qualquer inferencia livre sobre live = local = remoto.

---

## 6. O que impede a readiness automaticamente

`NO-GO` automatico para repetir a readiness quando ocorrer qualquer um dos
casos abaixo:

- falta de um dos cinco insumos criticos;
- prova incompleta da stack live;
- prova incompleta do PostgreSQL live;
- escopo administrativo nao formalizado;
- appliance sem controlos legitimos;
- inventario parcial ou inconsistente.

---

## 7. O que impede a campanha automaticamente

Mesmo depois de a readiness ser repetida, a campanha continua `NO-GO`
automatico se qualquer um dos pontos abaixo persistir:

- qualquer insumo critico deixar de estar `entregue valido`;
- a readiness repetida concluir que o deploy observado continua sem prova de
  revisao, schema ou sessao administrativa;
- o controlo real do contrato `409` vs `403` continuar incompativel com o
  contrato canónico da F3;
- o drift de CORS/same-origin continuar presente no ambiente observado e a
  readiness o classificar como blocker activo;
- qualquer cenario obrigatorio permanecer estruturalmente `BLOCKED`.

---

## 8. Leitura formal do drift de CORS nesta fase

O drift ja registado continua a valer:

- `/api/auth/login` live aceitou `Origin` externo com
  `Access-Control-Allow-Origin: *`;
- isso diverge do contrato canónico `same-origin only`;
- esta rodada **nao** corrige esse drift;
- a repeticao da readiness deve considerá-lo explicitamente no ambiente
  observado;
- se o drift persistir como blocker no ambiente candidato, a campanha nao
  abre.

---

## 9. Nota operacional de publicacao

Leitura factual desta rodada:

- o branch local entrou nesta rodada `ahead 20` do remoto;
- nao foi feito push;
- um push agora publicaria historico local acumulado;
- por isso, este gate e apenas documental-operacional e nao altera o estado
  de publicacao.

---

## 10. Decisao binaria

### `GO` para repetir a readiness

So existe `GO` quando:

1. os cinco insumos estiverem `entregue valido`;
2. as evidencias minimas estiverem registadas;
3. o aceite estiver documentado;
4. o estado de publicacao estiver anotado sem push.

### `NO-GO`

Qualquer falta nos pontos acima mantem:

- `F3 aberta`;
- `F3.11 bloqueada`.
