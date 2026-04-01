#!/bin/sh
# Funcoes comuns da cadeia segura de blacklists F1.3

die() {
	echo "ERRO: $*" >&2
	exit 1
}

require_cmd() {
	command -v "$1" >/dev/null 2>&1 || die "comando obrigatorio nao encontrado: $1"
}

sha256_hex() {
	file="$1"
	if command -v sha256 >/dev/null 2>&1; then
		sha256 -q "$file"
	elif command -v sha256sum >/dev/null 2>&1; then
		sha256sum "$file" | awk '{print $1}'
	elif command -v shasum >/dev/null 2>&1; then
		shasum -a 256 "$file" | awk '{print $1}'
	else
		die "nenhum comando sha256 disponivel"
	fi
}

file_size_bytes() {
	file="$1"
	if stat -f '%z' "$file" >/dev/null 2>&1; then
		stat -f '%z' "$file"
	elif stat -c '%s' "$file" >/dev/null 2>&1; then
		stat -c '%s' "$file"
	else
		wc -c < "$file" | tr -d ' '
	fi
}

utc_now() {
	date -u '+%Y-%m-%dT%H:%M:%SZ'
}

manifest_name() {
	echo "layer7-blacklists-manifest.v1.txt"
}

signature_name() {
	echo "layer7-blacklists-manifest.v1.txt.sig"
}

public_key_asset_name() {
	echo "blacklists-signing-public-key.pem"
}

snapshot_asset_name() {
	echo "layer7-blacklists-ut1.tar.gz"
}

manifest_path() {
	stage_dir="$1"
	echo "$stage_dir/$(manifest_name)"
}

signature_path() {
	stage_dir="$1"
	echo "$stage_dir/$(signature_name)"
}

public_key_asset_path() {
	stage_dir="$1"
	echo "$stage_dir/$(public_key_asset_name)"
}

snapshot_asset_path() {
	stage_dir="$1"
	echo "$stage_dir/$(snapshot_asset_name)"
}

fingerprint_public_key_sha256() {
	pubkey="$1"
	openssl pkey -pubin -in "$pubkey" -outform DER 2>/dev/null \
		| openssl dgst -sha256 \
		| awk '{print $2}'
}

manifest_value() {
	key="$1"
	manifest="$2"
	sed -n "s/^${key}=//p" "$manifest" | head -1
}

snapshot_categories_count() {
	root="$1"
	find "$root/blacklists" -mindepth 1 -maxdepth 1 -type d 2>/dev/null \
		| wc -l | tr -d ' '
}

snapshot_domains_count() {
	root="$1"
	find "$root/blacklists" -type f -name domains 2>/dev/null \
		| while IFS= read -r file; do
			wc -l < "$file"
		done \
		| awk '{sum += $1} END {print sum + 0}'
}

require_snapshot_tree() {
	root="$1"
	[ -d "$root/blacklists" ] || die "snapshot sem directorio blacklists/"
	count="$(snapshot_categories_count "$root")"
	[ "$count" -gt 0 ] || die "snapshot sem categorias validas"
}
