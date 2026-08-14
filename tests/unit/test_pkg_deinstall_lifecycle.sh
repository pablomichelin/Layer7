#!/bin/sh
# Contrato P1-6/P1-7/P1-8/P2-12 + A1/A2/M2 — pkg-deinstall.in + uninstall.sh.
# Harness em tempdir: quatro ramos + falha de backup. Grep fica só como smoke.

set -eu

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
DEINSTALL="${ROOT}/package/pfSense-pkg-layer7/files/pkg-deinstall.in"
UNINSTALL="${ROOT}/scripts/release/uninstall.sh"
REMOVAL="${ROOT}/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_removal.php"

fail=0
PREFIX=""

cleanup() {
	if [ -n "${PREFIX}" ] && [ -d "${PREFIX}" ]; then
		chmod -R u+w "${PREFIX}" 2>/dev/null || true
		rm -rf "${PREFIX}"
	fi
}
trap cleanup EXIT

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

assert_not_grep() {
	_name="$1"
	_pattern="$2"
	_file="$3"
	if grep -q "$_pattern" "$_file"; then
		printf "FAIL: %s\n" "$_name"
		fail=1
	else
		printf "PASS: %s\n" "$_name"
	fi
}

file_mode() {
	if [ ! -e "$1" ]; then
		echo "missing"
		return 1
	fi
	if stat -f %Lp "$1" >/dev/null 2>&1; then
		stat -f %Lp "$1"
	else
		stat -c %a "$1"
	fi
}

assert_mode() {
	_name="$1"
	_path="$2"
	_want="$3"
	_got=$(file_mode "$_path" 2>/dev/null || echo missing)
	_got_n=$(printf '%s' "$_got" | sed 's/^0*//')
	_want_n=$(printf '%s' "$_want" | sed 's/^0*//')
	[ -n "$_got_n" ] || _got_n=0
	[ -n "$_want_n" ] || _want_n=0
	if [ "$_got_n" = "$_want_n" ]; then
		printf "PASS: %s (mode %s)\n" "$_name" "$_got"
	else
		printf "FAIL: %s (mode %s want %s)\n" "$_name" "$_got" "$_want"
		fail=1
	fi
}

assert_exists() {
	_name="$1"
	_path="$2"
	if [ -e "$_path" ]; then
		printf "PASS: %s\n" "$_name"
	else
		printf "FAIL: %s (missing %s)\n" "$_name" "$_path"
		fail=1
	fi
}

assert_missing() {
	_name="$1"
	_path="$2"
	if [ -e "$_path" ]; then
		printf "FAIL: %s (still present %s)\n" "$_name" "$_path"
		fail=1
	else
		printf "PASS: %s\n" "$_name"
	fi
}

# --- smoke: contratos de ficheiro (P2-12 / copy GUI) ---
assert_grep "pkg-deinstall define _is_upgrade via PKG_UPGRADE" \
    '_is_upgrade=1' "$DEINSTALL"
assert_grep "pkg-deinstall nao apaga json/lic em upgrade" \
    'upgrade nunca apaga json/.lic' "$DEINSTALL"
assert_grep "pkg-deinstall staging persistente (nao /tmp)" \
    'deinstall-preserve' "$DEINSTALL"
assert_grep "pkg-deinstall backup identity-ldap.secret" \
    'identity-ldap.secret' "$DEINSTALL"
assert_grep "pkg-deinstall backup identity-radius.secret" \
    'identity-radius.secret' "$DEINSTALL"
assert_grep "pkg-deinstall backup identity-dc.secret" \
    'identity-dc.secret' "$DEINSTALL"
assert_grep "pkg-deinstall apaga checkin no uninstall real" \
    'layer7-checkin.json' "$DEINSTALL"
assert_grep "pkg-deinstall apaga clock-mark no uninstall real" \
    'clock-mark.json' "$DEINSTALL"
assert_grep "pkg-deinstall apaga content-subscription no uninstall real" \
    'content-subscription.json' "$DEINSTALL"
assert_grep "pkg-deinstall PRE chama layer7_remove_unbound_anti_doh" \
    'layer7_remove_unbound_anti_doh' "$DEINSTALL"
assert_grep "pkg-deinstall anti-DoH so sem PKG_UPGRADE" \
    'anti-DoH só em uninstall real' "$DEINSTALL"
assert_not_grep "pkg-deinstall nao usa /tmp para mitm" \
    '/tmp/layer7-mitm.bak' "$DEINSTALL"
assert_not_grep "pkg-deinstall nao usa /tmp para identity" \
    '/tmp/layer7-identity-' "$DEINSTALL"

