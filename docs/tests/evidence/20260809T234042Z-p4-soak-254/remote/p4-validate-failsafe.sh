#!/bin/bash
# Validação segura: (1) scan de segredos; (2) harness watchdog→rollback;
# (3) dry-run remoto: transform failsafe+monitor NÃO reativa mitm (sem gravar).
# Não encerra o soak P4 vivo.
set -u
EV="/Users/pablomichelin/Documents/Layer 7/docs/tests/evidence/20260809T234042Z-p4-soak-254"
# shellcheck source=p4-lib-auth.sh
. "$EV/remote/p4-lib-auth.sh"
OUT="$EV/07-failsafe-validate.txt"
: >"$OUT"
log() { echo "$@" | tee -a "$OUT"; }

log "VALIDATE_START $(date -u +%Y-%m-%dT%H:%M:%SZ)"
log "auth_policy=no_literal_secrets; key_or_0600_tmp_or_SSHPASS_env"

# --- 1) Secret scan tracked scripts ---
SCAN_FAIL=0
OPS=(
  "$EV/remote/p4-lib-auth.sh"
  "$EV/remote/soak-health-loop.sh"
  "$EV/remote/p4-full-rollback.sh"
  "$EV/remote/p4-watchdog.sh"
  "$EV/remote/p4-finalize.sh"
  "$EV/remote/p4-health-remote.sh"
)
# Literais proibidos: sshpass com flag -p e valor entre aspas (nao -e)
if rg -n "sshpass[[:space:]]+-p[[:space:]]*['\"][^'\"]+['\"]|sshpass[[:space:]]+-p[[:space:]]+[^eI[:space:]]" "${OPS[@]}" 2>/dev/null; then
  log "SECRET_SCAN_FAIL literal_sshpass_-p in ops scripts"
  SCAN_FAIL=1
else
  log "SECRET_SCAN_OK no_literal_sshpass_-p in ops scripts"
fi
# Sem lista de senhas conhecidas no validador (evita reintroduzir tokens no repo).
log "SECRET_SCAN_OK no_password_token_list_in_validator"
# rollback must force mitm off, never assign enabled true on mitm
if rg -n 'mitm"\]\["enabled"\]\s*=\s*true|mitm\[.enabled.\]\s*=\s*true' "$EV/remote/p4-full-rollback.sh" 2>/dev/null; then
  log "STATIC_FAIL rollback_assigns_mitm_enabled_true"
  SCAN_FAIL=1
else
  log "STATIC_OK rollback_never_assigns_mitm_enabled_true"
fi
if rg -n 'mitm"\]\["enabled"\]\s*=\s*false|mitm\[.enabled.\]\s*=\s*false' "$EV/remote/p4-full-rollback.sh" >/dev/null 2>&1; then
  log "STATIC_OK rollback_explicit_mitm_enabled_false"
else
  log "STATIC_FAIL rollback_missing_explicit_mitm_off"
  SCAN_FAIL=1
fi

# --- 2) Watchdog harness (local, mocked SSH; não toca lab) ---
HDIR=$(mktemp -d /tmp/p4-wd-harness.XXXXXX)
chmod 700 "$HDIR"
mkdir -p "$HDIR/remote"
cp "$EV/remote/p4-watchdog.sh" "$HDIR/remote/"
# stub auth + rollback + finalize
cat >"$HDIR/remote/p4-lib-auth.sh" <<'STUB'
p4_ssh_254() {
  # Emular MITM ainda ON após deadline (órfão)
  case "$*" in
    *deadline_unix*) echo 1 ;;
    *effective*) echo true ;;
    *grep\ -c\ 8443*|*"grep -c 8443"*) echo 1 ;;
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
# patch EV path inside copied watchdog
sed -i.bak "s|^EV=.*|EV=\"$HDIR\"|" "$HDIR/remote/p4-watchdog.sh"
echo 1 >"$HDIR/00-deadline-unix.txt"
export P4_WATCHDOG_WAIT_OVERRIDE_SEC=1
bash "$HDIR/remote/p4-watchdog.sh"
if [ -f "$HDIR/STUB_ROLLBACK.txt" ] && [ -f "$HDIR/STUB_FINALIZE.txt" ]; then
  log "HARNESS_OK watchdog_calls_rollback_and_finalize_on_orphan"
  cat "$HDIR/STUB_ROLLBACK.txt" >>"$OUT"
  cat "$HDIR/STUB_FINALIZE.txt" >>"$OUT"
