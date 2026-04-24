#!/bin/sh
# Compila layer7d via src/layer7d/Makefile e testa -t / -e (versão embutida "smoke").
# Não substitui `make package` no builder FreeBSD (ver docs/04-package/validacao-lab.md).
set -e
if ! command -v cc >/dev/null 2>&1; then
	echo "smoke-layer7d: 'cc' não encontrado. Instale toolchain ou corra no builder FreeBSD." >&2
	exit 1
fi
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
case "$(uname -s)" in
Darwin)
	echo "smoke-layer7d: aviso: em Darwin/macOS o link com -lcrypto costuma falhar (OpenSSL/arquitectura); o smoke canónico é no builder FreeBSD (AGENTS.md, validacao-lab sec. 3)." >&2
	;;
esac
cd "$ROOT/src/layer7d"
SMOKE_VER="${TMPDIR:-/tmp}/l7smoke.$$"
mkdir "$SMOKE_VER" || exit 1
trap 'rm -rf "$SMOKE_VER"; rm -f layer7d-smoke' EXIT
printf '"smoke"\n' > "$SMOKE_VER/version.str"
rm -f layer7d-smoke
SRCS="main.c config_parse.c policy.c enforce.c license.c blacklist.c bl_config.c"
CFLAGS_NDPI="-DHAVE_NDPI=0"
LDFLAGS_NDPI=""
if [ -f /usr/local/include/ndpi/ndpi_api.h ] && [ -f /usr/local/lib/libndpi.a ]; then
	SRCS="$SRCS capture.c"
	CFLAGS_NDPI="-I/usr/local/include/ndpi -DHAVE_NDPI=1"
	LDFLAGS_NDPI="/usr/local/lib/libndpi.a -lpcap -lm -lpthread"
fi
cc -Wall -Wextra -O2 -I"$SMOKE_VER" -I. -I../common $CFLAGS_NDPI \
	-o layer7d-smoke $SRCS $LDFLAGS_NDPI -lcrypto
./layer7d-smoke -V | grep -q smoke || { echo "smoke-layer7d: -V falhou"; exit 1; }
./layer7d-smoke -t -c "$ROOT/samples/config/layer7-minimal.json" | grep -q layer7d_version || { echo "smoke-layer7d: falta layer7d_version no -t"; exit 1; }
./layer7d-smoke -t -c "$ROOT/package/pfSense-pkg-layer7/files/usr/local/etc/layer7.json.sample"
./layer7d-smoke -n -c "$ROOT/samples/config/layer7-minimal.json" -e 10.0.0.100 BitTorrent 2>&1 |
	grep -q "no pf table add" || {
	echo "smoke-layer7d: esperado 'no pf table add' (modo monitor / sem enforce)"
	exit 1
}
./layer7d-smoke -n -c "$ROOT/samples/config/layer7-enforce-smoke.json" -e 10.0.0.100 BitTorrent 2>&1 |
	grep -q "dry-run: pfctl" || {
	echo "smoke-layer7d: esperado dry-run pfctl (enforce-smoke + BitTorrent)"
	exit 1
}
./layer7d-smoke -n -c "$ROOT/samples/config/layer7-minimal.json" -e 10.0.0.99 BitTorrent 2>&1 |
	grep -q "exception" || {
	echo "smoke-layer7d: esperado exception match (IP 10.0.0.99 = whitelist)"
	exit 1
}
./layer7d-smoke -n -c "$ROOT/samples/config/layer7-minimal.json" -e 192.168.77.10 HTTP Web 2>&1 |
	grep -q "exception" || {
	echo "smoke-layer7d: esperado exception match (CIDR 192.168.77.0/24)"
	exit 1
}
rm -f layer7d-smoke
echo "smoke-layer7d: OK"
