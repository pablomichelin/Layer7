# F3.8 — Gate de Fechamento e Campanha Final de Validacao

## Finalidade

Este documento fecha a parte canónica da **F3.8** sem abrir F4/F5/F6/F7.

Objectivo desta subfase:

- transformar a matriz da F3.6 e o pack da F3.7 num **gate oficial de
  fechamento** da F3;
- deixar inequívoco quais cenarios exigem evidencia real para a F3 poder
  fechar;
- fixar criterios objectivos de `PASS`, `FAIL`, `INCONCLUSIVE` e `BLOCKED`
  por cenario;
- padronizar a leitura final da campanha sem maquilhar ausencia de prova;
- manter a F3 honestamente aberta enquanto faltar evidencia obrigatoria.

Documento complementar desta subfase:

- [`f3-validacao-manual-evidencias.md`](f3-validacao-manual-evidencias.md)
  continua a dizer **o que precisa de ser executado**;
- [`f3-pack-operacional-validacao.md`](f3-pack-operacional-validacao.md)
  continua a dizer **como recolher e organizar** a evidencia bruta;
- [`../tests/templates/f3-validation-campaign-report.md`](../tests/templates/f3-validation-campaign-report.md)
  passa a ser o template canónico do **relatorio final da campanha**;
- `scripts/license-validation/init-f3-validation-campaign.sh` passa a ser o
  helper shell opcional e barato para materializar uma campanha fora do
  caminho critico do produto.

**Regra central da F3.8:** robustez em codigo/contrato **nao** equivale a F3
fechada. A F3 so pode fechar quando a campanha real produzir evidencia
minima suficiente e um veredito final auditavel.

---

## 1. Relacao entre F3.6, F3.7 e F3.8

### F3.6

- define a matriz manual de cenarios;
- fixa pre-requisitos, comandos e evidencia minima;
- distingue o que e backend-only do que exige appliance/lab.

### F3.7

- organiza a recolha por `run_id`;
- fixa nomes de ficheiros e estados por cenario;
- fornece template por cenario e helper de exportacao do backend.

### F3.8

- decide o que conta como evidencia suficiente para **fechar** ou **nao
  fechar** a F3;
- fixa o relatorio unico de campanha;
- separa pendencia bloqueante de pendencia nao bloqueante.

Leitura oficial:

- F3.6 = matriz de execucao;
- F3.7 = pack operacional;
- F3.8 = gate de saida.

---

## 2. Gate oficial de fechamento da F3

### 2.1 O que pode fechar a F3

A F3 so pode ser tratada como **fechavel** quando, na mesma campanha:

1. existir um unico relatorio final preenchido a partir de
   `docs/tests/templates/f3-validation-campaign-report.md`;
2. todos os cenarios **Obrigatorios** da F3 estiverem com veredito `PASS`;
3. cada `PASS` obrigatorio apontar para evidencia bruta suficiente no mesmo
   `run_id`;
4. nao existir contradicao aberta entre:
   - resposta CLI/API;
   - estado persistido/auditoria do backend;
   - estado local do appliance, quando aplicavel;
5. os riscos remanescentes ficarem classificados no relatorio como
   bloqueantes ou nao bloqueantes, sem esconder lacuna de prova.

### 2.2 O que impede fechar a F3

A F3 **nao pode fechar** se ocorrer pelo menos uma das situacoes abaixo:

1. qualquer cenario obrigatorio ficar em `FAIL`;
2. qualquer cenario obrigatorio ficar em `INCONCLUSIVE`;
3. qualquer cenario obrigatorio ficar em `BLOCKED`;
4. faltar evidencia minima exigida para um cenario obrigatorio;
5. faltar o relatorio final da campanha;
6. surgir contradicao material entre evidencia bruta e veredito declarado;
7. a conclusao final assumir `PASS` por ausencia de prova.

### 2.3 Pendencias bloqueantes vs nao bloqueantes

**Bloqueantes para fechar a F3:**

- qualquer obrigatorio fora de `PASS`;
- qualquer evidencia minima ausente num obrigatorio;
- qualquer contradicao nao resolvida entre backend e appliance;
- qualquer divergencia que aponte regressao real no contrato da F3.

