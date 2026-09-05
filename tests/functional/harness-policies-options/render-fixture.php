<?php
/**
 * Render fixture Opções — cenário por argv[1].
 *
 *   php render-fixture.php groups2-vip
 */
require_once __DIR__ . "/bootstrap.php";

$scenario = isset($argv[1]) ? (string)$argv[1] : "groups2-vip";
$scenarios = l7hpo_scenarios();

if (!isset($scenarios[$scenario])) {
	fwrite(STDERR, "FAIL cenario desconhecido: {$scenario}\n");
	exit(1);
}

echo l7hpo_render(array_merge($scenarios[$scenario], array("source" => L7HP_CANDIDATE)));