assert_grep "uninstall.sh apaga checkin no uninstall completo" \
    'layer7-checkin.json' "$UNINSTALL"
assert_grep "uninstall.sh staging persistente" \
    'deinstall-preserve' "$UNINSTALL"
assert_grep "uninstall.sh keep-config preserva identity secrets" \
    'identity-ldap.secret' "$UNINSTALL"
assert_grep "uninstall.sh keep-config preserva identity-dc.secret" \
    'identity-dc.secret' "$UNINSTALL"
assert_not_grep "uninstall.sh nao usa /tmp para mitm" \
    '/tmp/layer7-mitm.bak' "$UNINSTALL"
assert_not_grep "uninstall.sh nao usa /tmp para identity" \
    '/tmp/layer7-identity-' "$UNINSTALL"

assert_grep "GUI keep-config menciona CA MITM" \
    'CA MITM' "$REMOVAL"

# --- harness funcional ---
setup_tree() {
	cleanup
	PREFIX=$(mktemp -d "${TMPDIR:-/tmp}/l7-deinstall.XXXXXX")
	ETC="${PREFIX}/etc"
	VAR_DB="${PREFIX}/var/db"
	RUN="${PREFIX}/var/run"
	mkdir -p "${ETC}/layer7/mitm" "${VAR_DB}/layer7" "${RUN}"
	printf 'json\n' > "${ETC}/layer7.json"
	printf 'lic\n' > "${ETC}/layer7.lic"
	printf 'ca-key-secret\n' > "${ETC}/layer7/mitm/ca.key"
	printf 'ca-crt\n' > "${ETC}/layer7/mitm/ca.crt"
	chmod 0600 "${ETC}/layer7/mitm/ca.key"
	chmod 0644 "${ETC}/layer7/mitm/ca.crt"
	printf 'ldap-secret\n' > "${ETC}/layer7/identity-ldap.secret"
	printf 'radius-secret\n' > "${ETC}/layer7/identity-radius.secret"
	printf 'dc-secret\n' > "${ETC}/layer7/identity-dc.secret"
	chmod 0600 "${ETC}/layer7/identity-ldap.secret" \
		"${ETC}/layer7/identity-radius.secret" \
		"${ETC}/layer7/identity-dc.secret"
	printf '{}\n' > "${ETC}/layer7/profiles-custom.json"
	chmod 0664 "${ETC}/layer7/profiles-custom.json"
	printf 'checkin\n' > "${VAR_DB}/layer7-checkin.json"
	printf 'clock\n' > "${VAR_DB}/layer7/clock-mark.json"
	printf 'sub\n' > "${VAR_DB}/layer7/content-subscription.json"
	chmod 0600 "${VAR_DB}/layer7-checkin.json"
}

# Versão vulnerável (c2b9fdb): /tmp + || true + rm -rf. O mutante A1
# tem de destruir os secrets — senão o detector está partido.
run_vulnerable() {
	_etc="$1"
	_tmp="$2"
	_mitm_bak="${_tmp}/layer7-mitm.bak"
	_id_ldap_bak="${_tmp}/layer7-identity-ldap.secret.bak"
	_id_radius_bak="${_tmp}/layer7-identity-radius.secret.bak"
	_id_dc_bak="${_tmp}/layer7-identity-dc.secret.bak"
	mkdir -p "${_tmp}"
	if [ -d "${_etc}/layer7/mitm" ]; then
		rm -rf "${_mitm_bak}"
		cp -a "${_etc}/layer7/mitm" "${_mitm_bak}" 2>/dev/null || true
	fi
	if [ -f "${_etc}/layer7/identity-ldap.secret" ]; then
		cp -f "${_etc}/layer7/identity-ldap.secret" \
			"${_id_ldap_bak}" 2>/dev/null || true
	fi
	if [ -f "${_etc}/layer7/identity-radius.secret" ]; then
		cp -f "${_etc}/layer7/identity-radius.secret" \
			"${_id_radius_bak}" 2>/dev/null || true
	fi
	if [ -f "${_etc}/layer7/identity-dc.secret" ]; then
		cp -f "${_etc}/layer7/identity-dc.secret" \
			"${_id_dc_bak}" 2>/dev/null || true
	fi
	rm -rf "${_etc}/layer7"
	if [ -d "${_mitm_bak}" ]; then
		mkdir -p "${_etc}/layer7"
		mv -f "${_mitm_bak}" "${_etc}/layer7/mitm" 2>/dev/null || true
	fi
	if [ -f "${_id_ldap_bak}" ]; then
		mkdir -p "${_etc}/layer7"
		mv -f "${_id_ldap_bak}" "${_etc}/layer7/identity-ldap.secret" \
			2>/dev/null || true
		chmod 0600 "${_etc}/layer7/identity-ldap.secret" 2>/dev/null || true
	fi
	if [ -f "${_id_radius_bak}" ]; then
		mkdir -p "${_etc}/layer7"
		mv -f "${_id_radius_bak}" "${_etc}/layer7/identity-radius.secret" \
			2>/dev/null || true
		chmod 0600 "${_etc}/layer7/identity-radius.secret" 2>/dev/null || true
	fi
	if [ -f "${_id_dc_bak}" ]; then
		mkdir -p "${_etc}/layer7"
		mv -f "${_id_dc_bak}" "${_etc}/layer7/identity-dc.secret" \
			2>/dev/null || true
		chmod 0600 "${_etc}/layer7/identity-dc.secret" 2>/dev/null || true
	fi
}

