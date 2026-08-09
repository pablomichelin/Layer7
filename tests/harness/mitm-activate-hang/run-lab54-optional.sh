#!/bin/sh
# Opcional: corre o hang harness no lab .54 (Ubuntu isolado).
# Não usa .254. Requer SSH BatchMode para root@192.168.100.54.
set -eu

ROOT=$(CDPATH= cd -- "$(dirname "$0")/../../.." && pwd)
HDIR="$ROOT/tests/harness/mitm-activate-hang"
SSH_54=${SSH_54:-root@192.168.100.54}
SSH_OPTS=${SSH_OPTS:-"-o BatchMode=yes -o ConnectTimeout=8"}
REMOTE=/tmp/l7-mitm-hang-harness

echo "LAB_TARGET=$SSH_54"
# shellcheck disable=SC2086
ssh $SSH_OPTS "$SSH_54" "rm -rf '$REMOTE' && mkdir -p '$REMOTE'"
# shellcheck disable=SC2086
scp $SSH_OPTS -q -r "$HDIR/mock-bin" "$HDIR/php-sync-exec-pattern.php" \
	"$HDIR/run-local-hang.sh" "$SSH_54:$REMOTE/"
# shellcheck disable=SC2086
ssh $SSH_OPTS "$SSH_54" "chmod +x '$REMOTE'/run-local-hang.sh '$REMOTE'/mock-bin/* && \
	cd '$REMOTE' && HANG_SLEEP=${HANG_SLEEP:-8} HANG_TIMEOUT=${HANG_TIMEOUT:-5} \
	sh ./run-local-hang.sh"
echo "PASS: lab .54 hang harness finished"
exit 0
