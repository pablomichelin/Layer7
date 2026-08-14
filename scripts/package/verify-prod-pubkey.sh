#!/bin/sh
# verify-prod-pubkey.sh — GA1 / passo 30.2 / P3-6
# Compara a pubkey Ed25519 embutida em src/layer7d/license.c e o PEM do
# port com o SoT fora do git no builder (decisão 4: material da pubkey
# fora do git). Sem o PEM, daemon e GUI podem discordar no mesmo .lic.
#
# Uso (no builder):
#   sh scripts/package/verify-prod-pubkey.sh
#   L7_PROD_PUBKEY_HEX_FILE=/path/to/hex sh scripts/package/verify-prod-pubkey.sh
#   L7_PROD_PUBKEY_PEM_FILE=/path/to.pem sh scripts/package/verify-prod-pubkey.sh
#
# Exit 0 = C == PEM == SoT; exit 1 = mismatch ou ficheiros em falta.

set -eu

REPO_ROOT=$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)
LICENSE_C="${REPO_ROOT}/src/layer7d/license.c"
HEX_FILE="${L7_PROD_PUBKEY_HEX_FILE:-/root/layer7-build-secrets/l7_ed25519_pubkey.hex}"
PEM_FILE="${L7_PROD_PUBKEY_PEM_FILE:-${REPO_ROOT}/package/pfSense-pkg-layer7/files/usr/local/share/pfSense-pkg-layer7/license-signing-public-key.pem}"
OPENSSL_BIN="${OPENSSL_BIN:-openssl}"

if [ ! -f "$LICENSE_C" ]; then
	echo "FAIL: license.c não encontrado: $LICENSE_C" >&2
	exit 1
fi

if [ ! -f "$HEX_FILE" ]; then
	echo "FAIL: SoT hex em falta: $HEX_FILE" >&2
	echo "Crie o SoT no builder (passo 30.2) antes de empacotar." >&2
	exit 1
fi

expected=$(tr -d ' \n\t\r' < "$HEX_FILE" | tr 'A-F' 'a-f')
case "$expected" in
*[!0-9a-f]*|"")
	echo "FAIL: SoT hex inválido em $HEX_FILE" >&2
	exit 1
	;;
esac
if [ "${#expected}" -ne 64 ]; then
	echo "FAIL: SoT hex deve ter 64 nibbles (32 bytes); tem ${#expected}" >&2
	exit 1
fi

# Extrai os 32 bytes do array l7_ed25519_pubkey em license.c (só hex 0xNN).
actual=$(awk '
	/l7_ed25519_pubkey\[32\]/ { grab=1 }
	grab {
		while (match($0, /0x[0-9A-Fa-f][0-9A-Fa-f]/)) {
			b = tolower(substr($0, RSTART+2, 2))
			out = out b
			$0 = substr($0, RSTART+RLENGTH)
			n++
			if (n >= 32) { print out; exit }
		}
	}
	grab && /};/ && n > 0 { print out; exit }
' "$LICENSE_C")

if [ -z "$actual" ] || [ "${#actual}" -ne 64 ]; then
	echo "FAIL: não foi possível extrair 32 bytes de l7_ed25519_pubkey em $LICENSE_C" >&2
	exit 1
fi

if [ "$actual" != "$expected" ]; then
	echo "FAIL: pubkey em license.c diverge do SoT (GA1.8 / decisão 4)" >&2
	echo "  license.c: $actual" >&2
	echo "  SoT:       $expected" >&2
	exit 1
fi

if [ ! -f "$PEM_FILE" ]; then
	echo "FAIL: PEM do port em falta: $PEM_FILE" >&2
	exit 1
fi

if ! command -v "$OPENSSL_BIN" >/dev/null 2>&1 && [ ! -x "$OPENSSL_BIN" ]; then
	echo "FAIL: openssl em falta (necessário para alinhar PEM vs SoT)" >&2
	exit 1
fi

der_tmp=$(mktemp "${TMPDIR:-/tmp}/l7-pubkey-der.XXXXXX")
if ! "$OPENSSL_BIN" pkey -pubin -in "$PEM_FILE" -outform DER > "$der_tmp" 2>/dev/null; then
	rm -f "$der_tmp"
	echo "FAIL: PEM inválido (openssl pkey -pubin): $PEM_FILE" >&2
	exit 1
fi

der_sz=$(wc -c < "$der_tmp" | tr -d ' ')
if [ "$der_sz" -ne 44 ]; then
	rm -f "$der_tmp"
	echo "FAIL: PEM não é SPKI Ed25519 de 44 bytes (tem $der_sz): $PEM_FILE" >&2
	exit 1
fi

prefix=$(od -An -tx1 -N 12 "$der_tmp" | tr -d ' \n\t' | tr 'A-F' 'a-f')
if [ "$prefix" != "302a300506032b6570032100" ]; then
	rm -f "$der_tmp"
	echo "FAIL: PEM não tem OID Ed25519 (1.3.101.112): $PEM_FILE" >&2
	exit 1
fi

pem_hex=$(tail -c 32 "$der_tmp" | od -An -tx1 | tr -d ' \n\t' | tr 'A-F' 'a-f')
rm -f "$der_tmp"

if [ -z "$pem_hex" ] || [ "${#pem_hex}" -ne 64 ]; then
	echo "FAIL: não foi possível extrair 32 bytes do PEM: $PEM_FILE" >&2
	exit 1
fi

if [ "$pem_hex" != "$expected" ] || [ "$pem_hex" != "$actual" ]; then
	echo "FAIL: PEM do port diverge do SoT ou do array C (P3-6 / GA1.8)" >&2
	echo "  PEM:       $pem_hex" >&2
	echo "  license.c: $actual" >&2
	echo "  SoT:       $expected" >&2
	exit 1
fi

echo "PASS: license.c pubkey == PEM do port == SoT"
echo "pubkey_hex=$actual"
exit 0