run_production() {
	_is_upgrade="$1"
	_keep_config="$2"
	_keep_license="$3"
	LAYER7_ETC_DIR="${ETC}"
	LAYER7_VAR_DB_DIR="${VAR_DB}"
	LAYER7_RUN_DIR="${RUN}"
	export LAYER7_ETC_DIR LAYER7_VAR_DB_DIR LAYER7_RUN_DIR
	layer7_deinstall_etc_lifecycle
}

# Carregar funções de produção (pkg-deinstall.in).
LAYER7_DEINSTALL_LIB=1
# shellcheck disable=SC1090
. "$DEINSTALL"
unset LAYER7_DEINSTALL_LIB

# Mutante A1: cp falha → versão vulnerável APAGA secrets.
setup_tree
_blocked="${PREFIX}/blocked-tmp"
mkdir -p "${_blocked}"
chmod 000 "${_blocked}"
run_vulnerable "${ETC}" "${_blocked}"
chmod 700 "${_blocked}" 2>/dev/null || true
if [ -d "${ETC}/layer7/mitm" ] || [ -f "${ETC}/layer7/identity-ldap.secret" ]; then
	printf "FAIL: mutant detector (vulnerable impl kept secrets)\n"
	fail=1
else
	printf "PASS: mutant A1 (vulnerable wipe after backup fail)\n"
fi

# Produção + mesma falha de staging: secrets intactos, sem wipe.
setup_tree
# deinstall-preserve como ficheiro → mkdir -p falha
touch "${VAR_DB}/layer7/deinstall-preserve"
run_production 0 1 0
assert_exists "A1 keep-config: mitm intacto se backup falha" \
    "${ETC}/layer7/mitm/ca.key"
assert_exists "A1 keep-config: ldap intacto se backup falha" \
    "${ETC}/layer7/identity-ldap.secret"
assert_exists "A1 keep-config: radius intacto se backup falha" \
    "${ETC}/layer7/identity-radius.secret"
assert_exists "A1 keep-config: dc intacto se backup falha" \
    "${ETC}/layer7/identity-dc.secret"
assert_mode "A1 keep-config: ca.key 0600 apos falha" \
    "${ETC}/layer7/mitm/ca.key" 600
assert_mode "A1 keep-config: ldap 0600 apos falha" \
    "${ETC}/layer7/identity-ldap.secret" 600
assert_missing "A1 staging parcial removido apos abort" \
    "${VAR_DB}/layer7/deinstall-preserve/mitm"

# Upgrade com backup OK: restore + 0600 + staging limpo.
setup_tree
run_production 1 0 0
assert_exists "upgrade: json preservado" "${ETC}/layer7.json"
assert_exists "upgrade: lic preservado" "${ETC}/layer7.lic"
assert_exists "upgrade: mitm restaurado" "${ETC}/layer7/mitm/ca.key"
assert_exists "upgrade: ldap restaurado" "${ETC}/layer7/identity-ldap.secret"
assert_exists "upgrade: radius restaurado" "${ETC}/layer7/identity-radius.secret"
assert_exists "upgrade: dc restaurado" "${ETC}/layer7/identity-dc.secret"
assert_exists "upgrade: profiles-custom restaurado" \
    "${ETC}/layer7/profiles-custom.json"
assert_exists "upgrade: check-in preservado" "${VAR_DB}/layer7-checkin.json"
assert_mode "upgrade: ca.key 0600" "${ETC}/layer7/mitm/ca.key" 600
assert_mode "upgrade: ldap 0600" "${ETC}/layer7/identity-ldap.secret" 600
assert_mode "upgrade: radius 0600" "${ETC}/layer7/identity-radius.secret" 600
assert_mode "upgrade: dc 0600" "${ETC}/layer7/identity-dc.secret" 600
assert_missing "upgrade: staging removido apos restore" \
    "${VAR_DB}/layer7/deinstall-preserve"

