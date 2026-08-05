# Checklist Mestre — Evolucao Segura

Este checklist e o gate operacional das fases F0-F7. Ele nao substitui o
roadmap nem o backlog; ele transforma ambos em disciplina executavel.

---

## Como usar

1. Antes de abrir uma fase, passar pelo checklist de entrada.
2. Durante a execucao, manter checklist documental e de rollback vivos.
3. Antes de encerrar a fase, validar o checklist de saida e o gate.
4. Se a mudanca for documental apenas, aplicar a excepcao documental.

---

## Checklist de entrada de fase

- [ ] a fase actual esta correcta no `CORTEX.md`
- [ ] a fase a abrir esta autorizada no roadmap canónico
- [ ] o backlog foi revisto e os itens puxados para a fase foram marcados
- [ ] riscos abertos da fase anterior foram herdados ou encerrados
- [ ] existe escopo claro e exclusoes claras
- [ ] existe criterio de saida e gate definidos
- [ ] foi verificado se ADR novo e necessario
- [ ] foi verificado se a fase mexe em area com manual/runbook proprio

---

## Checklist de execucao

- [ ] um subsistema critico por vez
- [ ] objectivo, impacto, risco, teste e rollback declarados
- [ ] na F3, contrato canónico de estados/transicoes foi definido antes do primeiro endurecimento de codigo
- [ ] na F3.2, matriz operacional de fingerprint/binding foi formalizada antes de qualquer ajuste adicional de codigo
- [ ] na F3.3, semantica de expiracao/revogacao/grace e validade offline do `.lic` foi formalizada antes de qualquer ajuste adicional de codigo
- [ ] na F3.6, matriz canónica de validacao manual/evidencias foi formalizada antes de qualquer ajuste adicional de codigo
- [ ] na F3.7, pack operacional com nomes de ficheiros, estados de resultado e template de evidencia foi formalizado antes de qualquer tentativa de “automatizar” o laboratorio
- [ ] na F3.8, gate canónico de fechamento, classificacao bloqueante/nao bloqueante e relatorio final unico de campanha foram formalizados antes de qualquer declaracao de fecho da F3
- [ ] sem refactor amplo nao solicitado
- [ ] sem mover/apagar/renomear antes da F6
- [ ] sem tratar documento historico como SSOT
- [ ] docs afectadas identificadas antes da primeira alteracao
- [ ] backlog e roadmap alinhados ao bloco em execucao

---

## Checklist de saida de fase

- [ ] criterios de saida da fase foram cumpridos
- [ ] gate da fase foi verificado
- [ ] riscos remanescentes ficaram registados no `CORTEX.md`
- [ ] backlog da proxima fase ficou actualizado
- [ ] docs canónicas ficaram coerentes entre si
- [ ] changelog foi actualizado quando houve mudanca tecnica ou release
- [ ] rollback permanece claro depois da alteracao

---

## Checklist documental

- [ ] `CORTEX.md` reflecte fase, estado, proximos passos e riscos
- [ ] `docs/README.md` continua a apontar para as fontes certas
- [ ] roadmap e backlog continuam alinhados
- [ ] checklist mestre reflecte gates reais
- [ ] ADR index foi revisto
- [ ] classificacao documental foi revista se surgiu novo conflito
- [ ] mapa de equivalencia foi revisto se surgiu nova sobreposicao
- [ ] `MANUAL-INSTALL.md` foi actualizado se houve impacto operacional

---

## Checklist de testes e gates

- [ ] o tipo de mudanca tem teste minimo definido
- [ ] existe evidencia suficiente para marcar o bloco como concluido
- [ ] a validacao ocorreu no ambiente correcto para o risco da mudanca
- [ ] gates documentais e gates tecnicos nao foram confundidos
- [ ] nenhum “OK” foi marcado sem prova minima
- [ ] na F3.6, cada cenario obrigatorio tem comando objectivo, expectativa e evidencia minima declarados
- [ ] na F3.7, cada execucao manual obrigatoria tem directoria por `run_id`, ficheiros de evidencia com nome padronizado e decisao final `PASS` / `FAIL` / `INCONCLUSIVE` / `BLOCKED`
- [x] na F3.8, cada cenario obrigatorio so fecha a F3 se estiver em `PASS` com evidencia real; `FAIL`, `INCONCLUSIVE` ou `BLOCKED` obrigatorio mantem a fase aberta
- [x] na F3.8, existe relatorio final unico de campanha com contagem de resultados e conclusao explicita `F3 pode fechar` / `F3 nao pode fechar`
- [x] na F4 com F3 ainda aberta, blocos tecnicos respeitam o paralelismo do
  [`f4-plano-de-implementacao.md`](f4-plano-de-implementacao.md) (secção 0) —
  nao alterar o contrato de licenciamento salvo decisao e documentacao no
  mesmo bloco
