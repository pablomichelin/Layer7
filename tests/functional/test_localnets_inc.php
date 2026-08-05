<?php
/*
 * GV1.6 / S-02 — tabela layer7_localnets (nao depende de localsubnets CE/Plus).
 * Uso: php tests/functional/test_localnets_inc.php
 */
$root = dirname(__DIR__, 2);
require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

if (layer7_pf_localnets_table_name() !== "layer7_localnets") {
	fwrite(STDERR, "FAIL: unexpected localnets table name\n");
	exit(1);
}
if (layer7_pf_not_localnets_clause() !== "to !<layer7_localnets>") {
	fwrite(STDERR, "FAIL: not_localnets clause\n");
	exit(1);
}

$c4 = layer7_ipv4_network_cidr("192.168.100.254", 24);
if ($c4 !== "192.168.100.0/24") {
	fwrite(STDERR, "FAIL: ipv4 network cidr got {$c4}\n");
	exit(1);
}
$c6 = layer7_ipv6_network_cidr("2804:6c4:11d:cc00:250:56ff:fe88:a4f7", 64);
if ($c6 === null || !preg_match('#^2804:6c4:11d:cc00::/64$#', $c6)) {
	fwrite(STDERR, "FAIL: ipv6 network cidr got " . var_export($c6, true) . "\n");
	exit(1);
}

$nets = layer7_pf_collect_local_networks();
if (!in_array("fe80::/10", $nets, true)) {
	fwrite(STDERR, "FAIL: fe80::/10 must always be in localnets\n");
	exit(1);
}

/* Simular config de interfaces (sem bootstrap pfSense). */
$GLOBALS["config"] = array(
	"interfaces" => array(
		"lan" => array(
			"ipaddr" => "192.168.100.254",
			"subnet" => "24",
			"ipaddrv6" => "2804:6c4:11d:cc00:250:56ff:fe88:a4f7",
			"subnetv6" => "64"
		)
	)
);
$nets2 = layer7_pf_collect_local_networks();
if (!in_array("192.168.100.0/24", $nets2, true) ||
    !in_array("2804:6c4:11d:cc00::/64", $nets2, true) ||
    !in_array("fe80::/10", $nets2, true)) {
	fwrite(STDERR, "FAIL: collect from config missed LAN v4/v6\n");
	fwrite(STDERR, implode(", ", $nets2) . "\n");
	exit(1);
}

$tbl = layer7_pf_localnets_table_text();
if (strpos($tbl, "table <layer7_localnets> persist {") === false ||
    strpos($tbl, "fe80::/10") === false ||
    strpos($tbl, "192.168.100.0/24") === false ||
    strpos($tbl, "2804:6c4:11d:cc00::/64") === false) {
	fwrite(STDERR, "FAIL: localnets table text incomplete\n");
	fwrite(STDERR, $tbl);
	exit(1);
}

$tables = layer7_pf_tables_text();
if (strpos($tables, "table <layer7_localnets> persist") === false ||
    strpos($tables, "<localsubnets>") !== false ||
    strpos($tables, "!<localsubnets>") !== false) {
	fwrite(STDERR, "FAIL: pf_tables_text must emit layer7_localnets only\n");
	fwrite(STDERR, $tables);
	exit(1);
}

$data = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"enforcement_model" => "legacy_global",
		"block_dot_doq" => true,
		"block_quic" => true,
		"policies" => array()
	)
);
$actions = layer7_pf_action_rules_text($data);
if (strpos($actions, "to !<layer7_localnets>") === false ||
    strpos($actions, "<localsubnets>") !== false ||
    strpos($actions, "layer7:block:src6") === false ||
    strpos($actions, "layer7:anti-dot6") === false) {
	fwrite(STDERR, "FAIL: action rules must use layer7_localnets\n");
	fwrite(STDERR, $actions);
	exit(1);
}

$quic = layer7_anti_quic_filter_rules_text($data);
if (strpos($quic, "to !<layer7_localnets>") === false ||
    strpos($quic, "<localsubnets>") !== false) {
	fwrite(STDERR, "FAIL: anti-quic must use layer7_localnets\n");
	fwrite(STDERR, $quic);
	exit(1);
}

echo "PASS test_localnets_inc\n";
exit(0);
