# F3.11 - Pacote Canonico de Habilitacao de Acessos

## Finalidade

Este documento fecha a rodada actual como **bloco documental-operacional**
sem abrir campanha, sem alterar codigo do produto e sem reinterpretar
blockers ja comprovados.

Objectivo deste bloco:

- transformar as lacunas operacionais da F3.11 num pacote canonico de
  habilitacao minima;
- deixar explicito **o que falta**, **quem precisa fornecer**, **qual
  evidencia comprova a entrega** e **qual risco permanece**;
- preparar um caminho objectivo para repetir a readiness da F3.11 assim que
  os acessos existirem de forma legitimada;
- impedir progresso ficticio baseado em suposicoes sobre deploy, schema,
  credenciais ou appliance.

Estado formal deste pacote:

- `F3 continua aberta`;
- `F3.11 continua bloqueada`;
- `a campanha real nao foi aberta`;
- `existe agora pacote operacional de desbloqueio definido`.

Leitura complementar obrigatoria:

- [`f3-matriz-prerequisitos-campanha.md`](f3-matriz-prerequisitos-campanha.md)
- [`f3-matriz-drift-operacional.md`](f3-matriz-drift-operacional.md)
- [`f3-11-readiness-check.md`](f3-11-readiness-check.md)
- [`f3-11-readiness-saneamento.md`](f3-11-readiness-saneamento.md)
- [`../05-runbooks/f3-11-live-access-checklist.md`](../05-runbooks/f3-11-live-access-checklist.md)
- [`f3-11-drift-registry.md`](f3-11-drift-registry.md)

---

## 1. Base factual herdada

Factos ja comprovados antes deste pacote:

- `https://license.systemup.inf.br` respondeu `HTTP/2 200`;
- `https://license.systemup.inf.br/api/health` respondeu `HTTP/2 200`;
- `http://192.168.100.244:8445/api/health` respondeu `HTTP/1.1 200 OK`;
- `origin/main` remoto foi confirmado em
  `66e00f5a36e78056aae27df6aea0ccbd0ed78553`;
- o `HEAD` local da rodada de saneamento anterior era
  `1834b4833c780b82693f97cd3a986531655c40f3`;
- a rodada actual iniciou com `main...origin/main [ahead 19]`;
- o repositorio local continua sem prova de publicacao integral no remoto;
- o contrato canonico do repositorio continua a exigir
  `admin_sessions`, `admin_audit_log` e `admin_login_guards`;
- o baseline documental do schema continua em
  `license-server/backend/migrations/001-init.sql`;
- o caminho canonico esperado para a stack continua documentado como
  `/opt/layer7-license`, mas apenas como expectativa documental;
- o live observado em `/api/auth/login` continua a aceitar `Origin` externo
  com `Access-Control-Allow-Origin: *`.

Leitura operacional obrigatoria desta base:

- ha prova de conectividade HTTP e de estado Git remoto;
- nao ha prova suficiente de shell/DB live, credencial admin autorizada,
  appliance pfSense utilizavel nem inventario real `LIC-A` a `LIC-F`;
- sem esses acessos, a F3.11 continua bloqueada por governanca e ambiente,
  nao por falta de mais um teste improvisado.

---

## 2. Pre-requisitos externos desta rodada

Os itens abaixo **nao** puderam ser obtidos tecnicamente dentro desta rodada
e passam a ser dependencias externas formais:

| Dominio | O que precisa ser entregue | Quem precisa fornecer | Evidencia que comprova a entrega |
|---------|----------------------------|-----------------------|----------------------------------|
| Host live `192.168.100.244` | acesso read-only legitimado ao host observado | owner operacional do license server / gestor do ambiente live | `ssh` bem sucedido + output do checklist live |
| PostgreSQL live | meio read-only de consulta ao banco do deploy observado | owner operacional do banco/stack | `SELECT current_database(), current_user...` + queries de schema/tabelas |
| Credencial administrativa | credencial autorizada com escopo formal para login e checks administrativos | owner operacional do painel / gestor da campanha | login real em `/api/auth/login` + cookie de sessao + escopo registado |
| Appliance pfSense | host/IP real com SSH funcional e baseline recolhivel | owner do lab/appliance | `ssh root@<host>` funcional + baseline recolhida |
| Controlo de snapshot/relogio/offline/NIC/UUID | meios legitimos para S08/S09/S11/S12/S13 | owner do lab/hypervisor | identificadores reais de snapshot/restore e controlos aprovados |
| Inventario `LIC-A` a `LIC-F` | pool real de licencas dedicadas por cenario | owner administrativo do licenciamento | artefacto de inventario + query/objecto correspondente no backend |

