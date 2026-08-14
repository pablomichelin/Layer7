#!/bin/sh
# F5 minima — runner local (executa sem appliance).
#
# Faz:
#   1. Compila e corre tests/functional/test_allowlist.c.
#   2. Compila e corre tests/functional/test_config_parse.c.
#   3. Compila e corre tests/functional/test_policy_decide.c (Caminho B / E1).
#   4. Testa rotacao limitada e ingestao de relatorios atraves da rotacao.
#   5. Verifica sintaxe PHP dos ficheiros do pacote (php -l).
#   6. Verifica sintaxe shell dos scripts do pacote (sh -n).
#
# Uso:  sh tests/run-local.sh

set -u
RC=0
ROOT=$(cd "$(dirname "$0")/.." && pwd)
cd "$ROOT" || exit 1

step() { printf "\n== %s ==\n" "$1"; }
pass() { printf "PASS: %s\n" "$1"; }
fail() { printf "FAIL: %s\n" "$1"; RC=1; }

CC_BIN=${CC:-cc}

step "Unit: allowlist"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_allowlist \
    tests/functional/test_allowlist.c src/layer7d/allowlist.c 2>/tmp/test_allowlist.cc.err; then
	if /tmp/test_allowlist; then
		pass "test_allowlist"
	else
		fail "test_allowlist runtime"
	fi
else
	cat /tmp/test_allowlist.cc.err
	fail "test_allowlist compile"
fi

step "Unit: identity_map (20.12 / ADR-0027§4.3 + ADR-0028)"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_identity_map \
    tests/functional/test_identity_map.c src/layer7d/identity_map.c -lpthread \
    2>/tmp/test_identity_map.cc.err; then
	if /tmp/test_identity_map; then
		pass "test_identity_map"
	else
		fail "test_identity_map runtime"
	fi
else
	cat /tmp/test_identity_map.cc.err
	fail "test_identity_map compile"
fi

step "Unit: identity_ldap cache/fail-mode (20.17)"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d -DHAVE_OPENLDAP=0 \
    -o /tmp/test_identity_ldap \
    tests/functional/test_identity_ldap.c \
    src/layer7d/identity_ldap.c src/layer7d/identity_map.c -lpthread \
    2>/tmp/test_identity_ldap.cc.err; then
	if /tmp/test_identity_ldap; then
		pass "test_identity_ldap"
	else
		fail "test_identity_ldap runtime"
	fi
else
	cat /tmp/test_identity_ldap.cc.err
	fail "test_identity_ldap compile"
fi

step "Unit: identity_radius accounting (20.19)"
CRYPTO_FLAGS=""
if [ -f /usr/local/opt/openssl/lib/libcrypto.dylib ] || [ -f /opt/homebrew/opt/openssl/lib/libcrypto.dylib ]; then
	if [ -d /opt/homebrew/opt/openssl ]; then
		CRYPTO_FLAGS="-I/opt/homebrew/opt/openssl/include -L/opt/homebrew/opt/openssl/lib -lcrypto"
	else
		CRYPTO_FLAGS="-I/usr/local/opt/openssl/include -L/usr/local/opt/openssl/lib -lcrypto"
	fi
elif pkg-config --exists libcrypto 2>/dev/null; then
	CRYPTO_FLAGS="$(pkg-config --cflags --libs libcrypto)"
else
	CRYPTO_FLAGS="-lcrypto"
fi
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_identity_radius \
    tests/functional/test_identity_radius.c \
    src/layer7d/identity_radius.c src/layer7d/identity_map.c \
    -lpthread $CRYPTO_FLAGS \
    2>/tmp/test_identity_radius.cc.err; then
	if /tmp/test_identity_radius; then
		pass "test_identity_radius"
	else
		fail "test_identity_radius runtime"
	fi
else
	cat /tmp/test_identity_radius.cc.err
	fail "test_identity_radius compile"
fi

