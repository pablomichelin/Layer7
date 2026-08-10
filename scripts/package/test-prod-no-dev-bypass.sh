#!/bin/sh
# test-prod-no-dev-bypass.sh — GA2.1 / GA2.2 / GA2.3 / passo 30.4
#
# Confirma que um build de produção (sem -DL7_DEV_BUILD) com pubkey
# all-zeros NÃO activa licença válida (A-01 eliminado no caminho de prod).
#
# Uso (builder FreeBSD):
#   sh scripts/package/test-prod-no-dev-bypass.sh
#
# Exit 0 = PASS; 1 = FAIL; 2 = ambiente inadequado.

set -eu

REPO_ROOT=$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)
LAYER7D="$REPO_ROOT/src/layer7d"
PORT_MK="$REPO_ROOT/package/pfSense-pkg-layer7/Makefile"
OS=$(uname -s)

echo "=== 30.4 / GA2 — produção sem bypass dev ==="

# --- GA2.3: port Makefile não define L7_DEV_BUILD (comentários OK) ---
if grep -n -- '-DL7_DEV_BUILD' "$PORT_MK" | grep -v '^[^:]*:[[:space:]]*#' >/dev/null 2>&1; then
	echo "FAIL: package/pfSense-pkg-layer7/Makefile define -DL7_DEV_BUILD activo" >&2
	grep -n -- '-DL7_DEV_BUILD' "$PORT_MK" >&2 || true
	exit 1
fi
echo "PASS: port Makefile sem -DL7_DEV_BUILD activo"

# --- Fonte: is_dev_key só sob #ifdef L7_DEV_BUILD ---
if ! grep -q '#ifdef L7_DEV_BUILD' "$LAYER7D/license.c"; then
	echo "FAIL: license.c sem #ifdef L7_DEV_BUILD" >&2
	exit 1
fi
# Contar ocorrências de is_dev_key fora de comentários de documentação:
# todas as linhas com is_dev_key( devem estar entre ifdef/endif (heurística: ficheiro
# contém exatamente as chamadas esperadas e o ifdef).
if ! awk '
	/#ifdef L7_DEV_BUILD/ { d=1; next }
	/#endif/ && d { d=0; next }
	/is_dev_key[[:space:]]*\(/ {
		if (!d) { print "OUTSIDE",$0; bad=1 }
	}
	END { exit bad ? 1 : 0 }
' "$LAYER7D/license.c"; then
	echo "FAIL: is_dev_key() referenciado fora de #ifdef L7_DEV_BUILD" >&2
	exit 1
fi
echo "PASS: is_dev_key() apenas sob #ifdef L7_DEV_BUILD"

case "$OS" in
FreeBSD) ;;
*)
	echo "SKIP runtime: $OS não é FreeBSD (compile/runtime no builder)"
	echo "PASS parcial (fonte + Makefile). Correr este script no builder para GA2.2."
	exit 0
	;;
esac

need_cmd() {
	command -v "$1" >/dev/null 2>&1 || {
		echo "FAIL: comando em falta: $1" >&2
		exit 2
	}
}
need_cmd cc

TMP=$(mktemp -d "${TMPDIR:-/tmp}/l7-30.4-XXXXXX")
trap 'rm -rf "$TMP"' EXIT INT TERM

cat > "$TMP/harness.c" <<'EOF'
#include "license.h"
#include <stdio.h>
#include <string.h>

int
main(void)
{
	struct l7_license_info info;
	int rc;

	memset(&info, 0, sizeof(info));
	rc = layer7_license_check(&info);

	if (info.dev_mode != 0) {
		fprintf(stderr, "FAIL: dev_mode=%d (esperado 0 em produção)\n",
		    info.dev_mode);
		return 1;
	}
	if (info.valid != 0) {
		fprintf(stderr, "FAIL: valid=%d (esperado 0 com pubkey all-zeros)\n",
		    info.valid);
		return 1;
	}
	if (rc != -1) {
		fprintf(stderr, "FAIL: rc=%d (esperado -1)\n", rc);
		return 1;
	}
	printf("PASS: pubkey all-zeros + build produção => invalid "
	    "(dev_mode=0 valid=0 rc=-1)\n");
	printf("error=%s\n", info.error);
	return 0;
}
EOF

# Build produção forçada com pubkey zero — SEM L7_DEV_BUILD.
cc -Wall -Wextra -O2 -DL7_TEST_ZERO_PUBKEY \
	-I"$LAYER7D" -I"$REPO_ROOT/src/common" \
	-o "$TMP/test-prod-zero" \
	"$TMP/harness.c" "$LAYER7D/license.c" "$LAYER7D/features.c" \
	-lssl -lcrypto

# String do bypass não deve existir no binário de produção.
if strings "$TMP/test-prod-zero" | grep -q 'development key'; then
	echo "FAIL: string 'development key' presente em build produção" >&2
	exit 1
fi
echo "PASS: string bypass ausente no binário de produção (zero-key)"

"$TMP/test-prod-zero"

# Controlo positivo: com L7_DEV_BUILD + zero key, bypass activa.
cc -Wall -Wextra -O2 -DL7_TEST_ZERO_PUBKEY -DL7_DEV_BUILD \
	-I"$LAYER7D" -I"$REPO_ROOT/src/common" \
	-o "$TMP/test-dev-zero" \
	"$TMP/harness.c" "$LAYER7D/license.c" "$LAYER7D/features.c" \
	-lssl -lcrypto

set +e
"$TMP/test-dev-zero" >/dev/null 2>&1
dev_rc=$?
set -e
if [ "$dev_rc" -eq 0 ]; then
	echo "FAIL: build L7_DEV_BUILD+zero deveria activar bypass (harness espera invalid)" >&2
	exit 1
fi
# Confirmar explicitamente valid=1 no modo dev
cat > "$TMP/harness_dev.c" <<'EOF'
#include "license.h"
#include <stdio.h>
#include <string.h>
int main(void) {
	struct l7_license_info info;
	memset(&info, 0, sizeof(info));
	(void)layer7_license_check(&info);
	if (info.dev_mode == 1 && info.valid == 1) {
		printf("PASS: L7_DEV_BUILD+zero => bypass activo (controlo)\n");
		return 0;
	}
	fprintf(stderr, "FAIL: controlo dev: dev_mode=%d valid=%d\n",
	    info.dev_mode, info.valid);
	return 1;
}
EOF
cc -Wall -Wextra -O2 -DL7_TEST_ZERO_PUBKEY -DL7_DEV_BUILD \
	-I"$LAYER7D" -I"$REPO_ROOT/src/common" \
	-o "$TMP/test-dev-ok" \
	"$TMP/harness_dev.c" "$LAYER7D/license.c" "$LAYER7D/features.c" \
	-lssl -lcrypto
"$TMP/test-dev-ok"

echo "PASS: GA2.1/GA2.2/GA2.3 (runtime FreeBSD)"
exit 0