**Regra:** placeholder, memoria oral ou acesso "de favor" sem escopo
documentado **nao** valem como entrega.

---

## 3. Acessos minimos por dominio

### 3.1 Host live `192.168.100.244`

| Campo | Minimo canonico |
|-------|-----------------|
| Tipo de acesso necessario | `ssh` legitimado ao host observado, suficiente para comandos read-only |
| Comando minimo de validacao | `ssh root@192.168.100.244 'hostname -f || hostname; docker ps --format "table {{.Names}}\\t{{.Image}}\\t{{.Ports}}"; docker compose ls 2>/dev/null || true'` |
| Evidencia esperada | hostname real, containers/compose project observaveis, saida guardada no artefacto de preflight |
| Risco mitigado | parar de assumir host, stack, portas e compose efectiva sem prova live |

**O que continua em falta hoje:** acesso read-only legitimado e output real do
host.

### 3.2 PostgreSQL live

| Campo | Minimo canonico |
|-------|-----------------|
| Conexao read-only minima necessaria | role read-only dedicada **ou** invocacao supervisionada `psql`/`docker compose exec -T db psql` limitada a `SELECT` |
| Queries minimas de confirmacao de schema | `SELECT current_database(), current_user, inet_server_addr(), inet_server_port();` e `SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('licenses','activations_log','admin_sessions','admin_audit_log','admin_login_guards') ORDER BY 1;` |
| Evidencia esperada | identidade da base/utilizador e lista real das tabelas canonicamente esperadas |
| Risco mitigado | parar de inferir schema live, auditoria e sessao a partir do repositorio local |

Queries minimas adicionais de confirmacao operacional:

```sql
SELECT 'admin_sessions' AS table_name, count(*)::bigint AS row_count FROM admin_sessions
UNION ALL
SELECT 'admin_audit_log', count(*)::bigint FROM admin_audit_log
UNION ALL
SELECT 'admin_login_guards', count(*)::bigint FROM admin_login_guards;
```

**O que continua em falta hoje:** meio read-only real de query ao banco do
deploy observado.

### 3.3 Credencial administrativa

| Campo | Minimo canonico |
|-------|-----------------|
| Escopo autorizado minimo | login real, leitura de sessao, leitura administrativa e exercicio controlado dos checks necessarios a S04/S05/S06/S10 |
| Validacao minima do login | `curl -k -si -c "$COOKIE_JAR" "$L7_BASE_URL/api/auth/login" -H 'Content-Type: application/json' -d '{"email":"<ADMIN_EMAIL>","password":"<ADMIN_PASSWORD>"}'` seguido de `GET /api/auth/session` |
| Evidencia esperada | `200` ou resposta equivalente de sucesso, cookie `layer7_admin_session`, e registo formal do escopo/janela de uso |
| Risco mitigado | impedir mutacoes ou leituras administrativas no escuro, sem ownership nem autorizacao documentada |

**O que continua em falta hoje:** credencial real autorizada e escopo formal
de uso.

### 3.4 Appliance pfSense

| Campo | Minimo canonico |
|-------|-----------------|
| IP/host esperado | ainda **nao fornecido**; placeholder `<PFSENSE_IP>` nao e aceite |
| Acesso SSH necessario | `ssh root@<HOST_REAL>` funcional |
| Baseline minima a recolher | `hostname`, `date -u`, `sysctl -n kern.hostuuid`, `ifconfig -a`, `service layer7d status`, `layer7d --fingerprint`, estado do `.lic` local e do stats JSON |
| Controles necessarios | snapshot/restore real, controlo legitimo de relogio, capacidade de offline/online e controlo real de NIC/UUID/clone/restore |
| Evidencia esperada | SSH bem sucedido + baseline recolhida + identificadores reais dos controlos de snapshot/restore |

