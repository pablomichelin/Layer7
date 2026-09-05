<?php
/**
 * V15 Settings — prefixo congelado até $pgtitle.
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_settings.php";
$baseline = $root . "/tests/functional/baseline-v15-settings/layer7_settings.php";
$expected_sha = "f63703091802c7b3c75da43faa20b2395f4cd29c9fc372e1ed0ca5ba5fd7f6bf";

$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$strip = function ($s) {
	$p = strpos($s, '$pgtitle = array(');
	return $p === false ? null : substr($s, 0, $p);
};
$cur = file_get_contents($current);
$ref = file_get_contents($baseline);
check(hash_file("sha256", $baseline) === $expected_sha, "baseline V15 SHA pinado");
$pc = $strip($cur); $pr = $strip($ref);
check($pc !== null && $pr !== null, "freeze: prefixo extraivel");
if ($pc !== null && $pr !== null) {
	check($pc === $pr, "freeze: prefixo byte-identico (" . strlen($pc) . " bytes)");
}
check(strpos($cur, "layer7_render_styles()") === false, "V15: sem render_styles");
check(strpos($cur, "layer7_render_footer()") === false, "V15: sem render_footer");
check(strpos($cur, 'id="l7-settings-root"') !== false, "V15: raiz nativa");
check(strpos($cur, "layer7-admin-block") === false, "V15: sem admin-block");
check(strpos($cur, "layer7-page") === false, "V15: sem layer7-page");
check(strpos($cur, 'name="save_scope" value="general"') !== false, "V15: save_scope general");
check(strpos($cur, 'name="save_scope" value="reports"') !== false, "V15: save_scope reports");
check(strpos($cur, 'name="save" value="1"') !== false, "V15: hidden save reports");
check(strpos($cur, 'name="save" value="1" class="btn btn-primary"') !== false, "V15: submit save general");
check(strpos($cur, 'name="reports_enabled" value=') === false, "V15: reports_enabled sem value");
check(strpos($cur, 'name="reports_event_log_enabled" value=') === false, "V15: reports_event_log_enabled sem value");
check(strpos($cur, "layer7_settings_update.js") !== false, "V15: script update externo");
check(strpos($cur, 'id="l7_pkg_update"') !== false, "V15: ancora update");
check(strpos($cur, 'id="l7_rpt_preset"') !== false, "V15: retention preset executivo");
check(strpos($cur, 'id="l7_evt_preset"') !== false, "V15: retention preset detalhado");
if ($fail) { fwrite(STDERR, "SOME SETTINGS FREEZE TESTS FAILED\n"); exit(1); }
echo "ALL SETTINGS FREEZE TESTS PASSED\n";
exit(0);
