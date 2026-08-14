#!/bin/bash
# Rollback completo P4: .254 MITM OFF + rota/CA; .54 Phase A OFF; .24 hosts/CA.
# Segredos: p4-lib-auth.sh. Não reativa mitm.enabled.
# Payload remoto em ficheiro para poder RETENTAR SSH (heredoc só se lê uma vez).
set -u
HARNESS="${HARNESS:-$(cd "$(dirname "$0")" && pwd)}"
EV="${EV:?set EV to evidence directory}"
# shellcheck source=p4-lib-auth.sh
. "$HARNESS/p4-lib-auth.sh"
REASON="${1:-P4 window end rollback}"
UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)
mkdir -p "$EV"
echo "P4_FULL_ROLLBACK_START $UTC reason=$REASON" | tee "$EV/90-rollback-full-start.txt"
echo "auth_policy=no_literal_secrets; probe=-T; retry_254=3" | tee -a "$EV/90-rollback-full-start.txt"

REASON_PHP=$(printf '%s' "$REASON" | sed "s/'/'\\\\''/g")
REMOTE254="$EV/90-rollback-254.remote.sh"
cat >"$REMOTE254" <<R254
set +e
echo ROLLBACK_P4_START \$(date -u +%Y-%m-%dT%H:%M:%SZ)
atq 2>/dev/null | awk 'NR>1 {print \$1}' | while read j; do atrm "\$j" 2>/dev/null; done
echo at_cleared
php -r '
require_once("config.inc");
require_once("/usr/local/pkg/layer7.inc");
\$reason = '\''$REASON_PHP'\'';
if (\$reason === "") { \$reason = "P4 rollback"; }
\$data = layer7_load_or_default();
\$data = layer7_mitm_failsafe_rollback(\$data, \$reason);
if (!layer7_save_json(\$data)) { fwrite(STDERR, "save_fail\\n"); exit(2); }
layer7_filter_configure_safe();
\$ok = layer7_mitm_ca_delete() ? "ok" : "fail";
echo "ca_delete=\$ok\\n";
\$m = layer7_mitm_from_config(layer7_load_or_default());
echo "mitm_enabled=" . (!empty(\$m["enabled"]) ? "true" : "false") . "\\n";
echo "mitm_effective=" . (layer7_mitm_effective(\$m, true) ? "true" : "false") . "\\n";
'
php -r '
require_once("config.inc");
require_once("/usr/local/pkg/layer7.inc");
\$data = layer7_load_or_default();
if (!isset(\$data["layer7"]) || !is_array(\$data["layer7"])) { \$data["layer7"] = array(); }
\$data["layer7"]["enabled"] = true;
\$data["layer7"]["mode"] = "monitor";
if (!isset(\$data["layer7"]["mitm"]) || !is_array(\$data["layer7"]["mitm"])) {
  \$data["layer7"]["mitm"] = array();
}
\$data["layer7"]["mitm"]["enabled"] = false;
layer7_save_json(\$data);
layer7_filter_configure_safe();
\$m = layer7_mitm_from_config(layer7_load_or_default());
echo "post_mode=monitor\\n";
echo "post_mitm_enabled=" . (!empty(\$m["enabled"]) ? "true" : "false") . "\\n";
echo "post_effective=" . (layer7_mitm_effective(\$m, true) ? "true" : "false") . "\\n";
if (!empty(\$m["enabled"]) || layer7_mitm_effective(\$m, true)) {
  fwrite(STDERR, "ROLLBACK_REACTIVATED_MITM\\n");
  exit(3);
}
'
route delete -host 198.18.0.10 2>/dev/null || route delete 198.18.0.10 2>/dev/null || true
echo "route_delete_done"
echo "=== PF MITM ==="
(pfctl -sn; pfctl -sr) 2>/dev/null | grep -i mitm | grep -E 'rdr|layer7_mitm' && echo HAS_MITM_RULES || echo NO_MITM_RDR
(pfctl -sn; pfctl -sr) 2>/dev/null | grep -i mitm | grep -E 'from[[:space:]]+any' && echo ABORT_STILL_FROM_ANY || echo NO_FROM_ANY
pfctl -t layer7_mitm_src -T show 2>&1 | head -5
pfctl -t layer7_mitm_dst -T show 2>&1 | head -5
sockstat -l | grep 8443 && echo STILL_8443 || echo NO_8443
service layer7-tlsproxy onestatus 2>&1 || true
route -n get 198.18.0.10 2>&1 | head -5
curl -sk -o /dev/null -w "GUI9999=%{http_code}\\n" --connect-timeout 5 https://127.0.0.1:9999/ || echo GUI9999=FAIL
pkg query '%v' pfSense-pkg-layer7
tail -3 /var/log/layer7-mitm-audit.log 2>/dev/null
echo ROLLBACK_P4_254_DONE \$(date -u +%Y-%m-%dT%H:%M:%SZ)
R254

RB254=9
tries=0
: >"$EV/90-rollback-254.txt"
while [ "$tries" -lt 3 ]; do
  tries=$((tries + 1))
  echo "rollback_254_try=$tries" >>"$EV/90-rollback-254.txt"
  if p4_ssh_254 "bash -s" <"$REMOTE254" >>"$EV/90-rollback-254.txt" 2>&1; then
    RB254=0
    break
  fi
  RB254=$?
  sleep $((tries * 3))
done

p4_ssh_54 "bash -s" <<'R54' | tee "$EV/92-rollback-54.txt"
set +e
atq 2>/dev/null | awk 'NR>1 {print $1}' | while read j; do atrm "$j" 2>/dev/null; done
if [ -x /opt/layer7-poc/mitm-lab-a/phase-a-control.sh ]; then
  /opt/layer7-poc/mitm-lab-a/phase-a-control.sh rollback
