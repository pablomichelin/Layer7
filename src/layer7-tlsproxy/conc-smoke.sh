#!/bin/sh
# Concurrent allow+block smoke on .54 (single-threaded server; backlog OK)
set -eu
cd "$(dirname "$0")"
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
	--cert lab-certs/server.crt --key lab-certs/server.key \
	--upstream 127.0.0.1:19080 --block-sni blocked.test \
	>/tmp/conc.log 2>&1 &
SPID=$!
i=0
while [ "$i" -lt 40 ]; do
	kill -0 "$SPID" 2>/dev/null || { echo "proxy died"; cat /tmp/conc.log; exit 1; }
	if curl -sSk --connect-timeout 1 --max-time 1 -o /dev/null https://127.0.0.1:8443/ 2>/dev/null; then
		break
	fi
	i=$((i + 1))
	sleep 0.05
done

# Warm sequential
curl -sSk --max-time 3 https://127.0.0.1:8443/ | grep -q UPSTREAM_OK
curl -sSk --max-time 3 --resolve blocked.test:8443:127.0.0.1 https://blocked.test:8443/ | grep -q 'Acesso bloqueado'

# Parallel
curl -sSk --max-time 8 https://127.0.0.1:8443/ >/tmp/a.out &
P1=$!
curl -sSk --max-time 8 --resolve blocked.test:8443:127.0.0.1 https://blocked.test:8443/ >/tmp/b.out &
P2=$!
wait "$P1"
wait "$P2"
grep -q UPSTREAM_OK /tmp/a.out
grep -q 'Acesso bloqueado' /tmp/b.out
echo CONC_PASS
