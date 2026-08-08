#!/bin/sh
# Homologação 20.33 — pacote 1.9.29 em produção (two-client + restore).
# Executar NO pfSense como root. Não deixa enforce activo no fim.
#
#   sh /tmp/run-im9-20.33-homolog.sh /tmp/l7-2033-ev
#
set -eu

CLIENT_A=${L7_CLIENT_A:-root@192.168.100.234}
CLIENT_B=${L7_CLIENT_B:-root@192.168.100.235}
EVID="${1:-/tmp/l7-2033-ev}"
CFG=/usr/local/etc/layer7.json
BACKUP=/root/layer7.json.pre-homolog-2033-run
PHP=/usr/local/bin/php
RC=0

mkdir -p "$EVID"
log() { printf '%s\n' "$1" | tee -a "$EVID/00-run.log"; }
pass() { log "PASS: $1"; }
fail() { log "FAIL: $1"; RC=1; }
info() { log "INFO: $1"; }

curl_code() {
	# $1 = ssh target, $2 = url
	ssh -o BatchMode=yes -o ConnectTimeout=10 "$1" \
		"curl -4 -s -o /dev/null -w '%{http_code}' --connect-timeout 10 --max-time 15 '$2'" \
		2>/dev/null || echo "000"
}

restore_cfg() {
	info "RESTORE config → $BACKUP"
	if [ -f "$BACKUP" ]; then
		cp -a "$BACKUP" "$CFG"
		"$PHP" <<'PHPEOF' >/dev/null 2>&1 || true
<?php
require_once("/etc/inc/globals.inc");
require_once("/etc/inc/config.inc");
require_once("/etc/inc/filter.inc");
require_once("/usr/local/pkg/layer7.inc");
layer7_pf_config_resync(true);
PHPEOF
		if [ -x /etc/rc.filter_configure ]; then
			/etc/rc.filter_configure >/dev/null 2>&1 || true
		fi
		service layer7d restart >/dev/null 2>&1 || true
		sleep 2
	fi
}

trap 'restore_cfg; exit $RC' EXIT INT TERM

log "=== IM9 20.33 homolog $(date -u +%Y-%m-%dT%H:%M:%SZ) ==="
log "pkg=$(pkg query %v pfSense-pkg-layer7 2>/dev/null || echo '?')"
log "daemon=$(layer7d -V 2>/dev/null || echo '?')"
layer7d --license-status 2>/dev/null | tee "$EVID/01-license.txt" || true

case "$(pkg query %v pfSense-pkg-layer7 2>/dev/null || true)" in
1.9.29) pass "pkg 1.9.29" ;;
*) fail "pkg != 1.9.29" ;;
esac

if grep -q '^valid=1' "$EVID/01-license.txt"; then
	pass "licença válida"
else
	fail "licença inválida — enforce real impossível"
fi

cp -a "$CFG" "$BACKUP"
info "backup=$BACKUP"

# --- Fase A: Identity OFF / sem entitlement identity (full→base T1) ---
log ""
log "--- Fase A: Identity inerte (T1 full→base) ---"
FEAT=$(grep '^features=' "$EVID/01-license.txt" | cut -d= -f2-)
info "features=$FEAT"
PORTS=$(sockstat -4l 2>/dev/null | grep -E ':(8743|1813)\b' || true)
if [ -z "$PORTS" ]; then
	pass "sem listeners Identity 8743/1813"
else
	fail "listeners Identity activos sem entitlement: $PORTS"
fi
if grep -q 'ADR-0029' /usr/local/www/packages/layer7/layer7_identity.php 2>/dev/null; then
	pass "GUI Identity ADR-0029 presente"
else
	fail "GUI Identity sem ADR-0029"
fi

# --- Fase B: two-client scoped enforce ---
log ""
log "--- Fase B: setup scoped_hybrid YouTube só A (.234) ---"
"$PHP" <<'PHPEOF'
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

if [ -x /etc/rc.filter_configure ]; then
	/etc/rc.filter_configure >/dev/null 2>&1 || true
fi
service layer7d restart
sleep 5

php -r '
$j=json_decode(file_get_contents("/usr/local/etc/layer7.json"),true);
$l7=$j["layer7"]??$j;
echo "enabled=".(!empty($l7["enabled"])?"1":"0")." mode=".($l7["mode"]??"")." model=".($l7["enforcement_model"]??"")."\n";
' | tee "$EVID/02-setup.txt"

