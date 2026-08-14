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
