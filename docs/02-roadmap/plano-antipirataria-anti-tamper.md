# Plano — Anti-pirataria e Anti-tamper (trilha AP0–AP4)

**Estado do plano:** **`30.10` FECHADO**; **`30.11` FECHADO** (cut
`20260812T011217Z` — `delete-asset` ×4; release/tag preservados; GA4.10/15
**PASS**; residual CDN @cut + recheck 404×4); **GA4.12 N/A**; **30.9 live PASS**;
evidência [`../tests/evidence/20260812T011217Z-30.11-cut-mirror/`](../tests/evidence/20260812T011217Z-30.11-cut-mirror/);
lab/`latest` = produção **`1.9.54`**; GA4.4 **PASS**; ADRs 0030–0033 **`Aceito`**
(rev. `2026-08-10c`); **próximo AP3 `30.12`**
**Tipo:** nova trilha pós-fecho; **não** reabre P0–J, IPv6 V0–V6 nem Identity de rede
**Modelo de ameaças (base analítica):** [`../01-architecture/modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md) — **ACEITE como diagnóstico**
**SSOT de execução:** este ficheiro
**Arranque de chat (único desta trilha):** [`../00-overview/START-HERE-antipirataria.md`](../00-overview/START-HERE-antipirataria.md)
**SSOT de estado vivo do produto:** [`../../CORTEX.md`](../../CORTEX.md)
**Gates:** [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md)
**Protocolo agente:** §8 deste ficheiro (**Composer 2.5** — um passo por chat)
**ADRs propostos:** 0030 (anti-tamper) · 0031 (entitlement de conteúdo) · 0032 (check-in obrigatório e assinado — **emenda ADR-0021**) · 0033 (anti-rollback temporal)
**Baseline produção enforce:** `1.9.8` — rollback enforce `1.9.0`
**Canal lab/`latest` no arranque da trilha:** `1.9.48` — rollback lab `1.9.47`
**Artefacto de produto:** sempre **`.pkg`** FreeBSD/pfSense (nunca APK Android). Releases novas desta trilha publicam-se em `pablomichelin/Layer7`.
**Backlog:** BG-114…BG-123

**Rev. `a` (`2026-08-10`)** = criação da trilha; modelo de ameaças aceite; ondas AP0–AP4; **zero código**.
**Rev. `b` (`2026-08-10`)** = riscos residuais RR-1…RR-5 (§0.1); emendas 30.3/30.6/30.7/30.8/30.11/30.19.
**Rev. `c` (`2026-08-10`)** = plano **executável por Composer 2.5**: 30.1 partido em `30.1a`/`30.1b`; §5 com 8 decisões; §8 contrato por passo; gates GA3.9/GA4.14/GA6.12 alinhados. **Zero código de produto**.

---

## 0. Estado e progresso

| Campo | Valor |
|-------|-------|
| Onda actual | **AP2 em curso** (AP0/AP1 higiene FECHADAS; GA1 PASS) |
| Passo actual | **`30.11` FECHADO** (cut espelho); AP2 cut feito |
| Próximo | AP3 **`30.12`** |
| Depois | `30.13`… |
| Bloqueio duro | A-09 / pubkey: **resolvido**; primary auth: **PASS**; cut: **PASS** |
| Código alterado até agora | `license-server` **30.9 live** + primary content; cliente/pkg **`1.9.54`**; espelho GitHub sem assets correntes |
| Gate activo | **GA4 cut PASS** (GA4.10/15); GA4.12 **N/A**; próximo gate GA5 (AP3) |
| Decisões 1/3 (RR-1) | **Sim** / **Sim** — cut `30.11` **feito**; falta GO/execução `30.14` |
| Agente recomendado | **Composer 2.5** — um passo `30.x` por chat (§8) |
| Rev. do plano | **`2026-08-10c`** |

```text
TRILHA ANTI-PIRATARIA — progresso
- Modelo de ameaças: ACEITE como diagnóstico (2026-08-10)
- Rev. plano: 2026-08-10c (Composer-ready; RR-1..RR-5)
- Passo: 30.11 FECHADO (cut 20260812T011217Z)
- 30.9 live PASS; e2e 1.9.54 PASS; primary auth PASS
- Cut: delete-asset x4; asset_count=0; residual CDN @cut; recheck 404x4
- GA4.12 N/A; GA4.10/15 PASS
- Evidência: 20260812T011217Z-30.11-cut-mirror
- Produção .254 / latest: 1.9.54
- BG-117: Concluido
- Próximo: AP3 30.12
```

Actualizar este bloco **e** o CORTEX **e** o `START-HERE` no mesmo commit documental de cada fecho de passo.

---

## 0.0 Decisões arquitectónicas obrigatórias

Estas regras são canónicas para toda a trilha e prevalecem sobre qualquer
interpretação local durante a execução.

| # | Tema | Decisão canónica |
|---|------|------------------|
| R-A | **Limite teórico assumido** | Root no appliance **pode** contornar qualquer verificação local. O objectivo **não** é impossibilitar; é (1) encarecer acima do preço da licença, (2) tornar a cópia **inútil no tempo**, (3) tornar o abuso **detectável e atribuível**. Qualquer proposta que ignore este limite é rejeitada. |
| R-B | **Ordem de valor** | A defesa estruturalmente sólida é **AP2 (entitlement de conteúdo)**. AP1 é higiene, AP3 é poder comercial, AP4 é rastreabilidade. Nenhuma onda posterior justifica atrasar AP2 indefinidamente. |
| R-C | **Nunca fail-closed por rede** | Indisponibilidade do license server, DNS ou internet **nunca** pode reduzir enforce, parar o daemon ou degradar o firewall do cliente. Violação disto é bug crítico, não feature. |
| R-D | **Conteúdo ≠ enforce** | Falta de token de subscrição impede **actualizar** conteúdo. Nunca apaga conteúdo existente nem desliga enforce. Degradação é **suave e visível**, não abrupta. |
| R-E | **Sem kill-switch remoto** | Nenhum comando de servidor pode desligar o enforce de um appliance. A alavanca comercial é a degradação de conteúdo (AP2) e a revogação de licença pela via já existente. |
| R-F | **Daemon é a autoridade** | A GUI pode informar e fazer upsell; o gate real vive no `layer7d`. Qualquer entitlement mostrado na GUI tem de ser derivado de estado **assinado**, nunca de ficheiro editável sem verificação. |
| R-G | **Sem ofuscação pesada** | Packers, VM de código e anti-debug estão **fora de escopo por decisão**, não por esquecimento — custo/risco num daemon root em firewall excede o retorno. Reabrir só com ADR próprio. |
| R-H | **Builder é a verdade** | `src/layer7d/license.c` e `Makefile` divergem do repo no builder (`AGENTS.md`). Auditar git **não** prova o que está em campo. Todo gate desta trilha valida o **`.pkg` publicado**. |
| R-I | **Isolamento de trilhas** | **Proibido** misturar um passo 30.x com promoção de enforce, trilha MITM (20.x) ou IPv6. Caminhos críticos independentes, blocos separados, releases separadas. |
| R-J | **Erro do cliente honesto** | Relógio errado, rede cortada ou hardware substituído por avaria são cenários **legítimos**. Cada mecanismo desta trilha tem de ter recuperação documentada e executável pelo operador sem contactar suporte. |
| R-K | **Sem segredos no git** | Chaves privadas, tokens e material de assinatura nunca entram no repositório. Vale para AP2/AP3, incluindo fixtures de teste. |
| R-L | **Prova em CE pendente** | ADR-0022 continua a valer: gates em lab Plus não provam CE. Declarar explicitamente o que ficou sem prova CE. |

---

## 0.1 Riscos residuais assumidos (RR-1…RR-5)

Registados na revisão crítica do plano (rev. `b`), **antes** do GO do 30.1, para
que a decisão humana seja tomada com os limites à vista. Cada RR indica onde é
tratado e o que **continua** possível mesmo com a trilha completa. Nenhum destes
riscos invalida o plano; invalidam apenas *overclaim*.

| # | Risco residual | Onde se trata | O que continua possível |
|---|----------------|---------------|--------------------------|
| RR-1 | **A protecção real depende dos GOs comerciais de `30.11` e `30.14`.** Sem retirar o espelho público, o token de AP2 é decorativo (o pirata actualiza pelo caminho anónimo); sem check-in por defeito, revogar licença continua inócuo. | Decisões 1–3 do §5; GA4.10; GA5.7 | Se os GOs não forem dados, a trilha entrega **higiene (AP1)**, não protecção contra T1/T2 |
| RR-2 | **Redistribuição de conteúdo por appliance licenciado** (variante de T1): um integrador com 1 licença legítima pode descarregar as blacklists nesse appliance e espelhá-las internamente para N cópias piratas. O token trava o download anónimo, não a re-serva de ficheiros. | `30.17` (marcação por cliente → atribuição a posteriori); ADR-0031 declara o limite; GA4.14 | Redistribuição interna continua tecnicamente possível; a resposta é **atribuição + via contratual (AP4)**, não bloqueio |
| RR-3 | **Os `.pkg` já publicados continuam vulneráveis para sempre.** Remover `is_dev_key` (30.4) só afecta builds futuros; a `1.9.48` e anteriores, com o caminho de bypass intacto, permanecem publicamente descarregáveis. Um pirata pode fixar-se na `1.9.48` indefinidamente. | Inventário em `30.3`; decisão n.º 8 do §5 (despublicar/limitar releases antigas); a desvalorização vem de AP2 (conteúdo obsoleto) | Binário antigo patchado continua a correr; perde valor com o tempo, não deixa de existir |
| RR-4 | **Anti-rollback (30.6) tem duas evasões conhecidas:** (a) root pode apagar o ficheiro de estado em `/var/db/`; (b) a marca «maior timestamp observado» detecta *retrocesso*, não um relógio **congelado/atrasado desde a instalação**. | ADR-0033 declara o limite; GA3.9; fecho real do vector é AP3 (o servidor conhece a hora real) | `30.6` encarece o truque casual do `date`; o T2 técnico só é contido com check-in obrigatório |
| RR-5 | **AP3 continua contornável por patch no cliente.** A resposta assinada impede servidor falso; não impede root de patchar o `layer7d` para ignorar o check-in. | ADR-0032 declara que o valor de AP3 é contra o T2 *não-técnico*; o técnico é apanhado por AP2 + `30.15` (multi-appliance) + AP4 | Patch local do binário — coberto por R-A |

**Regra de uso:** qualquer ADR, doc de desenho ou material da trilha que descreva
AP2/AP3 sem mencionar o RR correspondente está **incompleto** e não passa revisão.

---

## 1. Não-regressão — invariantes verificáveis

Todo passo desta trilha tem de preservar estes invariantes. São critério de
`FAIL` no gate correspondente, não recomendações.

| # | Invariante | Como se verifica |
|---|-----------|------------------|
| N1 | Licença válida ⇒ comportamento idêntico ao `1.9.48` | smoke de enforce no appliance; políticas activas mantêm-se |
| N2 | Licença inválida/expirada ⇒ **monitor**, daemon vivo, sem crash | parar licença e observar `s_ge=0`, daemon a correr, zero regras PF Layer7 de block |
| N3 | Rede/license server indisponível ⇒ **zero** impacto em enforce | cortar rota ao servidor e confirmar enforce inalterado |
| N4 | Conteúdo desactualizado ⇒ enforce continua com conteúdo antigo | expirar token e confirmar blacklists antigas ainda carregadas e activas |
| N5 | Features OFF ⇒ comportamento de baseline | Identity/MITM OFF sem threads novas nem superfície nova |
| N6 | Relógio corrigido para trás por erro humano ⇒ recuperável sem suporte | procedimento documentado no runbook, testado |
| N7 | Rollback ao `.pkg` anterior restaura estado operacional | `pkg add -f` da versão anterior + smoke PASS |
| N8 | Nenhum segredo novo no repositório | `git diff` revisto + gate de segredos |

---

## 2. Ondas e passos

Um passo `30.x` por entrega. Cada passo declara objectivo, ficheiros,
teste mínimo, risco, rollback e gate — nos termos do `AGENTS.md`.

### AP0 — Governação e baseline *(sem código de produto)*

#### 30.0 — Documentação da trilha — **FECHADO documental** (`2026-08-10`)

**Objectivo:** existir trilha navegável e auditável antes de qualquer código.
**Entrega:** `START-HERE-antipirataria.md`, este plano, `plano-gates-antipirataria.md`,
registo no CORTEX / `docs/README.md` / backlog (BG-114…BG-123) / índice de ADRs.
**Teste mínimo:** links resolvem; estado coincide entre START-HERE, plano §0 e CORTEX.
**Risco:** nulo (documental). **Rollback:** reverter commit documental.
**Gate:** GA0.

#### 30.1 — GO humano e aceitação dos ADRs *(partido em 30.1a + 30.1b)*

**Objectivo:** transformar o diagnóstico em decisão formal **sem** o agente inventar
GO. Partição obrigatória para Composer:

##### 30.1a — Rascunhos (agente / Composer 2.5) — **FECHADO** (`2026-08-10`)

**Objectivo:** deixar o humano a decidir com texto completo à frente, não com
títulos vazios.
**Entrega (só documental):**
1. Criados `ADR-0030` … `ADR-0033` em estado **`Proposto`** (nunca `Aceito`
   neste subpasso), com Contexto / Decisão / Consequências / Fora de escopo /
   RR aplicáveis (§0.1).
2. Criada [`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md)
   — ficha das **8** decisões do §5 com recomendação + `Decisão humana` em branco.
