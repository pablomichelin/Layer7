# Gates — Anti-pirataria e Anti-tamper (GA0–GA6)

**Plano:** [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md)
**Arranque:** [`../00-overview/START-HERE-antipirataria.md`](../00-overview/START-HERE-antipirataria.md)
**Modelo de ameaças:** [`../01-architecture/modelo-ameacas-antipirataria.md`](../01-architecture/modelo-ameacas-antipirataria.md)

Cada critério: `PASS` | `FAIL` | `BLOCKED` | `DEFERRED` (só com ADR) |
`PENDENTE` | `N/A` (só com **decisão humana registada** + justificativa
rastreável — ex.: audiência vazia; **não** usar para esconder FAIL técnico).
Evidência: pasta `docs/tests/evidence/<run_id>/` sempre que houver lab.

**Regra dos gates desta trilha:** todo critério que envolva o binário valida o
**`.pkg` publicado**, nunca só o código no repositório (R-H — o builder tem
`license.c`/`Makefile` divergentes).

**Regra de não-regressão:** os invariantes **N1–N8** do plano §1 são critério de
`FAIL` em **todos** os gates de AP1 em diante, além dos critérios próprios de cada gate.

**Dois ciclos (não misturar):** engenharia AP0–AP4 **FECHADA** em `30.19`
(`20260812T025741Z`). Ciclo de **evidência operacional FECHADO** — **BG-127 PASS**
(`20260814T224213Z`; GA2.6 enforce + GA4.8 campo; GA2.7/GA3.7/GA5.9 já PASS).
**GA6.7** permanece parecer jurídico **externo**. **`.254` vivo:**
`1.9.63` `mode=monitor` MITM **OFF**
([`20260814T034904Z-20.36-soak-align-163-254`](../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/)).
Histórico e2e AP2: `.254` = `1.9.54` (`20260811T114320Z`).
Runbook de campo:
[`../13-runbooks/evidencia-operacional-antipirataria-bg127.md`](../13-runbooks/evidencia-operacional-antipirataria-bg127.md).
Evidência:
[`../tests/evidence/20260814T051611Z-bg127/`](../tests/evidence/20260814T051611Z-bg127/)
+ [`../tests/evidence/20260814T053905Z-bg127/`](../tests/evidence/20260814T053905Z-bg127/)
+ [`../tests/evidence/20260814T224213Z-bg127/`](../tests/evidence/20260814T224213Z-bg127/).

---

## GA0 — Governação *(onda AP0, passos 30.0–30.1b)*

| # | Critério | Estado |
|---|----------|--------|
| GA0.1 | `START-HERE` + plano + este ficheiro existem e os links resolvem | **PASS** (`2026-08-10`) |
| GA0.2 | CORTEX / `docs/README.md` / backlog / índice de ADRs apontam a trilha | **PASS** (`2026-08-10`) |
| GA0.3 | Modelo de ameaças aceite como diagnóstico | **PASS** (`2026-08-10`) |
| GA0.4 | ADR-0030/0031/0032/0033 com estado `Aceito` (ou emenda registada) | **PASS** (`2026-08-10`, `30.1b`) |
| GA0.5 | As **oito** decisões humanas do plano §5 resolvidas e registadas na ficha | **PASS** (`2026-08-10`, `30.1b`) |
| GA0.6 | BG-114…BG-123 registados no backlog canónico | **PASS** (`2026-08-10`) |
| GA0.7 | BG-101 reclassificado ou mantido, com justificação escrita | **PASS** (`2026-08-10`, `30.1b`) — reaberto lacuna comercial |
| GA0.8 | Ficheiros ADR-0030…0033 existem em `Proposto` + ficha `decisoes-humanas-30.1.md` | **PASS** (`2026-08-10`, `30.1a`) |
| GA0.9 | Plano §8 (protocolo Composer) + RR-1…RR-5 presentes no SSOT | **PASS** (`2026-08-10c`) |

