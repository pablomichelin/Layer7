<?php
/**
 * V6b — constantes VIP reais de layer7.inc + helpers extraidos (nao stubs 64/16).
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/test_exceptions_vip_pure.php
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

$inc = l7he_layer7_inc_path();
$src = l7he_layer7_inc_source();

check(defined("LAYER7_VIP_MAX_HOSTS"), "constante LAYER7_VIP_MAX_HOSTS definida");
check(defined("LAYER7_VIP_MAX_CIDRS"), "constante LAYER7_VIP_MAX_CIDRS definida");
check(LAYER7_VIP_MAX_HOSTS === 32, "layer7.inc: LAYER7_VIP_MAX_HOSTS=32 (daemon BG-072)");
check(LAYER7_VIP_MAX_CIDRS === 16, "layer7.inc: LAYER7_VIP_MAX_CIDRS=16");
check(
	LAYER7_VIP_MAX_HOSTS !== L7HE_VIP_MAX_HOSTS_WRONG_FIXTURE,
	"divergencia: fixture antigo bootstrap tinha " . L7HE_VIP_MAX_HOSTS_WRONG_FIXTURE .
	", produto layer7.inc exige " . LAYER7_VIP_MAX_HOSTS
);
check(
	preg_match('/define\s*\(\s*["\']LAYER7_VIP_MAX_HOSTS["\']\s*,\s*32\s*\)/', $src) === 1,
	"fonte layer7.inc declara LAYER7_VIP_MAX_HOSTS 32"
);
check(
	preg_match('/define\s*\(\s*["\']LAYER7_VIP_MAX_CIDRS["\']\s*,\s*16\s*\)/', $src) === 1,
	"fonte layer7.inc declara LAYER7_VIP_MAX_CIDRS 16"
);

$vip_helpers = array(
	"layer7_vip_validate_limits",
	"layer7_vip_list_entries",
	"layer7_vip_add_entry",
	"layer7_vip_remove_entry",
	"layer7_vip_import_from_raw",
	"layer7_vip_export_text",
	"layer7_dhcp_static_maps",
	"layer7_vip_add_from_dhcp_ips",
);
foreach ($vip_helpers as $fn) {
	check(function_exists($fn), "helper real extraido: {$fn}");
}

function l7he_reflection_body($fn)
{
	$ref = new ReflectionFunction($fn);
	$lines = file($ref->getFileName());
	return implode("", array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
}

function l7he_body_lacks_io($body, $label)
{
	global $fail;
	$forbidden = array(
		"exec(",
		"shell_exec(",
		"system(",
		"passthru(",
		"proc_open(",
		"popen(",
		"system_get_dhcpleases",
		"/usr/sbin/arp",
		"is_executable(",
	);
	foreach ($forbidden as $needle) {
		if (strpos($body, $needle) !== false) {
			echo "FAIL: {$label} contem IO proibido {$needle}\n";
			$fail = 1;
			return false;
		}
	}
	return true;
}

$daemon_body = l7he_reflection_body("layer7_daemon_version");
check(
	layer7_daemon_version() === L7HE_DAEMON_VERSION_FIXTURE,
	"stub daemon_version: fixture deterministico"
);
check(
	strpos($daemon_body, "L7HE_DAEMON_VERSION_FIXTURE") !== false,
	"stub daemon_version: corpo devolve fixture via constante pinada"
);
check(
	l7he_body_lacks_io($daemon_body, "stub daemon_version"),
	"stub daemon_version: corpo sem exec/ARP"
);
$ref_daemon = new ReflectionFunction("layer7_daemon_version");
check(
	strpos((string)$ref_daemon->getFileName(), "inc-pure.php") !== false,
	"stub daemon_version: origem inc-pure.php"
);
check(
	l7he_extract_function_src($src, "layer7_daemon_version") !== null,
	"fonte layer7.inc: layer7_daemon_version existe (nao extraida)"
);

$GLOBALS["l7he_mac_resolve_fixture"] = array(
	"aa:bb:cc:dd:ee:01" => "192.168.10.50",
);
$mac_body = l7he_reflection_body("layer7_resolve_macs_to_ips");
$resolved = layer7_resolve_macs_to_ips(array("aa:bb:cc:dd:ee:01", "ff:ff:ff:ff:ff:ff"));
check($resolved === array("192.168.10.50"), "stub resolve_macs_to_ips: fixture deterministico");
check(
	strpos($mac_body, "l7he_mac_resolve_fixture") !== false,
	"stub resolve_macs_to_ips: corpo usa fixture"
);
check(
	l7he_body_lacks_io($mac_body, "stub resolve_macs_to_ips"),
	"stub resolve_macs_to_ips: corpo sem exec/ARP/leases"
);
$ref_mac = new ReflectionFunction("layer7_resolve_macs_to_ips");
check(
	strpos((string)$ref_mac->getFileName(), "inc-pure.php") !== false,
	"stub resolve_macs_to_ips: origem inc-pure.php"
);
unset($GLOBALS["l7he_mac_resolve_fixture"]);

$data_groups = array(
	"layer7" => array(
		"groups" => array(
			array(
				"id" => "gestores",
				"name" => "Gestores",
				"cidrs" => array("192.168.10.0/24"),
				"device_macs" => array("aa:bb:cc:dd:ee:01"),
			),
		),
		"exceptions" => array(),
	),
);
$res_grp = layer7_upsert_vip_exception($data_groups, array("gestores"), "192.168.1.100\n", "");
check(!empty($res_grp["ok"]), "upsert_vip_exception: grupo + host manual sem ARP real");
$vip_grp = layer7_find_vip_exception($data_groups);
$hosts_grp = isset($vip_grp["hosts"]) && is_array($vip_grp["hosts"]) ? $vip_grp["hosts"] : array();
$cidrs_grp = isset($vip_grp["cidrs"]) && is_array($vip_grp["cidrs"]) ? $vip_grp["cidrs"] : array();
check(in_array("192.168.1.100", $hosts_grp, true), "upsert_vip_exception: host manual preservado");
check(in_array("192.168.10.0/24", $cidrs_grp, true), "upsert_vip_exception: CIDR do grupo expandido (helper real)");

$payload = layer7_vip_export_payload(l7he_vip_data(array(
	array("target" => "10.0.0.1", "description" => "A"),
)));
check(
	isset($payload["version"]) && $payload["version"] === L7HE_DAEMON_VERSION_FIXTURE,
	"export_payload: versao via stub daemon_version"
);

$hosts32 = array();
for ($i = 1; $i <= 32; $i++) {
	$hosts32[] = "10.0.0." . $i;
}
check(layer7_vip_validate_limits($hosts32, array()) === "", "validate_limits: 32 hosts OK");
$hosts33 = $hosts32;
$hosts33[] = "10.0.0.33";
$err33 = layer7_vip_validate_limits($hosts33, array());
check($err33 !== "" && strpos($err33, "32") !== false, "validate_limits: 33 hosts rejeitados");

$cidrs16 = array();
for ($i = 0; $i < 16; $i++) {
	$cidrs16[] = "192.168." . $i . ".0/24";
}
check(layer7_vip_validate_limits(array(), $cidrs16) === "", "validate_limits: 16 CIDRs OK");
$cidrs17 = $cidrs16;
$cidrs17[] = "192.168.99.0/24";
$err_cidr = layer7_vip_validate_limits(array(), $cidrs17);
check($err_cidr !== "" && strpos($err_cidr, "16") !== false, "validate_limits: 17 CIDRs rejeitados");

$data = l7he_vip_data(array(
	array("target" => "192.168.1.50", "description" => "Director"),
));
$rows = layer7_vip_list_entries($data);
check(count($rows) === 1, "list_entries: uma entrada");
check($rows[0]["target"] === "192.168.1.50", "list_entries: target");
check($rows[0]["description"] === "Director", "list_entries: descricao");
check($rows[0]["kind"] === "host", "list_entries: kind host");

$res_add = layer7_vip_add_entry($data, "Lab", "10.0.0.99");
check(!empty($res_add["ok"]), "add_entry: sucesso");
check(count(layer7_vip_list_entries($data)) === 2, "add_entry: segunda entrada");

$res_dup = layer7_vip_add_entry($data, "Dup", "10.0.0.99");
check(empty($res_dup["ok"]), "add_entry: duplicado rejeitado");

$res_bad = layer7_vip_add_entry($data, "Bad", "not-an-ip");
check(empty($res_bad["ok"]), "add_entry: alvo invalido rejeitado");

$res_empty_desc = layer7_vip_add_entry($data, "", "10.0.0.100");
check(empty($res_empty_desc["ok"]), "add_entry: descricao vazia rejeitada");

$res_remove = layer7_vip_remove_entry($data, "192.168.1.50");
check(!empty($res_remove["ok"]), "remove_entry: sucesso");
check(count(layer7_vip_list_entries($data)) === 1, "remove_entry: uma entrada restante");

$export = layer7_vip_export_text($data, false);
check(strpos($export, "10.0.0.99") !== false, "export_text: inclui entrada restante");

$fresh = l7he_vip_data(array());
$imp = layer7_vip_import_from_raw($fresh, "10.1.0.5, Importado\n10.1.0.6, Outro\n");
check(!empty($imp["ok"]), "import_from_raw: texto simples OK");
check(count(layer7_vip_list_entries($fresh)) === 2, "import_from_raw: duas entradas");

global $config;
$config = array(
	"dhcpd" => array(
		"lan" => array(
			"staticmap" => array(
				array(
					"ipaddr" => "192.168.1.60",
					"mac" => "aa:bb:cc:dd:ee:01",
					"hostname" => "silvana",
					"descr" => "Silvana",
				),
			),
		),
	),
	"interfaces" => array(
		"lan" => array("descr" => "LAN"),
	),
);
$maps = layer7_dhcp_static_maps();
check(count($maps) === 1 && $maps[0]["ip"] === "192.168.1.60", "dhcp_static_maps: le config harness");
$dhcp_data = l7he_vip_data(array());
$dhcp_res = layer7_vip_add_from_dhcp_ips($dhcp_data, array("192.168.1.60"), $maps);
check(!empty($dhcp_res["ok"]) && (int)$dhcp_res["added"] === 1, "add_from_dhcp_ips: uma entrada");

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS VIP PURE TESTS FAILED\n");
	exit(1);
}
echo "ALL EXCEPTIONS VIP PURE TESTS PASSED\n";
exit(0);
