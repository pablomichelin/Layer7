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
  (`1.8.11`); politica de `GET /api/licenses/:id/download` concentrada em
  modulo com teste; `npm test` do backend a cobrir `src/**/*.test.js`.
- **F3 fechada em `2026-08-04` (Onda C):** campanha DR-05 no appliance
  `192.168.100.254` com S07–S09, S12–S14 e S13 em `PASS`; veredito
  `F3 pode fechar` em
  `docs/tests/evidence/20260804T211500Z-ondaC-f3-report/F3-PODE-FECHAR.md`;
  BG-077 implementado (`1.8.11_68`).

## Checkpoint actual da F4 (aberta F4.0 em 2026-04-24)

- `f4-plano-de-implementacao.md` fixa subfases F4.0–F4.3, mapeia BG-009 a
  F4.1, BG-010/020/021 a F4.2 e BG-011 a F4.3; **F3 fechada** em
  `2026-08-04` — paralelismo DR-05 concluído.
- `f5-preparacao-malha.md` prepara a F5 (malha de testes) sem antecipar
  reestruturacao (F6).
- BG-009 e BG-010 passam a `Em curso` na fila, com criterio de conclusao
  documentado no plano F4 e no roadmap.

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
| BG-006 | Definir modelo de estados do licenciamento: activar, reactivar, renovar, revogar, expirar, grace e offline | Alta | licenciamento | F3 | suporte e troubleshooting continuarem dependentes de tentativa e erro | M | Alto | **Concluido** (F3 fechada `2026-08-04`) | contrato F3.1–F3.8 + campanha DR-05 PASS; BG-077 cobre check-in/revogação remota |
| BG-007 | Validar robustez do hardware fingerprint em cenarios de mudanca de NIC, VM, reinstall e clock | Alta | licenciamento | F3 | activacoes legitimas falharem ou exigirem workaround manual | M | Alto | **Concluido** (S13 PASS `2026-08-04`) | drift NIC reversível validado; matriz canónica fechada na F3.2 |
| BG-008 | Fechar lacunas de previsibilidade em activacao offline e revogacao sem quebrar comportamento actual | Alta | licenciamento | F3 | operador assumir garantias que o sistema ainda nao oferece | M | Alto | **Parcial → BG-077** | F3.3 declarou limite da revogacao offline; S08/S12 cobertos na campanha 2026-08-04; revogacao remota fica em **BG-077** (ADR-0021) |
| BG-026 | Endurecer a mutacao administrativa e a reemissao para impedir transferencia silenciosa de licenca bindada | Alta | license-server/licenciamento | F3 | operador conseguir mover ownership da licenca bindada sem invalidar o artefacto antigo em campo | P | Alto | Acompanhar | F3.4 bloqueia `customer_id` apos bind/activacao no CRUD normal, reserva rebind/transferencia para trilha futura dedicada e agora cobre por teste o guardrail de update administrativo |
| BG-027 | Reforcar a rastreabilidade de emissao e reemissao do `.lic` sem mudar o formato do artefacto | Alta | license-server/licenciamento | F3 | operador nao conseguir distinguir com clareza quando, como e em que contexto um artefacto foi emitido/reenviado | P | Alto | **Concluido** (F3 fechada `2026-08-04`) | F3.5 + campanha DR-05; metadata em activate/download validada em appliance |
| BG-009 | Consolidar confiabilidade de package/daemon em reboot, reload, upgrade, rollback e reinicio de servico | Alta | package/daemon | F4 | runtime continuar a divergir entre estado desejado e estado real | G | Alto | Em curso (F4.1) | `layer7-stats-collect.sh`; `rc.d/layer7d` (stop robusto desde branch `1.8.11_15`, ver `BG-031`); PHP `layer7_daemon_pid_from_file` + paginas Status/Diagnostics; roteiro **10a**; teste **3.8**; ver `f4-plano-de-implementacao.md` |
| BG-010 | Hardening da trilha de blacklists UT1: download, cron, reload, fallback, except tables e forcing DNS | Alta | blacklists | F4 | subsistema seguir operacionalmente fragil apesar de funcional | G | Alto | Em curso (F4.2) | `send_sighup` + lock `restore-lkg`; roteiro **10b** em `validacao-lab.md`; testes **12.1–12.2** em `test-matrix.md`; `PLANO-BLACKLISTS-UT1` + `DIRETRIZES` + plano F4; sem bloquear F3/DR-05 |
| BG-011 | Validar forcing DNS e anti-bypass em cenarios reais de VLAN/interface, excepcoes e tabelas PF | Alta | daemon/enforcement | F4 | bypass continuar a aparecer em combinacoes menos comuns | M | Alto | Em curso (F4.3) | blocos 2026-04-24: dedupe/CIDR/guards; doc MANUAL; `1.8.11_8`–`10`: dedupe (interface,CIDR), ordem estavel em `force_dns`; `1.8.11_11`: fallback `force_dns` com `layer7_pf_ifname_for_rules`; `1.8.11_12`: anti-QUIC por interface com o mesmo helper (DRY); `pf-enforcement.md` / `target-architecture.md`; `validacao-lab` sec. **11** (anti-QUIC opcional; multi-interface / VLAN); matriz de lab ainda aberta |
| BG-012 | Transformar os riscos principais em malha canónica de testes e regressao por componente | Critica | testes/governanca | F5 | cada nova mudanca voltar a depender de memoria humana | G | Alto | Planeado | unir smoke, builder e appliance |
| BG-013 | Fechar cobertura minima de testes para licenciamento, blacklists, package e rollback | Alta | testes | F5 | regressao funcional escapar entre fases tecnicas | G | Alto | Planeado | alinhar com checklist mestre |
| BG-014 | Criar trilha de evidencias e gates para mudancas sensiveis, com ligacao directa entre backlog, checklist e changelog | Media | governanca/testes | F5 | perda de rastreabilidade entre decisao e validacao | M | Medio | Planeado | reforca continuidade entre chats |
| BG-015 | Reorganizar fisicamente a documentacao e normalizar duplicidades de directorios e readmes | Media | estrutura/documentacao | F6 | legado continuar confuso e caro de manter | G | Medio | **Concluido (H1–H5)** | H5: `docs/archive/raiz-legado/` + `planos-fechados/` + stubs; mapa `f6-mapa-consolidacao-H0.md` |
| BG-016 | Normalizar areas sobrepostas como `docs/04-tests` vs `docs/tests`, `docs/04-package` vs docs historicos e prompts antigos | Media | estrutura/documentacao | F6 | agentes continuarem a abrir documentos errados | M | Medio | **Concluido (H1–H2)** | stubs + archive |
| BG-017 | Instituir checklist interno de release com verificacao de artefacto, docs sincronizadas e disponibilidade de download | Media | release-engineering | F7 | publicacoes continuarem dependentes de memoria operacional | M | Alto | **Concluido** | `docs/06-releases/RELEASE-CHECKLIST.md` (Onda I) |
| BG-018 | Definir telemetria operacional minima para pacote, daemon e servidor de licencas | Media | observabilidade | F7 | troubleshooting e auditoria continuarem com visibilidade insuficiente | M | Medio | Planeado | sem analytics pesado |
| BG-019 | Rever e refrescar tutorial longo, guias comerciais e docs preservadas por compatibilidade | Baixa | documentacao/comercial | F7 | materiais antigos continuarem a coexistir com a base canónica | M | Medio | Acompanhar | so depois das fases tecnicas centrais |
| BG-020 | Formalizar pipeline seguro de blacklists com origem aprovada, HTTPS obrigatorio, checksum/assinatura e politica de espelhamento | Critica | blacklists/seguranca | F1 | feed continuar dependente de transporte inseguro ou de origem nao autenticada | M | Alto | Concluido (em `1.8.11_13`) | F1.3 materializou origem oficial, manifesto assinado, mirror controlado e last-known-good; **primeira snapshot publica assinada publicada em `2026-04-24`** em `pablomichelin/Layer7` rolling tag `blacklists-ut1-current` com a chave Ed25519 rotacionada embutida no pacote `1.8.11_13` (fingerprint `6190b8d2…`); F4 herda a robustez operacional do runtime |
| BG-021 | Definir politica de fallback e degradacao segura por componente, distinguindo disponibilidade de integridade | Critica | seguranca/operacao | F1 | produto continuar susceptivel a aplicar conteudo suspeito em nome de disponibilidade | M | Alto | Acompanhar | materializado na F1.4 em `install.sh`, `update-blacklists.sh` e docs canónicas; comportamento `fail-closed` por fingerprint mismatch validado em campo na transicao `1.8.11_12 -> 1.8.11_13`; F5 herda a formalizacao de testes |
| BG-022 | Reduzir o risco das dependencias externas criticas de distribuicao e blacklists | Alta | distribuicao/dependencias | F1 | GitHub, UT1 e builder continuarem como pontos unicos de falha sem contrato formal | M | Alto | Acompanhar | F1.3 reduziu o risco no consumo de blacklists com origem oficial, mirror GitHub e cache/LKG local; em `1.8.11_13` removeu-se a dependencia historica do dominio `downloads.systemup.inf.br` (que nunca chegou a existir publicamente) — operacao oficial passa a ser **apenas** GitHub Releases (`pablomichelin/Layer7` rolling tag `blacklists-ut1-current`); operacao de publicacao e builder continuam monitorados |
| BG-028 | Activar pela primeira vez a trust chain F1.2/F1.4 do pacote nas releases publicas (manifesto Ed25519 assinado, `install.sh` carimbado fail-closed) | Alta | release-engineering/seguranca | F7 | continuar a publicar pacotes sem manifesto/assinatura como em `v1.7.8` a `v1.8.11_13` | M | Alto | **Concluido** (Fase 1 `v1.9.58`, `20260813T154800Z`) | ADR-0023 fase 1; 7 assets F1.2; `install.sh` oficial; `.254`/`GA5.9` fora deste bloco |
| BG-029 | Operacionalizar refresh periodico da snapshot UT1 assinada (rolling tag `blacklists-ut1-current`) com cron/manual e politica de retencao de chaves | Media | blacklists/operacao | F7 | snapshot publica envelhecer e perder utilidade enquanto UT1 vai actualizando o feed upstream | M | Medio | Planeado | inclui: definir cadencia (semanal? quinzenal?) e responsavel; runbook de "publicar nova snapshot" usando `scripts/blacklists/{stage,sign,verify}-snapshot.sh` + `gh release upload --clobber blacklists-ut1-current ...`; politica de rotacao da chave privada (e do par embutido no pacote) caso seja comprometida; gate humano a cada publicacao enquanto F1.3 nao tiver pipeline automatizado em CI seguro |
| BG-030 | Endurecer o updater do GUI (`layer7_settings.php > check_update`) para ignorar releases que nao sao versoes do pacote | Media | gui/release-engineering | F7 | botao "Verificar actualizacao" mostrar erradamente *"Release encontrado mas sem artefacto .pkg."* sempre que existir uma release nao-pacote (ex.: rolling `blacklists-ut1-current`) marcada como `Latest` | P | Medio | Concluido (em `1.8.11_14`) | implementado em `1.8.11_14`: filtra `tag_name` por `/^v?\d+\.\d+/` (rejeita ex. `blacklists-ut1-current`) e mostra mensagem dedicada *"Release mais recente nao e uma versao do pacote (tag ignorada): ..."*. Como bonus, a "versao instalada" passou a vir da fonte canonica do pkg manager (`pkg query %v pfSense-pkg-layer7`), e o `version.str` do daemon passou a usar `${PKGVERSION}` (corrige o loop original do botao "Verificar actualizacao" em `1.8.11_13`). Convencao operacional "releases nao-pacote sao publicadas como prerelease" continua valida e registada em `CHANGELOG.md`/`MANUAL-INSTALL.md` §11b.1 (rede de seguranca dupla) |
| BG-031 | Tornar `layer7d_stop` fiavel (GUI Status/Services e `service layer7d stop`): eliminar processos residuais `daemon(8)` + `layer7d` | Alta | package/daemon | F4 | operador nao consegue parar o daemon para isolamento; estado do servico desalinhado da realidade | P | Alto | Concluido (branch `1.8.11_15`; aguarda release) | `files/usr/local/etc/rc.d/layer7d`: TERM ao PID do pidfile + `pkill -TERM/-KILL -f /usr/local/sbin/layer7d`; ver `CHANGELOG.md` [Unreleased]. Com `layer7.enabled` true, `layer7_resync()` pode voltar a subir o daemon apos reload — para paragem duradoura, desactivar o motor em Layer7 → Settings. |
| BG-032 | Restaurar ou substituir CLI `layer7d --license-status` (auditoria: argumento desconhecido) | Media | daemon/operacao | F3 | troubleshooting de licenca sem comando canónico | P | Medio | Concluido (release `v1.8.11_18`; validado no appliance) | `src/layer7d/main.c`: novo handler com saida `chave=valor` (exit 0 se valida); doc em `MANUAL-INSTALL.md` §2.1 |
| BG-033 | Remocao 100% do pacote: GUI **Removal**, `pkg-deinstall` com cron+PF+residuos, `layer7-pfctl flush-all` | Alta | package/operacao | F4 | operador sem caminho unico para desinstalar sem lixo (tabelas PF, blacklists, cron) | M | Alto | Concluido (branch `1.8.11_16`; aguarda release) | `layer7_removal.php`; `pkg-deinstall.in`; `layer7-pfctl flush-all`; `MANUAL-INSTALL.md` §6; `uninstall.sh` |
| BG-034 | Monitor verdadeiramente passivo: gating de `mode`/`enabled` em `layer7_generate_rules()` e `layer7_pf_default_rules_text()` | Critica | package/operacao | F4 | "bloqueia bancos em monitor" — anti-DoT/DoQ e block_dst sempre injectados independentemente do modo | P | Alto | Concluido (release `v1.8.11_18`; validado no appliance) | `package/.../layer7.inc`: novo `layer7_pf_should_enforce()`, gates em `layer7_generate_rules()` e `layer7_resync()`; `CHANGELOG.md > [Unreleased]` (Bloco 1) |
| BG-035 | Anti-DoT/DoQ (porta 853) como toggle explicito `block_dot_doq`, OFF por defeito | Alta | package/seguranca | F4 | bloqueio cego de "DNS privado" Android e apps moveis que dependem de DoT (incluindo bancos) | P | Alto | Concluido (release `v1.8.11_18`; validado no appliance) | `layer7_bare_config()`, `layer7_pf_default_rules_text()`, `layer7_settings.php`; `CHANGELOG.md > [Unreleased]` (Bloco 2) |
| BG-036 | Allowlist de destinos (bancos/gov/push) honrada no daemon e no PF | Critica | daemon/package/usabilidade | F4 | falsos positivos em IPs partilhados de CDN (bancos, push mobile, gov.br) bloqueados por engano | M | Alto | Concluido (release `v1.8.11_18`; hardening `_28` pendente de gate) | `allowlist.{c,h}`, seed e GUI; `_28` substitui `pass quick` por `match/tag L7ALLOW`, preservando regras nativas do pfSense |
| BG-037 | Flush fiavel de `layer7_block_dst`, `layer7_block` e `layer7_bld_*` em mudancas de modo, paragem do daemon e SIGKILL | Alta | daemon/package | F4 | IPs `stale` de enforce anterior continuarem a bloquear apos voltar a monitor | P | Alto | Concluido (release `v1.8.11_18`; validado no appliance) | `dst_cache_flush()` reforcado + nova `enforcement_flush_all_tables()` em `main.c`; `rc.d/layer7d stop` chama `layer7-pfctl flush-all`; `layer7_resync()` flush quando passivo |
| BG-038 | F5 minima: testes unitarios da allowlist + smoke "monitor nao bloqueia" no appliance | Alta | testes/operacao | F5 | sem rede de seguranca contra regressao do que foi corrigido nos Blocos 1-5 | P | Alto | Concluido para a Fase 1 (release `v1.8.11_18`; smoke exit 0 no appliance) | `tests/functional/test_allowlist.c` (24 PASS), `tests/lab/smoke-monitor-mode.sh` (exit 0 appliance), `tests/run-local.sh`; extensao com casos de policy decision (BG-012/013) fica para a F5 alargada da Fase 2 |
| BG-039 | Caminho A / A0 — higiene: perfil `github` em falta, alinhar limite de hosts GUI(64) vs daemon(32), clarificar nas docs que `block` runtime = bloqueio por DESTINO | Media | package/daemon/docs | Caminho A (V2/15) | perfil prometido inexistente; truncamento silencioso de hosts; docs antigas enganam sobre semantica de block | P | Baixo | Concluido (`1.8.11_19`; build FreeBSD + smoke exit 0 no appliance; perfil github + limite 64 daemon/GUI + doc pf-enforcement) | quick win de baixo risco, primeiro bloco do Caminho A |
| BG-040 | Caminho A / A1 — inventario de dispositivos read-only (IP<->MAC<->hostname<->vendor via DHCP leases + ARP) + pagina GUI Dispositivos | Alta | package/gui | Caminho A (V2/17) | sem identidade de dispositivo nao ha UX/policy tipo UDM; base para A2 | M | Medio | Concluido (`1.8.11_20`; ADR-0011; pagina Dispositivos + `layer7_device_inventory()`; validado no appliance: 470 dispositivos, 230 vendor, alias OK, smoke exit 0) | so leitura; nao altera enforcement; base para A2 |
| BG-041 | Caminho A / A2 — politicas e grupos por dispositivo (MAC -> IP actual via leases; grupos dinamicos do inventario A1) | Alta | daemon/package/gui | Caminho A (V2/17) | politicas hoje so por IP/CIDR; alvo por dispositivo e nucleo da UX UDM | M | Medio | Concluido (`1.8.11_21`; ADR-0012; grupos `device_macs`->`device_ips`, daemon le `device_ips`, GUI grupos+dispositivos+resync; validado: MAC->IP 10.0.85.89, parse OK, smoke exit 0; enforce ao vivo license-gated) | reutiliza `groups[]`; resolve MAC->IP no resync/DHCP change |
| BG-042 | Caminho A / A3 — SNI-aware/CDN: parser TLS ClientHello no daemon + modo politica strict/permissive | Alta | daemon | Caminho A (V2/18) | "host" so vem de DNS; fragil sob DoH/cache/QUIC; falsos positivos em IP CDN partilhado | G | Alto | Concluido (`1.8.11_22`; ADR-0013; usa SNI/Host do nDPI — sem parser proprio nem MITM; toggle `sni_inspection` opt-in OFF; validado: flag aplicado + host extraido em flow_decide + smoke exit 0) | maior risco tecnico; depois da base A0-A2; ECH continua limite |
| BG-043 | Caminho A / A4 — UX tipo UDM: toggle de perfil on/off directo + vista unificada Apps & Categorias & Perfis + hit counters por perfil/dispositivo | Media | gui/package | Caminho A | activar perfil cria politica estatica `profile-*`; falta toggle e vista consolidada | M | Medio | Concluido (`1.8.11_23`; toggle on/off directo + estado visual + hit counters por perfil via top_apps_blocked + nome de dispositivo no top clientes) | melhora a percepcao "tipo UDM" |
| BG-044 | Caminho A / A5 — F5 alargada do Caminho A: testes policy decision (BG-012/013), resolve de dispositivo, parse SNI; matriz de lab e counters | Alta | testes/operacao | F5/Caminho A | rede de seguranca de nao regressao para os blocos A0-A4 | M | Alto | Concluido (`1.8.11_23`; test_config_parse.c no run-local + smoke-caminho-a.sh no appliance; cobre A0-A4 e a regressao do parse sni) | encerra o Caminho A com gate repetivel |
| BG-045 | Caminho B / E0 — ADR-0014 + flag enforcement_model + doc | Media | package/daemon/docs | Caminho B (E0) | sem fundacao reversivel, scoped_hybrid arrisca regressao sem rollback claro | P | Alto | Concluido (E0 2026-06-15; ADR-0014; parse config; GUI Settings; default legacy_global) | primeiro bloco Caminho B; nao altera runtime PF |
| BG-046 | Caminho B / E1 — decisao unificada DNS/nDPI/SNI | Alta | daemon | Caminho B (E1) | DNS atalho ignora origem; decisoes inconsistentes entre caminhos | M | Alto | Concluido | `layer7_decide_for_client()` em DNS+nDPI (ambos modos); `test_policy_decide.c`; legacy aplica `layer7_block_dst` global |
| BG-047 | Caminho B / E2 — PF escopado layer7_pdst/psrc no package | Alta | package/enforcement | Caminho B (E2) | regras PF globais contradizem politicas por dispositivo | G | Alto | Concluido (E2 2026-06-15; layer7_policy_enforcement_rules_text; resync/flush pdst/psrc; GUI scope_global; test_scoped_pf_inc) | runtime daemon scoped em E3 |
| BG-048 | Caminho B / E3 — daemon enforcement escopado | Critica | daemon/enforcement | Caminho B (E3) | nDPI decide certo mas aplica block_dst global | G | Alto | Concluido codigo (E3 2026-06-15; runtime pdst/psrc; cache table+ip; enforce.c/CLI -e; test_enforce_scoped) | **gate two-client appliance pendente** — nao avancar E4 |
| BG-049 | Caminho B / E4 — semantica AND/OR + validacao GUI | Alta | daemon/gui | Caminho B (E4) | app+host OR alarga bloqueio; match vazio catch-all | M | Alto | Em execucao (parcial `_25` + runtime `match_mode` em `1.9.78`) | `_25` recusa scoped block sem origem/global/quarentena; runtime honra `match_mode` (default AND; Pornografia = OR via BG-171). Campo GUI avançado continua pendente. |
| BG-050 | Caminho B / E5 — app/site por destino; quarentena de origem opt-in | Alta | daemon/package | Caminho B (E5) | política de aplicação cortar toda a Internet do cliente | M | Alto | Em execucao (corrigido no candidato `_27`) | app/categoria normal e host usam `pdst`; somente `quarantine_origin=true` usa `psrc`; gate appliance pendente |
| BG-051 | Caminho B / E6 — SNI/CDN/anti-bypass | Media | daemon/docs | Caminho B (E6) | falsos positivos CDN; limites nao expostos na GUI | M | Medio | Planeado | cdn_mode; avisos sni_inspection |
| BG-052 | Caminho B / E7/E8 — testes two-client + release default scoped | Alta | testes/release | Caminho B (E7/E8) | sem rede de seguranca scoped; default legacy permanente | G | Alto | Em curso (E7 parcial) | regressões `_25` para PID/interface/psrc/híbrido; `smoke-enforcement-scoped.sh`; build e gate appliance two-client pendentes; default scoped_hybrid so em E8 |
| BG-053 | Estabilizacao `_25` — ciclo de vida, captura real e integração scoped | Critica | package/daemon/gui | Caminho B pre-gate | reload pode duplicar daemon; interface amigavel produz `captures=0`; scoped pode criar tabela sem regra | M | Alto | **Absorvido em `1.9.x`** (`2026-08-09`) | código na base `1.9.46`; **não** reinstalar `_25`; ver auditoria reconciliação `_24`…`_65` vs `1.9.46` |
| BG-054 | Contenção L1 de logs — separar operação/tráfego, limitar rotação/SQLite e reduzir ruído | Alta | daemon/package/gui/docs | F4.1 (contenção; L2/L3 em F7) | logs ilimitados ocuparem disco e interface não distinguir vista, histórico e ficheiros | M | Alto | **Absorvido em `1.9.x`** (`2026-08-09`) | ADR-0015; `log_store` na base actual; **não** reinstalar `_26` |
| BG-055 | Estabilização funcional pré-produção — captura bidireccional, enforcement imediato e precedência segura | Critica | daemon/package/PF/docs | Caminho B pré-gate | nDPI receber meia conversa; app normal quarentenar cliente; estado PF manter sessão; allow ser anulada por blacklist | M | Alto | **Absorvido em `1.9.x`** (`2026-08-09`) | hash/pdst/state kill na base; gates G2–G7 na linhagem `_69`/`1.9.8` |
| BG-056 | Enforcement PF de allow/excepção escopado | Critica | daemon/package/PF | Caminho B pré-produção | decisão allow não superar destino já presente em tabela por outro cliente/regra | G | Alto | **Absorvido em `1.9.x`** (`2026-08-09`) | ADR-0016; `L7ALLOW`/`pallow` na base; **não** reinstalar `_28` |
| BG-057 | Sintaxe PF anti-QUIC por interface | Critica | package/PF/testes | F4.3 pré-produção | `inet on <if>` fazer o reload inteiro falhar quando anti-QUIC estiver activo | P | Alto | **Absorvido em `1.9.x`** (`2026-08-09`) | FP-018; forma `on <if> inet` na base |
| BG-058 | Preservar estado nDPI sob buracos/colisões da tabela de fluxos | Critica | daemon/captura/testes | Caminho B pré-produção | mesmo fluxo ganhar dois estados nDPI ou ser descartado silenciosamente, causando classificação intermitente | M | Alto | **Absorvido em `1.9.x`** (`2026-08-09`) | FP-019; métricas `cap_*` na base |
| BG-059 | Aguardar estado final do nDPI antes da decisão | Critica | daemon/captura/testes | Caminho B pré-produção | resultado parcial TLS/QUIC ser congelado antes do refinamento para app/SNI, causando falso allow intermitente | P | Alto | **Absorvido em `1.9.x`** (`2026-08-09`) | FP-020; `NDPI_STATE_CLASSIFIED` na base |
| BG-060 | Fechar auditoria E2E Etapa 1 e executar **Bloco B1** — gate passivo `_31` no appliance | Critica | testes/governanca/release | Caminho B pré-gate | `_31` corrige código mas sem evidência física; veredicto **NO-GO** para publicar ou activar enforce | G | Alto | **Encerrado por supersessão** (`2026-08-09`) | B1 `_31`/`_65` **não** reabrir; G2–G7 PASS em `_69`/`1.9.x`; SSOT: `auditoria-reconciliacao-enforcement-1.8.11_24-_65-vs-1.9.46-2026-08-09.md`; único pacote elegível lab = **`1.9.46`** |
| BG-061 | Flush PF completo em lifecycle (exc_allow, blacklist delete, pkg-deinstall) | Media | package/PF | Caminho B pré-gate | reorder/delete deixa tabelas órfãs; hook deinstall desalinhado de `flush-all` | P | Medio | **Absorvido em `1.9.x`** (`2026-08-09`) | B-002/B-003/B-004; R-21; código na linhagem `_32`→`1.9.x` |
| BG-062 | Pagina de bloqueio utilizador final (DNS sinkhole + HTTP local) | Alta | package/GUI/UX | Caminho B / UX | utilizador final sem feedback claro em bloqueios PF | M | Alto | Concluido codigo (`1.8.11_35`); gate appliance pendente | ADR-0017; toggle Definições; Unbound + `layer7-blockpage`; validacao-lab sec. **18**; HTTPS limitado documentado |
| BG-063 | Precedencia de bloqueio sobre allowlist-seed + DNS forcado global anti-bypass | Critica | daemon/package/GUI | Caminho B / UX | allowlist-seed anulava politicas block do admin (youtube.com); clientes contornavam sinkhole via DNS externo | M | Alto | Concluido codigo (`1.8.11_39` daemon + `_40` force_dns); gate appliance pendente | ADR-0018; politica manual prevalece; `allow_cache_revoke_ip`; `block_page.force_dns` (rdr :53 + anti-DoH automatico); seed sem youtube.com |
| BG-064 | Isencao VIP nos Perfis rapidos (atalho para excepcao canonica `vip-isentos`) | Alta | package/GUI | Caminho B / UX F4.3 | admin nao consegue isentar gestores/VIPs sem ir a Excepcoes; duplicacao de mecanismos se inventar campo novo | M | Alto | Concluido codigo (consolidado no pacote `1.8.11_49` — `_48` nunca foi construido/publicado); gate appliance pendente | Plano SSOT `plano-isencao-vip-e-ux-gui.md`; regra de ouro em `modelo-conceptual-gui.md`; excepcao global partilhada; grupos expandem para hosts/cidrs |
| BG-065 | UX modal Perfis rapidos + verificador de politica efectiva | Media | package/GUI | Caminho B / UX F4.3 | modal sobrecarregado; admin nao percebe porque um IP foi bloqueado/permitido | M | Medio | Concluido codigo (`1.8.11_49`); gate appliance pendente | Progressive disclosure; grupos-first; `layer7_test.php` com veredicto e motivo; link Testar no modal |
| BG-066 | Exclusao de origem por politica (`src_exclude_*`) no daemon/PF | Alta | daemon/package/GUI/PF | Caminho B F4.3 | caso fino "isento so deste perfil" impossivel sem duplicar excepcoes globais | G | Alto | Concluido codigo (`1.8.11_50` publicado; fix de ordem PF no `1.8.11_51`); gate appliance pendente | `layer7_pexc_N` scoped; daemon `src_matches_rule`; flush/self-heal completo; `_50` tinha match pexc **depois** do block quick (exclusao PF inoperante) — corrigido no `_51` com assercao de ordem no teste |
| BG-068 | Expansao catalogo Perfis rapidos Bloco 2 (72 perfis, comunicacao, presets, anonymizers) | Alta | package/GUI | Caminho A / UX F4.3 | lacunas videoconferencia, anonymizers, presets UDM | M | Alto | Concluido codigo (`1.8.11_53`); gate appliance pendente | `profiles.json` + `layer7_policies.php`; teste `test_profiles_json.sh`; producao enforce continua `_24` |
| BG-067 | Catalogo Perfis rapidos nivel UniFi/UDM (38 perfis, grupos GUI, ndpi_categories, validacao builder) | Alta | package/GUI | Caminho A / UX F4.3 | perfis desactualizados; modal limitava 12 apps; catalogo inferior a UDM Pro | M | Alto | Concluido codigo (`1.8.11_52`); gate appliance pendente | `profiles.json` + `layer7_policies.php`; teste `test_profiles_json.sh`; nomes nDPI validados no builder FreeBSD 15; producao enforce continua `_24` |
| BG-070 | Perfis rapidos editaveis e personalizados (overlay profiles-custom.json) | Alta | package/GUI | Caminho A / UX F4.3 | upgrades sobrescrevem profiles.json; cliente nao pode ajustar catalogo | G | Medio | Concluido codigo (`1.8.11_56` integral); gate appliance pendente | overlay fora pkg-plist; merge layer7_load_profiles; GUI editar/criar/apagar; export/import; auto-reconnect politica; secao Perfis ocultos; `_55` defeituosa (nao instalar); producao enforce continua `_24` |
| BG-069 | Correccao visual da grelha Perfis rapidos (cabecalhos de grupo, icones FA 4.7, alinhamento) | Media | package/GUI | Caminho A / UX F4.3 | cabecalhos flutuavam inline (grid-column em flex); 55/72 perfis sem icone (mapa SVG hardcoded ignorava `icon` do profiles.json); cartoes com alturas irregulares | P | Baixo | Concluido codigo (`1.8.11_54`); gate appliance pendente | so `layer7_policies.php` + `profiles.json` (fa-robot→fa-magic); fixture FA 4.7 em `test_profiles_json.sh`; zero mudanca funcional (toggle/modal/hits/VIP intactos); producao enforce continua `_24` |
| BG-071 | GUI Lista VIP global (descricoes, export/import, atalho SSOT `vip-isentos`) | Alta | package/GUI | Caminho B / UX F4.3 | admin nao regista directores com nome; excepcao escondida no modal; sem export da lista | M | Medio | Feature completa A–E; execucao lab sec. 20 pendente | secao de primeira classe em `layer7_exceptions.php`; labels em `layer7["vip_meta"]["labels"]` (D2 — daemon nunca le); validacao limites coerente com daemon; export/import padrao BG-070; link desde modal Perfis rapidos; aviso DHCP static mapping; roteiro Bloco E sec. 20; producao enforce continua `_24` |
| BG-072 | Limites daemon excepcoes (`L7_EXC_MAX_HOSTS` 32, `L7_EXC_MAX_CIDRS` 16) | Alta | daemon/package | Caminho B / F4.3 | GUI/upsert aceitam >8 entradas mas daemon trunca silenciosamente (PF ve tudo, decisao DNS so 8+8) | M | Medio | Feature completa A–E; execucao lab sec. 20 pendente | unica mudanca C da feature Lista VIP; alinhar validacao PHP e upsert; testes parse/decisao; memoria ~+19 KiB estatica; roteiro Bloco E sec. 20; producao enforce continua `_24` |
| BG-073 | Isencao VIP no caminho DNS (sinkhole Unbound + DNS forcado) | Alta | package/Unbound/PF | Caminho B / F4.3 | VIP na `vip-isentos` continua sujeito a sinkhole global e rdr :53 `from any` | G | Medio | Feature completa A–E; pos-auditoria `_60`; perf `_61`; execucao lab sec. 20 pendente | ADR-0020; opção (a) view Unbound; fallback (b) rdr `from !<layer7_exc_allow_N>`; fix `_60` estado persistente; `_61` passa `$data` a `layer7_vip_dns_rdr_fallback_enabled()` (perf filter_configure, zero mudanca funcional); fix `_62` corrige `$data` indefinido em `layer7_generate_rdr_rules_snippet()` (caminho CIDR usa `$l7config`; elimina warning PHP 8 e releitura por linha rdr); producao enforce continua `_24` |
| BG-074 | Redesign compacto grelha Perfis rapidos (cartoes horizontais, switches, accordion, pesquisa) | Media | package/GUI | Caminho A / UX F4.3 | grelha vertical ocupa demasiado espacio; toggles texto pesados; grupos sempre abertos dificultam navegacao em 72 perfis | M | Medio | Concluido codigo (`1.8.11_63`); gate appliance pendente | so `layer7_policies.php` + `en.php`; switches CSS mantem POST `toggle_profile_on/off`; pesquisa/filtro So ligados client-side; localStorage accordion; zero mudanca funcional; producao enforce continua `_24` |
| BG-075 | Materializar tabelas PF estaticas VIP/excepcoes (`exc_allow`/`pexc`/`blsrc`) no PF live | Critica | package/PF | Caminho B / F4.3 | VIP correcto no JSON/rules.debug mas `pfctl -t layer7_exc_allow_N` = Table does not exist; sem `L7ALLOW` o VIP cai no block global | P | Alto | Concluido codigo (`1.8.11_64`); gate appliance pendente | padrao 1.5.3 + replace de membros; `layer7_static_origin_tables_apply_to_pf` em resync; ensure `exc_allow_0..15`; producao enforce continua `_24` |
| BG-076 | GUI i18n EN/PT completo + icones FA6 Perfis rapidos + renome Mensagens | Media | package/GUI | Caminho A / UX F4.3 | opcoes novas so em PT; marcas FA4 mostram X branco no FA6 do pfSense; label Mensageria | M | Medio | Concluido codigo (`1.8.11_65`); gate appliance pendente | so apresentacao: `en.php`, `layer7_profile_icon_*`, `profiles.json` labels; id `mensageria` intacto; zero mudanca daemon/enforcement; producao enforce continua `_24` |
| BG-077 | Check-in online periodico e revogacao remota de licenca (cancelamento comercial) | **Critica** | license-server/daemon/licenciamento | **F3+** (bloqueante comercial recomendado antes GO enforce) | revogacao no servidor nao corta appliance; cliente cancelado continua em enforce ate expiry+grace offline | G | **Alto** | **Implementado** (`2026-08-04`) | API `244` + daemon `1.8.11_68`; S14 PASS; flag `check_in_enabled` default OFF; ADR-0021; plano `f3-plano-check-in-online-revogacao-remota.md` |
| BG-124 | Lista VIP em texto simples (export/import .txt + editor em lote) | Media | package/GUI | F4.3 / UX | operador PME edita JSON, falha com «JSON invalido» (virgula final) | P | Alto | **Concluido** (`1.9.61` publicado) | uma linha `IP, nome`; JSON legado aceite; `test_vip_exception.php`; daemon intacto |
| BG-125 | Lista VIP a partir das reservas DHCP das interfaces | Media | package/GUI | F4.3 / UX | operador recopia IPs prefixados a mao; drift face ao DHCP | P | Alto | **Concluido** (`1.9.61` publicado) | `dhcpd/<if>/staticmap`; colunas+filtro por interface; sem auto-isencao; daemon intacto |
| BG-126 | Copy de operador nas paginas MITM / Identity / check-in | Media | package/GUI | F4.3 / UX | GUI expoe ADRs, gates `20.x`, paths de `docs/` e checklist de lab | P | Alto | **Concluido** (`1.9.62` publicado) | so texto; daemon/licenca intactos; `test_gui_operator_copy.php` |

