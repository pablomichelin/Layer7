#!/bin/sh
# generate-release-signing-key.sh — Gera um par Ed25519 para assinatura de releases
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
. "$SCRIPT_DIR/common.sh"

usage() {
  echo "Uso: $0 --out-dir DIR [--basename layer7-release-ed25519]"
  echo ""
  echo "Executar apenas no signer, nunca no builder."
  exit 1
}

OUT_DIR=""
BASENAME="layer7-release-ed25519"

while [ $# -gt 0 ]; do
  case "$1" in
    --out-dir) OUT_DIR="$2"; shift 2 ;;
    --basename) BASENAME="$2"; shift 2 ;;
    *) usage ;;
  esac
done

[ -n "$OUT_DIR" ] || usage

require_cmd openssl

mkdir -p "$OUT_DIR"
chmod 700 "$OUT_DIR" 2>/dev/null || true

PRIVATE_KEY="$OUT_DIR/${BASENAME}.pem"
PUBLIC_KEY="$OUT_DIR/${BASENAME}.pub.pem"

[ ! -e "$PRIVATE_KEY" ] || die "private key ja existe: $PRIVATE_KEY"
[ ! -e "$PUBLIC_KEY" ] || die "public key ja existe: $PUBLIC_KEY"

openssl genpkey -algorithm Ed25519 -out "$PRIVATE_KEY" >/dev/null 2>&1
chmod 600 "$PRIVATE_KEY"
openssl pkey -in "$PRIVATE_KEY" -pubout -out "$PUBLIC_KEY" >/dev/null 2>&1
chmod 644 "$PUBLIC_KEY"

echo "Par de chaves gerado com sucesso."
echo "Private key: $PRIVATE_KEY"
echo "Public key:  $PUBLIC_KEY"
echo "Fingerprint SHA256: $(fingerprint_public_key_sha256 "$PUBLIC_KEY")"
echo ""
echo "Proximos passos:"
echo "  1. manter a private key fora do builder e fora do repositorio"
echo "  2. distribuir a public key pelo canal definido em docs/06-releases/RELEASE-SIGNING.md"
