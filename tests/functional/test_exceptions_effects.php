<?php
/**
 * V6a — rastreio save_json / pf_config_resync + objeto JSON completo entregue.
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/test_exceptions_effects.php
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

function l7he_exc_equals($a, $b)
{
	return json_encode($a, JSON_UNESCAPED_UNICODE) === json_encode($b, JSON_UNESCAPED_UNICODE);
}

$seed = array(
	l7he_exc("sentinel", array(
		"hosts" => array("10.99.0.1"),
		"priority" => 111,
		"action" => "allow",
		"enabled" => true,
	)),
	l7he_exc("mgmt", array(
		"hosts" => array("10.0.0.1"),
		"priority" => 500,
		"action" => "allow",
		"enabled" => true,
	)),
);
$data_seed = l7he_data($seed);

/* GET consulta */
l7he_render(array("data" => $data_seed));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "GET: zero save/resync");

/* GET edit invalido */
l7he_render(array("get" => array("edit" => "9"), "data" => $data_seed));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "GET edit invalido: zero save/resync");

/* POST add erro validacao */
l7he_render(array(
	"get" => array("new" => "1"),
	"post" => array(
		"add_exception" => "1",
		"new_id" => "ok-id",
		"new_hosts" => "",
		"new_cidrs" => "",
	),
	"data" => $data_seed,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "POST add erro: zero save/resync");

/* POST add sucesso — JSON completo + sentinelas */
l7he_render(array(
	"get" => array("new" => "1"),
	"post" => array(
		"add_exception" => "1",
		"new_id" => "novo",
		"new_hosts" => "10.0.0.50\n2001:db8::2",
		"new_cidrs" => "192.0.2.0/24",
		"new_priority" => "0",
		"new_action" => "tag",
		"new_enabled" => "1",
		"new_exc_ifaces" => array("lan", "wan"),
	),
	"data" => $data_seed,
	"save_result" => true,
));
$fx = l7he_effects();
check($fx["save_calls"] === 1 && $fx["resync_calls"] === 1, "POST add ok: save+resync");
check($fx["resync_forces"] === array(true), "POST add ok: resync(true)");
$exc_add = l7he_saved_exceptions();
check(count($exc_add) === 3, "POST add ok: tres excecoes no JSON");
$sentinel = l7he_exc_by_id($exc_add, "sentinel");
check($sentinel !== null && $sentinel["priority"] === 111, "POST add ok: sentinel priority intacta");
$novo = l7he_exc_by_id($exc_add, "novo");
check($novo !== null, "POST add ok: nova excecao presente");
check($novo["enabled"] === true, "POST add ok: enabled true");
check($novo["priority"] === 0, "POST add ok: priority 0");
check($novo["action"] === "tag", "POST add ok: action tag");
check($novo["hosts"] === array("10.0.0.50", "2001:db8::2"), "POST add ok: hosts");
check($novo["cidrs"] === array("192.0.2.0/24"), "POST add ok: cidrs");
check($novo["interfaces"] === array("em0", "em1"), "POST add ok: interfaces real em0/em1");

/* POST logical ifid → JSON real (get_real_interface stub) */
check(layer7_real_interface_name("lan") === "em0", "stub: lan→em0");
check(layer7_real_interface_name("wan") === "em1", "stub: wan→em1");

/* GET registro real → checkbox logical */
$data_real_iface = l7he_data(array(
	l7he_exc("if-real", array(
		"hosts" => array("10.0.0.20"),
		"interfaces" => array("em0"),
	)),
));
$html_iface = l7he_render(array(
	"get" => array("edit" => "0"),
	"data" => $data_real_iface,
));
check(
	preg_match('/name="edit_exc_ifaces\[\]" value="lan"[^>]*checked/', $html_iface) === 1,
	"GET em0: checkbox lan marcada"
);

/* POST save_exceptions — toggles enabled */
$data_toggle = l7he_data(array(
	l7he_exc("a", array("hosts" => array("10.0.0.1"), "enabled" => true)),
	l7he_exc("b", array("hosts" => array("10.0.0.2"), "enabled" => true)),
));
l7he_render(array(
	"post" => array(
		"save_exceptions" => "1",
		"eon" => array("1" => "1"),
	),
	"data" => $data_toggle,
	"save_result" => true,
));
$exc_toggle = l7he_saved_exceptions();
check($exc_toggle[0]["enabled"] === false, "save_exceptions: idx0 desligado");
check($exc_toggle[1]["enabled"] === true, "save_exceptions: idx1 ligado");

/* POST save_exceptions savefalse */
l7he_render(array(
	"post" => array("save_exceptions" => "1", "eon" => array("0" => "1")),
	"data" => $data_seed,
	"save_result" => false,
));
$fx = l7he_effects();
check($fx["save_calls"] === 1 && $fx["resync_calls"] === 0, "save_exceptions savefalse: save sem resync");
$exc_sf = l7he_saved_exceptions();
check($exc_sf[0]["enabled"] === true, "save_exceptions savefalse: JSON eon[0] true");

/* POST edit savefalse — JSON completo sem exit do handler */
l7he_render(array(
	"get" => array("edit" => "1"),
	"post" => array(
		"save_exception_edit" => "1",
		"edit_exception_index" => "1",
		"edit_hosts" => "10.0.0.99",
		"edit_cidrs" => "192.0.2.0/25",
		"edit_priority" => "99999",
		"edit_action" => "monitor",
		"edit_exc_ifaces" => array("wan"),
	),
	"data" => $data_seed,
	"save_result" => false,
));
$fx = l7he_effects();
check($fx["save_calls"] === 1 && $fx["resync_calls"] === 0, "edit savefalse: save sem resync");
$exc_edit = l7he_saved_exceptions();
$mgmt = l7he_exc_by_id($exc_edit, "mgmt");
check($mgmt !== null, "edit savefalse: sentinel mgmt presente");
check($mgmt["action"] === "monitor", "edit savefalse: action monitor");
check($mgmt["priority"] === 99999, "edit savefalse: priority 99999");
check($mgmt["enabled"] === false, "edit savefalse: enabled false");
check($mgmt["hosts"] === array("10.0.0.99"), "edit savefalse: hosts");
check($mgmt["cidrs"] === array("192.0.2.0/25"), "edit savefalse: cidrs");
check($mgmt["interfaces"] === array("em1"), "edit savefalse: interface em1");
check(l7he_exc_by_id($exc_edit, "sentinel")["priority"] === 111, "edit savefalse: sentinel intacto");

/* POST delete sucesso */
l7he_render(array(
	"post" => array(
		"delete_exception" => "1",
		"delete_exception_index" => "0",
	),
	"data" => $data_seed,
	"save_result" => true,
));
$exc_del = l7he_saved_exceptions();
check(count($exc_del) === 1, "delete ok: uma excecao restante");
check(l7he_exc_by_id($exc_del, "mgmt") !== null, "delete ok: mgmt sobrevive");
check(l7he_exc_by_id($exc_del, "sentinel") === null, "delete ok: sentinel removida");

/* POST delete indice invalido */
l7he_render(array(
	"post" => array(
		"delete_exception" => "1",
		"delete_exception_index" => "9",
	),
	"data" => $data_seed,
));
$fx = l7he_effects();
check($fx["save_calls"] === 0 && $fx["resync_calls"] === 0, "delete idx invalido: zero save/resync");

/* parser real layer7.inc: limite 64 hosts (nao 16 do stub antigo) */
$many = layer7_parse_ip_textarea(implode("\n", array_map(function ($i) {
	return "10.0." . (int)($i / 256) . "." . ($i % 256);
}, range(1, 40))));
check(count($many) === 40, "inc-pure: parser IP real aceita >16 entradas validas");

$bad_cidr = layer7_parse_cidr_textarea("foo/bar\n192.0.2.0/24");
check(count($bad_cidr) === 1 && $bad_cidr[0] === "192.0.2.0/24", "inc-pure: CIDR invalido rejeitado");

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS EFFECTS TESTS FAILED\n");
	exit(1);
}
echo "ALL EXCEPTIONS EFFECTS TESTS PASSED\n";
exit(0);