else
  echo NO_PHASE_A_CTRL
  pkill -f 'mitm-lab|198.18.0.10' 2>/dev/null || true
fi
ss -lntp 2>/dev/null | grep 198.18.0.10 && echo STILL_LISTEN || echo NO_LISTEN
ip addr show 2>/dev/null | grep 198.18.0.10 || echo NO_VIP
echo ROLLBACK_A_DONE $(date -u +%Y-%m-%dT%H:%M:%SZ)
R54
RB54=${PIPESTATUS[0]}

B64_24=$(python3 - <<'PY'
import base64
ps = r'''
$ErrorActionPreference='Continue'
$ts = Get-Date -Format o
$n = 0
Get-ChildItem Cert:\LocalMachine\Root -ErrorAction SilentlyContinue | Where-Object { $_.Subject -like '*Layer7-P4-Soak-CA*' } | ForEach-Object {
  Write-Output ("{0} REMOVE_CA={1}|CN=Layer7-P4-Soak-CA" -f $ts, $_.Thumbprint)
  Remove-Item $_.PSPath -Force -ErrorAction SilentlyContinue
  $n++
}
if ($n -eq 0) { Write-Output "$ts REMOVE_CA=none_present" }
$bak = 'C:\Windows\System32\drivers\etc\hosts.p4-soak.bak'
$hosts = 'C:\Windows\System32\drivers\etc\hosts'
if (Test-Path $bak) {
  Copy-Item -Force $bak $hosts
  Write-Output "$ts HOSTS_RESTORED_FROM_BAK"
} else {
  $c = @(Get-Content $hosts -ErrorAction SilentlyContinue)
  $filtered = $c | Where-Object { $_ -notmatch 'mitm-lab\.test' }
  Set-Content -Path $hosts -Value $filtered -Encoding ASCII
  Write-Output "$ts HOSTS_MITM_LAB_LINES_REMOVED"
}
Write-Output "$ts ROLLBACK_24_OK"
'''
print(base64.b64encode(ps.encode('utf-16le')).decode('ascii'))
PY
)
if p4_ssh_24 "powershell -NoProfile -EncodedCommand $B64_24" 2>&1 | tee "$EV/91-rollback-24.txt"
then
  if grep -q 'ROLLBACK_24_OK' "$EV/91-rollback-24.txt" 2>/dev/null; then
    RB24=0
  else
    RB24=1
  fi
else
  echo "ROLLBACK_24_SKIP_OR_FAIL auth_or_remote" | tee "$EV/91-rollback-24.txt"
  RB24=1
fi
perl -i -pe 's/[A-Za-z0-9._-]+\\[A-Za-z0-9._-]+/REDACTED_ACCOUNT/g; s/Administrador/REDACTED_USER/g' \
  "$EV/91-rollback-24.txt" 2>/dev/null || true

POST="$EV/93-post-state.remote.sh"
cat >"$POST" <<'V'
set +e
php -r '
require_once("/usr/local/pkg/layer7.inc");
$data = layer7_load_or_default();
$m = layer7_mitm_from_config($data);
$l = $data["layer7"];
echo "mode=" . (isset($l["mode"]) ? $l["mode"] : "?") . "\n";
echo "layer7_enabled=" . (!empty($l["enabled"]) ? "true" : "false") . "\n";
echo "mitm_enabled=" . (!empty($m["enabled"]) ? "true" : "false") . "\n";
echo "mitm_effective=" . (layer7_mitm_effective($m, true) ? "true" : "false") . "\n";
echo "ca_present=" . (!empty($m["ca"]["present"]) ? "true" : "false") . "\n";
'
echo RDR=$( (pfctl -sn; pfctl -sr) 2>/dev/null | grep -ci mitm || true )
sockstat -l | grep 8443 && echo LISTEN8443=1 || echo LISTEN8443=0
route -n get 198.18.0.10 2>&1 | head -3
ls /usr/local/etc/layer7-mitm-ca* 2>&1 || echo NO_CA_FILES
V
tries=0
: >"$EV/93-post-state.txt"
while [ "$tries" -lt 3 ]; do
  tries=$((tries + 1))
  if p4_ssh_254 "bash -s" <"$POST" >>"$EV/93-post-state.txt" 2>&1; then
    break
  fi
  sleep $((tries * 3))
done

PASS_RB=1
grep -qE 'post_effective=false|mitm_effective=false' "$EV/90-rollback-254.txt" "$EV/93-post-state.txt" 2>/dev/null || PASS_RB=0
grep -qE 'NO_8443|LISTEN8443=0' "$EV/90-rollback-254.txt" "$EV/93-post-state.txt" 2>/dev/null || PASS_RB=0
grep -qiE 'mitm_effective=true|post_mitm_enabled=true|STILL_8443|ABORT_STILL_FROM_ANY|ROLLBACK_REACTIVATED_MITM' \
  "$EV/93-post-state.txt" "$EV/90-rollback-254.txt" 2>/dev/null && PASS_RB=0

{
  echo "UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "reason=$REASON"
  echo "rb254_exit=$RB254 rb54_exit=$RB54 rb24_ok=$RB24"
  echo "rollback_clean=$PASS_RB"
  echo "mitm_reactivation_guard=enforced"
} | tee "$EV/90-rollback-summary.txt"

if [ "$PASS_RB" = "1" ]; then
  echo "P4_FULL_ROLLBACK_OK $(date -u +%Y-%m-%dT%H:%M:%SZ)" | tee "$EV/90-rollback-ok.txt"
  exit 0
fi
echo "P4_FULL_ROLLBACK_INCOMPLETE $(date -u +%Y-%m-%dT%H:%M:%SZ)" | tee "$EV/90-rollback-incomplete.txt"
exit 1
