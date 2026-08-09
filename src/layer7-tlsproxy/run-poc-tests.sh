#!/bin/sh
# Foreground-friendly PoC tests — make must NOT background long listeners.
# Usage: timeout 25 sh run-poc-tests.sh poc3|poc4
# TLS: cadeia verificada via -CAfile (sem curl -k / CERT_NONE).
set -eu
cd "$(dirname "$0")"
MODE=${1:-poc3}
OUT=./layer7-tlsproxy
CRT=lab-certs/server.crt
KEY=lab-certs/server.key
STUB=${STUB:-../../scripts/lab/poc-upstream-stub.py}

test -x "$OUT"
test -f "$CRT" -a -f "$KEY"
# shellcheck source=tls-http-get.sh
. ./tls-http-get.sh

cleanup() {
	if [ -n "${SPID:-}" ]; then kill -9 "$SPID" 2>/dev/null || true; fi
	if [ -n "${UPID:-}" ]; then kill -9 "$UPID" 2>/dev/null || true; fi
	for p in $(pgrep -x layer7-tlsproxy 2>/dev/null || true); do kill -9 "$p" 2>/dev/null || true; done
	for p in $(pgrep -f '[p]oc-upstream-stub' 2>/dev/null || true); do kill -9 "$p" 2>/dev/null || true; done
}
trap cleanup EXIT INT TERM

start_proxy() {
	LAYER7_TLSPROXY_LAB=1 "$OUT" --lab-tls-listen 127.0.0.1:8443 \
		--cert "$CRT" --key "$KEY" "$@" >/tmp/l7-poc-srv.log 2>&1 &
	SPID=$!
	i=0
	while [ "$i" -lt 40 ]; do
		kill -0 "$SPID" 2>/dev/null || { echo "proxy died"; cat /tmp/l7-poc-srv.log; exit 1; }
		if https_ready; then
			return 0
		fi
		i=$((i + 1))
		sleep 0.05
	done
	echo "proxy not ready"; cat /tmp/l7-poc-srv.log; exit 1
}

case "$MODE" in
poc3)
	start_proxy --block-sni blocked.test --bypass-sni bank.example
	echo "$(https_body blocked.test)" | grep -q 'Acesso bloqueado'
	echo "$(https_body bank.example)" | grep -q '"verdict":"bypass"'
	echo "$(https_body other.test)" | grep -q '"verdict":"allow"'
	echo "PoC-3 S3/S4 lab PASS"
	;;
poc4)
	test -f "$STUB"
	python3 "$STUB" 19080 >/tmp/l7-up.log 2>&1 &
	UPID=$!
	sleep 0.3
	start_proxy --upstream 127.0.0.1:19080
	BODY=$(https_body "$(openssl x509 -in "$CRT" -noout -subject |
		sed -n 's/.*CN *= *//p' | head -1)")
	[ -n "$BODY" ] || BODY=$(https_body lab.local)
	echo "$BODY" | grep -q UPSTREAM_OK
	echo "PoC-4 upstream PASS"
	;;
*)
	echo "usage: $0 poc3|poc4" >&2
	exit 2
	;;
esac
