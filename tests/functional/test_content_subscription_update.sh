#!/bin/sh
# test_content_subscription_update.sh — 30.10 / GA4.4–GA4.6 / GA4.9 (cliente)
#
# Sem token válido: update não contacta rede; snapshot local intacto.
# Com token válido + L7_BL_SKIP_FETCH=1: gate passa (Bearer preparado).
# Manifest verify path permanece o openssl pkeyutl existente (GA4.9).
#
# Uso: sh tests/functional/test_content_subscription_update.sh

set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)"
SCRIPT="$ROOT/package/pfSense-pkg-layer7/files/usr/local/etc/layer7/update-blacklists.sh"
TESTDIR="${TMPDIR:-/tmp}/layer7-cs-upd.$$"
OPENSSL="$(command -v openssl)"

fail() {
	echo "FAIL: $*" >&2
	rm -rf "$TESTDIR"
	exit 1
}

rm -rf "$TESTDIR"
mkdir -p \
	"$TESTDIR/usr/local/share/pfSense-pkg-layer7" \
	"$TESTDIR/usr/local/etc/layer7/blacklists/.state" \
	"$TESTDIR/var/db/layer7" \
	"$TESTDIR/var/log"

PUB="$TESTDIR/usr/local/share/pfSense-pkg-layer7/license-signing-public-key.pem"
PRIV="$TESTDIR/test-priv.pem"
BL_PUB="$TESTDIR/usr/local/share/pfSense-pkg-layer7/blacklists-signing-public-key.pem"
CS="$TESTDIR/var/db/layer7/content-subscription.json"
ACTIVE="$TESTDIR/usr/local/etc/layer7/blacklists/.state/active-snapshot.state"
HW="aabbccddeeff00112233445566778899aabbccddeeff00112233445566778899"

"$OPENSSL" genpkey -algorithm ED25519 -out "$PRIV" 2>/dev/null \
	|| fail "genpkey"
"$OPENSSL" pkey -in "$PRIV" -pubout -out "$PUB" 2>/dev/null \
	|| fail "pubout"
cp "$PUB" "$BL_PUB"

cat > "$ACTIVE" <<EOF
snapshot_id=snapshot-keep-me
manifest_url=https://example.invalid/manifest
source_role=primary
EOF
echo "domains" > "$TESTDIR/usr/local/etc/layer7/blacklists/keep-marker.txt"

sign_token() {
	_iat="$1"
	_exp="$2"
	_hw="$3"
	_payload=$(printf '{"v":1,"hardware_id":"%s","license_id":7,"scope":"content","iat":%s,"exp":%s,"jti":"t-%s"}' \
		"$_hw" "$_iat" "$_exp" "$$")
	_df="$TESTDIR/data.txt"
	_sf="$TESTDIR/sig.bin"
	printf '%s' "$_payload" > "$_df"
	"$OPENSSL" pkeyutl -sign -inkey "$PRIV" -rawin -in "$_df" -out "$_sf" 2>/dev/null \
		|| fail "sign"
	if command -v php >/dev/null 2>&1; then
		_sig=$(php -r 'echo bin2hex(file_get_contents($argv[1]));' "$_sf")
		php -r '
$payload = file_get_contents($argv[1]);
$sig = $argv[2];
file_put_contents($argv[3], json_encode(["data" => $payload, "sig" => $sig]));
' "$_df" "$_sig" "$CS"
	elif command -v python3 >/dev/null 2>&1; then
		_sig=$(python3 -c 'import sys; print(open(sys.argv[1],"rb").read().hex())' "$_sf")
		python3 -c '
import json,sys
payload=open(sys.argv[1],"r",encoding="utf-8").read()
sig=sys.argv[2]
open(sys.argv[3],"w",encoding="utf-8").write(json.dumps({"data":payload,"sig":sig}))
' "$_df" "$_sig" "$CS"
	else
		fail "need php or python3 to build fixture token"
	fi
	[ "${#_sig}" -eq 128 ] || fail "sig len ${#_sig}"
	chmod 600 "$CS"
}

NOW=$(date +%s)

export L7_CONTENT_SUBSCRIPTION_PATH="$CS"
export L7_LICENSE_PUBKEY="$PUB"
export L7_CONTENT_HW_ID="$HW"
export L7_BL_SKIP_FETCH=1

WORK_SCRIPT="$TESTDIR/update-blacklists.sh"
sed \
	-e "s|^BL_DIR=.*|BL_DIR=\"$TESTDIR/usr/local/etc/layer7/blacklists\"|" \
	-e "s|^L7_VAR_DB=.*|L7_VAR_DB=\"$TESTDIR/var/db/layer7\"|" \
	-e "s|^LOG=.*|LOG=\"$TESTDIR/var/log/layer7-bl-update.log\"|" \
	-e "s|^PUBKEY=.*|PUBKEY=\"$BL_PUB\"|" \
	"$SCRIPT" > "$WORK_SCRIPT"
chmod +x "$WORK_SCRIPT"

