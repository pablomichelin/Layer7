<?php
/**
 * Contagem de forms toggle/unhide na biblioteca full (catálogo real).
 *
 *   php forms-parity-fixture.php
 */
require_once __DIR__ . "/bootstrap.php";

$all = l7hpl_repo_profiles_all();
$data = l7hp_data(array(
	l7hpl_profile_policy("social", array("name" => "Social ligado")),
));
$html = l7hpl_render(array(
	"get" => array("library" => "1"),
	"data" => $data,
	"profiles" => array("visible" => $all),
	"method" => "GET",
));
$forms = l7hpl_profile_post_forms($html);
echo count($forms);