3. Índice ADR, §0 / START-HERE / CORTEX actualizados; **GA0.8 PASS**.
**Proibido em 30.1a (cumprido):** marcar ADR `Aceito`; código; `license.c`;
resolver as 8 decisões; misturar com 30.2.
**Gate:** GA0 parcial (0.8 PASS).

##### 30.1b — GO humano (operador) — **FECHADO** (`2026-08-10`)

**Objectivo:** decisão formal.
**Entrega:** 8/8 decisões = recomendações do plano (operador: «concordo com tudo»);
ADRs 0030–0033 **`Aceito`**; BG-101 **reaberto** lacuna comercial; GA0.4/0.5/0.7
**PASS**; GA0 completo.
**Quem executou:** humano (ditado) + agente (aplicação documental).
**Bloqueio removido:** AP1+ pode iniciar após **`30.2`**.
**Gate:** GA0 completo.

#### 30.2 — Reconciliação builder ↔ repositório *(bloqueio duro)* — **FECHADO** (`2026-08-10`)

**Objectivo:** eliminar o achado A-09 antes de tocar em `license.c`.
**Problema:** `src/layer7d/license.c` e `src/layer7d/Makefile` têm alterações locais
não commitadas no builder, incluindo a chave pública de produção; o fluxo de build
faz `git stash` → `pull` → `checkout stash --` desses ficheiros. Alterar `license.c`
no repo **sem** resolver isto arrisca perder ou sobrepor a alteração no próximo build.
**Entrega:** decisão registada sobre onde vive a pubkey de produção (repo privado
versionada, ou ficheiro/flag de build fora do git), fluxo de build actualizado no
`AGENTS.md` e em `docs/08-lab/builder-freebsd.md`, e diff explícito builder↔repo
documentado como evidência.
**Teste mínimo:** build limpo no builder após a mudança de fluxo produz `.pkg`
funcional; `strings` do binário confirma a pubkey esperada.
**Risco:** **Alto** se mal feito — pode invalidar licenças em campo (pubkey trocada).
**Rollback:** restaurar fluxo anterior e o `license.c` do builder a partir de cópia
de segurança tirada **antes** do passo.
**Gate:** GA1 (parcial — critérios 30.2 PASS; GA1.6/1.7 em `30.3`).
**Fecho:** WT==HEAD (diff vazio); SoT `/root/layer7-build-secrets/`; fluxo sem stash;
`verify-prod-pubkey.sh` PASS; `.pkg` com sbin **sha256 idêntico** a `1.9.48` publicado;
evidência `docs/tests/evidence/20260810T231840Z-30.2-builder-pubkey/`. Espelho
transitório da pubkey permanece em `license.c` até AP1 (contrato Composer §8.3:
este passo não edita `license.c`).

