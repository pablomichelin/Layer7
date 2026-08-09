#!/bin/sh
# Gate D1 smoke: com CA como --cert/--key, o peer TLS deve ser leaf
# (CA:FALSE, serverAuth, SAN=SNI) — não a CA.
# Não toca .254.
set -eu

HDIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
ROOT=$(CDPATH= cd -- "$HDIR/../../.." && pwd)
SRC="$ROOT/src/layer7-tlsproxy"
WORK=$(mktemp -d "${TMPDIR:-/tmp}/l7-mitm-leaf.XXXXXX")
PORT=${L7_TLS_LEAF_PORT:-18445}
SNI=${L7_TLS_LEAF_SNI:-mitm-lab.test}
BIN="$WORK/layer7-tlsproxy"

cleanup() {
	if [ -n "${SPID:-}" ]; then
		kill -TERM "$SPID" 2>/dev/null || true
		sleep 0.2
		kill -KILL "$SPID" 2>/dev/null || true
		wait "$SPID" 2>/dev/null || true
		SPID=""
	fi
}
trap cleanup EXIT

command -v openssl >/dev/null 2>&1 || {
	echo "FAIL: openssl necessario"
	exit 2
}
command -v cc >/dev/null 2>&1 || {
	echo "FAIL: cc necessario"
	exit 2
}

echo "HARNESS=mitm-tls-leaf-fix WORK=$WORK SNI=$SNI PORT=$PORT"

cat >"$WORK/ca.cnf" <<'EOF'
[req]
distinguished_name=req_dn
x509_extensions=v3_ca
prompt=no
[req_dn]
CN=Layer7-D1-Harness-CA
[v3_ca]
basicConstraints=critical,CA:TRUE
keyUsage=critical,keyCertSign,cRLSign
subjectKeyIdentifier=hash
authorityKeyIdentifier=keyid:always,issuer
EOF

openssl req -x509 -newkey rsa:2048 -sha256 -nodes -days 1 \
	-keyout "$WORK/ca.key" -out "$WORK/ca.crt" \
	-config "$WORK/ca.cnf" -extensions v3_ca >/dev/null 2>&1

(cd "$SRC" && make clean >/dev/null 2>&1 || true)
(cd "$SRC" && make OUT="$BIN" -j2)

LAYER7_TLSPROXY_PRODUCT=1 "$BIN" \
	--product-listen "127.0.0.1:$PORT" \
	--cert "$WORK/ca.crt" --key "$WORK/ca.key" \
	--block-sni "$SNI" \
	>"$WORK/proxy.log" 2>&1 &
SPID=$!
i=0
while [ "$i" -lt 40 ]; do
	if grep -q 'mint-mode ON' "$WORK/proxy.log" 2>/dev/null; then
		break
	fi
	if ! kill -0 "$SPID" 2>/dev/null; then
		echo "FAIL: proxy morreu no arranque"
		cat "$WORK/proxy.log" || true
		exit 1
	fi
	sleep 0.1
	i=$((i + 1))
done

PEER="$WORK/peer.pem"
# Política: validação TLS real — proibido -k / ignore-certificate-errors.
echo | openssl s_client -connect "127.0.0.1:$PORT" -servername "$SNI" \
	-CAfile "$WORK/ca.crt" -verify_return_error -verify_hostname "$SNI" \
	2>"$WORK/s_client.err" \
	| openssl x509 -out "$PEER" 2>/dev/null

if [ ! -s "$PEER" ]; then
	echo "FAIL: sem peer cert"
	cat "$WORK/s_client.err" || true
	cat "$WORK/proxy.log" || true
	exit 1
fi

echo "=== Peer ==="
openssl x509 -in "$PEER" -noout -subject -issuer
TEXT=$(openssl x509 -in "$PEER" -noout -text)

if ! echo "$TEXT" | grep -q 'CA:FALSE'; then
	echo "FAIL: peer deve ser CA:FALSE"
	echo "$TEXT" | sed -n '/X509v3 Basic/,/Signature Algorithm/p'
	exit 1
