# Harness isolado — Policies (BG-174 / V4-A)

**Não é pfSense. Não é o appliance. Não prova visual no host.**

`run.php` executa `layer7_policies.php` (modos list/new/edit/view) com stubs.
Não carrega `guiconfig.inc` nem `layer7.inc`. `layer7_load_profiles()` devolve
`[]` neste subbloco (biblioteca/modais = V4-B). `layer7_save_json()` devolve
sempre `false`.

`Form_*` oficiais pinadas no vendor de Devices (`9363ac5b`), incluindo
`Checkbox`/`Textarea`/`Select`.

```
php tests/functional/harness-policies-view/run.php
php tests/functional/test_policies_native_view.php
php tests/functional/test_policies_handlers_baseline.php  # prefixo pinado + SHA em source-baseline.php
LAYER7_JSDOM=... LAYER7_PHP=... node tests/functional/test_policies_filters.js
```

HTML gerado em `generated/` (gitignored). Julgar por `PASS:`/`FAIL:` e
`ALL … PASSED`, não só pelo exit do wrapper PHP.
