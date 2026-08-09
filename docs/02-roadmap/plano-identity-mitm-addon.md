# Plano — Identity + MITM Add-on (trilha IM0–IM9)

**Estado do plano:** Identity **FECHADA**; MITM **GO lab** (rev. `2026-08-09w`; PoC-3 PASS em `.54`; 20.10 bloqueado)
**Tipo:** novo plano pós-fecho (ESTADO-PRODUTO §6); **não** reabre P0–J nem IPv6
**Posicionamento de produto (nicho PME):** [`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md) — **ACEITE**
**SSOT de execução:** este ficheiro  
**Arranque de chat (único desta trilha):** [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)  
**SSOT de estado vivo do produto:** [`../../CORTEX.md`](../../CORTEX.md)  
**Mapa técnico:** [`../01-architecture/identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md)  
**Desenho MITM (opção E):** [`../01-architecture/desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md) — runtime produto **AUSENTE**; PoC-0 idle no repo  
**Contrato IPC 20.9:** [`../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md`](../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md)  
**Runbook S1–S8 pré-runtime:** [`../09-blocking/runbook-s1-s8-mitm-pre-runtime.md`](../09-blocking/runbook-s1-s8-mitm-pre-runtime.md)  
**PoC lab tlsproxy:** [`../09-blocking/poc-layer7-tlsproxy-lab.md`](../09-blocking/poc-layer7-tlsproxy-lab.md) — GO lab; **sem** produção  
**Lab real (testes):** [`../08-lab/lab-topology.md`](../08-lab/lab-topology.md) — `.254`/`.234`/`.235`; SSH pfSense **8=Shell**  
**Gates:** [`../09-blocking/plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md)  
**ADRs:** [0025](../03-adr/ADR-0025-entitlements-addon-identity-mitm.md) · [0026](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md) (**Aceito — implementação em curso / 20.9 intenção; runtime diferido**) · [0027](../03-adr/ADR-0027-identity-userid-multi-fonte.md) · [0028](../03-adr/ADR-0028-concorrencia-io-daemon-identity.md) · [0029](../03-adr/ADR-0029-adiamento-agente-endpoint-exclusao-ts.md) (**IM7 ADIAR + IM8 exclusão**)  
**Baseline produção:** `1.9.8` — rollback enforce `1.9.0`  
**Baseline perf 20.11a:** [`../tests/evidence/20260806T174000Z-20.11a-baseline-perf/`](../tests/evidence/20260806T174000Z-20.11a-baseline-perf/)  
**Canal lab/`latest`:** `1.9.38` (20.9 PASS; SHA `7c60f6b1a052b675fd064825bd7f0ae79012143b271215d39ed9848b059d1dab`)

**Nota:** **Rev. `m` (`2026-08-08`)** = 20.33 homolog two-client PASS; GI9; Identity rede **FECHADA**.  
**Rev. `n` (`2026-08-08`)** = **GO humano reopen MITM**; passo **20.8** scaffolding.  
**Rev. `o` (`2026-08-08`)** = **20.8 PASS** (`1.9.37`).  
**Rev. `p` (`2026-08-08`)** = **20.9 PASS** — intenção `mitm.enabled`, bypass endurecido, `quic_mode`, contrato IPC; `mitm_effective` sempre false sem runtime; Squid rejeitado.  
**Rev. `q` (`2026-08-08`)** = docs alinhados ao publish **`1.9.38`** (`releases/latest`).  
**Rev. `r` (`2026-08-08`)** = alinhamento git+SSOT + runbook S1–S8 pré-runtime (sem intercept).  
**Rev. `s` (`2026-08-09`)** = S5+S7+S8 PASS pré-runtime.  
**Rev. `t` (`2026-08-09`)** = **GO lab** PoC-0 idle `layer7-tlsproxy`; sem produção; 20.10 bloqueado.  
**Rev. `u` (`2026-08-09`)** = **PoC-1 PASS** — IPC PING lab-only; `mitm_effective` false.  
**Rev. `v` (`2026-08-09`)** = lab `.54` + **PoC-2** TLS localhost PASS (S2); S1 produto PENDING.  
**Rev. `w` (`2026-08-09`)** = **PoC-3 PASS** — SNI bypass/block + página HTTPS (S3/S4 lab).

