#!/bin/sh
# sign-snapshot.sh — assina o manifesto oficial de blacklists
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
. "$SCRIPT_DIR/common.sh"

usage() {
	echo "Uso: $0 --stage-dir DIR --private-key KEY [--public-key PUB]"
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
[ -f "$(snapshot_asset_path "$STAGE_DIR")" ] || die "snapshot asset ausente"

if [ -n "$PUBLIC_KEY" ]; then
	[ -f "$PUBLIC_KEY" ] || die "public key nao existe: $PUBLIC_KEY"
	cp "$PUBLIC_KEY" "$PUBKEY_PATH"
else
	TMP_PUB="$(mktemp /tmp/layer7-bl-pub.XXXXXX.pem)"
	openssl pkey -in "$PRIVATE_KEY" -pubout -out "$TMP_PUB" >/dev/null 2>&1
	cp "$TMP_PUB" "$PUBKEY_PATH"
fi

chmod 0644 "$PUBKEY_PATH"

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

[ "$MANIFEST_VERSION" = "1" ] || die "manifest_version inesperado: $MANIFEST_VERSION"
[ "$DATASET" = "ut1" ] || die "dataset inesperado: $DATASET"
[ -n "$SNAPSHOT_ID" ] || die "snapshot_id ausente"
[ "$SNAPSHOT_ASSET" = "$(snapshot_asset_name)" ] || die "snapshot_asset inesperado: $SNAPSHOT_ASSET"
[ -n "$SNAPSHOT_SIZE" ] || die "snapshot_size ausente"
[ -n "$SNAPSHOT_SHA256" ] || die "snapshot_sha256 ausente"
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

PUBKEY_FINGERPRINT="$(fingerprint_public_key_sha256 "$PUBKEY_PATH")"
TMP_MANIFEST="$(mktemp /tmp/layer7-bl-manifest.XXXXXX.txt)"

{
	echo "manifest_version=1"
	echo "dataset=$DATASET"
	echo "snapshot_id=$SNAPSHOT_ID"
	echo "snapshot_asset=$SNAPSHOT_ASSET"
	echo "snapshot_size=$SNAPSHOT_SIZE"
	echo "snapshot_sha256=$SNAPSHOT_SHA256"
	echo "categories_count=$CATEGORIES_COUNT"
	echo "domains_count=$DOMAINS_COUNT"
	echo "checksum_algorithm=$CHECKSUM_ALGO"
	echo "signing_scheme=$SIGNING_SCHEME"
	echo "publisher_role=$PUBLISHER_ROLE"
	echo "publisher_name=$PUBLISHER_NAME"
	echo "published_at_utc=$PUBLISHED_AT"
	echo "upstream_role=$UPSTREAM_ROLE"
	echo "upstream_name=$UPSTREAM_NAME"
	echo "upstream_url=$UPSTREAM_URL"
	echo "upstream_acquired_at_utc=$UPSTREAM_ACQUIRED_AT"
	echo "public_key_asset=$(public_key_asset_name)"
	echo "public_key_fingerprint_sha256=$PUBKEY_FINGERPRINT"
	echo "signer_role=signer"
	echo "signer_generated_at_utc=$(utc_now)"
} > "$TMP_MANIFEST"

mv "$TMP_MANIFEST" "$MANIFEST"

openssl pkeyutl -sign -rawin \
	-inkey "$PRIVATE_KEY" \
	-in "$MANIFEST" \
	-out "$SIG_PATH" >/dev/null 2>&1

openssl pkeyutl -verify -rawin \
	-pubin \
	-inkey "$PUBKEY_PATH" \
	-sigfile "$SIG_PATH" \
	-in "$MANIFEST" >/dev/null 2>&1 \
		|| die "falha ao verificar a assinatura acabada de gerar"

[ -z "$TMP_PUB" ] || rm -f "$TMP_PUB"

echo "Manifesto de blacklists assinado com sucesso."
echo "Stage dir: $STAGE_DIR"
echo "Manifesto: $(basename "$MANIFEST")"
echo "Assinatura: $(basename "$SIG_PATH")"
echo "Chave publica staged: $(basename "$PUBKEY_PATH")"
echo "Fingerprint SHA256 da chave publica: $PUBKEY_FINGERPRINT"
