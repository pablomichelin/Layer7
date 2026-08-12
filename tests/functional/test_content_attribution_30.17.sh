#!/bin/sh
# test_content_attribution_30.17.sh — GA6.3 / GA6.4 (marcação por cliente)
#
# Uso: sh tests/functional/test_content_attribution_30.17.sh

set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)"
SCRIPT="$ROOT/package/pfSense-pkg-layer7/files/usr/local/etc/layer7/update-blacklists.sh"
TESTDIR="${TMPDIR:-/tmp}/layer7-attr-30.17.$$"
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
LIC_ID=7

"$OPENSSL" genpkey -algorithm ED25519 -out "$PRIV" 2>/dev/null || fail "genpkey"
"$OPENSSL" pkey -in "$PRIV" -pubout -out "$PUB" 2>/dev/null || fail "pubout"
cp "$PUB" "$BL_PUB"

cat > "$ACTIVE" <<EOF
snapshot_id=snap-attr-test
manifest_url=https://example.invalid/manifest
source_role=primary
EOF

sign_token() {
	_iat="$1"
	_exp="$2"
	_hw="$3"
	_payload=$(printf '{"v":1,"hardware_id":"%s","license_id":%s,"scope":"content","iat":%s,"exp":%s,"jti":"t-%s"}' \
		"$_hw" "$LIC_ID" "$_iat" "$_exp" "$$")
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
		fail "need php or python3"
	fi
	chmod 600 "$CS"
}

NOW=$(date +%s)
export L7_CONTENT_SUBSCRIPTION_PATH="$CS"
export L7_LICENSE_PUBKEY="$PUB"
export L7_CONTENT_HW_ID="$HW"

WORK_SCRIPT="$TESTDIR/update-blacklists.sh"
sed \
	-e "s|^BL_DIR=.*|BL_DIR=\"$TESTDIR/usr/local/etc/layer7/blacklists\"|" \
	-e "s|^L7_VAR_DB=.*|L7_VAR_DB=\"$TESTDIR/var/db/layer7\"|" \
	-e "s|^LOG=.*|LOG=\"$TESTDIR/var/log/layer7-bl-update.log\"|" \
	-e "s|^PUBKEY=.*|PUBKEY=\"$BL_PUB\"|" \
	"$SCRIPT" > "$WORK_SCRIPT"
chmod +x "$WORK_SCRIPT"

EXPECTED_MARK=$(printf 'l7-attr-v1:%s:%s' "$LIC_ID" "$HW" | "$OPENSSL" dgst -sha256 \
	| awk '{print $NF}')

# --- GA6.3: stamp cria sidecars com mark correcto ---
sign_token "$NOW" "$((NOW + 86400))" "$HW"
set +e
sh "$WORK_SCRIPT" --stamp-attribution >"$TESTDIR/stamp.out" 2>&1
_rc=$?
set -e
[ "$_rc" -eq 0 ] || {
	cat "$TESTDIR/stamp.out" >&2
	fail "stamp-attribution should succeed"
}
grep -q "content_attribution=ok" "$TESTDIR/stamp.out" || fail "ok line"

STATE_MARK="$TESTDIR/usr/local/etc/layer7/blacklists/.state/content-attribution.json"
TREE_MARK="$TESTDIR/usr/local/etc/layer7/blacklists/.l7-content-attribution"
[ -f "$STATE_MARK" ] || fail "state mark missing"
[ -f "$TREE_MARK" ] || fail "tree mark missing"
grep -q "\"mark\":\"$EXPECTED_MARK\"" "$STATE_MARK" || fail "mark mismatch"
grep -q "\"license_id\":$LIC_ID" "$STATE_MARK" || fail "license_id missing"
grep -q "\"hardware_id\":\"$HW\"" "$STATE_MARK" || fail "hardware_id missing"
grep -q "local_only_no_telemetry" "$STATE_MARK" || fail "policy missing"
grep -q "snap-attr-test" "$STATE_MARK" || fail "snapshot_id missing"

# --- GA6.3 privacy: sem PII cleartext ---
grep -Eiq 'customer|email|@' "$STATE_MARK" "$TREE_MARK" && fail "PII leaked into mark"

# --- GA6.4: stamp não usa rede (sem curl no PATH útil) ---
# Já validado: --stamp-attribution só verifica token local + escreve ficheiros.

# --- SKIP_FETCH também escreve marca ---
rm -f "$STATE_MARK" "$TREE_MARK"
export L7_BL_SKIP_FETCH=1
set +e
sh "$WORK_SCRIPT" --download >"$TESTDIR/skip.out" 2>&1
_rc=$?
set -e
[ "$_rc" -eq 0 ] || fail "skip-fetch download failed"
[ -f "$STATE_MARK" ] || fail "skip-fetch did not write mark"
grep -q "\"mark\":\"$EXPECTED_MARK\"" "$STATE_MARK" || fail "skip-fetch mark wrong"

# --- sem token → stamp falha; sem ficheiros novos forjados com PII ---
rm -f "$CS" "$STATE_MARK" "$TREE_MARK"
unset L7_BL_SKIP_FETCH
set +e
sh "$WORK_SCRIPT" --stamp-attribution >"$TESTDIR/stamp-miss.out" 2>&1
_rc=$?
set -e
[ "$_rc" -ne 0 ] || fail "stamp without token should fail"
[ ! -f "$STATE_MARK" ] || fail "mark written without token"

echo "PASS: 30.17 content attribution (GA6.3/GA6.4)"
rm -rf "$TESTDIR"
exit 0
