#!/bin/sh
# Regressões próximas ao código layer7-tlsproxy (1.9.43 / D1 / TLS sem bypass).
# Uso: sh ./test-regress.sh   ou   make test-regress
set -eu
cd "$(dirname "$0")"
ROOT=$(CDPATH= cd -- ../.. && pwd)
RC=0

pass() { printf "PASS: %s\n" "$1"; }
fail() { printf "FAIL: %s\n" "$1"; RC=1; }

echo "== tlsproxy test-regress (cwd=$(pwd)) =="

# 1) Gates de produto/PoC já no Makefile
if make test >/tmp/l7-tlsproxy-make-test.out 2>&1; then
	pass "make test (health/gates)"
else
	fail "make test"
	tail -20 /tmp/l7-tlsproxy-make-test.out || true
fi

# 2) Política: nenhum bypass TLS activo nos scripts deste directório
#    (comentários que mencionam a proibição são OK).
BYPASS_HITS=$(grep -nE 'curl[[:space:]]+[^|]*-[a-zA-Z]*k|verify_mode[[:space:]]*=[[:space:]]*ssl\.CERT_NONE|--ignore-certificate-errors' \
	./*.sh 2>/dev/null | grep -vE 'proibido|sem curl -k|CERT_NONE /|ignore-certificate-errors' || true)
if [ -n "$BYPASS_HITS" ]; then
	fail "bypass TLS encontrado nos scripts locais"
	echo "$BYPASS_HITS"
else
	pass "sem curl -k / CERT_NONE / ignore-certificate-errors nos *.sh"
fi

# 3) Helper tls-http-get obrigatório e usável
if [ ! -f ./tls-http-get.sh ]; then
	fail "tls-http-get.sh em falta"
else
	pass "tls-http-get.sh presente"
fi

# 4) Leaf Chromium-safe (harness D1 — mesma árvore)
LEAF="$ROOT/tests/harness/mitm-activate-hang/run-local-tls-leaf-fix.sh"
if [ ! -f "$LEAF" ]; then
	fail "harness leaf em falta: $LEAF"
elif ! command -v cc >/dev/null 2>&1 || ! command -v openssl >/dev/null 2>&1; then
	printf "SKIP: leaf regress (cc/openssl ausente)\n"
else
	if sh "$LEAF" >/tmp/l7-tlsproxy-leaf-regress.out 2>&1; then
		pass "D1 leaf (serverAuth+SAN+verify hostname)"
	else
		fail "D1 leaf regress"
		tail -40 /tmp/l7-tlsproxy-leaf-regress.out || true
	fi
fi

# 5) Versão embutida >= 0.1.3 (mint identity)
if make OUT=./.l7-regress-bin >/dev/null 2>&1 && \
	./.l7-regress-bin --version 2>/dev/null | grep -qE '0\.1\.[3-9]|0\.[2-9]'; then
	pass "version string 0.1.3+"
else
	VER=$(./.l7-regress-bin --version 2>/dev/null || echo none)
	fail "version esperada 0.1.3+ (got: $VER)"
fi
rm -f ./.l7-regress-bin

if [ "$RC" -eq 0 ]; then
	echo "ALL tlsproxy test-regress PASS"
else
	echo "tlsproxy test-regress FAILED"
fi
exit "$RC"
