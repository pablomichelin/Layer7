#!/bin/sh
# sign-release.sh — Assina o manifesto de release fora do builder
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
. "$SCRIPT_DIR/common.sh"

usage() {
  echo "Uso: $0 --stage-dir DIR --private-key KEY [--public-key PUB]"
  echo ""
  echo "Assumir: este script roda no signer, fora do builder."
  exit 1
}

STAGE_DIR=""
PRIVATE_KEY=""
PUBLIC_KEY=""

while [ $# -gt 0 ]; do
  case "$1" in
    --stage-dir) STAGE_DIR="$2"; shift 2 ;;
    --private-key) PRIVATE_KEY="$2"; shift 2 ;;
    --public-key) PUBLIC_KEY="$2"; shift 2 ;;
    *) usage ;;
  esac
done

[ -n "$STAGE_DIR" ] || usage
[ -n "$PRIVATE_KEY" ] || usage
[ -d "$STAGE_DIR" ] || die "stage dir nao existe: $STAGE_DIR"
[ -f "$PRIVATE_KEY" ] || die "private key nao existe: $PRIVATE_KEY"

require_cmd openssl

MANIFEST="$(manifest_path "$STAGE_DIR")"
SIG_PATH="$(signature_path "$STAGE_DIR")"
PUBKEY_PATH="$(public_key_asset_path "$STAGE_DIR")"
TMP_PUB=""

[ -f "$MANIFEST" ] || die "manifesto nao encontrado em $MANIFEST"

if [ -n "$PUBLIC_KEY" ]; then
  [ -f "$PUBLIC_KEY" ] || die "public key nao existe: $PUBLIC_KEY"
  cp "$PUBLIC_KEY" "$PUBKEY_PATH"
else
  TMP_PUB="$(mktemp /tmp/layer7-release-pub.XXXXXX.pem)"
  openssl pkey -in "$PRIVATE_KEY" -pubout -out "$TMP_PUB" >/dev/null 2>&1
  cp "$TMP_PUB" "$PUBKEY_PATH"
fi

chmod 0644 "$PUBKEY_PATH"

MANIFEST_VERSION="$(manifest_value manifest_version "$MANIFEST")"
RELEASE_VERSION="$(manifest_value release_version "$MANIFEST")"
RELEASE_TAG="$(manifest_value release_tag "$MANIFEST")"
SOURCE_REPO="$(manifest_value source_repo "$MANIFEST")"
SOURCE_COMMIT="$(manifest_value source_commit "$MANIFEST")"
DISTRIBUTION_REPO="$(manifest_value distribution_repo "$MANIFEST")"
BUILDER_ROLE="$(manifest_value builder_role "$MANIFEST")"
BUILDER_HOSTNAME="$(manifest_value builder_hostname "$MANIFEST")"
BUILDER_GENERATED_AT="$(manifest_value builder_generated_at_utc "$MANIFEST")"
CHECKSUM_ALGO="$(manifest_value checksum_algorithm "$MANIFEST")"
SIGNING_SCHEME="$(manifest_value signing_scheme "$MANIFEST")"
SIGNATURE_ASSET="$(manifest_value signature_asset "$MANIFEST")"
PUBLIC_KEY_ASSET="$(manifest_value public_key_asset "$MANIFEST")"

[ "$MANIFEST_VERSION" = "1" ] || die "manifest_version inesperado: $MANIFEST_VERSION"
[ -n "$RELEASE_VERSION" ] || die "release_version ausente no manifesto"
[ -n "$RELEASE_TAG" ] || die "release_tag ausente no manifesto"
[ -n "$SOURCE_REPO" ] || die "source_repo ausente no manifesto"
[ -n "$SOURCE_COMMIT" ] || die "source_commit ausente no manifesto"
[ -n "$DISTRIBUTION_REPO" ] || die "distribution_repo ausente no manifesto"
[ "$BUILDER_ROLE" = "builder" ] || die "builder_role inesperado: $BUILDER_ROLE"
[ -n "$BUILDER_HOSTNAME" ] || die "builder_hostname ausente no manifesto"
[ -n "$BUILDER_GENERATED_AT" ] || die "builder_generated_at_utc ausente no manifesto"
[ "$CHECKSUM_ALGO" = "sha256" ] || die "checksum_algorithm inesperado: $CHECKSUM_ALGO"
[ "$SIGNING_SCHEME" = "ed25519-openssl-pkeyutl-v1" ] || die "signing_scheme inesperado: $SIGNING_SCHEME"
[ "$SIGNATURE_ASSET" = "$(signature_name)" ] || die "signature_asset inesperado: $SIGNATURE_ASSET"
[ "$PUBLIC_KEY_ASSET" = "$(public_key_asset_name)" ] || die "public_key_asset inesperado: $PUBLIC_KEY_ASSET"

PUBKEY_FINGERPRINT="$(fingerprint_public_key_sha256 "$PUBKEY_PATH")"

TMP_MANIFEST="$(mktemp /tmp/layer7-release-manifest.XXXXXX.txt)"
{
  echo "manifest_version=1"
  echo "release_version=$RELEASE_VERSION"
  echo "release_tag=$RELEASE_TAG"
  echo "source_repo=$SOURCE_REPO"
  echo "source_commit=$SOURCE_COMMIT"
  echo "distribution_repo=$DISTRIBUTION_REPO"
  echo "builder_role=$BUILDER_ROLE"
  echo "builder_hostname=$BUILDER_HOSTNAME"
  echo "builder_generated_at_utc=$BUILDER_GENERATED_AT"
  echo "checksum_algorithm=$CHECKSUM_ALGO"
  echo "signing_scheme=$SIGNING_SCHEME"
  echo "signature_asset=$SIGNATURE_ASSET"
  echo "public_key_asset=$PUBLIC_KEY_ASSET"
  echo "signer_role=signer"
  echo "signer_generated_at_utc=$(utc_now)"
  echo "public_key_fingerprint_sha256=$PUBKEY_FINGERPRINT"
  echo ""
  grep '^asset|' "$MANIFEST" | grep -v "^asset|name=$(public_key_asset_name)|" || true
  emit_asset_line "$PUBKEY_PATH" "release-public-key"
} > "$TMP_MANIFEST"

mv "$TMP_MANIFEST" "$MANIFEST"

openssl pkeyutl -sign -rawin \
  -inkey "$PRIVATE_KEY" \
  -in "$MANIFEST" \
  -out "$SIG_PATH" >/dev/null 2>&1

TMP_VERIFY="$(mktemp /tmp/layer7-release-verify.XXXXXX.log)"
if ! openssl pkeyutl -verify -rawin \
  -pubin \
  -inkey "$PUBKEY_PATH" \
  -sigfile "$SIG_PATH" \
  -in "$MANIFEST" >"$TMP_VERIFY" 2>&1; then
  cat "$TMP_VERIFY" >&2
  rm -f "$TMP_VERIFY"
  die "falha ao verificar a assinatura acabada de gerar"
fi
rm -f "$TMP_VERIFY"

[ -z "$TMP_PUB" ] || rm -f "$TMP_PUB"

echo "Manifesto assinado com sucesso."
echo "Stage dir: $STAGE_DIR"
echo "Manifesto: $(basename "$MANIFEST")"
echo "Assinatura: $(basename "$SIG_PATH")"
echo "Chave publica staged: $(basename "$PUBKEY_PATH")"
echo "Fingerprint SHA256 da chave publica: $PUBKEY_FINGERPRINT"