#### 30.3 — Baseline de auditoria do artefacto — **FECHADO** (`2026-08-10`)

**Objectivo:** saber e provar o que está em campo hoje.
**Entrega:** inventário do `.pkg` `1.9.48` publicado (sha256, símbolos presentes,
pubkey embutida, presença/ausência de `is_dev_key`); teste automatizado que **falha**
se um artefacto de release contiver o caminho de bypass dev; **inventário das
releases anteriores ainda publicadas** (insumo do RR-3 e da decisão n.º 8 do §5);
registo em `docs/tests/evidence/20260810T234552Z-ap0-baseline/`.
**Resultado:** binário `1.9.48` **stripped**; marcadores binários de bypass
**ABSENT** (provável DCE); pubkey produção **FOUND**; residual A-01 no **fonte**
até `30.4`; script `scripts/package/audit-release-dev-bypass.sh` (GA1.7).
**Teste mínimo:** o teste corre sobre `1.9.48` e reporta o estado real, sem falso PASS.
**Risco:** Baixo (read-only). **Rollback:** n/a.
**Gate:** GA1 — **PASS**.

---

### AP1 — Anti-tamper de baixo custo *(primeiro código)*

Corrige A-01, A-02 (parcial), A-03 e A-07. Todos os passos exigem 30.2 fechado.

