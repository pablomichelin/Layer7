# Mapa canónico — prontidão MITM para piloto (`2026-08-09`)

**Tipo:** auditoria **somente leitura / documental** (sem mutação lab, código, build ou release).  
**Veredicto:** ficha/P5 **RETIRADOS** (ADR-0035). Trilha **【FILA FECHADA】** (20.37). Operação MITM = GUI + entitlement. Soak `.254` = `1.9.63` MITM **OFF**. Default OFF. Sem overclaim de paridade **já** atingida.  
**P1:** [`GO-escopo-piloto-mitm-generico.md`](GO-escopo-piloto-mitm-generico.md) — D1–D9 **ACEITE**.  
**P2:** [`runbook-piloto-mitm-generico.md`](runbook-piloto-mitm-generico.md) — canónico ops.  
**P4 evidência (PASS):** [`../tests/evidence/20260813T224009Z-p4-retry2-254/`](../tests/evidence/20260813T224009Z-p4-retry2-254/) — **CLOSED PASS**. Histórico FAIL: [`../tests/evidence/20260809T234042Z-p4-soak-254/`](../tests/evidence/20260809T234042Z-p4-soak-254/).  
**Pacote de referência lab/`latest`:** `1.9.63` (`SHA256=f47b1dd82e7d99f8a1f8e6bbd2fe101c0ed33688b45cfcfbb356367db853c373`). Soak `.254`: `1.9.63` MITM OFF (20.36).  
**Produção enforce base (sem MITM):** `1.9.8` (inalterada por este mapa).  
**Arranque:** [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)  
**SSOT execução:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**SSOT vivo:** [`../../CORTEX.md`](../../CORTEX.md)

---

## Definições (obrigatórias — anti-overclaim)

| Termo | Significado canónico | Estado |
|-------|----------------------|--------|
| **Teste controlado** | Janela ≤15 min; src `/32` × dst `/32` + SNI lab; rollback imediato | **PASS** `215442Z` |
| **Piloto** | Janela multi-hora/dias; clientes/destinos nomeados; CA/trust operacional; suporte; critérios de saída; failsafe | **NÃO atingido** |
| **Permanente / produção MITM** | Intercept ON sem janela de teste; políticas estáveis | **NO-GO** (decisão humana mantida) |
| **Pronto para piloto** | Termo legado — ficha **já não é gate** (ADR-0035). Feature opera via GUI + entitlement. | **20.35** productizar |

**Regra:** nenhum documento pode marcar “pronto para piloto” sem evidência de soak/ops + GO humano explícito de piloto.

---

## (a) Implementado e evidenciado (verificável)

| # | Item | Evidência / gate | Nota |
|---|------|------------------|------|
| A1 | Runtime `layer7-tlsproxy` no `.pkg` (default OFF) | `1.9.39`+; MANUAL addenda; GI2.1 | Opt-in |
| A2 | Intenção vs efectivo (`mitm.enabled` / `mitm_effective`) | 20.9; contrato IPC; `test_mitm_config.php` | Sem gates ⇒ effective false |
| A3 | Rdr só `source_cidr` ∧ `dest_cidr` (proibido `from any`) | `1.9.42`; BG-087 | Hardening produção |
| A4 | Anti-QUIC UDP/443 escopo MITM + `filter_configure_sync` | `1.9.46`; Gate C `210753Z` | Edge **sem** `--disable-quic` |
| A5 | Leaf SNI `serverAuth` (D1) | `gate-D1-leaf-sni`; tlsproxy ≥0.1.3 | Corrige KEY_USAGE |
| A6 | Control-plane timeout / anti-hang | `1.9.43/44`; harness timeout | D0 F1-bis |
| A7 | Tabelas PF MITM live | `1.9.45`+ | |
| A8 | GI2 / GI3 lab | `20260809T060000Z-20.11-gi2-gi3-54` | S3 Edge `.24` |
| A9 | Topologia lab `198.18.0.10` via `.54` (A+B+C) | `180157Z` / `180624Z` / `181302Z` | Path via GW |
| A10 | GO teste controlado `.254` + rollback | `215442Z` + preflight `215218Z` | `quic_mode=block`; **não** permanente |
| A11 | Builder regressões MITM | gates-obrigatorios §B **PASS** | Antes de publish |
| A12 | Squid rejeitado | ADR-0026 / spike | Permanente |
| A13 | Identity rede FECHADA (ortogonal) | 20.33 / GI9 | Não bloqueia MITM |
| A14 | Baseline appliance pós lab-pair | `225533Z` → MONITOR; MITM OFF | Bom ponto de partida |
| A15 | P4.1 supervisor on-box live | cron `/etc/crontab` 1 min; stamp fresco; MITM OFF pós-retry2 | Failsafe confirmado |
| A16 | P4.2 helper SSH `-T` | `tests/harness/mitm-p4-soak/` local PASS + 16/16 health retry2 | Corrige falso `no_key` |
| A17 | P4 soak 4 h + rollback limpo | `224009Z` CLOSED PASS; `rollback_clean=1`; verify `02:54:33Z` | Phase C `.24` **NA** neste retry |

