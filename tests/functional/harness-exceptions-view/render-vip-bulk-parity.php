<?php
/**
 * Render baseline V6b2b + candidato com os mesmos stubs/dados (JSON stdin).
 * Saida: {"baseline":"...","candidate":"..."}
 */
require_once __DIR__ . "/bootstrap.php";

if (!function_exists("l7he_render_v6b2b_baseline") || !function_exists("l7he_render")) {
	fwrite(STDERR, "FAIL harness bootstrap: contrato V6b2b em falta\n");
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
$base_opts = $opts;
if (!isset($base_opts["get"]) || !is_array($base_opts["get"])) {
	$base_opts["get"] = array();
}
if (empty($cand_opts["get"]["vip"]) && empty($cand_opts["get"]["vip_add"]) &&
    empty($cand_opts["get"]["vip_dhcp"]) && empty($cand_opts["get"]["vip_bulk"]) &&
    empty($cand_opts["get"]["vip_import"]) &&
    empty($cand_opts["post"]["add_vip_entry"]) && empty($cand_opts["post"]["remove_vip_entry"]) &&
    empty($cand_opts["post"]["save_vip_bulk"]) && empty($cand_opts["post"]["import_vip_list"]) &&
    empty($cand_opts["post"]["add_vip_from_dhcp"]) && empty($cand_opts["post"]["export_vip_list"])) {
	if (!empty($cand_opts["vip_import_mode"])) {
		$cand_opts["get"]["vip_import"] = "1";
	} elseif (!empty($cand_opts["vip_bulk_mode"])) {
		$cand_opts["get"]["vip_bulk"] = "1";
	} elseif (!empty($cand_opts["vip_dhcp_mode"])) {
		$cand_opts["get"]["vip_dhcp"] = "1";
	} elseif (!empty($cand_opts["vip_add_mode"])) {
		$cand_opts["get"]["vip_add"] = "1";
	} elseif (empty($cand_opts["vip_general_only"])) {
		$cand_opts["get"]["vip"] = "1";
	}
}
if (empty($base_opts["get"]["vip"]) && empty($base_opts["get"]["vip_add"]) &&
    empty($base_opts["get"]["vip_dhcp"]) &&
    empty($base_opts["post"]["add_vip_entry"]) && empty($base_opts["post"]["remove_vip_entry"]) &&
    empty($base_opts["post"]["save_vip_bulk"]) && empty($base_opts["post"]["import_vip_list"]) &&
    empty($base_opts["post"]["add_vip_from_dhcp"]) && empty($base_opts["post"]["export_vip_list"])) {
	if (!empty($base_opts["vip_add_mode"])) {
		$base_opts["get"]["vip_add"] = "1";
	} elseif (empty($base_opts["vip_general_only"])) {
		$base_opts["get"]["vip"] = "1";
	}
}
if (!empty($base_opts["post"]["save_vip_bulk"]) && empty($base_opts["get"]["vip"])) {
	$base_opts["get"]["vip"] = "1";
}
if (!empty($base_opts["post"]["import_vip_list"]) && empty($base_opts["get"]["vip"])) {
	$base_opts["get"]["vip"] = "1";
}
echo json_encode(array(
	"baseline" => l7he_render_v6b2b_baseline($base_opts),
	"candidate" => l7he_render($cand_opts),
), JSON_UNESCAPED_UNICODE);