#### 30.4 — Remover o modo dev do binário de produção — **BG-114** — **FECHADO** (`2026-08-10`)

**Objectivo:** eliminar o caminho de bypass total documentado em A-01.
**Implementação:** `is_dev_key()` e o bloco `if (is_dev_key())` de
`src/layer7d/license.c` passam a existir apenas sob `#ifdef L7_DEV_BUILD`; a flag
**não** é definida no `Makefile` do port. Build de produção deixa de conter a lógica.
Se a pubkey vier inválida num build de produção, o resultado é licença **inválida**
(monitor), nunca válida.
**Ficheiros:** `src/layer7d/license.c`, `src/layer7d/Makefile`,
`package/pfSense-pkg-layer7/Makefile`; teste
`scripts/package/test-prod-no-dev-bypass.sh`.
**Resultado:** GA2.1–2.3 **PASS** no builder; release **`1.9.49`**
(`SHA256=f380ad493c5229fc08704673abf758edaa5e15ea05061820d04bb9abdca4d3cb`);
evidência `docs/tests/evidence/20260810T235325Z-30.4-no-dev-bypass/`.
**Teste mínimo:** teste C all-zeros ⇒ inválida (builder PASS); smoke builder PASS;
appliance `.254` não corrido neste passo.
**Risco:** Médio — caminho crítico de licença. **Rollback:** `.pkg` anterior.
**Gate:** GA2 (parcial — GA2.1–2.3).

#### 30.5 — Strip e endurecimento de build — **BG-115** — **FECHADO** (`2026-08-10`)

**Objectivo:** remover o mapa de símbolos que aponta para as funções de licença.
**Implementação:** `${STRIP_CMD}` explícito após `INSTALL_PROGRAM` para `layer7d`
e `layer7-tlsproxy`; `-fvisibility=hidden` nos binários standalone (avaliado
seguro — sem ABI/.so exportada). **Não** introduzir ofuscação (R-G).
**Ficheiros:** `package/pfSense-pkg-layer7/Makefile`; teste
`scripts/package/test-prod-strip.sh`.
**Resultado:** GA2.4 / GA2.5 / GA2.11 **PASS** no builder; release **`1.9.50`**
(`SHA256=3598828d057948732efb10ac0e958b3078f93a7ce86ad35f73d5f5ce086ec85e`);
evidência `docs/tests/evidence/20260810T200329Z-30.5-strip/`.
**Teste mínimo:** `nm`/`strings` do artefacto sem `is_dev_key`/`layer7_license_check`;
daemon arranca, `layer7d -t` PASS, `--fingerprint` PASS.
**Risco:** Baixo — atenção a diagnóstico futuro (core dumps menos legíveis; registar
como limite aceite). **Rollback:** `.pkg` anterior (`1.9.49`).
**Gate:** GA2 (parcial — GA2.4/2.5/2.11).

#### 30.6 — Anti-rollback de relógio — **BG-116** — **FECHADO** (`2026-08-10`)

**Objectivo:** corrigir A-03 sem penalizar erro honesto (R-J).
**Implementação:** marca em `/var/db/layer7/clock-mark.json`; limiar
`L7_CLOCK_SUSPECT_SEC=86400`; retrocesso maior ⇒ `clock_suspect` + `valid=0` +
`L7_AUDIT_NOTE`; GUI badge; runbook N6.
**Ficheiros:** `src/layer7d/license.c`, `license.h`, `main.c`, `layer7.inc`,
`layer7_settings.php`, lang, `tests/functional/test_license_clock.c`,
`docs/13-runbooks/anti-rollback-relogio.md`.
**Resultado:** GA3 **PASS** (GA3.7 DEFERRED lab); release **`1.9.51`**
(`SHA256=aec3642824df0fd8b3a49d9cc41b4b8a30e8c88dd5be6d6da7e142965b722204`);
evidência `docs/tests/evidence/20260810T201043Z-30.6-anti-rollback/`.
**Limites declarados (RR-4):** root pode apagar o ficheiro de estado; relógio
**congelado desde a instalação** não é detectado. Fecho real = AP3.
**Teste mínimo:** unitário `test_license_clock` PASS; `layer7d -t` PASS.
**Risco:** Médio — falso positivo mitiga-se com limiar 1 dia.
**Rollback:** `.pkg` `1.9.50`; ficheiro de estado ignorado por versões antigas.
**Gate:** GA3.

#### 30.7 — Entitlements assinados para a GUI — **BG-120** — **FECHADO** (`2026-08-10`)

