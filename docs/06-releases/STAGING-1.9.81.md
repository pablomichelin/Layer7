# Staging explícito — candidato `1.9.81` (sem commit)

**Gerado:** 2026-09-05 — aguarda diff review do gerente.

## Regra

- **Não** usar `git add .` nem `git add -A`.
- **Não** incluir `artifacts/`, evidence antiga, VIP/commercial, untracked históricos fora do redesign.

## Produto (views + versão)

```
package/pfSense-pkg-layer7/Makefile
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_settings.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_status.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_devices.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_groups.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_categories.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_policies.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_allowlist.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_exceptions.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_events.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_diagnostics.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_test.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_reports.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_removal.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_identity.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_mitm.php
package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_blacklists.php
package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc
package/pfSense-pkg-layer7/files/usr/local/etc/layer7/lang/pt.php
package/pfSense-pkg-layer7/files/usr/local/etc/layer7/lang/en.php
package/pfSense-pkg-layer7/files/usr/local/etc/layer7/lang/es.php
```

## Testes V1–V15 (novos desta sessão)

```
tests/functional/baseline-v*-*/
tests/functional/harness-*-view/
tests/functional/test_*_freeze.php
tests/functional/test_*_native_view.php
tests/functional/test_*_render.php
tests/functional/test_*_payload.js
tests/functional/test_*_js.js
tests/functional/test_devices_native_view.php
tests/functional/test_policies_native_view.php
```

## Documentação

```
CORTEX.md
docs/changelog/CHANGELOG.md
docs/10-license-server/MANUAL-INSTALL.md
docs/MANUAL-PRODUTO.md
docs/13-runbooks/rollback.md
docs/06-releases/candidato-1.9.81-redesign-visual.md
docs/06-releases/STAGING-1.9.81.md
docs/00-overview/relatorio-gerencial-redesign-2026-09-05.md
docs/01-architecture/frontend-redesign-inventario-paridade.md
docs/02-roadmap/plano-redesign-frontend.md
docs/02-roadmap/roadmap.md
docs/02-roadmap/backlog.md
docs/02-roadmap/checklist-mestre.md
docs/tests/README.md
scripts/release/README.md
```

## Excluir explicitamente

- `artifacts/**`
- `docs/tests/evidence/**` (salvo GO)
- Untracked antigos não listados acima
- `layer7_settings_update.js` (não alterado)

## Coerência pré-commit

```sh
grep -n 'releases/download/v' docs/10-license-server/MANUAL-INSTALL.md  # só v1.9.81
export LAYER7_PHP=/private/tmp/layer7-php-runtime/node_modules/.bin/php-wasm-cli
export LAYER7_JSDOM=/private/tmp/layer7-dom-runtime/node_modules/jsdom
# gates V15 (exemplo)
$LAYER7_PHP tests/functional/test_settings_*.php
node tests/functional/test_settings_*.js
git diff --check
```
