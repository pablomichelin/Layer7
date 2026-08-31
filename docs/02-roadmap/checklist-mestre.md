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
- [ ] sem mover/apagar/renomear fora de lote F6 autorizado (H1–H5 ja FECHADA; residual so BG-112 + gate G0–G7)
- [ ] sem tratar documento historico como SSOT
- [ ] docs afectadas identificadas antes da primeira alteracao
- [ ] backlog e roadmap alinhados ao bloco em execucao

---

## Gate F6 higiene residual (BG-112)

Plano SSOT:
[`../00-overview/f6-plano-higiene-estrutural-residual.md`](../00-overview/f6-plano-higiene-estrutural-residual.md)
(§3 exclusões; §4 G0–G7).

- [x] inventário rastreável criado (`f6-inventario-higiene-estrutural-2026-08-09.md`)
- [x] classificação MANTER/ARQUIVAR/REMOVER/CORRIGIR (`f6-classificacao-candidatos-higiene-2026-08-09.md`)
- [x] plano residual com gate + lista de exclusão
- [x] mapa de equivalência / README / mapa H0 apontam higiene residual
- [x] canonicidade F6: H1–H5 FECHADA nos SSOTs (deixar de dizer só “planeada”)
- [x] lote P1 CORRIGIR INV-081/082/083 PASS (`2026-08-10`)
- [ ] G0 — humano marcou IDs GO/DEFER para o lote (P2–P4 físicos)
- [ ] G1 — exclusões §3 reafirmadas no pedido de execução
- [ ] G2 — mapa de links do lote preenchido (se move/rename)
- [ ] G3 — rollback do lote definido
- [ ] G4 — scan de segredos no diff PASS
- [ ] G5 — nenhum path da exclusão §3 no lote
- [ ] G6 — evidências MANTER (P4, Gate C, GO teste, P3, ABORTs) fora de remoção
- [ ] G7 — commit por lote pequeno; push só com pedido explícito
- [ ] **bloqueio:** sem G0 PASS → não executar REMOVER/ARQUIVAR

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
- [x] **Auditoria E2E Etapa 1 + multitask (BG-060) — histórico:** inventário e
  consolidação em
  [`../09-blocking/auditoria-end-to-end-2026-07-29.md`](../09-blocking/auditoria-end-to-end-2026-07-29.md)
  e [`../09-blocking/diagnostico-multitask-2026-07-30.md`](../09-blocking/diagnostico-multitask-2026-07-30.md);
  ledger [`matriz-unificada-rev-fp-aud.md`](../09-blocking/matriz-unificada-rev-fp-aud.md).
  **Bloco B1 `_31`/`_65`:** **encerrado por supersessão** — G2–G7 PASS na
  linhagem `_69`/`1.9.x` ([`plano-gates-producao.md`](../09-blocking/plano-gates-producao.md));
  **não** reinstalar `1.8.11_*` sobre base `1.9.46`.
  Reconciliação canónica `2026-08-09`:
  [`../09-blocking/auditoria-reconciliacao-enforcement-1.8.11_24-_65-vs-1.9.46-2026-08-09.md`](../09-blocking/auditoria-reconciliacao-enforcement-1.8.11_24-_65-vs-1.9.46-2026-08-09.md)
  (**NO-GO** candidatos `_24`…`_65`; único pacote elegível lab = **`1.9.46`**).
- [x] **Flush lifecycle (BG-061):** R-21 PASS; código absorvido na linhagem
  `_32`→`1.9.x`. Validação uninstall histórica coberta pelos gates de fecho;
  **não** reabrir com candidato `_65`.

---

## Checklist de rollback

