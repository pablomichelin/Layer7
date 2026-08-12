#!/bin/sh
# test_release_signing_f12_30.18.sh — 30.18 / BG-123 / GA6.5 (dry-run F1.2)
#
# Prova sign→verify offline com chave Ed25519 efémera (nunca commitada).
# Não publica GitHub Release; não toca .254 / CF / license-server.
#
# Uso: sh tests/functional/test_release_signing_f12_30.18.sh

set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)"
SCRIPT_DIR="$ROOT/scripts/release"
BASE_TMP="${TMPDIR:-/tmp}"
TESTDIR="${BASE_TMP}/layer7-f12-30.18.$$"
VERSION="0.0.0-f12test"
TAG="v${VERSION}"

fail() {
	echo "FAIL: $*" >&2
	rm -rf "$TESTDIR"
	exit 1
}

rm -rf "$TESTDIR"
mkdir -p "$TESTDIR/keys" "$TESTDIR/stage" "$TESTDIR/tmp"
# Isolar mktemp dos scripts de release (evita colisão com /tmp residual)
export TMPDIR="$TESTDIR/tmp"

# --- chave efémera (só TMP; R-K) ---
PRIV="$TESTDIR/keys/release-ed25519.pem"
PUB="$TESTDIR/keys/release-ed25519.pub.pem"
openssl genpkey -algorithm ED25519 -out "$PRIV" 2>/dev/null || fail "genpkey"
openssl pkey -in "$PRIV" -pubout -out "$PUB" 2>/dev/null || fail "pubout"
chmod 600 "$PRIV"

# --- stage mínimo (sem make package / sem publish) ---
STAGE="$TESTDIR/stage"
PKG="$STAGE/pfSense-pkg-layer7-${VERSION}.pkg"
printf 'FAKE-PKG-FOR-F12-TEST\n' > "$PKG"
. "$SCRIPT_DIR/common.sh"
write_sha256_file "$PKG" "${PKG}.sha256"

# install/uninstall a partir dos templates oficiais (sem rede)
sed \
	-e "s/^REPO_OWNER=\".*\"$/REPO_OWNER=\"pablomichelin\"/" \
	-e "s/^REPO_NAME=\".*\"$/REPO_NAME=\"Layer7\"/" \
	-e "s/^DEFAULT_VERSION=\"\"$/DEFAULT_VERSION=\"${VERSION}\"/" \
	"$SCRIPT_DIR/install.sh" > "$STAGE/install.sh"
chmod 0755 "$STAGE/install.sh"
sed \
	-e "s/^REPO_OWNER=\".*\"$/REPO_OWNER=\"pablomichelin\"/" \
	-e "s/^REPO_NAME=\".*\"$/REPO_NAME=\"Layer7\"/" \
	-e "s/^RELEASE_VERSION_HINT=\"\"$/RELEASE_VERSION_HINT=\"${VERSION}\"/" \
	"$SCRIPT_DIR/uninstall.sh" > "$STAGE/uninstall.sh"
chmod 0755 "$STAGE/uninstall.sh"

COMMIT="$(git -C "$ROOT" rev-parse HEAD)"
MANIFEST="$(manifest_path "$STAGE")"
{
	echo "manifest_version=1"
	echo "release_version=$VERSION"
	echo "release_tag=$TAG"
	echo "source_repo=https://github.com/pablomichelin/pfsense-layer7"
	echo "source_commit=$COMMIT"
	echo "distribution_repo=https://github.com/pablomichelin/Layer7"
	echo "builder_role=builder"
	echo "builder_hostname=f12-test"
	echo "builder_generated_at_utc=$(utc_now)"
	echo "checksum_algorithm=sha256"
	echo "signing_scheme=ed25519-openssl-pkeyutl-v1"
	echo "signature_asset=$(signature_name)"
	echo "public_key_asset=$(public_key_asset_name)"
	echo ""
	emit_asset_line "$PKG" "package"
	emit_asset_line "${PKG}.sha256" "package-checksum"
	emit_asset_line "$STAGE/install.sh" "installer"
	emit_asset_line "$STAGE/uninstall.sh" "uninstaller"
} > "$MANIFEST"

# --- sign + verify ---
sh "$SCRIPT_DIR/sign-release.sh" \
	--stage-dir "$STAGE" \
	--private-key "$PRIV" \
	--public-key "$PUB" \
	|| fail "sign-release"

[ -f "$(signature_path "$STAGE")" ] || fail "missing .sig"
[ -f "$(public_key_asset_path "$STAGE")" ] || fail "missing public key asset"
grep -q '^signer_role=signer$' "$MANIFEST" || fail "signer_role missing"
grep -q '^public_key_fingerprint_sha256=' "$MANIFEST" || fail "fingerprint missing"

sh "$SCRIPT_DIR/verify-release.sh" --stage-dir "$STAGE" \
	|| fail "verify-release after sign"

# --- negativo: tamper no .pkg deve falhar verify ---
printf 'TAMPER\n' >> "$PKG"
set +e
sh "$SCRIPT_DIR/verify-release.sh" --stage-dir "$STAGE" >"$TESTDIR/verify-tamper.out" 2>&1
_rc=$?
set -e
[ "$_rc" -ne 0 ] || fail "verify should fail after pkg tamper"
grep -Eqi 'sha256|divergente|ERRO' "$TESTDIR/verify-tamper.out" \
	|| fail "tamper error message missing"

# --- garantia: private key nunca no stage ---
find "$STAGE" -type f -name '*.pem' | while IFS= read -r f; do
	case "$f" in
	*release-signing-public-key.pem) ;;
	*) fail "unexpected pem in stage: $f" ;;
	esac
done

# --- publish exige verify (smoke: script chama verify; não executar publish) ---
grep -q 'verify-release.sh' "$SCRIPT_DIR/publish-release.sh" \
	|| fail "publish-release must call verify-release"

echo "PASS: 30.18 F1.2 sign+verify dry-run (GA6.5 toolchain)"
rm -rf "$TESTDIR"
exit 0
