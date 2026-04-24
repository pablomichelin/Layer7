# Backlog Canónico

Este backlog passa a ser a fila unica de priorizacao do projecto apos a F0.
Nao serve para listar “ideias soltas”; serve para orientar as proximas fases
com criterio de risco, beneficio e ordem de execucao.

---

## Legenda

- **Severidade:** `Critica`, `Alta`, `Media`, `Baixa`
- **Risco se adiado:** impacto principal de deixar o item para depois
- **Esforco:** `P`, `M`, `G`
- **Beneficio:** `Alto`, `Medio`, `Baixo`
- **Status:**
  - `Pronto apos F0`
  - `Planeado`
  - `Planeamento F1 concluido`
  - `Planeamento F2 concluido`
  - `Em execucao na F3.1`
  - `Em execucao na F3.2`
  - `Em execucao na F3.3`
  - `Em execucao na F3.4`
  - `Em execucao na F3.5`
  - `Em execucao na F3.7`
  - `Em execucao na F3.8`
  - `Em execucao na F3.9`
  - `Em execucao na F3.10`
  - `Bloqueado por pre-requisitos da F3.11`
  - `Bloqueado pela fase`
  - `Acompanhar`

**Regra de priorizacao:** severidade e fase sugerida prevalecem sobre
conveniencia local. Itens fora da fase actual nao devem ser puxados sem
reavaliacao formal.

## Checkpoint actual da F1

- F1.1 foi concluida em `2026-04-01` com o contrato operacional oficial de
  distribuicao em `.pkg`, URLs versionadas de release e scripts
  `install.sh`/`uninstall.sh` publicados no canal oficial.
- F1.2 foi concluida em `2026-04-01` com manifesto versionado, assinatura
  destacada Ed25519, public key de verificacao e separacao operacional entre
  builder, signer e publisher.
- F1.3 foi concluida em `2026-04-01` com origem oficial HTTPS de blacklists,
  manifesto dedicado, public key propria, mirror controlado, cache local e
  last-known-good materializados na trilha do consumidor.
- BG-020 foi materializado na F1.3, BG-021 foi materializado na F1.4 com
  matriz explicita de fallback/fail-closed por componente, e BG-022 ficou
  reduzido na trilha do consumidor, mas continua a exigir acompanhamento das
  dependencias externas.

## Checkpoint actual da F2

- O planejamento detalhado da F2 foi concluido em `2026-04-01` com ADRs de
  publicacao segura, autenticacao/sessao, protecao da superficie
  administrativa e integridade transacional/validacao do CRUD.
- A F2.1 foi concluida em `2026-04-01` com `443/TLS` como canal publico
  oficial, `8445` restrito a origin privado por defeito e documentacao
  operacional explicita para edge proxy, certificado e troubleshooting.
- A F2.2 foi concluida em `2026-04-01` com sessao administrativa stateful em
  backend, cookie `HttpOnly + Secure + SameSite=Strict`, expiracao
  ociosa/absoluta, renovacao controlada, logout com invalidacao real e
  remocao do JWT em `localStorage` da trilha activa.
- A F2.3 foi concluida em `2026-04-01` com same-origin only em producao,
  limiter dedicado no login, lockout temporario e auditoria minima para auth
  e mutacoes administrativas.
- A F2.4 foi concluida em `2026-04-01` com validacao forte de payload/query,
  transacoes explicitas em `activate` e mutacoes administrativas, e arquivo
  logico no painel em vez de delete fisico.
- A F2.5 foi concluida em `2026-04-01` com ownership minimo de segredos,
  bootstrap administrativo explicito, backup/restore minimo do PostgreSQL e
  runbooks essenciais; a F2 fica encerrada e a proxima fase elegivel passa a
  ser a F3.

## Checkpoint actual da F3

- A F3 foi aberta formalmente em `2026-04-01` com o documento canónico
  `docs/01-architecture/f3-arquitetura-licenciamento-ativacao.md`.
- A F3.1 mapeou o estado real do codigo: activacao publica em
  `POST /api/activate`, estado persistido em `licenses`, estado derivado por
  expiracao em leituras/listagens e grace local apenas no daemon.
- O primeiro endurecimento minimo desta fase passa a tornar a reactivacao do
  mesmo hardware mais idempotente no backend, sem rebind, sem mudanca de
  contrato e sem abrir trilhas paralelas.
