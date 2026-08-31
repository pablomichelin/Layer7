<?php
/**
 * BG-173 — anti-bypass: defaults sem AppleiCloud; migracao idempotente.
 *
 *   php tests/functional/test_anti_bypass_migrate.php
 */
$root = dirname(__DIR__, 2);
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

$sample = json_decode(file_get_contents(
    $root . "/package/pfSense-pkg-layer7/files/usr/local/etc/layer7.json.sample"),
    true);
$ab = null;
foreach ($sample["layer7"]["policies"] as $p) {
	if (($p["id"] ?? "") === "anti-bypass-dns") {
		$ab = $p;
		break;
	}
}
check(is_array($ab), "sample has anti-bypass-dns");
$apps = layer7_string_list_norm($ab["match"]["ndpi_app"] ?? array());
$hosts = layer7_string_list_norm($ab["match"]["hosts"] ?? array());
check(!in_array("AppleiCloud", $apps, true), "sample apps without AppleiCloud");
check(in_array("DoH_DoT", $apps, true), "sample has DoH_DoT");
check(in_array("iCloudPrivateRelay", $apps, true), "sample has iCloudPrivateRelay");
check(in_array("mask.icloud.com", $hosts, true), "sample has mask.icloud.com");
check(in_array("mask-h2.icloud.com", $hosts, true), "sample has mask-h2.icloud.com");
check(in_array("dns.google", $hosts, true), "sample has dns.google");
check(!in_array("gateway.icloud.com", $hosts, true),
    "sample does not list gateway.icloud.com");

$profiles = json_decode(file_get_contents(
    $root . "/package/pfSense-pkg-layer7/files/usr/local/etc/layer7/profiles.json"),
    true);
$prof = null;
foreach ($profiles["profiles"] as $p) {
	if (($p["id"] ?? "") === "anti-bypass-dns") {
		$prof = $p;
		break;
	}
}
check(is_array($prof), "catalog anti-bypass exists");
check(!in_array("AppleiCloud", $prof["ndpi_apps"] ?? array(), true),
    "catalog apps without AppleiCloud");
check(in_array("DoH_DoT", $prof["ndpi_apps"] ?? array(), true),
    "catalog has DoH_DoT");
check(in_array("iCloudPrivateRelay", $prof["ndpi_apps"] ?? array(), true),
    "catalog has iCloudPrivateRelay");
check(in_array("mask.icloud.com", $prof["hosts"] ?? array(), true),
    "catalog has mask.icloud.com");

$s_gw = layer7_policy_selectors_evaluate($ab, "gateway.icloud.com",
    "AppleiCloud", "Web");
check(empty($s_gw["matched"]),
    "php: gateway.icloud.com + AppleiCloud does not match");
$s_mask = layer7_policy_selectors_evaluate($ab, "mask.icloud.com", "TLS", "Web");
check(!empty($s_mask["matched"]) && !empty($s_mask["host"]),
    "php: mask.icloud.com matches");
$s_relay = layer7_policy_selectors_evaluate($ab, "cdn.example",
    "iCloudPrivateRelay", "Web");
check(!empty($s_relay["matched"]) && !empty($s_relay["app"]),
    "php: iCloudPrivateRelay matches");
$s_doh = layer7_policy_selectors_evaluate($ab, "unknown.doh.example",
    "DoH_DoT", "Web");
check(!empty($s_doh["matched"]) && !empty($s_doh["app"]),
    "php: DoH_DoT matches unknown host");
$s_cap = layer7_policy_selectors_evaluate($ab, "captive.apple.com",
    "TLS", "Web");
check(empty($s_cap["matched"]),
    "php: captive.apple.com does not match anti-bypass");

$legacy = array(
	"layer7" => array(
		"policies" => array(
			array(
				"id" => "anti-bypass-dns",
				"name" => "Anti-bypass DNS (DoH/DoT/Private Relay)",
				"enabled" => true,
				"action" => "block",
				"priority" => 1,
				"match" => array(
					"ndpi_app" => array("DoH_DoT", "AppleiCloud",
					    "iCloudPrivateRelay")
				)
			),
			array(
				"id" => "custom-icloud",
				"name" => "Operador: bloquear iCloud",
				"enabled" => true,
				"action" => "block",
				"priority" => 40,
				"match" => array(
					"ndpi_app" => array("AppleiCloud")
				)
			),
		),
	),
);
$n1 = layer7_policies_migrate_anti_bypass_dns($legacy);
check($n1 === 1, "migrate changes exactly one factory policy");
$migrated = $legacy["layer7"]["policies"][0];
check(($migrated["id"] ?? "") === "anti-bypass-dns", "migrate keeps factory id");
check(!in_array("AppleiCloud",
    $migrated["match"]["ndpi_app"] ?? array(), true),
    "migrate removed AppleiCloud");
check(in_array("DoH_DoT", $migrated["match"]["ndpi_app"] ?? array(), true),
    "migrate keeps DoH_DoT");
check(in_array("mask.icloud.com", $migrated["match"]["hosts"] ?? array(), true),
    "migrate added mask.icloud.com");
check(($legacy["layer7"]["policies"][1]["id"] ?? "") === "custom-icloud",
    "migrate preserves custom policy");
check(in_array("AppleiCloud",
    $legacy["layer7"]["policies"][1]["match"]["ndpi_app"] ?? array(), true),
    "custom AppleiCloud untouched");
$n2 = layer7_policies_migrate_anti_bypass_dns($legacy);
check($n2 === 0, "second migrate is idempotent");
check(count($legacy["layer7"]["policies"]) === 2,
    "migrate does not duplicate policies");

$profile_id = array(
	"layer7" => array(
		"policies" => array(
			array(
				"id" => "profile-anti-bypass-dns",
				"action" => "block",
				"match" => array(
					"ndpi_app" => array("AppleiCloud", "DoH_DoT",
					    "iCloudPrivateRelay")
				)
			),
		),
	),
);
check(layer7_policies_migrate_anti_bypass_dns($profile_id) === 1,
    "migrate factory profile-anti-bypass-dns");

$customized = array(
	"layer7" => array(
		"policies" => array(
			array(
				"id" => "anti-bypass-dns",
				"action" => "block",
				"match" => array(
					"ndpi_app" => array("DoH_DoT", "AppleiCloud",
					    "iCloudPrivateRelay", "BitTorrent")
				)
			),
		),
	),
);
check(layer7_policies_migrate_anti_bypass_dns($customized) === 0,
    "migrate skips factory id with extra apps");
check(in_array("AppleiCloud",
    $customized["layer7"]["policies"][0]["match"]["ndpi_app"], true),
    "customized extra-app policy untouched");

if ($fail) {
	fwrite(STDERR, "SOME ANTI-BYPASS MIGRATE TESTS FAILED\n");
	exit(1);
}
echo "ALL ANTI-BYPASS MIGRATE TESTS PASSED\n";
exit(0);
