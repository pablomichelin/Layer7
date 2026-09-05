<?php
/**
 * V6b2a — efeitos save/resync + JSON completo para modo DHCP exclusivo.
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/test_exceptions_vip_dhcp_effects.php
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
$dhcp_cfg = l7he_vip_dhcp_config();

/* GET vip_dhcp=1 */
l7he_render(array("get" => array("vip_dhcp" => "1"), "data" => $seed, "config" => $dhcp_cfg));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "GET vip_dhcp: zero save/resync");

/* add DHCP sucesso */
$add_opts = array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("192.168.1.50"),
	),
	"data" => $seed,
	"config" => $dhcp_cfg,
	"save_result" => true,
);
l7he_render_v6b2a_baseline($add_opts);
$json_base = l7he_saved_json();
l7he_render($add_opts);
$fx = l7he_effects();
check($fx["save_calls"] === 1 && $fx["resync_calls"] === 1, "dhcp add1: save+resync");
$json = l7he_saved_json();
check(l7he_json_eq($json_base, $json), "dhcp add1: JSON completo baseline/candidato identico");
$vip = layer7_find_vip_exception($json);
check(in_array("192.168.1.50", $vip["hosts"] ?? array(), true), "dhcp add1: host gravado");
check(l7he_exc_by_id($json["layer7"]["exceptions"], "mgmt") !== null, "dhcp add1: vizinho preservado");

/* duas interfaces */
l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("192.168.1.50", "192.168.2.50"),
	),
	"data" => $seed,
	"config" => $dhcp_cfg,
	"save_result" => true,
));
$json2 = l7he_saved_json();
$vip2 = layer7_find_vip_exception($json2);
check(
	in_array("192.168.1.50", $vip2["hosts"] ?? array(), true) &&
	in_array("192.168.2.50", $vip2["hosts"] ?? array(), true),
	"dhcp add2: duas interfaces gravadas"
);

/* selecao vazia */
l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array("add_vip_from_dhcp" => "1"),
	"data" => $seed,
	"config" => $dhcp_cfg,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "dhcp vazio: zero save/resync");

/* IP inexistente/duplicado */
l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("9.9.9.9", "192.168.1.50", "192.168.1.50"),
	),
	"data" => $seed,
	"config" => $dhcp_cfg,
	"save_result" => true,
));
$json_dup = l7he_saved_json();
$vip_dup = layer7_find_vip_exception($json_dup);
$hosts_dup = $vip_dup["hosts"] ?? array();
check(in_array("192.168.1.50", $hosts_dup, true), "dhcp dup: IP valido gravado");
check(count(array_keys(array_count_values($hosts_dup), 1)) === count($hosts_dup), "dhcp dup: sem duplicados no JSON");

/* savefalse preserva JSON entregue */
l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("192.168.2.50"),
	),
	"data" => $seed,
	"config" => $dhcp_cfg,
	"save_result" => false,
));
$fx = l7he_effects();
check($fx["save_calls"] === 1 && $fx["resync_calls"] === 0, "dhcp savefalse: save sem resync");
$json_sf = l7he_saved_json();
check(in_array("192.168.2.50", layer7_find_vip_exception($json_sf)["hosts"] ?? array(), true), "dhcp savefalse: host no JSON");

/* sucesso parcial no limite (31 hosts + 2 seleccionados) */
$hosts31 = array();
$labels31 = array();
for ($i = 1; $i <= 31; $i++) {
	$h = "10.0.0." . $i;
	$hosts31[] = $h;
	$labels31[$h] = "h" . $i;
}
$data31 = array(
	"layer7" => array(
		"exceptions" => array(array(
			"id" => layer7_vip_exception_id(),
			"enabled" => true,
			"priority" => 9000,
			"action" => "allow",
			"managed_by" => "profiles",
			"hosts" => $hosts31,
		)),
		"vip_meta" => array("labels" => $labels31),
	),
);
l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("192.168.1.50", "192.168.2.50"),
	),
	"data" => $data31,
	"config" => $dhcp_cfg,
	"save_result" => true,
));
$json_part = l7he_saved_json();
$vip_part = layer7_find_vip_exception($json_part);
$hosts_part = $vip_part["hosts"] ?? array();
check(count($hosts_part) === 32, "dhcp parcial limite: 32 hosts apos adicao parcial");
check(in_array("192.168.1.50", $hosts_part, true), "dhcp parcial limite: primeiro IP adicionado");
check(!in_array("192.168.2.50", $hosts_part, true), "dhcp parcial limite: segundo IP bloqueado pelo limite");

/* limite 32 hosts: POST com IP disponivel — zero save, formulario retry com selecao */
l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("192.168.1.50"),
	),
	"data" => l7he_vip_build_full(),
	"config" => $dhcp_cfg,
	"save_result" => true,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "dhcp limite32 POST: zero save/resync");

l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("192.168.1.50"),
	),
	"data" => l7he_vip_build_full(),
	"config" => $dhcp_cfg,
	"save_result" => false,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "dhcp limite32 savefalse: zero save/resync");

/* source_groups preservado */
$data_grp = l7he_vip_data(array(), array(), array("source_groups" => array("gestores")));
$data_grp["layer7"]["custom_root"] = array("note" => "neighbor");
$grp_opts = array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("192.168.1.50"),
	),
	"data" => $data_grp,
	"config" => $dhcp_cfg,
	"save_result" => true,
);
l7he_render_v6b2a_baseline($grp_opts);
$json_grp_base = l7he_saved_json();
l7he_render($grp_opts);
$json_grp = l7he_saved_json();
check(l7he_json_eq($json_grp_base, $json_grp), "dhcp source_groups: JSON completo baseline/candidato");
$vip_grp = layer7_find_vip_exception($json_grp);
$sg = isset($vip_grp["source_groups"]) && is_array($vip_grp["source_groups"]) ? $vip_grp["source_groups"] : array();
check(in_array("gestores", $sg, true), "dhcp source_groups: preservados");
check(
	isset($json_grp["layer7"]["custom_root"]["note"]) &&
	$json_grp["layer7"]["custom_root"]["note"] === "neighbor",
	"dhcp source_groups: prop raiz vizinha preservada"
);

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS VIP DHCP EFFECTS TESTS FAILED\n");
	exit(1);
}
echo "ALL EXCEPTIONS VIP DHCP EFFECTS TESTS PASSED\n";
exit(0);
