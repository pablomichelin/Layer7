<?php
/*
 * test_mitm_config.php — IM2 / 20.8–20.9
 * Defaults OFF, intenção vs effective, bypass endurecido, CA, quic_mode.
 *
 * Uso: php tests/functional/test_mitm_config.php
 */
$root = dirname(__DIR__, 2);
$testdir = sys_get_temp_dir() . "/layer7-mitm-" . getmypid();
@mkdir($testdir . "/usr/local/etc/layer7", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);

require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

function fail($msg)
{
	fwrite(STDERR, "FAIL: $msg\n");
	exit(1);
}

if (layer7_mitm_runtime_available()) {
	fail("runtime deve estar OFF neste bloco");
}
if (layer7_mitm_should_start_helper(array("enabled" => true), true)) {
	fail("helper nao deve arrancar sem runtime");
}

$d = layer7_mitm_defaults();
if (!empty($d["enabled"]) || ($d["quic_mode"] ?? "") !== "bypass") {
	fail("defaults enabled OFF + quic bypass");
}

$bare = layer7_bare_config();
if (!isset($bare["layer7"]["mitm"]["enabled"]) || $bare["layer7"]["mitm"]["enabled"]) {
	fail("bare_config mitm.enabled=false");
}

/* Sem CA: intenção enabled nao persiste. */
$n = layer7_mitm_normalize(array(
	"enabled" => true,
	"quic_mode" => "BLOCK",
	"bypass" => array(
		"sni" => "Bank.Example.PT\n*.login.microsoftonline.com\nbad host!!\n127.0.0.1",
		"cidr" => array("10.0.0.0/8", "999.1.1.1", "192.168.1.50", "2001:db8::/32")
	)
));
if (!empty($n["enabled"])) {
	fail("enabled exige CA presente");
}
if ($n["quic_mode"] !== "block") {
	fail("quic_mode normalizado");
}
if (!in_array("bank.example.pt", $n["bypass"]["sni"], true)) {
	fail("sni lower");
}
if (!in_array("login.microsoftonline.com", $n["bypass"]["sni"], true)) {
	fail("sni wildcard strip");
}
if (in_array("bad host!!", $n["bypass"]["sni"], true) || in_array("127.0.0.1", $n["bypass"]["sni"], true)) {
	fail("sni invalido / IP rejeitado em sni");
}
foreach (array("127.0.0.1/32", "::1/128", "10.0.0.0/8", "192.168.1.50", "2001:db8::/32") as $need) {
	if (!in_array($need, $n["bypass"]["cidr"], true)) {
		fail("cidr em falta: $need");
	}
}
if (in_array("999.1.1.1", $n["bypass"]["cidr"], true)) {
	fail("ip invalido");
}
if (layer7_mitm_effective($n, true)) {
	fail("effective false sem runtime/CA");
}

$has_openssl = is_executable("/usr/bin/openssl") || is_executable("/usr/local/bin/openssl");
if (!$has_openssl) {
	fwrite(STDOUT, "SKIP openssl generate\n");
} else {
	$r = layer7_mitm_ca_generate("Layer7 Test CA", 30);
	if (empty($r["ok"])) {
		fail("generate: " . $r["msg"]);
	}
	$n2 = layer7_mitm_normalize(array(
		"enabled" => true,
		"bypass" => array("sni" => array("example.com"), "cidr" => array())
	));
	if (empty($n2["enabled"])) {
		fail("com CA, intencao enabled deve persistir");
	}
	if (layer7_mitm_effective($n2, true)) {
		fail("effective ainda false sem runtime");
	}
	if (layer7_mitm_effective($n2, false)) {
		fail("effective false sem entitlement");
	}
	if (!in_array("127.0.0.1/32", $n2["bypass"]["cidr"], true)) {
		fail("protegido loopback");
	}
	layer7_mitm_ca_delete();
	$n3 = layer7_mitm_normalize(array("enabled" => true));
	if (!empty($n3["enabled"])) {
		fail("apos delete CA, enabled volta false");
	}
}

$cfg = layer7_mitm_apply_to_config(layer7_bare_config(), array(
	"enabled" => true,
	"bypass" => array("sni" => array("example.com"), "cidr" => array())
));
if (!empty($cfg["layer7"]["mitm"]["enabled"])) {
	fail("apply sem CA: enabled false");
}

fwrite(STDOUT, "PASS test_mitm_config.php\n");
exit(0);
