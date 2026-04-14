#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Uso:
  run-pfsense-gui-license-flow.sh --scenario-code <S07> --gui-base <url> --gui-user <user> --gui-password <pass> --action <probe|register|revoke> [opcoes]

Opcoes:
  --run-id <id>             Identificador do bloco de execucao.
  --output-root <dir>       Directorio raiz das evidencias.
  --scenario-code <Sxx>     Codigo do cenario.
  --gui-base <url>          Base URL da GUI do pfSense (ex.: https://192.168.100.254:9999).
  --ssh-target <host>       Executa o fluxo a partir do host remoto via SSH.
  --ssh-port <port>         Porta SSH para --ssh-target (default: 22).
  --ssh-key <path>          Ficheiro de identidade para SSH.
  --ssh-option <opt>        Opcao extra passada ao ssh. Pode repetir.
  --gui-user <user>         Utilizador autorizado da GUI.
  --gui-password <pass>     Password autorizada da GUI.
  --action <acao>           Uma de: probe, register, revoke.
  --license-key <key>       Obrigatoria em --action register.
  --layer7-path <path>      Caminho do settings do pacote (default: /packages/layer7/layer7_settings.php).
  --cookie-jar <path>       Caminho fixo do cookie jar. Default: dentro do directorio do cenario.
  --help                    Mostra esta ajuda.
EOF
}

RUN_ID="$(date -u +%Y%m%dT%H%M%SZ)"
OUTPUT_ROOT="${TMPDIR:-/tmp}/layer7-f3-evidence"
SCENARIO_CODE=""
GUI_BASE=""
SSH_TARGET=""
SSH_PORT=22
SSH_KEY=""
SSH_OPTIONS=()
GUI_USER=""
GUI_PASSWORD=""
ACTION=""
LICENSE_KEY=""
LAYER7_PATH="/packages/layer7/layer7_settings.php"
COOKIE_JAR=""

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
    --scenario-code)
      SCENARIO_CODE="${2:-}"
      shift 2
      ;;
    --gui-base)
      GUI_BASE="${2:-}"
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
    --gui-user)
      GUI_USER="${2:-}"
      shift 2
      ;;
    --gui-password)
      GUI_PASSWORD="${2:-}"
      shift 2
      ;;
    --action)
      ACTION="${2:-}"
      shift 2
      ;;
    --license-key)
      LICENSE_KEY="${2:-}"
      shift 2
      ;;
    --layer7-path)
      LAYER7_PATH="${2:-}"
      shift 2
      ;;
    --cookie-jar)
      COOKIE_JAR="${2:-}"
      shift 2
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "[run-pfsense-gui-license-flow] argumento desconhecido: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

if [[ -z "$SCENARIO_CODE" || -z "$GUI_BASE" || -z "$GUI_USER" || -z "$GUI_PASSWORD" || -z "$ACTION" ]]; then
  echo "[run-pfsense-gui-license-flow] --scenario-code, --gui-base, --gui-user, --gui-password e --action sao obrigatorios." >&2
  usage >&2
  exit 1
fi

if [[ ! "$SSH_PORT" =~ ^[0-9]+$ ]]; then
  echo "[run-pfsense-gui-license-flow] ssh-port deve ser numerico." >&2
  exit 1
fi

case "$ACTION" in
  probe|register|revoke)
    ;;
  *)
    echo "[run-pfsense-gui-license-flow] action invalida: $ACTION" >&2
    exit 1
    ;;
esac

if [[ "$ACTION" == "register" && -z "$LICENSE_KEY" ]]; then
  echo "[run-pfsense-gui-license-flow] --license-key e obrigatoria em action=register." >&2
  exit 1
fi

SCENARIO_DIR="${OUTPUT_ROOT%/}/${RUN_ID}/${SCENARIO_CODE}"
mkdir -p "$SCENARIO_DIR"

if [[ -z "$COOKIE_JAR" ]]; then
  COOKIE_JAR="$SCENARIO_DIR/40-gui-cookie-jar.txt"
fi

SSH_CMD=()
REMOTE_TMP_DIR=""
REMOTE_COOKIE_JAR=""

if [[ -n "$SSH_TARGET" ]]; then
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
fi

LOGIN_HEADERS="$SCENARIO_DIR/41-gui-login-headers.txt"
LOGIN_HTML="$SCENARIO_DIR/42-gui-login.html"
AUTH_HEADERS="$SCENARIO_DIR/43-gui-auth-headers.txt"
AUTH_HTML="$SCENARIO_DIR/44-gui-auth.html"
SETTINGS_HEADERS="$SCENARIO_DIR/45-gui-layer7-headers.txt"
SETTINGS_HTML="$SCENARIO_DIR/46-gui-layer7.html"
ACTION_HEADERS="$SCENARIO_DIR/47-gui-action-headers.txt"
ACTION_HTML="$SCENARIO_DIR/48-gui-action.html"
POST_HEADERS="$SCENARIO_DIR/49-gui-post-action-headers.txt"
POST_HTML="$SCENARIO_DIR/50-gui-post-action.html"
NOTES_FILE="$SCENARIO_DIR/39-gui-flow-notes.txt"

