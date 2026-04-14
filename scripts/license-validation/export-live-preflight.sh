#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Uso:
  export-live-preflight.sh --base-url <url> [opcoes]

Opcoes:
  --run-id <id>             Identificador da campanha.
  --output-root <dir>       Directorio raiz das evidencias.
  --base-url <url>          URL publica base (ex.: https://license.systemup.inf.br).
  --origin-url <url>        URL directa do origin observado (ex.: http://192.168.100.244:8445).
  --admin-email <email>     Credencial administrativa para preflight de login.
  --admin-password <pass>   Password administrativa para preflight de login.
  --cookie-jar <path>       Caminho do cookie jar temporario.
  --curl-insecure           Adiciona -k aos curls.
  --timeout <seg>           Timeout maximo por request (default: 15).
  --help                    Mostra esta ajuda.
EOF
}

RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)"
OUTPUT_ROOT="${TMPDIR:-/tmp}/layer7-f3-evidence"
BASE_URL=""
ORIGIN_URL=""
ADMIN_EMAIL=""
ADMIN_PASSWORD=""
COOKIE_JAR=""
CURL_INSECURE=0
TIMEOUT=15

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
    --base-url)
      BASE_URL="${2:-}"
      shift 2
      ;;
    --origin-url)
      ORIGIN_URL="${2:-}"
      shift 2
      ;;
    --admin-email)
      ADMIN_EMAIL="${2:-}"
      shift 2
      ;;
    --admin-password)
      ADMIN_PASSWORD="${2:-}"
      shift 2
      ;;
    --cookie-jar)
      COOKIE_JAR="${2:-}"
      shift 2
      ;;
    --curl-insecure)
      CURL_INSECURE=1
      shift
      ;;
    --timeout)
      TIMEOUT="${2:-}"
      shift 2
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "[export-live-preflight] argumento desconhecido: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

if [[ -z "$BASE_URL" ]]; then
  echo "[export-live-preflight] --base-url e obrigatorio." >&2
  usage >&2
  exit 1
fi

if [[ ! "$TIMEOUT" =~ ^[0-9]+$ ]]; then
  echo "[export-live-preflight] timeout deve ser numerico." >&2
  exit 1
fi

RUN_ROOT="${OUTPUT_ROOT%/}/${RUN_ID}"
mkdir -p "$RUN_ROOT"

if [[ -z "$COOKIE_JAR" ]]; then
  COOKIE_JAR="${RUN_ROOT}/.cookie-jar.txt"
fi

CURL_CMD=(curl -sS -i --max-time "$TIMEOUT")
if [[ "$CURL_INSECURE" -eq 1 ]]; then
  CURL_CMD+=(-k)
fi

write_section() {
  local file="$1"
  local title="$2"
  printf '\n## %s\n' "$title" >> "$file"
}

append_curl_output() {
  local file="$1"
  local title="$2"
  shift 2

  write_section "$file" "$title"
  printf 'command=' >> "$file"
  printf '%q ' "${CURL_CMD[@]}" "$@" >> "$file"
  printf '\n\n' >> "$file"

  if "${CURL_CMD[@]}" "$@" >> "$file" 2>&1; then
    printf '\nexit_status=0\n' >> "$file"
  else
    local status=$?
    printf '\nexit_status=%s\n' "$status" >> "$file"
  fi
}

cat > "$RUN_ROOT/10-preflight-deploy.txt" <<EOF
run_id=$RUN_ID
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
base_url=$BASE_URL
origin_url=${ORIGIN_URL:-not_set}
timeout_seconds=$TIMEOUT
EOF

append_curl_output "$RUN_ROOT/10-preflight-deploy.txt" "public-health" \
  "$BASE_URL/api/health"

if [[ -n "$ORIGIN_URL" ]]; then
  append_curl_output "$RUN_ROOT/10-preflight-deploy.txt" "origin-health" \
    "$ORIGIN_URL/api/health"
fi

cat > "$RUN_ROOT/30-preflight-admin.txt" <<EOF
run_id=$RUN_ID
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
base_url=$BASE_URL
cookie_jar=$COOKIE_JAR
admin_email=${ADMIN_EMAIL:-not_set}
EOF

append_curl_output "$RUN_ROOT/30-preflight-admin.txt" "origin-denied-control" \
  -X POST "$BASE_URL/api/auth/login" \
  -H 'Origin: https://evil.example' \
  -H 'Content-Type: application/json' \
  -d '{"email":"nobody@example.com","password":"invalid"}'

append_curl_output "$RUN_ROOT/30-preflight-admin.txt" "cors-preflight-control" \
  -X OPTIONS "$BASE_URL/api/auth/login" \
  -H 'Origin: https://evil.example' \
  -H 'Access-Control-Request-Method: POST'

if [[ -n "$ADMIN_EMAIL" && -n "$ADMIN_PASSWORD" ]]; then
  rm -f "$COOKIE_JAR"
  append_curl_output "$RUN_ROOT/30-preflight-admin.txt" "admin-login" \
    -c "$COOKIE_JAR" \
    -H 'Content-Type: application/json' \
    -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}" \
    "$BASE_URL/api/auth/login"

  append_curl_output "$RUN_ROOT/30-preflight-admin.txt" "admin-session" \
    -b "$COOKIE_JAR" \
    "$BASE_URL/api/auth/session"
else
  write_section "$RUN_ROOT/30-preflight-admin.txt" "admin-login"
  printf 'skipped=missing_admin_credentials\n' >> "$RUN_ROOT/30-preflight-admin.txt"
fi

echo "[export-live-preflight] artefactos actualizados:"
echo "  - $RUN_ROOT/10-preflight-deploy.txt"
echo "  - $RUN_ROOT/30-preflight-admin.txt"
