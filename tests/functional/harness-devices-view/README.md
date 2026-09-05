# Harness isolado — Devices (BG-174 / V1-B)

**Não é pfSense. Não é o appliance. Não prova visual no host.**

`run.php` **executa** `layer7_devices.php` (o ficheiro actual do produto)
com stubs de inventário/grupos/mensagens. Não carrega `guiconfig.inc` nem
`/usr/local/pkg/layer7.inc`. Os HTML manuais deixaram de ser evidência.

`Form_*` são as classes oficiais do pfSense pinadas em
`vendor/pfsense-form/` — ver `CITATION.txt` (commit/hash e construtores).
Uma imitação de Form **não** é usada. CSRF não é emitido por estas classes;
no appliance o csrf-magic entra via `head.inc`. O harness não inventa token
no HTML.

Dados: RFC 5737 (`192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24`) e
MACs `aa:00:00:xx:xx:xx`. Sem dados reais.

HTML gerado em runtime vai para `generated/` (gitignored; evidência, não
produto). O PHP WASM pode não persistir escritas em `/tmp` do anfitrião.

```
php tests/functional/harness-devices-view/run.php
php tests/functional/test_devices_batch_payload.php
php tests/functional/test_devices_native_view.php
php tests/functional/test_devices_handlers_baseline.php
```

Julgar por linhas `PASS:` / `FAIL:` e pelo marcador `ALL … PASSED`, não
só pelo exit do wrapper npm.
