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

check(layer7_install_ping_should_send(array(), 1000) === true,
	'primeira vez envia');
check(layer7_install_ping_should_send(array(
	'last_ok' => 1000,
	'last_http' => 200,
	'last_attempt' => 1000,
), 2000) === false, 'sucesso recente nao reenvia');
check(layer7_install_ping_should_send(array(
	'last_ok' => 1000,
	'last_http' => 200,
	'last_attempt' => 1000,
), 1000 + 86401) === true, 'sucesso com mais de 24h reenvia');
check(layer7_install_ping_should_send(array(
	'last_attempt' => 1000,
	'last_http' => 0,
), 1100) === false, 'falha recente nao martela');
check(layer7_install_ping_should_send(array(
	'last_attempt' => 1000,
	'last_http' => 0,
), 1000 + 901) === true, 'falha com 15 min reenvia');

@mkdir($tmpdir . '/cf/conf', 0750, true);
@mkdir($tmpdir . '/var/db', 0750, true);
file_put_contents($tmpdir . '/var/db/uniqueid', "uid-xml-test\n");
file_put_contents($tmpdir . '/cf/conf/config.xml', <<<'XML'
<?xml version="1.0"?>
<pfsense>
  <system>
    <hostname>fw-xml</hostname>
    <domain>lab.local</domain>
  </system>
  <interfaces>
    <wan>
      <if>igb0</if>
      <descr>WAN XML</descr>
      <ipaddr>198.51.100.10</ipaddr>
      <gateway>WANGW</gateway>
    </wan>
    <lan>
      <if>igb1</if>
      <descr>LAN XML</descr>
      <ipaddr>10.0.0.1</ipaddr>
    </lan>
  </interfaces>
  <gateways>
    <defaultgw4>WANGW</defaultgw4>
    <gateway_item>
      <name>WANGW</name>
      <gateway>198.51.100.1</gateway>
    </gateway_item>
  </gateways>
</pfsense>
XML
);
$fromXml = layer7_install_ping_load_pfsense_config();
check(($fromXml['system']['hostname'] ?? '') === 'fw-xml', 'XML hostname');
check(($fromXml['interfaces']['wan']['ipaddr'] ?? '') === '198.51.100.10', 'XML WAN ipaddr');
$xmlPayload = layer7_install_inventory(null, array(
	'hardware_id' => str_repeat('c', 64),
	'install_id' => str_repeat('d', 32),
	'package_version' => '1.9.72',
	'event' => 'install',
	'os_release' => '',
	'hw_model' => '',
	'ncpu' => 0,
	'mem_mb' => 0,
	'system_serial' => '',
));
check(($xmlPayload['fqdn'] ?? '') === 'fw-xml.lab.local', 'inventario a partir do config.xml');
check(($xmlPayload['gateway_v4'] ?? '') === '198.51.100.1', 'gateway a partir do XML');
check(($xmlPayload['uniqueid'] ?? '') === 'uid-xml-test', 'uniqueid via LAYER7_TEST_ROOT');

$fallback = layer7_install_ping_hardware_id_fallback();
check((bool)preg_match('/^[a-f0-9]{64}$/', $fallback), 'fallback hardware_id 64 hex');
check(layer7_install_ping_curl_bin() !== '', 'curl bin resolvido');

$src = file_get_contents($inc);
check(strpos($src, 'return "1.9.72"') === false &&
    strpos($src, "return '1.9.72'") === false,
	'PKG-6 versao sem PORTVERSION embutido');
check(layer7_install_ping_package_version() !== '1.9.72' ||
    strpos($src, 'return "unknown"') !== false,
	'PKG-6 fallback unknown, nao 1.9.72');

$noHw = layer7_install_inventory(null, array(
	'package_version' => '1.9.73',
	'event' => 'heartbeat',
	'install_id' => str_repeat('e', 32),
	'os_release' => '',
	'hw_model' => '',
	'ncpu' => 0,
	'mem_mb' => 0,
	'system_serial' => '',
));
check(empty($noHw['hardware_id']), 'PKG-5 sem fingerprint nao inventa hardware_id');

layer7_install_ping_save_state(array(
	'last_hardware_id' => str_repeat('f', 64),
));
$reused = layer7_install_inventory(null, array(
	'package_version' => '1.9.73',
	'event' => 'heartbeat',
	'install_id' => str_repeat('e', 32),
	'os_release' => '',
	'hw_model' => '',
	'ncpu' => 0,
	'mem_mb' => 0,
	'system_serial' => '',
));
check(($reused['hardware_id'] ?? '') === str_repeat('f', 64),
	'PKG-5 reusa last_hardware_id do estado');

layer7_install_ping_save_state(array());
$skipped = layer7_install_ping_run(null, array(
	'force' => true,
	'package_version' => '1.9.73',
	'install_id' => str_repeat('e', 32),
));
check(!empty($skipped['skipped']) && (($skipped['reason'] ?? '') === 'hardware_id'),
	'PKG-5 run sem fingerprint nao envia');

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
