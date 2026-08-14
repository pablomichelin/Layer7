#!/bin/sh
# Contrato P1-6/P1-7/P1-8/P2-12 — pkg-deinstall.in + uninstall.sh.
# Sem host: só asserções de ficheiro (ramos keep / PKG_UPGRADE / leftovers).

set -eu

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
DEINSTALL="${ROOT}/package/pfSense-pkg-layer7/files/pkg-deinstall.in"
UNINSTALL="${ROOT}/scripts/release/uninstall.sh"
REMOVAL="${ROOT}/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_removal.php"

fail=0

assert_grep() {
	_name="$1"
	_pattern="$2"
	_file="$3"
	if grep -q "$_pattern" "$_file"; then
		printf "PASS: %s\n" "$_name"
	else
		printf "FAIL: %s\n" "$_name"
		fail=1
	fi
}

assert_grep "pkg-deinstall define _is_upgrade via PKG_UPGRADE" \
    '_is_upgrade=1' "$DEINSTALL"
assert_grep "pkg-deinstall nao apaga json/lic em upgrade" \
    'upgrade nunca apaga json/.lic' "$DEINSTALL"
assert_grep "pkg-deinstall backup mitm em keep/upgrade" \
    'layer7-mitm.bak' "$DEINSTALL"
assert_grep "pkg-deinstall backup identity-ldap.secret" \
    'identity-ldap.secret' "$DEINSTALL"
assert_grep "pkg-deinstall backup identity-radius.secret" \
    'identity-radius.secret' "$DEINSTALL"
assert_grep "pkg-deinstall backup identity-dc.secret" \
    'identity-dc.secret' "$DEINSTALL"
assert_grep "pkg-deinstall apaga checkin no uninstall real" \
    'layer7-checkin.json' "$DEINSTALL"
assert_grep "pkg-deinstall apaga clock-mark no uninstall real" \
    'clock-mark.json' "$DEINSTALL"
assert_grep "pkg-deinstall apaga content-subscription no uninstall real" \
    'content-subscription.json' "$DEINSTALL"
assert_grep "pkg-deinstall PRE chama layer7_remove_unbound_anti_doh" \
    'layer7_remove_unbound_anti_doh' "$DEINSTALL"
assert_grep "pkg-deinstall anti-DoH so sem PKG_UPGRADE" \
    'anti-DoH só em uninstall real' "$DEINSTALL"

assert_grep "uninstall.sh apaga checkin no uninstall completo" \
    'layer7-checkin.json' "$UNINSTALL"
assert_grep "uninstall.sh keep-config preserva mitm" \
    'layer7-mitm.bak' "$UNINSTALL"
assert_grep "uninstall.sh keep-config preserva identity secrets" \
    'identity-ldap.secret' "$UNINSTALL"
assert_grep "uninstall.sh keep-config preserva identity-dc.secret" \
    'identity-dc.secret' "$UNINSTALL"

assert_grep "GUI keep-config menciona CA MITM" \
    'CA MITM' "$REMOVAL"

if [ "$fail" -ne 0 ]; then
	printf "RESULT: FAIL\n"
	exit 1
fi
printf "RESULT: PASS\n"
exit 0
