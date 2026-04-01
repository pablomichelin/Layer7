#!/bin/sh
# deployz.sh — Preparacao do stage dir oficial de release no builder
# Executar no builder FreeBSD. Gera .pkg, .pkg.sha256, install.sh,
# uninstall.sh e o manifesto versionado ainda nao assinado.
# A assinatura e a publicacao acontecem fora do builder.
# Ver: scripts/release/README.md e docs/06-releases/RELEASE-SIGNING.md
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
. "$SCRIPT_DIR/common.sh"

usage() {
  echo "Uso: $0 --repo-owner OWNER --repo-name REPO --version VERSION [--port-dir DIR] [--stage-dir DIR] [--source-repo-url URL]"
  echo ""
  echo "Exemplo:"
  echo "  $0 --repo-owner pablomichelin --repo-name Layer7 --version 1.8.0"
  echo ""
  echo "Opções:"
  echo "  --repo-owner   Dono do repositório GitHub"
  echo "  --repo-name    Nome do repositório"
  echo "  --version      Versão (ex: 1.8.0); tag será v<VERSION>"
  echo "  --port-dir     Diretório do port (default: package/pfSense-pkg-layer7)"
  echo "  --stage-dir    Diretório de staging da release (default: /tmp/layer7-release-v<VERSION>)"
  echo "  --source-repo-url URL  URL do repositório de origem (default: https://github.com/pablomichelin/pfsense-layer7)"
  exit 1
}

REPO_OWNER=""
REPO_NAME=""
VERSION=""
PORT_DIR="package/pfSense-pkg-layer7"
STAGE_DIR=""
SOURCE_REPO_URL="https://github.com/pablomichelin/pfsense-layer7"

while [ $# -gt 0 ]; do
  case "$1" in
    --repo-owner) REPO_OWNER="$2"; shift 2 ;;
    --repo-name)  REPO_NAME="$2";  shift 2 ;;
    --version)    VERSION="$2";     shift 2 ;;
    --port-dir)   PORT_DIR="$2";    shift 2 ;;
    --stage-dir)  STAGE_DIR="$2";   shift 2 ;;
    --source-repo-url) SOURCE_REPO_URL="$2"; shift 2 ;;
    *) usage ;;
  esac
done

[ -n "$REPO_OWNER" ] || usage
[ -n "$REPO_NAME" ]  || usage
[ -n "$VERSION" ]    || usage

# Validar dependências
require_cmd git
require_cmd make
require_cmd find
require_cmd sed

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
[ -n "$STAGE_DIR" ] || STAGE_DIR="/tmp/layer7-release-${TAG}"
[ ! -e "$STAGE_DIR" ] || die "stage dir ja existe: $STAGE_DIR"

SOURCE_COMMIT="$(git rev-parse HEAD)"
DISTRIBUTION_REPO_URL="https://github.com/${REPO_OWNER}/${REPO_NAME}"

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

mkdir -p "$STAGE_DIR"

PKG_FILE="$(basename "$PKG_PATH")"
STAGED_PKG="$STAGE_DIR/$PKG_FILE"
cp "$PKG_PATH" "$STAGED_PKG"

SHA_FILE="${PKG_FILE}.sha256"
STAGED_SHA="$STAGE_DIR/$SHA_FILE"
write_sha256_file "$STAGED_PKG" "$STAGED_SHA"

# Gerar install.sh e uninstall.sh fixados a esta release
STAGED_INSTALL="$STAGE_DIR/install.sh"
sed \
  -e "s/^REPO_OWNER=\".*\"$/REPO_OWNER=\"${REPO_OWNER}\"/" \
  -e "s/^REPO_NAME=\".*\"$/REPO_NAME=\"${REPO_NAME}\"/" \
  -e "s/^DEFAULT_VERSION=\"\"$/DEFAULT_VERSION=\"${VERSION}\"/" \
  "$SCRIPT_DIR/install.sh" > "$STAGED_INSTALL"
chmod +x "$STAGED_INSTALL"

STAGED_UNINSTALL="$STAGE_DIR/uninstall.sh"
sed \
  -e "s/^REPO_OWNER=\".*\"$/REPO_OWNER=\"${REPO_OWNER}\"/" \
  -e "s/^REPO_NAME=\".*\"$/REPO_NAME=\"${REPO_NAME}\"/" \
  -e "s/^RELEASE_VERSION_HINT=\"\"$/RELEASE_VERSION_HINT=\"${VERSION}\"/" \
  "$SCRIPT_DIR/uninstall.sh" > "$STAGED_UNINSTALL"
chmod +x "$STAGED_UNINSTALL"

MANIFEST_PATH="$(manifest_path "$STAGE_DIR")"
{
  echo "manifest_version=1"
  echo "release_version=$VERSION"
  echo "release_tag=$TAG"
  echo "source_repo=$SOURCE_REPO_URL"
  echo "source_commit=$SOURCE_COMMIT"
  echo "distribution_repo=$DISTRIBUTION_REPO_URL"
  echo "builder_role=builder"
  echo "builder_hostname=$(host_name_safe)"
  echo "builder_generated_at_utc=$(utc_now)"
  echo "checksum_algorithm=sha256"
  echo "signing_scheme=ed25519-openssl-pkeyutl-v1"
  echo "signature_asset=$(signature_name)"
  echo "public_key_asset=$(public_key_asset_name)"
  echo ""
  emit_asset_line "$STAGED_PKG" "package"
  emit_asset_line "$STAGED_SHA" "package-checksum"
  emit_asset_line "$STAGED_INSTALL" "installer"
  emit_asset_line "$STAGED_UNINSTALL" "uninstaller"
} > "$MANIFEST_PATH"

# Saída final
echo ""
echo "============================================================"
echo "Stage dir preparado no builder: $STAGE_DIR"
echo "Asset principal: $PKG_FILE"
echo "Manifesto (ainda nao assinado): $(basename "$MANIFEST_PATH")"
echo ""
echo "Proximos passos:"
echo "  1. copiar o stage dir para o signer"
echo "  2. assinar: sh scripts/release/sign-release.sh --stage-dir $STAGE_DIR --private-key /caminho/seguro/release-key.pem"
echo "  3. validar: sh scripts/release/verify-release.sh --stage-dir $STAGE_DIR"
echo "  4. publicar: sh scripts/release/publish-release.sh --stage-dir $STAGE_DIR --repo-owner ${REPO_OWNER} --repo-name ${REPO_NAME} --version ${VERSION}"
echo "============================================================"
