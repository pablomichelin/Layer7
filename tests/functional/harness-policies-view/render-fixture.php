<?php
/**
 * Render edit/new da fonte real (stdout = HTML).
 * Usado por test_policies_filters.js / jsdom. Não é pfSense.
 *
 *   php render-fixture.php edit
 *   php render-fixture.php new
 */
require_once __DIR__ . "/bootstrap.php";

$mode = isset($argv[1]) ? (string)$argv[1] : "edit";
$g = l7hp_groups();

if ($mode === "new") {
	$data = l7hp_data(array(l7hp_policy("p-one", array("name" => "Uma", "priority" => 5))), $g);
	echo l7hp_render(array("get" => array("new" => "1"), "data" => $data));
	exit(0);
}

$data = l7hp_data(array(l7hp_full_policy()), $g);
echo l7hp_render(array("get" => array("edit" => "0"), "data" => $data));