---

## (b) Lacunas técnicas concretas

| ID | Lacuna | Porquê bloqueia piloto | Severidade |
|----|--------|------------------------|------------|
| T1 | **Failsafe de janela longa** | P3 + P4.1 + soak retry2 **PASS** (`224009Z`); residual = ops em site cliente (P5) | Baixa (ops piloto) |
| T2 | **Observabilidade operador insuficiente para soak** | Contadores/banner/`mitm_effective` claros na GUI durante ON; evidência só de janela curta | Alta |
| T3 | **S6 ECH = NA/limite** | Comportamento previsível documentado, **não** exercitado; piloto deve declarar limite honesto | Média (aceite se honesto) |
| T4 | **Prova CE física ausente** | Lab = pfSense Plus; ADR-0022 — não inferir CE só de Plus | Média |
| T5 | **Soak multi-hora** | **Fechado** no lab (`224009Z` PASS; 16 health; rollback limpo). Residual: Phase C NA neste retry; Edge já em P4 original | Baixa |
| T6 | **CA piloto vs CA efémera** | Teste apaga CA; piloto exige ciclo export GPO / rotação / revogação documentado **e** exercitado | Alta (ops+tech) |
| T7 | **Escopo multi-cliente não aprovado** | Só `.24` evidenciado; `.234/.235` proibidos sem GO | Depende do GO piloto |
| T8 | **Resíduos documentais “runtime AUSENTE”** | Plano/ADR/R-T desactualizados vs `1.9.46` — risco de agente errar | Média (docs; corrigido neste bloco) |

**Não é lacuna de motor TLS “enterprise”:** fora de objectivo (ADR-0026 / posicionamento PME).

---

## Gate de activação externa (≠ lacuna de engenharia)

| Campo | Valor |
|-------|--------|
| **Objectivo** | Separar o que bloqueia **activação num cliente** do que ainda é **débito de código (P3)** |
| **Impacto** | SSOT / ops; zero runtime |
| **Risco** | Baixo — evita overclaim “falta feature” quando falta ficha |
| **Teste documental** | Activação externa exige todos os campos abaixo nomeados; checklist P1 §3 |
| **Rollback** | N/A (norma); reverter docs se texto for ambíguo |

**Norma (`2026-08-14`, ADR-0035):** a ficha de sete campos **deixou de ser gate**.  
Activar MITM = entitlement + GUI + controlos de produto (`source_cidr`∧`dest_cidr`, default OFF).  
O formulário P1 §3 fica **histórico** (princípios D1–D9 ainda válidos como qualidade de produto, não como papel).

---

## (c) Lacunas operacionais / comerciais e decisões humanas

| ID | Tipo | Decisão / item | Dono | Bloqueia activação? |
|----|------|----------------|------|---------------------|
| H1 | Norma | GO piloto controlado (≠ teste 15 min; ≠ permanente) | Operador | Norma P1 ACEITE |
| H2 | **RETIRADO** | Ficha site (ADR-0035) | — | **Não** |
| H3 | Ops site | CA cliente + GPO/MDM + privkey | Ops / MSP | **Sim** (exercício site) |
| H4 | Comercial | SKU / entitlement `mitm` | Comercial | **Sim** (gate site) |
| H5 | **RETIRADO** | Critérios de saída em papel | — | **Não** (passa à GUI / 20.35) |
| H6 | Honesty | Limites ECH/pinning/não-NGFW | Produto | P1 §4 |
| H7 | Norma lab | `.234/.235` / produção real | Operador | Proibido salvo GO adicional |
| H8 | **RETIRADO** | Responsáveis em papel | — | **Não** |

