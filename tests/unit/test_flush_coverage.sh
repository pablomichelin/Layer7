#!/bin/sh
# Contract test: cobertura de flush PF alinhada entre PHP, helper e pkg-deinstall.
# Regressao B-002/B-003/B-004 (tabelas orfas e uninstall incompleto).

set -eu

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
INC="${ROOT}/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc"
PFCTL="${ROOT}/package/pfSense-pkg-layer7/files/usr/local/libexec/layer7-pfctl"
DEINSTALL="${ROOT}/package/pfSense-pkg-layer7/files/pkg-deinstall.in"

fail=0

assert_grep() {
	_name="$1"
	_pattern="$2"
	_file="$3"
	if grep -q "$_pattern" "$_file"; then
		printf "PASS: %s\n" "$_name"
	else
		printf "FAIL: %s\n" "$_name"
		fail=1
	fi
}

assert_grep "layer7.inc flush inclui layer7_exc_allow_*" \
    'layer7_exc_allow_' "$INC"

if awk '/function layer7_bl_apply/,/^}/' "$INC" | grep -q 'layer7_flush_dynamic_tables'; then
	printf "PASS: layer7_bl_apply chama layer7_flush_dynamic_tables\n"
else
	printf "FAIL: layer7_bl_apply chama layer7_flush_dynamic_tables\n"
	fail=1
fi

assert_grep "layer7.inc materializa tabelas estaticas (BG-075)" \
    'layer7_static_origin_tables_apply_to_pf' "$INC"
assert_grep "layer7.inc resync chama apply estatico" \
    'layer7_static_origin_tables_apply_to_pf($data, $bl_config)' "$INC"
assert_grep "layer7-pfctl ensure cobre layer7_exc_allow_*" \
    'layer7_exc_allow_' "$PFCTL"
if awk '/^ensure\)/,/^flush\)/' "$PFCTL" | grep -q 'layer7_exc_allow_'; then
	printf "PASS: layer7-pfctl ensure inclui exc_allow\n"
else
	printf "FAIL: layer7-pfctl ensure inclui exc_allow\n"
	fail=1
fi

assert_grep "layer7-pfctl define flush_tables_exception_allow" \
    'flush_tables_exception_allow' "$PFCTL"

if grep -A8 'flush_tables_all()' "$PFCTL" | grep -q 'flush_tables_exception_allow'; then
	printf "PASS: layer7-pfctl flush-all inclui exc_allow\n"
else
	printf "FAIL: layer7-pfctl flush-all inclui exc_allow\n"
	fail=1
fi

assert_grep "pkg-deinstall PRE chama layer7-pfctl flush-all" \
    'layer7-pfctl flush-all' "$DEINSTALL"
assert_grep "pkg-deinstall POST fallback inclui exc_allow" \
    'layer7_exc_allow_' "$DEINSTALL"
assert_grep "pkg-deinstall POST fallback inclui pallow" \
    'layer7_pallow_' "$DEINSTALL"
assert_grep "pkg-deinstall POST fallback inclui pexc" \
    'layer7_pexc_' "$DEINSTALL"
assert_grep "layer7.inc flush inclui layer7_pexc_*" \
    'layer7_pexc_' "$INC"
assert_grep "layer7-pfctl flush-all inclui pexc" \
    'layer7_pexc_' "$PFCTL"
assert_grep "pkg-deinstall POST fallback inclui allow_dst" \
    'layer7_allow_dst' "$DEINSTALL"
assert_grep "pkg-deinstall POST fallback inclui layer7_mitm_src" \
    'layer7_mitm_src' "$DEINSTALL"
assert_grep "pkg-deinstall POST fallback inclui layer7_mitm_dst" \
    'layer7_mitm_dst' "$DEINSTALL"
assert_grep "layer7-pfctl define flush_tables_mitm" \
    'flush_tables_mitm' "$PFCTL"
if grep -A12 'flush_tables_all()' "$PFCTL" | grep -q 'flush_tables_mitm'; then
	printf "PASS: layer7-pfctl flush-all inclui mitm\n"
else
	printf "FAIL: layer7-pfctl flush-all inclui mitm\n"
	fail=1
fi
assert_grep "layer7.inc flush inclui layer7_mitm_src" \
    'layer7_mitm_src' "$INC"

if [ "$fail" -ne 0 ]; then
	printf "\nTEST FLUSH COVERAGE: FAILED\n"
	exit 1
fi
printf "\nTEST FLUSH COVERAGE: ALL PASSED\n"
exit 0