---

## 0. Estado e progresso

| Campo | Valor |
|-------|-------|
| Passo actual | **20.9 PASS**; **PoC-3 PASS** em `.54`; **20.10** bloqueado |
| Código | `src/layer7-tlsproxy/` **0.0.3-poc3** (fora do `.pkg`) |
| Próximo | Splice/upstream ou S1 inline **só** em `.54`; GO produto → 20.10 |
| Lab PoC | **`192.168.100.54`** root |

```text
TRILHA — progresso
- PoC-3 PASS (S3/S4) em 192.168.100.54
- 20.10 BLOQUEADO; mitm_effective false
- Evidência: 20260809T041800Z-poc3-sni-s3s4-54
- Plano rev.: 2026-08-09w
```

### 0.0 Correcções arquitectónicas obrigatórias (rev. `b`)

Estas regras **substituem** interpretações ingénuas da rev. `a`:

| # | Tema | Decisão canónica |
|---|------|------------------|
| R-A | MITM ≠ dependência de Identity | MITM e Identity são **ortogonais**. Preferência de produto “MITM cedo” **não** bloqueia Identity se o spike falhar. |
| R-B | Spike MITM GO/NO-GO | Passo **20.7** é **spike** (desenho + decisão). **Cumprido:** veredicto **DEFER 20.7a** (`2026-08-06`) sem PoC de intercept (Squid rejeitado; Identity-first). **Reopen GO `2026-08-08`:** 20.8/20.9 PASS; runtime/intercept exige S1–S8 + GO lab. |
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

### 0.0.2 Posicionamento e caminho de valor (rev. `d`)

| # | Tema | Decisão canónica |
|---|------|------------------|
| R-R | Nicho PME / MSP | Produto do add-on = **controlo de internet por pessoa** para empresas **pequenas/médias** e canal MSP em pfSense. **Não** é paridade com NGFW enterprise. Detalhe: [`posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md). |
| R-S | Identity-first | Após IM1, o **caminho de valor obrigatório** é IM3→IM6. MITM **não** atrasa Identity. |
| R-T | MITM DEFER 20.7a → reopen GO | Spike fechado como **DEFER** (`2026-08-06`): Squid **rejeitado**; PoC runtime não iniciada. **Reopen GO humano `2026-08-08`:** **20.8 PASS** + **20.9 PASS** (intenção `mitm.enabled`, bypass endurecido, `quic_mode`, contrato IPC); runtime `layer7-tlsproxy` **AUSENTE**; `mitm_effective` **sempre false** sem runtime; GI2/GI3 **runtime** permanece `DEFERRED` até S1–S8 + GO lab. Desenho: [`desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md); contrato: [`contrato-ipc-layer7-tlsproxy-20.9.md`](../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md). Token `mitm` no SKU **sem** intercept activo. |
| R-U | Barra UX PME | Cada passo Identity deve cumprir critérios U*/P*/H*/N* do posicionamento (§6) — “perfeito para empresas usarem”, não só “compila e passa gate”. |
| R-V | Anti-overclaim | Materiais e GUI **proibidos** de prometer paridade Fortinet/Palo/Check Point, MITM universal, ou exactidão GlobalProtect sem agente. |

### 0.1 Relação com planos fechados

| Plano | Estado | Relação |
|-------|--------|---------|
| Fecho P0–J | **FECHADO** | Baseline comercial; não reabrir |
| IPv6 V0–V6 | **FECHADA** | Dual-stack já no produto; Identity/MITM devem **respeitar** IPv4+IPv6 |
| Este plano | Identity **FECHADA**; MITM **20.9 PASS** | Extensão comercial; Identity rede fechada; MITM intenção/IPC pós-reopen GO; runtime ausente |

