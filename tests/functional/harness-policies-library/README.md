# Harness V4-B1 — biblioteca de perfis

Executa `layer7_policies.php` com catálogo real de `profiles.json` e stubs isolados.
Compara baseline V4-B pinado com o candidato.

| Item | Valor |
|------|-------|
| Baseline | `source-baseline-v4b.php` (origem: revisão gerente V4-A, pré-bloco B1) |
| SHA256 | `5c0ff398155a4e64d53a8fb5b5b47fd17a35f767da2effd8f6a70e6dddb8951a` |

```
php tests/functional/harness-policies-library/run.php
LAYER7_JSDOM=... LAYER7_PHP=... node tests/functional/test_policies_library.js
```

Incluído em `tests/run-local.sh` (`harness-policies-library` + `test_policies_library`).

Cenários: navegação bookmark, library full (105 perfis), limit24, catálogo vazio,
custom/hidden, **hidden activo** (`render-hidden-active.php`), POST retry,
**payload integral** dos 105 forms toggle/unhide (method/action/campos/valores)
baseline vs candidato, modais/JS rascunho byte-idênticos. Não é pfSense,
appliance, visual nem CSRF real.

Complemento jsdom cumulativo: `tests/functional/test_policies_library.js` (navegação,
details, rascunho, apply batch, filtro oninput/onchange, fallback form.submit,
flags redirect, contagem 105 forms via `forms-parity-fixture.php`).

HTML gerado em `generated/` (gitignored; evidência local, não produto). Julgar por
`PASS:`/`FAIL:` e `ALL … PASSED`, não só pelo exit do wrapper PHP.
