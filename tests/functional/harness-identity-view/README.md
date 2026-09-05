# Harness V12 — Identity

Render isolado da **view** (`$pgtitle` → fim) de `layer7_identity.php`.

- **Sem** prefixo congelado, LDAP/RADIUS/DC real, tokens/secrets reais ou rede.
- Fixtures injectam `$unlocked`, `$identity`, flags `*_set`, `$dc_token_once` sintético, `$ldap_test`.
- Fila Identity+MITM **20.37 FECHADA** — este harness cobre **só** apresentação.

```bash
export LAYER7_PHP=... LAYER7_JSDOM=...
"$LAYER7_PHP" tests/functional/test_identity_render.php
node tests/functional/test_identity_payload.js
```
