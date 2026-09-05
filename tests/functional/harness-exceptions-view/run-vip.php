<?php
/**
 * Harness render — layer7_exceptions.php (V6b1 Lista VIP modos exclusivos).
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/harness-exceptions-view/run-vip.php
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

$e0 = array();
$e1 = array(l7he_exc("mgmt", array(
	"hosts" => array("10.0.0.1"),
	"priority" => 500,
	"action" => "allow",
)));

echo "HARNESS RENDER — layer7_exceptions.php V6b1 VIP\n";

/* consulta geral: sem VIP, com ponte */
$html_general = l7he_render(array("data" => l7he_data($e1)));
check(!has($html_general, 'id="l7-vip-list"'), "geral: sem bloco VIP");
check(has($html_general, $bridge), "geral: ponte bookmark presente");
check(has($html_general, 'href="layer7_exceptions.php?vip=1#l7-vip-list"'), "geral: nav Lista VIP");

$vip_dhcp_cfg = array(
	"dhcpd" => array(
		"lan" => array(
			"staticmap" => array(
				array("ipaddr" => "192.168.1.50", "mac" => "aa:bb:cc:dd:ee:01"),
			),
		),
		"wan" => array(
			"staticmap" => array(
				array("ipaddr" => "192.168.2.50", "mac" => "aa:bb:cc:dd:ee:02"),
			),
		),
	),
	"interfaces" => array(
		"lan" => array("descr" => "LAN"),
		"wan" => array("descr" => "WAN"),
	),
);

/* ?vip=1 */
$html_vip = l7he_render(array(
	"get" => array("vip" => "1"),
	"data" => l7he_vip_data(array(
		array("target" => "10.0.0.10", "description" => "vip-a"),
	)),
	"config" => $vip_dhcp_cfg,
));
check(has($html_vip, 'id="l7-vip-list"'), "vip=1: bloco VIP");
check(!has($html_vip, 'id="l7-exceptions"'), "vip=1: sem lista geral");
check(!has($html_vip, $bridge), "vip=1: sem ponte bookmark");
check(has($html_vip, $vip_action), "vip=1: action literal #l7-vip-list");
check(has($html_vip, 'vip_dhcp=1#l7-vip-list'), "vip=1: link modo DHCP exclusivo");
check(!has($html_vip, 'name="add_vip_from_dhcp"'), "vip=1: sem formulario DHCP embutido");
check(has($html_vip, 'id="l7-vip-iface-filter"'), "vip=1: filtro interface");
check(has($html_vip, "l7-vip-iface-btn"), "vip=1: botoes filtro");
check(has($html_vip, 'vip_bulk=1#l7-vip-list'), "vip=1: link modo bulk exclusivo");
check(has($html_vip, 'vip_import=1#l7-vip-list'), "vip=1: link modo import exclusivo");
check(!has($html_vip, 'name="save_vip_bulk"'), "vip=1: sem lote embutido");
check(!has($html_vip, 'name="import_vip_list"'), "vip=1: sem import embutido");
check(has($html_vip, 'name="export_vip_list"'), "vip=1: export literal");
check(substr_count($html_vip, $vip_action) >= 2, "vip=1: multiplos forms action literal");

/* ?vip_add=1 */
$html_vip_add = l7he_render(array(
	"get" => array("vip_add" => "1"),
	"data" => l7he_vip_data(array()),
));
check(has($html_vip_add, 'name="add_vip_entry" value="1"'), "vip_add: form add");
check(has($html_vip_add, $vip_action), "vip_add: action literal");
check(!has($html_vip_add, $bridge), "vip_add: sem ponte bookmark");
check(!has($html_vip_add, 'name="add_vip_from_dhcp"'), "vip_add: sem DHCP");
$html_vip_err = l7he_render(array(
	"post" => array(
		"add_vip_entry" => "1",
		"vip_description" => "desc-teste",
		"vip_target" => "not-an-ip",
	),
	"data" => l7he_vip_data(array()),
));
check(!has($html_vip_err, $bridge), "POST add_vip erro: sem ponte bookmark");
check(has($html_vip_err, 'value="desc-teste"'), "POST add_vip erro: descricao raw");
check(has($html_vip_err, 'value="not-an-ip"'), "POST add_vip erro: target raw");
check(has($html_vip_err, 'name="add_vip_entry"'), "POST add_vip erro: form visivel");

/* POST add_vip savefalse */
$html_vip_sf = l7he_render(array(
	"post" => array(
		"add_vip_entry" => "1",
		"vip_description" => "sf-desc",
		"vip_target" => "10.0.0.50",
	),
	"data" => l7he_vip_data(array()),
	"save_result" => false,
));
check(!has($html_vip_sf, $bridge), "POST add_vip savefalse: sem ponte");
check(has($html_vip_sf, 'value="sf-desc"'), "POST add_vip savefalse: descricao raw");

/* POST remove_vip savefalse */
$html_vip_rm = l7he_render(array(
	"post" => array(
		"remove_vip_entry" => "1",
		"vip_remove_target" => "10.0.0.1",
	),
	"data" => l7he_vip_data(array(array(
		"target" => "10.0.0.1",
		"description" => "x",
	))),
	"save_result" => false,
));
check(!has($html_vip_rm, $bridge), "POST remove savefalse: sem ponte");
check(has($html_vip_rm, 'id="l7-vip-list"'), "POST remove: modo lista VIP");

/* GET new/edit: sem VIP e sem ponte */
$html_new = l7he_render(array(
	"get" => array("new" => "1"),
	"data" => l7he_data($e1),
));
check(!has($html_new, 'id="l7-vip-list"'), "new: sem VIP");
check(!has($html_new, $bridge), "new: sem ponte bookmark");

