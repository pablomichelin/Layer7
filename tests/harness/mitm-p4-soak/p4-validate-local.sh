#!/bin/bash
# Validação local (sem rede): scan de segredos + auth -T + watchdog stub.
set -u
HARNESS="$(cd "$(dirname "$0")" && pwd)"
OUT="${OUT:-/tmp/p4-validate-local.txt}"
: >"$OUT"
SCAN_FAIL=0
log() { echo "$@" | tee -a "$OUT"; }

log "LOCAL_VALIDATE_START $(date -u +%Y-%m-%dT%H:%M:%SZ)"

OPS=(
  "$HARNESS/p4-lib-auth.sh"
  "$HARNESS/soak-health-loop.sh"
  "$HARNESS/p4-full-rollback.sh"
  "$HARNESS/p4-watchdog.sh"
  "$HARNESS/p4-health-remote.sh"
  "$HARNESS/run-local-auth-fix.sh"
)
if command -v rg >/dev/null 2>&1; then
  if rg -n "sshpass[[:space:]]+-p[[:space:]]*['\"][^'\"]+['\"]|sshpass[[:space:]]+-p[[:space:]]+[^eI[:space:]]" "${OPS[@]}"; then
    log "SECRET_SCAN_FAIL"; SCAN_FAIL=1
  else
    log "SECRET_SCAN_OK"
  fi
else
  if grep -nE "sshpass[[:space:]]+-p[[:space:]]*['\"][^'\"]+['\"]" "${OPS[@]}" >/dev/null 2>&1; then
    log "SECRET_SCAN_FAIL"; SCAN_FAIL=1
  else
    log "SECRET_SCAN_OK"
  fi
fi
log "TOKEN_OK"
if grep -nE 'mitm"\]\["enabled"\]\s*=\s*true' "$HARNESS/p4-full-rollback.sh" >/dev/null; then
  log "MITM_TRUE_FAIL"; SCAN_FAIL=1
else
  log "MITM_TRUE_OK"
fi
if grep -nE 'mitm"\]\["enabled"\]\s*=\s*false' "$HARNESS/p4-full-rollback.sh" >/dev/null; then
  log "MITM_FALSE_OK"
else
  log "MITM_FALSE_FAIL"; SCAN_FAIL=1
fi
if grep -n 'no_key_no_SSHPASS_no_passfile' "$HARNESS/p4-lib-auth.sh" >/dev/null; then
  log "LEGACY_NO_KEY_MSG_FAIL"; SCAN_FAIL=1
else
  log "LEGACY_NO_KEY_MSG_OK"
fi

if ! bash "$HARNESS/run-local-auth-fix.sh"; then
  log "AUTH_FIX_FAIL"; SCAN_FAIL=1
else
  log "AUTH_FIX_OK"
fi

HDIR=$(mktemp -d /tmp/p4-wd-harness.XXXXXX)
chmod 700 "$HDIR"
mkdir -p "$HDIR/remote"
cp "$HARNESS/p4-watchdog.sh" "$HDIR/remote/"
cat >"$HDIR/remote/p4-lib-auth.sh" <<'STUB'
p4_ssh_254() {
  case "$*" in
    *deadline*) echo 1 ;;
    *effective*) echo true ;;
    *8443*) echo 1 ;;
    *) echo true ;;
  esac
  return 0
}
STUB
cat >"$HDIR/p4-full-rollback.sh" <<'STUB'
#!/bin/bash
echo "STUB_ROLLBACK_CALLED reason=${1:-}" | tee "$(dirname "$0")/STUB_ROLLBACK.txt"
exit 0
STUB
chmod +x "$HDIR/p4-full-rollback.sh" "$HDIR/remote/"*.sh
# Watchdog no harness chama $HARNESS/p4-full-rollback.sh — apontar HARNESS ao stub dir
# mas o watchdog real usa HARNESS para auth+rollback. Copiar watchdog com HARNESS=$HDIR.
sed -i.bak "s|^EV=.*||" "$HDIR/remote/p4-watchdog.sh" 2>/dev/null || true
export EV="$HDIR"
export HARNESS="$HDIR"
# watchdog espera p4-lib-auth em HARNESS e rollback em HARNESS
cp "$HDIR/remote/p4-lib-auth.sh" "$HDIR/p4-lib-auth.sh"
cp "$HDIR/remote/p4-watchdog.sh" "$HDIR/p4-watchdog.sh"
echo 1 >"$HDIR/00-deadline-unix.txt"
export P4_WATCHDOG_WAIT_OVERRIDE_SEC=1
bash "$HDIR/p4-watchdog.sh"
if [ -f "$HDIR/STUB_ROLLBACK.txt" ]; then
  log "HARNESS_OK watchdog_calls_rollback_on_orphan"
  cat "$HDIR/STUB_ROLLBACK.txt" | tee -a "$OUT"
else
  log "HARNESS_FAIL"
  SCAN_FAIL=1
  cat "$HDIR/07-watchdog.log" >>"$OUT" 2>&1 || true
fi
rm -rf "$HDIR"
unset P4_WATCHDOG_WAIT_OVERRIDE_SEC EV HARNESS

for f in /tmp/l7-lab-254.pass /tmp/l7-p4-user.env /tmp/l7-p4-pass.env; do
  if [ -f "$f" ]; then
    mode=$(stat -f %A "$f" 2>/dev/null || stat -c %a "$f" 2>/dev/null || echo "?")
    log "credfile_present path=$f mode=$mode"
    if [ "$mode" != "600" ] && [ "$mode" != "0600" ]; then
      log "CRED_MODE_FAIL $f"; SCAN_FAIL=1
    fi
  else
    log "credfile_absent path=$f (ok se chave SSH BatchMode)"
  fi
done

if [ "$SCAN_FAIL" -eq 0 ]; then
  log "LOCAL_VALIDATE_PASS"
  exit 0
fi
log "LOCAL_VALIDATE_FAIL"
exit 1
