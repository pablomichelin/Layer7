# F3.11 - Gate Formal para Reabrir a Readiness

## Finalidade

Este documento define o gate exacto para decidir se a readiness da F3.11
pode ser reaberta.

Estado formal preservado:

- `F3 continua aberta`;
- `F3.11 alinhada no license-server live`;
- `DR-05 continua como unico blocker real para fechar a F3`;
- `esta rodada nao faz push`.

Nota de actualizacao em `2026-04-14`:

- o gate antigo de cinco insumos fica preservado neste documento apenas como
  historico/compatibilidade;
- o gate corrente para continuar a F3 e executar `DR-05` no appliance com
  permissao suficiente, snapshot/rollback e evidencias por `run_id`;
- `DR-01`, `DR-03`, `DR-04` e `DR-06` ja nao bloqueiam a F3 no ambiente live
  activo.

---

## 1. Pergunta de gate

Pergunta unica:

> O `DR-05` do appliance ja tem evidencia real suficiente para fechar os
> cenarios locais obrigatorios da F3?

Se a resposta for `nao`, a F3 permanece aberta.

---

## 2. Insumos que precisam estar validos antes de repetir a readiness

| Insumo critico | Estado exigido antes da readiness | Prova minima exigida |
|----------------|-----------------------------------|----------------------|
| acesso read-only ao host live `192.168.100.244` | `entregue valido` | output bruto de SSH com host, directorio real, revisao Git e stack efectiva |
| query read-only ao PostgreSQL live | `entregue valido` | queries read-only com identidade da base, schema e tabelas administrativas |
| credencial admin autorizada com escopo formal | `entregue valido` | nota de escopo + login real + sessao valida |
| appliance pfSense com SSH, baseline e controlos legitimos | `entregue valido` | SSH funcional + baseline + prova real de snapshot/restore e controlos do lab |
| inventario real `LIC-A` a `LIC-F` | `entregue valido` | artefacto de inventario + prova objectiva em backend |

**Regra actual:** esta tabela e historica. O gate corrente nao exige voltar a
`5/5`; exige apenas fechar `DR-05` sem reabrir blockers ja saneados.

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

1. appliance sem permissao suficiente para os cenarios mutaveis;
2. ausencia de snapshot/rollback antes de relogio, offline, NIC/UUID ou
   clone/restore;
3. ausencia de evidencias por `run_id`;
4. qualquer novo drift objectivo que invalide o live/admin/inventario ja
   saneados.

---

## 5. O que ainda pode permanecer como risco documentado sem impedir a readiness

Os pontos abaixo podem continuar como risco documentado **sem** impedir a
repeticao da readiness, desde que nao sejam tratados como equivalencia nem
como autorizacao para campanha:

1. o branch local continuar ahead do remoto;
2. a inexistencia de push nesta rodada;
3. `DR-07` proveniencia exacta do deploy continuar aberto para F7;
4. a necessidade de manter o live observado como ambiente "provado por
   evidencia" e nao "assumido igual ao repositorio".

**Importante:** estes riscos nao impedem repetir a readiness, mas continuam a
proibir qualquer inferencia livre sobre live = local = remoto.

---

## 6. O que impede a readiness automaticamente

`NO-GO` automatico para repetir a readiness quando ocorrer qualquer um dos
casos abaixo:

- appliance sem controlos legitimos para `DR-05`;
- ausencia de permissao para reescrever `/usr/local/etc/layer7.lic` quando o
  cenario exigir;
- ausencia de snapshot/rollback para cenario mutavel;
- ausencia de evidencia bruta por `run_id`;
- drift novo que invalide a leitura actual do live.

---

## 7. O que impede a campanha automaticamente

Mesmo depois de a readiness ser repetida, a campanha continua `NO-GO`
automatico se qualquer um dos pontos abaixo persistir:

- `DR-05` permanecer sem `PASS` ou sem classificacao conclusiva aceitavel;
- qualquer cenario obrigatorio permanecer estruturalmente `BLOCKED`;
- aparecer drift novo que invalide o license-server live, auth/admin ou
  inventario ja saneados.

---

## 8. Leitura formal do drift de CORS nesta fase

O drift historico ja registado fica preservado:

- `/api/auth/login` live aceitou `Origin` externo com
  `Access-Control-Allow-Origin: *`;
- isso diverge do contrato canónico `same-origin only`;
- em `2026-04-14`, o ambiente activo voltou a responder `403` fail-closed
  para `Origin` externo;
- portanto, `DR-06` nao bloqueia a F3 no estado corrente.

---

## 9. Nota operacional de publicacao

Leitura factual desta rodada:

- o branch local entrou nesta rodada `ahead 23` do remoto;
- nao foi feito push;
- um push agora publicaria historico local acumulado;
- por isso, este gate e apenas documental-operacional e nao altera o estado
  de publicacao.

---

## 10. Decisao binaria

### `GO` para repetir a readiness

So existe `GO` quando:

1. `DR-05` tiver permissao suficiente no appliance;
2. snapshot/rollback estiverem garantidos antes de cenario mutavel;
3. evidencias por `run_id` estiverem registadas;
4. o estado de publicacao estiver anotado sem push.

### `NO-GO`

Qualquer falta nos pontos acima mantem:

- `F3 aberta`;
- `F3.11 alinhada no license-server live`;
- `DR-05 pendente`.
