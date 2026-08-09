# TESTES / GATES OBRIGATÓRIOS — candidata MITM `1.9.43`

**Estado:** **Gate B PASS** + **Gate C PASS** (`2026-08-09`) — release **`1.9.46`**  
**SSOT de correções:** [`correccoes-minimas-1.9.43-pos-D0.md`](correccoes-minimas-1.9.43-pos-D0.md)  
**Política TLS:** [`politica-tls-sem-bypass.md`](politica-tls-sem-bypass.md)  
**Prova sync foreground:** [`../tests/evidence/20260809T202500Z-sync-timeout-foreground-fix/`](../tests/evidence/20260809T202500Z-sync-timeout-foreground-fix/)  
**Gate C Edge:** [`../tests/evidence/20260809T210753Z-phaseBD-d1-254/`](../tests/evidence/20260809T210753Z-phaseBD-d1-254/) — **PASS** sem `--disable-quic`  
**Diagnóstico QUIC (histórico):** [`../tests/evidence/20260809T204452Z-phaseBD-d1-254/`](../tests/evidence/20260809T204452Z-phaseBD-d1-254/) — c/ `--disable-quic` **≠** PASS

MITM produção `.254` permanece **DEFER** (GO + runbook).

---

## A) Gates documentais (já fechados ou pendentes)

| Gate | Critério | Estado | Doc / evidência |
|------|----------|--------|-----------------|
| **D0** | Causas B+D comprovadas (peer=CA / hang timeout) | **PASS** | [`diagnostico-D0-phaseBD-mitm-20260809.md`](diagnostico-D0-phaseBD-mitm-20260809.md) + addendum |
| **D1** | Peer = leaf `serverAuth`+SAN; CA não é peer | **PASS local** | [`gate-D1-leaf-sni-20260809.md`](gate-D1-leaf-sni-20260809.md) |
| **GI2/GI3** | Lab MITM pré-1.9.43 | **PASS** (histórico) | `20260809T060000Z-20.11-gi2-gi3-54` |
| **B+D Edge `.24`** | HTML Layer7 «acesso bloqueado» **sem** bypass TLS **nem** `--disable-quic` | **PASS** | `20260809T210753Z-phaseBD-d1-254` (`1.9.46`) |
| **Produção `.254`** | MITM OFF salvo GO + runbook | **DEFER** | sem activação neste bloco |

---

## B) Testes obrigatórios locais / builder (antes de publish)

Correr no builder (`192.168.100.12`) ou host com `cc`+`openssl`+`php`:

```sh
# 1) Regressão junto ao tlsproxy (D1 + sem bypass nos scripts)
make -C src/layer7-tlsproxy test-regress

# 2) Regressão junto ao pacote (scope/rdr/lifecycle/filter_configure_safe)
php package/pfSense-pkg-layer7/tests/test_mitm_regress.php

# 3) Control-plane timeout finito (+ failsafe)
php tests/functional/test_ctrl_exec_timeout.php

# 4) Timeout hang fix (mock service)
sh tests/harness/mitm-activate-hang/run-local-timeout-fix.sh

# 5) Suite MITM config completa (CA/rdr/effective)
php tests/functional/test_mitm_config.php
```

| # | Comando | Obrigatório para | PASS mínimo | Estado |
|---|---------|------------------|-------------|--------|
| 1 | `make -C src/layer7-tlsproxy test-regress` | publish `.pkg` | exit 0; leaf verify+hostname; sem `curl -k`/`CERT_NONE` | **PASS** |
| 2 | `php …/tests/test_mitm_regress.php` | publish `.pkg` | exit 0; rdr/failsafe/safe-filter | **PASS** |
| 3 | `php tests/functional/test_ctrl_exec_timeout.php` | publish `.pkg` | exit 0; `-k` mata filho ignore-TERM | **PASS** |
| 4 | `run-local-timeout-fix.sh` | publish `.pkg` | sync falha limpa sem hang | **PASS** |
| 5 | `php tests/functional/test_mitm_config.php` | publish `.pkg` | exit 0 | **PASS** |

**Opcional mas recomendado:** `sh tests/run-local.sh` (inclui 2+3+1 quando php/cc disponíveis).

---

## C) Gates obrigatórios pós-publish (lab / humano)

| Gate | Critério | Proibido |
|------|----------|----------|
| Install `1.9.46` no lab | `pkg` + SHA; tlsproxy `0.1.3+` | activar em `.254` sem GO |
| Trust CA no Windows `.24` | `LocalMachine\Root` thumb = CA appliance | `--ignore-certificate-errors` |
| Edge real `.24` + MITM scoped | DOM/HTML Layer7 block page **sem** flags Edge | `--disable-quic`, `--ignore-certificate-errors`, interstitial como PASS |
| Scope PF | rdr + anti-QUIC `from <layer7_mitm_src> to <layer7_mitm_dst>` | `from any`, rdr/quic genérico, inet6 indevido |
| Rollback | MITM OFF; gate/flag/tabelas/regras QUIC limpos; GUI/Internet OK | deixar helper/rdr/quic órfãos |

---

## D) Critérios de rejeição imediata (FAIL)

- Peer TLS = CA (`CA:TRUE` / KU certSign) → `ERR_SSL_KEY_USAGE_INCOMPATIBLE`
- Activação hung / `filter_configure` sem limite / sync sem timeout `-k`
- Rdr emitido sem gate+flag materializados
- Validate/activação com `any`, `0.0.0.0/0`, IPv6 ou prefixo `</8`
- Prova Edge/Chrome com bypass de certificado
- Mutação `.234`/`.235` ou MITM produção sem GO

---

## E) Ordem segura de avanço

```text
D0 PASS → correcções 1.9.43/44/45/46 → B) testes builder PASS
  → C) B+D Edge .24 sem flags PASS (210753Z)
  → publish 1.9.46 .pkg+SHA  ← FEITO
  → GO produção .254 + runbook (humano; DEFER)
```