Congelamento das filas fechadas:
[`../00-overview/ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](../00-overview/ESTADO-PRODUTO-E-PLANOS-FECHADOS.md).

### 0.2 Motivação e objectivo (decisão de produto)

**Origem (`2026-08-05`, rev. `b`):**

1. Clientes em domínio AD precisam de políticas por **utilizador/grupo**, não só IP/MAC/dispositivo.
2. Controlo “exacto” no mercado NGFW = política por identidade + mapa **dinâmico** user→IP (não IP estático eterno).
3. Add-on comercial: **licença X (base)** vs **licença Y (base + add-on)**.
4. MITM no SKU como opção; **não** dependência de Identity.
5. Captive portal do pfSense: **fora**.
6. MVP Identity **sem** agente endpoint = User-ID de rede; exactidão tipo agente = IM7+.

**Emenda estratégica (`2026-08-06`, rev. `d`) — nicho PME:**

7. **Mercado-alvo:** empresas **pequenas e médias** (+ MSP). Empresas grandes contratam NGFW caro — **fora** do objectivo de paridade.
8. **Ideia:** não desistir porque “já existe NGFW”; criar o produto que o NGFW **não** empacota bem no pfSense/PME (Identity + DPI + um pacote + ops simples).
9. **Objectivo mensurável:** “quem na rede pode o quê” por user/grupo, instalável e **utilizável** por TI PME/MSP (barra U*/P*/H* do posicionamento).
10. **MITM:** diferido formalmente (R-T); futuro = helper próprio selectivo, **não** Squid, **não** paridade enterprise.
11. Documento canónico da ideia/objectivo/nicho/UX:
    [`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md).

### 0.3 Definição de “completo” nesta trilha (critérios I–M)

| # | Critério | Prova |
|---|----------|-------|
| C1 | Entitlements no `.lic` com gate real (daemon + GUI) | GI1 |
| C2 | Compat: `features=full` e licenças antigas não partem o produto base | GI1 |
| C3 | MITM: spike 20.7 GO **ou** DEFER formal; se GO — opt-in OFF + lab GI2–GI3 | **DEFER formal 20.7a** (`2026-08-06`) — GI2/GI3 `DEFERRED`; C3 **satisfeito por defer** |
| C3b | Posicionamento PME documentado + caminho Identity-first | [`posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md) **ACEITE** |
| C4 | Sem entitlement MITM: zero interceptação TLS (mesmo com código presente) | GI1.3 + defer (sem runtime MITM) |
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
7. **Ordem segura:** IM0 → IM1 **sempre primeiro**. Depois: **caminho de valor obrigatório** IM3–IM6 (Identity) com IM2 em **DEFER** (R-S/R-T). Não activar MITM sem entitlement + bypass + **novo GO** pós-defer.
8. **IPv4 e IPv6.** Mapas e enforcement Identity dual-stack; IPv6 privacy (vários IPs/user).
9. **Honestidade.** Limites (ECH, QUIC, NAT, VDI sem TS, stale, topologia IP≠AD) na GUI e docs; anti-overclaim NGFW (R-V).
10. **Rollback.** Toggle OFF / `.lic` sem feature / reinstalar baseline.
11. **Captive portal fora de escopo.** Não reimplementar o do pfSense.
12. **Documentação no mesmo bloco** quando houver execução/release.
13. **Mapa Identity no daemon** (R-C). Pacote configura; não é SSOT de sessão.
14. **Spike MITM** foi obrigatório antes de investir IM2 completa (R-B) — **cumprido com DEFER 20.7a**; **reopen GO `2026-08-08`** autoriza 20.8/20.9 (não intercept). Runtime exige S1–S8 + GO lab.
15. **Nicho PME / barra UX** (R-R, R-U): cada passo Identity revê posicionamento §6 (U*/P*/H*/N*).

---

## 2. Modelo comercial (resumo; detalhe ADR-0025)

| SKU | Entitlements (exemplo canónico) | Inclui |
|-----|----------------------------------|--------|
| **Standard (X)** | `base` | Produto actual (`1.9.8` capabilities) |
| **Identity Add-on (Y)** | `base,identity` | + User-ID multi-fonte + políticas user/grupo — **oferta principal PME** |
| **Identity + MITM (Y+)** | `base,identity,mitm` | + inspeção TLS opt-in — **futuro** (MITM diferido; token pode existir sem runtime) |
| **MITM only (opcional)** | `base,mitm` | MITM sem Identity — baixa prioridade vs Y até haver implementação |
| **Legado** | `full` ou vazio parseado como hoje | **Compat:** equivale a `base` + todas features **já** existentes; **não** auto-concede `identity`/`mitm` após ADR-0025 Aceito (ver regra de transição no ADR) |

**Regra de transição (ADR-0025 — GO 20.2):**  

- **T1 (ACEITE `2026-08-05`):** `full` → `base` apenas; Identity/MITM exigem reemissão com flags novas.  
- **T2:** rejeitada no GO 20.2 (documentada só como alternativa histórica).

Preços X/Y são decisão comercial externa ao repo; o plano só materializa o **gate técnico**.

**Posicionamento comercial PME:** oferta âncora = **Y (Identity)**; ver
[`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md) §9.

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

