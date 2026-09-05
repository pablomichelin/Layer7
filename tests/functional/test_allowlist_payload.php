<?php
/**
 * BG-174 / V5 Allowlist — entrada obsoleta (substituída por test_allowlist_payload.js).
 *
 * Não executa provas de paridade. Imprime SKIP explícito e aponta o gate Node/jsdom.
 * Fora de tests/run-local.sh — não confundir com o gate activo.
 *
 *   php tests/functional/test_allowlist_payload.php
 *
 * Gate activo:
 *   LAYER7_PHP=<php-wasm-cli> LAYER7_JSDOM=<jsdom> node tests/functional/test_allowlist_payload.js
 */
fwrite(STDOUT, "SKIP: test_allowlist_payload.php obsoleto — usar tests/functional/test_allowlist_payload.js (FormData jsdom + render-parity.php; Array.from(fd.entries()) completo)\n");
exit(0);