---

## (d) Sequência mínima de blocos até “pronto para piloto”

Cada bloco: objectivo / impacto / risco / teste / rollback.  
**Parar** se qualquer bloco falhar. **Sem** classificar pronto sem evidência.

### Bloco P0 — Fecho documental / SSOT (este mapa) — **PASS** (`bc6f5c2`)

| Campo | Valor |
|-------|--------|
| Objectivo | Resolver drift “runtime AUSENTE”; fixar veredicto NÃO PRONTO; apontar próximo passo |
| Impacto | Só docs |
| Risco | Baixo |
| Teste | Links vivos; CORTEX/START-HERE/plano/ADR/backlog alinhados |
| Rollback | Reverter commit docs |

### Bloco P1 — GO / escopo piloto genérico — **PASS docs** (`GO-escopo-piloto-mitm-generico.md`)

| Campo | Valor |
|-------|--------|
| Objectivo | Materializar D1–D9 + formulário site |
| Impacto | Desbloqueia P2–P5 (norma); não activa |
| Risco | Baixo |
| Teste | Checklist P1 §6 |
| Rollback | Reverter commit docs |

### Bloco P2 — Runbook piloto genérico — **PASS docs** (`runbook-piloto-mitm-generico.md`)

| Campo | Valor |
|-------|--------|
| Objectivo | Ops: CA/GPO, metadados 30d, allow explícito, explicabilidade, break-glass, auto-disable |
| Impacto | Ops |
| Risco | Baixo |
| Teste | Checklist P2 §11 |
| Rollback | Reverter commit docs |

### Bloco P3 — **Primeiro bloco de código** — **PASS** (`1.9.47`)

| Campo | Valor |
|-------|--------|
| Objectivo | Trilho de segurança para janela piloto (failsafe + visibilidade) |
| Impacto | Package/GUI control-plane; **sem** alargar blast radius do rdr |
| Risco | Médio |
| Teste | Builder `test_mitm_config` + `test_mitm_regress` PASS — [`20260809T230400Z-p3-mitm-window`](../tests/evidence/20260809T230400Z-p3-mitm-window/) |
| Rollback | Pacote `1.9.46` |

### Bloco P4.1 — Supervisor on-box (código) — **PASS publicado** (`1.9.59`)

| Campo | Valor |
|-------|--------|
| Objectivo | Failsafe operacional no appliance (cron 1 min) — P4 abortou porque o supervisor Mac não armou |
| Impacto | Package/GUI; **não** alarga rdr; default OFF |
| Risco | Baixo — tick no-op com MITM OFF |
| Teste | `test_mitm_config.php` + `test_mitm_regress.php` PASS |
| Rollback | Pacote `1.9.58` |
| Runbook | [`runbook-p4-retry-supervisor-onbox.md`](runbook-p4-retry-supervisor-onbox.md) |
| Estado | **P4 retry2 CLOSED PASS** (`224009Z`); **P4.1 live**; MITM OFF `02:54:33Z`; **sem** MITM permanente |

### Bloco P4 — Soak lab controlado (evidência) — **CLOSED PASS** retry2 `20260813T224009Z`

| Campo | Valor |
|-------|--------|
| Objectivo | Janela ≥4 h (mínimo) scoped; rollback limpo no fecho |
| Impacto | `.254` temporário; **não** permanente |
| Risco | Médio |
| Teste | 16/16 health tries=1 (P4.2 `-T`); escopo `.24`→`198.18.0.10`; `rollback_clean=1`; verify live OFF |
| Rollback | Fecho automático `02:39:19Z` + watchdog `02:43:21Z` |
| Evidência PASS | [`../tests/evidence/20260813T224009Z-p4-retry2-254/`](../tests/evidence/20260813T224009Z-p4-retry2-254/) |
| Histórico FAIL | `234042Z` supervisor não armado; `170000Z` `health_ssh_fail` |
| Phase C | **NA** neste retry (sem cliente `.24`); Edge já em P4 original |
| Veredicto | **PASS** (janela+rollback). Histórico: não desbloqueava piloto sem P5 — **P5 retirado** (ADR-0035) |

### Bloco P5 — **RETIRADO** (`2026-08-14`, ADR-0035)

