<?php
/*
 * test_content_subscription_client.php — 30.10 / BG-117 / GA4.5–GA4.8 (GUI)
 *
 * Valida layer7_content_subscription_status():
 *  - token válido → ok
 *  - ausente / sig má / expirado / hw mismatch → não ok
 *  - nunca desbloqueia Identity/MITM (C12)
 *
 * Uso: php tests/functional/test_content_subscription_client.php
 */
$root = dirname(__DIR__, 2);
$testdir = sys_get_temp_dir() . "/layer7-cs-" . getmypid();
@mkdir($testdir . "/usr/local/share/pfSense-pkg-layer7", 0755, true);
@mkdir($testdir . "/var/db/layer7", 0755, true);
@mkdir($testdir . "/usr/local/etc", 0755, true);
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

function cleanup($testdir)
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

$openssl = is_executable("/usr/bin/openssl") ? "/usr/bin/openssl" : "openssl";
$priv = $testdir . "/test-priv.pem";
$pub = $testdir . "/usr/local/share/pfSense-pkg-layer7/license-signing-public-key.pem";
exec(escapeshellarg($openssl) . " genpkey -algorithm ED25519 -out " .
    escapeshellarg($priv) . " 2>/dev/null", $o, $rc);
need($rc === 0 && is_readable($priv), "openssl genpkey ED25519");
exec(escapeshellarg($openssl) . " pkey -in " . escapeshellarg($priv) .
    " -pubout -out " . escapeshellarg($pub) . " 2>/dev/null", $o2, $rc2);
need($rc2 === 0 && is_readable($pub), "openssl pkey -pubout");
putenv("LAYER7_LICENSE_PUBKEY=" . $pub);

$hw = "aabbccddeeff00112233445566778899aabbccddeeff00112233445566778899";
putenv("LAYER7_TEST_HW_ID=" . $hw);

function sign_content_token($priv, $path, $payload)
{
	$data = json_encode($payload, JSON_UNESCAPED_SLASHES);
	need(is_string($data) && $data !== "", "json encode payload");
	$td = dirname($path) . "/.sigtmp." . getmypid();
	@mkdir($td, 0700, true);
	$data_f = $td . "/data";
	$sig_f = $td . "/sig";
	file_put_contents($data_f, $data);
	$openssl = is_executable("/usr/bin/openssl") ? "/usr/bin/openssl" : "openssl";
	exec(escapeshellarg($openssl) . " pkeyutl -sign -inkey " .
	    escapeshellarg($priv) . " -rawin -in " . escapeshellarg($data_f) .
	    " -out " . escapeshellarg($sig_f) . " 2>/dev/null", $out, $rc);
	need($rc === 0 && is_readable($sig_f), "openssl pkeyutl -sign");
	$sig = bin2hex(file_get_contents($sig_f));
	need(strlen($sig) === 128, "sig hex 64 bytes");
	file_put_contents($path, json_encode(array("data" => $data, "sig" => $sig)));
	@chmod($path, 0600);
	@unlink($data_f);
	@unlink($sig_f);
	@rmdir($td);
}

$cs_path = layer7_content_subscription_path();
$now = time();

/* GA4.5 / missing */
@unlink($cs_path);
$st = layer7_content_subscription_status($now, $hw);
need($st["ok"] === false, "missing not ok");
need($st["status"] === "missing", "status missing");

/* válido */
sign_content_token($priv, $cs_path, array(
	"v" => 1,
	"hardware_id" => $hw,
	"license_id" => 42,
	"scope" => "content",
	"iat" => $now - 60,
	"exp" => $now + 86400,
	"jti" => "test-jti-ok"
));
$st = layer7_content_subscription_status($now, $hw);
need($st["ok"] === true, "valid token ok");
need($st["status"] === "ok", "status ok");
need($st["exp"] === $now + 86400, "exp preserved");

/* GA4.8: dentro da janela com skew — iat no futuro curto */
sign_content_token($priv, $cs_path, array(
	"v" => 1,
	"hardware_id" => $hw,
	"license_id" => 42,
	"scope" => "content",
	"iat" => $now + 3600,
	"exp" => $now + 86400,
	"jti" => "test-jti-skew"
));
$st = layer7_content_subscription_status($now, $hw);
need($st["ok"] === true, "skew +1h still ok");

/* expirado para além do skew */
sign_content_token($priv, $cs_path, array(
	"v" => 1,
	"hardware_id" => $hw,
	"license_id" => 42,
	"scope" => "content",
	"iat" => $now - 40 * 86400,
	"exp" => $now - 2 * 86400,
	"jti" => "test-jti-exp"
));
$st = layer7_content_subscription_status($now, $hw);
need($st["ok"] === false, "expired not ok");
need($st["status"] === "expired", "status expired");

/* hw mismatch */
sign_content_token($priv, $cs_path, array(
	"v" => 1,
	"hardware_id" => str_repeat("ab", 32),
	"license_id" => 42,
	"scope" => "content",
	"iat" => $now - 60,
	"exp" => $now + 86400,
	"jti" => "test-jti-hw"
));
$st = layer7_content_subscription_status($now, $hw);
need($st["ok"] === false, "hw mismatch not ok");
need($st["status"] === "hw_mismatch", "status hw_mismatch");

/* sig inválida */
file_put_contents($cs_path, json_encode(array(
	"data" => json_encode(array(
		"v" => 1,
		"hardware_id" => $hw,
		"license_id" => 1,
		"scope" => "content",
		"iat" => $now,
		"exp" => $now + 1000,
		"jti" => "x"
	)),
	"sig" => str_repeat("ab", 64)
)));
$st = layer7_content_subscription_status($now, $hw);
need($st["ok"] === false, "bad sig not ok");
need($st["status"] === "invalid", "status invalid on bad sig");

/* C12: token de conteúdo não unlock MITM */
$e = layer7_entitlements();
need(empty($e["has_mitm"]), "content token must not unlock mitm");
need(empty($e["has_identity"]), "content token must not unlock identity");

cleanup($testdir);
fwrite(STDOUT, "PASS test_content_subscription_client.php\n");
exit(0);
