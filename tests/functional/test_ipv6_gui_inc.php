<?php
/**
 * test_ipv6_gui_inc.php — passo 12.9: validação IPv6 em layer7.inc (GUI/package).
 *
 * Uso:
 *   php tests/functional/test_ipv6_gui_inc.php
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

check(layer7_ip_valid("10.0.0.1"), "ip_valid v4");
check(layer7_ip_valid("2001:db8::1"), "ip_valid v6");
check(!layer7_ip_valid("fe80::1"), "ip_valid reject link-local");
check(!layer7_ip_valid("::1"), "ip_valid reject loopback");
check(layer7_cidr_any_valid("10.0.0.0/24"), "cidr_any v4");
check(layer7_cidr_any_valid("2001:db8::/32"), "cidr_any v6");
check(!layer7_cidr6_valid("2001:db8::/8"), "cidr6 reject short prefix");
check(!layer7_cidr6_valid("::/0"), "cidr6 reject /0");
check(layer7_ip_or_cidr_valid("2804:6c4:11d:cc00::/64"), "ip_or_cidr lab");

$hosts = layer7_parse_ip_textarea("10.0.0.5\n2001:db8::99\nfe80::1\nbad");
check($hosts === array("10.0.0.5", "2001:db8::99"), "parse_ip textarea dual");

$cidrs = layer7_parse_cidr_textarea("192.168.1.0/24\n2001:db8:abcd::/48\n::/0");
check($cidrs === array("192.168.1.0/24", "2001:db8:abcd::/48"),
    "parse_cidr textarea dual");

check(layer7_ip_in_cidr("2001:db8:abcd::5", "2001:db8:abcd::/48"),
    "ip_in_cidr v6 inside");
check(!layer7_ip_in_cidr("2001:db8:abce::1", "2001:db8:abcd::/48"),
    "ip_in_cidr v6 outside");
check(layer7_ip_in_cidr("10.1.2.3", "10.1.0.0/16"), "ip_in_cidr v4");

/* Persistência VIP aceita IPv6 */
$data = array("layer7" => array("exceptions" => array()));
$r = layer7_vip_add_entry($data, "lab-v6", "2001:db8::10");
check(!empty($r["ok"]), "vip_add ipv6 host");

if ($fail) {
	echo "\n$fail FAILED\n";
	exit(1);
}
echo "\nALL IPV6 GUI VALIDATION TESTS PASSED\n";
exit(0);
