<?php
/**
 * BG-174 / V6a — handlers VIP (15-114) e excecoes (115-303) byte-identicos ao baseline.
 *
 *   php tests/functional/test_exceptions_handlers_baseline.php
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_exceptions.php";
$baseline = $root . "/tests/functional/baseline-v6a-exceptions/layer7_exceptions.php";

if (!is_file($current) || !is_file($baseline)) {
	fwrite(STDERR, "FAIL ficheiro em falta\n");
	exit(1);
}

$extract = function ($src, $start_marker, $end_marker) {
	$start = strpos($src, $start_marker);
	$end = strpos($src, $end_marker, $start === false ? 0 : $start);
	if ($start === false || $end === false || $end <= $start) {
		return null;
	}
	return substr($src, $start, $end - $start);
};

$cur_src = (string)file_get_contents($current);
$ref_src = (string)file_get_contents($baseline);

$vip_cur = $extract($cur_src, 'if ($_POST["export_vip_list"] ?? false) {', 'if ($_POST["add_exception"] ?? false) {');
$vip_ref = $extract($ref_src, 'if ($_POST["export_vip_list"] ?? false) {', 'if ($_POST["add_exception"] ?? false) {');
$exc_cur = $extract(
	$cur_src,
	'if ($_POST["add_exception"] ?? false) {',
	"\$data = layer7_load_or_default();\n\$exceptions = isset(\$data[\"layer7\"][\"exceptions\"])"
);
$exc_ref = $extract(
	$ref_src,
	'if ($_POST["add_exception"] ?? false) {',
	"\$data = layer7_load_or_default();\n\$exceptions = isset(\$data[\"layer7\"][\"exceptions\"])"
);

$fail = 0;
if ($vip_cur === null || $vip_ref === null) {
	fwrite(STDERR, "FAIL nao foi possivel extrair bloco VIP\n");
	exit(1);
}
if ($exc_cur === null || $exc_ref === null) {
	fwrite(STDERR, "FAIL nao foi possivel extrair bloco excecoes\n");
	exit(1);
}
if ($vip_cur !== $vip_ref) {
	fwrite(STDERR, "FAIL handlers VIP divergem do baseline V6a\n");
	$fail = 1;
} else {
	echo "PASS: handlers VIP identicos ao baseline (" . strlen($vip_cur) . " bytes)\n";
}
if ($exc_cur !== $exc_ref) {
	fwrite(STDERR, "FAIL handlers excecoes divergem do baseline V6a\n");
	$fail = 1;
} else {
	echo "PASS: handlers excecoes identicos ao baseline (" . strlen($exc_cur) . " bytes)\n";
}

if ($fail) {
	exit(1);
}
echo "ALL EXCEPTIONS HANDLER BASELINE TESTS PASSED\n";
exit(0);
