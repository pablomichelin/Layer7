#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Uso:
  prepare-f3-preflight.sh [opcoes]

Opcoes:
  --run-id <id>             Identificador da campanha.
  --output-root <dir>       Directorio raiz das evidencias.

  --base-url <url>          URL publica base para preflight live.
  --origin-url <url>        URL directa do origin observado.
  --admin-email <email>     Credencial administrativa para preflight live.
  --admin-password <pass>   Password administrativa para preflight live.
  --cookie-jar <path>       Cookie jar para preflight live.
  --curl-insecure           Adiciona -k ao preflight live.
  --timeout <seg>           Timeout por request do preflight live.

  --compose-dir <dir>       Directorio do deploy/repo do license-server.
  --db-service <name>       Nome do servico DB no docker compose.

  --ssh-target <host>       Host alvo do appliance.
  --ssh-port <port>         Porta SSH do appliance.
  --ssh-key <path>          Ficheiro de identidade SSH.
  --ssh-option <opt>        Opcao extra passada ao ssh. Pode repetir.
  --license-path <path>     Caminho do .lic no appliance.
  --stats-path <path>       Caminho primario do stats JSON no appliance.
  --layer7d-bin <path>      Caminho do binario layer7d no appliance.
  --pidfile <path>          Caminho do pidfile do daemon no appliance.
  --appliance-scenario <S>  Cenario a usar como fonte da baseline (default: S01).

  --skip-live               Nao executa o helper de preflight live.
  --skip-schema             Nao executa o helper de preflight schema.
  --skip-appliance          Nao executa o helper de appliance.
  --help                    Mostra esta ajuda.
EOF
}

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"

RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)"
OUTPUT_ROOT="${TMPDIR:-/tmp}/layer7-f3-evidence"

BASE_URL=""
ORIGIN_URL=""
ADMIN_EMAIL=""
ADMIN_PASSWORD=""
COOKIE_JAR=""
CURL_INSECURE=0
TIMEOUT=""

COMPOSE_DIR=""
DB_SERVICE=""

SSH_TARGET=""
SSH_PORT=""
SSH_KEY=""
SSH_OPTIONS=()
LICENSE_PATH=""
STATS_PATH=""
LAYER7D_BIN=""
PIDFILE=""
APPLIANCE_SCENARIO="S01"

SKIP_LIVE=0
SKIP_SCHEMA=0
SKIP_APPLIANCE=0

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
    --compose-dir)
      COMPOSE_DIR="${2:-}"
      shift 2
      ;;
    --db-service)
      DB_SERVICE="${2:-}"
      shift 2
      ;;
    --ssh-target)
      SSH_TARGET="${2:-}"
      shift 2
      ;;
    --ssh-port)
      SSH_PORT="${2:-}"
      shift 2
      ;;
    --ssh-key)
      SSH_KEY="${2:-}"
      shift 2
      ;;
    --ssh-option)
      SSH_OPTIONS+=("${2:-}")
      shift 2
      ;;
    --license-path)
      LICENSE_PATH="${2:-}"
      shift 2
      ;;
    --stats-path)
      STATS_PATH="${2:-}"
      shift 2
      ;;
    --layer7d-bin)
      LAYER7D_BIN="${2:-}"
      shift 2
      ;;
    --pidfile)
      PIDFILE="${2:-}"
      shift 2
      ;;
    --appliance-scenario)
      APPLIANCE_SCENARIO="${2:-}"
      shift 2
      ;;
    --skip-live)
      SKIP_LIVE=1
      shift
      ;;
    --skip-schema)
      SKIP_SCHEMA=1
      shift
      ;;
    --skip-appliance)
      SKIP_APPLIANCE=1
      shift
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "[prepare-f3-preflight] argumento desconhecido: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

run_cmd() {
  printf '[prepare-f3-preflight] executar:'
  printf ' %q' "$@"
  printf '\n'
  "$@"
}

run_cmd "$SCRIPT_DIR/init-f3-validation-campaign.sh" \
  --run-id "$RUN_ID" \
  --output-root "$OUTPUT_ROOT"

