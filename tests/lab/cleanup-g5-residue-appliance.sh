#!/bin/sh
# Pós-Onda B — remover permanentemente artefacto G5 `g5-test-bl` do appliance.
# Pré-requisito: backup Veeam/snapshot (confirmado pelo operador).
set -eu

BL=/usr/local/etc/layer7/blacklists/config.json
BK=/tmp/layer7-bl-pre-cleanup.json

[ -f "$BL" ] || { echo "FAIL: $BL ausente"; exit 1; }
cp "$BL" "$BK"

echo "=== cleanup G5 g5-test-bl ==="
date -u +%Y%m%dT%H%M%SZ

/usr/local/bin/php -r '
	$path = "/usr/local/etc/layer7/blacklists/config.json";
	$c = json_decode(file_get_contents($path), true);
	if (!is_array($c)) { fwrite(STDERR, "invalid bl config\n"); exit(1); }
	$before = count($c["rules"] ?? []);
	$rules = [];
	foreach (($c["rules"] ?? []) as $r) {
		if (($r["name"] ?? "") === "g5-test-bl") continue;
		$rules[] = $r;
	}
	$c["rules"] = $rules;
	file_put_contents($path, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
	printf("blacklist rules: %d -> %d\n", $before, count($rules));
'

/usr/local/libexec/layer7-pfctl flush-all
/etc/rc.filter_configure
sleep 4
/usr/local/etc/rc.d/layer7d restart
sleep 2

echo "=== verificação ==="
G5=$(pfctl -sr 2>/dev/null | grep -c 'g5-test-bl' || true)
if [ "$G5" -eq 0 ]; then
	echo "PASS: zero regras g5-test-bl no PF"
else
	echo "FAIL: ainda existem $G5 regras g5-test-bl"
	exit 1
fi

/usr/local/bin/php -r '
$c = json_decode(file_get_contents("/usr/local/etc/layer7.json"), true);
$l = $c["layer7"] ?? [];
printf("mode=%s enabled=%s enforcement=%s\n",
	$l["mode"] ?? "monitor",
	!empty($l["enabled"]) ? "true" : "false",
	$l["enforcement_model"] ?? "legacy_global");
'
