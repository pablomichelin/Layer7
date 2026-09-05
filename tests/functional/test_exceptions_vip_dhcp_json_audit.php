<?php
/**
 * V6b2a — gate portátil: paridade JSON/efeitos DHCP baseline V6b2a vs candidato (12 cenários).
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/test_exceptions_vip_dhcp_json_audit.php
 *
 * **36** asserções `PASS:` (12 cenários × 3 verificações). Não grava evidência gerencial.
 * Evidência independente do gerente (revisão externa): ficheiro canónico em
 * `/private/tmp/layer7-coordenacao-20260904/evidencia-gerente/v6b2a-json-independente.json`
 * (produzido pelo gerente; cópia eventual na raiz do repo é snapshot untracked, fora de commit).
 *
 * Saída JSON opcional (gitignored): `harness-exceptions-view/generated/v6b2a-json-audit-portable.json`
 * quando `LAYER7_DHCP_JSON_AUDIT_WRITE=1`. Sem env: apenas stdout PASS/FAIL.
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

$cfg = l7he_vip_dhcp_config();
$cfg["dhcpdv6"] = array(
	"lan" => array(
		"staticmap" => array(
			array("ipaddrv6" => "2001:db8::10", "descr" => "IPv6"),
		),
	),
);
$data = l7he_vip_data(
	array(),
	array(l7he_exc("neighbor", array("custom" => array("keep" => 19)))),
	array("source_groups" => array("gestores"))
);
$data["outside"] = array("untouched" => true);
$data["layer7"]["custom"] = array("keep" => array(1, 2, 3));

$cases = array(
	"empty" => array(),
	"v4" => array("192.168.1.50"),
	"v6" => array("2001:db8::10"),
	"both" => array("192.168.1.50", "192.168.2.50"),
	"invalid" => array("192.0.2.100"),
	"mixed" => array("192.0.2.100", "192.168.1.50", "192.168.1.50"),
);

$out = array();
foreach ($cases as $name => $ips) {
	foreach (array(false, true) as $save) {
		$label = $name . ($save ? "-success" : "-savefalse");
		$opts = array(
			"data" => $data,
			"config" => $cfg,
			"post" => array(
				"add_vip_from_dhcp" => "1",
				"vip_dhcp_ip" => $ips,
			),
			"save_result" => $save,
			"get" => array("vip_add" => "1"),
		);
		l7he_render_v6b2a_baseline($opts);
		$bf = l7he_effects();
		l7he_render($opts);
		$cf = l7he_effects();
		$valid = !in_array($name, array("empty", "invalid"), true);
		$eq = ($bf === $cf);
		$counts = $cf["save_calls"] === ($valid ? 1 : 0) &&
			$cf["resync_calls"] === (($valid && $save) ? 1 : 0);
		$preserved = true;
		foreach ($cf["save_payloads"] as $p) {
			$preserved = $preserved &&
				$p["outside"] === $data["outside"] &&
				$p["layer7"]["custom"] === $data["layer7"]["custom"];
		}
		$out[$label] = array(
			"full_json_effects_equal" => $eq,
			"expected_counts" => $counts,
			"unrelated_preserved" => $preserved,
		);
		check($eq, "dhcp json $label: efeitos baseline/candidato iguais");
		check($counts, "dhcp json $label: save/resync esperados");
		check($preserved, "dhcp json $label: props vizinhas preservadas");
	}
}

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS VIP DHCP JSON AUDIT TESTS FAILED\n");
	exit(1);
}

$portable = array("pass" => true, "cases" => $out);
if (getenv("LAYER7_DHCP_JSON_AUDIT_WRITE") === "1") {
	$gen_dir = __DIR__ . "/harness-exceptions-view/generated";
	if (!is_dir($gen_dir)) {
		mkdir($gen_dir, 0755, true);
	}
	$gen_path = $gen_dir . "/v6b2a-json-audit-portable.json";
	file_put_contents(
		$gen_path,
		json_encode($portable, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
	);
}
if (getenv("LAYER7_DHCP_JSON_AUDIT_STDOUT") === "1") {
	echo json_encode($portable, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "ALL EXCEPTIONS VIP DHCP JSON AUDIT TESTS PASSED\n";
exit(0);
