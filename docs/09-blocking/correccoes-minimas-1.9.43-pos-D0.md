# Correcções mínimas `1.9.43` (somente após D0)

**Pré-requisito:** Gate D0 + addendum **PASS** (causas comprovadas).  
**Regra:** uma correção mínima por causa; sem refactor; sem hipóteses descartadas.

| Causa comprovada (D0) | Correção mínima | Onde | Estado |
|-----------------------|-----------------|------|--------|
| **H9 / F2** — peer TLS = CA → `ERR_SSL_KEY_USAGE_INCOMPATIBLE` | Mint leaf por SNI (`serverAuth`, KU TLS, SAN=DNS:SNI); CA só como emissora | `src/layer7-tlsproxy/tls_lab.c` (0.1.3) | **Feito** (Gate D1) |
| **TLS identity** — CA/leaf inconsistente ou incompatível Chromium | Leaf: AKI/SKI + `l7_leaf_identity_ok` + fp log; CA gen `CA:TRUE`+SKI; import exige CA | tlsproxy `0.1.3` + `layer7_mitm_ca_*` | **Feito** |
| **TLS bypass** — ignore-certificate-errors / curl -k / CERT_NONE | Política + harness/PoC com verify obrigatório | `politica-tls-sem-bypass.md` + harness/tlsproxy scripts | **Feito** |
| **F1-bis** — `timeout SEC` sem `-k` não regressa se filho ignora TERM | `timeout -k <L7_CTRL_TIMEOUT_KILL_GRACE>` + fallback TERM→KILL | `layer7_exec_timeout()` | **Feito** (ETAPA 2) |
| **F1 / 195101Z** — sync falha/timeout mas helper já UP; cleanup derruba runtime bom | `onestop`+`onestart` (não `onerestart`); se falhar e `layer7_mitm_helper_listening()` → sucesso | `layer7_mitm_sync_helper()` | **Feito** (este bloco) |
| **F1 / 201504Z** — `timeout` sem `--foreground` mata process group do tlsproxy (~25s) | `timeout --foreground -k …` + `daemon -f` no rc.d | `layer7.inc` + `rc.d/layer7-tlsproxy` → **`1.9.44`** | **Feito** (prova sync `0.33s`) |
| **Scope** — source/dest vazios ou inválidos | Fail-closed: `layer7_mitm_intercept_scope_ok` em `mitm_effective` + validate GUI; sem helper/rdr | `layer7.inc` / `layer7_mitm.php` | **Feito** |
| **Scope** — `from any` / rdr genérico / IPv6 implícito / expansão silenciosa | Tokens proibidos + `layer7_mitm_rdr_line_ok` + validate raw (any/`::`/prefixo `</8`) | `layer7.inc` / GUI help | **Feito** |
| **Lifecycle** — falha sync deixa intenção ON / rdr órfão | Rdr exige gate+flag; `failsafe_rollback` (OFF+teardown+filter); cleanup com pkill se listen residual | `layer7.inc` / `layer7_mitm.php` | **Feito** |
| **Lifecycle** — `filter_configure` recursa / sem limite; enable/disable não idempotente | `layer7_filter_configure_safe` (anti-reentrada+lock+timeout); sync MITM skip se já no estado desejado | `layer7.inc` + GUIs | **Feito** |

## Fora de escopo (não corrigir aqui)

- Hipóteses H1–H8 descartadas (mismatch CA, store, chain, cache, clock, …)
- Publish / B+D Edge (exige GO humano)
- Alterar geração da CA para “parecer leaf” (errado: CA deve continuar CA)

## TESTES / GATES OBRIGATÓRIOS

SSOT completo: [`gates-obrigatorios-1.9.43-mitm.md`](gates-obrigatorios-1.9.43-mitm.md)

**Antes de publish** (todos PASS):

```sh
make -C src/layer7-tlsproxy test-regress
php package/pfSense-pkg-layer7/tests/test_mitm_regress.php
php tests/functional/test_ctrl_exec_timeout.php
sh tests/harness/mitm-activate-hang/run-local-timeout-fix.sh
php tests/functional/test_mitm_config.php
```

**Depois de publish:** GO humano B+D Edge `.24` sem bypass TLS; `.254` DEFER.

## Rollback

Pacote / working tree → `1.9.42` (sem mint / sem timeout -k / sync com `onerestart` legado).
