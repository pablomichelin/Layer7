<?php
/*
 * test_ctrl_exec_timeout.php — control-plane: timeout finito + erro explicito
 *
 * Uso: php tests/functional/test_ctrl_exec_timeout.php
 */
$root = dirname(__DIR__, 2);
$testdir = sys_get_temp_dir() . "/layer7-ctrl-" . getmypid();
@mkdir($testdir . "/usr/local/etc/layer7", 0755, true);
@mkdir($testdir . "/var/run/layer7", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);

require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

function fail($msg)
{
	fwrite(STDERR, "FAIL: $msg\n");
	exit(1);
}

if (!defined("L7_CTRL_TIMEOUT_SERVICE") || L7_CTRL_TIMEOUT_SERVICE < 1) {
	fail("L7_CTRL_TIMEOUT_SERVICE em falta");
}

$ok = layer7_exec_timeout("/usr/bin/true", 5);
if (empty($ok["ok"]) || !empty($ok["timed_out"])) {
	fail("true deve ok sem timeout: " . json_encode($ok));
}

$bad = layer7_exec_timeout("/usr/bin/false", 5);
if (!empty($bad["ok"]) || $bad["error"] === "") {
	fail("false deve erro explicito: " . json_encode($bad));
}

$t0 = microtime(true);
$to = layer7_exec_timeout("/bin/sleep 8", 1);
$elapsed = microtime(true) - $t0;
if (empty($to["timed_out"]) || !empty($to["ok"])) {
	fail("sleep deve timed_out: " . json_encode($to));
}
if ($elapsed > 4.0) {
	fail("timeout demasiado longo: {$elapsed}s");
}
if (strpos($to["error"], "timeout") === false) {
	fail("error deve mencionar timeout: " . $to["error"]);
}

/* D0 F1-bis: filho ignora SIGTERM — wrapper deve regressar via -k / KILL. */
if (!defined("L7_CTRL_TIMEOUT_KILL_GRACE") || L7_CTRL_TIMEOUT_KILL_GRACE < 1) {
	fail("L7_CTRL_TIMEOUT_KILL_GRACE em falta");
}
/* FreeBSD: sem --foreground o timeout mata o process group do daemon. */
$src = file_get_contents($root .
    "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc");
if (strpos($src, "timeout --foreground") === false &&
    strpos($src, "--foreground -k") === false) {
	fail("layer7_exec_timeout deve usar timeout --foreground");
}
$rcsrc = file_get_contents($root .
    "/package/pfSense-pkg-layer7/files/usr/local/etc/rc.d/layer7-tlsproxy");
if (strpos($rcsrc, "daemon -f") === false) {
	fail("rc.d layer7-tlsproxy deve usar daemon -f");
}
$ignore = 'trap "" TERM; /bin/sleep 60';
$t0 = microtime(true);
$hard = layer7_exec_timeout("/bin/sh -c " . escapeshellarg($ignore), 1);
$elapsed = microtime(true) - $t0;
if (empty($hard["timed_out"]) || !empty($hard["ok"])) {
	fail("ignore-TERM deve timed_out: " . json_encode($hard));
}
/* 1s timeout + grace(5) + margem — nao pode aproximar-se dos 60s do sleep. */
$max_ok = 1 + L7_CTRL_TIMEOUT_KILL_GRACE + 4;
if ($elapsed > $max_ok) {
	fail("ignore-TERM demorou demais ({$elapsed}s > {$max_ok}s) — falta -k?");
}

/* sync_helper sob TEST_ROOT nao chama service; OFF limpa gate/flag. */
$gate = layer7_mitm_product_gate_path();
$flag = layer7_mitm_effective_flag_path();
@file_put_contents($gate, "LAYER7_TLSPROXY_PRODUCT=1\n");
@file_put_contents($flag, "1\n");
$r = layer7_mitm_sync_helper(layer7_bare_config(), false);
if ($r !== false) {
	fail("sync OFF deve return false");
}
if (file_exists($gate) || file_exists($flag)) {
	fail("cleanup idempotente deve remover gate/flag");
}
/* Segunda chamada: idempotente */
layer7_mitm_ctrl_cleanup("");
if (file_exists($gate) || file_exists($flag)) {
	fail("cleanup repetido deve permanecer limpo");
}
if (!function_exists("layer7_mitm_failsafe_rollback") ||
    !function_exists("layer7_mitm_control_plane_materialized")) {
	fail("failsafe_rollback / control_plane_materialized em falta");
}
@file_put_contents($gate, "LAYER7_TLSPROXY_PRODUCT=1\n");
@file_put_contents($flag, "1\n");
$rolled = layer7_mitm_failsafe_rollback(layer7_bare_config(), "timeout harness");
if (layer7_mitm_control_plane_materialized() || file_exists($gate) || file_exists($flag)) {
	fail("failsafe_rollback deve teardown limpo");
}
if (!empty($rolled["layer7"]["mitm"]["enabled"])) {
	fail("failsafe_rollback enabled OFF");
}

/* filter_configure_safe: anti-reentrada + idempotente sob TEST_ROOT. */
if (!function_exists("layer7_filter_configure_safe")) {
	fail("layer7_filter_configure_safe em falta");
}
if (!defined("L7_CTRL_TIMEOUT_FILTER") || (int)L7_CTRL_TIMEOUT_FILTER < 1) {
	fail("L7_CTRL_TIMEOUT_FILTER deve ser finito");
}
$fc1 = layer7_filter_configure_safe();
if (empty($fc1["ok"]) || !empty($fc1["skipped"])) {
	fail("primeiro filter_configure_safe deve correr: " . json_encode($fc1));
}
$GLOBALS["layer7_resync_active"] = true;
$fc2 = layer7_filter_configure_safe();
$GLOBALS["layer7_resync_active"] = false;
if (empty($fc2["skipped"]) || ($fc2["reason"] ?? "") !== "reentrant") {
	fail("durante resync deve skip reentrante: " . json_encode($fc2));
}
$GLOBALS["layer7_filter_configure_active"] = true;
$fc3 = layer7_filter_configure_safe();
$GLOBALS["layer7_filter_configure_active"] = false;
if (empty($fc3["skipped"])) {
	fail("durante filter activo deve skip: " . json_encode($fc3));
}

if (!function_exists("layer7_mitm_helper_listening")) {
	fail("layer7_mitm_helper_listening em falta");
}
/* Porta produto tipica fechada neste harness → false (sem falso positivo). */
if (layer7_mitm_helper_listening()) {
	fail("listening deve ser false sem helper neste teste");
}

echo "PASS: control-plane timeout + erro explicito + cleanup idempotente\n";
exit(0);
