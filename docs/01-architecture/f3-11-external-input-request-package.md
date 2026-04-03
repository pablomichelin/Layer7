# F3.11 - Pacote Canonico de Solicitacao Externa dos Insumos

## Finalidade

Este documento formaliza, em modo **documental-operacional estrito**, o
pedido tecnico dos cinco insumos externos que continuam a bloquear a F3.11.

Objectivo deste pacote:

- pedir os cinco insumos sem linguagem vaga;
- tornar a entrega verificavel por qualquer operador ou gestor;
- reduzir rejeicoes por ambiguidade;
- impedir que a readiness da F3.11 seja repetida com dependencias
  "parcialmente entendidas";
- manter explicito que esta rodada **nao** obtem acessos reais, **nao**
  executa campanha e **nao** corrige runtime.

Estado formal herdado e preservado:

- `F3 continua aberta`;
- `F3.11 continua bloqueada`;
- `nenhum ficheiro de codigo foi alterado`;
- `nao foi feito push`;
- `o drift de CORS/same-origin continua apenas registado`.

Leitura complementar obrigatoria:

- [`f3-11-access-enablement-package.md`](f3-11-access-enablement-package.md)
- [`f3-11-input-acceptance-matrix.md`](f3-11-input-acceptance-matrix.md)
- [`f3-11-readiness-reopen-gate.md`](f3-11-readiness-reopen-gate.md)
- [`../05-runbooks/f3-11-input-triage-runbook.md`](../05-runbooks/f3-11-input-triage-runbook.md)
- [`../05-runbooks/f3-11-evidence-intake-template.md`](../05-runbooks/f3-11-evidence-intake-template.md)
- [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md)

---

## 1. Lista canonica dos cinco insumos

1. acesso read-only ao host live `192.168.100.244`
2. query read-only ao PostgreSQL live
3. credencial admin autorizada com escopo formal
4. appliance pfSense com SSH, baseline e controlos legitimos
5. inventario real `LIC-A` a `LIC-F`

---

## 2. Especificacao minima por insumo

### 2.1 Insumo 01 - acesso read-only ao host live `192.168.100.244`

| Campo | Especificacao canonica |
|-------|-------------------------|
| Nome do insumo | acesso read-only ao host live `192.168.100.244` |
| Finalidade operacional | provar hostname real, directorio real da stack, compose activa e revisao observada do deploy |
| Acesso minimo aceitavel | `ssh` legitimado com comandos read-only suficientes para `hostname`, `pwd`, `git rev-parse HEAD`, `git status --short --branch`, `docker compose ps`, `docker compose config --services` |
| Formato de entrega aceitavel | acesso temporario read-only **ou** execucao supervisionada dos comandos acima com output bruto preservado |
| Evidencia minima exigida | output com hostname, directorio real, hash Git observado e servicos activos da stack |
| Exemplos de aceite | `ssh` funciona; `cd "<dir-real>" && git rev-parse HEAD` devolve hash; `docker compose ps` mostra stack coerente |
| Exemplos de rejeicao | apenas "o directorio e /opt/layer7-license"; screenshot sem comando de origem; output truncado sem hash nem servicos |
| Risco de entrega incompleta | assumir equivalencia entre live, local e remoto; executar consultas no directorio errado; classificar deploy sem prova |
| Impacto directo sobre a F3.11 | sem este insumo nao ha prova da revisao exacta em producao nem da stack efectiva |
| Status de bloqueio enquanto ausente | `bloqueio total da readiness e da campanha` |

### 2.2 Insumo 02 - query read-only ao PostgreSQL live

| Campo | Especificacao canonica |
|-------|-------------------------|
| Nome do insumo | query read-only ao PostgreSQL live |
| Finalidade operacional | confirmar schema live, tabelas administrativas e estado real do backend observado |
| Acesso minimo aceitavel | role read-only dedicada **ou** comando supervisionado `psql`/`docker compose exec -T db psql` limitado a `SELECT` |
| Formato de entrega aceitavel | string de comando autorizada, sessao read-only supervisionada ou artefacto bruto de queries executadas na hora |
| Evidencia minima exigida | `SELECT current_database(), current_user, inet_server_addr(), inet_server_port();`, listagem de `licenses`, `activations_log`, `admin_sessions`, `admin_audit_log`, `admin_login_guards`, e `count(*)` nas tres tabelas administrativas |
| Exemplos de aceite | queries executadas sem `permission denied`; identidade da base comprovada; presenca/ausencia das tabelas registada objectivamente |
| Exemplos de rejeicao | export antigo sem timestamp; descricao oral do schema; query sem identidade da base; print parcial sem SQL executado |
| Risco de entrega incompleta | repetir readiness sem saber se o live respeita o contrato canónico da F2/F3 |
| Impacto directo sobre a F3.11 | sem este insumo continuam abertos os blockers de schema, auditoria e sessao |
| Status de bloqueio enquanto ausente | `bloqueio total da readiness e da campanha` |

### 2.3 Insumo 03 - credencial admin autorizada com escopo formal

