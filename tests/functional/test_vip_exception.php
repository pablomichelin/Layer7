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

/* BG-071: export/import round-trip */
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

echo "PASS: test_vip_exception.php\n";
exit(0);
