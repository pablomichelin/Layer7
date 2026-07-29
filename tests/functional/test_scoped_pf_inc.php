<?php
/*
 * Caminho B / E2 — simulacao local de layer7_policy_enforcement_rules_text().
 * Uso: php tests/functional/test_scoped_pf_inc.php
 */
$root = dirname(__DIR__, 2);
require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

$data = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"enforcement_model" => "scoped_hybrid",
		"policies" => array(
			array(
				"id" => "yt-child",
				"name" => "YouTube filho",
				"enabled" => true,
				"action" => "block",
				"priority" => 100,
				"match" => array(
					"hosts" => array("youtube.com", "www.youtube.com"),
					"src_hosts" => array("10.0.0.10")
				)
			),
			array(
				"id" => "bt-global",
				"name" => "BitTorrent global",
				"enabled" => true,
				"action" => "block",
				"priority" => 50,
				"scope_global" => true,
				"match" => array(
					"ndpi_app" => array("BitTorrent")
				)
			)
		),
		"groups" => array()
	)
);

$scoped = layer7_policy_enforcement_rules_text($data);
if ($scoped === "" ||
    strpos($scoped, "layer7_pdst_0") === false ||
    strpos($scoped, "from 10.0.0.10 to <layer7_pdst_0>") === false ||
    strpos($scoped, "layer7_pdst_1") === false ||
    strpos($scoped, "to <layer7_pdst_1>") === false ||
    strpos($scoped, "layer7_psrc_1") !== false) {
	fwrite(STDERR, "FAIL: normal app policy must use pdst, not psrc quarantine\n");
	fwrite(STDERR, $scoped);
	exit(1);
}

$legacy = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"enforcement_model" => "legacy_global",
		"policies" => $data["layer7"]["policies"]
	)
);
$legacy_rules = layer7_policy_enforcement_rules_text($legacy);
$default_legacy = layer7_pf_default_rules_text($legacy);
if ($legacy_rules !== "" ||
    strpos($default_legacy, "layer7:block:dst") === false) {
	fwrite(STDERR, "FAIL: legacy_global should keep block_dst and no scoped rules\n");
	exit(1);
}

$default_scoped = layer7_pf_default_rules_text($data);
if (strpos($default_scoped, "layer7:block:dst") !== false) {
	fwrite(STDERR, "FAIL: scoped_hybrid default rules must omit global block_dst\n");
	exit(1);
}

$quarantine = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"enforcement_model" => "scoped_hybrid",
		"policies" => array(
			array(
				"id" => "vpn-quarantine",
				"enabled" => true,
				"action" => "block",
				"quarantine_origin" => true,
				"match" => array("ndpi_app" => array("OpenVPN"))
			)
		)
	)
);
$quarantine_rules = layer7_policy_enforcement_rules_text($quarantine);
if (strpos($quarantine_rules, "table <layer7_psrc_0> persist") === false ||
    strpos($quarantine_rules, "from <layer7_psrc_0> to !<localsubnets>") === false) {
	fwrite(STDERR, "FAIL: quarantine_origin must emit executable psrc rule\n");
	fwrite(STDERR, $quarantine_rules);
	exit(1);
}

$unscoped_policy = array(
	"id" => "profile-youtube",
	"enabled" => true,
	"action" => "block",
	"match" => array(
		"ndpi_app" => array("YouTube"),
		"hosts" => array("youtube.com")
	)
);
if (layer7_policy_scoped_block_valid($unscoped_policy, $data)) {
	fwrite(STDERR, "FAIL: scoped block without source/global/quarantine accepted\n");
	exit(1);
}
$unscoped_policy["match"]["src_hosts"] = array("10.0.0.10");
if (!layer7_policy_scoped_block_valid($unscoped_policy, $data)) {
	fwrite(STDERR, "FAIL: scoped block with static source rejected\n");
	exit(1);
}

