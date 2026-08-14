#!/bin/bash
# Prova P4.2: probe com -T não classifica timeout/menu como "no_key".
# Sem rede, sem MITM, sem .254.
set -eu
HARNESS="$(cd "$(dirname "$0")" && pwd)"
FAIL=0
pass() { echo "PASS $1"; }
fail() { echo "FAIL $1"; FAIL=1; }

# --- A: mock que exige -T ---
export P4_SSH_BIN="$HARNESS/mock-bin/ssh"
export P4_SSH_PROBE_TRIES=1
export P4_SSH_CONNECT_TIMEOUT=2
unset SSHPASS L7_LAB_254_PASS_FILE L7_LAB_254_IDENTITY
# shellcheck source=p4-lib-auth.sh
. "$HARNESS/p4-lib-auth.sh"

OUT=$(mktemp)
if p4_ssh_254 "true" >"$OUT" 2>"$OUT.err"; then
  if grep -q 'auth_method=ssh_key_batchmode_254' "$OUT.err" \
    && grep -q MOCK_SSH_OK "$OUT"; then
    pass "A_probe_with_-T_uses_key"
  else
    fail "A_output"; cat "$OUT" "$OUT.err"
  fi
else
  fail "A_exit"; cat "$OUT" "$OUT.err"
fi
if grep -q 'no_key_no_SSHPASS_no_passfile' "$OUT.err"; then
  fail "A_legacy_no_key_message"
fi

# --- B: timeout → ssh_transient, NÃO no_key ---
export P4_SSH_BIN="$HARNESS/mock-bin/ssh-timeout"
# re-source to pick P4_SSH_BIN in p4_ssh_bin (reads env each call — OK)
OUTB=$(mktemp)
ec=0
p4_ssh_254 "true" >"$OUTB" 2>"$OUTB.err" || ec=$?
if grep -q 'AUTH_FAIL ssh_transient host=.254' "$OUTB.err"; then
  pass "B_timeout_classified_transient"
else
  fail "B_class"; cat "$OUTB.err"
fi
if grep -q 'no_key_no_SSHPASS_no_passfile' "$OUTB.err"; then
  fail "B_legacy_no_key_message"
fi
if [ "$ec" -eq 0 ]; then
  fail "B_should_nonzero"
fi

rm -f "$OUT" "$OUT.err" "$OUTB" "$OUTB.err"
if [ "$FAIL" -eq 0 ]; then
  echo LOCAL_AUTH_FIX_PASS
  exit 0
fi
echo LOCAL_AUTH_FIX_FAIL
exit 1