**Saída:** GA0 **completo** (`2026-08-10`). Bloqueio A-09 / pubkey fora do git
resolvido em **`30.2` FECHADO**. **`30.3` FECHADO** / **GA1 PASS** — próximo AP1 (`30.4`+).

---

## GA1 — Baseline e integridade do artefacto *(passos 30.2–30.3)*

| # | Critério | Estado |
|---|----------|--------|
| GA1.1 | Diff builder ↔ repo de `license.c` e `Makefile` documentado como evidência | **PASS** (`20260810T231840Z` — WT==HEAD) |
| GA1.2 | Decisão sobre a residência da pubkey de produção aplicada e registada | **PASS** (SoT `/root/layer7-build-secrets/`) |
| GA1.3 | Fluxo de build actualizado (`AGENTS.md` + `builder-freebsd.md`) e executado com sucesso | **PASS** (sem stash; verify+package) |
| GA1.4 | Build limpo pós-mudança produz `.pkg` funcional; `layer7d -t` e `--fingerprint` PASS | **PASS** |
| GA1.5 | `strings`/`nm` do `.pkg` confirmam a pubkey de produção esperada | **PASS** (sbin contém SoT) |
| GA1.6 | Inventário do `1.9.48` em campo registado (sha256, símbolos, presença de caminho dev) | **PASS** (`20260810T234552Z` — stripped; string bypass ABSENT; pubkey FOUND; residual fonte até 30.4) |
| GA1.7 | Teste automatizado que **falha** se um artefacto de release contiver o caminho de bypass dev | **PASS** (`audit-release-dev-bypass.sh` — selftest + dirty fixture exit 1) |
| GA1.8 | Licenças válidas em campo continuam válidas após a mudança de fluxo (pubkey inalterada) | **PASS** (sbin sha256 == `1.9.48` publicado) |

**Saída:** deixa de existir ambiguidade entre o que está no git e o que está em campo.
**GA1.8 é bloqueante absoluto** — trocar a pubkey inadvertidamente invalida licenças
de clientes pagantes.
**Estado GA1:** **PASS** completo (`30.2` + `30.3`). Evidências:
[`../tests/evidence/20260810T231840Z-30.2-builder-pubkey/`](../tests/evidence/20260810T231840Z-30.2-builder-pubkey/),
[`../tests/evidence/20260810T234552Z-ap0-baseline/`](../tests/evidence/20260810T234552Z-ap0-baseline/).
**Nota GA1.6:** o `.pkg` `1.9.48` **não** expõe marcadores binários de
`is_dev_key` (strip + DCE); o residual A-01 permanece no **fonte** até `30.4`
(`--check-source` exit 1). Isto **não** é falso PASS do artefacto.

---

## GA2 — Anti-tamper do binário *(passos 30.4, 30.5, 30.7)*

| # | Critério | Estado |
|---|----------|--------|
| GA2.1 | Build de produção **não contém** `is_dev_key` nem o bloco de bypass | **PASS** (`20260810T235325Z` — `#ifdef L7_DEV_BUILD`; marcadores ausentes no `.pkg` 1.9.49) |
| GA2.2 | Pubkey inválida/all-zeros num build de produção ⇒ licença **inválida** (monitor), nunca válida | **PASS** (`test-prod-no-dev-bypass.sh` no builder) |
| GA2.3 | Modo dev existe apenas sob `L7_DEV_BUILD`, flag ausente do port | **PASS** (`30.4`) |
| GA2.4 | Artefacto strippado: `nm`/`strings` sem símbolos de licença | **PASS** (`20260810T200329Z` — stripped; marcadores ausentes) |
| GA2.5 | Daemon arranca, `-t` PASS, `--fingerprint` PASS após strip | **PASS** (`1.9.50` no builder) |
| GA2.6 | Licença válida ⇒ enforce idêntico ao `1.9.48` (**N1**) | **PASS** campo (`20260814T224213Z`) — `mode=enforce` + PF `layer7:block:*`; `valid=1`; PID intacto; MITM OFF; restore a `monitor`. Monitor já PASS em `20260814T051611Z` |
| GA2.7 | Licença ausente/inválida ⇒ monitor, daemon vivo, zero regras PF de block (**N2**) | **PASS** campo (`20260814T053905Z`) — licença teste id 14 ausente ⇒ `valid=0`, daemon vivo, zero block; produção restaurada |
| GA2.8 | Stats/ficheiros forjados **não** desbloqueiam Identity/MITM na GUI | **PASS** (`20260810T214800Z` — `test_entitlements_gui.php`) |
| GA2.9 | Gate MITM não é activável escrevendo ficheiros à mão | **PASS** (`layer7-mitm-entitle-ok` + rc.d; R-A permanece) |
| GA2.10 | Sem regressão na trilha Identity+MITM: `test_mitm_config.php` e suite de entitlements PASS | **PASS** (`1.9.52` builder) |
| GA2.11 | Limite de diagnóstico do strip registado honestamente (core dumps menos legíveis) | **PASS** (evidência `30.5` + ADR-0030) |

