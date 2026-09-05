<?php
/**
 * Render baseline + candidato com os mesmos stubs/dados (JSON stdin).
 * Saida: {"baseline":"...","candidate":"..."}
 */
require_once __DIR__ . "/bootstrap.php";

$raw = isset($argv[1]) ? (string)$argv[1] : stream_get_contents(STDIN);
$opts = json_decode($raw, true);
if (!is_array($opts)) {
	fwrite(STDERR, "FAIL JSON invalido em stdin\n");
	exit(1);
}
if (isset($opts["entries"]) && is_array($opts["entries"]) && !isset($opts["data"])) {
	$opts["data"] = l7ha_data($opts["entries"]);
}
echo json_encode(array(
	"baseline" => l7ha_render_baseline($opts),
	"candidate" => l7ha_render($opts),
), JSON_UNESCAPED_UNICODE);
