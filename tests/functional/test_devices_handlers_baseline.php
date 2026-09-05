<?php
/**
 * BG-174 / V1 — handlers Devices iguais ao HEAD (byte a byte no bloco POST).
 *
 * Não executa handlers. Compara o texto entre o primeiro if POST e o
 * load de grupos.
 *
 *   php tests/functional/test_devices_handlers_baseline.php
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_devices.php";
$baseline = "/private/tmp/layer7-coordenacao-20260904/baseline/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_devices.php";
$head_file = $current;

if (!is_file($current)) {
	fwrite(STDERR, "FAIL actual em falta: {$current}\n");
	exit(1);
}

$cur = file_get_contents($current);
$extract = function ($src) {
	$start = strpos($src, 'if ($_POST["save_aliases"] ?? false) {');
	$end = strpos($src, '$l7_groups = layer7_load_groups();');
	if ($start === false || $end === false || $end <= $start) {
		return null;
	}
	return substr($src, $start, $end - $start);
};

$cur_h = $extract($cur);
if ($cur_h === null) {
	fwrite(STDERR, "FAIL nao foi possivel extrair handlers actuais\n");
	exit(1);
}

$ref = null;
$ref_src = "HEAD";
$git = trim((string)shell_exec("git -C " . escapeshellarg($root) . " show HEAD:package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_devices.php 2>/dev/null"));
if (is_string($git) && $git !== "") {
	$ref = $extract($git);
}
if ($ref === null && is_file($baseline)) {
	$ref_src = "coordenacao-baseline";
	$ref = $extract((string)file_get_contents($baseline));
}

if ($ref === null) {
	fwrite(STDERR, "FAIL sem referencia de handlers (HEAD/baseline)\n");
	exit(1);
}

if ($cur_h !== $ref) {
	fwrite(STDERR, "FAIL handlers Devices divergem de {$ref_src}\n");
	echo "---- actual ----\n{$cur_h}\n---- ref ({$ref_src}) ----\n{$ref}\n";
	exit(1);
}

echo "PASS: handlers save_aliases/assign_to_group iguais a {$ref_src}\n";
echo "ALL DEVICES HANDLER BASELINE TESTS PASSED\n";
exit(0);