step "Unit: identity_dc agent push (20.20)"
CRYPTO_FLAGS=""
if [ -d /opt/homebrew/opt/openssl ]; then
	CRYPTO_FLAGS="-I/opt/homebrew/opt/openssl/include -L/opt/homebrew/opt/openssl/lib -lssl -lcrypto"
elif [ -d /usr/local/opt/openssl ]; then
	CRYPTO_FLAGS="-I/usr/local/opt/openssl/include -L/usr/local/opt/openssl/lib -lssl -lcrypto"
elif pkg-config --exists libssl 2>/dev/null; then
	CRYPTO_FLAGS="$(pkg-config --cflags --libs libssl libcrypto)"
else
	CRYPTO_FLAGS="-lssl -lcrypto"
fi
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_identity_dc \
    tests/functional/test_identity_dc.c \
    src/layer7d/identity_dc.c src/layer7d/identity_map.c \
    -lpthread $CRYPTO_FLAGS \
    2>/tmp/test_identity_dc.cc.err; then
	if /tmp/test_identity_dc; then
		pass "test_identity_dc"
	else
		fail "test_identity_dc runtime"
	fi
else
	cat /tmp/test_identity_dc.cc.err
	fail "test_identity_dc compile"
fi

step "Unit: config_parse (sni_inspection / A3)"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_config_parse \
    tests/functional/test_config_parse.c src/layer7d/config_parse.c \
    src/layer7d/policy.c src/layer7d/enforce.c src/layer7d/identity_map.c \
    -lpthread 2>/tmp/test_config_parse.cc.err; then
	if /tmp/test_config_parse; then
		pass "test_config_parse"
	else
		fail "test_config_parse runtime"
	fi
else
	cat /tmp/test_config_parse.cc.err
	fail "test_config_parse compile"
fi

step "Unit: capture flow key bidireccional"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_capture_flow_key \
    tests/functional/test_capture_flow_key.c \
    2>/tmp/test_capture_flow_key.cc.err; then
	if /tmp/test_capture_flow_key; then
		pass "test_capture_flow_key"
	else
		fail "test_capture_flow_key runtime"
	fi
else
	cat /tmp/test_capture_flow_key.cc.err
	fail "test_capture_flow_key compile"
fi

step "Unit: DNS A/AAAA wire (dns_observe)"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_dns_aaaa_wire \
    tests/functional/test_dns_aaaa_wire.c \
    2>/tmp/test_dns_aaaa_wire.cc.err; then
	if /tmp/test_dns_aaaa_wire; then
		pass "test_dns_aaaa_wire"
	else
		fail "test_dns_aaaa_wire runtime"
	fi
else
	cat /tmp/test_dns_aaaa_wire.cc.err
	fail "test_dns_aaaa_wire compile"
fi

step "Unit: DNS corr / allowlist (BG-104)"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_dns_corr \
    tests/functional/test_dns_corr.c \
    2>/tmp/test_dns_corr.cc.err; then
	if /tmp/test_dns_corr; then
		pass "test_dns_corr"
	else
		fail "test_dns_corr runtime"
	fi
else
	cat /tmp/test_dns_corr.cc.err
	fail "test_dns_corr compile"
fi

step "Unit: BL limits / truncate (BG-100)"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_bl_limits \
    tests/functional/test_bl_limits.c src/layer7d/blacklist.c \
    2>/tmp/test_bl_limits.cc.err; then
	if /tmp/test_bl_limits; then
		pass "test_bl_limits"
	else
		fail "test_bl_limits runtime"
	fi
else
	cat /tmp/test_bl_limits.cc.err
	fail "test_bl_limits compile"
fi

step "Unit: log_store (rotacao limitada)"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_log_store \
    tests/functional/test_log_store.c src/layer7d/log_store.c \
    2>/tmp/test_log_store.cc.err; then
	if /tmp/test_log_store; then
		pass "test_log_store"
	else
		fail "test_log_store runtime"
	fi
else
	cat /tmp/test_log_store.cc.err
	fail "test_log_store compile"