**Nao bloqueantes para fechar a F3, desde que documentados no relatorio:**

- `S10` nao executado, `BLOCKED` ou `INCONCLUSIVE`, porque continua
  classificado como **Desejavel** e nao como obrigatorio;
- limites ja assumidos como fora de escopo da F3, se o comportamento
  observado continuar aderente ao que ja esta formalizado:
  - sem `latest only`;
  - sem revogacao offline forte;
  - sem rebind governado;
  - sem versionamento consumido pelo daemon.

**Excepcao importante:** se `S10` for executado e revelar `FAIL` real, a F3
nao fecha ate haver triagem formal, porque a campanha passou a mostrar
contradicao efectiva dentro do escopo observado.

---

## 3. Matriz objectiva de decisao por cenario

### 3.1 Cenarios obrigatorios

| ID | Cenario | PASS | FAIL | INCONCLUSIVE | BLOCKED | Impacto no gate |
|----|---------|------|------|--------------|---------|-----------------|
| S01 | Activacao inicial valida | `activate` com sucesso, bind preenchido, `activated_at` preenchido e auditoria `initial_issue` presente | activacao falha, bind nao persiste ou auditoria esperada nao existe | CLI/API indica sucesso, mas falta estado persistido ou auditoria minima | nao existe licenca desbindada valida, appliance indisponivel ou sem acesso administrativo | bloqueante |
| S02 | Re-activacao legitima do mesmo hardware | re-activacao com sucesso, mesmo `hardware_id`, `activated_at` preservado e `reactivation_reissue` presente | `409`, rebinding, sobrescrita de `activated_at` ou ausencia de trilha esperada | re-activacao executada sem comparacao objectiva com o estado anterior | appliance original indisponivel ou licenca ja nao corresponde ao bind esperado | bloqueante |
| S03 | Activacao com hardware diferente para licenca bindada | `409`, `activations_log.result='fail'` e bind original inalterado | activacao aceita, bind muda ou o backend nao falha fechado | resposta incompleta ou sem prova do estado persistido apos a tentativa | nao ha licenca bindada valida para provar o cenario | bloqueante |
| S04 | Download administrativo de licenca bindada | download `200`, ficheiro em `{ data, sig }` e auditoria `admin_download_reissue` | download falha sem motivo, resposta nao segue contrato ou auditoria nao aparece | artefacto foi baixado, mas sem hash, sem resposta guardada ou sem auditoria minima | sem sessao valida ou sem licenca bindada/activa | bloqueante |
| S05 | Mutacao permitida de `expiry` e reemissao | `PUT` bem-sucedido, bind preservado e nova reemissao viavel no mesmo hardware | bind muda, mutacao falha de forma incoerente ou reemissao legitima deixa de funcionar | `PUT` ocorre, mas sem prova da reemissao/estado final | sem licenca activa bindada ou sem sessao administrativa valida | bloqueante |
| S06 | Tentativa de mudar `customer_id` em licenca bindada | `409`, `customer_id` inalterado e `license_update_denied` presente | update aceite ou ownership alterado | resposta falha por motivo lateral e nao prova o guardrail de bind | licenca nao bindada ou cliente alternativo inexistente quando exigido | bloqueante |
| S07 | Licenca expirada no backend sem `.lic` local | activacao falha fechada, backend reflecte `expired` e nenhum `.lic` novo e criado | activacao passa, backend nao reflecte expiracao ou `.lic` reaparece | expiracao nao fica demonstrada em backend e appliance no mesmo cenario | sem licenca de teste expirada ou sem janela segura para remover `.lic` local | bloqueante |
| S08 | Licenca expirada no backend com `.lic` local ainda dentro da grace | backend `expired`, nova activacao/download negados e appliance com `license_valid=true` + `license_grace=true` | backend continua `active`, daemon invalida cedo demais ou backend volta a abrir activacao/download | falta registo do relogio local, falta stats ou nao fica provado que o `.lic` e anterior a expiracao | sem controlo de relogio/data ou sem `.lic` previo | bloqueante |
| S09 | Licenca revogada no backend com `.lic` antigo offline | backend revoga, novas activacoes/downloads falham e appliance offline continua coerente com o limite documentado | revogacao nao persiste, download continua aberto ou comportamento offline contradiz o contrato actual | nao fica provado que o appliance estava offline ou que o artefacto era anterior a revogacao | sem possibilidade de isolar o appliance ou sem licenca previamente emitida | bloqueante |
| S11 | Coexistencia de artefacto antigo e artefacto novo | dois artefactos ficam demonstrados por hashes/evidencia e o comportamento local bate com a trilha auditada | evidencia contradiz a auditoria ou o comportamento local contradiz o contrato documentado | apenas um artefacto existe, faltam hashes ou falta correlacao com a auditoria | artefacto antigo ja nao existe para comparacao | bloqueante |
| S12 | Appliance offline antes e depois do grace | antes/dentro da grace continua valido e depois da grace cai para o comportamento degradado esperado | transicao local contradiz o contrato de grace | relogio local nao fica registado de forma suficiente ou faltam snapshots chave | ambiente sem controlo legitimo de data ou sem janela para isolamento | bloqueante |
| S13 | Divergencia de fingerprint por mudanca de NIC/UUID | fingerprint/UUID/NIC antes e depois ficam demonstrados e o resultado bate com a politica conservadora da F3.2 | comportamento observado contradiz a politica documentada sem explicacao plausivel | mudanca ocorreu sem baseline suficiente ou sem tentativa observavel depois do drift | ambiente nao permite mudar/observar NIC, UUID, clone, restore ou equivalente controlado | bloqueante |

