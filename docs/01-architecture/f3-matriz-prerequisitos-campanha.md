# F3.10 — Matriz de Pre-requisitos da Proxima Campanha

## Finalidade

Este documento fecha a parte canónica da **F3.10** sem abrir F4/F5/F6/F7.

Objectivo desta subfase:

- transformar o resultado real da F3.9 numa **matriz executavel de
  pre-requisitos** para a proxima campanha;
- deixar explicito o que precisa de existir antes de abrir a **F3.11**;
- separar com rigor o que bloqueia a execucao de um cenario, o que bloqueia
  o fechamento da F3 e o que continua apenas desejavel;
- evitar uma nova rodada que reaprenda na pratica os mesmos blockers da
  campanha `20260402T130015Z-deploy244`.

Leitura complementar obrigatoria:

- [`f3-validacao-manual-evidencias.md`](f3-validacao-manual-evidencias.md)
  continua a definir a matriz dos cenarios;
- [`f3-pack-operacional-validacao.md`](f3-pack-operacional-validacao.md)
  continua a definir a estrutura das evidencias por `run_id`;
- [`f3-gate-fechamento-validacao.md`](f3-gate-fechamento-validacao.md)
  continua a definir o gate de fechamento da F3;
- [`f3-matriz-drift-operacional.md`](f3-matriz-drift-operacional.md)
  passa a classificar os drifts reais observados na F3.9;
- [`f3-runbook-proxima-campanha-real.md`](f3-runbook-proxima-campanha-real.md)
  passa a definir a ordem sequencial da proxima rodada.

**Regra central da F3.10:** a F3.11 so pode ser aberta como campanha real de
fechamento se esta matriz estiver satisfeita. Se um item obrigatorio faltar,
o correcto e parar antes da execucao ou marcar o cenario afectado como
`BLOCKED`, nunca forcando `FAIL`.

---

## 1. Pre-requisitos transversais obrigatorios

### 1.1 Credenciais minimas necessarias

| Item | Minimo exigido | Necessario para | Se faltar |
|------|----------------|-----------------|-----------|
| Credencial administrativa autorizada | `ADMIN_EMAIL` + `ADMIN_PASSWORD` validos no fluxo oficial de sessao | login administrativo, `GET /api/licenses/:id`, `GET /download`, `PUT /licenses/:id`, `POST /revoke` | S04, S05, S06 e S10 ficam `BLOCKED`; a F3 nao fecha |
| Prova de escopo autorizado | registo explicito de que a credencial pode ser usada na campanha real sem mexer "no escuro" em producao | toda a metade administrativa da campanha | campanha deve abortar antes de S04-S06/S10 |
| Acesso ao host/origin do deploy | shell no host do license server ou acesso equivalente para consultas objectivas ao PostgreSQL | queries em `licenses`, `activations_log`, `admin_audit_log` e comprovacao de schema | falta de evidencia persistida; cenarios obrigatorios nao podem fechar |
| Acesso SSH ao appliance pfSense | `ssh root@<PFSENSE_IP>` funcional | `layer7d --activate`, captura de `.lic`, `layer7-stats.json`, data local, fingerprint | S01, S02, S07, S08, S09, S11, S12 e S13 ficam `BLOCKED` |

### 1.2 Acessos minimos necessarios

| Acesso | Minimo exigido | Necessario para | Se faltar |
|--------|----------------|-----------------|-----------|
| HTTPS publico do backend | `https://license.systemup.inf.br` ou ambiente substituto oficialmente escolhido | `POST /api/activate` e chamadas administrativas | campanha nao abre |
| Origin observado do deploy | host e porta do origin efectivo registados no manifesto da campanha | distinguir edge, proxy e backend real | qualquer leitura de drift fica ambigua |
| PostgreSQL do deploy observado | consultas read-only objectivas ao schema e ao estado da licenca | validar drift de schema e evidencias persistidas | obrigatorios com auditoria ficam sem prova suficiente |
| Snapshot/rollback do appliance | snapshot antes de relogio, offline, NIC, UUID, clone ou restore | S08, S09, S11, S12 e S13 | esses cenarios nao devem ser iniciados |

### 1.3 Estado minimo do deploy

O deploy escolhido para a F3.11 tem de satisfazer simultaneamente:

1. referencia de repo explicitamente declarada no manifesto da campanha;
2. commit/revisao do deploy observada no proprio ambiente, sem assumir que o
   live coincide com o repositório;
