# Harness V8 — Diagnósticos

Render isolado da **view** (`$pgtitle` → fim) de `layer7_diagnostics.php`.

- **Sem** prefixo congelado, `exec`, coleta PF ou rede.
- Fixtures JSON mínimas (`bootstrap.php` → `l7hd_fixture_vars`).
- `render-parity.php` devolve `{"baseline":"...","candidate":"..."}`.

Uso (runtime via env, sem paths hardcoded no repo):

```bash
export LAYER7_PHP=... LAYER7_JSDOM=...
node tests/functional/test_diagnostics_payload.js
node tests/functional/test_diagnostics_js.js
```

Gate JS (`test_diagnostics_js.js`): delimitador aspas simples no HTML **fonte** PHP; no DOM usa `getAttribute("onsubmit")` decodificado + `installSubmitHandler` + `dispatchEvent(submit)` (não exige aspas na serialização `outerHTML` do jsdom).
