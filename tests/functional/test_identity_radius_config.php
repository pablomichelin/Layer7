<?php
/*
 * test_identity_radius_config.php — IM5 / 20.19
 * Defaults OFF, NAS ACL, secret 0600 fora do JSON.
 *
 * Uso: php tests/functional/test_identity_radius_config.php
 */
$root = dirname(__DIR__, 2);
$testdir = sys_get_temp_dir() . "/layer7-id-radius-" . getmypid();
@mkdir($testdir . "/usr/local/etc/layer7", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);

require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

function fail($msg)
{
	fwrite(STDERR, "FAIL: $msg\n");
	exit(1);
}

$d = layer7_identity_defaults();
if (!empty($d["radius"]["enabled"])) {
	fail("radius default OFF");
}
if ((int)$d["radius"]["listen_port"] !== 1813) {
	fail("default port 1813");
}
if ($d["radius"]["bind_address"] !== "0.0.0.0") {
	fail("default bind");
}
if (!is_array($d["radius"]["nas_acl"]) || count($d["radius"]["nas_acl"]) !== 0) {
	fail("default ACL vazia");
}

$n = layer7_identity_normalize(array(
	"radius" => array(
		"enabled" => true,
		"listen_port" => 99999,
		"bind_address" => "not-an-ip",
		"nas_acl" => "10.0.0.1, bad, 10.0.0.2",
		"secret" => "should-not-persist"
	)
));
if ((int)$n["radius"]["listen_port"] !== 1813) {
	fail("porto invalido → 1813");
}
if ($n["radius"]["bind_address"] !== "0.0.0.0") {
	fail("bind invalido → 0.0.0.0");
}
if ($n["radius"]["nas_acl"] !== array("10.0.0.1", "10.0.0.2")) {
	fail("nas_acl parse");
}
if (isset($n["radius"]["secret"])) {
	fail("secret nao deve persistir no JSON");
}

$errs = layer7_identity_validate(array(
	"radius" => array("enabled" => true, "nas_acl" => array())
));
if (count($errs) < 1) {
	fail("validate radius ON sem ACL deve falhar");
}
$errs_off = layer7_identity_validate(array(
	"radius" => array("enabled" => false)
));
if (!empty($errs_off)) {
	fail("validate radius OFF sem erros");
}

if (layer7_identity_radius_secret_is_set()) {
	fail("secret nao deve existir");
}
if (!layer7_identity_radius_secret_save("radius-s3cret")) {
	fail("save secret");
}
if (!layer7_identity_radius_secret_is_set()) {
	fail("secret definido");
}
$mode = substr(sprintf("%o", fileperms(layer7_identity_radius_secret_path())), -3);
if ($mode !== "600") {
	fail("secret 0600, got $mode");
}
if (!layer7_identity_radius_secret_clear()) {
	fail("clear secret");
}

$data = layer7_bare_config();
$data = layer7_identity_apply_to_config($data, array(
	"enabled" => true,
	"radius" => array(
		"enabled" => true,
		"listen_port" => 1813,
		"bind_address" => "192.168.1.1",
		"nas_acl" => array("192.168.1.10")
	)
));
$got = layer7_identity_from_config($data);
if ($got["radius"]["bind_address"] !== "192.168.1.1") {
	fail("roundtrip bind");
}
if ($got["radius"]["nas_acl"] !== array("192.168.1.10")) {
	fail("roundtrip acl");
}

@unlink(layer7_identity_radius_secret_path());
@rmdir($testdir . "/usr/local/etc/layer7");
@rmdir($testdir . "/usr/local/etc");
@rmdir($testdir . "/usr/local");
@rmdir($testdir . "/usr");
@rmdir($testdir);

echo "PASS: test_identity_radius_config\n";
exit(0);