fi
if ! echo "$TEXT" | grep -q 'TLS Web Server Authentication\|serverAuth'; then
	echo "FAIL: peer sem EKU serverAuth"
	exit 1
fi
if ! echo "$TEXT" | grep -q 'Digital Signature'; then
	echo "FAIL: peer sem digitalSignature KU"
	exit 1
fi
if ! echo "$TEXT" | grep -qi "DNS:$SNI"; then
	echo "FAIL: SAN sem DNS:$SNI"
	exit 1
fi
if echo "$TEXT" | grep -q 'Certificate Sign'; then
	echo "FAIL: peer ainda tem Certificate Sign (parece CA)"
	exit 1
fi
if ! echo "$TEXT" | grep -qi 'sha256\|sha-256\|Signature Algorithm.*sha256'; then
	# OpenSSL text varia; aceitar Presence of sha256 in Signature Algorithm line
	if ! echo "$TEXT" | grep -i 'Signature Algorithm' | head -1 | grep -qi sha256; then
		echo "FAIL: assinatura leaf deve ser sha256 (Chromium)"
		echo "$TEXT" | grep -i 'Signature Algorithm' | head -3
		exit 1
	fi
fi
if ! echo "$TEXT" | grep -q 'Subject Key Identifier'; then
	echo "FAIL: leaf sem Subject Key Identifier"
	exit 1
fi

CA_FP=$(openssl x509 -in "$WORK/ca.crt" -noout -fingerprint -sha256 |
	sed -n 's/^.*=//p' | tr -d ':' | tr 'A-F' 'a-f')
PEER_FP=$(openssl x509 -in "$PEER" -noout -fingerprint -sha256 |
	sed -n 's/^.*=//p' | tr -d ':' | tr 'A-F' 'a-f')
if [ -z "$CA_FP" ] || [ -z "$PEER_FP" ] || [ "$CA_FP" = "$PEER_FP" ]; then
	echo "FAIL: peer fingerprint deve diferir da CA (identidade inconsistente)"
	echo "CA_FP=$CA_FP PEER_FP=$PEER_FP"
	exit 1
fi
ISSUER=$(openssl x509 -in "$PEER" -noout -issuer)
CA_SUBJ=$(openssl x509 -in "$WORK/ca.crt" -noout -subject)
# issuer do leaf deve reflectir subject da CA (CN)
CA_CN=$(echo "$CA_SUBJ" | sed -n 's/.*CN *= *//p')
if [ -n "$CA_CN" ] && ! echo "$ISSUER" | grep -q "$CA_CN"; then
	echo "FAIL: issuer leaf != CA subject (CN=$CA_CN)"
	echo "ISSUER=$ISSUER"
	echo "CA_SUBJ=$CA_SUBJ"
	exit 1
fi
if ! openssl verify -CAfile "$WORK/ca.crt" "$PEER" >/dev/null 2>&1; then
	echo "FAIL: openssl verify -CAfile ca peer"
	openssl verify -CAfile "$WORK/ca.crt" "$PEER" || true
	exit 1
fi

# HTTP over TLS com verify obrigatório (sem -k / sem soft-fail).
BODY=$( (
	printf 'GET / HTTP/1.1\r\nHost: %s\r\nConnection: close\r\n\r\n' "$SNI"
	sleep 0.2
) | openssl s_client -connect "127.0.0.1:$PORT" -servername "$SNI" \
	-CAfile "$WORK/ca.crt" -verify_return_error -verify_hostname "$SNI" \
	-quiet 2>/dev/null | head -c 8192 || true)

if ! echo "$BODY" | grep -qi 'acesso bloqueado\|Layer7'; then
	echo "FAIL: body sem marcador block page Layer7"
	echo "$BODY" | head -40
	exit 1
fi

echo "PASS: peer=leaf serverAuth SAN=$SNI; verify OK com CA; block page presente"
echo "D1_SMOKE=local_pass (Edge .24 ainda exige GO B+D)"
exit 0
