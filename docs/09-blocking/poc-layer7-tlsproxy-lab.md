# PoC lab — `layer7-tlsproxy` (pós GO lab)

**Estado:** `ACTIVO` — GO lab humano `2026-08-09`  
**NÃO é** passo **20.10** (intercept de produto).  
**Objectivo:** criar runtime isolado para **medir S1–S4/S6** sem tocar produção.

| Campo | Valor |
|-------|--------|
| GO lab | **SIM** (`2026-08-09`) |
| Produção `.254`/`.234`/`.235` | **PROIBIDO** intercept / rdr MITM / CA de lab nestes hosts neste PoC |
| Builder | `192.168.100.12` — build + smoke idle |
| Binário | `src/layer7-tlsproxy/` |
| Pacote `.pkg` | **Não** incluir no `pkg-plist` até smoke idle PASS + GO explícito de empacotar |
| `mitm_effective` | Continua **false** até gates de produto (20.10) |

---

## 1. O que o GO lab autoriza

1. Scaffold do processo `layer7-tlsproxy` no repositório.  
2. Build no **builder FreeBSD** (e CI local sem OpenSSL se necessário).  
3. Modo **idle**: `--version` / `--health` / recusa de bind sem flags de lab.  
4. Documentação e evidências de medição S1–S4/S6 **quando** houver ambiente de lab isolado.

## 2. O que o GO lab **NÃO** autoriza

| Proibido | Motivo |
|----------|--------|
| Instalar/activar intercept no `.254` de produção | Hosts funcionais em produção |
| `rdr` PF MITM / bind 443 nos clientes `.234`/`.235` | Risco operacional |
| Distribuir CA de lab via GPO real | Privacidade / S7 |
| Claim `mitm_effective=true` no produto | 20.10 ainda bloqueado |
| Squid | Rejeitado permanente |
| Empacotar no `.pkg` público sem GO | Canal `latest` ≠ PoC |

---

## 3. Fases da PoC

| Fase | Entrega | Gate |
|------|---------|------|
| **PoC-0** (este bloco) | Binário idle + Makefile + docs GO | `--version`/`--health`; **sem** listen |
| **PoC-1** | IPC `PING` unix socket lab-only | Fail closed sem `mitm_effective` |
| **PoC-2** | Terminação TLS mínima em **VM lab isolada** (não `.254` prod) | Medir S1/S2 |
| **PoC-3** | Bypass + block page HTTPS lab | S3/S4; S6 nota |
| **→ 20.10** | Só após S1–S8 medidos + GO produto | Plano IM2 |

---

## 4. Hardening do binário PoC-0

```text
default: sem bind, sem PF, sem CA load obrigatório
--lab-allow-bind: recusado a menos que env LAYER7_TLSPROXY_LAB=1
sem LAYER7_TLSPROXY_LAB: exit com mensagem "intercept disabled"
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
| 2026-08-09 | GO lab; PoC-0 idle iniciado |
