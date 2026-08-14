#!/bin/bash
# P4 soak supervisor: health periódica → abort+rollback OU fim de janela+rollback → finalize.
# Segredos: apenas via remote/p4-lib-auth.sh (chave / SSHPASS / ficheiro 0600 fora do repo).
set -u
EV="/Users/pablomichelin/Documents/Layer 7/docs/tests/evidence/20260813T170000Z-p4-retry-254"
# shellcheck source=p4-lib-auth.sh
. "$EV/remote/p4-lib-auth.sh"
REMOTE_H="$EV/remote/p4-health-remote.sh"
RB="$EV/remote/p4-full-rollback.sh"
FIN="$EV/remote/p4-finalize.sh"
LOG="$EV/07-soak-loop.log"
INTERVAL="${INTERVAL:-900}"
SSH_RETRY=3

exec >>"$LOG" 2>&1
echo "SOAK_LOOP_START $(date -u +%Y-%m-%dT%H:%M:%SZ) pid=$$"
echo "auth_policy=no_literal_secrets; key_or_0600_tmp_or_SSHPASS_env"

DEADLINE=$(cat "$EV/00-deadline-unix.txt" 2>/dev/null || echo "")
if ! [[ "$DEADLINE" =~ ^[0-9]+$ ]] || [ "$DEADLINE" -le 0 ]; then
  DEADLINE=$(p4_ssh_254 "php -r 'require_once(\"/usr/local/pkg/layer7.inc\"); \$m=layer7_mitm_from_config(layer7_load_or_default()); \$s=layer7_mitm_window_status(\$m); echo (int)\$s[\"deadline_unix\"];'")
fi
NOW=$(date -u +%s)
if ! [[ "$DEADLINE" =~ ^[0-9]+$ ]] || [ "$DEADLINE" -le "$NOW" ]; then
  echo "DEADLINE_INVALID=$DEADLINE NOW=$NOW — abort to rollback"
  bash "$RB" "P4 deadline invalid/missing"
  bash "$FIN" "FAIL" "deadline invalid at loop start"
  exit 2
fi
echo "deadline_unix=$DEADLINE remaining=$((DEADLINE-NOW))"
echo "$DEADLINE" >"$EV/00-deadline-unix.txt"
date -u -r "$DEADLINE" +%Y-%m-%dT%H:%M:%SZ >"$EV/00-deadline-utc.txt" 2>/dev/null \
  || date -u -d "@$DEADLINE" +%Y-%m-%dT%H:%M:%SZ >"$EV/00-deadline-utc.txt" 2>/dev/null || true

sample_health() {
  local n="$1" f tries=0
  f=$(printf "%s/07-health-%02d.txt" "$EV" "$n")
  while [ $tries -lt $SSH_RETRY ]; do
    tries=$((tries+1))
    if p4_ssh_254 "sh -s" <"$REMOTE_H" >"$f" 2>&1; then
      if grep -qE 'UTC=|effective=' "$f"; then
        # strip any accidental auth noise from sample body already separate
        echo "health_$n ok tries=$tries"
        return 0
      fi
    fi
    echo "health_$n ssh_retry=$tries" >>"$f"
    sleep 5
  done
  echo SSH_FAIL >>"$f"
  echo "health_$n FAIL after retries"
  return 1
}

abort_now() {
  local why="$1"
  echo "ABORT_PREDICATE $(date -u +%Y-%m-%dT%H:%M:%SZ) $why" | tee "$EV/11-ABORT-PREDICATE.txt"
  bash "$RB" "P4 abort: $why"
  bash "$FIN" "ABORT" "$why"
  exit 2
}

N=0
for f in "$EV"/07-health-[0-9][0-9].txt; do
  [ -f "$f" ] || continue
  b=$(basename "$f" .txt)
  num=${b#07-health-}
  case "$num" in
    ''|*[!0-9]*) ;;
    *) [ "$num" -gt "$N" ] && N=$num ;;
  esac
done
echo "health_seq_start=$N"

while true; do
  NOW=$(date -u +%s)
  if [ "$NOW" -ge $((DEADLINE - 60)) ]; then
    echo "WINDOW_END_APPROACHING now=$NOW deadline=$DEADLINE"
    break
  fi

  N=$((N+1))
  if ! sample_health "$N"; then
    abort_now "health_ssh_fail sample=$N"
  fi
  F=$(printf "%s/07-health-%02d.txt" "$EV" "$N")

  if grep -qE 'ABORT_MITM_FROM_ANY|ABORT_UNEXPECTED_' "$F"; then
    abort_now "pf_scope_drift sample=$N"
  fi
  if grep -q 'GUI=FAIL' "$F"; then
    abort_now "gui_health_fail sample=$N"
  fi
  if grep -q 'expired=true' "$F"; then
    echo "WINDOW_EXPIRED_IN_SAMPLE $(date -u +%Y-%m-%dT%H:%M:%SZ)" | tee "$EV/07-window-expired.txt"
    break
  fi
  if grep -q 'effective=false' "$F"; then
    abort_now "mitm_effective_false_mid_window sample=$N"
  fi
  if grep -q 'ROUTE_LAB_MISSING' "$F"; then
    abort_now "lab_route_missing sample=$N"
  fi
  if grep -q 'LISTEN=0' "$F"; then
    abort_now "helper_8443_down sample=$N"
  fi

  NOW=$(date -u +%s)
  LEFT=$((DEADLINE - 60 - NOW))
  if [ "$LEFT" -le 0 ]; then
    break
  fi
  SLEEP=$INTERVAL
  if [ "$LEFT" -lt "$SLEEP" ]; then
    SLEEP=$LEFT
  fi
  echo "sleep ${SLEEP}s until next health (left_to_close=${LEFT}s)"
  sleep "$SLEEP"
done

echo "SOAK_WINDOW_CLOSE $(date -u +%Y-%m-%dT%H:%M:%SZ) samples=$N" | tee "$EV/07-soak-loop-done.txt"
bash "$RB" "P4 window end rollback"
RB_EC=$?
if [ "$RB_EC" -eq 0 ] && [ "$N" -ge 1 ]; then
  bash "$FIN" "PASS" "soak completed samples=$N; rollback clean"
  exit 0
fi
bash "$FIN" "FAIL" "window end but rollback_ec=$RB_EC samples=$N"
exit 1