| Campo | Valor |
|-------|--------|
| Objectivo | Era: ficha nomeada antes de ON externo |
| Estado | **Cancelado** — o operador rejeitou o gate papel; ninguém o usa |
| Substitui | **20.35** — productizar MITM na GUI (entitlement; rumo a UX NGFW) |
| Rollback deste retrair | Restaurar texto P5 + ADR-0035 |

---

## (e) Primeiro bloco de código — o quê e porquê

### Escolha canónica: **P3 — Trilho de segurança da janela piloto**

**Não** começar por novas features de intercept (já cobertas em `1.9.46` para o caso scoped).  
O salto teste→piloto falha por **ops + failsafe + visibilidade**, não por falta de rdr/anti-QUIC.

**Escopo mínimo de código (um PR):**

1. **Auto-disable / max window** configurável (ex. minutos) com failsafe fiável (não só `at` manual).  
2. **Estado operador** inequívoco na GUI/diagnóstico enquanto `mitm_effective=true` (escopo src/dst/SNI, tempo restante, QUIC mode).  
3. **Auditoria de metadados** da activação (sem payload) para evidência de piloto.  
4. Testes: extensão de `test_mitm_config.php` / regress MITM + smoke lab OFF após timeout.

| Campo | Valor |
|-------|--------|
| Objectivo | Tornar seguro um soak/piloto sem depender de disciplina humana dos 15 min |
| Impacto | Controlo de lifecycle MITM; sem mudar semântica de rdr scoped |
| Risco | Médio — control-plane; mitigar com default conservador |
| Teste | Builder suite MITM + lab auto-disable + S8 OFF |
| Rollback | Desactivar feature; voltar a `1.9.46` |

**Porquê não outro primeiro código:**

| Alternativa | Motivo de adiamento |
|-------------|---------------------|
| ECH (S6) completo | Limite já honesto; não é MVP piloto |
| Alargar destinos/CDN | Aumenta blast radius sem GO H2 |
| Agente endpoint | ADR-0029 ADIAR |
| Squid | Rejeitado |
| CE port completo | ADR-0022 — trilho paralelo, não primeiro bloco MITM |

**Pré-condição:** P0–P2 docs PASS (já) **ou** GO explícito “implementar P3 sem activar”.  
**Histórico:** P3 não substituía a ficha. **ADR-0035:** a ficha deixou de existir como gate.

### Critérios de aceite fechados — P3 (failsafe + visibilidade)

P3 só pode marcar **PASS** se **todos** os itens abaixo forem evidência verificável:

| ID | Critério | Prova mínima |
|----|----------|--------------|
| P3.1 | **max_window / auto-disable** configurável (minutos) no produto | Config + comportamento: ao expirar ⇒ `mitm_effective=false` sem intervenção |
| P3.2 | Failsafe **fiável** (não só `at` manual ad-hoc) | Teste automatizado ou lab com relógio/janela curta; OFF limpo |
| P3.3 | Enquanto ON: GUI ou diagnóstico mostra **src, dst, SNI/hosts, quic_mode, tempo restante** | Screenshot/log de diagnóstico (sem segredos) |
| P3.4 | **Auditoria de metadados** da activação (quem/quando/escopo) — **zero** payload TLS | Evento/registo inspeccionável |
| P3.5 | Suite builder MITM regress **PASS** | `test_mitm_config` / `test_mitm_regress` (+ timeout harness se tocado) |
| P3.6 | **S8**: MITM OFF após timeout ≡ sem rdr/anti-QUIC/`:8443` órfãos | Smoke OFF pós-auto-disable |
| P3.7 | Docs do bloco: objectivo/impacto/risco/teste/rollback + PORTVERSION se release | Commit no mesmo bloco técnico |
| P3.8 | **Não** alarga rdr para `from any` nem dest aberto | Grep/regra PF na evidência |

Fora de P3 (continua gate activação, não eng.): ficha cliente/responsáveis/src/dst/SNI/janela/saída.

---

## Conflitos documentais encontrados e resolução

