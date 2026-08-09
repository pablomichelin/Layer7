#!/bin/bash
EV="/Users/pablomichelin/Documents/Layer 7/docs/tests/evidence/20260809T234042Z-p4-soak-254"
END=$(( $(date +%s) + 14400 ))
N=0
while [ $(date +%s) -lt $END ]; do
  N=$((N+1))
  F=$(printf "%s/07-health-%02d.txt" "$EV" "$N")
  sshpass -p 'pablo' ssh -o ConnectTimeout=15 -o StrictHostKeyChecking=no root@192.168.100.254 "sh -s" <<'R' >"$F" 2>&1 || echo SSH_FAIL >>"$F"
set +e
echo UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)
php -r 'require_once("/usr/local/pkg/layer7.inc"); $m=layer7_mitm_from_config(layer7_load_or_default()); $s=layer7_mitm_window_status($m); echo "effective=".(layer7_mitm_effective($m,true)?"true":"false")."\n"; echo "remaining=".$s["remaining_sec"]."\n"; echo "expired=".(!empty($s["expired"])?"true":"false")."\n"; echo "src=".implode(",",$s["source_cidr"])."\n"; echo "dst=".implode(",",$s["dest_cidr"])."\n";'
if (pfctl -sn; pfctl -sr) 2>/dev/null | grep -i mitm | grep -E 'from[[:space:]]+any'; then echo ABORT_MITM_FROM_ANY; else echo MITM_SRC_SCOPED_OK; fi
echo SRC=$(pfctl -t layer7_mitm_src -T show | tr '\n' ' ')
echo DST=$(pfctl -t layer7_mitm_dst -T show | tr '\n' ' ')
pfctl -t layer7_mitm_dst -T show | grep -vE '198\.18\.0\.10|^$' >/dev/null && echo ABORT_UNEXPECTED_DST || echo DST_LAB_ONLY
pfctl -t layer7_mitm_src -T show | grep -vE '192\.168\.100\.24|^$' >/dev/null && echo ABORT_UNEXPECTED_SRC || echo SRC_LAB_ONLY
sockstat -l | grep 8443 >/dev/null && echo LISTEN=1 || echo LISTEN=0
curl -sk -o /dev/null -w "GUI=%{http_code}\n" https://127.0.0.1:9999/ || echo GUI=FAIL
tail -1 /var/log/layer7-mitm-audit.log 2>/dev/null
R
  if grep -qE 'ABORT_MITM_FROM_ANY|ABORT_UNEXPECTED_|SSH_FAIL|GUI=000|GUI=FAIL' "$F" 2>/dev/null; then
    echo "ABORT $(date -u +%Y-%m-%dT%H:%M:%SZ)" | tee "$EV/11-VERDICT.txt"
    grep -E 'ABORT_|SSH_FAIL|GUI=' "$F" | tee -a "$EV/11-VERDICT.txt"
    exit 2
  fi
  # if window expired naturally, stop loop (expected near end)
  if grep -q 'expired=true' "$F" 2>/dev/null; then
    echo "WINDOW_EXPIRED $(date -u +%Y-%m-%dT%H:%M:%SZ)" | tee "$EV/07-window-expired.txt"
    break
  fi
  sleep 900
done
echo SOAK_LOOP_DONE $(date -u +%Y-%m-%dT%H:%M:%SZ) | tee "$EV/07-soak-loop-done.txt"
