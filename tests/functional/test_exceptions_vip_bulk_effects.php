<?php
/**
 * V6b2b — efeitos save/resync + JSON completo para modos bulk/import (export: test_exceptions_vip_bulk_export.js).
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/test_exceptions_vip_bulk_effects.php
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

function l7he_json_eq($a, $b)
{
	return json_encode($a, JSON_UNESCAPED_UNICODE) === json_encode($b, JSON_UNESCAPED_UNICODE);
}

function l7he_temp_upload($content, $name = "vip-import.txt")
{
	$dir = sys_get_temp_dir() . "/l7he-vip-import-" . getmypid();
	if (!is_dir($dir)) {
		@mkdir($dir, 0700, true);
	}
	$path = $dir . "/" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
	file_put_contents($path, $content);
	return $path;
}

function l7he_upload_ok($path, $name = "vip-import.txt")
{
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
$seed = l7he_vip_data(array(), array($neighbor));

/* GET vip_bulk=1 */
l7he_render(array("get" => array("vip_bulk" => "1"), "data" => $seed));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "GET vip_bulk: zero save/resync");

/* GET vip_import=1 */
l7he_render(array("get" => array("vip_import" => "1"), "data" => $seed));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "GET vip_import: zero save/resync");

/* bulk TXT sucesso */
$bulk_opts = array(
	"get" => array("vip_bulk" => "1"),
	"post" => array(
		"save_vip_bulk" => "1",
		"vip_bulk_text" => "192.168.1.50, Director\n192.0.2.0/24, Rede\n",
	),
	"data" => $seed,
	"save_result" => true,
);
l7he_render_v6b2b_baseline($bulk_opts);
$json_base = l7he_saved_json();
l7he_render($bulk_opts);
$fx = l7he_effects();
check($fx["save_calls"] === 1 && $fx["resync_calls"] === 1, "bulk TXT: save+resync");
$json = l7he_saved_json();
check(l7he_json_eq($json_base, $json), "bulk TXT: JSON completo baseline/candidato identico");
$labels = isset($json["layer7"]["vip_meta"]["labels"]) ? $json["layer7"]["vip_meta"]["labels"] : array();
check(($labels["192.168.1.50"] ?? "") === "Director", "bulk TXT: label gravado");
$vip = layer7_find_vip_exception($json);
check(in_array("192.168.1.50", $vip["hosts"] ?? array(), true), "bulk TXT: host gravado");
check(in_array("192.0.2.0/24", $vip["cidrs"] ?? array(), true), "bulk TXT: CIDR gravado");

/* bulk CSV / descricao adversarial */
l7he_render(array(
	"get" => array("vip_bulk" => "1"),
	"post" => array(
		"save_vip_bulk" => "1",
		"vip_bulk_text" => '10.1.0.5, "Nome, com virgula" e acentuação <b>x</b>' . "\n",
	),
	"data" => $seed,
	"save_result" => true,
));
$json_adv = l7he_saved_json();
$labels_adv = layer7_vip_get_labels($json_adv);
check(strpos($labels_adv["10.1.0.5"] ?? "", "virgula") !== false, "bulk CSV: descricao com virgula/acentos preservada");

/* bulk JSON legado */
$legacy_json = '{"layer7_vip_list":true,"entries":[{"description":"Legacy","target":"10.2.0.1"}]}';
l7he_render(array(
	"get" => array("vip_bulk" => "1"),
	"post" => array("save_vip_bulk" => "1", "vip_bulk_text" => $legacy_json),
	"data" => $seed,
	"save_result" => true,
));
$json_legacy = l7he_saved_json();
check(in_array("10.2.0.1", layer7_find_vip_exception($json_legacy)["hosts"] ?? array(), true), "bulk JSON legado: host gravado");

