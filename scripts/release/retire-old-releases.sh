#!/bin/sh
# retire-old-releases.sh — Canal público latest-only (BG-164 / ADR-0003)
#
# Remove do download público todas as GitHub Releases excepto as tags
# em --keep. As tags git NÃO são apagadas (--cleanup-tag nunca é usado).
#
# Uso:
#   sh scripts/release/retire-old-releases.sh \
#     --repo pablomichelin/Layer7 \
#     --keep v1.9.72 \
#     --keep blacklists-ut1-current
#
#   sh scripts/release/retire-old-releases.sh \
#     --repo pablomichelin/pfsense-layer7
#     # sem --keep: retira todas as releases (repo de origem não é canal)
#
set -eu

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
. "$SCRIPT_DIR/common.sh"

usage() {
  echo "Uso: $0 --repo OWNER/NAME [--keep TAG]... [--dry-run]" >&2
  exit 1
}

REPO=""
DRY_RUN=0
KEEP=""

while [ $# -gt 0 ]; do
  case "$1" in
    --repo) REPO="$2"; shift 2 ;;
    --keep) KEEP="${KEEP} $2 "; shift 2 ;;
    --dry-run) DRY_RUN=1; shift ;;
    *) usage ;;
  esac
done

[ -n "$REPO" ] || usage
require_cmd gh

is_kept() {
  tag="$1"
  case "$KEEP" in
    *" $tag "*) return 0 ;;
    *) return 1 ;;
  esac
}

echo "==> Canal latest-only em $REPO"
if [ -n "$KEEP" ]; then
  echo "    keep:$KEEP"
else
  echo "    keep: (nenhuma — retira todas as releases)"
fi

TAGS_FILE="$(mktemp "${TMPDIR:-/tmp}/layer7-retire-tags.XXXXXX")"
gh release list --repo "$REPO" --limit 500 --json tagName \
  --jq '.[].tagName' > "$TAGS_FILE"

kept=0
retired=0
while IFS= read -r tag; do
  [ -n "$tag" ] || continue
  if is_kept "$tag"; then
    echo "KEEP  $tag"
    kept=$((kept + 1))
    continue
  fi
  if [ "$DRY_RUN" = "1" ]; then
    echo "DRY   $tag"
    retired=$((retired + 1))
    continue
  fi
  echo "DROP  $tag"
  gh release delete "$tag" --repo "$REPO" --yes
  retired=$((retired + 1))
done < "$TAGS_FILE"
rm -f "$TAGS_FILE"

echo "==> kept=$kept retired=$retired (tags git preservadas)"
