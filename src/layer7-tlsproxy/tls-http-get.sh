# Helper partilhado — HTTP sobre TLS com verificação de cadeia.
# Proibido: curl -k, CERT_NONE, ignore-certificate-errors.
# Uso: . ./tls-http-get.sh   # define https_body / https_ready
# Requer: CRT=caminho PEM (CA ou leaf self-signed usado como trust anchor)

https_body() {
	_host=$1
	if [ -z "${CRT:-}" ] || [ ! -f "$CRT" ]; then
		echo "tls-http-get: CRT em falta" >&2
		return 2
	fi
	(
		printf 'GET / HTTP/1.0\r\nHost: %s\r\nConnection: close\r\n\r\n' "$_host"
		sleep 0.12
	) | openssl s_client -connect 127.0.0.1:8443 -servername "$_host" \
		-CAfile "$CRT" -verify_return_error -quiet 2>/dev/null
}

https_ready() {
	_host=${1:-}
	if [ -z "$_host" ]; then
		_host=$(openssl x509 -in "$CRT" -noout -subject 2>/dev/null |
			sed -n 's/.*CN *= *//p' | head -1)
		[ -n "$_host" ] || _host=lab.local
	fi
	https_body "$_host" >/dev/null 2>&1
}
