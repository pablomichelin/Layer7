#!/bin/sh
# Smoke S8 — runtime presente e OFF (lab .54 apenas).
# Uso: sh smoke-s8-runtime-present-off.sh
# NÃO corre em .254/.234/.235.

set -eu
HOST="${LAYER7_POC_HOST:-192.168.100.54}"

ssh -o BatchMode=yes -o ConnectTimeout=8 "root@${HOST}" 'bash -s' <<'REMOTE'
set -e
BIN=/opt/layer7-poc/src/layer7-tlsproxy
test -x "$BIN"
cd /opt/layer7-poc/src
sh ./lab-inline-down.sh >/dev/null || true
LAYER7_TLSPROXY_LAB=1 "$BIN" --health | grep -q '"mitm_effective_claim": false'
LAYER7_TLSPROXY_LAB=1 "$BIN" --health | grep -q '"intercept": false'
pgrep -a layer7 >/dev/null 2>&1 && { echo "FAIL: process running"; exit 1; } || true
ss -lnt | grep -E ':(8443|443)\s' && { echo "FAIL: listener"; exit 1; } || true
ip netns list 2>/dev/null | grep -q l7poccli && { echo "FAIL: netns"; exit 1; } || true
iptables -t nat -S 2>/dev/null | grep -qi REDIRECT && { echo "FAIL: REDIRECT"; exit 1; } || true
code=$(curl -sS -o /dev/null -w "%{http_code}" --max-time 12 http://example.com || echo fail)
echo "runtime_present=yes service=off intercept=no example_http=$code"
test "$code" = "200"
echo "PASS s8-runtime-present-off"
REMOTE
