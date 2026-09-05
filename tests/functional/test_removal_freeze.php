<?php
/**
 * V11 Remoção — prefixo congelado até $pgtitle.
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_removal.php";
$baseline = $root . "/tests/functional/baseline-v11-removal/layer7_removal.php";
$expected_sha = "342d6eb6946fa2d8c870b222ee55e0e8e076c2fe7988af42bd768d8579c8bccb";

$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$strip = function ($s) {
	$p = strpos($s, '$pgtitle = array(');
	return $p === false ? null : substr($s, 0, $p);
};
$cur = file_get_contents($current);
$ref = file_get_contents($baseline);
check(hash_file("sha256", $baseline) === $expected_sha, "baseline V11 SHA pinado");
$pc = $strip($cur); $pr = $strip($ref);
check($pc !== null && $pr !== null, "freeze: prefixo extraivel");
if ($pc !== null && $pr !== null) {
	check($pc === $pr, "freeze: prefixo byte-identico (" . strlen($pc) . " bytes)");
}
check(strpos($cur, "layer7_render_styles()") === false, "V11: sem render_styles");
check(strpos($cur, "layer7_render_footer()") === false, "V11: sem render_footer");
check(strpos($cur, 'id="l7-removal-root"') !== false, "V11: raiz nativa");
check(strpos($cur, "layer7-admin-block") === false, "V11: sem admin-block");
check(strpos($cur, "layer7-page") === false, "V11: sem layer7-page");
check(strpos($cur, "style=") === false, "V11: sem style inline");
check(strpos($cur, "new Form(false)") !== false, "V11: Form(false)");
check(strpos($cur, '"keep_license"') !== false && strpos($cur, "new Form_Checkbox") !== false, "V11: checkbox keep_license");
check(strpos($cur, '"keep_config"') !== false && strpos($cur, "new Form_Checkbox") !== false, "V11: checkbox keep_config");
check(strpos($cur, 'name="layer7_pkg_remove_do" value="1"') !== false, "V11: submitter remove");
check(strpos($cur, 'placeholder", "REMOVER"') !== false, "V11: placeholder REMOVER");
if ($fail) { fwrite(STDERR, "SOME REMOVAL FREEZE TESTS FAILED\n"); exit(1); }
echo "ALL REMOVAL FREEZE TESTS PASSED\n";
exit(0);
