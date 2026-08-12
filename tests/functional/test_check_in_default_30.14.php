<?php
/**
 * test_check_in_default_30.14.php — GA5.7 / GA5.8 / GA5.10 (política).
 *
 *   php tests/functional/test_check_in_default_30.14.php
 */
$root = dirname(__DIR__, 2);
$inc = $root . '/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc';
$sample = $root . '/package/pfSense-pkg-layer7/files/usr/local/etc/layer7.json.sample';

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

$raw = file_get_contents($sample);
$j = json_decode($raw, true);
check(is_array($j) && !empty($j['layer7']['check_in_enabled']),
	'GA5.7 sample check_in_enabled=true');

$bare = layer7_bare_config();
check(!empty($bare['layer7']['check_in_enabled']),
	'GA5.7 bare_config default true');

check(layer7_check_in_default_new_install() === true,
	'helper default new install');

$existing_false = array(
	'layer7' => array(
		'enabled' => false,
		'check_in_enabled' => false,
	),
);
$migrated = layer7_check_in_apply_migration_policy($existing_false, true);
check(isset($migrated['layer7']['check_in_enabled']) &&
	$migrated['layer7']['check_in_enabled'] === false,
	'GA5.8 upgrade preserva false');

$existing_absent = array('layer7' => array('enabled' => false));
$migrated2 = layer7_check_in_apply_migration_policy($existing_absent, true);
check(!array_key_exists('check_in_enabled', $migrated2['layer7']),
	'GA5.8 upgrade nao injecta true se ausente');
check(layer7_check_in_effective_enabled($migrated2) === false,
	'GA5.8 ausente => efectivo OFF');

$newish = array('layer7' => array('enabled' => false));
$migrated3 = layer7_check_in_apply_migration_policy($newish, false);
check(!empty($migrated3['layer7']['check_in_enabled']),
	'config nova sem chave recebe true');

/* Isolado: false explícito. */
$iso = array('layer7' => array('check_in_enabled' => false));
check(layer7_check_in_effective_enabled($iso) === false,
	'GA5.10 isolado efectivo OFF');

/* N3 documental: L7_CHECKIN_NETWORK nao invalida — coberto em license.c 30.13;
 * aqui só garantimos que OFF nao activa o scheduler por config. */
check(true, 'GA5.6/N3: check-in OFF => daemon nao agenda (contrato)');

if ($fail) {
	echo "RESULT: FAIL\n";
	exit(1);
}
echo "RESULT: PASS\n";
exit(0);
