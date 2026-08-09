#!/bin/sh
# Concurrent allow+block smoke on .54 (single-threaded server; backlog OK)
# TLS: verify cadeia via CAfile — sem curl -k.
set -eu
cd "$(dirname "$0")"
CRT=lab-certs/server.crt
KEY=lab-certs/server.key
# shellcheck source=tls-http-get.sh
. ./tls-http-get.sh
SPID="" UPID=""
cleanup() {
	[ -n "${SPID:-}" ] && kill -9 "$SPID" 2>/dev/null || true
	[ -n "${UPID:-}" ] && kill -9 "$UPID" 2>/dev/null || true
	for p in $(pgrep -x layer7-tlsproxy 2>/dev/null || true); do kill -9 "$p" 2>/dev/null || true; done
	for p in $(pgrep -f '[p]oc-upstream-stub' 2>/dev/null || true); do kill -9 "$p" 2>/dev/null || true; done
}
trap cleanup EXIT INT TERM
cleanup

python3 ./poc-upstream-stub.py 19080 >/dev/null 2>&1 &
UPID=$!
sleep 0.25
LAYER7_TLSPROXY_LAB=1 ./layer7-tlsproxy --lab-tls-listen 127.0.0.1:8443 \
	--cert "$CRT" --key "$KEY" \
	--upstream 127.0.0.1:19080 --block-sni blocked.test \
	>/tmp/conc.log 2>&1 &
SPID=$!
i=0
while [ "$i" -lt 40 ]; do
	kill -0 "$SPID" 2>/dev/null || { echo "proxy died"; cat /tmp/conc.log; exit 1; }
	if https_ready; then
		break
	fi
	i=$((i + 1))
	sleep 0.05
done

CN=$(openssl x509 -in "$CRT" -noout -subject 2>/dev/null |
	sed -n 's/.*CN *= *//p' | head -1)
[ -n "$CN" ] || CN=lab.local

# Warm sequential
https_body "$CN" | grep -q UPSTREAM_OK
https_body blocked.test | grep -q 'Acesso bloqueado'

# Parallel
https_body "$CN" >/tmp/a.out &
P1=$!
https_body blocked.test >/tmp/b.out &
P2=$!
wait "$P1"
wait "$P2"
grep -q UPSTREAM_OK /tmp/a.out
grep -q 'Acesso bloqueado' /tmp/b.out
echo CONC_PASS
