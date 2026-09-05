<?php
/**
 * V6b1 — efeitos save/resync + JSON completo (vip-isentos schema real).
 * Complementa auditoria gerencial JSON 10 casos (evidencia externa).
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/test_exceptions_vip_effects.php
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

$neighbor = l7he_exc("mgmt", array(
	"hosts" => array("10.99.0.1"),
	"priority" => 111,
	"action" => "allow",
	"enabled" => true,
));
$seed = l7he_vip_data(array(), array($neighbor));

/* GET vip=1 */
l7he_render(array("get" => array("vip" => "1"), "data" => $seed));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "GET vip=1: zero save/resync");

/* add IPv4 sucesso — savemsg vem do handler real, nao da fixture */
$add_opts = array(
	"get" => array("vip_add" => "1"),
	"post" => array(
		"add_vip_entry" => "1",
		"vip_description" => "Director",
		"vip_target" => "192.168.1.50",
	),
	"data" => $seed,
	"save_result" => true,
);
l7he_render_v6b1_baseline($add_opts);
$json_base = l7he_saved_json();
l7he_render($add_opts);
$fx = l7he_effects();
check($fx["save_calls"] === 1 && $fx["resync_calls"] === 1, "add4: save+resync");
$json = l7he_saved_json();
check(l7he_json_eq($json_base, $json), "add4: JSON completo baseline/candidato identico");
$labels = isset($json["layer7"]["vip_meta"]["labels"]) ? $json["layer7"]["vip_meta"]["labels"] : array();
check(isset($labels["192.168.1.50"]) && $labels["192.168.1.50"] === "Director", "add4: label gravado");
$vip = layer7_find_vip_exception($json);
check(in_array("192.168.1.50", $vip["hosts"] ?? array(), true), "add4: host na excepcao vip-isentos");
check(l7he_exc_by_id($json["layer7"]["exceptions"], "mgmt") !== null, "add4: vizinho mgmt preservado");
check(isset($json["layer7"]["exceptions"]) && count($json["layer7"]["exceptions"]) === 2,
	"add4: contador excepcoes raiz (vip+vizinho)");

/* add IPv6 sucesso */
l7he_render(array(
	"get" => array("vip_add" => "1"),
	"post" => array(
		"add_vip_entry" => "1",
		"vip_description" => "IPv6 lab",
		"vip_target" => "2001:db8::1",
	),
	"data" => $seed,
	"save_result" => true,
));
$json6 = l7he_saved_json();
$vip6 = layer7_find_vip_exception($json6);
check(in_array("2001:db8::1", $vip6["hosts"] ?? array(), true), "add6: IPv6 gravado");

/* add CIDR */
l7he_render(array(
	"get" => array("vip_add" => "1"),
	"post" => array(
		"add_vip_entry" => "1",
		"vip_description" => "Rede",
		"vip_target" => "192.0.2.0/24",
	),
	"data" => $seed,
	"save_result" => true,
));
$jsonc = l7he_saved_json();
$vipc = layer7_find_vip_exception($jsonc);
check(in_array("192.0.2.0/24", $vipc["cidrs"] ?? array(), true), "add CIDR: rede gravada");

/* duplicado: zero save */
$data_dup = l7he_vip_data(array(array("target" => "10.0.0.1", "description" => "A")));
l7he_render(array(
	"get" => array("vip_add" => "1"),
	"post" => array(
		"add_vip_entry" => "1",
		"vip_description" => "Dup",
		"vip_target" => "10.0.0.1",
	),
	"data" => $data_dup,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "duplicate: zero save/resync");

/* alvo invalido */
l7he_render(array(
	"get" => array("vip_add" => "1"),
	"post" => array(
		"add_vip_entry" => "1",
		"vip_description" => "Bad",
		"vip_target" => "not-valid",
	),
	"data" => $seed,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "bad target: zero save/resync");

/* remove sucesso */
$data_rm = l7he_vip_data(array(
	array("target" => "10.0.0.1", "description" => "A"),
	array("target" => "10.0.0.2", "description" => "B"),
), array($neighbor));
l7he_render(array(
	"get" => array("vip" => "1"),
	"post" => array(
		"remove_vip_entry" => "1",
		"vip_remove_target" => "10.0.0.1",
	),
	"data" => $data_rm,
	"save_result" => true,
));
$json_rm = l7he_saved_json();
$rows = layer7_vip_list_entries($json_rm);
check(count($rows) === 1 && $rows[0]["target"] === "10.0.0.2", "remove1: uma entrada restante");
check(l7he_exc_by_id($json_rm["layer7"]["exceptions"], "mgmt") !== null, "remove1: vizinho preservado");

/* savefalse: JSON entregue sem resync */
l7he_render(array(
	"get" => array("vip_add" => "1"),
	"post" => array(
		"add_vip_entry" => "1",
		"vip_description" => "SF",
		"vip_target" => "10.0.0.88",
	),
	"data" => $seed,
	"save_result" => false,
));
$fx = l7he_effects();
check($fx["save_calls"] === 1 && $fx["resync_calls"] === 0, "savefalse: save sem resync");
$json_sf = l7he_saved_json();
check(in_array("10.0.0.88", layer7_find_vip_exception($json_sf)["hosts"] ?? array(), true), "savefalse: host no JSON");

/* source_groups + vizinho + props raiz — JSON completo portatil */
$data_grp = l7he_vip_data(array(), array(), array("source_groups" => array("gestores", "vip")));
$data_grp["layer7"]["custom_root"] = array("note" => "harness-neighbor");
$grp_opts = array(
	"get" => array("vip_add" => "1"),
	"post" => array(
		"add_vip_entry" => "1",
		"vip_description" => "Extra",
		"vip_target" => "10.0.0.77",
	),
	"data" => $data_grp,
	"save_result" => true,
);
l7he_render_v6b1_baseline($grp_opts);
$json_grp_base = l7he_saved_json();
l7he_render($grp_opts);
$json_grp = l7he_saved_json();
check(l7he_json_eq($json_grp_base, $json_grp), "source_groups: JSON completo baseline/candidato");
$vip_grp = layer7_find_vip_exception($json_grp);
$sg = isset($vip_grp["source_groups"]) && is_array($vip_grp["source_groups"]) ? $vip_grp["source_groups"] : array();
check(in_array("gestores", $sg, true) && in_array("vip", $sg, true), "source_groups: preservados apos add");
check(
	isset($json_grp["layer7"]["custom_root"]["note"]) &&
	$json_grp["layer7"]["custom_root"]["note"] === "harness-neighbor",
	"source_groups: prop raiz vizinha preservada"
);

/* limite 48 (32+16): add rejeitado */
$data_full = l7he_vip_build_full(array("neighbors" => array($neighbor)));
l7he_render(array(
	"get" => array("vip_add" => "1"),
	"post" => array(
		"add_vip_entry" => "1",
		"vip_description" => "Overflow",
		"vip_target" => "10.0.0.99",
	),
	"data" => $data_full,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "limite48: add bloqueado sem save");

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS VIP EFFECTS TESTS FAILED\n");
	exit(1);
}
echo "ALL EXCEPTIONS VIP EFFECTS TESTS PASSED\n";
exit(0);