Riscos mitigados por este acesso:

- deixar de tratar o appliance como entidade abstracta;
- impedir S08/S09/S11/S12/S13 sem snapshot/restore legitimos;
- provar baseline real antes de qualquer drift de relogio ou fingerprint.

**O que continua em falta hoje:** host/IP real do appliance, SSH funcional e
evidencia de controlos do lab.

### 3.5 Inventario `LIC-A` a `LIC-F`

Campos obrigatorios por licenca:

- `pool_id` (`LIC-A` ... `LIC-F`);
- `license_id`;
- `license_key`;
- `customer_id`;
- `status` persistido;
- `expiry`;
- `hardware_id` actual/esperado;
- `activated_at` e `revoked_at` quando aplicavel;
- appliance alvo;
- cenario reservado;
- fonte de verdade usada;
- owner que aprovou o uso da licenca na campanha.

Fonte aceitavel de verdade:

- query read-only ao backend live;
- leitura administrativa comprovada no painel com output preservado;
- artefacto de preflight assinado/documentado que case com a query do banco.

Criterios de elegibilidade para F3.11:

- cada pool reservado a um conjunto claro de cenarios;
- sem reaproveitamento "na hora" de licenca de producao ou em estado
  desconhecido;
- mapeamento `licenca -> cliente -> appliance -> cenario` fechado antes da
  campanha;
- coerencia entre inventario declarado e estado real do backend.

**O que continua em falta hoje:** inventario real `LIC-A` a `LIC-F`, com IDs,
keys e estados comprovados.

---

## 4. Evidencias aceites

Evidencia aceite para liberar a F3.11:

- output bruto de comando (`ssh`, `curl`, `psql`, `docker compose`) guardado
  em artefacto de preflight;
- query SQL objectiva executada em modo read-only;
- cookie de sessao e resposta HTTP de login real;
- identificador real de snapshot/restore e prova de controlo do appliance;
- inventario `LIC-A` a `LIC-F` acompanhado de prova em backend.

Evidencia **nao** aceite:

- mensagem oral sem output;
- placeholder em documentacao;
- suposicao de que live = local = `origin/main`;
- print isolado sem comando de origem;
- credencial herdada de documento antigo sem autorizacao formal.

---

## 5. Matriz operacional de bloqueios remanescentes

| Item | Estado actual | Evidencia ja existente | Evidencia faltante | Origem da dependencia | Responsavel esperado | Comando ou artefacto de validacao | Impacto directo na F3.11 | Pode prosseguir sem isso? |
|------|---------------|------------------------|--------------------|-----------------------|----------------------|-----------------------------------|--------------------------|---------------------------|
| Revisao exacta em producao | bloqueado | `origin/main` confirmado em `66e00f5...`; branch local entrou na rodada `ahead 19` | `git rev-parse HEAD` executado no host live | publicacao/governanca do deploy | owner operacional do host live | output de `cd "<dir-real>" && git rev-parse HEAD && git status --short --branch` | impede assumir equivalencia entre repo e live | nao |
| Directorio real do servico | parcial | `/opt/layer7-license` existe como expectativa canonica | confirmacao do caminho real no host observado | acesso ao host live | owner operacional do host live | output do passo 1 do checklist live | impede usar `docker compose`/Git no directorio correcto | nao |
| Compose/stack efectivamente activa | bloqueado | backend publico e origin respondem `200` | `docker compose ls`, `docker compose ps`, `docker compose config --services` no host real | acesso ao host live | owner operacional do host live | artefacto `10-preflight-deploy.txt` | impede provar que o origin observado corresponde a stack elegivel | nao |
| Schema live | bloqueado | baseline documental em `001-init.sql` | queries read-only ao PostgreSQL live | acesso DB live | owner operacional do banco/stack | output de `SELECT ... information_schema.tables ...` | impede confirmar `admin_sessions`, `admin_audit_log` e `admin_login_guards` | nao |
| Tabelas administrativas reais | bloqueado | contrato do repo exige as tres tabelas | query com `count(*)` nas tres tabelas | acesso DB live | owner operacional do banco/stack | output de `SELECT ... FROM admin_sessions/admin_audit_log/admin_login_guards` | impede fechar S04/S05/S06/S10/S11 com prova suficiente | nao |
| Credencial administrativa autorizada | bloqueado | endpoint `/api/auth/login` responde | credencial real + escopo formal | governanca administrativa | owner do painel / gestor da campanha | login real + `GET /api/auth/session` + nota de escopo | metade administrativa da F3.11 continua fechada | nao |
| Appliance pfSense por SSH | bloqueado | apenas placeholder `<PFSENSE_IP>` no repo | host/IP real + SSH funcional | lab/appliance | owner do lab/pfSense | output de `ssh root@<host> ...` | S01/S02/S07/S08/S09/S11/S12/S13 continuam bloqueados | nao |
| Snapshot/restore e controlos do appliance | bloqueado | nenhum | snapshot ID real + prova de controlo de relogio/offline/NIC/UUID | lab/hypervisor | owner do lab/hypervisor | artefacto de controlo + baseline do appliance | S08/S09/S11/S12/S13 nao podem ser iniciados legitimamente | nao |
| Inventario real `LIC-A` a `LIC-F` | bloqueado | apenas placeholders documentais | inventario mapeado e provado no backend | inventario de campanha | owner administrativo do licenciamento | artefacto `50-preflight-inventory.md` + query `licenses` | sem pool real nao existe campanha limpa | nao |
| Revalidacao `409` vs `403` em cenario real | bloqueado | drift historico de `403` continua sem nova prova | tentativa controlada com licenca bindada e `hardware_id` alternativo | deploy + inventario + licenca bindada real | gestor da campanha F3.11 | resposta de `POST /api/activate` em cenario S03 | sem isso o drift DO-02 continua aberto | nao |