## Checkpoint trilha IPv6 (pós-fecho plano mestre — 2026-08-05, rev. L)

- Trilha IPv6 **FECHADA**; veredicto consolidado em
  [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](../00-overview/ESTADO-PRODUTO-E-PLANOS-FECHADOS.md).
  Arranque: [`START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md).
- Produção / `latest`: **`1.9.8`**. Rollback: **`1.9.0`**.
- Novos planos: backlog + GO (não reabrir BG-078…084 sem GO).

| ID | Item | Severidade | Area | Fase | Risco se adiado | Esforco | Beneficio | Status | Notas |
|----|------|------------|------|------|-----------------|---------|-----------|--------|-------|
| BG-078 | Governança IPv6 V0: ADR-0024, matriz limitações, banner GUI, índices, mapa M-xx | Alta | documentacao/governanca | F4+ | claim dual-stack falso; operador desinformado | P | Alto | **Concluido (12.1–12.2)** | GV0 PASS; banner em Diagnostics |
| BG-079 | Paridade PF scoped `inet6` (REV-018): pdst/psrc/pallow/pexc/exc_allow | Alta | package/PF | F4 | bypass IPv6 em scoped_hybrid | M | Alto | **Concluido (12.3 + GV1)** | `1.9.4`+; `layer7_localnets` |
| BG-080 | Daemon captura + fluxos + nDPI IPv6 (`capture.c`, flow key) | Critica | daemon | F4 | FP-010 core; sem classificação v6 | G | Alto | **Concluido (12.4–12.5 + GV3)** | appliance PASS |
| BG-081 | Policy/enforce/allowlist IPv6 (`policy.c`, `enforce.c`, `allowlist`) | Critica | daemon | F4 | decisão runtime v6 ausente | G | Alto | **Concluido (12.6–12.8 + GV4)** | GV4 closed `1.9.6` |
| BG-082 | GUI + validação JSON IPv6 (`layer7.inc`, páginas GUI) | Alta | package/GUI | F4 | truncamento/validação silenciosa | M | Alto | **Concluido (12.9)** — Onda V4 completa | portal HTTP dual-stack em 12.11 |
| BG-083 | DNS forçado / block page / VIP isenção IPv6 (NAT `rdr inet6`) | Alta | package/PF/Unbound | F4 | bypass DNS em v6 | G | Medio | **Concluido (12.10+12.11)** — `1.9.8` | DNS :53 + HTTP/HTTPS portal + VIP ACL v6 |
| BG-084 | Malha lab dual-stack + fecho trilha (GV6–GV7, release) | Alta | testes/F5/F7 | F5/F7 | sem evidência repetível v6 | M | Alto | **Concluido (12.12+12.13+GV7.4)** | produção enforce `1.9.8` |

## Checkpoint trilha Identity + MITM Add-on (aberta 2026-08-05; reopen MITM 2026-08-08)

- Arranque: [`START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)
- Posicionamento PME: [`posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md) — **ACEITE**
- Plano: [`plano-identity-mitm-addon.md`](plano-identity-mitm-addon.md) rev. `2026-08-14bh` — **【FILA FECHADA】**
- Passo actual: **20.37 PASS** — fecho documental; soak `.254` = **`1.9.63`** MITM OFF
- Prontidão: ficha **já não é gate**; operação = GUI + entitlement; [`mapa`](../09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md)
- Ambição: paridade NGFW no tempo (estado actual ≠ tecto)
- Desenho: [`desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md) — runtime no `.pkg` desde `1.9.39`
- PoC: [`poc-layer7-tlsproxy-lab.md`](../09-blocking/poc-layer7-tlsproxy-lab.md) — Opção A PASS
- Prep: [`prep-20.10-checklist.md`](../09-blocking/prep-20.10-checklist.md)
- Contrato: [`contrato-ipc-layer7-tlsproxy-20.9.md`](../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md)
- ADRs: 0025–0029 Aceito; **0035 Aceito** (ficha fora; ambição NGFW); **0026** emenda rev. `r`
- Baseline produção: **`1.9.8`**. lab/`latest`: **`1.9.63`**. Soak `.254`: **`1.9.63`** MITM OFF. Captive portal: **fora de escopo**. Squid: **rejeitado**.

