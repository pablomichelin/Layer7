<?php
/*
 * test_entitlements_gui.php — 30.7 / BG-120 / GA2.8–GA2.10 + P2-11 / BG-128
 *
 * Stats forjados NÃO desbloqueiam Identity/MITM.
 * .lic Ed25519 verificado concede só com HW + expiry/grace do daemon.
 * Gate GA2.9: layer7-mitm-entitle-ok rejeita sem mitm assinado e sem binding.
 *
 * Uso: php tests/functional/test_entitlements_gui.php
 */
$root = dirname(__DIR__, 2);
$testdir = sys_get_temp_dir() . "/layer7-ent-" . getmypid();
@mkdir($testdir . "/usr/local/etc", 0755, true);
@mkdir($testdir . "/usr/local/share/pfSense-pkg-layer7", 0755, true);
@mkdir($testdir . "/var/db/layer7", 0755, true);
@mkdir($testdir . "/var/run/layer7", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);
putenv("LAYER7_TEST_HW_ID=test-hw-30.7");
putenv("LAYER7_INC=" . $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc");

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

function l7_ent_cleanup($testdir)
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

/* Gerar keypair Ed25519 efémero para o teste (não usa pubkey de produção). */
$openssl = "openssl";
foreach (array("/usr/local/bin/openssl", "/usr/bin/openssl") as $cand) {
	if (is_executable($cand)) {
		$openssl = $cand;
		break;
	}
}
$priv = $testdir . "/test-priv.pem";
$pub = $testdir . "/usr/local/share/pfSense-pkg-layer7/license-signing-public-key.pem";
exec(escapeshellarg($openssl) . " genpkey -algorithm ED25519 -out " .
    escapeshellarg($priv) . " 2>/dev/null", $o, $rc);
need($rc === 0 && is_readable($priv), "openssl genpkey ED25519");
exec(escapeshellarg($openssl) . " pkey -in " . escapeshellarg($priv) .
    " -pubout -out " . escapeshellarg($pub) . " 2>/dev/null", $o2, $rc2);
need($rc2 === 0 && is_readable($pub), "openssl pkey -pubout");
putenv("LAYER7_LICENSE_PUBKEY=" . $pub);
putenv("OPENSSL_BIN=" . $openssl);

function l7_sign_lic($priv, $features, $path, $hw = "test-hw-30.7",
    $expiry = "2099-12-31")
{
	$data = json_encode(array(
		"hardware_id" => $hw,
		"customer" => "GA2.8",
		"expiry" => $expiry,
		"features" => $features
	), JSON_UNESCAPED_SLASHES);
	need(is_string($data) && $data !== "", "json encode lic data");
	$td = dirname($path) . "/.sigtmp." . getmypid();
	@mkdir($td, 0700, true);
	$data_f = $td . "/data";
	$sig_f = $td . "/sig";
	file_put_contents($data_f, $data);
	$openssl = is_executable("/usr/local/bin/openssl") ? "/usr/local/bin/openssl" :
	    (is_executable("/usr/bin/openssl") ? "/usr/bin/openssl" : "openssl");
	exec(escapeshellarg($openssl) . " pkeyutl -sign -inkey " .
	    escapeshellarg($priv) . " -rawin -in " . escapeshellarg($data_f) .
	    " -out " . escapeshellarg($sig_f) . " 2>/dev/null", $out, $rc);
	need($rc === 0 && is_readable($sig_f), "openssl pkeyutl -sign");
	$sig = bin2hex(file_get_contents($sig_f));
	need(strlen($sig) === 128, "sig hex 64 bytes");
	$env = json_encode(array("data" => $data, "sig" => $sig));
	file_put_contents($path, $env);
	@unlink($data_f);
	@unlink($sig_f);
	@rmdir($td);
}

$lic = layer7_lic_path();
$inc = $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";
$now = 1786708800; /* 2026-08-14 15:00:00 UTC — longe de fronteira DST */
/* LAYER7_TEST_NOW só é honrado com LAYER7_TEST_ROOT (já definido acima). */
putenv("LAYER7_TEST_NOW=" . (string)$now);
$exp_ok = date("Y-m-d", $now + (30 * 86400));
$exp_grace = date("Y-m-d", $now - (5 * 86400));
$exp_dead = date("Y-m-d", $now - (20 * 86400));

/* GA2.8: stats forjados sem .lic → sem unlock. */
file_put_contents(layer7_stats_path(), json_encode(array(
	"license_valid" => true,
	"license_features" => "base,identity,mitm",
	"license_features_flags" => 7,
	"license_customer" => "forged",
	"license_hardware_id" => "test-hw-30.7",
	"license_expiry" => "2099-12-31"
)));
$e = layer7_entitlements();
need(empty($e["has_mitm"]), "stats forged must not unlock mitm");
need(empty($e["has_identity"]), "stats forged must not unlock identity");
need($e["source"] === "none", "source none without verified lic");
need(empty($e["license_valid"]), "forged stats must not set license_valid");
need(!layer7_has_entitlement("mitm"), "has_entitlement mitm false");
need(!layer7_has_entitlement("identity"), "has_entitlement identity false");
need(layer7_has_entitlement("base"), "base always true");

/* .lic sem assinatura válida → sem unlock. */
file_put_contents($lic, json_encode(array(
	"data" => "{\"features\":\"base,mitm\",\"hardware_id\":\"test-hw-30.7\",\"expiry\":\"2099-12-31\"}",
	"sig" => str_repeat("ab", 64)
)));
$e = layer7_entitlements();
need(empty($e["has_mitm"]), "bad sig must not unlock");
need($e["verified"] === false, "verified false on bad sig");

/* P2-11: .lic assinado com HW errado → GUI locked. */
l7_sign_lic($priv, "base,identity,mitm", $lic, "other-appliance-hw");
$e = layer7_entitlements();
need(empty($e["has_mitm"]), "wrong hw must not unlock mitm");
need(empty($e["has_identity"]), "wrong hw must not unlock identity");
need($e["verified"] === true, "sig still verified on hw mismatch");
need($e["source"] === "lic_hw_mismatch", "source lic_hw_mismatch");
need(empty($e["license_valid"]), "wrong hw license_valid false");

/* P2-11: expiry além da graça (20d) → locked. */
l7_sign_lic($priv, "base,identity,mitm", $lic, "test-hw-30.7", $exp_dead);
$e = layer7_entitlements();
need(empty($e["has_mitm"]), "expired beyond grace must not unlock mitm");
need(empty($e["has_identity"]), "expired beyond grace must not unlock identity");
need($e["source"] === "lic_expired", "source lic_expired");
need($e["verified"] === true, "sig still verified when expired");

/* P2-11: dentro da graça (5d) → unlock. */
l7_sign_lic($priv, "base,identity,mitm", $lic, "test-hw-30.7", $exp_grace);
$e = layer7_entitlements();
need(!empty($e["has_mitm"]), "grace must unlock mitm");
need(!empty($e["has_identity"]), "grace must unlock identity");
need($e["source"] === "lic_verified", "source lic_verified in grace");
need(!empty($e["license_valid"]), "grace license_valid true");

/* .lic assinado válido (HW+expiry) com mitm+identity → unlock. */
l7_sign_lic($priv, "base,identity,mitm", $lic, "test-hw-30.7", $exp_ok);
$e = layer7_entitlements();
need(!empty($e["has_mitm"]), "signed lic unlocks mitm");
need(!empty($e["has_identity"]), "signed lic unlocks identity");
need($e["source"] === "lic_verified", "source lic_verified");
need($e["verified"] === true, "verified true");
need(!empty($e["license_valid"]), "binding sets license_valid");
need(layer7_has_entitlement("mitm"), "has mitm");

/* T1: full não concede mitm. */
l7_sign_lic($priv, "full", $lic, "test-hw-30.7", $exp_ok);
$e = layer7_entitlements();
need(empty($e["has_mitm"]), "full must not unlock mitm");
need(empty($e["has_identity"]), "full must not unlock identity");

/* Check-in intersect: retira mitm. */
l7_sign_lic($priv, "base,identity,mitm", $lic, "test-hw-30.7", $exp_ok);
file_put_contents($testdir . "/var/db/layer7-checkin.json", json_encode(array(
	"features_set" => true,
	"features" => "base,identity"
)));
$e = layer7_entitlements();
need(!empty($e["has_identity"]), "checkin keeps identity");
need(empty($e["has_mitm"]), "checkin removes mitm");
need($e["source"] === "lic_verified_intersect_checkin", "intersect source");

/* Check-in sozinho NÃO acrescenta (sem .lic). */
@unlink($lic);
file_put_contents($testdir . "/var/db/layer7-checkin.json", json_encode(array(
	"features_set" => true,
	"features" => "base,identity,mitm"
)));
$e = layer7_entitlements();
need(empty($e["has_mitm"]), "checkin alone must not unlock");
need($e["source"] === "none", "source none without lic");

/* GA2.9 + P2-11: libexec layer7-mitm-entitle-ok */
$entitle = $root . "/package/pfSense-pkg-layer7/files/usr/local/libexec/layer7-mitm-entitle-ok";
need(is_readable($entitle), "entitle-ok script present");
l7_sign_lic($priv, "base,mitm", $lic, "test-hw-30.7", $exp_ok);
putenv("LAYER7_LIC_PATH=" . $lic);
$cmd = "/bin/sh " . escapeshellarg($entitle);
exec($cmd . " 2>/dev/null", $eo, $erc);
need($erc === 0, "entitle-ok PASS with mitm signed+bound");

l7_sign_lic($priv, "base,identity", $lic, "test-hw-30.7", $exp_ok);
exec($cmd . " 2>/dev/null", $eo2, $erc2);
need($erc2 !== 0, "entitle-ok FAIL without mitm token");

l7_sign_lic($priv, "base,mitm", $lic, "other-appliance-hw", $exp_ok);
exec($cmd . " 2>/dev/null", $eo_hw, $erc_hw);
need($erc_hw !== 0, "entitle-ok FAIL with wrong hardware");

l7_sign_lic($priv, "base,mitm", $lic, "test-hw-30.7", $exp_dead);
exec($cmd . " 2>/dev/null", $eo_exp, $erc_exp);
need($erc_exp !== 0, "entitle-ok FAIL beyond grace");

l7_sign_lic($priv, "base,mitm", $lic, "test-hw-30.7", $exp_grace);
exec($cmd . " 2>/dev/null", $eo_gr, $erc_gr);
need($erc_gr === 0, "entitle-ok PASS within grace");

/* 1.9.60: rc.d PATH curto nao inclui /usr/local/bin — php absoluto. */
l7_sign_lic($priv, "base,mitm", $lic, "test-hw-30.7", $exp_ok);
$cmd_rcpath = "env -i PATH=/sbin:/bin:/usr/sbin:/usr/bin" .
    " LAYER7_LIC_PATH=" . escapeshellarg($lic) .
    " LAYER7_LICENSE_PUBKEY=" . escapeshellarg($pub) .
    " LAYER7_TEST_ROOT=" . escapeshellarg($testdir) .
    " LAYER7_TEST_HW_ID=test-hw-30.7" .
    " LAYER7_TEST_NOW=" . escapeshellarg((string)$now) .
    " LAYER7_INC=" . escapeshellarg($inc) .
    " OPENSSL_BIN=" . escapeshellarg($openssl) .
    " /bin/sh " . escapeshellarg($entitle);
exec($cmd_rcpath . " 2>/dev/null", $eo3, $erc3);
need($erc3 === 0, "entitle-ok PASS under rc.d PATH with mitm");
l7_sign_lic($priv, "base,identity", $lic, "test-hw-30.7", $exp_ok);
exec($cmd_rcpath . " 2>/dev/null", $eo4, $erc4);
need($erc4 !== 0, "entitle-ok FAIL under rc.d PATH without mitm");
l7_sign_lic($priv, "base,mitm", $lic, "other-appliance-hw", $exp_ok);
exec($cmd_rcpath . " 2>/dev/null", $eo5, $erc5);
need($erc5 !== 0, "entitle-ok FAIL under rc.d PATH with wrong hw");

/* Sync helper em TEST_ROOT ainda aceita entitled forçado (GA2.10 / R-I). */
$cfg = layer7_bare_config();
$r = layer7_mitm_sync_helper($cfg, true);
need($r === false, "sync false without effective prerequisites");

/* BG-161: toggle JSON ON sem token nao activa; ganhar token nao ressuscita. */
l7_sign_lic($priv, "base", $lic, "test-hw-30.7", $exp_ok);
$armed = layer7_bare_config();
$armed["layer7"]["identity"]["enabled"] = true;
$armed["layer7"]["mitm"]["enabled"] = true;
$mitm_armed = layer7_mitm_from_config($armed);
need(layer7_mitm_effective($mitm_armed, false) === false,
    "mitm JSON ON sem entitlement = effective false");
need(layer7_has_entitlement("identity") === false,
    "base lic has no identity");
$before_base = array("has_identity" => false, "has_mitm" => false);
$after_id = array("has_identity" => true, "has_mitm" => false);
$tr = layer7_addon_apply_license_transition($armed, $before_base, $after_id);
need($tr["changed"] === true, "gaining identity disarms leftover toggles");
$tr_id = layer7_identity_from_config($tr["data"]);
$tr_mitm = layer7_mitm_from_config($tr["data"]);
need(empty($tr_id["enabled"]), "upgrade identity keeps toggle OFF");
need(empty($tr_mitm["enabled"]), "upgrade without mitm token disarms mitm");

$keep_src = layer7_bare_config();
$keep_src["layer7"]["identity"]["enabled"] = true;
$keep = layer7_addon_apply_license_transition($keep_src,
    array("has_identity" => true, "has_mitm" => false),
    array("has_identity" => true, "has_mitm" => false));
$keep_id = layer7_identity_from_config($keep["data"]);
need(!empty($keep_id["enabled"]), "renewal with identity keeps operator ON");

$lost = layer7_addon_apply_license_transition($keep_src,
    array("has_identity" => true, "has_mitm" => false),
    array("has_identity" => false, "has_mitm" => false));
$lost_id = layer7_identity_from_config($lost["data"]);
need(empty($lost_id["enabled"]), "losing identity persists toggle OFF");

/* BG-165 / PKG-1: .lic valido sem stats do daemon → badge valida. */
@unlink(layer7_stats_path());
l7_sign_lic($priv, "base,identity", $lic, "test-hw-30.7", $exp_ok);
$st = layer7_read_license_status();
need(!empty($st["valid"]), "PKG-1 status valid without daemon stats");
need(empty($st["error"]), "PKG-1 status error empty when .lic binds");
need(($st["customer"] ?? "") === "GA2.8", "PKG-1 customer from verified .lic");

@unlink($lic);
$st_none = layer7_read_license_status();
need(empty($st_none["valid"]), "PKG-1 valid false without .lic");
need(($st_none["error"] ?? "") === "no license file", "PKG-1 error no license file");

fwrite(STDOUT, "PASS test_entitlements_gui.php\n");
l7_ent_cleanup($testdir);
exit(0);
