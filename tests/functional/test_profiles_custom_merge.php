<?php
/**
 * BG-070 — merge profiles-custom.json overlay sobre profiles.json de fabrica.
 */
$root = dirname(__DIR__, 2);
require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

$tmpdir = sys_get_temp_dir() . "/layer7-profiles-test-" . getmypid();
@mkdir($tmpdir, 0777, true);

$factory = $root . "/package/pfSense-pkg-layer7/files/usr/local/etc/layer7/profiles.json";
if (!copy($factory, $tmpdir . "/profiles.json")) {
	fwrite(STDERR, "FAIL: nao foi possivel copiar profiles.json\n");
	exit(1);
}

putenv("LAYER7_PROFILES_TEST_DIR=" . $tmpdir);

$base_count = count(layer7_profiles_factory_load());
if ($base_count < 72) {
	fwrite(STDERR, "FAIL: fabrica esperava >=72 perfis, got {$base_count}\n");
	exit(1);
}

$custom = array(
	"version" => 1,
	"overrides" => array(
		"youtube" => array(
			"hosts_add" => array("yt-custom.example.com"),
			"apps_add" => array(),
			"apps_remove" => array(),
			"hosts_remove" => array(),
			"hidden" => false,
		),
		"facebook" => array(
			"hidden" => true,
		),
	),
	"custom_profiles" => array(
		array(
			"id" => "c-lab-test",
			"name" => "Lab Test",
			"description" => "Perfil de teste",
			"icon" => "fa-flask",
			"group" => "Personalizados",
			"ndpi_apps" => array("YouTube"),
			"ndpi_categories" => array(),
			"hosts" => array("lab.example.com"),
		),
	),
);
file_put_contents(
	$tmpdir . "/profiles-custom.json",
	json_encode($custom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

$merged = layer7_load_profiles();
$visible = layer7_load_profiles(false);
$all = layer7_load_profiles(true);

if (count($merged) !== $base_count) {
	fwrite(STDERR, "FAIL: merge count esperado {$base_count} (+" .
	    "1 custom -1 hidden), got " . count($merged) . "\n");
	exit(1);
}

$yt = null;
$fb_visible = false;
$custom_found = false;
foreach ($merged as $p) {
	if (($p["id"] ?? "") === "youtube") {
		$yt = $p;
	}
	if (($p["id"] ?? "") === "facebook") {
		$fb_visible = true;
	}
	if (($p["id"] ?? "") === "c-lab-test") {
		$custom_found = true;
	}
}
if ($yt === null || !in_array("yt-custom.example.com", $yt["hosts"] ?? array(), true)) {
	fwrite(STDERR, "FAIL: override youtube hosts_add nao aplicado\n");
	exit(1);
}
if ($fb_visible) {
	fwrite(STDERR, "FAIL: facebook hidden ainda visivel na grelha\n");
	exit(1);
}
if (!$custom_found) {
	fwrite(STDERR, "FAIL: perfil personalizado c-lab-test ausente\n");
	exit(1);
}

$fb_in_all = false;
foreach ($all as $p) {
	if (($p["id"] ?? "") === "facebook") {
		$fb_in_all = true;
	}
}
if (!$fb_in_all) {
	fwrite(STDERR, "FAIL: facebook hidden deve existir com include_hidden\n");
	exit(1);
}

$catalog = layer7_profiles_catalog();
if (empty($catalog["apps"]) || !isset($catalog["apps_set"]["YouTube"])) {
	fwrite(STDERR, "FAIL: catalogo de apps vazio ou sem YouTube\n");
	exit(1);
}

@unlink($tmpdir . "/profiles-custom.json");
@unlink($tmpdir . "/profiles.json");
@rmdir($tmpdir);
putenv("LAYER7_PROFILES_TEST_DIR");

echo "PASS: test_profiles_custom_merge\n";
exit(0);
