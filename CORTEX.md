# CORTEX.md

## Finalidade

Este ficheiro e o **SSOT operacional e documental** do projecto Layer7.
Qualquer agente, maintainer ou novo chat deve conseguir retomar o contexto
do projecto a partir daqui sem depender de memoria implícita.

Se houver conflito entre documentos, a ordem de prevalencia e:

1. `CORTEX.md`
2. `docs/README.md`
3. `docs/02-roadmap/roadmap.md`
4. `docs/02-roadmap/backlog.md`
5. `docs/02-roadmap/checklist-mestre.md`
6. `docs/00-overview/document-classification.md`
7. `docs/00-overview/document-equivalence-map.md`
8. Documentacao canónica por area
9. Documentos historicos preservados na raiz e em subpastas

---

## Visao executiva do projecto

**Produto:** Layer7 para pfSense CE
**Empresa:** Systemup Solucao em Tecnologia
**Estado funcional conhecido:** V1 Comercial concluida e publicada
**Ultima versao do pacote publicada em release:** `1.8.3` (referencia de instalacao
em `docs/10-license-server/MANUAL-INSTALL.md` e GitHub Releases).
**Versao do port no branch actual (`package/pfSense-pkg-layer7` / `PORTVERSION`
+ `PORTREVISION`):** `1.8.11_10` (`PORTVERSION=1.8.11`, `PORTREVISION=10`;
artefacto ainda nao publicado; actualizar manual e procedimentos na mesma
entrega de release).
**Data-base deste checkpoint:** `2026-04-24`

O Layer7 e um pacote proprietario para pfSense CE com daemon `layer7d`,
GUI integrada, classificacao Layer 7 via nDPI, politicas granulares,
enforcement PF, anti-bypass DNS, blacklists UT1, relatorios locais e
licenciamento baseado em ficheiro `.lic` assinado com Ed25519.

O produto **nao esta em fase de descoberta funcional**. A prioridade agora
e preservar o que ja funciona, reduzir risco tecnico e evoluir por fases
controladas, com governanca forte e zero regressao desnecessaria.

---

## Estado actual

### Estado funcional

- V1 Comercial ja foi concluida e publicada.
- O pacote publico de referencia continua a ser o `.pkg` distribuido via
  GitHub Releases.
- A ultima publicacao conhecida no canal publico continua a ser o pacote
  `1.8.3` (V1 comercial estavel). O repositório pode antecipar o proximo
  `pkgver` (actualmente `1.8.11` no `Makefile` do port) antes da release; nao
  misturar isso com o pacote publicado sem fechar o bloco de build/validacao.
- Bloqueio QUIC configuravel por interface na GUI e restricao
  `to !<localsubnets>` em bloqueios permanecem como base funcional conhecida.
- O license server existe e esta operacional como componente separado,
  com F2 concluida e a F3 aberta para endurecer o contrato real de
  licenciamento/activacao sem regressao.

### Estado documental

- A Fase 0 documental foi usada para consolidar governanca, canonicidade,
  backlog, roadmap, checklist mestre e mapas de classificacao/equivalencia.
- O planeamento detalhado da F1 foi consolidado sem implementacao tecnica,
  com ADRs formais para distribuicao, cadeia de confianca, blacklists e
  fallback/degradacao segura.
- O directorio `docs/` passa a ser o **centro documental canónico**.
- A raiz actual do repositório continua preservada como **legado importante**.
- **Nao houve reorganizacao fisica** do repositório nesta fase.

### Estado de governanca

- O projecto passa a trabalhar pela ordem segura **F0 -> F7**.
- Nenhuma reorganizacao fisica de pastas esta autorizada antes da **F6**.
- Nenhuma alteracao tecnica deve avancar sem impacto, risco, teste e rollback
  declarados.

---

## Objectivo estrategico

Manter o Layer7 operacionalmente previsivel e tecnicamente auditavel,
priorizando:

- preservacao da V1 ja entregue;
- cadeia de confianca real entre codigo, builder, servidor de licencas e
  artefacto distribuido;
- hardening de componentes pos-V1 sem inflar escopo;
- continuidade entre chats e entre mantenedores;
- documentacao viva, rastreavel e com canonicidade explicita.

---

## Principios obrigatorios

1. **Nao regredir comportamento existente.**
2. **Um bloco pequeno por vez.**
3. **Documentacao e execucao caminham juntas.**
4. **Sem reestruturacao fisica antes da hora.**
5. **Sem “solucoes magicas” nem refactors impulsivos.**
6. **Tudo relevante precisa de gate, risco, teste e rollback.**
7. **Se existir conflito documental, ele deve ser declarado e classificado.**
8. **Na duvida, conservar e documentar.**

---

## Fase actual

