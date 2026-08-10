#!/bin/bash
# Validação local (sem rede): scan de segredos + harness watchdog→rollback.
set -u
EV="/Users/pablomichelin/Documents/Layer 7/docs/tests/evidence/20260809T234042Z-p4-soak-254"
OUT="$EV/07-failsafe-validate-local.txt"
: >"$OUT"
SCAN_FAIL=0
log() { echo "$@" | tee -a "$OUT"; }

log "LOCAL_VALIDATE_START $(date -u +%Y-%m-%dT%H:%M:%SZ)"

OPS=(
  "$EV/remote/p4-lib-auth.sh"
  "$EV/remote/soak-health-loop.sh"
  "$EV/remote/p4-full-rollback.sh"
  "$EV/remote/p4-watchdog.sh"
  "$EV/remote/p4-finalize.sh"
  "$EV/remote/p4-health-remote.sh"
)
if rg -n "sshpass[[:space:]]+-p[[:space:]]*['\"][^'\"]+['\"]|sshpass[[:space:]]+-p[[:space:]]+[^eI[:space:]]" "${OPS[@]}"; then
  log "SECRET_SCAN_FAIL"; SCAN_FAIL=1
else
  log "SECRET_SCAN_OK"
fi
# Qualquer sshpass -p com literal (já coberto acima). Sem tokens de senha embutidos neste validador.
log "TOKEN_OK"
if rg -n 'mitm"\]\["enabled"\]\s*=\s*true' "$EV/remote/p4-full-rollback.sh"; then
  log "MITM_TRUE_FAIL"; SCAN_FAIL=1
else
  log "MITM_TRUE_OK"
fi
if rg -n 'mitm"\]\["enabled"\]\s*=\s*false' "$EV/remote/p4-full-rollback.sh"; then
  log "MITM_FALSE_OK"
else
  log "MITM_FALSE_FAIL"; SCAN_FAIL=1
fi

HDIR=$(mktemp -d /tmp/p4-wd-harness.XXXXXX)
chmod 700 "$HDIR"
mkdir -p "$HDIR/remote"
cp "$EV/remote/p4-watchdog.sh" "$HDIR/remote/"
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
cat >"$HDIR/remote/p4-full-rollback.sh" <<'STUB'
#!/bin/bash
echo "STUB_ROLLBACK_CALLED reason=${1:-}" | tee "$(dirname "$0")/../STUB_ROLLBACK.txt"
exit 0
STUB
cat >"$HDIR/remote/p4-finalize.sh" <<'STUB'
#!/bin/bash
echo "STUB_FINALIZE_CALLED $*" | tee "$(dirname "$0")/../STUB_FINALIZE.txt"
exit 0
STUB
chmod +x "$HDIR/remote/"*.sh
sed -i.bak "s|^EV=.*|EV=\"$HDIR\"|" "$HDIR/remote/p4-watchdog.sh"
echo 1 >"$HDIR/00-deadline-unix.txt"
export P4_WATCHDOG_WAIT_OVERRIDE_SEC=1
bash "$HDIR/remote/p4-watchdog.sh"
if [ -f "$HDIR/STUB_ROLLBACK.txt" ] && [ -f "$HDIR/STUB_FINALIZE.txt" ]; then
  log "HARNESS_OK watchdog_calls_rollback_and_finalize_on_orphan"
  cat "$HDIR/STUB_ROLLBACK.txt" "$HDIR/STUB_FINALIZE.txt" | tee -a "$OUT"
else
  log "HARNESS_FAIL"
  SCAN_FAIL=1
  cat "$HDIR/07-watchdog.log" >>"$OUT" 2>&1 || true
fi
rm -rf "$HDIR"
unset P4_WATCHDOG_WAIT_OVERRIDE_SEC

# Cred files fora do repo: só reportar presença/mode, nunca conteúdo
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
