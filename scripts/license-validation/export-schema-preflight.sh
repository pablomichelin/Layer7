#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Uso:
  export-schema-preflight.sh [opcoes]

Opcoes:
  --run-id <id>           Identificador da campanha.
  --output-root <dir>     Directorio raiz das evidencias.
  --compose-dir <dir>     Directorio do deploy/repo do license-server.
  --db-service <name>     Nome do servico DB no docker compose (default: db).
  --help                  Mostra esta ajuda.
EOF
}

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
REPO_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)"

RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)"
OUTPUT_ROOT="${TMPDIR:-/tmp}/layer7-f3-evidence"
COMPOSE_DIR="$REPO_ROOT/license-server"
DB_SERVICE="db"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --run-id)
      RUN_ID="${2:-}"
      shift 2
      ;;
    --output-root)
      OUTPUT_ROOT="${2:-}"
      shift 2
      ;;
    --compose-dir)
      COMPOSE_DIR="${2:-}"
      shift 2
      ;;
    --db-service)
      DB_SERVICE="${2:-}"
      shift 2
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "[export-schema-preflight] argumento desconhecido: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

if [[ ! -d "$COMPOSE_DIR" ]]; then
  echo "[export-schema-preflight] compose-dir inexistente: $COMPOSE_DIR" >&2
  exit 1
fi

RUN_ROOT="${OUTPUT_ROOT%/}/${RUN_ID}"
mkdir -p "$RUN_ROOT"

run_psql_section() {
  local target_file="$1"
  local title="$2"
  local sql="$3"

  printf '\n## %s\n' "$title" >> "$target_file"
  (
    cd "$COMPOSE_DIR"
    docker compose exec -T "$DB_SERVICE" sh -lc '
      export PGPASSWORD="$POSTGRES_PASSWORD"
      psql \
        -v ON_ERROR_STOP=1 \
        -U "$POSTGRES_USER" \
        -d "$POSTGRES_DB" \
        -P pager=off \
        -x
    ' <<<"$sql"
  ) >> "$target_file"
}

TARGET_FILE="$RUN_ROOT/20-preflight-schema.txt"
cat > "$TARGET_FILE" <<EOF
run_id=$RUN_ID
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
compose_dir=$COMPOSE_DIR
db_service=$DB_SERVICE
EOF

run_psql_section "$TARGET_FILE" "database-identity" "$(cat <<'EOF'
SELECT
  current_database() AS current_database,
  current_user AS current_user,
  inet_server_addr() AS server_addr,
  inet_server_port() AS server_port;
EOF
)"

run_psql_section "$TARGET_FILE" "required-tables" "$(cat <<'EOF'
SELECT
  table_name
FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_name IN (
    'licenses',
    'activations_log',
    'admin_sessions',
    'admin_audit_log',
    'admin_login_guards'
  )
ORDER BY table_name;
EOF
)"

run_psql_section "$TARGET_FILE" "required-table-counts" "$(cat <<'EOF'
SELECT 'licenses' AS table_name, count(*)::bigint AS row_count FROM licenses
UNION ALL
SELECT 'activations_log', count(*)::bigint FROM activations_log
UNION ALL
SELECT 'admin_sessions', count(*)::bigint FROM admin_sessions
UNION ALL
SELECT 'admin_audit_log', count(*)::bigint FROM admin_audit_log
UNION ALL
SELECT 'admin_login_guards', count(*)::bigint FROM admin_login_guards;
EOF
)"

run_psql_section "$TARGET_FILE" "required-columns" "$(cat <<'EOF'
SELECT
  table_name,
  column_name,
  data_type
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name IN (
    'admin_sessions',
    'admin_audit_log',
    'admin_login_guards'
  )
ORDER BY table_name, ordinal_position;
EOF
)"

echo "[export-schema-preflight] artefacto actualizado:"
echo "  - $TARGET_FILE"