if [[ "$SKIP_LIVE" -eq 0 ]]; then
  if [[ -n "$BASE_URL" ]]; then
    LIVE_CMD=(
      "$SCRIPT_DIR/export-live-preflight.sh"
      --run-id "$RUN_ID"
      --output-root "$OUTPUT_ROOT"
      --base-url "$BASE_URL"
    )
    if [[ -n "$ORIGIN_URL" ]]; then
      LIVE_CMD+=(--origin-url "$ORIGIN_URL")
    fi
    if [[ -n "$ADMIN_EMAIL" ]]; then
      LIVE_CMD+=(--admin-email "$ADMIN_EMAIL")
    fi
    if [[ -n "$ADMIN_PASSWORD" ]]; then
      LIVE_CMD+=(--admin-password "$ADMIN_PASSWORD")
    fi
    if [[ -n "$COOKIE_JAR" ]]; then
      LIVE_CMD+=(--cookie-jar "$COOKIE_JAR")
    fi
    if [[ "$CURL_INSECURE" -eq 1 ]]; then
      LIVE_CMD+=(--curl-insecure)
    fi
    if [[ -n "$TIMEOUT" ]]; then
      LIVE_CMD+=(--timeout "$TIMEOUT")
    fi
    run_cmd "${LIVE_CMD[@]}"
  else
    echo "[prepare-f3-preflight] live preflight ignorado: falta --base-url"
  fi
fi

if [[ "$SKIP_SCHEMA" -eq 0 ]]; then
  if [[ -n "$COMPOSE_DIR" ]]; then
    SCHEMA_CMD=(
      "$SCRIPT_DIR/export-schema-preflight.sh"
      --run-id "$RUN_ID"
      --output-root "$OUTPUT_ROOT"
      --compose-dir "$COMPOSE_DIR"
    )
    if [[ -n "$DB_SERVICE" ]]; then
      SCHEMA_CMD+=(--db-service "$DB_SERVICE")
    fi
    run_cmd "${SCHEMA_CMD[@]}"
  else
    echo "[prepare-f3-preflight] schema preflight ignorado: falta --compose-dir"
  fi
fi

if [[ "$SKIP_APPLIANCE" -eq 0 ]]; then
  if [[ -n "$SSH_TARGET" ]]; then
    APPLIANCE_CMD=(
      "$SCRIPT_DIR/export-appliance-evidence.sh"
      --scenario-code "$APPLIANCE_SCENARIO"
      --run-id "$RUN_ID"
      --output-root "$OUTPUT_ROOT"
      --ssh-target "$SSH_TARGET"
      --update-root-preflight
    )
    if [[ -n "$SSH_PORT" ]]; then
      APPLIANCE_CMD+=(--ssh-port "$SSH_PORT")
    fi
    if [[ -n "$SSH_KEY" ]]; then
      APPLIANCE_CMD+=(--ssh-key "$SSH_KEY")
    fi
    if [[ ${#SSH_OPTIONS[@]} -gt 0 ]]; then
      for opt in "${SSH_OPTIONS[@]}"; do
        APPLIANCE_CMD+=(--ssh-option "$opt")
      done
    fi
    if [[ -n "$LICENSE_PATH" ]]; then
      APPLIANCE_CMD+=(--license-path "$LICENSE_PATH")
    fi
    if [[ -n "$STATS_PATH" ]]; then
      APPLIANCE_CMD+=(--stats-path "$STATS_PATH")
    fi
    if [[ -n "$LAYER7D_BIN" ]]; then
      APPLIANCE_CMD+=(--layer7d-bin "$LAYER7D_BIN")
    fi
    if [[ -n "$PIDFILE" ]]; then
      APPLIANCE_CMD+=(--pidfile "$PIDFILE")
    fi
    run_cmd "${APPLIANCE_CMD[@]}"
  else
    echo "[prepare-f3-preflight] appliance preflight ignorado: falta --ssh-target"
  fi
fi

echo "[prepare-f3-preflight] campanha preparada em: ${OUTPUT_ROOT%/}/${RUN_ID}"
