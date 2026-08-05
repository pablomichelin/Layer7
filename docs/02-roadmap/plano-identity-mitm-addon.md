# Plano — Identity + MITM Add-on (trilha IM0–IM9)

**Estado do plano:** `ABERTO` (rev. `2026-08-05c` — contratos técnicos fechados; **código ainda não iniciado**)  
**Tipo:** novo plano pós-fecho (ESTADO-PRODUTO §6); **não** reabre P0–J nem IPv6  
**SSOT de execução:** este ficheiro  
**Arranque de chat (único desta trilha):** [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)  
**SSOT de estado vivo do produto:** [`../../CORTEX.md`](../../CORTEX.md)  
**Mapa técnico:** [`../01-architecture/identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md)  
**Gates:** [`../09-blocking/plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md)  
**ADRs:** [0025](../03-adr/ADR-0025-entitlements-addon-identity-mitm.md) · [0026](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md) · [0027](../03-adr/ADR-0027-identity-userid-multi-fonte.md) · [0028](../03-adr/ADR-0028-concorrencia-io-daemon-identity.md)  
**Baseline produção:** `1.9.8` — rollback enforce `1.9.0`  
**Commits:** `trilha-identity-mitm/20.x: …`  
**Nota:** rev. `b` incorpora reparos da revisão arquitectónica (spike MITM, mapa Identity no daemon, fontes canónicas, fail-mode, precedência). Rev. `c` fecha os **contratos técnicos** que faltavam para iniciar código sem ambiguidade: concorrência do daemon (ADR-0028), contrato `features` (buffer 64B, fail-closed do gate), precedência check-in vs `.lic`, reconciliação §3.1 com o modelo first-match, critérios mensuráveis do spike MITM, canal do agente DC, conflito NAT multi-user, cold start e limites de escala.

---

## 0. Estado e progresso

| Campo | Valor |
|-------|-------|
| Passo actual | **20.2** (IM0 — aceitar ADRs 0025/0026/0027/0028 + GO T1/T2) |
| Código | Não iniciado |
| ADRs | Proposto ×4 (aceitação = passo 20.2; ADR-0028 pode aceitar-se até 20.11a) |
| Próximo após IM0 | IM1 — contrato `features` / entitlement |

```text
TRILHA IDENTITY + MITM — progresso
- Passo actual: 20.2 / IM0
- IM0: 20.1 PASS (docs); rev.b PASS; rev.c contratos técnicos PASS; 20.2 PENDENTE (ADRs Aceito + T1/T2)
- IM1–IM9: PENDENTE — código NÃO iniciado
- Baseline: 1.9.8
```

### 0.0 Correcções arquitectónicas obrigatórias (rev. `b`)

Estas regras **substituem** interpretações ingénuas da rev. `a`:

| # | Tema | Decisão canónica |
|---|------|------------------|
| R-A | MITM ≠ dependência de Identity | MITM e Identity são **ortogonais**. Preferência de produto “MITM cedo” **não** bloqueia Identity se o spike falhar. |
| R-B | Spike MITM GO/NO-GO | Passo **20.7** é **spike** (desenho + PoC mínima). **NO-GO** ou custo inaceitável → MITM **DEFER** (ADR-0026) **sem** bloquear IM3–IM6. |
| R-C | Mapa Identity no **daemon** | SSOT de sessão user↔IP vive no daemon (refresh contínuo → PF/tabelas). **Não** copiar o padrão PHP `device_ips` (resync lento demais para User-ID). |
| R-D | Eventos AD canónicos | MVP = **agente leve no Domain Controller** → push seguro para o appliance. WinRM/WMI a partir do FreeBSD = **não** canónico. |
| R-E | RADIUS canónico | MVP = Layer7 como **accounting receiver** (UDP, secret, ACL de NAS). Não confundir com captive portal. |
| R-F | Fail-mode `ad_*` | Definido em ADR-0027: cache TTL + comportamento explícito se LDAP/fonte cair (não “fail silencioso”). |
| R-G | Precedência cedo | Esboço de precedência **antes** de IM6 (secção 3.1 deste plano); formalizar em `core/precedence.md` no passo 20.25. |
| R-H | Exactidão do MVP | Sem agente endpoint (IM7) / TS (IM8), o controlo é **User-ID de rede** (classe NGFW sem GlobalProtect) — limites honestos na GUI. |
| R-I | IPv6 privacy | Utilizador pode ter vários IPv6 temporários; mapa aceita N endereços/user; TTL agressivo. |
| R-J | CE vs lab Plus | Gates Identity/MITM em produção CE exigem prova CE (ADR-0022) — não inferir só de lab Plus. |

