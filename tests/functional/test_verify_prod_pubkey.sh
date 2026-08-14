#!/bin/sh
# test_verify_prod_pubkey.sh — P3-6 / BG-144
#
# Selftest local do gate verify-prod-pubkey.sh com fixture isolada:
#   L7_PROD_PUBKEY_HEX_FILE + PEM temporário construído do array C.
#   Não lê o PEM versionado do port nem /root/layer7-build-secrets.
#
# Uso: sh tests/functional/test_verify_prod_pubkey.sh

set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)"
GATE="$ROOT/scripts/package/verify-prod-pubkey.sh"
LICENSE_C="$ROOT/src/layer7d/license.c"
TESTDIR="${TMPDIR:-/tmp}/layer7-p36-pubkey.$$"
OPENSSL_BIN="${OPENSSL_BIN:-openssl}"

fail() {
	echo "FAIL: $*" >&2
	rm -rf "$TESTDIR"
	exit 1
}

pass() {
	echo "PASS: $*"
}

rm -rf "$TESTDIR"
mkdir -p "$TESTDIR"

command -v "$OPENSSL_BIN" >/dev/null 2>&1 || fail "openssl em falta"

# Hex SoT extraído do array C (mesmo awk do gate) — fixture local.
c_hex=$(awk '
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

[ "${#c_hex}" -eq 64 ] || fail "não extraí 32 bytes de license.c"

printf '%s\n' "$c_hex" > "$TESTDIR/sot.hex"

# 32 bytes raw a partir do hex, sem xxd obrigatório.
hex_to_bin() {
	_h="$1"
	_i=1
	while [ "$_i" -le 64 ]; do
		_b=$(printf '%s' "$_h" | cut -c "$_i-$((_i + 1))")
		printf "\\$(printf '%03o' $((0x${_b})))"
		_i=$((_i + 2))
	done
}

# PEM temporário alinhado: SPKI Ed25519 (12 B + 32 B) a partir do hex C.
{
	printf '\060\052\060\005\006\003\053\145\160\003\041\000'
	hex_to_bin "$c_hex"
} > "$TESTDIR/aligned.der"
[ "$(wc -c < "$TESTDIR/aligned.der" | tr -d ' ')" -eq 44 ] \
	|| fail "DER alinhado não tem 44 bytes"
"$OPENSSL_BIN" pkey -pubin -inform DER -in "$TESTDIR/aligned.der" \
	-out "$TESTDIR/aligned.pem" 2>/dev/null \
	|| fail "não gerei PEM temporário alinhado"

run_gate() {
	env -u L7_PROD_PUBKEY_INC_FILE \
		L7_PROD_PUBKEY_HEX_FILE="$TESTDIR/sot.hex" \
		L7_PROD_PUBKEY_PEM_FILE="$1" \
		OPENSSL_BIN="$OPENSSL_BIN" \
		sh "$GATE"
}

assert_no_secrets_path() {
	if echo "$1" | grep -q 'layer7-build-secrets'; then
		fail "saída menciona /root/layer7-build-secrets"
	fi
}

# pass-after: PEM temporário construído do array C
if out=$(run_gate "$TESTDIR/aligned.pem" 2>&1); then
	assert_no_secrets_path "$out"
	echo "$out" | grep -q 'license.c pubkey == PEM do port == SoT' \
		|| fail "PASS sem mensagem de alinhamento C/PEM/SoT"
	pass "SoT=C=PEM temporário alinhado"
else
	echo "$out" >&2
	fail "PEM temporário alinhado deveria PASS"
fi

# fail-after: outra Ed25519
"$OPENSSL_BIN" genpkey -algorithm ED25519 -out "$TESTDIR/other.key" 2>/dev/null \
	|| fail "genpkey"
"$OPENSSL_BIN" pkey -in "$TESTDIR/other.key" -pubout -out "$TESTDIR/other.pem" 2>/dev/null \
	|| fail "pubout"
if out=$(run_gate "$TESTDIR/other.pem" 2>&1); then
	echo "$out" >&2
	fail "PEM de outra Ed25519 deveria FAIL (P3-6)"
fi
assert_no_secrets_path "$out"
echo "$out" | grep -q 'PEM do port diverge do SoT ou do array C' \
	|| fail "FAIL da outra Ed25519 sem mensagem P3-6"
pass "PEM temporário de outra Ed25519 → FAIL"

# fail-after: PEM em falta
if out=$(run_gate "$TESTDIR/missing.pem" 2>&1); then
	echo "$out" >&2
	fail "PEM em falta deveria FAIL"
fi
assert_no_secrets_path "$out"
echo "$out" | grep -q 'PEM do port em falta' \
	|| fail "FAIL de PEM em falta sem mensagem"
pass "PEM em falta → FAIL"

# fail-after: PEM inválido
printf '%s\n' 'not-a-pem' > "$TESTDIR/bad.pem"
if out=$(run_gate "$TESTDIR/bad.pem" 2>&1); then
	echo "$out" >&2
	fail "PEM inválido deveria FAIL"
fi
assert_no_secrets_path "$out"
echo "$out" | grep -q 'PEM inválido' \
	|| fail "FAIL de PEM inválido sem mensagem"
pass "PEM inválido → FAIL"

# regressão: SoT ≠ C continua a falhar antes do PEM
printf '%s\n' '0000000000000000000000000000000000000000000000000000000000000000' \
	> "$TESTDIR/wrong.hex"
if out=$(
	env -u L7_PROD_PUBKEY_INC_FILE \
		L7_PROD_PUBKEY_HEX_FILE="$TESTDIR/wrong.hex" \
		L7_PROD_PUBKEY_PEM_FILE="$TESTDIR/aligned.pem" \
		OPENSSL_BIN="$OPENSSL_BIN" \
		sh "$GATE" 2>&1
); then
	echo "$out" >&2
	fail "SoT ≠ C deveria FAIL"
fi
assert_no_secrets_path "$out"
echo "$out" | grep -q 'license.c diverge do SoT' \
	|| fail "FAIL SoT≠C sem mensagem GA1.8"
pass "SoT ≠ C → FAIL (regressão GA1.8)"

rm -rf "$TESTDIR"
echo "PASS: test_verify_prod_pubkey (P3-6)"
exit 0
