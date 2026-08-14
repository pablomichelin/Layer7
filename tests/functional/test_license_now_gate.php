<?php
/*
 * test_license_now_gate.php — BG-128 P2-17
 *
 * Relógio da GUI/helper (layer7_license_now):
 *  - LAYER7_TEST_NOW só com LAYER7_TEST_ROOT (caminho controlado)
 *  - sem TEST_ROOT, o ambiente sozinho não congela a data (produção)
 *
 * Uso: php tests/functional/test_license_now_gate.php
 */
$root = dirname(__DIR__, 2);
$inc = $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";
$testdir = sys_get_temp_dir() . "/layer7-now-p217-" . getmypid();
@mkdir($testdir, 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);
putenv("LAYER7_TEST_NOW");
putenv("LAYER7_INC=" . $inc);

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

function l7_now_cleanup($testdir)
{
	$it = new RecursiveIteratorIterator(
	    new RecursiveDirectoryIterator($testdir, FilesystemIterator::SKIP_DOTS),
	    RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($it as $f) {
		$f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
	}
	@rmdir($testdir);
}

$src = file_get_contents($inc);
need($src !== false, "read layer7.inc");
need(preg_match_all('/getenv\\s*\\(\\s*["\']LAYER7_TEST_NOW["\']/',
    $src) === 1,
    "LAYER7_TEST_NOW may be read only in layer7_license_now");
need(strpos($src, 'function layer7_license_now()') !== false &&
    preg_match('/function layer7_license_now\\(\\)\\s*\\{[^}]*LAYER7_TEST_ROOT[^}]*LAYER7_TEST_NOW/s',
	$src) === 1,
    "LAYER7_TEST_NOW must be gated on LAYER7_TEST_ROOT");

$frozen = 1786708800; /* 2026-08-14 15:00:00 UTC — longe de fronteira DST */

/* 1) Caminho controlado: TEST_ROOT + TEST_NOW congela o relógio. */
putenv("LAYER7_TEST_NOW=" . (string)$frozen);
need(layer7_license_now() === $frozen,
    "TEST_NOW with TEST_ROOT must freeze the clock");

/* 2) TEST_ROOT sem TEST_NOW usa time() real. */
putenv("LAYER7_TEST_NOW");
$before = time();
$got = layer7_license_now();
$after = time();
need($got >= $before && $got <= $after + 1,
    "TEST_ROOT without TEST_NOW must use wall clock");

/* 3) TEST_NOW inválido é ignorado mesmo com TEST_ROOT. */
putenv("LAYER7_TEST_NOW=not-a-unix-ts");
$before = time();
$got = layer7_license_now();
$after = time();
need($got >= $before && $got <= $after + 1,
    "non-numeric TEST_NOW must be ignored");

/* 4) TEST_ROOT vazio não honra TEST_NOW. */
putenv("LAYER7_TEST_NOW=" . (string)$frozen);
putenv("LAYER7_TEST_ROOT=");
need(layer7_license_now() !== $frozen,
    "empty TEST_ROOT must not honor TEST_NOW");
putenv("LAYER7_TEST_ROOT=" . $testdir);

/* 5) Sem TEST_ROOT, TEST_NOW não é bypass de produção (processo limpo). */
$php = PHP_BINARY;
$probe = 'require $argv[1]; echo layer7_license_now();';
$cmd = "env -i PATH=" . escapeshellarg(getenv("PATH")) .
    " LAYER7_TEST_NOW=" . (string)$frozen . " " .
    escapeshellarg($php) . " -d pcre.jit=0 -d display_errors=0 -r " .
    escapeshellarg($probe) . " " . escapeshellarg($inc);
exec($cmd . " 2>/dev/null", $probe_out, $probe_rc);
$probe_now = 0;
if (!empty($probe_out)) {
	$probe_now = (int)end($probe_out);
}
need($probe_rc === 0, "probe without TEST_ROOT must run");
need($probe_now !== $frozen,
    "LAYER7_TEST_NOW without TEST_ROOT must not win");
need(abs($probe_now - time()) <= 5,
    "production clock without TEST_ROOT must be wall time");

/* 6) Binding: sem TEST_ROOT, TEST_NOW no passado não reabre licença morta. */
$verified_dead = array(
	"hardware_id" => "test-hw-p217",
	"expiry" => "2020-01-15",
	"features" => "base,mitm",
	"verified" => true
);
putenv("LAYER7_TEST_ROOT");
putenv("LAYER7_TEST_NOW=1579046400");
$bind_deny = layer7_license_binding_ok($verified_dead, "test-hw-p217");
need($bind_deny["reason"] === "expired",
    "TEST_NOW without TEST_ROOT must not revive expired license");
need(empty($bind_deny["ok"]),
    "expired license stays locked without TEST_ROOT");

/* 7) Binding controlado: TEST_ROOT + TEST_NOW no passado reabre no harness. */
putenv("LAYER7_TEST_ROOT=" . $testdir);
putenv("LAYER7_TEST_NOW=1579046400"); /* 2020-01-15 00:00 UTC */
$bind_ok = layer7_license_binding_ok($verified_dead, "test-hw-p217");
need(!empty($bind_ok["ok"]) &&
    ($bind_ok["reason"] === "valid" || $bind_ok["reason"] === "grace"),
    "TEST_NOW with TEST_ROOT may freeze binding for harness");

fwrite(STDOUT, "PASS test_license_now_gate.php\n");
l7_now_cleanup($testdir);
exit(0);
