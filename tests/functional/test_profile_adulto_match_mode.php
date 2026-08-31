<?php
/**
 * BG-171 — Pornografia: host OU AdultContent; migração sem duplicar.
 *
 *   php tests/functional/test_profile_adulto_match_mode.php
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

$profiles = $root . "/package/pfSense-pkg-layer7/files/usr/local/etc/layer7/profiles.json";
$catalog = json_decode(file_get_contents($profiles), true);
$adulto = null;
foreach ($catalog["profiles"] as $p) {
	if (($p["id"] ?? "") === "adulto") {
		$adulto = $p;
		break;
	}
}
check(is_array($adulto), "catalog adulto exists");
check(($adulto["match_mode"] ?? "") === "or", "catalog match_mode=or");
check(!empty($adulto["hosts"]) && in_array("pornhub.com", $adulto["hosts"], true),
	"catalog has pornhub.com");
check(!empty($adulto["ndpi_categories"]) &&
	in_array("AdultContent", $adulto["ndpi_categories"], true),
	"catalog has AdultContent");

$rule = array(
	"id" => "profile-adulto",
	"name" => "Pornografia",
	"enabled" => true,
	"action" => "block",
	"priority" => 20,
	"match" => array(),
);
layer7_policy_apply_profile_selectors($rule, $adulto);
check(($rule["match_mode"] ?? "") === "or", "apply selectors sets or");
check(!empty($rule["match"]["hosts"]), "apply selectors keeps hosts");
check(!empty($rule["match"]["ndpi_category"]), "apply selectors keeps cats");

$s1 = layer7_policy_selectors_evaluate($rule, "pornhub.com", "TLS", "");
check(!empty($s1["matched"]) && !empty($s1["host"]),
	"php: pornhub + empty cat → match");
$s2 = layer7_policy_selectors_evaluate($rule, "pornhub.com", "TLS", "Web");
check(!empty($s2["matched"]) && !empty($s2["host"]),
	"php: pornhub + Web → match");
$s3 = layer7_policy_selectors_evaluate($rule, "random-tube.example", "TLS",
	"AdultContent");
check(!empty($s3["matched"]) && !empty($s3["cat"]),
	"php: unknown host + AdultContent → match");
$s4 = layer7_policy_selectors_evaluate($rule, "example.com", "TLS", "Web");
check(empty($s4["matched"]),
	"php: unknown host + Web → no match");

$mixed_and = array(
	"id" => "profile-escolas",
	"action" => "block",
	"match" => array(
		"hosts" => array("pornhub.com"),
		"ndpi_category" => array("AdultContent"),
	),
);
check(layer7_policy_match_mode($mixed_and) === "and",
	"other mixed profile stays and");
$s5 = layer7_policy_selectors_evaluate($mixed_and, "pornhub.com", "TLS", "Web");
check(empty($s5["matched"]),
	"php: mixed AND still requires category");

$legacy = array(
	"id" => "profile-adulto",
	"action" => "block",
	"match" => array(
		"hosts" => array("pornhub.com"),
		"ndpi_category" => array("AdultContent"),
	),
);
check(layer7_policy_match_mode($legacy) === "or",
	"legacy profile-adulto without field → or");

$data = array(
	"layer7" => array(
		"policies" => array(
			$legacy,
			array(
				"id" => "profile-escolas",
				"action" => "block",
				"match" => array(
					"hosts" => array("pornhub.com"),
					"ndpi_category" => array("AdultContent"),
				),
			),
		),
	),
);
$n1 = layer7_policies_migrate_adulto_match_mode($data);
check($n1 === 1, "migrate stamps exactly one policy");
check(($data["layer7"]["policies"][0]["match_mode"] ?? "") === "or",
	"migrate wrote match_mode=or");
check(!isset($data["layer7"]["policies"][1]["match_mode"]),
	"migrate does not touch other mixed profiles");
$n2 = layer7_policies_migrate_adulto_match_mode($data);
check($n2 === 0, "second migrate is idempotent");
check(count($data["layer7"]["policies"]) === 2,
	"migrate does not duplicate policies");

$policies = array(
	array(
		"id" => "p-mon-001",
		"name" => "Monitor geral",
		"enabled" => true,
		"action" => "monitor",
		"priority" => 50,
		"match" => array(),
	),
	$rule,
);
usort($policies, function ($a, $b) {
	return ((int)($b["priority"] ?? 50)) - ((int)($a["priority"] ?? 50));
});
$winner = null;
$catchall = null;
foreach ($policies as $pol) {
	$sel = layer7_policy_selectors_evaluate($pol, "pornhub.com", "TLS", "Web");
	if (empty($sel["matched"])) {
		continue;
	}
	if (layer7_policy_is_catch_all($pol)) {
		if ($catchall === null) {
			$catchall = $pol;
		}
		continue;
	}
	$winner = $pol;
	break;
}
if ($winner === null) {
	$winner = $catchall;
}
check(is_array($winner) && ($winner["id"] ?? "") === "profile-adulto",
	"php two-pass: p-mon-001 does not shadow Pornografia");
check(layer7_policy_is_catch_all($policies[0]),
	"p-mon-001 is catch-all");

$reconnect = array(
	"layer7" => array(
		"policies" => array($rule),
	),
);
$before = json_encode($reconnect["layer7"]["policies"]);
layer7_profile_reconnect_policy($reconnect, $adulto);
layer7_profile_reconnect_policy($reconnect, $adulto);
check(count($reconnect["layer7"]["policies"]) === 1,
	"reconnect twice does not duplicate");
check(($reconnect["layer7"]["policies"][0]["match_mode"] ?? "") === "or",
	"reconnect keeps match_mode=or");
check(json_encode($reconnect["layer7"]["policies"]) !== "" &&
	($reconnect["layer7"]["policies"][0]["id"] ?? "") === "profile-adulto",
	"reconnect keeps profile-adulto id");
unset($before);

if ($fail) {
	fwrite(STDERR, "SOME ADULTO MATCH_MODE TESTS FAILED\n");
	exit(1);
}
echo "ALL ADULTO MATCH_MODE TESTS PASSED\n";
exit(0);
