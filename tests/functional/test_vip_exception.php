<?php
/*
 * BG-064 / BG-071 — excepcao VIP vip-isentos e Lista VIP global.
 * Uso: php tests/functional/test_vip_exception.php
 */
$root = dirname(__DIR__, 2);
require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

$data = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"groups" => array(
			array(
				"id" => "gestores",
				"name" => "Gestores",
				"cidrs" => array("192.168.10.0/24"),
				"device_macs" => array("aa:bb:cc:dd:ee:01"),
				"device_ips" => array("192.168.10.50")
			)
		),
		"exceptions" => array()
	)
);

$res = layer7_upsert_vip_exception(
	$data,
	array("gestores"),
	"192.168.1.100\n",
	""
);
if (!$res["ok"] || !$res["updated"]) {
	fwrite(STDERR, "FAIL: upsert VIP should succeed\n");
	exit(1);
}

$vip = layer7_find_vip_exception($data);
if ($vip === null || ($vip["id"] ?? "") !== "vip-isentos") {
	fwrite(STDERR, "FAIL: vip-isentos not found\n");
	exit(1);
}
if (($vip["action"] ?? "") !== "allow" || empty($vip["enabled"])) {
	fwrite(STDERR, "FAIL: VIP exception must be enabled allow\n");
	exit(1);
}
$hosts = isset($vip["hosts"]) && is_array($vip["hosts"]) ? $vip["hosts"] : array();
if (!in_array("192.168.1.100", $hosts, true) ||
    !in_array("192.168.10.50", $hosts, true)) {
	fwrite(STDERR, "FAIL: VIP hosts missing manual or group-expanded IP\n");
	fwrite(STDERR, json_encode($hosts));
	exit(1);
}
$cidrs = isset($vip["cidrs"]) && is_array($vip["cidrs"]) ? $vip["cidrs"] : array();
if (!in_array("192.168.10.0/24", $cidrs, true)) {
	fwrite(STDERR, "FAIL: VIP cidrs missing group CIDR\n");
	exit(1);
}
if (!isset($vip["source_groups"]) || !in_array("gestores", $vip["source_groups"], true)) {
	fwrite(STDERR, "FAIL: source_groups metadata missing\n");
	exit(1);
}

/* Actualizacao: substituir conteudo */
$res2 = layer7_upsert_vip_exception($data, array(), "10.0.0.5", "");
if (!$res2["ok"]) {
	fwrite(STDERR, "FAIL: VIP update failed\n");
	exit(1);
}
$vip2 = layer7_find_vip_exception($data);
$hosts2 = isset($vip2["hosts"]) && is_array($vip2["hosts"]) ? $vip2["hosts"] : array();
if (count($hosts2) !== 1 || $hosts2[0] !== "10.0.0.5") {
	fwrite(STDERR, "FAIL: VIP update should replace hosts\n");
	fwrite(STDERR, json_encode($hosts2));
	exit(1);
}

/* BG-071: labels save/cleanup */
$data["layer7"]["vip_meta"]["labels"] = array(
	"10.0.0.5" => "Director",
	"orphan" => "Stale"
);
layer7_vip_labels_cleanup($data, $hosts2, array());
$labels = layer7_vip_get_labels($data);
if (($labels["10.0.0.5"] ?? "") !== "Director") {
	fwrite(STDERR, "FAIL: label for 10.0.0.5 missing\n");
	exit(1);
}
if (isset($labels["orphan"])) {
	fwrite(STDERR, "FAIL: orphan label not cleaned up\n");
	exit(1);
}

/* BG-071: add entry with label */
$res3 = layer7_vip_add_entry($data, "Financeiro", "192.168.2.10");
if (!$res3["ok"]) {
	fwrite(STDERR, "FAIL: add VIP entry failed: " . $res3["error"] . "\n");
	exit(1);
}
$labels2 = layer7_vip_get_labels($data);
if (($labels2["192.168.2.10"] ?? "") !== "Financeiro") {
	fwrite(STDERR, "FAIL: label not saved on add\n");
	exit(1);
}

/* BG-072: limit validation (LAYER7_VIP_MAX_HOSTS hosts) */
$bulk_hosts = array();
for ($i = 1; $i <= LAYER7_VIP_MAX_HOSTS; $i++) {
	$bulk_hosts[] = "10.1.0." . $i;
}
$err = layer7_vip_validate_limits($bulk_hosts, array());
if ($err !== "") {
	fwrite(STDERR, "FAIL: max hosts should pass validation\n");
	exit(1);
}
$bulk_hosts[] = "10.1.0.99";
$err2 = layer7_vip_validate_limits($bulk_hosts, array());
if ($err2 === "") {
	fwrite(STDERR, "FAIL: max+1 hosts should fail validation\n");
	exit(1);
}

/* BG-071: export/import round-trip JSON legado */
$export = layer7_vip_export_payload($data);
if (empty($export["layer7_vip_list"]) || !is_array($export["entries"])) {
	fwrite(STDERR, "FAIL: export payload invalid\n");
	exit(1);
}

