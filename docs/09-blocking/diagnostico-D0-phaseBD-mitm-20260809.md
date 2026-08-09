# Gate D0 — Diagnóstico B+D MITM (sem código)

**Estado:** **PASS** (diagnóstico fechado; **sem** programação; **sem** reactivação)  
**Data:** `2026-08-09`  
**Pacote observado:** `1.9.42` (`aa1e10b`; SHA `6bd6ba37…4c4b`)  
**Evidência B+D NO-GO:** [`../tests/evidence/20260809T185035Z-phaseBD-mitm-254/`](../tests/evidence/20260809T185035Z-phaseBD-mitm-254/)  
**Evidência abort rollback:** [`../tests/evidence/20260809T185719Z-abort-rollbackD-254/`](../tests/evidence/20260809T185719Z-abort-rollbackD-254/)  
**Docs resultado:** commit `1db7b29`

---

## Declaração

| Campo | Valor |
|-------|--------|
| **Objectivo** | Fechar causa-raiz das falhas do ciclo B+D sem mutar código nem reactivar MITM |
| **Impacto** | Só documental (SSOT + este gate); produção `.254` permanece passiva |
| **Risco** | Baixo (read-only + docs) |
| **Teste** | Correlação evidência Edge + CA PEM + `tls_lab.c` + sync helper |
| **Rollback** | N/A (sem mutação runtime/código) |

---

## Baseline RO pós-rollback (confirmado neste bloco)

| Check `.254` | Resultado |
|--------------|-----------|
| Pacote | `1.9.42` |
| Rota `198.18` | ausente |
| `:8443` / rdr MITM | ausentes |
| `mitm.enabled` / `mitm_effective` | `no` / `no` |
| `layer7-tlsproxy` | not running |
| GUI `:9999` | `200` |

---

## Falha 1 — `filter_configure` pendurado

| Item | Achado |
|------|--------|
| Sintoma | Activação PHP ficou >2 min em `filter_configure`; tlsproxy já UP, rdr ainda ausente |
| Mitigação na corrida | Kill do PHP pendurado + segundo `filter_configure` com timeout → rdr apareceu |
| Abort relacionado | `20260809T185719Z` — gate abort por hang no caminho de reload |
| Escopo PF final (janela D) | **Correcto:** `from <layer7_mitm_src>`=`.24` `to <layer7_mitm_dst>`=`198.18.0.10` → `127.0.0.1:8443`; zero `from any` MITM; zero IPv6 rdr |
| Classificação D0 | **Risco operacional / lifecycle** — não explica o DOM Edge |
| Atribuição refinada (pós-D0) | Chamada exacta que reteve o activador: `exec(service layer7-tlsproxy onerestart)` em `layer7_mitm_sync_helper` **antes** de `filter_configure`. Candidata **`1.9.43`**: `layer7_exec_timeout` + cleanup idempotente. |
| Hipótese residual PF | Reload PF longo / reentrância `filter_configure` — secundário se o hang for cortado no `onerestart` |

**Veredicto F1:** observado e mitigável; **não** é a causa do NO-GO Edge. Hardening control-plane em candidata `1.9.43` (lab).

---

## Falha 2 — Edge sem block page Layer7

### Observado

| Item | Valor |
|------|--------|
| URL | `https://mitm-lab.test/` |
| Screenshot | página Chromium/Edge PT: «Hum... Não consigo chegar a esta página» |
| Código | **`ERR_SSL_KEY_USAGE_INCOMPATIBLE`** |
| Marcador Layer7 | ausente (`EDGE_BLOCK_MARKER=NO`) |
| Abort | conforme runbook (sem bypass de alerta) |

### Causa-raiz (fechada)

O runtime produto apresenta o **certificado CA** como certificado TLS de servidor.

| Elo | Evidência |
|-----|-----------|
| Sync | `layer7_mitm_sync_helper()` escreve `LAYER7_TLSPROXY_CERT/KEY` = paths da **CA** (`ca.crt` / `ca.key`) |
| Listen | `src/layer7-tlsproxy/tls_lab.c` → `SSL_CTX_use_certificate_file(ctx, cert_path, …)` **sem** mint de leaf por SNI |
| CA gerada D | `openssl req -x509 …` via `layer7_mitm_ca_generate()` |
| Extensões CA | `Basic Constraints: CA:TRUE`; `Key Usage: Certificate Sign, CRL Sign` (sem `digitalSignature` / `keyEncipherment` / EKU `serverAuth`) |
| Browser | Chromium rejeita CA-only KU em handshake TLS → `ERR_SSL_KEY_USAGE_INCOMPATIBLE` |

Contraste **20.11 S3** (PASS em `.54`): peer `CN=blocked.test` **emitido por** CA efémera — leaf com uso de servidor, não a CA como peer.

### Não-causa (secundário)

| Hipótese | Estado |
|----------|--------|
| «Só falta trust da CA Phase D no `.24`» | **Insuficiente** — mesmo com Root trust da CA D, o peer continua a ser a CA (KU incompatível). Código de erro não é `ERR_CERT_AUTHORITY_INVALID`. |
| CA Fase C ≠ CA Fase D | Verdade (C=`Layer7-PhaseA-Ephemeral-CA` / D=`Layer7-PhaseD-Lab-CA`), mas é **ruído operacional**; a falha dominante é KU do peer. |
| Escopo PF / `from any` | **Descartado** — rdr estava correcto na janela. |

---

## Veredicto Gate D0

| Gate | Resultado |
|------|-----------|
| D0 — diagnóstico B+D | **PASS** |
| Programação / bump / reactivação MITM | **FORA** deste gate |
| Novo ciclo B+D | **Bloqueado** até fix de leaf TLS + GO humano |

**Seguinte (feito em D1):** mint leaf por SNI — ver [`gate-D1-leaf-sni-20260809.md`](gate-D1-leaf-sni-20260809.md) (**PASS local**). Novo B+D exige publish `1.9.43` + GO humano.

**Addendum hipóteses / hang (sem código):** [`diagnostico-D0-addendum-hipoteses-20260809.md`](diagnostico-D0-addendum-hipoteses-20260809.md) — matriz H1–H9 + F1-bis `timeout` sem `-k`.

---

## Critérios de saída D1 (pré-vista; não executar agora)

1. Peer TLS para `mitm-lab.test` = leaf (CN/SAN do SNI), issuer = CA MITM.  
2. Leaf: EKU `serverAuth` + KU compatível TLS (não `keyCertSign` como único uso).  
3. Edge `.24` com CA no Root → HTML block page Layer7 (sem bypass).  
4. Activação: control-plane com timeout finito (candidata `1.9.43`); `filter_configure` com observabilidade se ainda necessário.  
5. Novo GO humano antes de escrita `.254`.
