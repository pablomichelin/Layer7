# Evidência P4.1 — supervisor on-box (local)

**Estado:** PASS local `2026-08-13` — candidato `1.9.59`; **sem** publish; **sem** activar MITM.

## Teste

```text
/usr/local/mds-php/bin/php tests/functional/test_mitm_config.php
→ PASS test_mitm_config.php

/usr/local/mds-php/bin/php package/pfSense-pkg-layer7/tests/test_mitm_regress.php
→ PASS package/pfSense-pkg-layer7/tests/test_mitm_regress.php
```

## Cobertura

- Cron aponta a `layer7-mitm-window-tick.php`; pkg-install arma; deinstall desarma
- Tick com MITM OFF: stamp armado; `enabled` permanece false
- Tick com janela expirada (CA presente): expire + persist OFF
- API regress: `layer7_mitm_setup_window_cron` / `supervisor_tick` / `supervisor_status`

## Não feito neste bloco

- Build FreeBSD / GitHub Release
- Activação MITM em `.254` / `.234` / `.235`
- Retry P4 soak
- P5 / ficha de site
