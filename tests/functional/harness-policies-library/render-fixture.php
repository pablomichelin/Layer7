<?php
/**
 * Render biblioteca da fonte real (stdout = HTML).
 *
 *   php render-fixture.php library
 *   php render-fixture.php list
 */
require_once __DIR__ . "/bootstrap.php";

$mode = isset($argv[1]) ? (string)$argv[1] : "library";
$all = l7hpl_repo_profiles_all();
$profiles = array("visible" => $all);
$data = l7hp_data(array(
	l7hpl_profile_policy("social", array("name" => "Social ligado")),
));

if ($mode === "list") {
	echo l7hpl_render(array(
		"get" => array(),
		"data" => $data,
		"profiles" => $profiles,
		"method" => "GET",
	));
	exit(0);
}

echo l7hpl_render(array(
	"get" => array("library" => "1"),
	"data" => $data,
	"profiles" => $profiles,
	"method" => "GET",
));
