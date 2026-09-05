<?php
/**
 * V6b2b — gate portátil: paridade JSON/efeitos bulk/import baseline V6b2b vs candidato (14 cenários).
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/test_exceptions_vip_bulk_json_audit.php
 *
 * **56** asserções `PASS:` (14 cenários × 4 verificações). Não grava evidência gerencial.
 * Evidência independente do gerente (revisão externa): ficheiro canónico em
 * `/private/tmp/layer7-coordenacao-20260904/evidencia-gerente/v6b2b-json-independente.json`
 * (produzido pelo gerente; **não** copiar nem rotular como evidência deste gate).
 *
 * Saída JSON opcional (gitignored): `harness-exceptions-view/generated/v6b2b-json-audit-portable.json`
 * quando `LAYER7_BULK_JSON_AUDIT_WRITE=1`. Sem env: apenas stdout PASS/FAIL.
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

function l7he_json_audit_upload($content, $name = "vip-import.txt")
{
	$dir = sys_get_temp_dir() . "/l7he-vip-json-audit-" . getmypid();
	if (!is_dir($dir)) {
		@mkdir($dir, 0700, true);
	}
	$path = $dir . "/" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
	file_put_contents($path, $content);
	return array(
		"vip_import_file" => array(
			"name" => $name,
			"type" => "text/plain",
			"tmp_name" => $path,
			"error" => UPLOAD_ERR_OK,
			"size" => (int)@filesize($path),
		),
	);
}

$neighbor = l7he_exc("mgmt", array(
	"hosts" => array("10.99.0.1"),
	"priority" => 111,
	"action" => "allow",
	"enabled" => true,
));
$data_seed = l7he_vip_data(
	array(array("target" => "10.0.0.1", "description" => "Seed")),
	array($neighbor),
	array("source_groups" => array("gestores"))
);
$data_seed["outside"] = array("untouched" => true);
$data_seed["layer7"]["custom"] = array("keep" => array(1, 2, 3));

$legacy_json = '{"layer7_vip_list":true,"entries":[{"description":"Legacy","target":"10.2.0.1"}]}';

$cases = array(
	"bulk-empty" => array(
		"get" => array("vip_bulk" => "1"),
		"post" => array("save_vip_bulk" => "1", "vip_bulk_text" => ""),
		"valid" => true,
	),
	"bulk-text" => array(
		"get" => array("vip_bulk" => "1"),
		"post" => array(
			"save_vip_bulk" => "1",
			"vip_bulk_text" => "192.168.1.50, Director\n192.0.2.0/24, Rede\n",
		),
		"valid" => true,
	),
	"bulk-json" => array(
		"get" => array("vip_bulk" => "1"),
		"post" => array("save_vip_bulk" => "1", "vip_bulk_text" => $legacy_json),
		"valid" => true,
	),
	"bulk-invalid" => array(
		"get" => array("vip_bulk" => "1"),
		"post" => array(
			"save_vip_bulk" => "1",
			"vip_bulk_text" => "192.168.1.60, OK\nnao-e-ip, Bad\n",
		),
		"valid" => false,
	),
	"import-empty" => array(
		"get" => array("vip_import" => "1"),
		"post" => array("import_vip_list" => "1"),
		"upload" => array("content" => "", "name" => "empty.txt"),
		"valid" => false,
	),
	"import-text" => array(
		"get" => array("vip_import" => "1"),
		"post" => array("import_vip_list" => "1"),
		"upload" => array("content" => "10.3.0.1, Import\n", "name" => "text.txt"),
		"valid" => true,
	),
	"import-invalid" => array(
		"get" => array("vip_import" => "1"),
		"post" => array("import_vip_list" => "1"),
		"upload" => array(
			"content" => "192.168.1.60, OK\nnao-e-ip, Bad\n",
			"name" => "invalid.txt",
		),
		"valid" => false,
	),
);

$out = array();
foreach ($cases as $name => $case) {
	foreach (array(false, true) as $save) {
		$label = $name . ($save ? "-success" : "-savefalse");
		$data = json_decode(json_encode($data_seed), true);
		$opts = array(
			"data" => $data,
			"get" => $case["get"],
			"post" => $case["post"],
			"save_result" => $save,
		);
		if (!empty($case["upload"])) {
			$opts["files"] = l7he_json_audit_upload(
				(string)$case["upload"]["content"],
				(string)$case["upload"]["name"]
			);
		}
		$base_opts = $opts;
		$base_opts["data"] = json_decode(json_encode($data_seed), true);
		$cand_opts = $opts;
		$cand_opts["data"] = json_decode(json_encode($data_seed), true);
		l7he_render_v6b2b_baseline($base_opts);
		$bf = l7he_effects();
		l7he_render($cand_opts);
		$cf = l7he_effects();
		$valid = !empty($case["valid"]);
		$eq = ($bf === $cf);
		$counts = $cf["save_calls"] === ($valid ? 1 : 0) &&
			$cf["resync_calls"] === (($valid && $save) ? 1 : 0);
		$preserved = true;
		$groups_cleared = true;
		foreach ($cf["save_payloads"] as $p) {
			$preserved = $preserved &&
				$p["outside"] === $data_seed["outside"] &&
				$p["layer7"]["custom"] === $data_seed["layer7"]["custom"];
			if ($valid) {
				$vip = layer7_find_vip_exception($p);
				$sg = isset($vip["source_groups"]) && is_array($vip["source_groups"])
					? $vip["source_groups"] : null;
				$groups_cleared = $groups_cleared &&
					($sg === array() || $sg === null || count($sg) === 0);
			}
		}
		if (!$valid) {
			$groups_cleared = ($cf["save_calls"] === 0);
		}
		$out[$label] = array(
			"full_json_effects_equal" => $eq,
			"expected_counts" => $counts,
			"unrelated_preserved" => $preserved,
			"groups_cleared_as_original" => $groups_cleared,
		);
		check($eq, "bulk json $label: efeitos baseline/candidato iguais");
		check($counts, "bulk json $label: save/resync esperados");
		check($preserved, "bulk json $label: props vizinhas preservadas");
		check($groups_cleared, "bulk json $label: source_groups conforme original");
	}
}

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS VIP BULK JSON AUDIT TESTS FAILED\n");
	exit(1);
}

$portable = array("pass" => true, "cases" => $out);
if (getenv("LAYER7_BULK_JSON_AUDIT_WRITE") === "1") {
	$gen_dir = __DIR__ . "/harness-exceptions-view/generated";
	if (!is_dir($gen_dir)) {
		mkdir($gen_dir, 0755, true);
	}
	$gen_path = $gen_dir . "/v6b2b-json-audit-portable.json";
	file_put_contents(
		$gen_path,
		json_encode($portable, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
	);
}
if (getenv("LAYER7_BULK_JSON_AUDIT_STDOUT") === "1") {
	echo json_encode($portable, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "ALL EXCEPTIONS VIP BULK JSON AUDIT TESTS PASSED\n";
exit(0);
