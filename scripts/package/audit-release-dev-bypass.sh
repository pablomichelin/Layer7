#!/bin/sh
# audit-release-dev-bypass.sh — GA1.6 / GA1.7 / passo 30.3
#
# Audita um .pkg FreeBSD/pfSense publicado quanto a marcadores do caminho
# de bypass de desenvolvimento (A-01 / is_dev_key).
#
# Marcadores binários (artefacto):
#   - símbolo nm "is_dev_key" (se o binário não estiver stripped)
#   - string "development key" (mensagem de license.c quando o path não é DCE)
#
# Uso:
#   sh scripts/package/audit-release-dev-bypass.sh --inventory PKG
#   sh scripts/package/audit-release-dev-bypass.sh --gate PKG
#   sh scripts/package/audit-release-dev-bypass.sh --selftest
#   sh scripts/package/audit-release-dev-bypass.sh --check-source [license.c]
#
# Exit:
#   --inventory / --selftest(ok) / --check-source(limpo): 0
#   --gate com marcadores: 1
#   --check-source com is_dev_key fora de L7_DEV_BUILD: 1
#   erro de uso/extracção: 2

set -eu

MODE="gate"
PKG=""
LICENSE_C=""
REPO_ROOT=$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)
EXPECTED_PUBKEY_HEX="${L7_PROD_PUBKEY_HEX:-8c52b6772a64749e4a57b34ba16578a1b130960b1a8e88e6c1d86dbd99fd1824}"

usage() {
	sed -n '2,22p' "$0" | tr -d '#'
	exit 2
}

while [ $# -gt 0 ]; do
	case "$1" in
	--inventory) MODE="inventory"; shift ;;
	--gate) MODE="gate"; shift ;;
	--selftest) MODE="selftest"; shift ;;
	--check-source)
		MODE="check-source"
		shift
		if [ $# -gt 0 ] && [ "${1#-}" = "$1" ]; then
			LICENSE_C=$1
			shift
		fi
		;;
	-h|--help) usage ;;
	--) shift; break ;;
	-*)
		echo "FAIL: opção desconhecida: $1" >&2
		usage
		;;
	*)
		PKG=$1
		shift
		;;
	esac
done

need_cmd() {
	if ! command -v "$1" >/dev/null 2>&1; then
		echo "FAIL: comando em falta: $1" >&2
		exit 2
	fi
}

sha256_file() {
	if command -v sha256 >/dev/null 2>&1; then
		sha256 -q "$1"
	else
		shasum -a 256 "$1" | awk '{print $1}'
	fi
}

extract_layer7d() {
	_pkg=$1
	_outdir=$2
	mkdir -p "$_outdir"
	# FreeBSD pkg actuais: zstd; alguns legados: xz.
	if zstd -dc "$_pkg" 2>/dev/null | tar xf - -C "$_outdir" 2>/dev/null; then
		:
	elif xz -dc "$_pkg" 2>/dev/null | tar xf - -C "$_outdir" 2>/dev/null; then
		:
	else
		echo "FAIL: não foi possível extrair $_pkg (zstd/xz + tar)" >&2
		return 1
	fi
	_bin=$(find "$_outdir" \( -path '*/sbin/layer7d' -o -path 'sbin/layer7d' \) -type f 2>/dev/null | head -n 1)
	if [ -z "$_bin" ] || [ ! -f "$_bin" ]; then
		echo "FAIL: usr/local/sbin/layer7d ausente no .pkg" >&2
		return 1
	fi
	printf '%s\n' "$_bin"
}

analyze_bin() {
	# stdout: KEY=value lines
	_bin=$1
	need_cmd python3
	need_cmd strings
	python3 - "$_bin" "$EXPECTED_PUBKEY_HEX" <<'PY'
import sys
from pathlib import Path
bin_path = Path(sys.argv[1])
expected = sys.argv[2].strip().lower()
data = bin_path.read_bytes()
print(f"bin_path={bin_path}")
print(f"bin_size={len(data)}")
markers = {
    "marker_development_key": b"development key",
    "marker_verification_skipped": b"license verification skipped",
    "marker_is_dev_key_string": b"is_dev_key",
    "marker_license_dev_mode": b"license_dev_mode",
}
hits = 0
for key, needle in markers.items():
    found = data.find(needle) >= 0
    print(f"{key}={'FOUND' if found else 'ABSENT'}")
    if key != "marker_license_dev_mode" and found:
        hits += 1
print(f"bypass_markers_hit={hits}")
pub = bytes.fromhex(expected)
off = data.find(pub)
print(f"pubkey_expected={expected}")
print(f"pubkey_in_bin={'FOUND' if off >= 0 else 'ABSENT'}")
print(f"pubkey_offset={off}")
# ELF strip hint
stripped = "unknown"
if data[:4] == b"\x7fELF":
    # e_shnum == 0 often means stripped section headers; file(1) is better when present
    stripped = "elf"
print(f"elf_hint={stripped}")
PY
	if command -v file >/dev/null 2>&1; then
		echo "file_desc=$(file -b "$_bin" | tr '\n' ' ')"
	fi
	sym_hit=0
	if command -v nm >/dev/null 2>&1; then
		if nm "$_bin" 2>/dev/null | grep -q 'is_dev_key'; then
			echo "symbol_is_dev_key=FOUND"
			sym_hit=1
		else
			# nm may say "no symbols" on stripped ELF
			if nm "$_bin" 2>&1 | grep -qi 'no symbols'; then
				echo "symbol_is_dev_key=ABSENT (stripped/no symbols)"
			else
				echo "symbol_is_dev_key=ABSENT"
			fi
		fi
	else
		echo "symbol_is_dev_key=SKIP (nm ausente)"
	fi
	echo "symbol_bypass_hit=$sym_hit"
}

