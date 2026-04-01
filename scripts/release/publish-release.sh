#!/bin/sh
# publish-release.sh — Publica um stage dir ja assinado no GitHub Releases
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
. "$SCRIPT_DIR/common.sh"

usage() {
  echo "Uso: $0 --stage-dir DIR --repo-owner OWNER --repo-name REPO --version VERSION [--skip-tag] [--skip-push]"
  exit 1
}

STAGE_DIR=""
REPO_OWNER=""
REPO_NAME=""
VERSION=""
SKIP_TAG="0"
SKIP_PUSH="0"

while [ $# -gt 0 ]; do
  case "$1" in
    --stage-dir) STAGE_DIR="$2"; shift 2 ;;
    --repo-owner) REPO_OWNER="$2"; shift 2 ;;
    --repo-name) REPO_NAME="$2"; shift 2 ;;
    --version) VERSION="$2"; shift 2 ;;
    --skip-tag) SKIP_TAG="1"; shift 1 ;;
    --skip-push) SKIP_PUSH="1"; shift 1 ;;
    *) usage ;;
  esac
done

[ -n "$STAGE_DIR" ] || usage
[ -n "$REPO_OWNER" ] || usage
[ -n "$REPO_NAME" ] || usage
[ -n "$VERSION" ] || usage
[ -d "$STAGE_DIR" ] || die "stage dir nao existe: $STAGE_DIR"

require_cmd git
require_cmd gh
require_cmd openssl

TAG="v$VERSION"

"$SCRIPT_DIR/verify-release.sh" --stage-dir "$STAGE_DIR"

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "execute dentro do repositorio git"

if [ "$SKIP_TAG" = "0" ]; then
  if ! git rev-parse "$TAG" >/dev/null 2>&1; then
    echo "==> Criando tag $TAG"
    git tag "$TAG"
  fi
fi

if [ "$SKIP_PUSH" = "0" ]; then
  echo "==> git push"
  git push
  if [ "$SKIP_TAG" = "0" ]; then
    echo "==> git push origin $TAG"
    git push origin "$TAG"
  fi
fi

echo "==> Criando GitHub Release $TAG"
gh release create "$TAG" \
  "$STAGE_DIR"/* \
  --title "$TAG" \
  --notes "Release oficial assinada do Layer7 para pfSense CE. Ver docs/06-releases/RELEASE-SIGNING.md"

echo ""
echo "=============================================="
echo "Release publicada: $TAG"
echo "Stage dir publicado: $STAGE_DIR"
echo "=============================================="
