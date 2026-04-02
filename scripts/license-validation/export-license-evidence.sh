#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Uso:
  export-license-evidence.sh --license-id <id> --scenario-code <S01> [opcoes]

Opcoes:
  --run-id <id>         Identificador do bloco de execucao.
  --output-root <dir>   Directorio raiz das evidencias.
  --compose-dir <dir>   Directorio do deploy/repo do license-server.
  --limit <n>           Numero maximo de linhas de log por query (default: 20).
  --help                Mostra esta ajuda.
EOF
}

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
REPO_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)"
DEFAULT_COMPOSE_DIR="$REPO_ROOT/license-server"
TEMPLATE_PATH="$REPO_ROOT/docs/tests/templates/f3-scenario-evidence.md"

LICENSE_ID=""
SCENARIO_CODE=""
RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)"
OUTPUT_ROOT="${TMPDIR:-/tmp}/layer7-f3-evidence"
COMPOSE_DIR="$DEFAULT_COMPOSE_DIR"
LIMIT=20

while [[ $# -gt 0 ]]; do
  case "$1" in
    --license-id)
      LICENSE_ID="${2:-}"
      shift 2
      ;;
    --scenario-code)
      SCENARIO_CODE="${2:-}"
      shift 2
      ;;
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
    --limit)
      LIMIT="${2:-}"
      shift 2
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "[export-license-evidence] argumento desconhecido: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

if [[ -z "$LICENSE_ID" || -z "$SCENARIO_CODE" ]]; then
  echo "[export-license-evidence] --license-id e --scenario-code sao obrigatorios." >&2
  usage >&2
  exit 1
fi

if [[ ! "$LICENSE_ID" =~ ^[0-9]+$ ]]; then
  echo "[export-license-evidence] license-id deve ser numerico." >&2
  exit 1
fi

if [[ ! "$LIMIT" =~ ^[0-9]+$ ]]; then
  echo "[export-license-evidence] limit deve ser numerico." >&2
  exit 1
fi

if [[ ! -d "$COMPOSE_DIR" ]]; then
  echo "[export-license-evidence] compose-dir inexistente: $COMPOSE_DIR" >&2
  exit 1
fi

SCENARIO_DIR="${OUTPUT_ROOT%/}/${RUN_ID}/${SCENARIO_CODE}"
mkdir -p "$SCENARIO_DIR"

run_psql_to_file() {
  local target_file="$1"
  local sql="$2"

  (
    cd "$COMPOSE_DIR"
    docker compose exec -T db sh -lc '
      export PGPASSWORD="$POSTGRES_PASSWORD"
      psql \
        -v ON_ERROR_STOP=1 \
        -U "$POSTGRES_USER" \
        -d "$POSTGRES_DB" \
        -P pager=off \
        -x
    ' <<<"$sql"
  ) > "$target_file"
}

cat > "$SCENARIO_DIR/00-manifest.txt" <<EOF
run_id=$RUN_ID
scenario_code=$SCENARIO_CODE
license_id=$LICENSE_ID
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
compose_dir=$COMPOSE_DIR
output_root=$OUTPUT_ROOT
limit=$LIMIT
EOF

if [[ -f "$TEMPLATE_PATH" && ! -f "$SCENARIO_DIR/01-operator-notes.md" ]]; then
  cp "$TEMPLATE_PATH" "$SCENARIO_DIR/01-operator-notes.md"
fi

run_psql_to_file "$SCENARIO_DIR/10-backend-license.txt" "$(cat <<EOF
SELECT
  l.id,
  l.customer_id,
  c.name AS customer_name,
  LEFT(l.license_key, 8) AS license_key_prefix,
  l.hardware_id,
  l.expiry,
  l.features,
  l.status,
  l.activated_at,
  l.revoked_at,
  l.archived_at,
  l.created_at,
  l.updated_at
FROM licenses l
LEFT JOIN customers c
  ON c.id = l.customer_id
WHERE l.id = $LICENSE_ID;
EOF
)"

run_psql_to_file "$SCENARIO_DIR/20-backend-activations-log.txt" "$(cat <<EOF
SELECT
  id,
  created_at,
  result,
  hardware_id,
  ip_address,
  user_agent,
  error_message
FROM activations_log
WHERE license_id = $LICENSE_ID
ORDER BY created_at DESC
LIMIT $LIMIT;
EOF
)"

run_psql_to_file "$SCENARIO_DIR/30-backend-admin-audit-log.txt" "$(cat <<EOF
SELECT
  created_at,
  component,
  event_type,
  result,
  reason,
  actor_identifier,
  metadata->>'flow' AS flow,
  metadata->>'emission_kind' AS emission_kind,
  metadata->>'license_id' AS license_id,
  metadata->>'customer_id' AS customer_id,
  metadata->>'hardware_id' AS hardware_id,
  metadata->>'effective_status' AS effective_status,
  metadata->>'artifact_envelope_sha256' AS artifact_envelope_sha256
FROM admin_audit_log
WHERE metadata->>'license_id' = '$LICENSE_ID'
ORDER BY created_at DESC
LIMIT $LIMIT;
EOF
)"

echo "[export-license-evidence] evidencias exportadas para: $SCENARIO_DIR"
echo "[export-license-evidence] ficheiros gerados:"
echo "  - $SCENARIO_DIR/00-manifest.txt"
echo "  - $SCENARIO_DIR/01-operator-notes.md"
echo "  - $SCENARIO_DIR/10-backend-license.txt"
echo "  - $SCENARIO_DIR/20-backend-activations-log.txt"
echo "  - $SCENARIO_DIR/30-backend-admin-audit-log.txt"
