# Testes próximos ao pacote `pfSense-pkg-layer7`

| Script | Cobertura |
|--------|-----------|
| `test_mitm_regress.php` | Regressões MITM `1.9.43`: scope, rdr, lifecycle, `filter_configure_safe`, CA |

```sh
php package/pfSense-pkg-layer7/tests/test_mitm_regress.php
# ou via suite local:
sh tests/run-local.sh
```

TLS leaf / bypass: `make -C src/layer7-tlsproxy test-regress`.
