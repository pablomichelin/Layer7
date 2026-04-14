#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Uso:
  export-appliance-evidence.sh --scenario-code <S08> --ssh-target <host> [opcoes]

Opcoes:
  --run-id <id>             Identificador do bloco de execucao.
  --output-root <dir>       Directorio raiz das evidencias.
  --update-root-preflight   Actualiza tambem o artefacto raiz 40-preflight-appliance.txt.
  --ssh-target <host>       Host alvo do appliance (ex.: root@192.168.100.254).
  --ssh-port <port>         Porta SSH (default: 22).
  --ssh-key <path>          Ficheiro de identidade para SSH.
  --ssh-option <opt>        Opcao extra passada ao ssh. Pode repetir.
  --license-path <path>     Caminho do .lic no appliance.
  --stats-path <path>       Caminho primario do stats JSON no appliance.
  --layer7d-bin <path>      Caminho do binario layer7d no appliance.
  --pidfile <path>          Caminho do pidfile do daemon no appliance.
  --help                    Mostra esta ajuda.
EOF
}

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
REPO_ROOT="$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)"
TEMPLATE_PATH="$REPO_ROOT/docs/tests/templates/f3-scenario-evidence.md"

SCENARIO_CODE=""
SSH_TARGET=""
SSH_PORT=22
SSH_KEY=""
SSH_OPTIONS=()
RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)"
OUTPUT_ROOT="${TMPDIR:-/tmp}/layer7-f3-evidence"
UPDATE_ROOT_PREFLIGHT=0
LICENSE_PATH="/usr/local/etc/layer7.lic"
STATS_PATH="/tmp/layer7-stats.json"
LAYER7D_BIN="/usr/local/sbin/layer7d"
PIDFILE="/var/run/layer7d.pid"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --scenario-code)
      SCENARIO_CODE="${2:-}"
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
    --run-id)
      RUN_ID="${2:-}"
      shift 2
      ;;
    --output-root)
      OUTPUT_ROOT="${2:-}"
      shift 2
      ;;
    --update-root-preflight)
      UPDATE_ROOT_PREFLIGHT=1
      shift
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
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "[export-appliance-evidence] argumento desconhecido: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

if [[ -z "$SCENARIO_CODE" || -z "$SSH_TARGET" ]]; then
  echo "[export-appliance-evidence] --scenario-code e --ssh-target sao obrigatorios." >&2
  usage >&2
  exit 1
fi

if [[ ! "$SSH_PORT" =~ ^[0-9]+$ ]]; then
  echo "[export-appliance-evidence] ssh-port deve ser numerico." >&2
  exit 1
fi

SCENARIO_DIR="${OUTPUT_ROOT%/}/${RUN_ID}/${SCENARIO_CODE}"
RUN_ROOT="${OUTPUT_ROOT%/}/${RUN_ID}"
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

run_remote_script_to_file() {
  local target_file="$1"

  "${SSH_CMD[@]}" sh -s -- \
    "$LICENSE_PATH" \
    "$STATS_PATH" \
    "$PIDFILE" \
    "$LAYER7D_BIN" >"$target_file"
}

write_root_preflight() {
  local target_file="$RUN_ROOT/40-preflight-appliance.txt"

  cat > "$target_file" <<EOF
run_id=$RUN_ID
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
source_scenario=$SCENARIO_CODE
ssh_target=$SSH_TARGET
ssh_port=$SSH_PORT
license_path=$LICENSE_PATH
stats_path=$STATS_PATH
layer7d_bin=$LAYER7D_BIN
pidfile=$PIDFILE

## 50-appliance-cli.txt
EOF

  cat "$SCENARIO_DIR/50-appliance-cli.txt" >> "$target_file"
  printf '\n\n## 60-appliance-license.json\n' >> "$target_file"
  cat "$SCENARIO_DIR/60-appliance-license.json" >> "$target_file"
  printf '\n\n## 70-local-hashes.txt\n' >> "$target_file"
  cat "$SCENARIO_DIR/70-local-hashes.txt" >> "$target_file"

  echo "[export-appliance-evidence] preflight actualizado: $target_file"
}

cat > "$SCENARIO_DIR/00-manifest.txt" <<EOF
run_id=$RUN_ID
scenario_code=$SCENARIO_CODE
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
ssh_target=$SSH_TARGET
ssh_port=$SSH_PORT
license_path=$LICENSE_PATH
stats_path=$STATS_PATH
layer7d_bin=$LAYER7D_BIN
pidfile=$PIDFILE
output_root=$OUTPUT_ROOT
EOF

