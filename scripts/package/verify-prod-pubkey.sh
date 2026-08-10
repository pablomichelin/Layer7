#!/bin/sh
# verify-prod-pubkey.sh — GA1 / passo 30.2
# Compara a pubkey Ed25519 embutida em src/layer7d/license.c com o SoT
# fora do git no builder (decisão 4: material da pubkey fora do git).
#
# Uso (no builder):
#   sh scripts/package/verify-prod-pubkey.sh
#   L7_PROD_PUBKEY_HEX_FILE=/path/to/hex sh scripts/package/verify-prod-pubkey.sh
#
# Exit 0 = match; exit 1 = mismatch ou ficheiros em falta.

set -eu

REPO_ROOT=$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)
LICENSE_C="${REPO_ROOT}/src/layer7d/license.c"
HEX_FILE="${L7_PROD_PUBKEY_HEX_FILE:-/root/layer7-build-secrets/l7_ed25519_pubkey.hex}"
INC_FILE="${L7_PROD_PUBKEY_INC_FILE:-/root/layer7-build-secrets/l7_ed25519_pubkey.inc}"

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
	echo "  SoT file:  $HEX_FILE" >&2
	if [ -f "$INC_FILE" ]; then
		echo "  inc file:  $INC_FILE (referência)" >&2
	fi
	exit 1
fi

echo "PASS: license.c pubkey == SoT ($HEX_FILE)"
echo "pubkey_hex=$actual"
exit 0
