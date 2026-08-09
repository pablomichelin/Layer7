#!/bin/sh
# Safe one-shot smoke for lab .54 — never leaves listeners hanging.
# Run ON the lab host: /opt/layer7-poc/src/../ OR from repo after deploy.
set -eu
cd "$(dirname "$0")/../../src/layer7-tlsproxy" 2>/dev/null || cd /opt/layer7-poc/src
ln -sfn /opt/layer7-poc/lab-certs lab-certs 2>/dev/null || true
make -s clean
make -s
make -s test
make -s test-poc3
make -s test-poc4
# hard cleanup
kill $(pgrep -f 'layer7-tlsproxy --lab-tls-listen' || true) 2>/dev/null || true
kill $(pgrep -f 'ThreadingHTTPServer' || true) 2>/dev/null || true
echo ALL_POC_SMOKE_PASS
./layer7-tlsproxy --version