if [[ -f "$TEMPLATE_PATH" && ! -f "$SCENARIO_DIR/01-operator-notes.md" ]]; then
  cp "$TEMPLATE_PATH" "$SCENARIO_DIR/01-operator-notes.md"
fi

run_remote_script_to_file "$SCENARIO_DIR/50-appliance-cli.txt" <<'EOF'
LICENSE_PATH="$1"
STATS_PATH="$2"
PIDFILE="$3"
LAYER7D_BIN="$4"
ALT_STATS_PATH="/var/db/layer7/layer7-stats.json"

echo "# appliance-baseline"
date -u '+utc_now=%Y-%m-%dT%H:%M:%SZ'
hostname 2>/dev/null || echo hostname_unavailable
echo
echo "# effective-user"
id 2>&1 || true
echo
echo "# file-permissions"
ls -ld /usr/local/etc 2>&1 || true
ls -l "$LICENSE_PATH" "$STATS_PATH" "$ALT_STATS_PATH" "$PIDFILE" 2>&1 || true
echo
echo "# write-permissions"
if [ -w "$LICENSE_PATH" ]; then
  echo "license_writable=yes"
else
  echo "license_writable=no"
fi
if [ -w /usr/local/etc ]; then
  echo "usr_local_etc_writable=yes"
else
  echo "usr_local_etc_writable=no"
fi
echo
echo "# daemon-status"
service layer7d status 2>&1 || true
echo
echo "# daemon-process"
if command -v pgrep >/dev/null 2>&1; then
  pgrep -fl layer7d 2>&1 || true
else
  ps ax 2>&1 | grep '[l]ayer7d' || true
fi
echo
echo "# kern.hostuuid"
sysctl -n kern.hostuuid 2>&1 || true
echo
echo "# ifconfig -a"
ifconfig -a 2>&1 || true
echo
echo "# fingerprint"
"$LAYER7D_BIN" --fingerprint 2>&1 || true
echo
echo "# stats-json"
if [ -f "$PIDFILE" ]; then
  _pid="$(cat "$PIDFILE" 2>/dev/null || true)"
  if [ -n "$_pid" ]; then
    kill -USR1 "$_pid" 2>/dev/null || true
    sleep 1
  fi
fi

if [ -f "$STATS_PATH" ]; then
  cat "$STATS_PATH"
elif [ -f "$ALT_STATS_PATH" ]; then
  cat "$ALT_STATS_PATH"
else
  echo no_stats_json
fi
EOF

run_remote_script_to_file "$SCENARIO_DIR/60-appliance-license.json" <<'EOF'
LICENSE_PATH="$1"

if [ -f "$LICENSE_PATH" ]; then
  if command -v python3 >/dev/null 2>&1; then
    python3 -m json.tool "$LICENSE_PATH" 2>/dev/null || cat "$LICENSE_PATH"
  else
    cat "$LICENSE_PATH"
  fi
else
  echo no_local_license
fi
EOF

run_remote_script_to_file "$SCENARIO_DIR/70-local-hashes.txt" <<'EOF'
LICENSE_PATH="$1"
STATS_PATH="$2"
ALT_STATS_PATH="/var/db/layer7/layer7-stats.json"

if command -v sha256 >/dev/null 2>&1; then
  HASH_CMD="sha256"
elif command -v sha256sum >/dev/null 2>&1; then
  HASH_CMD="sha256sum"
else
  HASH_CMD=""
fi

echo "# local-license-hash"
if [ -n "$HASH_CMD" ] && [ -f "$LICENSE_PATH" ]; then
  $HASH_CMD "$LICENSE_PATH"
elif [ ! -f "$LICENSE_PATH" ]; then
  echo no_local_license
else
  echo no_sha256_tool
fi

echo
echo "# stats-source"
if [ -f "$STATS_PATH" ]; then
  echo "$STATS_PATH"
elif [ -f "$ALT_STATS_PATH" ]; then
  echo "$ALT_STATS_PATH"
else
  echo no_stats_json
fi
EOF

echo "[export-appliance-evidence] evidencias exportadas para: $SCENARIO_DIR"
echo "[export-appliance-evidence] ficheiros gerados:"
echo "  - $SCENARIO_DIR/00-manifest.txt"
echo "  - $SCENARIO_DIR/01-operator-notes.md"
echo "  - $SCENARIO_DIR/50-appliance-cli.txt"
echo "  - $SCENARIO_DIR/60-appliance-license.json"
echo "  - $SCENARIO_DIR/70-local-hashes.txt"

if [[ "$UPDATE_ROOT_PREFLIGHT" -eq 1 ]]; then
  write_root_preflight
fi