- A F3.2 passa a formalizar a matriz real de fingerprint/binding em
  `docs/01-architecture/f3-fingerprint-e-binding.md`, com politica
  conservadora para reinstall, troca de NIC, clone de VM, restore, migracao
  de hypervisor e appliances com multiplas interfaces, sem alterar a formula
  do fingerprint.
- O unico endurecimento tecnico adicional aceite nesta subfase fica limitado
  a normalizacao defensiva do `hardware_id` persistido no servidor antes de
  comparar ou assinar o `.lic`, reduzindo falso bloqueio por drift de
  formato legacy sem abrir rebind automatico.
- A F3.3 passa a formalizar em
  `docs/01-architecture/f3-expiracao-revogacao-grace.md` a semantica real de
  expiracao, revogacao, validade offline e grace local, declarando sem
  maquilhagem o limite actual da revogacao e o risco de um `.lic` antigo
  continuar valido offline.
- O unico endurecimento tecnico adicional aceite nesta subfase fica limitado
  a um helper minimo de estado efectivo da licenca no backend, reutilizado em
  `activate`, `licenses`, `customers` e `dashboard`, sem mudar schema,
  formato `.lic` ou algoritmo de fingerprint.
- A F3.4 passa a formalizar em
  `docs/01-architecture/f3-mutacao-admin-reemissao-guardrails.md` a
  superficie administrativa real da licenca, a matriz de mutacoes seguras e
  perigosas e a politica conservadora de imutabilidade parcial apos bind.
- O unico endurecimento tecnico adicional aceite nesta subfase fica limitado
  a bloquear a mudanca de `customer_id` em licenca activada/bindada e a
  tornar a auditoria de `license_updated` mais explicita, sem abrir workflow
  novo de rebind/desrevogacao.
- A F3.5 passa a formalizar em
  `docs/01-architecture/f3-emissao-reemissao-rastreabilidade.md` a trilha
  real de emissao/reemissao do `.lic`, a governanca conservadora do artefacto
  e a diferenca entre emissao inicial, reemissao legitima e reemissao
  administrativa.
- O unico endurecimento tecnico adicional aceite nesta subfase fica limitado
  a enriquecer a auditoria do artefacto emitido em `activate` e `download`,
  sem schema novo, sem mudar o formato `.lic` e sem abrir enforcement de
  "latest only".
- A F3.6 passa a formalizar em
  `docs/01-architecture/f3-validacao-manual-evidencias.md` a matriz manual
  de cenarios obrigatorios/desejaveis, os comandos objectivos de recolha de
  evidencia e a politica oficial de "validacao suficiente" da F3.
- Esta subfase **nao** fecha a validacao real por si so; ela prepara e
  governa a execucao controlada em lab/appliance sem abrir F4/F5/F6/F7.
- A F3.7 passa a formalizar em
  `docs/01-architecture/f3-pack-operacional-validacao.md` o pack operacional
  dessa execucao, com directoria por `run_id`, template markdown por cenario,
  nomes fixos de ficheiros e helper shell barato para exportar evidencias do
  backend sem tocar no produto.
- A F3.8 passa a formalizar em
  `docs/01-architecture/f3-gate-fechamento-validacao.md` o gate oficial de
  fechamento da F3, a matriz objectiva de decisao por cenario, a
  classificacao de pendencias bloqueantes vs nao bloqueantes e o relatorio
  final unico de campanha em
  `docs/tests/templates/f3-validation-campaign-report.md`.
- A F3 continua aberta depois da F3.8: sem campanha real com todos os
  cenarios obrigatorios em `PASS`, a fase **nao** pode ser declarada fechada.
- A F3.9 executou em `2026-04-02` a primeira campanha real controlada com
  `run_id` `20260402T130015Z-deploy244`, relatorio final unico e conclusao
  explicita `F3 nao pode fechar`.
- A campanha F3.9 confirmou blockers reais e auditaveis: drift do deploy
  observado face ao contrato canónico (schema live sem `admin_sessions`,
  `admin_audit_log` e `admin_login_guards`, e `activate` live a responder
  `403` onde a F3.8 exige `409`), falta de appliance pfSense autenticavel e
  falta de credencial administrativa autorizada para S04-S06/S10.
