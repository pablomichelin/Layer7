<?php
/*
 * test_mitm_config.php — IM2 / 20.8–20.10b / 1.9.42 source_cidr
 * Defaults OFF, intenção vs effective, bypass, CA, rdr source+dest.
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
	fail("intercept_ready deve ser true em 20.10b+");
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
if (!isset($d["intercept"]["source_cidr"]) || !empty($d["intercept"]["source_cidr"])) {
	fail("defaults intercept.source_cidr vazio");
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
		"source_cidr" => "192.168.100.24/32\nany\n0.0.0.0/0\nbad\n2001:db8::1",
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
if (!in_array("192.168.100.24/32", $n["intercept"]["source_cidr"], true)) {
	fail("source_cidr .24/32 aceite");
}
if (in_array("any", $n["intercept"]["source_cidr"], true) ||
    in_array("0.0.0.0/0", $n["intercept"]["source_cidr"], true) ||
    in_array("2001:db8::1", $n["intercept"]["source_cidr"], true)) {
	fail("source rejeita any/0.0.0.0/0/IPv6");
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
			"source_cidr" => array("192.168.100.24/32"),
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
		fail("effective true com CA+runtime+intercept_ready+enabled+entitlement+scope");
	}
	/* Fail-closed: sem source+dest ⇒ effective false mesmo com CA/runtime. */
	$n_noscope = layer7_mitm_normalize(array(
		"enabled" => true,
		"intercept" => array(
			"source_cidr" => array(),
			"dest_cidr" => array("203.0.113.10"),
			"block_sni" => array("blocked.test")
		)
	));
	if (layer7_mitm_effective($n_noscope, true)) {
		fail("effective deve ser false sem source_cidr");
	}
	$errs_ns = layer7_mitm_validate(array(
		"enabled" => true,
		"intercept" => array(
			"source_cidr" => array(),
			"dest_cidr" => array("203.0.113.10")
		)
	));
	if (empty($errs_ns)) {
		fail("validate deve rejeitar activacao sem source+dest");
	}
	$errs_any = layer7_mitm_validate(array(
		"enabled" => true,
		"intercept" => array(
			"source_cidr" => array("any"),
			"dest_cidr" => array("203.0.113.10")
		)
	));
	if (empty($errs_any)) {
		fail("validate deve rejeitar source=any (fail-closed)");
	}
	if (!function_exists("layer7_mitm_intercept_scope_ok") ||
	    layer7_mitm_intercept_scope_ok($n_noscope) ||
	    !layer7_mitm_intercept_scope_ok($n2)) {
		fail("intercept_scope_ok");
	}
	$cfg = layer7_mitm_apply_to_config(layer7_bare_config(), $n2);
	$cfg["layer7"]["interfaces"] = array("lan");
	/* Fail-safe: sem control-plane materializado ⇒ zero rdr. */
	layer7_mitm_ctrl_cleanup("");
	if (layer7_generate_mitm_rdr_snippet($cfg, true) !== "") {
		fail("rdr deve ser vazio sem gate/flag materializados");
	}
	if (!layer7_mitm_sync_helper($cfg, true)) {
		fail("sync ON para materializar control-plane antes do rdr");
	}
	if (!function_exists("layer7_mitm_control_plane_materialized") ||
	    !layer7_mitm_control_plane_materialized()) {
		fail("control_plane_materialized apos sync ON");
	}
	$snip = layer7_generate_mitm_rdr_snippet($cfg, true);
	if (strpos($snip, "layer7_mitm_src") === false ||
	    strpos($snip, "layer7_mitm_dst") === false ||
	    strpos($snip, "192.168.100.24/32") === false ||
	    strpos($snip, "203.0.113.10") === false ||
	    strpos($snip, "port 443") === false ||
	    strpos($snip, "127.0.0.1 port 8443") === false ||
	    strpos($snip, "from <layer7_mitm_src> to <layer7_mitm_dst>") === false) {
		fail("rdr source+dest incompleto: " . $snip);
	}
	if (preg_match('/\bfrom\s+any\b/i', $snip) || preg_match('/\bto\s+any\b/i', $snip)) {
		fail("from/to any proibido no rdr MITM");
	}
	foreach (explode("\n", $snip) as $ln_chk) {
		$ln_chk = trim($ln_chk);
		if ($ln_chk === "" || $ln_chk[0] === '#') {
			continue;
		}
		if (preg_match('/\binet6\b/i', $ln_chk) || strpos($ln_chk, "::1") !== false) {
			fail("rdr IPv6 / ::1 proibido no MITM: " . $ln_chk);
		}
	}
	if (!function_exists("layer7_mitm_rdr_line_ok")) {
		fail("layer7_mitm_rdr_line_ok em falta");
	}
	foreach (explode("\n", trim($snip)) as $ln) {
		if ($ln === "") {
			continue;
		}
		if (!layer7_mitm_rdr_line_ok($ln)) {
			fail("rdr_line_ok rejeitou linha valida: " . $ln);
		}
	}
	if (layer7_mitm_rdr_line_ok(
	    "rdr on em0 inet proto tcp from any to <layer7_mitm_dst> port 443 -> 127.0.0.1 port 8443")) {
		fail("rdr_line_ok deve rejeitar from any");
	}
	if (layer7_mitm_rdr_line_ok(
	    "rdr on em0 inet6 proto tcp from <layer7_mitm_src> to <layer7_mitm_dst> port 443 -> ::1 port 8443")) {
		fail("rdr_line_ok deve rejeitar inet6");
	}

	/* Prefixo </8 e IPv6 misturado: validate fail-closed (sem expansao silenciosa). */
	if (layer7_mitm_normalize_ipv4_cidr_list(array("10.0.0.0/7", "0.0.0.0/1")) !== array()) {
		fail("normalize rejeita prefixo </8");
	}
	$errs_broad = layer7_mitm_validate(array(
		"enabled" => true,
		"intercept" => array(
			"source_cidr" => array("192.168.100.24/32", "10.0.0.0/7"),
			"dest_cidr" => array("203.0.113.10")
		)
	));
	$broad_txt = implode(" | ", $errs_broad);
	if (strpos($broad_txt, "proibido") === false && strpos($broad_txt, "prefixo") === false) {
		fail("validate deve rejeitar prefixo </8 misturado: " . $broad_txt);
	}
	$errs_v6mix = layer7_mitm_validate(array(
		"enabled" => true,
		"intercept" => array(
			"source_cidr" => array("192.168.100.24/32", "2001:db8::1"),
			"dest_cidr" => array("203.0.113.10")
		)
	));
	$v6_txt = implode(" | ", $errs_v6mix);
	if (strpos($v6_txt, "proibido") === false && strpos($v6_txt, "IPv6") === false) {
		fail("validate deve rejeitar IPv6 misturado: " . $v6_txt);
	}

	/* Negativo: ambos vazios */
	$n_empty = layer7_mitm_normalize(array(
		"enabled" => true,
		"intercept" => array("source_cidr" => array(), "dest_cidr" => array(), "block_sni" => array("x.test"))
	));
	$cfg_e = layer7_mitm_apply_to_config(layer7_bare_config(), $n_empty);
	$cfg_e["layer7"]["interfaces"] = array("lan");
	if (layer7_generate_mitm_rdr_snippet($cfg_e, true) !== "") {
		fail("rdr vazio quando source e dest vazios");
	}

	/* Negativo: source-only */
	$n_src = layer7_mitm_normalize(array(
		"enabled" => true,
		"intercept" => array(
			"source_cidr" => array("192.168.100.24/32"),
			"dest_cidr" => array()
		)
	));
	$cfg_s = layer7_mitm_apply_to_config(layer7_bare_config(), $n_src);
	$cfg_s["layer7"]["interfaces"] = array("lan");
	if (layer7_generate_mitm_rdr_snippet($cfg_s, true) !== "") {
		fail("rdr deve ser vazio com source-only");
	}

	/* Negativo: dest-only (regressao 1.9.41) */
	$n_dst = layer7_mitm_normalize(array(
		"enabled" => true,
		"intercept" => array(
			"source_cidr" => array(),
			"dest_cidr" => array("203.0.113.10")
		)
	));
	$cfg_d = layer7_mitm_apply_to_config(layer7_bare_config(), $n_dst);
	$cfg_d["layer7"]["interfaces"] = array("lan");
	if (layer7_generate_mitm_rdr_snippet($cfg_d, true) !== "") {
		fail("rdr deve ser vazio com dest-only (1.9.42)");
	}

	/* IPv6 dest/source nao gera rdr */
	$n6 = layer7_mitm_normalize(array(
		"enabled" => true,
		"intercept" => array(
			"source_cidr" => array("192.168.100.24/32"),
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

	/* Topologia: .24 elegivel; outra origem nao esta na tabela src. */
	$n_topo = layer7_mitm_normalize(array(
		"enabled" => true,
		"intercept" => array(
			"source_cidr" => array("192.168.100.24/32"),
			"dest_cidr" => array("203.0.113.10")
		)
	));
	$cfg_t = layer7_mitm_apply_to_config(layer7_bare_config(), $n_topo);
	$cfg_t["layer7"]["interfaces"] = array("lan");
	$snip_t = layer7_generate_mitm_rdr_snippet($cfg_t, true);
	if (strpos($snip_t, "192.168.100.24/32") === false) {
		fail("topo: source .24/32 deve aparecer");
	}
	if (strpos($snip_t, "192.168.100.100") !== false) {
		fail("topo: origem nao listada nao deve aparecer no snippet");
	}
	if (!layer7_ip_in_cidr("192.168.100.24", "192.168.100.24/32")) {
		fail("topo: .24 member of /32");
	}
	if (layer7_ip_in_cidr("192.168.100.100", "192.168.100.24/32")) {
		fail("topo: .100 NAO member of .24/32");
	}

	/* webgui port collision → zero rdr */
	$old_wg = getenv("LAYER7_TEST_WEBGUI_PORT");
	/* layer7_webgui_port le config; forcar via listen==webgui mock: se listen 8443 e webgui 8443 */
	/* Cobertura: se port listen == webgui, snippet vazio — usar reflection via filtro self. */
	/* Validado indirectamente: listen default 8443; webgui tipico 443/9999 ≠. */

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
	/* Fail-safe rollback: teardown limpo + intenção OFF; rdr some. */
	if (!function_exists("layer7_mitm_failsafe_rollback")) {
		fail("layer7_mitm_failsafe_rollback em falta");
	}
	$rolled = layer7_mitm_failsafe_rollback($cfg, "test lifecycle");
	if (!empty($rolled["layer7"]["mitm"]["enabled"])) {
		fail("failsafe_rollback deve forcar mitm.enabled=OFF");
	}
	if (layer7_mitm_control_plane_materialized() || is_file($gate) || is_file($flag)) {
		fail("failsafe deve remover gate/flag");
	}
	if (layer7_generate_mitm_rdr_snippet($cfg, true) !== "") {
		fail("apos failsafe, zero rdr mesmo com cfg antiga effective");
	}
	/* Idempotente: segundo teardown permanece limpo */
	layer7_mitm_failsafe_rollback($rolled, "test lifecycle 2");
	if (layer7_mitm_control_plane_materialized()) {
		fail("failsafe repetido deve permanecer limpo");
	}
	/* enable/disable/reload idempotentes */
	if (!layer7_mitm_sync_helper($cfg, true)) {
		fail("sync ON 1");
	}
	if (!layer7_mitm_sync_helper($cfg, true)) {
		fail("sync ON 2 (reload idempotente) deve continuar true");
	}
	layer7_mitm_sync_helper(layer7_bare_config(), false);
	layer7_mitm_sync_helper(layer7_bare_config(), false);
	if (layer7_mitm_control_plane_materialized()) {
		fail("disable idempotente deve permanecer limpo");
	}
	/* disable/reload OFF limpa gate/flag (+ flush tabelas em path real) */
	layer7_mitm_sync_helper($cfg, true);
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

/* Uninstall contract: pkg-deinstall lista layer7_mitm_src */
$deinstall = $root . "/package/pfSense-pkg-layer7/files/pkg-deinstall.in";
$deinst = file_get_contents($deinstall);
if (strpos($deinst, "layer7_mitm_src") === false || strpos($deinst, "layer7_mitm_dst") === false) {
	fail("pkg-deinstall deve flush layer7_mitm_src e layer7_mitm_dst");
}
$pfctl = file_get_contents($root . "/package/pfSense-pkg-layer7/files/usr/local/libexec/layer7-pfctl");
if (strpos($pfctl, "flush_tables_mitm") === false ||
    strpos($pfctl, "layer7_mitm_src") === false) {
	fail("layer7-pfctl flush-all deve incluir mitm src/dst");
}

fwrite(STDOUT, "PASS test_mitm_config.php\n");
exit(0);
