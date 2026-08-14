<?php
/*
 * test_fingerprint_gui_daemon.php — BG-128 M1
 *
 * Prova o caminho de produção da GUI/helper:
 *  - fingerprint vem de layer7d --fingerprint (stub sob TEST_ROOT)
 *  - saída inválida / rc≠0 / binário ausente fecha
 *  - LAYER7_TEST_HW_ID só com LAYER7_TEST_ROOT (sem bypass de produção)
 *  - binding sucesso/falha continua correcto
 *
 * macOS / host local NÃO prova equivalência FreeBSD (getifaddrs/IFT_ETHER).
 * Campo: no appliance, `layer7d --fingerprint` tem de bater com o HW da GUI.
 *
 * Uso: php tests/functional/test_fingerprint_gui_daemon.php
 */
$root = dirname(__DIR__, 2);
$inc = $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";
$testdir = sys_get_temp_dir() . "/layer7-fp-m1-" . getmypid();
@mkdir($testdir . "/usr/local/sbin", 0755, true);
@mkdir($testdir . "/usr/local/etc", 0755, true);
@mkdir($testdir . "/usr/local/share/pfSense-pkg-layer7", 0755, true);
@mkdir($testdir . "/var/db/layer7", 0755, true);
putenv("LAYER7_TEST_ROOT=" . $testdir);
putenv("LAYER7_TEST_HW_ID");
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

function l7_fp_cleanup($testdir)
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

function l7_write_stub($path, $body)
{
	$ok = file_put_contents($path, $body);
	need($ok !== false, "write stub " . $path);
	need(chmod($path, 0755), "chmod stub");
}

$src = file_get_contents($inc);
need($src !== false, "read layer7.inc");
need(strpos($src, "function layer7_hw_fingerprint_local(") === false,
    "PHP fingerprint reimplementation must be gone");
need(strpos($src, "function layer7_hw_hostuuid_raw(") === false,
    "sysctl hostuuid helper must be gone");
need(strpos($src, "function layer7_hw_first_ether_mac(") === false,
    "ifconfig ether helper must be gone");
need(strpos($src, "--fingerprint") !== false,
    "production path must call layer7d --fingerprint");
need(strpos($src, "/usr/local/sbin/layer7d") !== false,
    "packaged binary path must be /usr/local/sbin/layer7d");
need(preg_match_all('/getenv\\s*\\(\\s*["\']LAYER7_TEST_HW_ID["\']/',
    $src) === 1,
    "LAYER7_TEST_HW_ID may be read only in layer7_local_hardware_id");
need(strpos($src, 'function layer7_local_hardware_id()') !== false &&
    preg_match('/function layer7_local_hardware_id\\(\\)\\s*\\{[^}]*LAYER7_TEST_ROOT[^}]*LAYER7_TEST_HW_ID/s',
	$src) === 1,
    "LAYER7_TEST_HW_ID must be gated on LAYER7_TEST_ROOT");

$bin = $testdir . "/usr/local/sbin/layer7d";
$marker = $testdir . "/fingerprint.called";
$hw_ok = "aabbccddeeff00112233445566778899aabbccddeeff00112233445566778899";
$hw_other = "ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff";

/* 1) Caminho de produção chama o binário e aceita 64 hex. */
l7_write_stub($bin, "#!/bin/sh\n" .
    "echo called >> " . escapeshellarg($marker) . "\n" .
    "if [ \"\$1\" = \"--fingerprint\" ]; then echo " . $hw_ok .
    "; exit 0; fi\nexit 1\n");
@unlink($marker);
putenv("LAYER7_TEST_HW_ID");
$got = layer7_local_hardware_id();
need($got === $hw_ok, "valid stub fingerprint accepted");
need(is_file($marker), "production path must exec the binary");
need(layer7_layer7d_bin() === $bin, "TEST_ROOT stub is the bin");

/* 2) Saída inválida fecha. */
$invalids = array(
	"not-a-fingerprint\n",
	"AABBCCDDEEFF00112233445566778899aabbccddeeff0011223344556677889\n",
	$hw_ok . "extra\n",
	"\n",
	$hw_ok . " " . $hw_ok . "\n"
);
foreach ($invalids as $i => $line) {
	l7_write_stub($bin, "#!/bin/sh\n" .
	    "if [ \"\$1\" = \"--fingerprint\" ]; then printf %s " .
	    escapeshellarg($line) . "; exit 0; fi\nexit 1\n");
	$got = layer7_local_hardware_id();
	need($got === "", "invalid stdout #$i must fail-closed");
}

/* 3) rc≠0 fecha mesmo com hex na stdout. */
l7_write_stub($bin, "#!/bin/sh\necho " . $hw_ok . "\nexit 1\n");
need(layer7_local_hardware_id() === "", "nonzero exit must fail-closed");

/* 4) Binário ausente fecha. */
@unlink($bin);
need(layer7_layer7d_bin() === "", "missing stub → no host fallback");
need(layer7_local_hardware_id() === "", "missing binary must fail-closed");

/* 5) Override de teste só com TEST_ROOT; não chama o binário. */
l7_write_stub($bin, "#!/bin/sh\n" .
    "echo called >> " . escapeshellarg($marker) . "\n" .
    "echo " . $hw_ok . "\nexit 0\n");
@unlink($marker);
putenv("LAYER7_TEST_HW_ID=test-hw-m1-override");
need(layer7_local_hardware_id() === "test-hw-m1-override",
    "TEST_HW_ID with TEST_ROOT is test injection");
need(!is_file($marker), "test override must not exec the binary");

/* 6) Sem TEST_ROOT, LAYER7_TEST_HW_ID não é bypass de produção. */
$php = PHP_BINARY;
$probe = 'require $argv[1]; ' .
    'echo layer7_local_hardware_id();';