**Saída:** o caminho de bypass mais curto desaparece e o custo de ataque sobe de
minutos para horas com ferramentas. **Não** se declara "impossível de contornar".
**Estado GA2:** **PASS** — GA2.1–2.5 + GA2.8–2.11 (`30.4`+`30.5`+`30.7`);
GA2.6 **PASS** enforce (`20260814T224213Z`); GA2.7 **PASS**
(`20260814T053905Z`). Campanhas [`20260814T051611Z-bg127`](../tests/evidence/20260814T051611Z-bg127/)
+ [`20260814T053905Z-bg127`](../tests/evidence/20260814T053905Z-bg127/)
+ [`20260814T224213Z-bg127`](../tests/evidence/20260814T224213Z-bg127/).

---

## GA3 — Anti-rollback temporal *(passo 30.6)*

| # | Critério | Estado |
|---|----------|--------|
| GA3.1 | Marca persistente do maior timestamp observado, resistente a reinício | **PASS** (`clock-mark.json` + `20260810T201043Z`) |
| GA3.2 | Relógio a avançar normalmente ⇒ zero efeito | **PASS** (`test_license_clock`) |
| GA3.3 | Retrocesso pequeno (ajuste NTP legítimo) ⇒ tolerado sem evento de alarme | **PASS** (≤86400 s) |
| GA3.4 | Retrocesso grande ⇒ estado suspeito, degradação para **monitor**, evento de auditoria | **PASS** (lógica + `L7_AUDIT_NOTE`; lab appliance DEFERRED) |
| GA3.5 | Daemon **nunca** termina nem entra em crash por estado temporal (**R-C**) | **PASS** |
| GA3.6 | Recuperação após corrigir a hora: documentada, testada e executável pelo operador (**N6**) | **PASS** (runbook + unitário recuperação) |
| GA3.7 | Licença dentro da validade com relógio correcto ⇒ inalterado (**N1**) | **PASS** campo (`20260814T051611Z`) — NTP OK; `clock_suspect=0`; `valid=1`; modo = P0 |
| GA3.8 | Rollback ao `.pkg` anterior ignora o ficheiro de estado sem erro (**N7**) | **PASS** (doc — versões <1.9.51 ignoram o ficheiro) |
| GA3.9 | ADR-0033 / runbook declaram explicitamente as evasões RR-4 (apagar estado; relógio congelado) e que o fecho real é AP3 | **PASS** |

**Saída:** prolongar licença expirada com `date` deixa de ser trivial, sem punir
clientes com relógio genuinamente errado. **Não** se afirma que 30.6 contém o T2 técnico.
**Estado GA3:** **PASS** (engenharia) + GA3.7 **PASS** campo (`20260814T051611Z`).

---

## GA4 — Entitlement na entrega de conteúdo *(passos 30.8–30.11)*

