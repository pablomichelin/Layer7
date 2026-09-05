<?php
/**
 * Render baseline + candidato V9 Teste (JSON stdin).
 * Saida: {"baseline":"...","candidate":"..."}
 */
require_once __DIR__ . "/bootstrap.php";

$raw = isset($argv[1]) ? (string)$argv[1] : stream_get_contents(STDIN);
$opts = json_decode($raw, true);
if (!is_array($opts)) {
	fwrite(STDERR, "FAIL JSON invalido em stdin\n");
	exit(1);
}
echo json_encode(array(
	"baseline" => l7ht_render_baseline($opts),
	"candidate" => l7ht_render($opts),
), JSON_UNESCAPED_UNICODE);