$html_edit = l7he_render(array(
	"get" => array("edit" => "0"),
	"data" => l7he_data($e1),
));
check(!has($html_edit, 'id="l7-vip-list"'), "edit: sem VIP");
check(!has($html_edit, $bridge), "edit: sem ponte bookmark");

/* onsubmit remover: aspas simples + json_encode */
check(
	preg_match("/onsubmit='return confirm\\(/", $html_vip) === 1,
	"vip=1: onsubmit remover aspas simples"
);

/* help VIP */
check(has($html_vip_add, "Um IP (IPv4/IPv6) ou CIDR por entrada."), "vip_add: help IP/CIDR");

/* nav sem style inline nas pills */
check(!preg_match('/<ul class="nav nav-pills"[^>]*style=/', $html_general), "geral: nav sem style inline");

/* 0 entradas VIP */
$html_vip0 = l7he_render(array(
	"get" => array("vip" => "1"),
	"data" => l7he_vip_data(array()),
));
check(has($html_vip0, "Nenhum isento directo na Lista VIP."), "vip0: mensagem lista vazia");
check(!has($html_vip0, 'name="remove_vip_entry"'), "vip0: sem form remover");

/* 1 entrada */
$html_vip1 = l7he_render(array(
	"get" => array("vip" => "1"),
	"data" => l7he_vip_data(array(array("target" => "10.0.0.42", "description" => "Unico"))),
	"config" => $vip_dhcp_cfg,
));
check(substr_count($html_vip1, 'name="remove_vip_entry"') === 1, "vip1: um form remover");
check(has($html_vip1, "10.0.0.42"), "vip1: target visivel");

/* 48 entradas (32 hosts + 16 CIDRs) */
$html_vip48 = l7he_render(array(
	"get" => array("vip" => "1"),
	"data" => l7he_vip_build_full(),
	"config" => $vip_dhcp_cfg,
));
check(substr_count($html_vip48, 'name="remove_vip_entry"') === 48, "vip48: 48 forms remover");
check(substr_count($html_vip48, "10.0.0.32") >= 1, "vip48: ultimo host presente");
check(substr_count($html_vip48, "192.168.15.0/24") >= 1, "vip48: ultimo CIDR presente");

/* limite cheio + retry add preserva raw */
$html_limite = l7he_render(array(
	"post" => array(
		"add_vip_entry" => "1",
		"vip_description" => "raw-limite",
		"vip_target" => "10.0.0.99",
	),
	"data" => l7he_vip_build_full(),
));
check(has($html_limite, 'value="raw-limite"'), "limite48 retry: descricao raw");
check(has($html_limite, 'value="10.0.0.99"'), "limite48 retry: target raw");
check(has($html_limite, 'name="add_vip_entry"'), "limite48 retry: form add visivel");

/* XSS adversarial escapado na lista */
$xss_desc = '<script>alert("x")</script>';
$html_xss = l7he_render(array(
	"get" => array("vip" => "1"),
	"data" => l7he_vip_data(array(array("target" => "10.0.0.5", "description" => $xss_desc))),
));
check(!has($html_xss, $xss_desc), "xss: descricao nao literal no HTML");
check(has($html_xss, htmlspecialchars($xss_desc, ENT_QUOTES, "UTF-8")), "xss: descricao escapada");

/* DNS modes via stub parametrizavel */
$html_dns_rdr = l7he_render(array(
	"get" => array("vip" => "1"),
	"data" => l7he_vip_data(array()),
	"vip_dns_mode" => "rdr_fallback",
));
check(has($html_dns_rdr, "Aviso DNS (fallback)"), "dns: rdr_fallback alerta");
$html_dns_ub = l7he_render(array(
	"get" => array("vip" => "1"),
	"data" => l7he_vip_data(array()),
	"vip_dns_mode" => "unbound_view",
));
check(has($html_dns_ub, "Isencao DNS"), "dns: unbound_view alerta");

/* grupos isentos adversariais escapados */
$html_grp = l7he_render(array(
	"get" => array("vip" => "1"),
	"data" => l7he_vip_data(array(), array(), array("source_groups" => array('Grupo "A"', "Gestores & VIP"))),
));
check(has($html_grp, htmlspecialchars('Grupo "A"', ENT_QUOTES, "UTF-8")), "grupos: nome adversarial escapado");

/* POST add sucesso com GET vip_add conflitante volta a lista */
$html_add_ok = l7he_render(array(
	"get" => array("vip_add" => "1"),
	"post" => array(
		"add_vip_entry" => "1",
		"vip_description" => "Novo",
		"vip_target" => "10.0.0.77",
	),
	"data" => l7he_vip_data(array()),
	"save_result" => true,
));
check(!has($html_add_ok, 'name="add_vip_entry"'), "add ok+vip_add: sem form add");
check(has($html_add_ok, 'id="l7-vip-list"'), "add ok+vip_add: lista VIP");
check(has($html_add_ok, "10.0.0.77"), "add ok+vip_add: entrada visivel");

/* POST geral invalido com ?vip=1 nao esconde editor geral */
$ex_bad = array(l7he_exc("mgmt", array("hosts" => array("10.0.0.1"))));
$html_gen_vip = l7he_render(array(
	"get" => array("vip" => "1", "new" => "1"),
	"post" => array(
		"add_exception" => "1",
		"new_id" => "",
		"new_hosts" => "10.0.0.1",
	),
	"data" => l7he_data($ex_bad),
));
check(has($html_gen_vip, 'id="l7-input-errors"'), "post geral+vip: erros visiveis");
check(has($html_gen_vip, 'name="add_exception"'), "post geral+vip: editor geral preservado");

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS VIP HARNESS TESTS FAILED\n");
	exit(1);
}
echo "ALL EXCEPTIONS VIP HARNESS TESTS PASSED\n";
exit(0);