**Objectivo:** corrigir A-07 (stats forjados desbloqueiam UX de add-ons).
**Implementação (entregue):** `layer7_entitlements()` deriva só de `.lic` Ed25519
verificado (`openssl pkeyutl`); check-in só ∩ (retira); PEM
`license-signing-public-key.pem`; rc.d + `layer7-mitm-entitle-ok` (GA2.9);
sync_helper revalida em produção. Sem assinatura local no daemon.
**Release:** `1.9.52`. Evidência: `docs/tests/evidence/20260810T214800Z-30.7-entitlements/`.
**Teste:** `test_entitlements_gui.php` + `test_mitm_config.php` + `test_mitm_regress.php` **PASS**.
**Risco:** Médio — superfície partilhada Identity+MITM; mitigado (R-I / TEST_ROOT).
**Rollback:** `.pkg` `1.9.51`.
**Gate:** GA2.8–2.10 **PASS**.

---

### AP2 — Entitlement na entrega de conteúdo *(maior valor estratégico)*

Corrige A-06. É a onda que faz a cópia sem licença **degradar sozinha**.

#### 30.8 — Desenho e contrato do token de subscrição — **FECHADO** (`2026-08-10`)

**Objectivo:** fechar o desenho **antes** de qualquer código (padrão da casa).
**Entrega:** [`../01-architecture/contrato-token-subscricao-conteudo-30.8.md`](../01-architecture/contrato-token-subscricao-conteudo-30.8.md)
— formato Ed25519; TTL **30d**; skew ±1d; histórico sem token (R-D); obtenção via
check-in; path `/var/db/layer7/content-subscription.json`; Bearer no CDN; RR-2 §7;
casos C1–C12. **Zero código.**
**Decisões fechadas:** D1–D10 no contrato.
**Teste mínimo:** revisão humana do desenho (pedir «desenho 30.8 OK»).
**Risco:** nulo (documental). **Rollback:** reverter commit documental.
**Gate:** GA4.1 / GA4.14 **PASS**.

#### 30.9 — Emissão do token no license server — **FECHADO** (`2026-08-10`) + **live PASS** (`2026-08-11`)

**Objectivo:** servidor emite token de subscrição para licença activa.
**Implementação:** `content-subscription.js` + `buildActiveCheckInResponse(..., {hardwareId})`;
campo `content_subscription` no JSON activo; denied sem campo; mesma
`ED25519_PRIVATE_KEY` do `.lic`; TTL 30d; sem migração schema; sem segredos no git.
**Teste:** `npm test` no backend — **112 PASS** (incl. GA4.2/GA4.3).
**Deploy live:** `192.168.100.244` RUNID `20260811T110043Z` — smoke active/denied PASS.
**Risco:** Médio (serviço). **Rollback:** imagem `api` pré-tag + tarball.
**Gate:** GA4.2 / GA4.3 / GA4.13 **PASS**.

#### 30.10 — Cliente: actualização de conteúdo com token — **FECHADO** (`2026-08-10`/`11`)

**Objectivo:** `update-blacklists.sh` apresenta o token; sem token válido não
actualiza, mas **mantém** o conteúdo e o enforce (R-C, R-D, N4).
**Implementação:** persistência `/var/db/layer7/content-subscription.json` no
check-in activo (`license.c`); gate + Bearer em `update-blacklists.sh`; GUI
Blacklists/Settings; runbook `content-subscription-update.md`; `.pkg` **`1.9.53`**
(token) + **`1.9.54`** (fix `fetch_authed` redirects HTTPS seguros).
**Fix `1.9.54`:** `fetch_authed` segue 301/302/303/307/308 só em HTTPS; credenciais
omitidas em cross-host (sem `--location-trusted`); Location não-HTTPS recusada;
máx. 5 hops. Testes regressivos 302→200 + anti-leak + hold-active PASS.
**Teste local/builder:** `test_content_subscription_update.sh` +
`test_content_subscription_client.php` **PASS**.
**E2e `.254` (`1.9.54`, `20260811T114320Z`):** install OK; check-in+token **PASS**;
update autenticado **PASS** (mirror HTTPS redirect; primary CDN DNS ainda fail
pré-existente); sem token hold-active **PASS**; GUI helper **PASS**; produção
mantida em **`1.9.54`**. Evidência
`docs/tests/evidence/20260811T114320Z-30.10-e2e-154-254/`.
**Histórico:** STOP em `1.9.53` por HTTP 302 —
`20260811T110638Z-30.10-revalidate-254/`.
**Risco residual (histórico pré-cut):** RR-1 enquanto o espelho anónimo servia
conteúdo corrente — **fechado em `30.11`**.
**Rollback lab:** **`1.9.53`**.
**Gate:** **GA4.4 PASS**; GA4.5–4.7/4.9 **PASS**. Primary auth preflight **PASS**.

#### 30.11 — Retirada do espelho público de conteúdo corrente

**Objectivo:** fechar a porta anónima que hoje mantém cópias piratas actualizadas.
**Nota de dependência (RR-1):** este passo é o que dá valor real a AP2 — sem ele,
o token dos passos 30.8–30.10 é decorativo, porque o caminho anónimo continua a
servir conteúdo corrente. O GO deve ser pedido com esta consequência à vista.
**FECHADO (`20260812T011217Z`):** GO gestor + `gh release delete-asset` ×4 em
`blacklists-ut1-current` (ids `405033619`/`405033621`/`405033618`/`405033620`);
release/tag preservados (`313502667`); `asset_count=0`.
Evidência: [`../tests/evidence/20260812T011217Z-30.11-cut-mirror/`](../tests/evidence/20260812T011217Z-30.11-cut-mirror/).
Prep: [`../09-blocking/prep-cut-30.11-espelho.md`](../09-blocking/prep-cut-30.11-espelho.md).
**GA4.12:** **N/A**. **GA4.10/15:** **PASS** (residual CDN @cut; recheck
`012017Z` anónimo **404×4**).
**Rollback:** [`../13-runbooks/content-mirror-rollback-ga4.11.md`](../13-runbooks/content-mirror-rollback-ga4.11.md).
**Gate:** GA4 cut **PASS**.