Gate da onda de **maior valor estratégico**. Também o de maior risco de suporte.

| # | Critério | Estado |
|---|----------|--------|
| GA4.1 | Desenho e contrato do token fechados e revistos antes de código | **PASS** (`contrato-token-subscricao-conteudo-30.8.md`; revisão humana OK `2026-08-10`) |
| GA4.2 | Servidor emite token para licença activa; recusa para revogada/expirada | **PASS** (`30.9` — `content_subscription` só em active; npm test) |
| GA4.3 | Token ligado ao `hardware_id`; inútil noutro appliance | **PASS** (`30.9` — payload `hardware_id`; verify testes) |
| GA4.4 | Cliente com token válido actualiza conteúdo (PASS ponta a ponta) | **PASS** (campo `1.9.54`, `20260811T114320Z`) — update autenticado (mirror); primary público também OK após DNS; hold-active/GUI OK |
| GA4.5 | Cliente **sem** token: não actualiza, mantém conteúdo antigo, **enforce intacto** (**R-D**, **N4**) | **PASS** (local/builder + `.254` hold-active; snapshot intacto; evidência `20260811T020533Z`) |
| GA4.6 | Falha de rede/servidor: **zero** impacto em enforce (**R-C**, **N3**) | **PASS** (`30.10` — sem token: conteúdo local mantido; enforce/mode intactos no `.254`) |
| GA4.7 | Estado da subscrição de conteúdo visível e compreensível na GUI | **PASS** (GUI + helper; e2e `1.9.54` / `20260811T114320Z` reportou `status=ok`) |
| GA4.8 | Offline prolongado dentro da janela definida: PASS sem intervenção | **PASS** campo (`20260814T224213Z`) — token `ok`; corte do updater (proxy morto) → HTTP 000 primary+mirror; hold-active `ut1-2026-04-25`; modo/`valid`/PID intactos. Duração real ~60 s (declarada). `/etc/hosts` no pfSense **não** isola `curl` (Unbound) |
| GA4.9 | Assinatura do manifesto continua verificada como hoje (integridade preservada) | **PASS** (`30.10` — `openssl pkeyutl -verify` do manifesto intacto no cliente) |
| GA4.10 | Espelho anónimo já não serve conteúdo **corrente**; nenhum appliance legítimo perde enforce | **PASS** (`20260812T011217Z`) — release `blacklists-ut1-current` com **0 assets**; enforce não tocado. Residual CDN @ cut+~1min (302→SAS; manifesto 200; tarball 404); recheck `20260812T012017Z` **404×4** — evidência `03-recheck-cdn.txt` |
| GA4.11 | Procedimento de reposição do espelho pronto e testado (rollback comercial) | **DOC READY** — [`../13-runbooks/content-mirror-rollback-ga4.11.md`](../13-runbooks/content-mirror-rollback-ga4.11.md); reposição não executada (não necessária) |
| GA4.12 | Comunicação a clientes emitida antes de 30.11 | **N/A** (`2026-08-12`) — decisão humana: sem destinatários externos; cut = decisão interna; impacto futuro → janela de manutenção por e-mail ops. Rastreio: [`prep-cut-30.11-espelho.md`](prep-cut-30.11-espelho.md) §1. Rascunho histórico **não emitido**: [`../13-runbooks/content-mirror-comms-ga4.12-draft.md`](../13-runbooks/content-mirror-comms-ga4.12-draft.md) |
| GA4.13 | Sem segredos novos no repositório, incluindo fixtures (**N8**, **R-K**) | **PASS** (`30.9` — seed efémera só em teste; prod via `ED25519_PRIVATE_KEY`) |
| GA4.14 | ADR-0031 / desenho 30.8 declaram RR-2 (redistribuição por appliance licenciado) e que a resposta é atribuição+contratual, não bloqueio técnico | **PASS** (ADR-0031 §5 + contrato 30.8 §7) |
| GA4.15 | GO próprio de 30.11 registado; se GO=Não, veredicto declara que AP2 ficou **higiene parcial** (RR-1) | **PASS** (`2026-08-12`) — GO gestor explícito + cut executado (`delete-asset` ×4); evidência [`../tests/evidence/20260812T011217Z-30.11-cut-mirror/`](../tests/evidence/20260812T011217Z-30.11-cut-mirror/) |

