<?php
/*
 * BG-064 — excepcao VIP vip-isentos (modal Perfis rapidos).
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

/* PF: excepcao allow gera tabela exc_allow */
$pf_exc = layer7_exception_allow_rules_text($data, array("enabled" => false));
if (strpos($pf_exc, "layer7_exc_allow_0") === false ||
    strpos($pf_exc, "tag L7ALLOW") === false) {
	fwrite(STDERR, "FAIL: VIP exception must emit L7ALLOW PF rule\n");
	fwrite(STDERR, $pf_exc);
	exit(1);
}

echo "PASS: test_vip_exception.php\n";
exit(0);