| Conflito | Documentos | Resolução (este bloco) |
|----------|------------|------------------------|
| “runtime produto **AUSENTE**” vs `1.9.46` no `.pkg` | plano header; R-T histórico; mapa M-07 tom antigo | Marcar como **histórico pré-20.10**; SSOT actual = runtime presente, default OFF, effective gated |
| ADR-0026 “runtime diferido / effective sempre false sem runtime” sem rev. pós-Gate C | ADR-0026 | Rev. **n**: runtime shipped; Gate C + teste controlado PASS; permanente/piloto NO-GO até mapa+GO |
| Backlog checkpoint “próximo fecho IM2 / GO produção” vs permanente NO-GO | `backlog.md` | Apontar a este mapa; próximo = P0–P5 piloto, não permanente |
| “PRONTO PARA ENFORCE” (CORTEX produto base) ≠ pronto MITM piloto | CORTEX checkpoint | Manter enforce base; MITM piloto **NO-GO** explícito |
| GI2/GI3 PASS vs ADR texto “GI2–GI3 DEFERRED” | ADR consequências antigas | Consequências históricas; estado vivo = GI2/GI3 PASS lab; produção permanente NO-GO |

---

## Veredicto final

```text
MITM motor scoped (1.9.46+) ....... PRONTO (teste 215442Z + soak retry2 PASS)
P1/P2 docs ........................ PASS (D1–D9 = princípios de produto, não ficha)
P3 failsafe ....................... PASS (1.9.47)
P4 soak retry2 .................... CLOSED PASS (224009Z)
P5 / ficha ........................ RETIRADOS (ADR-0035)
Próximo ........................... evoluir MITM/UX sem tecto; sem ligar .254
Ambição ........................... paridade NGFW no tempo (estado actual ≠ tecto)
Default ........................... OFF (este mapa não liga .254)
```

---

## Referências rápidas

| Tema | Doc |
|------|-----|
| Gates B/C | [`gates-obrigatorios-1.9.43-mitm.md`](gates-obrigatorios-1.9.43-mitm.md) |
| Escopo P1 / gate ficha | [`GO-escopo-piloto-mitm-generico.md`](GO-escopo-piloto-mitm-generico.md) |
| Runbook piloto P2 | [`runbook-piloto-mitm-generico.md`](runbook-piloto-mitm-generico.md) |
| Runbook teste ≤15 min | [`runbook-activacao-mitm-producao-1.9.46.md`](runbook-activacao-mitm-producao-1.9.46.md) |
| Evidência Gate C | [`../tests/evidence/20260809T210753Z-phaseBD-d1-254/`](../tests/evidence/20260809T210753Z-phaseBD-d1-254/) |
| Evidência teste | [`../tests/evidence/20260809T215442Z-phaseBD-d1-254/`](../tests/evidence/20260809T215442Z-phaseBD-d1-254/) |
| ADR MITM | [`../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md) |
| BG-087 | [`../02-roadmap/backlog.md`](../02-roadmap/backlog.md) |

### Histórico deste mapa

| Data | Nota |
|------|------|
| 2026-08-09 | Mapa P0 criado — NÃO PRONTO |
| 2026-08-09 | P1+P2 reflectidos — D1–D9 |
| 2026-08-09 | Gate activação externa ≠ lacuna eng.; critérios aceite P3.1–P3.8 fechados |
| 2026-08-09 | **P3 PASS** — `1.9.47` janela/deadline/audit/GUI; suite builder PASS |
| 2026-08-14 | **20.35 PASS** — GUI até desligar; copy operador; publicado `1.9.63` |
| 2026-08-14 | **ADR-0035** — ficha/P5 RETIRADOS; ambição paridade NGFW; próximo 20.35 |
| 2026-08-14 | **P4 soak retry2 CLOSED PASS** — `224009Z`; 16/16 health; rollback_clean=1; MITM OFF `02:54:33Z`; Phase C NA |
| 2026-08-13 | **P4 soak retry2 IN_PROGRESS** — `224009Z`; health_1 tries=1; deadline `2026-08-14T02:40:19Z` |
| 2026-08-13 | **P4.2 PASS** — causa-raiz probe sem `-T`; harness `tests/harness/mitm-p4-soak`; sem activar MITM |
| 2026-08-13 | **P4 retry CLOSED FAIL** — `170000Z` `health_ssh_fail`; MITM OFF `223009Z`; P4.1 live; `latest` `1.9.62` |
| 2026-08-13 | **P4.1 publicado** — `v1.9.59`; supervisor on-box; P4 retry no `.254` |
| 2026-08-13 | **P4.1 local** — supervisor on-box cron; candidato `1.9.59`; sem publish/activar |