- [ ] rollback e explicitamente descrito
- [ ] rollback nao depende de memoria oral
- [ ] rollback preserva a ultima versao segura conhecida
- [ ] rollback de `_26` aponta para `_24` passivo e preserva evidência de logs
- [ ] impacto do rollback sobre docs e artefactos foi considerado
- [ ] para reorganizacao estrutural (H1–H5 ou BG-112), rollback de links e caminhos foi previsto

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
| F6 | H1–H5 sem perda de contexto; higiene residual BG-112 com gate G0–G7 + exclusões; sem falsificar evidências |
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
| F6 | `CORTEX`, backlog, classificacao, equivalencia, changelog estrutural; higiene residual: plano/inventário/classificação BG-112 |
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
- [x] BG-124: formato operador = texto simples (`IP, nome`) + editor em lote; JSON legado aceite (`1.9.61` publicado)
- [x] BG-125: picker de reservas DHCP estáticas (`dhcpd/<if>/staticmap`) para a Lista VIP; colunas e filtro por interface; sem auto-isenção
- [x] BG-126: copy de operador em MITM/Identity/check-in (sem ADRs/gates/paths na GUI; `1.9.62` publicado)
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

- [x] REV-018 fechado no mapa (código + docs)
- [x] `test_scoped_pf_inc.php` PASS (inet6 pdst/psrc/pallow/pexc/exc_allow)
- [x] GV1 completo (`pfctl -nf` + `layer7_localnets` IPv6 no appliance — `1.9.4`+)

### V2–V3 — Daemon (12.4–12.8, BG-080–081)

- [x] 12.4 captura IPv6 + flow key (`test_capture_flow_key` + build layer7d PASS)
- [x] 12.5 métricas / nDPI v6 fechado no builder (GV2)
- [x] 12.6 `policy.c` CIDR IPv6 (`test_policy_decide` PASS local + builder)
- [x] 12.7 `enforce.c`/`main.c` PF tabelas + kill states v6 (`test_enforce_scoped` + `run-local.sh` PASS local + builder)
- [x] 12.8 `allowlist` IPv6 host/CIDR (`test_allowlist.c` + `run-local.sh` PASS local + builder) — **Onda V3 completa**
- [x] GV3 captura v6 appliance (+ NDP intacto) — PASS (`1.9.3`+)
- [x] GV4 enforce v6 scoped — PASS (`20260805T125500Z-gv4-closed-1.9.6`)
- [x] Regressão IPv4 confirmada (QA D1–D6 `1.9.3`; GV3.3/GV6)

### V4 — GUI/config (12.9, BG-082)

- [x] 12.9 helpers + validação IPv6 (`layer7_ip_valid`, `layer7_cidr_any_valid`, `layer7_ip_or_cidr_valid`, `layer7_ip_in_cidr`; `cidr6_valid` prefixo mín. 10 S-03)
- [x] 12.9 `parse_ip_textarea` / `parse_cidr_textarea` dual-stack (limites 64/16)
- [x] 12.9 GUI allowlist, VIP add/import, políticas/grupos/excepções, blacklists, `layer7_test.php`
- [x] `tests/functional/test_ipv6_gui_inc.php` + `run-local.sh` PASS
- [x] **Onda V4 completa** — portal/block page IPv4 permanece (V5); banner Diagnostics IPv4-only mantido

### V5 — NAT/DNS v6 (12.10–12.11, BG-083)

- [x] Decisão humana V5 — ADR-0024 **GO Opção A** 12.10 (`2026-08-05`)
- [x] 12.10 DNS `rdr inet6` + AF-split + Unbound AAAA (`1.9.7`; `test_dns_force_inet6`)
- [x] 12.11 HTTP/HTTPS `rdr inet6` portal + VIP Unbound ACL v6 (`1.9.8`)
- [x] GV5 completo (após 12.11 + smoke lab)

### V6 — Fecho (12.12–12.13, BG-084)

- [x] GV6 campanha dual-stack — PASS (`20260805T130620Z-gv6-dualstack`)
- [x] GV7.1–GV7.3 auditoria I1–I8 + release `1.9.6` — PASS (`20260805T133000Z-gv7-fecho`)
- [x] Trilha IPv6 **FECHADA (núcleo dual-stack)** no CORTEX
- [x] GV7.4 GO promoção enforce (`1.9.8`) — **PASS** (`20260805T150500Z-gv7.4-promocao-1.9.8`)

## Trilha Identity + MITM Add-on (IM0–IM9 / passos 20.x)