3. contrato HTTP alinhado ao gate da F3.8 para os cenarios exercitados;
4. fluxo administrativo exercitavel com sessao valida quando o cenario o
   exigir;
5. possibilidade real de recolher evidencias persistidas no backend.

Se o deploy nao provar estes cinco pontos, o correcto e classificar o desvio
como **drift operacional** e nao continuar como se fosse uma campanha valida
de fechamento.

### 1.4 Estado minimo do banco

| Item | Estado minimo exigido | Impacto se faltar |
|------|-----------------------|-------------------|
| `licenses` | licencas de teste existentes e consultaveis | sem inventario confiavel nao ha campanha |
| `activations_log` | queries objectivas disponiveis | S01, S02, S03 e S07 perdem prova de backend |
| `admin_audit_log` | tabela presente e populavel no fluxo administrativo/documentado | S02, S04, S05, S06, S10 e S11 nao podem fechar |
| `admin_sessions` | tabela presente para a politica canónica de sessao | deploy continua em drift da F2.2/F2.3 |
| `admin_login_guards` | tabela presente para a politica canónica de login/lockout | deploy continua em drift da F2.3 |

### 1.5 Estado minimo do appliance

| Item | Estado minimo exigido | Impacto se faltar |
|------|-----------------------|-------------------|
| SSH funcional | login como `root` sem improviso | nao ha campanha local |
| `layer7d` operacional | `service layer7d status` e `layer7d --fingerprint` funcionais | activacao e leitura local ficam invalidas |
| baseline recolhivel | fingerprint, `date -u`, stats JSON e estado do `.lic` local | falta de baseline invalida leitura posterior |
| controlo de data/relogio | janela legitima para alterar data ou usar espera real documentada | S08 e S12 nao podem ser executados |
| isolamento offline controlado | capacidade de isolar o appliance do servidor | S09 e parte de S12 nao podem ser executados |
| controlo de NIC/UUID/clone | mudanca real e reversivel do ambiente | S13 nao pode ser executado |

### 1.6 Inventario minimo de licencas

Inventario minimo **sem depender de reset invisivel nem de reutilizacao
arriscada de estado**:

| Pool | Estado minimo exigido | Reservado para |
|------|-----------------------|----------------|
| LIC-A | `active`, sem `hardware_id`, sem `.lic` local no appliance alvo | S01, S02 e S03 |
| LIC-B | `active`, bindada ao hardware de teste, com sessao admin autorizada | S04, S05, S06 e S10 |
| LIC-C | `expired` no backend e sem `.lic` local | S07 |
| LIC-D | artefacto emitido antes da expiracao, appliance com controlo de relogio | S08 e S12 |
| LIC-E | artefacto antigo preservado, possibilidade de gerar artefacto novo e depois revogar | S11 e S09 |
| LIC-F | bindada ao hardware de teste em lab com drift controlado de NIC/UUID | S13 |

**Regra operacional:** se o ambiente nao tiver este inventario minimo, a
campanha nao deve tentar "adaptar na hora" com licencas de producao ou com
licencas em estado desconhecido. O correcto e parar e regularizar o
inventario antes da F3.11.

---

## 2. Dependencias por cenario

