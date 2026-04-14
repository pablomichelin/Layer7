# F3.11 - Criterio Canonico de Fecho de Ciclo

## Finalidade

Este documento define o que e um ciclo operacional da F3.11 e quando ele pode
ser considerado formalmente fechado.

Objectivo:

- impedir rodadas meio abertas;
- impedir resumo sem intake, sem ledger ou sem scorecard sincronizado;
- padronizar a identificacao futura dos ciclos;
- ligar cada ciclo as evidencias realmente recebidas.

Estado formal preservado:

- `F3 continua aberta`;
- `F3.11 alinhada no license-server live`;
- `DR-05 pendente`;
- `este documento nao fecha a F3`;
- `este documento nao abre campanha`.

Nota de actualizacao em `2026-04-14`:

- ciclos baseados no pacote antigo de cinco insumos permanecem validos como
  historico;
- o ciclo operacional corrente deve focar `DR-05` e nao reabrir
  host/DB/admin/inventario ja saneados sem drift novo.

---

## 1. Definicao de ciclo operacional F3.11

Um ciclo operacional F3.11 e a menor unidade completa de trabalho documental
ou live que:

1. parte de um estado herdado da trilha;
2. trata zero, um ou varios insumos/evidencias da rodada;
3. produz pelo menos uma decisao operacional verificavel;
4. actualiza os artefactos correntes obrigatorios;
5. fecha com conclusao binaria ou parcial explicita.

Sem estes cinco elementos, nao ha ciclo valido.

---

## 2. O que constitui abertura de ciclo

Um ciclo abre formalmente quando existir, no minimo:

1. `cycle_id` atribuido;
2. data/hora de abertura em UTC;
3. estado herdado registado;
4. motivo da rodada identificado:
   - `recepcao de insumo`
   - `triagem`
   - `complemento`
   - `revisao interna`
   - `readiness repetida`
5. nota do estado de publicacao local:
   - branch local
   - se houve ou nao push

Abrir apenas um intake sem `cycle_id` nao abre um ciclo completo; abre apenas
um registo parcial.

---

## 3. O que constitui ciclo incompleto

Um ciclo e incompleto quando pelo menos um destes pontos falha:

- abriu mas nao fechou;
- existe intake sem conclusao;
- houve triagem sem ledger;
- houve ledger sem actualizacao do registro mestre;
- houve actualizacao do registro mestre sem scorecard;
- houve scorecard sem nota sobre gate;
- faltou nota operacional de publicacao;
- faltou indicar se readiness e campanha continuam proibidas ou nao.

Ciclo incompleto nao pode ser tratado como progresso formal.

---

## 4. O que constitui ciclo fechado com blockers mantidos

Um ciclo fecha validamente com blockers mantidos quando:

1. o que chegou foi ausente, invalido ou parcial; ou
2. nao chegou nada novo, mas a rodada consolidou o bloqueio de forma
   rastreavel;
3. o ledger regista a decisao correcta;
4. o registro mestre e o scorecard continuam coerentes;
5. o gate foi marcado como `NO-GO` ou como "nao reavaliado por falta de
   condicao";
6. o ciclo explicita o proximo passo e quem precisa actuar.

Este e um fecho valido. Nao e fracasso documental.

---

## 5. O que constitui ciclo fechado com avancos reais

Um ciclo fecha com avancos reais quando:

1. pelo menos um insumo mudou para `entregue valido`; ou
2. uma readiness repetida foi concluida com output real; ou
3. um drift foi objectivamente reclassificado com base em evidencia nova;
4. o ledger registou a mudanca;
5. o registro mestre e o scorecard reflectem a mudanca;
6. o gate foi reavaliado quando aplicavel;
7. o fecho do ciclo mostra o novo estado da trilha sem ambiguidade.

Avanco real nao significa automaticamente `GO` para readiness nem para
campanha.

---

## 6. O que constitui ciclo invalido

Um ciclo e invalido quando ocorrer qualquer um dos casos abaixo:

- nao existe `cycle_id`;
- o ciclo cita evidencia sem intake ou sem output bruto identificavel;
- a scorecard foi actualizada antes do registro mestre;
- o gate foi revisto antes do ledger e do scorecard;
- o ciclo declara `GO` sem cumprir o gate formal;
- o ciclo omite se houve push;
- o ciclo omite se readiness/campanha continuam proibidas;
- o ciclo mistura entrega, triagem e decisao sem separar o que foi observado
  do que foi decidido.

Ciclo invalido deve ser corrigido antes de qualquer nova rodada.

---

## 7. Documentos que precisam estar preenchidos para um ciclo fechar

Para fecho valido, o ciclo precisa deixar sincronizados:

1. o proprio relatorio do ciclo;
2. todos os intakes tocados na rodada;
3. o ledger;
4. o registro mestre;
5. o scorecard;
6. o gate, quando houver condicao para reavaliacao ou quando for necessario
   registar explicitamente que nao houve condicao;
7. o drift registry, se surgiu ou mudou drift;
8. a nota operacional de publicacao.

Se algum destes pontos estiver em falta, o ciclo fica aberto ou invalido.

---

## 8. Como numerar e identificar ciclos futuros

Formato canonico:

`F3.11-CYCLE-YYYYMMDD-NN`

Exemplos:

- `F3.11-CYCLE-20260402-01`
- `F3.11-CYCLE-20260402-02`
- `F3.11-CYCLE-20260403-01`

Regras:

1. `YYYYMMDD` usa a data UTC de abertura;
2. `NN` e sequencial no dia;
3. o mesmo `cycle_id` deve aparecer no relatorio do ciclo e nas referencias
   cruzadas do ledger/intakes;
4. se a rodada evoluir de documental para readiness repetida, mantem-se o
   mesmo `cycle_id` se for a mesma unidade operacional; se houver pausa e
   retomada noutra rodada, abre-se um novo ciclo.

---

## 9. Como relacionar o ciclo as evidencias recebidas

Cada ciclo deve listar explicitamente:

- quais insumos entraram na rodada;
- quais intakes correspondem a cada insumo;
- qual evidencia bruta sustentou a triagem;
- qual microdecisao foi registada no ledger;
- qual impacto ficou no registro mestre e no scorecard;
- se houve drift novo;
- se o gate foi ou nao reavaliado.

Sem esta ligacao, o ciclo nao fecha com lastro suficiente.

---

## 10. Regra final

Um ciclo F3.11 so esta formalmente fechado quando outro operador consegue ler:

1. o que chegou;
2. o que foi decidido;
3. qual e o estado corrente;
4. se a readiness mudou;
5. se a campanha continua proibida;
6. se houve ou nao push.