**Fase actual consolidada:** F3 aberta em 2026-04-01; F3.1, F3.2, F3.3, F3.4 e F3.5 executadas de forma conservadora em 2026-04-01; F3.6 formalizada documentalmente em 2026-04-01 com matriz canónica de validacao manual/evidencias; F3.7 formalizada de forma conservadora em 2026-04-02 com pack operacional, convencao de evidencias e helper shell barato; F3.8 formalizada de forma conservadora em 2026-04-02 com gate oficial de fechamento, matriz objectiva de decisao por cenario e relatorio final de campanha; F3.9 executada em 2026-04-02 como primeira campanha real controlada (run_id `20260402T130015Z-deploy244`), com evidencias reais de backend e conclusao formal `F3 nao pode fechar`; F3.10 concluida em 2026-04-02 como saneamento documental-operacional da validacao, com matriz de pre-requisitos, matriz de drift operacional e runbook da proxima campanha real; a verificacao de readiness da F3.11 foi executada em 2026-04-02 e resultou em `F3.11 bloqueada por pre-requisitos nao satisfeitos`; o saneamento minimo seguinte da readiness foi registado em `docs/01-architecture/f3-11-readiness-saneamento.md` e concluiu `readiness parcialmente saneada, mas ainda bloqueada`; a rodada documental-operacional seguinte materializou `docs/01-architecture/f3-11-access-enablement-package.md`, `docs/05-runbooks/f3-11-live-access-checklist.md` e `docs/01-architecture/f3-11-drift-registry.md` para transformar os blockers actuais em pacote canonico de desbloqueio, sem abrir campanha e mantendo `F3.11 bloqueada`; a rodada documental-operacional seguinte materializou `docs/01-architecture/f3-11-external-input-request-package.md`, `docs/01-architecture/f3-11-input-acceptance-matrix.md`, `docs/05-runbooks/f3-11-evidence-intake-template.md`, `docs/05-runbooks/f3-11-input-triage-runbook.md` e `docs/01-architecture/f3-11-readiness-reopen-gate.md` para transformar os cinco insumos externos em processo canonico de solicitacao, recepcao, validacao, aceite e `go/no-go`, sem reabrir decisoes, sem campanha e mantendo `F3.11 bloqueada`; a rodada documental-operacional seguinte materializou `docs/01-architecture/f3-11-execution-master-register.md`, `docs/01-architecture/f3-11-operational-decisions-ledger.md`, `docs/01-architecture/f3-11-readiness-scorecard.md`, `docs/05-runbooks/f3-11-cycle-report-template.md` e `docs/00-overview/f3-11-document-traceability-map.md` para converter o kit da F3.11 num cockpit canónico unico de acompanhamento, decisao, score, rastreabilidade e execucao operacional ponta a ponta, sem codigo, sem push, sem campanha e sem reabrir a readiness; a rodada documental-operacional seguinte materializou `docs/01-architecture/f3-11-state-machine.md`, `docs/01-architecture/f3-11-document-sync-protocol.md`, `docs/00-overview/f3-11-start-here.md`, `docs/01-architecture/f3-11-operational-responsibility-matrix.md` e `docs/05-runbooks/f3-11-cycle-closure-criteria.md` para transformar o cockpit da F3.11 num sistema canonico completo de estados, sincronizacao, entrada unica, responsabilidades e fecho de ciclo, mantendo explicitamente `F3 aberta`, `F3.11 bloqueada`, `readiness = NO-GO`, `campanha = NO-GO`, sem codigo, sem push e sem reabrir a readiness; a rodada documental-operacional seguinte materializou `docs/00-overview/f3-organizacao-local-e-fecho.md` e `docs/01-architecture/f3-fecho-operacional-restante.md`, e corrigiu o `start-here`, o mapa de rastreabilidade, o registro mestre, o scorecard e o drift registry para o estado real observado da fase: license-server live alinhado, auth administrativa alinhada, inventario real obtido, DR-01/DR-03/DR-04/DR-06 resolvidos, DR-02 reclassificado como cosmetico nao bloqueante e DR-05 mantido como unico blocker real para fechar a F3; em 2026-04-14, os commits locais foram publicados no `origin/main`, a trilha DR-05 ganhou helpers executaveis para preflight/appliance/GUI, o helper GUI foi exercitado em `probe` com resultado `BLOCKED` esperado sem credencial valida, e o branch passou a cobrir por teste o contrato `409` do `POST /api/activate` para licenca revogada, expirada e hardware divergente.

**Resultado actual conhecido da F1:** a F1.1 fechou o contrato oficial de
distribuicao sobre `.pkg`, URLs versionadas de release e scripts oficiais de
install/uninstall no canal publico. A F1.2 materializou manifesto versionado,
assinatura destacada Ed25519, public key de verificacao e separacao
builder/signer na cadeia de release. A F1.3 materializou manifesto dedicado
de blacklists, public key propria, origem oficial HTTPS, mirror controlado,
cache local e last-known-good restauravel no appliance. A F1.4 materializou a
matriz de fallback por componente: install/update da release passa a validar
manifesto, assinatura e checksum antes do `pkg add`, blacklists passam a
registar `healthy`/`degraded`/`fail-closed` em `.state/fallback.state`, e a
degradacao deixa de ficar implícita na trilha F1.

**Resultado actual conhecido da F2:** o estado real do license server foi
consolidado em `license-server/`, o planejamento detalhado da fase foi
fechado com ADRs normativos para publicacao segura, autenticacao/sessao,
protecao da superficie administrativa e integridade transacional do CRUD, e
a **F2.1** materializou a politica de publicacao segura: `443/TLS` passa a
ser o unico canal publico oficial, `8445` permanece como origin privado com
bind local por defeito, o Nginx interno deixa explicita a fronteira com o
edge proxy, e a documentacao operacional passa a tratar HTTP directo apenas
como troubleshooting controlado. A **F2.2** materializou o contrato de
autenticacao e sessao administrativa: login passa a exigir HTTPS/TLS real,
o frontend deixa de depender de JWT em `localStorage`, a sessao passa a ser
stateful no backend com `admin_sessions`, cookie `HttpOnly + Secure +
SameSite=Strict`, expiracao ociosa/absoluta, renovacao controlada e logout
com invalidacao real no servidor. A **F2.3** materializou a protecao da
superficie administrativa: o backend deixa de operar com `cors()` aberto, o
painel passa a aceitar apenas o origin oficial same-origin em producao, o
login administrativo passa a ter limiter dedicado por IP e por `email + IP`,
lockout temporario por falhas repetidas, politica minima de erro sem
enumeracao de credenciais e trilha minima de auditoria em `admin_audit_log`
e `admin_login_guards` para auth e mutacoes administrativas. A **F2.4**
materializou a integridade do CRUD administrativo: `activate`, `licenses` e
`customers` passam a validar payload com schema fechado, queries/listagens
passam a rejeitar parametros invalidos, mutacoes administrativas passam a
operar com codigos HTTP coerentes (`400`, `404`, `409`, `500`), `activate`
e mutacoes com auditoria passam a usar transacoes explicitas, e o delete
normal do painel passa de remocao fisica para arquivo logico com preservacao
de historico via `archived_at` / `archived_by_admin_id`. A **F2.5**
materializou o fecho operacional da fase: `ED25519_PRIVATE_KEY` passa a
aceitar tambem `_FILE`, o bootstrap administrativo ganha CLI explicito para
`init` e `reset-password`, `seed.js` fica apenas como compatibilidade, o
stack passa a ter scripts minimos de `backup-postgres.sh` /
`restore-postgres.sh`, e a operacao oficial passa a ter runbooks canónicos
de segredos/bootstrap e backup/restore.