fi

step "Unit: policy_decide (Caminho B / E1)"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_policy_decide \
    tests/functional/test_policy_decide.c \
    src/layer7d/policy.c src/layer7d/enforce.c src/layer7d/identity_map.c \
    -lpthread 2>/tmp/test_policy_decide.cc.err; then
	if /tmp/test_policy_decide; then
		pass "test_policy_decide"
	else
		fail "test_policy_decide runtime"
	fi
else
	cat /tmp/test_policy_decide.cc.err
	fail "test_policy_decide compile"
fi

step "Unit: enforce_scoped (Caminho B / E3)"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_enforce_scoped \
    tests/functional/test_enforce_scoped.c src/layer7d/enforce.c \
    2>/tmp/test_enforce_scoped.cc.err; then
	if /tmp/test_enforce_scoped; then
		pass "test_enforce_scoped"
	else
		fail "test_enforce_scoped runtime"
	fi
else
	cat /tmp/test_enforce_scoped.cc.err
	fail "test_enforce_scoped compile"
fi

step "Unit: profiles.json catalogo"
if sh tests/unit/test_profiles_json.sh; then
	pass "test_profiles_json"
else
	fail "test_profiles_json"
fi

step "Unit: rc.d pidfile sem newline"
if sh tests/unit/test_rc_pidfile.sh; then
	pass "test_rc_pidfile"
else
	fail "test_rc_pidfile"
fi

step "Unit: sinkhole local nao reclassifica portal"
if sh tests/unit/test_sinkhole_local_guard.sh; then
	pass "test_sinkhole_local_guard"
else
	fail "test_sinkhole_local_guard"
fi

step "Unit: flush PF coverage (lifecycle)"
if sh tests/unit/test_flush_coverage.sh; then
	pass "test_flush_coverage"
else
	fail "test_flush_coverage"
fi

step "Unit: pkg-deinstall lifecycle (P1-6/7/8 + P2-12 + A1/A2/M2)"
if sh tests/unit/test_pkg_deinstall_lifecycle.sh; then
	pass "test_pkg_deinstall_lifecycle"
else
	fail "test_pkg_deinstall_lifecycle"
fi

step "Unit: check-in config + enforce_ready (P1-5)"
CRYPTO_FLAGS=""
if [ -d /opt/homebrew/opt/openssl ]; then
	CRYPTO_FLAGS="-I/opt/homebrew/opt/openssl/include -L/opt/homebrew/opt/openssl/lib -lssl -lcrypto"
elif [ -d /usr/local/opt/openssl ]; then
	CRYPTO_FLAGS="-I/usr/local/opt/openssl/include -L/usr/local/opt/openssl/lib -lssl -lcrypto"
elif pkg-config --exists libssl 2>/dev/null; then
	CRYPTO_FLAGS="$(pkg-config --cflags --libs libssl libcrypto)"
else
	CRYPTO_FLAGS="-lssl -lcrypto"
fi
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_checkin_config_enabled \
    tests/functional/test_checkin_config_enabled.c \
    src/layer7d/license.c src/layer7d/features.c \
    $CRYPTO_FLAGS \
    2>/tmp/test_checkin_config_enabled.cc.err; then
	if /tmp/test_checkin_config_enabled; then
		pass "test_checkin_config_enabled"
	else
		fail "test_checkin_config_enabled runtime"
	fi
else
	cat /tmp/test_checkin_config_enabled.cc.err
	fail "test_checkin_config_enabled compile"
fi
step "Unit: check-in persist atomico + SKU + 0600 (P2-7/P2-8/P2-10)"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d -DL7_TEST_CHECKIN_STATE \
    -o /tmp/test_checkin_state_persist \
    tests/functional/test_checkin_state_persist.c \
    src/layer7d/license.c src/layer7d/features.c \
    $CRYPTO_FLAGS \
    2>/tmp/test_checkin_state_persist.cc.err; then
	if /tmp/test_checkin_state_persist; then
		pass "test_checkin_state_persist"
	else
		fail "test_checkin_state_persist runtime"
	fi
