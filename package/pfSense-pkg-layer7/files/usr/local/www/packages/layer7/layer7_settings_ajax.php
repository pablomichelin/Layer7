<?php
##|+PRIV
##|*IDENT=page-services-layer7-settings
##|*NAME=Services: Layer 7 (settings ajax)
##|*DESCR=Allow access to Layer 7 settings ajax.
##|*MATCH=layer7_settings_ajax.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

header("Cache-Control: no-cache, no-store");

$action = isset($_GET["action"]) ? (string)$_GET["action"] : "";

if ($action === "check_update") {
	header("Content-Type: application/json; charset=utf-8");
	$check = layer7_check_for_update();
	echo json_encode($check, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	exit;
}

header("HTTP/1.1 400 Bad Request");
header("Content-Type: application/json; charset=utf-8");
echo json_encode(array("ok" => false, "error" => "Unknown action"));