check_source() {
	_src=${1:-"$REPO_ROOT/src/layer7d/license.c"}
	if [ ! -f "$_src" ]; then
		echo "FAIL: license.c não encontrado: $_src" >&2
		exit 2
	fi
	echo "source_path=$_src"
	if ! grep -q 'is_dev_key' "$_src" 2>/dev/null; then
		echo "source_is_dev_key=ABSENT"
		echo "source_verdict=PASS"
		return 0
	fi
	echo "source_is_dev_key=PRESENT"
	if ! grep -q '#ifdef L7_DEV_BUILD' "$_src" 2>/dev/null; then
		echo "source_L7_DEV_BUILD=ABSENT"
		echo "source_verdict=FAIL (is_dev_key sem #ifdef L7_DEV_BUILD)"
		return 1
	fi
	echo "source_L7_DEV_BUILD=PRESENT"
	if ! awk '
		/#ifdef L7_DEV_BUILD/ { d=1; next }
		/#endif/ && d { d=0; next }
		/is_dev_key[[:space:]]*\(/ { if (!d) { bad=1 } }
		END { exit bad ? 1 : 0 }
	' "$_src"; then
		echo "source_verdict=FAIL (is_dev_key() fora de #ifdef L7_DEV_BUILD)"
		return 1
	fi
	echo "source_verdict=PASS (is_dev_key apenas sob L7_DEV_BUILD)"
	return 0
}

run_selftest() {
	need_cmd mktemp
	_td=$(mktemp -d "${TMPDIR:-/tmp}/l7-dev-bypass-selftest.XXXXXX")
	trap 'rm -rf "$_td"' EXIT INT TERM
	# Fixture mínima: blob com a string do bypass (simula artefacto sujo).
	printf 'layer7d-fixture\000development key — license verification skipped\000is_dev_key\000' \
		> "$_td/dirty.bin"
	# Empacota como tar.zst fingindo layout do .pkg
	mkdir -p "$_td/pkgroot/usr/local/sbin"
	cp "$_td/dirty.bin" "$_td/pkgroot/usr/local/sbin/layer7d"
	need_cmd zstd
	(
		cd "$_td/pkgroot"
		tar cf - usr | zstd -q -o "$_td/dirty.pkg"
	)
	echo "=== selftest: dirty fixture must FAIL --gate ==="
	set +e
	analyze_out=$(analyze_bin "$_td/pkgroot/usr/local/sbin/layer7d")
	set -e
	echo "$analyze_out"
	echo "$analyze_out" | grep -q 'marker_development_key=FOUND' || {
		echo "FAIL: selftest não detectou marker_development_key" >&2
		exit 1
	}
	# Gate path via temporary copy using same logic as main
	hits=$(echo "$analyze_out" | awk -F= '/^bypass_markers_hit=/{print $2}')
	sym=$(echo "$analyze_out" | awk -F= '/^symbol_bypass_hit=/{print $2}')
	if [ "${hits:-0}" -eq 0 ] && [ "${sym:-0}" -eq 0 ]; then
		echo "FAIL: selftest esperava hits>0" >&2
		exit 1
	fi
	echo "PASS: selftest — --gate falharia correctamente no artefacto com bypass"
	return 0
}

if [ "$MODE" = "selftest" ]; then
	run_selftest
	exit 0
fi

if [ "$MODE" = "check-source" ]; then
	set +e
	if [ -n "${LICENSE_C:-}" ]; then
		check_source "$LICENSE_C"
	else
		check_source
	fi
	rc=$?
	set -e
	exit "$rc"
fi

if [ -z "$PKG" ]; then
	echo "FAIL: indique o caminho do .pkg" >&2
	usage
fi
if [ ! -f "$PKG" ]; then
	echo "FAIL: .pkg não encontrado: $PKG" >&2
	exit 2
fi

need_cmd zstd
need_cmd tar
need_cmd find
need_cmd python3

TMP=$(mktemp -d "${TMPDIR:-/tmp}/l7-audit-pkg.XXXXXX")
trap 'rm -rf "$TMP"' EXIT INT TERM

PKG_SHA=$(sha256_file "$PKG")
echo "pkg_path=$PKG"
echo "pkg_sha256=$PKG_SHA"
echo "mode=$MODE"

BIN=$(extract_layer7d "$PKG" "$TMP/root")
BIN_SHA=$(sha256_file "$BIN")
echo "layer7d_sha256=$BIN_SHA"

REPORT=$(analyze_bin "$BIN")
echo "$REPORT"

HITS=$(echo "$REPORT" | awk -F= '/^bypass_markers_hit=/{print $2}')
SYM=$(echo "$REPORT" | awk -F= '/^symbol_bypass_hit=/{print $2}')
PUB=$(echo "$REPORT" | awk -F= '/^pubkey_in_bin=/{print $2}')

echo "--- source residual (informativo; não é o .pkg) ---"
set +e
check_source || true
set -e

TOTAL=$((${HITS:-0} + ${SYM:-0}))
if [ "$TOTAL" -gt 0 ]; then
	echo "artifact_verdict=FAIL (caminho de bypass dev detectado no binário)"
	if [ "$MODE" = "gate" ]; then
		exit 1
	fi
	exit 0
fi

echo "artifact_verdict=PASS (sem marcadores binários de bypass dev)"
if [ "$PUB" != "FOUND" ]; then
	echo "WARN: pubkey de produção esperada ABSENT no binário" >&2
fi
# Nota honesta: PASS binário ≠ A-01 fechado no fonte (ver --check-source / 30.4).
exit 0
