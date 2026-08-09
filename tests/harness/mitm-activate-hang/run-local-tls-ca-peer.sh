#!/bin/sh
# Prova segura: certificado CA (KU certSign/CRLSign) apresentado como peer TLS.
# Alinha com falha Edge ERR_SSL_KEY_USAGE_INCOMPATIBLE (evidência B+D).
# Não toca .254.
set -eu

ROOT=$(CDPATH= cd -- "$(dirname "$0")/../../.." && pwd)
EVID_CA="$ROOT/docs/tests/evidence/20260809T185035Z-phaseBD-mitm-254/06-mitm-ca.crt"
WORK=$(mktemp -d "${TMPDIR:-/tmp}/l7-mitm-tls.XXXXXX")
PORT=${L7_TLS_HARNESS_PORT:-18443}
cleanup() {
	if [ -n "${SPID:-}" ]; then
		kill "$SPID" 2>/dev/null || true
	fi
}
trap cleanup EXIT

command -v openssl >/dev/null 2>&1 || {
	echo "FAIL: openssl necessario"
	exit 2
}

# Preferir CA da evidência B+D; senão gerar CA equivalente (KU só CA).
if [ -f "$EVID_CA" ]; then
	# Evidência só tem cert público — gerar CA efémera com o mesmo perfil KU.
	echo "NOTE: evidencia tem so ca.crt; gerando CA efemera com KU equivalente"
fi

cat >"$WORK/ca.cnf" <<'EOF'
[req]
distinguished_name=req_dn
x509_extensions=v3_ca
prompt=no
[req_dn]
CN=Layer7-Harness-CA
[v3_ca]
basicConstraints=critical,CA:TRUE
keyUsage=keyCertSign,cRLSign
subjectKeyIdentifier=hash
authorityKeyIdentifier=keyid:always,issuer
EOF

openssl req -x509 -newkey rsa:2048 -sha256 -nodes -days 1 \
	-keyout "$WORK/ca.key" -out "$WORK/ca.crt" \
	-config "$WORK/ca.cnf" -extensions v3_ca >/dev/null 2>&1

if [ -f "$EVID_CA" ]; then
	echo "=== Evidencia B+D CA KU ==="
	openssl x509 -in "$EVID_CA" -noout -text | sed -n '/X509v3 Basic/,/Signature Algorithm/p'
fi
echo "=== Harness CA KU ==="
openssl x509 -in "$WORK/ca.crt" -noout -text | sed -n '/X509v3 Basic/,/Signature Algorithm/p'

openssl s_server -accept "$PORT" -cert "$WORK/ca.crt" -key "$WORK/ca.key" -www \
	>"$WORK/s_server.log" 2>&1 &
SPID=$!
sleep 0.4

PEER="$WORK/peer.pem"
# Cadeia: CAfile = peer (self-signed CA). Sem -k; objectivo = inspeccionar KU, não bypassar trust.
echo | openssl s_client -connect "127.0.0.1:$PORT" -servername mitm-lab.test \
	-CAfile "$WORK/ca.crt" -verify_return_error 2>/dev/null \
	| openssl x509 -out "$PEER" 2>/dev/null

echo "=== Peer apresentado (s_client) ==="
openssl x509 -in "$PEER" -noout -subject
openssl x509 -in "$PEER" -noout -text | sed -n '/X509v3 Basic/,/Signature Algorithm/p'

KU=$(openssl x509 -in "$PEER" -noout -text)
echo "$KU" | grep -q 'CA:TRUE' || {
	echo "FAIL: peer nao e CA"
	exit 1
}
echo "$KU" | grep -q 'Certificate Sign' || {
	echo "FAIL: peer sem Certificate Sign"
	exit 1
}
echo "$KU" | grep -q 'CRL Sign' || {
	echo "FAIL: peer sem CRL Sign"
	exit 1
}
# Não deve ter digitalSignature como uso de servidor típico
if echo "$KU" | grep -A2 'X509v3 Key Usage' | grep -qi 'Digital Signature'; then
	echo "FAIL: peer tem Digital Signature (nao reproduz defeito CA-only)"
	exit 1
fi

if [ -f "$EVID_CA" ]; then
	# Mesma classe de KU que a evidência
	EV_KU=$(openssl x509 -in "$EVID_CA" -noout -text)
	echo "$EV_KU" | grep -q 'Certificate Sign' && echo "$EV_KU" | grep -q 'CRL Sign' || {
		echo "FAIL: CA evidencia divergente"
		exit 1
	}
fi

echo "PASS: peer TLS = CA com KU certSign/CRLSign (classe B+D / KEY_USAGE)"
echo "PRODUCT_ALIGN=layer7_mitm_sync_helper CERT/KEY=ca + SSL_CTX_use_certificate_file"
echo "WORK=$WORK"
exit 0
