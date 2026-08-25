<?php
/**
 * BG-162 / BG-163 — driver CLI do install-ping (php -f, fail-open).
 */
$inc = getenv("LAYER7_INSTALL_PING_INC");
if (!is_string($inc) || $inc === "" || !is_readable($inc)) {
	$inc = "/usr/local/pkg/layer7-install-ping.inc";
}
if (!is_readable($inc)) {
	exit(0);
}

require_once $inc;
if (function_exists("layer7_install_ping_putenv_path")) {
	layer7_install_ping_putenv_path();
}

$inventory = in_array("--inventory", $argv, true)
	|| getenv("LAYER7_INSTALL_PING_INVENTORY") === "1";

try {
	if ($inventory) {
		echo json_encode(layer7_install_inventory(), JSON_UNESCAPED_SLASHES), "\n";
		exit(0);
	}
	layer7_install_ping_run();
} catch (Throwable $e) {
	if (function_exists("layer7_install_ping_load_state")
	    && function_exists("layer7_install_ping_save_state")) {
		$state = layer7_install_ping_load_state();
		$state["last_attempt"] = time();
		$state["last_error"] = substr($e->getMessage(), 0, 200);
		layer7_install_ping_save_state($state);
	}
}
exit(0);
