<?php
/**
 * Render baseline V8 + candidato com fixtures JSON (view isolada).
 * Saida: {"baseline":"...","candidate":"..."}
 */
@ini_set("display_errors", "0");
require_once __DIR__ . "/bootstrap.php";

if (!function_exists("l7hd_render") || !function_exists("l7hd_render_baseline")) {
	fwrite(STDERR, "FAIL harness bootstrap: contrato V8 em falta\n");
	exit(1);
}

$raw = isset($argv[1]) ? (string)$argv[1] : stream_get_contents(STDIN);
$opts = json_decode($raw, true);
if (!is_array($opts)) {
	fwrite(STDERR, "FAIL JSON invalido em stdin\n");
	exit(1);
}

echo json_encode(array(
	"baseline" => l7hd_render_baseline($opts),
	"candidate" => l7hd_render($opts),
), JSON_UNESCAPED_UNICODE);