### 3.2 Cenario desejavel

| ID | Cenario | PASS | FAIL | INCONCLUSIVE | BLOCKED | Impacto no gate |
|----|---------|------|------|--------------|---------|-----------------|
| S10 | Multiplos downloads/reemissoes da mesma licenca | downloads repetidos ficam auditados de forma coerente, mesmo quando o ficheiro pode sair identico | a repeticao observada contradiz a auditoria ou o contrato da F3.5 | houve repeticao, mas a evidencia nao permite concluir coerencia | nao houve janela/necessidade legitima para repeticao | nao bloqueia se nao executado; bloqueia se executado e falhar |

---

## 4. Relatorio final de campanha

Cada rodada real da F3 deve produzir **um unico artefacto final** de campanha:

- ficheiro baseado em
  `docs/tests/templates/f3-validation-campaign-report.md`;
- guardado no directorio raiz do `run_id`;
- preenchido apenas depois de todos os cenarios tentados nessa rodada;
- apontando para os directorios e ficheiros brutos por cenario.

### 4.1 Conteudo minimo obrigatorio

O relatorio final tem de declarar:

- identificacao unica da campanha;
- ambiente/lab/appliance;
- versao do produto e referencia de commit;
- referencia documental usada (`F3.6`, `F3.7`, `F3.8`);
- veredito por cenario e caminhos das evidencias;
- contagem total de `PASS` / `FAIL` / `INCONCLUSIVE` / `BLOCKED`;
- riscos remanescentes;
- pendencias bloqueantes e nao bloqueantes;
- conclusao final:
  - `F3 pode fechar`; ou
  - `F3 nao pode fechar`;
- proximos passos estritamente necessarios.

### 4.2 Regra de conclusao final

O relatorio final deve aplicar a regra abaixo sem interpretacao livre:

- `F3 pode fechar`:
  apenas quando **todos** os obrigatorios estiverem em `PASS`;
- `F3 nao pode fechar`:
  em qualquer outro caso.

Nao existe conclusao intermédia do tipo:

- "quase fechado";
- "fechamento parcial";
- "passa com ressalvas" para cenario obrigatorio sem prova.

---

## 5. Leitura oficial das pendencias apos a F3.8

Depois desta subfase:

- a F3.8 fica **formalizada**;
- a F3 continua **aberta** ate campanha real concluir os obrigatorios com
  `PASS`;
- ausencia de evidencia passa a significar pendencia bloqueante, e nao
  "validado por inferencia".

Resumo operativo:

- F3.6 diz o que provar;
- F3.7 diz como recolher;
- F3.8 diz quando pode fechar.
