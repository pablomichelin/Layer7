#!/bin/sh
# test-prod-strip.sh — GA2.4 / GA2.5 / passo 30.5 (BG-115)
#
# Valida que o layer7d do .pkg de release está strippado e sem marcadores
# de funções de licença em nm/strings (is_dev_key, layer7_license_check).
# Em FreeBSD, corre também -t e --fingerprint (GA2.5).
#
# Uso:
#   sh scripts/package/test-prod-strip.sh --gate PKG
#   sh scripts/package/test-prod-strip.sh --inventory PKG
#
# Exit: 0 PASS · 1 FAIL de gate · 2 erro de uso/extracção
#
# Limite aceite (GA2.11): strip reduz legibilidade de core dumps — deliberado.

set -eu

MODE="gate"
PKG=""
REPO_ROOT=$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)
RUN_SMOKE=1

usage() {
	sed -n '2,16p' "$0" | tr -d '#'
	exit 2
}

while [ $# -gt 0 ]; do
	case "$1" in
	--inventory) MODE="inventory"; shift ;;
	--gate) MODE="gate"; shift ;;
	--no-smoke) RUN_SMOKE=0; shift ;;
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

if [ -z "$PKG" ]; then
	echo "FAIL: indique o caminho do .pkg" >&2
	usage
fi
if [ ! -f "$PKG" ]; then
	echo "FAIL: .pkg inexistente: $PKG" >&2
	exit 2
fi

need_cmd() {
	if ! command -v "$1" >/dev/null 2>&1; then
		echo "FAIL: comando em falta: $1" >&2
		exit 2
	fi
}

need_cmd tar
need_cmd nm
need_cmd strings
need_cmd file

TMP=$(mktemp -d "${TMPDIR:-/tmp}/l7-strip.XXXXXX")
trap 'rm -rf "$TMP"' EXIT

# Extrair sbin/layer7d do .pkg (zstd/xz + tar; paths com / inicial)
extract_layer7d() {
	_pkg=$1
	_outdir=$2
	mkdir -p "$_outdir"
	if zstd -dc "$_pkg" 2>/dev/null | tar xf - -C "$_outdir" 2>/dev/null; then
		:
	elif xz -dc "$_pkg" 2>/dev/null | tar xf - -C "$_outdir" 2>/dev/null; then
		:
	elif tar xf "$_pkg" -C "$_outdir" 2>/dev/null; then
		:
	else
		echo "FAIL: não foi possível extrair $_pkg (zstd/xz + tar)" >&2
		exit 2
	fi
	_bin=$(find "$_outdir" \( -path '*/sbin/layer7d' -o -path 'sbin/layer7d' \) -type f 2>/dev/null | head -n 1)
	if [ -z "$_bin" ] || [ ! -f "$_bin" ]; then
		echo "FAIL: usr/local/sbin/layer7d ausente no .pkg" >&2
		exit 2
	fi
	printf '%s\n' "$_bin"
}

BIN=$(extract_layer7d "$PKG" "$TMP")
chmod +x "$BIN"

FILE_OUT=$(file "$BIN" || true)
echo "file=$FILE_OUT"

STRIPPED=0
case "$FILE_OUT" in
*[Nn]ot\ stripped*) STRIPPED=0 ;;
*[Ss]tripped*) STRIPPED=1 ;;
*)
	# FreeBSD file(1) por vezes só diz "ELF ... dynamically/statically linked"
	# Fallback: nm sem tabela de símbolos ⇒ stripped
	if nm "$BIN" 2>&1 | grep -qiE 'no symbols|not found'; then
		STRIPPED=1
	fi
	;;
esac
echo "stripped=$STRIPPED"

NM_OUT=$(nm "$BIN" 2>&1 || true)
echo "nm_head=$(echo "$NM_OUT" | head -n 3 | tr '\n' ';')"

SYM_DEV=0
SYM_LIC=0
echo "$NM_OUT" | grep -qw 'is_dev_key' && SYM_DEV=1 || true
echo "$NM_OUT" | grep -qw 'layer7_license_check' && SYM_LIC=1 || true
# "no symbols" conta como ausência
if echo "$NM_OUT" | grep -qiE 'no symbols|not found'; then
	SYM_DEV=0
	SYM_LIC=0
fi
echo "symbol_is_dev_key=$SYM_DEV"
echo "symbol_layer7_license_check=$SYM_LIC"

STR_DEV=0
STR_LIC=0
strings "$BIN" | grep -Fq 'is_dev_key' && STR_DEV=1 || true
strings "$BIN" | grep -Fq 'layer7_license_check' && STR_LIC=1 || true
echo "string_is_dev_key=$STR_DEV"
echo "string_layer7_license_check=$STR_LIC"

SMOKE_T=skip
SMOKE_FP=skip
OS=$(uname -s)
if [ "$RUN_SMOKE" = 1 ] && [ "$OS" = "FreeBSD" ]; then
	if "$BIN" -t >/dev/null 2>&1; then
		SMOKE_T=pass
	else
		SMOKE_T=fail
	fi
	if "$BIN" --fingerprint >/dev/null 2>&1; then
		SMOKE_FP=pass
	else
		SMOKE_FP=fail
	fi
	echo "smoke_t=$SMOKE_T"
	echo "smoke_fingerprint=$SMOKE_FP"
	echo "version=$("$BIN" -V 2>/dev/null || true)"
else
	echo "smoke_t=skip (OS=$OS ou --no-smoke)"
	echo "smoke_fingerprint=skip"
fi

echo "diagnostic_limit=GA2.11: core dumps de produção menos legíveis após strip (aceite deliberado; sem ofuscação R-G)"

FAIL=0
if [ "$MODE" = "gate" ]; then
	if [ "$STRIPPED" != 1 ]; then
		echo "FAIL: GA2.4 — binário não strippado" >&2
		FAIL=1
	fi
	if [ "$SYM_DEV" = 1 ] || [ "$SYM_LIC" = 1 ]; then
		echo "FAIL: GA2.4 — símbolos de licença presentes em nm" >&2
		FAIL=1
	fi
	if [ "$STR_DEV" = 1 ] || [ "$STR_LIC" = 1 ]; then
		echo "FAIL: GA2.4 — strings is_dev_key/layer7_license_check presentes" >&2
		FAIL=1
	fi
	if [ "$SMOKE_T" = "fail" ] || [ "$SMOKE_FP" = "fail" ]; then
		echo "FAIL: GA2.5 — smoke -t/--fingerprint" >&2
		FAIL=1
	fi
	if [ "$FAIL" = 0 ]; then
		echo "PASS: GA2.4/GA2.5 (strip + ausência marcadores + smoke quando FreeBSD)"
		echo "PASS: GA2.11 limite de diagnóstico registado"
	fi
	exit "$FAIL"
fi

# inventory — só reporta
echo "MODE=inventory"
exit 0
