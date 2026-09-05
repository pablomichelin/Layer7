<?php
/**
 * V7 Eventos — payload GET filtro/fonte (estático).
 *
 *   php tests/functional/test_events_payload.php
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_events.php";
$src = (string)file_get_contents($path);
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

check(strpos($src, '<form method="get" action="layer7_events.php"') !== false,
	"payload: form GET action layer7_events.php");
check(strpos($src, 'type="hidden" name="source"') !== false, "payload: hidden source");
check(strpos($src, 'name="filter"') !== false, "payload: input filter");
check(strpos($src, 'maxlength="100"') !== false, "payload: maxlength 100");
check(strpos($src, 'id="l7-events-filter-input"') !== false, "payload: label for acessivel");
check(strpos($src, 'for="l7-events-filter-input"') !== false, "payload: sr-only for filter");
check(strpos($src, "ajax=1&source=") !== false, "payload: ajaxUrl inclui source");
check(strpos($src, "&filter=") !== false, "payload: ajaxUrl inclui filter");
check(strpos($src, 'href="layer7_events.php?source=') !== false, "payload: links fonte GET");
check(substr_count($src, '$_GET["filter"]') >= 1, "payload: filter server-side GET");
check(substr_count($src, '$_GET["source"]') >= 1, "payload: source server-side GET");
check(strpos($src, '$_GET["ajax"]') !== false, "payload: ajax branch GET");

if ($fail) {
	fwrite(STDERR, "SOME EVENTS PAYLOAD TESTS FAILED\n");
	exit(1);
}
echo "ALL EVENTS PAYLOAD TESTS PASSED\n";
exit(0);
