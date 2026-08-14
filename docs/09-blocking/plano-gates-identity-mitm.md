# Gates — Identity + MITM Add-on (GI0–GI9)

**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**Arranque:** [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)  
**Mapa:** [`../01-architecture/identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md)

Cada gate: `PASS` | `FAIL` | `BLOCKED` | `DEFERRED` (só com ADR).  
Evidência: pasta `docs/tests/evidence/<run_id>/` quando houver lab.

---

## GI0 — Governança

| # | Critério | Estado |
|---|----------|--------|
| GI0.1 | START-HERE + plano + mapa + este ficheiro existem | **PASS** (`2026-08-05`) |
| GI0.2 | CORTEX / README / backlog / classification apontam a trilha | **PASS** (`2026-08-05`) |
| GI0.3 | ADR-0025/0026/0027 Aceito (ou emenda registada) + GO T1/T2 | **PASS** (`2026-08-05` — Aceito; **T1**) |
| GI0.4 | ADR-0028 (concorrência/IO daemon) Aceito — o mais tardar no 20.11a, antes de qualquer código IM3 | **PASS** (`2026-08-05` — Aceito no 20.2; baseline perf **PASS** no 20.11a `2026-08-06`) |

**Saída:** agente continua sem ambiguidade comercial/técnica.

---

## GI1 — Entitlements

| # | Critério | Estado |
|---|----------|--------|
| GI1.1 | Parse CSV `features` no daemon + testes | **PASS** (`2026-08-05`, `test_features_parse.c`) |
| GI1.2 | License-server emite `base` / `base,identity` / `base,mitm` / combinações | **PASS** (20.4 `normalizeFeatures` + UI presets) |
| GI1.3 | Sem `mitm`: impossível activar interceptação (daemon) | **PASS** — `layer7_features_allows_mitm` + zero código MITM runtime |
| GI1.4 | Sem `identity`: impossível carregar mapa / políticas AD (daemon) | **PASS** — `layer7_features_allows_identity` + zero mapa Identity runtime |
| GI1.5 | Licença legada conforme T1 — produto base OK | **PASS** (`full`→`base`) |
| GI1.6 | Suite local PASS; defaults OFF | **PASS** (C + license-server npm; smoke FreeBSD pendente no builder) |
| GI1.7 | Contrato parse P1–P6 | **PASS** |
| GI1.8 | Check-in ∩ `.lic` (retirar add-on em runtime) | **PASS** (`layer7_features_intersect` + check-in `features`) |

---

## GI2 — MITM segurança e default — **PASS** (`2026-08-09`, 20.11 / `1.9.41`)

| # | Critério | Estado |
|---|----------|--------|
| GI2.0 | Spike 20.7 registado como GO **ou** DEFER/NO-GO formal | **PASS (DEFER)** — ver `spike-mitm-20.7.md` + ADR-0026; reopen GO `2026-08-08` |
| GI2.1 | `mitm` / helper default OFF | **PASS** — rc `enable:=NO`; listen sem env LAB/PRODUCT recusado |
| GI2.2 | MITM OFF ≡ sem intercept / claim honesto | **PASS** — health `intercept=false`, `mitm_effective_claim=false` |
| GI2.3 | Sem entitlement/gate: zero interceptação | **PASS** — gate ausente ⇒ rc não arranca; `test_mitm_config.php` |
| GI2.4 | CA privada fora do repositório | **PASS** — CA efémera só `.54` + wipe; evidência sem private keys |
| GI2.5 | Bypass list funcional | **PASS** — SNI bypass em lab `.54` |

Evidência: [`../tests/evidence/20260809T060000Z-20.11-gi2-gi3-54/`](../tests/evidence/20260809T060000Z-20.11-gi2-gi3-54/).  
Squid rejeitado. **Sem** intercept em `.254`/`.234`/`.235`. Identity rede **FECHADA**.

---

## GI3 — MITM funcional (lab) — **PASS** (`2026-08-09`, S3 Windows + lab `.54`)

| # | Critério | Estado |
|---|----------|--------|
| GI3.1 | Cliente com CA vê block page HTTPS legível — ADR-0026 **S3:** ≥1 **browser Windows** | **PASS** — Edge 151 em `192.168.100.24` + CA + screenshot (`s3-windows/`) |
| GI3.2 | Cliente sem CA: falha TLS esperada | **PASS** — verify fail (`curl` rc=60) |
| GI3.3 | App com pinning em bypass **ou** limite documentado | **PASS** — bypass + limite honesto (não NGFW) |
| GI3.4 | Smoke IPv4 sem regressão base (rdr selectivo isolado) | **PASS** — loopback produto + Opção A ns |
| GI3.5 | CPU/latência lab anotados | **PASS** — S1/S2 localhost+inline ≤ limiares |

**20.11** = **PASS**. S6 ECH = **NA/limite** (não exercitado). **Sem** GO produção MITM.

---

## GI4 — Mapa Identity (daemon)

| # | Critério | Estado |
|---|----------|--------|
| GI4.0 | Pré-requisito 20.11a cumprido: ADR-0028 Aceito + baseline de perf registada | **PASS** (`2026-08-06`) — evidência `20260806T174000Z-20.11a-baseline-perf` |
| GI4.1 | add/refresh/expire no **daemon** (lista de IPs v4/v6 por user; limites de escala ADR-0027 §4.3) | **PASS** (`2026-08-06` — 20.13 API) |
| GI4.2 | TTL remove stale; não depende de resync PHP como SSOT | **PASS** (`2026-08-06` — expire + load skip expired) |
| GI4.3 | Diagnóstico GUI sem secrets | **PASS** parcial (dump JSON daemon; página GUI = depois) |
| GI4.4 | Sem entitlement `identity`: módulo inerte + **zero threads novas** (ADR-0028 §4) | **PASS** (`2026-08-07` — sem init sem flag) |
| GI4.5 | `SIGHUP` reload: mapa vivo sobrevive; cold start pós-reboot documentado (ADR-0027 §4.2) | **PASS** (`2026-08-07` — sync sem clear; load no cold start) |
| GI4.6 | Perf com módulo ON em lab dentro da tolerância vs baseline 20.11a; nenhum bloqueio do loop de captura observável | **PASS** parcial — OFF = baseline (inerte); ON lab quando existir `.lic` identity |

---

## GI5 — LDAP + pelo menos uma fonte canónica

| # | Critério | Estado (2026-08-07) |
|---|----------|---------------------|
| GI5.1 | LDAPS bind + grupo em lab | **PARCIAL** — bind/Base DN via Test LDAP (20.18); expand grupo em lab residual |
| GI5.2 | Cache + fail-mode ADR-0027 | **PASS** (20.17) |
| GI5.3 | Fonte MVP: **RADIUS accounting receiver** **ou** **agente DC** popula mapa | **PASS** (20.19 unitário; lab NAS físico residual) |
| GI5.4 | Sem passwords/secrets em logs | **PASS** (20.18 — CLI/GUI/estado) |
| GI5.5 | WinRM outbound do appliance **não** usado como caminho canónico | **PASS** (política ADR; sem WinRM) |

---

## GI6 — Segunda fonte + conflitos

| # | Critério | Estado (2026-08-07) |
|---|----------|---------------------|
| GI6.1 | Segunda fonte integrada (a que faltou no GI5) | **PASS código** (20.20 agente Win); lab DC residual |
| GI6.2 | Conflito mesmo IP: política determinada + audit | **PASS código** (20.22); lab residual |
| GI6.3 | Logout/expiry limpa IP | **PARCIAL** (agente/RADIUS remove_ip; gate lab PENDENTE) |
| GI6.4 | Users concorrentes no mesmo IP → estado `multi-user` → `ad_*` não-match + evento `identity_ip_conflict` (nunca política do user errado) — ADR-0027 §4.1 | **PASS código** (20.22); lab residual |
| GI6.5 | Canal agente DC conforme ADR-0027 §2.1: TLS/HMAC, bind só LAN, rate limit provados em lab | Desenho+código **PASS**; lab DC residual |

---

## GI7 — Políticas user/grupo

| # | Critério | Estado |
|---|----------|--------|
| GI7.1 | Política por grupo AD bloqueia só IPs do grupo | **PASS unit** (`test_ad_group_only_members`) |
| GI7.2 | Troca de IP → remap **daemon** → política segue o user | **PASS unit** (`test_ad_user_ip_remap`) |
| GI7.3 | Políticas só IP/MAC antigas inalteradas | **PASS unit** (não-regressão `test_policy_decide`) |
| GI7.4 | Precedência R-M: `ad_*` na mesma lista first-match; caso ad_group pri alta vs IP pri baixa documentado em `core/precedence.md` + `test_ad_priority_beats_static_ip` | **PASS unit** (20.25) |
| GI7.5 | LDAP down / TTL → fail-mode (não-match `ad_*`; base intacta) | **PASS unit** (`test_ad_after_expire_no_match`; lab LDAP residual) |

---

## GI8 — Agente / TS (ou exclusão)

| # | Critério | Estado |
|---|----------|--------|
| GI8.1 | Agente MVP **ou** ADR de adiamento com limite honesto na GUI | **PASS ADIAR** (`ADR-0029`) |
| GI8.2 | TS/VDI MVP **ou** ADR de exclusão (“não suportado multi-user mesmo IP”) | **PASS exclusão** (`ADR-0029`) |

---

## GI9 — Fecho de trilha / release add-on

| # | Critério |
|---|----------|
| GI9.1 | Evidências run_id indexadas | **PASS** (`20260808T135500Z-im9-20.31-identity-mesh` + `20260808T174100Z-im9-20.33-homolog-1.9.29`) |
| GI9.2 | MANUAL-INSTALL / USO-LICENÇAS / CHANGELOG / CORTEX | **PASS** (20.32) |
| GI9.3 | GO humano para publicar candidata add-on | **PASS** (2026-08-08 — homologação real two-client autorizada; Veeam) |
| GI9.4 | Default OFF em upgrades a partir de `1.9.8` | **PASS** (mesh + pós-restore enabled=0) |
| GI9.5 | START-HERE actualizado; plano pode fechar | **PASS** (20.33) |

---

## Matriz resumo

| Gate | Onda | Estado |
|------|------|--------|
| GI0 | IM0 | **PASS** (`2026-08-05` — ADRs Aceito; T1) |
| GI1 | IM1 | **PASS** (`2026-08-05`) |
| GI2 | IM2 | **PASS** (`2026-08-09` — 20.11 / `1.9.41` lab `.54`) |
| GI3 | IM2 | **PASS** (`2026-08-09` — S3 Edge Windows + lab `.54` / `1.9.41`) |
| GI4 | IM3 | **PASS** (`2026-08-07` — GI4.6 ON lab residual) |
| GI5 | IM4–IM5 | **PARCIAL** (GI5.3 PASS 20.19; GI5.4 PASS; GI5.1 lab residual) |
| GI6 | IM5 | **PARCIAL** (código 20.20 PASS; lab DC residual) |
| GI7 | IM6 | **PASS unitário** (20.26; lab AD/LDAP residual — checklist `tests/lab/run-gi7-identity-policies.sh`) |
| GI8 | IM7–IM8 | **PASS** (`ADR-0029`, 2026-08-08) |
| GI9 | IM9 | **PASS** (20.33; residuais AD lab GI5–GI7 assinados) |

**20.37:** fila **FECHADA**. GI5/GI6 PARCIAL permanecem residual AD assinado — **não** reabrem a trilha.

---

## Histórico

| Data | Evento |
|------|--------|
| 2026-08-09 | **20.11 / GI3 PASS** — S3 Edge Windows `192.168.100.24` + screenshot; S6 permanece **NA/limite**; sem GO produção |
| 2026-08-09 | **Rev. gerencial 20.11** — overclaim S3/S6 corrigido (interino): GI2 PASS; GI3 PENDENTE S3; S6 NA; supersedido pelo PASS S3 Windows |
| 2026-08-09 | **20.11 corrida lab** — `1.9.41` em `.54` (commit docs `8939ddb` classificou PASS de forma incorrecta em S3/S6; supersedido pela rev. gerencial) |
| 2026-08-08 | **20.9 PASS** — intenção/`mitm_effective`/bypass/`quic_mode`/IPC; GI2/GI3 **runtime** mantêm-se DEFERRED; 20.10 bloqueado até S1–S8+GO lab |
| 2026-08-09 | **GO lab** PoC-0 idle; S5+S7+S8 PASS; GI2/GI3 DEFERRED; 20.10 exige S1–S4/S6 + GO produto |
| 2026-08-08 | **Reopen MITM GO** — passo **20.8** scaffolding; GI2/GI3 **runtime** mantêm-se DEFERRED; desenho `layer7-tlsproxy`; Squid rejeitado |
| 2026-08-05 | Criação GI0–GI9 |
| 2026-08-05 | rev. `b` — GI2 spike/DEFER; GI4 daemon; GI5 fontes canónicas; GI7 fail-mode |
| 2026-08-05 | rev. `c` — GI0.4 (ADR-0028); GI1.7–GI1.8 (contrato parse + check-in); GI4.0/4.5/4.6 (baseline perf, threads, reload); GI6.4–GI6.5 (multi-user, canal agente DC); GI7.4 reconciliado |
| 2026-08-07 | **20.18** — Test LDAP GUI + `layer7d --ldap-test`; GI5.4 PASS (parcial GI5); passo → 20.19 |
| 2026-08-08 | **20.33 / GI9 PASS** — homolog two-client real `1.9.29` (`20260808T174100Z-im9-20.33-homolog-1.9.29`); trilha Identity rede fechável |
| 2026-08-08 | **20.32 / GI9.2 PASS** — MANUAL Identity + notes comerciais; passo → **20.33** |
| 2026-08-08 | **20.31 / GI9 parcial** — malha Identity OFF + unit local; evidência `20260808T135500Z-im9-20.31-identity-mesh`; passo → **20.32** |
| 2026-08-08 | **20.28–20.30 / GI8 PASS** — ADR-0029 ADIAR IM7 + exclusão IM8; passo → **20.31** |
| 2026-08-08 | **20.27 PASS** — especificação agente endpoint; passo → **20.28** |
| 2026-08-08 | **20.26 / GI7 PASS unitário** — GI7.1–7.5 unit; lab AD residual; passo → **20.27** (IM7) |
| 2026-08-08 | **20.25 PASS** — `core/precedence.md` Identity; GI7.4 unit; GI7 PARCIAL; passo → 20.26 |
| 2026-08-07 | **20.19** — RADIUS accounting receiver; GI5.3 PASS (unitário); candidato `1.9.17`; passo → 20.20 |
| 2026-08-07 | **20.20 desenho** — A1–A7 PASS (TLS+HMAC MVP); ADR-0027 rev. d; passo → 20.20 código |
| 2026-08-08 | **20.20 agente Win** — samples Event Log PASS; GI6 parcial; passo → 20.21 |
| 2026-08-05 | **GI1 PASS** — 20.3–20.6 entitlements + check-in ∩ `.lic` |
| 2026-08-06 | **20.7a DEFER** — GI2/GI3 DEFERRED; ADR-0026 implementação diferida; posicionamento PME; passo → IM3/20.11a |
| 2026-08-06 | **20.11a / GI4.0 PASS** — baseline perf registada; passo → 20.12 |
| 2026-08-06 | **20.13** — GI4.1 PASS; GI4.2/4.3 parciais (expire + dump JSON) |
| 2026-08-06 | **20.14** — GI4.2 PASS; GI4.5 parcial (snap + política SIGHUP) |
| 2026-08-07 | **20.15 / GI4 PASS** — entitlement gate; GI4.4/4.5 PASS |
