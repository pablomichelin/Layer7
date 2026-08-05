#!/bin/sh
# Onda E — validação de paridade CE mínima (passivo + smoke monitor).
# Executar no appliance pfSense (shell root). Restaura config anterior no fim.
set -u

CFG=/usr/local/etc/layer7.json
BK=/tmp/layer7-ondaE-restore.json
SMOKE=/tmp/smoke-monitor-mode.sh
RESTORED=0

restore_config() {
	if [ "$RESTORED" -eq 1 ]; then
		return 0
	fi
	if [ -f "$BK" ]; then
		cp "$BK" "$CFG"
	fi
	if [ -x /usr/local/etc/rc.d/layer7d ]; then
		/usr/local/etc/rc.d/layer7d restart >/dev/null 2>&1 || true
	else
		service layer7d restart >/dev/null 2>&1 || true
	fi
	RESTORED=1
	echo "=== restore OK ==="
}

trap restore_config EXIT INT TERM

if [ ! -f "$CFG" ]; then
	echo "FAIL: $CFG ausente"
	exit 1
fi

cp "$CFG" "$BK"
echo "=== ONDA E baseline ==="
date -u +%Y%m%dT%H%M%SZ
uname -a
cat /etc/version
pkg info -x pfSense-pkg-layer7 2>/dev/null || true
/usr/local/sbin/layer7d -V 2>/dev/null || true

/usr/local/bin/php -r '
$c = json_decode(file_get_contents("/usr/local/etc/layer7.json"), true);
$l = $c["layer7"] ?? [];
printf("BEFORE: mode=%s enabled=%s enforcement=%s\n",
	$l["mode"] ?? "monitor",
	!empty($l["enabled"]) ? "true" : "false",
	$l["enforcement_model"] ?? "legacy_global");
'

# Monitor passivo (equivalente install passivo + captura)
/usr/local/bin/php -r '
$c = json_decode(file_get_contents("/usr/local/etc/layer7.json"), true);
$c["layer7"]["mode"] = "monitor";
$c["layer7"]["enabled"] = true;
file_put_contents("/usr/local/etc/layer7.json",
	json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
'

if [ -x /usr/local/etc/rc.d/layer7d ]; then
	/usr/local/etc/rc.d/layer7d restart
else
	service layer7d restart
fi
sleep 2
# Recarregar PF para reflectir mode=monitor (sem blocks Layer7)
if [ -x /etc/rc.filter_configure ]; then
	/etc/rc.filter_configure >/dev/null 2>&1 || true
	sleep 3
fi

echo "=== G2 passive (monitor) ==="
BLOCKS=$(pfctl -sr 2>/dev/null | grep -E 'block drop.*"layer7:' || true)
if [ -z "$BLOCKS" ]; then
	echo "G2.3 PASS: zero block drop layer7"
else
	echo "G2.3 FAIL: regras block encontradas"
	printf "%s\n" "$BLOCKS"
fi

echo "=== G3 pfctl -nf ==="
if pfctl -nf /tmp/rules.debug 2>/dev/null; then
	echo "G3 PASS: rules.debug"
elif pfctl -nf /etc/pf.conf 2>/dev/null; then
	echo "G3 PASS: pf.conf"
else
	echo "G3 FAIL"
fi

echo "=== stats (monitor) ==="
/usr/local/sbin/layer7d -c "$CFG" -s 2>/dev/null | tr "," "\n" | grep -E 'captures|cap_pkts|cap_classified|enforce_mode|total_blocked' || true

echo "=== smoke-monitor-mode ==="
if [ -f "$SMOKE" ]; then
	sh "$SMOKE"
	SMOKE_RC=$?
else
	echo "FAIL: $SMOKE ausente"
	SMOKE_RC=1
fi

restore_config
/usr/local/bin/php -r '
$c = json_decode(file_get_contents("/usr/local/etc/layer7.json"), true);
$l = $c["layer7"] ?? [];
printf("AFTER RESTORE: mode=%s enabled=%s enforcement=%s\n",
	$l["mode"] ?? "monitor",
	!empty($l["enabled"]) ? "true" : "false",
	$l["enforcement_model"] ?? "legacy_global");
'
exit "$SMOKE_RC"
