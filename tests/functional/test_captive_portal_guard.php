<?php
/*
 * BG-173 — Captive Portal nativo vs block page Layer7 + destinos locais.
 * Uso: php tests/functional/test_captive_portal_guard.php
 */
$root = dirname(__DIR__, 2);
$l7_test_root = sys_get_temp_dir() . "/layer7-cp-guard-" . getmypid();
@mkdir($l7_test_root . "/var/db/layer7", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $l7_test_root);
file_put_contents($l7_test_root . "/var/db/layer7/layer7-stats.json",
    json_encode(array("enforce_mode" => 1, "license_valid" => true)));
require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

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

check(layer7_host_is_captive_probe("captive.apple.com"),
    "captive.apple.com is probe");
check(layer7_host_is_captive_probe("www.captive.apple.com"),
    "www.captive.apple.com is probe (suffix)");
check(layer7_host_is_captive_probe("connectivitycheck.gstatic.com"),
    "gstatic connectivity is probe");
check(layer7_host_is_captive_probe("clients3.google.com"),
    "clients3.google.com is probe");
check(layer7_host_is_captive_probe("www.msftconnecttest.com"),
    "msftconnecttest is probe");
check(!layer7_host_is_captive_probe("www.apple.com"),
    "www.apple.com is not a probe (too broad)");
check(!layer7_host_is_captive_probe("gateway.icloud.com"),
    "gateway.icloud.com is not a CNA probe");
check(!layer7_host_is_captive_probe("mask.icloud.com"),
    "mask.icloud.com is not a CNA probe");

$GLOBALS["config"] = array(
	"interfaces" => array(
		"opt1" => array(
			"if" => "oce0.60",
			"ipaddr" => "172.16.8.1",
			"subnet" => "24"
		)
	),
	"captiveportal" => array(
		"clientes" => array(
			"zone" => "clientes",
			"interface" => "opt1",
			"enable" => ""
		)
	)
);

$nets = layer7_pf_collect_local_networks();
check(in_array("172.16.8.1/32", $nets, true),
    "captive portal IP is in localnets /32");
check(in_array("172.16.8.0/24", $nets, true),
    "captive portal LAN is in localnets");
check(in_array("127.0.0.1/32", $nets, true), "loopback v4 in localnets");
check(in_array("::1/128", $nets, true), "loopback v6 in localnets");

$ips = layer7_native_captive_portal_ips();
check(in_array("172.16.8.1", $ips, true), "native CP IP collected");

$data = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"enforcement_model" => "legacy_global",
		"interfaces" => array("oce0.60"),
		"block_page" => array(
			"enabled" => true,
			"portal_ip" => "172.16.8.1",
			"portal_ipv6" => "",
			"title" => "",
			"message" => "",
			"contact" => "",
			"show_host" => true,
			"show_policy" => false,
			"sinkhole_blacklists" => false,
			"blacklist_domain_limit" => 256,
			"force_dns" => false
		),
		"policies" => array()
	)
);

$conflict = layer7_blockpage_conflicts_with_native_captive($data);
check(!empty($conflict["conflict"]), "conflict detected on same IP");

$rdr = layer7_generate_blockpage_rdr_snippet($data);
check(strpos($rdr, "rdr on") === false,
    "no Layer7 rdr when native CP owns the IP");
check(strpos($rdr, "Captive Portal nativo") !== false,
    "rdr snippet explains the omit");

$actions = layer7_pf_action_rules_text($data);
check(strpos($actions, "match inet to <layer7_localnets> tag L7ALLOW") !== false,
    "localnets L7ALLOW inet");
check(strpos($actions, "match inet6 to <layer7_localnets> tag L7ALLOW") !== false,
    "localnets L7ALLOW inet6");
check(strpos($actions, "block drop quick inet to <layer7_block_dst>") !== false,
    "legacy dest block inet");
check(strpos($actions, "block drop quick inet6 to <layer7_block_dst>") !== false,
    "legacy dest block inet6");
check(strpos($actions, "pass quick") === false,
    "L7ALLOW is never pass quick");
check(preg_match('/pass\s+quick/', $actions) !== 1,
    "no pass quick in action rules");

$seed = file_get_contents($root .
    "/package/pfSense-pkg-layer7/files/usr/local/etc/layer7/allowlist-seed.txt");
check(strpos($seed, "captive.apple.com") !== false, "seed has captive.apple.com");
check(strpos($seed, "gateway.icloud.com") !== false, "seed keeps gateway.icloud.com");

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($l7_test_root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $f) {
	$f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($l7_test_root);

if ($fail) {
	fwrite(STDERR, "SOME CAPTIVE PORTAL GUARD TESTS FAILED\n");
	exit(1);
}
echo "ALL CAPTIVE PORTAL GUARD TESTS PASSED\n";
exit(0);