- [ ] na F4.1 (**BG-009**), antes de declarar a trilha de **serviço/pidfile**
  concluida em relatorio, existe evidencia minima alinhada a
  [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) (secção
  **10a**) e ao teste **3.8** de [`../tests/test-matrix.md`](../tests/test-matrix.md)
- [ ] na F4.2 (**BG-010**), antes de declarar a trilha de **blacklists updater**
  concluida em relatorio, existe evidencia minima alinhada a
  [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) (secção
  **10b**) e aos testes **12.1** e **12.2** de
  [`../tests/test-matrix.md`](../tests/test-matrix.md)
- [ ] na F4.3 (**BG-011**), antes de declarar a trilha de **DNS forçado / anti-bypass**
  (`force_dns` / anchor `natrules/layer7_nat`; anti-QUIC por interface no branch,
  ex. `1.8.11_12`) concluida em relatorio, existe evidencia minima alinhada a
  [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) (secção **11**,
  incl. anti-QUIC opcional e cenário opcional multi-interface / VLAN no mesmo roteiro) e ao teste **6.7**
  de [`../tests/test-matrix.md`](../tests/test-matrix.md)
- [ ] **Caminho B E3 (BG-048):** codigo E0–E3 concluido em repo (`1.8.11_24`);
  gate **two-client** em [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md)
  (secção **12**) continua **PENDENTE** — nao declarar E3 fechado nem avancar E4
  sem evidencia no appliance; scripts preparados:
  [`../../tests/lab/smoke-enforcement-scoped.sh`](../../tests/lab/smoke-enforcement-scoped.sh),
  [`../../scripts/diagnose-layer7-appliance.sh`](../../scripts/diagnose-layer7-appliance.sh)
- [ ] **Estabilização `_25` (BG-053):** código/testes corrigem PID sem newline,
  interface real de captura, regra `psrc` e caminho app/host; antes de fechar,
  exigir build FreeBSD, `service layer7d onestatus` coerente, `captures > 0`
  numa interface real, monitor passivo e gate two-client no pfSense Plus
  `26.03.1`. `_25` continua candidato não publicado.
- [ ] **Contenção L1 `_26` (BG-054 / ADR-0015):** exigir suite local,
  PHP/SQLite e build FreeBSD; no appliance primeiro passivo, confirmar que
  idle/recheck/SIGUSR1 não inundam `info`, detalhe opt-in respeita interfaces,
  bloqueios continuam auditados e uso máximo corresponde aos limites.
  Limpar vista não pode apagar disco; limpar histórico deve preservar os logs.
- [ ] **Estabilização funcional `_27` (BG-055 / ADR-0014 emendada):** exigir
  build FreeBSD com nDPI, lint PHP, `pfctl -nf`, captura bidireccional,
  confirmação de kill de estado selectivo e gate two-client. Política normal
  de app deve bloquear só o destino; `psrc` exige quarentena explícita.
  Suite/build/artefacto passaram (`SHA256 8eae978d…d5388`); `pfctl -nf` e
  gates no appliance continuam pendentes.
- [ ] **Allow PF escopado (BG-056 / FP-017):** antes de produção, uma
  política/excepção allow do cliente A deve vencer destino já inserido por B,
  sem criar allow global e sem retirar o bloqueio de B.
- [ ] **Candidato `_28` (BG-056 / ADR-0016):** confirmar no ruleset que
  allowlist, política e excepção usam `match/tag L7ALLOW`, nunca `pass quick`;
  todos os blocks Layer7 relevantes usam `! tagged L7ALLOW`; uma regra nativa
  de bloqueio do pfSense continua a vencer. Exigir `pfctl -nf`, two-client,
  app-only cold-start documentado e rollback passivo. Suite/smoke/build
  passaram (`SHA256 62dd9ae5…9dc6`), mas `_28` foi supersedido por FP-018.
- [ ] **Candidato `_29` (BG-057 / FP-018):** anti-QUIC por interface deve
  emitir `on <if> inet|inet6`; amostra autocontida já passou no parser PF do
  appliance. Build nDPI, suite e pacote extraído `_29` passaram no builder
  (`SHA256 bea385dd…01840`). Exigir `pfctl -nf` do ruleset completo após
  instalação passiva e regressão com toggle antes de qualquer enforce.