- A F3.10 foi concluida em `2026-04-02` como saneamento
  documental-operacional da validacao: a matriz canónica de pre-requisitos da
  proxima campanha passa a viver em
  `docs/01-architecture/f3-matriz-prerequisitos-campanha.md`, a matriz
  canónica de drift pos-F3.9 passa a viver em
  `docs/01-architecture/f3-matriz-drift-operacional.md`, e o runbook
  sequencial da proxima rodada passa a viver em
  `docs/01-architecture/f3-runbook-proxima-campanha-real.md`.
- A verificacao de readiness da F3.11 foi executada em `2026-04-02` e ficou
  registada em `docs/01-architecture/f3-11-readiness-check.md`.
- O resultado foi bloqueio formal: backend publico e origin responderam, mas
  continuam pendentes acesso a shell/DB do deploy observado, credencial
  administrativa autorizada, appliance pfSense autenticavel e inventario real
  `LIC-A` a `LIC-F`.
- O checkpoint de `2026-04-14` alinhou o `license-server` live em
  `192.168.100.244:/opt/layer7-license`: schema administrativo presente,
  `/api/auth/session` funcional e same-origin administrativo novamente
  fail-closed. O blocker real remanescente da F3 passa a ser apenas o
  `DR-05` no appliance.
- O branch actual tambem passa a cobrir por teste o contrato `409` do
  `POST /api/activate` para licenca revogada, licenca expirada e hardware
  divergente, reduzindo o `DR-02` a alinhamento de deploy/publicacao e nao a
  blocker da F3.
- A F3 continua aberta depois desta verificacao: a F3.11 so passa a ser
  elegivel para execucao real depois de nova readiness check com todos os
  pre-requisitos em verde.
- Em `2026-04-24`, o `CORTEX.md` e o `CHANGELOG.md` foram alinhados ao estado
  real: distincao entre .pkg publicado (`1.8.3`) e `PORTVERSION` de trabalho
  (`1.8.4`); politica de `GET /api/licenses/:id/download` concentrada em
  modulo com teste; `npm test` do backend a cobrir `src/**/*.test.js`.

---

## Backlog priorizado

