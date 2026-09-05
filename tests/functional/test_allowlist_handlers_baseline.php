<?php
/**
 * BG-174 / V5 Allowlist — prefixo original byte-identico ate carga de dados.
 *
 * Bloco congelado: inicio do ficheiro ate
 * $seed_entries = layer7_dst_allowlist_seed_entries(); (inclusive).
 * O require de Form.class.php fica depois desta linha na view.
 *
 * Baseline versionado: tests/functional/baseline-v5-allowlist/layer7_allowlist.php
 * SHA256 prefixo: b264391961c1676754e76a58fdf4a61b87ff4a7c74a91b1c93b904ac26237eb8
 * SHA256 ficheiro: f36e0a42b2feebf375d677e21dcbcdb39eb9fd80a1e23e7d97cc7d54094d8326
 *
 *   php tests/functional/test_allowlist_handlers_baseline.php
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_allowlist.php";
$baseline = $root . "/tests/functional/baseline-v5-allowlist/layer7_allowlist.php";
const L7V5_PREFIX_SHA256 = "b264391961c1676754e76a58fdf4a61b87ff4a7c74a91b1c93b904ac26237eb8";
const L7V5_FILE_SHA256 = "f36e0a42b2feebf375d677e21dcbcdb39eb9fd80a1e23e7d97cc7d54094d8326";

if (!is_file($current)) {
	fwrite(STDERR, "FAIL actual em falta: {$current}\n");
	exit(1);
}
if (!is_file($baseline)) {
	fwrite(STDERR, "FAIL baseline em falta: {$baseline}\n");
	exit(1);
}

$fail = 0;
function check($cond, $name)
{
	global $fail;
	if ($cond) {
		echo "PASS: {$name}\n";
	} else {
		echo "FAIL: {$name}\n";
		$fail = 1;
	}
}

$extract_prefix = function ($src) {
	$marker = '$seed_entries = layer7_dst_allowlist_seed_entries();';
	$end = strpos($src, $marker);
	if ($end === false) {
		return null;
	}
	$end += strlen($marker);
	if ($end < strlen($src) && $src[$end] === "\n") {
		$end++;
	}
	return substr($src, 0, $end);
};

$cur = (string)file_get_contents($current);
$ref = (string)file_get_contents($baseline);
$cur_p = $extract_prefix($cur);
$ref_p = $extract_prefix($ref);
if ($cur_p === null || $ref_p === null) {
	fwrite(STDERR, "FAIL nao foi possivel extrair prefixo ate seed_entries\n");
	exit(1);
}

check(hash("sha256", $ref) === L7V5_FILE_SHA256, "baseline ficheiro SHA256 pinado");
check(hash("sha256", $ref_p) === L7V5_PREFIX_SHA256, "baseline prefixo SHA256 pinado");
check($cur_p === $ref_p, "prefixo byte-identico ao baseline ate carga de dados");

if ($fail) {
	fwrite(STDERR, "SOME ALLOWLIST HANDLER BASELINE TESTS FAILED\n");
	exit(1);
}
echo "ALL ALLOWLIST HANDLER BASELINE TESTS PASSED\n";
exit(0);
