#!/bin/sh
# unbound-anti-doh.sh — Configura overrides no Unbound do pfSense para bloquear
# resolvers DoH/DoT e Apple iCloud Private Relay.
#
# Uso (como root no pfSense):
#   sh /tmp/unbound-anti-doh.sh
#
# O script adiciona local-zone/local-data ao custom_options do Unbound,
# forcando NXDOMAIN para dominios de bypass DNS conhecidos.
# Quando o iOS recebe NXDOMAIN para mask.icloud.com, desativa
# automaticamente o Private Relay nessa rede.
#
# Para reverter: remover as linhas adicionadas de /var/unbound/unbound.conf
# e reiniciar o Unbound (pfSsh.php playback svc restart unbound).

set -eu

UNBOUND_CONF="/var/unbound/unbound.conf"
MARKER_START="# --- Layer7 anti-DoH/Relay START ---"
MARKER_END="# --- Layer7 anti-DoH/Relay END ---"

DOH_DOMAINS="
mask.icloud.com
mask-h2.icloud.com
use-application-dns.net
dns.google
dns.google.com
8888.google
dns64.dns.google
cloudflare-dns.com
one.one.one.one
1dot1dot1dot1.cloudflare-dns.com
security.cloudflare-dns.com
family.cloudflare-dns.com
dns.quad9.net
dns9.quad9.net
dns10.quad9.net
dns11.quad9.net
dns.adguard.com
dns-family.adguard.com
dns-unfiltered.adguard.com
doh.opendns.com
doh.cleanbrowsing.org
dns.nextdns.io
doh.xfinity.com
ordns.he.net
"

echo ""
echo "Layer7 — Configuracao Unbound anti-DoH/Relay"
echo "============================================="
echo ""

if [ ! -f "$UNBOUND_CONF" ]; then
    echo "ERRO: $UNBOUND_CONF nao encontrado."
    echo "  Este script deve ser executado no pfSense."
    exit 1
fi

if grep -q "$MARKER_START" "$UNBOUND_CONF" 2>/dev/null; then
    echo "As regras anti-DoH ja estao configuradas no Unbound."
    echo "Para reconfigurar, remova primeiro as linhas entre:"
    echo "  $MARKER_START"
    echo "  $MARKER_END"
    echo ""
    exit 0
fi

BLOCK_LINES=""
for domain in $DOH_DOMAINS; do
    domain=$(echo "$domain" | tr -d '[:space:]')
    [ -z "$domain" ] && continue
    BLOCK_LINES="${BLOCK_LINES}
server:
    local-zone: \"${domain}.\" always_nxdomain"
done

cp "$UNBOUND_CONF" "${UNBOUND_CONF}.layer7-bak.$(date +%Y%m%d%H%M%S)"

cat >> "$UNBOUND_CONF" <<EOF

$MARKER_START
# Dominios de resolvers DoH/DoT e Apple Private Relay.
# Devolver NXDOMAIN forca fallback para DNS convencional.
# Gerado por: scripts/unbound-anti-doh.sh
$BLOCK_LINES
$MARKER_END
EOF

echo "Overrides adicionados a $UNBOUND_CONF"
echo ""

echo "Reiniciando Unbound..."
if command -v pfSsh.php >/dev/null 2>&1; then
    pfSsh.php playback svc restart unbound 2>/dev/null && echo "  Unbound reiniciado." || echo "  AVISO: falha ao reiniciar Unbound."
elif command -v service >/dev/null 2>&1; then
    service unbound restart 2>/dev/null && echo "  Unbound reiniciado." || echo "  AVISO: falha ao reiniciar Unbound."
else
    echo "  AVISO: nao foi possivel reiniciar Unbound automaticamente."
    echo "  Execute manualmente: pfSsh.php playback svc restart unbound"
fi

echo ""
echo "Verificacao:"
for test_domain in mask.icloud.com dns.google cloudflare-dns.com; do
    result=$(drill "$test_domain" @127.0.0.1 2>/dev/null | grep -c "NXDOMAIN" || true)
    if [ "$result" -gt 0 ]; then
        echo "  $test_domain -> NXDOMAIN (OK)"
    else
        echo "  $test_domain -> resposta inesperada (verificar manualmente)"
    fi
done

echo ""
echo "Concluido. Para reverter:"
echo "  1. Edite $UNBOUND_CONF"
echo "  2. Remova tudo entre '$MARKER_START' e '$MARKER_END'"
echo "  3. pfSsh.php playback svc restart unbound"
echo ""