### 0.0.1 Contratos técnicos adicionais (rev. `c`)

| # | Tema | Decisão canónica |
|---|------|------------------|
| R-K | Concorrência do daemon | `layer7d` é loop único signal-driven; **nenhuma chamada bloqueante Identity no hot path**. Fontes (LDAP, RADIUS, agente DC) em threads próprias; mapa com rwlock. **ADR-0028** é pré-requisito de IM3–IM5. |
| R-L | Contrato `features` | Buffer real é `char features[64]` (`license.h`). CSV ≤ 63 bytes; normalização; tokens desconhecidos ignorados; **erro de parse ⇒ `base` apenas** (fail-closed no add-on, fail-open no base). Detalhe: ADR-0025 P1–P6. |
| R-M | Precedência reconciliada | Políticas `ad_*` entram na **mesma lista first-match por `priority`** do `precedence.md` V1 (resolvidas para IPs do mapa no momento do match) — **não** são camada separada. Ver §3.1 revisto. |
| R-N | Check-in vs `.lic` | Entitlements efectivos = **interseção** `.lic` ∩ check-in (check-in só retira, nunca acrescenta). Decidido em ADR-0025; testado em GI1. |
| R-O | Baseline de perf | Registar CPU/throughput/latência da `1.9.8` **antes** do primeiro código IM3 (passo 20.11a). “Equivalente à baseline” passa a ser mensurável. |
| R-P | Spike MITM mensurável | GO exige S1–S8 do ADR-0026 (CPU ≤ +25%, latência p95 ≤ 150 ms, privacidade, OFF ≡ ADR-0017). Sem veredicto subjectivo. |
| R-Q | Canal agente DC | Receiver no appliance = superfície nova: TLS mútuo/HMAC, bind só LAN, rate limit, privilégio mínimo no DC (ADR-0027 §2.1) — desenho fecha **antes** de código no 20.20. |

### 0.1 Relação com planos fechados

| Plano | Estado | Relação |
|-------|--------|---------|
| Fecho P0–J | **FECHADO** | Baseline comercial; não reabrir |
| IPv6 V0–V6 | **FECHADA** | Dual-stack já no produto; Identity/MITM devem **respeitar** IPv4+IPv6 |
| Este plano | **ABERTO** | Extensão comercial e técnica nova |

