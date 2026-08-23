#!/bin/sh
# BG-162 — fingerprint inalterado; pkg-install e libexec fail-open.
set -eu
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
fail=0
pass() { echo "PASS: $1"; }
failmsg() { echo "FAIL: $1"; fail=1; }

LICENSE_C="$ROOT/src/layer7d/license.c"
MAIN_C="$ROOT/src/layer7d/main.c"
PKG_INSTALL="$ROOT/package/pfSense-pkg-layer7/files/pkg-install.in"
LIBEXEC="$ROOT/package/pfSense-pkg-layer7/files/usr/local/libexec/layer7-install-ping"

if grep -F 'snprintf(combined, sizeof(combined), "%s:%s", uuid, mac);' "$LICENSE_C" >/dev/null; then
	pass "fingerprint formula hostuuid:mac inalterada"
else
	failmsg "fingerprint formula hostuuid:mac inalterada"
fi

if grep -F '/usr/local/libexec/layer7-install-ping' "$MAIN_C" >/dev/null \
	&& grep -F 'L7_INSTALL_PING_INTERVAL_SEC 86400' "$MAIN_C" >/dev/null \
	&& grep -F '>/dev/null 2>&1 &' "$MAIN_C" >/dev/null; then
	pass "layer7d tick 24h fail-open em background"
else
	failmsg "layer7d tick 24h fail-open em background"
fi

if grep -F 'layer7-install-ping' "$PKG_INSTALL" >/dev/null \
	&& grep -E 'layer7-install-ping".*>/dev/null 2>&1 &' "$PKG_INSTALL" >/dev/null; then
	pass "pkg-install.in dispara ping em background"
else
	failmsg "pkg-install.in dispara ping em background"
fi

if grep -F 'fail-open' "$LIBEXEC" >/dev/null \
	&& grep -E '^exit 0$' "$LIBEXEC" >/dev/null; then
	pass "libexec fail-open (exit 0)"
else
	failmsg "libexec fail-open (exit 0)"
fi

exit "$fail"