| Campo | Especificacao canonica |
|-------|-------------------------|
| Nome do insumo | credencial admin autorizada com escopo formal |
| Finalidade operacional | validar login real, sessao administrativa e checks controlados dos cenarios S04/S05/S06/S10 |
| Acesso minimo aceitavel | `ADMIN_EMAIL` + `ADMIN_PASSWORD` validos no fluxo oficial de sessao, com autorizacao explicita de uso na readiness/campanha |
| Formato de entrega aceitavel | entrega segura da credencial por canal autorizado + nota formal de escopo, janela de uso e owner responsavel |
| Evidencia minima exigida | login real em `/api/auth/login`, `Set-Cookie` de `layer7_admin_session`, `GET /api/auth/session` bem sucedido e registo documental do escopo |
| Exemplos de aceite | credencial entra no fluxo oficial; sessao valida e revogavel; owner e janela de uso documentados |
| Exemplos de rejeicao | password antiga "que talvez ainda funcione"; credencial sem owner; token informal fora do fluxo oficial; login sem prova de sessao |
| Risco de entrega incompleta | accoes administrativas sem ownership, sem autorizacao e sem trilha auditavel |
| Impacto directo sobre a F3.11 | sem este insumo a metade administrativa da readiness/campanha permanece fechada |
| Status de bloqueio enquanto ausente | `bloqueio total da readiness e da campanha` |

### 2.4 Insumo 04 - appliance pfSense com SSH, baseline e controlos legitimos

| Campo | Especificacao canonica |
|-------|-------------------------|
| Nome do insumo | appliance pfSense com SSH, baseline e controlos legitimos |
| Finalidade operacional | executar a metade local da F3, recolher baseline real e tornar legitimos os cenarios de relogio, offline e drift de fingerprint |
| Acesso minimo aceitavel | host/IP real com `ssh root@<HOST_REAL>` funcional, `layer7d` operacional e evidencia de snapshot/restore, relogio, offline/online e NIC/UUID/clone/restore |
| Formato de entrega aceitavel | dados reais do appliance + artefacto de controlos do lab/hypervisor + acesso SSH funcional |
| Evidencia minima exigida | output de `hostname`, `date -u`, `sysctl -n kern.hostuuid`, `ifconfig -a`, `service layer7d status`, `layer7d --fingerprint`, estado do `.lic` local, stats JSON e prova real dos controlos do lab |
| Exemplos de aceite | SSH funciona; baseline completa e reproduzivel; existe snapshot identificavel; owner do lab confirma controlos legitimos |
| Exemplos de rejeicao | apenas `<PFSENSE_IP>`; VM "parecida" sem snapshot; acesso via consola informal sem baseline; promessa de alterar relogio sem prova de controlo |
| Risco de entrega incompleta | executar S01/S02/S07/S08/S09/S11/S12/S13 em ambiente nao controlado ou nao reversivel |
| Impacto directo sobre a F3.11 | sem este insumo a metade local da readiness/campanha continua inviavel |
| Status de bloqueio enquanto ausente | `bloqueio total da readiness e da campanha` |

### 2.5 Insumo 05 - inventario real `LIC-A` a `LIC-F`

| Campo | Especificacao canonica |
|-------|-------------------------|
| Nome do insumo | inventario real `LIC-A` a `LIC-F` |
| Finalidade operacional | reservar licencas reais por cenario, sem improviso, reuso opaco ou conflito com producao |
| Acesso minimo aceitavel | artefacto documental e verificavel contendo `pool_id`, `license_id`, `license_key`, `customer_id`, `status`, `expiry`, `hardware_id`, `activated_at`, `revoked_at`, appliance alvo, cenario reservado e owner |
| Formato de entrega aceitavel | documento de inventario preenchido + prova objectiva em backend live via query read-only ou leitura administrativa comprovada |
| Evidencia minima exigida | mapeamento real `LIC-A` a `LIC-F` coerente com o backend observado e reservado para os cenarios correctos |
| Exemplos de aceite | seis pools reais com IDs e estados verificaveis; associacao `licenca -> cliente -> appliance -> cenario` fechada |
| Exemplos de rejeicao | placeholders `<LICENSE_ID>`; lista sem `license_key`; inventario sem prova em backend; licencas de producao reaproveitadas "na hora" |
| Risco de entrega incompleta | revalidar contrato `409` vs `403` e cenarios F3 com licencas erradas, misturadas ou em estado desconhecido |
| Impacto directo sobre a F3.11 | sem este insumo a readiness nao pode distinguir entrega real de improviso operacional |
| Status de bloqueio enquanto ausente | `bloqueio total da readiness e da campanha` |

---

## 3. Regras canonicas de pedido e recepcao

1. O pedido deve citar o nome exacto do insumo e a finalidade operacional.
2. O fornecedor deve entregar evidencia bruta ou acesso verificavel, nao
   interpretacoes livres.
3. Placeholder, memoria oral ou print isolado nao contam como entrega.
4. A recepcao de cada insumo deve ser registada no template canónico de
   intake.
5. O resultado de cada recepcao deve ser classificado na matriz canónica de
   aceite antes de qualquer nova readiness.

---

## 4. Impacto operacional consolidado

- Se um unico insumo ficar ausente, a F3.11 continua bloqueada.
- Se um insumo for entregue de forma parcial ou invalida, a F3.11 continua
  bloqueada.
- So depois de os cinco insumos estarem `entregue valido` a readiness pode
  ser reaberta pelo gate formal proprio.
- Este documento nao autoriza push, nao autoriza campanha e nao altera o
  estado de fase.

---

## 5. Nota operacional de publicacao

Leitura factual que continua a valer nesta rodada:

- o branch local entrou nesta rodada `ahead 20` do remoto;
- esta rodada **nao** faz push;
- um push agora publicaria tambem historico local acumulado;
- por isso, a entrega deste pacote e apenas documental-operacional.
