<?php
/*
 * Padrao CORRIGIDO (1.9.43+): layer7_exec_timeout em vez de exec() nu.
 * Com mock onerestart que dorme, o activador DEVE regressar com timed_out
 * e continuar (nao pendurar ate SIGTERM externo).
 *
 * Uso:
 *   L7_HARNESS_SERVICE=mock-bin/service php php-sync-fixed-pattern.php
 */
$root = dirname(__DIR__, 3);
$testdir = sys_get_temp_dir() . "/layer7-fixed-" . getmypid();
@mkdir($testdir . "/usr/local/etc/layer7", 0755, true);
@mkdir($testdir . "/var/run/layer7", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);

require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

$marker = getenv("L7_HARNESS_MARKER");
if ($marker !== false && $marker !== "") {
	@file_put_contents($marker, "started " . gmdate("c") . " pid=" . getmypid() . "\n");
}

fwrite(STDOUT, "effective_pre_sync=yes\n");
fflush(STDOUT);

$service = getenv("L7_HARNESS_SERVICE");
if ($service === false || $service === "") {
	$service = "service";
}
$timeout_sec = (int)getenv("L7_HARNESS_CTRL_TIMEOUT");
if ($timeout_sec < 1) {
	$timeout_sec = 2;
}

$t0 = microtime(true);
$svc = layer7_exec_timeout(
    escapeshellcmd($service) . " layer7-tlsproxy onerestart",
    $timeout_sec
);
$elapsed = microtime(true) - $t0;

if (!empty($svc["ok"])) {
	fwrite(STDOUT, "sync=yes\n");
	fwrite(STDOUT, "RESULT=unexpected_ok\n");
	exit(1);
}

fwrite(STDOUT, "sync=fail_cleaned\n");
fwrite(STDOUT, "timed_out=" . (!empty($svc["timed_out"]) ? "yes" : "no") . "\n");
fwrite(STDOUT, "error=" . $svc["error"] . "\n");
fwrite(STDOUT, "elapsed_s=" . sprintf("%.2f", $elapsed) . "\n");
fflush(STDOUT);

if ($marker !== false && $marker !== "") {
	@file_put_contents($marker, "finished " . gmdate("c") . "\n", FILE_APPEND);
}

if (empty($svc["timed_out"])) {
	fwrite(STDERR, "FAIL: esperado timed_out no mock onerestart\n");
	exit(1);
}
/* timeout_sec + kill grace (-k) + margem; alinhado a test_ctrl_exec_timeout. */
$grace = defined("L7_CTRL_TIMEOUT_KILL_GRACE")
    ? (int)L7_CTRL_TIMEOUT_KILL_GRACE : 5;
$max_ok = $timeout_sec + $grace + 3;
if ($elapsed > $max_ok) {
	fwrite(STDERR, "FAIL: activador demorou demais apos timeout"
	    . " ({$elapsed}s > {$max_ok}s)\n");
	exit(1);
}

fwrite(STDOUT, "PASS_FIXED=yes\n");
exit(0);