rm -f "$COOKIE_JAR" "$LOGIN_HEADERS" "$LOGIN_HTML" "$AUTH_HEADERS" "$AUTH_HTML" \
  "$SETTINGS_HEADERS" "$SETTINGS_HTML" "$ACTION_HEADERS" "$ACTION_HTML" \
  "$POST_HEADERS" "$POST_HTML" "$NOTES_FILE"

cat > "$NOTES_FILE" <<EOF
run_id=$RUN_ID
scenario_code=$SCENARIO_CODE
generated_at_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)
gui_base=$GUI_BASE
ssh_target=$SSH_TARGET
ssh_port=$SSH_PORT
layer7_path=$LAYER7_PATH
action=$ACTION
cookie_jar=$COOKIE_JAR
EOF

quote_sh() {
  printf '%q' "$1"
}

remote_run() {
  "${SSH_CMD[@]}" sh -lc "$1"
}

remote_copy_to_local() {
  local remote_path="$1"
  local local_path="$2"

  remote_run "test -f $(quote_sh "$remote_path") && cat $(quote_sh "$remote_path") || true" > "$local_path"
}

remote_init_workspace() {
  if [[ -z "$SSH_TARGET" ]]; then
    return
  fi

  REMOTE_TMP_DIR="$(remote_run "mktemp -d /tmp/l7-gui-flow.XXXXXX")"
  REMOTE_COOKIE_JAR="$REMOTE_TMP_DIR/cookie-jar.txt"
  remote_run ": > $(quote_sh "$REMOTE_COOKIE_JAR")"
  printf 'remote_tmp_dir=%s\n' "$REMOTE_TMP_DIR" >> "$NOTES_FILE"
}

remote_cleanup_workspace() {
  if [[ -n "$SSH_TARGET" && -n "$REMOTE_TMP_DIR" ]]; then
    remote_run "rm -rf $(quote_sh "$REMOTE_TMP_DIR")" || true
  fi
}

trap remote_cleanup_workspace EXIT

remote_capture_get() {
  local headers_file="$1"
  local body_file="$2"
  local url="$3"
  local remote_headers="$REMOTE_TMP_DIR/$(basename "$headers_file")"
  local remote_body="$REMOTE_TMP_DIR/$(basename "$body_file")"
  local cmd="curl -k -sS -D $(quote_sh "$remote_headers") -c $(quote_sh "$REMOTE_COOKIE_JAR")"

  cmd+=" -b $(quote_sh "$REMOTE_COOKIE_JAR")"
  cmd+=" $(quote_sh "$url") > $(quote_sh "$remote_body")"

  remote_run "$cmd"
  remote_copy_to_local "$remote_headers" "$headers_file"
  remote_copy_to_local "$remote_body" "$body_file"
  remote_copy_to_local "$REMOTE_COOKIE_JAR" "$COOKIE_JAR"
}

remote_capture_post() {
  local headers_file="$1"
  local body_file="$2"
  local url="$3"
  shift 3
  local remote_headers="$REMOTE_TMP_DIR/$(basename "$headers_file")"
  local remote_body="$REMOTE_TMP_DIR/$(basename "$body_file")"
  local cmd="curl -k -sS -D $(quote_sh "$remote_headers") -b $(quote_sh "$REMOTE_COOKIE_JAR") -c $(quote_sh "$REMOTE_COOKIE_JAR") -X POST $(quote_sh "$url")"
  local arg

  for arg in "$@"; do
    cmd+=" $(quote_sh "$arg")"
  done
  cmd+=" > $(quote_sh "$remote_body")"

  remote_run "$cmd"
  remote_copy_to_local "$remote_headers" "$headers_file"
  remote_copy_to_local "$remote_body" "$body_file"
  remote_copy_to_local "$REMOTE_COOKIE_JAR" "$COOKIE_JAR"
}