### 3.1 Precedência (SSOT: `docs/core/precedence.md` — formalizado em 20.25)

O esboço desta secção foi **promovido** para
[`docs/core/precedence.md`](../core/precedence.md) (secção *Identity:
`ad_users` / `ad_groups`*). Resumo:

**Decisão estrutural (R-M):** o motor V1 é **first-match por `priority`
decrescente** e isso **não muda**. Políticas `ad_*` **não** formam uma
camada separada: entram na mesma lista; no match resolvem para IPs do
**mapa daemon**.

Regras complementares (detalhe no SSOT):

1. Excepções / VIP / allowlist — antes da matriz; inalteradas.
2. User ausente / `multi_user` → não-match `ad_*` → first-match continua.
3. Conflito IP: last-writer + audit; concorrentes → `multi_user`.
4. LDAP down: fail-mode ADR-0027 §4 (`ad_*` não-match; base intacta).
5. MITM (se ON): não altera precedência allow/block.
6. Empates / default: como V1.

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

### IM2 — MITM TLS opt-in — **reopen GO `2026-08-08`** — passo **20.9 PASS**

MITM no stack actual (pcap+nDPI+PF) é **quase um segundo produto** (terminação TLS).
Histórico PME (`2026-08-06`): DEFER 20.7a; Squid **rejeitado**; Identity-first avançou e
**fechou** (20.33/GI9). **GO humano `2026-08-08`:** reabrir IM2 com scaffolding seguro
(opt-in OFF); **sem** intercept; runtime helper próprio ainda **ausente**.

| Passo | Entrega | Gate / estado |
|-------|---------|---------------|
| **20.7** | Spike: desenho + preflight appliance; Squid rejeitado; opções B/E/D | **PASS documental** (`spike-mitm-20.7.md`) |
| **20.7a** | **DEFER formal:** ADR-0026 diferida; GI2/GI3 `DEFERRED`; Identity IM3+ | **PASS** (`2026-08-06`) |
| **20.8** | Scaffolding: schema `mitm.*` OFF; gestão CA; bypass GUI; `mitm_entitled` no status daemon; `enabled` forçado false; **sem** `layer7-tlsproxy` | **PASS** (`1.9.37`, `2026-08-08`) |
| **20.9** | Toggle **intenção** `mitm.enabled` + bypass endurecido + `quic_mode` + contrato IPC; `mitm_effective` **sempre false** sem runtime | **PASS** (`1.9.38`, `2026-08-08`) |
| **20.10** | Intercept selectivo; block page HTTPS — **exige** runtime + S1–S8 + GO lab | **BLOQUEADO** até S1–S8 + GO lab |
| **20.11** | Lab CA; GI2–GI3 runtime | BLOQUEADO / gates runtime `DEFERRED` |

**Veredicto 20.7a (histórico):** **DEFER** — Identity avançou; Squid rejeitado.

