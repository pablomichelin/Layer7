# Gate D0 — Addendum: hipóteses TLS + hang `timeout` (sem código)

**Estado:** **PASS documental** (só evidência / repro; **zero** edição de produto)  
**Data:** `2026-08-09`  
**Base:** [`diagnostico-D0-phaseBD-mitm-20260809.md`](diagnostico-D0-phaseBD-mitm-20260809.md) (D0 original **PASS**)

---

## Declaração

| Campo | Valor |
|-------|--------|
| **Objectivo** | Apresentar causas **comprovadas** e hipóteses **descartadas** com evidência mínima reproduzível; parar onde não houver prova |
| **Impacto** | Só docs + pastas de evidência |
| **Risco** | Nenhum runtime |
| **Teste** | Harness CA-as-peer; matriz H1–H9; repro `timeout` sem `-k` no builder |
| **Rollback** | N/A |

**Proibido neste bloco:** editar `layer7.inc` / tlsproxy; correcção especulativa; reactivar MITM.

---

## A) NO-GO Edge `1.9.42` — causas

### Comprovado (causa-raiz)

| Afirmação | Prova |
|-----------|--------|
| Edge falhou com **`ERR_SSL_KEY_USAGE_INCOMPATIBLE`** | [`../tests/evidence/20260809T185035Z-phaseBD-mitm-254/remote/08-edge-dom.html`](../tests/evidence/20260809T185035Z-phaseBD-mitm-254/remote/08-edge-dom.html) |
| Peer = certificado **CA** (`CA:TRUE`, KU certSign/CRLSign) | [`…/06-mitm-ca.crt`](../tests/evidence/20260809T185035Z-phaseBD-mitm-254/06-mitm-ca.crt) + harness `run-local-tls-ca-peer.sh` **PASS** |
| Produto aponta CERT/KEY para a CA | `13-pkg-19242-wire.txt` na matriz |
| Chromium rejeita esse KU em handshake de servidor | alinhado ao código de erro observado (não `AUTHORITY_INVALID`) |

Matriz completa: [`../tests/evidence/20260809T204800Z-hypothesis-matrix-tls/`](../tests/evidence/20260809T204800Z-hypothesis-matrix-tls/).

### Descartado (não é a causa do DOM Edge)

Mismatch de fingerprint CA/Root, store errado, chain incompleta, cache antigo, clock skew, CA não carregada — ver tabela H1–H8 no README da matriz (**todos DESCARTADA** com ficheiros citados).

---

## B) Ciclo `1.9.43` `195101Z` — hang activação (F1-bis)

### Comprovado

| Afirmação | Prova |
|-----------|--------|
| Activação reportou `sync=fail` após **`sync_sec=141.646`** | [`../tests/evidence/20260809T195101Z-phaseBD-d1-254/06-phaseD-activate.txt`](../tests/evidence/20260809T195101Z-phaseBD-d1-254/06-phaseD-activate.txt) |
| Constante declarada `L7_CTRL_TIMEOUT_SERVICE=20` | leitura `layer7.inc` (sem editar) |
| `layer7_exec_timeout` invoca `timeout <sec> /bin/sh -c …` **sem** `-k` | mesma leitura |
| Em FreeBSD, `timeout SEC` **sem** `-k` pode **não retornar** se o filho ignora `SIGTERM` | repro builder [`…/15-timeout-nok-repro.txt`](../tests/evidence/20260809T195101Z-phaseBD-d1-254/15-timeout-nok-repro.txt) (`WITHOUT_K_PROVED_HANG=YES`) |
| Enquanto o sync estava preso, o peer em `:8443` já era **leaf** (`CN=mitm-lab.test`, issuer PhaseD) | observação RO na janela (antes do cleanup) |

### Não comprovado (parar aqui — sem correcção especulativa)

| Questão | Estado |
|---------|--------|
| `service layer7-tlsproxy onerestart` ignora `SIGTERM` de forma determinística? | **BLOQUEADO** — não há repro isolado `onerestart` instrumentado nesta evidência |
| Edge `.24` com leaf D1 → HTML Layer7? | **BLOQUEADO** — Edge MITM **não** correu no `195101Z` |
| `-k` no wrapper resolve o hang em produção? | **ETAPA 2 (candidata):** implementado + teste filho `trap '' TERM` PASS no builder; **B+D/Edge** ainda exige GO |

---

## Veredicto deste addendum

| Item | Resultado |
|------|-----------|
| Hipóteses TLS vs NO-GO Edge `1.9.42` | **Fechadas** (causa H9; restantes descartadas) |
| Hang `195101Z` | **Sintoma + mecanismo do wrapper `timeout` sem `-k` comprovados**; causa exacta do `onerestart` **não** fechada |
| Edição de produto / fix | **FORA** — D0 (diagnóstico) |
| **ETAPA 2 (pós-D0, GO implícito «CORRECÇÃO CANDIDATA»)** | `layer7_exec_timeout` com `timeout -k` + `L7_CTRL_TIMEOUT_KILL_GRACE`; testes builder PASS |

**Próximo:** build/publish `1.9.43` + GO B+D/Edge (não reactivar `.254` sem GO).