**Resultado actual conhecido da F3:** a fase foi formalmente aberta em
`2026-04-01` pela **F3.1**, com mapeamento factual do fluxo actual de
licenciamento/activacao no backend e no daemon, contrato canónico minimo de
estados/transicoes, clarificacao da diferenca entre expiracao online e grace
local do daemon, e um primeiro endurecimento defensivo em `POST /api/activate`
para tornar a reactivacao do mesmo hardware mais idempotente e previsivel sem
quebrar compatibilidade. A **F3.2** fechou de forma conservadora a leitura do
fingerprint/binding em cenarios reais de appliance e a normalizacao defensiva
do `hardware_id` persistido. A **F3.3** fechou a semantica real de expiracao,
revogacao, validade offline e grace em documento canónico proprio, e
materializou um helper minimo de estado efectivo no backend para alinhar
`activate`, `licenses`, `customers` e `dashboard` sem mudar schema, formato
`.lic` ou algoritmo de fingerprint. A **F3.4** fechou a superficie
administrativa real de mutacao/reemissao, formalizou a imutabilidade parcial
de campos criticos apos bind e bloqueou a mudanca de `customer_id` em licenca
activada/bindada no CRUD normal, evitando transferencia silenciosa de
ownership sem abrir workflow novo de rebind. A **F3.5** fechou a trilha real
de emissao/reemissao do `.lic`, passou a distinguir emissao inicial de
reemissao legitima no fluxo publico e reforcou a rastreabilidade minima do
artefacto emitido em `activate` e `download`, sem mudar payload, formato
`.lic` ou criterio de validacao do daemon. A **F3.6** passa a formalizar em
`docs/01-architecture/f3-validacao-manual-evidencias.md` a leitura factual
da validabilidade actual, a matriz de cenarios obrigatorios/desejaveis, os
comandos objectivos de recolha de evidencia e a politica oficial de
"validacao suficiente" da F3, sem fingir que a execucao real em
lab/appliance ja aconteceu. A **F3.7** passa a formalizar em
`docs/01-architecture/f3-pack-operacional-validacao.md` o pack operacional
para essa execucao, com directoria por `run_id`, nomes uniformes de
ficheiros, estados `PASS` / `FAIL` / `INCONCLUSIVE` / `BLOCKED`, template
markdown por cenario e helper shell barato para exportar evidencias de
backend sem mudar o contrato do produto. A **F3.8** passa a formalizar em
`docs/01-architecture/f3-gate-fechamento-validacao.md` o gate oficial de
fechamento da F3, a matriz objectiva de decisao por cenario, a classificacao
de pendencias bloqueantes vs nao bloqueantes e o relatorio final unico de
campanha em `docs/tests/templates/f3-validation-campaign-report.md`, sem
declarar a F3 fechada sem outputs reais. A **F3.9** executou a primeira
campanha real controlada dessa trilha no `run_id`
`20260402T130015Z-deploy244`, usando o backend vivo
`https://license.systemup.inf.br` / `192.168.100.244:8445` como ambiente
observado. O resultado foi objectivo e binario: `0 PASS`, `3 FAIL`,
`1 INCONCLUSIVE` e `9 BLOCKED`, com veredito final `F3 nao pode fechar`.
Os blockers concretos observados foram: drift do deploy real face ao contrato
canónico da F2/F3 (schema live sem `admin_sessions`, `admin_audit_log` e
`admin_login_guards`, e `POST /api/activate` live a responder `403` onde a
F3.8 exige `409`), ausencia de appliance pfSense autenticavel para a metade
local da campanha e ausencia de credencial administrativa autorizada para
S04-S06/S10 sem mexer no deploy vivo. A **F3.10** converteu estes achados em
artefactos canónicos de saneamento: a matriz de pre-requisitos em
`docs/01-architecture/f3-matriz-prerequisitos-campanha.md`, a matriz de
drift operacional em
`docs/01-architecture/f3-matriz-drift-operacional.md` e o runbook sequencial
da proxima campanha em
`docs/01-architecture/f3-runbook-proxima-campanha-real.md`. A verificacao
seguinte, materializada em
`docs/01-architecture/f3-11-readiness-check.md`, nao abriu campanha: o
backend publico e o origin continuaram acessiveis, mas a F3.11 ficou
bloqueada por falta de acesso a shell/DB do deploy observado, falta de
credencial administrativa autorizada, falta de appliance pfSense
autenticavel e falta de inventario real `LIC-A` a `LIC-F`. O saneamento
minimo seguinte, registado em
`docs/01-architecture/f3-11-readiness-saneamento.md`, confirmou ainda que
`origin/main` permanece em `66e00f5a36e78056aae27df6aea0ccbd0ed78553`,
enquanto o branch local continuava `ahead 18`, e observou no live uma
divergencia adicional de politica HTTP/admin: `/api/auth/login` continua a
aceitar `Origin` externo com `Access-Control-Allow-Origin: *`, contrariando o
contrato canónico `same-origin only` da F2.3.

O checkpoint seguinte de `2026-04-14` alinhou o `license-server` live em
`192.168.100.244:/opt/layer7-license`: `admin_sessions`,
`admin_audit_log` e `admin_login_guards` passam a existir no ambiente activo,
`/api/auth/session` volta a responder no contrato stateful actual e
`/api/auth/login` volta a falhar fechado para `Origin` externo. Com isso, os
blockers administrativos do live deixam de bloquear a F3, ficando apenas o
`DR-05` do appliance como blocker real remanescente.

