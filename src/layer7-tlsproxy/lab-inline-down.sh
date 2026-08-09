#!/bin/sh
# Rollback Opção A lab inline on .54
set -eu
NS=l7poccli
VETH_H=l7veth0

# Kill listeners by exact name only (never pkill -f layer7 in ssh cmdline)
for p in $(pgrep -x layer7-tlsproxy 2>/dev/null || true); do kill -9 "$p" 2>/dev/null || true; done
for p in $(pgrep -f '[p]oc-upstream-stub' 2>/dev/null || true); do kill -9 "$p" 2>/dev/null || true; done

iptables -t nat -D PREROUTING -i "$VETH_H" -p tcp --dport 443 -j REDIRECT --to-ports 8443 2>/dev/null || true
iptables -t nat -D POSTROUTING -s 10.67.67.0/24 ! -o "$VETH_H" -j MASQUERADE 2>/dev/null || true

ip link del "$VETH_H" 2>/dev/null || true
ip netns del "$NS" 2>/dev/null || true
echo "lab-inline DOWN"
