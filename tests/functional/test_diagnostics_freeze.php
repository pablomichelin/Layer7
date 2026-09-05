<?php
/**
 * V8 Diagnósticos — prefixo congelado até $pgtitle.
 *
 *   php tests/functional/test_diagnostics_freeze.php
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_diagnostics.php";
$baseline = $root . "/tests/functional/baseline-v8-diagnostics/layer7_diagnostics.php";
$expected_sha = "8d47d5efb2db00845e5e125fe457e7c45469f5af0d519a0ef1638a548a12863a";

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

check(hash_file("sha256", $baseline) === $expected_sha, "baseline V8 SHA256 pinado");

$prefix_cur = $strip_prefix($cur_src);
$prefix_ref = $strip_prefix($ref_src);
check($prefix_cur !== null && $prefix_ref !== null, "freeze: prefixo extraivel ate pgtitle");
if ($prefix_cur !== null && $prefix_ref !== null) {
	check($prefix_cur === $prefix_ref, "freeze: prefixo byte-identico (" .
	    strlen($prefix_cur) . " bytes)");
}

check(strpos($cur_src, "layer7_render_styles()") === false, "V8: sem layer7_render_styles");
check(strpos($cur_src, "layer7_render_footer()") === false, "V8: sem layer7_render_footer");
check(strpos($cur_src, 'id="l7-diag-root"') !== false, "V8: raiz nativa");
check(strpos($cur_src, 'id="l7-diag-summary"') !== false, "V8: painel resumo");
check(strpos($ref_src, "layer7_render_styles()") !== false, "V8 baseline: pre-migracao tinha render_styles");
check(strpos($ref_src, "layer7_render_footer()") !== false, "V8 baseline: pre-migracao tinha render_footer");
check(strpos($cur_src, "layer7-admin-block") === false, "V8: sem layer7-admin-block");
check(strpos($cur_src, "l7-report-chip") === false, "V8: sem chips report");

if ($fail) {
	fwrite(STDERR, "SOME DIAGNOSTICS FREEZE TESTS FAILED\n");
	exit(1);
}
echo "ALL DIAGNOSTICS FREEZE TESTS PASSED\n";
exit(0);
