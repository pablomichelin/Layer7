<?php
require_once __DIR__ . "/bootstrap.php";
$raw = isset($argv[1]) ? (string)$argv[1] : stream_get_contents(STDIN);
$opts = json_decode($raw, true);
if (!is_array($opts)) {
	fwrite(STDERR, "FAIL JSON invalido\n");
	exit(1);
}
echo json_encode(array(
	"baseline" => l7hr_render_baseline($opts),
	"candidate" => l7hr_render($opts),
), JSON_UNESCAPED_UNICODE);
