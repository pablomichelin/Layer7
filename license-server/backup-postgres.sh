#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_DIR="${SCRIPT_DIR}/backups"
OUTPUT_PATH="${1:-${BACKUP_DIR}/layer7-license-postgres-${STAMP}.sql}"

mkdir -p "$(dirname -- "$OUTPUT_PATH")"

echo "[backup-postgres] A gerar dump em: $OUTPUT_PATH"

cd "$SCRIPT_DIR"
docker compose exec -T db sh -lc '
  export PGPASSWORD="$POSTGRES_PASSWORD"
  pg_dump \
    --username="$POSTGRES_USER" \
    --dbname="$POSTGRES_DB" \
    --clean \
    --if-exists \
    --no-owner \
    --no-privileges
' > "$OUTPUT_PATH"

echo "[backup-postgres] Backup concluido."
