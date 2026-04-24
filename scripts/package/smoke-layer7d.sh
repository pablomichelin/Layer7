#!/bin/sh
# Compila layer7d via src/layer7d/Makefile e testa -t / -e (versão embutida "smoke").
# Não substitui `make package` no builder FreeBSD (ver docs/04-package/validacao-lab.md).
#
# Plataformas:
#   - FreeBSD: compila o license.c real e linka -lcrypto (idêntico ao port).
#   - Linux: apoio de CI com stub local de licenciamento.
#   - macOS/Darwin: bloqueado por defeito; o Mac e workspace de edicao/git/docs.
set -e
if ! command -v cc >/dev/null 2>&1; then
	echo "smoke-layer7d: 'cc' não encontrado. Instale toolchain ou corra no builder FreeBSD." >&2
	exit 1
fi
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SMOKE_OS="$(uname -s)"
case "$SMOKE_OS" in
Darwin)
	echo "smoke-layer7d: erro: macOS nao e ambiente de validacao tecnica do Layer7." >&2
	echo "smoke-layer7d: use o builder FreeBSD para smoke/build e o pfSense appliance para validacao." >&2
	exit 2
	;;
Linux)
	echo "smoke-layer7d: nota: em Linux o smoke usa stub de licenciamento (license.c é FreeBSD-only)." >&2
	;;
esac
cd "$ROOT/src/layer7d"
SMOKE_VER="${TMPDIR:-/tmp}/l7smoke.$$"
mkdir "$SMOKE_VER" || exit 1
trap 'rm -rf "$SMOKE_VER"; rm -f layer7d-smoke' EXIT
printf '"smoke"\n' > "$SMOKE_VER/version.str"
rm -f layer7d-smoke

LICENSE_SRC="license.c"
LDFLAGS_CRYPTO="-lcrypto"
if [ "$SMOKE_OS" != "FreeBSD" ]; then
	cat > "$SMOKE_VER/license_smoke_stub.c" <<'STUB'
/* Stub de licenciamento — exclusivo para smoke em Linux/Darwin.
 * Substitui src/layer7d/license.c (que usa headers FreeBSD-only).
 * Sempre devolve dev_mode=1 / valid=1, equivalente ao comportamento do
 * license.c real quando a chave Ed25519 embutida está zerada (DEV MODE).
 * Não é instalado no pacote; nunca executa fora do CI/smoke.
 */
#include "license.h"
#include <stdio.h>
#include <string.h>

int
layer7_hw_fingerprint(char *out, size_t outsz)
{
	if (out == NULL || outsz < L7_HW_ID_LEN)
		return -1;
	memset(out, '0', L7_HW_ID_LEN - 1);
	out[L7_HW_ID_LEN - 1] = '\0';
	return 0;
}

int
layer7_license_check(struct l7_license_info *info)
{
	if (info == NULL)
		return -1;
	memset(info, 0, sizeof(*info));
	info->dev_mode = 1;
	info->valid = 1;
	(void)layer7_hw_fingerprint(info->hardware_id,
	    sizeof(info->hardware_id));
	snprintf(info->error, sizeof(info->error),
	    "smoke stub — license verification skipped");
	return 0;
}

int
layer7_activate(const char *key, const char *url)
{
	(void)key;
	(void)url;
	fprintf(stderr, "smoke stub: layer7_activate is a no-op\n");
	return 0;
}
STUB
	LICENSE_SRC="$SMOKE_VER/license_smoke_stub.c"
	LDFLAGS_CRYPTO=""
fi

SRCS="main.c config_parse.c policy.c enforce.c $LICENSE_SRC blacklist.c bl_config.c"
CFLAGS_NDPI="-DHAVE_NDPI=0"
LDFLAGS_NDPI=""
if [ -f /usr/local/include/ndpi/ndpi_api.h ] && [ -f /usr/local/lib/libndpi.a ]; then
	SRCS="$SRCS capture.c"
	CFLAGS_NDPI="-I/usr/local/include/ndpi -DHAVE_NDPI=1"
	LDFLAGS_NDPI="/usr/local/lib/libndpi.a -lpcap -lm -lpthread"
fi
cc -Wall -Wextra -O2 -I"$SMOKE_VER" -I. -I../common $CFLAGS_NDPI \
	-o layer7d-smoke $SRCS $LDFLAGS_NDPI $LDFLAGS_CRYPTO
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