else
	cat /tmp/test_checkin_state_persist.cc.err
	fail "test_checkin_state_persist compile"
fi
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_license_enforce_gate \
    tests/functional/test_license_enforce_gate.c \
    src/layer7d/license_enforce_gate.c \
    2>/tmp/test_license_enforce_gate.cc.err; then
	if /tmp/test_license_enforce_gate; then
		pass "test_license_enforce_gate"
	else
		fail "test_license_enforce_gate runtime"
	fi
else
	cat /tmp/test_license_enforce_gate.cc.err
	fail "test_license_enforce_gate compile"
fi

step "Unit: bl_src_match (except_ips)"
if "$CC_BIN" -Wall -Wextra -O2 -I src/layer7d \
    -o /tmp/test_bl_src_match \
    tests/functional/test_bl_src_match.c src/layer7d/bl_config.c \
    2>/tmp/test_bl_src_match.cc.err; then
	if /tmp/test_bl_src_match; then
		pass "test_bl_src_match"
	else
		fail "test_bl_src_match runtime"
	fi
else
	cat /tmp/test_bl_src_match.cc.err
	fail "test_bl_src_match compile"
fi

step "Simulacao: scoped_hybrid PF rules (Caminho B / E2)"
PHP_BIN_E2=$(command -v php 2>/dev/null || true)
if [ -z "$PHP_BIN_E2" ]; then
	printf "SKIP: php nao instalado, saltando simulacao scoped PF\n"
else
	if "$PHP_BIN_E2" tests/functional/test_scoped_pf_inc.php; then
		pass "test_scoped_pf_inc"
	else
		fail "test_scoped_pf_inc"
	fi
	if "$PHP_BIN_E2" tests/functional/test_localnets_inc.php; then
		pass "test_localnets_inc"
	else
		fail "test_localnets_inc"
	fi
	if "$PHP_BIN_E2" tests/functional/test_ipv6_gui_inc.php; then
		pass "test_ipv6_gui_inc"
	else
		fail "test_ipv6_gui_inc"
	fi
	if "$PHP_BIN_E2" tests/functional/test_interface_normalization.php; then
		pass "test_interface_normalization"
	else
		fail "test_interface_normalization"
	fi
	if "$PHP_BIN_E2" tests/functional/test_identity_ldap_config.php; then
		pass "test_identity_ldap_config"
	else
		fail "test_identity_ldap_config"
	fi
	if "$PHP_BIN_E2" tests/functional/test_identity_radius_config.php; then
		pass "test_identity_radius_config"
	else
		fail "test_identity_radius_config"
	fi
	if "$PHP_BIN_E2" tests/functional/test_logging_reports.php; then
		pass "test_logging_reports"
	else
		fail "test_logging_reports"
	fi
	if "$PHP_BIN_E2" tests/functional/test_vip_exception.php; then
		pass "test_vip_exception"
	else
		fail "test_vip_exception"
	fi
	if "$PHP_BIN_E2" tests/functional/test_vip_dns_exempt.php; then
		pass "test_vip_dns_exempt"
	else
		fail "test_vip_dns_exempt"
	fi
	if "$PHP_BIN_E2" tests/functional/test_dns_force_inet6.php; then
		pass "test_dns_force_inet6"
	else
		fail "test_dns_force_inet6"
	fi
	if "$PHP_BIN_E2" tests/functional/test_ctrl_exec_timeout.php; then
		pass "test_ctrl_exec_timeout"
	else
		fail "test_ctrl_exec_timeout"
	fi
	if "$PHP_BIN_E2" package/pfSense-pkg-layer7/tests/test_mitm_regress.php; then
		pass "test_mitm_regress (package)"
	else
		fail "test_mitm_regress (package)"
	fi
	if "$PHP_BIN_E2" tests/functional/test_check_in_default_30.14.php; then
		pass "test_check_in_default_30.14"
	else
		fail "test_check_in_default_30.14"
	fi
	if "$PHP_BIN_E2" tests/functional/test_license_revoke_state.php; then
		pass "test_license_revoke_state"
	else
		fail "test_license_revoke_state"
	fi
	if "$PHP_BIN_E2" tests/functional/test_entitlements_gui.php; then
		pass "test_entitlements_gui"
	else
		fail "test_entitlements_gui"
	fi
	if "$PHP_BIN_E2" tests/functional/test_fingerprint_gui_daemon.php; then
		pass "test_fingerprint_gui_daemon"
	else
		fail "test_fingerprint_gui_daemon"
	fi
