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
| BG-028 | Activar pela primeira vez a trust chain F1.2/F1.4 do pacote nas releases publicas (manifesto Ed25519 assinado, `install.sh` carimbado fail-closed) | Alta | release-engineering/seguranca | F7 | continuar a publicar pacotes sem manifesto/assinatura como em `v1.7.8` a `v1.8.11_13` | M | Alto | **Fase 0 (ADR-0023)** | Fase 1 pendente custodia chave humana; caminho manual vigente |
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
| BG-049 | Caminho B / E4 — semantica AND/OR + validacao GUI | Alta | daemon/gui | Caminho B (E4) | app+host OR alarga bloqueio; match vazio catch-all | M | Alto | Em execucao (parcial `_25`) | `_25` recusa scoped block sem origem/global/quarentena; `match_mode` e alinhamento policy-matrix continuam pendentes |
| BG-050 | Caminho B / E5 — app/site por destino; quarentena de origem opt-in | Alta | daemon/package | Caminho B (E5) | política de aplicação cortar toda a Internet do cliente | M | Alto | Em execucao (corrigido no candidato `_27`) | app/categoria normal e host usam `pdst`; somente `quarantine_origin=true` usa `psrc`; gate appliance pendente |
| BG-051 | Caminho B / E6 — SNI/CDN/anti-bypass | Media | daemon/docs | Caminho B (E6) | falsos positivos CDN; limites nao expostos na GUI | M | Medio | Planeado | cdn_mode; avisos sni_inspection |
| BG-052 | Caminho B / E7/E8 — testes two-client + release default scoped | Alta | testes/release | Caminho B (E7/E8) | sem rede de seguranca scoped; default legacy permanente | G | Alto | Em curso (E7 parcial) | regressões `_25` para PID/interface/psrc/híbrido; `smoke-enforcement-scoped.sh`; build e gate appliance two-client pendentes; default scoped_hybrid so em E8 |
| BG-053 | Estabilizacao `_25` — ciclo de vida, captura real e integração scoped | Critica | package/daemon/gui | Caminho B pre-gate | reload pode duplicar daemon; interface amigavel produz `captures=0`; scoped pode criar tabela sem regra | M | Alto | Em execucao (`2026-07-29`) | codigo + testes direcionados PASS; build FreeBSD, monitor/captura e two-client no pfSense Plus pendentes; rollback `_24` passivo |
| BG-054 | Contenção L1 de logs — separar operação/tráfego, limitar rotação/SQLite e reduzir ruído | Alta | daemon/package/gui/docs | F4.1 (contenção; L2/L3 em F7) | logs ilimitados ocuparem disco e interface não distinguir vista, histórico e ficheiros | M | Alto | Código/build concluídos; gate appliance pendente (`1.8.11_26`) | ADR-0015; detalhe opt-in, bloqueios auditados, 5 MiB × 4 por destino e SQLite 100 MiB por default; local/PHP/SQLite/build PASS |
| BG-055 | Estabilização funcional pré-produção — captura bidireccional, enforcement imediato e precedência segura | Critica | daemon/package/PF/docs | Caminho B pré-gate | nDPI receber meia conversa; app normal quarentenar cliente; estado PF manter sessão; allow ser anulada por blacklist | M | Alto | Código/build concluídos; gate appliance pendente (`1.8.11_27`) | hash canónico, app→pdst, state kill, TTL SNI, self-heal e flush em mutação; SHA256 `8eae978d…d5388`; FP-017 segue para `_28`; produção intocada |
| BG-056 | Enforcement PF de allow/excepção escopado | Critica | daemon/package/PF | Caminho B pré-produção | decisão allow não superar destino já presente em tabela por outro cliente/regra | G | Alto | Código/suite/build concluídos (`1.8.11_28`); appliance pendente | ADR-0016; `pallow_N` + `L7ALLOW`, sem `pass quick`; smoke/portabilidade realinhados; SHA256 `62dd9ae5…9dc6`; exige `pfctl -nf` e regressão two-client |
| BG-057 | Sintaxe PF anti-QUIC por interface | Critica | package/PF/testes | F4.3 pré-produção | `inet on <if>` fazer o reload inteiro falhar quando anti-QUIC estiver activo | P | Alto | Código, parser sintético e build concluídos (`1.8.11_29`); appliance instalado pendente | FP-018; função pura gera `on <if> inet`; `pfctl -nf -` PASS no pfSense 26.03.1; pacote extraído PASS no FreeBSD 15 (`SHA256 bea385dd…01840`); `_28` supersedido |
| BG-058 | Preservar estado nDPI sob buracos/colisões da tabela de fluxos | Critica | daemon/captura/testes | Caminho B pré-produção | mesmo fluxo ganhar dois estados nDPI ou ser descartado silenciosamente, causando classificação intermitente | M | Alto | Código/suite/build concluídos (`1.8.11_30`); appliance pendente | FP-019; probe completo antes de inserir; evicção do menos recente; métricas `cap_active/evicted/dropped` no JSON; SHA256 `3a54c667…e9b40` |
| BG-059 | Aguardar estado final do nDPI antes da decisão | Critica | daemon/captura/testes | Caminho B pré-produção | resultado parcial TLS/QUIC ser congelado antes do refinamento para app/SNI, causando falso allow intermitente | P | Alto | Código/suite/build concluídos (`1.8.11_31`); appliance pendente | FP-020; `NDPI_STATE_CLASSIFIED` como contrato; `ndpi_detection_giveup()` no orçamento de 48 pacotes; SHA256 `dc5118dd…453e33` |
| BG-060 | Fechar auditoria E2E Etapa 1 e executar **Bloco B1** — gate passivo `_31` no appliance | Critica | testes/governanca/release | Caminho B pré-gate | `_31` corrige código mas sem evidência física; veredicto **NO-GO** para publicar ou activar enforce | G | Alto | Em execucao (`2026-07-30`) | Etapa 1 + multitask concluídos: `auditoria-end-to-end-2026-07-29.md`, `diagnostico-multitask-2026-07-30.md`, matrizes G0–G7, **AUD-001..015**; próximo passo = G2–G4 com snapshot/rollback `_24` |
| BG-061 | Flush PF completo em lifecycle (exc_allow, blacklist delete, pkg-deinstall) | Media | package/PF | Caminho B pré-gate | reorder/delete deixa tabelas órfãs; hook deinstall desalinhado de `flush-all` | P | Medio | Concluido codigo (`1.8.11_32`); rebuild/gate appliance pendente | B-002/B-003/B-004; R-21 PASS; gate uninstall/reorder no lab |
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

