#!/bin/bash
# Failsafe órfão: se o loop morrer e MITM continuar ON após deadline+3min, força rollback.
# Segredos: p4-lib-auth.sh apenas.
set -u
EV="/Users/pablomichelin/Documents/Layer 7/docs/tests/evidence/20260809T234042Z-p4-soak-254"
# shellcheck source=p4-lib-auth.sh
. "$EV/remote/p4-lib-auth.sh"
LOG="$EV/07-watchdog.log"
# P4_WATCHDOG_WAIT_OVERRIDE_SEC — só para testes controlados (não embutir segredos)
WAIT_OVERRIDE="${P4_WATCHDOG_WAIT_OVERRIDE_SEC:-}"

exec >>"$LOG" 2>&1
echo "WATCHDOG_START $(date -u +%Y-%m-%dT%H:%M:%SZ) pid=$$"
echo "auth_policy=no_literal_secrets; key_or_0600_tmp_or_SSHPASS_env"

DEADLINE=$(cat "$EV/00-deadline-unix.txt" 2>/dev/null || echo 0)
if ! [[ "$DEADLINE" =~ ^[0-9]+$ ]] || [ "$DEADLINE" -le 0 ]; then
  DEADLINE=$(p4_ssh_254 "php -r 'require_once(\"/usr/local/pkg/layer7.inc\"); \$m=layer7_mitm_from_config(layer7_load_or_default()); \$s=layer7_mitm_window_status(\$m); echo (int)\$s[\"deadline_unix\"];'" 2>/dev/null || echo 0)
fi
NOW=$(date -u +%s)
if [ -n "$WAIT_OVERRIDE" ] && [[ "$WAIT_OVERRIDE" =~ ^[0-9]+$ ]]; then
  WAIT="$WAIT_OVERRIDE"
  echo "wait_override_sec=$WAIT"
else
  WAIT=$((DEADLINE + 180 - NOW))
  if [ "$WAIT" -lt 30 ]; then WAIT=30; fi
fi
echo "watch_until_deadline_plus_180s wait=${WAIT}s deadline=$DEADLINE"
sleep "$WAIT"

if [ -f "$EV/.finalize.lock" ]; then
  echo "WATCHDOG_NOP finalize already done"
  exit 0
fi

EFF=$(p4_ssh_254 "php -r 'require_once(\"/usr/local/pkg/layer7.inc\"); \$m=layer7_mitm_from_config(layer7_load_or_default()); echo layer7_mitm_effective(\$m,true)?\"true\":\"false\";'" 2>/dev/null || echo unknown)
LISTEN=$(p4_ssh_254 "sockstat -l | grep -c 8443 || true" 2>/dev/null || echo 9)

echo "post_deadline check effective=$EFF listen8443=$LISTEN"
if [ "$EFF" = "true" ] || [ "${LISTEN:-0}" -gt 0 ]; then
  echo "WATCHDOG_FORCE_ROLLBACK"
  bash "$EV/remote/p4-full-rollback.sh" "P4 watchdog orphan MITM after deadline"
  bash "$EV/remote/p4-finalize.sh" "FAIL" "watchdog forced rollback (loop missed close)"
  exit 2
fi

if [ ! -f "$EV/.finalize.lock" ]; then
  echo "WATCHDOG_FINALIZE_MISSING_DOCS"
  bash "$EV/remote/p4-full-rollback.sh" "P4 watchdog finalize ensure clean"
  bash "$EV/remote/p4-finalize.sh" "FAIL" "watchdog finalize — loop ended without formal close"
fi
echo "WATCHDOG_DONE $(date -u +%Y-%m-%dT%H:%M:%SZ)"
