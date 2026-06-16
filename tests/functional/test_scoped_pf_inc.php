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
    strpos($scoped, "layer7_psrc_1") === false ||
    strpos($scoped, "from <layer7_psrc_1> to !<localsubnets>") === false) {
	fwrite(STDERR, "FAIL: scoped PF rules missing expected pdst/psrc lines\n");
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

echo "PASS: test_scoped_pf_inc\n";
exit(0);