| ID | Item | Severidade | Area | Fase | Risco se adiado | Esforco | Beneficio | Status | Notas |
|----|------|------------|------|------|-----------------|---------|-----------|--------|-------|
| BG-085 | Governança IM0: START-HERE, plano, mapa, gates, índices, ADRs | Alta | documentacao/governanca | F4+/novo plano | drift; chat sem continuidade | M | Alto | **Concluido (20.2 PASS / GI0)** | ADRs 0025–0028 Aceito; T1 |
| BG-086 | Entitlements `features` CSV + gates daemon/GUI/license-server (IM1) | Critica | licenciamento | IM1 | add-on sem enforcement comercial | G | Alto | **Concluido (20.6 / GI1 PASS)** | check-in ∩ .lic + gates |
| BG-087 | MITM TLS opt-in + CA (IM2) — 20.8–20.36 + fecho 20.37 | Alta | package/GUI | Identity+MITM | UX ainda a subir rumo a NGFW | M | Alto | **Concluido (20.37 / FILA FECHADA)** | Sem overclaim de paridade já atingida |
| BG-088 | Identity map **daemon** + LDAP/LDAPS (IM3–IM4) — **caminho de valor PME** | Critica | daemon/GUI | IM3–IM4 | user/grupo sem mapa dinâmico | G | Alto | **20.18 PASS** / fechar IM4 | Test LDAP GUI + GI5.4; GI5.3 = IM5 |
| BG-089 | RADIUS **accounting receiver** + **agente DC** (IM5) | Critica | daemon/ops | IM5 | Identity incompleto | G | Alto | **20.20 PASS** / GI6 lab | WinRM outbound não canónico; agente em `docs/samples/identity-dc-agent/` |
| BG-090 | Políticas `ad_users`/`ad_groups` → identity_ips (IM6) | Alta | package/daemon | IM6 | directório sem enforcement útil | G | Alto | Feito (20.24); GI7 lab residual | GI7; não-regressão IP/MAC |
| BG-091 | Agente endpoint + TS/VDI (IM7–IM8) | Media | endpoint | IM7–IM8 | multi-user/NAT frágil | G | Medio | **Fechado** ADR-0029 (ADIAR+exclusão) | GI8 PASS |
| BG-092 | Fecho lab/release add-on (IM9) | Alta | testes/F7/docs | IM9 | feature sem MANUAL/release | M | Alto | **Concluido** (20.33/GI9) | Identity rede FECHADA; residuais AD opcionais |

