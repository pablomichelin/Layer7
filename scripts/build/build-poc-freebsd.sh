#!/bin/sh
# Compila nDPI + layer7_ndpi_poc no FreeBSD (builder alinhado ao pfSense CE).
# Uso: ./scripts/build/build-poc-freebsd.sh
# Opcional: NDPI_TAG=4.12 BUILD_DIR=/tmp/poc-ndpi

set -e
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
NDPI_TAG="${NDPI_TAG:-4.12}"
BUILD_DIR="${BUILD_DIR:-$ROOT/build/poc-ndpi}"
JOBS="${JOBS:-4}"

case "$(uname -s 2>/dev/null)" in
FreeBSD) ;;
Linux)
	echo "Aviso: script testado para FreeBSD; em Linux ajuste paths/pkg se necessário."
	;;
*)
	echo "Use FreeBSD (builder) ou adapte o script."
	;;
esac

mkdir -p "$BUILD_DIR"
cd "$BUILD_DIR"

if [ ! -d nDPI ]; then
	git clone --depth 1 --branch "$NDPI_TAG" https://github.com/ntop/nDPI.git 2>/dev/null || \
		git clone --depth 1 https://github.com/ntop/nDPI.git
fi
cd nDPI
if [ ! -f ./configure ]; then
	./autogen.sh
fi
if [ ! -f src/lib/.libs/libndpi.a ] && [ ! -f src/lib/.libs/libndpi.so ]; then
	./configure
	make -j"$JOBS"
fi

NDPI_SRC="$BUILD_DIR/nDPI"
export NDPI_SRC
cd "$ROOT"

# Linkagem: preferir .so se existir (menos deps estáticas)
if [ -f "$NDPI_SRC/src/lib/.libs/libndpi.so" ]; then
	cc -O2 -Wall -o "$BUILD_DIR/layer7_ndpi_poc" "$ROOT/src/poc_ndpi/layer7_ndpi_poc.c" \
		-I "$NDPI_SRC/src/include" \
		-L "$NDPI_SRC/src/lib/.libs" -Wl,-rpath,"$NDPI_SRC/src/lib/.libs" \
		-lndpi -lpcap -lm
else
	cc -O2 -Wall -o "$BUILD_DIR/layer7_ndpi_poc" "$ROOT/src/poc_ndpi/layer7_ndpi_poc.c" \
		-I "$NDPI_SRC/src/include" \
		"$NDPI_SRC/src/lib/.libs/libndpi.a" -lpcap -lm
fi

echo "Binário: $BUILD_DIR/layer7_ndpi_poc"
echo "Teste:   $BUILD_DIR/layer7_ndpi_poc /caminho/arquivo.pcap"