**Arranque:** [`START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)  
**Posicionamento PME:** [`posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md) — ACEITE  
**Plano:** [`plano-identity-mitm-addon.md`](plano-identity-mitm-addon.md) rev. `2026-08-14bh` — **【FILA FECHADA】**  
**Gates:** [`plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md)  
**Prontidão piloto:** [`mapa-prontidao-mitm-piloto-2026-08-09.md`](../09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md)

### IM0 — Governança

- [x] 20.1 START-HERE + plano + mapa + gates + ADRs Proposto + índices
- [x] rev. `b` reparos arquitectónicos (spike MITM, mapa daemon, fontes, fail-mode) — **sem código**
- [x] rev. `c` contratos técnicos (ADR-0028 concorrência; contrato `features` P1–P6; check-in ∩ `.lic`; §3.1 first-match; spike S1–S8; canal DC A1–A7; NAT multi-user; passo 20.11a) — **sem código**
- [x] 20.2 Aceitar ADR-0025/0026/0027/0028 + GO transição **T1** (`2026-08-05`)
- [x] GI0 completo
- [x] rev. `d` posicionamento PME Identity-first + **20.7a DEFER MITM** (`2026-08-06`)

### IM1–IM9

- [x] IM1 Entitlements (GI1) — BG-086 — **20.3–20.6 PASS**
- [x] IM2 reopen + 20.8–20.11 + Gate C + teste controlado — BG-087 — **`1.9.46` PASS** (`215442Z`); permanente **NO-GO**
- [x] IM3 Map daemon + gate (GI4) — 20.11a–20.15 PASS (`2026-08-07`)
- [x] IM3–IM4 Map + LDAP (GI4 + GI5 parcial) — BG-088 — **20.18 PASS**
- [x] IM5 Fontes RADIUS/AD events (GI5–GI6) — BG-089 — **20.19–20.22 PASS**
- [x] IM6 Políticas user/grupo (GI7) — BG-090 — **20.23–20.26 PASS unit**; lab residual
- [x] IM7–IM8 Agente/TS ou exclusão ADR (GI8) — BG-091 — **PASS ADR-0029** (20.28–20.30)
- [x] IM9 Fecho/release Identity (GI9) — BG-092 — **20.33 PASS** (`20260808T174100Z-im9-20.33-homolog-1.9.29`)

### Piloto MITM (pós-IM2 técnico) — gates docs vs eng.

- [x] P0 mapa prontidão — **PASS** docs
- [x] P1 escopo D1–D9 — **PASS** docs ([`GO-escopo-piloto-mitm-generico.md`](../09-blocking/GO-escopo-piloto-mitm-generico.md))
- [x] P2 runbook piloto — **PASS** docs ([`runbook-piloto-mitm-generico.md`](../09-blocking/runbook-piloto-mitm-generico.md))
- [x] Norma ficha (legado) — **RETIRADA** ADR-0035 (`2026-08-14`)
- [x] P3 failsafe+visibilidade — **PASS** `1.9.47` (P3.1–P3.8; evid. `230400Z`)
- [x] P4 soak lab retry2 — **CLOSED PASS** `224009Z` (16/16 health; rollback_clean=1; MITM OFF; Phase C NA). Histórico: P4 `234042Z` FAIL/ABORT; retry `170000Z` FAIL
- [x] P5 / 20.34 — ficha **cancelada**; ADR-0035 aceite
- [x] 20.35: productizar MITM na GUI (até desligar + copy operador); publicado `1.9.63`
- [x] 20.36: soak `.254` alinhado a `1.9.63`; MITM OFF
- [x] 20.37: 【FILA FECHADA】 — [`fecho-trilha-identity-mitm-20.37.md`](../01-architecture/fecho-trilha-identity-mitm-20.37.md)

### Anti-pirataria / Anti-tamper (AP0–AP4, passos `30.x`)

- [x] Engenharia **FECHADA** em `30.19` (`20260812T025741Z`) — GA6.7–6.12 PASS
- [x] Fecho: [`../01-architecture/fecho-trilha-antipirataria-30.19.md`](../01-architecture/fecho-trilha-antipirataria-30.19.md)
- [x] Evidência fecho: `docs/tests/evidence/20260812T025741Z-30.19-fecho/`
- [x] BG-028 Fase 1 **Concluido** (`v1.9.58`); RR-3 tags; GA6.7 parecer EULA **externo**
- [x] **Não** reabrir engenharia AP0–AP4
- [x] Ciclo evidência operacional **BG-127** (GO `2026-08-14`) — **PASS** `20260814T224213Z` (GA2.6 enforce + GA4.8 campo); GA2.7/GA3.7/GA5.9 já PASS; GA6.7 fora
- [x] Runbook campo: [`../13-runbooks/evidencia-operacional-antipirataria-bg127.md`](../13-runbooks/evidencia-operacional-antipirataria-bg127.md)
- [x] `.254` vivo = `1.9.63` `mode=monitor` MITM OFF — [`20260814T034904Z-20.36-soak-align-163-254`](../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/)
- [x] Auditoria `2026-08-14` registada — [`../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md`](../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md)
- [x] **P0-1 ACTIVO** — proibido deploy integral do HEAD (serving `30.11` versionado no git; freeze **não** encerrado) — [`../13-runbooks/bloqueio-deploy-integral-head-30.11.md`](../13-runbooks/bloqueio-deploy-integral-head-30.11.md)
- [x] **BG-128 P1-1** — check-in arquivada `revoked`/`expired` → 409 envelope v2 (`2026-08-14`; sem deploy)
- [x] **BG-128 P0-2** — TOTP HMAC fail-closed (sem fallback estático; arranque produção recusa segredos vazios; `2026-08-14`; sem deploy)
- [x] **BG-128 P0-2 residual** — desafio TOTP single-use + bind ao servidor (`jti` + `admin_totp_challenges` + `FOR UPDATE`/`used_at` antes da sessão; `2026-08-14`; sem deploy)
- [x] **BG-128 P1-3** — `/login/totp` recusa `is_active=false`; reset só após TOTP OK; falha TOTP no lock existente sem enumerar (`2026-08-14`; P2-5 absorvido; sem deploy)
- [x] **BG-128 P1-2** — origin substitui `X-Forwarded-For` por `$remote_addr`; `getClientIp` usa `req.ip` (`trust proxy: 1`); Proto intacto (`2026-08-14`; sem deploy)
- [x] **BG-128 P1-4 + P2-1** — lock no `init`; primeiro admin já owner; promoção legado `LIMIT 1`; alerta se vários owners (`2026-08-14`; sem deploy)
- [x] **BG-128 allowlist `30.11`** — 7 paths versionados no git (`2026-08-14`; sem deploy; P0-1 **não** encerrado)
- [x] **BG-128 P1-5…P1-8 + P2-12** — FEITO no git após gates (`c2b9fdb` + governação neste commit) — enforce recusa check-in ON sem chave; upgrade/keep-config preserva json/`.lic`/CA/secrets/check-in; deinstall real limpa `/var/db` + anti-DoH (`2026-08-14`; sem deploy / `PORTVERSION`)
- [x] **BG-128 P2-7+P2-8+P2-10** — FEITO no git após gates (`2026-08-14`) — save atómico check-in + SKU + `.lic` 0600; sem deploy / `PORTVERSION`
- [x] **BG-128 P2-11** — FEITO no git após gates (`2026-08-14`) — GUI Identity/MITM + `layer7-mitm-entitle-ok` exigem HW + expiry/grace 14d; sem deploy / `PORTVERSION`
- [x] **BG-128 A1/A2/M2** — FEITO no git após gates (`28c97ad` + governação após gates; `2026-08-14`) — staging persistente + fail-closed + harness funcional; sem deploy / `PORTVERSION`; sem P2-13
- [x] **BG-128 M1** — FEITO no git após gates (`2026-08-14`) — GUI/helper fingerprint via `layer7d --fingerprint`; `LAYER7_TEST_HW_ID` só com `LAYER7_TEST_ROOT`; sem deploy / `PORTVERSION`; campo FreeBSD pendente
- [x] **BG-128 P2-17** — FEITO no git após gates (`2026-08-14`) — `LAYER7_TEST_NOW` só com `LAYER7_TEST_ROOT`; sem deploy / `PORTVERSION`
- [x] **BG-128 P2-3** — FEITO no git após gates (`2026-08-14`) — origin `X-Forwarded-Proto $scheme`; login HTTP+proto https no Host de origin → 400; sem deploy / `PORTVERSION`
- [x] **BG-128 P1-9** — AVALIADO no git (`2026-08-14`) — residual pós-P2-3 não aberto no contrato HEAD; cadeado compose/nginx; sem mudança de runtime; bind live não versionado; sem deploy / `PORTVERSION`
- [x] **BG-128 P2-2** — FEITO no git (`2026-08-14`) — CSRF admin fail-closed (`Origin` allowlist ou `Sec-Fetch-Site: same-origin`); `/api/users` e `/api/search` na superfície; Bearer autenticado e GET sem Origin compatíveis; sem deploy / `PORTVERSION`
- [x] **BG-128 P2-13** — AVALIADO no git (`2026-08-14`) — meia-noite local / DST / UTC sem correção única segura; sem mudança de runtime; cadeado `test_license_expiry_policy.php`; sem deploy / `PORTVERSION`
- [x] **BG-128 P2-4** — FEITO no git (`2026-08-14`) — incremento atómico de `failure_count` no lock de login (conta 5 / IP 10 / janela e lock 15 min); 10 `registerLoginFailure` paralelos → count=10 + lock; sem deploy / `PORTVERSION`
- [x] **BG-128 P2-6 Bloco A** — FEITO no git (`2026-08-14`) — `.dockerignore` (`.env` / `.env.*` / `node_modules` / `.git`) + `USER node` só no backend; frontend nginx listen 80 intacto; compose/healthcheck **fora**; sem Docker build/up; sem deploy / `PORTVERSION`
- [x] **BG-128 P2-6 Bloco B** — FEITO no git (`2026-08-14`) — `db.healthcheck` `pg_isready` via `$$POSTGRES_USER`/`$$POSTGRES_DB` + `api.depends_on.db.condition: service_healthy`; hash compose P0-1 actualizado; sem Docker build/up; sem deploy / `PORTVERSION`
- [x] **BG-128 P3-1 / BG-131** — FEITO no git (`2026-08-14`) — sessão única atómica (`BEGIN` + `FOR UPDATE` no admin + revoke + insert); 2 `createSession` paralelos → 1 activa; sem deploy / `PORTVERSION`
- [x] **BG-128 P3-2 / BG-132** — FEITO no git (`2026-08-14`) — `GET /api/auth/session` inclui `a.totp_enabled`; admin com TOTP → `totp_enabled: true`; sem deploy / `PORTVERSION`
- [x] **BG-128 P3-3A / BG-134** — FEITO no git (`2026-08-14`) — `POST /api/auth/login` disabled e inexistente partilham 401 genérico + bcrypt (hash real ou dummy) + `registerLoginFailure`; sem deploy / `PORTVERSION`
- [x] **BG-128 P3-3B / BG-136** — FEITO no git (`2026-08-14`) — `POST`/`PUT /api/users` exige password >=12; POST 10 → 400; POST 12 → 201; PUT 10 → 400; PUT sem password inalterado; `/login` não rejeita 10; sem deploy / `PORTVERSION`
- [x] **BG-128 P3-3C / BG-138** — FEITO no git (`2026-08-14`) — `verifyTotp` compara HOTP/TOTP com Buffer UTF-8 + guarda de comprimento + `timingSafeEqual`; válido mesmo now → true; 6 dígitos inválidos / malformed → false sem throw; sem deploy / `PORTVERSION`
- [x] **BG-128 P3-4 / BG-140** — FEITO no git (`2026-08-14`) — `GET /api/auth/2fa/status` try/catch; pool rejeitado → 500 JSON `Erro interno.`; sem unhandledRejection; segundo GET saudável 200 `{totp_enabled:true}`; 401/403 intactos; sem deploy / `PORTVERSION`
- [x] **BG-128 P3-5 / BG-142** — FEITO no git (`2026-08-14`) — promoção atómica do `.lic` em Activate (tmp 0600 + verify + rename); falha preserva o anterior; `activate.body` não renameado; contrato de check intacto; sem deploy / `PORTVERSION`
- [x] **BG-128 P3-6 / BG-144** — FEITO no git (`2026-08-14`) — `verify-prod-pubkey.sh` exige PEM == SoT == C; selftest local fail-before/pass-after; `license.c`/PEM intactos; sem deploy / `PORTVERSION`
- [x] **BG-128 P3-8 / BG-148** — AVALIADO no git (`2026-08-14`) — recheck read-only do cut `30.11` (`asset_count=0`, 404×4, primary 401; evidência `20260814T200900Z-p38-cut-recheck`); sem mudança de runtime; P3-9 separado (URLs não removidos); sem deploy / `PORTVERSION`
- [x] **BG-128 P3-9 / BG-150** — AVALIADO no git (`2026-08-14`; opção A — **FEITO documental**) — docs «404 esperado» pós-cut `30.11`; URLs **não** removidos (legado / fallback); nota `nota-404-esperado-cut-30.11.md`; evidência `20260814T204500Z-p39-404-esperado`; sem mudança de runtime; sem deploy / `PORTVERSION`
- [x] **BG-128 P2-16 / BG-151** — AVALIADO no git (`2026-08-14`; opção A — **FEITO documental**) — rollback preferido = overlay `bbc74a5…`; tag `pre-30.13` **não** é padrão/`latest` (só incidente específico de `30.13`); história preservada; sem tag/retag/deploy / `PORTVERSION`
- [x] **BG-128 P2-14 / BG-152** — AVALIADO no git (`2026-08-14`; opção A — **FEITO documental**) — bypass ABI `-f` = política BG-106 (GUI + `install.sh`); builder FreeBSD 16 **não** existe / **não** está provado; **não** é suporte nativo ABI 16; sem código / `PORTVERSION` / hosts
- [x] **BG-128 P3-7 / BG-153** — AVALIADO no git (`2026-08-14`; opção A — **FEITO documental**) — colisão TZ/expiry já provada em P2-13/REV-030; `timegm`/`gmmktime` **não** são correção (alteram o contrato e pioram Brasil/UTC); sem mudança de runtime; sem deploy / `PORTVERSION`
- [x] **BG-128 P2-9 / BG-154** — AVALIADO neste bloco (`2026-08-14`; opção A — cadeado + docs) — upgrade **não** injecta `check_in_enabled=true` (contrato `30.14` / ADR-0032); `load_or_default` / `pkg-install.in` não chamam a migration; cadeado `test_check_in_default_30.14.php`; sem mudança de runtime; sem deploy / `PORTVERSION`
- [x] **BG-165** — PUBLICADO `v1.9.73` — curl absoluto + flock; badge `.lic`; disarm; ping sem fallback de ID
- [x] **BG-166** — FEITO no git privado (live `.244` pendente GO) — UPSERT COALESCE; `normalizeFeatures`; `getClientIp` em activate/check-in
- [x] **BG-167** — PUBLICADO `v1.9.74` — GUI modo efectivo (`enforce_mode`); sem licença o badge é monitorizar
- [x] **BG-168** — PUBLICADO `v1.9.75` — PF só arma com pedido aplicar e `enforce_mode=1`
- [x] **BG-169** — PUBLICADO `v1.9.76` — perfil rápido **Pornografia** (id `adulto`, 64 hosts + `AdultContent`)
- [x] **BG-170** — PUBLICADO `v1.9.77` — check-in obrigatório; GUI sem opt-out; daemon ignora JSON false
- [ ] **BG-128** remediações restantes — P0-1 ACTIVO (falta GO rebuild `api` + smoke); próximo = P0-1 rebuild api + smoke (sem P2-9; sem P2-7/8/10/11; sem M1/P2-17/P2-3; sem P1-9 runtime; sem P2-2; sem P2-13; sem P2-4; sem P2-6 Bloco A; sem P2-6 Bloco B; sem P3-1; sem P3-2; sem P3-3A; sem P3-3B; sem P3-3C; sem P3-4; sem P3-5; sem P3-6; sem P3-8; sem P3-9; sem P2-16; sem P2-14; sem P3-7; sem P0-2 residual; sem `.244` neste bloco)