## Checkpoint auditoria segurança `1.9.8` → candidato `1.9.9` (2026-08-05)

Auditoria só-leitura do pacote `1.9.8` (daemon/GUI/helpers/license-server).
Veredicto: núcleo sólido; Altos reais em `/tmp`, DNS passivo e allowlist IPv6.
**Fora da trilha Identity+MITM** — bloco de estabilização dedicado.

| ID | Item | Severidade | Area | Fase | Risco se adiado | Esforco | Beneficio | Status | Notas |
|----|------|------------|------|------|-----------------|---------|-----------|--------|-------|
| BG-093 | Self-heal `pfctl -f /tmp/rules.debug` sem confiança (owner/symlink) | Alta | daemon/package/PF | F4/hardening | ruleset PF arbitrário se `/tmp` comprometido | M | Alto | **Concluido (`1.9.9`)** | gate: regular file, uid 0, !world-writable; helper + PHP + daemon |
| BG-094 | Escrita estado root em `/tmp` (symlink race: stats/update/activate) | Alta | daemon/GUI | F4/hardening | sobrescrita de ficheiros do sistema | M | Alto | **Concluido (`1.9.9`)** | stats/activate/checkin/update → `/var/db/layer7` + `O_EXCL\|O_NOFOLLOW` |
| BG-095 | DNS observe envenenável (só `sp==53`, sem QR/correlação) | Alta | daemon/capture | F4/hardening | DoS/bypass via UDP spoof LAN | G | Alto | **Concluido (`1.9.9`)** | QR + pendência; residual fechado em **BG-104** (`1.9.11`) |
| BG-096 | Allowlist IPv6 ineficaz (`pf_entry_strict_ok` rejeita `:`) | Alta | daemon/allowlist | F4/hardening | destinos v6 legítimos bloqueados (lacuna pós BG-081) | P | Alto | **Concluido (`1.9.9`)** | `layer7_pf_table_entry_ok` + `execv` add_entry |
| BG-097 | curl activate/check-in sem timeout | Media | daemon/license | F4 | hang indefinido | P | Medio | **Concluido (`1.9.9`)** | `--connect-timeout 10 --max-time 30` |
| BG-098 | `waitpid` sem retry EINTR em enforce | Media | daemon | F4 | falsos fails / zombies | P | Medio | **Concluido (`1.9.9`)** | `waitpid_retry` |
| BG-099 | Update GUI: URL só prefixo `https://github.com/` | Media | GUI/release | F7 | admin instala `.pkg` de outro repo | P | Medio | **Concluido (`1.9.9`)** | restringir a `pablomichelin/Layer7/` |
| BG-100 | Teto blacklist 8M entradas (OOM) | Media | daemon/blacklists | F4 | OOM com feed externo grande | M | Medio | **Concluido (`1.9.12`)** | hard-cap 5M + `mem_percent` 5–50% de `hw.physmem` (clamp 128–1536 MB) + GUI; truncagem com WARN |
| BG-101 | Revogação remota ineficaz com check-in default OFF (A-04) | Alta | licenciamento | AP3 / `30.14` | appliance instalado ignora revogação até expiry+grace | M | Alto | **Concluido** (`30.14` / BG-118) | default ON em novas; existentes opt-in; isolados opt-out; N3 mantido |
| BG-102 | Allowlist PF sem `match inet6` (L7ALLOW só inet) | Alta | package/PF | F4/hardening | IPs v6 na tabela allow_dst sem tag; block inet6 ignora allowlist | P | Alto | **Concluido (`1.9.10`)** | `layer7_pf_inet46_rules` + helper; smoke `pfctl -sr` PASS |
| BG-103 | TOCTOU `pfctl -f /tmp/rules.debug` (check≠use) | Alta | daemon/package/PF | F4/hardening | ruleset arbitrário entre `stat` e `pfctl -f` | M | Alto | **Concluido (`1.9.11`)** | open+O_NOFOLLOW+fstat → `pfctl -f -` (stdin); PHP+helper+daemon |
| BG-104 | DNS observe residual (spoof com txid+client) | Alta | daemon/capture | F4/hardening | sniffer LAN spoofa resposta com ID visto | M | Alto | **Concluido (`1.9.11`)** | pend client+txid+resolver+qname; allowlist auto-seed+`dns_observe_resolvers[]`; limite: spoof-as-resolver L2 |
| BG-105 | Reclassificação pós-sinkhole e log storm do portal local | Alta | daemon/logging | F4/hardening | evento enganoso e crescimento desnecessário do log quando o cliente alcança o IP local devolvido pelo DNS | P | Alto | **Appliance parcial PASS (`1.9.31`); observação dirigida pendente** | instalado controladamente em `192.168.100.254`: daemon/config/PF e dois clientes PASS; `dns.google`/`mask.icloud.com` continuam sinkhole para `.254`, sem novo `skip IP local`. Unbound encerra TLS antes da classificação com SNI OFF, por isso `outcome=sinkhole` não foi observado fisicamente; regressão `test_sinkhole_local_guard.sh`; SHA256 `dcbad868…ffcd15` |
| BG-106 | ABI do builder divergente do appliance | Alta | build/release | F7 / gate de release | pacote FreeBSD 15 exigir `pkg add -f` no pfSense FreeBSD 16 | M | Alto | **Aceite operacional** — builder permanece 15 até FreeBSD 16.0-RELEASE; install Plus com `-f`; P2-14 **AVALIADO** (BG-152) | política produto: alvo pfSense novo; builder 16 **não** provado; `-f` ≠ suporte nativo ABI 16 |
| BG-107 | Perfis rápidos / Acesso remoto visual + admin-block | Media | package/GUI | Caminho A / UX | RA vermelho/ícones/grelha; RA duplicado; layout inconsistente | P | Baixo | **Concluido em `1.9.33`** | cores marca, aliases FA6, grelha, redirect RA, admin-block |
| BG-108 | UX visual wave 2 (KPI unificado, subnav Políticas, catálogo, Chart.js offline) | Media | package/GUI | Caminho A / UX | Estado/Relatórios KPI distintos; Kick/Rumble; subnav; CDN Chart.js | P | Baixo | **Concluido; canal publico `1.9.34` (`latest`)** | redeploy limpo; SHA256 `87982ffb…0eec`; appliance PASS |
| BG-109 | GUI Identity — tabela read-only do mapa user↔IP (daemon) | Media | package/GUI + daemon | Identity | Identity configura LDAP/RADIUS/DC mas não mostra sessões activas | M | Medio | **Aberto — defer** | requer dump/IPC do mapa em `identity_map.*`; não inventar vista sem API |
| BG-110 | Perfis rápidos: rascunho + Aplicar em lote (sem resync por clique) | Alta | package/GUI | Caminho A / UX | cada toggle faz resync ~20s + refresh; activar N perfis é inviável | M | Alto | **Concluido + publicado `1.9.35`** | SHA256 `5f88e131…f4f4`; appliance PASS |
| BG-111 | Perfis rápidos: categorias colapsadas + polish UX | Media | package/GUI | Caminho A / UX | grelha poluída (grupos com activos abertos); localStorage reabria | P | Baixo | **Concluido + publicado `1.9.36`** | SHA256 `abfd772f…a71b`; superseded por `1.9.38` latest |
| BG-112 | F6 higiene estrutural residual pós-H5 (inventário, classificação, plano, gate, exclusões; lotes P1–P4) | Media | estrutura/documentacao | F6 residual | resíduo local/untracked/links/status F6; risco de apagar evidência ou misturar código/lab | P | Baixo (auditoria) / Medio (lotes) | **Auditoria PASS**; **P1 CORRIGIR PASS** (`2026-08-10`); P2–P4 físicos **bloqueados** ate GO + G0–G7 | Plano [`../00-overview/f6-plano-higiene-estrutural-residual.md`](../00-overview/f6-plano-higiene-estrutural-residual.md); inv/class `f6-*-2026-08-09.md`; **nao** reabre H1–H5; P4 FAIL/ABORT = MANTER |
| BG-113 | Pack produto PRD+UML+catálogo + botão GUI «Reportar erro» (opt-in GitHub, sem telemetria) | Media | docs + package/GUI | F4 / manutenção | operadores sem caminho seguro para reportar bugs; docs sem PRD/UML/catálogo canónicos | P | Medio | **Concluido — publicado `1.9.48`** | Hub `pack-produto-layer7.md`; PRD/UML/catálogo; GUI Diagnósticos fluxo 3 passos; `test_error_report.php`; sem segredos |

