<?php
/*
 * BG-168 — PF nao emite block/anti-QUIC sem enforce_mode=1.
 * Uso: php tests/functional/test_pf_should_enforce.php
 */
$root = dirname(__DIR__, 2);
$testdir = sys_get_temp_dir() . "/layer7-pf-enf-" . getmypid();
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

$data = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"enforcement_model" => "legacy_global",
		"block_quic" => true,
		"block_quic_interfaces" => array("oce0.60"),
		"block_dot_doq" => true,
		"policies" => array()
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

need(layer7_pf_should_enforce($data, $unlicensed) === false,
    "requested enforce + no license must not arm PF");
need(layer7_pf_should_enforce($data, $armed) === true,
    "armed daemon may emit PF enforcement");

$early_off = layer7_pf_early_enforcement_rules_text($data);
/* early_enforcement le stats do TEST_ROOT (ainda sem ficheiro) */
need($early_off === "",
    "pfearly must be empty when stats are absent");

file_put_contents($testdir . "/var/db/layer7/layer7-stats.json",
    json_encode($unlicensed));
$early_unlic = layer7_pf_early_enforcement_rules_text($data);
need($early_unlic === "",
    "pfearly must be empty without license");
need(strpos($early_unlic, "anti-quic") === false,
    "anti-QUIC must not appear unlicensed");
need(strpos($early_unlic, "block drop") === false,
    "no block drop unlicensed");

file_put_contents($testdir . "/var/db/layer7/layer7-stats.json",
    json_encode($armed));
$early_on = layer7_pf_early_enforcement_rules_text($data);
need(strpos($early_on, "block drop") !== false,
    "armed pfearly emits blocks");
need(strpos($early_on, "layer7:anti-quic") !== false,
    "armed pfearly emits anti-QUIC");

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testdir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $f) {
	$f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($testdir);

fwrite(STDOUT, "PASS test_pf_should_enforce.php\n");
