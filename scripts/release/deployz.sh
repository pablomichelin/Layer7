#!/bin/sh
# deployz.sh — Publicacao do pacote Layer7 no canal oficial de GitHub Releases
# Executar no builder FreeBSD. Gera .pkg, .pkg.sha256, install.sh e
# uninstall.sh versionados e publica o conjunto no release.
# Ver: scripts/release/README.md e docs/10-license-server/MANUAL-INSTALL.md
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

usage() {
  echo "Uso: $0 --repo-owner OWNER --repo-name REPO --version VERSION [--port-dir DIR] [--skip-tag] [--skip-push]"
  echo ""
  echo "Exemplo:"
  echo "  $0 --repo-owner pablomichelin --repo-name Layer7 --version 1.8.0"
  echo ""
  echo "Opções:"
  echo "  --repo-owner   Dono do repositório GitHub"
  echo "  --repo-name    Nome do repositório"
  echo "  --version      Versão (ex: 1.8.0); tag será v<VERSION>"
  echo "  --port-dir     Diretório do port (default: package/pfSense-pkg-layer7)"
  echo "  --skip-tag     Não criar tag git"
  echo "  --skip-push    Não fazer git push nem push --tags"
  exit 1
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "ERRO: comando obrigatório não encontrado: $1" >&2
    exit 1
  }
}

REPO_OWNER=""
REPO_NAME=""
VERSION=""
PORT_DIR="package/pfSense-pkg-layer7"
SKIP_TAG="0"
SKIP_PUSH="0"

while [ $# -gt 0 ]; do
  case "$1" in
    --repo-owner) REPO_OWNER="$2"; shift 2 ;;
    --repo-name)  REPO_NAME="$2";  shift 2 ;;
    --version)    VERSION="$2";     shift 2 ;;
    --port-dir)   PORT_DIR="$2";    shift 2 ;;
    --skip-tag)   SKIP_TAG="1";     shift 1 ;;
    --skip-push)  SKIP_PUSH="1";    shift 1 ;;
    *) usage ;;
  esac
done

[ -n "$REPO_OWNER" ] || usage
[ -n "$REPO_NAME" ]  || usage
[ -n "$VERSION" ]    || usage

# Validar dependências
require_cmd git
require_cmd make
require_cmd gh
require_cmd sha256
require_cmd find

# Validar FreeBSD
[ "$(uname -s)" = "FreeBSD" ] || {
  echo "ERRO: este script deve rodar em FreeBSD." >&2
  exit 1
}

# Validar repositório git
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || {
  echo "ERRO: execute dentro do repositório git." >&2
  exit 1
}

# Validar working tree limpo
if [ -n "$(git status --porcelain)" ]; then
  echo "ERRO: working tree não está limpo. Commit ou stash antes do deploy." >&2
  echo "      git status --short" >&2
  exit 1
fi

# Port dir absoluto (relativo à raiz do repo)
PORT_ABS="$REPO_ROOT/$PORT_DIR"
[ -d "$PORT_ABS" ] || {
  echo "ERRO: port dir não existe: $PORT_ABS" >&2
  exit 1
}

TAG="v$VERSION"

echo "==> Build do pacote em $PORT_DIR"
(
  cd "$PORT_ABS"
  make package
)

# Localizar .pkg mais recente
PKG_PATH="$(find "$PORT_ABS" -type f -name '*.pkg' 2>/dev/null | sort | tail -n 1)"
[ -n "$PKG_PATH" ] || {
  echo "ERRO: nenhum .pkg encontrado após make package em $PORT_DIR." >&2
  echo "      Procurar com: find $PORT_DIR -name '*.pkg'" >&2
  exit 1
}

# Gerar .sha256
SHA_PATH="${PKG_PATH}.sha256"
sha256 "$PKG_PATH" > "$SHA_PATH"

PKG_FILE="$(basename "$PKG_PATH")"
SHA_FILE="$(basename "$SHA_PATH")"

# Gerar install.sh e uninstall.sh fixados a esta release
TMP_INSTALL="$(mktemp /tmp/install-release.XXXXXX.sh)"
sed \
  -e "s/^REPO_OWNER=\".*\"$/REPO_OWNER=\"${REPO_OWNER}\"/" \
  -e "s/^REPO_NAME=\".*\"$/REPO_NAME=\"${REPO_NAME}\"/" \
  -e "s/^DEFAULT_VERSION=\"\"$/DEFAULT_VERSION=\"${VERSION}\"/" \
  "$SCRIPT_DIR/install.sh" > "$TMP_INSTALL"
chmod +x "$TMP_INSTALL"

TMP_UNINSTALL="$(mktemp /tmp/uninstall-release.XXXXXX.sh)"
sed \
  -e "s/^REPO_OWNER=\".*\"$/REPO_OWNER=\"${REPO_OWNER}\"/" \
  -e "s/^REPO_NAME=\".*\"$/REPO_NAME=\"${REPO_NAME}\"/" \
  -e "s/^RELEASE_VERSION_HINT=\"\"$/RELEASE_VERSION_HINT=\"${VERSION}\"/" \
  "$SCRIPT_DIR/uninstall.sh" > "$TMP_UNINSTALL"
chmod +x "$TMP_UNINSTALL"

# Criar tag se necessário
if [ "$SKIP_TAG" = "0" ]; then
  if ! git rev-parse "$TAG" >/dev/null 2>&1; then
    echo "==> Criando tag $TAG"
    git tag "$TAG"
  fi
fi

# Push se não skip
if [ "$SKIP_PUSH" = "0" ]; then
  echo "==> git push"
  git push
  if [ "$SKIP_TAG" = "0" ]; then
    echo "==> git push origin $TAG"
    git push origin "$TAG"
  fi
fi

# Criar GitHub Release
echo "==> Criando GitHub Release $TAG"
gh release create "$TAG" \
  "$PKG_PATH" \
  "$SHA_PATH" \
  "$TMP_INSTALL#install.sh" \
  "$TMP_UNINSTALL#uninstall.sh" \
  --title "$TAG" \
  --notes "Release oficial do Layer7 para pfSense CE. Ver docs/10-license-server/MANUAL-INSTALL.md"

# Limpar temp
rm -f "$TMP_INSTALL"
rm -f "$TMP_UNINSTALL"

# Saída final
echo ""
echo "=============================================="
echo "Release criado: $TAG"
echo "Asset principal: $PKG_FILE"
echo ""
echo "Comando único para instalar no pfSense:"
echo "  fetch -o /tmp/install.sh https://github.com/${REPO_OWNER}/${REPO_NAME}/releases/download/${TAG}/install.sh && sh /tmp/install.sh"
echo "=============================================="
