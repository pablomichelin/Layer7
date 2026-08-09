#!/bin/sh
# S1 inline (Opção A) on .54 — netns client → REDIRECT → tlsproxy → stub
set -eu
cd "$(dirname "$0")"
N=${N:-100}
SPID="" UPID=""

cleanup() {
	[ -n "${SPID:-}" ] && kill -9 "$SPID" 2>/dev/null || true
	[ -n "${UPID:-}" ] && kill -9 "$UPID" 2>/dev/null || true
	./lab-inline-down.sh 2>/dev/null || true
}
trap cleanup EXIT INT TERM

test -x ./layer7-tlsproxy
test -f lab-certs/server.crt
./lab-inline-up.sh

python3 ./poc-upstream-stub.py 19080 >/tmp/l7-up-inline.log 2>&1 &
UPID=$!
sleep 0.3

LAYER7_TLSPROXY_LAB=1 ./layer7-tlsproxy --lab-tls-listen 0.0.0.0:8443 \
	--lab-allow-any \
	--cert lab-certs/server.crt --key lab-certs/server.key \
	--upstream 127.0.0.1:19080 --lab-transparent \
	>/tmp/l7-inline-proxy.log 2>&1 &
SPID=$!
sleep 0.5
kill -0 "$SPID"
# Ready: curl via redirected path from netns (dest 1.1.1.1:443 → 10.67.67.1:8443)
ip netns exec l7poccli curl -sSk --connect-timeout 2 --max-time 3 \
	--resolve lab-inline.test:443:1.1.1.1 https://lab-inline.test/ | grep -q UPSTREAM_OK

IDLE=$(awk '/^cpu /{u=$2+$4; tot=$2+$3+$4+$5+$6+$7+$8; print u,tot}' /proc/stat)
python3 - <<PY
import subprocess, time
N=$N
times=[]; errors=0
cmd_prefix=["ip","netns","exec","l7poccli"]
for _ in range(N):
    t0=time.perf_counter()
    p=subprocess.run(cmd_prefix+["curl","-sSk","--connect-timeout","2","--max-time","3",
                     "--resolve","lab-inline.test:443:1.1.1.1","https://lab-inline.test/"],
                     capture_output=True, text=True)
    times.append((time.perf_counter()-t0)*1000)
    if p.returncode!=0 or "UPSTREAM_OK" not in (p.stdout or ""):
        errors+=1
times.sort()
p50=times[len(times)//2]; p95=times[int(len(times)*0.95)]
open("/tmp/l7-s2-inline.txt","w").write(
    f"n={N}\\np50_ms={p50:.2f}\\np95_ms={p95:.2f}\\nmax_ms={times[-1]:.2f}\\nerrors={errors}\\npath=netns_redirect_tls_upstream\\n")
print(f"S2_inline n={N} p50={p50:.2f}ms p95={p95:.2f}ms errors={errors}")
if errors: raise SystemExit(f"FAIL errors={errors}")
if p95>150: raise SystemExit(f"FAIL p95={p95}")
print("S2_INLINE_GATE: PASS")
log=open("/tmp/l7-inline-proxy.log").read()
if "orig_dst=1.1.1.1:443" not in log and "orig_dst=" not in log:
    raise SystemExit("FAIL missing SO_ORIGINAL_DST log")
print("TRANSPARENT_DST: PASS")
PY
BUSY=$(awk '/^cpu /{u=$2+$4; tot=$2+$3+$4+$5+$6+$7+$8; print u,tot}' /proc/stat)
python3 - <<PY
idle=list(map(int,"$IDLE".split())); busy=list(map(int,"$BUSY".split()))
du=busy[0]-idle[0]; dt=busy[1]-idle[1]
pct=(100.0*du/dt) if dt else 0.0
open("/tmp/l7-s1-inline.txt","w").write(
    f"cpu_busy_pct_during_${N}_inline={pct:.2f}\\nscope=opcao_A_netns_redirect\\nproduct_mitm_effective=false\\n")
print(f"S1_inline_cpu_busy_pct={pct:.2f}")
if pct>80: raise SystemExit("S1_FAIL high cpu")
print("S1_INLINE_LAB: PASS")
PY

echo INLINE_MEASURE_PASS
cat /tmp/l7-s2-inline.txt
cat /tmp/l7-s1-inline.txt
grep orig_dst /tmp/l7-inline-proxy.log | head -3
