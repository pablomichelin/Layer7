set +e
echo ROLLBACK_P4_START $(date -u +%Y-%m-%dT%H:%M:%SZ)
atq 2>/dev/null | awk 'NR>1 {print $1}' | while read j; do atrm "$j" 2>/dev/null; done
echo at_cleared
php -r '
require_once("config.inc");
require_once("/usr/local/pkg/layer7.inc");
$reason = '\''P4 window end rollback'\'';
if ($reason === "") { $reason = "P4 rollback"; }
$data = layer7_load_or_default();
$data = layer7_mitm_failsafe_rollback($data, $reason);
if (!layer7_save_json($data)) { fwrite(STDERR, "save_fail\n"); exit(2); }
layer7_filter_configure_safe();
$ok = layer7_mitm_ca_delete() ? "ok" : "fail";
echo "ca_delete=$ok\n";
$m = layer7_mitm_from_config(layer7_load_or_default());
echo "mitm_enabled=" . (!empty($m["enabled"]) ? "true" : "false") . "\n";
echo "mitm_effective=" . (layer7_mitm_effective($m, true) ? "true" : "false") . "\n";
'
php -r '
require_once("config.inc");
require_once("/usr/local/pkg/layer7.inc");
$data = layer7_load_or_default();
if (!isset($data["layer7"]) || !is_array($data["layer7"])) { $data["layer7"] = array(); }
$data["layer7"]["enabled"] = true;
$data["layer7"]["mode"] = "monitor";
if (!isset($data["layer7"]["mitm"]) || !is_array($data["layer7"]["mitm"])) {
  $data["layer7"]["mitm"] = array();
}
$data["layer7"]["mitm"]["enabled"] = false;
layer7_save_json($data);
layer7_filter_configure_safe();
$m = layer7_mitm_from_config(layer7_load_or_default());
echo "post_mode=monitor\n";
echo "post_mitm_enabled=" . (!empty($m["enabled"]) ? "true" : "false") . "\n";
echo "post_effective=" . (layer7_mitm_effective($m, true) ? "true" : "false") . "\n";
if (!empty($m["enabled"]) || layer7_mitm_effective($m, true)) {
  fwrite(STDERR, "ROLLBACK_REACTIVATED_MITM\n");
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
curl -sk -o /dev/null -w "GUI9999=%{http_code}\n" --connect-timeout 5 https://127.0.0.1:9999/ || echo GUI9999=FAIL
pkg query '%v' pfSense-pkg-layer7
tail -3 /var/log/layer7-mitm-audit.log 2>/dev/null
echo ROLLBACK_P4_254_DONE $(date -u +%Y-%m-%dT%H:%M:%SZ)
