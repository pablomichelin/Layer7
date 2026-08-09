# PoC lab — `layer7-tlsproxy` (pós GO lab)

**Estado:** `ACTIVO` — **PoC-1 PASS** (IPC lab); GO lab `2026-08-09`  
**NÃO é** passo **20.10** (intercept de produto).  
**Objectivo:** runtime isolado para **medir S1–S4/S6** sem tocar produção.

| Campo | Valor |
|-------|--------|
| GO lab | **SIM** (`2026-08-09`) |
| Fase actual | **PoC-1 PASS** — próximo **PoC-2** TLS em VM isolada |
| Produção `.254`/`.234`/`.235` | **PROIBIDO** intercept / rdr MITM / CA de lab nestes hosts |
| Builder | `192.168.100.12` — build + smoke |
| Binário | `src/layer7-tlsproxy/` (`0.0.1-poc1`) |
| Pacote `.pkg` | **Não** incluir no `pkg-plist` sem GO de empacotar |
| `mitm_effective` | Continua **false** (respostas IPC afirmam isto) |

---

## 1. O que o GO lab autoriza

1. Scaffold do processo `layer7-tlsproxy` no repositório.  
2. Build no **builder FreeBSD** (e smoke local).  
3. Modo **idle** + **IPC PING** lab-only.  
4. Documentação e evidências S1–S4/S6 **quando** houver VM lab isolada (PoC-2+).

## 2. O que o GO lab **NÃO** autoriza

| Proibido | Motivo |
|----------|--------|
| Instalar/activar intercept no `.254` de produção | Hosts funcionais em produção |
| `rdr` PF MITM / bind 443 nos clientes `.234`/`.235` | Risco operacional |
| Distribuir CA de lab via GPO real | Privacidade / S7 |
| Claim `mitm_effective=true` no produto | 20.10 ainda bloqueado |
| Squid | Rejeitado permanente |
| Empacotar no `.pkg` público sem GO | Canal `latest` ≠ PoC |
| Socket produto `/var/run/layer7/mitm.sock` neste PoC | Fail-closed; só `/tmp/` ou path relativo lab |

---

## 3. Fases da PoC

| Fase | Entrega | Gate |
|------|---------|------|
| **PoC-0** | Binário idle + Makefile + docs GO | **PASS** |
| **PoC-1** | IPC `PING` unix socket lab-only | **PASS** — evidência `20260809T031700Z-poc1-ipc-idle` |
| **PoC-2** | Terminação TLS mínima em **VM lab isolada** (não `.254` prod) | Medir S1/S2 |
| **PoC-3** | Bypass + block page HTTPS lab | S3/S4; S6 nota |
| **→ 20.10** | Só após S1–S8 medidos + GO produto | Plano IM2 |

---

## 4. Hardening PoC-1

```text
LAYER7_TLSPROXY_LAB=1 obrigatório para --ipc-serve / --ipc-ping / --lab-allow-bind
socket: /tmp/... ou path relativo (sem ..); /var/run/layer7/* recusado
frame: uint32 BE + JSON ≤ 4 KiB
PING → {"ok":true,"ts":…,"mitm_effective":false}
sem bind TCP, sem PF, sem CA
```

Smoke:

```bash
cd src/layer7-tlsproxy && make test
```

---

## 5. Rollback

- Remover árvore `src/layer7-tlsproxy/` se PoC abortada.  
- Nenhum artefacto no appliance de produção ⇒ rollback = no-op.  
- `mitm_runtime_available` no `layer7d` permanece `false` até decisão explícita.

---

## Histórico

| Data | Nota |
|------|------|
| 2026-08-09 | GO lab; PoC-0 idle |
| 2026-08-09 | **PoC-1 PASS** — IPC PING lab; evidência `20260809T031700Z-poc1-ipc-idle` |
