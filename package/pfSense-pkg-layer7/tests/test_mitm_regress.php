<?php
/*
 * Regressões MITM 1.9.43 — próximas ao pacote (layer7.inc / GUI).
 *
 * Cobre: scope fail-closed, anti from-any/IPv6/</8, rdr_line_ok,
 * control-plane materializado, failsafe_rollback, filter_configure_safe,
 * sync enable/disable idempotente.
 *
 * Uso: php package/pfSense-pkg-layer7/tests/test_mitm_regress.php
 */
$pkg = dirname(__DIR__);
$root = dirname($pkg, 2);
$inc = $pkg . "/files/usr/local/pkg/layer7.inc";
if (!is_file($inc)) {
	fwrite(STDERR, "FAIL: layer7.inc em falta\n");
	exit(1);
}

$testdir = sys_get_temp_dir() . "/layer7-mitm-regress-" . getmypid();
@mkdir($testdir . "/usr/local/etc/layer7", 0755, true);
@mkdir($testdir . "/var/run/layer7", 0755, true);
@mkdir($testdir . "/usr/local/sbin", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);

require_once $inc;

function fail($msg)
{
	fwrite(STDERR, "FAIL: $msg\n");
	exit(1);
}

function need($cond, $msg)
{
	if (!$cond) {
		fail($msg);
	}
}

/* --- API surface --- */
foreach (array(
	"layer7_mitm_intercept_token_forbidden_reason",
	"layer7_mitm_rdr_line_ok",
	"layer7_mitm_quic_line_ok",
	"layer7_mitm_control_plane_materialized",
	"layer7_mitm_failsafe_rollback",
	"layer7_filter_configure_safe",
	"layer7_mitm_sync_helper",
	"layer7_generate_mitm_rdr_snippet",
	"layer7_generate_mitm_quic_filter_rules_text",
	"layer7_mitm_tables_apply_to_pf",
	"layer7_mitm_expire_if_needed",
	"layer7_mitm_window_status",
	"layer7_mitm_window_until_off",
	"layer7_mitm_audit",
	"layer7_mitm_prepare_window_on_save",
	"layer7_mitm_lifecycle_tick",
	"layer7_mitm_setup_window_cron",
	"layer7_mitm_window_supervisor_tick",
	"layer7_mitm_window_supervisor_status",
) as $fn) {
	need(function_exists($fn), "API em falta: $fn");
}
need(defined("L7_MITM_WINDOW_MAX_MINUTES_DEFAULT") &&
    (int)L7_MITM_WINDOW_MAX_MINUTES_DEFAULT === 15, "P3 default 15min");
need(defined("L7_MITM_WINDOW_UNTIL_OFF") &&
    (int)L7_MITM_WINDOW_UNTIL_OFF === 0, "20.35 until_off=0");
/* Harness: apply e no-op (sem pfctl); API deve regressar 0. */
need(layer7_mitm_tables_apply_to_pf(layer7_bare_config()) === 0,
	"tables_apply harness OFF = 0");
need(defined("L7_CTRL_TIMEOUT_FILTER") && (int)L7_CTRL_TIMEOUT_FILTER >= 1,
	"L7_CTRL_TIMEOUT_FILTER finito");

/* --- Scope / anti-expansão --- */
need(layer7_mitm_intercept_token_forbidden_reason("any") !== null, "any proibido");
need(layer7_mitm_intercept_token_forbidden_reason("0.0.0.0/0") !== null, "0/0 proibido");
need(layer7_mitm_intercept_token_forbidden_reason("2001:db8::1") !== null, "IPv6 proibido");
need(layer7_mitm_intercept_token_forbidden_reason("10.0.0.0/7") !== null, "prefixo</8 proibido");
need(layer7_mitm_intercept_token_forbidden_reason("192.168.100.24/32") === null, "/32 aceite");
need(layer7_mitm_normalize_ipv4_cidr_list(array("any", "10.0.0.0/7", "192.168.1.1")) === array("192.168.1.1"),
	"normalize descarta proibidos");

$errs = layer7_mitm_validate(array(
	"enabled" => true,
	"intercept" => array(
		"source_cidr" => array("192.168.100.24/32", "any"),
		"dest_cidr" => array("203.0.113.10")
	)
));
need(!empty($errs), "validate rejeita any misturado");

/* --- rdr_line_ok --- */
need(layer7_mitm_rdr_line_ok(
	"rdr on em0 inet proto tcp from <layer7_mitm_src> to <layer7_mitm_dst> port 443 -> 127.0.0.1 port 8443"
), "linha rdr valida");
need(!layer7_mitm_rdr_line_ok(
	"rdr on em0 inet proto tcp from any to <layer7_mitm_dst> port 443 -> 127.0.0.1 port 8443"
), "from any rejeitado");
need(!layer7_mitm_rdr_line_ok(
	"rdr on em0 inet6 proto tcp from <layer7_mitm_src> to <layer7_mitm_dst> port 443 -> ::1 port 8443"
), "inet6 rejeitado");

