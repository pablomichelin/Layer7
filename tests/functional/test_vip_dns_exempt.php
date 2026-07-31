<?php
/*
 * BG-073 / ADR-0020 — isencao VIP no caminho DNS (Bloco D).
 * Uso: php tests/functional/test_vip_dns_exempt.php
 */
$root = dirname(__DIR__, 2);
require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

$data = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"interfaces" => array("lan"),
		"block_page" => array(
			"enabled" => true,
			"force_dns" => true,
			"portal_ip" => "192.168.1.1"
		),
		"exceptions" => array(
			array(
				"id" => "vip-isentos",
				"enabled" => true,
				"priority" => 9000,
				"action" => "allow",
				"hosts" => array("192.168.1.50", "10.0.0.5"),
				"cidrs" => array("192.168.10.0/24")
			)
		)
	)
);

$meta = layer7_vip_exc_allow_meta($data);
if ($meta === null || (int)$meta["idx"] !== 0) {
	fwrite(STDERR, "FAIL: vip-isentos meta missing\n");
	exit(1);
}

$nets = layer7_vip_dns_acl_netblocks($data);
if (count($nets) !== 3 ||
    !in_array("192.168.1.50/32", $nets, true) ||
    !in_array("10.0.0.5/32", $nets, true) ||
    !in_array("192.168.10.0/24", $nets, true)) {
	fwrite(STDERR, "FAIL: acl netblocks unexpected\n");
	fwrite(STDERR, json_encode($nets));
	exit(1);
}

if (!layer7_vip_dns_should_apply($data)) {
	fwrite(STDERR, "FAIL: should_apply expected true\n");
	exit(1);
}

$block = layer7_vip_unbound_view_block($data);
if (strpos($block, L7_VIP_DNS_MARKER_START) === false ||
    strpos($block, L7_VIP_DNS_MARKER_END) === false ||
    strpos($block, 'name: "' . L7_VIP_DNS_VIEW_NAME . '"') === false ||
    strpos($block, "access-control-view: 192.168.1.50/32") === false ||
    preg_match('/^\s*view-first\s*:/m', $block)) {
	fwrite(STDERR, "FAIL: unbound view block malformed\n");
	fwrite(STDERR, $block);
	exit(1);
}

$merged = "server:\n    verbosity: 1\n\n" . $block;
$stripped = layer7_unbound_vip_dns_strip($merged);
if (strpos($stripped, L7_VIP_DNS_MARKER_START) !== false) {
	fwrite(STDERR, "FAIL: vip dns strip idempotent failed\n");
	exit(1);
}

$check = layer7_unbound_checkconf_snippet($block);
if (!$check["ok"]) {
	fwrite(STDERR, "FAIL: unbound-checkconf rejected snippet: " .
	    $check["msg"] . "\n");
	exit(1);
}

/* Estado persistente: should_apply + marker ausente => fallback activo */
unset($GLOBALS["layer7_vip_dns_rdr_fallback"]);
global $config;
$saved_unbound = isset($config["unbound"]) ? $config["unbound"] : null;
$config["unbound"] = array("custom_options" => "");
if (!layer7_vip_dns_rdr_fallback_enabled($data)) {
	fwrite(STDERR, "FAIL: persistent fallback expected true (no marker)\n");
	exit(1);
}
$config["unbound"]["custom_options"] = base64_encode($block);
if (layer7_vip_dns_rdr_fallback_enabled($data)) {
	fwrite(STDERR, "FAIL: persistent fallback expected false (marker present)\n");
	exit(1);
}
if ($saved_unbound !== null) {
	$config["unbound"] = $saved_unbound;
} else {
	unset($config["unbound"]);
}

/* Opcao (a): rdr global mantem from any (override de teste) */
layer7_vip_dns_rdr_fallback_set(false);
$rdr = layer7_generate_blockpage_rdr_snippet($data);
if ($rdr === "" || strpos($rdr, "from any to !127.0.0.1 port 53") === false) {
	fwrite(STDERR, "FAIL: blockpage rdr should keep from any in opcao (a)\n");
	fwrite(STDERR, $rdr);
	exit(1);
}

/* Opcao (b) fallback: exc_allow no rdr global e blacklist */
layer7_vip_dns_rdr_fallback_set(true);
$rdr_fb = layer7_generate_blockpage_rdr_snippet($data);
if (strpos($rdr_fb, "from !<layer7_exc_allow_0> to !127.0.0.1 port 53") === false) {
	fwrite(STDERR, "FAIL: blockpage rdr fallback missing exc_allow exclusion\n");
	fwrite(STDERR, $rdr_fb);
	exit(1);
}

$from_cidr = layer7_vip_dns_rdr_from_cidr($data, "192.168.77.0/24");
if (strpos($from_cidr, "from 192.168.77.0/24 !<layer7_exc_allow_0>") === false) {
	fwrite(STDERR, "FAIL: blacklist rdr from_cidr fallback\n");
	fwrite(STDERR, $from_cidr);
	exit(1);
}

$pfctl = "/sbin/pfctl";
if (is_executable($pfctl)) {
	$tables = layer7_pf_managed_dynamic_tables_text($data);
	$nat = trim($tables) . "\n" . trim($rdr_fb);
	$tmp = tempnam(sys_get_temp_dir(), "l7pfc");
	if ($tmp !== false) {
		file_put_contents($tmp, $nat);
		$out = array();
		$code = 0;
		exec($pfctl . " -nf " . escapeshellarg($tmp) . " 2>&1", $out, $code);
		@unlink($tmp);
		$msg = implode("\n", $out);
		if ($code !== 0 &&
		    stripos($msg, "netlink") === false &&
		    stripos($msg, "syntax") !== false) {
			fwrite(STDERR, "FAIL: pfctl -nf rejected fallback rdr\n");
			fwrite(STDERR, $msg);
			exit(1);
		}
	}
}

unset($GLOBALS["layer7_vip_dns_rdr_fallback"]);
$monitor = $data;
$monitor["layer7"]["mode"] = "monitor";
if (layer7_vip_dns_should_apply($monitor)) {
	fwrite(STDERR, "FAIL: should_apply false in monitor\n");
	exit(1);
}

echo "PASS: test_vip_dns_exempt.php\n";
exit(0);
