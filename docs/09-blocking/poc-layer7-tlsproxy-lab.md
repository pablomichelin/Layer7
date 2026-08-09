# PoC lab — `layer7-tlsproxy` (pós GO lab)

**Estado:** `ACTIVO` — **PoC-2 smoke PASS** em `192.168.100.54`; GO lab `2026-08-09`  
**NÃO é** passo **20.10** (intercept de produto).  
**Objectivo:** runtime isolado para **medir S1–S4/S6** sem tocar produção.

| Campo | Valor |
|-------|--------|
| GO lab | **SIM** (`2026-08-09`) |
| Fase actual | **PoC-2** — TLS lab em `.54`; próximo PoC-3 / S1 inline |
| Lab descartável | **`192.168.100.54`** (`root`) — autorizado para PoC destrutivo |
| Produção `.254`/`.234`/`.235` | **PROIBIDO** intercept / rdr MITM / CA de lab |
| Builder | `192.168.100.12` — build FreeBSD |
| Binário | `src/layer7-tlsproxy/` (`0.0.2-poc2`); no `.54`: `/opt/layer7-poc/` |
| Pacote `.pkg` | **Não** incluir no `pkg-plist` sem GO de empacotar |
| `mitm_effective` | Continua **false** |

---

## 1. O que o GO lab autoriza

1. Scaffold + IPC + TLS lab no repositório / VM `.54`.  
2. Build no **builder FreeBSD** e smoke em `.54`.  
3. Gerar CA/cert **só** em `/opt/layer7-poc/lab-certs/` na VM.  
4. Medições S* em lab isolado.

## 2. O que o GO lab **NÃO** autoriza

| Proibido | Motivo |
|----------|--------|
| Instalar/activar intercept no `.254` | Produção |
| `rdr` PF / bind MITM em `.234`/`.235` | Produção |
| Distribuir CA de lab via GPO real | S7 |
| Claim `mitm_effective=true` | 20.10 bloqueado |
| Squid | Rejeitado |
| Empacotar `.pkg` sem GO | Canal `latest` ≠ PoC |
| Socket produto `/var/run/layer7/mitm.sock` | Fail-closed no PoC |

---

## 3. Fases da PoC

| Fase | Entrega | Gate |
|------|---------|------|
| **PoC-0** | Idle | **PASS** |
| **PoC-1** | IPC `PING` | **PASS** — `20260809T031700Z-poc1-ipc-idle` |
| **PoC-2** | TLS mínimo em **`.54`** | **PASS smoke** — `20260809T041000Z-poc2-tls-lab-54` (S2 lab PASS; S1 produto PENDING) |
| **PoC-3** | Bypass + block page HTTPS lab | S3/S4; S6 nota |
| **→ 20.10** | S1–S8 + GO produto | Plano IM2 |

---

## 4. Hardening

```text
LAYER7_TLSPROXY_LAB=1 obrigatório
IPC: /tmp ou relativo; /var/run/layer7/* recusado
TLS: default 127.0.0.1; 0.0.0.0 só com --lab-allow-any (só .54)
respostas: mitm_effective=false
chaves: nunca no git
```

No `.54`:

```bash
cd /opt/layer7-poc/src && make test && make test-tls-lab
```

---

## 5. Rollback

- Parar processo no `.54`; remover `/opt/layer7-poc/` se desejado.  
- Produção: no-op.  
- `mitm_runtime_available` no `layer7d` permanece `false`.

---

## Histórico

| Data | Nota |
|------|------|
| 2026-08-09 | GO lab; PoC-0 idle |
| 2026-08-09 | PoC-1 PASS IPC |
| 2026-08-09 | Lab `.54` criado; **PoC-2** TLS localhost PASS (S2 p95≈2.8 ms) |
