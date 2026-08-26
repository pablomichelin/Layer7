<?php
/*
 * BG-167 — GUI nao mostra "aplicar" sem enforce_mode=1 do daemon.
 * Uso: php tests/functional/test_gui_enforce_state.php
 */
$root = dirname(__DIR__, 2);
$testdir = sys_get_temp_dir() . "/layer7-enf-" . getmypid();
@mkdir($testdir . "/usr/local/etc", 0755, true);
@mkdir($testdir . "/var/db/layer7", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);

require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

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

$data_enforce = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce"
	)
);
$data_off = array(
	"layer7" => array(
		"enabled" => false,
		"mode" => "enforce"
	)
);
$data_monitor = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "monitor"
	)
);

$unlicensed = array(
	"enforce_mode" => 0,
	"license_valid" => false
);
$armed = array(
	"enforce_mode" => 1,
	"license_valid" => true
);

$s = layer7_gui_enforce_state($data_enforce, $unlicensed);
need($s["display_mode"] === "monitor", "unlicensed must display monitor");
need($s["enforce_armed"] === false, "unlicensed must not be armed");
need($s["reason"] === "no_license", "unlicensed reason");
need($s["requested_mode"] === "enforce", "requested stays enforce");
$badge = layer7_gui_mode_badge_html($s);
need(strpos($badge, "label-danger") === false, "no red apply badge without license");
need(strpos($badge, "label-info") !== false, "monitor badge present");

$s = layer7_gui_enforce_state($data_enforce, $armed);
need($s["display_mode"] === "enforce", "licensed armed displays enforce");
need($s["enforce_armed"] === true, "licensed armed");
need(strpos(layer7_gui_mode_badge_html($s), "label-danger") !== false,
    "armed shows apply badge");

$s = layer7_gui_enforce_state($data_off, $armed);
need($s["display_mode"] === "disabled", "service off wins over armed stats");

$s = layer7_gui_enforce_state($data_monitor, $unlicensed);
need($s["display_mode"] === "monitor", "requested monitor stays monitor");
need($s["reason"] === "monitor", "no extra reason when requested monitor");

$s = layer7_gui_enforce_state($data_enforce, array());
need($s["display_mode"] === "monitor", "empty stats never show apply");
need($s["reason"] === "daemon_down", "empty stats = daemon_down");

$s = layer7_gui_enforce_state($data_enforce, array(
	"enforce_mode" => 0,
	"license_valid" => true
));
need($s["display_mode"] === "monitor", "licensed but not armed = monitor");
need($s["reason"] === "not_armed", "not_armed reason");

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testdir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $f) {
	$f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($testdir);

fwrite(STDOUT, "PASS test_gui_enforce_state.php\n");
