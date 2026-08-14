#!/bin/bash
# Auth helper P4 — sem segredos literais no repositório.
# Ordem: SSH BatchMode (chave) → SSHPASS env → ficheiro 0600 fora do repo.
#
# Ficheiros esperados (fora do git, mode 0600):
#   /tmp/l7-lab-254.pass     — senha root .254 (uma linha)
#   /tmp/l7-p4-user.env      — utilizador .24 (uma linha)
#   /tmp/l7-p4-pass.env      — senha .24 (uma linha)
#
# Variáveis opcionais:
#   L7_LAB_254_PASS_FILE  L7_P4_24_USER_FILE  L7_P4_24_PASS_FILE
#   SSHPASS (já exportada pelo operador)

p4_auth_note() {
  # Evidência: só método, nunca valor
  echo "auth_method=$1"
}

p4_ssh_254() {
  # usage: p4_ssh_254 remote-command...
  # Preferir chave; senão sshpass -e com ficheiro/env.
  # Probe SEMPRE com stdin de /dev/null — senão consome heredoc do caller.
  local passfile="${L7_LAB_254_PASS_FILE:-/tmp/l7-lab-254.pass}"
  if ssh -o BatchMode=yes -o ConnectTimeout=8 -o StrictHostKeyChecking=no \
      root@192.168.100.254 "true" </dev/null 2>/dev/null; then
    p4_auth_note "ssh_key_batchmode_254" >&2
    ssh -T -o BatchMode=yes -o ConnectTimeout=20 -o StrictHostKeyChecking=no \
      root@192.168.100.254 "$@"
    return $?
  fi
  if [ -n "${SSHPASS:-}" ]; then
    p4_auth_note "sshpass_env_SSHPASS_254" >&2
    sshpass -e ssh -T -o ConnectTimeout=20 -o StrictHostKeyChecking=no \
      root@192.168.100.254 "$@"
    return $?
  fi
  if [ -f "$passfile" ]; then
    local mode
    mode=$(stat -f %A "$passfile" 2>/dev/null || stat -c %a "$passfile" 2>/dev/null || echo "?")
    if [ "$mode" != "600" ] && [ "$mode" != "0600" ]; then
      echo "AUTH_FAIL passfile_mode=$mode want=600 path=$passfile" >&2
      return 9
    fi
    # shellcheck disable=SC2155
    export SSHPASS=$(tr -d '\r\n' <"$passfile")
    p4_auth_note "sshpass_file_0600_254" >&2
    sshpass -e ssh -T -o ConnectTimeout=20 -o StrictHostKeyChecking=no \
      root@192.168.100.254 "$@"
    local ec=$?
    unset SSHPASS
    return $ec
  fi
  echo "AUTH_FAIL no_key_no_SSHPASS_no_passfile" >&2
  return 9
}

p4_ssh_54() {
  if ssh -o BatchMode=yes -o ConnectTimeout=8 -o StrictHostKeyChecking=no \
      root@192.168.100.54 "true" </dev/null 2>/dev/null; then
    p4_auth_note "ssh_key_batchmode_54" >&2
    ssh -T -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=no \
      root@192.168.100.54 "$@"
    return $?
  fi
  echo "AUTH_FAIL .54 requires BatchMode key" >&2
  return 9
}

p4_ssh_24() {
  local userfile="${L7_P4_24_USER_FILE:-/tmp/l7-p4-user.env}"
  local passfile="${L7_P4_24_PASS_FILE:-/tmp/l7-p4-pass.env}"
  if [ ! -f "$userfile" ] || [ ! -f "$passfile" ]; then
    echo "AUTH_FAIL .24 missing user/pass files (0600 out of repo)" >&2
    return 9
  fi
  local um pm
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
  local U
  U=$(tr -d '\r\n' <"$userfile")
  export SSHPASS
  SSHPASS=$(tr -d '\r\n' <"$passfile")
  p4_auth_note "sshpass_file_0600_24" >&2
  sshpass -e ssh -T -o ConnectTimeout=20 -o StrictHostKeyChecking=no \
    "${U}@192.168.100.24" "$@"
  local ec=$?
  unset SSHPASS
  return $ec
}