### Trilha Anti-pirataria / Anti-tamper (BG-114…BG-123 engenharia; BG-127 evidência; BG-128 auditoria)

Fila governada por [`plano-antipirataria-anti-tamper.md`](plano-antipirataria-anti-tamper.md)
(ondas AP0–AP4, passos `30.x`), arranque em
[`../00-overview/START-HERE-antipirataria.md`](../00-overview/START-HERE-antipirataria.md),
diagnóstico em
[`../01-architecture/modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md)
e gates em
[`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md).

**`30.3`–`30.6` FECHADOS** (`2026-08-10`). GA1 PASS; GA2.1–2.5 + GA2.11 PASS;
GA3 PASS. BG-120 **Concluido**. Token: `30.8`–`30.10` + e2e `1.9.54` PASS;
**30.9 live PASS**. Primary auth preflight **PASS**
(`20260812T003214Z` — 200/200 + 401). **GA4.12 N/A** (coms externas
dispensadas `2026-08-12`). **`30.11` cut FECHADO** (`20260812T011217Z`);
**BG-117 Concluido**. Evidência:
[`../tests/evidence/20260812T011217Z-30.11-cut-mirror/`](../tests/evidence/20260812T011217Z-30.11-cut-mirror/).
**`30.12`–`30.19` FECHADOS** — **trilha FECHADA** como engenharia
(`20260812T025741Z`; GA6 PASS; BG-114…123/101 **Concluido**; **BG-028 Fase 1
Concluido** (`v1.9.58`)). **Não** reabrir AP0–AP4 / código / PORTVERSION.
**GO `2026-08-14`:** reabre **somente** evidência operacional em campo —
item **BG-127**. **GA6.7** permanece parecer jurídico **externo** (fora deste
item). Não misturar com MITM/IPv6.

