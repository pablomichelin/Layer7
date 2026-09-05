<?php
/**
 * V12 Identity — prefixo congelado até $pgtitle.
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_identity.php";
$baseline = $root . "/tests/functional/baseline-v12-identity/layer7_identity.php";
$expected_sha = "60cd816218725d539dfd8215bf4f34db38088261ee0c882aeb73d9b95b7e84ea";

$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$strip = function ($s) {
	$p = strpos($s, '$pgtitle = array(');
	return $p === false ? null : substr($s, 0, $p);
};
$cur = file_get_contents($current);
$ref = file_get_contents($baseline);
check(hash_file("sha256", $baseline) === $expected_sha, "baseline V12 SHA pinado");
$pc = $strip($cur); $pr = $strip($ref);
check($pc !== null && $pr !== null, "freeze: prefixo extraivel");
if ($pc !== null && $pr !== null) {
	check($pc === $pr, "freeze: prefixo byte-identico (" . strlen($pc) . " bytes)");
}
check(strpos($cur, "layer7_render_styles()") === false, "V12: sem render_styles");
check(strpos($cur, "layer7_render_footer()") === false, "V12: sem render_footer");
check(strpos($cur, 'id="l7-identity-root"') !== false, "V12: raiz nativa");
check(strpos($cur, 'id="l7-identity-form"') !== false, "V12: form preservado");
check(strpos($cur, 'action="layer7_identity.php"') !== false, "V12: action preservada");
check(strpos($cur, "layer7-admin-block") === false, "V12: sem admin-block");
check(strpos($cur, "layer7-page") === false, "V12: sem layer7-page");
check(strpos($cur, "style=") === false, "V12: sem style inline");
check(strpos($cur, 'name="save_identity"') !== false, "V12: submitter save");
check(strpos($cur, 'name="test_ldap"') !== false, "V12: submitter test");
check(strpos($cur, 'name="dc_generate_token"') !== false, "V12: submitter token");
if ($fail) { fwrite(STDERR, "SOME IDENTITY FREEZE TESTS FAILED\n"); exit(1); }
echo "ALL IDENTITY FREEZE TESTS PASSED\n";
exit(0);
