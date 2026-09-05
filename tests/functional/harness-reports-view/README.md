# Harness V10 — Relatórios

Render isolado da **view** (`$pgtitle` → fim) de `layer7_reports.php`.

- **Sem** prefixo congelado, SQLite, ingestão, `layer7_reports_clear_all` real ou rede.
- Fixtures sintéticas (`bootstrap.php` → `l7hr_fixture_vars`): summary, timeline, tops, eventos, erros de ingest/detail.
- Stub `resolveIdentityByIp` via `$GLOBALS["l7hr_identity"]`.
- `render-parity.php` devolve `{"baseline":"...","candidate":"..."}`; paridade export compara **hrefs** baseline vs candidato com os mesmos queryparams.

```bash
export LAYER7_PHP=... LAYER7_JSDOM=...
"$LAYER7_PHP" tests/functional/test_reports_render.php
node tests/functional/test_reports_payload.js
node tests/functional/test_reports_js.js
```

Gate JS (`test_reports_js.js`): Chart stub local; fallback `#l7r-chart-empty` via `classList`; confirmação `clear_all_reports` com `installSubmitHandler` + `dispatchEvent(submit)` (não exige aspas na serialização `outerHTML` do jsdom).
