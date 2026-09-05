<?php
/**
 * V8 Diagnósticos — payload POST por submitter (gate estático de strings).
 * Não verifica pertencimento campo/submitter ao mesmo form — ver
 * test_diagnostics_payload.js (FormData DOM com render real).
 *
 *   php tests/functional/test_diagnostics_payload.php
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_diagnostics.php";
$src = (string)file_get_contents($path);
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

$submitters = array(
	'repair_pf_tables' => 'layer7_diagnostics.php#l7-pf',
	'remove_anti_doh' => 'layer7_diagnostics.php#l7-dns',
	'configure_anti_doh' => 'layer7_diagnostics.php#l7-dns',
	'send_sigusr1' => 'layer7_diagnostics.php#l7-actions',
	'send_sighup' => 'layer7_diagnostics.php#l7-actions',
	'report_error' => 'layer7_diagnostics.php#l7-report-error',
	'copy_error_report' => 'layer7_diagnostics.php#l7-report-error',
);

foreach ($submitters as $name => $action) {
	check(strpos($src, 'name="' . $name . '" value="1"') !== false,
		"payload: submitter {$name} value=1");
	check(strpos($src, 'action="' . $action . '"') !== false,
		"payload: action {$action}");
}

check(strpos($src, 'method="post"') !== false, "payload: forms POST");
check(strpos($src, 'id="error_summary"') !== false, "payload: id error_summary");
check(strpos($src, 'name="error_summary"') !== false, "payload: name error_summary");
check(strpos($src, 'rows="3"') !== false, "payload: rows 3");
check(strpos($src, 'maxlength="500"') !== false, "payload: maxlength 500");
check(strpos($src, '$_POST["error_summary"]') !== false, "payload: handler error_summary");
check(substr_count($src, '$_POST["report_error"]') >= 1, "payload: handler report_error");
check(substr_count($src, '$_POST["copy_error_report"]') >= 1, "payload: handler copy_error_report");
check(strpos($src, 'readonly onclick="this.focus(); this.select();"') !== false,
	"payload: textarea URL readonly");
check(strpos($src, "onsubmit='return confirm(") !== false,
	"payload: remove_anti_doh confirm onsubmit aspas simples");
check(strpos($src, "JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP") !== false,
	"payload: confirm JSON_HEX flags");

if ($fail) {
	fwrite(STDERR, "SOME DIAGNOSTICS PAYLOAD TESTS FAILED\n");
	exit(1);
}
echo "ALL DIAGNOSTICS PAYLOAD TESTS PASSED\n";
exit(0);
