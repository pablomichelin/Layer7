#!/bin/sh
# Inspecção completa leaf/chain do tlsproxy (mint D1).
# Não toca .254.
set -eu

HDIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
ROOT=$(CDPATH= cd -- "$HDIR/../../.." && pwd)
SRC="$ROOT/src/layer7-tlsproxy"
WORK=$(mktemp -d "${TMPDIR:-/tmp}/l7-leaf-insp.XXXXXX")
PORT=${L7_TLS_INSP_PORT:-18449}
SNI=${L7_TLS_LEAF_SNI:-mitm-lab.test}
BIN="$WORK/layer7-tlsproxy"
OUT=${L7_TLS_INSP_OUT:-$WORK/REPORT.txt}

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

command -v openssl >/dev/null 2>&1 || { echo "FAIL: openssl"; exit 2; }
command -v cc >/dev/null 2>&1 || { echo "FAIL: cc"; exit 2; }

cat >"$WORK/ca.cnf" <<'EOF'
[req]
distinguished_name=req_dn
x509_extensions=v3_ca
prompt=no
[req_dn]
CN=Layer7-Inspect-CA
[v3_ca]
basicConstraints=critical,CA:TRUE
keyUsage=critical,keyCertSign,cRLSign
subjectKeyIdentifier=hash
authorityKeyIdentifier=keyid:always,issuer
EOF

openssl req -x509 -newkey rsa:2048 -sha256 -nodes -days 1 \
	-keyout "$WORK/ca.key" -out "$WORK/ca.crt" \
	-config "$WORK/ca.cnf" -extensions v3_ca >/dev/null 2>&1

(cd "$SRC" && make OUT="$BIN" -j2 >/dev/null)

LAYER7_TLSPROXY_PRODUCT=1 "$BIN" \
	--product-listen "127.0.0.1:$PORT" \
	--cert "$WORK/ca.crt" --key "$WORK/ca.key" \
	--block-sni "$SNI" \
	>"$WORK/proxy.log" 2>&1 &
SPID=$!
i=0
while [ "$i" -lt 40 ]; do
	grep -q 'mint-mode ON' "$WORK/proxy.log" 2>/dev/null && break
	kill -0 "$SPID" 2>/dev/null || { echo "FAIL: proxy down"; cat "$WORK/proxy.log"; exit 1; }
	sleep 0.1
	i=$((i + 1))
done

# Handshake 1 — showcerts + leaf (cadeia verificada; sem -k)
SC1="$WORK/s_client1.txt"
echo | openssl s_client -connect "127.0.0.1:$PORT" -servername "$SNI" \
	-CAfile "$WORK/ca.crt" -verify_return_error -verify_hostname "$SNI" \
	-showcerts -tlsextdebug 2>"$WORK/s_client1.err" | tee "$SC1" >/dev/null

# Extrair todos os PEMs da chain enviada
awk '
	/BEGIN CERTIFICATE/{n++; f=sprintf("'"$WORK"'/chain%d.pem", n); in_c=1}
	in_c{print > f}
	/END CERTIFICATE/{close(f); in_c=0}
' "$SC1"
NCHAIN=$(ls "$WORK"/chain*.pem 2>/dev/null | wc -l | tr -d ' ')

# Handshake 2 — mesmo SNI (cache) + verify
echo | openssl s_client -connect "127.0.0.1:$PORT" -servername "$SNI" \
	-CAfile "$WORK/ca.crt" -verify_return_error -verify_hostname "$SNI" \
	2>/dev/null | openssl x509 -out "$WORK/leaf2.pem"
# Handshake 3 — SNI diferente (verify hostname desse SNI)
echo | openssl s_client -connect "127.0.0.1:$PORT" -servername "other-sni.test" \
	-CAfile "$WORK/ca.crt" -verify_return_error -verify_hostname "other-sni.test" \
	2>/dev/null | openssl x509 -out "$WORK/leaf3.pem" || true

