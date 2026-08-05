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
/* REV-018 / 12.3: pdst global e tabelas sem host L3 fixo emitem inet+inet6 */
if (strpos($scoped, "block drop quick inet to <layer7_pdst_1>") === false ||
    strpos($scoped, "block drop quick inet6 to <layer7_pdst_1>") === false) {
	fwrite(STDERR, "FAIL: scoped global pdst must emit inet and inet6\n");
	fwrite(STDERR, $scoped);
	exit(1);
}
/* Host IPv4 na regra: só inet (nao inet6 from 10.0.0.10) */
if (strpos($scoped, "inet6 from 10.0.0.10") !== false) {
	fwrite(STDERR, "FAIL: IPv4 source must not appear in inet6 rule\n");
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
    strpos($quarantine_rules, "from <layer7_psrc_0> to !<layer7_localnets>") === false) {
	fwrite(STDERR, "FAIL: quarantine_origin must emit executable psrc rule\n");
	fwrite(STDERR, $quarantine_rules);
	exit(1);
}
if (strpos($quarantine_rules,
	"block drop quick inet from <layer7_psrc_0> to !<layer7_localnets>") === false ||
    strpos($quarantine_rules,
	"block drop quick inet6 from <layer7_psrc_0> to !<layer7_localnets>") === false) {
	fwrite(STDERR, "FAIL: psrc quarantine must emit inet and inet6 (REV-018)\n");
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
        "from <layer7_exc_allow_0> to !<layer7_localnets>") === false ||
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
if (strpos($exception_rules,
	"match inet from <layer7_exc_allow_0> to !<layer7_localnets>") === false ||
    strpos($exception_rules,
	"match inet6 from <layer7_exc_allow_0> to !<layer7_localnets>") === false) {
	fwrite(STDERR, "FAIL: exc_allow must emit inet and inet6 (REV-018)\n");
	fwrite(STDERR, $exception_rules);
	exit(1);
}

$legacy_allow = $allow_data;
$legacy_allow["layer7"]["enforcement_model"] = "legacy_global";
$legacy_exc_rules = layer7_exception_allow_rules_text(
    $legacy_allow, $bl_cfg);
if (strpos($legacy_exc_rules,
    "from <layer7_exc_allow_0> to !<layer7_localnets>") === false ||
    strpos($legacy_exc_rules, "tag L7ALLOW") === false ||
    strpos($legacy_exc_rules, "pass quick") !== false) {
	fwrite(STDERR, "FAIL: legacy exception allow is not Layer7-scoped\n");
	exit(1);
}

$default_tagged = layer7_pf_default_rules_text($legacy_allow);
if (strpos($default_tagged,
    "match inet to <layer7_allow_dst> tag L7ALLOW") === false ||
    strpos($default_tagged,
    "match inet6 to <layer7_allow_dst> tag L7ALLOW") === false ||
    strpos($default_tagged, "pass quick inet to <layer7_allow_dst>") !== false ||
    strpos($default_tagged, "pass quick inet6 to <layer7_allow_dst>") !== false ||
    strpos($default_tagged,
    "to <layer7_block_dst> ! tagged L7ALLOW") === false) {
	fwrite(STDERR, "FAIL: allowlist may bypass native pfSense rules or miss inet6\n");
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

$quic_data = array(
	"layer7" => array(
		"block_quic_interfaces" => array("vmx0", "bad iface")
	)
);
$quic_rules = layer7_anti_quic_filter_rules_text($quic_data);
if (strpos($quic_rules,
    "block drop quick on vmx0 inet proto udp") === false ||
    strpos($quic_rules,
    "block drop quick on vmx0 inet6 proto udp") === false ||
    strpos($quic_rules, "inet on vmx0") !== false ||
    strpos($quic_rules, "bad iface") !== false) {
	fwrite(STDERR, "FAIL: anti-QUIC interface PF syntax is invalid\n");
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
    strpos($pfctl_helper, '"layer7_pexc_${_i}" -T flush') === false ||
    strpos($pfctl_helper, '"layer7_blsrc_${_i}" -T flush') === false) {
	fwrite(STDERR, "FAIL: helper does not flush allow/source-scope tables\n");
	exit(1);
}

$pexc_data = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"enforcement_model" => "scoped_hybrid",
		"policies" => array(
			array(
				"id" => "yt-with-exc",
				"enabled" => true,
				"action" => "block",
				"priority" => 100,
				"match" => array(
					"hosts" => array("youtube.com"),
					"src_cidrs" => array("10.0.0.0/24"),
					"src_exclude_cidrs" => array("10.0.0.50/32")
				)
			)
		),
		"groups" => array()
	)
);
$pexc_tables = layer7_pf_managed_dynamic_tables_text($pexc_data);
$pexc_rules = layer7_policy_enforcement_rules_text($pexc_data, false);
if (strpos($pexc_tables, "table <layer7_pexc_0> persist { 10.0.0.50/32 }") === false ||
    strpos($pexc_rules, "from <layer7_pexc_0> to <layer7_pdst_0> tag L7ALLOW") === false ||
    strpos($pexc_rules, "layer7:pexc:yt-with-exc") === false) {
	fwrite(STDERR, "FAIL: scoped src_exclude must emit pexc table and L7ALLOW rule\n");
	fwrite(STDERR, $pexc_tables . $pexc_rules);
	exit(1);
}
/* Ordem PF: o match pexc (tag L7ALLOW) tem de preceder o primeiro block
 * `quick` da politica, senao o pacote e dropado antes de ser marcado e a
 * exclusao vira codigo morto (regressao do candidato _50; ADR-0019). */