## Checkpoint trilha Identity + MITM Add-on (aberta 2026-08-05; rev. `d` 2026-08-06)

- Arranque: [`START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)
- Posicionamento PME: [`posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md) — **ACEITE**
- Plano: [`plano-identity-mitm-addon.md`](plano-identity-mitm-addon.md) rev. `2026-08-06d`
- Passo actual: **20.11a / IM3** (baseline perf); **IM1/GI1 PASS**; **IM2 DEFER 20.7a**
- ADRs: 0025/0027/0028 Aceito; **0026 Aceito — implementação diferida**
- Baseline produção: **`1.9.8`**. Captive portal: **fora de escopo**. Nicho: **PME Identity-first**.

| ID | Item | Severidade | Area | Fase | Risco se adiado | Esforco | Beneficio | Status | Notas |
|----|------|------------|------|------|-----------------|---------|-----------|--------|-------|
| BG-085 | Governança IM0: START-HERE, plano, mapa, gates, índices, ADRs | Alta | documentacao/governanca | F4+/novo plano | drift; chat sem continuidade | M | Alto | **Concluido (20.2 PASS / GI0)** | ADRs 0025–0028 Aceito; T1 |
| BG-086 | Entitlements `features` CSV + gates daemon/GUI/license-server (IM1) | Critica | licenciamento | IM1 | add-on sem enforcement comercial | G | Alto | **Concluido (20.6 / GI1 PASS)** | check-in ∩ .lic + gates |
| BG-087 | MITM TLS opt-in + CA (IM2) | Critica | daemon/package/PKI | IM2 | UX HTTPS; segundo produto | G | Alto | **Diferido (20.7a)** | ADR-0026 rev. d; Squid rejeitado; GI2/GI3 DEFERRED; reabrir só com novo GO + helper próprio |
| BG-088 | Identity map **daemon** + LDAP/LDAPS (IM3–IM4) — **caminho de valor PME** | Critica | daemon/GUI | IM3–IM4 | user/grupo sem mapa dinâmico | G | Alto | Planeado / **próximo** | ADR-0027; barra UX posicionamento; 20.11a primeiro |
| BG-089 | RADIUS **accounting receiver** + **agente DC** (IM5) | Critica | daemon/ops | IM5 | Identity incompleto | G | Alto | Planeado | WinRM outbound não canónico |
| BG-090 | Políticas `ad_users`/`ad_groups` → identity_ips (IM6) | Alta | package/daemon | IM6 | directório sem enforcement útil | G | Alto | Planeado | GI7; não-regressão IP/MAC |
| BG-091 | Agente endpoint + TS/VDI (IM7–IM8) | Media | endpoint | IM7–IM8 | multi-user/NAT frágil | G | Medio | Planeado / adiável | GI8 ou ADR exclusão |
| BG-092 | Fecho lab/release add-on (IM9) | Alta | testes/F7/docs | IM9 | feature sem MANUAL/release | M | Alto | Planeado | GI9; foco release Identity (MITM diferido) |

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
| BG-101 | Revogação remota fail-open até offline max (~14d) | Baixa | licenciamento | F3 | design ADR-0021; janela longa se rede cortada | — | — | Documentado | não é bug; rever só com GO comercial |
| BG-102 | Allowlist PF sem `match inet6` (L7ALLOW só inet) | Alta | package/PF | F4/hardening | IPs v6 na tabela allow_dst sem tag; block inet6 ignora allowlist | P | Alto | **Concluido (`1.9.10`)** | `layer7_pf_inet46_rules` + helper; smoke `pfctl -sr` PASS |
| BG-103 | TOCTOU `pfctl -f /tmp/rules.debug` (check≠use) | Alta | daemon/package/PF | F4/hardening | ruleset arbitrário entre `stat` e `pfctl -f` | M | Alto | **Concluido (`1.9.11`)** | open+O_NOFOLLOW+fstat → `pfctl -f -` (stdin); PHP+helper+daemon |
| BG-104 | DNS observe residual (spoof com txid+client) | Alta | daemon/capture | F4/hardening | sniffer LAN spoofa resposta com ID visto | M | Alto | **Concluido (`1.9.11`)** | pend client+txid+resolver+qname; allowlist auto-seed+`dns_observe_resolvers[]`; limite: spoof-as-resolver L2 |

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