fi

step "Regress: layer7-tlsproxy (junto ao codigo)"
if [ -f src/layer7-tlsproxy/test-regress.sh ]; then
	if command -v cc >/dev/null 2>&1 && command -v openssl >/dev/null 2>&1; then
		if make -C src/layer7-tlsproxy test-regress; then
			pass "tlsproxy test-regress"
		else
			fail "tlsproxy test-regress"
		fi
	else
		printf "SKIP: tlsproxy test-regress (cc/openssl)\n"
	fi
else
	fail "src/layer7-tlsproxy/test-regress.sh em falta"
fi

step "Lint: PHP do pacote (php -l)"
PHP_BIN=$(command -v php 2>/dev/null || true)
if [ -z "$PHP_BIN" ]; then
	printf "SKIP: php nao instalado, saltando syntax check\n"
else
	N_OK=0; N_FAIL=0
	for f in $(find package/pfSense-pkg-layer7/files -name '*.php' -o -name '*.inc' -o -name '*.priv.inc'); do
		if "$PHP_BIN" -l "$f" >/dev/null 2>&1; then
			N_OK=$((N_OK + 1))
		else
			"$PHP_BIN" -l "$f" || true
			N_FAIL=$((N_FAIL + 1))
		fi
	done
	if [ "$N_FAIL" -eq 0 ]; then
		pass "PHP syntax ($N_OK ficheiros OK)"
	else
		fail "PHP syntax ($N_FAIL erro(s) em $((N_OK + N_FAIL)) ficheiros)"
	fi
fi

step "Lint: sh do pacote (sh -n)"
N_OK=0; N_FAIL=0
for f in package/pfSense-pkg-layer7/files/usr/local/etc/rc.d/layer7d \
    package/pfSense-pkg-layer7/files/usr/local/libexec/layer7-pfctl \
    package/pfSense-pkg-layer7/files/usr/local/libexec/layer7-unbound-anti-doh \
    package/pfSense-pkg-layer7/files/usr/local/etc/layer7/*.sh \
    package/pfSense-pkg-layer7/files/pkg-deinstall.in \
    package/pfSense-pkg-layer7/files/pkg-install.in \
    scripts/diagnose-layer7-appliance.sh \
    scripts/release/uninstall.sh \
    tests/unit/test_rc_pidfile.sh \
    tests/unit/test_flush_coverage.sh \
    tests/unit/test_pkg_deinstall_lifecycle.sh \
    tests/lab/smoke-monitor-mode.sh \
    tests/lab/smoke-caminho-a.sh \
    tests/run-local.sh; do
	[ -f "$f" ] || continue
	if /bin/sh -n "$f" 2>/dev/null; then
		N_OK=$((N_OK + 1))
	else
		/bin/sh -n "$f"
		N_FAIL=$((N_FAIL + 1))
	fi
done
if [ "$N_FAIL" -eq 0 ]; then
	pass "sh syntax ($N_OK scripts OK)"
else
	fail "sh syntax ($N_FAIL erro(s) em $((N_OK + N_FAIL)) scripts)"
fi

step "Resumo"
if [ "$RC" -eq 0 ]; then
	printf "ALL LOCAL TESTS PASSED\n"
else
	printf "LOCAL TESTS FAILED (rc=%d)\n" "$RC"
fi
exit "$RC"