**Trilha activa dentro da F3:** `F3.11 alinhada no license-server live em 2026-04-14 — o ambiente activo em 192.168.100.244:/opt/layer7-license passa a expor admin_sessions, admin_audit_log e admin_login_guards, /api/auth/session volta a responder com sessao stateful e bridge Bearer, e /api/auth/login volta a falhar fechado para Origin externo com 403; DR-01 (schema admin), DR-03 (auth/admin), DR-04 (inventario) e DR-06 (same-origin) ficam resolvidos no ambiente activo; DR-02 (contrato 409 vs 403) fica coberto no branch por testes de regressao do activate e resta apenas como alinhamento de deploy quando houver publicacao; resta como unico blocker real da F3 apenas DR-05 (cenarios locais do appliance); a F3 permanece aberta ate os cenarios do appliance serem executados`

### Ordem segura das fases

| Fase | Nome | Estado | Intencao |
|------|------|--------|----------|
| F0 | Governanca documental | consolidada em `2026-04-01` | fixar canonicidade, continuidade e backlog |
| F1 | Cadeia de confianca e seguranca critica | concluida em `2026-04-01` | fechar contrato oficial de distribuicao, autenticidade de artefactos, blacklists e fallback |
| F2 | Hardening do license server | concluida em `2026-04-01` | endurecer deploy, segredos, backup e fronteiras operacionais |
| F3 | Robustez de licenciamento/activacao | aberta em `2026-04-01` | tornar activacao, revogacao e modo offline previsiveis |
| F4 | Confiabilidade package/daemon/blacklists | **F4.0 aberta** em `2026-04-24` (F3 ainda aberta; ver `f4-plano-de-implementacao.md`) | reduzir falhas operacionais e alinhar runtime com docs e gates |
| F5 | Malha de testes e regressao | preparacao (`f5-preparacao-malha.md`); fase plena apos criterio F4 | formalizar cobertura, evidencias e gates de nao regressao |
| F6 | Reorganizacao estrutural controlada | planeada | mover/normalizar estrutura apenas com mapa e rollback |
| F7 | Observabilidade e release engineering | planeada | fortalecer telemetria, verificacao de artefactos e governanca de release |

---

### Checkpoint F4 adicional (`1.8.11_10`)

Bloco tecnico em curso no branch de trabalho, ainda sem release publica:
reload seguro de blacklists passa a preservar a blacklist anterior e as
tabelas activas se a nova carga falhar; DNS/SNI de blacklists passam a casar
categoria por regra e origem do cliente antes de popular `layer7_bld_N`;
dominios pertencentes a multiplas categorias passam a preservar mascara de
categorias; GUI/package passam a preparar permissoes de
`/usr/local/etc/layer7/blacklists` para `www:wheel` e a falhar visivelmente se
`config.json` ou `_custom/*.domains` nao puderem ser gravados; cron de
auto-update passa a mapear `update_interval_hours` para campos cron coerentes;
CI ganha syntax check shell; `force_dns` deduplica pares (interface, CIDR)
entre regras de blacklist ao injectar `natrules/layer7_nat`; a lista de
interfaces efectivas para `rdr` e ordenada alfabeticamente; CIDRs validos por
regra sao unicos, ordenados e validados uma vez por regra antes do cruzamento
com interfaces. Gate real permanece no builder FreeBSD e no appliance pfSense;
smoke local em macOS nao conta como evidencia de fase e fica bloqueado por
defeito no script.

**Checkpoint documental-operacional adicional:** `docs/08-lab/guia-windows.md`
foi reclassificado como historico/legado, sem comandos activos. O fluxo
vigente passa a ficar explicito: macOS e apenas workspace de edicao/git/docs;
build e smoke tecnico no builder FreeBSD; validacao funcional no pfSense
appliance. O job Windows do CI foi removido para nao sugerir caminho de
validacao fora da operacao real.

---

## Proximos passos autorizados

1. Manter a **ordem segura** de fases (F0–F7) e o backlog; **nao reabrir** F1/F2
   como trabalho novo; **F6/F7** continuam fora de escopo ate os gates
   respectivos. A **F3** permanece em fecho: **DR-05** (appliance) e relatorio
   de campanha sob gate F3.8. Em **paralelo operacional e documental**,
   a **F4.0+** esta autorizada conforme
   `docs/02-roadmap/f4-plano-de-implementacao.md` (package/daemon/blacklists
   **sem** mudar o contrato de licenciamento salvo bloco aprovado).
2. Usar `docs/00-overview/f3-11-start-here.md`,
   `docs/00-overview/f3-organizacao-local-e-fecho.md` e
   `docs/01-architecture/f3-fecho-operacional-restante.md` como entrada
   curta da rodada actual: o license-server live, a auth administrativa e o
   inventario real ja estao alinhados para a F3; o proximo passo pratico
   seguro e executar somente o `DR-05` no appliance `192.168.100.254`, com
   snapshot/rollback, permissao suficiente para os cenarios mutaveis e
   evidencias por `run_id`.
3. Tratar a
   `docs/01-architecture/f3-matriz-drift-operacional.md` e o novo
   `docs/01-architecture/f3-11-drift-registry.md` como listas canónicas de
   desvios e blockers ainda abertos antes da F3.11, sem corrigir live "no
   escuro".
4. So reexecutar o readiness check da F3.11 depois de os pre-requisitos
   pendentes estarem realmente disponiveis e o
   `docs/00-overview/f3-11-document-traceability-map.md`,
   `docs/05-runbooks/f3-11-input-triage-runbook.md`,
   `docs/05-runbooks/f3-11-evidence-intake-template.md` e
   `docs/05-runbooks/f3-11-cycle-report-template.md` e
   `docs/05-runbooks/f3-11-live-access-checklist.md` poderem ser usados sem
   placeholders e sem saltar registos; sem isso, nao abrir campanha.
5. Reexecutar a campanha real da F3 em novo bloco proprio, usando
   `docs/01-architecture/f3-validacao-manual-evidencias.md` como matriz
   factual, `docs/01-architecture/f3-pack-operacional-validacao.md` como
   pack de recolha/classificacao das evidencias,
   `docs/01-architecture/f3-gate-fechamento-validacao.md` como gate oficial
   de saida, e
   `docs/01-architecture/f3-runbook-proxima-campanha-real.md` e
   `docs/05-runbooks/f3-11-live-access-checklist.md` como ordem sequencial
   minima da F3.11.
