#!/bin/sh
# Prova o padrao CORRIGIDO: layer7_exec_timeout corta onerestart mock e
# o activador regressa (nao precisa SIGTERM externo).
set -eu

HDIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
MOCK="$HDIR/mock-bin"
PHP_SCRIPT="$HDIR/php-sync-fixed-pattern.php"
WORK=$(mktemp -d "${TMPDIR:-/tmp}/l7-mitm-fixed.XXXXXX")
HANG_SLEEP=${HANG_SLEEP:-8}
CTRL_TIMEOUT=${CTRL_TIMEOUT:-2}
export L7_MOCK_SERVICE_SLEEP="$HANG_SLEEP"
export L7_MOCK_SERVICE_LOG="$WORK/mock-service.log"
export L7_HARNESS_MARKER="$WORK/marker.txt"
export L7_HARNESS_SERVICE="$MOCK/service"
export L7_HARNESS_CTRL_TIMEOUT="$CTRL_TIMEOUT"

chmod +x "$MOCK/service" "$MOCK/sysrc"
PHP_BIN=${PHP_BIN:-}
if [ -z "$PHP_BIN" ]; then
	if command -v php >/dev/null 2>&1; then
		PHP_BIN=$(command -v php)
	elif [ -x /usr/local/bin/php ]; then
		PHP_BIN=/usr/local/bin/php
	elif [ -x /opt/homebrew/bin/php ]; then
		PHP_BIN=/opt/homebrew/bin/php
	else
		echo "FAIL: php nao encontrado"
		exit 2
	fi
fi

echo "HARNESS=mitm-activate-hang-fixed"
echo "PHP_BIN=$PHP_BIN WORK=$WORK"
echo "HANG_SLEEP=$HANG_SLEEP CTRL_TIMEOUT=$CTRL_TIMEOUT"

OUT="$WORK/php.out"
ERR="$WORK/php.err"
set +e
"$PHP_BIN" "$PHP_SCRIPT" >"$OUT" 2>"$ERR"
RC=$?
set -e

echo "--- php.out ---"
cat "$OUT" || true
echo "--- php.err ---"
cat "$ERR" || true
echo "--- mock ---"
cat "$L7_MOCK_SERVICE_LOG" || true

if [ "$RC" -ne 0 ]; then
	echo "FAIL: php exit=$RC"
	exit 1
fi
if ! grep -q '^PASS_FIXED=yes$' "$OUT"; then
	echo "FAIL: falta PASS_FIXED"
	exit 1
fi
if ! grep -q '^sync=fail_cleaned$' "$OUT"; then
	echo "FAIL: falta sync=fail_cleaned"
	exit 1
fi
if ! grep -q '^timed_out=yes$' "$OUT"; then
	echo "FAIL: falta timed_out=yes"
	exit 1
fi

echo "PASS: padrao corrigido regressa com timeout (sem hang ate SIGTERM)"
exit 0