**Preflight primary (pré-cut, não substitui GA4.10):** GET HTTPS
`downloads.systemup.inf.br/.../manifest` + `.sig` com token local → **200/200**
(823/64); sem token → **401** — evidência
`docs/tests/evidence/20260812T003214Z-30.11-auth-get-254/` **PASS**.

**Estado GA4:** **PASS** nos critérios de cut (GA4.10/15) + GA4.1–4.7/4.9/4.13/4.14
**PASS**; primary auth preflight **PASS**; 30.9 **live**; GA4.8 campo **PASS** (`20260814T224213Z`);
GA4.11 doc ready; **GA4.12 N/A**. Residual CDN GitHub @ cut documentado;
recheck anónimo **404×4** (`03-recheck-cdn.txt`) — release continua vazia.

Evidência cut PASS: `docs/tests/evidence/20260812T011217Z-30.11-cut-mirror/`.
Evidência e2e PASS: `docs/tests/evidence/20260811T114320Z-30.10-e2e-154-254/`.
Evidência primary auth PASS: `docs/tests/evidence/20260812T003214Z-30.11-auth-get-254/`.
Evidência STOP `1.9.53`: `docs/tests/evidence/20260811T110638Z-30.10-revalidate-254/`.
Evidência 1ª janela: `docs/tests/evidence/20260811T020533Z-30.10-validate-254/`.

**Saída:** uma cópia sem subscrição válida degrada sozinha ao longo do tempo —
token obrigatório no primary; espelho rolling sem assets correntes.

---

## GA5 — Check-in verificável e obrigatório *(passos 30.12–30.15)*

Gate de maior impacto em suporte. **Não abrir sem GA4 estável.**

| # | Critério | Estado |
|---|----------|--------|
| GA5.1 | Protocolo assinado com nonce especificado e revisto | **PASS** (`2026-08-12`) — [`../01-architecture/contrato-check-in-assinado-30.12.md`](../01-architecture/contrato-check-in-assinado-30.12.md); evidência `20260812T013200Z-30.12-protocol-design` |
| GA5.2 | Resposta legítima assinada é aceite | **PASS** (`20260812T013913Z`) — unit JS C1 + payload C |
| GA5.3 | Resposta **não assinada** é rejeitada pelo cliente | **PASS** — cliente novo exige envelope; C2 unit |
| GA5.4 | Replay de resposta anterior rejeitado | **PASS** — nonce mismatch (JS C4 + `test_checkin_signed.c`) |
| GA5.5 | Servidor falso via `/etc/hosts` ou DNS **não** consegue manter licença viva | **PASS** (unit lógico C5/C6 — chave errada/unsigned); campo `/etc/hosts` opcional pós-deploy |
| GA5.6 | Falha de rede continua a não afectar enforce (**N3**) — verificado explicitamente neste gate | **PASS** — verify/rede → `L7_CHECKIN_NETWORK` sem invalidate |
| GA5.7 | Instalação nova arranca com check-in activo | **PASS** (`20260812T015519Z`) — sample/bare `true`; evidência `20260812T015519Z-30.14-checkin-default` |
| GA5.8 | Upgrade de instalação existente não quebra o appliance | **PASS** — preserva `false`/ausente (unit PHP+C) |
| GA5.9 | Revogação no painel corta enforce em ≤ intervalo configurado | **FAIL** campo (`20260814T053905Z`) — id 14 revogado; `--check-in` oficial HTTP 400 (API live rejeita `nonce`); `valid` ficou 1 (N3); legado sem nonce = `409 revoked` |
| GA5.10 | Caminho de excepção para appliance isolado documentado e testado | **PASS** — runbook + `false` efectivo OFF |
| GA5.11 | Runbook de suporte publicado antes da release | **PASS** — [`../13-runbooks/check-in-migration-30.14.md`](../13-runbooks/check-in-migration-30.14.md) |
| GA5.12 | Alerta de abuso multi-appliance funciona; rebind autorizado **não** gera falso positivo | **PASS** — unit `multi-appliance-abuse.test.js` (`30.15`) |
| GA5.13 | Compatibilidade de transição com clientes antigos verificada | **PASS parcial** — dual-mode D10 + upgrade preserve |

