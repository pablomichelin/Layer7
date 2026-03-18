#!/bin/sh
# deployz.sh — Deploy de pacote Layer7 para GitHub Release (lab/teste)
# Executar no builder FreeBSD. Gera .txz, .sha256, install-lab.sh e publica.
# Ver: scripts/release/README.md e docs/04-package/deploy-github-lab.md
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
TEMPLATE="$SCRIPT_DIR/install-lab.sh.template"

usage() {
  echo "Uso: $0 --repo-owner OWNER --repo-name REPO --version VERSION [--port-dir DIR] [--skip-tag] [--skip-push]"
  echo ""
  echo "Exemplo:"
  echo "  $0 --repo-owner pablomichelin --repo-name pfsense-layer7 --version 0.0.31"
  echo ""
  echo "Opções:"
  echo "  --repo-owner   Dono do repositório GitHub"
  echo "  --repo-name    Nome do repositório"
  echo "  --version      Versão (ex: 0.0.31); tag será v<VERSION>"
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
require_cmd awk

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

# Validar template
[ -f "$TEMPLATE" ] || {
  echo "ERRO: template em falta: $TEMPLATE" >&2
  exit 1
}

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

# Localizar .txz mais recente
TXZ_PATH="$(find "$PORT_ABS" -type f -name '*.txz' 2>/dev/null | sort | tail -n 1)"
[ -n "$TXZ_PATH" ] || {
  echo "ERRO: nenhum .txz encontrado após make package em $PORT_DIR." >&2
  echo "      Procurar com: find $PORT_DIR -name '*.txz'" >&2
  exit 1
}

# Gerar .sha256
SHA_PATH="${TXZ_PATH}.sha256"
sha256 "$TXZ_PATH" > "$SHA_PATH"

PKG_FILE="$(basename "$TXZ_PATH")"
SHA_FILE="$(basename "$SHA_PATH")"

# Gerar install-lab.sh a partir do template
TMP_INSTALL="$(mktemp /tmp/install-lab.XXXXXX.sh)"
awk -v owner="$REPO_OWNER" -v repo="$REPO_NAME" -v ver="$VERSION" -v tag="$TAG" -v pkg="$PKG_FILE" -v sha="$SHA_FILE" '
  {
    gsub(/@REPO_OWNER@/, owner);
    gsub(/@REPO_NAME@/, repo);
    gsub(/@VERSION@/, ver);
    gsub(/@TAG@/, tag);
    gsub(/@PACKAGE_NAME@/, pkg);
    gsub(/@SHA256_FILE@/, sha);
    print
  }
' "$TEMPLATE" > "$TMP_INSTALL"
chmod +x "$TMP_INSTALL"

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
  "$TXZ_PATH" \
  "$SHA_PATH" \
  "$TMP_INSTALL#install-lab.sh" \
  --title "$TAG" \
  --notes "Release de lab para instalação manual no pfSense. Ver docs/04-package/deploy-github-lab.md"

# Limpar temp
rm -f "$TMP_INSTALL"

# Saída final
echo ""
echo "=============================================="
echo "Release criado: $TAG"
echo "Asset principal: $PKG_FILE"
echo ""
echo "Comando único para instalar no pfSense:"
echo "  fetch -o /tmp/install-lab.sh https://github.com/${REPO_OWNER}/${REPO_NAME}/releases/download/${TAG}/install-lab.sh && sh /tmp/install-lab.sh"
echo "=============================================="