extract_csrf_token() {
  local source_file="$1"
  local token=""

  token="$(sed -n 's/.*csrfMagicToken = \"\([^\"]*\)\".*/\1/p' "$source_file" | head -n1)"
  if [[ -z "$token" ]]; then
    token="$(sed -n "s/.*name='__csrf_magic' value=\"\\([^\"]*\\)\".*/\\1/p" "$source_file" | head -n1)"
  fi
  if [[ -z "$token" ]]; then
    token="$(sed -n 's/.*name="__csrf_magic" value="\([^"]*\)".*/\1/p' "$source_file" | head -n1)"
  fi

  printf '%s' "$token"
}

html_contains_login() {
  local source_file="$1"
  if rg -q 'usernamefld|passwordfld|csrfMagicToken|Sign In' "$source_file"; then
    return 0
  fi
  return 1
}

html_contains_layer7_form() {
  local source_file="$1"
  if rg -q 'register_license|revoke_license|license_code' "$source_file"; then
    return 0
  fi
  return 1
}

if [[ -n "$SSH_TARGET" ]]; then
  remote_init_workspace
  remote_capture_get "$LOGIN_HEADERS" "$LOGIN_HTML" "$GUI_BASE/"
else
  curl -k -sS -D "$LOGIN_HEADERS" -c "$COOKIE_JAR" \
    "$GUI_BASE/" > "$LOGIN_HTML"
fi

LOGIN_CSRF="$(extract_csrf_token "$LOGIN_HTML")"
if [[ -z "$LOGIN_CSRF" ]]; then
  echo "[run-pfsense-gui-license-flow] nao foi possivel extrair __csrf_magic da login page." >&2
  exit 1
fi

printf 'login_csrf=%s\n' "$LOGIN_CSRF" >> "$NOTES_FILE"

if [[ -n "$SSH_TARGET" ]]; then
  remote_capture_post "$AUTH_HEADERS" "$AUTH_HTML" "$GUI_BASE/" \
    --data-urlencode "__csrf_magic=$LOGIN_CSRF" \
    --data-urlencode "usernamefld=$GUI_USER" \
    --data-urlencode "passwordfld=$GUI_PASSWORD" \
    --data-urlencode "login=Sign In"
  remote_capture_get "$SETTINGS_HEADERS" "$SETTINGS_HTML" "$GUI_BASE$LAYER7_PATH"
else
  curl -k -sS -D "$AUTH_HEADERS" -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -X POST "$GUI_BASE/" \
    --data-urlencode "__csrf_magic=$LOGIN_CSRF" \
    --data-urlencode "usernamefld=$GUI_USER" \
    --data-urlencode "passwordfld=$GUI_PASSWORD" \
    --data-urlencode 'login=Sign In' > "$AUTH_HTML"

  curl -k -sS -D "$SETTINGS_HEADERS" -b "$COOKIE_JAR" \
    "$GUI_BASE$LAYER7_PATH" > "$SETTINGS_HTML"
fi

if ! html_contains_layer7_form "$SETTINGS_HTML"; then
  {
    echo "result=BLOCKED"
    echo "reason=layer7_settings_not_authenticated"
  } >> "$NOTES_FILE"
  if html_contains_login "$SETTINGS_HTML"; then
    echo "[run-pfsense-gui-license-flow] layer7_settings.php devolveu login page; sessao nao ficou autenticada." >&2
  else
    echo "[run-pfsense-gui-license-flow] layer7_settings.php nao expôs o formulario esperado." >&2
  fi
  exit 2
fi

SETTINGS_CSRF="$(extract_csrf_token "$SETTINGS_HTML")"
printf 'settings_csrf=%s\n' "$SETTINGS_CSRF" >> "$NOTES_FILE"

if [[ "$ACTION" == "probe" ]]; then
  {
    echo "result=PASS"
    echo "reason=authenticated_layer7_settings"
  } >> "$NOTES_FILE"
  exit 0
fi

if [[ -z "$SETTINGS_CSRF" ]]; then
  echo "[run-pfsense-gui-license-flow] nao foi possivel extrair __csrf_magic da pagina autenticada." >&2
  exit 1
fi

ACTION_ARGS=(--data-urlencode "__csrf_magic=$SETTINGS_CSRF")
if [[ "$ACTION" == "register" ]]; then
  ACTION_ARGS+=(--data-urlencode "license_code=$LICENSE_KEY" --data-urlencode "register_license=1")
else
  ACTION_ARGS+=(--data-urlencode "revoke_license=1")
fi

if [[ -n "$SSH_TARGET" ]]; then
  remote_capture_post "$ACTION_HEADERS" "$ACTION_HTML" "$GUI_BASE$LAYER7_PATH#l7-sistema" \
    "${ACTION_ARGS[@]}"
  remote_capture_get "$POST_HEADERS" "$POST_HTML" "$GUI_BASE$LAYER7_PATH"
else
  curl -k -sS -D "$ACTION_HEADERS" -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
    -X POST "$GUI_BASE$LAYER7_PATH#l7-sistema" \
    "${ACTION_ARGS[@]}" > "$ACTION_HTML"

  curl -k -sS -D "$POST_HEADERS" -b "$COOKIE_JAR" \
    "$GUI_BASE$LAYER7_PATH" > "$POST_HTML"
fi

if rg -q 'CSRF Error' "$ACTION_HTML" "$ACTION_HEADERS"; then
  {
    echo "result=BLOCKED"
    echo "reason=csrf_out_of_sync"
  } >> "$NOTES_FILE"
  exit 2
fi

if html_contains_login "$ACTION_HTML" || html_contains_login "$POST_HTML"; then
  {
    echo "result=BLOCKED"
    echo "reason=session_not_authenticated_after_action"
  } >> "$NOTES_FILE"
  exit 2
fi

{
  echo "result=PASS"
  echo "reason=gui_action_submitted"
} >> "$NOTES_FILE"