$fresh = array("layer7" => array("enabled" => true, "exceptions" => array()));
$imp = layer7_vip_import_apply($fresh, $export);
if (!$imp["ok"]) {
	fwrite(STDERR, "FAIL: import failed: " . $imp["error"] . "\n");
	exit(1);
}
$imported_rows = layer7_vip_list_entries($fresh);
if (count($imported_rows) < 2) {
	fwrite(STDERR, "FAIL: import should restore entries\n");
	fwrite(STDERR, json_encode($imported_rows));
	exit(1);
}
$imp_labels = layer7_vip_get_labels($fresh);
if (($imp_labels["192.168.2.10"] ?? "") !== "Financeiro") {
	fwrite(STDERR, "FAIL: import lost label\n");
	exit(1);
}

/* BG-124: texto simples IP, nome */
$text = layer7_vip_export_text($data, true);
if (strpos($text, "192.168.2.10, Financeiro") === false) {
	fwrite(STDERR, "FAIL: text export missing entry\n");
	fwrite(STDERR, $text);
	exit(1);
}
$fresh_txt = array("layer7" => array("enabled" => true, "exceptions" => array()));
$imp_txt = layer7_vip_import_from_raw($fresh_txt, $text);
if (!$imp_txt["ok"]) {
	fwrite(STDERR, "FAIL: text import failed: " . $imp_txt["error"] . "\n");
	exit(1);
}
$txt_labels = layer7_vip_get_labels($fresh_txt);
if (($txt_labels["192.168.2.10"] ?? "") !== "Financeiro") {
	fwrite(STDERR, "FAIL: text import lost label\n");
	exit(1);
}

/* Nome primeiro, virgula, IP */
$fresh_name = array("layer7" => array("enabled" => true, "exceptions" => array()));
$imp_name = layer7_vip_import_from_raw($fresh_name, "Silvana, 192.168.1.60\nJessika, 192.168.1.62\n");
if (!$imp_name["ok"]) {
	fwrite(STDERR, "FAIL: name-first import failed: " . $imp_name["error"] . "\n");
	exit(1);
}
$name_labels = layer7_vip_get_labels($fresh_name);
if (($name_labels["192.168.1.60"] ?? "") !== "Silvana" ||
    ($name_labels["192.168.1.62"] ?? "") !== "Jessika") {
	fwrite(STDERR, "FAIL: name-first labels wrong\n");
	fwrite(STDERR, json_encode($name_labels) . "\n");
	exit(1);
}

/* JSON legado com virgula final (edicao manual) */
$trailing = <<<'JSON'
{
    "layer7_vip_list": true,
    "entries": [
        {
            "description": "Cel Elisio",
            "target": "192.168.1.78"
        },
    ]
}
JSON;
$fresh_json = array("layer7" => array("enabled" => true, "exceptions" => array()));
$imp_json = layer7_vip_import_from_raw($fresh_json, $trailing);
if (!$imp_json["ok"]) {
	fwrite(STDERR, "FAIL: trailing-comma JSON should import: " . $imp_json["error"] . "\n");
	exit(1);
}
$json_labels = layer7_vip_get_labels($fresh_json);
if (($json_labels["192.168.1.78"] ?? "") !== "Cel Elisio") {
	fwrite(STDERR, "FAIL: trailing-comma JSON lost label\n");
	exit(1);
}

/* Comentarios, linhas vazias e cabecalho */
$fresh_hdr = array("layer7" => array("enabled" => true, "exceptions" => array()));
$imp_hdr = layer7_vip_import_from_raw($fresh_hdr,
    "# comentario\n\ndescricao, ip\n192.168.1.71, Cel Supervisor Apoio\n");
if (!$imp_hdr["ok"]) {
	fwrite(STDERR, "FAIL: header/comment import failed: " . $imp_hdr["error"] . "\n");
	exit(1);
}
$hdr_rows = layer7_vip_list_entries($fresh_hdr);
if (count($hdr_rows) !== 1 || ($hdr_rows[0]["target"] ?? "") !== "192.168.1.71") {
	fwrite(STDERR, "FAIL: header/comment should leave one entry\n");
	fwrite(STDERR, json_encode($hdr_rows) . "\n");
	exit(1);
}

/* Linha invalida falha fechado */
$fresh_bad = array("layer7" => array("enabled" => true, "exceptions" => array()));
$imp_bad = layer7_vip_import_from_raw($fresh_bad, "192.168.1.60, Silvana\nnao-e-um-ip, Fulano\n");
if ($imp_bad["ok"]) {
	fwrite(STDERR, "FAIL: invalid line should fail closed\n");
	exit(1);
}
if (strpos($imp_bad["error"], "Linha 2") === false) {
	fwrite(STDERR, "FAIL: invalid line should cite Linha 2: " . $imp_bad["error"] . "\n");
	exit(1);
}