- [ ] **Candidato `_30` (BG-058 / FP-019):** lookup deve procurar toda a
  janela antes de reutilizar slot expirado; janela cheia deve evictar o fluxo
  menos recente e contabilizar `cap_evicted`/`cap_dropped`. Build nDPI e
  pacote extraído passaram (`SHA256 3a54c667…e9b40`). Exigir JSON de stats
  válido, captura passiva real e pressão zero em tráfego normal antes do gate
  two-client.
- [ ] **Candidato `_31` (BG-059 / FP-020):** resultado
  `NDPI_STATE_PARTIAL` não pode finalizar o fluxo; aguardar
  `NDPI_STATE_CLASSIFIED` ou chamar `ndpi_detection_giveup()` ao atingir 48
  pacotes. Build nDPI e pacote extraído passaram
  (`SHA256 dc5118dd…453e33`). Exigir tráfego real que refine TLS genérico
  para aplicação/SNI antes do gate two-client.
- [ ] **Auditoria E2E Etapa 1 + multitask (BG-060):** inventário e consolidação
  concluídos em
  [`../09-blocking/auditoria-end-to-end-2026-07-29.md`](../09-blocking/auditoria-end-to-end-2026-07-29.md)
  e [`../09-blocking/diagnostico-multitask-2026-07-30.md`](../09-blocking/diagnostico-multitask-2026-07-30.md);
  ledger [`matriz-unificada-rev-fp-aud.md`](../09-blocking/matriz-unificada-rev-fp-aud.md);
  veredicto **NO-GO** para activar enforce; **AUD-001..015**.
  **Bloco B1** (install passivo candidato lab **`1.8.11_65`**): snapshot VM,
  G2.1–G2.5, G3 (`pfctl -nf` ruleset completo), G4 (captura/métricas) — **parar**
  se G2.5 falhar; G5 two-client só após G2–G4 PASS com `run_id`.
  SSOT gates: [`plano-gates-producao.md`](../09-blocking/plano-gates-producao.md);
  plano mestre: [`plano-fecho-producao-e-consolidacao.md`](plano-fecho-producao-e-consolidacao.md).
- [ ] **Flush lifecycle local (BG-061):** R-21 `test_flush_coverage.sh` PASS;
  correcções em repo; validar uninstall Package Manager no lab com candidato `_65`.

---

## Checklist de rollback

- [ ] rollback e explicitamente descrito
- [ ] rollback nao depende de memoria oral
- [ ] rollback preserva a ultima versao segura conhecida
- [ ] rollback de `_26` aponta para `_24` passivo e preserva evidência de logs
- [ ] impacto do rollback sobre docs e artefactos foi considerado
- [ ] para reorganizacao estrutural futura, rollback de links e caminhos foi previsto

---

## Checklist de release interno

Usar apenas quando o bloco envolver artefacto, publicacao ou distribuicao.

- [ ] changelog revisto
- [ ] release notes revistas
- [ ] `MANUAL-INSTALL.md` sincronizado com a versao real
- [ ] artefacto correcto identificado como `.pkg`
- [ ] `.pkg.sha256` publicado para o mesmo artefacto
- [ ] `install.sh` e `uninstall.sh` versionados publicados no release
- [ ] manifesto versionado publicado
- [ ] assinatura destacada do manifesto publicada
- [ ] public key de verificacao publicada
- [ ] disponibilidade do download verificada
- [ ] rollback de release documentado

---

## Excepcao documental

Quando a mudanca for **apenas documental**:

- [ ] nenhum ficheiro de codigo foi alterado
- [ ] nenhum ficheiro de package/build/install/runtime foi alterado
- [ ] nenhuma logica funcional foi tocada
- [ ] revisao de coerencia cruzada foi feita
- [ ] commit local foi feito
- [ ] push para o GitHub foi feito

**Nao exigido nesta situacao:** build, pacote `.pkg`, release e validacao em
appliance, salvo se o proprio pedido disser o contrario.

---

## Gate resumido por fase

| Fase | Gate minimo |
|------|-------------|
| F0 | um novo agente entende o projecto lendo poucos documentos canónicos |
| F1 | cadeia de confianca de distribuicao, blacklists e fallback deixa de depender de conhecimento implícito |
| F2 | license server opera com publicacao segura, sessao administrativa revogavel, bootstrap controlado e operacao sob controlo |
| F3 | activacao, revogacao e offline ficam previsiveis, com estados/transicoes explicitos, evidencias reais dos cenarios obrigatorios e relatorio final de campanha antes do fecho |
| F4 | package, daemon e blacklists com runtime mais confiavel e evidencia minima por subfase (ex.: F4.3 em `validacao-lab` / `test-matrix`); ver plano e backlog |
| F5 | existe malha real de nao regressao por componente |
| F6 | reorganizacao fisica acontece sem perda de contexto nem links quebrados |
| F7 | release e observabilidade deixam de depender de memoria operacional |

