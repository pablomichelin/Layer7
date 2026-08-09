#!/bin/sh
# Inventário SOMENTE LEITURA na .254 — zero mutação.
# Proibido: pkg, filter_configure, save JSON, service restart, rotas, licença.
set -eu

SSH_254=${SSH_254:-root@192.168.100.254}
SSH_OPTS=${SSH_OPTS:-"-o BatchMode=yes -o ConnectTimeout=8 -o StrictHostKeyChecking=accept-new"}

echo "RO_TARGET=$SSH_254"
echo "MODE=read-only"
echo "STARTED=$(date -u +%Y-%m-%dT%H:%M:%SZ)"

# shellcheck disable=SC2086
ssh $SSH_OPTS "$SSH_254" 'set -eu
date -u
echo PKG=$(pkg query %v pfSense-pkg-layer7 2>/dev/null || echo unknown)
php -r "
require_once(\"/usr/local/pkg/layer7.inc\");
\$m = layer7_mitm_from_config(layer7_load_or_default());
\$e = layer7_has_entitlement(\"mitm\");
echo \"en=\".(!empty(\$m[\"enabled\"])?\"yes\":\"no\")
  .\" eff=\".(layer7_mitm_effective(\$m,\$e)?\"yes\":\"no\").\"\\n\";
echo \"src=\".json_encode(\$m[\"intercept\"][\"source_cidr\"]??[]).\"\\n\";
echo \"dst=\".json_encode(\$m[\"intercept\"][\"dest_cidr\"]??[]).\"\\n\";
echo \"block_sni=\".json_encode(\$m[\"intercept\"][\"block_sni\"]??[]).\"\\n\";
"
echo "=== NAT mitm/8443 ==="
pfctl -s nat 2>/dev/null | egrep "mitm|8443" || echo NO_MITM_NAT
echo "=== LISTEN ==="
sockstat -l4 | egrep ":8443|:9999" || echo NO_LISTEN_MATCH
echo "=== SERVICES ==="
service layer7-tlsproxy onestatus 2>&1 | head -1 || true
service layer7d onestatus 2>&1 | head -1 || true
echo "=== ROUTE 198.18 ==="
route -n get 198.18.0.10 2>&1 | egrep "destination:|gateway:" || echo NO_19818
echo "=== GUI port (sem curl -k; nao e gate TLS MITM) ==="
if command -v nc >/dev/null 2>&1 && nc -z 127.0.0.1 9999 2>/dev/null; then
	echo GUI_PORT=open
else
	echo GUI_PORT=closed_or_nc_missing
fi
echo "=== GATE FILES (ls only) ==="
ls -la /var/run/layer7/ 2>/dev/null || echo NO_RUN_DIR
'

echo "FINISHED=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "PASS: RO check completed (no mutations issued by this script)"
exit 0
