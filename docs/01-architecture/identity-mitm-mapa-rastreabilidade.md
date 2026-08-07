# Mapa de rastreabilidade — Identity + MITM Add-on

**Classificação:** Canónico (trilha Identity + MITM)  
**Rev.:** `2026-08-06d` (PME Identity-first; MITM DEFER 20.7a)  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**Posicionamento:** [`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md)  
**Arranque:** [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)  
**Gates:** [`../09-blocking/plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md)  
**Baseline:** produto `1.9.8` sem módulos activos  

Actualizar este mapa **a cada passo 20.x** que toque código ou contratos.  
**Código Identity ainda não iniciado**; entitlements IM1 no git; **MITM runtime não iniciado (DEFER)**.


---

## 0. Não-regressão — superfícies sagradas

Estas superfícies **não podem mudar de comportamento** enquanto `identity`/`mitm` estiverem OFF ou sem entitlement:

| ID | Superfície | SSOT / código típico | Nota |
|----|------------|----------------------|------|
| NR-01 | Captura + nDPI IPv4/IPv6 | `src/layer7d/capture*.c`, flow | Herança IPv6 fechada |
| NR-02 | Policy match host/app/CIDR/MAC | `policy.c`, ADR-0012 | |
| NR-03 | Enforce PF global/scoped | `enforce.c`, `layer7.inc`, `pf-enforcement.md` | |
| NR-04 | Allowlist / pallow / L7ALLOW | ADR-0016 | |
| NR-05 | Blacklists UT1 | F4 / `bl_*.c` | |
| NR-06 | DNS force + anti-DoH/DoT | ADR-0018 | |
| NR-07 | Block page HTTP (MITM OFF) | ADR-0017 | |
| NR-08 | VIP isenção | ADR-0020 | |
| NR-09 | Licença valid/expiry/grace/check-in | F3 / ADR-0021 | Só **estende** `features` |
| NR-10 | Updater GitHub Releases | ADR-0003 | |
| NR-11 | Inventário dispositivos | ADR-0011 | |
| NR-12 | Defaults `layer7.json` | package | Sem flags ON |

**Regra:** qualquer PR que altere NR-* com módulos OFF = **bloqueio** até prova de não-regressão.

---

## 1. Matriz de módulos novos (M-xx)

| ID | Componente | Onda | Estado | Acção | Notas |
|----|------------|------|--------|-------|-------|
| M-01 | Contrato `features` CSV | IM1 | **20.3 PASS** | LIC/DMN | ADR-0025; `features.c` |
| M-02 | License-server emissão SKU | IM1 | **20.4 PASS** | LIC | P1 + T1 + UI presets |
| M-03 | Gate daemon entitlements | IM1 | **20.6 PASS** | DMN | intersect + allows_* |
| M-04 | Gate GUI upsell | IM1 | **20.5 PASS** | GUI | páginas Identity/MITM |
| M-05 | CA store + geração/import | IM2 | **DEFERRED** | PKG/OPS | 20.7a; reabrir com novo GO |
| M-06 | Export trust (PEM) para GPO | IM2 | **DEFERRED** | GUI/OPS | |
| M-07 | Toggle `mitm.enabled` | IM2 | **DEFERRED** | GUI/PKG | default false quando existir |
| M-08 | Bypass MITM | IM2 | **DEFERRED** | GUI/DMN | |
| M-09 | Caminho intercept TLS | IM2 | **DEFER 20.7a** | DOC | Squid rejeitado; futuro = helper próprio |
| M-10 | Block page HTTPS via MITM | IM2 | **DEFERRED** | PKG | ADR-0017 permanece |
| M-11 | Session map user↔IP **no daemon** | IM3 | **PASS** (20.12–20.15) | DMN | Gated por entitlement |
| M-12 | Diagnóstico Identity GUI | IM3 | Dump JSON **PASS**; GUI page pendente | GUI/DMN | `layer7_idmap_dump_json` |
| M-13 | LDAP/LDAPS client | IM4 | **Config GUI 20.16 PASS**; cliente C = 20.17 | DMN/PKG | limites escala; secret bind 0600 |
| M-14 | Group expansion cache + fail-mode | IM4 | Planeado (20.17) | DMN | ADR-0027 |
| M-15 | RADIUS **accounting receiver** | IM5 | Planeado | DMN/PKG | canónico |
| M-16 | **Agente no DC** → push logons | IM5 | Planeado | OPS/DMN | canónico; não WinRM |
| M-17 | Conflict policy same-IP | IM5 | Planeado | DMN | |
| M-18 | Policy `ad_users`/`ad_groups` | IM6 | Planeado | GUI/PKG/DMN | |
| M-19 | Enforce ← mapa daemon | IM6 | Planeado | DMN | **não** PHP `device_ips` SSOT |
| M-20 | Precedence (§3.1 → core) | IM6 | Planeado | DOC | |
| M-21 | Endpoint agent | IM7 | Adiável | — | |
| M-22 | TS/VDI agent | IM8 | Adiável | — | |
| M-23 | Evidências + MANUAL | IM9 | Planeado | DOC/TST | |
| M-24 | Modelo concorrência/IO daemon (threads + rwlock) | IM3 (20.11a/20.12) | **PASS** rwlock no mapa; threads = 20.15+ | DMN | ADR-0028; `pthread_rwlock` em `identity_map` |
| M-25 | Baseline de perf registada | IM3 (20.11a) | **PASS** (`2026-08-06`) | TST | Evidência `20260806T174000Z-20.11a-baseline-perf`; pin doc `1.9.8` |

