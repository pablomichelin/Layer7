#!/bin/bash
# Auth helper P4.2 — probe com -T (igual à sessão real). Sem segredos literais.
#
# Causa 170000Z: probe SEM -T falhava (menu pfSense / timeout) e o helper
# classificava como «sem credenciais» apesar da chave existir.
#
# Ficheiros esperados (fora do git, mode 0600):
#   /tmp/l7-lab-254.pass     — senha root .254 (uma linha)
#   /tmp/l7-p4-user.env      — utilizador .24
#   /tmp/l7-p4-pass.env      — senha .24
#
# Variáveis opcionais:
#   P4_SSH_BIN  P4_SSH_HOST_254  P4_SSH_HOST_54
#   P4_SSH_CONNECT_TIMEOUT  P4_SSH_PROBE_TRIES
#   L7_LAB_254_IDENTITY  L7_LAB_254_PASS_FILE
#   L7_P4_24_USER_FILE  L7_P4_24_PASS_FILE  SSHPASS

p4_auth_note() {
  echo "auth_method=$1"
}

p4_ssh_bin() {
  printf '%s' "${P4_SSH_BIN:-ssh}"
}

p4_ssh_tty_opts() {
  # RequestTTY=no + -T: shell directo no pfSense (evita menu 0–8).
  printf '%s' "-T -o RequestTTY=no -o BatchMode=yes -o ConnectTimeout=${P4_SSH_CONNECT_TIMEOUT:-20} -o StrictHostKeyChecking=no"
}

p4_ident_opts() {
  local ident="${L7_LAB_254_IDENTITY:-}"
  if [ -n "$ident" ] && [ -f "$ident" ]; then
    printf '%s' "-i ${ident} -o IdentitiesOnly=yes"
  fi
}

p4_classify_ssh_err() {
  # stdin: stderr do ssh. Nunca imprime segredos.
  local err
  err=$(cat)
  if printf '%s' "$err" | grep -qiE 'Permission denied|publickey'; then
    echo publickey_denied
    return
  fi
  if printf '%s' "$err" | grep -qiE 'timed out|Connection refused|Connection reset|Network is unreachable|No route to host'; then
    echo ssh_transient
    return
  fi
  echo ssh_probe_failed
}

p4_ssh_254() {
  local host="${P4_SSH_HOST_254:-root@192.168.100.254}"
  local passfile="${L7_LAB_254_PASS_FILE:-/tmp/l7-lab-254.pass}"
  local tries=0 max="${P4_SSH_PROBE_TRIES:-3}"
  local errfile class sshb
  sshb=$(p4_ssh_bin)
  errfile=$(mktemp "${TMPDIR:-/tmp}/p4-ssh-254.XXXXXX")

  # Probe com as MESMAS flags da sessão real; stdin descartado.
  while [ "$tries" -lt "$max" ]; do
    tries=$((tries + 1))
    # shellcheck disable=SC2046,SC2086
    if "$sshb" $(p4_ssh_tty_opts) $(p4_ident_opts) "$host" "true" </dev/null 2>"$errfile"; then
      p4_auth_note "ssh_key_batchmode_254" >&2
      rm -f "$errfile"
      # shellcheck disable=SC2046,SC2086
      "$sshb" $(p4_ssh_tty_opts) $(p4_ident_opts) "$host" "$@"
      return $?
    fi
    sleep $((tries * 2))
  done

  class=$(p4_classify_ssh_err <"$errfile")
  rm -f "$errfile"

  if [ "$class" = "publickey_denied" ] && [ -n "${SSHPASS:-}" ]; then
    p4_auth_note "sshpass_env_SSHPASS_254" >&2
    sshpass -e "$sshb" -T -o RequestTTY=no -o ConnectTimeout="${P4_SSH_CONNECT_TIMEOUT:-20}" \
      -o StrictHostKeyChecking=no "$host" "$@"
    return $?
  fi
  if [ "$class" = "publickey_denied" ] && [ -f "$passfile" ]; then
    local mode
    mode=$(stat -f %A "$passfile" 2>/dev/null || stat -c %a "$passfile" 2>/dev/null || echo "?")
    if [ "$mode" != "600" ] && [ "$mode" != "0600" ]; then
      echo "AUTH_FAIL passfile_mode=$mode want=600" >&2
      return 9
    fi
    # shellcheck disable=SC2155
    export SSHPASS
    SSHPASS=$(tr -d '\r\n' <"$passfile")
    p4_auth_note "sshpass_file_0600_254" >&2
    sshpass -e "$sshb" -T -o RequestTTY=no -o ConnectTimeout="${P4_SSH_CONNECT_TIMEOUT:-20}" \
      -o StrictHostKeyChecking=no "$host" "$@"
    local ec=$?
    unset SSHPASS
    return $ec
  fi

  echo "AUTH_FAIL $class host=.254" >&2
  return 8
}

p4_ssh_54() {
  local host="${P4_SSH_HOST_54:-root@192.168.100.54}"
  local sshb errfile
  sshb=$(p4_ssh_bin)
  errfile=$(mktemp "${TMPDIR:-/tmp}/p4-ssh-54.XXXXXX")
  # shellcheck disable=SC2046,SC2086
  if "$sshb" $(p4_ssh_tty_opts) "$host" "true" </dev/null 2>"$errfile"; then
    p4_auth_note "ssh_key_batchmode_54" >&2
    rm -f "$errfile"
    # shellcheck disable=SC2046,SC2086
    "$sshb" $(p4_ssh_tty_opts) "$host" "$@"
    return $?
  fi
  echo "AUTH_FAIL $(p4_classify_ssh_err <"$errfile") host=.54" >&2
  rm -f "$errfile"
  return 8
}

p4_ssh_24() {
  local userfile="${L7_P4_24_USER_FILE:-/tmp/l7-p4-user.env}"
  local passfile="${L7_P4_24_PASS_FILE:-/tmp/l7-p4-pass.env}"
  if [ ! -f "$userfile" ] || [ ! -f "$passfile" ]; then
    echo "AUTH_FAIL .24 missing user/pass files (0600 out of repo)" >&2
    return 9
  fi
  local um pm U
  um=$(stat -f %A "$userfile" 2>/dev/null || stat -c %a "$userfile" 2>/dev/null || echo "?")
  pm=$(stat -f %A "$passfile" 2>/dev/null || stat -c %a "$passfile" 2>/dev/null || echo "?")
  if [ "$um" != "600" ] && [ "$um" != "0600" ]; then
    echo "AUTH_FAIL userfile_mode=$um want=600" >&2
    return 9
  fi
  if [ "$pm" != "600" ] && [ "$pm" != "0600" ]; then
    echo "AUTH_FAIL passfile_mode=$pm want=600" >&2
    return 9
  fi
  U=$(tr -d '\r\n' <"$userfile")
  export SSHPASS
  SSHPASS=$(tr -d '\r\n' <"$passfile")
  p4_auth_note "sshpass_file_0600_24" >&2
  sshpass -e "$(p4_ssh_bin)" -T -o RequestTTY=no -o ConnectTimeout="${P4_SSH_CONNECT_TIMEOUT:-20}" \
    -o StrictHostKeyChecking=no "${U}@192.168.100.24" "$@"
  local ec=$?
  unset SSHPASS
  return $ec
}