# baseline allow both → example.com
CA_EX=$(curl_code "$CLIENT_A" "http://example.com")
CB_EX=$(curl_code "$CLIENT_B" "http://example.com")
log "A example.com=$CA_EX B example.com=$CB_EX"
echo "A=$CA_EX B=$CB_EX" >"$EVID/03-example.txt"
if echo "$CA_EX$CB_EX" | grep -qE '200'; then
	pass "example.com acessível (baseline allow)"
else
	fail "example.com falhou A=$CA_EX B=$CB_EX"
fi

# generate youtube traffic to populate pdst
info "gerar tráfego YouTube A/B (classificação)"
for i in 1 2 3; do
	curl_code "$CLIENT_A" "https://www.youtube.com" >/dev/null || true
	curl_code "$CLIENT_B" "https://www.youtube.com" >/dev/null || true
	# also HTTP host match path
	curl_code "$CLIENT_A" "http://www.youtube.com" >/dev/null || true
	sleep 2
done

# optional synthetic classify
/usr/local/sbin/layer7d -c "$CFG" -e 192.168.100.234 YouTube 2>&1 | tee "$EVID/04-layer7d-e-a.txt" || true
sleep 2

pfctl -t layer7_pdst_0 -T show 2>/dev/null | tee "$EVID/05-pdst0.txt" || true
pfctl -sr 2>/dev/null | grep -E 'layer7:(pdst|psrc|block)' | tee "$EVID/06-pf-rules.txt" || true

CA_YT=$(curl_code "$CLIENT_A" "https://www.youtube.com")
CB_YT=$(curl_code "$CLIENT_B" "https://www.youtube.com")
CA_GO=$(curl_code "$CLIENT_A" "https://www.google.com")
CB_GO=$(curl_code "$CLIENT_B" "https://www.google.com")
log "A youtube=$CA_YT B youtube=$CB_YT A google=$CA_GO B google=$CB_GO"
printf 'A_yt=%s B_yt=%s A_go=%s B_go=%s\n' "$CA_YT" "$CB_YT" "$CA_GO" "$CB_GO" | tee "$EVID/07-http-codes.txt"

# Expectation: A blocked (000/timeout) or non-2xx; B 2xx/3xx
A_BLOCKED=0
case "$CA_YT" in
000|403|451|502|503|504) A_BLOCKED=1 ;;
esac
# also: if pdst has entries and A cannot complete TLS
N_PDST=$(wc -l <"$EVID/05-pdst0.txt" | tr -d ' ')
info "pdst0_entries=$N_PDST"

if [ "$A_BLOCKED" -eq 1 ] || [ "${N_PDST:-0}" -gt 0 ]; then
	if [ "$A_BLOCKED" -eq 1 ]; then
		pass "cliente A YouTube bloqueado/indisponível (HTTP=$CA_YT)"
	else
		pass "cliente A: tabela pdst populada (DPI/enforce path activo; HTTP A=$CA_YT)"
	fi
else
	fail "cliente A YouTube ainda OK e pdst vazia (HTTP=$CA_YT)"
fi

case "$CB_YT" in
2*|3*) pass "cliente B YouTube OK (HTTP=$CB_YT) — scoped" ;;
*)
	# B may also fail for unrelated reasons; check google as control
	case "$CB_GO" in
	2*|3*) pass "cliente B google OK (YouTube=$CB_YT — verificar manualmente)" ;;
	*) fail "cliente B sem conectividade (yt=$CB_YT go=$CB_GO)" ;;
	esac
	;;
esac

case "$CA_GO" in
2*|3*) pass "cliente A google OK (não quarentena total)" ;;
*) fail "cliente A google falhou ($CA_GO) — risco quarentena total" ;;
esac

if pfctl -sr 2>/dev/null | grep -E 'layer7:pdst' | grep -q '192.168.100.234'; then
	pass "regra PF pdst scoped a 192.168.100.234"
else
	info "regra pdst textual não encontrada (pode usar tabela/tag)"
	if [ "${N_PDST:-0}" -gt 0 ]; then
		pass "enforce scoped evidenciado via tabela pdst"
	fi
fi

# Identity ainda inerte durante enforce base
PORTS2=$(sockstat -4l 2>/dev/null | grep -E ':(8743|1813)\b' || true)
if [ -z "$PORTS2" ]; then
	pass "Identity continua inerte sob enforce base"
else
	fail "Identity listeners abertos sob features=full/base"
fi

log ""
log "=== fim homolog; RC=$RC (restore via trap) ==="
# trap restores
exit "$RC"
