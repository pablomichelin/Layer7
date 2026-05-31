#!/bin/sh
# F5 minima — runner local (executa sem appliance).
#
# Faz:
#   1. Compila e corre tests/functional/test_allowlist.c.
#   2. Verifica sintaxe PHP dos ficheiros do pacote (php -l).
#   3. Verifica sintaxe shell dos scripts do pacote (sh -n).
#
# Uso:  sh tests/run-local.sh

set -u
RC=0
ROOT=$(cd "$(dirname "$0")/.." && pwd)
cd "$ROOT" || exit 1

step() { printf "\n== %s ==\n" "$1"; }
pass() { printf "PASS: %s\n" "$1"; }
fail() { printf "FAIL: %s\n" "$1"; RC=1; }

CC_BIN=${CC:-cc}

step "Unit: allowlist"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_allowlist \
    tests/functional/test_allowlist.c src/layer7d/allowlist.c 2>/tmp/test_allowlist.cc.err; then
	if /tmp/test_allowlist; then
		pass "test_allowlist"
	else
		fail "test_allowlist runtime"
	fi
else
	cat /tmp/test_allowlist.cc.err
	fail "test_allowlist compile"
fi

step "Unit: config_parse (sni_inspection / A3)"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_config_parse \
    tests/functional/test_config_parse.c src/layer7d/config_parse.c 2>/tmp/test_config_parse.cc.err; then
	if /tmp/test_config_parse; then
		pass "test_config_parse"
	else
		fail "test_config_parse runtime"
	fi
else
	cat /tmp/test_config_parse.cc.err
	fail "test_config_parse compile"
fi

step "Lint: PHP do pacote (php -l)"
PHP_BIN=$(command -v php 2>/dev/null || true)
if [ -z "$PHP_BIN" ]; then
	printf "SKIP: php nao instalado, saltando syntax check\n"
else
	N_OK=0; N_FAIL=0
	for f in $(find package/pfSense-pkg-layer7/files -name '*.php' -o -name '*.inc' -o -name '*.priv.inc'); do
		if "$PHP_BIN" -l "$f" >/dev/null 2>&1; then
			N_OK=$((N_OK + 1))
		else
			"$PHP_BIN" -l "$f" || true
			N_FAIL=$((N_FAIL + 1))
		fi
	done
	if [ "$N_FAIL" -eq 0 ]; then
		pass "PHP syntax ($N_OK ficheiros OK)"
	else
		fail "PHP syntax ($N_FAIL erro(s) em $((N_OK + N_FAIL)) ficheiros)"
	fi
fi

step "Lint: sh do pacote (sh -n)"
N_OK=0; N_FAIL=0
for f in package/pfSense-pkg-layer7/files/usr/local/etc/rc.d/layer7d \
    package/pfSense-pkg-layer7/files/usr/local/libexec/layer7-pfctl \
    package/pfSense-pkg-layer7/files/usr/local/libexec/layer7-unbound-anti-doh \
    package/pfSense-pkg-layer7/files/usr/local/etc/layer7/*.sh \
    scripts/diagnose-layer7-appliance.sh \
    tests/lab/smoke-monitor-mode.sh \
    tests/lab/smoke-caminho-a.sh \
    tests/run-local.sh; do
	[ -f "$f" ] || continue
	if /bin/sh -n "$f" 2>/dev/null; then
		N_OK=$((N_OK + 1))
	else
		/bin/sh -n "$f"
		N_FAIL=$((N_FAIL + 1))
	fi
done
if [ "$N_FAIL" -eq 0 ]; then
	pass "sh syntax ($N_OK scripts OK)"
else
	fail "sh syntax ($N_FAIL erro(s) em $((N_OK + N_FAIL)) scripts)"
fi

step "Resumo"
if [ "$RC" -eq 0 ]; then
	printf "ALL LOCAL TESTS PASSED\n"
else
	printf "LOCAL TESTS FAILED (rc=%d)\n" "$RC"
fi
exit "$RC"
