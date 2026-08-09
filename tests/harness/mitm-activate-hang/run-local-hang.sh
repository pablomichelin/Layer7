#!/bin/sh
# Reprodução segura do hang: exec(service layer7-tlsproxy onerestart) sem timeout.
# Não toca .254, PF, licença nem package.
set -eu

HDIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
MOCK="$HDIR/mock-bin"
PHP_SCRIPT="$HDIR/php-sync-exec-pattern.php"
WORK=$(mktemp -d "${TMPDIR:-/tmp}/l7-mitm-hang.XXXXXX")
HANG_SLEEP=${HANG_SLEEP:-8}
HANG_TIMEOUT=${HANG_TIMEOUT:-5}
export L7_MOCK_SERVICE_SLEEP="$HANG_SLEEP"
export L7_MOCK_SERVICE_LOG="$WORK/mock-service.log"
export L7_HARNESS_MARKER="$WORK/marker.txt"
export L7_HARNESS_SYSRC="$MOCK/sysrc"
export L7_HARNESS_SERVICE="$MOCK/service"

cleanup() {
	if [ -n "${PHP_PID:-}" ] && kill -0 "$PHP_PID" 2>/dev/null; then
		kill -TERM "$PHP_PID" 2>/dev/null || true
		sleep 0.2
		kill -KILL "$PHP_PID" 2>/dev/null || true
	fi
}
trap cleanup EXIT

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
		echo "FAIL: php nao encontrado (local: brew php; builder/lab: /usr/local/bin/php)"
		echo "HINT: scp -r tests/harness/mitm-activate-hang root@192.168.100.12:/tmp/ && ssh root@192.168.100.12 sh /tmp/mitm-activate-hang/run-local-hang.sh"
		exit 2
	fi
fi
echo "PHP_BIN=$PHP_BIN"

echo "HARNESS=mitm-activate-hang"
echo "WORK=$WORK"
echo "HANG_SLEEP=$HANG_SLEEP HANG_TIMEOUT=$HANG_TIMEOUT"
echo "SERVICE_MOCK=$L7_HARNESS_SERVICE"

OUT="$WORK/php.out"
"$PHP_BIN" "$PHP_SCRIPT" >"$OUT" 2>"$WORK/php.err" &
PHP_PID=$!
echo "PHP_PID=$PHP_PID"

i=0
while [ "$i" -lt "$HANG_TIMEOUT" ]; do
	if ! kill -0 "$PHP_PID" 2>/dev/null; then
		wait "$PHP_PID" || true
		echo "FAIL: PHP terminou antes do timeout (nao bloqueou)"
		echo "--- php.out ---"; cat "$OUT" || true
		echo "--- mock log ---"; cat "$L7_MOCK_SERVICE_LOG" || true
		exit 1
	fi
	# Ainda nao pode ter impresso sync=yes se estiver bloqueado no exec
	if grep -q '^sync=yes$' "$OUT" 2>/dev/null; then
		echo "FAIL: sync=yes apareceu antes do timeout (onerestart nao bloqueou)"
		exit 1
	fi
	sleep 1
	i=$((i + 1))
done

if ! kill -0 "$PHP_PID" 2>/dev/null; then
	echo "FAIL: PHP morreu exactamente no limite sem prova de bloqueio"
	exit 1
fi

if ! grep -q '^effective_pre_sync=yes$' "$OUT" 2>/dev/null; then
	echo "FAIL: falta effective_pre_sync=yes (padrao eco B+D)"
	exit 1
fi

if grep -q '^sync=yes$' "$OUT" 2>/dev/null; then
	echo "FAIL: sync=yes durante hang (inesperado)"
	exit 1
fi

echo "STATE_AT_TIMEOUT: php_alive=yes sync_echo=no (bloqueado em onerestart mock)"
kill -TERM "$PHP_PID" 2>/dev/null || true
wait "$PHP_PID" 2>/dev/null || true
PHP_PID=""

echo "--- php.out ---"
cat "$OUT"
echo "--- mock-service.log ---"
cat "$L7_MOCK_SERVICE_LOG" || true
echo "--- marker ---"
cat "$L7_HARNESS_MARKER" || true

# Correlaciona com B+D: ecos parciais + Terminated sem sync=
if grep -q '^effective_pre_sync=yes$' "$OUT" && ! grep -q '^sync=yes$' "$OUT"; then
	echo "PASS: padrao de hang reproduzido (exec onerestart bloqueia antes de sync=)"
	echo "EVIDENCE_ALIGN=20260809T185035Z-phaseBD (effective_pre_sync sim; sync= nao; SIGTERM)"
	exit 0
fi

echo "FAIL: correlacao incompleta"
exit 1
