#!/bin/sh
# Compila layer7d via src/layer7d/Makefile e testa -t / -e (versão embutida "smoke").
# Não substitui `make package` no builder FreeBSD (ver docs/04-package/validacao-lab.md).
set -e
if ! command -v cc >/dev/null 2>&1; then
	echo "smoke-layer7d: 'cc' não encontrado. Instale toolchain ou corra no builder FreeBSD." >&2
	exit 1
fi
if ! command -v make >/dev/null 2>&1; then
	echo "smoke-layer7d: 'make' não encontrado (necessário para o Makefile em src/layer7d)." >&2
	exit 1
fi
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT/src/layer7d"
SMOKE_VER="${TMPDIR:-/tmp}/l7smoke.$$"
mkdir "$SMOKE_VER" || exit 1
trap 'rm -rf "$SMOKE_VER"; rm -f layer7d-smoke' EXIT
printf '"smoke"\n' > "$SMOKE_VER/version.str"
rm -f layer7d-smoke
make OUT=layer7d-smoke VSTR_DIR="$SMOKE_VER"
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