/* --- quic_line_ok / presença+escopo+remoção --- */
need(layer7_mitm_quic_line_ok(
	'block drop quick inet proto udp from <layer7_mitm_src> to <layer7_mitm_dst> port 443 label "layer7:mitm-anti-quic"'
), "linha quic valida");
need(!layer7_mitm_quic_line_ok(
	'block drop quick inet proto udp from any to <layer7_mitm_dst> port 443'
), "quic from any rejeitado");
need(!layer7_mitm_quic_line_ok(
	'block drop quick inet6 proto udp from <layer7_mitm_src> to <layer7_mitm_dst> port 443'
), "quic inet6 rejeitado");
need(!layer7_mitm_quic_line_ok(
	'block drop quick inet proto udp to port 443 label "layer7:anti-quic"'
), "quic global sem tabelas rejeitado");

/* --- GUI/package: sem filter_configure() cru nos callers MITM --- */
$mitm_php = $pkg . "/files/usr/local/www/packages/layer7/layer7_mitm.php";
need(is_file($mitm_php), "layer7_mitm.php");
$mitm_src = file_get_contents($mitm_php);
need(strpos($mitm_src, "layer7_filter_configure_safe()") !== false,
	"GUI usa filter_configure_safe");
need(!preg_match('/\bfilter_configure\s*\(\s*\)/', $mitm_src),
	"GUI MITM sem filter_configure() directo");

/* --- CA + runtime para sync/rdr --- */
$has_openssl = is_executable("/usr/bin/openssl") || is_executable("/usr/local/bin/openssl");
if (!$has_openssl) {
	fwrite(STDOUT, "SKIP openssl CA/runtime bloco\n");
	fwrite(STDOUT, "PASS package/pfSense-pkg-layer7/tests/test_mitm_regress.php (parcial)\n");
	exit(0);
}

$r = layer7_mitm_ca_generate("Layer7 Regress CA", 30);
need(!empty($r["ok"]), "ca_generate: " . ($r["msg"] ?? ""));
$ca_text = (string)@shell_exec(
	(is_executable("/usr/local/bin/openssl") ? "/usr/local/bin/openssl" : "openssl") .
	" x509 -in " . escapeshellarg(layer7_mitm_ca_cert_path()) . " -noout -text 2>/dev/null"
);
need(strpos($ca_text, "CA:TRUE") !== false, "CA gerada com CA:TRUE");

$fake = $testdir . "/usr/local/sbin/layer7-tlsproxy";
file_put_contents($fake, "#!/bin/sh\nexit 0\n");
chmod($fake, 0755);

$n = layer7_mitm_prepare_window_on_save(
    array("enabled" => false),
    array(
	"enabled" => true,
	"quic_mode" => "block",
	"window" => array("max_minutes" => 15),
	"intercept" => array(
		"source_cidr" => array("192.168.100.24/32"),
		"dest_cidr" => array("203.0.113.10"),
		"block_sni" => array("mitm-lab.test")
	)
    )
);
need(!empty($n["enabled"]), "enabled com CA");
need((int)$n["window"]["deadline_unix"] > time(), "P3 deadline armado");
$cfg = layer7_mitm_apply_to_config(layer7_bare_config(), $n);
$cfg["layer7"]["interfaces"] = array("lan");

layer7_mitm_ctrl_cleanup("");
need(layer7_generate_mitm_rdr_snippet($cfg, true) === "",
	"rdr vazio sem materialização");

need(layer7_mitm_sync_helper($cfg, true) === true, "sync ON");
need(layer7_mitm_control_plane_materialized(), "materializado apos ON");
$snip = layer7_generate_mitm_rdr_snippet($cfg, true);
need(strpos($snip, "from <layer7_mitm_src> to <layer7_mitm_dst>") !== false, "rdr source+dest");
need(!preg_match('/\bfrom\s+any\b/i', $snip), "snippet sem from any");
$quic = layer7_generate_mitm_quic_filter_rules_text($cfg, true);
need(strpos($quic, "layer7:mitm-anti-quic") !== false, "quic presente com MITM ON");
need(strpos($quic, "from <layer7_mitm_src> to <layer7_mitm_dst>") !== false,
	"quic escopo src→dst");
need(strpos($quic, "table <layer7_mitm_src> persist") !== false,
	"quic declara table src (pfctl -nf pfearly)");
need(strpos($quic, "table <layer7_mitm_dst> persist") !== false,
	"quic declara table dst (pfctl -nf pfearly)");