---

## 2. Pontos de integração no código existente (a preencher na implementação)

| Área | Ficheiros candidatos (baseline) | Como integrar sem partir |
|------|----------------------------------|---------------------------|
| Licença | `src/layer7d/license.c`, `license.h` | Extender parse (contrato ADR-0025 P1–P6; `features[64]`); não mudar valid/expiry |
| Main / identity | `identity_map.c` + `identity_module_sync` em `main.c` | **20.15:** init só com `L7_FEAT_IDENTITY`; SIGHUP sem clear; zero threads OFF |
| Policy / enforce | `policy.c`, `enforce.c` | Consultar mapa daemon só se identity ON |
| Package | `layer7.inc`, `layer7_identity.php` | **20.16:** config LDAP em `layer7.json` + secret 0600; sem SSOT de sessão |
| License-server | `license-server/backend/...` | Campo `features` já existe |
| Precedence | `docs/core/precedence.md` | 20.25 |
| Block page | `layer7-blockpage` | MITM ON (se GO) |

---

## 3. Fluxos runtime (alvo)

### 3.1 MITM OFF (default)

```text
Cliente → DNS/SNI/nDPI → policy → PF/DNS sinkhole
HTTPS bloqueado: erro TLS / timeout (ADR-0017)
```

### 3.2 MITM ON (só se spike GO + entitlement)

```text
Cliente (confia CA) → intercept selectivo → inspect/policy
  → allow | block (página HTTPS) | bypass
```

### 3.3 Identity ON + entitlement

```text
RADIUS accounting | Agente DC | (agente endpoint depois)
        → mapa daemon (user, ips[], groups, ttl)
LDAP → expand groups (cache + fail-mode)
Policy ad_* → IPs do mapa → enforce PF
```

---

## 4. Dependências e exclusões

| Dependência | Obrigatória? |
|-------------|--------------|
| Produção `1.9.8` estável | Sim |
| Domínio AD + agente DC **ou** NAS/RADIUS | Para GI5–GI7 |
| GPO/CA nos clientes | Só GI3 se MITM GO |
| Captive pfSense | **Não usar** |
| WinRM do appliance para DC | **Não canónico** |

---

## 5. Salvaguardas de segurança

1. LDAPS preferido.  
2. Conta serviço / agente DC com privilégio mínimo.  
3. CA/private `0600`; fora do git.  
4. Logs sem passwords/secrets/RADIUS secret.  
5. Failures Identity/MITM não derrubam base.  
6. Check-in pode retirar entitlements (ADR-0025/0021).  
7. MITM: retenção mínima de conteúdo desencriptado (spike).

---

## 6. Histórico do mapa

| Data | Evento |
|------|--------|
| 2026-08-05 | Criação (IM0) — M-01..M-23 + NR-01..NR-12 |
| 2026-08-05 | rev. `b` — spike MITM; mapa daemon; RADIUS receiver; agente DC; fail-mode; M-19 corrigido |
| 2026-08-05 | rev. `c` — M-24/M-25 (concorrência ADR-0028 + baseline perf); contrato `features` na área Licença; regra “sem IO bloqueante no hot path” |
| 2026-08-05 | **20.3** — M-01 PASS (`features.c` P1–P6 + T1; testes C) |
| 2026-08-05 | **20.4** — M-02 PASS (license-server normalizeFeatures + UI SKU) |
| 2026-08-05 | **20.5** — M-04 PASS (GUI upsell Identity/MITM) |
| 2026-08-05 | **20.6** — M-03 PASS + GI1 (check-in ∩ features) |
| 2026-08-06 | **20.11a** — M-25 PASS + M-24 confirmado (baseline perf; 1 thread) |
| 2026-08-06 | **20.12** — M-11 structs PASS (`identity_map`); rwlock M-24 |
| 2026-08-06 | **20.13** — M-11 API + M-12 dump JSON PASS |
| 2026-08-06 | **20.14** — persistência snap + stale skip PASS |
| 2026-08-07 | **20.15 / GI4** — gate entitlement em main PASS |