LEAF="$WORK/chain1.pem"
[ -s "$LEAF" ] || { echo "FAIL: sem leaf"; exit 1; }

TEXT=$(openssl x509 -in "$LEAF" -noout -text)
CA_SUBJ=$(openssl x509 -in "$WORK/ca.crt" -noout -subject)
NOW_UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)
NB=$(openssl x509 -in "$LEAF" -noout -startdate | sed 's/notBefore=//')
NA=$(openssl x509 -in "$LEAF" -noout -enddate | sed 's/notAfter=//')
SERIAL=$(openssl x509 -in "$LEAF" -noout -serial | sed 's/serial=//')
SERIAL2=$(openssl x509 -in "$WORK/leaf2.pem" -noout -serial 2>/dev/null | sed 's/serial=//' || echo "")
SERIAL3=$(openssl x509 -in "$WORK/leaf3.pem" -noout -serial 2>/dev/null | sed 's/serial=//' || echo "")
FP1=$(openssl x509 -in "$LEAF" -noout -fingerprint -sha256 | sed 's/.*=//')
FP2=$(openssl x509 -in "$WORK/leaf2.pem" -noout -fingerprint -sha256 2>/dev/null | sed 's/.*=//' || echo "")
FP3=$(openssl x509 -in "$WORK/leaf3.pem" -noout -fingerprint -sha256 2>/dev/null | sed 's/.*=//' || echo "")
ENC=$(head -1 "$LEAF")
VERIFY=$(openssl verify -CAfile "$WORK/ca.crt" "$LEAF" 2>&1 | tr '\n' ' ')

# Clock skew check via openssl -checkend
CLOCK_OK=yes
openssl x509 -in "$LEAF" -noout -checkend 0 >/dev/null 2>&1 || CLOCK_OK=no