Congelamento das filas fechadas:
[`../00-overview/ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](../00-overview/ESTADO-PRODUTO-E-PLANOS-FECHADOS.md).

### 0.2 Motivação (decisão de produto `2026-08-05`, emenda rev. `b`)

1. Clientes em domínio AD precisam de políticas por **utilizador/grupo**, não só IP/MAC/dispositivo.
2. Controlo “exacto” no mercado NGFW = política por identidade + mapa **dinâmico** user→IP (não IP estático eterno).
3. Add-on comercial: **licença X (base)** vs **licença Y (base + add-on)**.
4. **Ressalva MITM:** opção de inspeção TLS com CA no SKU e no plano; **não** é dependência técnica de Identity. Calendário preferido “MITM cedo” **cede** a spike NO-GO — Identity MVP (IM3–IM6) avança.
5. Captive portal do pfSense: **fora** — já existe nativo.
6. MVP Identity **sem** agente endpoint = User-ID de rede (limites honestos); exactidão tipo agente = IM7+.

### 0.3 Definição de “completo” nesta trilha (critérios I–M)

| # | Critério | Prova |
|---|----------|-------|
| C1 | Entitlements no `.lic` com gate real (daemon + GUI) | GI1 |
| C2 | Compat: `features=full` e licenças antigas não partem o produto base | GI1 |
| C3 | MITM: spike 20.7 GO **ou** DEFER formal; se GO — opt-in OFF + lab GI2–GI3 | GI2–GI3 ou ADR defer |
| C4 | Sem entitlement MITM: zero interceptação TLS (mesmo com código presente) | GI2 |
| C5 | Mapa user→IP **no daemon** (TTL, refresh, logout/stale; N IPs/user) | GI4 |
| C6 | LDAP/LDAPS para grupos | GI5 |
| C7 | Fonte eventos AD (agente DC) **e/ou** RADIUS accounting receiver (MVP ≥1) | GI5–GI6 |
| C8 | Políticas Layer7 por user/grupo → IPs do mapa daemon (não só resync PHP) | GI7 |
| C9 | Agente endpoint e TS/VDI desenhados; implementação faseada | GI8 (pode ADIAR com ADR) |
| C10 | Não-regressão: smoke base `1.9.8` PASS com add-on OFF | GI0 + cada GI |
| C11 | Docs + MANUAL + changelog + CORTEX alinhados em cada release | GI9 |

**Fora de C1–C11 (não bloqueiam fecho mínimo se ADR excluir):**

- Captive portal Layer7
- MITM de **todo** o tráfego internet sem excepções (sempre allowlist/bypass)
- Controlo “sem nenhum vínculo de rede” (impossível no L3/L4)
- Console multi-firewall / SIEM pesado

---

## 1. Princípios invioláveis

1. **Plano manda; agente obedece.** Um passo `20.x` por bloco de entrega.
2. **Não-regressão absoluta do comportamento base.** Com add-on OFF / sem entitlement novo, o appliance comporta-se como `1.9.8` (monitor/enforce, políticas host/app/CIDR/MAC, UT1, DNS, block page HTTP, IPv6 no âmbito fechado).
3. **Opt-in.** MITM e Identity default **OFF**. Upgrade de pacote **não** activa nada novo sozinho.
4. **Um pacote.** Add-on = módulo no `pfSense-pkg-layer7` + entitlement; **não** segundo `.pkg` nesta trilha.
5. **Daemon é autoridade do gate.** GUI pode esconder/upsell; bypass de UI não activa MITM/Identity.
6. **Segredos fora do git.** CA privada, bind AD, passwords RADIUS — só no appliance / vault humano.
7. **Ordem segura:** IM0 → IM1 **sempre primeiro**. Depois: **caminho de valor** IM3–IM6 (Identity) pode avançar se IM2 (MITM) estiver DEFER após spike. Não activar MITM sem entitlement + bypass + GO spike.
8. **IPv4 e IPv6.** Mapas e enforcement Identity dual-stack; IPv6 privacy (vários IPs/user).
9. **Honestidade.** Limites (ECH, QUIC, NAT, VDI sem TS, stale, topologia IP≠AD) na GUI e docs.
10. **Rollback.** Toggle OFF / `.lic` sem feature / reinstalar baseline.
11. **Captive portal fora de escopo.** Não reimplementar o do pfSense.
12. **Documentação no mesmo bloco** quando houver execução/release.
13. **Mapa Identity no daemon** (R-C). Pacote configura; não é SSOT de sessão.
14. **Spike MITM não é opcional** antes de investir IM2 completa (R-B).

---

## 2. Modelo comercial (resumo; detalhe ADR-0025)

| SKU | Entitlements (exemplo canónico) | Inclui |
|-----|----------------------------------|--------|
| **Standard (X)** | `base` | Produto actual (`1.9.8` capabilities) |
| **Identity Add-on (Y)** | `base,identity` | + User-ID multi-fonte + políticas user/grupo |
| **Identity + MITM (Y+)** | `base,identity,mitm` | + inspeção TLS opt-in |
| **MITM only (opcional)** | `base,mitm` | MITM sem Identity (se venda fizer sentido) |
| **Legado** | `full` ou vazio parseado como hoje | **Compat:** equivale a `base` + todas features **já** existentes; **não** auto-concede `identity`/`mitm` após ADR-0025 Aceito (ver regra de transição no ADR) |

**Regra de transição (obrigatória no ADR-0025):**  
Na aceitação do ADR, decidir explicitamente:

- **Opção T1 (recomendada):** `full` → `base` apenas; Identity/MITM exigem reemissão com flags novas (mais seguro comercialmente).  
- **Opção T2:** `full` → `base,identity,mitm` (mais generoso a clientes antigos; menos upsell).

**GO humano obrigatório** na escolha T1/T2 no passo 20.2.

Preços X/Y são decisão comercial externa ao repo; o plano só materializa o **gate técnico**.

---

## 3. Arquitectura alvo (visão)

```text
                    ┌─────────────────────────────┐
                    │  License .lic (features)    │
                    │  base | identity | mitm      │
                    └─────────────┬───────────────┘
                                  │ gate
          ┌───────────────────────┼───────────────────────┐
          ▼                       ▼                       ▼
   ┌─────────────┐      ┌──────────────────┐     ┌─────────────────┐
   │ Produto base│      │ Identity engine  │     │ MITM / TLS proxy│
   │ (inalterado │      │ user↔IP map      │     │ CA + intercept  │
   │  se OFF)    │      │ LDAP + AD events │     │ opt-in          │
   │             │      │ + RADIUS (+agent)│     │                 │
   └──────┬──────┘      └────────┬─────────┘     └────────┬────────┘
          │                      │                        │
          │              resolve groups→IPs               │
          │                      │                        │
          └──────────────► Policy engine ◄────────────────┘
                           allow/block/monitor
                           (src = IP actual do user)
                                  │
                                  ▼
                           PF / DNS / logs
                           (como hoje + extensões)
```

**Enforcement continua por IP (e tabelas PF).** A novidade é a **origem do conjunto de IPs** (user/grupo via mapa **daemon**) e, com MITM (se GO), a **visibilidade HTTPS**.

Analogia pedagógica: ADR-0012 MAC→IP. **Diferença crítica:** Identity **não** usa o padrão PHP `device_ips` no resync como SSOT — o daemon mantém o mapa vivo.

### 3.1 Precedência (rev. `c` — reconciliada com `core/precedence.md`; formalizar em 20.25)

**Decisão estrutural (R-M):** o motor V1 é **first-match por `priority`
decrescente** (`docs/core/precedence.md`) e isso **não muda**. Políticas
`ad_user`/`ad_group` **não** formam uma camada separada: entram na mesma
lista ordenada, e no momento do match o alvo `ad_*` **resolve para o
conjunto de IPs actual do mapa daemon**. Consequência prática: uma política
`ad_group` com `priority` alta pode vencer uma política IP com `priority`
baixa — comportamento coerente com o motor actual e previsível para o
operador.

Regras complementares:

1. Excepções / VIP / allowlist nativas (ADR-0016/0019/0020) continuam a ser
   avaliadas **antes** da matriz principal — **inalteradas**.
2. Política `ad_*` cujo user **não está no mapa** (ou IP em estado
   `multi-user`, ADR-0027 §4.1) = **não-match** → first-match continua para
   as políticas seguintes (as políticas IP/MAC/CIDR base aplicam-se como em
   `1.9.8`).
3. Conflito de sessão no mesmo IP: `last-writer + audit` para troca normal;
   users concorrentes → estado `multi-user` = não-match `ad_*` (fallback
   seguro, ADR-0027 §4.1).
4. LDAP/fonte indisponível: **fail-mode** ADR-0027 §4 (cache até TTL; depois
   `ad_*` → não-match; base intacta; fail-closed total da LAN proibido).
5. MITM (se ON): aplica-se ao **caminho TLS** seleccionado; não altera a
   precedência allow/block — só visibilidade/UX HTTPS.
6. Empates e default implícito: exactamente como `precedence.md` V1
   (id lexicográfico; enforce default allow).

---

## 4. Ondas IM0–IM9 e passos 20.x

### IM0 — Governança (sem código de produto)

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.1** | START-HERE + este plano + mapa + gates + índices CORTEX/README/backlog | GI0 parcial |
| **20.2** | Aceitar ADR-0025/0026/0027/0028 (ou emendar) + GO T1/T2 | GI0 |

**Saída IM0:** agente novo continua só com START-HERE; ADRs Aceito ou explicitamente adiados.

---

### IM1 — Entitlements / add-on (fundação; sem MITM ainda)

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.3** | Parse `features` no daemon conforme **contrato ADR-0025 P1–P6** (≤63 bytes, normalização, tokens desconhecidos ignorados, erro ⇒ `base`) + testes C incl. truncamento/overflow | — |
| **20.4** | License-server: emissão SKU / UI admin features + **validação de tamanho na emissão (P1)** | — |
| **20.5** | GUI: secções Identity/MITM **bloqueadas** sem entitlement (upsell texto) | — |
| **20.6** | Compat licenças existentes + **precedência check-in vs `.lic` (R-N: interseção)** + suite não-regressão | **GI1** |

**Não-regressão:** sem flags novas, zero mudança de enforcement.

**Rollback:** reinstalar pacote anterior; `.lic` antigo continua válido para base.

---

### IM2 — MITM TLS opt-in — **spike primeiro**; não bloqueia Identity

MITM no stack actual (pcap+nDPI+PF) é **quase um segundo produto** (terminação TLS). Por isso:

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.7** | **SPIKE GO/NO-GO:** desenho em pfSense (proxy/sslbump/outro) + PoC mínima + veredicto pelos **critérios mensuráveis S1–S8 (ADR-0026)** — CPU, latência, QUIC/ECH, privacidade, OFF ≡ ADR-0017 | Decisão registada |
| **20.7a** | Se **NO-GO** / defer: emenda ADR-0026 “implementação diferida”; **saltar** 20.8–20.11; avançar IM3+ | DEFER formal |
| **20.8** | (só se GO) Gestão CA: gerar/importar, export trust, rotação, storage seguro | — |
| **20.9** | (só se GO) Toggle `mitm.enabled` OFF + entitlement; bypass IP/CIDR/SNI/cat | — |
| **20.10** | (só se GO) Intercept selectivo; block page HTTPS legível quando MITM ON | — |
| **20.11** | (só se GO) Lab CA em cliente; smoke; MITM OFF ≡ ADR-0017 | **GI2–GI3** |

**Relação com ADR-0017:** MITM OFF → ADR-0017 intacto. MITM ON só após GO spike + entitlement.

**Riscos críticos IM2:** performance; pinning; GPO da CA; QUIC/ECH; privacidade de conteúdo desencriptado; superfície de ataque no `.pkg` mesmo OFF; lab Plus ≠ CE.

**Rollback:** toggle OFF / remover entitlement / defer sem código MITM em releases se NO-GO cedo.

---

### IM3 — Núcleo Identity (mapa user↔IP **no daemon**)

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.11a** | **Pré-requisito de código:** ADR-0028 **Aceito** (modelo de concorrência/IO) + **baseline de perf `1.9.8` registada** (CPU/throughput/latência, lab ou builder) | Sem isto, 20.12 não abre |
| **20.12** | Estruturas no daemon: sessão (user, IPs v4/v6 **lista**, source, seen_at, ttl, groups cache); **limites de escala ADR-0027 §4.3**; rwlock conforme ADR-0028 | — |
| **20.13** | API interna: add/refresh/expire; export para enforce/PF; dump diagnóstico GUI | — |
| **20.14** | Persistência best-effort opcional + política stale + **cold start/SIGHUP conforme ADR-0027 §4.2**; **não** depender de resync PHP como SSOT | — |
| **20.15** | Entitlement `identity` gate; módulo inerte sem ele (**zero threads novas com OFF**, ADR-0028 §4) | **GI4** |

---

### IM4 — LDAP/LDAPS (directório)

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.16** | Config GUI: servidor, porto, TLS, bind DN, base DN, filtros; limites de escala (depth grupos, máx. members) | — |
| **20.17** | Resolve grupos → membros; cache TTL; fail-mode ADR-0027 | — |
| **20.18** | Test connection na GUI; logs sem passwords | **GI5** (parcial LDAP) |

---

### IM5 — Fontes de sessão (NGFW-like)

**Fontes canónicas MVP (rev. `b`):**

| Passo | Fonte | Arquitectura canónica |
|-------|-------|------------------------|
| **20.19** | **RADIUS accounting receiver** | Daemon/pacote escuta accounting (User-Name + Framed-IP-Address / v6); secret + ACL NAS |
| **20.20** | **Eventos de logon AD** | **Agente no DC** faz push autenticado para o appliance (não WinRM outbound do FreeBSD). **Desenho de segurança do canal (ADR-0027 §2.1 A1–A7) fecha antes de código**: mTLS/HMAC, bind só LAN, rate limit, privilégio mínimo |

MVP fecho parcial: LDAP + **pelo menos uma** fonte. Ambas no plano completo.

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.21** | Normalização: fontes → mesmo mapa daemon | **GI5–GI6** |
| **20.22** | Conflito mesmo IP: last-writer + audit **+ estado `multi-user` → não-match `ad_*` (ADR-0027 §4.1)**; nota topologia (IP AD ≠ IP visto no firewall) | — |

**Explicitamente fora:** captive portal Layer7; WinRM/WMI canónico a partir do pfSense.

---

### IM6 — Políticas por utilizador / grupo

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.23** | Grupo/política aceita `ad_users` / `ad_groups` (além de hosts/MAC) | — |
| **20.24** | Match/enforce usa IPs do **mapa daemon** (não SSOT PHP tipo `device_ips`) | — |
| **20.25** | Precedência: promover esboço §3.1 → `docs/core/precedence.md` | — |
| **20.26** | Lab: user A vs B; troca de IP → remap daemon; LDAP down → fail-mode | **GI7** |

**Não-regressão:** políticas só IP/MAC existentes inalteradas.

---

### IM7 — Agente endpoint (fase posterior, no plano)

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.27** | Especificação agente (OS, canal, auth, heartbeat) | Doc |
| **20.28** | MVP agente Windows (report user + IP) **ou** ADIAR com ADR | GI8 ou exclusão |

---

### IM8 — Terminal Server / VDI (fase posterior)

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.29** | Desenho TS agent (user→porta) | Doc |
| **20.30** | Implementação **ou** limite honesto “não suportado multi-user mesmo IP” | GI8 ou exclusão |

---

### IM9 — Fecho documental, malha, release

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.31** | Malha lab Identity+MITM + evidências `run_id` | GI9 parcial |
| **20.32** | MANUAL-INSTALL + MANUAL-USO-LICENCAS + changelog + commercial notes | — |
| **20.33** | Release candidata + GO produção add-on (pode ser prerelease) | **GI9** |

---

## 5. Ordem de implementação (resumo visual)

**Caminho preferido de calendário (se spike MITM = GO):**

```text
IM0 → IM1 Entitlements
  → IM2 spike 20.7
       ├─ GO  → 20.8–20.11 MITM completo
       └─ NO-GO/DEFER → saltar MITM implementação
  → IM3–IM6 Identity MVP (mapa daemon + LDAP + fontes + políticas)
  → IM7–IM8 agente/TS (adiável)
  → IM9 Fecho
```

**Caminho de valor (recomendado se spike incerto / NO-GO):**

```text
IM0 → IM1 → IM3 → IM4 → IM5 → IM6 → (IM2 quando/se GO) → IM7–IM9
```

**Proibido:** IM6 sem IM3 + IM1.  
**Proibido:** MITM activo sem entitlement, bypass e **GO do spike 20.7**.  
**Proibido:** tratar `device_ips` PHP como SSOT de Identity.

---

## 6. Não-regressão — checklist permanente

Antes de fechar **qualquer** passo com código:

- [ ] `tests/run-local.sh` (ou equivalente builder) PASS  
- [ ] Com `identity`/`mitm` OFF: smoke monitor + enforce mínimo (lab ou builder) equivalente à baseline  
- [ ] Perf vs baseline registada (20.11a): CPU/latência do daemon dentro de **±10%** com módulos OFF; desvio maior = bloqueio até explicação  
- [ ] Com `identity` OFF: **zero threads novas** no daemon (ADR-0028 §4)  
- [ ] Nenhuma mudança de defaults em `layer7.json` que active módulos novos  
- [ ] `features=full` legado: comportamento base OK (conforme T1/T2)  
- [ ] IPv6: não reintroduzir FP-010 / REV-018  
- [ ] Block page ADR-0017 intacta com MITM OFF  
- [ ] Documentação do passo actualizada  

Detalhe por superfície: mapa de rastreabilidade §0–§2.

---

## 7. Riscos e mitigações

| ID | Risco | Severidade | Mitigação |
|----|-------|------------|-----------|
| R1 | Quebra enforcement base | Crítica | Opt-in; gates; feature flags; testes |
| R2 | MITM inviável / CPU / segundo produto | Alta | Spike 20.7 GO/NO-GO; DEFER sem bloquear Identity |
| R3 | Pinning / apps quebradas | Alta | Bypass list; docs; default OFF |
| R4 | Mapa stale / padrão device_ips lento | Alta | Mapa no daemon; TTL; refresh; logout |
| R5 | Credenciais AD no disco | Alta | Permissões; não logar secrets; LDAPS |
| R6 | Expectativa “exacto” sem agente endpoint | Média | Honestidade GUI (R-H); IM7 |
| R7 | VDI multi-user | Alta | IM8 ou exclusão explícita |
| R8 | Contorno comercial (patch GUI) | Alta | Gate no daemon |
| R9 | ECH/DoH/QUIC | Média | Mitigações existentes + docs |
| R10 | Scope creep captive/SIEM | Média | Fora de escopo |
| R11 | AD events via WinRM no FreeBSD | Alta | Agente no DC canónico (R-D) |
| R12 | RADIUS ambíguo | Alta | Accounting receiver canónico (R-E) |
| R13 | Fail-mode Identity buraco de segurança | Alta | ADR-0027 cache + não-match; sem lock LAN |
| R14 | IP no AD ≠ IP no firewall | Alta | Limite documentado; lab topologia simples |
| R15 | Conteúdo MITM / privacidade | Alta | Retenção mínima; docs legais no spike |
| R16 | Lab Plus ≠ CE | Alta | Gate CE (R-J / ADR-0022) |
| R17 | IO bloqueante (LDAP/RADIUS/agente) pára o loop de captura/enforce | Crítica | ADR-0028: threads + rwlock; proibição no hot path; passo 20.11a |
| R18 | Overflow/truncamento silencioso de `features[64]` | Alta | Contrato ADR-0025 P1–P6; validação na emissão; testes de truncamento |
| R19 | Política aplicada ao user errado em IP partilhado (NAT/proxy) | Alta | Estado `multi-user` → não-match `ad_*` + audit (ADR-0027 §4.1) |
| R20 | Janela pós-reboot com mapa vazio interpretada como bug/bypass | Média | Cold start documentado (ADR-0027 §4.2); persistência best-effort; GUI mostra estado do mapa |
---

## 8. Rollback global da trilha

| Nível | Acção |
|-------|-------|
| Runtime | Desligar toggles MITM/Identity nas Definições |
| Licença | Reemitir `.lic` só com `base` |
| Pacote | Reinstalar `1.9.8` (ou última release sem a feature) via MANUAL-INSTALL |
| Docs | Marcar passos como revertidos no CORTEX; não apagar histórico |

---

## 9. Backlog (IDs)

| ID | Tema | Onda |
|----|------|------|
| BG-085 | Governança Identity+MITM (IM0) | IM0 |
| BG-086 | Entitlements `features` (IM1) | IM1 |
| BG-087 | MITM TLS opt-in + CA (IM2) | IM2 |
| BG-088 | Identity map + LDAP (IM3–IM4) | IM3–IM4 |
| BG-089 | Fontes RADIUS + AD events (IM5) | IM5 |
| BG-090 | Políticas user/grupo (IM6) | IM6 |
| BG-091 | Agente + TS/VDI (IM7–IM8) | IM7–IM8 |
| BG-092 | Fecho lab/release add-on (IM9) | IM9 |

Detalhe em [`../02-roadmap/backlog.md`](backlog.md).

---

## 10. Matriz documental (o que actualizar por tipo de passo)

| Tipo de passo | Obrigatório actualizar |
|---------------|------------------------|
| Governança | START-HERE, este plano, CORTEX, backlog, classification |
| Entitlement | ADR-0025, MANUAL-USO-LICENCAS, license-server docs |
| MITM | ADR-0026, mapa, matriz-limitacoes-dpi, emenda ADR-0017, MANUAL |
| Identity | ADR-0027, mapa, core/precedence, pf-enforcement se PF mudar |
| Release | CHANGELOG, MANUAL-INSTALL (comandos!), CORTEX, RELEASE-CHECKLIST |

---

## 11. Critérios de entrada / saída do plano

### Entrada (já satisfeita `2026-08-05`)

- [x] GO humano para criar o plano  
- [x] Baseline `1.9.8` estável  
- [x] Filas fecho+IPv6 fechadas  
- [x] Captive excluído; MITM incluído como opção; multi-fonte Identity; add-on X/Y  

### Saída (fecho futuro)

- [ ] C1–C11 satisfeitos ou excluídos por ADR  
- [ ] GI0–GI9 PASS (ou exclusões assinadas)  
- [ ] Produção: add-on documentado; default OFF  
- [ ] START-HERE marcado 【FILA FECHADA】 + arquivo se aplicável  

---

## 12. Histórico

| Data | Evento |
|------|--------|
| 2026-08-05 | Criação do plano + START-HERE + mapa + gates + ADRs Proposto (GO humano no chat) |
| 2026-08-05 | 20.1 documental PASS; passo actual → 20.2 |
| 2026-08-05 | **rev. `b`** — reparos arquitectónicos (spike MITM, mapa daemon, fontes canónicas, fail-mode, precedência, riscos R11–R16); código ainda não iniciado |
| 2026-08-05 | **rev. `c`** — contratos técnicos fechados: ADR-0028 (concorrência daemon) criado; contrato `features` P1–P6; precedência check-in vs `.lic` (interseção); §3.1 reconciliado com first-match; critérios S1–S8 do spike MITM; canal agente DC A1–A7; conflito NAT `multi-user`; cold start; limites de escala; passo 20.11a; riscos R17–R20; código ainda não iniciado |

---

## 13. Referências canónicas existentes (não duplicar conteúdo)

| Tema | Documento |
|------|-----------|
| Enforcement PF | [`../05-daemon/pf-enforcement.md`](../05-daemon/pf-enforcement.md) |
| Policy matrix | [`../core/policy-matrix.md`](../core/policy-matrix.md) |
| Precedence | [`../core/precedence.md`](../core/precedence.md) |
| Device identity | ADR-0011, ADR-0012 |
| Block page / anti-MITM histórico | ADR-0017 |
| Limitações DPI | [`../09-blocking/matriz-limitacoes-dpi.md`](../09-blocking/matriz-limitacoes-dpi.md) |
| Licenças | [`../10-license-server/MANUAL-USO-LICENCAS.md`](../10-license-server/MANUAL-USO-LICENCAS.md), F3 arch |
| Validação lab | [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) |
| Release | [`../06-releases/RELEASE-CHECKLIST.md`](../06-releases/RELEASE-CHECKLIST.md) |
