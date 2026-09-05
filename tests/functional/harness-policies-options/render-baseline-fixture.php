<?php
/**
 * Render baseline V4-B2 (modal manual) com fixture grupos/VIP.
 *
 *   php render-baseline-fixture.php groups2-vip
 */
require_once __DIR__ . "/bootstrap.php";

$scenario = isset($argv[1]) ? (string)$argv[1] : "groups2-vip";
$allowed = array("groups0", "groups2-vip", "groups2-vip-empty", "groups16-vip");
$scenarios = l7hpo_scenarios();

if (!in_array($scenario, $allowed, true) || !isset($scenarios[$scenario])) {
	fwrite(STDERR, "FAIL cenario baseline desconhecido: {$scenario}\n");
	exit(1);
}

echo l7hpo_render(array_merge($scenarios[$scenario], array("source" => L7HPO_BASELINE)));
