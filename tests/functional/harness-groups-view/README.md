# Harness isolado — Groups (BG-174 / V2)

**Não é pfSense. Não é o appliance. Não prova visual no host.**

`run.php` executa `layer7_groups.php` com stubs. Não carrega `guiconfig.inc`
nem `layer7.inc`. `layer7_save_json()` devolve sempre `false` para não
persistir nem seguir o `header`/`exit` de edição com sucesso.

Adaptação limitada do harness (não no produto): substitui `require_once` /
`head.inc` / `foot.inc`; se `layer7_group_policy_count` já existir no
processo, omite a redeclaração do helper do ficheiro real. **Não**
reimplementa a contagem nas stubs. O helper no produto fica como no
baseline, sem `function_exists`.

`Form_*` oficiais pinadas no vendor de Devices, mais `Textarea`/`Select`
do mesmo commit `9363ac5b` — ver
`../harness-devices-view/vendor/pfsense-form/CITATION.txt`.
`Form_Button` de submit usa `value` = legenda da acção (API oficial;
handlers só exigem truthy) e ícone `fa fa-*` da família já presente
no produto. Lote Devices manual continua `value="1"`.

```
php tests/functional/harness-groups-view/run.php
php tests/functional/test_groups_native_view.php
php tests/functional/test_groups_handlers_baseline.php
```

Avisos 8.3 do vendor: só se toleram mensagens conhecidas
(`$hidden` / `$help` / `$title` / `$target` / `id` / `type` em
`Form/Group` e `Form/Input`); as outras falham o harness.

Excepção de render: `layer7_render_footer()` não é chamado (emite
`style=`). O crédito Systemup fica em classes nativas, só nesta página.

Julgar por `PASS:`/`FAIL:` e `ALL … PASSED`, não só pelo exit do wrapper.