# keep-config: igual ao upgrade para runtime + json/lic + /var/db.
setup_tree
run_production 0 1 0
assert_exists "keep-config: json" "${ETC}/layer7.json"
assert_exists "keep-config: lic" "${ETC}/layer7.lic"
assert_exists "keep-config: mitm" "${ETC}/layer7/mitm/ca.key"
assert_exists "keep-config: ldap" "${ETC}/layer7/identity-ldap.secret"
assert_exists "keep-config: check-in" "${VAR_DB}/layer7-checkin.json"
assert_mode "keep-config: ldap 0600" "${ETC}/layer7/identity-ldap.secret" 600
assert_missing "keep-config: staging removido" \
    "${VAR_DB}/layer7/deinstall-preserve"

# 0600 durante o staging (antes do wipe).
setup_tree
LAYER7_ETC_DIR="${ETC}"
LAYER7_VAR_DB_DIR="${VAR_DB}"
export LAYER7_ETC_DIR LAYER7_VAR_DB_DIR
layer7_deinstall_init_paths
if layer7_deinstall_stage_secrets; then
	assert_mode "A2 staging: ldap 0600 antes do restore" \
	    "${_l7_stage}/identity-ldap.secret" 600
	assert_mode "A2 staging: radius 0600 antes do restore" \
	    "${_l7_stage}/identity-radius.secret" 600
	assert_mode "A2 staging: dc 0600 antes do restore" \
	    "${_l7_stage}/identity-dc.secret" 600
	assert_mode "A2 staging: ca.key 0600 antes do restore" \
	    "${_l7_stage}/mitm/ca.key" 600
	_st_mode=$(file_mode "${_l7_stage}" 2>/dev/null || echo missing)
	_st_n=$(printf '%s' "$_st_mode" | sed 's/^0*//')
	if [ "$_st_n" = "700" ]; then
		printf "PASS: A2 staging dir 0700\n"
	else
		printf "FAIL: A2 staging dir mode %s want 700\n" "$_st_mode"
		fail=1
	fi
	layer7_deinstall_secure_rm "${_l7_stage}"
else
	printf "FAIL: A2 stage_secrets (backup OK deveria passar)\n"
	fail=1
fi

# keep-license: apaga json + etc/layer7; mantém .lic e /var/db.
setup_tree
run_production 0 0 1
assert_missing "keep-license: json apagado" "${ETC}/layer7.json"
assert_exists "keep-license: lic preservado" "${ETC}/layer7.lic"
assert_missing "keep-license: mitm wipe" "${ETC}/layer7/mitm"
assert_missing "keep-license: ldap wipe" "${ETC}/layer7/identity-ldap.secret"
assert_exists "keep-license: check-in preservado" "${VAR_DB}/layer7-checkin.json"
assert_exists "keep-license: clock-mark preservado" \
    "${VAR_DB}/layer7/clock-mark.json"

# uninstall real: apaga json/.lic + etc/layer7 + /var/db leftovers.
setup_tree
run_production 0 0 0
assert_missing "uninstall: json apagado" "${ETC}/layer7.json"
assert_missing "uninstall: lic apagado" "${ETC}/layer7.lic"
assert_missing "uninstall: mitm wipe" "${ETC}/layer7/mitm"
assert_missing "uninstall: check-in apagado" "${VAR_DB}/layer7-checkin.json"
assert_missing "uninstall: clock-mark apagado" \
    "${VAR_DB}/layer7/clock-mark.json"
assert_missing "uninstall: content-subscription apagado" \
    "${VAR_DB}/layer7/content-subscription.json"

# uninstall.sh (mesmo lifecycle) — keep-config + falha de backup.
LAYER7_DEINSTALL_LIB=1
# shellcheck disable=SC1090
. "$UNINSTALL"
unset LAYER7_DEINSTALL_LIB
setup_tree
touch "${VAR_DB}/layer7/deinstall-preserve"
_keep_config=1
_keep_license=0
_is_upgrade=0
run_production 0 1 0
assert_exists "uninstall.sh keep-config: mitm intacto se backup falha" \
    "${ETC}/layer7/mitm/ca.key"
assert_mode "uninstall.sh keep-config: ldap 0600 apos falha" \
    "${ETC}/layer7/identity-ldap.secret" 600

if [ "$fail" -ne 0 ]; then
	printf "RESULT: FAIL\n"
	exit 1
fi
printf "RESULT: PASS\n"
exit 0
