<?php
/**
 * V6a — layer7_exc_target_summary: funcao real da pagina (sem copia no bootstrap).
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/test_exceptions_target_summary.php
 */
require_once __DIR__ . "/harness-exceptions-view/bootstrap.php";

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

if (!function_exists("l7he_eval_summary_helper")) {
	function l7he_eval_summary_helper($path, $as_name = "layer7_exc_target_summary")
	{
		$raw = file_get_contents($path);
		if (!is_string($raw) || $raw === "") {
			return false;
		}
		$fn = l7he_extract_function_src($raw, "layer7_exc_target_summary");
		if ($fn === null) {
			return false;
		}
		if ($as_name !== "layer7_exc_target_summary") {
			$fn = preg_replace(
				'/function\s+layer7_exc_target_summary/',
				"function " . $as_name,
				$fn,
				1
			);
		}
		eval($fn);
		return function_exists($as_name);
	}
}

check(l7he_eval_summary_helper(L7HE_EXCEPTIONS), "candidato: helper carregado");
check(
	l7he_eval_summary_helper(L7HE_BASELINE, "layer7_exc_target_summary_baseline"),
	"baseline: helper carregado"
);

$cand_legacy = layer7_exc_target_summary(l7he_exc("legacy", array(
	"host" => "192.0.2.10",
	"cidr" => "192.0.2.0/24",
)));
check(
	strpos($cand_legacy, "192.0.2.10") !== false && strpos($cand_legacy, "192.0.2.0/24") !== false,
	"candidato: legado host/cidr no summary"
);

$fixture = array_merge(l7he_exc("cmp", array()), array(
	"hosts" => array("10.0.0.1", "10.0.0.2"),
	"cidrs" => array("192.0.2.0/24"),
	"interfaces" => array("em0", "wan"),
));
if (function_exists("layer7_exc_target_summary_baseline")) {
	check(
		layer7_exc_target_summary($fixture) === layer7_exc_target_summary_baseline($fixture),
		"baseline vs candidato: summary identico"
	);
}

$managed = l7he_exc("vip-isentos", array("hosts" => array("10.0.0.99")));
check(layer7_is_managed_vip_exception($managed), "inc-pure: managed VIP id real");

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS TARGET SUMMARY TESTS FAILED\n");
	exit(1);
}
echo "ALL EXCEPTIONS TARGET SUMMARY TESTS PASSED\n";
exit(0);