$pexc_match_pos = strpos($pexc_rules,
    "match inet from <layer7_pexc_0> to <layer7_pdst_0> tag L7ALLOW");
$pexc_match6_pos = strpos($pexc_rules,
    "match inet6 from <layer7_pexc_0> to <layer7_pdst_0> tag L7ALLOW");
$pexc_block_pos = strpos($pexc_rules, "block drop quick inet");
if ($pexc_match_pos === false || $pexc_match6_pos === false ||
    $pexc_block_pos === false ||
    $pexc_match_pos > $pexc_block_pos ||
    $pexc_match6_pos > $pexc_block_pos) {
	fwrite(STDERR, "FAIL: pexc match rule must precede quick block rules\n");
	fwrite(STDERR, $pexc_rules);
	exit(1);
}

/* Host IPv6 na origem scoped → só inet6 */
$v6_data = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"enforcement_model" => "scoped_hybrid",
		"policies" => array(
			array(
				"id" => "yt-v6",
				"enabled" => true,
				"action" => "block",
				"match" => array(
					"hosts" => array("youtube.com"),
					"src_hosts" => array("2001:db8::10")
				)
			)
		),
		"groups" => array()
	)
);
$v6_rules = layer7_policy_enforcement_rules_text($v6_data, false);
if (strpos($v6_rules,
	"block drop quick inet6 from 2001:db8::10 to <layer7_pdst_0>") === false ||
    strpos($v6_rules, "inet from 2001:db8::10") !== false ||
    layer7_ipv6_valid("fe80::1") ||
    layer7_ipv6_valid("::1") ||
    layer7_cidr6_valid("ff00::/8")) {
	fwrite(STDERR, "FAIL: IPv6 src host / S-03 validators\n");
	fwrite(STDERR, $v6_rules);
	exit(1);
}

/* pfnearly vs filter: blocks em enforce devem ir para pfnearly, nao filter */
$early_rules = layer7_pf_early_enforcement_rules_text($data);
$schema_only = layer7_pf_schema_rules_text($data);
if (strpos($early_rules, "block drop quick inet from 10.0.0.10") === false ||
    strpos($early_rules, "pfearly") === false ||
    strpos($schema_only, "block drop quick") !== false ||
    strpos($schema_only, "table <layer7_pdst_") === false) {
	fwrite(STDERR, "FAIL: enforce blocks must be pfnearly, filter schema-only\n");
	fwrite(STDERR, "early:\n" . $early_rules . "\nschema:\n" . $schema_only);
	exit(1);
}
$monitor_early = layer7_pf_early_enforcement_rules_text($monitor_allow);
if ($monitor_early !== "") {
	fwrite(STDERR, "FAIL: monitor mode must not emit pfnearly enforcement\n");
	exit(1);
}
$layer7_inc = file_get_contents(
    $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc"
);
if (!is_string($layer7_inc) ||
    strpos($layer7_inc, 'if ($type === "pfearly")') === false) {
	fwrite(STDERR, "FAIL: layer7_generate_rules must handle pfnearly hook\n");
	exit(1);
}

echo "PASS: test_scoped_pf_inc\n";
exit(0);
