#!/bin/sh
# Opção A lab inline — ONLY on 192.168.100.54
# Creates netns client + iptables REDIRECT 443 → 8443. No LAN DHCP/gateway change.
set -eu
cd "$(dirname "$0")"
NS=l7poccli
VETH_H=l7veth0
VETH_C=l7veth1
GW=10.67.67.1
CLI=10.67.67.2

./lab-inline-down.sh 2>/dev/null || true

sysctl -w net.ipv4.ip_forward=1 >/dev/null
ip netns add "$NS"
ip link add "$VETH_H" type veth peer name "$VETH_C"
ip link set "$VETH_C" netns "$NS"
ip addr add "${GW}/24" dev "$VETH_H"
ip link set "$VETH_H" up
ip netns exec "$NS" ip link set lo up
ip netns exec "$NS" ip addr add "${CLI}/24" dev "$VETH_C"
ip netns exec "$NS" ip link set "$VETH_C" up
ip netns exec "$NS" ip route add default via "$GW"

# REDIRECT HTTPS from lab ns iface only
iptables -t nat -C PREROUTING -i "$VETH_H" -p tcp --dport 443 -j REDIRECT --to-ports 8443 2>/dev/null \
  || iptables -t nat -A PREROUTING -i "$VETH_H" -p tcp --dport 443 -j REDIRECT --to-ports 8443
# NAT for any non-redirected traffic from ns (optional, keep local)
iptables -t nat -C POSTROUTING -s 10.67.67.0/24 ! -o "$VETH_H" -j MASQUERADE 2>/dev/null \
  || iptables -t nat -A POSTROUTING -s 10.67.67.0/24 ! -o "$VETH_H" -j MASQUERADE

echo "lab-inline UP ns=$NS gw=$GW cli=$CLI redirect=443->8443"
