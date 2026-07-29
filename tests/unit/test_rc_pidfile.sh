#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
RC="${ROOT}/package/pfSense-pkg-layer7/files/usr/local/etc/rc.d/layer7d"
TMP=$(mktemp -d "${TMPDIR:-/tmp}/layer7-rc-pid.XXXXXX")
trap 'rm -rf "${TMP}"' EXIT HUP INT TERM

# Executa exactamente a funcao entregue no rc.d, sem carregar rc.subr nem
# disparar qualquer comando de servico.
PID_FUNC=$(sed -n '/^layer7d_pid_from_file()$/,/^}$/p' "${RC}")
if [ -z "${PID_FUNC}" ]; then
	echo "FAIL: layer7d_pid_from_file ausente"
	exit 1
fi
eval "${PID_FUNC}"

pidfile="${TMP}/layer7d.pid"

printf '20278' >"${pidfile}"
layer7d_pid_from_file
[ "${_p}" = "20278" ] || {
	echo "FAIL: PID sem newline nao foi lido"
	exit 1
}

printf '  20279  \n' >"${pidfile}"
layer7d_pid_from_file
[ "${_p}" = "20279" ] || {
	echo "FAIL: espacos/newline nao foram normalizados"
	exit 1
}

printf '20x79\n' >"${pidfile}"
if layer7d_pid_from_file; then
	echo "FAIL: PID alfanumerico foi aceite"
	exit 1
fi

: >"${pidfile}"
if layer7d_pid_from_file; then
	echo "FAIL: pidfile vazio foi aceite"
	exit 1
fi

rm -f "${pidfile}"
if layer7d_pid_from_file; then
	echo "FAIL: pidfile ausente foi aceite"
	exit 1
fi

echo "PASS: test_rc_pidfile"