| ID | Item | Severidade | Area | Fase | Risco se adiado | Esforco | Beneficio | Status | Notas |
|----|------|------------|------|------|-----------------|---------|-----------|--------|-------|
| BG-114 | Remover modo dev (`is_dev_key`) do binário de produção; gate por `L7_DEV_BUILD` | **Critica** | daemon/licenciamento | AP1 / passo `30.4` | 32 bytes zerados no binário publicado dão licença universal permanente (achado A-01) | P | Alto | **Concluido** (`30.4` + release `1.9.49`, `2026-08-10`) | GA2.1–2.3 PASS; evidência `20260810T235325Z-30.4-no-dev-bypass` |
| BG-115 | Strip do `layer7d` no port; remover símbolos de licença | Alta | package/build | AP1 / passo `30.5` | mapa de símbolos aponta para as funções de licença (A-01) | P | Alto | **Concluido** (`30.5` + release `1.9.50`, `2026-08-10`) | GA2.4/2.5/2.11 PASS; evidência `20260810T200329Z-30.5-strip`; `-fvisibility=hidden`; sem ofuscação |
| BG-116 | Anti-rollback de relógio (marca persistente + degradação para monitor) | Alta | daemon/licenciamento | AP1 / passo `30.6` | `date` para trás estende licença expirada indefinidamente (A-03) | M | Alto | **Concluido** (`30.6` + release `1.9.51`, `2026-08-10`) | GA3 PASS (GA3.7 DEFERRED); evidência `20260810T201043Z-30.6-anti-rollback`; RR-4 no runbook |
| BG-117 | Token de subscrição na entrega de blacklists/catálogos; retirar espelho público corrente | Alta | blacklists + license server | AP2 / passos `30.8`–`30.11` | cópia pirata mantém-se actualizada indefinidamente (A-06) | G | **Alto** | **Concluido** (`30.11` cut `20260812T011217Z`; GA4.10/15 PASS; GA4.12 N/A) | API `asset_count=0`; residual CDN @cut documentado; recheck 404×4; evidência `20260812T011217Z-30.11-cut-mirror` |
| BG-118 | Check-in `true` por defeito + política de migração | Alta | package + licenciamento | AP3 / passo `30.14` | revogação no painel não corta appliance instalado (A-04) | M | Alto | **Concluido** (`30.14` `20260812T015519Z`; GO registado) | novas=ON; upgrade preserva; runbook isolados; GA5.9 campo PENDENTE; candidato `1.9.56` |
| BG-119 | Resposta de check-in assinada com nonce; rejeitar não assinada | Alta | daemon + license server | AP3 / passos `30.12`–`30.13` | servidor falso via `/etc/hosts` mantém licença viva (A-05) | M | Alto | **Concluido** (`30.13` `20260812T013913Z`; GA5.2–5.6 PASS unit) | dual-mode D10; candidato `1.9.55` sem release; evidência `20260812T013913Z-30.13-checkin-signed` |
| BG-120 | Estado de entitlements assinado para a GUI; eliminar fallback sem verificação | Media | package/GUI | AP1 / passo `30.7` | stats forjados desbloqueiam UX de Identity/MITM (A-07) | M | Medio | **Concluido** (`30.7` + release `1.9.52`, `2026-08-10`) | GA2.8–2.10 PASS; evidência `20260810T214800Z-30.7-entitlements`; verify via `pkeyutl` |
| BG-121 | Alerta de abuso multi-appliance no license server (+ decisão sobre `max_activations`) | Media | license server | AP3 / passo `30.15` | integrador multi-cliente invisível (A-08) | M | Alto | **Concluido** (`30.15` `20260812T020331Z`; decisão 7 = só alerta) | dashboard queue; rebind sem FP; sem `max_activations`; sem deploy; GA5.12 PASS unit |
| BG-122 | Distribuir decisão de licença (remover ponto único em `refresh_enforce_cfg`) | Media | daemon | AP4 / passo `30.16` | um NOP activa enforce sem licença (A-02) | M | Medio | **Concluido** (`30.16` `20260812T023529Z`; GA6.1/6.2 PASS) | gates A/B + `enforce_armed`; candidato `1.9.57` sem release; R-A declarado |
| BG-123 | Completar cadeia de assinatura de release nas publicações (manifesto + `.sig`) | Baixa | release | AP4 / passo `30.18` | releases só com `.sha256`; contrato F1.2/ADR-0023 incompleto (A-10) | P | Medio | **Concluido** (`30.18` gate-control `20260812T025135Z`; GA6.5 processo + GA6.6) | processo F1.2 obrigatório + dry-run PASS; campo/tags → BG-028 Fase 1 **Concluido** em `v1.9.58` |
| BG-127 | Evidência operacional em campo dos residuais anti-pirataria (N1/N2, anti-rollback, token offline, revogação) | Alta | testes/lab/licenciamento | manutenção / evidência campo (pós-`30.19`) | controlos AP1–AP3 fechados em engenharia sem prova viva N1/N2/GA3.7/GA4.8/GA5.9 | M | Alto | **Concluido — PASS** (`20260814T224213Z`) | Runbook [`../13-runbooks/evidencia-operacional-antipirataria-bg127.md`](../13-runbooks/evidencia-operacional-antipirataria-bg127.md). Fecho [`../tests/evidence/20260814T224213Z-bg127/`](../tests/evidence/20260814T224213Z-bg127/). GA2.6 **PASS** (monitor + enforce); GA2.7 **PASS**; GA3.7 **PASS**; GA4.8 **PASS** campo; GA5.9 **PASS**. **Fora:** GA6.7. Soak `.254` = `1.9.63` monitor / MITM OFF. |
| BG-128 | Remediações da auditoria `2026-08-14` (P0-1 freeze HEAD↔30.11; fila P0-2…P3) | **Critica** (P0-1) / Alta | license server + daemon + package | manutenção / pós-auditoria | deploy integral do HEAD apaga serving `30.11` live; TOTP fail-open; revoke após arquivo não corta | G | Alto | **Aberto — P0-1 ACTIVO (serving versionado; freeze NÃO encerrado); P0-2, P1-1, P1-2, P1-3, P1-4, P2-1, allowlist `30.11` e P1-5…P1-8 + P2-12 FEITOS no git** (`2026-08-14`; `c2b9fdb` + governação após gates; sem deploy / `PORTVERSION`); **P2-7+P2-8+P2-10 FEITOS no git** (`2026-08-14`; sem deploy / `PORTVERSION`); **P2-11 FEITO no git** (`2026-08-14`; sem deploy / `PORTVERSION`); **A1/A2/M2 FEITO no git** (`28c97ad` + governação após gates; `2026-08-14`; sem deploy / `PORTVERSION`); **M1 FEITO no git** (`2026-08-14`; sem deploy / `PORTVERSION`); **P2-17 FEITO no git** (`2026-08-14`; sem deploy / `PORTVERSION`); **P2-3 FEITO no git** (`2026-08-14`; sem deploy / `PORTVERSION`); **P1-9 AVALIADO no git** (`2026-08-14`; residual pós-P2-3 não aberto no HEAD; sem mudança de runtime); **P2-2 FEITO no git** (`2026-08-14`; CSRF admin fail-closed; sem deploy / `PORTVERSION`); **P2-13 AVALIADO no git** (`2026-08-14`; meia-noite local / DST / UTC sem correção única segura; sem mudança de runtime); **P2-4 FEITO no git** (`2026-08-14`; incremento atómico de `failure_count`; sem deploy / `PORTVERSION`); **P2-6 Bloco A FEITO no git** (`2026-08-14`; `.dockerignore` + `USER node` no backend; sem compose/healthcheck; sem deploy / `PORTVERSION`); **P2-6 Bloco B FEITO no git** (`2026-08-14`; `pg_isready` + `depends_on` `service_healthy`; sem Docker build/up; sem deploy / `PORTVERSION`); **P0-2 residual single-use/bind FEITO no git** (`2026-08-14`; `jti` + `admin_totp_challenges` + consumo transaccional; sem deploy / `PORTVERSION`); **P3-1 FEITO no git** (`2026-08-14`; sessão única atómica com lock do admin; sem deploy / `PORTVERSION`); **P3-2 FEITO no git** (`2026-08-14`; `GET /api/auth/session` inclui `a.totp_enabled`; sem deploy / `PORTVERSION`); **P3-3A FEITO no git** (`2026-08-14`; `/login` disabled e inexistente partilham 401 genérico + bcrypt + `registerLoginFailure`; sem deploy / `PORTVERSION`); **P3-3B** FEITO no git (`2026-08-14`; `POST`/`PUT /api/users` exige password >=12; `/login` não rejeita 10; sem deploy / `PORTVERSION`); **P3-3C** FEITO no git (`2026-08-14`; `verifyTotp` Buffer UTF-8 + guarda de comprimento + `timingSafeEqual`; sem deploy / `PORTVERSION`); **P3-4** FEITO no git (`2026-08-14`; `GET /api/auth/2fa/status` try/catch + 500 JSON; sem deploy / `PORTVERSION`); **P3-5** FEITO no git (`2026-08-14`; promoção atómica do `.lic` em Activate; sem deploy / `PORTVERSION`); **P3-6** FEITO no git (`2026-08-14`; gate PEM do port == SoT; sem deploy / `PORTVERSION`); **P3-8 AVALIADO no git** (`2026-08-14`; cut `30.11` `asset_count=0` + 404×4; sem mudança de runtime); **P3-9 AVALIADO no git** (`2026-08-14`; **BG-150**; opção A — **FEITO documental**; URLs **não** removidos; sem mudança de runtime); **P2-16 AVALIADO no git** (`2026-08-14`; **BG-151**; opção A — **FEITO documental**; rollback preferido = overlay `bbc74a5…`; tag `pre-30.13` **não** é padrão/`latest`; sem tag/retag/deploy); **P2-14 AVALIADO no git** (`2026-08-14`; **BG-152**; opção A — **FEITO documental**; bypass ABI `-f` = política BG-106; builder FreeBSD 16 **não** provado; sem código/`PORTVERSION`); **P3-7 AVALIADO no git** (`2026-08-14`; **BG-153**; opção A — **FEITO documental**; colisão TZ/expiry já provada em P2-13/REV-030; `timegm`/`gmmktime` **não** são correção; sem mudança de runtime) | Relatório [`../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md`](../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md). Runbook freeze [`../13-runbooks/bloqueio-deploy-integral-head-30.11.md`](../13-runbooks/bloqueio-deploy-integral-head-30.11.md). **Proibido** rsync/rebuild integral HEAD→`.244`. Allowlist 7 paths **versionada** (content-auth + rota + `.gitkeep` + volume compose + vhost `downloads` sobre nginx HEAD/P1-2 + ignore snapshot). Snapshot/`.env`/SPA **fora do git**. **P0-2:** HMAC TOTP sem fallback estático; arranque produção recusa `ADMIN_BEARER_JWT_SECRET`/`JWT_SECRET` vazios; `development`/`test` explícitos podem arrancar sem esses valores. Residual P0-2 single-use/bind **FEITO** no git: `jti` no HMAC; `admin_totp_challenges`; consumo `FOR UPDATE` + `used_at` antes da sessão. Sem bind IP/UA. **P1-1:** check-in arquivada `revoked`/`expired` → 409 envelope v2. **P1-2:** origin substitui XFF por `$remote_addr`; `getClientIp` usa `req.ip` (`trust proxy: 1`). **P2-3:** origin `X-Forwarded-Proto $scheme`; login recusa HTTP+proto https no Host de origin. **P1-3:** `/login/totp` recusa `is_active=false`; reset só após TOTP OK; falha TOTP no lock existente sem enumerar (P2-5 absorvido). **P1-4 + P2-1:** lock no `init`; primeiro admin já owner; promoção legado `LIMIT 1`; alerta se vários owners (sem unique/demotion). **P1-5…P1-8 + P2-12:** FEITO no git após gates (`c2b9fdb` + governação neste commit) — enforce recusa check-in ON sem chave; upgrade/keep-config preserva json/`.lic`/CA/secrets/check-in; deinstall real limpa `/var/db` + anti-DoH. **P2-7+P2-8+P2-10:** FEITO no git após gates — save atómico + escape JSON; `store_key` zera só features; `.lic` 0600. **P2-11:** FEITO no git após gates — GUI/helper exigem HW + expiry/grace 14d (não só assinatura). **A1/A2/M2:** staging `/var/db/layer7/deinstall-preserve`; fail-closed se backup obrigatório falhar; 0600; harness funcional (sem P2-13/P2-9/P3). **M1:** GUI/helper obtêm fingerprint via `layer7d --fingerprint` (CLI one-shot); sem fórmula PHP sysctl/ifconfig; `LAYER7_TEST_HW_ID` só com `LAYER7_TEST_ROOT`. Campo FreeBSD pendente. **P2-17:** `LAYER7_TEST_NOW` só com `LAYER7_TEST_ROOT` (data de binding da GUI). **P2-3:** origin `$scheme` + login fail-closed no Host de origin. **P1-9 AVALIADO:** Host oficial no origin HTTP é o contrato F2.1 (edge TLS → origin HTTP); bind HEAD `127.0.0.1:8445`; bind live `0.0.0.0` não versionado; sem mudança de runtime. **P2-2:** users/search na superfície admin; mutações fail-closed sem `Origin` allowlist nem `Sec-Fetch-Site: same-origin` (Bearer autenticado e GET sem Origin continuam). **P2-13 AVALIADO:** meia-noite local; D 12:00 → grace; DST ±1 h C vs PHP; sem correção única (fim do dia UTC/local / `timegm` / só `tm_isdst=-1` colidem com REV-030 / P3-7 / P2-11); cadeado `test_license_expiry_policy.php`; sem mudança de runtime. **P2-4:** UPSERT atómico `failure_count = failure_count + 1` na janela de 15 min; lock conta 5 / IP 10 / 15 min. **P3-1:** `createSession` com `BEGIN` + `FOR UPDATE` no admin + revoke + insert; 2 paralelos → 1 `revoked_at IS NULL`. **P3-2:** `resolveSessionToken` SELECT inclui `a.totp_enabled`; admin com TOTP → `GET /api/auth/session` `totp_enabled: true`. **P3-3A:** `POST /api/auth/login` disabled e inexistente partilham 401 genérico + bcrypt (hash real ou dummy) + `registerLoginFailure`. **P3-3B:** `POST`/`PUT /api/users` exige password >=12; POST 10 → 400; POST 12 → 201; PUT 10 → 400; PUT sem password inalterado; `/login` não rejeita 10. **P3-3C:** `verifyTotp` Buffer UTF-8 + guarda de comprimento + `timingSafeEqual`; válido mesmo now → true; malformed → false sem throw. **P3-4:** `GET /api/auth/2fa/status` try/catch local; pool rejeitado → 500 JSON `Erro interno.`; sem unhandledRejection; segundo GET 200; 401/403 intactos. **P3-5:** tmp 0600 + verify + rename; falha preserva o `.lic` anterior; `activate.body` não renameado. **P3-6:** `verify-prod-pubkey.sh` FAIL se PEM ≠ SoT; selftest local. **P3-8 AVALIADO:** recheck `20260814T200900Z` — `asset_count=0`, 404×4, primary 401; sem runtime. **P3-9 AVALIADO (BG-150; opção A — FEITO documental):** docs «404 esperado»; URLs **não** removidos (legado / fallback). **P2-16 AVALIADO (BG-151; opção A — FEITO documental):** rollback preferido = overlay `bbc74a5…`; tag `pre-30.13` **não** é padrão/`latest`. **P2-14 AVALIADO (BG-152; opção A — FEITO documental):** bypass ABI `-f` = política BG-106; builder FreeBSD 16 **não** provado; **não** é suporte nativo ABI 16. **P3-7 AVALIADO (BG-153; opção A — FEITO documental):** colisão TZ/expiry já provada em P2-13/REV-030; `timegm`/`gmmktime` **não** são correção. **P2-9 AVALIADO (BG-154; opção A — cadeado + docs):** upgrade **não** injecta `true` (contrato `30.14` / ADR-0032). **P2-6 Bloco A:** `.dockerignore` + `USER node` só no backend; compose/healthcheck fora; sem Docker build/up. **P2-6 Bloco B:** `pg_isready` + `depends_on` `service_healthy`; hash compose P0-1 actualizado; sem Docker build/up. Próximo código com GO: P0-1 rebuild api + smoke (sem P2-9; sem P2-7/8/10/11; sem M1/P2-17/P2-3; sem P1-9 runtime; sem P2-2; sem P2-13; sem P2-4; sem P2-6 Bloco A; sem P2-6 Bloco B; sem P3-1; sem P3-2; sem P3-3A; sem P3-3B; sem P3-3C; sem P3-4; sem P3-5; sem P3-6; sem P3-8; sem P3-9; sem P2-16; sem P2-14; sem P3-7; sem P0-2 residual). P0-1 só cai com GO do primeiro rebuild `api` + smoke. Não misturar com BG-127 campo / MITM / SPA `2.1.0`. |
| BG-140 | P3-4: try/catch em `GET /api/auth/2fa/status` quando o pool rejeita | Baixa | license server | manutenção / pós-auditoria (BG-128) | Express 4 deixa Promise rejeitada sem 500 JSON | P | Baixo | **Concluido** (`2026-08-14`; sem deploy / `PORTVERSION`) | Pool rejeitado → 500 `{error:'Erro interno.'}`; processo vivo; 401/403 intactos. Sem wrapper global / Express 5. |
| BG-142 | P3-5: promoção atómica do `.lic` em Activate (tmp 0600 + verify + rename) | Baixa | daemon/licenciamento | manutenção / pós-auditoria (BG-128) | crash entre write e verify deixava `.lic` lixo; verify falha apagava o anterior | P | Baixo | **Concluido** (`2026-08-14`; sem deploy / `PORTVERSION`) | tmp no mesmo dir; candidato inválido remove tmp e preserva o final; sucesso 0600; `activate.body` não renameado. |
| BG-144 | P3-6: gate de alinhamento da chave de produção (PEM do port vs SoT) | Baixa | package/builder | manutenção / pós-auditoria (BG-128) | rotação actualiza C+SoT e esquece o PEM; daemon e GUI discordam no mesmo `.lic` | P | Baixo | **Concluido** (`2026-08-14`; sem deploy / `PORTVERSION`) | `verify-prod-pubkey.sh` FAIL se PEM ≠ SoT ou PEM ≠ C; selftest fixture; `license.c`/PEM intactos. |
| BG-148 | P3-8: recheck read-only do cut `30.11` (`asset_count=0` + 404 anónimo) | Baixa | docs/distribuição | manutenção / pós-auditoria (BG-128) | último recheck 12-08; PoPs/TTL não observados na auditoria de 14-08 | P | Baixo | **Concluido** (`2026-08-14`; sem deploy / `PORTVERSION`) | Confirmação BG-148 `20260814T201800Z`: id `313502667`; `prerelease=true`; `draft=false`; `assets=[]`; `asset_count=0`; 404×4; primary 401. Evidência `20260814T200900Z-p38-cut-recheck`. Sem mudança de runtime. Residual P3-9 fechado em BG-150 (opção A). |
| BG-149 | P3-9: triagem só leitura (documentar 404 vs remover URL) | Baixa | docs/distribuição | manutenção / pós-auditoria (BG-128) | cliente/docs anunciam URL morto após P3-8 | P | Baixo | **Concluido** (`2026-08-14`; sem mutação) | Recomendação: opção A (docs-only); URLs no runtime mantidos (GA4.11). Remover URL = bloco futuro + GO + `PORTVERSION`. |
| BG-150 | P3-9 opção A: documentar «404 esperado» pós-cut `30.11` | Baixa | docs/distribuição | manutenção / pós-auditoria (BG-128) | 404 do espelho confundido com incidente; risco de reupload GA4.11 sem GO | P | Baixo | **Concluido** (`2026-08-14`; **FEITO documental**; URLs **não** removidos; sem deploy / `PORTVERSION`) | P3-9 **AVALIADO**. Nota [`../09-blocking/nota-404-esperado-cut-30.11.md`](../09-blocking/nota-404-esperado-cut-30.11.md). Evidência [`../tests/evidence/20260814T204500Z-p39-404-esperado/`](../tests/evidence/20260814T204500Z-p39-404-esperado/). MANUAL-INSTALL (secção UT1) / PLANO / DIRETRIZES alinhados. Runtime (`update-blacklists.sh` / `layer7.inc` / `config.json.sample`) intacto. |
| BG-151 | P2-16 opção A: documentar rollback preferido (overlay `bbc74a5`) vs tag `pre-30.13` | Media | docs/ops | manutenção / pós-auditoria (BG-128) | tag `pre-30.13` anunciada como rollback padrão/`latest` reabre rejeição de `nonce` (GA5.9 FAIL) | P | Medio | **Concluido** (`2026-08-14`; **FEITO documental**; sem tag/retag/deploy) | P2-16 **AVALIADO**. Rollback preferido = overlay `30.13` / imagem `bbc74a5…`. Tag `pre-30.13` só incidente específico de `30.13`. História preservada em ACOES/evidência. Runbook [`../13-runbooks/bloqueio-deploy-integral-head-30.11.md`](../13-runbooks/bloqueio-deploy-integral-head-30.11.md). |
| BG-152 | P2-14 opção A: documentar limite do builder FreeBSD 16 e fronteira ABI | Media | docs/build | manutenção / pós-auditoria (BG-128) | P2-14 aberto parece buraco; builder 16 ou gate no código sem GO parece mudança operacional | P | Medio | **Concluido** (`2026-08-14`; **FEITO documental**; sem código/`PORTVERSION`/hosts) | P2-14 **AVALIADO**. Bypass `-f` (GUI + `install.sh`) = política BG-106. Builder 16 **não** existe / **não** está provado. **Não** é suporte nativo ABI 16. Guia [`../08-lab/builder-freebsd.md`](../08-lab/builder-freebsd.md). |
| BG-153 | P3-7 opção A: consolidar colisão TZ/expiry; não usar `timegm`/`gmmktime` | Baixa | docs/licenciamento | manutenção / pós-auditoria (BG-128) | texto original P3-7 / REV-030 sugere `timegm` e reabre a colisão já provada em P2-13 | P | Baixo | **Concluido** (`2026-08-14`; **FEITO documental**; sem mudança de runtime) | P3-7 **AVALIADO**. Cliente mais estrito no dia D (UTC−3) vs servidor UTC = contrato HEAD, **não** bypass. `timegm`/`gmmktime` alteram o contrato e *apertam* o Brasil (grace às 21:00 de D−1). SSOT [`../01-architecture/f3-expiracao-revogacao-grace.md`](../01-architecture/f3-expiracao-revogacao-grace.md). Prova P2-13/P3-7 na auditoria. |
| BG-154 | P2-9 opção A: cadeado de upgrade que **não** injecta `check_in_enabled=true` | Media | docs/package | manutenção / pós-auditoria (BG-128) | P2-9 aberto parece buraco; injectar `true` invertiria o GO `30.14` / ADR-0032 e arriscaria air-gap | P | Medio | **Concluido** (`2026-08-14`; opção A — cadeado + docs; sem mudança de runtime) | P2-9 **AVALIADO**. `load_or_default` / `pkg-install.in` não chamam a migration; chave ausente ⇒ OFF. Injectar `true` = GO novo que emende o `30.14`. Runbook [`../13-runbooks/check-in-migration-30.14.md`](../13-runbooks/check-in-migration-30.14.md) §7. |
| BG-155 | Correções da revisão de código: lock de check-in, zone-id PF, lockfiles Docker e gates locais/release | Alta | license server + daemon + build | manutenção / F7 | revoke concorrente pode emitir resposta `active`; IP IPv6 com `%iface` chega ao `pfctl`; imagens não reproduzíveis; gates podem falhar por fontes/aceite de licença divergentes | M | Alto | **Concluido — PUBLICADO** (`v1.9.64`, `2026-08-15`) | Check-in serializa `FOR UPDATE` com operações administrativas; zone-id é rejeitado; Docker usa lockfiles + `npm ci`; runner TLS injeta flags Homebrew; smoke inclui o gate de licença e `deployz` não depende de confirmação interativa. Pacote/stage assinado e publicado (`SHA256=692ab615b0a45f70958f2b866d339e44f833f7953aeec5f780ee0af9e5afeb5f`). P0-1 continua: não fazer deploy `.244` sem GO. |
| BG-156 | Completar tradução inglesa do pacote e da página pública de bloqueio | Média | package/GUI | manutenção / F4.3 | cliente escolhe inglês, mas textos de áreas recentes e página de bloqueio ainda regressam ao português | P | Médio | **Concluído — PUBLICADO** (`v1.9.65`, `2026-08-15`) | Catálogo EN completo para chamadas literais GUI e descrições de perfis; bloqueio público entende `layer7.language`; teste de regressão `test_i18n_coverage.js`; pacote assinado `SHA256=0c12cf38f44347ba69af876eea1e0b3cefc22cde07d9e5fd85ae208876ce0d6f`. Sem daemon, licença ou deploy `.244`. |
| BG-157 | Disponibilizar espanhol como idioma adicional | Média | package/GUI | manutenção / F4.3 | operador hispanofalante não consegue selecionar idioma próprio | P | Baixo | **Concluído — PUBLICADO** (`v1.9.66`, `2026-08-15`) | Opção `es`, catálogo espanhol com interface operacional traduzida e fallback EN seguro para mensagens técnicas; página pública de bloqueio em espanhol; pacote assinado `SHA256=c621b4f803cf527e01678886eb93f3aa73d55f304c3c265bcc7d3da2f00e3b18`; daemon, licença e `.244` intactos. |
| BG-158 | Corrigir integridade de tradução PT/EN/ES | Alta | package/GUI | manutenção / F4.3 | fallback ES para EN e chaves históricas EN no PT causam interface mista | P | Médio | **Concluído — PUBLICADO** (`v1.9.67`, `2026-08-15`) | Catálogo ES sem fallback EN, catálogo PT para chaves históricas EN, textos dinâmicos de Eventos localizados e gate PT/EN/ES reforçado. Pacote assinado `SHA256=0d85bbf00f33147c7655fa0ab091a71b02c4f7dcb4149d877da2b03a2ad1fdb3`; sem daemon, licença ou deploy `.244`. |
| BG-159 | Completar inglês de Configurações e defaults da página de bloqueio | Alta | package/GUI | manutenção / F4.3 | textos EN parcialmente em português e defaults PT persistidos após troca de idioma | P | Médio | **Concluído — PUBLICADO** (`v1.9.68`, `2026-08-15`) | Catálogo EN corrigido, defaults internos localizados por idioma e migração limitada aos defaults quando o idioma é alterado. Pacote assinado `SHA256=d73fd0ea3b41fc8c621ae5572ec826bdae271a1abfa8f4ba8aa05a3d8d243f3f`; conteúdo personalizado, daemon, licença e `.244` intactos. |
| BG-160 | Aplicar migração de defaults após upgrade | Alta | package/GUI | manutenção / F4.3 | idioma já selecionado após upgrade impede migração dos defaults PT | P | Baixo | **Concluído — PUBLICADO** (`v1.9.69`, `2026-08-15`) | Salvar Configurações aplica o idioma selecionado aos defaults reconhecidos, mesmo sem troca de idioma. Pacote assinado `SHA256=b08acf83798da7bd3541194bcf5758febada8aa0794423930afc6a162f928735`; conteúdo personalizado, daemon, licença e `.244` intactos. |
| BG-161 | Upgrade de licença / toggle editado não ligam Identity/MITM sozinhos | Alta | daemon + package | manutenção / add-on | token no `.lic` ligava o mapa Identity sem toggle; leftover JSON ON podia ressuscitar no upgrade | P | Alto | **Concluído — PUBLICADO** (`v1.9.71`, `2026-08-22`) | Runtime = entitlement ∧ toggle. Saiu junto com BG-162 (não houve `1.9.70` publicado). Residual: root a patchar o produto (R-A). |
| BG-162 | Sinal de instalação/heartbeat sem serial + página Instalações no portal | Alta | license-server + package/daemon | manutenção / F7 | pacote instalado sem chave é invisível; operador não sabe quem usa | G | Alto | **Concluído — PUBLICADO** (`v1.9.71`, overlay `.244` `20260823T022826Z`) | ADR-0036. Endpoint live `POST /api/license/install-ping` **OK** (provado `2026-08-25`). Residual cliente: BG-163. Caixas ≤`1.9.69` não pingam. |
| BG-163 | Cliente install-ping `1.9.71` falha em silêncio | Alta | package/daemon | manutenção / F7 | endpoint live 200 mas Instalações vazia; `config.inc` CLI + curl fora do PATH + retry 24 h | M | Alto | **Concluído — PUBLICADO** (`v1.9.72`) | Lê `config.xml`; `php -f`; `/usr/local/bin/curl`; fallback `hardware_id`; tick 15 min + throttle PHP. Sem overlay `.244` (P0-1). |
| BG-164 | Canal publico latest-only (so o ultimo pacote para download) | Alta | distribuicao / F7 | manutenção / F7 | releases antigas + textos com instaladores velhos permitem caixas invisíveis e `latest` stale no repo de origem | P | Alto | **Concluído — PUBLICADO** (`2026-08-25`; latest actual `v1.9.79`) | ADR-0003 §12; `Layer7` = `latest` + blacklists; `pfsense-layer7` sem Releases de pacote; tags git preservadas |
| BG-165 | Auditoria de licença no cliente (D-1, D-5, PKG-1..6) | Alta | daemon + package | manutenção / F7 | curl sem path no daemon; badge «Sem licença» com `.lic`; disarm nunca chamado; fallback inventava 2.ª linha no portal | M | Alto | **Concluído — PUBLICADO** (`v1.9.73`, `SHA256=9ea84e54115280c53f3b77f5359bd99e652839a8aebf8a5eb22d9b1ecf0352af`) | `/usr/local/bin/curl` + flock check-in; badge via `.lic` verificado; disarm em revoke/import/save; ping sem inventar `hardware_id`; versão via `pkg query`. Sem overlay `.244`. |
| BG-166 | Auditoria de licença no license-server (LS-1/2, LS-3, LS-5/6) | Alta | license-server | manutenção / F7 | UPSERT apaga inventário; activate/download sem `normalizeFeatures`; logs com XFF cru | M | Medio | **FEITO no git privado; live `.244` pendente de GO** | COALESCE/NULLIF no UPSERT; `normalizeFeatures` em activate/download; `getClientIp` em activate/check-in. Sem rsync/overlay (P0-1). |
| BG-167 | GUI mostra «aplicar» sem licença (falso positivo) | Alta | package/GUI | manutenção / F4.3 | cliente testa bloqueio, vê vermelho «aplicar» e conclui que o produto falha | P | Alto | **Concluído — PUBLICADO** (`v1.9.74`, `2026-08-26`) | Badge/diagnósticos usam `enforce_mode`; sem `enforce_mode=1` nunca «aplicar». Pacote assinado `SHA256=bb4cc7810b26d2246ffd71912d04b0c83299eb826f09b7a324a83dfa42084542`. Sem overlay `.244`. |
| BG-168 | PF arma enforce sem licença (pedido JSON) | Critica | package/PF | manutenção / F4.3 | modo aplicar + sem `.lic` injecta anti-QUIC/blocks no pfSense; cai a Internet | P | Alto | **Concluído — PUBLICADO** (`v1.9.75`, `2026-08-26`) | PF só arma com pedido aplicar **e** `enforce_mode=1`. Pacote assinado `SHA256=90e5bb2e6369ca2c5b2ce5afc926cacd2ea0fdd2426b13d400901b1de3c72e75`. `v1.9.74` retirada (latest-only). Sem overlay `.244`. |
| BG-169 | Perfil rápido Pornografia (renome + catálogo) | Media | package/GUI | manutenção / F4.3 | atalho adulto tinha 15 hosts e nome pouco visível; cliente pede perfil Pornografia | P | Medio | **Concluído — PUBLICADO** (`v1.9.76`, `2026-08-26`) | Id `adulto` estável; nome **Pornografia**; 64 hosts + `AdultContent`; preset infantil alargado; i18n EN/ES. Pacote assinado `SHA256=a7d6ba444351f57611c1a6ca70c480bce1b26322425577330b01e6cac805bcc0`. `v1.9.75` retirada (latest-only). Sem overlay `.244`. |
| BG-170 | Check-in obrigatório (remover opt-out do cliente) | Critica | daemon + package/GUI | manutenção / licença | cliente desliga o toggle e usa o `.lic` até expiry após cancelamento | P | Alto | **Concluído — PUBLICADO** (`v1.9.77`, `2026-08-31`) | GO produto: filtro de Internet não tem air-gap. Daemon ignora JSON false; GUI sem checkbox; `save_json` força true. N3 intacto. Emenda ADR-0032. Fecha A-04 / BG-101 no cliente. Pacote assinado `SHA256=1b595f5014316f0fa25e52a974b1e7137a13ec443af80f5e000849c103445f57`. `v1.9.76` retirada (latest-only). Sem overlay `.244`. |
| BG-171 | Pornografia: host OU AdultContent (`match_mode`) | Critica | daemon + package/GUI | manutenção / F4.3 | política mista AND fazia `pornhub.com` falhar sem categoria nDPI; simulador caía em `p-mon-001` | P | Alto | **Concluído — PUBLICADO** (`v1.9.78`, `2026-08-31`) | `match_mode=or` só em `profile-adulto`; default AND intacto. Migração sem duplicar. Simulador = daemon. Pacote assinado `SHA256=8b7b9a67bd24b275c37ac4df57de68ecc270b5d7f6d411c7423fa942f1eafff7`. `v1.9.77` retirada (latest-only). Residual: outros perfis mistos (ex. escolar) continuam AND de propósito. |
| BG-172 | Eventos em linguagem de operador | Media | package/GUI | manutenção / F4.3 | `flow_decide`/`dns_query`/`dns_resolved` só fazem sentido para técnicos | P | Medio | **Concluído — PUBLICADO** (`v1.9.78`, `2026-08-31`) | Cartões com título + frase; detalhe cru opt-in; filtro casa texto humano e linha crua; `dns_resolved` não entra no SQLite. Residual: log operacional continua genérico («Mensagem do sistema»). |
| BG-173 | Captive Portal nativo + anti-bypass (`AppleiCloud`) | Critica | daemon + package/PF | manutenção / F4.3 | `legacy_global` + `AppleiCloud` bloqueia `gateway.icloud.com` para todos após ACCEPT do portal; iOS CNA falha | P | Alto | **Concluído — PUBLICADO** (`v1.9.79`, `2026-08-31`) | Remove `AppleiCloud` do default de fábrica; migração idempotente; `layer7_localnets` via L7ALLOW; sem rdr Layer7 no IP do portal nativo; CNA ignorado sem `pass quick`. Default continua `legacy_global`. Pacote assinado `SHA256=26ef9ef1b28bee63a886bb169ead27208292548b29b47149280c1a8acfcaa482`. `v1.9.78` retirada (latest-only). Residual: prova iOS CNA no appliance. |
| BG-174 | Redesenho integral do frontend Layer7 com paridade funcional | Alta | package/GUI + docs + testes | manutenção F4/F5/F7; ondas GUI0–GUI7 | reclamação generalizada; páginas longas, edição fora da dobra, perda de contexto e ambiguidade de efeitos | G | Alto | **GUI0 FEITO `3563757`; ADR-0037 FEITA `f44a14b`; emenda visual nativa FEITA `c429be3`; emenda frontend-only implementada, pendente gates/commit; GUI1–GUI7 aguardam GO** (`2026-08-31`) | Produto funcional congelado: reorganizar somente a view. Consumir `head.inc`/`foot.inc`, `Form_*` e assets pfSense; alvo zero CSS visual próprio. Sem daemon/PF/licença/defaults/handlers; Identity+MITM preservada e fechada. |

**Nota sobre BG-101:** reaberto em **`30.1b`** (`2026-08-10`) como **lacuna comercial
a corrigir** (achado A-04), via ADR-0032 / BG-118 — decisão humana n.º 5 na ficha
[`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md).
Antes: `Documentado` como design da ADR-0021. Execução no passo `30.14` (GO próprio).

---

## Itens explicitamente fora da fila imediata

Os itens abaixo continuam conhecidos, mas **nao entram agora**:

- console central multi-firewall;
- MITM/TLS inspection **universal sempre ON** (MITM **opt-in** está na trilha Identity+MITM / BG-087);
- captive portal Layer7 (usar o do pfSense);
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
