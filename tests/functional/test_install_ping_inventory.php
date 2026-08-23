<?php
/**
 * test_install_ping_inventory.php — BG-162
 *
 *   php tests/functional/test_install_ping_inventory.php
 */
$root = dirname(__DIR__, 2);
$inc = $root . '/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7-install-ping.inc';
require_once $inc;

$tmpdir = sys_get_temp_dir() . '/l7-install-ping-' . getmypid();
@mkdir($tmpdir . '/var/db/layer7', 0750, true);
if (!defined('LAYER7_TEST_ROOT')) {
	define('LAYER7_TEST_ROOT', $tmpdir);
}

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

check(layer7_install_ping_classify_event(array(), '1.9.71') === 'install',
	'evento install na primeira vez');
check(layer7_install_ping_classify_event(array('last_version' => '1.9.69'), '1.9.71') === 'upgrade',
	'evento upgrade quando a versao muda');
check(layer7_install_ping_classify_event(array('last_version' => '1.9.71'), '1.9.71') === 'heartbeat',
	'evento heartbeat na mesma versao');

$cfg = array(
	'system' => array(
		'hostname' => 'fw-matriz',
		'domain' => 'empresa.local',
	),
	'interfaces' => array(
		'wan' => array(
			'if' => 'igb0',
			'descr' => 'WAN Vivo',
			'ipaddr' => '200.1.2.3',
			'gateway' => 'WAN_DHCP',
		),
		'lan' => array(
			'if' => 'igb1',
			'descr' => 'Escritorio',
			'ipaddr' => '192.168.10.1',
		),
		'lo0' => array(
			'if' => 'lo0',
			'ipaddr' => '127.0.0.1',
		),
	),
	'gateways' => array(
		'defaultgw4' => 'WAN_DHCP',
		'gateway_item' => array(
			array('name' => 'WAN_DHCP', 'gateway' => '200.1.2.1'),
		),
	),
);

$payload = layer7_install_inventory($cfg, array(
	'hardware_id' => str_repeat('a', 64),
	'install_id' => str_repeat('b', 32),
	'package_version' => '1.9.71',
	'event' => 'install',
	'pfsense_version' => '2.8.0-RELEASE',
	'platform' => 'pfSense',
	'uniqueid' => 'uid-test',
	'system_serial' => '',
	'os_release' => '15.0-RELEASE',
	'hw_model' => 'Protectli',
	'ncpu' => 4,
	'mem_mb' => 8192,
));

check(($payload['fqdn'] ?? '') === 'fw-matriz.empresa.local', 'FQDN de hostname+domain');
check(($payload['wan_ipv4'] ?? '') === '200.1.2.3', 'WAN IPv4');
check(($payload['gateway_v4'] ?? '') === '200.1.2.1', 'gateway IPv4');
$lan = null;
foreach ($payload['interfaces'] as $iface) {
	if (($iface['id'] ?? '') === 'lan') {
		$lan = $iface;
	}
}
check(is_array($lan) && ($lan['ipv4'] ?? '') === '192.168.10.1', 'LAN IPv4');
check(is_array($lan) && ($lan['descr'] ?? '') === 'Escritorio', 'descricao LAN');
check(($payload['hardware_id'] ?? '') === str_repeat('a', 64), 'hardware_id injectado');
check(empty($payload['system_serial']), 'serial vazio omitido');
check(!isset($payload['license_key']), 'sem license_key se nao existir');

$lo = null;
foreach ($payload['interfaces'] as $iface) {
	if (($iface['id'] ?? '') === 'lo0') {
		$lo = $iface;
	}
}
check(is_array($lo) && empty($lo['ipv4']), 'loopback 127.0.0.1 nao enviado como IPv4 util');

check(layer7_install_ping_url() === 'https://license.systemup.inf.br/api/license/install-ping',
	'URL canonica do license server');

function rrmdir($dir) {
	if (!is_dir($dir)) {
		return;
	}
	$items = scandir($dir);
	foreach ($items as $item) {
		if ($item === '.' || $item === '..') {
			continue;
		}
		$path = $dir . '/' . $item;
		if (is_dir($path)) {
			rrmdir($path);
		} else {
			@unlink($path);
		}
	}
	@rmdir($dir);
}
rrmdir($tmpdir);

exit($fail);