{
	echo "HARNESS=inspect-leaf-chain"
	echo "UTC_NOW=$NOW_UTC"
	echo "SNI_REQUESTED=$SNI"
	echo "LISTEN=127.0.0.1:$PORT"
	echo
	echo "=== PEER / LEAF ==="
	openssl x509 -in "$LEAF" -noout -subject -issuer
	echo "SERIAL=$SERIAL"
	echo "NOT_BEFORE=$NB"
	echo "NOT_AFTER=$NA"
	echo "CLOCK_VALID_NOW=$CLOCK_OK"
	echo "SHA256=$FP1"
	echo "ENCODING_FIRST_LINE=$ENC"
	echo "VERIFY_CAFILE=$VERIFY"
	echo
	echo "=== ALGORITMO ==="
	echo "$TEXT" | grep -E 'Public Key Algorithm|Signature Algorithm|RSA Public-Key|Public-Key:' | head -10
	echo
	echo "=== BasicConstraints / KeyUsage / EKU / SAN ==="
	echo "$TEXT" | sed -n '/X509v3 Basic Constraints:/,/X509v3 Subject Key Identifier:/p' |
		head -40
	# fallback dump extensions block
	echo "$TEXT" | sed -n '/X509v3 extensions:/,/Signature Algorithm:/p' | head -40
	echo
	echo "=== CHECKS ==="
	echo "ISSUER_MATCH_CA=$(echo "$(openssl x509 -in "$LEAF" -noout -issuer)" | grep -q 'Layer7-Inspect-CA' && echo YES || echo NO)"
	echo "SAN_HAS_SNI=$(echo "$TEXT" | grep -qi "DNS:$SNI" && echo YES || echo NO)"
	echo "EKU_SERVERAUTH=$(echo "$TEXT" | grep -qE 'TLS Web Server Authentication|serverAuth' && echo YES || echo NO)"
	echo "KU_DIGITAL_SIGNATURE=$(echo "$TEXT" | grep -q 'Digital Signature' && echo YES || echo NO)"
	echo "KU_KEY_ENCIPHERMENT=$(echo "$TEXT" | grep -q 'Key Encipherment' && echo YES || echo NO)"
	echo "BC_CA_FALSE=$(echo "$TEXT" | grep -q 'CA:FALSE' && echo YES || echo NO)"
	echo "BC_CA_TRUE=$(echo "$TEXT" | grep -q 'CA:TRUE' && echo YES || echo NO)"
	echo "PEER_EQ_CA_FP=$( [ "$(openssl x509 -in "$LEAF" -noout -fingerprint -sha1 | sed s/.*=//)" = "$(openssl x509 -in "$WORK/ca.crt" -noout -fingerprint -sha1 | sed s/.*=//)" ] && echo YES || echo NO )"
	echo
	echo "=== CHAIN ENVIADA ==="
	echo "CHAIN_CERT_COUNT=$NCHAIN"
	c=1
	while [ "$c" -le "$NCHAIN" ]; do
		f="$WORK/chain${c}.pem"
		echo "-- chain[$c] --"
		openssl x509 -in "$f" -noout -subject -issuer
		openssl x509 -in "$f" -noout -fingerprint -sha1
		c=$((c + 1))
	done
	echo "CHAIN_INCLUDES_CA=$([ "$NCHAIN" -ge 2 ] && echo YES || echo NO_LEAF_ONLY)"
	echo "CA_SUBJECT_FILE=$CA_SUBJ"
	echo
	echo "=== CACHE / RELOAD ==="
	echo "HANDSHAKE1_SERIAL=$SERIAL SHA256=$FP1"
	echo "HANDSHAKE2_SAME_SNI_SERIAL=$SERIAL2 SHA256=$FP2"
	echo "CACHE_HIT_SAME_CERT=$([ "$FP1" = "$FP2" ] && echo YES || echo NO)"
	echo "HANDSHAKE3_OTHER_SNI_SERIAL=$SERIAL3 SHA256=$FP3"
	echo "OTHER_SNI_DIFFERENT_CERT=$([ -n "$FP3" ] && [ "$FP1" != "$FP3" ] && echo YES || echo NO_OR_FAIL)"
	echo "PROXY_LOG_MINT=$(grep -E 'mint-mode' "$WORK/proxy.log" | head -1)"
	echo "NOTE_CACHE=in-memory L7_LEAF_CACHE=32; FIFO evict; sem reload SIGHUP — só restart processo"
	echo "NOTE_RELOAD=rc.d onerestart / layer7_mitm_sync_helper reinicia processo e esvazia cache"
	echo
	echo "=== SNI WIRE (tlsextdebug se disponivel) ==="
	grep -iE 'server name|servername|TLS extension' "$WORK/s_client1.err" | head -10 || true
	grep -iE 'server name|servername' "$SC1" | head -5 || true
	echo
	echo "=== VERDICT LINES ==="
	FAIL=0
	for chk in ISSUER_MATCH_CA SAN_HAS_SNI EKU_SERVERAUTH KU_DIGITAL_SIGNATURE BC_CA_FALSE CACHE_HIT_SAME_CERT; do
		# re-eval quickly
		:
	done
	ok=1
	echo "$TEXT" | grep -qi "DNS:$SNI" || ok=0
	echo "$TEXT" | grep -qE 'TLS Web Server Authentication|serverAuth' || ok=0
	echo "$TEXT" | grep -q 'CA:FALSE' || ok=0
	echo "$TEXT" | grep -q 'Digital Signature' || ok=0
	[ "$NCHAIN" -eq 1 ] || ok=0
	[ "$FP1" = "$FP2" ] || ok=0
	[ "$CLOCK_OK" = "yes" ] || ok=0
	echo "$VERIFY" | grep -q OK || ok=0
	if [ "$ok" -eq 1 ]; then
		echo "PASS: leaf/chain inspection OK (SNI=$SNI, chain=1 leaf, cache hit, verify OK)"
	else
		echo "FAIL: ver checks acima"
		exit 1
	fi
} | tee "$OUT"

echo "REPORT=$OUT"
echo "WORK=$WORK"
