#!/bin/sh
# stage-snapshot.sh — materializa snapshot oficial candidata de blacklists
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
. "$SCRIPT_DIR/common.sh"

usage() {
	echo "Uso: $0 --input PATH --stage-dir DIR --snapshot-id ID --upstream-url URL [--upstream-acquired-at UTC]"
	echo ""
	echo "PATH pode ser um directorio com blacklists/ ou um tar.gz da snapshot."
	exit 1
}

INPUT_PATH=""
STAGE_DIR=""
SNAPSHOT_ID=""
UPSTREAM_URL=""
UPSTREAM_ACQUIRED_AT=""

while [ $# -gt 0 ]; do
	case "$1" in
	--input) INPUT_PATH="$2"; shift 2 ;;
	--stage-dir) STAGE_DIR="$2"; shift 2 ;;
	--snapshot-id) SNAPSHOT_ID="$2"; shift 2 ;;
	--upstream-url) UPSTREAM_URL="$2"; shift 2 ;;
	--upstream-acquired-at) UPSTREAM_ACQUIRED_AT="$2"; shift 2 ;;
	*) usage ;;
	esac
done

[ -n "$INPUT_PATH" ] || usage
[ -n "$STAGE_DIR" ] || usage
[ -n "$SNAPSHOT_ID" ] || usage
[ -n "$UPSTREAM_URL" ] || usage
[ -e "$INPUT_PATH" ] || die "input nao existe: $INPUT_PATH"

require_cmd tar

UPSTREAM_ACQUIRED_AT="${UPSTREAM_ACQUIRED_AT:-$(utc_now)}"
TMP="$(mktemp -d /tmp/layer7-bl-stage.XXXXXX)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$STAGE_DIR"
SNAPSHOT_OUT="$(snapshot_asset_path "$STAGE_DIR")"
MANIFEST_OUT="$(manifest_path "$STAGE_DIR")"

if [ -d "$INPUT_PATH" ]; then
	require_snapshot_tree "$INPUT_PATH"
	tar czf "$SNAPSHOT_OUT" -C "$INPUT_PATH" blacklists
	EXTRACT_ROOT="$INPUT_PATH"
else
	cp "$INPUT_PATH" "$SNAPSHOT_OUT"
	mkdir -p "$TMP/extract"
	tar xzf "$SNAPSHOT_OUT" -C "$TMP/extract"
	require_snapshot_tree "$TMP/extract"
	EXTRACT_ROOT="$TMP/extract"
fi

CATEGORIES_COUNT="$(snapshot_categories_count "$EXTRACT_ROOT")"
DOMAINS_COUNT="$(snapshot_domains_count "$EXTRACT_ROOT")"
SNAPSHOT_SIZE="$(file_size_bytes "$SNAPSHOT_OUT")"
SNAPSHOT_SHA256="$(sha256_hex "$SNAPSHOT_OUT")"

{
	echo "manifest_version=1"
	echo "dataset=ut1"
	echo "snapshot_id=$SNAPSHOT_ID"
	echo "snapshot_asset=$(basename "$SNAPSHOT_OUT")"
	echo "snapshot_size=$SNAPSHOT_SIZE"
	echo "snapshot_sha256=$SNAPSHOT_SHA256"
	echo "categories_count=$CATEGORIES_COUNT"
	echo "domains_count=$DOMAINS_COUNT"
	echo "checksum_algorithm=sha256"
	echo "signing_scheme=ed25519-openssl-pkeyutl-v1"
	echo "publisher_role=publisher"
	echo "publisher_name=Layer7/Systemup"
	echo "published_at_utc=$(utc_now)"
	echo "upstream_role=content-authority"
	echo "upstream_name=UT1 Universite Toulouse Capitole"
	echo "upstream_url=$UPSTREAM_URL"
	echo "upstream_acquired_at_utc=$UPSTREAM_ACQUIRED_AT"
} > "$MANIFEST_OUT"

echo "Snapshot staged com sucesso."
echo "Stage dir: $STAGE_DIR"
echo "Manifesto: $(basename "$MANIFEST_OUT")"
echo "Snapshot: $(basename "$SNAPSHOT_OUT")"
echo "Categorias: $CATEGORIES_COUNT"
echo "Dominios: $DOMAINS_COUNT"
