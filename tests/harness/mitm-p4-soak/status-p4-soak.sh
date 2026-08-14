#!/bin/sh
# Estado local do soak P4 (não toca no appliance).
# Uso: EV=.../20260813T224009Z-p4-retry2-254 sh tests/harness/mitm-p4-soak/status-p4-soak.sh
set -eu
EV="${EV:-}"
if [ -z "$EV" ] || [ ! -d "$EV" ]; then
	echo "FAIL: EV= pasta de evidência do soak" >&2
	exit 1
fi
echo "=== P4 soak status $(date -u +%Y-%m-%dT%H:%M:%SZ) ==="
echo "EV=$EV"
if [ -f "$EV/00-deadline-utc.txt" ]; then
	echo "deadline_utc=$(cat "$EV/00-deadline-utc.txt")"
fi
if [ -f "$EV/00-deadline-unix.txt" ]; then
	dl=$(cat "$EV/00-deadline-unix.txt")
	now=$(date -u +%s)
	left=$((dl - now))
	echo "deadline_unix=$dl remaining_s=$left"
fi
loop_ok=0
wd_ok=0
pidfile_loop=""
for cand in "$EV/07-soak-loop.pid" "$EV/00-soak-loop.pid"; do
	[ -f "$cand" ] && pidfile_loop="$cand" && break
done
if [ -n "$pidfile_loop" ]; then
	lp=$(cat "$pidfile_loop")
	if kill -0 "$lp" 2>/dev/null; then
		loop_ok=1
		echo "soak_loop pid=$lp ALIVE ($pidfile_loop)"
	else
		echo "soak_loop pid=$lp DEAD ($pidfile_loop)"
	fi
else
	echo "soak_loop pid=MISSING"
fi
pidfile_wd=""
for cand in "$EV/07-watchdog.pid" "$EV/00-watchdog.pid"; do
	[ -f "$cand" ] && pidfile_wd="$cand" && break
done
if [ -n "$pidfile_wd" ]; then
	wp=$(cat "$pidfile_wd")
	if kill -0 "$wp" 2>/dev/null; then
		wd_ok=1
		echo "watchdog pid=$wp ALIVE ($pidfile_wd)"
	else
		echo "watchdog pid=$wp DEAD ($pidfile_wd)"
	fi
else
	echo "watchdog pid=MISSING"
fi
if [ -f "$EV/07-soak-loop.log" ]; then
	echo "--- last 8 soak-loop ---"
	tail -n 8 "$EV/07-soak-loop.log"
fi
if [ -f "$EV/08-watchdog.log" ]; then
	echo "--- last 4 watchdog ---"
	tail -n 4 "$EV/08-watchdog.log"
fi
if [ "$loop_ok" -eq 1 ] && [ "$wd_ok" -eq 1 ]; then
	echo "VERDICT=RUNNING"
	exit 0
fi
echo "VERDICT=NOT_RUNNING"
exit 2