**Reopen GO `2026-08-08`:** IM2 reaberto para scaffolding (20.8) e intenção/IPC (20.9), **não** para intercept.
Desenho opção E: [`desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md).
Contrato IPC: [`contrato-ipc-layer7-tlsproxy-20.9.md`](../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md).
Squid / `pfSense-pkg-squid` permanece **rejeitado**. ADR-0017 permanece verdade enquanto
`mitm_effective=false` (sempre, sem runtime). Runtime `layer7-tlsproxy` **AUSENTE**.
GI2/GI3 **critérios de runtime** continuam `DEFERRED` até S1–S8 medidos + GO lab.

**Relação com ADR-0017:** `!mitm_effective` → ADR-0017 intacto (obrigatório no 20.8/20.9).

**Rollback:** `mitm.enabled=false`; remover entitlement `mitm`; sem processo proxy a parar (ausente).

---

### IM3 — Núcleo Identity (mapa user↔IP **no daemon**) — **FECHADA** (caminho de valor PME cumprido)

**Foco PME:** estruturas e diagnóstico devem nascer já com linguagem e estados
úteis à empresa (posicionamento U2/U3/H1) — não só structs internas.

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.11a** | **Pré-requisito de código:** ADR-0028 **Aceito** + **baseline de perf registada** (CPU/throughput; latência proxy builder; lab `1.9.13` pré-Identity) + checklist posicionamento §11 | **PASS** (`2026-08-06`) — evidência `20260806T174000Z-20.11a-baseline-perf` |
| **20.12** | Estruturas no daemon: sessão (user, IPs v4/v6 **lista**, source, seen_at, ttl, groups cache); **limites de escala ADR-0027 §4.3**; rwlock conforme ADR-0028 | **PASS** (`2026-08-06`) — `identity_map.h`/`identity_map.c`; `test_identity_map`; port `1.9.14` candidato |
| **20.13** | API interna: add/refresh/expire; export para enforce/PF; dump diagnóstico GUI | **PASS** (`2026-08-06`) — upsert/refresh/expire/lookup/export/dump_json; multi_user ADR-0027 §4.1 |
| **20.14** | Persistência best-effort opcional + política stale + **cold start/SIGHUP conforme ADR-0027 §4.2**; **não** depender de resync PHP como SSOT | **PASS** (`2026-08-06`) — save/load snap; expired skip; `clear` ≠ SIGHUP |
| **20.15** | Entitlement `identity` gate; módulo inerte sem ele (**zero threads novas com OFF**, ADR-0028 §4) | **PASS / GI4** (`2026-08-07`) — `identity_module_sync` em startup/SIGHUP/license; stats `identity_*` |

---

### IM4 — LDAP/LDAPS (directório)

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.16** | Config GUI: servidor, porto, TLS, bind DN, base DN, filtros; limites de escala (depth grupos, máx. members) | **PASS** (`2026-08-07`) |
| **20.17** | Resolve grupos → membros; cache TTL; fail-mode ADR-0027 | **PASS** (`2026-08-07`) |
| **20.18** | Test connection na GUI; logs sem passwords | **GI5** (parcial LDAP) — **PASS** `2026-08-07` |

---

### IM5 — Fontes de sessão (NGFW-like)

**Fontes canónicas MVP (rev. `b`):**

| Passo | Fonte | Arquitectura canónica |
|-------|-------|------------------------|
| **20.19** | **RADIUS accounting receiver** | Daemon/pacote escuta accounting (User-Name + Framed-IP-Address / v6); secret + ACL NAS — **PASS** `2026-08-07` |
| **20.20** | **Eventos de logon AD** | Desenho A1–A7 **PASS**; receiver **PASS**; agente Win **PASS** (`docs/samples/identity-dc-agent/`) |

MVP fecho parcial: LDAP + **pelo menos uma** fonte. Ambas no plano completo.

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.21** | Normalização: fontes → mesmo mapa daemon | **PASS** (`2026-08-08` — normalize + remove_ip) |
| **20.22** | Conflito mesmo IP: last-writer + audit **+ estado `multi-user` → não-match `ad_*` (ADR-0027 §4.1)**; nota topologia (IP AD ≠ IP visto no firewall) | **PASS** (`2026-08-08`) |

**Explicitamente fora:** captive portal Layer7; WinRM/WMI canónico a partir do pfSense.

---

### IM6 — Políticas por utilizador / grupo

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.23** | Grupo/política aceita `ad_users` / `ad_groups` (além de hosts/MAC) | **PASS** (`1.9.27`) |
| **20.24** | Match/enforce usa IPs do **mapa daemon** (não SSOT PHP tipo `device_ips`) | **PASS** (`1.9.28`) |
| **20.25** | Precedência: promover esboço §3.1 → `docs/core/precedence.md` | **PASS** (doc + GI7.4 unit) |
| **20.26** | Lab: user A vs B; troca de IP → remap daemon; LDAP down → fail-mode | **PASS unitário** (lab AD residual) |

**Não-regressão:** políticas só IP/MAC existentes inalteradas.

---

### IM7 — Agente endpoint (fase posterior, no plano)

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.27** | Especificação agente (OS, canal, auth, heartbeat) | **PASS** (`especificacao-agente-endpoint-20.27.md`) |
| **20.28** | MVP agente Windows **ou** ADIAR com ADR | **PASS ADIAR** (`ADR-0029`) |

---

### IM8 — Terminal Server / VDI (fase posterior)

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.29** | Desenho TS agent (user→porta) | **PASS exclusão** (`ADR-0029` / `desenho-ts-vdi-20.29-excluido.md`) |
| **20.30** | Implementação **ou** limite honesto multi-user | **PASS exclusão** (`ADR-0029`) |

---

### IM9 — Fecho documental, malha, release

| Passo | Entrega | Gate |
|-------|---------|------|
| **20.31** | Malha lab Identity+MITM + evidências `run_id` | **PASS** (`20260808T135500Z-im9-20.31-identity-mesh`; MITM SKIP/DEFER; lab AD residual) |
| **20.32** | MANUAL-INSTALL + MANUAL-USO-LICENCAS + changelog + commercial notes | **PASS** (`2026-08-08`) |
| **20.33** | Release candidata + GO produção add-on (pode ser prerelease) | **PASS** (`20260808T174100Z-im9-20.33-homolog-1.9.29`; GI9) |

---

## 5. Ordem de implementação (resumo visual)

**Caminho canónico actual (rev. `q` — Identity FECHADA; MITM 20.9 PASS):**

```text
IM0 → IM1 Entitlements          [FEITO]
  → IM2 spike 20.7 + 20.7a DEFER [FEITO]
  → IM3–IM6 Identity MVP         [FEITO]
  → IM7 ADIAR + IM8 exclusão (ADR-0029 / GI8 PASS)
  → IM9 Fecho Identity de rede   [FEITO — 20.33/GI9]
  → IM2 reopen GO 2026-08-08 → **20.8 PASS** → **20.9 PASS**
       (intenção/bypass/quic_mode/IPC; mitm_effective=false; tlsproxy AUSENTE;
        Squid rejeitado; contrato-ipc-layer7-tlsproxy-20.9.md)
  → **20.10** BLOQUEADO até S1–S8 + GO lab (sem intercept)
  → IM7 reopen endpoint só com GO separado
```

**Caminho histórico (se spike tivesse sido GO) — arquivado como alternativa:**

```text
IM0 → IM1 → IM2 GO → 20.8–20.11 MITM → IM3–IM6 → IM7–IM9
```

**Proibido:** IM6 sem IM3 + IM1.  
**Proibido:** MITM activo sem entitlement, bypass e **novo GO** pós-defer.  
**Proibido:** tratar `device_ips` PHP como SSOT de Identity.  
**Proibido:** Squid como caminho de produto.  
**Proibido:** overclaim de paridade NGFW (R-V).

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
| R2 | MITM inviável / CPU / segundo produto | Alta | **Mitigado parcial:** DEFER 20.7a → reopen 20.8/20.9 (intenção ≠ effective); runtime + S1–S8 ainda pendentes; Squid rejeitado |
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
| R21 | Overclaim vs NGFW / expectativa MITM total na PME | Alta | Posicionamento PME + R-V; GUI honesta; MITM diferido |
| R22 | Identity “técnicamente OK” mas inutilizável por TI PME | Alta | Barra U*/P*/H* (R-U); testes de ligação; estados visíveis |
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
| BG-087 | MITM TLS opt-in + CA (IM2) — **20.9 PASS** (intenção/IPC; runtime diferido) | IM2 (20.8–20.9) |
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
| Governança | START-HERE, este plano, CORTEX, backlog, classification, **posicionamento PME** |
| Entitlement | ADR-0025, MANUAL-USO-LICENCAS, license-server docs |
| MITM | ADR-0026, spike, mapa, matriz-limitacoes-dpi, emenda ADR-0017, MANUAL (quando reabrir) |
| Identity | ADR-0027, mapa, core/precedence, pf-enforcement se PF mudar, **barra UX posicionamento §6** |
| Release | CHANGELOG, MANUAL-INSTALL (comandos!), CORTEX, RELEASE-CHECKLIST, notas comerciais anti-overclaim |

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
| 2026-08-05 | **20.2 PASS** — ADRs 0025/0026/0027/0028 Aceito; transição legado **T1**; GI0 PASS; passo actual → **20.3** (IM1) |
| 2026-08-05 | **20.3 PASS** — `features.c`/`features.h` (ADR-0025 P1–P6 + T1); integração em `license_check`; `test_features_parse.c` PASS; passo actual → **20.4** |
| 2026-08-05 | **20.4–20.6 PASS / GI1 PASS** — LS SKU, GUI upsell, check-in ∩ features; passo actual → **20.7** |
| 2026-08-05 | Spike 20.7: desenho + preflight appliance; Squid rejeitado pelo operador |
| 2026-08-06 | **rev. `d` / 20.7a PASS** — posicionamento PME Identity-first ACEITE; ADR-0026 implementação diferida; GI2/GI3 DEFERRED; saltar 20.8–20.11; passo actual → **20.11a / IM3** |
| 2026-08-06 | **rev. `e` / 20.11a PASS** — baseline perf registada (appliance monitor + builder latency proxy); passo actual → **20.12** |
| 2026-08-06 | **rev. `f` / 20.12 PASS** — `identity_map` (structs, limites §4.3, rwlock); smoke builder OK; passo → **20.13**; candidato `1.9.14` |
| 2026-08-06 | **rev. `g` / 20.13 PASS** — API upsert/refresh/expire/lookup/export/dump; multi_user; passo → **20.14** |
| 2026-08-06 | **rev. `h` / 20.14 PASS** — snap save/load; stale nunca restaurado; clear documentado ≠ SIGHUP; passo → **20.15** |
| 2026-08-07 | **rev. `a` / 20.15 PASS / GI4** — gate entitlement em `main`; zero threads OFF; passo → **20.16** LDAP |
| 2026-08-07 | **rev. `b` / 20.16 PASS** — GUI LDAP (`layer7_identity.php`); secret bind 0600; defaults OFF; `test_identity_ldap_config` PASS; passo → **20.17** |
| 2026-08-07 | **rev. `c` / 20.17 PASS** — `identity_ldap` worker+cache+fail-mode+OpenLDAP; `test_identity_ldap` PASS; candidato `1.9.15`; passo → **20.18** |
| 2026-08-07 | **rev. `d` / 20.18 PASS** — Test LDAP GUI + `layer7d --ldap-test` (GI5.4); candidato `1.9.16`; passo → **20.19** |
| 2026-08-07 | **rev. `e` / 20.19 PASS** — RADIUS accounting receiver (`identity_radius`); secret+ACL NAS; GI5.3 unitário; candidato `1.9.17`; passo → **20.20** |
| 2026-08-07 | **rev. `f` / 20.20 desenho PASS** — A1–A7 fechados (TLS+HMAC MVP; porto 8743); ADR-0027 rev. d; passo → **20.20 código** |
| 2026-08-07 | **rev. `g` / 20.20 receiver + 1.9.18** — `identity_dc` HTTPS+HMAC; GUI; lab PS1; publicado lab/`latest`; passo → **agente Win** |
| 2026-08-08 | **rev. `a` / 20.20 agente Win PASS + 1.9.24** — `Layer7IdentityDcAgent.ps1` + Install/Uninstall + README; publicado lab/`latest`; passo → **20.21** |
| 2026-08-08 | **rev. `b` / 20.21 PASS** — `normalize_user` + `remove_ip`; RADIUS/DC alinhados; candidato `1.9.25`; passo → **20.22** |
| 2026-08-08 | **rev. `c` / 20.22 PASS** — audit conflict/last_writer + GUI topologia; candidato `1.9.26`; passo → **20.23** |
| 2026-08-08 | **rev. `d` / 20.23 PASS** — políticas `ad_users`/`ad_groups` (parse+GUI); candidato `1.9.27`; passo → **20.24** |
| 2026-08-08 | **rev. `e` / 20.24 PASS** — match `ad_*` via mapa daemon; candidato `1.9.28`; passo → **20.25** |
| 2026-08-08 | **rev. `f` / 20.25 PASS** — `core/precedence.md` Identity formalizado; GI7.4 unit; passo → **20.26** |
| 2026-08-08 | **rev. `g` / 20.26 GI7 PASS unitário** — testes GI7.1–7.5; lab residual; passo → **20.27** |
| 2026-08-08 | **rev. `r` / alinhamento + S1–S8 runbook** — git `1.9.38`+portal `2.0.0` em origin; MANUAL SHA; runbook pré-runtime; 20.10 bloqueado |
| 2026-08-09 | **rev. `v` / PoC-2** — lab `192.168.100.54`; TLS localhost; S2 p95≈2.8 ms PASS; S1 produto PENDING |
| 2026-08-09 | **rev. `u` / PoC-1 PASS** — IPC PING lab-only; evidência `20260809T031700Z-poc1-ipc-idle`; 20.10 bloqueado |
| 2026-08-09 | **rev. `t` / GO lab PoC-0** — `src/layer7-tlsproxy` idle; proibido intercept em `.254`/`.234`/`.235`; 20.10 bloqueado |
| 2026-08-09 | **rev. `s` / S5+S7+S8** — S8 ADR-0017 real PASS; S5 quic bypass PASS doc; S7 privacidade PASS doc; 20.10 bloqueado (S1–S4/S6+GO lab) |
| 2026-08-09 | **rev. `r+` / lab real obrigatório** — topologia `.254`/`.234`/`.235`; SSH pfSense opção 8; S8 two-client real; sem simular gates |
| 2026-08-08 | **rev. `q` / docs align** — `latest` **`1.9.38`** publicado (SHA `7c60f6b1…1dab`); passo continua **20.9 PASS**; 20.10 bloqueado |
| 2026-08-08 | **rev. `p` / 20.9 PASS** — intenção `mitm.enabled`; bypass endurecido; `quic_mode`; contrato IPC; `mitm_effective` sempre false sem runtime; próximo **20.10** bloqueado (S1–S8+GO lab); ADR-0026 rev. `f`; publicado depois como `1.9.38` |
| 2026-08-08 | **rev. `o` / 20.8 PASS** — scaffolding publicado `1.9.37`; schema/CA/bypass/status; tlsproxy AUSENTE; Squid rejeitado |
| 2026-08-08 | **rev. `n` / reopen MITM GO** — passo **20.8** scaffolding; Identity rede permanece FECHADA; ADR-0026 rev. `e` |
| 2026-08-08 | **rev. `m` / 20.33 GI9 PASS** — homolog two-client real; licença Veeam; fila Identity rede **FECHADA** |
| 2026-08-08 | **rev. `k` / 20.32 PASS** — MANUAL Identity + USO-LICENÇAS §14 + notes comerciais; GI9.2; passo → **20.33** |
| 2026-08-08 | **rev. `j` / 20.31 PASS** — malha Identity OFF + `run-im9-20.31`; evidência indexada; GI9.1/9.4; passo → **20.32** |
| 2026-08-08 | **rev. `i` / 20.28–20.30 PASS** — ADR-0029 ADIAR IM7 + exclusão IM8; GI8; passo → **20.31** |
| 2026-08-08 | **rev. `h` / 20.27 PASS** — especificação agente endpoint; recomendação ADIAR salvo GO; passo → **20.28** |

---

## 13. Referências canónicas existentes (não duplicar conteúdo)

| Tema | Documento |
|------|-----------|
| Posicionamento PME / objectivo | [`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md) |
| Spike MITM / DEFER | [`../09-blocking/spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md) |
| Enforcement PF | [`../05-daemon/pf-enforcement.md`](../05-daemon/pf-enforcement.md) |
| Policy matrix | [`../core/policy-matrix.md`](../core/policy-matrix.md) |
| Precedence | [`../core/precedence.md`](../core/precedence.md) |
| Device identity | ADR-0011, ADR-0012 |
| Block page / anti-MITM histórico | ADR-0017 |
| Limitações DPI | [`../09-blocking/matriz-limitacoes-dpi.md`](../09-blocking/matriz-limitacoes-dpi.md) |
| Licenças | [`../10-license-server/MANUAL-USO-LICENCAS.md`](../10-license-server/MANUAL-USO-LICENCAS.md), F3 arch |
| Validação lab | [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) |
| Release | [`../06-releases/RELEASE-CHECKLIST.md`](../06-releases/RELEASE-CHECKLIST.md) |
