<?php
/**
 * V14 Blacklists — prefixo congelado até $pgtitle.
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_blacklists.php";
$baseline = $root . "/tests/functional/baseline-v14-blacklists/layer7_blacklists.php";
$expected_sha = "926e9099bfb3bde7e9ef3740ce9624b461d300b12b89b13bd2521e8f377452cc";

$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$strip = function ($s) {
	$p = strpos($s, '$pgtitle = array(');
	return $p === false ? null : substr($s, 0, $p);
};
$cur = file_get_contents($current);
$ref = file_get_contents($baseline);
check(hash_file("sha256", $baseline) === $expected_sha, "baseline V14 SHA pinado");
$pc = $strip($cur); $pr = $strip($ref);
check($pc !== null && $pr !== null, "freeze: prefixo extraivel");
if ($pc !== null && $pr !== null) {
	check($pc === $pr, "freeze: prefixo byte-identico (" . strlen($pc) . " bytes)");
}
check(strpos($cur, "layer7_render_styles()") === false, "V14: sem render_styles");
check(strpos($cur, "layer7_render_footer()") === false, "V14: sem render_footer");
check(strpos($cur, 'id="l7-blacklists-root"') !== false, "V14: raiz nativa");
check(strpos($cur, "layer7-admin-block") === false, "V14: sem admin-block");
check(strpos($cur, "layer7-page") === false, "V14: sem layer7-page");
check(strpos($cur, "style=") === false, "V14: sem style inline");
check(strpos($cur, 'name="do_download"') !== false, "V14: submitter download");
check(strpos($cur, 'name="save_rule"') !== false, "V14: submitter save_rule");
check(strpos($cur, 'name="delete_rule"') !== false, "V14: submitter delete_rule");
check(strpos($cur, 'name="save_cat_sites"') !== false, "V14: submitter save_cat_sites");
check(strpos($cur, 'name="delete_cat_sites"') !== false, "V14: submitter delete_cat_sites");
check(strpos($cur, 'name="save_whitelist"') !== false, "V14: submitter save_whitelist");
check(strpos($cur, 'name="save_settings"') !== false, "V14: submitter save_settings");
check(strpos($cur, 'name="do_download" value=') === false, "V14: do_download sem value");
check(strpos($cur, 'name="save_rule" value=') === false, "V14: save_rule sem value");
if ($fail) { fwrite(STDERR, "SOME BLACKLISTS FREEZE TESTS FAILED\n"); exit(1); }
echo "ALL BLACKLISTS FREEZE TESTS PASSED\n";
exit(0);
