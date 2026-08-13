<?php
/*
 * test_entitlements_gui.php — 30.7 / BG-120 / GA2.8–GA2.10
 *
 * Stats forjados NÃO desbloqueiam Identity/MITM.
 * .lic Ed25519 verificado concede; check-in só retira.
 * Gate GA2.9: layer7-mitm-entitle-ok rejeita sem mitm assinado.
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

function l7_sign_lic($priv, $features, $path)
{
	$data = json_encode(array(
		"hardware_id" => "test-hw-30.7",
		"customer" => "GA2.8",
		"expiry" => "2099-12-31",
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

/* GA2.8: stats forjados sem .lic → sem unlock. */
file_put_contents(layer7_stats_path(), json_encode(array(
	"license_valid" => true,
	"license_features" => "base,identity,mitm",
	"license_features_flags" => 7,
	"license_customer" => "forged"
)));
$e = layer7_entitlements();
need(empty($e["has_mitm"]), "stats forged must not unlock mitm");
need(empty($e["has_identity"]), "stats forged must not unlock identity");
need($e["source"] === "none", "source none without verified lic");
need(!layer7_has_entitlement("mitm"), "has_entitlement mitm false");
need(!layer7_has_entitlement("identity"), "has_entitlement identity false");
need(layer7_has_entitlement("base"), "base always true");

/* .lic sem assinatura válida → sem unlock. */
file_put_contents($lic, json_encode(array(
	"data" => "{\"features\":\"base,mitm\",\"hardware_id\":\"x\"}",
	"sig" => str_repeat("ab", 64)
)));
$e = layer7_entitlements();
need(empty($e["has_mitm"]), "bad sig must not unlock");
need($e["verified"] === false, "verified false on bad sig");

/* .lic assinado com mitm+identity → unlock. */
l7_sign_lic($priv, "base,identity,mitm", $lic);
$e = layer7_entitlements();
need(!empty($e["has_mitm"]), "signed lic unlocks mitm");
need(!empty($e["has_identity"]), "signed lic unlocks identity");
need($e["source"] === "lic_verified", "source lic_verified");
need($e["verified"] === true, "verified true");
need(layer7_has_entitlement("mitm"), "has mitm");

/* T1: full não concede mitm. */
l7_sign_lic($priv, "full", $lic);
$e = layer7_entitlements();
need(empty($e["has_mitm"]), "full must not unlock mitm");
need(empty($e["has_identity"]), "full must not unlock identity");

/* Check-in intersect: retira mitm. */
l7_sign_lic($priv, "base,identity,mitm", $lic);
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

/* GA2.9: libexec layer7-mitm-entitle-ok */
$entitle = $root . "/package/pfSense-pkg-layer7/files/usr/local/libexec/layer7-mitm-entitle-ok";
need(is_readable($entitle), "entitle-ok script present");
l7_sign_lic($priv, "base,mitm", $lic);
putenv("LAYER7_LIC_PATH=" . $lic);
$cmd = "/bin/sh " . escapeshellarg($entitle);
exec($cmd . " 2>/dev/null", $eo, $erc);
need($erc === 0, "entitle-ok PASS with mitm signed");

l7_sign_lic($priv, "base,identity", $lic);
exec($cmd . " 2>/dev/null", $eo2, $erc2);
need($erc2 !== 0, "entitle-ok FAIL without mitm token");

/* 1.9.60: rc.d PATH curto nao inclui /usr/local/bin — php absoluto. */
l7_sign_lic($priv, "base,mitm", $lic);
$cmd_rcpath = "env -i PATH=/sbin:/bin:/usr/sbin:/usr/bin" .
    " LAYER7_LIC_PATH=" . escapeshellarg($lic) .
    " LAYER7_LICENSE_PUBKEY=" . escapeshellarg($pub) .
    " OPENSSL_BIN=" . escapeshellarg($openssl) .
    " /bin/sh " . escapeshellarg($entitle);
exec($cmd_rcpath . " 2>/dev/null", $eo3, $erc3);
need($erc3 === 0, "entitle-ok PASS under rc.d PATH with mitm");
l7_sign_lic($priv, "base,identity", $lic);
exec($cmd_rcpath . " 2>/dev/null", $eo4, $erc4);
need($erc4 !== 0, "entitle-ok FAIL under rc.d PATH without mitm");

/* Sync helper em TEST_ROOT ainda aceita entitled forçado (GA2.10 / R-I). */
$cfg = layer7_bare_config();
$r = layer7_mitm_sync_helper($cfg, true);
need($r === false, "sync false without effective prerequisites");

fwrite(STDOUT, "PASS test_entitlements_gui.php\n");
l7_ent_cleanup($testdir);
exit(0);