need(stripos($quic, "inet6") === false, "quic sem inet6");
need(!preg_match('/\bfrom\s+any\b/i', $quic), "quic sem from any");
need(layer7_mitm_sync_helper($cfg, true) === true, "sync ON idempotente");
layer7_mitm_sync_helper(layer7_bare_config(), false);
layer7_mitm_sync_helper(layer7_bare_config(), false);
need(!layer7_mitm_control_plane_materialized(), "disable idempotente");
need(layer7_generate_mitm_quic_filter_rules_text($cfg, true) === "",
	"quic removido apos MITM OFF");

/* Rematerializar e failsafe */
need(layer7_mitm_sync_helper($cfg, true) === true, "sync ON para failsafe");
$rolled = layer7_mitm_failsafe_rollback($cfg, "regress");
need(empty($rolled["layer7"]["mitm"]["enabled"]), "failsafe enabled OFF");
need(!layer7_mitm_control_plane_materialized(), "failsafe remove gate/flag");
need(layer7_generate_mitm_rdr_snippet($cfg, true) === "",
	"apos failsafe zero rdr");
need(layer7_generate_mitm_quic_filter_rules_text($cfg, true) === "",
	"apos failsafe zero quic");

/* P3: expire fail-closed + audit + status + S8 */
need(layer7_mitm_sync_helper($cfg, true) === true, "sync ON para expire");
$n_exp = $n;
$n_exp["window"]["deadline_unix"] = time() - 10;
$cfg_exp = layer7_mitm_apply_to_config(layer7_bare_config(), $n_exp);
$cfg_exp["layer7"]["interfaces"] = array("lan");
need(!layer7_mitm_effective($n_exp, true), "P3 effective false expirado");
$st = layer7_mitm_window_status($n_exp);
need(!empty($st["expired"]), "P3 status expired");
need($st["quic_mode"] === "block", "P3 status quic_mode");
$ex = layer7_mitm_expire_if_needed($cfg_exp);
need(!empty($ex["changed"]) && !empty($ex["expired"]), "P3 expire changed");
need(empty($ex["data"]["layer7"]["mitm"]["enabled"]), "P3 expire OFF");
need(!layer7_mitm_control_plane_materialized(), "P3 S8 limpo apos expire");
need(layer7_generate_mitm_rdr_snippet($cfg_exp, true) === "", "P3 S8 zero rdr");
$alog = @file_get_contents(layer7_mitm_audit_path());
need(is_string($alog) && strpos($alog, '"event":"expire"') !== false, "P3 audit expire");
need(strpos($alog, '"payload_tls":false') !== false, "P3 audit sem payload");
need(strpos($alog, "PRIVATE KEY") === false, "P3 audit sem chave");

/* GUI: max_window + break-glass + status fields */
need(strpos($mitm_src, "mitm_max_window") !== false, "GUI max_window");
need(strpos($mitm_src, "mitm_duration_mode") !== false, "GUI duration mode");
need(strpos($mitm_src, "mitm_break_glass") !== false, "GUI break-glass");
need(strpos($mitm_src, "tempo restante") !== false, "GUI tempo restante");
need(strpos($mitm_src, "layer7_mitm_prepare_window_on_save") !== false, "GUI arma janela");
need(strpos($mitm_src, "Activar inspeccao TLS") !== false, "GUI label produto");
need(strpos($mitm_src, "Manter ligada ate eu desligar") !== false, "GUI ate desligar");

/* 20.35: até desligar — sem deadline, não expira, effective possível */
$n_off = layer7_mitm_prepare_window_on_save(
    array("enabled" => false),
    array(
	"enabled" => true,
	"quic_mode" => "block",
	"window" => array("max_minutes" => 0),
	"intercept" => array(
		"source_cidr" => array("192.168.100.24/32"),
		"dest_cidr" => array("203.0.113.10"),
		"block_sni" => array("mitm-lab.test")
	)
    )
);
need((int)$n_off["window"]["max_minutes"] === 0, "20.35 max_minutes=0");
need((int)$n_off["window"]["deadline_unix"] === 0, "20.35 sem deadline");
need(layer7_mitm_window_until_off($n_off), "20.35 until_off");
need(!layer7_mitm_window_expired($n_off), "20.35 nao expira por tempo");
$st_off = layer7_mitm_window_status($n_off);
need(!empty($st_off["until_off"]), "20.35 status until_off");
need(empty($st_off["expired"]), "20.35 status nao expired");

/* filter_configure_safe anti-reentrada */
$fc = layer7_filter_configure_safe();
need(!empty($fc["ok"]), "filter_configure_safe ok");
$GLOBALS["layer7_resync_active"] = true;
$fc2 = layer7_filter_configure_safe();
$GLOBALS["layer7_resync_active"] = false;
need(!empty($fc2["skipped"]), "filter_configure_safe skip em resync");

layer7_mitm_ca_delete();
@unlink($fake);

fwrite(STDOUT, "PASS package/pfSense-pkg-layer7/tests/test_mitm_regress.php\n");
exit(0);