---

### AP3 — Check-in verificável e obrigatório

Corrige A-04, A-05 e A-08. É a onda de maior impacto em suporte — não iniciar
sem AP2 estável.

#### 30.12 — Protocolo de check-in assinado com anti-replay

**Objectivo:** corrigir A-05 (resposta hoje é JSON simples, sem assinatura nem nonce).
**Entrega:** contrato do protocolo (nonce emitido pelo cliente, `hardware_id` e
estado dentro do payload assinado, resposta Ed25519), com compatibilidade explícita
para clientes antigos durante a transição.
**Ficheiros:** documento de arquitectura + ADR-0032.
**Teste mínimo:** revisão humana; casos de replay e de servidor falso enumerados.
**Risco:** nulo (documental). **Gate:** GA5.

#### 30.13 — Implementação: servidor assina, cliente exige assinatura

**Ficheiros:** `license-server/backend/src/routes/check-in.js`,
`src/layer7d/license.c`, testes de ambos os lados.
**Teste mínimo:** resposta legítima aceita; resposta não assinada **rejeitada**;
replay rejeitado; servidor falso via `/etc/hosts` **não** consegue manter licença
viva; falha de rede continua a **não** afectar enforce (N3).
**Risco:** Médio-Alto. **Rollback:** `.pkg` anterior + deploy anterior do servidor.
**Gate:** GA5.

#### 30.14 — Check-in activo por defeito e política de migração — **BG-118**

**Objectivo:** corrigir A-04, a lacuna comercial mais caras.
**Entrega:** `check_in_enabled: true` em instalações novas; política explícita para
instalações existentes; caminho de excepção documentado para appliances
verdadeiramente isolados; runbook de suporte.
**Teste mínimo:** instalação nova arranca com check-in activo; upgrade não quebra
appliance existente; revogação no painel corta enforce em ≤ intervalo; **N3 mantido**.
**Risco:** **Alto** — muda comportamento de clientes instalados e cria dependência
de rede que hoje não existe. **Exige GO humano próprio** (secção 5, pontos 1 e 2).
**Rollback:** `.pkg` anterior + reverter default por configuração.
**Gate:** GA5.

#### 30.15 — Detecção de abuso multi-appliance — **BG-121**

**Objectivo:** corrigir A-08; tornar T1 (integrador multi-cliente) visível.
**Entrega:** alerta no license server quando a mesma chave tenta activar ou fazer
check-in de múltiplos `hardware_id`/IPs; painel mostra o sinal; decisão registada
sobre introduzir `max_activations`.
**Ficheiros:** `license-server/backend/src/`, migração, frontend admin.
**Teste mínimo:** cenário simulado gera alerta; licença legítima com rebind
autorizado **não** gera falso positivo.
**Risco:** Baixo (só servidor). **Rollback:** deploy anterior.
**Gate:** GA5.

---

### AP4 — Endurecimento residual e rastreabilidade

#### 30.16 — Decisão de licença distribuída — **BG-122**

**Objectivo:** mitigar A-02 (ponto único em `refresh_enforce_cfg()`).
**Implementação:** múltiplos pontos de decisão com resultados que se cruzam, de modo
a que um único patch não active enforce sem licença.
**Teste mínimo:** todos os casos de N1/N2 mantidos; nenhuma regressão de enforce.
**Risco:** Médio — complexidade no caminho crítico. Manter legibilidade.
**Rollback:** `.pkg` anterior. **Gate:** GA6.

#### 30.17 — Marcação por cliente (atribuição)

**Objectivo:** tornar uma cópia encontrada em campo atribuível à origem.
**Entrega:** marcação por cliente no conteúdo entregue e/ou no artefacto, com
avaliação de privacidade e sem telemetria (coerente com a política do produto).
**Risco:** Médio (privacidade e percepção). **Gate:** GA6.

#### 30.18 — Cadeia de assinatura de release completa — **BG-123**

**Objectivo:** fechar A-10 e cumprir o contrato F1.2 / ADR-0023 nas publicações
(manifesto + `.sig` + chave pública, além do `.sha256`).
**Ficheiros:** processo de release, `docs/06-releases/RELEASE-SIGNING.md`,
`MANUAL-INSTALL.md`. **Gate:** GA6.

#### 30.19 — Fecho da trilha

**Entrega:** revisão jurídica da EULA quanto a auditoria e penalidade por instalação
excedente; reavaliação do modelo de ameaças com os controlos novos; declaração
honesta do que **continua** possível para root (usar RR-1…RR-5 do §0.1 como base);
**execução da decisão n.º 8 do §5** sobre as releases antigas publicadas com o
caminho dev (RR-3); registo do que ficou sem prova CE (R-L); fecho no CORTEX e no
`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`.
**Gate:** GA6.

---

## 3. Matriz de rastreabilidade

| Achado | Descrição curta | Passo | Backlog | Gate |
|--------|-----------------|-------|---------|------|
| A-01 | modo dev no binário (bypass total) | 30.4, 30.5 | BG-114, BG-115 | GA2 |
| A-02 | ponto único de decisão do enforce | 30.16 | BG-122 | GA6 |
| A-03 | clock rollback não detectado | 30.6 | BG-116 | GA3 |
| A-04 | check-in desligado por defeito | 30.14 | BG-118 | GA5 |
| A-05 | resposta de check-in não assinada | 30.12, 30.13 | BG-119 | GA5 |
| A-06 | conteúdo actualiza sem licença | 30.8–30.11 | BG-117 | GA4 |
| A-07 | stats/GUI forjáveis | 30.7 | BG-120 | GA2 |
| A-08 | sem detecção multi-appliance | 30.15 | BG-121 | GA5 |
| A-09 | divergência builder ↔ repo | 30.2 | — (bloqueio de plano) | GA1 |
| A-10 | release sem manifesto assinado | 30.18 | BG-123 | GA6 |

