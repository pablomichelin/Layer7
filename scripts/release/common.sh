#!/bin/sh
# Funcoes comuns da cadeia de release F1.2

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

host_name_safe() {
  hostname -s 2>/dev/null || hostname 2>/dev/null || echo unknown
}

manifest_name() {
  echo "release-manifest.v1.txt"
}

signature_name() {
  echo "release-manifest.v1.txt.sig"
}

public_key_asset_name() {
  echo "release-signing-public-key.pem"
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

fingerprint_public_key_sha256() {
  pubkey="$1"
  openssl pkey -pubin -in "$pubkey" -outform DER 2>/dev/null \
    | openssl dgst -sha256 \
    | awk '{print $2}'
}

write_sha256_file() {
  src="$1"
  out="$2"
  printf '%s  %s\n' "$(sha256_hex "$src")" "$(basename "$src")" > "$out"
}

manifest_value() {
  key="$1"
  manifest="$2"
  sed -n "s/^${key}=//p" "$manifest" | head -1
}

emit_asset_line() {
  file="$1"
  role="$2"
  printf 'asset|name=%s|role=%s|size=%s|sha256=%s\n' \
    "$(basename "$file")" \
    "$role" \
    "$(file_size_bytes "$file")" \
    "$(sha256_hex "$file")"
}
