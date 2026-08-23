#!/bin/sh
# verify-latest-release.sh — BG-162 / segurança do canal de download
#
# Confirma que GitHub /releases/latest é uma release de pacote (não
# prerelease), com .pkg + .sha256 + install.sh, e que a tag coincide com
# o PORTVERSION esperado quando passado.
#
# Releases históricas continuam visíveis para rollback documentado; o
# canal oficial do GUI/updater é só `latest`.
#
# Uso:
#   sh scripts/release/verify-latest-release.sh
#   sh scripts/release/verify-latest-release.sh --expect 1.9.69
#
set -eu

REPO="${LAYER7_RELEASE_REPO:-pablomichelin/Layer7}"
EXPECT=""

while [ $# -gt 0 ]; do
	case "$1" in
	--expect) EXPECT="$2"; shift 2 ;;
	--repo) REPO="$2"; shift 2 ;;
	*) echo "Uso: $0 [--expect VERSION] [--repo owner/name]" >&2; exit 2 ;;
	esac
done

if ! command -v gh >/dev/null 2>&1; then
	echo "FAIL: gh CLI ausente" >&2
	exit 1
fi

JSON="$(gh api "repos/${REPO}/releases/latest")"

tag="$(printf '%s' "$JSON" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d.get("tag_name") or "")')"
draft="$(printf '%s' "$JSON" | python3 -c 'import json,sys; d=json.load(sys.stdin); print("true" if d.get("draft") else "false")')"
prerelease="$(printf '%s' "$JSON" | python3 -c 'import json,sys; d=json.load(sys.stdin); print("true" if d.get("prerelease") else "false")')"
assets="$(printf '%s' "$JSON" | python3 -c '
import json,sys
d=json.load(sys.stdin)
print("\n".join(a.get("name") or "" for a in d.get("assets") or []))
')"

version="${tag#v}"
echo "latest_tag=$tag"
echo "draft=$draft"
echo "prerelease=$prerelease"

fail=0
if [ "$draft" = "true" ]; then
	echo "FAIL: latest é draft" >&2
	fail=1
fi
if [ "$prerelease" = "true" ]; then
	echo "FAIL: latest é prerelease (o GUI ignora e o canal oficial parte)" >&2
	fail=1
fi
echo "$version" | grep -Eq '^[0-9]+\.[0-9]+' || {
	echo "FAIL: tag $tag nao parece versao de pacote" >&2
	fail=1
}

has_pkg=0
has_sha=0
has_install=0
printf '%s\n' "$assets" | while IFS= read -r name; do
	[ -n "$name" ] || continue
	echo "asset=$name"
done

printf '%s\n' "$assets" | grep -q "pfSense-pkg-layer7-${version}.pkg$" && has_pkg=1 || true
printf '%s\n' "$assets" | grep -q "pfSense-pkg-layer7-${version}.pkg.sha256$" && has_sha=1 || true
printf '%s\n' "$assets" | grep -q "^install.sh$" && has_install=1 || true

# grep in subshell issue: compute without pipe to while
case "$assets" in
*"pfSense-pkg-layer7-${version}.pkg"*) has_pkg=1 ;;
esac
case "$assets" in
*"pfSense-pkg-layer7-${version}.pkg.sha256"*) has_sha=1 ;;
esac
case "$assets" in
*install.sh*) has_install=1 ;;
esac

if [ "$has_pkg" -ne 1 ]; then
	echo "FAIL: falta asset pfSense-pkg-layer7-${version}.pkg" >&2
	fail=1
fi
if [ "$has_sha" -ne 1 ]; then
	echo "FAIL: falta asset .sha256" >&2
	fail=1
fi
if [ "$has_install" -ne 1 ]; then
	echo "FAIL: falta install.sh no latest" >&2
	fail=1
fi

if [ -n "$EXPECT" ] && [ "$version" != "$EXPECT" ]; then
	echo "FAIL: latest=$version esperado=$EXPECT" >&2
	echo "O canal oficial de download nao e a versao que este bloco espera." >&2
	fail=1
fi

if [ "$fail" -ne 0 ]; then
	exit 1
fi

echo "PASS: latest=$version e o canal oficial (pkg+sha256+install.sh, nao prerelease)"
exit 0
