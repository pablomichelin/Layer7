<?php
/**
 * BG-174 / V2 — handlers Groups iguais ao HEAD (byte a byte).
 *
 * Bloco: primeiro if add_group até ANTES do último
 * $data = layer7_load_or_default() após resync_devices.
 *
 *   php tests/functional/test_groups_handlers_baseline.php
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_groups.php";
$baseline_txt = "/tmp/layer7-groups-handlers-baseline.txt";
$baseline_pkg = "/private/tmp/layer7-coordenacao-20260904/baseline/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_groups.php";

if (!is_file($current)) {
	fwrite(STDERR, "FAIL actual em falta: {$current}\n");
	exit(1);
}

$extract = function ($src) {
	$start = strpos($src, 'if ($_POST["add_group"] ?? false) {');
	$resync = strpos($src, 'if ($_POST["resync_devices"] ?? false) {');
	if ($start === false || $resync === false) {
		return null;
	}
	$end = strpos($src, '$data = layer7_load_or_default();', $resync);
	if ($end === false || $end <= $start) {
		return null;
	}
	return substr($src, $start, $end - $start);
};

$cur_h = $extract((string)file_get_contents($current));
if ($cur_h === null) {
	fwrite(STDERR, "FAIL nao foi possivel extrair handlers actuais\n");
	exit(1);
}

$ref = null;
$ref_src = "HEAD";
$git = trim((string)shell_exec("git -C " . escapeshellarg($root) . " show HEAD:package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_groups.php 2>/dev/null"));
if (is_string($git) && $git !== "") {
	$ref = $extract($git);
}
if ($ref === null && is_file($baseline_txt)) {
	$ref_src = "tmp-baseline";
	$ref = (string)file_get_contents($baseline_txt);
}
if ($ref === null && is_file($baseline_pkg)) {
	$ref_src = "coordenacao-baseline";
	$ref = $extract((string)file_get_contents($baseline_pkg));
}

if ($ref === null) {
	fwrite(STDERR, "FAIL sem referencia de handlers (HEAD/baseline)\n");
	exit(1);
}

if ($cur_h !== $ref) {
	fwrite(STDERR, "FAIL handlers Groups divergem de {$ref_src}\n");
	exit(1);
}

echo "PASS: handlers add/delete/edit/resync iguais a {$ref_src} (" . strlen($cur_h) . " bytes)\n";
echo "ALL GROUPS HANDLER BASELINE TESTS PASSED\n";
exit(0);
