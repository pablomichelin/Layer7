<?php
/*
 * test_identity_ldap_test.php — IM4 / 20.18
 * Estado do teste LDAP (sem secrets) + defesa de redaccao.
 *
 * Uso: php tests/functional/test_identity_ldap_test.php
 */
$root = dirname(__DIR__, 2);
$testdir = sys_get_temp_dir() . "/layer7-id-ldap-test-" . getmypid();
@mkdir($testdir . "/var/db/layer7", 0755, true);
@mkdir($testdir . "/usr/local/etc/layer7", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);

require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

function fail($msg)
{
	fwrite(STDERR, "FAIL: $msg\n");
	exit(1);
}

$path = layer7_identity_ldap_test_state_path();
if (strpos($path, $testdir) !== 0) {
	fail("state path deve respeitar LAYER7_TEST_ROOT");
}

$ok = array(
	"ok" => true,
	"phase" => "ok",
	"server" => "dc.example.local",
	"port" => 636,
	"tls" => true,
	"base_ok" => true,
	"ms" => 42,
	"message" => "Ligacao LDAP OK (bind + Base DN).",
	"raw" => "{\"ok\":true,\"message\":\"x\",\"bind_password\":\"LEAK\"}"
);
/* save deve descartar raw e nunca persistir secrets */
if (!layer7_identity_ldap_test_state_save($ok)) {
	fail("state save");
}
$loaded = layer7_identity_ldap_test_state_load();
if (!is_array($loaded) || empty($loaded["ok"])) {
	fail("state load ok");
}
if (isset($loaded["raw"])) {
	fail("raw nao deve persistir");
}
$disk = file_get_contents($path);
if (strpos($disk, "LEAK") !== false || strpos($disk, "bind_password") !== false) {
	fail("secret nao deve ir para o ficheiro de estado");
}
if (empty($loaded["tested_at"])) {
	fail("tested_at obrigatorio");
}

$fail = array(
	"ok" => false,
	"phase" => "bind",
	"server" => "dc.example.local",
	"port" => 636,
	"tls" => true,
	"base_ok" => false,
	"ms" => 5,
	"message" => "Falha a ligar ou autenticar no servidor LDAP."
);
if (!layer7_identity_ldap_test_state_save($fail)) {
	fail("state save fail case");
}
$loaded2 = layer7_identity_ldap_test_state_load();
if (!empty($loaded2["ok"]) || ($loaded2["phase"] ?? "") !== "bind") {
	fail("state load fail case");
}

echo "PASS: test_identity_ldap_test\n";
exit(0);