/* PF: excepcao allow gera tabela exc_allow */
$pf_exc = layer7_exception_allow_rules_text($data, array("enabled" => false));
if (strpos($pf_exc, "layer7_exc_allow_0") === false ||
    strpos($pf_exc, "tag L7ALLOW") === false) {
	fwrite(STDERR, "FAIL: VIP exception must emit L7ALLOW PF rule\n");
	fwrite(STDERR, $pf_exc);
	exit(1);
}

/* BG-075: meta VIP expoe entradas para materializacao PF live */
$meta = layer7_exception_allow_meta($data);
if (empty($meta) || empty($meta[0]["entries"]) ||
    !in_array("10.0.0.5", $meta[0]["entries"], true)) {
	fwrite(STDERR, "FAIL: exception_allow_meta must expose VIP hosts\n");
	exit(1);
}
if (!function_exists("layer7_static_origin_tables_apply_to_pf") ||
    !function_exists("layer7_pf_table_replace_entries")) {
	fwrite(STDERR, "FAIL: static origin PF apply helpers missing\n");
	exit(1);
}
/* Em lab sem pfctl real, o helper ainda conta entradas validas. */
$n_apply = layer7_static_origin_tables_apply_to_pf($data, array("enabled" => false));
if ($n_apply < 1) {
	fwrite(STDERR, "FAIL: static origin apply should count VIP entries\n");
	exit(1);
}

/* BG-125: reservas DHCP staticmap */
$dhcpd_fake = array(
	"lan" => array(
		"staticmap" => array(
			array(
				"ipaddr" => "192.168.1.60",
				"mac" => "aa:bb:cc:dd:ee:01",
				"hostname" => "silvana-pc",
				"descr" => "Silvana"
			),
			array(
				"ipaddr" => "192.168.1.61",
				"mac" => "aa:bb:cc:dd:ee:02",
				"hostname" => "impressora"
			),
			array(
				"mac" => "aa:bb:cc:dd:ee:03"
			)
		)
	)
);
$maps = layer7_dhcp_static_maps($dhcpd_fake, array());
if (count($maps) !== 2) {
	fwrite(STDERR, "FAIL: static maps should skip entries without IP\n");
	fwrite(STDERR, json_encode($maps) . "\n");
	exit(1);
}
if ($maps[0]["ip"] !== "192.168.1.60" || $maps[0]["label"] !== "Silvana") {
	fwrite(STDERR, "FAIL: descr should win over hostname\n");
	exit(1);
}
if ($maps[1]["ip"] !== "192.168.1.61" || $maps[1]["label"] !== "impressora") {
	fwrite(STDERR, "FAIL: hostname should be used when descr empty\n");
	exit(1);
}
$grouped = layer7_dhcp_maps_group_by_iface($maps);
if (count($grouped) !== 1 || count($grouped[0]["entries"]) !== 2 ||
    ($grouped[0]["ifid"] ?? "") !== "lan") {
	fwrite(STDERR, "FAIL: maps should group by interface\n");
	fwrite(STDERR, json_encode($grouped) . "\n");
	exit(1);
}
$idx = layer7_dhcp_ip_iface_index($maps);
if (($idx["192.168.1.60"]["ifid"] ?? "") !== "lan") {
	fwrite(STDERR, "FAIL: IP iface index missing lan\n");
	exit(1);
}
$dhcpd_two = $dhcpd_fake;
$dhcpd_two["opt1"] = array(
	"staticmap" => array(
		array(
			"ipaddr" => "10.10.10.5",
			"mac" => "aa:bb:cc:dd:ee:10",
			"descr" => "Sala2"
		)
	)
);
$maps_two = layer7_dhcp_static_maps($dhcpd_two, array());
$grouped_two = layer7_dhcp_maps_group_by_iface($maps_two);
if (count($grouped_two) !== 2) {
	fwrite(STDERR, "FAIL: two interfaces should yield two columns\n");
	fwrite(STDERR, json_encode($grouped_two) . "\n");
	exit(1);
}

$fresh_dhcp = array("layer7" => array("enabled" => true, "exceptions" => array()));
$imp_dhcp = layer7_vip_add_from_dhcp_ips($fresh_dhcp, array("192.168.1.60", "10.9.9.9"), $maps);
if (!$imp_dhcp["ok"] || (int)$imp_dhcp["added"] !== 1) {
	fwrite(STDERR, "FAIL: DHCP add should import only mapped IPs\n");
	fwrite(STDERR, json_encode($imp_dhcp) . "\n");
	exit(1);
}
$dhcp_labels = layer7_vip_get_labels($fresh_dhcp);
if (($dhcp_labels["192.168.1.60"] ?? "") !== "Silvana") {
	fwrite(STDERR, "FAIL: DHCP add lost label\n");
	exit(1);
}
$imp_dup = layer7_vip_add_from_dhcp_ips($fresh_dhcp, array("192.168.1.60"), $maps);
if ($imp_dup["ok"]) {
	fwrite(STDERR, "FAIL: duplicate DHCP IP should not report success\n");
	exit(1);
}

echo "PASS: test_vip_exception.php\n";
exit(0);
