#!/bin/sh
# sync-public-distribution.sh — Publica árvore mínima em pablomichelin/Layer7
#
# Mantém GitHub Releases intactas; substitui apenas o conteúdo do branch main
# por documentação comercial + scripts de instalação (sem código-fonte).
#
# Uso:
#   ./scripts/release/sync-public-distribution.sh --dry-run
#   ./scripts/release/sync-public-distribution.sh --push
#   ./scripts/release/sync-public-distribution.sh --push --message "chore: public distribution tree only"
#
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
PUBLIC_REMOTE="${PUBLIC_REMOTE:-layer7}"
PUBLIC_BRANCH="${PUBLIC_BRANCH:-main}"
DRY_RUN=0
DO_PUSH=0
COMMIT_MSG="chore(public): distribution docs and install scripts only"

while [ $# -gt 0 ]; do
  case "$1" in
    --dry-run) DRY_RUN=1; shift ;;
    --push) DO_PUSH=1; shift ;;
    --message) COMMIT_MSG="$2"; shift 2 ;;
    --remote) PUBLIC_REMOTE="$2"; shift 2 ;;
    *) echo "Uso: $0 [--dry-run] [--push] [--message MSG] [--remote NAME]" >&2; exit 1 ;;
  esac
done

die() { echo "ERRO: $*" >&2; exit 1; }

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "comando em falta: $1"
}

require_cmd git
require_cmd rsync

WORK_DIR="$(mktemp -d "${TMPDIR:-/tmp}/layer7-public-sync.XXXXXX")"
trap 'rm -rf "$WORK_DIR"' EXIT INT TERM

echo "==> A preparar árvore pública em $WORK_DIR"

copy_file() {
  src="$1"
  dst="$2"
  [ -f "$REPO_ROOT/$src" ] || die "ficheiro em falta: $src"
  mkdir -p "$(dirname "$WORK_DIR/$dst")"
  cp "$REPO_ROOT/$src" "$WORK_DIR/$dst"
}

copy_file "public-dist/README.md" "README.md"
copy_file "LICENSE" "LICENSE"

for f in \
  docs/commercial/LAYER7-EVALUATION-PACK-EN.md \
  docs/commercial/LAYER7-EVALUATION-PACK-PT.md \
  docs/commercial/LAYER7-PRODUCT-OVERVIEW-EN.md \
  docs/commercial/LAYER7-PRODUCT-OVERVIEW-PT.md \
  docs/commercial/LAYER7-INSTALL-GUIDE-EN.md \
  docs/commercial/LAYER7-INSTALL-GUIDE-PT.md \
  docs/commercial/LAYER7-MANUAL-PRODUTO-PT.md
do
  copy_file "$f" "$f"
done

for f in \
  scripts/release/install.sh \
  scripts/release/uninstall.sh \
  scripts/release/common.sh \
  scripts/release/verify-release.sh
do
  copy_file "$f" "$f"
done

cat > "$WORK_DIR/.gitignore" <<'EOF'
.DS_Store
*.swp
*~
EOF

echo "==> Árvore pública preparada:"
find "$WORK_DIR" -type f | sed "s|^$WORK_DIR/||" | sort

if [ "$DRY_RUN" = "1" ] && [ "$DO_PUSH" = "0" ]; then
  echo ""
  echo "DRY-RUN concluído. Use --push para publicar em $PUBLIC_REMOTE/$PUBLIC_BRANCH."
  exit 0
fi

[ "$DO_PUSH" = "1" ] || die "sem --push: nada foi enviado (use --dry-run para pré-visualizar)"

git -C "$REPO_ROOT" remote get-url "$PUBLIC_REMOTE" >/dev/null 2>&1 \
  || die "remote git em falta: $PUBLIC_REMOTE"

PUBLIC_URL="$(git -C "$REPO_ROOT" remote get-url "$PUBLIC_REMOTE")"
CLONE_DIR="$WORK_DIR/clone"
git clone --depth 1 --branch "$PUBLIC_BRANCH" "$PUBLIC_URL" "$CLONE_DIR" 2>/dev/null \
  || git clone "$PUBLIC_URL" "$CLONE_DIR"

cd "$CLONE_DIR"
git checkout -B "$PUBLIC_BRANCH"

# Remove tudo excepto .git
find . -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} +

# Copia árvore mínima
rsync -a "$WORK_DIR/" "$CLONE_DIR/" --exclude clone

git add -A
if git diff --cached --quiet; then
  echo "==> Sem alterações face ao remoto; nada a enviar."
  exit 0
fi

git commit -m "$COMMIT_MSG"

echo "==> A enviar para $PUBLIC_REMOTE ($PUBLIC_URL) branch $PUBLIC_BRANCH"
git push origin "$PUBLIC_BRANCH"

echo ""
echo "=============================================="
echo "Repositório público actualizado."
echo "GitHub Releases existentes NÃO foram alteradas."
echo "SSOT privado: pfsense-layer7 (origin)"
echo "=============================================="
