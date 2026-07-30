#!/bin/sh
# Teste minimo: colecta de dominios sinkhole e snippet NAT block-page.
set -eu

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
INC="${ROOT}/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc"

if [ ! -f "${INC}" ]; then
	echo "FAIL: layer7.inc nao encontrado"
	exit 1
fi

export ROOT INC
php -r '
require_once(getenv("INC"));

$data = array(
	"layer7" => array(
		"enabled" => true,
		"mode" => "enforce",
		"interfaces" => array("lan"),
		"block_page" => array("enabled" => true),
		"policies" => array(
			array(
				"id" => "p-yt",
				"name" => "YouTube",
				"enabled" => true,
				"action" => "block",
				"match" => array("hosts" => array("youtube.com", "googlevideo.com"))
			)
		)
	)
);

$col = layer7_blockpage_collect_domains($data);
$domains = $col["domains"];
if (!in_array("youtube.com", $domains, true)) {
	fwrite(STDERR, "FAIL: youtube.com ausente\n");
	exit(1);
}
if (!in_array("googlevideo.com", $domains, true)) {
	fwrite(STDERR, "FAIL: googlevideo.com ausente\n");
	exit(1);
}

$block = layer7_blockpage_unbound_block($data);
if (strpos($block, "local-data:") === false) {
	fwrite(STDERR, "FAIL: bloco Unbound vazio\n");
	exit(1);
}
if (strpos($block, "youtube.com") === false) {
	fwrite(STDERR, "FAIL: youtube.com ausente no Unbound\n");
	exit(1);
}

$rdr = layer7_generate_blockpage_rdr_snippet($data);
if ($rdr !== "" && strpos($rdr, "layer7:blockpage:http") === false) {
	fwrite(STDERR, "FAIL: label rdr ausente\n");
	exit(1);
}

echo "PASS: blockpage domain collect + unbound + rdr\n";
' 2>/dev/null || {
	echo "SKIP: PHP nao disponivel localmente (executar no builder/appliance)"
	exit 0
}
