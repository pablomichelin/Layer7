<?php
/**
 * Fixture biblioteca — perfil oculto com politica activa + override factory.
 *
 *   php render-hidden-active.php
 */
require_once __DIR__ . "/bootstrap.php";

$all = l7hpl_repo_profiles_all();
$facebook = null;
$visible = array();
foreach ($all as $p) {
	if (is_array($p) && ($p["id"] ?? "") === "facebook") {
		$facebook = $p;
	}
	if (count($visible) < 3) {
		$visible[] = $p;
	}
}
if ($facebook !== null && !in_array($facebook, $visible, true)) {
	$visible[] = $facebook;
}

$hidden_active = array(
	"id" => "c-hidden-active",
	"name" => "Oculto activo harness",
	"group" => "Personalizados",
	"icon" => "fa-eye-slash",
	"description" => "Hidden com politica ligada",
	"ndpi_apps" => array("HTTP"),
	"ndpi_categories" => array(),
	"hosts" => array("hidden-active.example"),
	"hidden" => true,
);
$hidden_only = array($hidden_active);
$custom_store = array(
	"custom" => array($hidden_active),
	"overrides" => array(
		"facebook" => array(
			"hidden" => false,
			"hosts_add" => array("override.example"),
		),
	),
);
$data = l7hp_data(array(
	l7hpl_profile_policy("social", array("name" => "Social activo")),
	l7hpl_profile_policy("c-hidden-active", array("name" => "Oculto activo")),
));

echo l7hpl_render(array(
	"get" => array("library" => "1"),
	"data" => $data,
	"profiles" => array(
		"visible" => $visible,
		"hidden" => $hidden_only,
		"custom" => $custom_store,
	),
	"method" => "GET",
));
