<?php
/*
 * test_mitm_config.php — IM2 / 20.8–20.10b
 * Defaults OFF, intenção vs effective, bypass, CA, rdr selectivo.
 *
 * Uso: php tests/functional/test_mitm_config.php
 */
$root = dirname(__DIR__, 2);
$testdir = sys_get_temp_dir() . "/layer7-mitm-" . getmypid();
@mkdir($testdir . "/usr/local/etc/layer7", 0755, true);
@mkdir($testdir . "/var/run/layer7", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);

require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

function fail($msg)
{
	fwrite(STDERR, "FAIL: $msg\n");
	exit(1);
}

if (layer7_mitm_runtime_available()) {
	fail("runtime deve estar OFF sem binario no TEST_ROOT");
}
if (!layer7_mitm_intercept_ready()) {
	fail("intercept_ready deve ser true em 20.10b");
}
if (layer7_mitm_should_start_helper(array("enabled" => true), true)) {
	fail("helper nao deve arrancar sem effective (CA/runtime)");
}

$d = layer7_mitm_defaults();
if (!empty($d["enabled"]) || ($d["quic_mode"] ?? "") !== "bypass") {
	fail("defaults enabled OFF + quic bypass");
}
if (!isset($d["intercept"]["dest_cidr"]) || !empty($d["intercept"]["dest_cidr"])) {
	fail("defaults intercept.dest_cidr vazio");
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
	),
	"intercept" => array(
		"dest_cidr" => "203.0.113.10\n127.0.0.1/32\nbad",
		"block_sni" => "Blocked.Test\nbad host!!"
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
if (!in_array("203.0.113.10", $n["intercept"]["dest_cidr"], true)) {
	fail("dest_cidr aceite");
}
if (in_array("127.0.0.1/32", $n["intercept"]["dest_cidr"], true)) {
	fail("loopback nao pode ser dest rdr");
}
if (!in_array("blocked.test", $n["intercept"]["block_sni"], true)) {
	fail("block_sni lower");
}
if (layer7_mitm_effective($n, true)) {
	fail("effective false sem runtime/CA");
}
if (layer7_generate_mitm_rdr_snippet(layer7_mitm_apply_to_config(layer7_bare_config(), $n), true) !== "") {
	fail("rdr vazio sem effective");
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
		"bypass" => array("sni" => array("example.com"), "cidr" => array()),
		"intercept" => array(
			"dest_cidr" => array("203.0.113.10"),
			"block_sni" => array("blocked.test")
		)
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
	/* Runtime presente no TEST_ROOT: available=true → effective true (20.10b). */
	@mkdir($testdir . "/usr/local/sbin", 0755, true);
	$fake = $testdir . "/usr/local/sbin/layer7-tlsproxy";
	file_put_contents($fake, "#!/bin/sh\nexit 0\n");
	chmod($fake, 0755);
	if (!layer7_mitm_runtime_available()) {
		fail("runtime deve detectar binario no TEST_ROOT");
	}
	if (!layer7_mitm_effective($n2, true)) {
		fail("effective true com CA+runtime+intercept_ready+enabled+entitlement");
	}
	$cfg = layer7_mitm_apply_to_config(layer7_bare_config(), $n2);
	$cfg["layer7"]["interfaces"] = array("lan");
	$snip = layer7_generate_mitm_rdr_snippet($cfg, true);
	if (strpos($snip, "layer7_mitm_dst") === false ||
	    strpos($snip, "203.0.113.10") === false ||
	    strpos($snip, "port 443") === false ||
	    strpos($snip, "127.0.0.1 port 8443") === false) {
		fail("rdr selectivo incompleto: " . $snip);
	}
	/* IPv6 dest nao gera rdr (listener so IPv4) — 1.9.41. */
	$n6 = layer7_mitm_normalize(array(
		"enabled" => true,
		"intercept" => array(
			"dest_cidr" => array("2001:db8::10"),
			"block_sni" => array("blocked.test")
		)
	));
	$cfg6 = layer7_mitm_apply_to_config(layer7_bare_config(), $n6);
	$cfg6["layer7"]["interfaces"] = array("lan");
	if (layer7_generate_mitm_rdr_snippet($cfg6, true) !== "") {
		fail("rdr inet6 nao deve ser emitido sem listener ::1");
	}
	/* Anti-lockout: host e CIDR que contenham IP do appliance. */
	if (!layer7_mitm_dest_is_self("192.168.100.254", array("192.168.100.254")) ||
	    !layer7_mitm_dest_is_self("192.168.100.0/24", array("192.168.100.254")) ||
	    layer7_mitm_dest_is_self("203.0.113.10", array("192.168.100.254"))) {
		fail("dest_is_self anti-lockout");
	}
	/* Sem dest → zero rdr mesmo com effective. */
	$n2b = layer7_mitm_normalize(array(
		"enabled" => true,
		"intercept" => array("dest_cidr" => array(), "block_sni" => array("x.test"))
	));
	$cfg2 = layer7_mitm_apply_to_config(layer7_bare_config(), $n2b);
	if (layer7_generate_mitm_rdr_snippet($cfg2, true) !== "") {
		fail("rdr deve ser vazio sem dest_cidr");
	}
	if (!layer7_mitm_sync_helper($cfg, true)) {
		fail("sync helper deve escrever gate com effective");
	}
	$gate = layer7_mitm_product_gate_path();
	$flag = layer7_mitm_effective_flag_path();
	if (!is_file($gate) || strpos(file_get_contents($gate), "LAYER7_TLSPROXY_PRODUCT=1") === false) {
		fail("gate produto em falta");
	}
	if (!is_file($flag)) {
		fail("flag mitm.effective em falta apos sync ON");
	}
	layer7_mitm_sync_helper(layer7_bare_config(), false);
	if (is_file($gate)) {
		fail("gate deve ser removido sem effective");
	}
	if (is_file($flag)) {
		fail("flag effective deve ser removida sem effective");
	}
	@unlink($fake);
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
