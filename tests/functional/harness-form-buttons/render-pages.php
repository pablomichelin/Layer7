<?php
/**
 * HTML real (stubs) PT/EN/ES para legendas e FormData.
 * Catálogos = ficheiros lang do produto. Não é pfSense.
 */
$GLOBALS["l7_test_cat"] = array();
function l7_t($key)
{
	$cat = isset($GLOBALS["l7_test_cat"]) && is_array($GLOBALS["l7_test_cat"])
		? $GLOBALS["l7_test_cat"] : array();
	if (isset($cat[$key]) && is_string($cat[$key]) && $cat[$key] !== "") {
		return $cat[$key];
	}
	return $key;
}

function l7fb_catalogue($lang)
{
	$root = dirname(__DIR__, 3);
	$file = $root . "/package/pfSense-pkg-layer7/files/usr/local/etc/layer7/lang/" . $lang . ".php";
	if ($lang === "pt" || !is_file($file)) {
		return array();
	}
	$L7_STRINGS = array();
	include $file;
	return is_array($L7_STRINGS) ? $L7_STRINGS : array();
}

require_once dirname(__DIR__) . "/harness-devices-view/bootstrap.php";
require_once dirname(__DIR__) . "/harness-groups-view/bootstrap.php";

function l7fb_pages()
{
	$mac1 = l7h_mac(1);
	return array(
		"devices_edit" => l7h_render(array(
			"get" => array("edit" => $mac1),
			"inventory" => l7h_inventory(1),
		)),
		"devices_batch" => l7h_render(array(
			"get" => array("mode" => "batch"),
			"inventory" => l7h_inventory(2),
		)),
		"groups_edit" => l7hg_render(array(
			"get" => array("edit" => "0"),
			"data" => l7hg_data(array(l7hg_group("lab", array("name" => "Lab", "cidrs" => array("192.0.2.0/24"))))),
		)),
		"groups_new" => l7hg_render(array(
			"get" => array("new" => "1"),
			"data" => l7hg_data(array()),
		)),
		"groups_list" => l7hg_render(array(
			"data" => l7hg_data(array(l7hg_group("lab", array("name" => "Lab", "cidrs" => array("192.0.2.0/24"))))),
		)),
		"legends" => array(
			"save_aliases" => l7_t("Gravar aliases"),
			"assign_to_group" => l7_t("Atribuir a grupo"),
			"save_group_edit" => l7_t("Guardar alteracoes"),
			"add_group" => l7_t("Adicionar grupo"),
			"resync_devices" => l7_t("Resync IPs dos dispositivos"),
			"delete_group" => l7_t("Remover"),
		),
	);
}

$out = array();
foreach (array("pt", "en", "es") as $lang) {
	$GLOBALS["l7_test_cat"] = l7fb_catalogue($lang);
	$out[$lang] = l7fb_pages();
}

echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
