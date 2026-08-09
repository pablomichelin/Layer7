#!/bin/sh
# Compara fingerprints:
#   A) CA carregada pelo tlsproxy (ficheiro CERT / ca.crt)
#   B) CA em LocalMachine\Root na .24 (opcional; SSHPASS)
#   C) Emissora do leaf apresentado (openssl s_client) — opcional
#
# Uso mínimo (só ficheiros):
#   CA_PEM=path/to/ca.crt LEAF_PEM=path/to/peer.pem \
#     sh tests/harness/mitm-activate-hang/compare-ca-fingerprints.sh
#
# Não activa MITM; não escreve na .254.
set -eu

norm_sha1() {
	openssl x509 -in "$1" -noout -fingerprint -sha1 |
		sed 's/.*=//' | tr -d ':' | tr 'a-f' 'A-F'
}

CA_PEM=${CA_PEM:-}
LEAF_PEM=${LEAF_PEM:-}
SSH_254=${SSH_254:-root@192.168.100.254}
SSH_24=${SSH_24:-}
LISTEN=${LISTEN:-}
SNI=${SNI:-mitm-lab.test}

echo "HARNESS=compare-ca-fingerprints"

if [ -z "$CA_PEM" ] && [ -n "${SSH_254}" ]; then
	TMP=$(mktemp)
	if ssh -o BatchMode=yes -o ConnectTimeout=8 "$SSH_254" \
		'test -f /usr/local/etc/layer7/mitm/ca.crt' 2>/dev/null; then
		scp -o BatchMode=yes "$SSH_254:/usr/local/etc/layer7/mitm/ca.crt" "$TMP"
		CA_PEM=$TMP
		echo "CA_SOURCE=254_disk"
	elif ssh -o BatchMode=yes -o ConnectTimeout=8 "$SSH_254" \
		'test -f /var/run/layer7/tlsproxy.product' 2>/dev/null; then
		CERT=$(ssh -o BatchMode=yes "$SSH_254" \
			"awk -F= '/LAYER7_TLSPROXY_CERT=/{print \$2}' /var/run/layer7/tlsproxy.product")
		scp -o BatchMode=yes "$SSH_254:$CERT" "$TMP"
		CA_PEM=$TMP
		echo "CA_SOURCE=254_gate:$CERT"
	fi
fi

if [ -z "$CA_PEM" ] || [ ! -f "$CA_PEM" ]; then
	echo "FAIL: defina CA_PEM=... ou tenha ca.crt/gate na .254"
	exit 2
fi

A=$(norm_sha1 "$CA_PEM")
echo "A_CA_LOADED_SHA1=$A"
openssl x509 -in "$CA_PEM" -noout -subject

B=""
if [ -n "${SSHPASS:-}" ] && [ -n "$SSH_24" ]; then
	B=$(sshpass -e ssh -o PreferredAuthentications=password \
		-o PubkeyAuthentication=no -o StrictHostKeyChecking=no \
		-o ConnectTimeout=12 "$SSH_24" \
		"powershell -NoProfile -Command \"
\\\$c=Get-ChildItem Cert:\\LocalMachine\\Root | Where-Object { \\\$_.Subject -like '*Layer7*' } | Select-Object -First 1;
if(\\\$c){Write-Output \\\$c.Thumbprint.ToUpperInvariant()} else {Write-Output NONE}
\"" 2>/dev/null | grep -E '^[0-9A-F]{40}$|NONE' | head -1)
	echo "B_ROOT_24_SHA1=$B"
else
	echo "B_ROOT_24_SHA1=SKIP (defina SSHPASS + SSH_24=user@192.168.100.24)"
fi

C=""
if [ -n "$LEAF_PEM" ] && [ -f "$LEAF_PEM" ]; then
	:
elif [ -n "$LISTEN" ]; then
	LEAF_PEM=$(mktemp)
	# Sem -k: exigir cadeia contra CA_PEM quando disponivel.
	if [ -n "${CA_PEM:-}" ] && [ -f "$CA_PEM" ]; then
		echo | openssl s_client -connect "$LISTEN" -servername "$SNI" \
			-CAfile "$CA_PEM" -verify_return_error 2>/dev/null |
			openssl x509 -out "$LEAF_PEM" 2>/dev/null || true
	else
		echo "C_PEER_SKIP=no_CA_PEM_for_verify (politica: sem bypass)"
	fi
fi

if [ -n "${LEAF_PEM:-}" ] && [ -f "$LEAF_PEM" ] && [ -s "$LEAF_PEM" ]; then
	echo "C_PEER_SUBJECT=$(openssl x509 -in "$LEAF_PEM" -noout -subject)"
	echo "C_PEER_ISSUER=$(openssl x509 -in "$LEAF_PEM" -noout -issuer)"
	PEER=$(norm_sha1 "$LEAF_PEM")
	echo "C_PEER_SHA1=$PEER"
	if openssl verify -CAfile "$CA_PEM" "$LEAF_PEM" >/dev/null 2>&1; then
		echo "C_VERIFY_AGAINST_A=OK"
		C=$A
		echo "C_ISSUER_FP_EQUALS_A=YES (verify OK ⇒ emissora = CA A)"
	else
		echo "C_VERIFY_AGAINST_A=FAIL"
		# Se peer é a própria CA:
		if [ "$PEER" = "$A" ]; then
			C=$A
			echo "C_PEER_IS_CA=YES (bug class B+D)"
		else
			C=UNKNOWN
		fi
	fi
else
	echo "C_LEAF=SKIP (LEAF_PEM=... ou LISTEN=host:port)"
fi

echo "---"
echo "MATCH_A_B=$([ -n "$B" ] && [ "$B" != "NONE" ] && [ "$A" = "$B" ] && echo YES || echo NO_OR_SKIP)"
echo "MATCH_A_C=$([ -n "$C" ] && [ "$C" = "$A" ] && echo YES || echo NO_OR_SKIP)"
echo "DONE"
