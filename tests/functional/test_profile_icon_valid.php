<?php
/**
 * BG-070 — layer7_profile_icon_valid() contra lista FontAwesome 4.7 embebida.
 */
$inc = dirname(__DIR__, 2) . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";
if (!is_readable($inc)) {
	fwrite(STDERR, "FAIL: layer7.inc not found\n");
	exit(1);
}
require_once $inc;

$checks = array(
	array("fa-youtube", true),
	array("fa-magic", true),
	array("fa-cube", true),
	array("", true),
	array("fa-not-a-real-icon-xyz", false),
	array("fa-robot", false),
	array("INVALID", false),
	array("fa-", false),
);

foreach ($checks as $c) {
	$got = layer7_profile_icon_valid($c[0]);
	if ($got !== $c[1]) {
		fwrite(STDERR, sprintf(
		    "FAIL: layer7_profile_icon_valid(%s) = %s, expected %s\n",
		    json_encode($c[0]),
		    $got ? "true" : "false",
		    $c[1] ? "true" : "false"
		));
		exit(1);
	}
}

$set = layer7_fa47_icon_set();
if (count($set) < 780) {
	fwrite(STDERR, "FAIL: FA47 set too small (" . count($set) . ")\n");
	exit(1);
}

/* FA6 render: brands use fab; FA4 aliases map to visible FA6 names. */
$render_checks = array(
	array("fa-facebook", "fab", "fa-facebook"),
	array("fa-linkedin", "fab", "fa-linkedin"),
	array("fa-reddit", "fab", "fa-reddit"),
	array("fa-pinterest", "fab", "fa-pinterest"),
	array("fa-snapchat-ghost", "fab", "fa-snapchat-ghost"),
	array("fa-telegram", "fab", "fa-telegram"),
	array("fa-whatsapp", "fab", "fa-whatsapp"),
	array("fa-youtube-play", "fab", "fa-youtube"),
	array("fa-comments-o", "fa", "fa-comments"),
	array("fa-comments", "fa", "fa-comments"),
	array("fa-users", "fa", "fa-users"),
	array("fa-video-camera", "fa", "fa-video"),
);
foreach ($render_checks as $c) {
	$spec = layer7_profile_icon_render_spec($c[0]);
	if (($spec["prefix"] ?? "") !== $c[1] || ($spec["name"] ?? "") !== $c[2]) {
		fwrite(STDERR, sprintf(
		    "FAIL: render_spec(%s) = %s/%s, expected %s/%s\n",
		    $c[0],
		    $spec["prefix"] ?? "",
		    $spec["name"] ?? "",
		    $c[1],
		    $c[2]
		));
		exit(1);
	}
	$html = layer7_profile_icon_html($c[0]);
	$want = '<i class="' . $c[1] . " " . $c[2] . '" aria-hidden="true"></i>';
	if ($html !== $want) {
		fwrite(STDERR, "FAIL: icon_html({$c[0]}) = {$html}, expected {$want}\n");
		exit(1);
	}
}

echo "PASS: test_profile_icon_valid\n";
exit(0);
