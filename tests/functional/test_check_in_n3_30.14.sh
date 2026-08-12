#!/bin/sh
# test_check_in_n3_30.14.sh — N3 factual: falha rede/unsigned não invalida via tick.
#
# Uso: sh tests/functional/test_check_in_n3_30.14.sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)
MAIN="$ROOT/src/layer7d/main.c"
LIC="$ROOT/src/layer7d/license.c"
fail=0

pass() { echo "PASS: $1"; }
bad() { echo "FAIL: $1"; fail=1; }

tick=$(sed -n '/^license_checkin_tick(/,/^allow_cache_add(/p' "$MAIN" | sed '$d')

echo "$tick" | grep -q 'layer7_checkin_config_enabled' || bad "tick le config enabled"
echo "$tick" | grep -q 'L7_CHECKIN_DENIED' || bad "tick trata DENIED"
echo "$tick" | grep -q 'L7_CHECKIN_OFFLINE_MAX' || bad "tick trata OFFLINE_MAX"

# Invalidacao remota no tick: apenas DENIED ou OFFLINE_MAX
denied_block=$(echo "$tick" | sed -n '/result == L7_CHECKIN_DENIED/,/else if (result == L7_CHECKIN_OK)/p')
echo "$denied_block" | grep -q 'license_apply_invalidation' \
	&& pass "tick invalida so em DENIED/OFFLINE_MAX" \
	|| bad "tick sem invalidate em DENIED/OFFLINE_MAX"

# NETWORK nao causa invalidate no tick
if echo "$tick" | grep -q 'L7_CHECKIN_NETWORK'; then
	bad "tick nao deve ramificar NETWORK para invalidate"
else
	pass "tick ignora NETWORK (N3 — sem invalidate)"
fi

# layer7_check_in: unsigned => NETWORK; invalidate so revoked/expired
ci=$(awk '/^layer7_check_in\(/,/^layer7_checkin_get_status\(/' "$LIC")
echo "$ci" | grep -q 'unsigned or invalid check-in response' || bad "unsigned path ausente"
echo "$ci" | grep -q 'license server unreachable' || bad "unreachable path ausente"

# Exactamente uma chamada a checkin_invalidate_local, dentro do bloco revoked/expired
inv_ci=$(echo "$ci" | grep -c 'checkin_invalidate_local' || true)
[ "$inv_ci" -eq 1 ] && pass "check_in invalidate count=1" || bad "check_in invalidate count=$inv_ci"

rev_block=$(echo "$ci" | sed -n '/strcmp(status, "revoked")/,/return L7_CHECKIN_DENIED;/p')
echo "$rev_block" | grep -q 'checkin_invalidate_local' \
	&& pass "invalidate apenas no bloco revoked/expired" \
	|| bad "invalidate fora do bloco revoked/expired"

# Apos unsigned, retorno NETWORK (nao DENIED)
un_block=$(echo "$ci" | sed -n '/unsigned or invalid check-in response/,/return L7_CHECKIN_NETWORK;/p' | head -20)
echo "$un_block" | grep -q 'L7_CHECKIN_NETWORK' \
	&& pass "unsigned => L7_CHECKIN_NETWORK" \
	|| bad "unsigned nao retorna NETWORK"
echo "$un_block" | grep -q 'checkin_invalidate_local' \
	&& bad "unsigned invalida licenca (quebra N3)" \
	|| pass "unsigned nao chama checkin_invalidate_local"

if [ "$fail" -ne 0 ]; then
	echo "RESULT: FAIL"
	exit 1
fi
echo "RESULT: PASS"
exit 0
