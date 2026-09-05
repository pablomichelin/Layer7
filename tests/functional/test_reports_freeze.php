<?php
/**
 * V10 Relatórios — prefixo congelado até $pgtitle.
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_reports.php";
$baseline = $root . "/tests/functional/baseline-v10-reports/layer7_reports.php";
$expected_sha = "17137833c7b60189aa728306ac00caeac5055c7df7afb794989893e4af3259a6";

$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$strip = function ($s) {
	$p = strpos($s, '$pgtitle = array(');
	return $p === false ? null : substr($s, 0, $p);
};
$cur = file_get_contents($current);
$ref = file_get_contents($baseline);
check(hash_file("sha256", $baseline) === $expected_sha, "baseline V10 SHA pinado");
$pc = $strip($cur); $pr = $strip($ref);
check($pc !== null && $pr !== null, "freeze: prefixo extraivel");
if ($pc !== null && $pr !== null) {
	check($pc === $pr, "freeze: prefixo byte-identico (" . strlen($pc) . " bytes)");
}
check(strpos($cur, "layer7_render_styles()") === false, "V10: sem render_styles");
check(strpos($cur, "layer7_render_footer()") === false, "V10: sem render_footer");
check(strpos($cur, 'id="l7-reports-root"') !== false, "V10: raiz nativa");
check(strpos($cur, "l7-kpi-card") === false, "V10: sem KPI cards");
check(strpos($cur, "layer7-admin-block") === false, "V10: sem admin-block");
check(strpos($cur, "onsubmit='return confirm(") !== false, "V10: clear onsubmit aspas simples");
check(strpos($cur, 'onclick="return confirm(') === false, "V10: sem onclick clear");
if ($fail) { fwrite(STDERR, "SOME REPORTS FREEZE TESTS FAILED\n"); exit(1); }
echo "ALL REPORTS FREEZE TESTS PASSED\n";
exit(0);
