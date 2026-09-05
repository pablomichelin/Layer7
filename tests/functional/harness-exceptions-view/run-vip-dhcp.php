<?php
/**
 * Harness render — modo DHCP exclusivo V6b2a.
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/harness-exceptions-view/run-vip-dhcp.php
 */
require_once __DIR__ . "/bootstrap.php";

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
function has($html, $needle)
{
	return strpos($html, $needle) !== false;
}

$bridge = 'id="l7-vip-bookmark-bridge"';
$vip_action = 'action="layer7_exceptions.php#l7-vip-list"';
$dhcp_cfg = l7he_vip_dhcp_config();
$large_cfg = l7he_vip_dhcp_large_config();

echo "HARNESS RENDER — layer7_exceptions.php V6b2a DHCP\n";

/* GET vip_dhcp=1 */
$html_dhcp = l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"data" => l7he_vip_data(array()),
	"config" => $dhcp_cfg,
));
check(has($html_dhcp, 'name="add_vip_from_dhcp"'), "vip_dhcp: formulario DHCP");
check(has($html_dhcp, $vip_action), "vip_dhcp: action literal");
check(has($html_dhcp, 'vip_dhcp_ip[]'), "vip_dhcp: checkboxes");
check(!has($html_dhcp, $bridge), "vip_dhcp: sem ponte bookmark");
check(has($html_dhcp, 'href="layer7_exceptions.php?vip=1#l7-vip-list"'), "vip_dhcp: voltar lista");
check(!has($html_dhcp, 'name="save_vip_bulk"'), "vip_dhcp: sem lote");
check(has($html_dhcp, "l7-dhcp-iface-panel"), "vip_dhcp: paineis por interface");
check(!has($html_dhcp, "l7-dhcp-iface-section"), "vip_dhcp: sem wrapper interno legado");
check(!preg_match('/id="l7-dhcp-iface-filter"[^>]*style=/', $html_dhcp), "vip_dhcp: filtro sem style inline");
check(!preg_match('/class="l7-bulk-tools" style=/', $html_dhcp), "vip_dhcp: tools sem style inline");
check(
	strpos($html_dhcp, l7_t("Reservas DHCP (IPs prefixados)")) <
	    strpos($html_dhcp, "l7-dhcp-iface-panel"),
	"vip_dhcp: intro antes dos paineis de interface"
);

/* GET vip_dhcp invalido nao activa ponte */
$html_dhcp_bad = l7he_render(array(
	"get" => array("vip_dhcp" => "0"),
	"data" => l7he_data(array(l7he_exc("mgmt", array("hosts" => array("10.0.0.1"))))),
));
check(!has($html_dhcp_bad, $bridge), "vip_dhcp=0: sem ponte bookmark");

/* 0 reservas */
$html_no_maps = l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"data" => l7he_vip_data(array()),
	"config" => array("dhcpd" => array(), "interfaces" => array()),
));
check(has($html_no_maps, "Nenhuma reserva DHCP com IP nas interfaces"), "dhcp0: mensagem sem reservas");
check(!has($html_no_maps, 'name="add_vip_from_dhcp"'), "dhcp0: sem formulario");

/* todas ja VIP */
$data_all = l7he_vip_data(array(
	array("target" => "192.168.1.50", "description" => "LAN"),
	array("target" => "192.168.2.50", "description" => "WAN"),
));
$html_all = l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"data" => $data_all,
	"config" => $dhcp_cfg,
));
check(has($html_all, "Todas as reservas DHCP com IP ja estao na Lista VIP"), "dhcp all-vip: mensagem");
check(!has($html_all, 'name="add_vip_from_dhcp"'), "dhcp all-vip: sem formulario");

/* limite hosts */
$html_limit = l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"data" => l7he_vip_build_full(),
	"config" => $dhcp_cfg,
));
check(has($html_limit, "Limites da Lista VIP atingidos"), "dhcp limite32: aviso");
check(!has($html_limit, 'name="add_vip_from_dhcp"'), "dhcp limite32: sem formulario");

/* limite 32 hosts: POST erro preserva formulario e selecao disponivel */
$html_limit_post = l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("192.168.1.50"),
	),
	"data" => l7he_vip_build_full(),
	"config" => $dhcp_cfg,
	"save_result" => true,
));
check(has($html_limit_post, 'name="add_vip_from_dhcp"'), "dhcp limite32 POST: formulario retry");
check(has($html_limit_post, "Limites da Lista VIP atingidos"), "dhcp limite32 POST: aviso");
check(
	preg_match('/value="192\.168\.1\.50"[^>]*checked="checked"/', $html_limit_post) === 1 ||
	preg_match('/checked="checked"[^>]*value="192\.168\.1\.50"/', $html_limit_post) === 1,
	"dhcp limite32 POST: checkbox disponivel preservado"
);

$html_limit_savefalse = l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("192.168.1.50"),
	),
	"data" => l7he_vip_build_full(),
	"config" => $dhcp_cfg,
	"save_result" => false,
));
check(has($html_limit_savefalse, 'name="add_vip_from_dhcp"'), "dhcp limite32 savefalse: formulario retry");
check(
	preg_match('/value="192\.168\.1\.50"[^>]*checked="checked"/', $html_limit_savefalse) === 1 ||
	preg_match('/checked="checked"[^>]*value="192\.168\.1\.50"/', $html_limit_savefalse) === 1,
	"dhcp limite32 savefalse: checkbox disponivel preservado"
);