6. So declarar a F3 fechada se o relatorio final de campanha indicar
   explicitamente `F3 pode fechar`, com todos os cenarios obrigatorios da
   F3.8 em `PASS`; qualquer `FAIL`, `INCONCLUSIVE` ou `BLOCKED` obrigatorio
   mantem a F3 aberta.
7. **F4:** seguir `docs/02-roadmap/f4-plano-de-implementacao.md` — subfases
   F4.1 (package/daemon), F4.2 (blacklists), F4.3 (enforcement), um bloco de
   risco de cada vez, com `MANUAL-INSTALL` e docs de area actualizados quando
   o pacote for afectado.
8. **F5:** tratar `docs/02-roadmap/f5-preparacao-malha.md` como roteiro de
   preparacao; a fase F5 fica “em execucao plena” depois de cumprir os
   criterios de saida da F4 e de actualizar a matriz em `docs/tests/`.
9. Usar o backlog canónico como fila unica antes de tocar em
  codigo, empacotamento, daemon, frontend ou scripts operacionais, salvo
  excepção explícita no plano de fase (F4 paralela a F3).
10. O **checklist mestre** inclui gates de teste para **F4** (paralelismo com
  a F3): **F4.1** / **BG-009** (`validacao-lab` sec. **10a**, `test-matrix`
  **3.8**), **F4.2** / **BG-010** (sec. **10b**, **12.1–12.2**), **F4.3** /
  **BG-011** (sec. **11**, **6.7**) antes de declarar fechadas as trilhas
  respectivas em relatorio.

---

## Riscos abertos

- A cadeia actual entre repositório, builder com ficheiros sensiveis locais,
  chave publica embutida e artefacto publicado ainda precisa de formalizacao.
- O fluxo oficial ja saiu de `main`, mas referencias historicas a
  `raw.githubusercontent.com/.../main` ainda coexistem em material legado e
  precisam continuar classificadas como nao normativas.
- O canal oficial passa a publicar `.pkg`, `.pkg.sha256`, `install.sh`,
  `uninstall.sh`, `release-manifest.v1.txt`, assinatura destacada e public key
  de verificacao; a F1.4 integrou esta validacao no `install.sh` publicado,
  mas isso continua a depender de o signer carimbar o asset versionado com a
  public key oficial e o fingerprint esperado.
- A trilha de blacklists deixou o feed HTTP directo, mas a disponibilidade
  ainda depende de publicar o manifesto assinado na origem oficial
  `downloads.systemup.inf.br` e no mirror controlado em GitHub Releases.
- GitHub, builder e origem UT1 continuam como dependencias externas fortes;
  a F1 reduziu o risco do lado do consumidor com manifesto assinado,
  mirror/cache/LKG e install fail-closed, mas nao elimina a necessidade
  operacional de publicar snapshots e releases aprovadas.
- A F2.1 fechou a ambiguidade de publicacao do license server: o canal
  publico oficial passa a ser `https://license.systemup.inf.br`, o origin
  `8445` fica preso ao loopback por defeito e o Nginx interno passa a
  aceitar apenas o host oficial e troubleshooting local controlado.
- A F2.1 passa a depender operacionalmente de certificado valido na borda,
  redirect `HTTP -> HTTPS`, allowlist/firewall coerente para o origin
  `8445` e ausencia de exposicao publica directa desse origin.
- A F2.2 passa a depender operacionalmente de o canal administrativo ficar
  sempre atras de HTTPS/TLS real e de o cookie `Secure` nao ser degradado por
  acessos directos ao origin privado.
- A F2.3 passa a depender operacionalmente de o origin oficial
  `https://license.systemup.inf.br` continuar a ser o unico origin de browser
  permitido em producao, de os limites de login (`10/10 min` por IP e
  `5/10 min` por `email + IP`) permanecerem calibrados para o uso real e de
  os operadores consultarem `admin_audit_log`/`admin_login_guards` em
  incidente em vez de alargarem a superficie administrativa.
- A F2.5 fechou o ownership minimo de segredos, bootstrap administrativo e
  backup/restore do banco, mas a rotacao formal da chave Ed25519 em incidente
  e a automacao ampliada de retention/observabilidade continuam fora da F2.
- A F3.3 passou a centralizar o estado efectivo da licenca no backend, mas o
  modelo continua deliberadamente hibrido: `licenses.status = 'expired'`
  continua opcional/legado e a expiracao efectiva continua a ser derivada
  tambem por `expiry < CURRENT_DATE`.
- O daemon aceita `.lic` expirado em grace local de `14` dias, enquanto a
  activacao online recusa imediatamente licencas expiradas; a diferenca esta
  agora formalizada, mas ainda exige validacao manual/appliance na F3.6.
- A revogacao actual corta activacao e download no servidor, mas **nao**
  invalida imediatamente um `.lic` ja emitido que continue em uso offline; o
  risco operacional fica explicito e bloqueia qualquer rebind administrativo
  precoce.
- A F3.4 bloqueia a mudanca de `customer_id` em licenca bindada no CRUD
  normal, mas reemissao legitima da mesma licenca continua a poder coexistir
  com um `.lic` antigo ainda valido offline ate data/grace.
- A F3.5 melhora a trilha auditada do artefacto emitido, mas o sistema ainda
  nao tem contador/versionamento consumido pelo daemon nem enforcement de
  "artefacto mais recente unico"; em 2026-04-14, a metadata auditada de
  emissao/reemissao passou a ter regressao dedicada para `flow`,
  `emission_kind`, binding, customer/features e hashes SHA-256 do artefacto.
- A F3.6 formaliza a matriz de evidencias, a F3.7 operacionaliza essa
  recolha com pack e helper shell baratos, e a F3.8 formaliza o gate de
  fechamento e o relatorio final de campanha; ainda assim, a robustez da F3
  continua dependente de executar em lab/appliance os cenarios obrigatorios
  de grace, revogacao com `.lic` antigo, coexistencia de artefactos e drift
  real de fingerprint sem abrir escopo tecnico novo.
