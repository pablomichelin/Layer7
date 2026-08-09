# Runbook S1–S8 — pré-runtime MITM (sem intercept)

**Estado:** `ACTIVO` — continuidade documental pós-**20.9 PASS**  
**Autoriza:** checklist, smoke OFF, decisões escritas, pastas de evidência  
**NÃO autoriza:** `layer7-tlsproxy`, intercept TLS, block page HTTPS via MITM,
claim `mitm_effective=true`, Squid, promoção enforce além de `1.9.8`

| Campo | Valor |
|-------|--------|
| Passo plano | **20.9 PASS**; **20.10 BLOQUEADO** |
| Lab / `latest` | **`1.9.38`** (`SHA256=7c60f6b1…1dab`) |
| Pin enforce | **`1.9.8`** (não misturar com lab MITM) |
| Baseline perf | [`../tests/evidence/20260806T174000Z-20.11a-baseline-perf/`](../tests/evidence/20260806T174000Z-20.11a-baseline-perf/) |
| Spike | [`spike-mitm-20.7.md`](spike-mitm-20.7.md) §4 |
| Desenho | [`../01-architecture/desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md) |
| Arranque | [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md) |

---

## 1. Ordem segura (obrigatória)

```text
S5 (decisão escrita) → S8 (smoke OFF em 1.9.38)
  → S7 (política escrita / auditoria de defaults)
  → (só com GO lab) PoC runtime isolada
  → S1 + S2 + S4 (+ S3 / S6 com tráfego real)
  → GO lab explícito → só então 20.10
```

**Regra:** sem PoC runtime **não** se pode fechar S1–S4/S3/S6.  
Fechar **S5 parcial + S8 smoke** é o máximo seguro **sem** código de intercept.

---

## 2. Baseline de comparação (S1)

| Uso | Pacote | Motivo |
|-----|--------|--------|
| Não-regressão enforce | **`1.9.8`** | Pin produção; baseline 20.11a |
| Lab scaffolding actual | **`1.9.38`** | Intenção/IPC presentes; `mitm_effective=false` |

Para S1 futuro com runtime: medir **delta** face a `1.9.38` com MITM OFF
(mesmo appliance, mesma carga). Não usar `1.9.8` como único comparador se o
lab já corre `1.9.38` — declarar explicitamente no `run_id`.

---

## 3. Matriz S1–S8 (procedimento)

| # | Como provar (mínimo) | Artefacto | Pode fechar sem runtime? |
|---|----------------------|-----------|---------------------------|
| **S5** | Confirmar default `quic_mode=bypass` no schema/docs/GUI; escrever decisão (bypass vs block) | nota em evidência + ADR/contrato | **Sim (parcial)** — já em 20.9 |
| **S8** | Appliance lab `1.9.38`, `mitm.enabled` false ou sem entitlement: sinkhole + block page HTTP ≡ ADR-0017; status `mitm_effective=false` | `smoke-s8-off/` | **Sim** (obrigatório agora) |
| **S7** | Checklist: sem dump de payload por defeito no desenho; paths de log só metadados; CA fora do git | checklist assinado | **Parcial** (política) |
| **S1** | CPU idle/load com e sem proxy sob carga definida | `s1-cpu/` | **Não** |
| **S2** | Handshake TLS p95 ≤ 150 ms via proxy | `s2-latency/` | **Não** |
| **S3** | ≥1 browser: block page HTTPS com CA lab | `s3-blockpage/` | **Não** |
| **S4** | Destino em bypass não termina TLS no proxy | `s4-bypass/` | **Não** |
| **S6** | ECH: comportamento observado + nota de limite | `s6-ech/` | **Não** |

---

## 4. Smoke S8 (executável agora — sem intercept)

Pré-requisitos: lab appliance; pacote **`1.9.38`**; modo seguro (preferir
monitor / sem enforce de produção); snapshot/rollback disponíveis.

Checklist mínimo:

1. `layer7d -V` → `1.9.38`
2. Status JSON: `mitm_effective` **false**; `mitm_runtime_available` **false** (ou equivalente)
3. Com MITM OFF / sem entitlement: domínio bloqueado por política → DNS sinkhole → página HTTP ADR-0017
4. Confirmar **nenhum** processo `layer7-tlsproxy` / Squid a interceptar
5. Guardar evidência em  
   `docs/tests/evidence/<run_id>-s8-mitm-off-1.9.38/`  
   (`run_id` UTC, ex. `20260809T030000Z-s8-mitm-off-1.9.38`)

**PASS S8:** ADR-0017 intacto + `mitm_effective=false` + sem runtime.  
**FAIL S8:** qualquer intercept, página HTTPS via MITM, ou effective true.

---

## 5. Pasta de evidência (template)

```text
docs/tests/evidence/<run_id>-s1s8-mitm/
  README.md          # objectivo, pacote, appliance, veredicto
  s5-quic.md         # decisão escrita
  s8-off/            # smoke OFF
  s7-privacy.md      # defaults
  # só após GO lab + PoC:
  s1-cpu/
  s2-latency/
  s3-blockpage/
  s4-bypass/
  s6-ech/
```

---

## 6. GO lab (quando pedir)

Pedir GO lab **só** se:

1. S5 parcial documentado  
2. S8 smoke **PASS** em `1.9.38`  
3. S7 política escrita  
4. Desenho opção E aceite  
5. Squid continua rejeitado  
6. Escopo PoC limitado (lab; sem produção enforce)

GO lab **não** é GO produção. `20.10` só depois de S1–S8 medidos + GO lab.

---

## 7. Rollback / segurança

| Acção proibida neste runbook | Motivo |
|------------------------------|--------|
| Implementar `layer7-tlsproxy` | Sem GO lab |
| Activar intercept em produção / enforce `1.9.8` | Pin enforce separado |
| Distribuir CA de lab a clientes reais | Privacidade / S7 |
| Claim “MITM funciona” | Overclaim / N* posicionamento PME |

Rollback de qualquer teste: `mitm.enabled=false`; reinstalar `1.9.37` (lab) ou
`1.9.8` (enforce) conforme contexto.

---

## Histórico

| Data | Nota |
|------|------|
| 2026-08-08 | Criado na continuidade pós-alinhamento git/docs; passo 20.9 PASS; 20.10 bloqueado |
| 2026-08-09 | S8 **PASS parcial** lab `1.9.38` — evidência `docs/tests/evidence/20260809T021800Z-s8-mitm-off-1.9.38/` |