/* POST selecao vazia */
$html_empty_sel = l7he_render(array(
	"post" => array("add_vip_from_dhcp" => "1"),
	"data" => l7he_vip_data(array()),
	"config" => $dhcp_cfg,
));
check(has($html_empty_sel, 'name="add_vip_from_dhcp"'), "dhcp POST vazio: modo DHCP");
check(!has($html_empty_sel, 'checked="checked"'), "dhcp POST vazio: nenhum checkbox marcado");

/* POST erro preserva selecao disponivel */
$html_retry = l7he_render(array(
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("192.168.1.50", "9.9.9.9"),
	),
	"data" => l7he_vip_data(array()),
	"config" => $dhcp_cfg,
	"save_result" => false,
));
check(
	substr_count($html_retry, 'value="192.168.1.50"') >= 1 &&
	has($html_retry, 'checked="checked"'),
	"dhcp retry: selecao disponivel preservada"
);
check(!has($html_retry, 'value="9.9.9.9" checked'), "dhcp retry: IP inexistente nao marcado");

/* POST sucesso volta consulta mesmo com query vip_dhcp */
$html_ok_q = l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("192.168.1.50"),
	),
	"data" => l7he_vip_data(array()),
	"config" => $dhcp_cfg,
	"save_result" => true,
));
check(!has($html_ok_q, 'name="add_vip_from_dhcp"'), "dhcp sucesso+vip_dhcp: sem formulario DHCP");
check(has($html_ok_q, "192.168.1.50"), "dhcp sucesso+vip_dhcp: entrada na lista");

/* POST erro DHCP prevalece sobre query vip_add */
$html_dhcp_over_add = l7he_render(array(
	"get" => array("vip_add" => "1"),
	"post" => array("add_vip_from_dhcp" => "1"),
	"data" => l7he_vip_data(array()),
	"config" => $dhcp_cfg,
));
check(has($html_dhcp_over_add, 'name="add_vip_from_dhcp"'), "dhcp erro+vip_add: modo DHCP");
check(!has($html_dhcp_over_add, 'name="add_vip_entry"'), "dhcp erro+vip_add: sem modo manual");

/* POST sucesso volta consulta */
$html_ok = l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"post" => array(
		"add_vip_from_dhcp" => "1",
		"vip_dhcp_ip" => array("192.168.1.50"),
	),
	"data" => l7he_vip_data(array()),
	"config" => $dhcp_cfg,
	"save_result" => true,
));
check(!has($html_ok, 'name="add_vip_from_dhcp"'), "dhcp sucesso: sem formulario DHCP");
check(has($html_ok, "192.168.1.50"), "dhcp sucesso: entrada na lista");

/* POST geral invalido com ?vip_dhcp=1 nao esconde editor geral */
$ex_bad = array(l7he_exc("mgmt", array("hosts" => array("10.0.0.1"))));
$html_gen_dhcp = l7he_render(array(
	"get" => array("vip_dhcp" => "1", "new" => "1"),
	"post" => array(
		"add_exception" => "1",
		"new_id" => "bad id",
		"new_hosts" => "10.0.0.2",
	),
	"data" => l7he_data($ex_bad),
));
check(has($html_gen_dhcp, 'id="l7-add-exc"'), "POST geral erro+vip_dhcp: editor geral visivel");
check(!has($html_gen_dhcp, 'name="add_vip_from_dhcp"'), "POST geral erro+vip_dhcp: sem DHCP");

/* descricao adversarial escapada */
$adv_cfg = array(
	"dhcpd" => array(
		"lan" => array(
			"staticmap" => array(
				array(
					"ipaddr" => "192.168.9.9",
					"mac" => "aa:bb:cc:dd:ee:99",
					"descr" => '<script>x</script>',
				),
			),
		),
	),
	"interfaces" => array("lan" => array("descr" => "LAN")),
);
$html_adv = l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"data" => l7he_vip_data(array()),
	"config" => $adv_cfg,
));
check(!has($html_adv, "<script>x</script>"), "dhcp xss: descr nao literal");
check(has($html_adv, htmlspecialchars("<script>x</script>", ENT_QUOTES, "UTF-8")), "dhcp xss: descr escapada");

/* fixture grande: todos os checkboxes presentes */
$html_large = l7he_render(array(
	"get" => array("vip_dhcp" => "1"),
	"data" => l7he_vip_data(array()),
	"config" => $large_cfg,
));
$maps = layer7_dhcp_static_maps($large_cfg["dhcpd"], $large_cfg["dhcpdv6"]);
$cb_ok = true;
foreach ($maps as $m) {
	$ip = (string)($m["ip"] ?? "");
	if ($ip === "") {
		continue;
	}
	if (!has($html_large, 'value="' . $ip . '"')) {
		$cb_ok = false;
		break;
	}
}
check($cb_ok, "dhcp grande: todos os " . count($maps) . " checkboxes presentes");

/* IPv6 */
check(has($html_large, "2001:db8::1"), "dhcp grande: entrada IPv6 visivel");

if ($fail) {
	fwrite(STDERR, "SOME VIP DHCP HARNESS TESTS FAILED\n");
	exit(1);
}
echo "ALL VIP DHCP HARNESS TESTS PASSED\n";
exit(0);
