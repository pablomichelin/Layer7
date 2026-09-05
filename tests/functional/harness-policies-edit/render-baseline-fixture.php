<?php
/**
 * Render baseline editor — cenário por argv[1].
 */
require_once __DIR__ . "/bootstrap.php";

$scenario = isset($argv[1]) ? (string)$argv[1] : "edit-custom-get";
$scenarios = l7hpe_scenarios();

if (!isset($scenarios[$scenario])) {
	fwrite(STDERR, "FAIL cenario desconhecido: {$scenario}\n");
	exit(1);
}

echo l7hpe_render(array_merge($scenarios[$scenario], array("source" => L7HPE_BASELINE)));