- A F3.9 revelou drifts reais no ambiente observado, mas o checkpoint de
  `2026-04-14` reclassificou o estado corrente: `DR-01` schema/admin,
  `DR-03` auth/admin, `DR-04` inventario e `DR-06` same-origin foram
  saneados no `license-server` live activo; `DR-02` ficou como divergencia
  cosmetica de codigo HTTP (`403` vs `409`) sem bloquear a F3, e o branch
  actual passou a cobrir o contrato `409` do `activate` com testes de
  regressao.
- O contrato de estado efectivo do license-server (`active`, `expired`,
  `revoked`, expiracao por data, precedencia de revogacao, binding e
  predicados SQL) passou a ter regressao dedicada em 2026-04-14.
- O payload publico de activacao (`key` + `hardware_id`) passou a ter
  regressao dedicada em 2026-04-14, cobrindo normalizacao, campos
  inesperados e rejeicoes `400` antes da transacao de activacao.
- O guardrail de update administrativo que bloqueia mudanca de `customer_id`
  em licenca activada/bindada passou a ter regressao dedicada em
  2026-04-14, preservando a proteccao contra transferencia silenciosa de
  ownership.
- O unico blocker real remanescente da F3 e `DR-05`: cenarios locais do
  appliance `192.168.100.254` que ainda exigem permissao suficiente para
  reescrever `/usr/local/etc/layer7.lic`, controlar o daemon, executar
  offline/online, grace/relogio, snapshot/restore e NIC/UUID/clone com
  evidencias por `run_id`.
- Em `2026-04-14`, o run read-only
  `20260414T123526Z-appliance254-permissions` confirmou que `codex` nao tem
  escrita no `.lic`; o rc.d passou a aplicar `chmod 0644` ao pidfile apos
  arranque (F4.1) para `service layer7d status` nao falhar por falta de
  leitura do PID; o daemon continua auditavel por `pgrep` e stats JSON; isto
  melhora a evidencia de DR-05, mas nao fecha os cenarios mutaveis.
- Os pacotes antigos de cinco insumos da F3.11 permanecem como memoria
  documental-operacional, mas nao sao mais o gate corrente para continuar a
  F3. O caminho actual e consultar `f3-11-start-here.md`,
  `f3-organizacao-local-e-fecho.md`, `f3-fecho-operacional-restante.md`,
  o scorecard, o registro mestre e o drift registry, e executar apenas o
  bloco `DR-05`.
- `DR-07` proveniencia exacta do deploy continua aberto como governanca
  operacional/F7: nao autoriza inferir live = local = remoto, mas tambem nao
  bloqueia os cenarios de licenciamento do appliance na F3.
- Apos cada bloco de alteracoes, confirmar `git status` e `main` alinhado a
  `origin/main` antes de assumir o estado alheio; commits/push em bloco unico
  (sem misturar saneamento documental com mudanca funcional nao relacionada).
- Nao existe ainda trilha dedicada para transferencia entre clientes,
  desrevogacao ou rebind seguro com governanca explicita.
- O fingerprint continua dependente de `SHA256(kern.hostuuid + ":" + primeira
  MAC Ethernet nao-loopback)`; mudanca de NIC, VM, reinstall ou ordem de
  interfaces ainda pode exigir validacao dedicada na F3.
- O `docs/` tem areas canónicas e areas apenas suplementares/historicas;
  sem ler a classificacao, um agente pode seguir um documento antigo.
- Existem documentos antigos ainda a mencionar `.txz`, `v0.x` e estados
  pre-V1; isso esta agora classificado, mas a limpeza fisica fica para a F6.
- O tutorial longo e alguns guias de lab continuam uteis, mas nao devem ser
  tratados como SSOT para instalacao ou governanca.
- O builder possui alteracoes locais de producao que **nao podem ser
  commitadas**, exigindo disciplina operacional.

---

## Restricoes

- foco em **pfSense CE**;
- pacote **proprietario** com EULA Systemup;
- distribuicao publica por **`.pkg` via GitHub Releases**;
- sem software pago obrigatorio;
- V1 sem MITM TLS universal;
- V1 sem console central multi-firewall;
- V1 sem analytics pesado;
- sem reorganizacao fisica antes da F6;
- sem alterar codigo-fonte, package, daemon, license server, frontend,
  scripts operacionais ou logica funcional durante a F0 e durante o
  planeamento documental da F1.

---

## Regras de nao regressao

1. Nenhuma fase tecnica pode alterar mais de um subsistema critico ao mesmo
   tempo sem justificacao e rollback explicitos.
2. Nenhuma alteracao funcional entra sem declarar:
   - objectivo;
   - impacto;
   - risco;
   - teste;
   - rollback.
3. `docs/10-license-server/MANUAL-INSTALL.md` e a referencia canónica para
   instalacao, upgrade, reinstall e desinstalacao do pacote.
4. `docs/changelog/CHANGELOG.md` e a linha temporal oficial de releases e
   correccoes; o `CORTEX.md` nao deve voltar a carregar changelog detalhado.
5. Nenhum agente deve assumir que documentos da raiz ainda sao canónicos so
   porque foram a base original do projecto.
6. Antes da F6, conflitos estruturais resolvem-se por **classificacao e
   equivalencia**, nao por mover/apagar ficheiros.

---

## Hierarquia documental

### Documentos canónicos de governanca

- [`docs/README.md`](docs/README.md)
- [`docs/02-roadmap/roadmap.md`](docs/02-roadmap/roadmap.md)
- [`docs/02-roadmap/backlog.md`](docs/02-roadmap/backlog.md)
- [`docs/02-roadmap/checklist-mestre.md`](docs/02-roadmap/checklist-mestre.md)
- [`docs/02-roadmap/f1-plano-de-implementacao.md`](docs/02-roadmap/f1-plano-de-implementacao.md)
- [`docs/02-roadmap/f4-plano-de-implementacao.md`](docs/02-roadmap/f4-plano-de-implementacao.md)
- [`docs/02-roadmap/f5-preparacao-malha.md`](docs/02-roadmap/f5-preparacao-malha.md)
- [`docs/00-overview/document-classification.md`](docs/00-overview/document-classification.md)
- [`docs/00-overview/document-equivalence-map.md`](docs/00-overview/document-equivalence-map.md)
- [`docs/03-adr/README.md`](docs/03-adr/README.md)

