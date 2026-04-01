#!/bin/sh
# verify-snapshot.sh — valida manifesto, snapshot e assinatura da trilha F1.3
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
require_cmd tar

MANIFEST="$(manifest_path "$STAGE_DIR")"
SIG_PATH="$(signature_path "$STAGE_DIR")"
SNAPSHOT="$(snapshot_asset_path "$STAGE_DIR")"

[ -f "$MANIFEST" ] || die "manifesto nao encontrado: $MANIFEST"
[ -f "$SNAPSHOT" ] || die "snapshot nao encontrada: $SNAPSHOT"

MANIFEST_VERSION="$(manifest_value manifest_version "$MANIFEST")"
DATASET="$(manifest_value dataset "$MANIFEST")"
SNAPSHOT_ID="$(manifest_value snapshot_id "$MANIFEST")"
SNAPSHOT_ASSET="$(manifest_value snapshot_asset "$MANIFEST")"
SNAPSHOT_SIZE="$(manifest_value snapshot_size "$MANIFEST")"
SNAPSHOT_SHA256="$(manifest_value snapshot_sha256 "$MANIFEST")"
CATEGORIES_COUNT="$(manifest_value categories_count "$MANIFEST")"
DOMAINS_COUNT="$(manifest_value domains_count "$MANIFEST")"
CHECKSUM_ALGO="$(manifest_value checksum_algorithm "$MANIFEST")"
SIGNING_SCHEME="$(manifest_value signing_scheme "$MANIFEST")"
PUBLISHER_ROLE="$(manifest_value publisher_role "$MANIFEST")"
PUBLISHER_NAME="$(manifest_value publisher_name "$MANIFEST")"
PUBLISHED_AT="$(manifest_value published_at_utc "$MANIFEST")"
UPSTREAM_ROLE="$(manifest_value upstream_role "$MANIFEST")"
UPSTREAM_NAME="$(manifest_value upstream_name "$MANIFEST")"
UPSTREAM_URL="$(manifest_value upstream_url "$MANIFEST")"
UPSTREAM_ACQUIRED_AT="$(manifest_value upstream_acquired_at_utc "$MANIFEST")"
PUBLIC_KEY_ASSET="$(manifest_value public_key_asset "$MANIFEST")"
PUBKEY_FINGERPRINT="$(manifest_value public_key_fingerprint_sha256 "$MANIFEST")"
SIGNER_ROLE="$(manifest_value signer_role "$MANIFEST")"
SIGNER_GENERATED_AT="$(manifest_value signer_generated_at_utc "$MANIFEST")"

[ "$MANIFEST_VERSION" = "1" ] || die "manifest_version inesperado: $MANIFEST_VERSION"
[ "$DATASET" = "ut1" ] || die "dataset inesperado: $DATASET"
[ -n "$SNAPSHOT_ID" ] || die "snapshot_id ausente"
[ "$SNAPSHOT_ASSET" = "$(basename "$SNAPSHOT")" ] || die "snapshot_asset nao bate com o ficheiro staged"
[ "$(file_size_bytes "$SNAPSHOT")" = "$SNAPSHOT_SIZE" ] || die "snapshot_size divergente"
[ "$(sha256_hex "$SNAPSHOT")" = "$SNAPSHOT_SHA256" ] || die "snapshot_sha256 divergente"
[ -n "$CATEGORIES_COUNT" ] || die "categories_count ausente"
[ -n "$DOMAINS_COUNT" ] || die "domains_count ausente"
[ "$CHECKSUM_ALGO" = "sha256" ] || die "checksum_algorithm inesperado: $CHECKSUM_ALGO"
[ "$SIGNING_SCHEME" = "ed25519-openssl-pkeyutl-v1" ] || die "signing_scheme inesperado: $SIGNING_SCHEME"
[ "$PUBLISHER_ROLE" = "publisher" ] || die "publisher_role inesperado: $PUBLISHER_ROLE"
[ -n "$PUBLISHER_NAME" ] || die "publisher_name ausente"
[ -n "$PUBLISHED_AT" ] || die "published_at_utc ausente"
[ "$UPSTREAM_ROLE" = "content-authority" ] || die "upstream_role inesperado: $UPSTREAM_ROLE"
[ -n "$UPSTREAM_NAME" ] || die "upstream_name ausente"
[ -n "$UPSTREAM_URL" ] || die "upstream_url ausente"
[ -n "$UPSTREAM_ACQUIRED_AT" ] || die "upstream_acquired_at_utc ausente"

TMP="$(mktemp -d /tmp/layer7-bl-verify.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT
tar xzf "$SNAPSHOT" -C "$TMP"
require_snapshot_tree "$TMP"

actual_categories="$(snapshot_categories_count "$TMP")"
[ "$actual_categories" = "$CATEGORIES_COUNT" ] || die "categories_count divergente"
actual_domains="$(snapshot_domains_count "$TMP")"
[ "$actual_domains" = "$DOMAINS_COUNT" ] || die "domains_count divergente"

if [ "$SKIP_SIGNATURE" = "1" ]; then
	echo "Manifesto e snapshot verificados sem assinatura."
	exit 0
fi

[ -f "$SIG_PATH" ] || die "assinatura nao encontrada: $SIG_PATH"
[ "$PUBLIC_KEY_ASSET" = "$(public_key_asset_name)" ] || die "public_key_asset inesperado: $PUBLIC_KEY_ASSET"
[ -n "$PUBKEY_FINGERPRINT" ] || die "public_key_fingerprint_sha256 ausente"
[ "$SIGNER_ROLE" = "signer" ] || die "signer_role inesperado: $SIGNER_ROLE"
[ -n "$SIGNER_GENERATED_AT" ] || die "signer_generated_at_utc ausente"

if [ -z "$PUBLIC_KEY" ]; then
	PUBLIC_KEY="$STAGE_DIR/$PUBLIC_KEY_ASSET"
fi

[ -f "$PUBLIC_KEY" ] || die "public key nao encontrada: $PUBLIC_KEY"
[ "$(fingerprint_public_key_sha256 "$PUBLIC_KEY")" = "$PUBKEY_FINGERPRINT" ] \
	|| die "fingerprint da chave publica diverge do manifesto"

openssl pkeyutl -verify -rawin \
	-pubin \
	-inkey "$PUBLIC_KEY" \
	-sigfile "$SIG_PATH" \
	-in "$MANIFEST" >/dev/null 2>&1 || die "assinatura invalida"

echo "Manifesto, snapshot e assinatura verificados com sucesso."
