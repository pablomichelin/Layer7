<?php
/**
 * Gate contrato — bootstrap exceptions harness (V6a + V6b1 + V6b2a separados).
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/test_exceptions_harness_bootstrap.php
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

check(function_exists("l7he_render"), "contrato: l7he_render");
check(function_exists("l7he_render_baseline"), "contrato: l7he_render_baseline (V6a geral)");
check(function_exists("l7he_render_v6b1_baseline"), "contrato: l7he_render_v6b1_baseline (VIP)");
check(function_exists("l7he_render_v6b2a_baseline"), "contrato: l7he_render_v6b2a_baseline (DHCP)");
check(function_exists("l7he_render_v6b2b_baseline"), "contrato: l7he_render_v6b2b_baseline (bulk/import)");
check(defined("L7HE_BASELINE") && is_file(L7HE_BASELINE), "contrato: ficheiro L7HE_BASELINE");
check(defined("L7HE_BASELINE_V6B1") && is_file(L7HE_BASELINE_V6B1), "contrato: ficheiro L7HE_BASELINE_V6B1");
check(defined("L7HE_BASELINE_V6B2A") && is_file(L7HE_BASELINE_V6B2A), "contrato: ficheiro L7HE_BASELINE_V6B2A");
check(defined("L7HE_BASELINE_V6B2B") && is_file(L7HE_BASELINE_V6B2B), "contrato: ficheiro L7HE_BASELINE_V6B2B");
check(
	defined("L7HE_BASELINE_V6B1_SHA256") &&
	hash_file("sha256", L7HE_BASELINE_V6B1) === L7HE_BASELINE_V6B1_SHA256,
	"contrato: SHA256 baseline V6b1 pinado"
);
check(
	defined("L7HE_BASELINE_V6B2A_SHA256") &&
	hash_file("sha256", L7HE_BASELINE_V6B2A) === L7HE_BASELINE_V6B2A_SHA256,
	"contrato: SHA256 baseline V6b2a pinado"
);
check(
	defined("L7HE_BASELINE_V6B2B_SHA256") &&
	hash_file("sha256", L7HE_BASELINE_V6B2B) === L7HE_BASELINE_V6B2B_SHA256,
	"contrato: SHA256 baseline V6b2b pinado"
);
check(L7HE_BASELINE !== L7HE_BASELINE_V6B1, "contrato: baselines V6a/V6b1 distintas");
check(L7HE_BASELINE_V6B2A !== L7HE_BASELINE_V6B1, "contrato: baselines V6b1/V6b2a distintas");
check(L7HE_BASELINE_V6B2B !== L7HE_BASELINE_V6B2A, "contrato: baselines V6b2a/V6b2b distintas");

$html_v6a = l7he_render_baseline(array("data" => l7he_data(array())));
$html_v6b1 = l7he_render_v6b1_baseline(array("data" => l7he_data(array())));
check(strpos($html_v6a, 'id="l7-vip-list"') !== false, "V6a baseline: VIP inline (legado)");
check(strpos($html_v6b1, 'id="l7-vip-list"') !== false, "V6b1 baseline: VIP inline");
$html_cand = l7he_render(array("data" => l7he_data(array())));
check(strpos($html_cand, 'id="l7-vip-list"') === false, "candidato geral: sem VIP inline");

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS HARNESS BOOTSTRAP TESTS FAILED\n");
	exit(1);
}
echo "ALL EXCEPTIONS HARNESS BOOTSTRAP TESTS PASSED\n";
exit(0);