### Documentos canónicos por area

- Produto/escopo: [`docs/00-overview/product-charter.md`](docs/00-overview/product-charter.md)
- Arquitectura alvo: [`docs/01-architecture/target-architecture.md`](docs/01-architecture/target-architecture.md)
- Arquitectura de confianca F1: [`docs/01-architecture/f1-arquitetura-de-confianca.md`](docs/01-architecture/f1-arquitetura-de-confianca.md)
- Arquitectura de seguranca F2 do license server: [`docs/01-architecture/f2-arquitetura-license-server.md`](docs/01-architecture/f2-arquitetura-license-server.md)
- Plano F2: [`docs/02-roadmap/f2-plano-de-implementacao.md`](docs/02-roadmap/f2-plano-de-implementacao.md)
- Plano F4: [`docs/02-roadmap/f4-plano-de-implementacao.md`](docs/02-roadmap/f4-plano-de-implementacao.md)
- Preparacao F5: [`docs/02-roadmap/f5-preparacao-malha.md`](docs/02-roadmap/f5-preparacao-malha.md)
- Instalacao/operacao do pacote: [`docs/10-license-server/MANUAL-INSTALL.md`](docs/10-license-server/MANUAL-INSTALL.md)
- Changelog: [`docs/changelog/CHANGELOG.md`](docs/changelog/CHANGELOG.md)
- Core tecnico: [`docs/core/README.md`](docs/core/README.md)
- Testes: [`docs/tests/README.md`](docs/tests/README.md)

### Legado preservado

- Os documentos `00-` a `16-` na raiz continuam preservados para contexto,
  rastreabilidade e compatibilidade de links, mas deixaram de ser a fonte
  primaria de decisao.

---

## Ordem de leitura obrigatoria

### Para qualquer novo chat ou agente

1. `CORTEX.md`
2. [`docs/README.md`](docs/README.md)
3. [`docs/02-roadmap/roadmap.md`](docs/02-roadmap/roadmap.md)
4. [`docs/02-roadmap/backlog.md`](docs/02-roadmap/backlog.md)
5. [`docs/02-roadmap/checklist-mestre.md`](docs/02-roadmap/checklist-mestre.md)
6. [`docs/00-overview/document-classification.md`](docs/00-overview/document-classification.md)
7. [`docs/00-overview/document-equivalence-map.md`](docs/00-overview/document-equivalence-map.md)
8. [`docs/03-adr/README.md`](docs/03-adr/README.md)
9. [`docs/01-architecture/f1-arquitetura-de-confianca.md`](docs/01-architecture/f1-arquitetura-de-confianca.md)
10. [`docs/02-roadmap/f1-plano-de-implementacao.md`](docs/02-roadmap/f1-plano-de-implementacao.md)

### Para trabalho tecnico numa area especifica

- Instalacao, upgrade, desinstalacao, rollback operacional:
  [`docs/10-license-server/MANUAL-INSTALL.md`](docs/10-license-server/MANUAL-INSTALL.md)
- Arquitectura/config/policy:
  [`docs/01-architecture/target-architecture.md`](docs/01-architecture/target-architecture.md)
  e [`docs/core/README.md`](docs/core/README.md)
- License server e licenciamento:
  [`docs/10-license-server/PLANO-LICENSE-SERVER.md`](docs/10-license-server/PLANO-LICENSE-SERVER.md)
  e [`docs/10-license-server/MANUAL-USO-LICENCAS.md`](docs/10-license-server/MANUAL-USO-LICENCAS.md)
  e [`docs/01-architecture/f3-validacao-manual-evidencias.md`](docs/01-architecture/f3-validacao-manual-evidencias.md)
- Distribuicao, builder, blacklists e fallback seguro:
  [`docs/01-architecture/f1-arquitetura-de-confianca.md`](docs/01-architecture/f1-arquitetura-de-confianca.md),
  [`docs/02-roadmap/f1-plano-de-implementacao.md`](docs/02-roadmap/f1-plano-de-implementacao.md),
  [`docs/03-adr/README.md`](docs/03-adr/README.md)
- Blacklists UT1:
  [`docs/11-blacklists/PLANO-BLACKLISTS-UT1.md`](docs/11-blacklists/PLANO-BLACKLISTS-UT1.md),
  [`docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md`](docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md),
  [`docs/11-blacklists/REGRAS-QUALIDADE.md`](docs/11-blacklists/REGRAS-QUALIDADE.md)
- Testes e validacao:
  [`docs/tests/README.md`](docs/tests/README.md)
  e [`docs/04-package/validacao-lab.md`](docs/04-package/validacao-lab.md)

---

## Mapa rapido do repositorio

- `docs/` -> centro documental canónico progressivo
- `src/` -> codigo-fonte do daemon, PoC e modulos de runtime
- `package/pfSense-pkg-layer7/` -> port e ficheiros do pacote pfSense
- `webgui/` -> documentacao curta da GUI e referencias auxiliares
- `license-server/` -> backend, frontend e nginx do servidor de licencas
- `scripts/` -> build, package, lab, diagnostico e release
- `tests/` -> material de teste e fixtures
- `samples/` -> amostras de configuracao e politicas
- raiz `00-16` -> legado documental preservado

---

## Componentes principais

1. **Pacote pfSense**
   - entrega o pacote instalavel, metadados, hooks e GUI integrada
2. **Daemon `layer7d`**
   - carrega configuracao, classifica trafego, decide politicas e alimenta PF
3. **Engine de classificacao**
   - nDPI como decisao congelada de V1
