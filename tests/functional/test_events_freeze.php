<?php
/**
 * V7 Eventos — prefixo congelado salvo layer7_events_render_row.
 *
 *   php tests/functional/test_events_freeze.php
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_events.php";
$baseline = $root . "/tests/functional/baseline-v7-events/layer7_events.php";
$expected_sha = "0e146d603df8163d0b4bac21b5c8147cd50bbb034985560cb9e1c433fcdd7de7";

if (!is_file($current) || !is_file($baseline)) {
	fwrite(STDERR, "FAIL ficheiro em falta\n");
	exit(1);
}

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

$strip_render_row = function ($src) {
	$pg = strpos($src, '$pgtitle = array(');
	if ($pg === false) {
		return null;
	}
	$prefix = substr($src, 0, $pg);
	$start = strpos($prefix, 'function layer7_events_render_row');
	$end = strpos($prefix, '$filter = isset($_GET["filter"])', $start === false ? 0 : $start);
	if ($start === false || $end === false || $end <= $start) {
		return null;
	}
	return substr($prefix, 0, $start) . substr($prefix, $end);
};

$cur_src = (string)file_get_contents($current);
$ref_src = (string)file_get_contents($baseline);

check(hash_file("sha256", $baseline) === $expected_sha, "baseline V7 SHA256 pinado");

$prefix_cur = $strip_render_row($cur_src);
$prefix_ref = $strip_render_row($ref_src);
check($prefix_cur !== null && $prefix_ref !== null, "freeze: prefixo extraivel sem render_row");
if ($prefix_cur !== null && $prefix_ref !== null) {
	check($prefix_cur === $prefix_ref, "freeze: prefixo byte-identico salvo render_row (" .
	    strlen($prefix_cur) . " bytes)");
}

check(strpos($cur_src, "layer7_render_styles()") === false, "V7: sem layer7_render_styles");
check(strpos($cur_src, "layer7_render_footer()") === false, "V7: sem layer7_render_footer");
check(strpos($cur_src, 'id="l7-events-root"') !== false, "V7: raiz nativa");
check(strpos($cur_src, 'details class="l7-event-raw-wrap"') !== false, "V7: raw em details");
check(strpos($ref_src, "layer7_render_styles()") !== false, "V7 baseline: pre-migracao tinha render_styles");
check(strpos($cur_src, 'class="l7-event-row l7-event-row--') === false,
	"V7: render_row sem modificadores CSS antigos");
check(strpos($cur_src, 'list-group-item-danger') !== false ||
    strpos($cur_src, 'list-group-item-info') !== false,
	"V7: render_row usa list-group compacto por tom");

if ($fail) {
	fwrite(STDERR, "SOME EVENTS FREEZE TESTS FAILED\n");
	exit(1);
}
echo "ALL EVENTS FREEZE TESTS PASSED\n";
exit(0);
