<?php
/**
 * Render baseline V6b1 + candidato com os mesmos stubs/dados (JSON stdin).
 * Saida: {"baseline":"...","candidate":"..."}
 */
require_once __DIR__ . "/bootstrap.php";

if (!function_exists("l7he_render_v6b1_baseline") || !function_exists("l7he_render")) {
	fwrite(STDERR, "FAIL harness bootstrap: contrato V6b1 em falta (l7he_render_v6b1_baseline/l7he_render)\n");
	exit(1);
}

$raw = isset($argv[1]) ? (string)$argv[1] : stream_get_contents(STDIN);
$opts = json_decode($raw, true);
if (!is_array($opts)) {
	fwrite(STDERR, "FAIL JSON invalido em stdin\n");
	exit(1);
}
if (isset($opts["exceptions"]) && is_array($opts["exceptions"]) && !isset($opts["data"])) {
	$opts["data"] = l7he_data($opts["exceptions"]);
}
$cand_opts = $opts;
if (!isset($cand_opts["get"]) || !is_array($cand_opts["get"])) {
	$cand_opts["get"] = array();
}
if (empty($cand_opts["get"]["vip"]) && empty($cand_opts["get"]["vip_add"]) &&
    empty($cand_opts["post"]["add_vip_entry"]) && empty($cand_opts["post"]["remove_vip_entry"]) &&
    empty($cand_opts["post"]["save_vip_bulk"]) && empty($cand_opts["post"]["import_vip_list"]) &&
    empty($cand_opts["post"]["add_vip_from_dhcp"])) {
	if (!empty($cand_opts["vip_dhcp_mode"])) {
		$cand_opts["get"]["vip_dhcp"] = "1";
	} elseif (!empty($cand_opts["vip_add_mode"])) {
		$cand_opts["get"]["vip_add"] = "1";
	} elseif (empty($cand_opts["vip_general_only"])) {
		$cand_opts["get"]["vip"] = "1";
	}
}
echo json_encode(array(
	"baseline" => l7he_render_v6b1_baseline($opts),
	"candidate" => l7he_render($cand_opts),
), JSON_UNESCAPED_UNICODE);
