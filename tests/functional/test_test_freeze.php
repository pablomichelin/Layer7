<?php
/**
 * V9 Teste — prefixo congelado até $pgtitle.
 *
 *   php tests/functional/test_test_freeze.php
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_test.php";
$baseline = $root . "/tests/functional/baseline-v9-test/layer7_test.php";
$expected_sha = "a25506028db1d53e2851e9e2ed5627e834fafab8d571ee0c35957090cbf34556";

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

$strip_prefix = function ($src) {
	$pg = strpos($src, '$pgtitle = array(');
	if ($pg === false) {
		return null;
	}
	return substr($src, 0, $pg);
};

$cur_src = (string)file_get_contents($current);
$ref_src = (string)file_get_contents($baseline);

check(hash_file("sha256", $baseline) === $expected_sha, "baseline V9 SHA256 pinado");

$prefix_cur = $strip_prefix($cur_src);
$prefix_ref = $strip_prefix($ref_src);
check($prefix_cur !== null && $prefix_ref !== null, "freeze: prefixo extraivel ate pgtitle");
if ($prefix_cur !== null && $prefix_ref !== null) {
	check($prefix_cur === $prefix_ref, "freeze: prefixo byte-identico (" .
	    strlen($prefix_cur) . " bytes)");
}

check(strpos($cur_src, "layer7_render_styles()") === false, "V9: sem layer7_render_styles");
check(strpos($cur_src, "layer7_render_footer()") === false, "V9: sem layer7_render_footer");
check(strpos($cur_src, 'id="l7-test-root"') !== false, "V9: raiz nativa");
check(strpos($cur_src, 'id="l7-test-results"') !== false, "V9: painel resultados");
check(strpos($ref_src, "layer7_render_styles()") !== false, "V9 baseline: pre-migracao tinha render_styles");
check(strpos($cur_src, "layer7-admin-block") === false, "V9: sem layer7-admin-block");
check(strpos($cur_src, "style=") === false, "V9: sem style= inline");

if ($fail) {
	fwrite(STDERR, "SOME TEST FREEZE TESTS FAILED\n");
	exit(1);
}
echo "ALL TEST FREEZE TESTS PASSED\n";
exit(0);
