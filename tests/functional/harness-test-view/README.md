# Harness V9 — Teste de políticas

Render isolado da **view** (`$pgtitle` → fim) de `layer7_test.php`.

- **Sem** prefixo congelado, `gethostbynamel`, `l7_run_policy_test` ou rede.
- Catálogo injectado: **fixture V3 Categories** (`l7hc_fixture_catalog_472()` — **472** protocolos `Proto001`..`Proto472`, **20** categorias `Cat01`..`Cat20`; **não** é o catálogo nDPI do appliance).
- `render-parity.php` devolve `{"baseline":"...","candidate":"..."}`; paridade compara **todas** as `<option>`/ordem baseline vs candidato.

```bash
export LAYER7_PHP=... LAYER7_JSDOM=...
node tests/functional/test_test_payload.js
```

Adversarial de domínio (`test_domain`) é cenário separado; fixture 472 mantém-se para selects.