/* bulk vazio limpa lista e source_groups */
$data_grp = l7he_vip_data(
	array(array("target" => "10.0.0.1", "description" => "A")),
	array($neighbor),
	array("source_groups" => array("gestores"))
);
$data_grp["layer7"]["custom_root"] = array("note" => "neighbor");
l7he_render(array(
	"get" => array("vip_bulk" => "1"),
	"post" => array("save_vip_bulk" => "1", "vip_bulk_text" => ""),
	"data" => $data_grp,
	"save_result" => true,
));
$json_empty = l7he_saved_json();
$vip_empty = layer7_find_vip_exception($json_empty);
$sg_empty = isset($vip_empty["source_groups"]) && is_array($vip_empty["source_groups"]) ? $vip_empty["source_groups"] : null;
check(empty($vip_empty["hosts"] ?? array()) && empty($vip_empty["cidrs"] ?? array()), "bulk vazio: lista directa limpa");
check($sg_empty === array() || $sg_empty === null || count($sg_empty) === 0, "bulk vazio: source_groups limpos");
check(l7he_exc_by_id($json_empty["layer7"]["exceptions"], "mgmt") !== null, "bulk vazio: vizinho preservado");
check(
	isset($json_empty["layer7"]["custom_root"]["note"]) &&
	$json_empty["layer7"]["custom_root"]["note"] === "neighbor",
	"bulk vazio: prop raiz vizinha preservada"
);

