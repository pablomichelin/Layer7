<?php
/**
 * Render baseline + candidato com os mesmos stubs/dados (JSON stdin).
 * Saida: {"baseline":"...","candidate":"..."}
 */
require_once __DIR__ . "/bootstrap.php";

if (!function_exists("l7he_render_baseline") || !function_exists("l7he_render")) {
	fwrite(STDERR, "FAIL harness bootstrap: contrato V6a em falta (l7he_render_baseline/l7he_render)\n");
	exit(1);
}

$raw = isset($argv[1]) ? (string)$argv[1] : stream_get_contents(STDIN);
$opts = json_decode($raw, true);
if (!is_array($opts)) {
	fwrite(STDERR, "FAIL JSON invalido em stdin\n");
	exit(1);
}
if (isset($opts["exceptions"]) && is_array($opts["exceptions"]) && !isset($opts["data"])) {
	$opts["data"] = l7he_data($opts["exceptions"]);
}
echo json_encode(array(
	"baseline" => l7he_render_baseline($opts),
	"candidate" => l7he_render($opts),
), JSON_UNESCAPED_UNICODE);
