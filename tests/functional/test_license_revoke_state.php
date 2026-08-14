<?php
/**
 * test_license_revoke_state.php — P1-6 / BG-128: revoke local limpa /var/db.
 *
 *   php tests/functional/test_license_revoke_state.php
 */
$root = dirname(__DIR__, 2);
$inc = $root . '/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc';

$testdir = sys_get_temp_dir() . '/l7-revoke-' . bin2hex(random_bytes(4));
putenv('LAYER7_TEST_ROOT=' . $testdir);
$_ENV['LAYER7_TEST_ROOT'] = $testdir;

require_once $inc;

$fail = 0;
function check($cond, $name) {
	global $fail;
	if ($cond) {
		echo "PASS: $name\n";
	} else {
		echo "FAIL: $name\n";
		$fail = 1;
	}
}

@mkdir($testdir . '/usr/local/etc', 0777, true);
@mkdir($testdir . '/var/db/layer7', 0777, true);

$lic = layer7_lic_path();
$ci = layer7_checkin_state_path();
$clock = layer7_clock_mark_path();
$sub = layer7_content_subscription_path();

file_put_contents($lic, '{"data":"x","sig":"y"}');
file_put_contents($ci, '{"license_key":"ABC123"}');
file_put_contents($clock, '{"max_seen":1}');
file_put_contents($sub, '{"status":"ok"}');

check(file_exists($lic) && file_exists($ci) && file_exists($clock) &&
    file_exists($sub), 'fixture criada');

layer7_clear_local_license_state();

check(!file_exists($lic), 'revoke apaga .lic');
check(!file_exists($ci), 'revoke apaga layer7-checkin.json');
check(!file_exists($clock), 'revoke apaga clock-mark.json');
check(!file_exists($sub), 'revoke apaga content-subscription.json');

@unlink($lic);
@unlink($ci);
@unlink($clock);
@unlink($sub);
@rmdir($testdir . '/usr/local/etc');
@rmdir($testdir . '/usr/local');
@rmdir($testdir . '/var/db/layer7');
@rmdir($testdir . '/var/db');
@rmdir($testdir . '/var');
@rmdir($testdir . '/usr');
@rmdir($testdir);

if ($fail) {
	echo "RESULT: FAIL\n";
	exit(1);
}
echo "RESULT: PASS\n";
exit(0);
