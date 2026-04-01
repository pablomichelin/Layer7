#!/bin/sh
# verify-release.sh — Valida manifesto, hashes e assinatura do conjunto de release
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
. "$SCRIPT_DIR/common.sh"

usage() {
  echo "Uso: $0 --stage-dir DIR [--public-key PUB] [--skip-signature]"
  exit 1
}

STAGE_DIR=""
PUBLIC_KEY=""
SKIP_SIGNATURE="0"

while [ $# -gt 0 ]; do
  case "$1" in
    --stage-dir) STAGE_DIR="$2"; shift 2 ;;
    --public-key) PUBLIC_KEY="$2"; shift 2 ;;
    --skip-signature) SKIP_SIGNATURE="1"; shift 1 ;;
    *) usage ;;
  esac
done

[ -n "$STAGE_DIR" ] || usage
[ -d "$STAGE_DIR" ] || die "stage dir nao existe: $STAGE_DIR"

require_cmd openssl

MANIFEST="$(manifest_path "$STAGE_DIR")"
SIG_PATH="$(signature_path "$STAGE_DIR")"

[ -f "$MANIFEST" ] || die "manifesto nao encontrado: $MANIFEST"

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
SIGNER_ROLE="$(manifest_value signer_role "$MANIFEST")"
SIGNER_GENERATED_AT="$(manifest_value signer_generated_at_utc "$MANIFEST")"
PUBKEY_FINGERPRINT="$(manifest_value public_key_fingerprint_sha256 "$MANIFEST")"

[ "$MANIFEST_VERSION" = "1" ] || die "manifest_version inesperado: $MANIFEST_VERSION"
[ -n "$RELEASE_VERSION" ] || die "release_version ausente"
[ "$RELEASE_TAG" = "v$RELEASE_VERSION" ] || die "release_tag nao bate com release_version"
[ -n "$SOURCE_REPO" ] || die "source_repo ausente"
[ -n "$SOURCE_COMMIT" ] || die "source_commit ausente"
[ -n "$DISTRIBUTION_REPO" ] || die "distribution_repo ausente"
[ "$BUILDER_ROLE" = "builder" ] || die "builder_role inesperado: $BUILDER_ROLE"
[ -n "$BUILDER_HOSTNAME" ] || die "builder_hostname ausente"
[ -n "$BUILDER_GENERATED_AT" ] || die "builder_generated_at_utc ausente"
[ "$CHECKSUM_ALGO" = "sha256" ] || die "checksum_algorithm inesperado: $CHECKSUM_ALGO"
[ "$SIGNING_SCHEME" = "ed25519-openssl-pkeyutl-v1" ] || die "signing_scheme inesperado: $SIGNING_SCHEME"
[ "$SIGNATURE_ASSET" = "$(signature_name)" ] || die "signature_asset inesperado: $SIGNATURE_ASSET"
[ "$PUBLIC_KEY_ASSET" = "$(public_key_asset_name)" ] || die "public_key_asset inesperado: $PUBLIC_KEY_ASSET"

PKG_NAME=""
PKG_HASH=""
PKG_SHA_FILE=""
PKG_SHA_FILE_HASH=""
ASSET_COUNT=0

ASSET_LINES_FILE="$(mktemp /tmp/layer7-release-assets.XXXXXX.txt)"
grep '^asset|' "$MANIFEST" > "$ASSET_LINES_FILE"

while IFS= read -r line; do
  name=""
  role=""
  size=""
  sha=""

  OLD_IFS="$IFS"
  IFS='|'
  set -- ${line#asset|}
  IFS="$OLD_IFS"

  for field in "$@"; do
    key="${field%%=*}"
    value="${field#*=}"
    case "$key" in
      name) name="$value" ;;
      role) role="$value" ;;
      size) size="$value" ;;
      sha256) sha="$value" ;;
    esac
  done

  [ -n "$name" ] || die "asset sem name no manifesto"
  [ -n "$role" ] || die "asset sem role no manifesto ($name)"
  [ -n "$size" ] || die "asset sem size no manifesto ($name)"
  [ -n "$sha" ] || die "asset sem sha256 no manifesto ($name)"

  asset_path="$STAGE_DIR/$name"
  [ -f "$asset_path" ] || die "asset ausente em stage dir: $name"

  actual_size="$(file_size_bytes "$asset_path")"
  [ "$actual_size" = "$size" ] || die "size divergente para $name: manifesto=$size actual=$actual_size"

  actual_sha="$(sha256_hex "$asset_path")"
  [ "$actual_sha" = "$sha" ] || die "sha256 divergente para $name"

  case "$role" in
    package)
      PKG_NAME="$name"
      PKG_HASH="$sha"
      ;;
    package-checksum)
      PKG_SHA_FILE="$name"
      PKG_SHA_FILE_HASH="$sha"
      ;;
  esac

  ASSET_COUNT=$((ASSET_COUNT + 1))
done < "$ASSET_LINES_FILE"

rm -f "$ASSET_LINES_FILE"

[ "$ASSET_COUNT" -gt 0 ] || die "manifesto sem assets"

if [ -n "$PKG_NAME" ] && [ -n "$PKG_SHA_FILE" ]; then
  expected_pkg_sha_line="$(printf '%s  %s' "$PKG_HASH" "$PKG_NAME")"
  actual_pkg_sha_line="$(tr -d '\r' < "$STAGE_DIR/$PKG_SHA_FILE" | head -1)"
  [ "$actual_pkg_sha_line" = "$expected_pkg_sha_line" ] || die "conteudo de $PKG_SHA_FILE nao bate com o pacote"
  [ -n "$PKG_SHA_FILE_HASH" ] || die "hash do asset package-checksum ausente"
fi

if [ "$SKIP_SIGNATURE" = "1" ]; then
  echo "Manifesto e assets verificados sem assinatura."
  exit 0
fi

[ -f "$SIG_PATH" ] || die "assinatura nao encontrada: $SIG_PATH"
[ "$SIGNER_ROLE" = "signer" ] || die "signer_role inesperado: $SIGNER_ROLE"
[ -n "$SIGNER_GENERATED_AT" ] || die "signer_generated_at_utc ausente"
[ -n "$PUBKEY_FINGERPRINT" ] || die "public_key_fingerprint_sha256 ausente"

if [ -z "$PUBLIC_KEY" ]; then
  PUBLIC_KEY="$STAGE_DIR/$PUBLIC_KEY_ASSET"
fi

[ -f "$PUBLIC_KEY" ] || die "public key nao encontrada: $PUBLIC_KEY"

actual_fingerprint="$(fingerprint_public_key_sha256 "$PUBLIC_KEY")"
[ "$actual_fingerprint" = "$PUBKEY_FINGERPRINT" ] || die "fingerprint da chave publica diverge do manifesto"

openssl pkeyutl -verify -rawin \
  -pubin \
  -inkey "$PUBLIC_KEY" \
  -sigfile "$SIG_PATH" \
  -in "$MANIFEST" >/dev/null 2>&1 || die "assinatura invalida"

echo "Manifesto, assets e assinatura verificados com sucesso."
