# Runbook S1–S8 — pré-runtime MITM (sem intercept)

**Estado:** `ACTIVO` — continuidade pós-**20.9 PASS**; **GO lab `2026-08-09`** (PoC idle)  
**Autoriza:** checklist S*, PoC-0 idle `layer7-tlsproxy`, evidências  
**NÃO autoriza:** intercept em `.254`/`.234`/`.235`; `mitm_effective=true`; empacotar PoC sem GO; Squid; **20.10** produto

| Campo | Valor |
|-------|--------|
| Passo plano | **20.9 PASS**; **GO lab** → PoC-0; **20.10** ainda **BLOQUEADO** |
| PoC lab | [`poc-layer7-tlsproxy-lab.md`](poc-layer7-tlsproxy-lab.md) |
| Lab / `latest` | **`1.9.38`** (`SHA256=7c60f6b1…1dab`) |
| Pin enforce | **`1.9.8`** (não misturar com lab MITM) |
| Baseline perf | [`../tests/evidence/20260806T174000Z-20.11a-baseline-perf/`](../tests/evidence/20260806T174000Z-20.11a-baseline-perf/) |
| Spike | [`spike-mitm-20.7.md`](spike-mitm-20.7.md) §4 |
| Desenho | [`../01-architecture/desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md) |
| Arranque | [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md) |
| Malha real | [`../08-lab/lab-topology.md`](../08-lab/lab-topology.md) — `.254` + `.234` + `.235` |

---

## 0. Lab real (obrigatório — não simular)

| Host | Papel |
|------|--------|
| `192.168.100.254` | pfSense + Layer7 |
| `192.168.100.234` | Ubuntu cliente A (`server`) |
| `192.168.100.235` | Ubuntu cliente B (`zpro-aimirim`) |

**Regras:**

1. Gates S8 / two-client / ADR-0017 exigem **tráfego real** dos clientes (SSH
   `root@234` / `root@235` a partir do Mac) — **não** curls só no Mac nem
   unit tests como substituto.
2. pfSense: se SSH abrir o **menu**, escolher **`8) Shell`** antes de comandos.
   Automação deste lab: `root@254` (shell directo).
3. Appliance **não** alcança SSH aos clientes — orquestrar no Mac.
4. Hosts são **funcionais em produção** — mutações scoped + rollback no mesmo bloco.

---

## 1. Ordem segura (obrigatória)

```text
S5 → S8 → S7 (PASS pré-runtime)
  → GO lab (2026-08-09) → PoC-0 idle layer7-tlsproxy
  → PoC-1..3 em lab isolado (NÃO .254 prod) → medir S1–S4/S6
  → GO produto → só então 20.10
```

**Regra:** sem PoC runtime **não** se pode fechar S1–S4/S6.  
Pré-runtime fechável: **S5 + S7 + S8** (cumpridos `2026-08-09`).  
**GO lab ≠ 20.10** e **≠** install no appliance de produção.

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
| **S5** | Confirmar default `quic_mode=bypass` no schema/docs/GUI; escrever decisão (bypass vs block) | `S5-decisao-quic.md` | **Sim** — **PASS** `20260809T031200Z-s5-s7-pre-runtime` |
| **S8** | Appliance lab `1.9.38`, sinkhole + block page HTTP ≡ ADR-0017; `mitm_effective=false` | evidência two-client | **Sim** — **PASS** `20260809T024500Z-s8-adr0017-real-1.9.38` |
| **S7** | Checklist: sem dump de payload por defeito; paths de log só metadados; CA fora do git | `S7-politica-privacidade.md` | **Sim (política)** — **PASS** documental; auditoria PoC pendente |
| **S1** | CPU idle/load com e sem proxy sob carga definida | `s1-cpu/` | **Não** |
| **S2** | Handshake TLS p95 ≤ 150 ms via proxy | `s2-latency/` | **Não** |
| **S3** | ≥1 browser: block page HTTPS com CA lab | `s3-blockpage/` | **Não** |
| **S4** | Destino em bypass não termina TLS no proxy | `s4-bypass/` | **Não** |
| **S6** | ECH: comportamento observado + nota de limite | `s6-ech/` | **Não** |

---

## 4. Smoke S8 — **teste real** (sem intercept)

Pré-requisitos: pacote **`1.9.38`** no `.254`; clientes `.234`/`.235` acessíveis
do Mac; preferir mutações **scoped**; snapshot/rollback disponíveis.

Checklist mínimo (**real**):

1. Appliance: `layer7d -V` → `1.9.38` (shell; menu → **8**)
2. Stats: `mitm_effective=false`; `mitm_runtime_available=false`
3. Sem processos `layer7-tlsproxy` / Squid
4. **Cliente A e B:** `curl` real a `http(s)://example.com` (esperado 200) —
   prova de caminho sem MITM
5. Com política de block + sinkhole activa (quando aplicável): domínio bloqueado
   → DNS sinkhole → página HTTP ADR-0017 num dos clientes
6. Evidência em `docs/tests/evidence/<run_id>-s8-…/` com outputs dos **dois**
   clientes + appliance

**PASS S8:** ADR-0017 intacto (quando block activo) + `mitm_effective=false` +
sem runtime + two-client sem intercept.  
**FAIL S8:** qualquer intercept, página HTTPS via MITM, ou effective true.

Orquestração tipica:

```sh
# Mac → clientes
ssh root@192.168.100.234 'curl -sS -o /dev/null -w "%{http_code}\n" --max-time 12 http://example.com'
ssh root@192.168.100.235 'curl -sS -o /dev/null -w "%{http_code}\n" --max-time 12 http://example.com'
# Mac → appliance
ssh root@192.168.100.254 'layer7d -V; grep mitm_effective /var/db/layer7/layer7-stats.json'
```

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
| 2026-08-09 | Lab real obrigatório no runbook; S8 two-client real `20260809T022400Z-s8-real-two-client-1.9.38` |
| 2026-08-09 | **S8 PASS** ADR-0017 real (`20260809T024500Z-s8-adr0017-real-1.9.38`) — sem mutar config; sinkhole+página nos dois clientes |
| 2026-08-09 | **S5+S7 PASS documental** (`20260809T031200Z-s5-s7-pre-runtime`) — quic bypass + privacidade; 20.10 ainda bloqueado (S1–S4/S6+GO lab) |
| 2026-08-09 | **GO lab** — PoC-0 idle `src/layer7-tlsproxy/`; proibido intercept em `.254`/`.234`/`.235`; ver `poc-layer7-tlsproxy-lab.md` |
