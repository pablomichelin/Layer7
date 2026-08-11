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
	_sig=$(python3 -c 'import sys; print(open(sys.argv[1],"rb").read().hex())' "$_sf")
	[ "${#_sig}" -eq 128 ] || fail "sig len ${#_sig}"
	python3 -c '
import json,sys
payload=open(sys.argv[1],"r",encoding="utf-8").read()
sig=sys.argv[2]
open(sys.argv[3],"w",encoding="utf-8").write(json.dumps({"data":payload,"sig":sig}))
' "$_df" "$_sig" "$CS"
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

rm -rf "$TESTDIR"
echo "PASS test_content_subscription_update.sh"
exit 0
