<?php
/**
 * V7 Eventos — layer7_events_render_row escapa HTML adversarial.
 *
 *   php tests/functional/test_events_render_row.php
 */
$root = dirname(__DIR__, 2);
require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_events.php";
$src = (string)file_get_contents($path);
$start = strpos($src, 'function layer7_events_render_row');
$end = strpos($src, '$filter = isset($_GET["filter"])', $start === false ? 0 : $start);
if ($start === false || $end === false) {
	fwrite(STDERR, "FAIL render_row nao extraivel\n");
	exit(1);
}
eval(substr($src, $start, $end - $start));

$fail = 0;
function check($cond, $name)
{
	global $fail;
	if ($cond) {
		echo "PASS: $name\n";
	} else {
		echo "FAIL: $name\n";
		$fail = 1;
	}
}

$adversarial = array(
	"when" => '2026-01-01 <script>alert(1)</script>',
	"title" => '<img src=x onerror=alert(1)>',
	"summary" => '" onclick="alert(1)',
	"raw" => "<raw>&\"'",
	"tone" => "block",
);

ob_start();
layer7_events_render_row($adversarial);
$html = ob_get_clean();

check(strpos($html, '<script') === false, "render_row: sem tag script literal");
check(strpos($html, '<img') === false, "render_row: title sem tag img literal");
check(strpos($html, htmlspecialchars($adversarial["when"])) !== false, "render_row: when escapado");
check(strpos($html, htmlspecialchars($adversarial["title"])) !== false, "render_row: title escapado");
check(strpos($html, htmlspecialchars($adversarial["summary"])) !== false, "render_row: summary escapado");
check(strpos($html, htmlspecialchars($adversarial["raw"])) !== false, "render_row: raw escapado");
check(strpos($html, 'list-group-item-danger') !== false, "render_row: tom block nativo");
check(strpos($html, 'l7-event-raw-wrap') !== false, "render_row: details raw");
check(strpos($html, 'pre-scrollable') !== false, "render_row: pre nativo");

$monitor = array(
	"when" => "2026-08-31 12:00:00",
	"title" => "Trafego observado",
	"summary" => "Resumo curto",
	"raw" => "linha bruta",
	"tone" => "monitor",
);
ob_start();
layer7_events_render_row($monitor);
$monHtml = ob_get_clean();
check(strpos($monHtml, 'list-group-item-info') !== false, "render_row: tom monitor nativo");
check(strpos($monHtml, "Trafego observado") !== false, "render_row: ordem title preservada");
check(strpos($monHtml, "Resumo curto") !== false, "render_row: ordem summary preservada");
check(strpos($monHtml, "linha bruta") !== false, "render_row: ordem raw preservada");

if ($fail) {
	fwrite(STDERR, "SOME EVENTS RENDER ROW TESTS FAILED\n");
	exit(1);
}
echo "ALL EVENTS RENDER ROW TESTS PASSED\n";
exit(0);