/* bulk invalido */
l7he_render(array(
	"get" => array("vip" => "1"),
	"post" => array(
		"save_vip_bulk" => "1",
		"vip_bulk_text" => "192.168.1.60, OK\nnao-e-ip, Bad\n",
	),
	"data" => $seed,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "bulk invalido: zero save/resync");
$html_bulk_err = l7he_render(array(
	"get" => array("vip" => "1"),
	"post" => array(
		"save_vip_bulk" => "1",
		"vip_bulk_text" => "192.168.1.60, OK\nnao-e-ip, Bad\n",
	),
	"data" => $seed,
));
check(strpos($html_bulk_err, 'name="vip_bulk_text"') !== false, "bulk invalido: modo bulk retry");
check(strpos($html_bulk_err, "nao-e-ip, Bad") !== false, "bulk invalido: raw retry integral");

/* bulk limite 33 hosts */
$hosts33 = array();
$labels33 = array();
for ($i = 1; $i <= 33; $i++) {
	$line = "10.0.0." . $i . ", h" . $i;
	$hosts33[] = $line;
}
l7he_render(array(
	"get" => array("vip_bulk" => "1"),
	"post" => array("save_vip_bulk" => "1", "vip_bulk_text" => implode("\n", $hosts33)),
	"data" => $seed,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "bulk limite33: zero save/resync");

/* bulk savefalse raw retido */
$raw_sf = "10.8.0.1, SF\n";
l7he_render(array(
	"get" => array("vip_bulk" => "1"),
	"post" => array("save_vip_bulk" => "1", "vip_bulk_text" => $raw_sf),
	"data" => $seed,
	"save_result" => false,
));
$fx = l7he_effects();
check($fx["save_calls"] === 1 && $fx["resync_calls"] === 0, "bulk savefalse: save sem resync");
$html_sf = l7he_render(array(
	"get" => array("vip_bulk" => "1"),
	"post" => array("save_vip_bulk" => "1", "vip_bulk_text" => $raw_sf),
	"data" => $seed,
	"save_result" => false,
));
check(strpos($html_sf, "10.8.0.1, SF") !== false, "bulk savefalse: textarea raw retido");

/* import ausencia ficheiro */
l7he_render(array(
	"get" => array("vip" => "1"),
	"post" => array("import_vip_list" => "1"),
	"data" => $seed,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "import ausente: zero save/resync");
$html_imp_miss = l7he_render(array(
	"get" => array("vip" => "1"),
	"post" => array("import_vip_list" => "1"),
	"data" => $seed,
));
check(strpos($html_imp_miss, 'name="vip_import_file"') !== false, "import ausente: modo import retry");
check(strpos($html_imp_miss, "Seleccione novamente o ficheiro") !== false, "import ausente: pedir selecionar ficheiro");

/* import UPLOAD_ERR */
l7he_render(array(
	"get" => array("vip_import" => "1"),
	"post" => array("import_vip_list" => "1"),
	"files" => array(
		"vip_import_file" => array(
			"name" => "bad.txt",
			"type" => "text/plain",
			"tmp_name" => "",
			"error" => UPLOAD_ERR_NO_FILE,
			"size" => 0,
		),
	),
	"data" => $seed,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "import UPLOAD_ERR: zero save/resync");

/* import ficheiro vazio */
$empty_path = l7he_temp_upload("", "empty.txt");
l7he_render(array(
	"get" => array("vip_import" => "1"),
	"post" => array("import_vip_list" => "1"),
	"files" => l7he_upload_ok($empty_path, "empty.txt"),
	"data" => $seed,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "import vazio: zero save/resync");

/* import valido limpa source_groups */
$import_path = l7he_temp_upload("10.3.0.1, Importado\n", "valid.txt");
$imp_opts = array(
	"get" => array("vip_import" => "1"),
	"post" => array("import_vip_list" => "1"),
	"files" => l7he_upload_ok($import_path, "valid.txt"),
	"data" => $data_grp,
	"save_result" => true,
);
l7he_render_v6b2b_baseline($imp_opts);
$json_imp_base = l7he_saved_json();
l7he_render($imp_opts);
$json_imp = l7he_saved_json();
check(l7he_json_eq($json_imp_base, $json_imp), "import valido: JSON completo baseline/candidato");
$vip_imp = layer7_find_vip_exception($json_imp);
$sg_imp = isset($vip_imp["source_groups"]) && is_array($vip_imp["source_groups"]) ? $vip_imp["source_groups"] : array();
check(empty($sg_imp), "import valido: source_groups limpos");
check(in_array("10.3.0.1", $vip_imp["hosts"] ?? array(), true), "import valido: host gravado");

/* import savefalse */
$sf_path = l7he_temp_upload("10.4.0.1, SF\n", "sf.txt");
l7he_render(array(
	"get" => array("vip_import" => "1"),
	"post" => array("import_vip_list" => "1"),
	"files" => l7he_upload_ok($sf_path, "sf.txt"),
	"data" => $seed,
	"save_result" => false,
));
$fx = l7he_effects();
check($fx["save_calls"] === 1 && $fx["resync_calls"] === 0, "import savefalse: save sem resync");

/* POST bulk sucesso volta consulta */
$html_bulk_ok = l7he_render(array(
	"get" => array("vip_bulk" => "1"),
	"post" => array(
		"save_vip_bulk" => "1",
		"vip_bulk_text" => "10.5.0.1, OK\n",
	),
	"data" => $seed,
	"save_result" => true,
));
check(strpos($html_bulk_ok, 'name="export_vip_list"') !== false, "bulk sucesso: consulta VIP");
check(strpos($html_bulk_ok, 'name="save_vip_bulk"') === false, "bulk sucesso: sem form lote na consulta");

/* bulk limite 17 CIDRs */
$cidrs17 = array();
for ($i = 0; $i < 17; $i++) {
	$cidrs17[] = "192.168." . $i . ".0/24, c" . $i;
}
l7he_render(array(
	"get" => array("vip_bulk" => "1"),
	"post" => array("save_vip_bulk" => "1", "vip_bulk_text" => implode("\n", $cidrs17)),
	"data" => $seed,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "bulk limite17 CIDR: zero save/resync");

/* import parser invalido */
$bad_path = l7he_temp_upload("192.168.1.60, OK\nnao-e-ip, Bad\n", "bad.txt");
l7he_render(array(
	"get" => array("vip_import" => "1"),
	"post" => array("import_vip_list" => "1"),
	"files" => l7he_upload_ok($bad_path, "bad.txt"),
	"data" => $seed,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "import invalido: zero save/resync");
$html_imp_bad = l7he_render(array(
	"get" => array("vip" => "1"),
	"post" => array("import_vip_list" => "1"),
	"files" => l7he_upload_ok($bad_path, "bad.txt"),
	"data" => $seed,
));
check(strpos($html_imp_bad, 'name="import_vip_list"') !== false, "import invalido: modo import retry");

/* import UTF-8 BOM */
$bom_path = l7he_temp_upload("\xEF\xBB\xBF10.6.0.1, BOM\n", "bom.txt");
l7he_render(array(
	"get" => array("vip_import" => "1"),
	"post" => array("import_vip_list" => "1"),
	"files" => l7he_upload_ok($bom_path, "bom.txt"),
	"data" => $seed,
	"save_result" => true,
));
$json_bom = l7he_saved_json();
check(in_array("10.6.0.1", layer7_find_vip_exception($json_bom)["hosts"] ?? array(), true), "import BOM: host gravado");

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS VIP BULK EFFECTS TESTS FAILED\n");
	exit(1);
}
echo "ALL EXCEPTIONS VIP BULK EFFECTS TESTS PASSED\n";
exit(0);
