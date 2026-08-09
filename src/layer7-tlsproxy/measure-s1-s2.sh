#!/bin/sh
# S1/S2 load measurement on lab .54 — foreground, timeout-friendly, always cleanup.
# TLS: CERT_REQUIRED + CAfile (proibido CERT_NONE / curl -k).
set -eu
cd "$(dirname "$0")"
OUT=./layer7-tlsproxy
CRT=lab-certs/server.crt
KEY=lab-certs/server.key
STUB=./poc-upstream-stub.py
N=${N:-100}
SPID="" UPID=""
# shellcheck source=tls-http-get.sh
. ./tls-http-get.sh

cleanup() {
	if [ -n "${SPID:-}" ]; then kill -9 "$SPID" 2>/dev/null || true; fi
	if [ -n "${UPID:-}" ]; then kill -9 "$UPID" 2>/dev/null || true; fi
	# NEVER pkill -f with pattern present in this script/ssh cmdline (kills sshd session).
	for p in $(pgrep -x layer7-tlsproxy 2>/dev/null || true); do kill -9 "$p" 2>/dev/null || true; done
	for p in $(pgrep -f '[p]oc-upstream-stub' 2>/dev/null || true); do kill -9 "$p" 2>/dev/null || true; done
}
trap cleanup EXIT INT TERM

test -x "$OUT"
test -f "$CRT" -a -f "$KEY" -a -f "$STUB"

CN=$(openssl x509 -in "$CRT" -noout -subject 2>/dev/null |
	sed -n 's/.*CN *= *//p' | head -1)
[ -n "$CN" ] || CN=lab.local

python3 "$STUB" 19080 >/tmp/l7-up.log 2>&1 &
UPID=$!
sleep 0.3

LAYER7_TLSPROXY_LAB=1 "$OUT" --lab-tls-listen 127.0.0.1:8443 \
	--cert "$CRT" --key "$KEY" --upstream 127.0.0.1:19080 \
	>/tmp/l7-poc-s1.log 2>&1 &
SPID=$!
i=0
while [ "$i" -lt 40 ]; do
	kill -0 "$SPID" 2>/dev/null || { echo "proxy died"; cat /tmp/l7-poc-s1.log; exit 1; }
	if https_body "$CN" | grep -q UPSTREAM_OK; then
		break
	fi
	i=$((i + 1))
	sleep 0.05
done
https_body "$CN" | grep -q UPSTREAM_OK

# Baseline: direct upstream (no TLS proxy)
python3 - <<PY
import time, urllib.request
N=$N
t=[]
for _ in range(N):
    t0=time.perf_counter()
    urllib.request.urlopen("http://127.0.0.1:19080/", timeout=2).read()
    t.append((time.perf_counter()-t0)*1000)
t.sort()
p95=t[int(len(t)*0.95)]
open("/tmp/l7-s2-baseline.txt","w").write(f"n={N}\\np50_ms={t[len(t)//2]:.2f}\\np95_ms={p95:.2f}\\nmax_ms={t[-1]:.2f}\\npath=direct_upstream\\n")
print(f"BASELINE_direct n={N} p50={t[len(t)//2]:.2f}ms p95={p95:.2f}ms")
PY

# S2 via proxy + S1 CPU sample around the load
IDLE=$(awk '/^cpu /{u=$2+$4; tot=$2+$3+$4+$5+$6+$7+$8; print u,tot}' /proc/stat)
python3 - <<PY
import ssl, socket, time
N=$N
CN="$CN"
CRT="$CRT"
ctx=ssl.create_default_context(cafile=CRT)
ctx.verify_mode=ssl.CERT_REQUIRED
ctx.check_hostname=True
times=[]
errors=0
for _ in range(N):
    t0=time.perf_counter()
    try:
        s=socket.create_connection(("127.0.0.1",8443), timeout=2)
        ss=ctx.wrap_socket(s, server_hostname=CN)
        ss.sendall(("GET / HTTP/1.0\r\nHost: %s\r\n\r\n" % CN).encode())
        data=b""
        while True:
            chunk=ss.recv(4096)
            if not chunk: break
            data+=chunk
        ss.close()
        if b"UPSTREAM_OK" not in data:
            errors+=1
        times.append((time.perf_counter()-t0)*1000)
    except Exception:
        errors+=1
        times.append((time.perf_counter()-t0)*1000)
times.sort()
p50=times[len(times)//2]
p95=times[int(len(times)*0.95)]
open("/tmp/l7-s2-proxy.txt","w").write(
    f"n={N}\\np50_ms={p50:.2f}\\np95_ms={p95:.2f}\\nmax_ms={times[-1]:.2f}\\nerrors={errors}\\npath=tls_proxy_upstream\\n"
)
print(f"S2_proxy n={N} p50={p50:.2f}ms p95={p95:.2f}ms max={times[-1]:.2f}ms errors={errors}")
if errors:
    raise SystemExit(f"S2_FAIL errors={errors}")
if p95 > 150.0:
    raise SystemExit(f"S2_FAIL p95={p95:.2f} > 150ms")
print("S2_GATE: PASS")
PY
BUSY=$(awk '/^cpu /{u=$2+$4; tot=$2+$3+$4+$5+$6+$7+$8; print u,tot}' /proc/stat)
python3 - <<PY
idle=list(map(int,"$IDLE".split())); busy=list(map(int,"$BUSY".split()))
du=busy[0]-idle[0]; dt=busy[1]-idle[1]
pct=(100.0*du/dt) if dt else 0.0
# Product gate S1 is "+25% overhead vs baseline path" — lab notes honest scope.
open("/tmp/l7-s1.txt","w").write(
    f"cpu_busy_pct_during_${N}_proxy_reqs={pct:.2f}\\n"
    f"scope=lab_localhost_tls_terminate_plus_upstream\\n"
    f"product_inline_mitm=PENDING\\n"
    f"note=not_gateway_inline; compare later to appliance baseline 20.11a\\n"
)
print(f"S1_cpu_busy_pct_during_load={pct:.2f}")
# Soft lab gate: if machine is nearly idle otherwise, busy% during short burst should be modest.
# Fail only if absurd (>80% sustained on this tiny N) — real +25% needs appliance path.
if pct > 80.0:
    raise SystemExit(f"S1_LAB_FAIL cpu_busy_pct={pct:.2f} unexpectedly high")
print("S1_LAB: PASS (local acceptor+upstream; product inline still PENDING)")
PY

echo "MEASURE_PASS"
cat /tmp/l7-s2-baseline.txt
cat /tmp/l7-s2-proxy.txt
cat /tmp/l7-s1.txt