---

## Documentacao obrigatoria por fase

| Fase | Docs minimas |
|------|--------------|
| F0 | `CORTEX`, `AGENTS`, indice docs, roadmap, backlog, checklist, classificacao, equivalencia, ADR index |
| F1 | `CORTEX`, `docs/01-architecture/f1-arquitetura-de-confianca.md`, backlog, `docs/02-roadmap/f1-plano-de-implementacao.md`, ADR-0003 a ADR-0006 |
| F2 | `CORTEX`, `docs/01-architecture/f2-arquitetura-license-server.md`, `docs/02-roadmap/f2-plano-de-implementacao.md`, backlog, docs de licencas, runbooks de publicacao/sessao/segredos/backup-restore do servidor, ADR-0007 a ADR-0010 |
| F3 | `CORTEX`, `docs/01-architecture/f3-arquitetura-licenciamento-ativacao.md`, `docs/01-architecture/f3-fingerprint-e-binding.md`, `docs/01-architecture/f3-expiracao-revogacao-grace.md`, `docs/01-architecture/f3-mutacao-admin-reemissao-guardrails.md`, `docs/01-architecture/f3-emissao-reemissao-rastreabilidade.md`, `docs/01-architecture/f3-validacao-manual-evidencias.md`, `docs/01-architecture/f3-pack-operacional-validacao.md`, `docs/01-architecture/f3-gate-fechamento-validacao.md`, backlog, docs de licencas, matriz de testes, ADRs afectados |
| F4 | `CORTEX`, `f4-plano-de-implementacao.md`, backlog, `MANUAL-INSTALL`, docs de blacklists, runbooks, changelog |
| F5 | `CORTEX`, `f5-preparacao-malha.md`, backlog, docs de testes, checklist mestre, evidencias |
| F6 | `CORTEX`, backlog, classificacao, equivalencia, changelog estrutural |
| F7 | `CORTEX`, backlog, releases, changelog, `MANUAL-INSTALL`, checklist interno |

---

## Gates BG-064 / BG-065 / BG-066 (isencoes VIP e UX GUI)

Plano SSOT: [`plano-isencao-vip-e-ux-gui.md`](plano-isencao-vip-e-ux-gui.md).
Modelo conceptual: [`modelo-conceptual-gui.md`](../00-overview/modelo-conceptual-gui.md).

### BG-064 — Isencao VIP modal (`_48`)

- [ ] Secção "Isentos" no modal Perfis rapidos gere excepcao `vip-isentos`
- [ ] Grupos expandem para hosts/cidrs na gravacao
- [ ] Badge em `layer7_exceptions.php`; `toggle_profile_off` nao remove excepcao
- [ ] `php -l` + teste funcional PHP PASS
- [ ] Changelog, CORTEX, backlog actualizados

### BG-065 — UX modal + verificador (`_49`)

- [ ] Progressive disclosure (essencial vs Avancado)
- [ ] Atalho criar grupo quando lista vazia
- [ ] `layer7_test.php` com veredicto e motivo legivel
- [ ] Paridade simulacao com cadeia real documentada/testada

### BG-066 — Exclusao por politica (`_50`)

- [x] ADR-0019 aprovado
- [x] Daemon + PF (`layer7_pexc_N`) + flush/self-heal completos
- [x] Testes C/PHP/shell PASS (local; PHP no builder)
- [x] Cenario "gestor isento" em `validacao-lab.md` (Bloco E)
- [ ] Build `_50` + release GitHub + gate appliance §19.3

**Nota:** estes blocos nao alteram o veredicto NO-GO para enforce em producao
(gates G2–G7 permanecem pendentes).

---

## Gates BG-071 / BG-072 / BG-073 (Lista VIP global)

Feature «Lista VIP global»: isenção total com nome por entrada, limites daemon
coerentes e isenção no caminho DNS. Ordem de execução **A → B → C → D → E**.
ADR-0020 (Bloco A). SSOT da isenção: excepção `vip-isentos` (D1); labels em
`layer7["vip_meta"]["labels"]` (D2). Produção enforce continua `_24`.

### Bloco A — Governança documental

- [x] ADR-0020 aprovado (`docs/03-adr/ADR-0020-isencao-vip-dns.md`)
- [x] BG-071, BG-072, BG-073 registados no backlog
- [x] CORTEX, roadmap e checklist actualizados

### Bloco B — GUI Lista VIP (`_57`, BG-071)