4. **Policy engine**
   - regras por interface, IP/CIDR, grupo, horario, host, app e categoria
5. **Enforcement PF**
   - bloqueio por origem/destino, forcing DNS, tabelas de excepcoes e blacklist
6. **Servidor de licencas**
   - stack separada para activacao, gestao administrativa e emissao de `.lic`
7. **Sistema documental**
   - governanca, roadmap, backlog, ADRs, runbooks, changelog e guias por area

---

## Decisoes congeladas

- foco exclusivo em **pfSense CE**
- produto comercial/proprietario da **Systemup**
- artefacto publico de distribuicao: **`.pkg`**
- activacao/licenciamento via ficheiro `.lic` assinado com **Ed25519**
- nDPI continua como engine de classificacao
- sem MITM universal na V1
- sem console central nesta etapa
- `docs/` e o centro canónico progressivo
- a raiz actual e **legado importante**, nao lixo
- reorganizacao fisica so na **F6**

---

## Politica de continuidade entre chats

Cada novo chat deve conseguir responder, sem ambiguidade:

1. Em que fase o projecto esta.
2. O que e canónico e o que e historico.
3. Qual e o ultimo estado seguro conhecido.
4. O que pode e o que nao pode ser mexido agora.
5. Qual e o proximo passo autorizado.

Para isso:

- o `CORTEX.md` deve ser lido antes de qualquer accao;
- o estado de fase deve ser mantido alinhado ao roadmap canónico;
- backlog, ADR index e checklist mestre devem ser actualizados sempre que
  houver mudanca real de prioridade, decisao ou gate;
- no fim de cada bloco relevante, registrar um checkpoint seguro.

### Chat longo no Cursor (handoff)

Quando a conversa se tornar **demasiado longa** (muitas trocas, contexto
difícil de seguir, ou limites do produto), mudar para **um chat novo** em vez
de empurrar todo o historico. O procedimento canónico e o **prompt modelo**
estao em
[`docs/00-overview/handoff-chat-novo.md`](docs/00-overview/handoff-chat-novo.md).
O agente pode sugerir esse movimento; o utilizador pode exigi-lo a qualquer
momento. O estado oficial do projecto continua sempre no **Git** e no
**CORTEX**, nao na memoria do chat.

---

## Politica de documentacao viva

| Quando algo mudar | Documentos obrigatorios |
|-------------------|-------------------------|
| fase actual, gate ou sequencia aprovada | `CORTEX.md`, `docs/02-roadmap/roadmap.md`, `docs/02-roadmap/backlog.md`, `docs/02-roadmap/checklist-mestre.md` |
| decisao arquitectural, de seguranca ou de distribuicao | `docs/03-adr/README.md`, ADR novo/actualizado, `CORTEX.md` |
| cadeia de confianca, distribuicao, blacklists ou fallback seguro | `docs/01-architecture/f1-arquitetura-de-confianca.md`, `docs/02-roadmap/f1-plano-de-implementacao.md`, backlog, ADR index |
| instalacao, upgrade, uninstall, rollback, caminhos, comandos ou versao publicada | `docs/10-license-server/MANUAL-INSTALL.md`, runbooks afectados, changelog, release docs |
| mudanca funcional relevante | changelog, docs da area, `CORTEX.md`, backlog/status da fase |
| reorganizacao estrutural | mapa de equivalencia, classificacao documental, roadmap/checklist da F6 |
| release publicada | changelog, release notes, `MANUAL-INSTALL.md`, `CORTEX.md` |

---

## Bloco fixo de checkpoint

```text
CHECKPOINT CANONICO
- Data base: 2026-04-24
- Produto: Layer7 para pfSense CE
- Ultima versao .pkg publicada (referencia operacional): 1.8.3
- PORTVERSION no repositorio (pre-release / proximo build): 1.8.11 (PORTREVISION 10)
- Estado funcional: V1 Comercial concluida e publicada; F3 aberta
- Estado documental: governanca F0 consolidada; F1 e F2 concluidas; F3 em
  fecho operacional (blocker: DR-05 no appliance)
- Fase actual: F3 (robustez de licenciamento/activacao)
- Proxima fase elegivel apos F3: F4
- Reorganizacao fisica autorizada: nao (F6)
- Artefacto publico actual: .pkg via GitHub Releases
- Fonte canónica de instalacao: docs/10-license-server/MANUAL-INSTALL.md
- Fonte canónica de prioridade: docs/02-roadmap/backlog.md
- Fonte canónica de gates: docs/02-roadmap/checklist-mestre.md
```

---

## Ultimo status seguro conhecido

### Tecnico

- A referencia de **instalacao publica** continua a ser o pacote `1.8.3` ate
  nova release; o branch carrega `PORTVERSION=1.8.11` com `PORTREVISION=10` para
  o proximo empacotamento.
- O produto ja contem enforcement PF, forcing DNS, blacklists UT1,
  relatorios locais e licenciamento funcional.
- Na linha 1.8.3+ conhecida: bloqueio QUIC por interface na GUI; retrocompat
  com `block_quic:true` (legado global).

### Documental

- A canonicidade passou a estar explicitamente declarada.
- O projecto ja nao depende da raiz como fonte principal de governanca.
- O backlog, o roadmap, o checklist mestre e o mapa de equivalencia passam a
  servir de ponte segura entre chats e entre fases.
- A F1.2 passou a ter manifesto versionado, assinatura destacada e cadeia
  builder -> signer -> publish documentada e executavel.
- A F1.3 passou a ter manifesto dedicado de blacklists, public key propria,
  origem oficial `downloads.systemup.inf.br`, mirror controlado em GitHub
  Releases, cache local em `.cache`, estado activo em `.state` e
  last-known-good em `.last-known-good`.

### Operacional

- A F1.4 (fallback/fail-closed da distribuicao) ja esta concluida; a
  intervencao corrente prioriza o **fecho da F3** (validacao com evidencia,
  sobretudo `DR-05` no appliance) antes de abrir a F4. Reorganizacao de
  arvore de ficheiros continua proibida antes da F6.