$cmd = "env -i PATH=" . escapeshellarg(getenv("PATH")) .
    " LAYER7_TEST_HW_ID=evil-bypass-hw-id " .
    escapeshellarg($php) . " -r " . escapeshellarg($probe) .
    " " . escapeshellarg($inc);
exec($cmd . " 2>/dev/null", $probe_out, $probe_rc);
$probe_hw = isset($probe_out[0]) ? $probe_out[0] : "";
need($probe_hw !== "evil-bypass-hw-id",
    "LAYER7_TEST_HW_ID without TEST_ROOT must not win");

/* 7) Binding sucesso/falha com fingerprint do binário. */
putenv("LAYER7_TEST_HW_ID");
l7_write_stub($bin, "#!/bin/sh\n" .
    "if [ \"\$1\" = \"--fingerprint\" ]; then echo " . $hw_ok .
    "; exit 0; fi\nexit 1\n");
$verified_ok = array(
	"hardware_id" => $hw_ok,
	"expiry" => "2099-12-31",
	"features" => "base,mitm",
	"verified" => true
);
$bind = layer7_license_binding_ok($verified_ok, null, 1786708800);
need(!empty($bind["ok"]) && $bind["reason"] === "valid",
    "binding ok when stub HW matches");

$verified_bad = $verified_ok;
$verified_bad["hardware_id"] = $hw_other;
$bind_bad = layer7_license_binding_ok($verified_bad, null, 1786708800);
need(empty($bind_bad["ok"]) && $bind_bad["reason"] === "hw_mismatch",
    "binding fails on HW mismatch");

@unlink($bin);
$bind_miss = layer7_license_binding_ok($verified_ok, null, 1786708800);
need(empty($bind_miss["ok"]) && $bind_miss["reason"] === "hw_unavailable",
    "binding fails closed without fingerprint");

/* 8) Helper entitle-ok usa a mesma fonte (stub HW + .lic bound). */
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
need($rc === 0 && is_readable($priv), "openssl genpkey");
exec(escapeshellarg($openssl) . " pkey -in " . escapeshellarg($priv) .
    " -pubout -out " . escapeshellarg($pub) . " 2>/dev/null", $o2, $rc2);
need($rc2 === 0 && is_readable($pub), "openssl pkey -pubout");
putenv("LAYER7_LICENSE_PUBKEY=" . $pub);
putenv("OPENSSL_BIN=" . $openssl);

function l7_fp_sign_lic($priv, $features, $path, $hw, $expiry)
{
	$data = json_encode(array(
		"hardware_id" => $hw,
		"customer" => "M1",
		"expiry" => $expiry,
		"features" => $features
	), JSON_UNESCAPED_SLASHES);
	$td = dirname($path) . "/.sigtmp." . getmypid();
	@mkdir($td, 0700, true);
	file_put_contents($td . "/data", $data);
	$openssl = getenv("OPENSSL_BIN");
	exec(escapeshellarg($openssl) . " pkeyutl -sign -inkey " .
	    escapeshellarg($priv) . " -rawin -in " . escapeshellarg($td . "/data") .
	    " -out " . escapeshellarg($td . "/sig") . " 2>/dev/null", $out, $rc);
	need($rc === 0, "sign lic");
	$sig = bin2hex(file_get_contents($td . "/sig"));
	file_put_contents($path, json_encode(array("data" => $data, "sig" => $sig)));
	@unlink($td . "/data");
	@unlink($td . "/sig");
	@rmdir($td);
}

l7_write_stub($bin, "#!/bin/sh\n" .
    "if [ \"\$1\" = \"--fingerprint\" ]; then echo " . $hw_ok .
    "; exit 0; fi\nexit 1\n");
$lic = layer7_lic_path();
putenv("LAYER7_LIC_PATH=" . $lic);
l7_fp_sign_lic($priv, "base,mitm", $lic, $hw_ok, "2099-12-31");
$e = layer7_entitlements();
need(!empty($e["has_mitm"]) && !empty($e["license_valid"]),
    "entitlements unlock when stub HW matches signed lic");

l7_fp_sign_lic($priv, "base,mitm", $lic, $hw_other, "2099-12-31");
$e2 = layer7_entitlements();
need(empty($e2["has_mitm"]) && $e2["source"] === "lic_hw_mismatch",
    "entitlements stay locked on stub HW mismatch");

$entitle = $root . "/package/pfSense-pkg-layer7/files/usr/local/libexec/layer7-mitm-entitle-ok";
need(is_readable($entitle), "entitle-ok present");
l7_fp_sign_lic($priv, "base,mitm", $lic, $hw_ok, "2099-12-31");
$cmd_ok = "env -i PATH=/sbin:/bin:/usr/sbin:/usr/bin" .
    " LAYER7_LIC_PATH=" . escapeshellarg($lic) .
    " LAYER7_LICENSE_PUBKEY=" . escapeshellarg($pub) .
    " LAYER7_TEST_ROOT=" . escapeshellarg($testdir) .
    " LAYER7_INC=" . escapeshellarg($inc) .
    " OPENSSL_BIN=" . escapeshellarg($openssl) .
    " /bin/sh " . escapeshellarg($entitle);
exec($cmd_ok . " 2>/dev/null", $eo, $erc);
need($erc === 0, "entitle-ok PASS via stub --fingerprint");

l7_fp_sign_lic($priv, "base,mitm", $lic, $hw_other, "2099-12-31");
exec($cmd_ok . " 2>/dev/null", $eo2, $erc2);
need($erc2 !== 0, "entitle-ok FAIL via stub HW mismatch");

fwrite(STDOUT, "PASS test_fingerprint_gui_daemon.php\n");
l7_fp_cleanup($testdir);
exit(0);