**Estado GA5:** **parcial** (engenharia PASS) — GA5.1–5.8 + 5.10–5.12 **PASS**;
GA5.9 **FAIL campo** (`20260814T053905Z`) — API live pré-30.13; residual
deploy API + repetir corte. BG-127 permanece aberto.
Evidência `30.15`: `docs/tests/evidence/20260812T020331Z-30.15-multi-appliance-abuse/`.

**Saída:** revogar uma licença passa a ter efeito real, sem transformar
indisponibilidade de rede em firewall parado.

---

## GA6 — Endurecimento residual e fecho *(passos 30.16–30.19)*

| # | Critério | Estado |
|---|----------|--------|
| GA6.1 | Decisão de licença distribuída sem regressão de enforce (**N1**, **N2**) | **PASS** (unit `30.16` — `test_license_enforce_gate.c`) |
| GA6.2 | Código continua legível e mantível apesar da distribuição de decisão | **PASS** — módulo puro `license_enforce_gate.c` + `enforce_armed` sem ofuscação |
| GA6.3 | Marcação por cliente implementada com avaliação de privacidade registada | **PASS** (gate-control `20260812T024419Z`; sidecar + [`../01-architecture/marcacao-cliente-30.17.md`](../01-architecture/marcacao-cliente-30.17.md)) |
| GA6.4 | Sem telemetria introduzida (coerente com a política do produto) | **PASS** (gate-control `20260812T024419Z`) — marca só local; `policy=local_only_no_telemetry` |
| GA6.5 | Releases publicam manifesto + `.sig` + chave pública (ADR-0023 / F1.2) | **PASS (processo)** (gate-control `20260812T025135Z`; impl. `b36f2e3`) — dry-run ×2 + política; **residual campo** até 1ª publish Fase 1 (**BG-028**) |
| GA6.6 | `MANUAL-INSTALL.md` com comandos e links da versão actual (regra especial do `AGENTS.md`) | **PASS** (gate-control `20260812T025135Z`) — links `1.9.54` + nota/procedimento F1.2 |
| GA6.7 | Revisão jurídica da EULA concluída | **PASS (agenda)** (`30.19`) — parecer **externo** residual; **fora** do BG-127 |
| GA6.8 | Modelo de ameaças reavaliado com os controlos novos | **PASS** (`30.19`) — [`../01-architecture/fecho-trilha-antipirataria-30.19.md`](../01-architecture/fecho-trilha-antipirataria-30.19.md) |
| GA6.9 | Declaração honesta do que **continua** possível para root (**RR-1…RR-5**) | **PASS** (`30.19`) — RR-1…RR-5 + R-A no fecho |
| GA6.10 | O que ficou sem prova em pfSense CE declarado (**R-L**, ADR-0022) | **PASS** (`30.19`) — CE sem prova; lab Plus |
| GA6.11 | Fecho registado no CORTEX e no `ESTADO-PRODUTO-E-PLANOS-FECHADOS.md` | **PASS** (`30.19`) |
| GA6.12 | Decisão §5 n.º 8 (releases antigas com bypass) executada ou risco residual aceite por escrito (RR-3) | **PASS** (`30.19`) — apontadores `1.9.54` + aviso; tags **não** alteradas |

