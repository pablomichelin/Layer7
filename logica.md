#!/bin/sh
set -eu

usage() {
  echo "Uso: $0 --repo-owner OWNER --repo-name REPO --version VERSION [--port-dir DIR] [--skip-tag] [--skip-push]"
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
    --repo-name) REPO_NAME="$2"; shift 2 ;;
    --version) VERSION="$2"; shift 2 ;;
    --port-dir) PORT_DIR="$2"; shift 2 ;;
    --skip-tag) SKIP_TAG="1"; shift 1 ;;
    --skip-push) SKIP_PUSH="1"; shift 1 ;;
    *) usage ;;
  esac
done

como [ -n "$REPO_NAME" ] || usage
[ -n "$VERSION" ] || usage

[ "$(uname -s)" = "FreeBSD" ] || {
  echo "ERRO: este script deve rodar em FreeBSD." >&2
  exit 1
}

require_cmd git
require_cmd make
require_cmd gh
require_cmd sha256
require_cmd find
require_cmd awk
require_cmd sed

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || {
  echo "ERRO: execute dentro do repositório git." >&2
  exit 1
}

if [ -n "$(git status --porcelain)" ]; then
  echo "ERRO: working tree não está limpo. Commit/stash antes do deploy." >&2
  exit 1
fi

TAG="v$VERSION"

echo "==> Build do pacote"
(
  cd "$PORT_DIR"
  make package
)

TXZ_PATH="$(find "$PORT_DIR" -type f -name '*.txz' | sort | tail -n 1)"
[ -n "$TXZ_PATH" ] || {
  echo "ERRO: nenhum .txz encontrado após make package." >&2
  exit 1
}

SHA_PATH="${TXZ_PATH}.sha256"
sha256 "$TXZ_PATH" > "$SHA_PATH"

PKG_FILE="$(basename "$TXZ_PATH")"
SHA_FILE="$(basename "$SHA_PATH")"

TMP_INSTALL="$(mktemp /tmp/install-lab.XXXXXX.sh)"
cat > "$TMP_INSTALL" <<EOF
#!/bin/sh
set -eu

OWNER="${REPO_OWNER}"
REPO="${REPO_NAME}"
TAG="${TAG}"
PKG_FILE="${PKG_FILE}"
SHA_FILE="${SHA_FILE}"

BASE_URL="https://github.com/\${OWNER}/\${REPO}/releases/download/\${TAG}"
PKG_URL="\${BASE_URL}/\${PKG_FILE}"
SHA_URL="\${BASE_URL}/\${SHA_FILE}"

echo "==> Baixando pacote"
fetch -o "/tmp/\${PKG_FILE}" "\${PKG_URL}"

echo "==> Baixando checksum"
fetch -o "/tmp/\${SHA_FILE}" "\${SHA_URL}" || true

if [ -f "/tmp/\${SHA_FILE}" ]; then
  echo "==> Validando checksum"
  (cd /tmp && sha256 -c "\${SHA_FILE}") || {
    echo "ERRO: checksum inválido." >&2
    exit 1
  }
fi

echo "==> Instalando pacote"
pkg add -f "/tmp/\${PKG_FILE}"

echo "==> Pacote instalado"
pkg info | grep layer7 || true

echo
echo "Próximos comandos:"
echo "cp /usr/local/etc/layer7.json.sample /usr/local/etc/layer7.json"
echo "service layer7d onestart"
echo "service layer7d status"
EOF
chmod +x "$TMP_INSTALL"

if [ "$SKIP_TAG" = "0" ]; then
  if ! git rev-parse "$TAG" >/dev/null 2>&1; then
    git tag "$TAG"
  fi
fi

if [ "$SKIP_PUSH" = "0" ]; then
  git push
  if [ "$SKIP_TAG" = "0" ]; then
    git push origin "$TAG"
  fi
fi

echo "==> Criando GitHub Release"
gh release create "$TAG" \
  "$TXZ_PATH" \
  "$SHA_PATH" \
  "$TMP_INSTALL#install-lab.sh" \
  --title "$TAG" \
  --notes "Release de lab para instalação manual no pfSense."

echo
echo "Release criado: $TAG"
echo "Comando único no pfSense:"
echo "fetch -o /tmp/install-layer7.sh https://github.com/${REPO_OWNER}/${REPO_NAME}/releases/download/${TAG}/install-lab.sh && sh /tmp/install-layer7.sh"