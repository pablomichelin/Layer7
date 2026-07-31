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

echo "PASS: test_profile_icon_valid\n";
exit(0);
