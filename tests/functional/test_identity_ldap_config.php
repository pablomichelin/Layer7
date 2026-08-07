<?php
/*
 * test_identity_ldap_config.php — IM4 / 20.16
 * Normalização, defaults OFF, clamps ADR-0027, password fora do JSON.
 *
 * Uso: php tests/functional/test_identity_ldap_config.php
 */
$root = dirname(__DIR__, 2);
$testdir = sys_get_temp_dir() . "/layer7-id-ldap-" . getmypid();
@mkdir($testdir . "/usr/local/etc/layer7", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);

require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

function fail($msg)
{
	fwrite(STDERR, "FAIL: $msg\n");
	exit(1);
}

$d = layer7_identity_defaults();
if (!empty($d["enabled"]) || !empty($d["ldap"]["enabled"])) {
	fail("defaults devem estar OFF");
}
if ((int)$d["ldap"]["port"] !== 636 || empty($d["ldap"]["use_tls"])) {
	fail("default LDAPS (636 + TLS)");
}
if ((int)$d["ldap"]["group_depth"] !== 5 || (int)$d["ldap"]["max_members"] !== 4096) {
	fail("defaults escala ADR-0027");
}

$bare = layer7_bare_config();
if (!isset($bare["layer7"]["identity"]["ldap"]["port"])) {
	fail("bare_config deve incluir identity");
}

$n = layer7_identity_normalize(array(
	"enabled" => "1",
	"ldap" => array(
		"enabled" => true,
		"server" => "dc.example.local",
		"port" => 99999,
		"use_tls" => false,
		"bind_dn" => "CN=svc,DC=ex",
		"base_dn" => "DC=ex",
		"group_depth" => 99,
		"max_members" => 999999,
		"bind_password" => "should-not-persist"
	)
));
if ($n["ldap"]["port"] !== 389) {
	fail("porto invalido com use_tls=false deve cair para 389, got " . $n["ldap"]["port"]);
}
if ($n["ldap"]["group_depth"] !== 10) {
	fail("group_depth clamp 10");
}
if ($n["ldap"]["max_members"] !== 16384) {
	fail("max_members clamp 16384");
}
if (isset($n["ldap"]["bind_password"])) {
	fail("bind_password nao deve ficar no bloco normalizado");
}

$errs = layer7_identity_validate(array(
	"ldap" => array("enabled" => true, "server" => "", "base_dn" => "", "bind_dn" => "")
));
if (count($errs) < 3) {
	fail("validate com ldap ON e campos vazios deve falhar");
}
$errs_off = layer7_identity_validate(array("ldap" => array("enabled" => false)));
if (!empty($errs_off)) {
	fail("validate com ldap OFF nao deve exigir campos");
}

if (layer7_identity_bind_password_is_set()) {
	fail("password nao deve existir ainda");
}
if (!layer7_identity_bind_password_save("s3cret")) {
	fail("save password");
}
if (!layer7_identity_bind_password_is_set()) {
	fail("password deve estar definida");
}
if (layer7_identity_bind_password_load() !== "s3cret") {
	fail("load password");
}
$mode = substr(sprintf("%o", fileperms(layer7_identity_secret_path())), -3);
if ($mode !== "600") {
	fail("secret deve ser 0600, got $mode");
}
if (!layer7_identity_bind_password_clear()) {
	fail("clear password");
}
if (layer7_identity_bind_password_is_set()) {
	fail("password deve ter sido limpa");
}

$data = layer7_bare_config();
$data = layer7_identity_apply_to_config($data, array(
	"enabled" => true,
	"ldap" => array(
		"enabled" => true,
		"server" => "10.0.0.5",
		"port" => 636,
		"use_tls" => true,
		"bind_dn" => "CN=a,DC=b",
		"base_dn" => "DC=b",
		"group_depth" => 3,
		"max_members" => 100
	)
));
$got = layer7_identity_from_config($data);
if ($got["ldap"]["server"] !== "10.0.0.5" || (int)$got["ldap"]["group_depth"] !== 3) {
	fail("apply/from_config roundtrip");
}

/* cleanup */
@unlink(layer7_identity_secret_path());
@rmdir($testdir . "/usr/local/etc/layer7");
@rmdir($testdir . "/usr/local/etc");
@rmdir($testdir . "/usr/local");
@rmdir($testdir . "/usr");
@rmdir($testdir);

echo "PASS: test_identity_ldap_config\n";
exit(0);