---

## 4. Sequenciamento e dependências

```text
AP0  30.0 ─→ 30.1a (Composer: rascunhos) ─→ 30.1b (humano: GO) ─→ 30.2 ─→ 30.3
                                                              │
                                                              ▼  (bloqueio duro: nada toca license.c antes)
AP1  30.4 ─→ 30.5 ─→ 30.6 ─→ 30.7
                                │
                                ▼
AP2  30.8 ─→ 30.9 ─→ 30.10 ─→ 30.11 (GO próprio — RR-1)
                                │
                                ▼
AP3  30.12 ─→ 30.13 ─→ 30.14 (GO próprio — RR-1) ─→ 30.15
                                │
                                ▼
AP4  30.16 ─→ 30.17 ─→ 30.18 ─→ 30.19 (fecho + decisão 8 / RR-3)
```

**Regras de sequenciamento:**

1. **`30.1a` antes de `30.1b`.** O agente não “aceita” ADRs; o humano aceita.
2. **30.2 é bloqueio duro.** Nenhum passo AP1+ inicia antes de fechar A-09.
3. **AP2 antes de AP3.** A degradação de conteúdo é reversível e de baixo risco
   contratual; a obrigatoriedade de check-in não é. Ganhar AP2 primeiro reduz a
   pressão para forçar AP3.
4. **30.11 e 30.14 exigem GO humano próprio**, além do GO da trilha (RR-1).
5. **Um passo por bloco** (Composer §8); release apenas quando a natureza da
   mudança o exigir; artefacto = **`.pkg`**.
6. **Nunca** no mesmo bloco que promoção de enforce ou trilha MITM (R-I).

---

## 5. Decisões humanas — **RESOLVIDAS** (`30.1b`, `2026-08-10`)

