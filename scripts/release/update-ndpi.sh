#!/bin/sh
# update-ndpi.sh — Atualiza nDPI no builder FreeBSD e reconstrói o pacote Layer7.
# Executar no builder como root.
#
# Uso:
#   ./update-ndpi.sh              # atualiza nDPI + reconstrói pacote
#   ./update-ndpi.sh --ndpi-only  # só atualiza nDPI, sem rebuildar pacote
#
# Pré-requisitos:
#   - git, gmake, autoconf, automake, libtool, pkgconf instalados
#   - nDPI clonado em /root/nDPI
#   - Repo Layer7 em /root/pfsense-layer7

set -eu

NDPI_DIR="/root/nDPI"
LAYER7_DIR="/root/pfsense-layer7"
PKG_DIR="${LAYER7_DIR}/package/pfSense-pkg-layer7"

NDPI_ONLY=0
if [ "${1:-}" = "--ndpi-only" ]; then
    NDPI_ONLY=1
fi

echo "=== 1. Atualizar fonte nDPI ==="
if [ ! -d "$NDPI_DIR" ]; then
    echo "Clonando nDPI..."
    cd /root
    git clone --depth 1 https://github.com/ntop/nDPI.git
else
    cd "$NDPI_DIR"
    BEFORE=$(git rev-parse HEAD 2>/dev/null || echo "none")
    git pull
    AFTER=$(git rev-parse HEAD 2>/dev/null || echo "none")
    if [ "$BEFORE" = "$AFTER" ]; then
        echo "nDPI já está atualizado ($AFTER)"
    else
        echo "nDPI atualizado: $BEFORE -> $AFTER"
    fi
fi

echo ""
echo "=== 2. Compilar nDPI ==="
cd "$NDPI_DIR"
./autogen.sh 2>&1 | tail -2
./configure --prefix=/usr/local 2>&1 | tail -3
gmake -j4 2>&1 | tail -5
echo ""

echo "=== 3. Instalar nDPI ==="
gmake install 2>&1 | tail -3
ldconfig
echo "libndpi instalada:"
ls -la /usr/local/lib/libndpi.so*

if [ "$NDPI_ONLY" -eq 1 ]; then
    echo ""
    echo "=== Concluído (--ndpi-only) ==="
    echo "Para rebuildar o pacote Layer7, execute sem --ndpi-only"
    exit 0
fi

echo ""
echo "=== 4. Rebuildar pacote Layer7 ==="
cd "$PKG_DIR"
make clean 2>&1 | tail -2
make package DISABLE_VULNERABILITIES=yes 2>&1 | tail -5

STAGED_BIN="${PKG_DIR}/work/stage/usr/local/sbin/layer7d"
if [ ! -x "$STAGED_BIN" ]; then
    echo "ERRO: binário staged não encontrado: $STAGED_BIN"
    exit 1
fi
if ldd "$STAGED_BIN" 2>/dev/null | grep -q 'libndpi\.so'; then
    echo "ERRO: pacote gerado ainda depende de libndpi.so em runtime"
    ldd "$STAGED_BIN" || true
    exit 1
fi

PKG=$(ls -t "${PKG_DIR}/work/pkg/"*.pkg 2>/dev/null | head -1)
if [ -z "$PKG" ]; then
    echo "ERRO: pacote não encontrado após build"
    exit 1
fi

echo ""
echo "=== Concluído ==="
echo "Pacote: $PKG"
echo "Versão daemon: $(${PKG_DIR}/work/stage/usr/local/sbin/layer7d -V 2>/dev/null || echo '?')"
echo ""
echo "Próximo passo: transferir para o pfSense e instalar com:"
echo "  pkg delete -y pfSense-pkg-layer7"
echo "  IGNORE_OSVERSION=yes pkg add -f $(basename "$PKG")"
echo "  service layer7d onerestart"
