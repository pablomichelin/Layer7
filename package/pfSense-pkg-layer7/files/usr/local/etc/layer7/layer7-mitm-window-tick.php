#!/usr/local/bin/php
<?php
/*
 * P4.1 — supervisor on-box da janela MITM.
 * Cron * * * * *: expire/cleanup sem GUI nem Mac.
 * Nunca activa mitm.enabled. Sem payload TLS / segredos.
 */
if (php_sapi_name() !== "cli") {
	exit(0);
}
$inc = "/usr/local/pkg/layer7.inc";
if (!is_file($inc)) {
	exit(0);
}
require_once $inc;
if (!function_exists("layer7_mitm_window_supervisor_tick")) {
	exit(0);
}
layer7_mitm_window_supervisor_tick();
exit(0);