| ID | Item | Severidade | Componente | Fase sugerida | Risco se adiado | Esforco | Beneficio | Status | Observacoes |
|----|------|------------|------------|---------------|-----------------|---------|-----------|--------|-------------|
| BG-001 | Formalizar a cadeia de confianca entre repo, builder, chave publica embutida, servidor de licencas e artefacto publicado | Critica | seguranca/governanca | F1 | decisao tecnica continuar baseada em suposicoes sobre confianca | M | Alto | Planeamento F1 concluido | coberto por ADR-0004 e pelo documento consolidado de arquitectura F1; implementacao pendente |
| BG-002 | Governar a custodia da chave de producao e o tratamento dos ficheiros sensiveis locais no builder | Critica | builder/licencas | F1 | risco operacional e de seguranca concentrado em conhecimento implícito | M | Alto | Planeamento F1 concluido | politica de papeis, assinatura e tratamento de builder suspeito definida; execucao fica para F1 |
| BG-003 | Criar ADR que substitua a ambiguidade historica entre `.txz` e `.pkg` como artefacto de distribuicao | Critica | distribuicao/ADR | F1 | documentos historicos continuam a confundir instalacao e release | P | Alto | Planeamento F1 concluido | ADR-0003 passa a ser a referencia normativa; ADR-0002 fica historico |
| BG-004 | Hardening da stack do license server: segredos, fronteira HTTP/HTTPS, backup, restore e operacao administrativa | Critica | license-server | F2 | indisponibilidade ou exposicao do servidor comprometer activacao | M | Alto | Acompanhar | F2.1-F2.5 materializaram publicacao segura, sessao, superficie administrativa, CRUD, segredos/bootstrap e backup/restore; F3 herda apenas o que pertence ao modelo de licenciamento |
| BG-005 | Endurecer o endpoint de activacao e os controlos de abuso, auditoria e monitorizacao minima | Alta | license-server | F2 | abuso ou comportamento opaco em incidente | M | Alto | Planeamento F2 concluido | F2 definiu rate limit, logging e separacao entre activate publico e admin |
| BG-023 | Fechar a politica oficial de publicacao segura do license server com TLS, edge proxy e portas permitidas | Critica | license-server/publicacao | F2 | exposicao ambigua do painel e do endpoint publico | M | Alto | Acompanhar | materializado na F2.1 com `443/TLS` oficial, origin `8445` privado por defeito, headers minimos e runbook de borda/TLS |
| BG-024 | Substituir JWT em `localStorage` por sessao administrativa segura e fechar CORS/login/brute force | Critica | license-server/auth | F2 | roubo de sessao, abuso administrativo e superficie web permissiva | M | Alto | Acompanhar | F2.2 materializou sessao stateful com cookie seguro e logout real; F2.3 fechou same-origin, limiter dedicado, lockout temporario, politica minima de erro e auditoria administrativa |
| BG-025 | Endurecer validacao, transacoes, arquivo/delete seguro e atomicidade do CRUD do license server | Alta | license-server/crud | F2 | estado parcial, perda de auditoria e conflitos silenciosos | M | Alto | Acompanhar | materializado na F2.4 com validacao forte, transacoes explicitas em `activate`/mutacoes administrativas e arquivo logico no painel |
| BG-006 | Definir modelo de estados do licenciamento: activar, reactivar, renovar, revogar, expirar, grace e offline | Alta | licenciamento | F3 | suporte e troubleshooting continuarem dependentes de tentativa e erro | M | Alto | Bloqueado por DR-05 no appliance | contrato canónico inicial foi aberto na F3.1, a matriz de fingerprint foi fechada na F3.2, a semantica real de expiracao/revogacao/offline foi fechada na F3.3, a superficie administrativa com guardrails minimos foi fechada na F3.4, a rastreabilidade de emissao/reemissao foi fechada na F3.5, a F3.6 governou a evidencia real, a F3.7 operacionalizou a recolha por `run_id`, a F3.8 formalizou o gate de fechamento, a F3.9 executou a primeira campanha real, a F3.10 fixou pre-requisitos, o checkpoint de 2026-04-14 alinhou o live, e o branch cobre por teste payload publico, estado efectivo e os `409` de rejeicao do `activate`; falta fechar apenas os cenarios locais do appliance |
| BG-007 | Validar robustez do hardware fingerprint em cenarios de mudanca de NIC, VM, reinstall e clock | Alta | licenciamento | F3 | activacoes legitimas falharem ou exigirem workaround manual | M | Alto | Bloqueado por DR-05 no appliance | matriz canónica de cenarios e politica conservadora fechadas; o live ja esta alinhado e S13 continua dependente apenas de snapshot e controlo real de NIC/UUID no appliance |
| BG-008 | Fechar lacunas de previsibilidade em activacao offline e revogacao sem quebrar comportamento actual | Alta | licenciamento | F3 | operador assumir garantias que o sistema ainda nao oferece | M | Alto | Bloqueado por DR-05 no appliance | F3.3 declarou o limite real da revogacao actual e da validade offline do `.lic`; com o live alinhado, S08, S09 e S12 passam a depender apenas do appliance e do controlo real de relogio/offline |
| BG-026 | Endurecer a mutacao administrativa e a reemissao para impedir transferencia silenciosa de licenca bindada | Alta | license-server/licenciamento | F3 | operador conseguir mover ownership da licenca bindada sem invalidar o artefacto antigo em campo | P | Alto | Acompanhar | F3.4 bloqueia `customer_id` apos bind/activacao no CRUD normal, reserva rebind/transferencia para trilha futura dedicada e agora cobre por teste o guardrail de update administrativo |
| BG-027 | Reforcar a rastreabilidade de emissao e reemissao do `.lic` sem mudar o formato do artefacto | Alta | license-server/licenciamento | F3 | operador nao conseguir distinguir com clareza quando, como e em que contexto um artefacto foi emitido/reenviado | P | Alto | Bloqueado por DR-05 no appliance | F3.5 audita contexto do artefacto em `activate` e `download`; o branch cobre por teste a metadata de emissao/reemissao e hashes do artefacto; o checkpoint de 2026-04-14 alinhou o live e removeu a duvida de schema/admin, restando apenas a validacao final em appliance |
| BG-009 | Consolidar confiabilidade de package/daemon em reboot, reload, upgrade, rollback e reinicio de servico | Alta | package/daemon | F4 | runtime continuar a divergir entre estado desejado e estado real | G | Alto | Planeado | exige evidencias em appliance |
| BG-010 | Hardening da trilha de blacklists UT1: download, cron, reload, fallback, except tables e forcing DNS | Alta | blacklists | F4 | subsistema seguir operacionalmente fragil apesar de funcional | G | Alto | Bloqueado pela fase | documentos da trilha ja existem e devem ser usados |
| BG-011 | Validar forcing DNS e anti-bypass em cenarios reais de VLAN/interface, excepcoes e tabelas PF | Alta | daemon/enforcement | F4 | bypass continuar a aparecer em combinacoes menos comuns | M | Alto | Planeado | derivado das correccoes recentes em `rdr` |
| BG-012 | Transformar os riscos principais em malha canónica de testes e regressao por componente | Critica | testes/governanca | F5 | cada nova mudanca voltar a depender de memoria humana | G | Alto | Planeado | unir smoke, builder e appliance |
| BG-013 | Fechar cobertura minima de testes para licenciamento, blacklists, package e rollback | Alta | testes | F5 | regressao funcional escapar entre fases tecnicas | G | Alto | Planeado | alinhar com checklist mestre |
| BG-014 | Criar trilha de evidencias e gates para mudancas sensiveis, com ligacao directa entre backlog, checklist e changelog | Media | governanca/testes | F5 | perda de rastreabilidade entre decisao e validacao | M | Medio | Planeado | reforca continuidade entre chats |
| BG-015 | Reorganizar fisicamente a documentacao e normalizar duplicidades de directorios e readmes | Media | estrutura/documentacao | F6 | legado continuar confuso e caro de manter | G | Medio | Bloqueado pela fase | so apos estabilidade tecnica e malha de regressao |
| BG-016 | Normalizar areas sobrepostas como `docs/04-tests` vs `docs/tests`, `docs/04-package` vs docs historicos e prompts antigos | Media | estrutura/documentacao | F6 | agentes continuarem a abrir documentos errados | M | Medio | Bloqueado pela fase | depende do mapa de equivalencia |
| BG-017 | Instituir checklist interno de release com verificacao de artefacto, docs sincronizadas e disponibilidade de download | Media | release-engineering | F7 | publicacoes continuarem dependentes de memoria operacional | M | Alto | Planeado | ligar changelog, release notes e manual install |
| BG-018 | Definir telemetria operacional minima para pacote, daemon e servidor de licencas | Media | observabilidade | F7 | troubleshooting e auditoria continuarem com visibilidade insuficiente | M | Medio | Planeado | sem analytics pesado |
| BG-019 | Rever e refrescar tutorial longo, guias comerciais e docs preservadas por compatibilidade | Baixa | documentacao/comercial | F7 | materiais antigos continuarem a coexistir com a base canónica | M | Medio | Acompanhar | so depois das fases tecnicas centrais |
| BG-020 | Formalizar pipeline seguro de blacklists com origem aprovada, HTTPS obrigatorio, checksum/assinatura e politica de espelhamento | Critica | blacklists/seguranca | F1 | feed continuar dependente de transporte inseguro ou de origem nao autenticada | M | Alto | Acompanhar | F1.3 materializou origem oficial, manifesto assinado, mirror controlado e last-known-good; F4 herda a robustez operacional do runtime |
| BG-021 | Definir politica de fallback e degradacao segura por componente, distinguindo disponibilidade de integridade | Critica | seguranca/operacao | F1 | produto continuar susceptivel a aplicar conteudo suspeito em nome de disponibilidade | M | Alto | Acompanhar | materializado na F1.4 em `install.sh`, `update-blacklists.sh` e docs canónicas; F5 herda a formalizacao de testes |
| BG-022 | Reduzir o risco das dependencias externas criticas de distribuicao e blacklists | Alta | distribuicao/dependencias | F1 | GitHub, UT1 e builder continuarem como pontos unicos de falha sem contrato formal | M | Alto | Acompanhar | F1.3 reduziu o risco no consumo de blacklists com origem oficial, mirror GitHub e cache/LKG local; operacao de publicacao e builder continuam monitorados |

---

## Itens explicitamente fora da fila imediata

Os itens abaixo continuam conhecidos, mas **nao entram agora**:

- console central multi-firewall;
- MITM/TLS inspection universal;
- analytics pesado;
- reestruturacao fisica precoce;
- refactor amplo de package/daemon/frontend sem gate especifico.

---

## Politica de manutencao do backlog

1. Todo item novo entra com componente, fase sugerida, risco, esforco,
   beneficio e status.
2. Nenhum item tecnico fora da fase actual deve ser executado sem ser puxado
   formalmente para a frente.
3. Quando um item mudar de fase, estado ou severidade, actualizar tambem:
   - `CORTEX.md`
   - `docs/02-roadmap/roadmap.md`
   - `docs/02-roadmap/checklist-mestre.md` se afectar gate
