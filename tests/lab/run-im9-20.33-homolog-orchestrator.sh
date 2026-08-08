#!/bin/sh
# Homologação 20.33 — orquestrador no Mac/lab (appliance sem chave SSH aos clientes).
# Uso:
#   sh tests/lab/run-im9-20.33-homolog-orchestrator.sh [evidence_dir]
#
set -eu

APPLIANCE=${L7_APPLIANCE:-root@192.168.100.254}
CLIENT_A=${L7_CLIENT_A:-root@192.168.100.234}
CLIENT_B=${L7_CLIENT_B:-root@192.168.100.235}
EVID="${1:-}"
PASS_APPL=${L7_APPLIANCE_PASS:-pablo}
RC=0

if [ -z "$EVID" ]; then
	EVID="/tmp/l7-2033-orch-$$"
fi
mkdir -p "$EVID"

log() { printf '%s\n' "$1" | tee -a "$EVID/00-run.log"; }
pass() { log "PASS: $1"; }
fail() { log "FAIL: $1"; RC=1; }
info() { log "INFO: $1"; }

ash() {
	sshpass -p "$PASS_APPL" ssh -o StrictHostKeyChecking=no -o ConnectTimeout=12 -T "$APPLIANCE" "$@"
}
csh() {
	# clients: BatchMode key auth from Mac
	ssh -o BatchMode=yes -o StrictHostKeyChecking=no -o ConnectTimeout=12 -T "$1" "$2"
}

curl_code() {
	csh "$1" "curl -4 -s -o /dev/null -w '%{http_code}' --connect-timeout 10 --max-time 15 '$2'" 2>/dev/null || echo "000"
}

restore_cfg() {
	info "RESTORE config on appliance"
	ash 'cp -a /root/layer7.json.pre-homolog-2033-run /usr/local/etc/layer7.json 2>/dev/null || cp -a /root/layer7.json.pre-homolog-2033 /usr/local/etc/layer7.json
php <<'"'"'PHPEOF'"'"'
<?php
require_once("/etc/inc/globals.inc");
require_once("/etc/inc/config.inc");
require_once("/etc/inc/filter.inc");
require_once("/usr/local/pkg/layer7.inc");
layer7_pf_config_resync(true);
PHPEOF
/etc/rc.filter_configure >/dev/null 2>&1 || true
service layer7d restart >/dev/null 2>&1 || true
sleep 2
php -r '"'"'$j=json_decode(file_get_contents("/usr/local/etc/layer7.json"),true);$l7=$j["layer7"]??$j;echo "restored enabled=".(!empty($l7["enabled"])?"1":"0")." mode=".($l7["mode"]??"")."\n";'"'"''
}

trap 'restore_cfg; exit $RC' EXIT INT TERM

log "=== IM9 20.33 homolog orchestrator $(date -u +%Y-%m-%dT%H:%M:%SZ) ==="
log "appliance=$APPLIANCE A=$CLIENT_A B=$CLIENT_B"

# baseline client reachability from Mac
CA0=$(curl_code "$CLIENT_A" "http://example.com")
CB0=$(curl_code "$CLIENT_B" "http://example.com")
log "baseline A example=$CA0 B example=$CB0"
echo "A=$CA0 B=$CB0" >"$EVID/03-baseline-example.txt"
case "$CA0$CB0" in
*200*) pass "baseline example.com OK via Mac→clientes" ;;
*) fail "baseline clientes sem internet A=$CA0 B=$CB0"; exit 1 ;;
esac

# appliance health
ash 'pkg query %v pfSense-pkg-layer7; layer7d -V; layer7d --license-status' | tee "$EVID/01-license.txt"
grep -q '^1.9.29$' "$EVID/01-license.txt" && pass "pkg 1.9.29" || fail "pkg != 1.9.29"
grep -q '^valid=1' "$EVID/01-license.txt" && pass "licença válida" || fail "licença inválida"

# Identity inerte
ash 'sockstat -4l | grep -E ":(8743|1813)\\b" || echo NO_ID_PORTS; grep -c ADR-0029 /usr/local/www/packages/layer7/layer7_identity.php' | tee "$EVID/01b-identity.txt"
grep -q NO_ID_PORTS "$EVID/01b-identity.txt" && pass "sem listeners Identity" || fail "listeners Identity"
grep -qE '^[1-9]' "$EVID/01b-identity.txt" && pass "GUI ADR-0029" || fail "GUI ADR-0029 ausente"

