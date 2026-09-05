<?php
/**
 * Fixture navegação — lista com POST erro (sem redirect bookmark).
 *
 *   php render-nav-fixture.php post-error
 */
require_once __DIR__ . "/bootstrap.php";

$mode = isset($argv[1]) ? (string)$argv[1] : "post-error";
$all = l7hpl_repo_profiles_all();
$profiles = array("visible" => $all);
$data = l7hp_data(array(
	l7hpl_profile_policy("social", array("name" => "Social ligado")),
));

if ($mode === "post-error") {
	echo l7hpl_render(array(
		"get" => array(),
		"post" => array("save_policies" => "1"),
		"data" => $data,
		"profiles" => $profiles,
		"method" => "POST",
		"input_errors" => array("Erro simulado na lista"),
	));
	exit(0);
}

fwrite(STDERR, "FAIL modo desconhecido: {$mode}\n");
exit(1);
