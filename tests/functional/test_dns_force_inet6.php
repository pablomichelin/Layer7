<?php
/**
 * test_dns_force_inet6.php — passo 12.10: rdr inet6 DNS forçado + AAAA sinkhole.
 *
 * Uso: php tests/functional/test_dns_force_inet6.php
 */
$root = dirname(__DIR__, 2);
require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

$fail = 0;
function check($cond, $label) {
	global $fail;
	if ($cond) {
		echo "PASS: $label\n";
	} else {
		echo "FAIL: $label\n";
		$fail++;
	}
}

check(layer7_ip_or_cidr_family("192.168.1.0/24") === "inet", "family v4 cidr");
check(layer7_ip_or_cidr_family("2001:db8::/32") === "inet6", "family v6 cidr");
check(layer7_ip_or_cidr_family("10.0.0.5") === "inet", "family v4 host");
check(layer7_ip_or_cidr_family("2001:db8::10") === "inet6", "family v6 host");
check(layer7_ip_or_cidr_family("fe80::1") === null, "family reject link-local");
check(layer7_ip_or_cidr_family("::1") === null, "family reject loopback");

$pair4 = layer7_dns_force_rdr_pair("lan", "from any ", "inet");
check(count($pair4) === 2, "pair inet count");
check(strpos($pair4[0], "rdr on lan inet proto udp") !== false, "pair inet udp");
check(strpos($pair4[0], "to !127.0.0.1 port 53") !== false, "pair inet loop");
check(strpos($pair4[0], "-> 127.0.0.1") !== false, "pair inet dest");

$pair6 = layer7_dns_force_rdr_pair("lan", "from 2001:db8::/32 ", "inet6");
check(strpos($pair6[0], "rdr on lan inet6 proto udp") !== false, "pair inet6 udp");
check(strpos($pair6[0], "to !::1 port 53") !== false, "pair inet6 loop");
check(strpos($pair6[1], "-> ::1") !== false, "pair inet6 dest");

$data = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"interfaces" => array("lan"),
		"block_page" => array(
			"enabled" => true,
			"force_dns" => true,
			"portal_ip" => "192.168.1.1",
			"portal_ipv6" => "2001:db8::1"
		),
		"policies" => array(
			array(
				"id" => "p-yt",
				"name" => "YouTube",
				"enabled" => true,
				"action" => "block",
				"match" => array("hosts" => array("youtube.com"))
			)
		)
	)
);

check(layer7_blockpage_portal_ipv6($data) === "2001:db8::1", "portal_ipv6 custom");

$ub = layer7_blockpage_unbound_block($data);
check(strpos($ub, "IN A 192.168.1.1") !== false, "unbound IN A");
check(strpos($ub, "IN AAAA 2001:db8::1") !== false, "unbound IN AAAA");

$data_no6 = $data;
$data_no6["layer7"]["block_page"]["portal_ipv6"] = "";
$ub2 = layer7_blockpage_unbound_block($data_no6);
check(strpos($ub2, "IN A 192.168.1.1") !== false, "unbound A sem portal6");
check(strpos($ub2, "IN AAAA") === false, "unbound sem AAAA sem portal6");

layer7_vip_dns_rdr_fallback_set(false);
$rdr = layer7_generate_blockpage_rdr_snippet($data);
check(strpos($rdr, "inet proto udp") !== false &&
    strpos($rdr, "to !127.0.0.1 port 53") !== false, "blockpage rdr inet :53");
check(strpos($rdr, "inet6 proto udp") !== false &&
    strpos($rdr, "to !::1 port 53") !== false, "blockpage rdr inet6 :53");
check(strpos($rdr, "inet proto tcp") !== false &&
    strpos($rdr, "to 192.168.1.1 port 80") !== false, "blockpage http ainda inet");
check(!preg_match('/inet6 proto tcp[^\n]*port 80/', $rdr),
    "blockpage http sem rdr inet6 (12.11)");

$bl = array(
	"rules" => array(
		array(
			"enabled" => true,
			"force_dns" => true,
			"src_cidrs" => array(
				"192.168.50.0/24",
				"2001:db8:1::/64",
				"fe80::/64"
			)
		)
	)
);
$bl_rdr = layer7_generate_rdr_rules_snippet($bl, $data);
check(strpos($bl_rdr, "from 192.168.50.0/24") !== false &&
    strpos($bl_rdr, "inet proto") !== false, "blacklist rdr inet cidr");
check(strpos($bl_rdr, "from 2001:db8:1::/64") !== false &&
    strpos($bl_rdr, "inet6 proto") !== false, "blacklist rdr inet6 cidr");
check(strpos($bl_rdr, "fe80::") === false, "blacklist rdr rejeita link-local");

/* AF-split: CIDR v6 nao deve gerar regra inet incorrecta */
check(!preg_match('/rdr on \S+ inet proto \S+ from 2001:db8/', $bl_rdr),
    "cidr v6 nao emite inet");
check(!preg_match('/rdr on \S+ inet6 proto \S+ from 192\.168/', $bl_rdr),
    "cidr v4 nao emite inet6");

unset($GLOBALS["layer7_vip_dns_rdr_fallback"]);

if ($fail) {
	echo "\n$fail FAILED\n";
	exit(1);
}
echo "\nALL DNS FORCE INET6 TESTS PASSED\n";
exit(0);
