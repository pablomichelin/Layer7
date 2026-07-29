<?php
/*
 * Regressao: a GUI guarda IDs amigaveis (lan/optN), mas libpcap/PF exigem
 * nomes reais. O teste usa um mapa minimo equivalente ao pfSense.
 */
function get_real_interface($ifname)
{
	$map = array(
		"lan" => "vmx0",
		"opt1" => "pppoe1",
		"opt2" => "vmx0.10"
	);
	return $map[$ifname] ?? null;
}

$root = dirname(__DIR__, 2);
require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

$data = array(
	"layer7" => array(
		"interfaces" => array("lan", "vmx0", "opt2", "bad iface"),
		"block_quic_interfaces" => array("opt2"),
		"reports" => array("event_interfaces" => array("opt1")),
		"policies" => array(
			array("interfaces" => array("lan", "opt2"))
		),
		"exceptions" => array(
			array("interfaces" => array("opt1"))
		)
	)
);

$got = layer7_config_interfaces_normalize($data);
$l7 = $got["layer7"];

if ($l7["interfaces"] !== array("vmx0", "vmx0.10") ||
    $l7["block_quic_interfaces"] !== array("vmx0.10") ||
    $l7["reports"]["event_interfaces"] !== array("pppoe1") ||
    $l7["policies"][0]["interfaces"] !== array("vmx0", "vmx0.10") ||
    $l7["exceptions"][0]["interfaces"] !== array("pppoe1")) {
	fwrite(STDERR, "FAIL: normalizacao de interfaces divergiu\n");
	fwrite(STDERR, json_encode($got, JSON_PRETTY_PRINT) . "\n");
	exit(1);
}

echo "PASS: test_interface_normalization\n";
exit(0);
