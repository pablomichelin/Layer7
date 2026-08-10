<?php
/*
 * Regressão mínima — Reportar erro (contexto seguro + URL GitHub).
 *
 * Uso: php package/pfSense-pkg-layer7/tests/test_error_report.php
 */
$pkg = dirname(__DIR__);
$inc = $pkg . "/files/usr/local/pkg/layer7.inc";
if (!is_file($inc)) {
	fwrite(STDERR, "FAIL: layer7.inc em falta\n");
	exit(1);
}

$testdir = sys_get_temp_dir() . "/layer7-error-report-" . getmypid();
@mkdir($testdir . "/usr/local/etc/layer7", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);

require_once $inc;

function fail($msg)
{
	fwrite(STDERR, "FAIL: $msg\n");
	exit(1);
}

function need($cond, $msg)
{
	if (!$cond) {
		fail($msg);
	}
}

need(function_exists("layer7_error_report_safe_context"), "API em falta: layer7_error_report_safe_context");
need(function_exists("layer7_error_report_sanitize_summary"), "API em falta: layer7_error_report_sanitize_summary");
need(function_exists("layer7_error_report_issue_url"), "API em falta: layer7_error_report_issue_url");

$cfg = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"enforcement_model" => "scoped_hybrid",
		"interfaces" => array("lan", "opt1", "opt2"),
		"mitm" => array("enabled" => true),
	),
);
$ctx = layer7_error_report_safe_context($cfg, true);
need($ctx["daemon"] === "running", "daemon running");
need($ctx["enabled"] === "true", "enabled");
need($ctx["mode"] === "enforce", "mode");
need($ctx["enforcement_model"] === "scoped_hybrid", "model");
need($ctx["interface_count"] === "3", "iface count");
need($ctx["mitm"] === "configured_on", "mitm flag");

$bad_model = layer7_error_report_safe_context(array(
	"layer7" => array(
		"enabled" => false,
		"mode" => "weird",
		"enforcement_model" => "legacy_global; DROP TABLE",
		"interfaces" => "not-array",
	),
), false);
need($bad_model["mode"] === "(unset)", "mode invalid -> unset");
need($bad_model["enforcement_model"] === "(invalid)", "model invalid");
need($bad_model["interface_count"] === "0", "iface non-array");
need($bad_model["daemon"] === "stopped", "daemon stopped");

$summary = layer7_error_report_sanitize_summary("linha1\r\nlinha2\x01\x02");
need(strpos($summary, "\x01") === false, "control chars stripped");
need(strpos($summary, "linha1") !== false && strpos($summary, "linha2") !== false, "newlines kept as text");

$long = layer7_error_report_sanitize_summary(str_repeat("a", 600));
need(strlen($long) <= 500, "summary max 500");

$url = layer7_error_report_issue_url($ctx, "politicas nao bloqueiam YouTube");
need(strpos($url, "https://github.com/pablomichelin/Layer7/issues/new?") === 0, "github base URL");
$decoded = urldecode($url);
/* Mencionar ".lic" na lista de exclusões é OK; não vazar o path/conteúdo. */
need(strpos($decoded, "/usr/local/etc/layer7.lic") === false, "no license path leak");
need(strpos($decoded, "ED25519") === false, "no key material token");
need(strpos($decoded, "scoped_hybrid") !== false, "model in body");
need(strpos($decoded, "politicas nao bloqueiam YouTube") !== false, "summary in body");
need(strpos($decoded, "O que N") !== false && strpos($decoded, "foi enviado") !== false,
    "privacy section present");
need(strpos($decoded, "chaves") !== false, "mentions keys excluded");
need(strpos($decoded, "dumps") !== false, "mentions dumps excluded");

fwrite(STDOUT, "PASS: test_error_report\n");
exit(0);