- [x] Secção «Lista VIP (isencão total)» em `layer7_exceptions.php`
- [x] Labels em `vip_meta.labels`; validação de limites (32+16 desde Bloco C)
- [x] Export/import da lista; link «Gerir Lista VIP» no modal Perfis rápidos
- [x] Aviso DHCP static mapping; aviso sinkhole DNS até Bloco D
- [x] `php -l` + teste funcional PHP PASS
- [x] Release `1.8.11_57` publicada em `pablomichelin/Layer7`

### Bloco C — Limites daemon (`_58`, BG-072)

- [x] `L7_EXC_MAX_HOSTS=32`, `L7_EXC_MAX_CIDRS=16` em `policy.h`
- [x] Validação PHP/upsert alinhada; testes C parse/decisão PASS
- [x] Release `1.8.11_58` publicada em `pablomichelin/Layer7`

### Bloco D — Isenção DNS (`_59`, BG-073)

- [x] ADR-0020 implementado: opção (a) view Unbound `layer7-vip-exempt`
- [x] `unbound-checkconf` antes de gravar; fallback (b) rdr `from !<layer7_exc_allow_N>`
- [x] GUI Lista VIP: aviso conforme modo (`unbound_view` vs `rdr_fallback`)
- [x] `test_vip_dns_exempt.php` + `php -l` PASS (builder)
- [x] Release `1.8.11_59` publicada em `pablomichelin/Layer7`

### Bloco E — Validação lab

- [x] Cenário «director isento de tudo» em `validacao-lab.md` (sec. **20**;
  pacote lab **`>= 1.8.11_61`** recomendado; **`>= 1.8.11_60`** minimo fix P1
  `filter_configure`)
- [x] Gate **20.4** persistencia `filter_configure` documentado (pos **20.3** two-client)
- [x] Gate humano documentado; NO-GO produção inalterado (`_24` até G2–G7)
- [ ] Execução física no appliance (evidência sec. **20**, incl. **20.4** em modo
  fallback **(b)**) — pendente validação humana

---

## Condicoes para pedir validacao humana

- [ ] existe duvida sobre compatibilidade com pfSense CE
- [ ] existe duvida sobre empacotamento ou builder
- [ ] a mudanca afecta seguranca de forma relevante
- [ ] a decisao e arquitecturalmente grande
- [ ] ha necessidade de fallback sem resposta fechada
- [ ] ha necessidade de mover estrutura antes da F6

---

## Trilha IPv6 (pós-fecho plano mestre — Ondas V0–V6)

SSOT: [`plano-ipv6-completo.md`](plano-ipv6-completo.md), gates
[`plano-gates-ipv6.md`](../09-blocking/plano-gates-ipv6.md), mapa
[`f4-ipv6-mapa-rastreabilidade.md`](../01-architecture/f4-ipv6-mapa-rastreabilidade.md)
(§8 salvaguardas).
**Arranque único:** [`START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md).

**Regra:** não iniciar V2 (daemon) antes de V1 (PF scoped) sem ADR emenda.
**Desambiguação:** passos 12.x desta trilha ≠ `test-matrix` §12 (blacklists).

### V0 — Governança (passos 12.1–12.2, BG-078)

- [x] ADR-0024 aceite e indexado
- [x] Mapa M-01..M-25 (+ §8) publicado
- [x] Matriz limitações alinhada (GV0.4) — passo 12.1
- [x] Banner GUI Diagnostics + `pf-enforcement.md` (GV0.3) — passo 12.2
- [x] GV0 completo
- [x] CORTEX checklist trilha IPv6 + arranque único

### V1 — PF scoped inet6 (12.3, BG-079)

- [ ] REV-018 fechado no mapa
- [ ] GV1 PASS (`test_scoped_pf_inc.php` + `pfctl -nf` + salvaguardas NDP/localsubnets)

### V2–V3 — Daemon (12.4–12.8, BG-080–081)

- [ ] GV2 PASS builder
- [ ] GV3 captura v6 appliance (+ NDP intacto)
- [ ] GV4 enforce v6 scoped
- [ ] Regressão IPv4 confirmada

### V4 — GUI/config (12.9, BG-082)

- [ ] Validação IPv6 sem truncamento silencioso

### V5 — NAT/DNS v6 (12.10–12.11, BG-083)

- [ ] Decisão humana V5 registada
- [ ] GV5 PASS ou ADIADO com I7 documentado

### V6 — Fecho (12.12–12.13, BG-084)

- [ ] GV6 campanha dual-stack
- [ ] GV7 auditoria I1–I8 + release
- [ ] Trilha IPv6 **FECHADA** no CORTEX
