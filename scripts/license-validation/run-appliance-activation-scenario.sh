#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Uso:
  run-appliance-activation-scenario.sh --scenario-code <S01> --license-id <id> --license-key <key> --ssh-target <host> [opcoes]

Opcoes:
  --run-id <id>             Identificador do bloco de execucao.
  --output-root <dir>       Directorio raiz das evidencias.
  --compose-dir <dir>       Directorio do deploy/repo do license-server.
  --limit <n>               Numero maximo de linhas por query SQL (default: 20).

  --scenario-code <Sxx>     Codigo do cenario.
  --license-id <id>         ID da licenca no backend.
  --license-key <key>       Chave da licenca usada no layer7d --activate.

  --ssh-target <host>       Host alvo do appliance (ex.: root@192.168.100.254).
  --ssh-port <port>         Porta SSH (default: 22).
  --ssh-key <path>          Ficheiro de identidade para SSH.
  --ssh-option <opt>        Opcao extra passada ao ssh. Pode repetir.
  --license-path <path>     Caminho do .lic no appliance.
  --stats-path <path>       Caminho primario do stats JSON no appliance.
  --layer7d-bin <path>      Caminho do binario layer7d no appliance.
  --pidfile <path>          Caminho do pidfile do daemon no appliance.

  --clear-local-license     Remove o .lic local antes da activacao.
  --skip-before-export      Nao exporta o snapshot inicial do backend.
  --skip-after-export       Nao exporta o snapshot final do backend.
  --skip-activate           Nao executa layer7d --activate; apenas recolhe evidencia.
  --help                    Mostra esta ajuda.
EOF
}

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"

RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)"
OUTPUT_ROOT="${TMPDIR:-/tmp}/layer7-f3-evidence"
COMPOSE_DIR=""
LIMIT=20

SCENARIO_CODE=""
LICENSE_ID=""
LICENSE_KEY=""

SSH_TARGET=""
SSH_PORT=22
SSH_KEY=""
SSH_OPTIONS=()
LICENSE_PATH="/usr/local/etc/layer7.lic"
STATS_PATH="/tmp/layer7-stats.json"
LAYER7D_BIN="/usr/local/sbin/layer7d"
PIDFILE="/var/run/layer7d.pid"

CLEAR_LOCAL_LICENSE=0
SKIP_BEFORE_EXPORT=0
SKIP_AFTER_EXPORT=0
SKIP_ACTIVATE=0

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
    --limit)
      LIMIT="${2:-}"
      shift 2
      ;;
    --scenario-code)
      SCENARIO_CODE="${2:-}"
      shift 2
      ;;
    --license-id)
      LICENSE_ID="${2:-}"
      shift 2
      ;;
    --license-key)
      LICENSE_KEY="${2:-}"
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
    --clear-local-license)
      CLEAR_LOCAL_LICENSE=1
      shift
      ;;
    --skip-before-export)
      SKIP_BEFORE_EXPORT=1
      shift
      ;;
    --skip-after-export)
      SKIP_AFTER_EXPORT=1
      shift
      ;;
    --skip-activate)
      SKIP_ACTIVATE=1
      shift
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "[run-appliance-activation-scenario] argumento desconhecido: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

if [[ -z "$SCENARIO_CODE" || -z "$LICENSE_ID" || -z "$SSH_TARGET" ]]; then
  echo "[run-appliance-activation-scenario] --scenario-code, --license-id e --ssh-target sao obrigatorios." >&2
  usage >&2
  exit 1
fi

if [[ "$SKIP_ACTIVATE" -eq 0 && -z "$LICENSE_KEY" ]]; then
  echo "[run-appliance-activation-scenario] --license-key e obrigatorio quando a activacao nao e ignorada." >&2
  exit 1
fi

if [[ ! "$LICENSE_ID" =~ ^[0-9]+$ ]]; then
  echo "[run-appliance-activation-scenario] license-id deve ser numerico." >&2
  exit 1
fi

if [[ ! "$SSH_PORT" =~ ^[0-9]+$ ]]; then
  echo "[run-appliance-activation-scenario] ssh-port deve ser numerico." >&2
  exit 1
fi

if [[ ! "$LIMIT" =~ ^[0-9]+$ ]]; then
  echo "[run-appliance-activation-scenario] limit deve ser numerico." >&2
  exit 1
fi

SCENARIO_DIR="${OUTPUT_ROOT%/}/${RUN_ID}/${SCENARIO_CODE}"
mkdir -p "$SCENARIO_DIR"

SSH_CMD=(ssh -p "$SSH_PORT" -o BatchMode=yes)
if [[ -n "$SSH_KEY" ]]; then
  SSH_CMD+=(-i "$SSH_KEY")