# --- GA4.5: sem token → não actualiza; marker intacto ---
rm -f "$CS"
set +e
sh "$WORK_SCRIPT" --download >"$TESTDIR/out-missing.txt" 2>&1
_rc=$?
set -e
[ "$_rc" -ne 0 ] || fail "expected non-zero without token"
grep -qi "content subscription" "$TESTDIR/out-missing.txt" \
	"$TESTDIR/var/log/layer7-bl-update.log" 2>/dev/null \
	|| fail "missing token log"
[ -f "$TESTDIR/usr/local/etc/layer7/blacklists/keep-marker.txt" ] \
	|| fail "local content removed without token"
grep -q "snapshot-keep-me" "$ACTIVE" || fail "active snapshot changed without token"

# --- GA4.4: token válido + SKIP_FETCH → gate PASS ---
sign_token "$NOW" "$((NOW + 86400))" "$HW"
set +e
sh "$WORK_SCRIPT" --download >"$TESTDIR/out-ok.txt" 2>&1
_rc=$?
set -e
[ "$_rc" -eq 0 ] || {
	cat "$TESTDIR/out-ok.txt" >&2
	fail "expected success with valid token + SKIP_FETCH"
}
grep -q "L7_BL_SKIP_FETCH" "$TESTDIR/out-ok.txt" \
	|| grep -q "L7_BL_SKIP_FETCH" "$TESTDIR/var/log/layer7-bl-update.log" \
	|| fail "skip-fetch marker missing"
grep -q "content subscription token OK" "$TESTDIR/out-ok.txt" \
	|| grep -q "content subscription token OK" "$TESTDIR/var/log/layer7-bl-update.log" \
	|| fail "token OK log missing"

# --check-subscription
set +e
sh "$WORK_SCRIPT" --check-subscription >"$TESTDIR/check-ok.txt" 2>&1
_rc=$?
set -e
[ "$_rc" -eq 0 ] || fail "check-subscription should pass"
grep -q "content_subscription=ok" "$TESTDIR/check-ok.txt" || fail "check ok line"

# --- expirado ---
sign_token "$((NOW - 40 * 86400))" "$((NOW - 2 * 86400))" "$HW"
set +e
sh "$WORK_SCRIPT" --check-subscription >"$TESTDIR/check-exp.txt" 2>&1
_rc=$?
set -e
[ "$_rc" -ne 0 ] || fail "expired should fail check"
grep -q "reason=expired" "$TESTDIR/check-exp.txt" || fail "reason expired"

# --- GA4.6: sem token → conteúdo local intacto (enforce fora deste teste) ---
rm -f "$CS"
set +e
sh "$WORK_SCRIPT" --download >/dev/null 2>&1
set -e
[ -f "$TESTDIR/usr/local/etc/layer7/blacklists/keep-marker.txt" ] \
	|| fail "GA4.6 content not preserved"
grep -q "snapshot-keep-me" "$ACTIVE" || fail "GA4.6 active snapshot lost"

# GA4.9: verify de manifesto ainda presente
grep -q 'openssl pkeyutl -verify -rawin' "$WORK_SCRIPT" \
	|| fail "GA4.9 manifest verify removed"

# --- Regressão 30.10: fetch_authed 302→200 sem vazar Bearer cross-host ---
FAKEBIN="$TESTDIR/fakebin"
FAKESTATE="$TESTDIR/fake-curl-state"
mkdir -p "$FAKEBIN" "$FAKESTATE" "$TESTDIR/tmp-fetch"
cat > "$FAKEBIN/curl" <<'EOF'
#!/bin/sh
OUT=""
CODE_FMT=""
HDR_OUT=""
URL=""
HAS_AUTH=0
HAS_XLAYER=0
i=1
while [ "$i" -le "$#" ]; do
	eval "arg=\${$i}"
	case "$arg" in
	-o)
		i=$((i + 1))
		eval "OUT=\${$i}"
		;;
	-w)
		i=$((i + 1))
		eval "CODE_FMT=\${$i}"
		;;
	-D)
		i=$((i + 1))
		eval "HDR_OUT=\${$i}"
		;;
	-H)
		i=$((i + 1))
		eval "h=\${$i}"
		case "$h" in
		[Aa]uthorization:*|[Aa]uthorization:\ *) HAS_AUTH=1 ;;
		[Xx]-[Ll]ayer7-[Cc]ontent-[Tt]oken:*|[Xx]-[Ll]ayer7-[Cc]ontent-[Tt]oken:\ *) HAS_XLAYER=1 ;;
		esac
		;;
	--connect-timeout|--max-time|--proto|--proto-redir|--max-redirs)
		i=$((i + 1))
		;;
	-sS|-s|-S|-L|--location|--location-trusted|--fail)
		;;
	https://*|http://*)
		URL="$arg"
		;;
	esac
	i=$((i + 1))
done

_state="${L7_FAKE_CURL_STATE:-/tmp}"
_emit_code() {
	if [ "$CODE_FMT" = "%{http_code}" ]; then
		printf '%s' "$1"
	fi
}