$allow_data = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"enforcement_model" => "scoped_hybrid",
		"policies" => array(
			array(
				"id" => "allow-youtube-a",
				"enabled" => true,
				"action" => "allow",
				"priority" => 200,
				"match" => array(
					"hosts" => array("youtube.com"),
					"src_hosts" => array("10.0.0.10")
				)
			),
			array(
				"id" => "block-youtube-b",
				"enabled" => true,
				"action" => "block",
				"priority" => 100,
				"match" => array(
					"hosts" => array("youtube.com"),
					"src_hosts" => array("10.0.0.20")
				)
			)
		),
		"exceptions" => array(
			array(
				"id" => "management-a",
				"enabled" => true,
				"priority" => 500,
				"action" => "allow",
				"hosts" => array("10.0.0.10")
			)
		),
		"groups" => array()
	)
);
$bl_cfg = array(
	"enabled" => true,
	"rules" => array(
		array(
			"name" => "adult",
			"enabled" => true,
			"except_ips" => array("10.0.0.10"),
			"src_cidrs" => array("10.0.0.0/24")
		)
	)
);
$allow_tables = layer7_pf_managed_dynamic_tables_text($allow_data, $bl_cfg);
$allow_rules = layer7_policy_allow_rules_text($allow_data);
$exception_rules = layer7_exception_allow_rules_text($allow_data, $bl_cfg);
$block_rules = layer7_policy_enforcement_rules_text($allow_data, false);
$combined = $allow_tables . $allow_rules . $exception_rules . $block_rules;
if (strpos($allow_tables, "table <layer7_pallow_0> persist") === false ||
    strpos($allow_rules,
        "from 10.0.0.10 to <layer7_pallow_0>") === false ||
    strpos($exception_rules,
        "from <layer7_exc_allow_0> to !<localsubnets>") === false ||
    strpos($allow_rules, "tag L7ALLOW") === false ||
    strpos($exception_rules, "tag L7ALLOW") === false ||
    strpos($allow_rules . $exception_rules, "pass quick") !== false ||
    strpos($block_rules, "! tagged L7ALLOW") === false ||
    strpos($combined, "layer7:pallow:allow-youtube-a") >
        strpos($combined, "layer7:pdst:block-youtube-b")) {
	fwrite(STDERR, "FAIL: scoped allow/exception PF precedence is unsafe\n");
	fwrite(STDERR, $combined);
	exit(1);
}

$legacy_allow = $allow_data;
$legacy_allow["layer7"]["enforcement_model"] = "legacy_global";
$legacy_exc_rules = layer7_exception_allow_rules_text(
    $legacy_allow, $bl_cfg);
if (strpos($legacy_exc_rules,
    "from <layer7_exc_allow_0> to !<localsubnets>") === false ||
    strpos($legacy_exc_rules, "tag L7ALLOW") === false ||
    strpos($legacy_exc_rules, "pass quick") !== false) {
	fwrite(STDERR, "FAIL: legacy exception allow is not Layer7-scoped\n");
	exit(1);
}

$default_tagged = layer7_pf_default_rules_text($legacy_allow);
if (strpos($default_tagged,
    "match inet to <layer7_allow_dst> tag L7ALLOW") === false ||
    strpos($default_tagged, "pass quick inet to <layer7_allow_dst>") !== false ||
    strpos($default_tagged,
    "to <layer7_block_dst> ! tagged L7ALLOW") === false) {
	fwrite(STDERR, "FAIL: allowlist may bypass native pfSense rules\n");
	exit(1);
}

$blacklist_scoped = layer7_blacklist_filter_rules_text($bl_cfg);
if (strpos($allow_tables,
    "table <layer7_blsrc_0> persist { 10.0.0.0/24, !10.0.0.10 }")
    === false ||
    strpos($blacklist_scoped,
    "from <layer7_blsrc_0> to <layer7_bld_0> ! tagged L7ALLOW")
    === false ||
    strpos($blacklist_scoped, "L7BLEX") !== false ||
    strpos($blacklist_scoped, "pass quick") !== false) {
	fwrite(STDERR, "FAIL: blacklist exception may bypass pfSense/policies\n");
	exit(1);
}

$monitor_allow = $allow_data;
$monitor_allow["layer7"]["mode"] = "monitor";
if (layer7_policy_allow_rules_text($monitor_allow) !== "" ||
    layer7_exception_allow_rules_text($monitor_allow, $bl_cfg) !== "") {
	fwrite(STDERR, "FAIL: monitor mode emitted allow enforcement rules\n");
	exit(1);
}

$policies_ui = file_get_contents(
    $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_policies.php"
);
if (!is_string($policies_ui) ||
    strpos($policies_ui, "layer7_pf_config_resync(false)") !== false) {
	fwrite(STDERR, "FAIL: policy mutation without dynamic table flush\n");
	exit(1);
}
$exceptions_ui = file_get_contents(
    $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_exceptions.php"
);
if (!is_string($exceptions_ui) ||
    preg_match('/layer7_pf_config_resync\\(\\s*\\)/', $exceptions_ui)) {
	fwrite(STDERR, "FAIL: exception mutation without dynamic table flush\n");
	exit(1);
}
$pfctl_helper = file_get_contents(
    $root . "/package/pfSense-pkg-layer7/files/usr/local/libexec/layer7-pfctl"
);
if (!is_string($pfctl_helper) ||
    strpos($pfctl_helper, '"layer7_pallow_${_i}" -T flush') === false ||
    strpos($pfctl_helper, '"layer7_blsrc_${_i}" -T flush') === false) {
	fwrite(STDERR, "FAIL: helper does not flush allow/source-scope tables\n");
	exit(1);
}

echo "PASS: test_scoped_pf_inc\n";
exit(0);