| ID | Classe | Deploy alinhado | Credencial admin | Appliance autenticavel | Controlo especial | Inventario minimo | Se faltar pre-requisito | Impacto no fechamento |
|----|--------|-----------------|------------------|------------------------|-------------------|-------------------|-------------------------|-----------------------|
| S01 | Obrigatorio | sim | nao para `activate`, sim para evidencias completas | sim | sem `.lic` local | LIC-A | `BLOCKED` | bloqueia |
| S02 | Obrigatorio | sim | nao para `activate`, sim para auditar `reactivation_reissue` | sim | mesmo hardware de S01 | LIC-A apos S01 | `BLOCKED` ou `FAIL` falso se insistir | bloqueia |
| S03 | Obrigatorio | sim, com `409` no contrato | nao | nao, desde que o bind previo esteja provado | `hardware_id` alternativo objectivo | LIC-A ja bindada ou equivalente provado | `BLOCKED` se nao houver bind provado; `FAIL` se o deploy responder fora do contrato | bloqueia |
| S04 | Obrigatorio | sim | sim | nao | sessao valida e auditoria funcional | LIC-B | `BLOCKED` | bloqueia |
| S05 | Obrigatorio | sim | sim | idealmente sim para prova de reemissao no mesmo hardware | bind preservado | LIC-B | `BLOCKED` | bloqueia |
| S06 | Obrigatorio | sim | sim | nao | `ALT_CUSTOMER_ID` valido | LIC-B + cliente alternativo | `BLOCKED` | bloqueia |
| S07 | Obrigatorio | sim, com resposta de expiracao coerente | nao para `activate`, sim para leitura da licenca se aplicavel | sim | appliance sem `.lic` local | LIC-C | `BLOCKED` | bloqueia |
| S08 | Obrigatorio | sim | sim para evidencias online | sim | relogio controlado dentro da grace | LIC-D | `BLOCKED` | bloqueia |
| S09 | Obrigatorio | sim | sim para `revoke` e leitura | sim | appliance offline controlado | LIC-E | `BLOCKED` | bloqueia |
| S10 | Desejavel | sim | sim | nao | mesma data e mesmo payload funcional | LIC-B | `BLOCKED` ou nao executado | nao bloqueia se nao executado |
| S11 | Obrigatorio | sim | sim | sim | preservar artefacto antigo e descarregar o novo | LIC-E | `BLOCKED` | bloqueia |
| S12 | Obrigatorio | nao depende de mutacao no backend depois do baseline, mas depende de baseline correcto | nao | sim | relogio local antes/dentro/depois da grace | LIC-D | `BLOCKED` | bloqueia |
| S13 | Obrigatorio | sim para provar `409` quando houver drift | nao para o baseline; sim se houver prova adicional via API | sim | snapshot + drift real de NIC/UUID/clone/restore | LIC-F | `BLOCKED` | bloqueia |

---

## 3. O que bloqueia execucao, o que bloqueia fechamento e o que e apenas desejavel

### 3.1 Bloqueia a abertura da F3.11 como campanha real

- deploy sem referencia observada e sem comparacao explicita com o repo;
- schema live sem `admin_sessions`, `admin_audit_log` e
  `admin_login_guards`;
- ausencia de credencial administrativa autorizada;
- ausencia de appliance pfSense autenticavel por SSH;
- ausencia do inventario minimo de licencas por cenario;
- ausencia de snapshot ou controlo legitimo para relogio/offline/NIC.

### 3.2 Bloqueia o fechamento da F3

- qualquer obrigatorio `FAIL`, `INCONCLUSIVE` ou `BLOCKED`;
- qualquer cenario obrigatorio executado sem a evidencia minima da F3.7/F3.8;
- qualquer drift de deploy ainda aberto no ambiente da campanha;
- qualquer tentativa de usar inventario improvisado em vez do inventario
  minimo declarado nesta matriz.

### 3.3 Continua apenas desejavel

- S10, desde que nao executado ou fique `BLOCKED`/`INCONCLUSIVE` com causa
  objectiva;
- appliances adicionais para repeticao em mais de um hypervisor;
- permutacoes extra de fingerprint para alem do cenario minimo da F3.2;
- repeticao da campanha em segundo ambiente depois de uma primeira rodada
  canónica completa.

---

## 4. Ordem minima de retomada dos cenarios

Retomar primeiro apenas o que depende de saneamento mais curto e produz
sinal binario sobre o ambiente:

1. **S03** apos alinhar o contrato HTTP do deploy, porque e o teste mais
   barato para provar se `activate` respeita `409`.
2. **S04, S06 e S05** apos obter credencial administrativa autorizada e
   schema coerente, porque provam a metade administrativa obrigatoria.
3. **S01 e S02** assim que existir appliance autenticavel e LIC-A valido,
   porque restabelecem o baseline de bind real.
4. **S07** com licenca expirada dedicada, porque valida o caminho online sem
   depender ainda de grace/offline.
5. **S08, S12, S11, S09 e S13** so depois do baseline anterior estar estavel,
   porque exigem controlo de relogio, isolamento offline, coexistencia de
   artefactos ou drift real de ambiente.

---

## 5. Leitura formal da F3.10

- a F3.10 **nao** muda o gate de fechamento da F3;
- a F3.10 **nao** corrige produto nem deploy;
- a F3.10 transforma os blockers da F3.9 em checklist de entrada objectiva
  para a F3.11;
- a F3.11 so deve comecar quando esta matriz puder ser marcada como
  satisfeita sem inferencia livre.
