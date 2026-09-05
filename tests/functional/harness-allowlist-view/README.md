# Harness isolado — Allowlist (BG-174 / V5)

**Não é pfSense. Não é o appliance. Não prova visual no host.**

`run.php` executa `layer7_allowlist.php` com stubs. Não carrega `guiconfig.inc`
nem `layer7.inc` completo. `layer7_save_json()` é rastreado e nunca persiste
fora do processo; `layer7_dst_allowlist_apply_to_pf()`, `layer7_signal_reload()`
e `layer7_filter_configure_safe()` só incrementam contadores.

`l7_allow_normalize_input` / `l7_allow_classify` são omitidos no `eval` após a
primeira carga para evitar redeclare fatal. Validadores `layer7_ipv4_valid` /
`layer7_ipv6_valid` / `layer7_cidr_valid` / `layer7_cidr6_valid` são extraídos
por leitura de `layer7.inc` (funções reais, sem copiar regras à mão). Isto
alimenta o handler congelado no eval — **não** substitui `test_allowlist.c` nem
afirma suite de validação isolada.

Residual fora do bloco visual: o normalizador descarta silenciosamente linhas
com caracteres fora da regex antes do classificador. Falha silenciosa de
`layer7_save_json()` não emite mensagem (handler inalterado), mas a view
preserva o texto POST quando não há `$savemsg`.

```
php tests/functional/harness-allowlist-view/run.php
php tests/functional/test_allowlist_native_view.php
php tests/functional/test_allowlist_handlers_baseline.php
php tests/functional/test_allowlist_native_view.php
LAYER7_PHP=<php-wasm-cli> LAYER7_JSDOM=<jsdom> node tests/functional/test_allowlist_payload.js
```

`form-original.html` na pasta baseline é fixture manual auxiliar (ver `README-form-original.md`), **não** evidência de paridade. A paridade usa render real do `layer7_allowlist.php` pinado via `render-parity.php` + FormData jsdom.

| Artefacto | SHA256 |
|-----------|--------|
| `baseline-v5-allowlist/layer7_allowlist.php` (ficheiro) | `f36e0a42…` |
| prefixo até `$seed_entries` | `b2643919…` |
| `baseline-v5-allowlist/form-original.html` | `a5aa0ccf…` |

O `require_once("classes/Form.class.php")` fica **depois** da carga de dados.

Avisos 8.3 do vendor Form_*: só mensagens conhecidas; as outras falham o harness.

Justificativa de copy (sem ensaio de tráfego): `src/layer7d/main.c` avalia
decisão de bloqueio antes do gate DNS/allowlist; blacklist SNI exclui domínios
da allowlist; regras nativas do pfSense não são anuladas por `L7ALLOW`.
