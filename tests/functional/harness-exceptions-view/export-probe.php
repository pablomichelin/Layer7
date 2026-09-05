<?php
/**
 * Probe export_vip_list — eval do handler REAL (mesma extraccao que export-subprocess).
 * Instrumenta apenas exit -> L7heExportProbeExit; captura echo via ob; reporta efeitos.
 *
 * Entrada JSON argv[1]: {"data":{...}}
 * Saida JSON: {"text":"...","save_calls":0,"resync_calls":0,"handler_exit":true}
 */
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/export-handler-lib.php";

if (!class_exists("L7heExportProbeExit", false)) {
	class L7heExportProbeExit extends Exception
	{
	}
}

$raw = isset($argv[1]) ? (string)$argv[1] : "";
$opts = json_decode($raw, true);
if (!is_array($opts)) {
	fwrite(STDERR, "FAIL JSON invalido\n");
	exit(2);
}

l7he_reset_tracking();
$_POST = array("export_vip_list" => "1");
$_GET = array();
$_SERVER["REQUEST_METHOD"] = "POST";
$GLOBALS["l7he_data"] = isset($opts["data"]) && is_array($opts["data"])
	? $opts["data"] : l7he_vip_data(array());

$handler = l7he_export_handler_instrument_exit(l7he_export_handler_source());

ob_start();
$handler_exit = false;
try {
	eval($handler);
} catch (L7heExportProbeExit $e) {
	$handler_exit = true;
}
$text = ob_get_clean();

if (!$handler_exit) {
	fwrite(STDERR, "FAIL handler export nao terminou via exit instrumentado\n");
	exit(3);
}

$fx = l7he_effects();

echo json_encode(array(
	"text" => $text,
	"save_calls" => $fx["save_calls"],
	"resync_calls" => $fx["resync_calls"],
	"handler_exit" => true,
), JSON_UNESCAPED_UNICODE);