fi
if [[ ${#SSH_OPTIONS[@]} -gt 0 ]]; then
  for opt in "${SSH_OPTIONS[@]}"; do
    SSH_CMD+=(-o "$opt")
  done
fi
SSH_CMD+=("$SSH_TARGET")

run_cmd() {
  printf '[run-appliance-activation-scenario] executar:'
  printf ' %q' "$@"
  printf '\n'
  "$@"
}

capture_backend_snapshot() {
  local when_label="$1"
  local manifest_file="$SCENARIO_DIR/02-orchestrator-notes.txt"

  printf '\n## backend-%s\n' "$when_label" >> "$manifest_file"
  printf 'captured_at_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" >> "$manifest_file"

  local cmd=(
    "$SCRIPT_DIR/export-license-evidence.sh"
    --license-id "$LICENSE_ID"
    --scenario-code "$SCENARIO_CODE"
    --run-id "$RUN_ID"
    --output-root "$OUTPUT_ROOT"
    --limit "$LIMIT"
  )
  if [[ -n "$COMPOSE_DIR" ]]; then
    cmd+=(--compose-dir "$COMPOSE_DIR")
  fi
  run_cmd "${cmd[@]}"
}

cat > "$SCENARIO_DIR/02-orchestrator-notes.txt" <<EOF
run_id=$RUN_ID
scenario_code=$SCENARIO_CODE
license_id=$LICENSE_ID
ssh_target=$SSH_TARGET
ssh_port=$SSH_PORT
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
clear_local_license=$CLEAR_LOCAL_LICENSE
skip_before_export=$SKIP_BEFORE_EXPORT
skip_after_export=$SKIP_AFTER_EXPORT
skip_activate=$SKIP_ACTIVATE
license_path=$LICENSE_PATH
stats_path=$STATS_PATH
layer7d_bin=$LAYER7D_BIN
pidfile=$PIDFILE
EOF

if [[ "$SKIP_BEFORE_EXPORT" -eq 0 ]]; then
  capture_backend_snapshot before
fi

if [[ "$CLEAR_LOCAL_LICENSE" -eq 1 ]]; then
  printf '\n## appliance-clear-local-license\n' >> "$SCENARIO_DIR/02-orchestrator-notes.txt"
  printf 'executed_at_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" >> "$SCENARIO_DIR/02-orchestrator-notes.txt"
  run_cmd "${SSH_CMD[@]}" "rm -f '$LICENSE_PATH'"
fi

if [[ "$SKIP_ACTIVATE" -eq 0 ]]; then
  {
    echo "# appliance-activate"
    printf 'utc_now=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    echo "scenario_code=$SCENARIO_CODE"
    echo "license_id=$LICENSE_ID"
    "${SSH_CMD[@]}" "$LAYER7D_BIN --activate '$LICENSE_KEY'"
  } > "$SCENARIO_DIR/40-appliance-activate.txt" 2>&1
fi

APPLIANCE_CMD=(
  "$SCRIPT_DIR/export-appliance-evidence.sh"
  --scenario-code "$SCENARIO_CODE"
  --run-id "$RUN_ID"
  --output-root "$OUTPUT_ROOT"
  --ssh-target "$SSH_TARGET"
  --ssh-port "$SSH_PORT"
  --license-path "$LICENSE_PATH"
  --stats-path "$STATS_PATH"
  --layer7d-bin "$LAYER7D_BIN"
  --pidfile "$PIDFILE"
)
if [[ -n "$SSH_KEY" ]]; then
  APPLIANCE_CMD+=(--ssh-key "$SSH_KEY")
fi
if [[ ${#SSH_OPTIONS[@]} -gt 0 ]]; then
  for opt in "${SSH_OPTIONS[@]}"; do
    APPLIANCE_CMD+=(--ssh-option "$opt")
  done
fi
run_cmd "${APPLIANCE_CMD[@]}"

if [[ "$SKIP_AFTER_EXPORT" -eq 0 ]]; then
  capture_backend_snapshot after
fi

echo "[run-appliance-activation-scenario] evidencias orquestradas em: $SCENARIO_DIR"
echo "[run-appliance-activation-scenario] ficheiros principais:"
echo "  - $SCENARIO_DIR/02-orchestrator-notes.txt"
if [[ "$SKIP_ACTIVATE" -eq 0 ]]; then
  echo "  - $SCENARIO_DIR/40-appliance-activate.txt"
fi
echo "  - $SCENARIO_DIR/50-appliance-cli.txt"
echo "  - $SCENARIO_DIR/60-appliance-license.json"
echo "  - $SCENARIO_DIR/70-local-hashes.txt"
