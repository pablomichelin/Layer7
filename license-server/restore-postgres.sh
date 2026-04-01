#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
DUMP_PATH="${1:-}"
CONFIRM_FLAG="${2:-}"

if [ -z "$DUMP_PATH" ]; then
  echo "Uso: ./restore-postgres.sh /caminho/para/backup.sql --yes" >&2
  exit 1
fi

if [ ! -f "$DUMP_PATH" ]; then
  echo "[restore-postgres] Dump nao encontrado: $DUMP_PATH" >&2
  exit 1
fi

if [ "$CONFIRM_FLAG" != "--yes" ]; then
  echo "[restore-postgres] Restore recusado sem confirmacao explicita --yes" >&2
  exit 1
fi

echo "[restore-postgres] A restaurar dump: $DUMP_PATH"

cd "$SCRIPT_DIR"
docker compose exec -T db sh -lc '
  export PGPASSWORD="$POSTGRES_PASSWORD"
  psql \
    --set ON_ERROR_STOP=1 \
    --username="$POSTGRES_USER" \
    --dbname="$POSTGRES_DB"
' < "$DUMP_PATH"

echo "[restore-postgres] Restore concluido."
