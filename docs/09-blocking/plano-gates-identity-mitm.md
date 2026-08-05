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
| GI0.4 | ADR-0028 (concorrência/IO daemon) Aceito — o mais tardar no 20.11a, antes de qualquer código IM3 | **PASS** (`2026-08-05` — Aceito no 20.2; baseline perf permanece no 20.11a) |

**Saída:** agente continua sem ambiguidade comercial/técnica.

---

## GI1 — Entitlements

| # | Critério |
|---|----------|
| GI1.1 | Parse CSV `features` no daemon + testes |
| GI1.2 | License-server emite `base` / `base,identity` / `base,mitm` / combinações |
| GI1.3 | Sem `mitm`: impossível activar interceptação (daemon) |
| GI1.4 | Sem `identity`: impossível carregar mapa / políticas AD (daemon) |
| GI1.5 | Licença legada conforme T1 ou T2 (ADR-0025) — produto base OK |
| GI1.6 | Suite local / builder PASS; defaults OFF |
| GI1.7 | Contrato de parse ADR-0025 P1–P6 provado: truncamento >63B sem overflow, tokens desconhecidos ignorados, erro de parse ⇒ `base` apenas |
| GI1.8 | Precedência check-in vs `.lic` (interseção): retirar `identity` via check-in desliga o módulo em runtime sem reinstalar `.lic` |

---

## GI2 — MITM segurança e default (só se spike GO; senão DEFERRED)

| # | Critério |
|---|----------|
| GI2.0 | Spike 20.7 registado como GO **ou** DEFER/NO-GO formal |
| GI2.1 | (se GO) `mitm.enabled` default `false` |
| GI2.2 | (se GO) MITM OFF ≡ ADR-0017 |
| GI2.3 | Sem entitlement `mitm`: zero interceptação |
| GI2.4 | (se GO) CA privada fora do repositório |
| GI2.5 | (se GO) Bypass list funcional |

Se DEFER: marcar GI2/GI3 `DEFERRED` e avançar Identity.

---

## GI3 — MITM funcional (lab)

| # | Critério |
|---|----------|
| GI3.1 | Cliente com CA instalada vê página/bloqueio HTTPS legível em destino de teste |
| GI3.2 | Cliente **sem** CA: falha TLS esperada (documentada) |
| GI3.3 | App com pinning em bypass **ou** limite documentado |
| GI3.4 | Smoke IPv4 (+ IPv6 se no escopo do passo) sem regressão base |
| GI3.5 | CPU/latência lab anotados (não GO produção cego) |

---

## GI4 — Mapa Identity (daemon)

| # | Critério |
|---|----------|
| GI4.0 | Pré-requisito 20.11a cumprido: ADR-0028 Aceito + baseline de perf `1.9.8` registada |
| GI4.1 | add/refresh/expire no **daemon** (lista de IPs v4/v6 por user; limites de escala ADR-0027 §4.3) |
| GI4.2 | TTL remove stale; não depende de resync PHP como SSOT |
| GI4.3 | Diagnóstico GUI sem secrets |
| GI4.4 | Sem entitlement `identity`: módulo inerte + **zero threads novas** (ADR-0028 §4) |
| GI4.5 | `SIGHUP` reload: mapa vivo sobrevive; cold start pós-reboot documentado (ADR-0027 §4.2) |
| GI4.6 | Perf com módulo ON em lab dentro da tolerância vs baseline 20.11a; nenhum bloqueio do loop de captura observável |

---

## GI5 — LDAP + pelo menos uma fonte canónica

| # | Critério |
|---|----------|
| GI5.1 | LDAPS bind + grupo em lab |
| GI5.2 | Cache + fail-mode ADR-0027 |
| GI5.3 | Fonte MVP: **RADIUS accounting receiver** **ou** **agente DC** popula mapa |
| GI5.4 | Sem passwords/secrets em logs |
| GI5.5 | WinRM outbound do appliance **não** usado como caminho canónico |

---

## GI6 — Segunda fonte + conflitos

| # | Critério |
|---|----------|
| GI6.1 | Segunda fonte integrada (a que faltou no GI5) |
| GI6.2 | Conflito mesmo IP: política determinada + audit |
| GI6.3 | Logout/expiry limpa IP |
| GI6.4 | Users concorrentes no mesmo IP → estado `multi-user` → `ad_*` não-match + evento `identity_ip_conflict` (nunca política do user errado) — ADR-0027 §4.1 |
| GI6.5 | Canal agente DC conforme ADR-0027 §2.1: TLS mútuo/HMAC, bind só LAN, rate limit provados em lab |

---

## GI7 — Políticas user/grupo

| # | Critério |
|---|----------|
| GI7.1 | Política por grupo AD bloqueia só IPs do grupo |
| GI7.2 | Troca de IP → remap **daemon** → política segue o user |
| GI7.3 | Políticas só IP/MAC antigas inalteradas |
| GI7.4 | Precedência reconciliada (R-M): `ad_*` na mesma lista first-match por `priority`; caso “ad_group priority alta vs IP priority baixa” testado e documentado em `core/precedence.md` |
| GI7.5 | LDAP down → fail-mode (não-match `ad_*`; base intacta) |

---

## GI8 — Agente / TS (ou exclusão)

| # | Critério |
|---|----------|
| GI8.1 | Agente MVP **ou** ADR de adiamento com limite honesto na GUI |
| GI8.2 | TS/VDI MVP **ou** ADR de exclusão (“não suportado multi-user mesmo IP”) |

---

## GI9 — Fecho de trilha / release add-on

| # | Critério |
|---|----------|
| GI9.1 | Evidências run_id indexadas |
| GI9.2 | MANUAL-INSTALL / USO-LICENÇAS / CHANGELOG / CORTEX |
| GI9.3 | GO humano para publicar candidata add-on |
| GI9.4 | Default OFF em upgrades a partir de `1.9.8` |
| GI9.5 | START-HERE actualizado; plano pode fechar |

---

## Matriz resumo

| Gate | Onda | Estado |
|------|------|--------|
| GI0 | IM0 | **PASS** (`2026-08-05` — ADRs Aceito; T1) |
| GI1 | IM1 | PENDENTE |
| GI2 | IM2 | PENDENTE |
| GI3 | IM2 | PENDENTE |
| GI4 | IM3 | PENDENTE |
| GI5 | IM4–IM5 | PENDENTE |
| GI6 | IM5 | PENDENTE |
| GI7 | IM6 | PENDENTE |
| GI8 | IM7–IM8 | PENDENTE |
| GI9 | IM9 | PENDENTE |

---

## Histórico

| Data | Evento |
|------|--------|
| 2026-08-05 | Criação GI0–GI9 |
| 2026-08-05 | rev. `b` — GI2 spike/DEFER; GI4 daemon; GI5 fontes canónicas; GI7 fail-mode |
| 2026-08-05 | rev. `c` — GI0.4 (ADR-0028); GI1.7–GI1.8 (contrato parse + check-in); GI4.0/4.5/4.6 (baseline perf, threads, reload); GI6.4–GI6.5 (multi-user, canal agente DC); GI7.4 reconciliado |
| 2026-08-05 | **GI0 PASS** — 20.2: ADRs 0025–0028 Aceito; transição legado T1 |