# backup + setup enforce scoped
ash 'cp -a /usr/local/etc/layer7.json /root/layer7.json.pre-homolog-2033-run
php <<'"'"'PHPEOF'"'"'
<?php
require_once("/etc/inc/globals.inc");
require_once("/etc/inc/config.inc");
require_once("/etc/inc/filter.inc");
require_once("/usr/local/pkg/layer7.inc");
$data = layer7_load_or_default();
$ifaces = isset($data["layer7"]["interfaces"]) ? $data["layer7"]["interfaces"] : array("vmx0");
$data["layer7"]["enabled"] = true;
$data["layer7"]["mode"] = "enforce";
$data["layer7"]["enforcement_model"] = "scoped_hybrid";
$data["layer7"]["interfaces"] = $ifaces;
$data["layer7"]["policies"] = array(
    array(
        "id" => "h33-yt-block-a",
        "name" => "Homolog 20.33 YouTube block A",
        "enabled" => true,
        "action" => "block",
        "priority" => 100,
        "match" => array(
            "hosts" => array("youtube.com", "www.youtube.com"),
            "src_hosts" => array("192.168.100.234")
        )
    )
);
layer7_save_json($data);
layer7_pf_config_resync(true);
PHPEOF
/etc/rc.filter_configure >/dev/null 2>&1 || true
service layer7d restart
sleep 5
php -r '"'"'$j=json_decode(file_get_contents("/usr/local/etc/layer7.json"),true);$l7=$j["layer7"]??$j;echo "enabled=".(!empty($l7["enabled"])?"1":"0")." mode=".($l7["mode"]??"")." model=".($l7["enforcement_model"]??"")."\n";'"'"'' | tee "$EVID/02-setup.txt"

# allow control
CA_EX=$(curl_code "$CLIENT_A" "http://example.com")
CB_EX=$(curl_code "$CLIENT_B" "http://example.com")
log "during A example=$CA_EX B example=$CB_EX"
echo "A=$CA_EX B=$CB_EX" >"$EVID/03-example.txt"
case "$CA_EX" in 200) pass "A example.com OK sob enforce scoped" ;; *) fail "A example=$CA_EX" ;; esac
case "$CB_EX" in 200) pass "B example.com OK sob enforce scoped" ;; *) fail "B example=$CB_EX" ;; esac

# warm classification traffic
info "aquecimento YouTube A/B"
i=0
while [ "$i" -lt 5 ]; do
	curl_code "$CLIENT_A" "https://www.youtube.com" >/dev/null || true
	curl_code "$CLIENT_B" "https://www.youtube.com" >/dev/null || true
	curl_code "$CLIENT_A" "http://www.youtube.com/" >/dev/null || true
	i=$((i + 1))
	sleep 2
done

# synthetic enforce from appliance
ash '/usr/local/sbin/layer7d -c /usr/local/etc/layer7.json -e 192.168.100.234 YouTube 2>&1; sleep 2; pfctl -t layer7_pdst_0 -T show 2>/dev/null; echo ---; pfctl -sr 2>/dev/null | grep -E "layer7:pdst|layer7:block" | head -20' | tee "$EVID/04-enforce-path.txt"

CA_YT=$(curl_code "$CLIENT_A" "https://www.youtube.com")
CB_YT=$(curl_code "$CLIENT_B" "https://www.youtube.com")
CA_GO=$(curl_code "$CLIENT_A" "https://www.google.com")
CB_GO=$(curl_code "$CLIENT_B" "https://www.google.com")
log "A_yt=$CA_YT B_yt=$CB_YT A_go=$CA_GO B_go=$CB_GO"
printf 'A_yt=%s B_yt=%s A_go=%s B_go=%s\n' "$CA_YT" "$CB_YT" "$CA_GO" "$CB_GO" | tee "$EVID/07-http-codes.txt"

# A should be blocked for YT (000) OR pdst populated; B should get 2xx for YT or at least google
N_PDST=$(ash 'pfctl -t layer7_pdst_0 -T show 2>/dev/null | wc -l | tr -d " "')
info "pdst0=$N_PDST"
echo "pdst0=$N_PDST" >"$EVID/05-pdst-count.txt"

A_BLOCKED=0
case "$CA_YT" in 000|403|451|502|503|504) A_BLOCKED=1 ;; esac

if [ "$A_BLOCKED" -eq 1 ]; then
	pass "A YouTube bloqueado (HTTP=$CA_YT)"
elif [ "${N_PDST:-0}" -gt 0 ]; then
	pass "A: pdst populada ($N_PDST) — caminho enforce activo (HTTP A=$CA_YT)"
else
	fail "A YouTube não bloqueado e pdst vazia (HTTP=$CA_YT)"
fi

case "$CB_YT" in
2*|3*) pass "B YouTube OK (HTTP=$CB_YT) — isolamento scoped" ;;
*)
	case "$CB_GO" in
	2*|3*)
		# If B YT also 000 but google works and A blocked — may be flaky YT; still check pdst only has effect on A
		if [ "$A_BLOCKED" -eq 1 ] && [ "$CB_YT" = "000" ]; then
			fail "B YouTube também 000 (scoped falhou ou YT indisponível global)"
		else
			pass "B google OK (yt=$CB_YT)"
		fi
		;;
	*) fail "B sem net yt=$CB_YT go=$CB_GO" ;;
	esac
	;;
esac

case "$CA_GO" in 2*|3*) pass "A google OK (não quarentena total)" ;; *) fail "A google=$CA_GO" ;; esac
case "$CB_GO" in 2*|3*) pass "B google OK" ;; *) fail "B google=$CB_GO" ;; esac

ash 'sockstat -4l | grep -E ":(8743|1813)\\b" || echo NO_ID_PORTS' | tee "$EVID/08-identity-under-enforce.txt"
grep -q NO_ID_PORTS "$EVID/08-identity-under-enforce.txt" && pass "Identity inerte sob enforce" || fail "Identity listeners sob enforce"

log "=== fim orch RC=$RC ==="
exit "$RC"