Todas as oito decisões aceites conforme recomendação do plano (operador:
«concordo com tudo»). Ficha:
[`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md).
GOs de **execução** próprios em `30.11` e `30.14` mantêm-se.

| # | Decisão | Natureza | Passo afectado | Recomendação do plano |
|---|---------|----------|----------------|------------------------|
| 1 | Aceitar criar dependência de rede para o produto? | **Comercial** | 30.14 | Sim, com caminho de excepção para isolados (R-J) e **nunca** fail-closed (R-C) |
| 2 | Política de migração de clientes instalados para check-in; existem clientes genuinamente isolados? | Comercial + suporte | 30.14 | Novos = ON; existentes = migração anunciada + opt-out documentado se isolados |
| 3 | Retirar o espelho público de conteúdo corrente? | Comercial + suporte | 30.11 | **Sim** — sem isto AP2 é decorativo (RR-1) |
| 4 | Onde vive a chave pública de produção: repo privado versionada, ou fora do git? | Técnica + segurança | 30.2 (**bloqueio**) | Preferir **fora do git** (ficheiro/flag de build no builder) até haver processo de rotação; versionar só o *procedimento* |
| 5 | Reabrir **BG-101** (hoje `Documentado — não é bug`)? | Classificação | 30.1b | Reabrir como **lacuna comercial a corrigir** via ADR-0032 / BG-118 |
| 6 | Revisão jurídica da EULA quanto a auditoria e penalidades | Jurídica (externa) | 30.19 | Agendar; não bloqueia AP1/AP2 |
| 7 | Introduzir `max_activations` ou manter só alerta? | Comercial | 30.15 | **Fase 1 = só alerta**; `max_activations` só após falsos positivos medidos |
| 8 | Despublicar ou limitar downloads das releases antigas com caminho `is_dev_key`? (RR-3) | Comercial + segurança | 30.3 (insumo) → 30.19 (execução) | Inventariar em 30.3; **preferir** deixar de apontar `latest`/docs para versões com bypass e documentar risco residual das tags antigas |

**Nota RR-1:** se as decisões 1 e/ou 3 forem **Não**, o GO da trilha deve declarar
explicitamente que se aceita entregar só **higiene (AP1)**, não protecção T1/T2.

---

## 6. ADRs da trilha

| ADR | Título | Estado | Passo | RR obrigatórios no texto |
|-----|--------|--------|-------|--------------------------|
| [ADR-0030](../03-adr/ADR-0030-postura-anti-tamper-layer7d.md) | Postura anti-tamper do `layer7d` e remoção do modo dev de produção | **Aceito** (`30.1b`) | 30.1 → 30.4/30.5 | R-A, R-G, RR-3 |
| [ADR-0031](../03-adr/ADR-0031-entitlement-entrega-conteudo.md) | Entitlement na entrega de conteúdo (token de subscrição) | **Aceito** (`30.1b`) | 30.1 → AP2 | RR-1, RR-2, R-B, R-D |
| [ADR-0032](../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md) | Check-in obrigatório por defeito e resposta assinada com anti-replay (**emenda ADR-0021**) | **Aceito** (`30.1b`) | 30.1 → AP3 | RR-1, RR-5, R-C |
| [ADR-0033](../03-adr/ADR-0033-anti-rollback-relogio.md) | Anti-rollback de relógio e estado temporal suspeito | **Aceito** (`30.1b`) | 30.1 → 30.6 | RR-4, R-J |

Decisões **fora** de escopo por deliberação, a registar na ADR-0030: ofuscação
pesada, packers, anti-debug, fail-closed por rede, kill-switch remoto, CRL offline
(já rejeitada na ADR-0021).

---

## 7. Definition of done de cada passo

Um passo só fecha com **todos** estes pontos cumpridos:

1. Objectivo, impacto, risco, teste e rollback declarados (`AGENTS.md`).
2. Invariantes N1–N8 verificados e registados (passos com código/lab).
3. Gate correspondente com veredicto `PASS` (ou `DEFERRED` com ADR).
4. Evidência em `docs/tests/evidence/<run_id>-<passo>/` quando houver lab.
5. Documentação actualizada **no mesmo bloco**: este plano §0, `START-HERE`,
   `CORTEX.md`, e `MANUAL-INSTALL.md` se houver release ou mudança de comando.
6. Backlog actualizado com o estado real do item.
7. Se houve release: publicada em `pablomichelin/Layer7` com **`.pkg` + `.sha256`**
   (e manifesto assinado a partir do 30.18). Gates validam o **`.pkg` publicado**.
8. Progresso compacto do §0 e do `START-HERE` coincidentes com o CORTEX.
9. Contrato Composer do §8 cumprido (um passo; sem misturar trilhas; STOP se bloqueio).

---

## 8. Protocolo Composer 2.5 — contrato por passo

Este § é a interface operacional para executar a trilha com **Composer 2.5**
(ou agente equivalente). Sem ele, o agente tende a fazer vários passos de uma vez
ou a “aceitar” ADRs sozinho.

### 8.1 Regras invioláveis do agente

1. **Um** passo `30.x` (ou subpasso `30.1a` / `30.1b`) por chat / entrega.
2. Ler na ordem do `START-HERE-antipirataria.md` **antes** de editar.
3. Actualizar no **mesmo** bloco: plano §0 + START-HERE + CORTEX (progresso).
4. **Nunca** marcar ADR como `Aceito` sem texto explícito do humano.
5. **Nunca** tocar `src/layer7d/license.c` antes de `30.2` FECHADO.
6. **Nunca** misturar com MITM (`20.x`), IPv6, promoção de enforce, ou portal UI.
7. **Nunca** fail-closed por rede, kill-switch, ofuscação, segredos no git.
8. Gates de binário validam o **`.pkg` publicado**, não só o git (R-H).
9. Se CORTEX / plano / START-HERE divergirem no passo actual → **STOP** e declarar.
10. Resposta final no formato AGENTS: Resumo · Arquivos · Implementação · Teste ·
    Risco · Rollback · Docs.

### 8.2 Cartão obrigatório no início de cada chat

O agente deve imprimir (e cumprir) este cartão antes de editar:

```text
PASSO: <30.x>
PERMITIDO: <ficheiros / acções>
PROIBIDO: <lista>
STOP SE: <condições>
GATE: <GAx>
DoD: <checklist curta do passo>
```

### 8.3 Mapa passo → o que o Composer pode fazer

| Passo | Quem | PERMITIDO | PROIBIDO | STOP SE |
|-------|------|-----------|----------|---------|
| **30.1a** | Composer | Criar 4 ADRs `Proposto` + ficha §5; actualizar índice ADR, §0, START-HERE, CORTEX | `Aceito` nos ADRs; código; `license.c`; “decidir” as 8 | Ficheiros ADR já `Aceito` sem humano |
| **30.1b** | Humano (+ Composer só aplica ditado) | Preencher ficha; mudar ADRs→`Aceito`; BG-101; GA0 | Inventar decisões; código | Decisões 1/3 em branco e humano pediu avançar AP2 |
| **30.2** | Composer + lab builder | Diff builder↔repo; docs fluxo build; **backup** pubkey; evidência | Trocar pubkey sem GA1.8; apagar stash sem cópia | Pubkey em campo mudaria |
| **30.3** | Composer | Inventário `.pkg` 1.9.48 + tags antigas; teste que falha se bypass dev | Remover releases sem decisão 8 | — |
| **30.4–30.7** | Composer | Código do passo + testes + `.pkg` se release | Ofuscação; misturar passos; MITM | `30.2` não FECHADO |
| **30.8** | Composer | Doc de arquitectura do token (RR-2 explícito) | Código de emissão/cliente | Desenho sem casos offline |
| **30.9–30.10** | Composer | Servidor / cliente conforme desenho | Fail-closed; apagar conteúdo antigo | GA4.1 não PASS |
| **30.11** | Humano GO + Composer executa | Retirar espelho corrente; comunicação | Executar sem GO próprio | GO 30.11 ausente |
| **30.12–30.13** | Composer | Protocolo + implementação assinatura | Fail-closed rede | AP2 instável |
| **30.14** | Humano GO + Composer | Default check-in + migração | Forçar sem GO / sem runbook | GO 30.14 ausente |
| **30.15–30.19** | Composer / misto | Conforme passo | Telemetria; overclaim | — |

### 8.4 Prompt canónico — executar só o próximo passo

Copiar do `START-HERE` (secção *Prompt — Composer 2.5*). O agente **não** avança
para o passo seguinte no mesmo chat salvo pedido explícito do humano *depois* do
DoD do passo actual.

### 8.5 Ordem de valor (para o Composer não “optimizar” mal)

```text
AP0 (governação) → AP1 (higiene binário) → AP2 (valor real) → AP3 (revogação) → AP4
                 ↑                              ↑
            30.2 bloqueia                  30.11 GO próprio
                                           (sem isto AP2 ≈ teatro)
```

Se o humano pedir “protege já” sem GOs 30.11/30.14: o Composer **explica RR-1**
e continua na ordem; não salta para ofuscação nem fail-closed.
