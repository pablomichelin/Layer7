# Harness V11 — Remoção

Render isolado da **view** (`$pgtitle` → fim) de `layer7_removal.php`.

- **Sem** prefixo congelado, `pkg delete`, `nohup`, flags `/var/run` ou rede.
- Fixtures injectam `$pkg_installed`, `$job_running`, `$log_rm` (estados sintéticos).
- `render-parity.php` devolve `{"baseline":"...","candidate":"..."}`; payload compara FormData baseline vs candidato.

```bash
export LAYER7_PHP=... LAYER7_JSDOM=...
"$LAYER7_PHP" tests/functional/test_removal_render.php
node tests/functional/test_removal_payload.js
```

**Não autoriza** teste destrutivo em appliance — apenas render com fixtures.