case "$URL" in
https://mirror.test/asset)
	printf 'hop1 auth=%s x=%s\n' "$HAS_AUTH" "$HAS_XLAYER" >>"$_state/curl.log"
	[ "$HAS_AUTH" -eq 1 ] || { _emit_code 401; exit 0; }
	[ -n "$HDR_OUT" ] && printf 'HTTP/1.1 302 Found\nLocation: https://cdn.test/asset\n\n' >"$HDR_OUT"
	[ -n "$OUT" ] && : >"$OUT"
	_emit_code 302
	exit 0
	;;
https://cdn.test/asset)
	printf 'hop2 auth=%s x=%s\n' "$HAS_AUTH" "$HAS_XLAYER" >>"$_state/curl.log"
	if [ "$HAS_AUTH" -eq 1 ] || [ "$HAS_XLAYER" -eq 1 ]; then
		echo LEAK >>"$_state/curl.log"
		[ -n "$OUT" ] && : >"$OUT"
		_emit_code 403
		exit 0
	fi
	[ -n "$HDR_OUT" ] && printf 'HTTP/1.1 200 OK\n\n' >"$HDR_OUT"
	[ -n "$OUT" ] && printf 'ok-body\n' >"$OUT"
	_emit_code 200
	exit 0
	;;
https://same.test/a)
	[ -n "$HDR_OUT" ] && printf 'HTTP/1.1 302 Found\nLocation: https://same.test/b\n\n' >"$HDR_OUT"
	[ -n "$OUT" ] && : >"$OUT"
	_emit_code 302
	exit 0
	;;
https://same.test/b)
	if [ "$HAS_AUTH" -ne 1 ] || [ "$HAS_XLAYER" -ne 1 ]; then
		_emit_code 401
		exit 0
	fi
	[ -n "$HDR_OUT" ] && printf 'HTTP/1.1 200 OK\n\n' >"$HDR_OUT"
	[ -n "$OUT" ] && printf 'same-ok\n' >"$OUT"
	_emit_code 200
	exit 0
	;;
https://evil.test/http-redir)
	[ -n "$HDR_OUT" ] && printf 'HTTP/1.1 302 Found\nLocation: http://evil.test/plain\n\n' >"$HDR_OUT"
	[ -n "$OUT" ] && : >"$OUT"
	_emit_code 302
	exit 0
	;;
*)
	printf 'unexpected:%s\n' "$URL" >>"$_state/curl.log"
	_emit_code 000
	exit 1
	;;
esac
EOF
chmod +x "$FAKEBIN/curl"

HARNESS="$TESTDIR/fetch_authed_harness.sh"
{
	echo '#!/bin/sh'
	echo 'set -eu'
	echo "TMP=\"$TESTDIR/tmp-fetch\""
	echo "LOG=\"$TESTDIR/fetch-authed.log\""
	echo 'CONTENT_BEARER="test-bearer-token"'
	echo 'log() { echo "$*" >> "$LOG"; }'
	sed -n '/^https_url_host()/,/^require_content_subscription_or_hold()/p' "$SCRIPT" | sed '$d'
	echo 'fetch_authed "$1" "$2"'
	echo 'exit $?'
} > "$HARNESS"
chmod +x "$HARNESS"

export L7_FAKE_CURL_STATE="$FAKESTATE"
export PATH="$FAKEBIN:$PATH"
: > "$FAKESTATE/curl.log"

set +e
sh "$HARNESS" "$TESTDIR/out-redir.txt" "https://mirror.test/asset"
_rc=$?
set -e
[ "$_rc" -eq 0 ] || {
	cat "$TESTDIR/fetch-authed.log" >&2
	fail "302→200 cross-host should succeed"
}
grep -qx 'ok-body' "$TESTDIR/out-redir.txt" || fail "redirect body missing"
grep -q 'hop2 auth=0 x=0' "$FAKESTATE/curl.log" || fail "credentials leaked to CDN host"
grep -q LEAK "$FAKESTATE/curl.log" && fail "CDN saw auth headers"

set +e
sh "$HARNESS" "$TESTDIR/out-same.txt" "https://same.test/a"
_rc=$?
set -e
[ "$_rc" -eq 0 ] || fail "same-host 302 should keep Bearer"
grep -qx 'same-ok' "$TESTDIR/out-same.txt" || fail "same-host body missing"

set +e
sh "$HARNESS" "$TESTDIR/out-http.txt" "https://evil.test/http-redir"
_rc=$?
set -e
[ "$_rc" -ne 0 ] || fail "http redirect must be refused"
grep -qi 'unsafe redirect\|non-HTTPS\|refused' "$TESTDIR/fetch-authed.log" \
	|| fail "unsafe redirect log missing"

# Confirmar hold-active / sem-token intacto após regressão redirect
rm -f "$CS"
set +e
sh "$WORK_SCRIPT" --download >"$TESTDIR/out-hold-after.txt" 2>&1
_rc=$?
set -e
[ "$_rc" -ne 0 ] || fail "hold-active broken after redirect fix"
[ -f "$TESTDIR/usr/local/etc/layer7/blacklists/keep-marker.txt" ] \
	|| fail "local content removed after redirect tests"
grep -q "snapshot-keep-me" "$ACTIVE" || fail "active snapshot lost after redirect tests"

rm -rf "$TESTDIR"
echo "PASS test_content_subscription_update.sh"
exit 0
