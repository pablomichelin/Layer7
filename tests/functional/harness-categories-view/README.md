# Harness isolado — Categorias nDPI (BG-174 / V3)

**Não é pfSense. Não é o appliance. Não prova visual no host.**

`run.php` executa `layer7_categories.php` com stubs. Não carrega
`guiconfig.inc` nem `layer7.inc`. `layer7_ndpi_list()` devolve o fixture
injectado — não executa o daemon nem lê cache.

Adaptação limitada do harness (não no produto): substitui `require_once` /
`head.inc` / `foot.inc`. O bloco de dados (`layer7_ndpi_list` / `ksort` /
contagens / privilégios) permanece no ficheiro real.

```
php tests/functional/harness-categories-view/run.php
php tests/functional/test_categories_native_view.php
php tests/functional/test_categories_data_baseline.php
node tests/functional/test_categories_search.js
```

O teste Node (`test_categories_search.js`) resolve jsdom/PHP de forma
portátil via `tests/functional/lib/layer7-test-runtime.js`:

- `LAYER7_JSDOM` — caminho do módulo jsdom; senão `require("jsdom")`
- `LAYER7_PHP` — executável PHP; senão `php` no PATH

Ausência = FAIL explícito (nunca PASS silencioso). Não depende de
directórios desta máquina. Sem dependência no produto. Constrói o DOM
a partir do HTML da fonte real (`render-fixture.php` + stubs) e
executa só o `<script>` local. Recursos externos desligados. Prova
contrato estrutural `details`/`summary` e click; **não** inventa
Enter/Space nativos. Enter/Space no navegador real continuam
**pendentes**. Não é browser, não prova visual, layout ou tema.
Julgar por `PASS:`/`FAIL:` e `ALL … PASSED`, não só pelo exit do
wrapper.

Excepção de render: `layer7_render_footer()` não é chamado (emite
`style=`). O crédito Systemup fica em classes nativas, só nesta página.