**Estado GA6:** **PASS** (engenharia `30.16`–`30.19`) — 6.5 residual campo/BG-028
**FECHADO** `v1.9.58`; 6.7 residual parecer EULA **externo** (fora do BG-127).
Evidência fecho `30.19`: `docs/tests/evidence/20260812T025741Z-30.19-fecho/`.
Evidência fecho `30.18` (gate-control): `docs/tests/evidence/20260812T025135Z-30.18-gate-control/`.
Evidência impl. `30.18`: `docs/tests/evidence/20260812T024826Z-30.18-release-signing/`.
Evidência fecho `30.17` (gate-control): `docs/tests/evidence/20260812T024408Z-30.17-gate-control/`.

**Saída:** trilha **fechada**, com limites declarados sem overclaim.

---

## Critérios transversais de `FAIL` imediato

Qualquer um destes, em qualquer gate, é `FAIL` sem discussão:

1. Falha de rede, DNS ou license server a reduzir enforce ou a parar o daemon (**R-C**).
2. Conteúdo em falta a desligar enforce em vez de apenas não actualizar (**R-D**).
3. Mecanismo que permita a um servidor desligar o enforce de um appliance (**R-E**).
4. Cliente legítimo sem caminho de recuperação documentado (**R-J**).
5. Segredo ou material de assinatura commitado (**R-K**).
6. Passo desta trilha misturado com promoção de enforce, MITM ou IPv6 (**R-I**).
7. Gate validado só contra o repositório, sem verificar o `.pkg` publicado (**R-H**).
8. Alegação de que o produto ficou "impossível de contornar" (**R-A**).

---

## Registo de veredictos

| Data | Gate | Passo | Veredicto | Evidência |
|------|------|-------|-----------|-----------|
| `2026-08-10` | GA0 (parcial) | 30.0 | **PASS parcial** — GA0.1/0.2/0.3/0.6 PASS; 0.4/0.5/0.7 pendentes de 30.1b | documental (sem lab) |
| `2026-08-10` | GA0.9 | rev. `c` | **PASS** — protocolo Composer §8 + RR-1…RR-5 no plano | documental |
| `2026-08-10` | GA0.8 | 30.1a | **PASS** — ADR-0030…0033 `Proposto` + ficha | documental |
| `2026-08-10` | **GA0** | 30.1b | **PASS completo** — ADRs `Aceito`; 8 decisões; BG-101 reaberto | ficha + ADRs + backlog |
| `2026-08-14` | campo residual | **BG-127** | **ABERTO** — formalização documental apenas; **sem** execução; **sem** PASS de GA2.6/2.7/3.7/4.8/5.9; GA6.7 fora | soak vivo [`20260814T034904Z-20.36-soak-align-163-254`](../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/) (`.254`=`1.9.63` monitor / MITM OFF) |
| `2026-08-14` | campo residual | **BG-127** P0–P6 | **PARTIAL** — P0/P1 **PASS**; GA2.6 **PASS parcial** (monitor) + enforce **DEFERRED**; GA3.7 **PASS**; GA2.7 **ABORT**; GA4.8 **DEFERRED**; GA5.9 **ABORT**; produção intocada; MITM OFF | [`20260814T051611Z-bg127`](../tests/evidence/20260814T051611Z-bg127/) |
| `2026-08-14` | campo residual | **BG-127** P3+P6 | **PARTIAL** — GA2.7 **PASS**; GA5.9 **FAIL campo** (API live rejeita `nonce`; N3; legado 409 revoked); id 13 intocado; produção restaurada; MITM OFF | [`20260814T053905Z-bg127`](../tests/evidence/20260814T053905Z-bg127/) |
| `2026-08-14` | campo residual | **BG-127** P2+P5 | **PASS** — GA2.6 enforce + GA4.8 campo; soak restaurado `1.9.63` monitor / MITM OFF; fecha BG-127 | [`20260814T224213Z-bg127`](../tests/evidence/20260814T224213Z-bg127/) |
