<?php
/*
 * test_license_expiry_policy.php — BG-128 P2-13 (prova, sem mudança de runtime)
 *
 * Contrato actual (HEAD): expiry=YYYY-MM-DD é a meia-noite local via
 * mktime hora 0. No dia D às 12:00 a licença já está em grace. O
 * servidor compara o calendário UTC (activo em D). tm_isdst=0 no
 * daemon C pode divergir ±1 h da GUI PHP em fusos com DST.
 *
 * Este cadeado trava o contrato-as-implemented. Uma política nova
 * (fim do dia local/UTC, timegm, só tm_isdst=-1) exige GO e falha
 * estes asserts de propósito.
 *
 * Uso: php tests/functional/test_license_expiry_policy.php
 */
$root = dirname(__DIR__, 2);
$inc = $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";
$lic_c = $root . "/src/layer7d/license.c";
$testdir = sys_get_temp_dir() . "/layer7-p213-" . getmypid();
@mkdir($testdir, 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);
putenv("LAYER7_TEST_NOW");

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

function l7_p213_cleanup($testdir)
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

function l7_bind_at($tz, $expiry, $now)
{
	putenv("TZ=" . $tz);
	date_default_timezone_set($tz);
	$v = array(
		"hardware_id" => "hw-p213",
		"expiry" => $expiry,
		"verified" => true
	);
	return layer7_license_binding_ok($v, "hw-p213", $now);
}

function l7_local_ts($tz, $y, $mo, $d, $h, $mi, $s)
{
	putenv("TZ=" . $tz);
	date_default_timezone_set($tz);
	return mktime($h, $mi, $s, $mo, $d, $y);
}

$src_php = file_get_contents($inc);
$src_c = file_get_contents($lic_c);
need($src_php !== false && $src_c !== false, "read sources");

/* Cadeado de fórmula: meia-noite local, sem EOD e sem tm_isdst=-1. */
need(strpos($src_c, "exp_time = mktime(&exp_tm);") !== false,
    "daemon must keep mktime on parsed expiry");
need(preg_match('/parse_date\\([^)]+\\)[\\s\\S]{0,200}memset\\(\\s*tm,\\s*0/',
    $src_c) === 1,
    "parse_date must memset tm (tm_isdst=0)");
need(strpos($src_c, "tm_hour = 23") === false &&
    strpos($src_c, "tm->tm_hour = 23") === false,
    "daemon must not switch to end-of-day without GO");
need(!preg_match('/parse_date\\([\\s\\S]{0,400}tm_isdst\\s*=\\s*-1/', $src_c),
    "parse_date must not set tm_isdst=-1 without GO");
need(strpos($src_php, 'mktime(0, 0, 0, $mo, $d, $y)') !== false,
    "GUI binding must keep local midnight mktime");
need(strpos($src_c, "timegm") === false &&
    strpos($src_php, "gmmktime") === false &&
    strpos($src_php, "timegm") === false,
    "neither side may switch to UTC instant without GO");

$tz_br = "America/Sao_Paulo";
$tz_utc = "UTC";
$tz_ny = "America/New_York";
$exp = "2026-08-14";

/* 1) Meia-noite local de D: ainda VALID (diff_days == 0). */
$b = l7_bind_at($tz_br, $exp, l7_local_ts($tz_br, 2026, 8, 14, 0, 0, 0));
need($b["reason"] === "valid" && empty($b["grace"]) && !empty($b["ok"]),
    "BR midnight on D must stay valid");

/* 2) 1 s após meia-noite de D: GRACE (teste canónico da auditoria). */
$b = l7_bind_at($tz_br, $exp, l7_local_ts($tz_br, 2026, 8, 14, 0, 0, 1));
need($b["reason"] === "grace" && !empty($b["grace"]) && !empty($b["ok"]),
    "BR 1s after midnight on D must enter grace");

/* 3) Meio-dia local de D: GRACE — «válido até D» já não é VALID. */
$b = l7_bind_at($tz_br, $exp, l7_local_ts($tz_br, 2026, 8, 14, 12, 0, 0));
need($b["reason"] === "grace" && !empty($b["grace"]) && !empty($b["ok"]),
    "BR noon on D must be grace (HEAD midnight policy)");

/* 4) 1 s antes de D: VALID. */
$b = l7_bind_at($tz_br, $exp, l7_local_ts($tz_br, 2026, 8, 13, 23, 59, 59));
need($b["reason"] === "valid" && empty($b["grace"]) && !empty($b["ok"]),
    "BR 1s before D must stay valid");

/* 5) Meio-dia D-1: VALID. */
$b = l7_bind_at($tz_br, $exp, l7_local_ts($tz_br, 2026, 8, 13, 12, 0, 0));
need($b["reason"] === "valid" && empty($b["grace"]),
    "BR noon D-1 must be valid");

/* 6) Meio-dia D+14: EXPIRED (14.5 d > 14). Fim-do-dia local reabriria grace. */
$b = l7_bind_at($tz_br, $exp, l7_local_ts($tz_br, 2026, 8, 28, 12, 0, 0));
need($b["reason"] === "expired" && empty($b["ok"]) && empty($b["grace"]),
    "BR noon D+14 must be expired under midnight policy");

/* 7) UTC: meio-dia de D também cai em grace. */
$b = l7_bind_at($tz_utc, $exp, l7_local_ts($tz_utc, 2026, 8, 14, 12, 0, 0));
need($b["reason"] === "grace" && !empty($b["ok"]),
    "UTC noon on D must be grace");

/* 8) Servidor UTC no mesmo instante: calendário D ainda ACTIVE. */
$noon_br = l7_local_ts($tz_br, 2026, 8, 14, 12, 0, 0);
$today_utc = gmdate("Y-m-d", $noon_br);
need($today_utc === "2026-08-14", "BR noon on D is still 2026-08-14 UTC");
need(!($exp < $today_utc),
    "server isLicenseExpired stays active on calendar day D");

/* 9) DST verão NY 00:30: GUI PHP (DST automático) já está em grace.
 * O daemon C com tm_isdst=0 neste instante fica VALID (+3600 s). Residual
 * documentado; sem correção neste bloco. */
$b = l7_bind_at($tz_ny, "2026-07-15",
    l7_local_ts($tz_ny, 2026, 7, 15, 0, 30, 0));
need($b["reason"] === "grace" && !empty($b["ok"]),
    "NY 00:30 summer must be grace in PHP (auto DST)");

/* 10) Compatibilidade GUI↔daemon fora de DST: mesmos unix em BR. */
$now_br_noon = l7_local_ts($tz_br, 2026, 8, 14, 12, 0, 0);
need($now_br_noon === 1786719600,
    "frozen BR noon unix must stay 1786719600");
$exp_midnight_br = l7_local_ts($tz_br, 2026, 8, 14, 0, 0, 0);
need($exp_midnight_br === 1786676400,
    "frozen BR midnight unix must stay 1786676400");

fwrite(STDOUT, "PASS test_license_expiry_policy.php\n");
l7_p213_cleanup($testdir);
exit(0);