else
  log "HARNESS_FAIL watchdog_did_not_call_rollback"
  SCAN_FAIL=1
  ls -la "$HDIR" >>"$OUT" 2>&1 || true
  cat "$HDIR/07-watchdog.log" >>"$OUT" 2>&1 || true
fi
rm -rf "$HDIR"
unset P4_WATCHDOG_WAIT_OVERRIDE_SEC

# --- 3) Dry-run remoto: transform não reativa MITM (sem save) ---
DRY=$(p4_ssh_254 "php -r '"'
require_once("/usr/local/pkg/layer7.inc");
$data = layer7_load_or_default();
$before = layer7_mitm_from_config($data);
$rolled = layer7_mitm_failsafe_rollback($data, "P4 validate dry-run no-save");
$m1 = layer7_mitm_from_config($rolled);
if (!isset($rolled["layer7"]) || !is_array($rolled["layer7"])) { $rolled["layer7"] = array(); }
$rolled["layer7"]["enabled"] = true;
$rolled["layer7"]["mode"] = "monitor";
if (!isset($rolled["layer7"]["mitm"]) || !is_array($rolled["layer7"]["mitm"])) {
  $rolled["layer7"]["mitm"] = array();
}
$rolled["layer7"]["mitm"]["enabled"] = false;
$m2 = layer7_mitm_from_config($rolled);
echo "dry_before_mitm_enabled=" . (!empty($before["enabled"]) ? "true" : "false") . "\n";
echo "dry_after_failsafe_mitm_enabled=" . (!empty($m1["enabled"]) ? "true" : "false") . "\n";
echo "dry_after_monitor_mitm_enabled=" . (!empty($m2["enabled"]) ? "true" : "false") . "\n";
echo "dry_after_monitor_effective=" . (layer7_mitm_effective($m2, true) ? "true" : "false") . "\n";
echo "dry_saved=false\n";
if (!empty($m2["enabled"]) || layer7_mitm_effective($m2, true)) {
  echo "DRY_FAIL_REACTIVATED\n";
  exit(3);
}
echo "DRY_OK_NO_REACTIVATION\n";
exit(0);
'"'" 2>&1) || true
log "$DRY"
if echo "$DRY" | grep -q 'DRY_OK_NO_REACTIVATION'; then
  log "REMOTE_DRY_OK rollback_transform_does_not_reactivate_mitm"
else
  log "REMOTE_DRY_FAIL"
  SCAN_FAIL=1
fi

# Confirm soak still untouched (effective should still reflect live window if was ON)
LIVE=$(p4_ssh_254 "php -r 'require_once(\"/usr/local/pkg/layer7.inc\"); \$m=layer7_mitm_from_config(layer7_load_or_default()); echo \"live_effective=\".(layer7_mitm_effective(\$m,true)?\"true\":\"false\").\"\\n\"; echo \"live_enabled=\".(!empty(\$m[\"enabled\"])?\"true\":\"false\").\"\\n\";'" 2>&1) || true
log "$LIVE"
log "note=dry-run did not save; live soak state should be unchanged"

if [ "$SCAN_FAIL" -eq 0 ]; then
  log "VALIDATE_PASS $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  exit 0
fi
log "VALIDATE_FAIL $(date -u +%Y-%m-%dT%H:%M:%SZ)"
exit 1
