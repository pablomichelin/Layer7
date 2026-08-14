<?php
/**
 * P4 soak activate — escopo lab apenas.
 * src 192.168.100.24/32 → dst 198.18.0.10/32; SNI mitm-lab.test;
 * max_window=240; quic_mode=block. Sem from any. Sem payload TLS.
 * Não usar sem GO lab. Não toca .234/.235.
 */
require_once("config.inc");
require_once("/usr/local/pkg/layer7.inc");

$out = array();
$r = layer7_mitm_ca_generate("Layer7-P4-Soak-CA", 2);
$out["ca_gen"] = !empty($r["ok"]) ? "ok" : "fail";
$out["ca_msg"] = isset($r["msg"]) ? $r["msg"] : "";
if (empty($r["ok"])) {
	echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
	exit(2);
}

$data = layer7_load_or_default();
$prev = layer7_mitm_from_config($data);
$mitm = $prev;
$mitm["enabled"] = true;
$mitm["quic_mode"] = "block";
$mitm["window"]["max_minutes"] = 240;
$mitm["intercept"]["source_cidr"] = array("192.168.100.24/32");
$mitm["intercept"]["dest_cidr"] = array("198.18.0.10/32");
$mitm["intercept"]["block_sni"] = array("mitm-lab.test");
$errs = layer7_mitm_validate($mitm);
if (!empty($errs)) {
	$out["validate"] = $errs;
	echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
	exit(3);
}
$mitm = layer7_mitm_prepare_window_on_save($prev, $mitm);
$data = layer7_mitm_apply_to_config($data, $mitm);
if (!layer7_save_json($data)) {
	$out["save"] = "fail";
	echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
	exit(4);
}
$t0 = microtime(true);
$sync = layer7_mitm_sync_helper($data, true);
$out["sync"] = $sync ? "ok" : "fail";
$out["sync_sec"] = round(microtime(true) - $t0, 3);
if (!$sync) {
	$data = layer7_mitm_failsafe_rollback($data, "P4 sync fail");
	layer7_save_json($data);
	layer7_filter_configure_safe();
	echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
	exit(5);
}
$fc = layer7_filter_configure_safe();
$out["filter"] = !empty($fc["ok"]) ? "ok" : "fail";
$mitm = layer7_mitm_from_config(layer7_load_or_default());
$out["enabled"] = !empty($mitm["enabled"]);
$out["effective"] = layer7_mitm_effective($mitm, true);
$out["window"] = layer7_mitm_window_status($mitm);
$out["materialized"] = layer7_mitm_control_plane_materialized();
echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
exit($out["effective"] && $out["materialized"] ? 0 : 6);
