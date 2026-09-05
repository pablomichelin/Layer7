<?php
/**
 * V13 MITM — prefixo congelado até $pgtitle.
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_mitm.php";
$baseline = $root . "/tests/functional/baseline-v13-mitm/layer7_mitm.php";
$expected_sha = "ee85f080ec44004bec13b04793f2f2f778fa5ba40ad2614ce8bb168bb28fe5ed";

$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$strip = function ($s) {
	$p = strpos($s, '$pgtitle = array(');
	return $p === false ? null : substr($s, 0, $p);
};
$cur = file_get_contents($current);
$ref = file_get_contents($baseline);
check(hash_file("sha256", $baseline) === $expected_sha, "baseline V13 SHA pinado");
$pc = $strip($cur); $pr = $strip($ref);
check($pc !== null && $pr !== null, "freeze: prefixo extraivel");
if ($pc !== null && $pr !== null) {
	check($pc === $pr, "freeze: prefixo byte-identico (" . strlen($pc) . " bytes)");
}
check(strpos($cur, "layer7_render_styles()") === false, "V13: sem render_styles");
check(strpos($cur, "layer7_render_footer()") === false, "V13: sem render_footer");
check(strpos($cur, 'id="l7-mitm-root"') !== false, "V13: raiz nativa");
check(strpos($cur, 'id="l7-mitm-form"') !== false, "V13: form principal");
check(strpos($cur, "layer7-admin-block") === false, "V13: sem admin-block");
check(strpos($cur, "layer7-page") === false, "V13: sem layer7-page");
check(strpos($cur, "style=") === false, "V13: sem style inline");
check(strpos($cur, 'name="mitm_save_bypass"') !== false, "V13: submitter save");
check(strpos($cur, 'name="mitm_break_glass"') !== false, "V13: submitter break-glass");
check(strpos($cur, 'name="mitm_ca_generate"') !== false, "V13: submitter ca generate");
check(strpos($cur, 'name="mitm_ca_import"') !== false, "V13: submitter ca import");
check(strpos($cur, 'name="mitm_ca_export"') !== false, "V13: submitter ca export");
check(strpos($cur, 'name="mitm_ca_delete"') !== false, "V13: submitter ca delete");
if ($fail) { fwrite(STDERR, "SOME MITM FREEZE TESTS FAILED\n"); exit(1); }
echo "ALL MITM FREEZE TESTS PASSED\n";
exit(0);
