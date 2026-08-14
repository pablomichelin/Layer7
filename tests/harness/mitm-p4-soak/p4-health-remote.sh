#!/bin/sh
# Sample interno .254 — sem destinos externos; só metadados/PF/GUI.
set +e
echo UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)
php -r '
require_once("/usr/local/pkg/layer7.inc");
$m = layer7_mitm_from_config(layer7_load_or_default());
$s = layer7_mitm_window_status($m);
echo "effective=" . (layer7_mitm_effective($m, true) ? "true" : "false") . "\n";
echo "enabled=" . (!empty($m["enabled"]) ? "true" : "false") . "\n";
echo "remaining=" . $s["remaining_sec"] . "\n";
echo "deadline=" . $s["deadline_unix"] . "\n";
echo "expired=" . (!empty($s["expired"]) ? "true" : "false") . "\n";
echo "src=" . implode(",", $s["source_cidr"]) . "\n";
echo "dst=" . implode(",", $s["dest_cidr"]) . "\n";
'
if (pfctl -sn; pfctl -sr) 2>/dev/null | grep -i mitm | grep -E 'from[[:space:]]+any'; then
	echo ABORT_MITM_FROM_ANY
else
	echo MITM_SRC_SCOPED_OK
fi
echo SRC=$(pfctl -t layer7_mitm_src -T show 2>/dev/null | tr '\n' ' ')
echo DST=$(pfctl -t layer7_mitm_dst -T show 2>/dev/null | tr '\n' ' ')
pfctl -t layer7_mitm_dst -T show 2>/dev/null | grep -vE '198\.18\.0\.10|^$' >/dev/null \
	&& echo ABORT_UNEXPECTED_DST || echo DST_LAB_ONLY
pfctl -t layer7_mitm_src -T show 2>/dev/null | grep -vE '192\.168\.100\.24|^$' >/dev/null \
	&& echo ABORT_UNEXPECTED_SRC || echo SRC_LAB_ONLY
route -n get 198.18.0.10 2>/dev/null | grep -q 'gateway: 192.168.100.54' \
	&& echo ROUTE_LAB_OK || echo ROUTE_LAB_MISSING
sockstat -l 2>/dev/null | grep 8443 >/dev/null && echo LISTEN=1 || echo LISTEN=0
curl -sk -o /dev/null -w "GUI=%{http_code}\n" --connect-timeout 5 https://127.0.0.1:9999/ \
	|| echo GUI=FAIL
tail -1 /var/log/layer7-mitm-audit.log 2>/dev/null
pkg info -e pfSense-pkg-layer7 2>/dev/null && pkg query '%v' pfSense-pkg-layer7