---

## 6. Estado canonico de publicacao e revisao

Leitura canonica, sem extrapolacao:

- `origin/main` remoto foi confirmado em
  `66e00f5a36e78056aae27df6aea0ccbd0ed78553`;
- `1834b4833c780b82693f97cd3a986531655c40f3` foi o `HEAD` local no inicio da
  rodada de saneamento minimo anterior;
- a rodada actual iniciou com `main...origin/main [ahead 19]`;
- o ultimo commit local antes desta rodada continua a ser
  `5f62035227f061d7f8dc1dd6fce2be7f530146b0`;
- sem shell/DB access ao live e com o branch local ainda `ahead` do remoto,
  e proibido assumir equivalencia entre documentacao/codigo local e ambiente
  observado em producao.

Isto **nao** prova que o live esteja desactualizado.
Isto **tambem nao** prova que o live esteja alinhado.
Isto prova apenas que a equivalencia continua **nao demonstrada**.

---

## 7. Criterios de liberacao

A F3.11 so pode sair de `bloqueada` para `elegivel para nova readiness` se
todos os itens abaixo forem satisfeitos:

1. host live acessivel por `ssh` com output objectivo de stack e revisao;
2. PostgreSQL live consultavel em modo read-only;
3. `admin_sessions`, `admin_audit_log` e `admin_login_guards` confirmadas por
   query real;
4. credencial admin autorizada e testada no fluxo oficial de sessao;
5. appliance pfSense real, com SSH funcional e baseline recolhivel;
6. controlos legitimos de snapshot/restore, relogio, offline e NIC/UUID
   comprovados;
7. inventario `LIC-A` a `LIC-F` materializado e coerente com o backend;
8. checklist live executavel sem placeholders;
9. nova readiness executada sem ambiguidade sobre deploy, schema, admin,
   appliance e inventario.

Enquanto qualquer item acima faltar:

- a F3.11 continua bloqueada;
- a campanha real nao abre;
- a F3 continua aberta.

---

## 8. Objectivo, impacto, risco, teste e rollback deste bloco

- **Objectivo:** transformar lacunas operacionais da F3.11 em pacote
  canonico de habilitacao de acessos.
- **Impacto:** documental-operacional apenas; nenhum ficheiro de codigo foi
  alterado.
- **Risco:** baixo; o bloco apenas formaliza dependencias e evidencias.
- **Teste minimo:** coerencia cruzada com F3.10, readiness check e
  saneamento minimo ja documentados.
- **Rollback:** `git revert <commit-deste-bloco>`.
